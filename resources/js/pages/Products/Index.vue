<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ArrowDown,
    Check,
    Wrench,
    Zap,
    Layers,
    ShieldCheck,
} from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';
import LogoLoop from '@/components/ui/LogoLoop.vue';
import {
    hardwarePartners,
    electricalPartners,
    pipePartners,
    safetyPartners,
} from '@/components/products/brandPartnersData';
import PublicLayout from '@/layouts/PublicLayout.vue';

const categories = [
    {
        id: 'hardware-machinery',
        label: 'Hardware & Machinery',
        code: 'A',
        icon: Wrench,
        title: 'Hardware, Power Tools, Hand Tools & Machineries',
        description:
            'Equip every job with dependable tools and machinery, from everyday repairs to demanding workshop and construction projects.',
        image: '/PRODUCT_A.png',
        imageAlt:
            'Hardware and machinery brand collection supplied by MR-Pride',
        caption: 'Tools & machinery • Our brand collection',
        products: [
            'Portable Welding Machines',
            'Portable Diesel Generators',
            'Portable Gasoline Generators',
            'Diesel Engines',
            'Gasoline Engines',
            'Electric Motors',
            'Submersible Pumps',
            'Water Pumps',
            'Power Tools',
            'Hand Tools',
            'Other related hardware and machinery',
        ],
        brands: [
            'DeWalt',
            'Makita',
            'Powerhouse',
            'Stanley',
            'STIHL',
            'Yale',
            'Zekoki',
            'Jr. Kawasaki',
            'Lotus',
            'Yamato',
            'Butterfly',
            'Tacoma',
            'Hoyoma',
            'DCA',
            'Ingco',
        ],
    },
    {
        id: 'electrical-materials',
        label: 'Electrical Materials',
        code: 'B',
        icon: Zap,
        title: 'Electrical Materials',
        description:
            'Find the essentials for reliable electrical installations, power distribution, and lighting in residential, commercial, and industrial spaces.',
        image: '/PRODUCT_B.png',
        imageAlt: 'Electrical materials brand collection supplied by MR-Pride',
        caption: 'Electrical supplies • Our brand collection',
        products: [
            'Circuit Breakers',
            'Electrical Wires and Cables',
            'Lighting Products',
            'Switches',
            'Electrical Outlets',
            'Panel Boards',
            'Power Transformers',
            'Other electrical materials and accessories',
        ],
        brands: [
            'Himel',
            'ROYU',
            'Panasonic',
            'Phelps Dodge',
            'General Electric',
            'Koten',
            'Schneider Electric',
            'Philflex',
            'Duraflex',
            'Hypertech',
            'Pureflex',
            'Paciflex',
        ],
    },
    {
        id: 'pipes-conduits',
        label: 'Pipes & Conduits',
        code: 'C',
        icon: Layers,
        title: 'Pipes & Conduits',
        description:
            'Build a dependable foundation for piping and electrical systems with pipes, conduits, and fittings for a wide range of installations.',
        image: '/PRODUCT_C.png',
        imageAlt: 'Pipe and conduit brand collection supplied by MR-Pride',
        caption: 'Pipes & conduits • Our brand collection',
        products: [
            'EMT Pipes',
            'PVC Pipes',
            'IMC Pipes',
            'RSC Pipes',
            'Conduits',
            'Fittings',
            'Other related piping and electrical conduit products',
        ],
        brands: [
            'Crown Pipes',
            'Emerald',
            'Neltex',
            'Nichi',
            'McGill',
            'Dupro',
            'Nikon',
            'Panasonic',
            'Atlanta',
        ],
    },
    {
        id: 'safety-products',
        label: 'Safety Products',
        code: 'D',
        icon: ShieldCheck,
        title: 'Safety Products',
        description:
            'Support a safer workplace with fire protection, protective equipment, and safety essentials for industrial and electrical work.',
        image: '/PRODUCT_D.png',
        imageAlt: 'A selection of green and red fire extinguishers',
        caption: 'Workplace essentials • Fire protection & safety',
        products: [
            'Fire Extinguishers',
            'Safety Equipment',
            'Protective Equipment',
            'Other industrial and electrical safety products',
        ],
        brands: [],
    },
];


function getCategoryPartners(code: string) {
    switch (code) {
        case 'A':
            return hardwarePartners;
        case 'B':
            return electricalPartners;
        case 'C':
            return pipePartners;
        case 'D':
            return safetyPartners;
        default:
            return [];
    }
}

function getCategoryDirection(code: string): 'left' | 'right' {
    switch (code) {
        case 'B':
        case 'D':
            return 'right';
        case 'A':
        case 'C':
        default:
            return 'left';
    }
}

const categorySections = ref<HTMLElement[]>([]);
let observer: IntersectionObserver | undefined;

function scrollToCategory(event: MouseEvent, id: string) {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
        return;
    }

    const section = document.getElementById(id);

    if (!section) {
        return;
    }

    event.preventDefault();
    window.history.replaceState(window.history.state, '', `#${id}`);
    section.focus({ preventScroll: true });
    section.scrollIntoView({
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
            ? 'instant'
            : 'smooth',
        block: 'start',
    });
}

onMounted(() => {
    if (
        window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
        !('IntersectionObserver' in window)
    ) {
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer?.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.05 },
    );
    categorySections.value.forEach((section) => {
        if (section.getBoundingClientRect().top > window.innerHeight) {
            section.classList.add('reveal-ready');
        }

        observer?.observe(section);
    });
});
onUnmounted(() => observer?.disconnect());
</script>

<template>
    <Head title="Product List | MR-Pride Electrical Supplies">
        <meta
            name="description"
            content="Explore MR-Pride's hardware, power tools, electrical materials, pipes, conduits, and safety products from trusted brand partners."
        />
    </Head>
    <PublicLayout>
        <header class="relative overflow-hidden border-b border-gray-800 bg-black text-white">
            <!-- Header Background Photo: PRODUCT_BACKGROUND.png with 70% opacity -->
            <div class="pointer-events-none absolute inset-0 z-0">
                <img
                    src="/PRODUCT_BACKGROUND.png"
                    alt=""
                    class="h-full w-full object-cover object-right lg:object-center opacity-70"
                    aria-hidden="true"
                />
                <div
                    class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/40 to-transparent"
                    aria-hidden="true"
                ></div>
            </div>

            <div
                class="relative z-10 mx-auto max-w-7xl px-4 pt-14 pb-10 sm:px-6 sm:pt-20 lg:px-8"
            >
                <p
                    class="mb-5 flex items-center gap-3 text-xs font-bold tracking-[0.2em] text-[#F8B803] uppercase"
                >
                    <span
                        class="h-0.5 w-9 bg-[#F8B803]"
                        aria-hidden="true"
                    ></span
                    >MR-Pride Electrical Supplies
                </p>
                <div
                    class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end"
                >
                    <div class="max-w-3xl">
                        <h1
                            class="text-4xl font-extrabold tracking-tight text-white sm:text-6xl"
                        >
                            PRODUCT LIST<span class="text-[#F8B803]">.</span>
                        </h1>
                        <p
                            class="mt-5 max-w-2xl text-base leading-8 text-gray-200 sm:text-lg"
                        >
                            Explore our wide range of hardware, electrical,
                            industrial, piping, and safety products from trusted
                            brands.
                        </p>
                    </div>
                    <p class="flex items-center gap-3 text-sm font-medium text-gray-300">
                        <span class="text-3xl font-semibold text-[#F8B803]"
                            >04</span
                        >Product categories
                    </p>
                </div>
                <nav
                    aria-label="Product categories"
                    class="mt-10 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <a
                        v-for="category in categories"
                        :key="category.id"
                        :href="`#${category.id}`"
                        class="group flex items-center gap-3 rounded-xl border border-white/20 bg-black/50 px-4 py-4 text-sm font-semibold text-white shadow-sm backdrop-blur-md transition-all hover:border-[#F8B803] hover:bg-black/75 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#F8B803]"
                        @click="scrollToCategory($event, category.id)"
                    >
                        <component
                            :is="category.icon"
                            class="size-5 shrink-0 text-[#F8B803] transition-transform group-hover:scale-110"
                            aria-hidden="true"
                        />
                        {{ category.label
                        }}<ArrowDown
                            class="ml-auto size-4 shrink-0 text-gray-300 transition-colors group-hover:text-[#F8B803]"
                            aria-hidden="true"
                        />
                    </a>
                </nav>
            </div>
        </header>
        <div
            class="mx-auto flex max-w-7xl flex-col gap-10 px-4 py-12 sm:gap-14 sm:px-6 lg:px-8 lg:py-16"
        >
            <section
                v-for="category in categories"
                :id="category.id"
                :key="category.id"
                ref="categorySections"
                tabindex="-1"
                :aria-labelledby="`${category.id}-title`"
                class="category-section scroll-mt-28 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition-[box-shadow,border-color] duration-300 hover:border-[#4b5320]/30 hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#4b5320]"
            >
                <div
                    class="grid gap-8 p-6 sm:p-9 lg:grid-cols-[1.15fr_1fr] lg:gap-12 lg:p-10"
                >
                    <div>
                        <div
                            class="mb-5 flex items-center gap-3 text-xs font-bold tracking-[0.16em] text-[#4b5320] uppercase"
                        >
                            <span
                                class="flex size-9 items-center justify-center rounded-lg bg-[#4b5320]/10 text-sm"
                                >{{ category.code }}</span
                            >Product category
                        </div>
                        <h2
                            :id="`${category.id}-title`"
                            class="max-w-xl text-2xl leading-tight font-bold tracking-tight text-gray-900 uppercase sm:text-3xl"
                        >
                            {{ category.title }}
                        </h2>
                        <p
                            class="mt-4 text-sm leading-7 text-gray-600 sm:text-base"
                        >
                            {{ category.description }}
                        </p>
                        <h3
                            class="mt-7 text-xs font-bold tracking-widest text-gray-500 uppercase"
                        >
                            Our product range
                        </h3>
                        <ul class="mt-4 grid gap-x-5 gap-y-3 sm:grid-cols-2">
                            <li
                                v-for="product in category.products"
                                :key="product"
                                class="flex items-start gap-2 text-sm leading-6 text-gray-700"
                            >
                                <Check
                                    class="mt-1 size-4 shrink-0 text-[#4b5320]"
                                    aria-hidden="true"
                                />{{ product }}
                            </li>
                        </ul>
                    </div>
                    <figure
                        class="group flex min-w-0 flex-col self-start overflow-hidden rounded-xl border border-gray-200 bg-white lg:sticky lg:top-28"
                    >
                        <div
                            class="flex aspect-[6/5] items-center justify-center overflow-hidden p-6 sm:p-8"
                        >
                            <img
                                :src="category.image"
                                :alt="category.imageAlt"
                                :loading="
                                    category.code === 'A' ? 'eager' : 'lazy'
                                "
                                decoding="async"
                                class="h-full w-full object-contain transition-transform duration-500 group-hover:scale-[1.03] motion-reduce:transform-none motion-reduce:transition-none"
                            />
                        </div>
                        <figcaption
                            class="border-t border-gray-100 bg-gray-50 px-5 py-4 text-xs leading-5 font-medium text-gray-500"
                        >
                            {{ category.caption }}
                        </figcaption>
                    </figure>
                </div>
                <div class="border-t border-gray-100 bg-gray-50/70 px-4 py-8 sm:px-8 sm:py-10">
                    <div class="mb-5 flex items-center justify-between">
                        <h3 class="text-xs sm:text-sm font-bold tracking-widest text-gray-900 uppercase">
                            Brand Partners
                        </h3>
                        <span class="text-[11px] font-semibold tracking-wider text-gray-400 uppercase hidden sm:inline">
                            Authorized Manufacturers & Suppliers
                        </span>
                    </div>
                    <LogoLoop
                        :logos="getCategoryPartners(category.code)"
                        :speed="70"
                        :direction="getCategoryDirection(category.code)"
                        :logo-height="55"
                        :gap="50"
                        :hover-speed="0"
                        :scale-on-hover="true"
                        :fade-out="true"
                        fade-out-color="#f9fafb"
                        :aria-label="`${category.label} brand partners`"
                        class="py-2"
                    />
                </div>
            </section>
        </div>
    </PublicLayout>
</template>

<style scoped>
.reveal-ready {
    opacity: 0;
    transform: translateY(18px);
}
.reveal-ready.is-visible {
    opacity: 1;
    transform: translateY(0);
    transition:
        opacity 550ms ease,
        transform 550ms ease;
}
@media (prefers-reduced-motion: reduce) {
    .category-section {
        transition: none;
    }
    .reveal-ready {
        opacity: 1;
        transform: none;
    }
    .reveal-ready.is-visible {
        transition: none;
    }
}
</style>
