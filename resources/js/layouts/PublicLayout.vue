<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

const isScrolled = ref(false);

const handleScroll = () => {
    isScrolled.value = window.scrollY > 20;
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll);
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});

const navigation = [
    { name: 'Home', href: '/' },
    { name: 'Products', href: '/products' },
    { name: 'Company Info', href: '/company' },
];
</script>

<template>
    <div class="min-h-screen bg-gray-50 text-gray-900 font-sans selection:bg-[#F8B803] selection:text-white">
        <!-- Navigation -->
        <nav 
            :class="[
                'fixed top-0 w-full z-50 transition-all duration-300 border-b',
                isScrolled 
                    ? 'bg-white/90 backdrop-blur-md border-gray-200 shadow-sm' 
                    : 'bg-transparent border-transparent'
            ]"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-20">
                    <!-- Logo -->
                    <div class="flex-shrink-0 flex items-center gap-2">
                        <Link href="/" class="flex items-center gap-2">
                            <img src="/LOGO_MR_PRIDE.png" alt="MR-Pride Logo" class="w-10 h-10 object-contain" />
                            <div class="flex flex-col justify-center leading-none">
                                <span class="text-lg font-bold tracking-tight text-gray-900">MR-PRIDE</span>
                                <span class="text-[10px] font-bold tracking-widest text-gray-500 uppercase mt-0.5">Electrical Supplies</span>
                            </div>
                        </Link>
                    </div>

                    <!-- Desktop Menu -->
                    <div class="hidden md:flex items-center space-x-8">
                        <Link 
                            v-for="item in navigation" 
                            :key="item.name" 
                            :href="item.href"
                            class="text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors duration-200"
                        >
                            {{ item.name }}
                        </Link>
                        
                        <Link 
                            href="/quotation"
                            class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium transition-all duration-200 bg-gray-900 text-white rounded-full hover:bg-gray-800 hover:scale-105 active:scale-95"
                        >
                            Request Quotation
                        </Link>
                    </div>

                    <!-- Mobile Menu Button -->
                    <div class="md:hidden flex items-center">
                        <button class="text-gray-600 hover:text-gray-900 p-2">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="pt-20">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="border-t border-gray-200 bg-white pt-16 pb-8 mt-24">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
                    <div class="col-span-1 md:col-span-2">
                        <div class="flex items-center gap-2 mb-4">
                            <img src="/LOGO_MR_PRIDE.png" alt="MR-Pride Logo" class="w-8 h-8 object-contain" />
                            <div class="flex flex-col justify-center leading-none">
                                <span class="text-lg font-bold text-gray-900">MR-PRIDE</span>
                                <span class="text-[10px] font-bold tracking-widest text-gray-500 uppercase mt-0.5">Electrical Supplies</span>
                            </div>
                        </div>
                        <p class="text-gray-600 text-sm max-w-sm">
                            Your premier partner for industrial electrical supplies, providing innovative solutions and top-tier brands since 1998.
                        </p>
                    </div>
                    <div>
                        <h3 class="text-gray-900 font-semibold mb-4">Quick Links</h3>
                        <ul class="space-y-2 text-sm text-gray-600">
                            <li><Link href="/products" class="hover:text-gray-900 transition-colors">Products</Link></li>
                            <li><Link href="/company" class="hover:text-gray-900 transition-colors">Company Info</Link></li>
                            <li><Link href="/quotation" class="hover:text-gray-900 transition-colors">Request Quotation</Link></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-gray-900 font-semibold mb-4">Contact</h3>
                        <ul class="space-y-2 text-sm text-gray-600">
                            <li>sales@mrpelectrodesk.com</li>
                            <li>+1 (555) 123-4567</li>
                            <li>123 Industrial Parkway, Suite 100</li>
                        </ul>
                    </div>
                </div>
                <div class="border-t border-gray-200 pt-8 flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-500 text-xs">
                        &copy; {{ new Date().getFullYear() }} MR-Pride Electrical Supplies. All rights reserved.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>
