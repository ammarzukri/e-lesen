<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, Banknote, Users, DollarSign, HandCoins, WalletCards } from 'lucide-vue-next';

const page = usePage();
const role = page.props.auth?.user?.role;
const isAdminRole = ['admin', 'bkt_admin', 'pbt_admin', 'bendahara_admin'].includes(role ?? '');

const navigationItems = isAdminRole
    ? [
        { label: 'Dashboard', href: '/fi-sejahtera/dashboard', icon: LayoutGrid },
        // { label: 'Senarai Tetamu', href: '/fi-sejahtera/guest', icon: Users },
        { label: 'Rekod Pembayaran', href: '/fi-sejahtera/tax', icon: DollarSign },
    ]
    : [
        { label: 'Dashboard', href: '/fi-sejahtera/dashboard', icon: LayoutGrid },
        { label: 'Kutipan Fi Sejahtera', href: '/fi-sejahtera/apply', icon: Banknote },
        { label: 'Rekod Tetamu', href: '/fi-sejahtera/guest', icon: Users },
        // { label: 'Senarai Pembayaran', href: '/fi-sejahtera/tax', icon: DollarSign },
    ];

if (role === 'user') {
    navigationItems.push(
        { label: 'Laporan Pembayaran Fi', href: '/fi-sejahtera/perbendaharaan', icon: HandCoins },
        { label: 'Rekod Bukti Pembayaran Fi', href: '/fi-sejahtera/payment', icon: WalletCards },
        { label: 'Tetapan Staff', href: '/fi-sejahtera/staff', icon: Users }
    );
}

const isActive = (href: string) => page.url.startsWith(href);
</script>

<template>
    <aside class="w-70 sticky top-0 h-screen border-r border-border bg-background overflow-auto">
        <div class="flex min-h-0 flex-col p-6">
            <div class="mb-8 flex flex-col items-center">
                <img
                    src="/images/jata-negeri.png"
                    alt="Jata Negeri"
                    class="h-24 w-24 object-contain"
                />
            </div>

            <div>
                <h2 class="mb-4 text-lg font-semibold text-foreground flex items-center justify-center">
                    Sistem Fi Sejahtera
                </h2>
            </div>

            <nav class="space-y-2">
                <Link
                    v-for="item in navigationItems"
                    :key="item.label"
                    :href="item.href"
                    class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                    :class="
                        isActive(item.href)
                            ? 'bg-muted text-foreground'
                            : 'text-muted-foreground hover:bg-muted/70 hover:text-foreground'
                    "
                >
                    <component :is="item.icon" class="h-4 w-4" />
                    {{ item.label }}
                </Link>
            </nav>
        </div>
    </aside>
</template>
