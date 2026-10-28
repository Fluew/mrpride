<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ShieldCheck, Laptop, KeyRound, ArrowRight, Home, Mail, UserCheck, Clock, Eye, EyeOff } from '@lucide/vue';
import { Button } from '@/components/ui/button';
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
    token?: string;
    // The expected code is never sent to the browser: the user must type the OTP from their email.
    assignedUser?: {
        name: string;
        email: string;
    } | null;
    expiresAt?: string;
    isInvitation?: boolean;
    status?: string;
}>();

const showPassword = ref(false);

const form = useForm({
    code: props.initialCode || '',
    token: props.token || '',
    email: props.assignedUser?.email || '',
    password: '',
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

        <!-- Invitation Context Card -->
        <div
            v-if="assignedUser"
            class="rounded-xl border border-[#4b5320]/20 bg-[#4b5320]/5 p-4 flex items-start gap-3.5"
        >
            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#4b5320] text-[#F8B803]">
                <UserCheck class="size-4.5" />
            </div>
            <div class="min-w-0 flex-1 text-xs text-gray-700 leading-relaxed">
                <div class="flex items-center justify-between gap-2">
                    <h4 class="font-semibold text-gray-900">
                        Staff Workstation Invitation
                    </h4>
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-full border border-amber-200">
                        <Clock class="size-3" />
                        Valid for 5 Minutes Only
                    </span>
                </div>
                <p class="mt-1 text-gray-600">
                    Invitation for <strong class="text-gray-900">{{ assignedUser.name }}</strong> ({{ assignedUser.email }}).
                </p>
                <p class="mt-0.5 text-[11px] text-gray-500 flex items-center gap-1">
                    <ShieldCheck class="size-3 text-emerald-600 inline" />
                    Strictly bound to your registered email. Cannot be transferred or used by other accounts.
                </p>
            </div>
        </div>

        <div
            v-else
            class="rounded-xl border border-[#4b5320]/20 bg-[#4b5320]/5 p-4 flex items-start gap-3.5"
        >
            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#4b5320] text-[#F8B803]">
                <Laptop class="size-4.5" />
            </div>
            <div class="min-w-0 flex-1 text-xs text-gray-700 leading-relaxed">
                <h4 class="font-semibold text-gray-900">
                    Trusted Workstation Enrollment
                </h4>
                <p class="mt-0.5 text-gray-600">
                    Enter your enrollment code and temporary password below to register this workstation.
                </p>
            </div>
        </div>

        <!-- Enrollment & Identity Verification Form -->
        <form @submit.prevent="submit" class="space-y-4">
            <!-- Hidden Token field if tokenized -->
            <input v-if="token" type="hidden" name="token" :value="token" />

            <!-- Floating Label: Registered Work Email -->
            <div class="space-y-1.5">
                <div class="relative">
                    <input
                        id="employee-email"
                        v-model="form.email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        placeholder=" "
                        :readonly="!!assignedUser"
                        :disabled="form.processing"
                        required
                        class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pr-10 pb-2 text-xs text-gray-900 placeholder-transparent focus:border-[#F8B803] focus:ring-2 focus:ring-[#F8B803]/40 focus:outline-none sm:text-sm disabled:opacity-75"
                        :class="[
                            assignedUser ? 'bg-gray-50/90 font-medium text-gray-800 cursor-not-allowed border-gray-200' : 'border-gray-200',
                            form.errors.email ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : ''
                        ]"
                    />
                    <label
                        for="employee-email"
                        class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:font-normal peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320] peer-focus:font-semibold"
                        :class="form.email ? '-translate-y-2.5 scale-75 text-gray-600 font-semibold' : ''"
                    >
                        Registered Work Email
                    </label>
                    <Mail class="size-4 text-gray-400 absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                </div>
                <div class="flex items-center justify-between text-xs">
                    <InputError :message="form.errors.email" class="text-xs text-red-600" />
                    <span v-if="assignedUser" class="ml-auto inline-flex items-center gap-1 text-[10px] font-semibold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                        Registered Account Only
                    </span>
                </div>
                <p v-if="assignedUser" class="text-xs text-gray-500">
                    This enrollment link is permanently locked to this email address as registered by your administrator.
                </p>
            </div>

            <!-- Floating Label: One-Time Enrollment Code -->
            <div class="space-y-1.5">
                <div class="relative">
                    <input
                        id="enrollment-code"
                        v-model="form.code"
                        type="text"
                        name="code"
                        placeholder=" "
                        autocomplete="off"
                        autofocus
                        required
                        :disabled="form.processing"
                        class="peer block w-full font-mono font-semibold rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pr-10 pb-2 text-xs text-gray-900 placeholder-transparent focus:border-[#F8B803] focus:ring-2 focus:ring-[#F8B803]/40 focus:outline-none sm:text-sm"
                        :class="form.errors.code ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : ''"
                    />
                    <label
                        for="enrollment-code"
                        class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:font-normal peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320] peer-focus:font-semibold"
                        :class="form.code ? '-translate-y-2.5 scale-75 text-gray-600 font-semibold' : ''"
                    >
                        One-Time Enrollment Code
                    </label>
                    <KeyRound class="size-4 text-gray-400 absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                </div>
                <InputError :message="form.errors.code" class="text-xs text-red-600" />
                <p class="text-xs text-gray-500">
                    Enter the single-use enrollment code shown in your invitation email or modal.
                </p>
            </div>

            <!-- Floating Label: Temporary Password with Toggle -->
            <div class="space-y-1.5">
                <div class="relative">
                    <input
                        id="employee-password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        name="password"
                        placeholder=" "
                        autocomplete="current-password"
                        required
                        :disabled="form.processing"
                        class="peer block w-full rounded-xl border border-gray-200 bg-white px-3.5 pt-5 pr-11 pb-2 text-xs text-gray-900 placeholder-transparent focus:border-[#F8B803] focus:ring-2 focus:ring-[#F8B803]/40 focus:outline-none sm:text-sm"
                        :class="form.errors.password ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : ''"
                    />
                    <label
                        for="employee-password"
                        class="pointer-events-none absolute top-3.5 left-3.5 origin-[0] -translate-y-2.5 scale-75 transform text-xs font-semibold text-gray-500 duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-placeholder-shown:text-gray-400 peer-placeholder-shown:font-normal peer-focus:-translate-y-2.5 peer-focus:scale-75 peer-focus:text-[#4b5320] peer-focus:font-semibold"
                        :class="form.password ? '-translate-y-2.5 scale-75 text-gray-600 font-semibold' : ''"
                    >
                        Temporary Password
                    </label>
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 hover:text-gray-700 transition-colors cursor-pointer focus:outline-none focus-visible:text-[#4b5320]"
                        :aria-label="showPassword ? 'Hide temporary password' : 'Show temporary password'"
                        :title="showPassword ? 'Hide temporary password' : 'Show temporary password'"
                    >
                        <EyeOff v-if="showPassword" class="size-4" />
                        <Eye v-else class="size-4" />
                    </button>
                </div>
                <InputError :message="form.errors.password" class="text-xs text-red-600" />
                <p class="text-xs text-gray-500">
                    Provided by your administrator. Confirms your employee identity and authorizes this workstation.
                </p>
            </div>

            <!-- Submit Button -->
            <Button
                type="submit"
                class="w-full h-12 mt-2 rounded-xl bg-[#F8B803] hover:bg-[#fac52e] text-gray-950 font-semibold text-sm shadow-md cursor-pointer transition-colors disabled:opacity-50"
                :disabled="form.processing || !form.code.trim() || !form.email.trim() || !form.password"
            >
                <Spinner v-if="form.processing" class="mr-2 text-gray-950" />
                <ShieldCheck v-else class="size-4 mr-2 text-gray-950" />
                Register & Authorize Workstation
            </Button>
        </form>

        <!-- Navigation Links -->
        <div class="flex items-center justify-between border-t border-gray-100 pt-4 text-xs">
            <Link
                href="/"
                class="inline-flex items-center gap-1.5 text-gray-500 hover:text-gray-900 font-medium transition-colors"
            >
                <Home class="size-3.5" />
                Return to Home
            </Link>

            <Link
                href="/login"
                class="inline-flex items-center gap-1 text-[#4b5320] hover:underline font-semibold transition-colors"
            >
                Proceed to Login
                <ArrowRight class="size-3.5" />
            </Link>
        </div>
    </div>
</template>
