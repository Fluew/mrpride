<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

const page = usePage();
const name = page.props.name;

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="relative grid h-dvh flex-col items-center justify-center lg:max-w-none lg:grid-cols-2 bg-gray-50">
        <!-- Left Side: Image and Branding -->
        <div class="relative hidden h-full flex-col p-10 text-white lg:flex justify-between overflow-hidden shadow-2xl z-10">
            <!-- Background Image -->
            <img src="/LOGIN_PHOTO.png" class="absolute inset-0 w-full h-full object-cover" alt="Industrial Background" />
            
            <!-- Army Green Overlays -->
            <div class="absolute inset-0 bg-[#4b5320]/70 mix-blend-multiply"></div>
            <div class="absolute inset-0 bg-gradient-to-tr from-[#4b5320] via-[#4b5320]/40 to-transparent opacity-90"></div>
            
            <!-- Logo area -->
            <Link :href="home()" class="relative z-20 flex items-center bg-white/10 backdrop-blur-md p-4 rounded-2xl border border-white/20 w-max hover:bg-white/20 transition-all duration-300">
                <div class="bg-white p-1.5 rounded-xl mr-3 shadow-lg">
                    <img src="/LOGO_MR_PRIDE.png" alt="MR-Pride Logo" class="w-12 h-12 object-contain" />
                </div>
                <div class="flex flex-col justify-center leading-none">
                    <span class="text-2xl font-extrabold tracking-tight text-white drop-shadow-md">MR-PRIDE</span>
                    <span class="text-[10px] font-bold tracking-widest text-[#F8B803] uppercase mt-1 drop-shadow-sm">Electrical Supplies</span>
                </div>
            </Link>

            <!-- Hero Text -->
            <div class="relative z-20 mt-auto mb-12 max-w-lg">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 backdrop-blur-md border border-white/30 text-white text-sm font-bold mb-6">
                    <span class="w-2 h-2 rounded-full bg-[#F8B803] animate-pulse"></span>
                    Admin Portal
                </div>
                <h2 class="text-4xl md:text-5xl font-extrabold mb-6 text-white leading-tight drop-shadow-lg">
                    Powering Your <br/><span class="text-[#F8B803]">Industrial Future</span>
                </h2>
                <p class="text-lg text-white/90 font-medium leading-relaxed">
                    Access your secure dashboard to manage inventory, track procurement, and analyze sales with our enterprise electrical supply system.
                </p>
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div class="h-full flex items-center justify-center p-8 bg-white lg:rounded-l-3xl relative z-20 shadow-[-20px_0_40px_rgba(0,0,0,0.05)]">
            <div class="mx-auto flex w-full flex-col justify-center space-y-8 sm:w-[400px]">
                <div class="flex flex-col space-y-2 text-center mb-4">
                    <div class="lg:hidden flex justify-center mb-8">
                        <img src="/LOGO_MR_PRIDE.png" alt="MR-Pride Logo" class="w-20 h-20 object-contain" />
                    </div>
                    <h1 class="text-3xl font-extrabold tracking-tight text-gray-900" v-if="title">
                        {{ title }}
                    </h1>
                    <p class="text-md text-gray-500 font-medium" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <div class="bg-[#4b5320] text-white p-8 rounded-2xl shadow-2xl border border-[#4b5320]/20">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
