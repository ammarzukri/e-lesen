<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem, type AppPageProps } from '@/types';
import { computed, ref } from 'vue';
import { FileText, CheckCircle, XCircle, Ban } from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth?.user ?? null);
type AdminStats = {
    totals: {
        total: number;
        approved: number;
        rejected: number;
        blocked?: number;
    };
    byPbt: Array<{ name: string; total: number }>;
};

const adminStats = computed(() => page.props.adminStats as AdminStats | undefined);
const totalApplications = computed(() => adminStats.value?.totals.total ?? 0);
const approvedApplications = computed(() => adminStats.value?.totals.approved ?? 0);
const rejectedApplications = computed(() => adminStats.value?.totals.rejected ?? 0);
const blockedApplications = computed(() => adminStats.value?.totals.blocked ?? 0);
const pbtData = computed(() => adminStats.value?.byPbt ?? []);

const hoveredSlice = ref<{ name: string; total: number; percentLabel?: string } | null>(null);

const colors = [
    '#2563EB',
    '#16A34A',
    '#DC2626',
    '#F59E0B',
    '#7C3AED',
    '#0EA5E9',
    '#14B8A6',
    '#F97316',
    '#64748B',
];

const pieSlices = computed(() => {
    const data = pbtData.value;
    const total = data.reduce((sum, item) => sum + item.total, 0);
    if (!total) {
        return [];
    }

    const totalApproved = approvedApplications.value || total;

    const radius = 14;
    const circumference = 2 * Math.PI * radius;
    let offset = 0;

    return data.map((item, index) => {
        const value = item.total;
        const dash = (value / total) * circumference;
        const percent = totalApproved ? (value / totalApproved) * 100 : 0;
        const slice = {
            name: item.name,
            total: item.total,
            percentLabel: `${percent.toFixed(1)}%`,
            dashArray: `${dash} ${circumference - dash}`,
            dashOffset: -offset,
            color: colors[index % colors.length],
        };

        offset += dash;
        return slice;
    });
});

function openApplicationsByPbt(pbtName: string) {
    router.get('/admin/license-applications', {
        pbt_name: pbtName,
    });
}

function openApplicationsByStatus(status?: string) {
    router.get('/admin/license-applications', status ? { status } : {});
}
</script>

<template>
    <Head title="Admin Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="grid auto-rows-min gap-4 md:grid-cols-4">
                <div class="mb-8 md:col-span-4">
                    <h1 class="text-2xl font-bold mb-2 text-[#2563EB] dark:text-[#60A5FA]">
                        Selamat Datang, {{ user?.name ?? '' }}!
                    </h1>
                </div>
                <div
                    class="relative cursor-pointer overflow-hidden rounded-xl border border-sidebar-border/70 bg-white p-5 transition-colors hover:bg-slate-50 dark:border-sidebar-border dark:bg-[#0f172a] dark:hover:bg-slate-900"
                    @click="openApplicationsByStatus()"
                >
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[#eef2ff] dark:bg-[#071033]">
                            <FileText class="h-6 w-6 text-[#2563EB]" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Jumlah Permohonan</p>
                            <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ totalApplications }}</p>
                        </div>
                    </div>
                </div>

                <div
                    class="relative cursor-pointer overflow-hidden rounded-xl border border-sidebar-border/70 bg-white p-5 transition-colors hover:bg-slate-50 dark:border-sidebar-border dark:bg-[#0f172a] dark:hover:bg-slate-900"
                    @click="openApplicationsByStatus('Diluluskan')"
                >
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[#ecfdf5] dark:bg-[#042015]">
                            <CheckCircle class="h-6 w-6 text-emerald-600" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Diluluskan</p>
                            <p class="mt-2 text-3xl font-semibold text-emerald-600 dark:text-emerald-400">{{ approvedApplications }}</p>
                        </div>
                    </div>
                </div>

                <div
                    class="relative cursor-pointer overflow-hidden rounded-xl border border-sidebar-border/70 bg-white p-5 transition-colors hover:bg-slate-50 dark:border-sidebar-border dark:bg-[#0f172a] dark:hover:bg-slate-900"
                    @click="openApplicationsByStatus('Ditolak')"
                >
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[#ffefef] dark:bg-[#2a0b0b]">
                            <XCircle class="h-6 w-6 text-rose-600" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Ditolak</p>
                            <p class="mt-2 text-3xl font-semibold text-rose-600 dark:text-rose-400">{{ rejectedApplications }}</p>
                        </div>
                    </div>
                </div>

                <div
                    class="relative cursor-pointer overflow-hidden rounded-xl border border-sidebar-border/70 bg-white p-5 transition-colors hover:bg-slate-50 dark:border-sidebar-border dark:bg-[#0f172a] dark:hover:bg-slate-900"
                    @click="openApplicationsByStatus('Disekat')"
                >
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[#fef3c7] dark:bg-[#2b1f07]">
                            <Ban class="h-6 w-6 text-amber-600" />
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Disekat</p>
                            <p class="mt-2 text-3xl font-semibold text-amber-600 dark:text-amber-400">{{ blockedApplications }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="relative flex-1 rounded-xl border border-sidebar-border/70 bg-white p-6 md:min-h-min dark:border-sidebar-border dark:bg-[#0f172a]">
                <div class="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Permohonan Diluluskan Mengikut PBT</h2>
                        <p class="text-sm text-muted-foreground">Jumlah permohonan diluluskan berdasarkan PBT.</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-8 md:grid-cols-[240px_1fr]">
                    <div class="flex items-center justify-center">
                        <div class="relative h-56 w-56">
                            <svg viewBox="0 0 32 32" class="h-full w-full -rotate-90">
                                <circle
                                    cx="16"
                                    cy="16"
                                    r="14"
                                    fill="transparent"
                                    stroke="#e5e7eb"
                                    stroke-width="4"
                                    class="dark:stroke-slate-700"
                                />
                                <circle
                                    v-for="slice in pieSlices"
                                    :key="slice.name"
                                    cx="16"
                                    cy="16"
                                    r="14"
                                    fill="transparent"
                                    stroke-width="4"
                                    stroke-linecap="butt"
                                    :stroke="slice.color"
                                    :stroke-dasharray="slice.dashArray"
                                    :stroke-dashoffset="slice.dashOffset"
                                    pointer-events="stroke"
                                        @mouseenter="hoveredSlice = { name: slice.name, total: slice.total, percentLabel: slice.percentLabel }"
                                        @mouseleave="hoveredSlice = null"
                                />
                            </svg>
                            <div v-if="hoveredSlice" class="absolute left-full top-1/2 transform -translate-y-1/2 ml-4 z-20 w-56 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2 text-sm shadow-md dark:border-slate-700 dark:bg-slate-900 pointer-events-none">
                                <div class="font-medium text-slate-900 dark:text-slate-100">{{ hoveredSlice.name }}</div>
                                <div class="text-muted-foreground">Diluluskan : {{ hoveredSlice.total }} permohonan <span class="ml-2">{{ hoveredSlice.percentLabel }}</span></div>
                            </div>
                            <div v-if="!pieSlices.length" class="absolute inset-0 flex items-center justify-center text-sm text-muted-foreground">
                                Tiada data
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-3">
                        <div
                            v-for="(slice, index) in pieSlices"
                            :key="slice.name"
                            class="flex cursor-pointer items-center justify-between rounded-lg border border-slate-200 px-4 py-3 text-sm transition-colors hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800/50"
                            @mouseenter="hoveredSlice = { name: slice.name, total: slice.total }"
                            @mouseleave="hoveredSlice = null"
                            @click="openApplicationsByPbt(slice.name)"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="h-3 w-3 rounded-full"
                                    :style="{ backgroundColor: colors[index % colors.length] }"
                                ></span>
                                <span class="font-medium text-slate-900 dark:text-slate-100">{{ slice.name }}</span>
                            </div>
                                <span class="text-muted-foreground">{{ slice.total }} permohonan • {{ slice.percentLabel }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
