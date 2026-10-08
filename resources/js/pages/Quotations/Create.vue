<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
    ClipboardList,
    Send,
    MapPin,
} from '@lucide/vue';
import { nextTick, ref } from 'vue';
import FadeContent from '@/components/vue-bits/FadeContent.vue';
import SpotlightCard from '@/components/vue-bits/SpotlightCard.vue';
import SpecularButton from '@/components/ui/SpecularButton.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { store } from '@/routes/quotations';
const form = useForm({
    company: '',
    contactName: '',
    email: '',
    phone: '',
    projectType: 'Commercial Building',
    timeline: '1-3 months',
    requirements: '',
});
const submittedSuccess = ref(false);
const formElement = ref<HTMLFormElement>();
const successMessage = ref<HTMLElement>();
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
function submit() {
    if (form.processing) {
        return;
    }

    submittedSuccess.value = false;
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: async () => {
            submittedSuccess.value = true;
            form.reset();
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
                    <summary class="text-sm font-semibold text-[#4b5320]">
                        What happens after you submit?
                    </summary>
                    <ol class="mt-5 grid gap-6">
                        <li
                            v-for="(step, index) in [
                                {
                                    title: 'Share your requirements',
                                    text: 'Include product names, specifications, and quantities.',
                                    icon: ClipboardList,
                                },
                                {
                                    title: 'Our team reviews your request',
                                    text: 'We assess your materials list and project needs.',
                                    icon: CheckCircle2,
                                },
                                {
                                    title: 'Receive your quotation',
                                    text: 'We contact you using the details you provide.',
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
                        Our team will review your requirements and contact you.
                    </p>
                </div>
                <form
                    ref="formElement"
                    class="mt-7 space-y-6"
                    :aria-busy="form.processing"
                    @submit.prevent="submit"
                >
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
                    <div>
                        <label for="requirements" class="field-label"
                            >Materials & project requirements
                            <span aria-hidden="true">*</span></label
                        ><textarea
                            id="requirements"
                            v-model="form.requirements"
                            rows="6"
                            maxlength="3000"
                            required
                            class="field-control resize-y"
                            placeholder="List the products, specifications, and quantities you need…"
                            :aria-invalid="!!form.errors.requirements"
                            aria-describedby="requirements-help requirements-error"
                        ></textarea>
                        <div
                            id="requirements-help"
                            class="mt-2 flex justify-between gap-3 text-xs text-gray-500"
                        >
                            <span
                                >Include sizes, brands, or model numbers if
                                available.</span
                            ><span class="shrink-0"
                                >{{ form.requirements.length }}/3000</span
                            >
                        </div>
                        <p
                            v-if="form.errors.requirements"
                            id="requirements-error"
                            class="mt-2 text-xs text-red-600"
                        >
                            {{ form.errors.requirements }}
                        </p>
                    </div>
                    <SpecularButton
                        type="submit"
                        variant="brand"
                        size="lg"
                        :radius="12"
                        :disabled="form.processing"
                        class="w-full"
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
