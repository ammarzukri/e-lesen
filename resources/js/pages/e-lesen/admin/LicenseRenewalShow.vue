<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type RenewalRecord = {
    id: number;
    status?: string;
    payment_status?: string;
    payment_amount?: number | string;
    payment_paid_at?: string;
    current_expiry_date?: string;
    renewed_until_date?: string;
    approved_at?: string;
    rejected_at?: string;
    created_at?: string;
    user?: {
        name?: string;
        email?: string;
    };
    license?: {
        license_number?: string;
        hotel?: {
            name?: string;
            company_name?: string;
            pbt_name?: string;
        };
    };
};

const props = defineProps<{
    renewal: RenewalRecord;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pembaharuan Lesen', href: '/admin/license-renewals' },
    { title: 'Butiran Pembaharuan', href: `/admin/license-renewals/${props.renewal.id}` },
];

const statusLabel = computed(() => props.renewal.status || 'Dalam Proses');

const dateFormatter = new Intl.DateTimeFormat('ms-MY', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

const dateTimeFormatter = new Intl.DateTimeFormat('ms-MY', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

function formatDate(value?: string) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return dateFormatter.format(date);
}

function formatDateTime(value?: string) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return dateTimeFormatter.format(date);
}

function formatAmount(value?: number | string) {
    if (value === null || value === undefined || value === '') return '-';

    const numeric = Number(value);

    if (Number.isNaN(numeric)) {
        return '-';
    }

    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
        minimumFractionDigits: 2,
    }).format(numeric / 100);
}
</script>

<template>
    <Head title="Butiran Pembaharuan Lesen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full h-full flex flex-col p-6 bg-white dark:bg-black rounded-xl shadow dark:shadow-black/30">
            <div class="flex-1 overflow-auto space-y-6">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">Butiran Pembaharuan Lesen</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400">Paparan maklumat pembaharuan lesen secara terperinci.</p>
                    </div>
                    <div
                        :class="[
                            'px-3 py-1 rounded-full text-sm font-semibold',
                            statusLabel === 'Diluluskan'
                                ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                : statusLabel === 'Ditolak'
                                    ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                                    : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-100',
                        ]"
                    >
                        {{ statusLabel }}
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 p-4">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400">ID Pembaharuan</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">#{{ renewal.id }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400">No. Lesen</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ renewal.license?.license_number || '-' }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Hotel</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ renewal.license?.hotel?.name || '-' }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Syarikat</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ renewal.license?.hotel?.company_name || '-' }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Pemohon</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ renewal.user?.name || '-' }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ renewal.user?.email || '-' }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400">PBT</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ renewal.license?.hotel?.pbt_name || '-' }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Tarikh Tamat Semasa</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ formatDate(renewal.current_expiry_date) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Tarikh Tamat Baharu</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ formatDate(renewal.renewed_until_date) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Status Bayaran</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ renewal.payment_status || '-' }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Jumlah: {{ formatAmount(renewal.payment_amount) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Tarikh Bayaran</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ formatDateTime(renewal.payment_paid_at) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Dicipta Pada</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ formatDateTime(renewal.created_at) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Tindakan Admin</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Diluluskan: {{ formatDateTime(renewal.approved_at) }}</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Ditolak: {{ formatDateTime(renewal.rejected_at) }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end">
                    <Link
                        href="/admin/license-renewals"
                        class="px-4 py-2 rounded-lg bg-slate-700 text-white text-sm font-semibold hover:bg-slate-800"
                    >
                        Kembali
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
