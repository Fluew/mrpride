<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Target, Eye, Star, Info } from '@lucide/vue';
</script>

<template>
    <Head title="Company Information | MR-Pride Electrical Supplies" />

    <PublicLayout>
        <!-- Hero Section -->
        <div class="relative bg-gray-50 py-24 overflow-hidden border-b border-gray-200">
            <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80')] opacity-10 bg-cover bg-center mix-blend-multiply"></div>
            <div class="absolute inset-0 bg-black/5"></div>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white border border-gray-200 text-gray-700 text-sm font-medium mb-6 shadow-sm">
                        <Info class="w-4 h-4 text-[#F8B803]" />
                        Company Profile
                    </div>
                    <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 mb-6 leading-tight tracking-tight">
                        About MR-Pride Electrical Supplies
                    </h1>
                    <p class="text-xl text-gray-600 leading-relaxed">
                        MR-Pride Electrical Supplies is an entity that offers list of quality products that will help to minimize your problems involving Electrical. Our office is located at #610 Esguerra St. Tondo Manila that is built to accommodate the increasing demand of our country, in terms of Electrical supply solution. This company is a fully Filipino Owned that is dedicated to support its clients, and future clients in their needs of any products in line, MR-Pride Electrical Supplies has devoted employees to provide products in good quality considering the words, “right in time.”
                    </p>
                </div>
            </div>
        </div>

        <!-- Vision and Mission Section -->
        <div class="bg-white py-24">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                    
                    <!-- Vision -->
                    <div class="bg-gray-50 rounded-3xl p-10 border border-gray-100 shadow-sm relative overflow-hidden group hover:border-[#F8B803]/30 transition-colors duration-300">
                        <div class="absolute -right-4 -top-4 text-[#F8B803] opacity-10 group-hover:scale-110 transition-transform duration-500">
                            <Eye class="w-48 h-48" />
                        </div>
                        <div class="relative z-10">
                            <div class="w-14 h-14 bg-white rounded-xl shadow-sm border border-gray-100 flex items-center justify-center mb-6">
                                <Eye class="w-7 h-7 text-[#F8B803]" />
                            </div>
                            <h2 class="text-3xl font-bold text-gray-900 mb-4">Vision</h2>
                            <p class="text-lg text-gray-600 leading-relaxed">
                                Our mission is to offer a good quality and fair price materials. As well as establishing an efficient relationship of teamwork with our business partners. To serve with a character and a purpose that brings honor to God.
                            </p>
                        </div>
                    </div>

                    <!-- Mission -->
                    <div class="bg-gray-50 rounded-3xl p-10 border border-gray-100 shadow-sm relative overflow-hidden group hover:border-[#4b5320]/30 transition-colors duration-300">
                        <div class="absolute -right-4 -top-4 text-[#4b5320] opacity-10 group-hover:scale-110 transition-transform duration-500">
                            <Target class="w-48 h-48" />
                        </div>
                        <div class="relative z-10">
                            <div class="w-14 h-14 bg-white rounded-xl shadow-sm border border-gray-100 flex items-center justify-center mb-6">
                                <Target class="w-7 h-7 text-[#4b5320]" />
                            </div>
                            <h2 class="text-3xl font-bold text-gray-900 mb-4">Mission</h2>
                            <p class="text-lg text-gray-600 leading-relaxed">
                                To become a well-known distributor and supplier in a field of office and Electrical materials, and honest dealing with all people while growing from a small start-up business to a huge provider.
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Core Values Section -->
        <div class="py-24 bg-gray-50 border-t border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <div class="mb-16">
                    <div class="w-16 h-16 bg-white rounded-2xl shadow-sm border border-gray-200 flex items-center justify-center mx-auto mb-6">
                        <Star class="w-8 h-8 text-[#F8B803]" />
                    </div>
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Our Core Values</h2>
                    <div class="w-24 h-1 bg-gradient-to-r from-[#F8B803] to-[#4b5320] mx-auto rounded-full"></div>
                </div>

                <div class="flex flex-wrap justify-center gap-4 md:gap-8 max-w-4xl mx-auto">
                    <div class="px-8 py-4 bg-white rounded-full shadow-sm border border-gray-200 text-xl font-bold text-gray-800 hover:scale-105 hover:border-[#F8B803] transition-all duration-300">
                        Honesty
                    </div>
                    <div class="px-8 py-4 bg-white rounded-full shadow-sm border border-gray-200 text-xl font-bold text-gray-800 hover:scale-105 hover:border-[#4b5320] transition-all duration-300">
                        Respect
                    </div>
                    <div class="px-8 py-4 bg-white rounded-full shadow-sm border border-gray-200 text-xl font-bold text-gray-800 hover:scale-105 hover:border-[#F8B803] transition-all duration-300">
                        Passion
                    </div>
                    <div class="px-8 py-4 bg-white rounded-full shadow-sm border border-gray-200 text-xl font-bold text-gray-800 hover:scale-105 hover:border-[#4b5320] transition-all duration-300">
                        Quality
                    </div>
                    <div class="px-8 py-4 bg-white rounded-full shadow-sm border border-gray-200 text-xl font-bold text-gray-800 hover:scale-105 hover:border-[#F8B803] transition-all duration-300">
                        Commitment
                    </div>
                </div>
            </div>
        </div>

    </PublicLayout>
</template>
