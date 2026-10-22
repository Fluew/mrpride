<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useSidebar } from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

export interface LineSidebarProps {
  items: NavItem[];
  label?: string;
  accentColor?: string;
  proximityRadius?: number;
  maxShift?: number;
  displayNumbers?: boolean;
}

const props = withDefaults(defineProps<LineSidebarProps>(), {
  label: 'Operations',
  accentColor: '#F8B803',
  proximityRadius: 130,
  maxShift: 5,
  displayNumbers: true,
});

const { isCurrentUrl } = useCurrentUrl();
const { isMobile, setOpenMobile, state } = useSidebar();

const isCollapsed = computed(() => state.value === 'collapsed');

function closeOnMobile() {
  if (isMobile.value) {
    setOpenMobile(false);
  }
}

// Proximity interactive tracking
const listContainerRef = ref<HTMLElement | null>(null);
const itemRefs = ref<HTMLElement[]>([]);

const cursorY = ref<number | null>(null);
const isHovering = ref(false);

// Proximity scores per item (0 to 1)
const proximityScores = ref<number[]>(props.items.map(() => 0));
let targetScores = props.items.map(() => 0);
let animationFrameId: number | null = null;

function setItemRef(el: any, index: number) {
  if (el) {
    itemRefs.value[index] = el.$el || el;
  }
}

function handleMouseMove(e: MouseEvent) {
  if (!listContainerRef.value) return;
  const containerRect = listContainerRef.value.getBoundingClientRect();
  cursorY.value = e.clientY - containerRect.top;
  isHovering.value = true;

  updateTargetScores();
}

function handleMouseLeave() {
  isHovering.value = false;
  cursorY.value = null;
  targetScores = props.items.map(() => 0);
}

function updateTargetScores() {
  if (!isHovering.value || cursorY.value === null || !listContainerRef.value) {
    targetScores = props.items.map(() => 0);
    return;
  }

  const radius = props.proximityRadius;
  const containerTop = listContainerRef.value.getBoundingClientRect().top;

  targetScores = props.items.map((_, i) => {
    const el = itemRefs.value[i];
    if (!el) return 0;
    const rect = el.getBoundingClientRect();
    const itemCenter = rect.top + rect.height / 2 - containerTop;
    const distance = Math.abs((cursorY.value as number) - itemCenter);

    if (distance >= radius) return 0;
    const t = 1 - distance / radius;
    // Cubic smoothstep easing
    return t * t * (3 - 2 * t);
  });
}

// Lerp animation loop for organic fluid transitions
function startAnimationLoop() {
  const tick = () => {
    let hasChanged = false;
    for (let i = 0; i < proximityScores.value.length; i++) {
      const current = proximityScores.value[i] || 0;
      const target = targetScores[i] || 0;
      const diff = target - current;

      if (Math.abs(diff) > 0.003) {
        proximityScores.value[i] = current + diff * 0.18;
        hasChanged = true;
      } else if (current !== target) {
        proximityScores.value[i] = target;
        hasChanged = true;
      }
    }

    animationFrameId = requestAnimationFrame(tick);
  };

  animationFrameId = requestAnimationFrame(tick);
}

onMounted(() => {
  startAnimationLoop();
});

onBeforeUnmount(() => {
  if (animationFrameId !== null) {
    cancelAnimationFrame(animationFrameId);
  }
});
</script>

<template>
  <div class="line-sidebar-container flex flex-col w-full select-none">
    <!-- Category Eyebrow Label -->
    <div
      v-if="label"
      class="mb-2 px-3 text-[10px] font-bold tracking-[0.16em] text-white/50 uppercase transition-opacity duration-300 group-data-[collapsible=icon]:hidden"
    >
      {{ label }}
    </div>

    <!-- Navigation List with Proximity Trackers -->
    <nav
      ref="listContainerRef"
      @mousemove="handleMouseMove"
      @mouseleave="handleMouseLeave"
      class="relative flex flex-col gap-1 w-full"
      aria-label="Admin Operations"
    >
      <div
        v-for="(item, index) in items"
        :key="item.title"
        :ref="(el) => setItemRef(el, index)"
        class="relative w-full group/item"
      >
        <Link
          :href="item.href"
          prefetch
          @click="closeOnMobile"
          :aria-current="isCurrentUrl(item.href) ? 'page' : undefined"
          :title="isCollapsed ? item.title : undefined"
          class="relative flex items-center w-full min-h-11 rounded-xl px-3 transition-colors duration-200 outline-none focus-visible:ring-2 focus-visible:ring-[#F8B803] group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0 group-data-[collapsible=icon]:size-10 group-data-[collapsible=icon]:mx-auto"
          :class="[
            isCurrentUrl(item.href)
              ? 'bg-[#F8B803] font-bold text-gray-950 shadow-sm'
              : 'text-white/80 hover:text-white hover:bg-white/10'
          ]"
          :style="{
            transform: !isCollapsed && proximityScores[index]
              ? `translateX(${proximityScores[index] * maxShift}px)`
              : undefined,
          }"
        >

          <!-- Item Index Numbering (e.g. 01, 02) -->
          <span
            v-if="displayNumbers && !isCollapsed"
            class="text-[10px] font-mono tracking-wider transition-colors duration-200 mr-2 group-data-[collapsible=icon]:hidden"
            :class="[
              isCurrentUrl(item.href)
                ? 'text-gray-950/70 font-bold'
                : 'text-white/40 group-hover/item:text-[#F8B803]'
            ]"
          >
            0{{ index + 1 }}
          </span>

          <!-- Lucide Icon with Proximity Scale Effect -->
          <component
            v-if="item.icon"
            :is="item.icon"
            class="size-4.5 shrink-0 transition-transform duration-200"
            :class="[
              isCurrentUrl(item.href)
                ? 'text-gray-950 stroke-[2.2]'
                : 'text-white/85 group-hover/item:text-white stroke-[1.8]'
            ]"
            :style="{
              transform: proximityScores[index] > 0.1 && !isCurrentUrl(item.href)
                ? `scale(${1 + proximityScores[index] * 0.15})`
                : undefined,
            }"
          />

          <!-- Navigation Label Title -->
          <span
            class="ml-3 truncate text-xs sm:text-sm tracking-tight transition-opacity duration-200 group-data-[collapsible=icon]:hidden"
            :class="[
              isCurrentUrl(item.href)
                ? 'text-gray-950 font-bold'
                : 'text-white/85 group-hover/item:text-white font-medium'
            ]"
          >
            {{ item.title }}
          </span>
        </Link>
      </div>
    </nav>
  </div>
</template>

<style scoped>
.line-sidebar-container {
  will-change: transform;
}
</style>
