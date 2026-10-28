<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
    ClipboardList,
    Send,
    MapPin,
    Plus,
    Trash2,
    AlertCircle,
    Info,
    UploadCloud,
} from '@lucide/vue';
import { nextTick, ref, computed, watch } from 'vue';
import FadeContent from '@/components/vue-bits/FadeContent.vue';
import SpotlightCard from '@/components/vue-bits/SpotlightCard.vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';
import MaterialListUploader from '@/components/quotation/MaterialListUploader.vue';
import type { MaterialState } from '@/components/quotation/MaterialListUploader.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { store } from '@/routes/quotations';

const props = defineProps<{
    brands?: string[];
}>();

interface BrandItem {
    brand: string;
    details: string;
}

const form = useForm({
    company: '',
    contactName: '',
    email: '',
    phone: '',
    projectType: 'Commercial Building',
    timeline: '1-3 months',
    brandItems: [{ brand: '', details: '' }] as BrandItem[],
    hasUnlistedBrands: false,
    unlistedBrands: '',
    attachment: null as File | null,
    // Optional files listing specific products (separate from the material list and the specs).
    productFiles: [] as File[],
});

const submittedSuccess = ref(false);
const formElement = ref<HTMLFormElement>();
const successMessage = ref<HTMLElement>();
const brandsValidationError = ref<string | null>(null);
const materialError = ref<string | null>(null);
// Errors for keys that are not form fields (materials, brands, upload limits).
const formErrors = computed(() => form.errors as Record<string, string | undefined>);
const materialUploader = ref<InstanceType<typeof MaterialListUploader>>();
const materialSection = ref<HTMLElement>();

// Material list from an uploaded file (or typed) that the client reviewed.
const materials = ref<MaterialState>({ rows: [], token: null, confirmed: false });
const selectedMaterials = computed(() => materials.value.rows.filter((r) => r.selected));

// First server error about the material list (e.g. "materials.2.quantity").
const materialsServerError = computed(() => {
    const key = Object.keys(form.errors).find((k) => k.startsWith('materials'));
    return key ? (form.errors as Record<string, string>)[key] : null;
});

// The required material list: confirmed lines, or the uploaded file itself for staff to read.
const hasMaterialList = computed(() => selectedMaterials.value.length > 0 || !!materials.value.token);
watch(
    () => [materials.value.rows.length, materials.value.token, materials.value.confirmed],
    () => (materialError.value = null),
);

// ------------------------------------------------------------------ product-list files

const PRODUCT_FILE_EXTENSIONS = ['pdf', 'xls', 'xlsx', 'csv', 'jpg', 'jpeg', 'png'];
const PRODUCT_FILE_ACCEPT = PRODUCT_FILE_EXTENSIONS.map((e) => `.${e}`).join(',');
const MAX_PRODUCT_FILES = 5;
const MAX_PRODUCT_FILE_BYTES = 10 * 1024 * 1024;
const MAX_PRODUCT_FILES_BYTES = 20 * 1024 * 1024;
const productFileError = ref<string | null>(null);

function formatSize(bytes: number) {
    return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function addProductFiles(event: Event) {
    const input = event.target as HTMLInputElement;
    const chosen = Array.from(input.files ?? []);
    input.value = '';
    productFileError.value = null;

    for (const file of chosen) {
        const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
        if (!PRODUCT_FILE_EXTENSIONS.includes(ext)) {
            productFileError.value = `${file.name}: please choose a PDF, Excel, CSV, JPG or PNG file.`;
            continue;
        }
        if (file.size === 0) {
            productFileError.value = `${file.name} is empty.`;
            continue;
        }
        if (file.size > MAX_PRODUCT_FILE_BYTES) {
            productFileError.value = `${file.name} is ${formatSize(file.size)}. Each file can be up to 10 MB.`;
            continue;
        }
        if (form.productFiles.some((f) => f.name === file.name && f.size === file.size)) {
            continue; // already selected
        }
        if (form.productFiles.length >= MAX_PRODUCT_FILES) {
            productFileError.value = `You can attach up to ${MAX_PRODUCT_FILES} files.`;
            break;
        }
        if (form.productFiles.reduce((sum, f) => sum + f.size, 0) + file.size > MAX_PRODUCT_FILES_BYTES) {
            productFileError.value = 'Product-list files can be up to 20 MB together.';
            break;
        }
        form.productFiles.push(file);
    }
}

function removeProductFile(index: number) {
    form.productFiles.splice(index, 1);
    productFileError.value = null;
}

function previewProductFile(file: File) {
    const url = URL.createObjectURL(file);
    window.open(url, '_blank', 'noopener');
    setTimeout(() => URL.revokeObjectURL(url), 60000);
}

const canPreview = (file: File) => /\.(pdf|jpe?g|png)$/i.test(file.name);

const productFileServerError = computed(() => {
    const key = Object.keys(form.errors).find((k) => k.startsWith('productFiles'));
    return key ? (form.errors as Record<string, string>)[key] : null;
});

function handleAttachmentChange(event: Event) {
    const target = event.target as HTMLInputElement;
    form.attachment = target.files?.[0] ?? null;
}

const fields = [
    {
        key: 'company',
        label: 'Company name',
        type: 'text',
        autocomplete: 'organization',
        placeholder: 'Your company',
        maxlength: 255,
    },
    {
        key: 'contactName',
        label: 'Contact name',
        type: 'text',
        autocomplete: 'name',
        placeholder: 'Your full name',
        maxlength: 255,
    },
    {
        key: 'email',
        label: 'Email address',
        type: 'email',
        autocomplete: 'email',
        placeholder: 'you@company.com',
        maxlength: 255,
    },
    {
        key: 'phone',
        label: 'Phone number',
        type: 'tel',
        autocomplete: 'tel',
        placeholder: 'Your contact number',
        maxlength: 50,
    },
] as const;

// A brand already chosen in another row cannot be chosen again.
function isBrandSelectedElsewhere(brand: string, currentRowIndex: number) {
    return form.brandItems.some(
        (item, idx) => idx !== currentRowIndex && item.brand === brand,
    );
}

// Rows are limited to the number of distinct brands, and a new row needs the last one filled.
const canAddBrandRow = computed(
    () =>
        form.brandItems.every((item) => item.brand !== '') &&
        form.brandItems.length < (props.brands?.length ?? 50),
);

function addBrandRow() {
    if (!canAddBrandRow.value) {
        return;
    }
    brandsValidationError.value = null;
    form.brandItems.push({ brand: '', details: '' });
}

function removeBrandRow(index: number) {
    brandsValidationError.value = null;
    if (form.brandItems.length > 1) {
        form.brandItems.splice(index, 1);
    } else {
        form.brandItems[0] = { brand: '', details: '' };
    }
}

const selectedBrands = computed(() => form.brandItems.filter((item) => item.brand !== ''));


function submit() {
    if (form.processing) {
        return;
    }

    brandsValidationError.value = null;

    if (form.hasUnlistedBrands && form.unlistedBrands.trim() === '') {
        brandsValidationError.value =
            'Please enter the brand names you are looking for.';
        return;
    }

    materialError.value = null;
    if (materialUploader.value?.hasUnsentFile) {
        materialError.value = 'Please click “Upload & extract” to send your material list file, or remove it.';
    } else if (!hasMaterialList.value) {
        materialError.value = 'Please add your material list: upload a file, or enter the items and quantities manually.';
    }
    if (materialError.value) {
        materialSection.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    if (selectedMaterials.value.length > 0) {
        const invalid = selectedMaterials.value.filter(
            (r) =>
                r.description.trim() === '' ||
                !/^\d+$/.test(r.quantity.trim().replace(/,/g, '')) ||
                Number(r.quantity.replace(/,/g, '')) < 1,
        );
        if (invalid.length > 0) {
            materialError.value = `Please fix the ${invalid.length} material line(s) marked in red, or untick them.`;
            return;
        }
        if (!materials.value.confirmed) {
            materialError.value = 'Please confirm that the material list matches what you need.';
            return;
        }
    }

    submittedSuccess.value = false;
    form
        .transform((data) => ({
            ...data,
            brands: data.brandItems
                .filter((item) => item.brand.trim() !== '')
                .map((item) => ({
                    brand: item.brand.trim(),
                    details: item.details.trim(),
                })),
            // Only selected lines are sent; the server recalculates every price and total.
            materials: selectedMaterials.value.map((r) => ({
                description: r.description.trim(),
                quantity: r.quantity.trim().replace(/,/g, ''),
                unit: r.unit.trim(),
                brand: r.brand.trim(),
                specs: r.specs.trim(),
                remarks: r.remarks.trim(),
                product_id: r.product_id ?? '',
            })),
            material_token: materials.value.token ?? '',
            materials_confirmed: materials.value.confirmed,
        }))
        .post(store().url, {
            preserveScroll: true,
            onSuccess: async () => {
                submittedSuccess.value = true;
                form.reset();
                form.brandItems = [{ brand: '', details: '' }];
                form.hasUnlistedBrands = false;
                form.unlistedBrands = '';
                materials.value = { rows: [], token: null, confirmed: false };
                form.productFiles = [];
                productFileError.value = null;
                await nextTick();
                successMessage.value?.focus();
            },
            onError: async () => {
                await nextTick();
                formElement.value
                    ?.querySelector<HTMLElement>('[aria-invalid="true"]')
                    ?.focus();
            },
        });
}
</script>

<template>
    <Head title="Request a Quotation | MR-Pride" />
    <PublicLayout>
        <div
            class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[0.85fr_1.15fr] lg:gap-16 lg:px-8 lg:py-20"
        >
            <FadeContent class="self-start lg:sticky lg:top-28">
                <p class="section-eyebrow">Plan your next project</p>
                <h1
                    class="mt-5 text-4xl leading-tight font-semibold tracking-tight sm:text-5xl"
                >
                    The right supplies.<br /><span class="text-[#4b5320]"
                        >A quote for your needs.</span
                    >
                </h1>
                <p class="mt-6 text-base leading-8 text-gray-600">
                    Send us your material list and the brands you prefer. Our
                    team will review your requirements and help you find the
                    right products.
                </p>
                <details
                    class="mt-7 rounded-xl border border-gray-200 bg-white p-4"
                >
                    <summary
                        class="cursor-pointer text-sm font-semibold text-[#4b5320]"
                    >
                        What happens after you submit?
                    </summary>
                    <ol class="mt-5 grid gap-6">
                        <li
                            v-for="(step, index) in [
                                {
                                    title: 'Share your requirements',
                                    text: 'Upload or enter your material list, and add the brands you prefer.',
                                    icon: ClipboardList,
                                },
                                {
                                    title: 'Our team reviews your request',
                                    text: 'We check every item, match products and prepare verified pricing.',
                                    icon: CheckCircle2,
                                },
                                {
                                    title: 'Receive your formal quotation',
                                    text: 'We contact you with a detailed, itemized quote.',
                                    icon: Send,
                                },
                            ]"
                            :key="step.title"
                            class="flex gap-4"
                        >
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#4b5320]/10 text-sm font-semibold text-[#4b5320]"
                                >0{{ index + 1 }}</span
                            >
                            <div>
                                <h2 class="text-sm font-semibold">
                                    {{ step.title }}
                                </h2>
                                <p class="mt-1 text-sm leading-6 text-gray-500">
                                    {{ step.text }}
                                </p>
                            </div>
                        </li>
                    </ol>
                </details>
                <div
                    class="mt-6 flex gap-3 border-t border-gray-200 pt-6 text-sm leading-6 text-gray-500"
                >
                    <MapPin class="size-5 shrink-0 text-[#4b5320]" />
                    <p>#610 Esguerra St., Tondo<br />Manila, Philippines</p>
                </div>
            </FadeContent>
            <SpotlightCard class="p-6 sm:p-9">
                <h2 class="text-xl font-semibold">Request a quotation</h2>
                <div
                    v-if="submittedSuccess"
                    ref="successMessage"
                    tabindex="-1"
                    role="status"
                    class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-800"
                >
                    <p class="font-semibold">Your request has been received.</p>
                    <p>
                        Our team will review your material list and contact you
                        shortly with an official itemized quotation.
                    </p>
                </div>
                <form
                    ref="formElement"
                    class="mt-7 space-y-6"
                    :aria-busy="form.processing"
                    @submit.prevent="submit"
                >
                    <!-- Client Details with Floating Labels -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div v-for="field in fields" :key="field.key">
                            <div class="relative">
                                <input
                                    :id="field.key"
                                    v-model="form[field.key]"
                                    :name="field.key"
                                    :type="field.type"
                                    :autocomplete="field.autocomplete"
                                    :maxlength="field.maxlength"
                                    placeholder=" "
                                    required
                                    class="peer block w-full rounded-xl border border-gray-200 bg-gray-50/50 px-3.5 pt-5 pb-2 text-xs text-gray-900 focus:border-[#4b5320] focus:bg-white focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                                    :class="
                                        form.errors[field.key]
                                            ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                                            : ''
                                    "
                                    :aria-invalid="!!form.errors[field.key]"
                                    :aria-describedby="
                                        form.errors[field.key]
                                            ? `${field.key}-error`
                                            : undefined
                                    "
                                />
                                <label
                                    :for="field.key"
                                    class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                                    :class="
                                        form[field.key]
                                            ? '-translate-y-2.5 scale-75 text-[#4b5320]'
                                            : ''
                                    "
                                >
                                    {{ field.label }}
                                </label>
                            </div>
                            <p
                                v-if="form.errors[field.key]"
                                :id="`${field.key}-error`"
                                class="mt-1.5 text-xs text-red-600"
                            >
                                {{ form.errors[field.key] }}
                            </p>
                        </div>
                    </div>

                    <!-- Project Info -->
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="projectType" class="field-label"
                                >Project type</label
                            ><select
                                id="projectType"
                                v-model="form.projectType"
                                class="field-control"
                                :aria-invalid="!!form.errors.projectType"
                                aria-describedby="projectType-error"
                            >
                                <option>Commercial Building</option>
                                <option>Industrial Facility</option>
                                <option>Infrastructure/Public Works</option>
                                <option>Residential Complex</option>
                                <option>Other</option>
                            </select>
                            <p
                                id="projectType-error"
                                class="mt-2 text-xs text-red-600"
                            >
                                {{ form.errors.projectType }}
                            </p>
                        </div>
                        <div>
                            <label for="timeline" class="field-label"
                                >Expected timeline</label
                            ><select
                                id="timeline"
                                v-model="form.timeline"
                                class="field-control"
                                :aria-invalid="!!form.errors.timeline"
                                aria-describedby="timeline-error"
                            >
                                <option>Immediate (ASAP)</option>
                                <option value="1-3 months">1–3 months</option>
                                <option value="3-6 months">3–6 months</option>
                                <option>Over 6 months</option>
                            </select>
                            <p
                                id="timeline-error"
                                class="mt-2 text-xs text-red-600"
                            >
                                {{ form.errors.timeline }}
                            </p>
                        </div>
                    </div>

                    <!-- ═════════════════════════════════════════════════════ -->
                    <!-- IMPROVED MATERIALS & PROJECT REQUIREMENTS SECTION      -->
                    <!-- ═════════════════════════════════════════════════════ -->
                    <div
                        class="space-y-5 rounded-xl border border-gray-200 bg-gray-50/50 p-5"
                    >
                        <!-- 1. Material list (required): upload a file or type it, then review the lines -->
                        <div ref="materialSection">
                            <MaterialListUploader
                                ref="materialUploader"
                                v-model="materials"
                                :error="materialError || formErrors.materials || formErrors.materials_confirmed || materialsServerError"
                            />
                        </div>

                        <!-- 2. Brand selection (optional) -->
                        <div class="border-t border-gray-200 pt-4">
                            <div class="mb-3">
                                <label
                                    class="field-label mb-0 font-semibold text-gray-900"
                                >
                                    Brands
                                </label>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    Select the brands you are interested in from
                                    our available catalog.
                                </p>
                            </div>

                            <!-- Empty catalog fallback -->
                            <div
                                v-if="!brands || brands.length === 0"
                                class="rounded-xl border border-gray-200 bg-white p-4 text-center text-xs text-gray-500"
                            >
                                <Info
                                    class="mx-auto mb-1 size-4 text-gray-400"
                                />
                                No brands are listed in our catalog yet. Use the
                                option below to tell us which brands you are
                                looking for.
                            </div>

                            <!-- Brand rows -->
                            <div v-else class="space-y-4">
                                <div
                                    v-for="(item, index) in form.brandItems"
                                    :key="index"
                                    class="rounded-xl border border-gray-200 bg-white p-4 transition-colors hover:border-gray-300 space-y-2.5"
                                >
                                    <!-- Header: Brand label & Delete icon -->
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-semibold text-gray-800">
                                            Brand {{ form.brandItems.length > 1 ? `#${index + 1}` : '' }}
                                        </span>
                                        <button
                                            type="button"
                                            :disabled="form.brandItems.length === 1 && !item.brand && !item.details"
                                            class="cursor-pointer rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600 disabled:pointer-events-none disabled:opacity-30"
                                            title="Remove this brand"
                                            aria-label="Remove brand"
                                            @click="removeBrandRow(index)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>

                                    <!-- Dropdown (Select a Brand) -->
                                    <div>
                                        <select
                                            v-model="item.brand"
                                            :aria-label="`Brand ${index + 1}`"
                                            class="field-control w-full px-3 py-2 text-sm"
                                            @change="brandsValidationError = null"
                                        >
                                            <option value="">
                                                Select a Brand
                                            </option>
                                            <option
                                                v-for="brand in brands"
                                                :key="brand"
                                                :value="brand"
                                                :disabled="isBrandSelectedElsewhere(brand, index)"
                                            >
                                                {{ brand }}
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Text box for being specific, quantity -->
                                    <div>
                                        <textarea
                                            v-model="item.details"
                                            rows="2"
                                            maxlength="1000"
                                            placeholder="Specific items, models, or quantity needed (e.g. 50 pcs 2-pole 30A breakers, 10 rolls 3.5mm wire)..."
                                            class="block w-full resize-y rounded-xl border border-gray-200 bg-gray-50/60 px-3.5 py-2 text-xs text-gray-900 placeholder:text-gray-400 focus:border-[#4b5320] focus:bg-white focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                                            @input="brandsValidationError = null"
                                        ></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Add another brand -->
                            <div
                                v-if="brands && brands.length > 0"
                                class="mt-3 flex items-center justify-between"
                            >
                                <button
                                    type="button"
                                    :disabled="!canAddBrandRow"
                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 transition-colors hover:border-[#4b5320] hover:bg-[#4b5320]/5 hover:text-[#4b5320] disabled:cursor-not-allowed disabled:opacity-50"
                                    @click="addBrandRow"
                                >
                                    <Plus class="size-3.5" />
                                    <span>Add Another Brand</span>
                                </button>
                            </div>

                            <!-- Optional: files naming the specific products wanted (not the material list, not the specs) -->
                            <div class="mt-4" data-test="product-list-upload">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold text-gray-800">Want to specify particular products?</p>
                                        <p class="text-xs text-gray-500">
                                            Upload a product list or select a file containing the materials you need.
                                        </p>
                                    </div>
                                    <label
                                        class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 focus-within:outline-2 focus-within:outline-[#4b5320]"
                                        :class="form.productFiles.length >= MAX_PRODUCT_FILES ? 'pointer-events-none opacity-50' : ''"
                                    >
                                        <UploadCloud class="size-3.5 text-gray-500" />
                                        <span>Upload product list</span>
                                        <input
                                            type="file"
                                            multiple
                                            class="sr-only"
                                            :accept="PRODUCT_FILE_ACCEPT"
                                            :disabled="form.productFiles.length >= MAX_PRODUCT_FILES"
                                            data-test="product-file-input"
                                            @change="addProductFiles"
                                        />
                                    </label>
                                </div>
                                <ul v-if="form.productFiles.length" class="mt-2 divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
                                    <li v-for="(f, i) in form.productFiles" :key="f.name + f.size" class="flex items-center gap-2 px-3 py-1.5 text-xs">
                                        <span class="min-w-0 flex-1 truncate text-gray-800" :title="f.name">{{ f.name }}</span>
                                        <span class="shrink-0 text-gray-400">{{ formatSize(f.size) }}</span>
                                        <button v-if="canPreview(f)" type="button" class="shrink-0 font-medium text-gray-600 hover:text-gray-900" @click="previewProductFile(f)">Preview</button>
                                        <button type="button" class="shrink-0 font-medium text-red-600 hover:text-red-700" :aria-label="`Remove ${f.name}`" @click="removeProductFile(i)">Remove</button>
                                    </li>
                                </ul>
                                <p class="mt-1 text-[11px] text-gray-400">PDF, Excel, CSV, JPG or PNG · up to {{ MAX_PRODUCT_FILES }} files, 10 MB each</p>
                                <p v-if="productFileError || productFileServerError" class="mt-1 text-xs font-medium text-red-600" role="alert">
                                    {{ productFileError || productFileServerError }}
                                </p>
                            </div>
                        </div>

                        <!-- 2. Brands not listed -->
                        <div class="border-t border-gray-200 pt-4">
                            <label
                                class="flex cursor-pointer items-center gap-2.5 select-none"
                            >
                                <input
                                    v-model="form.hasUnlistedBrands"
                                    type="checkbox"
                                    class="size-4 cursor-pointer rounded border-gray-300 text-[#4b5320] focus:ring-[#4b5320]"
                                />
                                <span
                                    class="text-xs font-semibold text-gray-800"
                                >
                                    I'm looking for brands not listed
                                </span>
                            </label>

                            <div
                                v-if="form.hasUnlistedBrands"
                                class="mt-3 animate-in space-y-1.5 duration-200 fade-in"
                            >
                                <div class="relative">
                                    <textarea
                                        id="unlistedBrands"
                                        v-model="form.unlistedBrands"
                                        rows="3"
                                        maxlength="3000"
                                        placeholder=" "
                                        class="peer block w-full resize-y rounded-xl border border-gray-200 bg-white px-3.5 pt-6 pb-2.5 text-xs text-gray-900 focus:border-[#4b5320] focus:ring-1 focus:ring-[#4b5320] focus:outline-none sm:text-sm"
                                        @input="brandsValidationError = null"
                                    ></textarea>
                                    <label
                                        for="unlistedBrands"
                                        class="pointer-events-none absolute top-4 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320]"
                                        :class="
                                            form.unlistedBrands
                                                ? '-translate-y-2.5 scale-75 text-[#4b5320]'
                                                : ''
                                        "
                                    >
                                        Brand names you are looking for
                                    </label>
                                </div>
                                <div
                                    class="flex justify-end text-[11px] text-gray-400"
                                >
                                    <span
                                        >{{ form.unlistedBrands.length }}/3000</span
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- 4. Specifications & Drawings Attachment -->
                        <div class="border-t border-gray-200 pt-4">
                            <div class="mb-2">
                                <label
                                    for="quotation-attachment"
                                    class="field-label mb-0 font-semibold text-gray-900"
                                >
                                    <span>Specifications, BOQ, or Plans (Optional)</span>
                                </label>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    Upload Bill of Materials, CAD/PDF drawings, or technical spec sheets (PDF, PNG, JPG, DOCX, ZIP up to 10MB).
                                </p>
                            </div>
                            <div class="relative flex items-center">
                                <input
                                    id="quotation-attachment"
                                    type="file"
                                    accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,.zip"
                                    class="block w-full cursor-pointer rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs text-gray-500 transition-colors file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-[#4b5320]/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[#4b5320] hover:file:bg-[#4b5320]/20 focus:outline-none"
                                    @change="handleAttachmentChange"
                                />
                            </div>
                            <p
                                v-if="form.errors.attachment"
                                class="mt-1.5 text-xs text-red-600 font-medium"
                            >
                                {{ form.errors.attachment }}
                            </p>
                        </div>

                        <!-- Inline validation notice -->
                        <div
                            v-if="
                                brandsValidationError ||
                                formErrors.brands ||
                                form.errors.unlistedBrands ||
                                formErrors.upload
                            "
                            class="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-xs font-medium text-red-700"
                        >
                            <AlertCircle class="size-4 shrink-0 text-red-600" />
                            <span>{{
                                brandsValidationError ||
                                formErrors.brands ||
                                form.errors.unlistedBrands ||
                                formErrors.upload
                            }}</span>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <SpecularButton
                        type="submit"
                        variant="brand"
                        size="lg"
                        :disabled="form.processing"
                        class="w-full cursor-pointer font-semibold text-white shadow-md transition-colors"
                    >
                        <span>{{
                            form.processing
                                ? form.progress?.percentage && form.progress.percentage < 100
                                    ? `Uploading… ${form.progress.percentage}%`
                                    : 'Sending your request…'
                                : 'Submit Quotation Request'
                        }}</span>
                        <ArrowRight v-if="!form.processing" class="size-4" />
                    </SpecularButton>
                </form>
            </SpotlightCard>
        </div>
    </PublicLayout>
</template>
