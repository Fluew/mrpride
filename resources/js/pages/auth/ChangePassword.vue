<script setup lang="ts">
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthSplitLayout from '@/layouts/auth/AuthSplitLayout.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import PasswordInput from '@/components/PasswordInput.vue';
import InputError from '@/components/InputError.vue';
import { ShieldAlert, KeyRound, CheckCircle2, XCircle, LogOut } from '@lucide/vue';

const props = defineProps<{
    reason: string;
    isTemporary: boolean;
    notice?: string;
}>();

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const lengthValid = computed(() => form.password.length >= 8);
const upperLowerValid = computed(() => /[a-z]/.test(form.password) && /[A-Z]/.test(form.password));
const numberValid = computed(() => /[0-9]/.test(form.password));
const symbolValid = computed(() => /[^A-Za-z0-9]/.test(form.password));
const matchValid = computed(() => form.password.length > 0 && form.password === form.password_confirmation);

const isFormValid = computed(() =>
    lengthValid.value &&
    upperLowerValid.value &&
    numberValid.value &&
    symbolValid.value &&
    matchValid.value &&
    form.current_password.length > 0
);

const submit = () => {
    form.put('/password/change', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <AuthSplitLayout
        title="Mandatory Password Update"
        description="Secure your account with a strong permanent password"
    >
        <Head title="Update Password | MR-Pride" />

        <div class="space-y-6">
            <!-- Notice Banner -->
            <div
                class="rounded-2xl border p-4 sm:p-5 flex items-start gap-4"
                :class="isTemporary ? 'border-amber-200/90 bg-amber-50/70' : 'border-blue-200/90 bg-blue-50/70'"
            >
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl text-white shadow-xs"
                    :class="isTemporary ? 'bg-[#4b5320] text-[#F8B803]' : 'bg-blue-600'"
                >
                    <ShieldAlert class="size-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <h4 class="text-sm font-bold text-gray-900">
                        {{ isTemporary ? 'Initial Temporary Password Change' : '90-Day Password Rotation Policy' }}
                    </h4>
                    <p class="text-xs text-gray-600 mt-1 leading-relaxed">
                        {{ reason }}
                    </p>
                </div>
            </div>

            <!-- Password Form -->
            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-1.5">
                    <Label for="current_password" class="text-sm font-semibold text-gray-800">
                        {{ isTemporary ? 'Current / Temporary Password' : 'Old Password' }}
                    </Label>
                    <PasswordInput
                        id="current_password"
                        v-model="form.current_password"
                        required
                        autofocus
                        class="h-11 rounded-xl"
                        :disabled="form.processing"
                    />
                    <InputError :message="form.errors.current_password" />
                </div>

                <div class="space-y-1.5">
                    <Label for="new_password" class="text-sm font-semibold text-gray-800">
                        New Password
                    </Label>
                    <PasswordInput
                        id="new_password"
                        v-model="form.password"
                        required
                        class="h-11 rounded-xl"
                        :disabled="form.processing"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="space-y-1.5">
                    <Label for="password_confirmation" class="text-sm font-semibold text-gray-800">
                        Confirm New Password
                    </Label>
                    <PasswordInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        required
                        class="h-11 rounded-xl"
                        :disabled="form.processing"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <!-- Password Policy Indicators -->
                <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-3.5 space-y-2 text-xs">
                    <p class="font-bold text-gray-700 uppercase tracking-wider text-[10px]">
                        Password Requirements
                    </p>
                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-1.5 text-gray-600">
                        <li class="flex items-center gap-1.5">
                            <component
                                :is="lengthValid ? CheckCircle2 : XCircle"
                                class="size-3.5 shrink-0"
                                :class="lengthValid ? 'text-green-600' : 'text-gray-400'"
                            />
                            <span>At least 8 characters</span>
                        </li>
                        <li class="flex items-center gap-1.5">
                            <component
                                :is="upperLowerValid ? CheckCircle2 : XCircle"
                                class="size-3.5 shrink-0"
                                :class="upperLowerValid ? 'text-green-600' : 'text-gray-400'"
                            />
                            <span>Upper & lowercase</span>
                        </li>
                        <li class="flex items-center gap-1.5">
                            <component
                                :is="numberValid ? CheckCircle2 : XCircle"
                                class="size-3.5 shrink-0"
                                :class="numberValid ? 'text-green-600' : 'text-gray-400'"
                            />
                            <span>At least one number</span>
                        </li>
                        <li class="flex items-center gap-1.5">
                            <component
                                :is="symbolValid ? CheckCircle2 : XCircle"
                                class="size-3.5 shrink-0"
                                :class="symbolValid ? 'text-green-600' : 'text-gray-400'"
                            />
                            <span>At least one symbol</span>
                        </li>
                    </ul>
                    <p class="text-[11px] text-gray-500 pt-1 border-t border-gray-200/60">
                        • Passwords cannot match recent passwords used on this account.
                        <br />
                        • All other active sessions on other devices will be terminated for security.
                    </p>
                </div>

                <Button
                    type="submit"
                    class="w-full h-12 rounded-xl bg-[#4b5320] hover:bg-[#3b4219] text-white font-bold text-sm shadow-md cursor-pointer transition-colors mt-2"
                    :disabled="form.processing || !isFormValid"
                >
                    <Spinner v-if="form.processing" class="mr-2" />
                    <KeyRound v-else class="size-4 mr-2 text-[#F8B803]" />
                    Update Password & Access Dashboard
                </Button>
            </form>

            <div class="border-t border-gray-200 pt-4 text-center">
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 hover:text-gray-800 transition-colors cursor-pointer"
                >
                    <LogOut class="size-3.5" />
                    Sign out
                </Link>
            </div>
        </div>
    </AuthSplitLayout>
</template>
