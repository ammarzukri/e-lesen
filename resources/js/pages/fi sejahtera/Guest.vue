<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { DollarSign, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import FiSejahteraNavbar from '@/components/fi-sejahtera/FiSejahteraNavbar.vue';
import FiSejahteraSidebar from '@/components/fi-sejahtera/FiSejahteraSidebar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type AppPageProps } from '@/types';

interface HotelOption {
    id: number;
    name: string;
    is_expired?: boolean;
}

interface GuestRow {
    id: number;
    hotel_id: number;
    hotel_name?: string;
    name?: string;
    nationality?: string;
    identity_number?: string;
    email?: string;
    phone_number?: string;
    total_room?: number;
    total_night?: number;
    amount?: number | string;
    payment_method?: string;
    created_at?: string;
}

interface GuestFilters {
    search?: string;
    hotel_id?: string;
    start_date?: string;
    end_date?: string;
}

const page = usePage<
    AppPageProps<{
        guests: GuestRow[];
        hotels: HotelOption[];
        hasHotel: boolean;
        canSelectHotel: boolean;
        filters: GuestFilters;
    }>
>();

const guests = computed<GuestRow[]>(() => ((page.props as any).guests ?? []) as GuestRow[]);
const hotels = computed<HotelOption[]>(() => ((page.props as any).hotels ?? []) as HotelOption[]);
const hasHotel = computed<boolean>(() => Boolean((page.props as any).hasHotel));
const canSelectHotel = computed<boolean>(() => Boolean((page.props as any).canSelectHotel));
const totalTax = computed<string>(() => {
    const total = guests.value.reduce((sum, guest) => {
        const amount = Number(guest.amount ?? 0);
        return sum + (Number.isFinite(amount) ? amount : 0);
    }, 0);

    return total.toLocaleString('ms-MY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
});

function getMonthDateRange() {
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    const toDateInputValue = (value: Date) => {
        const year = value.getFullYear();
        const month = String(value.getMonth() + 1).padStart(2, '0');
        const day = String(value.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    return {
        startDate: toDateInputValue(firstDay),
        endDate: toDateInputValue(lastDay),
    };
}

const defaultMonthRange = getMonthDateRange();

const search = ref<string>((page.props as any).filters?.search ?? '');
const selectedHotelId = ref<string>((page.props as any).filters?.hotel_id ?? '');
const startDate = ref<string>((page.props as any).filters?.start_date ?? defaultMonthRange.startDate);
const endDate = ref<string>((page.props as any).filters?.end_date ?? defaultMonthRange.endDate);

function applyFilters() {
    router.get(
        '/fi-sejahtera/guest',
        {
            search: search.value || undefined,
            hotel_id: selectedHotelId.value || undefined,
            start_date: startDate.value,
            end_date: endDate.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function resetFilters() {
    search.value = '';
    selectedHotelId.value = '';
    startDate.value = '';
    endDate.value = '';
    applyFilters();
}

function exportToPdf() {
    const query = new URLSearchParams();

    if (search.value) {
        query.set('search', search.value);
    }

    if (selectedHotelId.value) {
        query.set('hotel_id', selectedHotelId.value);
    }

    if (startDate.value) {
        query.set('start_date', startDate.value);
    }

    if (endDate.value) {
        query.set('end_date', endDate.value);
    }

    const queryString = query.toString();
    const url = queryString ? `/fi-sejahtera/guest/export-pdf?${queryString}` : '/fi-sejahtera/guest/export-pdf';

    window.location.href = url;
}

function formatCreatedAt(value?: string) {
    if (!value) {
        return '-';
    }

    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2}))?/);

    if (!match) {
        return value;
    }

    const [, year, month, day, hour, minute] = match;
    const formattedDate = `${day}-${month}-${year}`;

    if (hour && minute) {
        return `${formattedDate} ${hour}:${minute}`;
    }

    return formattedDate;
}
</script>

<template>
    <Head title="Rekod Tetamu" />

    <div class="flex min-h-screen bg-muted/30">
        <FiSejahteraSidebar />

        <div class="flex min-h-screen flex-1 flex-col">
            <FiSejahteraNavbar />

            <main class="flex-1 space-y-6 p-6">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">Rekod Tetamu</h1>
                    <p class="text-sm text-muted-foreground">Paparan tetamu berdasarkan rumah tumpangan dan carian.</p>
                </div>

                <Card v-if="!hasHotel">
                    <CardContent class="pt-6 text-sm text-muted-foreground">
                        Rumah tumpangan tidak ditemui untuk akaun ini. Lengkapkan data rumah tumpangan terlebih dahulu di modul E-Lesen.
                    </CardContent>
                </Card>

                <template v-else>
                <Card>
                    <CardHeader>
                        <CardTitle>Carian Tetamu</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="grid grid-cols-1 gap-4 md:grid-cols-3" @submit.prevent="applyFilters">
                            <div class="md:col-span-2">
                                <Label for="search_guest" class="mb-1">Carian</Label>
                                <Input
                                    id="search_guest"
                                    v-model="search"
                                    placeholder="Cari nama, no identiti, emel, atau no telefon"
                                />
                            </div>

                            <div v-if="canSelectHotel">
                                <Label for="hotel_filter" class="mb-1">Rumah Tumpangan</Label>
                                <select
                                    id="hotel_filter"
                                    v-model="selectedHotelId"
                                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">Semua Rumah Tumpangan</option>
                                    <option
                                        v-for="hotel in hotels"
                                        :key="hotel.id"
                                        :value="String(hotel.id)"
                                        :disabled="hotel.is_expired"
                                    >
                                        {{ hotel.name }}{{ hotel.is_expired ? ' ⚠' : '' }}
                                    </option>
                                </select>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:col-span-3 md:grid-cols-2">
                                <div>
                                    <Label for="start_date" class="mb-1">Tarikh Mula</Label>
                                    <Input id="start_date" v-model="startDate" type="date" class="w-full" />
                                </div>

                                <div>
                                    <Label for="end_date" class="mb-1">Tarikh Tamat</Label>
                                    <Input id="end_date" v-model="endDate" type="date" class="w-full" />
                                </div>
                            </div>

                            <div class="md:col-span-3 flex items-center gap-2">
                                <Button type="submit">Cari</Button>
                                <Button type="button" variant="outline" @click="resetFilters">Reset</Button>
                                <Button type="button" variant="secondary" @click="exportToPdf">Eksport ke PDF</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Card>
                        <CardContent class="flex items-center gap-3 p-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <Users class="h-5 w-5" />
                            </div>
                            <div class="text-left leading-tight">
                                <p class="text-sm font-medium text-muted-foreground">Jumlah Tetamu</p>
                                <p class="mt-1 text-2xl font-bold text-foreground">{{ guests.length }}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent class="flex items-center gap-3 p-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <DollarSign class="h-5 w-5" />
                            </div>
                            <div class="text-left leading-tight">
                                <p class="text-sm font-medium text-muted-foreground">Jumlah Cukai</p>
                                <p class="mt-1 text-2xl font-bold text-foreground">{{ totalTax }}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Senarai Tetamu</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="text-sm text-muted-foreground">
                                        <th class="p-2">Bil.</th>
                                        <th class="p-2">Nama</th>
                                        <th class="p-2">No Identiti (IC/Passport)</th>
                                        <th class="p-2">Emel</th>
                                        <th class="p-2">No Telefon</th>
                                        <th class="p-2">Rumah Tumpangan</th>
                                        <th class="p-2">Kuantiti Bilik</th>
                                        <th class="p-2">Jumlah Malam</th>
                                        <th class="p-2">Jumlah (RM)</th>
                                        <th class="p-2">Tarikh</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(guest, index) in guests"
                                        :key="guest.id"
                                        class="border-t"
                                        :class="index % 2 === 0 ? 'bg-background' : 'bg-muted'"
                                    >
                                        <td class="p-2">{{ Number(index) + 1 }}</td>
                                        <td class="p-2">{{ guest.name || '-' }}</td>
                                        <td class="p-2">{{ guest.identity_number || '-' }}</td>
                                        <td class="p-2">{{ guest.email || '-' }}</td>
                                        <td class="p-2">{{ guest.phone_number || '-' }}</td>
                                        <td class="p-2">{{ guest.hotel_name || '-' }}</td>
                                        <td class="p-2">{{ guest.total_room ?? '-' }}</td>
                                        <td class="p-2">{{ guest.total_night ?? '-' }}</td>
                                        <td class="p-2">{{ guest.amount ?? '-' }}</td>
                                        <td class="p-2">{{ formatCreatedAt(guest.created_at) }}</td>
                                    </tr>
                                    <tr v-if="guests.length === 0" class="border-t">
                                        <td colspan="10" class="p-2 text-sm text-muted-foreground">
                                            Tiada rekod tetamu dijumpai.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
                </template>
            </main>
        </div>
    </div>
</template>
