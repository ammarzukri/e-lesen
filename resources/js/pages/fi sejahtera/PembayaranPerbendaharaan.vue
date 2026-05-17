<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';

import FiSejahteraNavbar from '@/components/fi-sejahtera/FiSejahteraNavbar.vue';
import FiSejahteraSidebar from '@/components/fi-sejahtera/FiSejahteraSidebar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type AppPageProps } from '@/types';

interface HotelOption {
    id: number;
    name: string;
    is_expired?: boolean;
}

interface DailyBreakdownRow {
    date: string;
    date_label: string;
    total_room: number;
    total_amount: number;
}

interface TreasuryFilters {
    hotel_id: string;
    month: number;
    year: number;
}

interface TreasurySummary {
    total_room: number;
    total_amount: number;
    daily_breakdown: DailyBreakdownRow[];
}

const page = usePage<AppPageProps<{
    ownedHotels: HotelOption[];
    filters: TreasuryFilters;
    summary: TreasurySummary;
}>>();

const ownedHotels = computed<HotelOption[]>(() => ((page.props as any).ownedHotels ?? []) as HotelOption[]);
const filters = computed<TreasuryFilters>(() => ((page.props as any).filters ?? {}) as TreasuryFilters);
const summary = computed<TreasurySummary>(() => ((page.props as any).summary ?? {
    total_room: 0,
    total_amount: 0,
    daily_breakdown: [],
}) as TreasurySummary);

const selectedHotelId = ref(String(filters.value.hotel_id ?? ''));
const selectedMonth = ref(Number(filters.value.month ?? new Date().getMonth() + 1));
const selectedYear = ref(Number(filters.value.year ?? new Date().getFullYear()));

watch(
    filters,
    (nextFilters) => {
        selectedHotelId.value = String(nextFilters.hotel_id ?? '');
        selectedMonth.value = Number(nextFilters.month ?? new Date().getMonth() + 1);
        selectedYear.value = Number(nextFilters.year ?? new Date().getFullYear());
    },
    { deep: true },
);

const monthOptions = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Mac' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Jun' },
    { value: 7, label: 'Julai' },
    { value: 8, label: 'Ogos' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Disember' },
];

const yearOptions = computed(() => {
    const currentYear = new Date().getFullYear();

    return Array.from({ length: 8 }, (_, index) => currentYear - 5 + index);
});

const totalRoom = computed(() => Number(summary.value.total_room ?? 0));
const totalAmount = computed(() => Number(summary.value.total_amount ?? 0));
const dailyBreakdown = computed<DailyBreakdownRow[]>(() => summary.value.daily_breakdown ?? []);

const dailyTotalRoom = computed(() =>
    dailyBreakdown.value.reduce((total, row) => total + Number(row.total_room ?? 0), 0),
);
const dailyTotalAmount = computed(() =>
    dailyBreakdown.value.reduce((total, row) => total + Number(row.total_amount ?? 0), 0),
);

const canPay = computed(() => selectedHotelId.value !== '' && !selectedHotelIsExpired.value);
const selectedHotelIsExpired = computed(() => {
    const selected = ownedHotels.value.find((hotel) => String(hotel.id) === selectedHotelId.value);
    return Boolean(selected?.is_expired);
});

function formatCurrency(value: number) {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
        minimumFractionDigits: 2,
    }).format(Number(value || 0));
}

function formatNumber(value: number) {
    return new Intl.NumberFormat('ms-MY').format(Number(value || 0));
}

function displayRoom(value: number) {
    return value > 0 ? formatNumber(value) : '-';
}

function displayCurrency(value: number) {
    return value > 0 ? formatCurrency(value) : '-';
}

function applyFilters() {
    router.get('/fi-sejahtera/perbendaharaan', {
        hotel_id: selectedHotelId.value,
        month: String(selectedMonth.value),
        year: String(selectedYear.value),
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function goToBayar() {
    if (!canPay.value) {
        return;
    }

    const monthMap = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

    router.get('/fi-sejahtera/payment', {
        hotel_id: selectedHotelId.value,
        month: monthMap[Math.max(0, Math.min(11, selectedMonth.value - 1))],
        year: String(selectedYear.value),
    });
}
</script>

<template>
    <Head title="Pembayaran ke Perbendaharaan" />

    <div class="flex min-h-screen bg-muted/30">
        <FiSejahteraSidebar />

        <div class="flex min-h-screen flex-1 flex-col">
            <FiSejahteraNavbar />

            <main class="flex-1 space-y-6 p-6">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">Pembayaran ke Perbendaharaan</h1>
                    <p class="text-sm text-muted-foreground">
                        Semak jumlah kutipan cukai bulanan sebelum membuat bayaran kepada bendahari.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Carian Kutipan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-foreground">Hotel</label>
                                <select
                                    v-model="selectedHotelId"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">-- Pilih Hotel --</option>
                                    <option
                                        v-for="hotel in ownedHotels"
                                        :key="hotel.id"
                                        :value="String(hotel.id)"
                                        :disabled="hotel.is_expired"
                                    >
                                        {{ hotel.name }}{{ hotel.is_expired ? ' ⚠' : '' }}
                                    </option>
                                </select>
                                <p v-if="selectedHotelIsExpired" class="text-xs text-red-600">Lesen Penginapan Tamat Tempoh.</p>
                            </div>

                            <div class="space-y-2">
                                <label class="text-sm font-medium text-foreground">Bulan</label>
                                <select
                                    v-model.number="selectedMonth"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option v-for="month in monthOptions" :key="month.value" :value="month.value">
                                        {{ month.label }}
                                    </option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <label class="text-sm font-medium text-foreground">Tahun</label>
                                <select
                                    v-model.number="selectedYear"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option v-for="year in yearOptions" :key="year" :value="year">
                                        {{ year }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <Button type="button" @click="applyFilters">Semak</Button>
                        </div>
                    </CardContent>
                </Card>

                <div class="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Jumlah Bilik</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-3xl font-bold text-foreground">{{ formatNumber(totalRoom) }}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Jumlah Perlu Dibayar</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-3xl font-bold text-foreground">{{ formatCurrency(totalAmount) }}</p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Butiran Kutipan Harian</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <details open class="rounded-lg border border-border bg-background">
                            <summary class="cursor-pointer list-none px-4 py-3 text-sm font-semibold text-foreground">
                                <div class="flex items-center justify-between">
                                    <span>Butiran Harian</span>
                                    <button
                                        type="button"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-500 text-base font-bold leading-none text-foreground hover:bg-muted"
                                        aria-label="Tambah"
                                    >
                                        +
                                    </button>
                                </div>
                            </summary>

                            <div class="px-4 pb-4">
                                <div class="mx-auto w-full max-w-3xl overflow-x-auto">
                                    <table class="w-full border-collapse border-2 border-slate-400 text-sm dark:border-slate-600">
                                    <thead class="bg-muted/40 text-left text-muted-foreground">
                                        <tr>
                                            <th class="border-2 border-slate-400 px-4 py-3 font-medium dark:border-slate-600">Tarikh</th>
                                            <th class="border-2 border-slate-400 px-4 py-3 font-medium dark:border-slate-600">Jumlah Bilik</th>
                                            <th class="border-2 border-slate-400 px-4 py-3 font-medium dark:border-slate-600">Jumlah Kutipan (RM)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="dailyBreakdown.length === 0">
                                            <td colspan="3" class="border-2 border-slate-400 px-4 py-4 text-center text-muted-foreground dark:border-slate-600">
                                                Tiada rekod untuk carian yang dipilih.
                                            </td>
                                        </tr>

                                        <tr
                                            v-for="row in dailyBreakdown"
                                            :key="row.date"
                                        >
                                            <td class="border-2 border-slate-400 px-4 py-3 dark:border-slate-600">{{ row.date_label }}</td>
                                            <td class="border-2 border-slate-400 px-4 py-3 dark:border-slate-600">{{ displayRoom(row.total_room) }}</td>
                                            <td class="border-2 border-slate-400 px-4 py-3 dark:border-slate-600">{{ displayCurrency(row.total_amount) }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="bg-muted/20 font-semibold text-foreground">
                                        <tr>
                                            <td class="border-2 border-slate-400 px-4 py-3 dark:border-slate-600">Jumlah</td>
                                            <td class="border-2 border-slate-400 px-4 py-3 dark:border-slate-600">{{ formatNumber(dailyTotalRoom) }}</td>
                                            <td class="border-2 border-slate-400 px-4 py-3 dark:border-slate-600">{{ formatCurrency(dailyTotalAmount) }}</td>
                                        </tr>
                                    </tfoot>
                                    </table>
                                </div>
                            </div>
                        </details>

                        <div class="flex justify-end">
                            <Button type="button" :disabled="!canPay" @click="goToBayar">
                                Bayar
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </main>
        </div>
    </div>
</template>
