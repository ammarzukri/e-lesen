<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type LicenseType = {
    aktiviti?: string;
    keluasan?: string;
    unit_bilik?: string;
};

type AdvertisementInfo = {
    type?: string;
    structure?: string;
    length?: string;
    width?: string;
    number_of_ads?: string;
};

type LicenseDocument = {
    id: number;
    document_type?: string;
    file_path?: string;
    upload_at?: string;
};

type LicenseInfo = {
    license_number?: string;
    start_date?: string;
    expiry_date?: string;
    status?: string;
};

type HotelInfo = {
    license?: LicenseInfo;
};

type LicenseApplication = {
    id: number;
    pbt_name?: string;
    hotel_name?: string;
    name?: string;
    ic_no?: string;
    birth_date?: string;
    birth_place?: string;
    gender?: string;
    citizenship?: string;
    religion?: string;
    ethnicity?: string;
    maritial_status?: string;
    occupation?: string;
    income?: string;
    home_address?: string;
    postcode?: string;
    state?: string;
    district?: string;
    phone_number?: string;
    email?: string;
    company_name?: string;
    company_address?: string;
    company_postcode?: string;
    company_state?: string;
    company_district?: string;
    company_phone?: string;
    company_registration_number?: string;
    company_registration_date?: string;
    company_registration_expiry_date?: string;
    company_category?: string;
    company_premises_location?: string;
    employee_malay?: string;
    employee_chinese?: string;
    employee_indian?: string;
    employee_others?: string;
    company_operation_start?: string;
    company_operation_end?: string;
    company_address_hq?: string;
    company_postcode_hq?: string;
    company_state_hq?: string;
    company_district_hq?: string;
    company_phone_hq?: string;
    status?: string;
    created_at?: string;
    license_types?: LicenseType[];
    advertisement_infos?: AdvertisementInfo[];
    documents?: LicenseDocument[];
    payment_status?: string;
    payment_amount?: string;
    payment_paid_at?: string;
    hotel?: HotelInfo;
};

const documentTypeMap: Record<string, string> = {
    memorandum: 'Memorandum / Borang A / Borang B / Borang D',
    pelan_lokasi: 'Pelan Lokasi Premis',
    pelan_lantai: 'Pelan Lantai Premis / Kawasan',
    surat_perjanjian: 'Surat Perjanjian / Kebenaran Bangunan / Tanah',
    salinan_geran_tanah: 'Salinan Geran Tanah / Lesen Pendudukan Sementara (LPS) / Dokumen Berkaitan',
    sijil_menduduki_bangunan: 'Salinan Sijil Kelayakan Menduduki Bangunan / Sementara (CF/CCC) (TCF)',
    gambar_pemohon: 'Gambar Pemohon',
    salinan_kad_pengenalan_pemohon: 'Salinan Kad Pengenalan Pemohon',
    senarai_nama_semua_pengendali_makanan: 'Senarai Nama Pengendali Makanan / Pembantu',
    carta_proses_pengeluaran: 'Carta Proses Pengeluaran Pengilangan / Pemerosesan',
}

const props = defineProps<{
    application: LicenseApplication;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Senarai Permohonan', href: '/admin/license-applications' },
    {
        title: 'Butiran Permohonan',
        href: `/admin/license-applications/${props.application.id}`,
    },
];

const statusLabel = computed(() => props.application.status || 'Dalam Proses');
const canTakeAction = computed(() => statusLabel.value === 'Dalam Proses');
const licenseInfo = computed(() => props.application.hotel?.license);
const licenseStatusLabel = computed(() => licenseInfo.value?.status || '-');

const dateFormatter = new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

const previewUrl = ref<string | null>(null);
const previewTitle = ref<string>('');
const previewType = ref<'image' | 'pdf' | 'unknown'>('unknown');

function formatDate(value?: string) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return dateFormatter.format(date);
}

function formatGender(value?: string) {
    if (!value) return '-';
    if (value === 'lelaki') return 'Lelaki';
    if (value === 'perempuan') return 'Perempuan';
    return value;
}

function formatIncome(value?: string | number) {
    if (value === undefined || value === null || value === '') return '-';
    const numeric = Number(String(value).replace(/,/g, ''));
    if (Number.isNaN(numeric)) return String(value);
    return new Intl.NumberFormat('ms-MY').format(numeric);
}

function formatCompanyCategory(value?: string) {
    if (!value) return '-';
    return `Kategori ${String(value).toUpperCase()}`;
}

function formatPremisesLocation(value?: string) {
    if (!value) return '-';
    if (value === 'land') return 'Darat';
    if (value === 'island') return 'Pulau';
    return value;
}

function formatCurrency(value?: string | number) {
    if (value === undefined || value === null || value === '') return '-';
    const cents = Number(value);
    if (Number.isNaN(cents)) return '-';
    return (cents / 100).toFixed(2);
}

function licenseStatusBadgeClass(status?: string) {
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

function fileUrlById(id?: number) {
    if (!id) return null;
    return `/admin/license-documents/${id}`;
}

function getFileExtension(path?: string) {
    if (!path) return '';
    const cleanPath = path.split('?')[0].split('#')[0];
    const ext = cleanPath.split('.').pop();
    return (ext || '').toLowerCase();
}

function getPreviewType(path?: string): 'image' | 'pdf' | 'unknown' {
    const ext = getFileExtension(path);
    if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg'].includes(ext)) {
        return 'image';
    }
    if (ext === 'pdf') {
        return 'pdf';
    }
    return 'unknown';
}

function openPreview(doc: LicenseDocument, title?: string) {
    const url = fileUrlById(doc.id);
    if (!url) return;
    previewUrl.value = url;
    previewTitle.value = title || 'Dokumen';
    previewType.value = getPreviewType(doc.file_path);
}

function closePreview() {
    previewUrl.value = null;
    previewTitle.value = '';
    previewType.value = 'unknown';
}

function approve() {
    router.post(`/admin/license-applications/${props.application.id}/approve`);
}

function reject() {
    router.post(`/admin/license-applications/${props.application.id}/reject`);
}
</script>

<template>
    <Head title="Butiran Permohonan" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="w-full h-full flex flex-col p-6 bg-white dark:bg-black rounded-xl shadow dark:shadow-black/30"
        >
            <div class="flex-1 overflow-auto space-y-6">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h2
                            class="text-xl font-bold text-slate-900 dark:text-slate-100"
                        >
                            Butiran Permohonan
                        </h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            Maklumat penuh permohonan lesen penginapan.
                        </p>
                    </div>
                    <div
                        :class="[
                            'px-3 py-1 rounded-full text-sm font-semibold',
                            licenseStatusBadgeClass(licenseInfo?.status),
                        ]"
                    >
                        {{ licenseStatusLabel }}
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <Link
                        class="px-3 py-1 rounded-lg bg-slate-700 text-white text-sm font-semibold hover:bg-slate-800"
                        href="/admin/license-applications"
                    >
                       ← Kembali
                    </Link>
                </div>

                <div
                    class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 p-6 space-y-8"
                >
                    <section>
                        <h3
                            class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4"
                        >
                            Maklumat Lesen
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    No Lesen
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ licenseInfo?.license_number || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Tarikh Mula
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ formatDate(licenseInfo?.start_date) }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Tarikh Tamat
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ formatDate(licenseInfo?.expiry_date) }}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section>
                        <h3
                            class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4"
                        >
                            Pihak Berkuasa Tempatan (PBT)
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    PBT
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.pbt_name || '-' }}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section>
                        <h3
                            class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4"
                        >
                            Maklumat Pemohon
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Nama Pemohon
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.name || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    No Kad Pengenalan
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.ic_no || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Tarikh Lahir
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ formatDate(application.birth_date) || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Tempat Lahir
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.birth_place || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Jantina
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ formatGender(application.gender) }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Kewarganegaraan
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.citizenship || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Agama
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.religion || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Bangsa
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.ethnicity || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Status Perkahwinan
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.maritial_status || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Pekerjaan
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.occupation || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Pendapatan (RM)
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ formatIncome(application.income) }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Telefon
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.phone_number || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Email
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.email || '-' }}
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Alamat Rumah
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.home_address || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Poskod
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.postcode || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Negeri
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.state || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Daerah
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.district || '-' }}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section>
                        <h3
                            class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4"
                        >
                            Maklumat Perniagaan / Syarikat
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Nama Syarikat
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.company_name || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Nama Hotel
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.hotel_name || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    No Pendaftaran
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.company_registration_number || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Tarikh Pendaftaran
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ formatDate(application.company_registration_date) || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Tarikh Tamat
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ formatDate(application.company_registration_expiry_date) || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Kategori Syarikat
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ formatCompanyCategory(application.company_category) }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Lokasi Premis
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ formatPremisesLocation(application.company_premises_location) }}
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Alamat Syarikat
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.company_address || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Poskod
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.company_postcode || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Negeri
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.company_state || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Daerah
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.company_district || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Telefon Syarikat
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.company_phone || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Operasi
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.company_operation_start || '-' }} -
                                    {{ application.company_operation_end || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Pekerja (Melayu)
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.employee_malay || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Pekerja (Cina)
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.employee_chinese || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Pekerja (India)
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.employee_indian || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Pekerja (Lain-lain)
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.employee_others || '-' }}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section>
                        <h3
                            class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4"
                        >
                            Maklumat Iklan / Papan Tanda
                        </h3>
                        <div class="space-y-3">
                            <div
                                v-for="(item, idx) in application.advertisement_infos || []"
                                :key="`ad-${idx}`"
                                class="rounded-xl border border-slate-200 dark:border-slate-700 p-3"
                            >
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Iklan #{{ idx + 1 }}
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
                                    <div>
                                        <div
                                            class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                        >
                                            Jenis
                                        </div>
                                        <div
                                            class="text-md text-slate-900 dark:text-slate-100"
                                        >
                                            {{ item.type || '-' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                        >
                                            Struktur
                                        </div>
                                        <div
                                            class="text-md text-slate-900 dark:text-slate-100"
                                        >
                                            {{ item.structure || '-' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                        >
                                            Panjang
                                        </div>
                                        <div
                                            class="text-md text-slate-900 dark:text-slate-100"
                                        >
                                            {{ item.length || '-' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                        >
                                            Lebar
                                        </div>
                                        <div
                                            class="text-md text-slate-900 dark:text-slate-100"
                                        >
                                            {{ item.width || '-' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                        >
                                            Bilangan
                                        </div>
                                        <div
                                            class="text-md text-slate-900 dark:text-slate-100"
                                        >
                                            {{ item.number_of_ads || '-' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-if="!(application.advertisement_infos || []).length"
                                class="text-sm text-slate-600 dark:text-slate-400"
                            >
                                Tiada maklumat iklan direkodkan.
                            </div>
                        </div>
                    </section>

                    <section>
                        <h3
                            class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4"
                        >
                            Jenis Lesen Dipohon
                        </h3>
                        <div class="space-y-3">
                            <div
                                v-for="(item, idx) in application.license_types || []"
                                :key="`license-${idx}`"
                                class="rounded-xl border border-slate-200 dark:border-slate-700 p-3"
                            >
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Lesen #{{ idx + 1 }}
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-2">
                                    <div>
                                        <div
                                            class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                        >
                                            Aktiviti
                                        </div>
                                        <div
                                            class="text-md text-slate-900 dark:text-slate-100"
                                        >
                                            {{ item.aktiviti || '-' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                        >
                                            Keluasan
                                        </div>
                                        <div
                                            class="text-md text-slate-900 dark:text-slate-100"
                                        >
                                            {{ item.keluasan || '-' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                        >
                                            Unit / Bilik
                                        </div>
                                        <div
                                            class="text-md text-slate-900 dark:text-slate-100"
                                        >
                                            {{ item.unit_bilik || '-' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-if="!(application.license_types || []).length"
                                class="text-sm text-slate-600 dark:text-slate-400"
                            >
                                Tiada jenis lesen direkodkan.
                            </div>
                        </div>
                    </section>

                    <section>
                        <h3
                            class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4"
                        >
                            Dokumen Sokongan
                        </h3>
                        <div class="space-y-3">
                            <div
                                v-for="(doc, idx) in application.documents || []"
                                :key="`doc-${idx}`"
                                class="rounded-xl border border-slate-200 dark:border-slate-700 p-3"
                            >
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Dokumen #{{ idx + 1 }}
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
                                    <div>
                                        <div
                                            class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                        >
                                            Jenis Dokumen
                                        </div>
                                        <div
                                            class="text-sm text-slate-900 dark:text-slate-100"
                                        >
                                            {{ documentTypeMap[doc.document_type ?? ''] || '-' }}

                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                        >
                                            Fail
                                        </div>
                                        <div
                                            class="text-sm text-slate-900 dark:text-slate-100"
                                        >
                                            <button
                                                v-if="doc.file_path"
                                                type="button"
                                                class="text-blue-600 hover:text-blue-700 underline text-left"
                                                @click="openPreview(doc, documentTypeMap[doc.document_type ?? ''] || 'Dokumen')"
                                            >
                                                {{ doc.file_path }}
                                            </button>
                                            <span v-else>-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-if="!(application.documents || []).length"
                                class="text-sm text-slate-600 dark:text-slate-400"
                            >
                                Tiada dokumen direkodkan.
                            </div>
                        </div>
                    </section>

                    <section>
                        <h3
                            class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4"
                        >
                            Maklumat Pembayaran
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Status Pembayaran
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.payment_status || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Jumlah Dibayar (RM)
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ formatCurrency(application.payment_amount) }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Tarikh Pembayaran
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ formatDate(application.payment_paid_at) || '-' }}
                                </div>
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

    <div
        v-if="previewUrl"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
        @click.self="closePreview"
    >
        <div class="w-full max-w-4xl rounded-xl bg-white dark:bg-slate-900 p-4">
            <div class="flex items-center justify-between gap-4 mb-3">
                <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                    {{ previewTitle }}
                </div>
                <button
                    type="button"
                    class="px-3 py-1 rounded-lg bg-slate-700 text-white text-xs font-semibold hover:bg-slate-800"
                    @click="closePreview"
                >
                    Tutup
                </button>
            </div>
            <div class="max-h-[70vh] overflow-auto">
                <img
                    v-if="previewType === 'image'"
                    :src="previewUrl"
                    alt="Dokumen"
                    class="mx-auto max-h-[70vh] w-auto rounded-lg"
                />
                <iframe
                    v-else-if="previewType === 'pdf'"
                    :src="previewUrl"
                    class="h-[70vh] w-full rounded-lg border border-slate-200 dark:border-slate-700"
                />
                <div
                    v-else
                    class="rounded-lg border border-dashed border-slate-300 dark:border-slate-700 p-6 text-center text-sm text-slate-600 dark:text-slate-400"
                >
                    Pratonton tidak tersedia untuk fail ini.
                </div>
                <div class="mt-3 text-center">
                    <a
                        :href="previewUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-blue-600 hover:text-blue-700 underline text-sm"
                    >
                        Buka fail dalam tab baru
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
