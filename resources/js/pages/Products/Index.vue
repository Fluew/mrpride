<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';

const categories = [
    { name: 'Cables & Wiring', icon: 'M13 10V3L4 14h7v7l9-11h-7z', items: 124 },
    { name: 'Circuit Breakers', icon: 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z', items: 85 },
    { name: 'Industrial Transformers', icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10', items: 42 },
    { name: 'Control Panels', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', items: 36 },
];

const products = [
    { id: 1, name: 'Heavy Duty Copper Cable 50mm', category: 'Cables & Wiring', brand: 'Nexans', image: 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=60' },
    { id: 2, name: '3-Phase Circuit Breaker 100A', category: 'Circuit Breakers', brand: 'Schneider Electric', image: 'https://images.unsplash.com/photo-1621905251189-08b45d6a269e?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=60' },
    { id: 3, name: 'Step-Down Transformer 500kVA', category: 'Transformers', brand: 'ABB', image: 'https://images.unsplash.com/photo-1581092335397-9583eb92d232?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=60' },
    { id: 4, name: 'Modular Control Panel V2', category: 'Control Panels', brand: 'Siemens', image: 'https://images.unsplash.com/photo-1580983546531-15b53ebc8684?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=60' },
    { id: 5, name: 'Industrial Relay 24V DC', category: 'Components', brand: 'Omron', image: 'https://images.unsplash.com/photo-1620286888126-e265c71a3dcc?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=60' },
    { id: 6, name: 'Fiber Optic Cable Reel', category: 'Cables & Wiring', brand: 'Prysmian', image: 'https://images.unsplash.com/photo-1517077304055-6e89abbf09b0?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=60' },
];
</script>

<template>
    <Head title="Products | MRP ElectroDesk" />

    <PublicLayout>
        <!-- Page Header -->
        <div class="bg-[#111] border-b border-gray-800 py-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h1 class="text-4xl font-extrabold text-white mb-4">Product Catalog</h1>
                <p class="text-gray-400 max-w-2xl mx-auto text-lg">Browse our comprehensive range of high-quality industrial electrical supplies and components.</p>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="flex flex-col lg:flex-row gap-12">
                <!-- Sidebar Filters -->
                <div class="w-full lg:w-1/4">
                    <div class="bg-[#111] border border-gray-800 rounded-2xl p-6 sticky top-24">
                        <h3 class="text-white font-bold mb-4 text-lg">Categories</h3>
                        <ul class="space-y-2">
                            <li v-for="category in categories" :key="category.name">
                                <a href="#" class="group flex items-center justify-between p-3 rounded-lg hover:bg-[#1a1a1a] transition-colors">
                                    <div class="flex items-center gap-3">
                                        <svg class="w-5 h-5 text-gray-500 group-hover:text-[#F8B803] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="category.icon" />
                                        </svg>
                                        <span class="text-gray-300 group-hover:text-white transition-colors">{{ category.name }}</span>
                                    </div>
                                    <span class="text-xs bg-[#222] text-gray-400 px-2 py-1 rounded-full">{{ category.items }}</span>
                                </a>
                            </li>
                        </ul>

                        <div class="mt-8">
                            <h3 class="text-white font-bold mb-4 text-lg">Top Brands</h3>
                            <div class="flex flex-wrap gap-2">
                                <span class="bg-[#1a1a1a] border border-gray-800 text-gray-300 text-sm px-3 py-1.5 rounded-md hover:border-[#F8B803] cursor-pointer transition-colors">Schneider</span>
                                <span class="bg-[#1a1a1a] border border-gray-800 text-gray-300 text-sm px-3 py-1.5 rounded-md hover:border-[#F8B803] cursor-pointer transition-colors">ABB</span>
                                <span class="bg-[#1a1a1a] border border-gray-800 text-gray-300 text-sm px-3 py-1.5 rounded-md hover:border-[#F8B803] cursor-pointer transition-colors">Siemens</span>
                                <span class="bg-[#1a1a1a] border border-gray-800 text-gray-300 text-sm px-3 py-1.5 rounded-md hover:border-[#F8B803] cursor-pointer transition-colors">Nexans</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product Grid -->
                <div class="w-full lg:w-3/4">
                    <!-- Search & Sort -->
                    <div class="flex flex-col sm:flex-row justify-between items-center mb-8 gap-4">
                        <div class="relative w-full sm:w-96">
                            <input 
                                type="text" 
                                placeholder="Search products, brands, or SKUs..." 
                                class="w-full bg-[#111] border border-gray-800 rounded-full px-5 py-3 text-white placeholder-gray-500 focus:outline-none focus:border-[#F8B803] focus:ring-1 focus:ring-[#F8B803] transition-all"
                            >
                            <svg class="w-5 h-5 absolute right-4 top-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        
                        <select class="bg-[#111] border border-gray-800 text-white rounded-lg px-4 py-3 focus:outline-none focus:border-[#F8B803] appearance-none cursor-pointer w-full sm:w-auto">
                            <option>Sort by: Featured</option>
                            <option>Price: Low to High</option>
                            <option>Price: High to Low</option>
                            <option>Newest Arrivals</option>
                        </select>
                    </div>

                    <!-- Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        <div v-for="product in products" :key="product.id" class="group bg-[#111] border border-gray-800 rounded-2xl overflow-hidden hover:border-[#F8B803]/50 transition-all duration-300 hover:-translate-y-1">
                            <!-- Product Image -->
                            <div class="relative h-48 overflow-hidden bg-gray-900">
                                <img :src="product.image" :alt="product.name" class="w-full h-full object-cover opacity-80 group-hover:opacity-100 transition-opacity group-hover:scale-105 duration-500">
                                <div class="absolute top-3 left-3 bg-[#1a1a1a]/80 backdrop-blur-sm px-2 py-1 rounded text-xs font-medium text-gray-300">
                                    {{ product.category }}
                                </div>
                            </div>
                            
                            <!-- Product Info -->
                            <div class="p-5">
                                <div class="text-xs text-[#F8B803] font-semibold mb-1 uppercase tracking-wider">{{ product.brand }}</div>
                                <h4 class="text-white font-bold text-lg mb-4 line-clamp-2">{{ product.name }}</h4>
                                
                                <div class="pt-4 border-t border-gray-800 flex justify-between items-center">
                                    <button class="text-sm font-medium text-gray-400 hover:text-white transition-colors">View Details</button>
                                    <button class="w-8 h-8 rounded-full bg-[#1a1a1a] flex items-center justify-center text-gray-400 group-hover:bg-[#F8B803] group-hover:text-black transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-12 flex justify-center">
                        <nav class="flex items-center gap-2">
                            <button class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-800 text-gray-400 hover:bg-[#111] hover:text-white transition-colors disabled:opacity-50" disabled>
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <button class="w-10 h-10 flex items-center justify-center rounded-lg bg-[#F8B803] text-black font-bold">1</button>
                            <button class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-800 text-gray-400 hover:bg-[#111] hover:text-white transition-colors">2</button>
                            <button class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-800 text-gray-400 hover:bg-[#111] hover:text-white transition-colors">3</button>
                            <span class="text-gray-500">...</span>
                            <button class="w-10 h-10 flex items-center justify-center rounded-lg border border-gray-800 text-gray-400 hover:bg-[#111] hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
