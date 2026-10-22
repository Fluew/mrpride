import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const isStarting = ref(true);
const isNavigating = ref(false);
let initialized = false;
let navigationTimer: ReturnType<typeof setTimeout> | null = null;
let navigationStartTime = 0;
const MIN_NAVIGATION_DURATION = 450; // Smooth, perceived transition duration

export function useCustomerLoading() {
    function init() {
        if (initialized) return;
        initialized = true;

        if (typeof window !== 'undefined') {
            const hideInitial = () => {
                setTimeout(() => {
                    isStarting.value = false;
                }, 750);
            };

            if (document.readyState === 'complete') {
                hideInitial();
            } else {
                window.addEventListener('load', hideInitial, { once: true });
                // Fallback timeout in case load event already fired or is delayed
                setTimeout(hideInitial, 1200);
            }

            // Route transition events
            router.on('start', () => {
                navigationStartTime = Date.now();
                if (navigationTimer) {
                    clearTimeout(navigationTimer);
                    navigationTimer = null;
                }
                isNavigating.value = true;
            });

            router.on('finish', () => {
                const elapsed = Date.now() - navigationStartTime;
                const remaining = Math.max(
                    0,
                    MIN_NAVIGATION_DURATION - elapsed,
                );
                if (navigationTimer) clearTimeout(navigationTimer);
                navigationTimer = setTimeout(() => {
                    isNavigating.value = false;
                }, remaining);
            });
        }
    }

    const isVisible = computed(() => isStarting.value || isNavigating.value);

    return {
        isStarting,
        isNavigating,
        isVisible,
        init,
    };
}
