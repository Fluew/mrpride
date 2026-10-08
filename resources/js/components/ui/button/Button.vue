<script setup lang="ts">
import type { PrimitiveProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import type { ButtonVariants } from "."
import { Primitive } from "reka-ui"
import { cn } from "@/lib/utils"
import { buttonVariants } from "."
import SpecularButton from "@/components/ui/SpecularButton.vue"

interface Props extends PrimitiveProps {
  variant?: ButtonVariants["variant"]
  size?: ButtonVariants["size"]
  class?: HTMLAttributes["class"]
  specular?: boolean
  disabled?: boolean
  type?: "button" | "submit" | "reset"
}

const props = withDefaults(defineProps<Props>(), {
  as: "button",
  specular: true,
})
</script>

<template>
  <SpecularButton
    v-if="specular && !asChild && variant !== 'link' && variant !== 'ghost'"
    v-bind="$attrs"
    :as="as"
    :type="type"
    :disabled="disabled"
    :variant="variant === 'destructive' ? 'destructive' : variant === 'secondary' ? 'secondary' : variant === 'outline' ? 'outline' : 'primary'"
    :size="size === 'sm' ? 'sm' : size === 'lg' ? 'lg' : size === 'icon' ? 'icon' : size === 'icon-sm' ? 'icon-sm' : size === 'icon-lg' ? 'icon-lg' : 'default'"
    :class="cn(buttonVariants({ variant, size }), props.class)"
  >
    <slot />
  </SpecularButton>
  <Primitive
    v-else
    v-bind="$attrs"
    data-slot="button"
    :data-variant="variant"
    :data-size="size"
    :as="as"
    :as-child="asChild"
    :disabled="disabled"
    :type="type"
    :class="cn(buttonVariants({ variant, size }), props.class)"
  >
    <slot />
  </Primitive>
</template>
