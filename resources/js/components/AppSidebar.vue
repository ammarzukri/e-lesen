<script setup lang="ts">
import { computed } from 'vue';
import { App, Link, usePage } from '@inertiajs/vue3';
import { FolderOpen, AppWindow, LayoutGrid, Info, Timer, CircleFadingPlus, ShieldUser } from 'lucide-vue-next';

import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
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
import { apply as licenseApply, status as licenseStatus } from '@/routes/license';
import { type NavItem } from '@/types';

import AppLogo from './AppLogo.vue';

const page = usePage();
const isAdmin = computed(() => ['admin', 'bkt_admin', 'bendahara_admin'].includes(page.props.auth?.user?.role ?? ''));
const isPbtAdmin = computed(() => page.props.auth?.user?.role === 'pbt_clerk');
const isSuperAdmin = computed(() => page.props.auth?.user?.role === 'super_admin');
const isStaff = computed(() => page.props.auth?.user?.role === 'staff');

const mainNavItems = computed<NavItem[]>(() => {
    if (isSuperAdmin.value) {
        return [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
            {
                title: 'Senarai Admin',
                href: '/super-admin/admins',
                icon: ShieldUser,
            },
        ];
    }

    if (isAdmin.value) {
        return [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
            {
                title: 'Senarai Permohonan',
                href: '/admin/license-applications',
                icon: FolderOpen,
            },
            {
                title: 'Pembaharuan Lesen',
                href: '/admin/license-renewals',
                icon: Timer,
            },
        ];
    }

    if (isPbtAdmin.value) {
        return [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
            {
                title: 'Senarai Permohonan',
                href: '/admin/license-applications',
                icon: FolderOpen,
            },
            {
                title: 'Pembaharuan Lesen',
                href: '/admin/license-renewals',
                icon: Timer,
            },
            {
                title: 'Aktiviti Tambahan',
                href: '/admin/license-additional-activities',
                icon: CircleFadingPlus,
            },
        ];
    }

    if (isStaff.value) {
        return [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
            {
                title: 'Status Lesen Penginapan',
                href: licenseStatus(),
                icon: Info,
            },
        ];
    }

    return [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Mohon Lesen Penginapan',
            href: licenseApply(),
            icon: AppWindow,
        },
        {
            title: 'Status Lesen Penginapan',
            href: licenseStatus(),
            icon: Info,
        },
    ];
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
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
