<script setup lang="ts">
// Adapted from Vue Bits FadeContent. See LICENSE.md and README.md in this directory.
import { onMounted, onBeforeUnmount, ref } from 'vue';

const props = withDefaults(
    defineProps<{ duration?: number; delay?: number; threshold?: number }>(),
    {
        duration: 450,
        delay: 0,
        threshold: 0.08,
    },
);
const fadeRef = ref<HTMLElement>();
let observer: IntersectionObserver | undefined;
let animation: Animation | undefined;

onMounted(() => {
    const element = fadeRef.value;

    if (
        !element ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
        !('IntersectionObserver' in window)
    ) {
        return;
    }

    // Content is always readable: animate on arrival rather than hiding off-screen controls.
    observer = new IntersectionObserver(
        (entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                animation = element.animate(
                    [{ opacity: 0.35 }, { opacity: 1 }],
                    {
                        duration: props.duration,
                        delay: props.delay,
                        easing: 'ease-out',
                    },
                );
                observer?.disconnect();
            }
        },
        { threshold: props.threshold },
    );
    observer.observe(element);
});
onBeforeUnmount(() => {
    observer?.disconnect();
    animation?.cancel();
});
</script>

<template>
    <div ref="fadeRef"><slot /></div>
</template>
