<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ShieldAlert, Laptop, ArrowRight, Home, RefreshCw, Lock } from '@lucide/vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        title: 'Workstation Access Restricted',
        description: 'Trusted device registration required to access MRP ElectroDesk',
    },
});

withDefaults(
    defineProps<{
        title?: string;
        message?: string;
        canEnroll?: boolean;
    }>(),
    {
        title: 'Access Denied',
        message:
            'This device is not registered or is no longer authorized to access MRP ElectroDesk. Contact your system administrator.',
        canEnroll: true,
    }
);

const reloadPage = () => {
    window.location.reload();
};
</script>

<template>
    <Head title="Access Denied - MRP ElectroDesk" />

    <div class="space-y-6">
        <!-- Shield Icon & Warning Banner -->
        <div class="rounded-xl border border-red-200/80 bg-red-50/70 p-6 text-center">
            <div
                class="mx-auto mb-4 flex size-14 items-center justify-center rounded-xl bg-red-100 text-red-600"
            >
                <ShieldAlert class="size-7" />
            </div>

            <h3 class="text-lg font-semibold tracking-tight text-red-950">
                {{ title }}
            </h3>

            <p class="mt-2 text-xs leading-relaxed text-red-800/90 sm:text-sm">
                {{ message }}
            </p>
        </div>

        <!-- Technical explanation card -->
        <div class="rounded-xl border border-gray-200/80 bg-white/60 p-4 text-xs text-gray-600">
            <div class="flex items-start gap-3">
                <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[#4b5320]/10 text-[#4b5320]">
                    <Lock class="size-4" />
                </div>
                <div>
                    <h4 class="font-semibold text-gray-900">
                        Browser-Based Trusted Device Identity
                    </h4>
                    <p class="mt-1 leading-relaxed text-gray-600">
                        MRP ElectroDesk enforces zero-trust role-based access. Only authorized and pre-enrolled browser
                        installations receive the cryptographic credential required to open the login portal.
                    </p>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col gap-2.5 pt-2">
            <Link
                v-if="canEnroll"
                href="/device/enroll"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#F8B803] py-2.5 text-sm font-semibold text-gray-950 transition-colors hover:bg-[#fac52e] focus:outline-none"
            >
                <Laptop class="size-4" />
                Enroll With Code
                <ArrowRight class="size-4" />
            </Link>

            <div class="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    @click="reloadPage"
                    class="flex-1 rounded-xl border-gray-200 py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-100"
                >
                    <RefreshCw class="mr-1.5 size-3.5" />
                    Retry
                </Button>

                <Link
                    href="/"
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-white py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-100"
                >
                    <Home class="size-3.5" />
                    Home Page
                </Link>
            </div>
        </div>
    </div>
</template>
