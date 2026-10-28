<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthSplitLayout from '@/layouts/auth/AuthSplitLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/InputError.vue';
import { ShieldCheck, Laptop, KeyRound, AlertTriangle, RefreshCw, LogOut } from '@lucide/vue';

const props = defineProps<{
    status: 'pending' | 'revoked' | 'approved';
    device?: {
        name: string;
        ip: string;
        expires_at?: string;
    };
    email?: string;
    isAdmin?: boolean;
    recoveryMessage?: string;
}>();

const showRecoveryMode = ref(false);

const verifyForm = useForm({
    code: '',
});

const recoveryForm = useForm({
    recovery_key: '',
});

const resendForm = useForm({});

const submitVerification = () => {
    verifyForm.post('/device/verify', {
        preserveScroll: true,
    });
};

const submitRecovery = () => {
    recoveryForm.post('/device/verify/recovery', {
        preserveScroll: true,
    });
};

const submitResend = () => {
    resendForm.post('/device/verify/resend', {
        preserveScroll: true,
    });
};
</script>

<template>
    <AuthSplitLayout
        title="Trusted Device Verification"
        description="Verify your device to access the administrative portal"
    >
        <Head title="Device Verification | MR-Pride" />

        <!-- Revoked Device Notice -->
        <div v-if="status === 'revoked'" class="space-y-6">
            <div class="rounded-xl border border-red-200 bg-red-50 p-6 text-center">
                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-red-100 text-red-600 mb-3">
                    <AlertTriangle class="size-6" />
                </div>
                <h3 class="text-lg font-semibold text-red-900 mb-1">Access Suspended on this Device</h3>
                <p class="text-sm text-red-700 leading-relaxed">
                    This device (<span class="font-semibold">{{ device?.name || 'Device' }}</span>) has been revoked by an administrator for security purposes.
                </p>
            </div>

            <p class="text-xs text-center text-gray-500">
                Please sign in from an approved device or contact your system administrator to re-authorize this workstation.
            </p>

            <Link
                href="/logout"
                method="post"
                as="button"
                class="w-full inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 cursor-pointer"
            >
                <LogOut class="size-4" />
                Return to Login
            </Link>
        </div>

        <!-- Verification Form -->
        <div v-else class="space-y-6">
            <!-- Device Banner -->
            <div class="rounded-xl border border-amber-200/80 bg-amber-50/70 p-4 sm:p-5 flex items-start gap-4">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#4b5320] text-[#F8B803]">
                    <Laptop class="size-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <h4 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                        <span>Unrecognized Device Detected</span>
                        <span class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                            Verification Required
                        </span>
                    </h4>
                    <p class="text-xs text-gray-600 mt-1 leading-relaxed">
                        Workstation: <span class="font-medium text-gray-800">{{ device?.name || 'Current Browser' }}</span> • IP: <span class="font-medium text-gray-800">{{ device?.ip || 'Detected' }}</span>
                    </p>
                    <p class="text-xs text-gray-600 mt-1">
                        A 6-digit confirmation code was dispatched to <strong class="text-gray-900">{{ email }}</strong>.
                    </p>
                </div>
            </div>

            <!-- Standard 6-Digit Code Mode -->
            <form v-if="!showRecoveryMode" @submit.prevent="submitVerification" class="space-y-5">
                <div class="space-y-2">
                    <Label for="verification-code" class="text-sm font-semibold text-gray-900">
                        Enter 6-Digit Code
                    </Label>
                    <Input
                        id="verification-code"
                        v-model="verifyForm.code"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        placeholder="••••••"
                        class="text-center text-2xl tracking-[0.5em] font-mono h-14 rounded-xl border-gray-300 focus:border-[#4b5320] focus:ring-[#4b5320]/20 font-semibold"
                        autofocus
                        required
                        :disabled="verifyForm.processing"
                    />
                    <InputError :message="verifyForm.errors.code" />
                </div>

                <Button
                    type="submit"
                    class="w-full h-12 rounded-xl bg-[#4b5320] hover:bg-[#3b4219] text-white font-semibold text-sm shadow-md cursor-pointer transition-colors"
                    :disabled="verifyForm.processing || verifyForm.code.length !== 6"
                >
                    <Spinner v-if="verifyForm.processing" class="mr-2" />
                    <ShieldCheck v-else class="size-4 mr-2 text-[#F8B803]" />
                    Authorize & Register Device
                </Button>

                <div class="flex items-center justify-between text-xs pt-2">
                    <button
                        type="button"
                        @click="submitResend"
                        :disabled="resendForm.processing"
                        class="inline-flex items-center gap-1.5 text-gray-600 hover:text-gray-900 font-semibold cursor-pointer"
                    >
                        <RefreshCw class="size-3.5" :class="{ 'animate-spin': resendForm.processing }" />
                        Resend Code
                    </button>

                    <button
                        v-if="isAdmin"
                        type="button"
                        @click="showRecoveryMode = true"
                        class="inline-flex items-center gap-1.5 text-[#4b5320] hover:underline font-semibold cursor-pointer"
                    >
                        <KeyRound class="size-3.5 text-[#F8B803]" />
                        Lost access? Use Admin Recovery Key
                    </button>
                </div>
            </form>

            <!-- Admin Master Recovery Key Mode -->
            <form v-else @submit.prevent="submitRecovery" class="space-y-5">
                <div class="rounded-xl border border-blue-200 bg-blue-50/70 p-4 text-xs text-blue-900 leading-relaxed">
                    <p class="font-semibold mb-1 flex items-center gap-1.5">
                        <KeyRound class="size-4 text-blue-700" />
                        Admin Emergency Device Recovery
                    </p>
                    Enter your single-use Emergency Admin Recovery Key (issued during account creation or previous recovery) to immediately approve this device.
                </div>

                <div class="space-y-2">
                    <Label for="recovery-key" class="text-sm font-semibold text-gray-900">
                        Admin Recovery Key
                    </Label>
                    <Input
                        id="recovery-key"
                        v-model="recoveryForm.recovery_key"
                        type="text"
                        placeholder="RECOVER-XXXXXX-XXXXXX-XXXXXX"
                        class="font-mono text-sm h-12 rounded-xl border-gray-300"
                        autofocus
                        required
                        :disabled="recoveryForm.processing"
                    />
                    <InputError :message="recoveryForm.errors.recovery_key" />
                </div>

                <div class="flex gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        @click="showRecoveryMode = false"
                        class="w-1/3 h-12 rounded-xl text-gray-700"
                    >
                        Back
                    </Button>
                    <Button
                        type="submit"
                        class="flex-1 h-12 rounded-xl bg-[#4b5320] hover:bg-[#3b4219] text-white font-semibold text-sm shadow-md cursor-pointer"
                        :disabled="recoveryForm.processing || !recoveryForm.recovery_key"
                    >
                        <Spinner v-if="recoveryForm.processing" class="mr-2" />
                        Verify Recovery Key
                    </Button>
                </div>
            </form>

            <div class="border-t border-gray-200 pt-4 text-center">
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="text-xs font-semibold text-gray-500 hover:text-gray-800 transition-colors cursor-pointer"
                >
                    Sign out and return to login
                </Link>
            </div>
        </div>
    </AuthSplitLayout>
</template>
