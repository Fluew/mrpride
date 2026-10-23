<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ShieldCheck, Laptop, KeyRound, ArrowRight, Home } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/InputError.vue';

defineOptions({
    layout: {
        title: 'Workstation Registration',
        description: 'Authorize this browser to access MRP ElectroDesk',
    },
});

const props = defineProps<{
    initialCode?: string;
    status?: string;
}>();

const form = useForm({
    code: props.initialCode || '',
    device_name: '',
});

const submit = () => {
    form.post('/device/enroll', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Workstation Registration - MRP ElectroDesk" />

    <div class="space-y-6">
        <!-- Status Banner -->
        <div
            v-if="status"
            class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center text-xs font-semibold text-emerald-800"
        >
            {{ status }}
        </div>

        <!-- Info Card -->
        <div class="rounded-2xl border border-[#4b5320]/20 bg-[#4b5320]/5 p-4.5 flex items-start gap-3.5">
            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#4b5320] text-[#F8B803] shadow-xs">
                <Laptop class="size-4.5" />
            </div>
            <div class="min-w-0 flex-1 text-xs text-gray-700 leading-relaxed">
                <h4 class="font-bold text-gray-900">
                    Trusted Device Enrollment
                </h4>
                <p class="mt-0.5 text-gray-600">
                    Submitting a valid administrator-issued enrollment code issues a high-entropy credential stored securely in this browser installation.
                </p>
            </div>
        </div>

        <!-- Enrollment Form -->
        <form @submit.prevent="submit" class="space-y-4.5">
            <!-- Enrollment Code -->
            <div class="space-y-1.5">
                <Label for="enrollment-code" class="text-xs font-semibold text-gray-900">
                    One-Time Enrollment Code
                </Label>
                <div class="relative">
                    <Input
                        id="enrollment-code"
                        v-model="form.code"
                        type="text"
                        placeholder="e.g. MRP-9X82KD"
                        class="h-12 uppercase font-mono text-base font-bold tracking-wider rounded-xl border-gray-300 focus:border-[#4b5320] focus:ring-[#4b5320]/20"
                        autofocus
                        required
                        :disabled="form.processing"
                    />
                    <KeyRound class="size-4.5 text-gray-400 absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                </div>
                <InputError :message="form.errors.code" />
                <p class="text-[11px] text-gray-500">
                    Enter the 8-10 character enrollment code provided in Device Management.
                </p>
            </div>

            <!-- Optional Device Name -->
            <div class="space-y-1.5">
                <Label for="device-name" class="text-xs font-semibold text-gray-900">
                    Device / Workstation Label <span class="text-gray-400 font-normal">(Optional)</span>
                </Label>
                <Input
                    id="device-name"
                    v-model="form.device_name"
                    type="text"
                    placeholder="e.g. Gary's Mobile or Main Inventory Terminal"
                    class="h-11 rounded-xl border-gray-300 text-sm"
                    :disabled="form.processing"
                />
                <InputError :message="form.errors.device_name" />
            </div>

            <!-- Submit Button -->
            <Button
                type="submit"
                class="w-full h-12 rounded-xl bg-[#F8B803] hover:bg-[#fac52e] text-gray-950 font-bold text-sm shadow-md cursor-pointer transition-all hover:shadow-lg"
                :disabled="form.processing || !form.code.trim()"
            >
                <Spinner v-if="form.processing" class="mr-2 text-gray-950" />
                <ShieldCheck v-else class="size-4 mr-2 text-gray-950" />
                Register & Authorize This Workstation
            </Button>
        </form>

        <!-- Navigation Links -->
        <div class="flex items-center justify-between border-t border-gray-100 pt-4 text-xs">
            <Link
                href="/"
                class="inline-flex items-center gap-1.5 text-gray-600 hover:text-gray-900 font-medium"
            >
                <Home class="size-3.5" />
                Return to Home
            </Link>

            <Link
                href="/login"
                class="inline-flex items-center gap-1 text-[#4b5320] hover:underline font-bold"
            >
                Proceed to Login
                <ArrowRight class="size-3.5" />
            </Link>
        </div>
    </div>
</template>
