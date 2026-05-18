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
const dismissedNotificationIds = ref<number[]>([]);

function startOfDay(date: Date) {
    const normalized = new Date(date);
    normalized.setHours(0, 0, 0, 0);
    return normalized;
}

function daysUntil(dateString?: string | null) {
    if (!dateString) {
        return null;
    }

    const target = startOfDay(new Date(dateString));

    if (Number.isNaN(target.getTime())) {
        return null;
    }

    const today = startOfDay(new Date());
    const diffMs = target.getTime() - today.getTime();

    return Math.ceil(diffMs / (1000 * 60 * 60 * 24));
}

const applicationAlerts = computed(() => {
    const alerts: Array<{
        id: number;
        tone: 'warning' | 'danger';
        title: string;
        message: string;
    }> = [];

    recentApplications.value
        .filter((app: any) => app.status === 'Diluluskan' && app.payment_status !== 'Berjaya')
        .forEach((app: any) => {
            const appRef = app.license_number ? `No Lesen ${app.license_number}` : `Permohonan #${app.id}`;
            const appName = app.hotel_name ?? app.company_name ?? 'Permohonan tanpa nama';
            const appLabel = `${appName} (${appRef})`;

            alerts.push({
                id: Number(`1${app.id}`),
                tone: 'warning',
                title: 'Perlu Bayaran',
                message: `${appLabel} telah diluluskan tetapi masih memerlukan bayaran.`,
            });
        });

    approvedApplications.value.forEach((app: any) => {
        const appRef = app.license_number ? `No Lesen ${app.license_number}` : `Permohonan #${app.id}`;
        const appName = app.hotel_name ?? app.company_name ?? 'Permohonan tanpa nama';
        const appLabel = `${appName} (${appRef})`;
        const remainingDays = daysUntil(app.expiry_date);

        if (app.license_status === 'Disekat') {
            alerts.push({
                id: Number(`4${app.id}`),
                tone: 'danger',
                title: 'Lesen Disekat',
                message: `Lesen ${appLabel} telah disekat. Sila buat pembayaran ke Perbendaharaan.`,
            });

            return;
        }

        if (remainingDays === null) {
            return;
        }

        if (remainingDays < 0 || app.license_status === 'Tamat Tempoh') {
            alerts.push({
                id: Number(`2${app.id}`),
                tone: 'danger',
                title: 'Lesen Tamat Tempoh',
                message: `Lesen ${appLabel} telah tamat tempoh. Sila buat pembaharuan lesen segera.`,
            });

            return;
        }

        if (remainingDays <= 7) {
            alerts.push({
                id: Number(`3${app.id}`),
                tone: 'warning',
                title: 'Lesen Akan Tamat Tempoh',
                message: `${appLabel} akan tamat tempoh dalam ${remainingDays} hari. Sila buat pembaharuan awal.`,
            });
        }
    });

    return alerts.filter((alert) => !dismissedNotificationIds.value.includes(alert.id));
});
const selectedApplication = ref<any | null>(null);

function dismissNotification(id: number) {
    if (dismissedNotificationIds.value.includes(id)) {
        return;
    }

    dismissedNotificationIds.value = [...dismissedNotificationIds.value, id];
}

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

function licenseStatusBadgeClass(status?: string | null) {
    if (status === 'Aktif') {
        return 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300';
    }

    if (status === 'Tamat Tempoh') {
        return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
    }

    if (status === 'Disekat') {
        return 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300';
    }

    return 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-100';
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
                        class="flex items-start justify-between gap-3 rounded-lg border px-3 py-2 text-sm"
                        :class="[
                            alert.tone === 'warning' && 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-200',
                            alert.tone === 'danger' && 'border-red-200 bg-red-50 text-red-800 dark:border-red-900/60 dark:bg-red-900/20 dark:text-red-200',
                        ]"
                    >
                        <div>
                            <p class="font-semibold">{{ alert.title }}</p>
                            <p>{{ alert.message }}</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold hover:bg-black/10 dark:hover:bg-white/10"
                            @click="dismissNotification(alert.id)"
                        >
                            X
                        </button>
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
                            <span
                                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                :class="licenseStatusBadgeClass(app.license_status)"
                            >
                                {{ app.license_status ?? '-' }}
                            </span>
                        </button>

                        <Link
                            v-if="!isStaff"
                            href="/license/apply"
                            class="flex h-40 w-40 shrink-0 cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-blue-300 bg-blue-50 text-blue-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-100 hover:shadow-md dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-300 dark:hover:bg-blue-900/30"
                            title="Mohon Lesen Baru"
                        >
                            <span class="text-4xl font-bold leading-none">+</span>
                            <span class="mt-3 text-center text-xs font-semibold">Mohon Lesen Baru</span>
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
                                <span
                                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                    :class="licenseStatusBadgeClass(selectedApplication.license_status)"
                                >
                                    {{ selectedApplication.license_status || '-' }}
                                </span>
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
