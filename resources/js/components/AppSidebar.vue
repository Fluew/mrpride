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
    Banknote,
    ArrowUpRight,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
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
import { dashboard, home } from '@/routes';
import { index as quotations } from '@/routes/admin/quotations';
import { index as forecast } from '@/routes/forecast';
import { index as inventory } from '@/routes/inventory';
import { index as procurement } from '@/routes/procurement';
import { index as reports } from '@/routes/reports';
import { index as sales } from '@/routes/sales';
import { index as suppliers } from '@/routes/suppliers';
import { index as users } from '@/routes/users';
import type { NavItem } from '@/types';
const page = usePage();
const dashboardUrl = computed(() =>
    page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug).url
        : home().url,
);
const operations = computed<NavItem[]>(() => {
    const team = page.props.currentTeam?.slug;

    if (!team) {
        return [];
    }

    return [
        { title: 'Overview', href: dashboard(team), icon: LayoutGrid },
        { title: 'Inventory', href: inventory(team), icon: Package },
        { title: 'Suppliers', href: suppliers(team), icon: Truck },
        { title: 'Quotations', href: quotations(team), icon: FileText },
        { title: 'Procurement', href: procurement(team), icon: ShoppingCart },
        { title: 'Sales', href: sales(team), icon: Banknote },
    ];
});
const insights = computed<NavItem[]>(() => {
    const team = page.props.currentTeam?.slug;

    if (!team) {
        return [];
    }

    return [
        { title: 'Demand Forecasting', href: forecast(team), icon: LineChart },
        { title: 'Reports', href: reports(team), icon: PieChart },
        { title: 'User Management', href: users(team), icon: Users },
    ];
});
</script>
<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="gap-4 p-3">
            <SidebarMenu
                ><SidebarMenuItem
                    ><SidebarMenuButton
                        size="lg"
                        as-child
                        class="brand-lockup h-16 rounded-xl bg-white/95 p-3 hover:bg-white"
                        ><Link :href="dashboardUrl" prefetch
                            ><AppLogo /></Link></SidebarMenuButton></SidebarMenuItem
            ></SidebarMenu>
            <SidebarMenu
                ><SidebarMenuItem><TeamSwitcher /></SidebarMenuItem
            ></SidebarMenu>
        </SidebarHeader>
        <SidebarContent
            ><NavMain :items="operations" label="Operations" /><NavMain
                :items="insights"
                label="Insights & administration"
        /></SidebarContent>
        <SidebarFooter class="gap-3 p-3"
            ><SidebarMenu
                ><SidebarMenuItem
                    ><SidebarMenuButton
                        as-child
                        class="h-10 rounded-xl text-white/70 hover:bg-white/10 hover:text-white"
                        ><Link :href="home()"
                            ><ArrowUpRight /><span
                                >View public website</span
                            ></Link
                        ></SidebarMenuButton
                    ></SidebarMenuItem
                ></SidebarMenu
            >
            <div class="border-t border-white/15 pt-3"><NavUser /></div
        ></SidebarFooter>
    </Sidebar>
    <slot />
</template>
