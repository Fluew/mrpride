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
    Package,
    AlertCircle,
    Info,
    Layers,
    FileText,
} from '@lucide/vue';
import { nextTick, ref, computed } from 'vue';
import FadeContent from '@/components/vue-bits/FadeContent.vue';
import SpotlightCard from '@/components/vue-bits/SpotlightCard.vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { store } from '@/routes/quotations';

interface CatalogProduct {
    id: number;
    name: string;
    sku: string;
    category: string;
    unit: string | null;
    description: string | null;
    price: number;
}

interface QuotationItemRow {
    product_id: number | null;
    quantity: number;
    specifications: string;
}

const props = defineProps<{
    products?: CatalogProduct[];
}>();

const form = useForm({
    company: '',
    contactName: '',
    email: '',
    phone: '',
    projectType: 'Commercial Building',
    timeline: '1-3 months',
    items: [
        { product_id: null, quantity: 1, specifications: '' },
    ] as QuotationItemRow[],
    hasOtherMaterials: false,
    otherMaterials: '',
    additionalRequirements: '',
});

const submittedSuccess = ref(false);
const formElement = ref<HTMLFormElement>();
const successMessage = ref<HTMLElement>();
const itemsValidationError = ref<string | null>(null);

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

// Group catalog products by category for organized dropdown selection
const productsByCategory = computed(() => {
    const groups: Record<string, CatalogProduct[]> = {};
    (props.products || []).forEach((p) => {
        const cat = p.category || 'General Electrical Supplies';
        if (!groups[cat]) {
            groups[cat] = [];
        }
        groups[cat].push(p);
    });
    return groups;
});

// Helper to look up product metadata
function getProductById(id: number | null): CatalogProduct | undefined {
    if (!id || !props.products) return undefined;
    return props.products.find((p) => p.id === id);
}

// Check if a product has already been chosen in another row
function isProductSelectedElsewhere(productId: number, currentRowIndex: number): boolean {
    return form.items.some((item, idx) => idx !== currentRowIndex && item.product_id === productId);
}

// Add a new product row
function addItemRow() {
    itemsValidationError.value = null;
    form.items.push({
        product_id: null,
        quantity: 1,
        specifications: '',
    });
}

// Remove a product row
function removeItemRow(index: number) {
    itemsValidationError.value = null;
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    } else {
        // Reset single remaining row
        form.items[0] = {
            product_id: null,
            quantity: 1,
            specifications: '',
        };
    }
}

// Validate that either catalog products or unlisted materials are requested
const hasValidRequirements = computed(() => {
    const hasCatalogItems = form.items.some(
        (i) => i.product_id !== null && Number(i.quantity) >= 1
    );
    const hasUnlisted = form.hasOtherMaterials && form.otherMaterials.trim().length > 0;
    return hasCatalogItems || hasUnlisted;
});

function submit() {
    if (form.processing) {
        return;
    }

    itemsValidationError.value = null;

    if (!hasValidRequirements.value) {
        itemsValidationError.value = 'Please select at least one product from our catalog or describe the unlisted materials you need.';
        return;
    }

    submittedSuccess.value = false;
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: async () => {
            submittedSuccess.value = true;
            form.reset();
            form.items = [{ product_id: null, quantity: 1, specifications: '' }];
            form.hasOtherMaterials = false;
            form.otherMaterials = '';
            form.additionalRequirements = '';
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
                    class="mt-5 text-4xl leading-tight font-extrabold tracking-tight sm:text-5xl"
                >
                    The right supplies.<br /><span class="text-[#4b5320]"
                        >A quote for your needs.</span
                    >
                </h1>
                <p class="mt-6 text-base leading-8 text-gray-600">
                    Tell us about your project and the materials you need. Our
                    team will review your requirements and help you find the
                    right products.
                </p>
                <details
                    class="mt-7 rounded-xl border border-gray-200 bg-white p-4"
                >
                    <summary class="text-sm font-semibold text-[#4b5320] cursor-pointer">
                        What happens after you submit?
                    </summary>
                    <ol class="mt-5 grid gap-6">
                        <li
                            v-for="(step, index) in [
                                {
                                    title: 'Share your requirements',
                                    text: 'Select catalog products, specify quantities, or list custom items.',
                                    icon: ClipboardList,
                                },
                                {
                                    title: 'Our team reviews your request',
                                    text: 'We assess your materials list and prepare verified pricing.',
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
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#4b5320]/10 text-sm font-bold text-[#4b5320]"
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
            <SpotlightCard class="p-6 shadow-sm sm:p-9">
                <h2 class="text-xl font-bold">Request a quotation</h2>
                <p class="mt-2 text-sm text-gray-500">
                    Fields marked * are required.
                </p>
                <div
                    v-if="submittedSuccess"
                    ref="successMessage"
                    tabindex="-1"
                    role="status"
                    class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-800"
                >
                    <p class="font-bold">Your request has been received.</p>
                    <p>
                        Our team will review your required materials and contact you shortly with an official itemized quotation.
                    </p>
                </div>
                <form
                    ref="formElement"
                    class="mt-7 space-y-6"
                    :aria-busy="form.processing"
                    @submit.prevent="submit"
                >
                    <!-- Client Details -->
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div v-for="field in fields" :key="field.key">
                            <label :for="field.key" class="field-label"
                                >{{ field.label }}
                                <span aria-hidden="true">*</span></label
                            ><input
                                :id="field.key"
                                v-model="form[field.key]"
                                :name="field.key"
                                :type="field.type"
                                :autocomplete="field.autocomplete"
                                :maxlength="field.maxlength"
                                :placeholder="field.placeholder"
                                required
                                class="field-control"
                                :aria-invalid="!!form.errors[field.key]"
                                :aria-describedby="
                                    form.errors[field.key]
                                        ? `${field.key}-error`
                                        : undefined
                                "
                            />
                            <p
                                v-if="form.errors[field.key]"
                                :id="`${field.key}-error`"
                                class="mt-2 text-xs text-red-600"
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
                                <option>1-3 months</option>
                                <option>3-6 months</option>
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
                    <div class="space-y-5 rounded-2xl border border-gray-200 bg-gray-50/50 p-5">
                        
                        <!-- 1. Multi-Product Catalog Selection -->
                        <div>
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 mb-3">
                                <div>
                                    <label class="field-label mb-0 text-gray-900 font-bold">
                                        Materials &amp; Products
                                    </label>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        Select products from our inventory catalog and specify needed quantities.
                                    </p>
                                </div>
                                <span class="text-xs font-semibold text-gray-400">
                                    {{ form.items.filter(i => i.product_id !== null).length }} item(s) selected
                                </span>
                            </div>

                            <!-- Empty Catalog State Fallback -->
                            <div
                                v-if="!products || products.length === 0"
                                class="rounded-xl border border-gray-200 bg-white p-4 text-center text-xs text-gray-500"
                            >
                                <Info class="size-4 mx-auto text-gray-400 mb-1" />
                                Catalog products are currently loading or unavailable. You can specify needed materials using the unlisted materials section below.
                            </div>

                            <!-- Product Rows List -->
                            <div v-else class="space-y-3">
                                <div
                                    v-for="(item, index) in form.items"
                                    :key="index"
                                    class="rounded-xl border border-gray-200 bg-white p-3.5 shadow-2xs transition-all hover:border-gray-300"
                                >
                                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-start">
                                        <!-- Product Selector -->
                                        <div class="sm:col-span-7">
                                            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                                                Product #{{ index + 1 }}
                                            </label>
                                            <select
                                                v-model="item.product_id"
                                                class="field-control text-sm py-2 px-3"
                                            >
                                                <option :value="null">-- Select a catalog product --</option>
                                                <optgroup
                                                    v-for="(catProds, catName) in productsByCategory"
                                                    :key="catName"
                                                    :label="catName"
                                                >
                                                    <option
                                                        v-for="p in catProds"
                                                        :key="p.id"
                                                        :value="p.id"
                                                        :disabled="isProductSelectedElsewhere(p.id, index)"
                                                    >
                                                        {{ p.name }} ({{ p.sku }})
                                                        {{ isProductSelectedElsewhere(p.id, index) ? '— [Already Added]' : '' }}
                                                    </option>
                                                </optgroup>
                                            </select>

                                            <!-- Selected Product Specifications / Meta Info -->
                                            <p
                                                v-if="item.product_id && getProductById(item.product_id)?.description"
                                                class="text-[11px] text-gray-500 mt-1 line-clamp-1 italic"
                                            >
                                                {{ getProductById(item.product_id)?.description }}
                                            </p>
                                        </div>

                                        <!-- Quantity Input + Unit Badge -->
                                        <div class="sm:col-span-4">
                                            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">
                                                Quantity
                                            </label>
                                            <div class="flex items-center gap-1.5">
                                                <input
                                                    v-model.number="item.quantity"
                                                    type="number"
                                                    min="1"
                                                    step="1"
                                                    placeholder="1"
                                                    class="field-control text-sm py-2 px-3 w-full"
                                                />
                                                <span
                                                    class="shrink-0 rounded-lg bg-gray-100 border border-gray-200 px-2.5 py-2 text-xs font-semibold text-gray-700 whitespace-nowrap"
                                                    title="Unit of Measurement"
                                                >
                                                    {{ getProductById(item.product_id)?.unit || 'pcs' }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Remove Row Button -->
                                        <div class="sm:col-span-1 flex sm:justify-center items-center sm:pt-6">
                                            <button
                                                type="button"
                                                @click="removeItemRow(index)"
                                                :disabled="form.items.length === 1 && !item.product_id"
                                                class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors cursor-pointer disabled:opacity-30 disabled:pointer-events-none"
                                                title="Remove this product"
                                                aria-label="Remove item"
                                            >
                                                <Trash2 class="size-4" />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Optional Custom Specifications for this Item -->
                                    <div class="mt-2.5 pt-2.5 border-t border-gray-100">
                                        <input
                                            v-model="item.specifications"
                                            type="text"
                                            maxlength="255"
                                            placeholder="Optional item notes (e.g. cut length, voltage rating, brand preference, or color)..."
                                            class="w-full rounded-lg border border-gray-200 bg-gray-50/60 px-3 py-1.5 text-xs text-gray-800 placeholder:text-gray-400 focus:bg-white focus:border-[#F8B803] focus:outline-none focus:ring-1 focus:ring-[#F8B803]"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Add Another Product Action -->
                            <div class="mt-3 flex items-center justify-between">
                                <button
                                    type="button"
                                    @click="addItemRow"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-bold text-gray-700 hover:border-[#4b5320] hover:text-[#4b5320] hover:bg-[#4b5320]/5 shadow-2xs transition-all cursor-pointer"
                                >
                                    <Plus class="size-3.5" />
                                    <span>Add Another Product</span>
                                </button>
                            </div>
                        </div>

                        <!-- 2. Other Materials / Unlisted Products -->
                        <div class="border-t border-gray-200 pt-4">
                            <label class="flex items-center gap-2.5 cursor-pointer select-none">
                                <input
                                    v-model="form.hasOtherMaterials"
                                    type="checkbox"
                                    class="size-4 rounded border-gray-300 text-[#4b5320] focus:ring-[#4b5320] cursor-pointer"
                                />
                                <span class="text-xs font-semibold text-gray-800">
                                    I'm looking for materials not listed in the catalog
                                </span>
                            </label>

                            <!-- Collapsible Other Materials Textarea -->
                            <div
                                v-if="form.hasOtherMaterials"
                                class="mt-3 animate-in fade-in duration-200 space-y-1.5"
                            >
                                <label for="otherMaterials" class="field-label mb-0">
                                    Other Materials or Products
                                </label>
                                <textarea
                                    id="otherMaterials"
                                    v-model="form.otherMaterials"
                                    rows="3"
                                    maxlength="3000"
                                    class="field-control resize-y text-xs sm:text-sm"
                                    placeholder="Enter product names, quantities, sizes, preferred brands, and custom technical specifications manually..."
                                ></textarea>
                                <div class="flex justify-between text-[11px] text-gray-400">
                                    <span>Include quantities and preferred brands where applicable.</span>
                                    <span>{{ form.otherMaterials.length }}/3000</span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Additional Project Requirements & Instructions -->
                        <div class="border-t border-gray-200 pt-4">
                            <label for="additionalRequirements" class="field-label mb-1">
                                Additional Project Requirements &amp; Instructions
                                <span class="text-xs font-normal text-gray-400">(Optional)</span>
                            </label>
                            <textarea
                                id="additionalRequirements"
                                v-model="form.additionalRequirements"
                                rows="3"
                                maxlength="3000"
                                class="field-control resize-y text-xs sm:text-sm"
                                placeholder="Specify preferred delivery dates, logistics access, technical certifications, packaging preferences, or special handling instructions…"
                            ></textarea>
                            <div class="flex justify-end text-[11px] text-gray-400 mt-1">
                                <span>{{ form.additionalRequirements.length }}/3000</span>
                            </div>
                        </div>

                        <!-- Inline Requirements Validation Notice -->
                        <div
                            v-if="itemsValidationError || form.errors.items"
                            class="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-700 font-medium"
                        >
                            <AlertCircle class="size-4 shrink-0 text-red-600" />
                            <span>{{ itemsValidationError || form.errors.items }}</span>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <SpecularButton
                        type="submit"
                        variant="brand"
                        size="lg"
                        :disabled="form.processing"
                        class="w-full font-bold text-white shadow-md hover:shadow-lg transition-all cursor-pointer"
                    >
                        <span>{{
                            form.processing
                                ? 'Sending your request…'
                                : 'Submit Quotation Request'
                        }}</span>
                        <ArrowRight v-if="!form.processing" class="size-4" />
                    </SpecularButton>
                </form>
            </SpotlightCard>
        </div>
    </PublicLayout>
</template>
