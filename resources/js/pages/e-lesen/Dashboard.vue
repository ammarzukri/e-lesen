<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';

import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem, type AppPageProps } from '@/types';
import { computed, ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const page = usePage<AppPageProps>();
const user = computed(() => page.props.auth?.user ?? null);
const isStaff = computed(() => user.value?.role === 'staff');

const recentApplications = computed(() => (page.props as any).recentApplications ?? []);
const approvedApplications = computed(() =>
    recentApplications.value.filter((app: any) => app?.payment_status === 'Berjaya'),
);
const applicationAlerts = computed(() =>
    recentApplications.value
    .filter((app: any) => app.status === 'Diluluskan' && app.payment_status !== 'Berjaya')
    .map((app: any) => {
        const appRef = app.license_number ? `No Lesen ${app.license_number}` : `Permohonan #${app.id}`;
        const appName = app.hotel_name ?? app.company_name ?? 'Permohonan tanpa nama';
        const appLabel = `${appName} (${appRef})`;

        return {
            id: app.id,
            tone: 'warning',
            title: 'Perlu Bayaran',
            message: `${appLabel} telah diluluskan tetapi masih memerlukan bayaran.`,
        };
    }),
);
const selectedApplication = ref<any | null>(null);

function openDetails(app: any) {
    selectedApplication.value = app;
}

function closeDetails() {
    selectedApplication.value = null;
}

function formatDate(dt?: string | null) {
    if (!dt) return '-';
    try {
        const date = new Date(dt);

        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();

        return `${day}/${month}/${year}`;
    } catch {
        return dt;
    }
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="mb-8 md:col-span-3">
                    <h1 class="text-2xl font-bold mb-2 text-[#2563EB] dark:text-[#60A5FA]">
                        Selamat Datang, {{ user?.name ?? '' }}!
                    </h1>
                </div>

                <!-- Removed summary cards; show application table below -->
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/60">
                <h3 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">Notifikasi</h3>

                <div v-if="applicationAlerts.length > 0" class="space-y-2">
                    <div
                        v-for="alert in applicationAlerts"
                        :key="alert.id"
                        class="rounded-lg border px-3 py-2 text-sm"
                        :class="[
                            alert.tone === 'warning' && 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-200',
                        ]"
                    >
                        <p class="font-semibold">{{ alert.title }}</p>
                        <p>{{ alert.message }}</p>
                    </div>
                </div>

                <p v-else class="text-sm text-muted-foreground">Belum ada perkembangan lesen untuk dipaparkan.</p>
            </div>

            <div class="relative w-full rounded-xl border border-sidebar-border/70 bg-slate-50 p-4 dark:border-sidebar-border dark:bg-slate-900/60">
                <div class="mb-3 flex items-center">
                    <h2 class="text-lg font-semibold">Lesen Saya</h2>
                </div>

                <div class="flex justify-start">
                    <div
                        class="flex w-full max-w-full items-center justify-start gap-4 overflow-x-auto pb-2"
                    >
                        <button
                            v-for="app in approvedApplications"
                            :key="app.id"
                            type="button"
                            class="w-72 shrink-0 rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-900"
                            @click="openDetails(app)"
                        >
                            <p class="mb-1 text-xs text-muted-foreground">Nama Hotel</p>
                            <p class="mb-3 truncate text-base font-semibold text-slate-800 dark:text-slate-100">{{ app.hotel_name ?? '-' }}</p>

                            <p class="mb-1 text-xs text-muted-foreground">Nama Pengguna</p>
                            <p class="mb-3 truncate text-sm font-medium text-slate-700 dark:text-slate-200">{{ user?.name ?? app.company_name ?? '-' }}</p>

                            <p class="mb-1 text-xs text-muted-foreground">Status</p>
                            <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/40 dark:text-green-300">
                                {{ app.status ?? '-' }}
                            </span>
                        </button>

                        <Link
                            v-if="!isStaff"
                            href="/license/apply"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xl font-bold text-white transition hover:bg-blue-700"
                            title="Tambah Lesen"
                        >
                            +
                        </Link>
                    </div>
                </div>

                <div v-if="approvedApplications.length === 0" class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground">
                    Tiada lesen berstatus Diluluskan ditemui.
                </div>

                <div
                    v-if="selectedApplication"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                    @click.self="closeDetails"
                >
                    <div class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl dark:bg-slate-900">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="text-lg font-semibold">Maklumat Lesen</h3>
                            <button
                                type="button"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 text-lg font-bold text-slate-700 hover:bg-slate-300 dark:bg-slate-700 dark:text-slate-100 dark:hover:bg-slate-600"
                                @click="closeDetails"
                            >
                                &#x00d7;
                            </button>
                        </div>

                        <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <p class="text-xs text-muted-foreground">No Lesen</p>
                                <p class="font-semibold">{{ selectedApplication.license_number ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Nama Hotel</p>
                                <p class="font-semibold">{{ selectedApplication.hotel_name ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Nama Pengguna</p>
                                <p class="font-semibold">{{ user?.name ?? selectedApplication.company_name ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Status</p>
                                <p class="font-semibold">{{ selectedApplication.status ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Status Bayaran</p>
                                <p class="font-semibold">{{ selectedApplication.payment_status || 'Belum Dibayar' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Status Lesen</p>
                                <p class="font-semibold">{{ selectedApplication.license_status || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">PBT</p>
                                <p class="font-semibold">{{ selectedApplication.pbt_name ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">Tarikh Tamat</p>
                                <p class="font-semibold">{{ formatDate(selectedApplication.expiry_date) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
