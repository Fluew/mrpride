<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    ArrowDown,
    Check,
    Wrench,
    Zap,
    Layers,
    ShieldCheck,
    Search,
    X,
    RotateCcw,
    ChevronDown,
    Filter,
} from '@lucide/vue';
import { onMounted, onUnmounted, ref, computed } from 'vue';
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
        brands: [
            '3M',
            'Honeywell',
            'MSA Safety',
            'Ansell',
            'Kimberly-Clark',
        ],
    },
];

// Search & Filter State
const searchQuery = ref('');
const selectedCategory = ref<string>('all');

const totalProductCount = computed(() => {
    return categories.reduce((sum, c) => sum + c.products.length, 0);
});

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

// Reactive filtering logic
const filteredCategories = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();

    return categories
        .filter((cat) => {
            if (selectedCategory.value !== 'all' && cat.id !== selectedCategory.value) {
                return false;
            }
            return true;
        })
        .map((cat) => {
            if (!q) {
                return {
                    ...cat,
                    matchingProducts: cat.products,
                    matchingBrands: [] as string[],
                    hasMatches: true,
                };
            }

            const catMatches =
                cat.title.toLowerCase().includes(q) ||
                cat.label.toLowerCase().includes(q) ||
                cat.description.toLowerCase().includes(q);

            const matchingProducts = cat.products.filter((p) =>
                p.toLowerCase().includes(q),
            );

            const matchingBrands = cat.brands.filter((b) =>
                b.toLowerCase().includes(q),
            );

            const partnerMatches = getCategoryPartners(cat.code).filter((p) =>
                p.name.toLowerCase().includes(q),
            );

            const hasMatches =
                catMatches ||
                matchingProducts.length > 0 ||
                matchingBrands.length > 0 ||
                partnerMatches.length > 0;

            return {
                ...cat,
                matchingProducts:
                    matchingProducts.length > 0
                        ? matchingProducts
                        : catMatches || matchingBrands.length > 0
                          ? cat.products
                          : [],
                matchingBrands: Array.from(new Set([...matchingBrands, ...partnerMatches.map((p) => p.name)])),
                hasMatches,
            };
        })
        .filter((cat) => cat.hasMatches);
});

const totalMatchesCount = computed(() => {
    return filteredCategories.value.reduce(
        (sum, c) => sum + c.matchingProducts.length,
        0,
    );
});

const resetFilters = () => {
    searchQuery.value = '';
    selectedCategory.value = 'all';
};

const setQuickSearch = (term: string) => {
    searchQuery.value = term;
    selectedCategory.value = 'all';
};

function onCategoryDropdownChange(event: Event) {
    const val = (event.target as HTMLSelectElement).value;
    selectedCategory.value = val;
    if (val !== 'all') {
        const section = document.getElementById(val);
        if (section) {
            section.scrollIntoView({
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
                    ? 'instant'
                    : 'smooth',
                block: 'start',
            });
        }
    }
}

const categorySections = ref<HTMLElement[]>([]);
let observer: IntersectionObserver | undefined;

function scrollToCategory(event: MouseEvent, id: string) {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
        return;
    }

    selectedCategory.value = id;
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
        <!-- Header Banner Section -->
        <header class="relative overflow-hidden border-b border-[#3c421a] bg-black text-white">
            <div class="pointer-events-none absolute inset-0 z-0">
                <img
                    src="/PRODUCT_BACKGROUND.png"
                    alt=""
                    class="h-full w-full object-cover object-right lg:object-center opacity-80"
                    aria-hidden="true"
                />
                <!-- Army green gradient overlay with ~50% opacity -->
                <div
                    class="absolute inset-0 bg-gradient-to-r from-[#4b5320]/80 via-[#4b5320]/50 to-[#4b5320]/30"
                    aria-hidden="true"
                ></div>
                <!-- Contrast gradient for text legibility -->
                <div
                    class="absolute inset-0 bg-gradient-to-r from-black/70 via-black/20 to-transparent"
                    aria-hidden="true"
                ></div>
                <div
                    class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"
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

                <!-- Category Quick Navigation -->
                <nav
                    aria-label="Product categories quick navigation"
                    class="mt-10 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <a
                        v-for="category in categories"
                        :key="category.id"
                        :href="`#${category.id}`"
                        :class="[
                            'group flex items-center gap-3 rounded-xl border px-4 py-4 text-sm font-semibold shadow-sm backdrop-blur-md transition-all focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#F8B803]',
                            selectedCategory === category.id
                                ? 'border-[#F8B803] bg-black/85 text-[#F8B803]'
                                : 'border-white/20 bg-black/50 text-white hover:border-[#F8B803] hover:bg-black/75'
                        ]"
                        @click="scrollToCategory($event, category.id)"
                    >
                        <component
                            :is="category.icon"
                            class="size-5 shrink-0 text-[#F8B803] transition-transform group-hover:scale-110"
                            aria-hidden="true"
                        />
                        {{ category.label }}
                        <ArrowDown
                            class="ml-auto size-4 shrink-0 text-gray-300 transition-colors group-hover:text-[#F8B803]"
                            aria-hidden="true"
                        />
                    </a>
                </nav>
            </div>
        </header>

        <!-- Search & Filter Sticky Bar -->
        <div class="sticky top-20 z-30 border-b border-gray-200 bg-white/95 backdrop-blur-md shadow-xs">
            <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-3.5 lg:flex-row lg:items-center lg:justify-between">
                    
                    <!-- Search Input -->
                    <div class="relative flex-1 max-w-xl">
                        <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-gray-400 pointer-events-none" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search by product, brand, equipment (e.g. Breaker, Makita, PVC, Welding)..."
                            class="w-full rounded-xl border border-gray-200 bg-gray-50/80 py-2.5 pl-10 pr-9 text-sm text-gray-900 placeholder:text-gray-400 focus:border-[#F8B803] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#F8B803]/20 shadow-xs transition-all"
                        />
                        <button
                            v-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 p-0.5 rounded-full"
                            title="Clear search"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <!-- Category Dropdown Filter beside Search Bar -->
                    <div class="relative w-full lg:w-72 shrink-0">
                        <Filter class="absolute left-3.5 top-1/2 -translate-y-1/2 size-4 text-[#4b5320] pointer-events-none" />
                        <select
                            :value="selectedCategory"
                            @change="onCategoryDropdownChange"
                            class="w-full appearance-none rounded-xl border border-gray-200 bg-gray-50/80 hover:bg-white py-2.5 pl-9 pr-9 text-sm font-semibold text-gray-900 focus:border-[#F8B803] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#F8B803]/20 shadow-xs cursor-pointer transition-all"
                        >
                            <option value="all">All Categories ({{ totalProductCount }})</option>
                            <option
                                v-for="cat in categories"
                                :key="cat.id"
                                :value="cat.id"
                            >
                                {{ cat.label }} ({{ cat.products.length }})
                            </option>
                        </select>
                        <ChevronDown class="absolute right-3.5 top-1/2 -translate-y-1/2 size-4 text-gray-400 pointer-events-none" />
                    </div>
                </div>

                <!-- Active Filter Feedback & Reset -->
                <div v-if="searchQuery || selectedCategory !== 'all'" class="mt-3 flex items-center justify-between border-t border-gray-100 pt-2.5 text-xs text-gray-500">
                    <div class="flex items-center gap-2">
                        <span>
                            Showing <strong class="text-gray-900">{{ totalMatchesCount }}</strong> items in 
                            <strong class="text-gray-900">{{ filteredCategories.length }}</strong> categories
                        </span>
                        <span v-if="searchQuery">
                            matching "<span class="font-semibold text-[#4b5320]">{{ searchQuery }}</span>"
                        </span>
                    </div>
                    <button
                        @click="resetFilters"
                        class="inline-flex items-center gap-1 text-[#4b5320] font-semibold hover:underline cursor-pointer"
                    >
                        <RotateCcw class="size-3" />
                        Reset filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Product Sections Container -->
        <div
            class="mx-auto flex max-w-7xl flex-col gap-10 px-4 py-12 sm:gap-14 sm:px-6 lg:px-8 lg:py-16"
        >
            <!-- No Matches Empty State -->
            <div v-if="filteredCategories.length === 0" class="rounded-3xl border border-gray-200 bg-white p-10 sm:p-14 text-center shadow-sm">
                <div class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-[#4b5320]/10 text-[#4b5320] mb-4">
                    <Search class="size-8" />
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">No products or brands found</h3>
                <p class="text-sm text-gray-500 max-w-md mx-auto mb-6">
                    We couldn't find any products or brand partners matching "<strong class="text-gray-800">{{ searchQuery }}</strong>".
                </p>
                <div class="flex flex-wrap justify-center items-center gap-2 mb-8">
                    <span class="text-xs text-gray-400 font-medium">Try searching for:</span>
                    <button
                        v-for="term in ['Circuit Breakers', 'DeWalt', 'Generators', 'EMT Pipes', 'Makita', 'Fire Extinguishers', 'Himel', 'Transformers']"
                        :key="term"
                        @click="setQuickSearch(term)"
                        class="rounded-lg bg-gray-100 hover:bg-[#F8B803]/20 hover:text-gray-900 px-3 py-1 text-xs font-semibold text-gray-700 transition-colors cursor-pointer"
                    >
                        {{ term }}
                    </button>
                </div>
                <button
                    @click="resetFilters"
                    class="rounded-xl bg-[#4b5320] px-6 py-3 text-xs font-bold text-white shadow-sm hover:bg-[#3d441a] transition-all cursor-pointer inline-flex items-center gap-1.5"
                >
                    <RotateCcw class="size-3.5" />
                    Reset all filters
                </button>
            </div>

            <!-- Categories List -->
            <section
                v-for="category in filteredCategories"
                :id="category.id"
                :key="category.id"
                ref="categorySections"
                tabindex="-1"
                :aria-labelledby="`${category.id}-title`"
                class="category-section scroll-mt-36 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition-[box-shadow,border-color] duration-300 hover:border-[#4b5320]/30 hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#4b5320]"
            >
                <div
                    class="grid gap-8 p-6 sm:p-9 lg:grid-cols-[1.15fr_1fr] lg:gap-12 lg:p-10"
                >
                    <div>
                        <div
                            class="mb-5 flex items-center gap-3 text-xs font-bold tracking-[0.16em] text-[#4b5320] uppercase"
                        >
                            <span
                                class="flex size-9 items-center justify-center rounded-lg bg-[#4b5320]/10 text-sm font-black"
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

                        <!-- Matching Brands Highlight when searching -->
                        <div v-if="category.matchingBrands && category.matchingBrands.length > 0 && searchQuery" class="mt-5 p-3.5 rounded-xl bg-[#F8B803]/10 border border-[#F8B803]/25 text-xs text-gray-800">
                            <span class="font-bold text-gray-900 uppercase tracking-wider text-[11px] block mb-1">Matching Brand Partners:</span>
                            <div class="flex flex-wrap gap-1.5 mt-1">
                                <span v-for="b in category.matchingBrands" :key="b" class="px-2 py-0.5 rounded-md bg-white text-gray-900 font-semibold border border-[#F8B803]/30 shadow-2xs">
                                    {{ b }}
                                </span>
                            </div>
                        </div>

                        <!-- Product List -->
                        <div class="mt-7 flex items-center justify-between">
                            <h3
                                class="text-xs font-bold tracking-widest text-gray-500 uppercase"
                            >
                                Our product range
                            </h3>
                            <span v-if="searchQuery" class="text-xs text-emerald-600 font-medium">
                                {{ category.matchingProducts.length }} {{ category.matchingProducts.length === 1 ? 'item' : 'items' }} found
                            </span>
                        </div>

                        <ul class="mt-4 grid gap-x-5 gap-y-3 sm:grid-cols-2">
                            <li
                                v-for="product in category.matchingProducts"
                                :key="product"
                                class="flex items-start gap-2 text-sm leading-6 text-gray-700"
                            >
                                <Check
                                    class="mt-1 size-4 shrink-0 text-[#4b5320]"
                                    aria-hidden="true"
                                />
                                <span v-if="searchQuery && product.toLowerCase().includes(searchQuery.toLowerCase())" class="font-semibold text-gray-900 bg-[#F8B803]/25 px-1.5 py-0.5 rounded">
                                    {{ product }}
                                </span>
                                <span v-else>
                                    {{ product }}
                                </span>
                            </li>
                        </ul>
                    </div>

                    <figure
                        class="group flex min-w-0 flex-col self-start overflow-hidden rounded-xl border border-gray-200 bg-white lg:sticky lg:top-36"
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

                <!-- Brand Partners Carousel -->
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
