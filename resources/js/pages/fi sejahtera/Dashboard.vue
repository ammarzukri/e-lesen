<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { BedDouble, DollarSign, Users } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import FiSejahteraNavbar from '@/components/fi-sejahtera/FiSejahteraNavbar.vue';
import FiSejahteraSidebar from '@/components/fi-sejahtera/FiSejahteraSidebar.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type AppPageProps } from '@/types';

const page = usePage<AppPageProps>();

interface FrequencyRow {
    label: string;
    total: number;
}

interface HotelOption {
    id: number;
    name: string;
    is_expired?: boolean;
}

interface PieSlice {
    label: string;
    total: number;
    percent: number;
    color: string;
}

const monthlyStats = computed(() => {
    return ((page.props as any).monthlyStats ?? {
        totalGuests: 0,
        totalTaxPaid: 0,
    }) as { totalGuests: number; totalTaxPaid: number };
});

const countryFrequency = computed<FrequencyRow[]>(() => {
    return ((page.props as any).countryFrequency ?? []) as FrequencyRow[];
});

const stateFrequency = computed<FrequencyRow[]>(() => {
    return ((page.props as any).stateFrequency ?? []) as FrequencyRow[];
});

const hotels = computed<HotelOption[]>(() => {
    return ((page.props as any).hotels ?? []) as HotelOption[];
});

const canSelectHotel = computed<boolean>(() => Boolean((page.props as any).canSelectHotel));

const selectedHotelId = ref<string>((page.props as any).filters?.hotel_id ?? '');
const hotelSearch = ref<string>('');
const hotelDropdownOpen = ref<boolean>(false);
const hotelDropdownRef = ref<HTMLElement | null>(null);

const filteredHotels = computed<HotelOption[]>(() => {
    const keyword = hotelSearch.value.trim().toLowerCase();

    if (!keyword) {
        return hotels.value;
    }

    return hotels.value.filter((hotel) => hotel.name.toLowerCase().includes(keyword));
});

const selectedHotelLabel = computed(() => {
    if (!selectedHotelId.value) {
        return 'Semua Hotel';
    }

    const selected = hotels.value.find((hotel) => String(hotel.id) === selectedHotelId.value);
    return selected?.name ?? 'Semua Hotel';
});

const topCountryTotal = computed(() => {
    return Math.max(1, ...countryFrequency.value.map((item) => Number(item.total) || 0));
});

const topStateTotal = computed(() => {
    return Math.max(1, ...stateFrequency.value.map((item) => Number(item.total) || 0));
});

const pieColors = [
    '#2563eb',
    '#16a34a',
    '#dc2626',
    '#f59e0b',
    '#7c3aed',
    '#0ea5e9',
    '#14b8a6',
    '#f97316',
    '#64748b',
    '#84cc16',
    '#ec4899',
    '#22c55e',
    '#3b82f6',
    '#ef4444',
    '#a855f7',
    '#06b6d4',
    '#eab308',
    '#10b981',
    '#8b5cf6',
    '#fb7185',
];

function buildPieSlices(rows: FrequencyRow[]): PieSlice[] {
    const total = rows.reduce((sum, row) => sum + (Number(row.total) || 0), 0);

    if (total <= 0) {
        return [];
    }

    return rows.map((row, index) => {
        const rowTotal = Number(row.total) || 0;
        const percent = (rowTotal / total) * 100;

        return {
            label: row.label,
            total: rowTotal,
            percent,
            color: pieColors[index % pieColors.length],
        };
    });
}

function buildPieGradient(slices: PieSlice[]): string {
    if (slices.length === 0) {
        return 'conic-gradient(var(--muted) 0deg 360deg)';
    }

    let start = 0;
    const stops = slices.map((slice) => {
        const end = start + (slice.percent / 100) * 360;
        const stop = `${slice.color} ${start}deg ${end}deg`;
        start = end;
        return stop;
    });

    return `conic-gradient(${stops.join(', ')})`;
}

const countryPieSlices = computed(() => buildPieSlices(countryFrequency.value));
const statePieSlices = computed(() => buildPieSlices(stateFrequency.value));

const countryPieStyle = computed(() => ({
    backgroundImage: buildPieGradient(countryPieSlices.value),
}));

const statePieStyle = computed(() => ({
    backgroundImage: buildPieGradient(statePieSlices.value),
}));

function applyHotelFilter() {
    router.get(
        '/fi-sejahtera/dashboard',
        {
            hotel_id: selectedHotelId.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function toggleHotelDropdown() {
    hotelDropdownOpen.value = !hotelDropdownOpen.value;
}

function chooseHotel(hotelId?: number) {
    if (hotelId) {
        const selectedHotel = hotels.value.find((hotel) => hotel.id === hotelId);

        if (selectedHotel?.is_expired) {
            return;
        }
    }

    selectedHotelId.value = hotelId ? String(hotelId) : '';
    hotelDropdownOpen.value = false;
}

function handleOutsideClick(event: MouseEvent) {
    if (!hotelDropdownRef.value) {
        return;
    }

    const target = event.target as Node;

    if (!hotelDropdownRef.value.contains(target)) {
        hotelDropdownOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleOutsideClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleOutsideClick);
});

function formatCurrency(value: number) {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
        minimumFractionDigits: 2,
    }).format(value);
}
</script>

<template>
    <Head title="Fi Sejahtera" />

    <div class="flex min-h-screen bg-muted/30">
        <FiSejahteraSidebar />

        <div class="flex min-h-screen flex-1 flex-col">
            <FiSejahteraNavbar />

            <main class="flex-1 space-y-6 p-6">
                <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-foreground">
                            Selamat Datang, {{ page.props.auth.user.name }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            Ringkasan dashboard Fi Sejahtera.
                        </p>
                    </div>

                    <div v-if="canSelectHotel" class="w-full md:w-auto md:min-w-72">
                        <label class="mb-1 block text-sm font-medium text-foreground">Pilih Hotel</label>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <div ref="hotelDropdownRef" class="relative w-full">
                                <button
                                    type="button"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-left text-sm"
                                    @click="toggleHotelDropdown"
                                >
                                    {{ selectedHotelLabel }}
                                </button>

                                <div
                                    v-if="hotelDropdownOpen"
                                    class="absolute z-20 mt-1 w-full rounded-md border border-input bg-background p-2 shadow-lg"
                                >
                                    <input
                                        v-model="hotelSearch"
                                        type="text"
                                        placeholder="Cari nama hotel..."
                                        class="mb-2 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    />
                                    <div class="max-h-56 overflow-auto">
                                        <button
                                            type="button"
                                            class="w-full rounded px-2 py-2 text-left text-sm hover:bg-muted"
                                            @click="chooseHotel()"
                                        >
                                            Semua Hotel
                                        </button>
                                        <button
                                            v-for="hotel in filteredHotels"
                                            :key="hotel.id"
                                            type="button"
                                            :disabled="hotel.is_expired"
                                            class="w-full rounded px-2 py-2 text-left text-sm hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                            @click="chooseHotel(hotel.id)"
                                        >
                                            {{ hotel.name }} <span v-if="hotel.is_expired">⚠</span>
                                        </button>
                                        <p v-if="filteredHotels.length === 0" class="px-2 py-2 text-sm text-muted-foreground">
                                            Tiada hotel dijumpai.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-gray-900"
                                @click="applyHotelFilter"
                            >
                                Tapis
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardContent class="pt-4 pb-4">
                            <div class="flex items-start gap-3">
                                <div class="rounded-lg bg-muted p-2.5">
                                    <Users class="h-6 w-6 text-foreground" />
                                </div>
                                <div>
                                    <p class="text-base font-semibold text-foreground">Jumlah Tetamu Bulan Ini</p>
                                    <p class="text-2xl font-bold text-foreground">{{ monthlyStats.totalGuests }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent class="pt-4 pb-4">
                            <div class="flex items-start gap-3">
                                <div class="rounded-lg bg-muted p-2.5">
                                    <DollarSign class="h-6 w-6 text-foreground" />
                                </div>
                                <div>
                                    <p class="text-base font-semibold text-foreground">Jumlah Cukai Dibayar Bulan Ini</p>
                                    <p class="text-2xl font-bold text-foreground">{{ formatCurrency(Number(monthlyStats.totalTaxPaid) || 0) }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <Card class="min-h-80">
                        <CardHeader>
                            <CardTitle>Frekuensi Tetamu Mengikut Negara (Bulan Ini)</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="mb-5 space-y-3">
                                <div class="mx-auto h-40 w-40 rounded-full" :style="countryPieStyle"></div>
                                <div v-if="countryPieSlices.length > 0" class="space-y-1">
                                    <div
                                        v-for="(slice, index) in countryPieSlices"
                                        :key="`country-pie-${slice.label}-${index}`"
                                        class="flex items-center justify-between text-xs"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: slice.color }"></span>
                                            <span class="text-muted-foreground">{{ slice.label }}</span>
                                        </div>
                                        <span class="text-foreground">{{ slice.percent.toFixed(1) }}%</span>
                                    </div>
                                </div>
                                <p v-else class="text-center text-xs text-muted-foreground">
                                    Tiada data untuk carta pai bulan ini.
                                </p>
                            </div>

                            <div
                                v-for="(item, index) in countryFrequency"
                                :key="`${item.label}-${index}`"
                                class="space-y-1"
                            >
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-foreground">{{ item.label }}</span>
                                    <span class="text-muted-foreground">{{ item.total }}</span>
                                </div>
                                <div class="h-2 w-full rounded bg-muted">
                                    <div
                                        class="h-2 rounded bg-primary"
                                        :style="{ width: `${(item.total / topCountryTotal) * 100}%` }"
                                    ></div>
                                </div>
                            </div>

                            <p v-if="countryFrequency.length === 0" class="text-sm text-muted-foreground">
                                Tiada data negara untuk bulan ini.
                            </p>
                        </CardContent>
                    </Card>

                    <Card class="min-h-80">
                        <CardHeader>
                            <CardTitle>Frekuensi Tetamu Mengikut Negeri (Bulan Ini)</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="mb-5 space-y-3">
                                <div class="mx-auto h-40 w-40 rounded-full" :style="statePieStyle"></div>
                                <div v-if="statePieSlices.length > 0" class="space-y-1">
                                    <div
                                        v-for="(slice, index) in statePieSlices"
                                        :key="`state-pie-${slice.label}-${index}`"
                                        class="flex items-center justify-between text-xs"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: slice.color }"></span>
                                            <span class="text-muted-foreground">{{ slice.label }}</span>
                                        </div>
                                        <span class="text-foreground">{{ slice.percent.toFixed(1) }}%</span>
                                    </div>
                                </div>
                                <p v-else class="text-center text-xs text-muted-foreground">
                                    Tiada data untuk carta pai bulan ini.
                                </p>
                            </div>

                            <div
                                v-for="(item, index) in stateFrequency"
                                :key="`${item.label}-${index}`"
                                class="space-y-1"
                            >
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-foreground">{{ item.label }}</span>
                                    <span class="text-muted-foreground">{{ item.total }}</span>
                                </div>
                                <div class="h-2 w-full rounded bg-muted">
                                    <div
                                        class="h-2 rounded bg-primary"
                                        :style="{ width: `${(item.total / topStateTotal) * 100}%` }"
                                    ></div>
                                </div>
                            </div>

                            <p v-if="stateFrequency.length === 0" class="text-sm text-muted-foreground">
                                Tiada data negeri untuk bulan ini.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </main>
        </div>
    </div>
</template>
