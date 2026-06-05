<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
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
const canTakeAction = computed(() => statusLabel.value === 'Dalam Proses');

function renewalStatusBadgeClass(status?: string) {
    if (status === 'Diluluskan') {
        return 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300';
    }

    if (status === 'Ditolak') {
        return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
    }

    return 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-100';
}

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

function approve() {
    router.post(`/admin/license-renewals/${props.renewal.id}/approve`);
}

function reject() {
    router.post(`/admin/license-renewals/${props.renewal.id}/reject`);
}
</script>

<template>
    <Head title="Butiran Pembaharuan Lesen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full h-full flex flex-col p-6 bg-white dark:bg-black rounded-xl shadow dark:shadow-black/30">
            <div class="flex-1 overflow-auto space-y-6">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">Butiran Pembaharuan Lesen</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400">Paparan maklumat pembaharuan lesen secara terperinci.</p>
                    </div>
                    <div
                        :class="[
                            'px-3 py-1 rounded-full text-sm font-semibold',
                            renewalStatusBadgeClass(statusLabel),
                        ]"
                    >
                        {{ statusLabel }}
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <Link
                        class="px-3 py-1 rounded-lg bg-slate-700 text-white text-sm font-semibold hover:bg-slate-800"
                        href="/admin/license-renewals"
                    >
                        ← Kembali
                    </Link>
                </div>

                <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 p-6 space-y-8">
                    <section>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">Maklumat Pembaharuan</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm font-semibold text-slate-600 dark:text-slate-400">ID Pembaharuan</div>
                                <div class="text-md text-slate-900 dark:text-slate-100">#{{ renewal.id }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-slate-600 dark:text-slate-400">No. Lesen</div>
                                <div class="text-md text-slate-900 dark:text-slate-100">{{ renewal.license?.license_number || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-slate-600 dark:text-slate-400">Hotel</div>
                                <div class="text-md text-slate-900 dark:text-slate-100">{{ renewal.license?.hotel?.name || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-slate-600 dark:text-slate-400">Syarikat</div>
                                <div class="text-md text-slate-900 dark:text-slate-100">{{ renewal.license?.hotel?.company_name || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-slate-600 dark:text-slate-400">PBT</div>
                                <div class="text-md text-slate-900 dark:text-slate-100">{{ renewal.license?.hotel?.pbt_name || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-slate-600 dark:text-slate-400">Dicipta Pada</div>
                                <div class="text-md text-slate-900 dark:text-slate-100">{{ formatDateTime(renewal.created_at) }}</div>
                            </div>
                        </div>
                    </section>

                    <section>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">Maklumat Pemohon</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm font-semibold text-slate-600 dark:text-slate-400">Pemohon</div>
                                <div class="text-md text-slate-900 dark:text-slate-100">{{ renewal.user?.name || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-slate-600 dark:text-slate-400">Email</div>
                                <div class="text-md text-slate-900 dark:text-slate-100">{{ renewal.user?.email || '-' }}</div>
                            </div>
                        </div>
                    </section>

                    <section>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">Tempoh Lesen</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm font-semibold text-slate-600 dark:text-slate-400">Tarikh Tamat Semasa</div>
                                <div class="text-md text-slate-900 dark:text-slate-100">{{ formatDate(renewal.current_expiry_date) }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-slate-600 dark:text-slate-400">Tarikh Tamat Baharu</div>
                                <div class="text-md text-slate-900 dark:text-slate-100">{{ formatDate(renewal.renewed_until_date) }}</div>
                            </div>
                        </div>
                    </section>

                    <section>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">Maklumat Pembayaran</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm font-semibold text-slate-600 dark:text-slate-400">Status Bayaran</div>
                                <div class="text-md text-slate-900 dark:text-slate-100">{{ renewal.payment_status || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-slate-600 dark:text-slate-400">Jumlah Dibayar</div>
                                <div class="text-md text-slate-900 dark:text-slate-100">{{ formatAmount(renewal.payment_amount) }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-slate-600 dark:text-slate-400">Tarikh Bayaran</div>
                                <div class="text-md text-slate-900 dark:text-slate-100">{{ formatDateTime(renewal.payment_paid_at) }}</div>
                            </div>
                        </div>
                    </section>

                    <section>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">Tindakan Admin</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm font-semibold text-slate-600 dark:text-slate-400">Diluluskan Pada</div>
                                <div class="text-md text-slate-900 dark:text-slate-100">{{ formatDateTime(renewal.approved_at) }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-slate-600 dark:text-slate-400">Ditolak Pada</div>
                                <div class="text-md text-slate-900 dark:text-slate-100">{{ formatDateTime(renewal.rejected_at) }}</div>
                            </div>
                        </div>
                    </section>
                </div>

                <div
                    v-if="canTakeAction"
                    class="flex flex-wrap gap-3 justify-end"
                >
                    <button
                        type="button"
                        class="px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold hover:bg-green-700"
                        @click="approve"
                    >
                        Lulus
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700"
                        @click="reject"
                    >
                        Tolak
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
