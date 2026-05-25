<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';

import FiSejahteraNavbar from '@/components/fi-sejahtera/FiSejahteraNavbar.vue';
import FiSejahteraSidebar from '@/components/fi-sejahtera/FiSejahteraSidebar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type AppPageProps } from '@/types';

interface PaymentSubmissionRow {
    id: number;
    hotel_name?: string;
    month: string;
    year: number;
    payment_status?: string;
    total_amount: number;
    receipt_url?: string | null;
    guest_list_url?: string | null;
    hotel_guest_list_url?: string | null;
    status: string;
}

const page = usePage<AppPageProps<{ submissions: PaymentSubmissionRow[] }>>();

const submissions = computed<PaymentSubmissionRow[]>(() => ((page.props as any).submissions ?? []) as PaymentSubmissionRow[]);
const flashSuccess = computed<string>(() => ((page.props as any).flash?.success as string) ?? '');
const flashError = computed<string>(() => ((page.props as any).flash?.error as string) ?? '');

const selectedFiles = ref<Record<number, File | null>>({});
const uploadingSubmissionId = ref<number | null>(null);

const uploadForm = useForm<{
    submission_id: string;
    hotel_guest_list: File | null;
}>({
    submission_id: '',
    hotel_guest_list: null,
});

const submitToBktForm = useForm<{
    submission_id: string;
}>({
    submission_id: '',
});

const monthLabelMap: Record<string, string> = {
    jan: 'Januari',
    feb: 'Februari',
    mar: 'Mac',
    apr: 'April',
    may: 'Mei',
    jun: 'Jun',
    jul: 'Julai',
    aug: 'Ogos',
    sep: 'September',
    oct: 'Oktober',
    nov: 'November',
    dec: 'Disember',
};

function formatMonthYear(month: string, year: number) {
    return `${monthLabelMap[month] ?? month.toUpperCase()} ${year}`;
}

function formatCurrency(value: number) {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
        minimumFractionDigits: 2,
    }).format(Number(value || 0));
}

function formatPaymentStatus(status?: string) {
    if (status === 'Berjaya') {
        return 'Berjaya';
    }

    if (status === 'Dalam Proses') {
        return 'Dalam Proses';
    }

    if (status === 'Gagal') {
        return 'Gagal';
    }

    return status || '-';
}

function paymentStatusBadgeClass(status?: string) {
    if (status === 'Berjaya') {
        return 'bg-blue-100 text-blue-700';
    }

    if (status === 'Dalam Proses') {
        return 'bg-amber-100 text-amber-700';
    }

    if (status === 'Gagal') {
        return 'bg-red-100 text-red-700';
    }

    return 'bg-yellow-100 text-yellow-700';
}

function formatSubmissionStatus(status: string) {
    if (status === 'submitted_to_pbt') {
        return 'Dihantar ke PBT';
    }

    if (status === 'paid') {
        return 'Bayaran Berjaya';
    }

    if (status === 'submitted') {
        return 'Menunggu Semakan';
    }

    if (status === 'bkt_verified') {
        return 'Lulus BKT (Menunggu Bendahara)';
    }

    if (status === 'verified') {
        return 'Disahkan';
    }

    if (status === 'rejected') {
        return 'Ditolak';
    }

    if (status === 'payment_pending') {
        return 'Menunggu Pembayaran';
    }

    return status;
}

function submissionStatusBadgeClass(status: string) {
    if (status === 'submitted_to_pbt') {
        return 'bg-violet-100 text-violet-700';
    }

    if (status === 'verified') {
        return 'bg-green-100 text-green-700';
    }

    if (status === 'rejected') {
        return 'bg-red-100 text-red-700';
    }

    if (status === 'paid') {
        return 'bg-blue-100 text-blue-700';
    }

    if (status === 'bkt_verified') {
        return 'bg-indigo-100 text-indigo-700';
    }

    if (status === 'payment_pending') {
        return 'bg-amber-100 text-amber-700';
    }

    return 'bg-yellow-100 text-yellow-700';
}

function onHotelSystemGuestListChange(submissionId: number, event: Event) {
    const input = event.target as HTMLInputElement;
    selectedFiles.value[submissionId] = input.files?.[0] ?? null;
}

function selectedFileName(submissionId: number) {
    return selectedFiles.value[submissionId]?.name ?? '';
}

function canSubmitDocs(submission: PaymentSubmissionRow) {
    return submission.status === 'payment_pending' && !submission.hotel_guest_list_url;
}

function canProceedPayment(submission: PaymentSubmissionRow) {
    return submission.status === 'payment_pending' && Boolean(submission.hotel_guest_list_url) && submission.payment_status !== 'Berjaya';
}

function canSubmitToBkt(submission: PaymentSubmissionRow) {
    return submission.status === 'paid' && submission.payment_status === 'Berjaya' && Boolean(submission.hotel_guest_list_url);
}

function submitPaymentDoc(submissionId: number) {
    const selectedFile = selectedFiles.value[submissionId];

    if (!selectedFile) {
        return;
    }

    uploadingSubmissionId.value = submissionId;
    uploadForm.submission_id = String(submissionId);
    uploadForm.hotel_guest_list = selectedFile;

    uploadForm.post('/fi-sejahtera/payment', {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            uploadingSubmissionId.value = null;
        },
    });
}

function proceedPayment(submissionId: number) {
    router.get('/fi-sejahtera/payment/pay', {
        submission_id: String(submissionId),
    });
}

function submitToBkt(submissionId: number) {
    submitToBktForm.submission_id = String(submissionId);

    submitToBktForm.post('/fi-sejahtera/payment/submit-bkt', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Payment Document" />

    <div class="flex min-h-screen bg-muted/30">
        <FiSejahteraSidebar />

        <div class="flex min-h-screen flex-1 flex-col">
            <FiSejahteraNavbar />

            <main class="flex-1 space-y-6 p-6">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">Hantar Bukti Pembayaran</h1>
                    <p class="text-sm text-muted-foreground">
                        Ikuti aliran baharu: semak status laporan PBT, muat naik senarai sistem hotel, bayar, kemudian hantar ke BKT.
                    </p>
                </div>

                <div
                    v-if="flashSuccess"
                    class="rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800"
                >
                    {{ flashSuccess }}
                </div>

                <div
                    v-if="flashError"
                    class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    {{ flashError }}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Senarai Pembayaran</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="text-sm text-muted-foreground">
                                        <th class="p-2">Bil.</th>
                                        <th class="p-2">Hotel</th>
                                        <th class="p-2">Bulan/Tahun</th>
                                        <th class="p-2">Status Bayaran</th>
                                        <th class="p-2">Jumlah (RM)</th>
                                        <th class="p-2">Resit</th>
                                        <th class="p-2">Senarai Tetamu</th>
                                        <th class="p-2">Senarai Tetamu (Sistem Hotel)</th>
                                        <th class="p-2">Status</th>
                                        <th class="p-2">Tindakan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(submission, index) in submissions" :key="submission.id" class="border-t align-top">
                                        <td class="p-2">{{ index + 1 }}</td>
                                        <td class="p-2">{{ submission.hotel_name ?? '-' }}</td>
                                        <td class="p-2">{{ formatMonthYear(submission.month, submission.year) }}</td>
                                        <td class="p-2">
                                            <span
                                                v-if="formatPaymentStatus(submission.payment_status) !== '-'"
                                                class="inline-flex rounded-lg px-3 py-1 text-xs font-semibold"
                                                :class="paymentStatusBadgeClass(submission.payment_status)"
                                            >
                                                {{ formatPaymentStatus(submission.payment_status) }}
                                            </span>
                                            <span v-else>-</span>
                                        </td>
                                        <td class="p-2">{{ formatCurrency(submission.total_amount) }}</td>
                                        <td class="p-2">
                                            <a
                                                v-if="submission.receipt_url"
                                                :href="submission.receipt_url"
                                                target="_blank"
                                                class="text-blue-600 hover:underline"
                                            >
                                                Lihat
                                            </a>
                                            <span v-else>-</span>
                                        </td>
                                        <td class="p-2">
                                            <a
                                                v-if="submission.guest_list_url"
                                                :href="submission.guest_list_url"
                                                target="_blank"
                                                class="text-blue-600 hover:underline"
                                            >
                                                Lihat
                                            </a>
                                            <span v-else>-</span>
                                        </td>
                                        <td class="p-2">
                                            <div class="space-y-2">
                                                <a
                                                    v-if="submission.hotel_guest_list_url"
                                                    :href="submission.hotel_guest_list_url"
                                                    target="_blank"
                                                    class="text-blue-600 hover:underline"
                                                >
                                                    Lihat
                                                </a>
                                                <span v-else>-</span>

                                                <input
                                                    v-if="canSubmitDocs(submission)"
                                                    type="file"
                                                    accept=".pdf,.jpg,.jpeg,.png"
                                                    class="block w-full max-w-xs cursor-pointer rounded-lg border border-slate-300 bg-slate-50 p-2 text-xs text-slate-700 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-slate-800 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:border-slate-400 hover:bg-slate-100 file:hover:bg-slate-900"
                                                    @change="onHotelSystemGuestListChange(submission.id, $event)"
                                                />

                                                <p
                                                    v-if="selectedFileName(submission.id)"
                                                    class="max-w-xs truncate text-xs text-slate-600"
                                                >
                                                    Dipilih: {{ selectedFileName(submission.id) }}
                                                </p>
                                            </div>
                                        </td>
                                        <td class="p-2">
                                            <span
                                                class="inline-flex rounded-lg px-3 py-1 text-xs font-semibold"
                                                :class="submissionStatusBadgeClass(submission.status)"
                                            >
                                                {{ formatSubmissionStatus(submission.status) }}
                                            </span>
                                        </td>
                                        <td class="p-2">
                                            <div v-if="canSubmitDocs(submission)" class="space-y-2">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    :disabled="uploadForm.processing || !selectedFiles[submission.id]"
                                                    @click="submitPaymentDoc(submission.id)"
                                                >
                                                    {{ uploadForm.processing && uploadingSubmissionId === submission.id ? 'Memuat naik...' : 'Muat Naik Senarai Sistem Hotel' }}
                                                </Button>
                                                <p
                                                    v-if="uploadForm.errors.hotel_guest_list && uploadingSubmissionId === submission.id"
                                                    class="text-xs text-red-600"
                                                >
                                                    {{ uploadForm.errors.hotel_guest_list }}
                                                </p>
                                            </div>

                                            <Button
                                                v-else-if="canProceedPayment(submission)"
                                                type="button"
                                                size="sm"
                                                @click="proceedPayment(submission.id)"
                                            >
                                                Bayar
                                            </Button>

                                            <Button
                                                v-else-if="canSubmitToBkt(submission)"
                                                type="button"
                                                size="sm"
                                                :disabled="submitToBktForm.processing"
                                                @click="submitToBkt(submission.id)"
                                            >
                                                {{ submitToBktForm.processing ? 'Menghantar...' : 'Hantar' }}
                                            </Button>

                                            <span v-else-if="submission.status === 'submitted_to_pbt'">Menunggu kelulusan PBT</span>
                                            <span v-else-if="submission.status === 'submitted' || submission.status === 'bkt_verified' || submission.status === 'verified'">-</span>
                                            <span v-else>-</span>
                                        </td>
                                    </tr>
                                    <tr v-if="submissions.length === 0">
                                        <td colspan="10" class="p-3 text-sm text-muted-foreground">
                                            Tiada rekod pembayaran ditemui.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </main>
        </div>
    </div>
</template>
