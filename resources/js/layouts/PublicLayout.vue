<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Menu, X, MapPin } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { home } from '@/routes';
import { index as company } from '@/routes/company';
import { index as products } from '@/routes/products';
import { create as quotation } from '@/routes/quotations';

const page = usePage();
const mobileOpen = ref(false);
const menuButton = ref<HTMLButtonElement>();
const currentPath = computed(() => page.url.split(/[?#]/)[0]);
const navigation = [
    { name: 'Home', href: home().url },
    { name: 'Products', href: products().url },
    { name: 'Company Info', href: company().url },
];
watch(
    () => page.url,
    () => {
        mobileOpen.value = false;
    },
);
function closeMenu() {
    mobileOpen.value = false;
    menuButton.value?.focus();
}
</script>
<template>
    <div
        class="public-site min-h-screen bg-gray-50 font-sans text-gray-900 selection:bg-[#F8B803]/40"
    >
        <a href="#main-content" class="skip-link">Skip to content</a>
        <header
            class="fixed inset-x-0 top-0 z-50 border-b border-gray-200/80 bg-white/95 backdrop-blur-xl"
            @keydown.esc="closeMenu"
        >
            <nav
                aria-label="Main navigation"
                class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"
            >
                <div class="flex h-20 items-center justify-between gap-4">
                    <Link
                        :href="home()"
                        aria-label="MR-Pride home"
                        class="flex shrink-0 items-center gap-3 rounded-lg"
                    >
                        <img
                            src="/LOGO_MR_PRIDE.png"
                            alt=""
                            class="size-11 object-contain"
                        />
                        <span class="flex flex-col gap-1 leading-none"
                            ><span class="text-lg font-extrabold tracking-tight"
                                >MR-PRIDE</span
                            ><span
                                class="text-[9px] font-semibold tracking-[0.16em] text-gray-500 uppercase"
                                >Electrical Supplies</span
                            ></span
                        >
                    </Link>
                    <div class="hidden items-center gap-2 md:flex">
                        <Link
                            v-for="item in navigation"
                            :key="item.name"
                            :href="item.href"
                            :aria-current="
                                currentPath === item.href ? 'page' : undefined
                            "
                            class="rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors"
                            :class="
                                currentPath === item.href
                                    ? 'bg-[#4b5320]/10 text-[#4b5320]'
                                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                            "
                            >{{ item.name }}</Link
                        >
                        <Link :href="quotation()" class="brand-button ml-3"
                            >Request Quotation<ArrowRight
                                class="size-4"
                                aria-hidden="true"
                        /></Link>
                    </div>
                    <button
                        ref="menuButton"
                        type="button"
                        class="flex size-11 items-center justify-center rounded-xl border border-gray-200 text-[#4b5320] md:hidden"
                        :aria-label="
                            mobileOpen ? 'Close navigation' : 'Open navigation'
                        "
                        :aria-expanded="mobileOpen"
                        aria-controls="mobile-navigation"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <X v-if="mobileOpen" class="size-5" /><Menu
                            v-else
                            class="size-5"
                        />
                    </button>
                </div>
                <div
                    v-if="mobileOpen"
                    id="mobile-navigation"
                    class="grid gap-2 border-t border-gray-100 py-4 md:hidden"
                >
                    <Link
                        v-for="item in navigation"
                        :key="item.name"
                        :href="item.href"
                        :aria-current="
                            currentPath === item.href ? 'page' : undefined
                        "
                        class="rounded-xl px-4 py-3 text-sm font-semibold"
                        :class="
                            currentPath === item.href
                                ? 'bg-[#4b5320]/10 text-[#4b5320]'
                                : 'text-gray-700 hover:bg-gray-100'
                        "
                        @click="mobileOpen = false"
                        >{{ item.name }}</Link
                    >
                    <Link
                        :href="quotation()"
                        class="brand-button mt-2"
                        @click="mobileOpen = false"
                        >Request Quotation<ArrowRight class="size-4"
                    /></Link>
                </div>
            </nav>
        </header>
        <main id="main-content" tabindex="-1" class="pt-20 focus:outline-none">
            <slot />
        </main>
        <footer class="border-t border-gray-200 bg-white">
            <div class="mx-auto max-w-7xl px-4 pt-14 pb-7 sm:px-6 lg:px-8">
                <div class="grid gap-10 pb-12 md:grid-cols-[2fr_1fr_1fr]">
                    <div>
                        <p class="text-lg font-extrabold text-[#4b5320]">
                            MR-PRIDE<span class="text-[#F8B803]">.</span>
                        </p>
                        <p
                            class="mt-4 max-w-sm text-sm leading-7 text-gray-600"
                        >
                            Electrical, hardware, and industrial supplies for
                            your next project. Quality products. Trusted brand
                            partners.
                        </p>
                    </div>
                    <div>
                        <h2
                            class="text-xs font-bold tracking-widest text-gray-500 uppercase"
                        >
                            Explore
                        </h2>
                        <ul class="mt-5 grid gap-3 text-sm font-medium">
                            <li v-for="item in navigation" :key="item.name">
                                <Link
                                    :href="item.href"
                                    class="hover:text-[#4b5320] hover:underline"
                                    >{{ item.name }}</Link
                                >
                            </li>
                            <li>
                                <Link
                                    :href="quotation()"
                                    class="hover:text-[#4b5320] hover:underline"
                                    >Request Quotation</Link
                                >
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h2
                            class="text-xs font-bold tracking-widest text-gray-500 uppercase"
                        >
                            Visit our office
                        </h2>
                        <p
                            class="mt-5 flex gap-2 text-sm leading-7 text-gray-600"
                        >
                            <MapPin
                                class="mt-1 size-4 shrink-0 text-[#4b5320]"
                                aria-hidden="true"
                            />#610 Esguerra St.<br />Tondo, Manila, Philippines
                        </p>
                    </div>
                </div>
                <div
                    class="flex flex-col gap-3 border-t border-gray-100 pt-6 text-xs text-gray-500 sm:flex-row sm:justify-between"
                >
                    <p>
                        &copy; {{ new Date().getFullYear() }} MR-Pride
                        Electrical Supplies.
                    </p>
                    <p>Hardware · Electrical · Industrial · Safety</p>
                </div>
            </div>
        </footer>
    </div>
</template>
