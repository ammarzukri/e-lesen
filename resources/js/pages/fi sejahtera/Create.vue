<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted } from 'vue';

import FiSejahteraNavbar from '@/components/fi-sejahtera/FiSejahteraNavbar.vue';
import FiSejahteraSidebar from '@/components/fi-sejahtera/FiSejahteraSidebar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { type AppPageProps } from '@/types';
import axios from 'axios';

const page = usePage<AppPageProps>();

const props = withDefaults(
    defineProps<{
        initialHotelName?: string;
        isHotelNameFixed?: boolean;
        hasHotel?: boolean;
        ownedHotels?: Array<{ id: number; name: string; is_expired?: boolean }>;
    }>(),
    {
        initialHotelName: '',
        isHotelNameFixed: false,
        hasHotel: false,
        ownedHotels: () => [],
    },
);

type Nationality = 'Malaysian' | 'Non-Malaysian';

const form = ref({
    name: '',
    nationality: 'Malaysian' as Nationality,
    identity_type: 'IC',
    identity_number: '',
    email: '',
    phone_number: '',
    country: 'Malaysia',
    state: '',
    hotel_name: props.initialHotelName,
    total_room: '',
    total_night: '',
    payment_method: '',
    amount: '',
});

const errors = computed(() => 
(page.props.errors as Record<string, string> | undefined) ?? {});

const isMalaysian = computed(() => form.value.nationality === 'Malaysian');
const isHotelNameFixed = computed(() => props.isHotelNameFixed);
const hasHotel = computed(() => props.hasHotel);
const userRole = computed(() => page.props.auth?.user?.role ?? '');
const isUserRole = computed(() => userRole.value === 'user');
const displayHotelName = computed(() => {
    const hotelName = props.initialHotelName?.trim();
    return hotelName && hotelName.length > 0 ? hotelName : 'Hotel';
});
const pageTitle = computed(() =>
    isUserRole.value ? 'Kutipan Fi Sejahtera' : `Kutipan Fi Sejahtera ${displayHotelName.value}`,
);

const isSubmitting = ref(false);
const isVerificationDialogOpen = ref(false);
const isSuccessDialogOpen = ref(false);
const shouldRefreshAfterSuccess = ref(false);
const selectedHotelIsExpired = computed(() => {
    if (!isUserRole.value || !form.value.hotel_name) {
        return false;
    }

    const selectedHotel = props.ownedHotels.find((hotel) => hotel.name === form.value.hotel_name);
    return Boolean(selectedHotel?.is_expired);
});

const verificationItems = computed(() => {
    const valueOrDash = (value: string) => {
        const trimmed = value?.toString().trim();
        return trimmed && trimmed.length > 0 ? trimmed : '-';
    };

    return [
        { label: 'Nama', value: valueOrDash(form.value.name) },
        { label: 'Kewarganegaraan', value: valueOrDash(form.value.nationality) },
        { label: 'Jenis Identiti', value: valueOrDash(form.value.identity_type) },
        { label: 'Nombor Identiti', value: valueOrDash(form.value.identity_number) },
        { label: 'Alamat Emel', value: valueOrDash(form.value.email) },
        { label: 'Nombor Telefon', value: valueOrDash(form.value.phone_number) },
        { label: 'Negara', value: valueOrDash(form.value.country) },
        { label: 'Negeri', value: isMalaysian.value ? valueOrDash(form.value.state) : '-' },
        { label: 'Nama Hotel', value: valueOrDash(form.value.hotel_name) },
        { label: 'Jumlah Bilik', value: valueOrDash(form.value.total_room) },
        { label: 'Jumlah Malam', value: valueOrDash(form.value.total_night) },
        { label: 'Kaedah Pembayaran', value: valueOrDash(form.value.payment_method) },
        { label: 'Jumlah (RM)', value: valueOrDash(form.value.amount) },
    ];
});

watch(
    () => form.value.nationality,
    (nationality) => {
        form.value.identity_type = nationality === 'Malaysian' ? 'IC' : 'Passport';
        form.value.country = nationality === 'Malaysian' ? 'Malaysia' : form.value.country;
        form.value.state = nationality === 'Malaysian' ? form.value.state : '';
    },
    { immediate: true },
);

watch(
    [() => form.value.total_room, () => form.value.total_night],
    ([totalRoom, totalNight]) => {
        const room = Number(totalRoom) || 0;
        const night = Number(totalNight) || 0;
        const calculatedAmount = room * night * 3;

        form.value.amount = calculatedAmount > 0 ? calculatedAmount.toFixed(2) : '';
    },
    { immediate: true },
);

function submitForm() {
    if (selectedHotelIsExpired.value) {
        window.alert('Lesen Penginapan Tamat Tempoh');
        return;
    }

    isVerificationDialogOpen.value = true;
}

function proceedSubmitForm() {
    isVerificationDialogOpen.value = false;

    router.post('/fi-sejahtera/apply', form.value, {
        preserveScroll: true,
        onStart: () => {
            isSubmitting.value = true;
        },
        onSuccess: () => {
            shouldRefreshAfterSuccess.value = true;
            isSuccessDialogOpen.value = true;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
}

function refreshFormPage() {
    router.get('/fi-sejahtera/apply', {}, {
        preserveState: false,
        preserveScroll: false,
        replace: true,
    });
}

function closeSuccessDialog() {
    isSuccessDialogOpen.value = false;

    if (!shouldRefreshAfterSuccess.value) {
        return;
    }

    shouldRefreshAfterSuccess.value = false;
    refreshFormPage();
}

function handleSuccessDialogOpen(nextOpen: boolean) {
    if (nextOpen) {
        isSuccessDialogOpen.value = true;
        return;
    }

    if (!isSuccessDialogOpen.value) {
        return;
    }

    closeSuccessDialog();
}

interface Country {
    name: string;
    code: string;
}

const malaysiaStates = [
    'Johor',
    'Kedah',
    'Kelantan',
    'Melaka',
    'Negeri Sembilan',
    'Pahang',
    'Perak',
    'Perlis',
    'Pulau Pinang',
    'Sabah',
    'Sarawak',
    'Selangor',
    'Terengganu',
    'Wilayah Persekutuan Kuala Lumpur',
    'Wilayah Persekutuan Putrajaya',
    'Wilayah Persekutuan Labuan',
];

const countries = ref<Country[]>([]);

onMounted(async () => {
    try {
        const response = await axios.get('/countries');
        countries.value = Array.isArray(response.data) ? response.data : [];
    } catch {
        countries.value = [];
    }
});
</script>

<template>
    <Head title="Kutipan Fi Sejahtera" />

    <div class="flex min-h-screen bg-muted/30">
        <FiSejahteraSidebar />

        <div class="flex min-h-screen flex-1 flex-col">
            <FiSejahteraNavbar />

            <main class="flex-1 space-y-6 p-6">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">
                        {{ pageTitle }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Sila lengkapkan maklumat di bawah.
                    </p>
                </div>

                <Card v-if="!hasHotel">
                    <CardContent class="pt-6 text-sm text-muted-foreground">
                        Hotel tidak ditemui untuk akaun ini. Lengkapkan data hotel terlebih dahulu di modul E-Lesen.
                    </CardContent>
                </Card>

                <Card v-else>
                    <CardHeader>
                        <CardTitle>Maklumat Tetamu & Penginapan</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-8">
                        <div
                            v-if="errors.name"
                            class="rounded-xl bg-red-50 text-red-700 border border-red-200 px-4 py-3 text-sm dark:bg-red-900/30 dark:text-red-300 dark:border-red-900"
                        >
                            {{ errors.name }}
                        </div>

                        <section class="space-y-4">
                            <h2 class="text-base font-semibold text-foreground">Bahagian 1: Maklumat Tetamu</h2>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2 md:col-span-2">
                                    <label class="text-sm font-medium text-foreground">Nama</label>
                                    <input
                                        v-model="form.name"
                                        type="text"
                                        placeholder="Masukkan nama pengunjung"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    />
                                </div>

                                <div class="space-y-2 md:col-span-2">
                                    <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Kewarganegaraan</label>
                                    <div class="flex flex-col space-y-2">
                                        <label class="flex items-center">
                                            <input class="mr-2" type="radio" value="Malaysian" v-model="form.nationality" />
                                            <span class="text-slate-700 dark:text-slate-200">Warganegara</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input class="mr-2" type="radio" value="Non-Malaysian" v-model="form.nationality" />
                                            <span class="text-slate-700 dark:text-slate-200">Bukan Warganegara</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-foreground">Jenis Identiti</label>
                                    <input
                                        v-model="form.identity_type"
                                        type="text"
                                        readonly
                                        placeholder="Jenis identiti"
                                        class="w-full rounded-md border border-input bg-muted px-3 py-2 text-sm"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-foreground">Nombor Identiti</label>
                                    <input
                                        v-model="form.identity_number"
                                        type="text"
                                        placeholder="Nombor Kad Pengenalan / Nombor Pasport"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-foreground">Alamat Emel</label>
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        placeholder="contoh@emel.com"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-foreground">Nombor Telefon</label>
                                    <input
                                        v-model="form.phone_number"
                                        type="text"
                                        placeholder="Contoh: 0123456789"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-foreground">Negara</label>
                                    <select
                                        v-model="form.country"
                                        :disabled="isMalaysian"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option v-if="isMalaysian" value="Malaysia">Malaysia</option>
                                        <option v-else value="">-- Pilih Negara --</option>
                                        <option
                                            v-for="country in countries"
                                            :key="country.code"
                                            :value="country.name"
                                        >
                                            {{ country.name }}
                                        </option>
                                    </select>
                                </div>

                                <div v-if="isMalaysian" class="space-y-2">
                                    <label class="text-sm font-medium text-foreground">Negeri</label>
                                    <select
                                        v-model="form.state"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">-- Pilih Negeri --</option>
                                        <option
                                            v-for="state in malaysiaStates"
                                            :key="state"
                                            :value="state"
                                        >
                                            {{ state }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </section>

                        <hr class="border-t border-border" />

                        <section class="space-y-4">
                            <h2 class="text-base font-semibold text-foreground">Bahagian 2: Maklumat Penginapan</h2>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2 md:col-span-2">
                                    <label class="text-sm font-medium text-foreground">Nama Rumah Tumpangan</label>
                                    <select
                                        v-if="isUserRole"
                                        v-model="form.hotel_name"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">-- Pilih Hotel --</option>
                                        <option
                                            v-for="hotel in props.ownedHotels"
                                            :key="hotel.id"
                                            :value="hotel.name"
                                            :disabled="hotel.is_expired"
                                        >
                                            {{ hotel.name }}{{ hotel.is_expired ? ' ⚠' : '' }}
                                        </option>
                                    </select>
                                    <input
                                        v-else
                                        v-model="form.hotel_name"
                                        type="text"
                                        :readonly="isHotelNameFixed"
                                        placeholder="Masukkan nama hotel"
                                        :class="[
                                            'w-full rounded-md border border-input px-3 py-2 text-sm',
                                            isHotelNameFixed ? 'bg-muted' : 'bg-background',
                                        ]"
                                    />
                                    <p v-if="isHotelNameFixed" class="text-xs text-muted-foreground">
                                        Nama hotel ditetapkan mengikut hotel staf yang ditugaskan.
                                    </p>
                                    <p v-if="selectedHotelIsExpired" class="text-xs text-red-600">
                                        Lesen Penginapan Tamat Tempoh.
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-foreground">Jumlah Bilik</label>
                                    <input
                                        v-model="form.total_room"
                                        type="number"
                                        min="0"
                                        placeholder="Masukkan jumlah bilik"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-foreground">Jumlah Malam</label>
                                    <input
                                        v-model="form.total_night"
                                        type="number"
                                        min="0"
                                        placeholder="Masukkan jumlah malam"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Kaedah Pembayaran</label>
                                    <select v-model="form.payment_method"
                                            class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-black dark:text-slate-100 dark:border-slate-700">
                                        <option value="">-- Pilih kaedah pembayaran --</option>
                                        <option value="tunai">Tunai</option>
                                        <option value="qr">QR</option>
                                    </select>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-foreground">Jumlah (RM)</label>
                                    <input
                                        v-model="form.amount"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        readonly
                                        placeholder="bilik x malam x RM3"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    />
                                </div>
                            </div>
                        </section>

                        <div class="flex justify-end">
                            <Button
                                type="button"
                                @click="submitForm"
                                :disabled="isSubmitting || selectedHotelIsExpired"
                            >
                                {{ isSubmitting ? 'Sedang Disimpan...' : 'Simpan' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Dialog :open="isVerificationDialogOpen" @update:open="isVerificationDialogOpen = $event">
                    <DialogContent class="sm:max-w-2xl">
                        <DialogHeader>
                            <DialogTitle>Sahkan Maklumat Sebelum Simpan</DialogTitle>
                            <DialogDescription>
                                Sila semak maklumat di bawah. Tekan Teruskan untuk simpan atau Isi Semula untuk kembali edit.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="max-h-[55vh] space-y-3 overflow-y-auto pr-1">
                            <div
                                v-for="item in verificationItems"
                                :key="item.label"
                                class="grid grid-cols-1 gap-1 rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm md:grid-cols-[200px_minmax(0,1fr)] md:items-start"
                            >
                                <p class="font-medium text-foreground">{{ item.label }}</p>
                                <p class="wrap-break-word text-muted-foreground">{{ item.value }}</p>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="isVerificationDialogOpen = false">Isi Semula</Button>
                            <Button type="button" :disabled="isSubmitting" @click="proceedSubmitForm">
                                {{ isSubmitting ? 'Sedang Disimpan...' : 'Teruskan' }}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog :open="isSuccessDialogOpen" @update:open="handleSuccessDialogOpen">
                    <DialogContent class="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Simpanan Berjaya</DialogTitle>
                            <DialogDescription>
                                Maklumat tetamu berjaya disimpan.
                            </DialogDescription>
                        </DialogHeader>

                        <DialogFooter>
                            <Button type="button" @click="closeSuccessDialog">OK</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </main>
        </div>
    </div>
</template>
