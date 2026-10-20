<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative grid min-h-dvh flex-col items-center justify-center bg-gray-50 lg:max-w-none lg:grid-cols-2"
    >
        <!-- Left Side: Image and Branding -->
        <div
            class="relative z-10 hidden h-full flex-col justify-between overflow-hidden p-10 text-white shadow-2xl lg:flex"
        >
            <!-- Background Image -->
            <img
                src="/LOGIN_PHOTO.png"
                class="absolute inset-0 h-full w-full object-cover"
                alt="Industrial Background"
            />

            <!-- Army Green Overlays -->
            <div
                class="absolute inset-0 bg-[#4b5320]/70 mix-blend-multiply"
            ></div>
            <div
                class="absolute inset-0 bg-gradient-to-tr from-[#4b5320] via-[#4b5320]/40 to-transparent opacity-90"
            ></div>

            <!-- Logo area -->
            <Link
                :href="home()"
                class="relative z-20 flex w-max items-center rounded-2xl border border-white/20 bg-white/10 p-4 backdrop-blur-md transition-all duration-300 hover:bg-white/20"
            >
                <div class="mr-3 rounded-xl bg-white p-1.5 shadow-lg">
                    <img
                        src="/LOGO_MR_PRIDE.png"
                        alt="MR-Pride Logo"
                        class="h-12 w-12 object-contain"
                    />
                </div>
                <div class="flex flex-col justify-center leading-none">
                    <span
                        class="text-2xl font-extrabold tracking-tight text-white drop-shadow-md"
                        >MR-PRIDE</span
                    >
                    <span
                        class="mt-1 text-[10px] font-bold tracking-widest text-[#F8B803] uppercase drop-shadow-sm"
                        >Electrical Supplies</span
                    >
                </div>
            </Link>

            <!-- Hero Text -->
            <div class="relative z-20 mt-auto mb-12 max-w-lg">

                <h2
                    class="mb-6 text-4xl leading-tight font-extrabold text-white drop-shadow-lg md:text-5xl"
                >
                    Powering Your <br /><span class="text-[#F8B803]"
                        >Industrial Future</span
                    >
                </h2>
                <p class="text-lg leading-relaxed font-medium text-white/90">
                    Access your secure dashboard to manage inventory, track
                    procurement, and analyze sales with our enterprise
                    electrical supply system.
                </p>
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div
            class="relative z-20 flex min-h-dvh items-center justify-center bg-white p-4 py-10 shadow-[-20px_0_40px_rgba(0,0,0,0.05)] sm:p-8 lg:rounded-l-3xl"
        >
            <div
                class="mx-auto flex w-full flex-col justify-center space-y-8 sm:w-[400px]"
            >
                <div class="mb-4 flex flex-col space-y-2 text-center">
                    <div class="mb-8 flex justify-center lg:hidden">
                        <img
                            src="/LOGO_MR_PRIDE.png"
                            alt="MR-Pride Logo"
                            class="h-20 w-20 object-contain"
                        />
                    </div>
                    <h1
                        class="text-3xl font-extrabold tracking-tight text-gray-900"
                        v-if="title"
                    >
                        {{ title }}
                    </h1>
                    <p
                        class="text-md font-medium text-gray-500"
                        v-if="description"
                    >
                        {{ description }}
                    </p>
                </div>
                <div
                    class="rounded-2xl border border-[#4b5320]/20 bg-[#4b5320] p-6 text-white shadow-lg sm:p-8"
                >
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
