<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutGrid,
    Users,
    Package,
    Truck,
    Building2,
    FileText,
    ShoppingCart,
    LineChart,
    PieChart,
    Banknote,
    Laptop,
    ShieldCheck,
    Activity,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import LineSidebar from '@/components/vue-bits/LineSidebar.vue';
import NavUser from '@/components/NavUser.vue';
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
const currentUser = computed(() => (page.props as any).auth?.user);
const isAdmin = computed(() => ['admin', 'superadmin', 'owner'].includes(currentUser.value?.role));
const userRole = computed(() => currentUser.value?.role || '');

const operations = computed<NavItem[]>(() => {
    const team = page.props.currentTeam?.slug;

    if (!team) {
        return [];
    }

    // ADMIN CAN ACCESS ALL MODULE
    if (isAdmin.value) {
        return [
            { title: 'Overview', href: dashboard(team), icon: LayoutGrid },
            { title: 'User Management', href: users(team), icon: Users },
            { title: 'Inventory', href: inventory(team), icon: Package },
            { title: 'Suppliers', href: suppliers(team), icon: Building2 },
            { title: 'Quotations', href: quotations(team), icon: FileText },
            { title: 'Procurement', href: procurement(team), icon: ShoppingCart },
            { title: 'Deliveries', href: `/${team}/deliveries`, icon: Truck },
            { title: 'Sales', href: sales(team), icon: Banknote },
            { title: 'Demand Forecasting', href: forecast(team), icon: LineChart },
            { title: 'Reports & Analytics', href: reports(team), icon: PieChart },
        ];
    }

    // 1 MODULE PER STAFF ONLY - ONLY THAT ROLE THEY ARE ASSIGNED WITH IS SHOWN TO THEM
    const r = userRole.value;
    if (r === 'staff_inventory' || r === 'staff') {
        return [{ title: 'Inventory', href: inventory(team), icon: Package }];
    }
    if (r === 'staff_quotations') {
        return [{ title: 'Quotations', href: quotations(team), icon: FileText }];
    }
    if (r === 'staff_procurement') {
        return [{ title: 'Procurement', href: procurement(team), icon: ShoppingCart }];
    }
    if (r === 'staff_deliveries') {
        return [{ title: 'Deliveries', href: `/${team}/deliveries`, icon: Truck }];
    }
    if (r === 'staff_sales') {
        return [{ title: 'Sales', href: sales(team), icon: Banknote }];
    }
    if (r === 'staff_forecast') {
        return [{ title: 'Demand Forecasting', href: forecast(team), icon: LineChart }];
    }
    if (r === 'staff_suppliers') {
        return [{ title: 'Suppliers', href: suppliers(team), icon: Truck }];
    }
    if (r === 'staff_reports') {
        return [{ title: 'Reports & Analytics', href: reports(team), icon: PieChart }];
    }

    return [];
});

const dashboardUrl = computed(() => {
    const team = page.props.currentTeam?.slug;
    if (!team) return '/';
    if (isAdmin.value) return dashboard(team).url;
    if (operations.value.length > 0) return operations.value[0].href;
    return '/';
});
</script>
<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader
            class="p-3 transition-all duration-300 ease-in-out group-data-[collapsible=icon]:p-2 group-data-[collapsible=icon]:items-center"
        >
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="brand-lockup h-14 rounded-xl bg-white/95 p-2 shadow-xs transition-all duration-300 ease-in-out hover:bg-white group-data-[collapsible=icon]:size-10 group-data-[collapsible=icon]:p-1.5 group-data-[collapsible=icon]:mx-auto group-data-[collapsible=icon]:rounded-lg group-data-[collapsible=icon]:justify-center"
                    >
                        <Link
                            :href="dashboardUrl"
                            prefetch
                            class="flex w-full items-center overflow-hidden group-data-[collapsible=icon]:justify-center"
                        >
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>
        <SidebarContent class="overflow-y-auto px-3 py-2 transition-all duration-300 ease-in-out group-data-[collapsible=icon]:px-1.5 group-data-[collapsible=icon]:py-2">
            <LineSidebar :items="operations" label="Operations" />
        </SidebarContent>
        <SidebarFooter
            class="p-3 transition-all duration-300 ease-in-out group-data-[collapsible=icon]:p-2 group-data-[collapsible=icon]:items-center"
        >
            <div class="w-full">
                <NavUser />
            </div>
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
