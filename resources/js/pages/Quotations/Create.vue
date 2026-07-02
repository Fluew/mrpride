<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

const form = ref({
    company: '',
    contactName: '',
    email: '',
    phone: '',
    projectType: 'Commercial',
    timeline: '1-3 months',
    requirements: ''
});

const submit = () => {
    // Implement form submission logic here
    console.log('Form submitted:', form.value);
    alert('Thank you for your quotation request. Our team will contact you shortly.');
};
</script>

<template>
    <Head title="Request Quotation | MRP ElectroDesk" />

    <PublicLayout>
        <div class="relative min-h-screen bg-[#0a0a0a] py-20">
            <!-- Decorative background elements -->
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute -top-[10%] -right-[10%] w-[50%] h-[50%] bg-[#F8B803] opacity-10 blur-[120px] rounded-full"></div>
                <div class="absolute top-[40%] -left-[10%] w-[40%] h-[40%] bg-[#4b5320] opacity-10 blur-[100px] rounded-full"></div>
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="flex flex-col lg:flex-row gap-16 items-start">
                    
                    <!-- Text Content -->
                    <div class="w-full lg:w-5/12 lg:sticky lg:top-32">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#1a1a1a] border border-gray-800 text-[#F8B803] text-sm font-medium mb-6">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Get an Estimate
                        </div>
                        <h1 class="text-4xl md:text-5xl font-extrabold text-white mb-6 leading-tight">
                            Let's Power Your Next Big Project.
                        </h1>
                        <p class="text-gray-400 text-lg mb-8">
                            Fill out the form with your project details, and our technical sales team will provide a comprehensive quotation tailored to your specific requirements.
                        </p>

                        <div class="space-y-6">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-full bg-[#111] border border-gray-800 flex items-center justify-center text-[#F8B803]">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-white font-semibold">Call Us Directly</h4>
                                    <p class="text-gray-500">+1 (555) 123-4567</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-full bg-[#111] border border-gray-800 flex items-center justify-center text-[#F8B803]">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-white font-semibold">Email Us</h4>
                                    <p class="text-gray-500">sales@mrpelectrodesk.com</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form -->
                    <div class="w-full lg:w-7/12 bg-[#111] border border-gray-800 rounded-2xl p-8 md:p-10 shadow-2xl relative">
                        <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-[#F8B803] to-[#4b5320] rounded-t-2xl"></div>
                        
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-2">Company Name *</label>
                                    <input 
                                        v-model="form.company" 
                                        type="text" 
                                        required
                                        class="w-full bg-[#1a1a1a] border border-gray-800 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-[#F8B803] focus:ring-1 focus:ring-[#F8B803] transition-colors"
                                        placeholder="Enter company name"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-2">Contact Name *</label>
                                    <input 
                                        v-model="form.contactName" 
                                        type="text" 
                                        required
                                        class="w-full bg-[#1a1a1a] border border-gray-800 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-[#F8B803] focus:ring-1 focus:ring-[#F8B803] transition-colors"
                                        placeholder="John Doe"
                                    >
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-2">Email Address *</label>
                                    <input 
                                        v-model="form.email" 
                                        type="email" 
                                        required
                                        class="w-full bg-[#1a1a1a] border border-gray-800 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-[#F8B803] focus:ring-1 focus:ring-[#F8B803] transition-colors"
                                        placeholder="john@example.com"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-2">Phone Number *</label>
                                    <input 
                                        v-model="form.phone" 
                                        type="tel" 
                                        required
                                        class="w-full bg-[#1a1a1a] border border-gray-800 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-[#F8B803] focus:ring-1 focus:ring-[#F8B803] transition-colors"
                                        placeholder="+1 (555) 000-0000"
                                    >
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-2">Project Type</label>
                                    <select 
                                        v-model="form.projectType"
                                        class="w-full bg-[#1a1a1a] border border-gray-800 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-[#F8B803] focus:ring-1 focus:ring-[#F8B803] transition-colors appearance-none cursor-pointer"
                                    >
                                        <option>Commercial Building</option>
                                        <option>Industrial Facility</option>
                                        <option>Infrastructure/Public Works</option>
                                        <option>Residential Complex</option>
                                        <option>Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-400 mb-2">Expected Timeline</label>
                                    <select 
                                        v-model="form.timeline"
                                        class="w-full bg-[#1a1a1a] border border-gray-800 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-[#F8B803] focus:ring-1 focus:ring-[#F8B803] transition-colors appearance-none cursor-pointer"
                                    >
                                        <option>Immediate (ASAP)</option>
                                        <option>1-3 months</option>
                                        <option>3-6 months</option>
                                        <option>Over 6 months</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-2">Project Requirements & Materials Needed *</label>
                                <textarea 
                                    v-model="form.requirements"
                                    required
                                    rows="5"
                                    class="w-full bg-[#1a1a1a] border border-gray-800 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-[#F8B803] focus:ring-1 focus:ring-[#F8B803] transition-colors resize-none"
                                    placeholder="Please provide details about the components, quantities, or general scope of your electrical needs..."
                                ></textarea>
                            </div>

                            <div class="pt-4">
                                <button 
                                    type="submit"
                                    class="w-full flex justify-center py-4 px-8 border border-transparent rounded-lg shadow-sm text-lg font-bold text-black bg-gradient-to-r from-[#F8B803] to-[#4b5320] hover:from-[#4b5320] hover:to-[#F8B803] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#F8B803] transition-all transform hover:-translate-y-0.5"
                                >
                                    Submit Request
                                </button>
                                <p class="text-xs text-gray-600 text-center mt-4">
                                    By submitting this form, you agree to our privacy policy and terms of service.
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
