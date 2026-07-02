<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
</script>

<template>
    <Head title="Welcome | MR-Pride Electrical Supplies" />

    <PublicLayout>
        <!-- Hero Section -->
        <div class="relative bg-gray-50">
            <!-- Banner Image -->
            <div class="w-full h-[400px] md:h-[500px] relative">
                <img src="/HOME_BACKGROUND.png" class="w-full h-full object-cover object-center" alt="MR-Pride Electrical Supplies" />
                <div class="absolute inset-0 bg-black/20"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-gray-50 via-transparent to-transparent"></div>
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 -mt-32 pb-24">
                <div class="text-center max-w-4xl mx-auto bg-white/80 backdrop-blur-xl p-8 md:p-12 rounded-3xl shadow-2xl border border-white/50">
                    <h1 class="text-4xl md:text-6xl font-extrabold tracking-tight mb-6">
                        <span class="block text-gray-900 mb-2">Powering Your</span>
                        <span class="block bg-clip-text text-transparent bg-gradient-to-r from-[#F8B803] to-[#4b5320]">
                            Industrial Future
                        </span>
                    </h1>
                    
                    <p class="mt-4 text-lg md:text-xl text-gray-600 leading-relaxed max-w-2xl mx-auto mb-10">
                        MR-Pride Electrical Supplies delivers high-performance electrical components, innovative project solutions, and unparalleled expertise for modern industrial infrastructure.
                    </p>

                    <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                        <Link 
                            href="/products"
                            class="w-full sm:w-auto px-8 py-4 bg-gradient-to-r from-[#F8B803] to-[#4b5320] text-black font-bold rounded-full hover:shadow-[0_0_20px_rgba(248,184,3,0.3)] transition-all duration-300 transform hover:-translate-y-1"
                        >
                            Browse Products
                        </Link>
                        
                        <Link 
                            href="/quotation"
                            class="w-full sm:w-auto px-8 py-4 bg-white text-gray-900 font-bold rounded-full border border-gray-200 hover:border-[#F8B803] transition-all duration-300 shadow-sm"
                        >
                            Request Quotation
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- About Us Section -->
        <div class="py-24 bg-white relative z-10 border-t border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">ABOUT US</h2>
                    <div class="w-24 h-1 bg-gradient-to-r from-[#F8B803] to-[#4b5320] mx-auto rounded-full"></div>
                </div>

                <div class="max-w-4xl mx-auto text-center">
                    <p class="text-xl text-gray-700 leading-relaxed mb-8">
                        Established in 2020, MR-PRIDE is an electrical trading business that buys and sells electrical materials and equipment to customers such as contractors, engineers, and construction companies.
                    </p>
                    <p class="text-lg text-gray-600 leading-relaxed bg-gray-50 p-8 md:p-12 rounded-3xl border border-gray-100 shadow-sm relative overflow-hidden">
                        <span class="absolute top-0 left-0 w-2 h-full bg-gradient-to-b from-[#F8B803] to-[#4b5320]"></span>
                        An establishment that offers list of quality products that will help to minimize your problems involving Electrical. This company is a fully Filipino Owned that is dedicated to support its clients, and future clients in their needs of any products in line, MR-Pride Electrical Supplies has devoted employees to provide products in good quality considering the words, “right in time”.
                    </p>
                </div>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="py-24 relative overflow-hidden bg-gray-50 border-t border-gray-200">
            <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMSIgY3k9IjEiIHI9IjEiIGZpbGw9IiNjY2MiLz48L3N2Zz4=')] opacity-50"></div>
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
                <h2 class="text-4xl font-bold text-gray-900 mb-6">Ready to Power Up?</h2>
                <p class="text-xl text-gray-600 mb-10">Join hundreds of industrial leaders who trust MR-Pride Electrical Supplies for their critical electrical infrastructure.</p>
                <Link 
                    href="/quotation"
                    class="inline-flex items-center justify-center px-8 py-4 text-lg font-bold bg-gradient-to-r from-[#F8B803] to-[#4b5320] text-black rounded-full hover:shadow-lg hover:-translate-y-1 transition-all duration-300"
                >
                    Get in Touch Today
                </Link>
            </div>
        </div>
    </PublicLayout>
</template>
