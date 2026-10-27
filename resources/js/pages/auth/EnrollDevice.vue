<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ShieldCheck, Laptop, KeyRound, ArrowRight, Home, Lock, Mail, UserCheck, Clock, ShieldAlert } from '@lucide/vue';
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
    token?: string;
    expectedCode?: string;
    assignedUser?: {
        name: string;
        email: string;
    } | null;
    expiresAt?: string;
    isInvitation?: boolean;
    status?: string;
}>();

const form = useForm({
    code: props.initialCode || '',
    token: props.token || '',
    email: props.assignedUser?.email || '',
    password: '',
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

        <!-- Invitation Context Card -->
        <div
            v-if="assignedUser"
            class="rounded-2xl border border-[#4b5320]/20 bg-[#4b5320]/5 p-4 flex items-start gap-3.5"
        >
            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#4b5320] text-[#F8B803] shadow-xs">
                <UserCheck class="size-4.5" />
            </div>
            <div class="min-w-0 flex-1 text-xs text-gray-700 leading-relaxed">
                <div class="flex items-center justify-between gap-2">
                    <h4 class="font-bold text-gray-900">
                        Staff Workstation Invitation
                    </h4>
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-full border border-amber-200">
                        <Clock class="size-3" />
                        Valid for 5 Minutes Only
                    </span>
                </div>
                <p class="mt-1 text-gray-700">
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
            class="rounded-2xl border border-[#4b5320]/20 bg-[#4b5320]/5 p-4 flex items-start gap-3.5"
        >
            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#4b5320] text-[#F8B803] shadow-xs">
                <Laptop class="size-4.5" />
            </div>
            <div class="min-w-0 flex-1 text-xs text-gray-700 leading-relaxed">
                <h4 class="font-bold text-gray-900">
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

            <!-- Registered Work Email (Locked to assigned employee) -->
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <Label for="employee-email" class="text-xs font-semibold text-gray-900">
                        Registered Work Email
                    </Label>
                    <span v-if="assignedUser" class="text-[10px] font-bold text-emerald-800 bg-emerald-100/90 px-2 py-0.5 rounded-full">
                        Registered Account Only
                    </span>
                </div>
                <div class="relative">
                    <Input
                        id="employee-email"
                        v-model="form.email"
                        type="email"
                        :readonly="!!assignedUser"
                        :disabled="!!assignedUser && form.processing"
                        :class="[
                            'h-11 rounded-xl text-sm focus:border-[#4b5320] focus:ring-[#4b5320]/20',
                            assignedUser ? 'bg-gray-100/90 font-medium text-gray-800 cursor-not-allowed border-gray-300' : 'border-gray-300'
                        ]"
                        required
                    />
                    <Mail class="size-4 text-gray-400 absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                </div>
                <InputError :message="form.errors.email" />
                <p v-if="assignedUser" class="text-[11px] text-gray-500">
                    This enrollment link is permanently locked to this email address as registered by your administrator.
                </p>
            </div>

            <!-- Enrollment Code Input -->
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <Label for="enrollment-code" class="text-xs font-semibold text-gray-900">
                        One-Time Enrollment Code
                    </Label>
                    <span class="text-[11px] text-gray-500 font-medium">From Invitation Email</span>
                </div>
                <div class="relative">
                    <Input
                        id="enrollment-code"
                        v-model="form.code"
                        type="text"
                        placeholder="e.g. MRP-8X92K1"
                        class="h-11 uppercase font-mono text-sm font-bold tracking-wider rounded-xl border-gray-300 focus:border-[#4b5320] focus:ring-[#4b5320]/20"
                        autofocus
                        required
                        :disabled="form.processing"
                    />
                    <KeyRound class="size-4 text-gray-400 absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                </div>
                <InputError :message="form.errors.code" />
                <p class="text-[11px] text-gray-500">
                    Enter the single-use enrollment code shown in your invitation email or modal.
                </p>
            </div>

            <!-- Temporary / Account Password -->
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <Label for="employee-password" class="text-xs font-semibold text-gray-900">
                        Temporary Password
                    </Label>
                    <span class="text-[11px] text-gray-500 font-medium">Provided by Administrator</span>
                </div>
                <div class="relative">
                    <Input
                        id="employee-password"
                        v-model="form.password"
                        type="password"
                        placeholder="Enter your initial temporary password"
                        class="h-11 rounded-xl border-gray-300 text-sm focus:border-[#4b5320] focus:ring-[#4b5320]/20"
                        required
                        :disabled="form.processing"
                    />
                    <Lock class="size-4 text-gray-400 absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                </div>
                <InputError :message="form.errors.password" />
                <p class="text-[11px] text-gray-500">
                    Confirms your employee identity and authorizes this workstation.
                </p>
            </div>

            <!-- Optional Device Name -->
            <div class="space-y-1.5">
                <Label for="device-name" class="text-xs font-semibold text-gray-900">
                    Workstation / Laptop Name <span class="text-gray-400 font-normal">(Optional)</span>
                </Label>
                <Input
                    id="device-name"
                    v-model="form.device_name"
                    type="text"
                    placeholder="e.g. Maria's Office Laptop"
                    class="h-11 rounded-xl border-gray-300 text-sm focus:border-[#4b5320] focus:ring-[#4b5320]/20"
                    :disabled="form.processing"
                />
                <InputError :message="form.errors.device_name" />
            </div>

            <!-- Submit Button -->
            <Button
                type="submit"
                class="w-full h-12 mt-2 rounded-xl bg-[#F8B803] hover:bg-[#fac52e] text-gray-950 font-bold text-sm shadow-md cursor-pointer transition-all hover:shadow-lg"
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
