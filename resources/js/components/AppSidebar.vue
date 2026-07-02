<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutGrid,
    Users,
    Package,
    Truck,
    FileText,
    ShoppingCart,
    LineChart,
    PieChart,
    Banknote
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const teamSlug = computed(() => page.props.currentTeam ? `/${page.props.currentTeam.slug}` : '');

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboardUrl.value,
        icon: LayoutGrid,
    },
    {
        title: 'User Management',
        href: `${teamSlug.value}/users`,
        icon: Users,
    },
    {
        title: 'Inventory',
        href: `${teamSlug.value}/inventory`,
        icon: Package,
    },
    {
        title: 'Suppliers',
        href: `${teamSlug.value}/suppliers`,
        icon: Truck,
    },
    {
        title: 'Quotations',
        href: `${teamSlug.value}/quotations`,
        icon: FileText,
    },
    {
        title: 'Procurement',
        href: `${teamSlug.value}/procurement`,
        icon: ShoppingCart,
    },
    {
        title: 'Sales Management',
        href: `${teamSlug.value}/sales`,
        icon: Banknote,
    },
    {
        title: 'Demand Forecasting',
        href: `${teamSlug.value}/forecast`,
        icon: LineChart,
    },
    {
        title: 'Reports',
        href: `${teamSlug.value}/reports`,
        icon: PieChart,
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardUrl">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarMenu>
                <SidebarMenuItem>
                    <TeamSwitcher />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
