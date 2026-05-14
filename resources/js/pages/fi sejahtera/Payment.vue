<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Upload, FileText } from 'lucide-vue-next';
import FiSejahteraNavbar from '@/components/fi-sejahtera/FiSejahteraNavbar.vue';
import FiSejahteraSidebar from '@/components/fi-sejahtera/FiSejahteraSidebar.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type AppPageProps } from '@/types';

interface HotelOption {
    id: number;
    name: string;
    is_expired?: boolean;
}

const page = usePage<AppPageProps<{ ownedHotels: HotelOption[]; selectedHotelId?: number }>>();
const ownedHotels = computed<HotelOption[]>(() => ((page.props as any).ownedHotels ?? []) as HotelOption[]);

const selectedHotelId = ref('');
const selectedMonth = ref('');
const selectedYear = ref('');
const selectedSubmissionId = ref('');
const document1Name = ref('');
const document2Name = ref('');

const form = useForm<{
    submission_id: string;
    hotel_id: string;
    month: string;
    year: string;
    payment_proof: File | null;
    guest_report: File | null;
}>({
    submission_id: '',
    hotel_id: '',
    month: '',
    year: '',
    payment_proof: null,
    guest_report: null,
});

const isSubmitting = computed(() => form.processing);
const selectedHotelIsExpired = computed(() => {
    const selected = ownedHotels.value.find((hotel) => String(hotel.id) === selectedHotelId.value);
    return Boolean(selected?.is_expired);
});
const completionNotice = computed(() => {
    const flash = (page.props as any).flash;

    return flash?.success ?? flash?.payment?.message ?? '';
});

function onDocumentChange(target: 'document1' | 'document2', event: Event) {
    const input = event.target as HTMLInputElement;
    const fileName = input.files?.[0]?.name ?? '';
    const file = input.files?.[0] ?? null;

    if (target === 'document1') {
        document1Name.value = fileName;
        form.payment_proof = file;
        return;
    }

    document2Name.value = fileName;
    form.guest_report = file;
}

function submitPaymentProof() {
    if (selectedHotelIsExpired.value) {
        return;
    }

    form.submission_id = selectedSubmissionId.value;
    form.hotel_id = selectedHotelId.value;
    form.month = selectedMonth.value;
    form.year = selectedYear.value;

    form.post('/fi-sejahtera/payment', {
        forceFormData: true,
    });
}

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const submissionId = params.get('submission_id');
    const hotelId = params.get('hotel_id');
    const month = params.get('month');
    const year = params.get('year');

    if (submissionId) {
        selectedSubmissionId.value = submissionId;
    }

    if (hotelId) {
        selectedHotelId.value = hotelId;
    } else {
        selectedHotelId.value = String((page.props as any).selectedHotelId ?? ownedHotels.value[0]?.id ?? '');
    }

    if (month) {
        selectedMonth.value = month;
    }

    if (year) {
        selectedYear.value = year;
    }
});
</script>

<template>
    <Head title="Hantar Bukti Pembayaran" />

    <div class="flex min-h-screen bg-muted/30">
        <FiSejahteraSidebar />

        <div class="flex min-h-screen flex-1 flex-col">
            <FiSejahteraNavbar />

            <main class="flex-1 space-y-6 p-6">
                <div
                    v-if="completionNotice"
                    class="rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800"
                >
                    {{ completionNotice }}
                </div>

                <div>
                    <h1 class="text-2xl font-bold text-foreground">Hantar Bukti Pembayaran</h1>
                    <p class="text-sm text-muted-foreground">Sila pilih bulan, tahun dan muat naik dokumen berkaitan.</p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Borang Bukti Pembayaran</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-6" @submit.prevent="submitPaymentProof">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-foreground">Hotel</label>
                                    <select
                                        v-model="selectedHotelId"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">Pilih Hotel</option>
                                        <option
                                            v-for="hotel in ownedHotels"
                                            :key="hotel.id"
                                            :value="String(hotel.id)"
                                            :disabled="hotel.is_expired"
                                        >
                                            {{ hotel.name }}{{ hotel.is_expired ? ' ⚠' : '' }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.hotel_id" class="text-xs text-red-600">{{ form.errors.hotel_id }}</p>
                                    <p v-if="selectedHotelIsExpired" class="text-xs text-red-600">Lesen Penginapan Tamat Tempoh.</p>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-foreground">Bulan</label>
                                    <select
                                        v-model="selectedMonth"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">Pilih Bulan</option>
                                        <option value="jan">Januari</option>
                                        <option value="feb">Februari</option>
                                        <option value="mar">Mac</option>
                                        <option value="apr">April</option>
                                        <option value="may">Mei</option>
                                        <option value="jun">Jun</option>
                                        <option value="jul">Julai</option>
                                        <option value="aug">Ogos</option>
                                        <option value="sep">September</option>
                                        <option value="oct">Oktober</option>
                                        <option value="nov">November</option>
                                        <option value="dec">Disember</option>
                                    </select>
                                    <p v-if="form.errors.month" class="text-xs text-red-600">{{ form.errors.month }}</p>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-foreground">Tahun</label>
                                    <input
                                        v-model="selectedYear"
                                        type="number"
                                        min="2000"
                                        max="2100"
                                        placeholder="Contoh: 2026"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    />
                                    <p v-if="form.errors.year" class="text-xs text-red-600">{{ form.errors.year }}</p>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 p-4 dark:border-slate-600 dark:bg-slate-900/40">
                                    <div class="mb-3 flex items-start gap-3">
                                        <div class="rounded-lg bg-blue-100 p-2 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">
                                            <Upload class="h-4 w-4" />
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-foreground">Bukti Pembayaran</label>
                                            <p class="text-xs text-muted-foreground">Muat naik fail bukti pembayaran terkini.</p>
                                        </div>
                                    </div>

                                    <input
                                        id="document1"
                                        type="file"
                                        class="hidden"
                                        @change="onDocumentChange('document1', $event)"
                                    />
                                    <label
                                        for="document1"
                                        class="inline-flex cursor-pointer items-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                                    >
                                        Pilih Fail
                                    </label>

                                    <p v-if="document1Name" class="mt-3 flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                                        <FileText class="h-3.5 w-3.5" />
                                        {{ document1Name }}
                                    </p>
                                    <p v-if="form.errors.payment_proof" class="mt-2 text-xs text-red-600">{{ form.errors.payment_proof }}</p>
                                </div>

                                <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 p-4 dark:border-slate-600 dark:bg-slate-900/40">
                                    <div class="mb-3 flex items-start gap-3">
                                        <div class="rounded-lg bg-blue-100 p-2 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300">
                                            <Upload class="h-4 w-4" />
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-foreground">Laporan Bulanan Pengunjung</label>
                                            <p class="text-xs text-muted-foreground">Muat naik laporan bilangan pengunjung bulanan.</p>
                                        </div>
                                    </div>

                                    <input
                                        id="document2"
                                        type="file"
                                        class="hidden"
                                        @change="onDocumentChange('document2', $event)"
                                    />
                                    <label
                                        for="document2"
                                        class="inline-flex cursor-pointer items-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                                    >
                                        Pilih Fail
                                    </label>

                                    <p v-if="document2Name" class="mt-3 flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                                        <FileText class="h-3.5 w-3.5" />
                                        {{ document2Name }}
                                    </p>
                                    <p v-if="form.errors.guest_report" class="mt-2 text-xs text-red-600">{{ form.errors.guest_report }}</p>
                                </div>
                            </div>

                            <div>
                                <button
                                    type="submit"
                                    :disabled="isSubmitting || selectedHotelIsExpired"
                                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                                >
                                    {{ isSubmitting ? 'Submitting...' : 'Submit' }}
                                </button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </main>
        </div>
    </div>
</template>
