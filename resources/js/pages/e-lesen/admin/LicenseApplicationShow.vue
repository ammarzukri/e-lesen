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

type AdditionalInfo = {
    activity_name?: string;
    type_name?: string;
    keluasan_mps?: string;
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
    license_type_selected?: string;
    room_count?: string;
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
    remarks?: string;
    created_at?: string;
    license_types?: LicenseType[];
    additional_infos?: AdditionalInfo[];
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

const licenseTypeLabelMap: Record<string, string> = {
    homestay_island: '"Homestay", "Kampungstay", dan "Townstay" di pulau, tasik, atau seumpamanya',
    homestay_land: '"Homestay", "Kampungstay", dan "Townstay" selain di pulau, tasik, atau seumpamanya',
    campsite_island: 'Tapak perkhemahan dan tapak perkhemahan mewah di pulau, tasik, atau seumpamanya',
    campsite_land: 'Tapak perkhemahan dan tapak perkhemahan mewah selain di pulau, tasik, atau seumpamanya',
    rv_site: 'Tapak kenderaan rekreasi',
    houseboat_raft_kelong: 'Rumah bot, rumah rakit, dan kelong',
    others_island: 'Mana-mana rumah tumpangan lain di pulau, tasik, atau seumpamanya',
    others_land: 'Mana-mana rumah tumpangan lain selain di pulau, tasik, atau seumpamanya',
};

const activityTypeLabelMap: Record<string, string> = {
    papan_iklan: 'Papan Iklan',
    permit_sementara_papan_tanda: 'Permit Sementara Papan Tanda',
    billiard_snooker: 'Billiard/Snooker',
    karaoke: 'Karaoke',
    gym: 'Gym',
    kedai_serbaneka: 'Kedai Serbaneka',
    pusat_penjagaan: 'Pusat Penjagaan Kesihatan, Kecantikan, dan Seumpamanya',
};

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

const openPdf = () => {
    window.open(`/admin/license-applications/${props.application.id}/pdf`, '_blank');
};

const previewUrl = ref<string | null>(null);
const previewTitle = ref<string>('');
const previewType = ref<'image' | 'pdf' | 'unknown'>('unknown');
const rejectModalOpen = ref<boolean>(false);
const rejectRemarks = ref<string>('');

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

function formatLicenseType(value?: string) {
    if (!value) return '-';
    return licenseTypeLabelMap[value] || value;
}

function formatActivityType(value?: string) {
    if (!value) return '-';
    return activityTypeLabelMap[value] || value;
}

function activityJenisLabel(value?: string) {
    if (value === 'billiard_snooker') {
        return 'Bilangan Meja';
    }

    if (value === 'papan_iklan' || value === 'karaoke') {
        return 'Jenis';
    }

    return '';
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

function downloadUrlById(id?: number) {
    const url = fileUrlById(id);
    if (!url) return null;
    return `${url}?download=1`;
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
    rejectRemarks.value = '';
    rejectModalOpen.value = true;
}

function closeRejectModal() {
    rejectModalOpen.value = false;
    rejectRemarks.value = '';
}

function submitReject() {
    const normalizedRemarks = rejectRemarks.value.trim();

    if (!normalizedRemarks) {
        return;
    }

    router.post(`/admin/license-applications/${props.application.id}/reject`, {
        remarks: normalizedRemarks,
    }, {
        onSuccess: () => {
            closeRejectModal();
        },
    });
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

                <button
                    @click="openPdf"
                    class="px-3 py-1 rounded-lg bg-blue-700 text-white text-sm font-semibold hover:bg-blue-800"
                >
                    Cetak Permohonan
                </button>

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
                                    Nama Perniagaan / Syarikat
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
                                    Nama Rumah Tumpangan
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
                                    Waktu Beroperasi
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
                            Maklumat Perniagaan / Syarikat (Ibu Pejabat)
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    Alamat Perniagaan (Ibu Pejabat)
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.company_address_hq || '-' }}
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
                                    {{ application.company_postcode_hq || '-' }}
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
                                    {{ application.company_district_hq || '-' }}
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
                                    {{ application.company_state_hq || '-' }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    No Telefon Syarikat (Ibu Pejabat)
                                </div>
                                <div
                                    class="text-md text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.company_phone_hq || '-' }}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section>
                        <h3
                            class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4"
                        >
                            Jenis Lesen Yang Dipohon
                        </h3>
                        <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <div
                                        class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                    >
                                        Kategori Lesen
                                    </div>
                                    <div
                                        class="text-md text-slate-900 dark:text-slate-100"
                                    >
                                        {{ formatLicenseType(application.license_type_selected || application.license_types?.[0]?.aktiviti) }}
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                    >
                                        Bilangan Bilik
                                    </div>
                                    <div
                                        class="text-md text-slate-900 dark:text-slate-100"
                                    >
                                        {{ application.room_count || application.license_types?.[0]?.unit_bilik || '-' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section>
                        <h3
                            class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4"
                        >
                            Maklumat Tambahan (Aktiviti)
                        </h3>
                        <div class="space-y-3">
                            <div
                                v-for="(item, idx) in application.additional_infos || []"
                                :key="`ad-${idx}`"
                                class="rounded-xl border border-slate-200 dark:border-slate-700 p-3"
                            >
                                <div class="text-sm font-semibold text-slate-600 dark:text-slate-400">
                                    #{{ idx + 1 }} {{ formatActivityType(item.activity_name) }} 
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
                                    <div>
                                        <div
                                            class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                        >
                                            Aktiviti Tambahan
                                        </div>
                                        <div
                                            class="text-md text-slate-900 dark:text-slate-100"
                                        >
                                            {{ formatActivityType(item.activity_name) }}
                                        </div>
                                    </div>
                                    <div v-if="activityJenisLabel(item.type_name)">
                                        <div
                                            class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                        >
                                            {{ activityJenisLabel(item.type_name) }}
                                        </div>
                                        <div
                                            class="text-md text-slate-900 dark:text-slate-100"
                                        >
                                            {{ item.type_name || '-' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div
                                            class="text-sm font-semibold text-slate-600 dark:text-slate-400"
                                        >
                                            Keluasan (MPS)
                                        </div>
                                        <div
                                            class="text-md text-slate-900 dark:text-slate-100"
                                        >
                                            {{ item.keluasan_mps || '-' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-if="!(application.additional_infos || []).length"
                                class="text-sm text-slate-600 dark:text-slate-400"
                            >
                                Tiada maklumat aktiviti tambahan direkodkan.
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
                                    <div class="md:flex md:flex-col md:items-end">
                                        <div
                                            class="text-sm font-semibold text-slate-600 dark:text-slate-400 md:w-28 md:text-center"
                                        >
                                            Tindakan
                                        </div>
                                        <div
                                            class="text-sm text-slate-900 dark:text-slate-100"
                                        >
                                            <div
                                                v-if="doc.file_path"
                                                class="flex flex-wrap items-center gap-3 md:w-28 md:justify-between"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-blue-200 bg-blue-50 text-blue-700 transition-colors hover:bg-blue-100 dark:border-blue-800/60 dark:bg-blue-900/20 dark:text-blue-300 dark:hover:bg-blue-900/40"
                                                    :aria-label="`Lihat`"
                                                    :title="`Lihat`"
                                                    @click="openPreview(doc, documentTypeMap[doc.document_type ?? ''] || 'Dokumen')"
                                                >
                                                    <svg
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        class="h-4 w-4"
                                                        aria-hidden="true"
                                                    >
                                                        <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" />
                                                        <circle cx="12" cy="12" r="3" />
                                                    </svg>
                                                </button>
                                                <a
                                                    v-if="downloadUrlById(doc.id)"
                                                    :href="downloadUrlById(doc.id) || undefined"
                                                    class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 text-emerald-700 transition-colors hover:bg-emerald-100 dark:border-emerald-800/60 dark:bg-emerald-900/20 dark:text-emerald-300 dark:hover:bg-emerald-900/40"
                                                    :aria-label="`Muat Turun`"
                                                    :title="`Muat Turun`"
                                                >
                                                    <svg
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        class="h-4 w-4"
                                                        aria-hidden="true"
                                                    >
                                                        <path d="M12 3v12" />
                                                        <path d="m7 10 5 5 5-5" />
                                                        <path d="M5 21h14" />
                                                    </svg>
                                                </a>
                                            </div>
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

                    <section>
                        <h3
                            class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4"
                        >
                            Ulasan Penolakan
                        </h3>
                        <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                            <div class="text-sm text-slate-900 dark:text-slate-100 whitespace-pre-wrap">
                                {{ application.remarks || '-' }}
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
        v-if="rejectModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
        @click.self="closeRejectModal"
    >
        <div class="w-full max-w-lg rounded-xl bg-white dark:bg-slate-900 p-4">
            <div class="mb-3">
                <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                    Sebab Penolakan Permohonan
                </h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    Sila berikan ulasan sebelum menolak permohonan ini.
                </p>
            </div>

            <textarea
                v-model="rejectRemarks"
                rows="4"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:ring-red-900/40"
                placeholder="Contoh: Dokumen tidak lengkap / maklumat tidak sah..."
            />
            <p
                v-if="!rejectRemarks.trim()"
                class="mt-2 text-xs text-red-600 dark:text-red-400"
            >
                Sila isi sebab penolakan.
            </p>

            <div class="mt-4 flex items-center justify-end gap-2">
                <button
                    type="button"
                    class="px-3 py-2 rounded-lg bg-slate-200 text-slate-800 text-sm font-semibold hover:bg-slate-300 dark:bg-slate-700 dark:text-slate-100 dark:hover:bg-slate-600"
                    @click="closeRejectModal"
                >
                    Batal
                </button>
                <button
                    type="button"
                    class="px-3 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="!rejectRemarks.trim()"
                    @click="submitReject"
                >
                    Tolak Permohonan
                </button>
            </div>
        </div>
    </div>

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
