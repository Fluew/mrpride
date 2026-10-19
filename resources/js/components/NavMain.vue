<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';
withDefaults(defineProps<{ items: NavItem[]; label?: string }>(), {
    label: '',
});
const { isCurrentUrl } = useCurrentUrl();
const { isMobile, setOpenMobile } = useSidebar();
function closeOnMobile() {
    if (isMobile.value) {
        setOpenMobile(false);
    }
}
</script>
<template>
    <SidebarGroup class="px-3 py-2">
        <SidebarGroupLabel
            v-if="label"
            class="mb-2 px-3 text-[10px] font-bold tracking-[0.16em] text-white/60 uppercase"
            >{{ label }}</SidebarGroupLabel
        >
        <SidebarMenu class="gap-1">
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                    class="h-11 rounded-xl px-3 text-white/85 transition-colors hover:bg-white/10 hover:text-white data-[active=true]:bg-[#F8B803] data-[active=true]:font-semibold data-[active=true]:text-gray-900"
                >
                    <Link
                        :href="item.href"
                        :aria-current="
                            isCurrentUrl(item.href) ? 'page' : undefined
                        "
                        prefetch
                        @click="closeOnMobile"
                        ><component :is="item.icon" /><span>{{
                            item.title
                        }}</span></Link
                    >
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
