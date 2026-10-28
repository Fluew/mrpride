<script setup lang="ts">
import {
    AlertCircle,
    CheckCircle2,
    Eye,
    FileSpreadsheet,
    FileText,
    Image as ImageIcon,
    Loader2,
    Plus,
    RefreshCw,
    Trash2,
    UploadCloud,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import GlideSelect from '@/components/vue-bits/GlideSelect.vue';

/**
 * Material list upload for the public quotation form.
 *
 * Choose file → Preview → Upload & extract → (map columns) → Review & edit lines → Prices → Confirm.
 * The server reads the file (Excel/CSV directly, PDF text, OCR for images/scans) and suggests
 * catalog products; the client edits and confirms. Only selected, valid lines are submitted, and
 * the server recalculates every price and total.
 *
 * The material list is required on the form: confirmed lines, or at least the uploaded file (when
 * it cannot be read automatically, staff read it). `error` shows the form's message for this section.
 */

export interface Suggestion {
    product_id: number;
    sku: string;
    name: string;
    brand: string | null;
    unit: string | null;
    price: number | null;
    confidence: number;
    level: 'high' | 'medium' | 'low';
    reason: string;
}

export interface MaterialRow {
    key: number;
    selected: boolean;
    description: string;
    brand: string;
    specs: string;
    quantity: string;
    unit: string;
    remarks: string;
    product_id: number | null;
    suggestions: Suggestion[];
    warnings: string[];
    needsReview: boolean;
    line: number | null;
}

export interface MaterialState {
    rows: MaterialRow[];
    token: string | null;
    confirmed: boolean;
}

const state = defineModel<MaterialState>({ required: true });
const props = defineProps<{ error?: string | null }>();

// How the client adds the list. Both can be combined: typed lines are kept when a file is read.
const mode = ref<'upload' | 'manual'>('upload');
const modeOptions = [
    { value: 'upload', label: 'Upload a file', tag: 'PDF · Excel · CSV · Image' },
    { value: 'manual', label: 'Enter manually' },
];
function chooseMode(value: string) {
    mode.value = value as 'upload' | 'manual';
    if (value === 'manual' && state.value.rows.length === 0) addManualRow();
}

// The file was stored but its lines could not be read: it can still be sent as the material list.
const fileOnly = computed(() => !!file.value && !!state.value.token && state.value.rows.length === 0 && status.value !== 'uploading' && status.value !== 'processing');

const MAX_BYTES = 10 * 1024 * 1024;
const EXTENSIONS = ['pdf', 'xlsx', 'xls', 'csv', 'jpg', 'jpeg', 'png'];
const FIELDS = [
    { key: 'description', label: 'Item description', required: true },
    { key: 'quantity', label: 'Quantity', required: true },
    { key: 'unit', label: 'Unit' },
    { key: 'brand', label: 'Brand' },
    { key: 'specs', label: 'Specifications' },
    { key: 'remarks', label: 'Remarks' },
    { key: 'item_code', label: 'Item code' },
] as const;

const file = ref<File | null>(null);
const fileError = ref<string | null>(null);
const previewUrl = ref<string | null>(null);
const previewText = ref<string | null>(null);
const showPreview = ref(false);

const status = ref<'idle' | 'uploading' | 'processing' | 'done' | 'error' | 'mapping'>('idle');
const progress = ref(0);
const serverError = ref<string | null>(null);
const canRetry = ref(false);

const result = ref<any>(null);
const mapping = ref<Record<string, number | null>>({});
const headerRow = ref(true);
const unparsed = ref<string[]>([]);
let nextKey = 1;

const fileInput = ref<HTMLInputElement>();

// ------------------------------------------------------------------ choosing a file

function extOf(name: string) {
    return name.split('.').pop()?.toLowerCase() ?? '';
}

const fileKind = computed(() => {
    const ext = file.value ? extOf(file.value.name) : '';
    if (['xlsx', 'xls', 'csv'].includes(ext)) return { label: ext === 'csv' ? 'CSV' : 'Excel', icon: FileSpreadsheet };
    if (['jpg', 'jpeg', 'png'].includes(ext)) return { label: 'Image', icon: ImageIcon };
    return { label: 'PDF', icon: FileText };
});

function formatSize(bytes: number) {
    return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function clearPreview() {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = null;
    previewText.value = null;
    showPreview.value = false;
}

function onFileChosen(event: Event) {
    const target = event.target as HTMLInputElement;
    const chosen = target.files?.[0] ?? null;
    target.value = '';
    fileError.value = null;
    serverError.value = null;
    clearPreview();

    if (!chosen) return;
    if (!EXTENSIONS.includes(extOf(chosen.name))) {
        fileError.value = 'This file type is not supported. Please choose a PDF, Excel (.xlsx/.xls), CSV, JPG or PNG file.';
        return;
    }
    if (chosen.size === 0) {
        fileError.value = 'This file is empty.';
        return;
    }
    if (chosen.size > MAX_BYTES) {
        fileError.value = `This file is ${formatSize(chosen.size)}. The limit is 10 MB.`;
        return;
    }
    file.value = chosen;
    status.value = 'idle';
    // A newly chosen file replaces the previous upload only once it is uploaded.
    state.value.token = null;
}

function removeFile() {
    file.value = null;
    fileError.value = null;
    serverError.value = null;
    status.value = 'idle';
    result.value = null;
    unparsed.value = [];
    state.value.token = null;
    clearPreview();
}

async function preview() {
    if (!file.value) return;
    clearPreview();
    const ext = extOf(file.value.name);
    if (['pdf', 'jpg', 'jpeg', 'png'].includes(ext)) {
        previewUrl.value = URL.createObjectURL(file.value);
    } else if (ext === 'csv') {
        previewText.value = (await file.value.slice(0, 4000).text()) || '(empty)';
    } else {
        previewText.value = 'Excel files can’t be previewed in the browser. Click “Upload & extract” to see the rows we read from it.';
    }
    showPreview.value = true;
}

onBeforeUnmount(clearPreview);

// ------------------------------------------------------------------ talking to the server

function csrfToken() {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

function send(url: string, body: FormData | object): Promise<any> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', url);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.timeout = 300000;
        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) progress.value = Math.round((e.loaded / e.total) * 100);
        };
        xhr.upload.onload = () => (status.value = 'processing');
        xhr.onload = () => {
            let data: any = null;
            try {
                data = JSON.parse(xhr.responseText);
            } catch {
                /* not JSON */
            }
            if (xhr.status >= 200 && xhr.status < 300) return resolve(data);
            const firstError = data?.errors ? Object.values(data.errors).flat()[0] : null;
            reject({
                message:
                    (firstError as string) ||
                    data?.message ||
                    (xhr.status === 413
                        ? 'The file is larger than the server allows (10 MB).'
                        : xhr.status === 429
                          ? 'Too many uploads. Please wait a minute and try again.'
                          : 'Something went wrong while reading this file. Please try again, or type your list below.'),
                canRetry: data?.can_retry ?? true,
                token: data?.token ?? null,
            });
        };
        xhr.onerror = () => reject({ message: 'Network error. Please check your connection and try again.', canRetry: true });
        xhr.ontimeout = () => reject({ message: 'Reading the file took too long. Please try a smaller or clearer file, or type your list below.', canRetry: true });

        if (body instanceof FormData) {
            xhr.send(body);
        } else {
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify(body));
        }
    });
}

async function uploadAndExtract() {
    if (!file.value || status.value === 'uploading' || status.value === 'processing') return;
    serverError.value = null;
    progress.value = 0;
    status.value = 'uploading';
    const data = new FormData();
    data.append('file', file.value);

    try {
        applyResult(await send('/quotation/materials/extract', data));
    } catch (e: any) {
        status.value = 'error';
        serverError.value = e.message;
        canRetry.value = e.canRetry;
        // A valid file that could not be read is kept on the server and can still be sent.
        state.value.token = e.token ?? null;
    }
}

async function applyMapping() {
    if (!result.value?.token) return;
    serverError.value = null;
    status.value = 'processing';
    const chosen = Object.fromEntries(Object.entries(mapping.value).filter(([, v]) => v !== null && v !== undefined));
    try {
        applyResult(
            await send('/quotation/materials/remap', {
                token: result.value.token,
                mapping: chosen,
                header_index: headerRow.value ? (result.value.header_index ?? 0) : null,
            }),
        );
    } catch (e: any) {
        status.value = 'mapping';
        serverError.value = e.message;
    }
}

function applyResult(data: any) {
    result.value = data;
    state.value.token = data.token;
    state.value.confirmed = false;
    unparsed.value = data.unparsed_lines ?? [];

    // Pre-fill the mapping form with whatever was recognised.
    const m: Record<string, number | null> = {};
    for (const f of FIELDS) m[f.key] = data.mapping?.[f.key] ?? null;
    mapping.value = m;

    if (!data.confident && data.structured && (data.items ?? []).length === 0) {
        status.value = 'mapping';
        return;
    }

    // Replace earlier extracted rows; keep rows the client typed by hand.
    const manual = state.value.rows.filter((r) => r.line === null);
    state.value.rows = [...(data.items ?? []).map(toRow), ...manual];
    status.value = 'done';
    if (data.message) serverError.value = data.message;
}

function toRow(item: any): MaterialRow {
    return {
        key: nextKey++,
        selected: (item.errors ?? []).length === 0,
        description: item.description ?? '',
        brand: item.brand ?? '',
        specs: item.specs ?? '',
        quantity: item.quantity !== null && item.quantity !== undefined ? String(item.quantity) : item.quantity_raw ?? '',
        unit: item.unit ?? '',
        remarks: item.remarks ?? '',
        // Only an exact product-code match is pre-selected; other suggestions are the client's choice.
        product_id: item.match?.product_id ?? null,
        suggestions: item.suggestions ?? [],
        warnings: item.warnings ?? [],
        needsReview: !!item.needs_review,
        line: item.line ?? null,
    };
}

// ------------------------------------------------------------------ review table

function addManualRow(description = '') {
    state.value.rows.push({
        key: nextKey++,
        selected: true,
        description,
        brand: '',
        specs: '',
        quantity: '',
        unit: '',
        remarks: '',
        product_id: null,
        suggestions: [],
        warnings: [],
        needsReview: description !== '',
        line: null,
    });
    state.value.confirmed = false;
}

function addUnparsed(index: number) {
    addManualRow(unparsed.value[index].replace(/\s*\|\s*/g, ' ').slice(0, 500));
    unparsed.value.splice(index, 1);
}

function removeRow(key: number) {
    state.value.rows = state.value.rows.filter((r) => r.key !== key);
    state.value.confirmed = false;
}

function setAll(selected: boolean) {
    state.value.rows.forEach((r) => (r.selected = selected));
    state.value.confirmed = false;
}

/** The same rules the server applies on submit. */
function rowErrors(row: MaterialRow): string[] {
    const errors: string[] = [];
    if (row.description.trim() === '') errors.push('Description is required.');
    if (row.description.length > 500) errors.push('Description is too long (max 500 characters).');
    const q = row.quantity.trim().replace(/,/g, '');
    if (q === '') errors.push('Quantity is required.');
    else if (!/^\d+$/.test(q)) errors.push('Quantity must be a whole number.');
    else if (Number(q) < 1) errors.push('Quantity must be at least 1.');
    else if (Number(q) > 1000000) errors.push('Quantity is too large.');
    return errors;
}

function chosenProduct(row: MaterialRow): Suggestion | undefined {
    return row.suggestions.find((s) => s.product_id === row.product_id);
}

function unitPrice(row: MaterialRow): number | null {
    return chosenProduct(row)?.price ?? null;
}

function lineTotal(row: MaterialRow): number | null {
    const price = unitPrice(row);
    return price !== null && rowErrors(row).length === 0 ? price * Number(row.quantity.replace(/,/g, '')) : null;
}

const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
const showPrices = computed(() => result.value?.show_prices ?? true);
const taxRate = computed(() => result.value?.tax_rate ?? 12);

const selectedRows = computed(() => state.value.rows.filter((r) => r.selected));
const invalidSelected = computed(() => selectedRows.value.filter((r) => rowErrors(r).length > 0));
const estimatedSubtotal = computed(() => selectedRows.value.reduce((sum, r) => sum + (lineTotal(r) ?? 0), 0));
const unpricedCount = computed(() => selectedRows.value.filter((r) => lineTotal(r) === null).length);
const estimatedTax = computed(() => Math.round(estimatedSubtotal.value * taxRate.value) / 100);

const methodLabel = computed(
    () =>
        ({
            spreadsheet: `Read from Excel${result.value?.sheet ? ` (sheet “${result.value.sheet}”)` : ''}`,
            csv: 'Read from CSV',
            'pdf-text': 'Read from the PDF text',
            'pdf-ocr': 'Read from a scanned PDF with text recognition (OCR)',
            ocr: 'Read from the image with text recognition (OCR)',
        })[result.value?.method as string] ?? '',
);

// A file was chosen but not uploaded yet: the form asks the client to upload it first.
const hasUnsentFile = computed(() => !!file.value && !state.value.token && status.value !== 'uploading' && status.value !== 'processing');

defineExpose({ invalidSelected, selectedRows, hasUnsentFile });
</script>

<template>
    <div data-test="material-list-uploader">
        <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <p id="material-list-label" class="field-label mb-0 font-semibold text-gray-900">
                    Material List <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">(required)</span>
                </p>
                <p class="mt-0.5 text-xs text-gray-500">
                    Upload your list of materials (PDF, Excel, CSV, JPG, or PNG, up to 10 MB). We'll extract the items and
                    quantities for you to review, or you can enter the list manually.
                </p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-[3px]">
                <GlideSelect
                    :options="modeOptions"
                    :value="mode"
                    ariaLabel="How to add your material list"
                    :showTags="false"
                    accentColor="#4b5320"
                    surfaceColor="#ffffff"
                    highlightColor="#f3f4f6"
                    textColor="#374151"
                    size="md"
                    :radius="12"
                    :menuWidth="220"
                    align="right"
                    @change="chooseMode"
                />
            </div>
        </div>
        <p v-if="props.error" id="material-list-error" class="mb-2 text-xs font-medium text-red-600" role="alert">{{ props.error }}</p>

        <!-- 1. Choose file -->
        <div v-if="!file && mode === 'upload'" class="flex flex-col gap-2 sm:flex-row" :aria-describedby="props.error ? 'material-list-error' : undefined">
            <label
                class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-300 bg-white px-4 py-5 text-sm font-semibold text-gray-600 transition-colors hover:border-[#4b5320] hover:text-[#4b5320]"
            >
                <UploadCloud class="size-5" />
                <span>Choose a file</span>
                <input
                    ref="fileInput"
                    type="file"
                    class="sr-only"
                    accept=".pdf,.xlsx,.xls,.csv,.jpg,.jpeg,.png"
                    data-test="material-file-input"
                    @change="onFileChosen"
                />
            </label>
            <button
                type="button"
                class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                @click="chooseMode('manual')"
            >
                Enter manually instead
            </button>
        </div>
        <p v-if="fileError" class="mt-1.5 text-xs font-medium text-red-600" role="alert">{{ fileError }}</p>
        <button
            v-if="mode === 'manual' && state.rows.length === 0"
            type="button"
            class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50"
            @click="addManualRow()"
        >
            <Plus class="size-3.5" /> Add a material line
        </button>

        <!-- 2. Selected file, preview, upload -->
        <div v-if="file" class="rounded-xl border border-gray-200 bg-white p-3">
            <div class="flex flex-wrap items-center gap-3">
                <component :is="fileKind.icon" class="size-8 shrink-0 text-[#4b5320]" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-gray-900" :title="file.name">{{ file.name }}</p>
                    <p class="text-xs text-gray-500">{{ fileKind.label }} · {{ formatSize(file.size) }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" @click="preview">
                        <Eye class="size-3.5" /> Preview
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg bg-[#4b5320] px-2.5 py-1.5 text-xs font-semibold text-white hover:opacity-90 disabled:opacity-50"
                        :disabled="status === 'uploading' || status === 'processing'"
                        data-test="material-upload-button"
                        @click="uploadAndExtract"
                    >
                        <UploadCloud class="size-3.5" /> {{ status === 'done' || status === 'mapping' ? 'Extract again' : 'Upload & extract' }}
                    </button>
                    <label class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                        <RefreshCw class="size-3.5" /> Replace
                        <input type="file" class="sr-only" accept=".pdf,.xlsx,.xls,.csv,.jpg,.jpeg,.png" @change="onFileChosen" />
                    </label>
                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50" @click="removeFile">
                        <Trash2 class="size-3.5" /> Remove
                    </button>
                </div>
            </div>

            <!-- Preview -->
            <div v-if="showPreview" class="relative mt-3 rounded-lg border border-gray-200 bg-gray-50 p-2">
                <button type="button" class="absolute top-2 right-2 rounded-md bg-white p-1 text-gray-500 shadow-2xs hover:text-gray-900" aria-label="Close preview" @click="showPreview = false">
                    <X class="size-4" />
                </button>
                <img v-if="previewUrl && fileKind.label === 'Image'" :src="previewUrl" alt="Preview of the selected file" class="mx-auto max-h-96 rounded" />
                <iframe v-else-if="previewUrl" :src="previewUrl" title="PDF preview" class="h-96 w-full rounded bg-white"></iframe>
                <pre v-else class="max-h-64 overflow-auto pr-8 text-[11px] whitespace-pre-wrap text-gray-700">{{ previewText }}</pre>
            </div>

            <!-- Progress -->
            <div v-if="status === 'uploading' || status === 'processing'" class="mt-3" role="status" aria-live="polite">
                <div class="flex items-center gap-2 text-xs font-semibold text-gray-700">
                    <Loader2 class="size-4 animate-spin text-[#4b5320]" />
                    <span>{{ status === 'uploading' ? `Uploading… ${progress}%` : 'Reading your file… (scanned files and photos can take up to a minute)' }}</span>
                </div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full bg-[#4b5320] transition-colors" :class="status === 'processing' ? 'w-full animate-pulse' : ''" :style="status === 'uploading' ? { width: `${progress}%` } : {}"></div>
                </div>
            </div>
        </div>

        <!-- Errors with retry / manual entry -->
        <div v-if="serverError" class="mt-3 flex flex-col gap-2 rounded-xl border p-3 text-xs sm:flex-row sm:items-center" :class="status === 'done' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-red-200 bg-red-50 text-red-700'" role="alert">
            <AlertCircle class="size-4 shrink-0" />
            <span class="flex-1 font-medium">{{ serverError }}</span>
            <div class="flex gap-2">
                <button v-if="status === 'error' && canRetry && file" type="button" class="rounded-lg border border-current px-2.5 py-1 font-semibold" @click="uploadAndExtract">Try again</button>
                <button type="button" class="rounded-lg border border-current px-2.5 py-1 font-semibold" @click="addManualRow()">Enter manually</button>
            </div>
        </div>

        <p v-if="fileOnly" class="mt-2 text-xs text-gray-600" data-test="material-file-only">
            You can still submit: our team will read the items from your file. Or add them below with “Enter manually”.
        </p>

        <!-- Column mapping when headers weren't recognised -->
        <div v-if="status === 'mapping' && result" class="mt-3 rounded-xl border border-amber-200 bg-amber-50/60 p-3">
            <p class="text-xs font-semibold text-amber-900">Which columns hold your materials?</p>
            <p class="mt-0.5 text-[11px] text-amber-800">{{ result.warnings?.[0] ?? 'Please match the columns of your file.' }}</p>

            <div class="mt-2 overflow-x-auto rounded-lg border border-amber-200 bg-white">
                <table class="min-w-full text-[11px]">
                    <thead class="bg-gray-50">
                        <tr>
                            <th v-for="(col, ci) in result.columns" :key="ci" class="px-2 py-1 text-left font-semibold whitespace-nowrap text-gray-700">{{ col }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, ri) in result.preview.slice(0, 5)" :key="ri" class="border-t border-gray-100">
                            <td v-for="(col, ci) in result.columns" :key="ci" class="max-w-[10rem] truncate px-2 py-1 text-gray-600">{{ row[ci] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <label v-for="f in FIELDS" :key="f.key" class="text-[11px] font-semibold text-gray-700">
                    {{ f.label }}<span v-if="'required' in f" class="text-red-600"> *</span>
                    <select v-model="mapping[f.key]" class="mt-0.5 block w-full rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs">
                        <option :value="null">{{ 'required' in f ? 'Choose a column…' : 'Not in my file' }}</option>
                        <option v-for="(col, ci) in result.columns" :key="ci" :value="ci">{{ col }}</option>
                    </select>
                </label>
            </div>
            <label class="mt-2 flex items-center gap-2 text-[11px] text-gray-700">
                <input v-model="headerRow" type="checkbox" class="rounded border-gray-300 text-[#4b5320]" />
                The first row shown above is a header row (not a material)
            </label>
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-lg bg-[#4b5320] px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                    :disabled="mapping.description === null || mapping.quantity === null"
                    @click="applyMapping"
                >
                    Read my materials
                </button>
                <button type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700" @click="addManualRow()">Enter manually instead</button>
            </div>
        </div>

        <!-- Review & edit -->
        <div v-if="state.rows.length > 0" class="mt-4 space-y-3" data-test="material-review">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-sm font-semibold text-gray-900">Review your materials</p>
                    <p class="text-[11px] text-gray-500">
                        <span v-if="methodLabel">{{ methodLabel }}. </span>
                        {{ selectedRows.length }} of {{ state.rows.length }} selected. Check every line; fix anything marked in red.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <button type="button" class="rounded-lg border border-gray-200 bg-white px-2.5 py-1 font-semibold text-gray-700 hover:bg-gray-50" @click="setAll(true)">Select all</button>
                    <button type="button" class="rounded-lg border border-gray-200 bg-white px-2.5 py-1 font-semibold text-gray-700 hover:bg-gray-50" @click="setAll(false)">Deselect all</button>
                    <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1 font-semibold text-gray-700 hover:bg-gray-50" @click="addManualRow()"><Plus class="size-3.5" /> Add line</button>
                </div>
            </div>

            <p v-for="w in result?.warnings ?? []" :key="w" class="rounded-lg bg-amber-50 px-3 py-2 text-[11px] font-medium text-amber-800">{{ w }}</p>

            <div
                v-for="row in state.rows"
                :key="row.key"
                class="rounded-xl border p-3 transition-colors"
                :class="[
                    rowErrors(row).length ? 'border-red-200 bg-red-50/40' : row.needsReview || row.warnings.length ? 'border-amber-200 bg-amber-50/30' : 'border-gray-200 bg-white',
                    row.selected ? '' : 'opacity-60',
                ]"
                data-test="material-row"
            >
                <div class="flex items-start gap-2">
                    <input v-model="row.selected" type="checkbox" class="mt-2 rounded border-gray-300 text-[#4b5320]" :aria-label="`Include ${row.description || 'this line'}`" @change="state.confirmed = false" />
                    <div class="grid flex-1 grid-cols-2 gap-2 lg:grid-cols-12">
                        <label class="col-span-2 text-[10px] font-semibold text-gray-500 lg:col-span-5">
                            Material description
                            <input v-model="row.description" maxlength="500" class="mt-0.5 block w-full rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-900" @input="state.confirmed = false" />
                        </label>
                        <label class="text-[10px] font-semibold text-gray-500 lg:col-span-2">
                            Quantity
                            <input v-model="row.quantity" inputmode="numeric" class="mt-0.5 block w-full rounded-lg border bg-white px-2 py-1.5 text-xs text-gray-900" :class="rowErrors(row).some((e) => e.startsWith('Quantity')) ? 'border-red-300' : 'border-gray-200'" @input="state.confirmed = false" />
                        </label>
                        <label class="text-[10px] font-semibold text-gray-500 lg:col-span-1">
                            Unit
                            <input v-model="row.unit" maxlength="30" placeholder="pcs" class="mt-0.5 block w-full rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-900" />
                        </label>
                        <label class="text-[10px] font-semibold text-gray-500 lg:col-span-2">
                            Brand
                            <input v-model="row.brand" maxlength="100" class="mt-0.5 block w-full rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-900" />
                        </label>
                        <label class="text-[10px] font-semibold text-gray-500 lg:col-span-2">
                            Specifications
                            <input v-model="row.specs" maxlength="500" class="mt-0.5 block w-full rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-900" />
                        </label>

                        <!-- Product match (separate from reading the file) -->
                        <label class="col-span-2 text-[10px] font-semibold text-gray-500 lg:col-span-7">
                            Catalog product
                            <select v-model="row.product_id" class="mt-0.5 block w-full rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-900" @change="state.confirmed = false">
                                <option :value="null">{{ row.suggestions.length ? 'Keep as my own item (staff will quote it)' : 'No catalog match: staff will quote this item' }}</option>
                                <option v-for="s in row.suggestions" :key="s.product_id" :value="s.product_id">
                                    {{ s.name }}{{ s.brand ? ` · ${s.brand}` : '' }} ({{ s.sku }}) · {{ s.level === 'high' ? 'Strong' : s.level === 'medium' ? 'Possible' : 'Weak' }} match {{ s.confidence }}%
                                </option>
                            </select>
                        </label>
                        <div class="text-[10px] font-semibold text-gray-500 lg:col-span-2">
                            Unit price
                            <p class="mt-1 text-xs" :class="unitPrice(row) !== null && showPrices ? 'font-bold text-gray-900' : 'text-amber-700'">
                                {{ unitPrice(row) !== null && showPrices ? peso.format(unitPrice(row)!) : 'For staff quotation' }}
                            </p>
                        </div>
                        <div class="text-[10px] font-semibold text-gray-500 lg:col-span-2">
                            Line total
                            <p class="mt-1 text-xs" :class="lineTotal(row) !== null && showPrices ? 'font-bold text-gray-900' : 'text-amber-700'">
                                {{ lineTotal(row) !== null && showPrices ? peso.format(lineTotal(row)!) : 'Pending price' }}
                            </p>
                        </div>
                        <div class="flex items-end justify-end lg:col-span-1">
                            <button type="button" class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600" :aria-label="`Remove ${row.description || 'line'}`" @click="removeRow(row.key)">
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <ul v-if="rowErrors(row).length || row.warnings.length || row.needsReview" class="mt-2 ml-6 space-y-0.5 text-[11px]">
                    <li v-for="e in rowErrors(row)" :key="e" class="font-medium text-red-700">• {{ e }}</li>
                    <li v-for="w in row.warnings" :key="w" class="text-amber-700">• {{ w }}</li>
                    <li v-if="row.needsReview && !rowErrors(row).length" class="text-amber-700">
                        • Please check this line{{ row.line ? ` (line ${row.line} of your file)` : '' }}: it was read automatically.
                    </li>
                </ul>
            </div>

            <!-- Lines we couldn't read as materials -->
            <details v-if="unparsed.length" class="rounded-xl border border-gray-200 bg-white p-3">
                <summary class="cursor-pointer text-xs font-semibold text-gray-700">{{ unparsed.length }} line(s) we couldn’t read as materials: add any you need</summary>
                <ul class="mt-2 max-h-48 space-y-1 overflow-auto">
                    <li v-for="(l, i) in unparsed" :key="i" class="flex items-center justify-between gap-2 text-[11px] text-gray-600">
                        <span class="truncate">{{ l }}</span>
                        <button type="button" class="shrink-0 rounded border border-gray-200 px-2 py-0.5 font-semibold text-[#4b5320]" @click="addUnparsed(i)">Add</button>
                    </li>
                </ul>
            </details>

            <details v-if="result?.raw_text" class="rounded-xl border border-gray-200 bg-white p-3">
                <summary class="cursor-pointer text-xs font-semibold text-gray-700">Show the text we read from your file</summary>
                <pre class="mt-2 max-h-48 overflow-auto text-[11px] whitespace-pre-wrap text-gray-600">{{ result.raw_text }}</pre>
            </details>

            <!-- Estimated prices -->
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 text-xs">
                <template v-if="showPrices">
                    <div class="flex justify-between"><span class="text-gray-600">Estimated subtotal (priced items)</span><span class="font-semibold">{{ peso.format(estimatedSubtotal) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-600">VAT ({{ taxRate }}%)</span><span class="font-semibold">{{ peso.format(estimatedTax) }}</span></div>
                    <div class="mt-1 flex justify-between border-t border-gray-200 pt-1 text-sm"><span class="font-semibold">Estimated total</span><span class="font-semibold">{{ peso.format(estimatedSubtotal + estimatedTax) }}</span></div>
                </template>
                <p class="mt-2 text-[11px] text-gray-500">
                    <span v-if="unpricedCount">{{ unpricedCount }} item(s) will be priced by our staff. </span>
                    This is an estimate from current catalog prices, not an official quotation. Our team will send you the official prices.
                </p>
            </div>

            <!-- Confirmation -->
            <label class="flex items-start gap-2 rounded-xl border p-3 text-xs" :class="state.confirmed ? 'border-emerald-200 bg-emerald-50' : 'border-gray-200 bg-white'">
                <input v-model="state.confirmed" type="checkbox" class="mt-0.5 rounded border-gray-300 text-[#4b5320]" :disabled="selectedRows.length === 0 || invalidSelected.length > 0" data-test="materials-confirm" />
                <span>
                    <span class="font-semibold text-gray-900">I confirm the {{ selectedRows.length }} selected material(s) match my intended list.</span>
                    <span v-if="invalidSelected.length" class="block text-red-700">Fix the {{ invalidSelected.length }} line(s) marked in red, or untick them, first.</span>
                    <span v-else-if="state.confirmed" class="mt-0.5 flex items-center gap-1 text-emerald-700"><CheckCircle2 class="size-3.5" /> Ready to submit with your request.</span>
                </span>
            </label>
        </div>
    </div>
</template>
