<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/LOGO_MR_PRIDE.png"
        alt="MR-Pride Logo"
        :class="className"
        v-bind="$attrs"
    />
</template>
