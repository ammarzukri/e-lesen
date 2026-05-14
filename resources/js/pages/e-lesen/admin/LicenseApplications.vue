<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppPageProps, BreadcrumbItem } from '@/types';

type LicenseApplication = {
    id: number;
    company_name?: string;
    hotel_name?: string;
    pbt_name?: string;
    status?: string;
    payment_status?: string;
    license_status?: string;
    fi_sejahtera_status?: string;
    expiry_date?: string;
};

const props = defineProps<{
    applications: LicenseApplication[];
    permissions?: {
        canApprove?: boolean;
        canReject?: boolean;
        canBlock?: boolean;
    };
}>();

const page = usePage<AppPageProps>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Senarai Permohonan', href: '/admin/license-applications' },
];

const allApplications = computed(() => props.applications ?? []);
const canApprove = computed<boolean>(() => Boolean(props.permissions?.canApprove));
const canReject = computed<boolean>(() => Boolean(props.permissions?.canReject));
const canBlock = computed<boolean>(() => Boolean(props.permissions?.canBlock));
const canViewDetails = computed<boolean>(() => ['admin', 'bkt_admin', 'pbt_admin'].includes(page.props.auth?.user?.role ?? ''));
const isPbtAdmin = computed<boolean>(() => page.props.auth?.user?.role === 'pbt_admin');
const isBktAdmin = computed<boolean>(() => ['admin', 'bkt_admin'].includes(page.props.auth?.user?.role ?? ''));

const selectedHotelName = ref<string>('');
const appliedHotelName = ref<string>('');
const hotelSearch = ref<string>('');
const hotelDropdownOpen = ref<boolean>(false);
const hotelDropdownRef = ref<HTMLElement | null>(null);
const selectedPbtName = ref<string>('');
const appliedPbtName = ref<string>('');
const pbtSearch = ref<string>('');
const pbtDropdownOpen = ref<boolean>(false);
const pbtDropdownRef = ref<HTMLElement | null>(null);
const selectedStatus = ref<string>('');
const selectedPaymentStatus = ref<string>('');
const selectedLicenseStatus = ref<string>('');
const selectedFiSejahteraStatus = ref<string>('');

function normalizePbtName(value?: string) {
    const trimmed = value?.trim() ?? '';
    return trimmed || 'Tidak Dinyatakan';
}

const hotelOptions = computed<string[]>(() => {
    const unique = new Set(
        allApplications.value
            .map((application) => application.hotel_name?.trim())
            .filter((name): name is string => Boolean(name)),
    );

    return Array.from(unique).sort((a, b) => a.localeCompare(b));
});

const filteredHotelOptions = computed<string[]>(() => {
    const keyword = hotelSearch.value.trim().toLowerCase();

    if (!keyword) {
        return hotelOptions.value;
    }

    return hotelOptions.value.filter((hotelName) => hotelName.toLowerCase().includes(keyword));
});

const pbtOptions = computed<string[]>(() => {
    const unique = new Set(
        allApplications.value
            .map((application) => normalizePbtName(application.pbt_name)),
    );

    return Array.from(unique).sort((a, b) => a.localeCompare(b));
});

const filteredPbtOptions = computed<string[]>(() => {
    const keyword = pbtSearch.value.trim().toLowerCase();

    if (!keyword) {
        return pbtOptions.value;
    }

    return pbtOptions.value.filter((pbtName) => pbtName.toLowerCase().includes(keyword));
});

const selectedHotelLabel = computed(() => selectedHotelName.value || 'Semua Hotel');
const selectedPbtLabel = computed(() => selectedPbtName.value || 'Semua PBT');

const statusOptions = computed(() => {
    const unique = new Set(
        allApplications.value.map((application) => application.status?.trim() ?? ''),
    );

    return Array.from(unique)
        .sort((a, b) => a.localeCompare(b))
        .map((value) => ({
            value,
            label: value || 'Dalam Proses',
        }));
});

const paymentStatusOptions = computed(() => {
    const unique = new Set(
        allApplications.value.map((application) => application.payment_status?.trim() ?? ''),
    );

    return Array.from(unique)
        .sort((a, b) => a.localeCompare(b))
        .map((value) => ({
            value,
            label: value || 'Belum Dibayar',
        }));
});

const fiSejahteraStatusOptions = computed(() => {
    const unique = new Set(
        allApplications.value.map((application) => application.fi_sejahtera_status?.trim() ?? ''),
    );

    return Array.from(unique)
        .sort((a, b) => a.localeCompare(b))
        .map((value) => ({
            value,
            label: value || 'Belum Dibayar',
        }));
});

const licenseStatusOptions = computed(() => {
    const unique = new Set(
        allApplications.value.map((application) => application.license_status?.trim() ?? ''),
    );

    return Array.from(unique)
        .sort((a, b) => a.localeCompare(b))
        .map((value) => ({
            value,
            label: value || 'Belum Aktif',
        }));
});

const filteredApplications = computed(() => {
    return allApplications.value.filter((application) => {
        const matchHotel = !appliedHotelName.value || application.hotel_name === appliedHotelName.value;
        const matchPbt = !appliedPbtName.value || normalizePbtName(application.pbt_name) === appliedPbtName.value;
        const matchStatus = !selectedStatus.value || (application.status?.trim() ?? '') === selectedStatus.value;
        const matchPaymentStatus = !selectedPaymentStatus.value || (application.payment_status?.trim() ?? '') === selectedPaymentStatus.value;
        const matchLicenseStatus = !selectedLicenseStatus.value || (application.license_status?.trim() ?? '') === selectedLicenseStatus.value;
        const matchFiSejahteraStatus = !selectedFiSejahteraStatus.value || (application.fi_sejahtera_status?.trim() ?? '') === selectedFiSejahteraStatus.value;

        return matchHotel && matchPbt && matchStatus && matchPaymentStatus && matchLicenseStatus && matchFiSejahteraStatus;
    });
});

const dateFormatter = new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

function formatDate(value?: string) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return dateFormatter.format(date);
}

function approve(applicationId: number) {
    router.post(`/admin/license-applications/${applicationId}/approve`);
}

function block(applicationId: number) {
    router.post(`/admin/license-applications/${applicationId}/block`);
}

function reject(applicationId: number) {
    router.post(`/admin/license-applications/${applicationId}/reject`);
}

function toggleHotelDropdown() {
    hotelDropdownOpen.value = !hotelDropdownOpen.value;
    if (hotelDropdownOpen.value) {
        pbtDropdownOpen.value = false;
    }
}

function togglePbtDropdown() {
    pbtDropdownOpen.value = !pbtDropdownOpen.value;
    if (pbtDropdownOpen.value) {
        hotelDropdownOpen.value = false;
    }
}

function chooseHotel(hotelName?: string) {
    selectedHotelName.value = hotelName ?? '';
    hotelDropdownOpen.value = false;
}

function choosePbt(pbtName?: string) {
    selectedPbtName.value = pbtName ?? '';
    pbtDropdownOpen.value = false;
}

function applyHotelFilter() {
    appliedHotelName.value = selectedHotelName.value;
}

function applyPbtFilter() {
    appliedPbtName.value = selectedPbtName.value;
}

function resetAllFilters() {
    selectedHotelName.value = '';
    appliedHotelName.value = '';
    hotelSearch.value = '';
    hotelDropdownOpen.value = false;

    selectedPbtName.value = '';
    appliedPbtName.value = '';
    pbtSearch.value = '';
    pbtDropdownOpen.value = false;

    selectedStatus.value = '';
    selectedPaymentStatus.value = '';
    selectedLicenseStatus.value = '';
    selectedFiSejahteraStatus.value = '';
}

function handleOutsideClick(event: MouseEvent) {
    if (!hotelDropdownRef.value) {
        return;
    }

    const target = event.target as Node;

    if (!hotelDropdownRef.value.contains(target)) {
        hotelDropdownOpen.value = false;
    }

    if (pbtDropdownRef.value && !pbtDropdownRef.value.contains(target)) {
        pbtDropdownOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleOutsideClick);

    const params = new URLSearchParams(window.location.search);
    const pbtName = params.get('pbt_name')?.trim();
    const status = params.get('status')?.trim();

    if (pbtName) {
        selectedPbtName.value = pbtName;
        appliedPbtName.value = pbtName;
    }

    if (status) {
        selectedStatus.value = status;
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleOutsideClick);
});
</script>

<template>
    <Head title="Senarai Permohonan" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="w-full h-full flex flex-col p-6 bg-white dark:bg-black rounded-xl shadow dark:shadow-black/30"
        >
            <div class="flex-1 overflow-auto flex flex-col gap-6">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h2
                            class="text-xl font-bold text-slate-900 dark:text-slate-100"
                        >
                            Senarai Permohonan
                        </h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            Senarai semua permohonan lesen penginapan.
                        </p>
                    </div>
                </div>

                <div
                    class="flex-1 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 p-4 overflow-x-auto"
                >
                    <div class="mb-3 flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-end">
                        <div class="grid gap-2 md:grid-cols-2">
                            <div class="w-full md:min-w-64">
                                <label class="mb-1 block text-sm font-medium text-slate-900 dark:text-slate-100">Pilih Hotel</label>
                                <div class="flex flex-col gap-1 sm:flex-row">
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
                                                    v-for="hotelName in filteredHotelOptions"
                                                    :key="hotelName"
                                                    type="button"
                                                    class="w-full rounded px-2 py-2 text-left text-sm hover:bg-muted"
                                                    @click="chooseHotel(hotelName)"
                                                >
                                                    {{ hotelName }}
                                                </button>
                                                <p v-if="filteredHotelOptions.length === 0" class="px-2 py-2 text-sm text-muted-foreground">
                                                    Tiada hotel dijumpai.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-gray-800 px-3 py-1.5 text-xs font-semibold text-white shadow hover:bg-gray-900"
                                        @click="applyHotelFilter"
                                    >
                                        Tapis Hotel
                                    </button>
                                </div>
                            </div>

                            <div class="w-full md:min-w-64">
                                <label class="mb-1 block text-sm font-medium text-slate-900 dark:text-slate-100">Pilih PBT</label>
                                <div class="flex flex-col gap-1 sm:flex-row">
                                    <div ref="pbtDropdownRef" class="relative w-full">
                                        <button
                                            type="button"
                                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-left text-sm"
                                            @click="togglePbtDropdown"
                                        >
                                            {{ selectedPbtLabel }}
                                        </button>

                                        <div
                                            v-if="pbtDropdownOpen"
                                            class="absolute z-20 mt-1 w-full rounded-md border border-input bg-background p-2 shadow-lg"
                                        >
                                            <input
                                                v-model="pbtSearch"
                                                type="text"
                                                placeholder="Cari nama PBT..."
                                                class="mb-2 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            />
                                            <div class="max-h-56 overflow-auto">
                                                <button
                                                    type="button"
                                                    class="w-full rounded px-2 py-2 text-left text-sm hover:bg-muted"
                                                    @click="choosePbt()"
                                                >
                                                    Semua PBT
                                                </button>
                                                <button
                                                    v-for="pbtName in filteredPbtOptions"
                                                    :key="pbtName"
                                                    type="button"
                                                    class="w-full rounded px-2 py-2 text-left text-sm hover:bg-muted"
                                                    @click="choosePbt(pbtName)"
                                                >
                                                    {{ pbtName }}
                                                </button>
                                                <p v-if="filteredPbtOptions.length === 0" class="px-2 py-2 text-sm text-muted-foreground">
                                                    Tiada PBT dijumpai.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-gray-800 px-3 py-1.5 text-xs font-semibold text-white shadow hover:bg-gray-900"
                                        @click="applyPbtFilter"
                                    >
                                        Tapis PBT
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center whitespace-nowrap rounded-md bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white shadow hover:bg-slate-800"
                            @click="resetAllFilters"
                        >
                            Reset Semua
                        </button>
                    </div>
                    <table class="w-full table-auto border-collapse">
                        <thead>
                            <tr class="bg-slate-100 dark:bg-slate-800">
                                <th
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold"
                                >
                                    No
                                </th>
                                <!-- <th
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold"
                                >
                                    Nama Syarikat
                                </th> -->
                                <th
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold"
                                >
                                    Nama Hotel
                                </th>
                                <th
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold"
                                >
                                    Tarikh Tamat
                                </th>
                                <th
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold"
                                >
                                    <div class="space-y-1">
                                        <div>Status</div>
                                        <select
                                            v-model="selectedStatus"
                                            class="w-full rounded-md border border-input bg-background px-2 py-1 text-xs font-normal"
                                        >
                                            <option value="">Semua</option>
                                            <option
                                                v-for="option in statusOptions"
                                                :key="`status-filter-${option.label}`"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </th>
                                <th
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold"
                                >
                                    <div class="space-y-1">
                                        <div>Status Bayaran</div>
                                        <select
                                            v-model="selectedPaymentStatus"
                                            class="w-full rounded-md border border-input bg-background px-2 py-1 text-xs font-normal"
                                        >
                                            <option value="">Semua</option>
                                            <option
                                                v-for="option in paymentStatusOptions"
                                                :key="`payment-status-filter-${option.label}`"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </th>
                                <th
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold"
                                >
                                    <div class="space-y-1">
                                        <div>Status Lesen</div>
                                        <select
                                            v-model="selectedLicenseStatus"
                                            class="w-full rounded-md border border-input bg-background px-2 py-1 text-xs font-normal"
                                        >
                                            <option value="">Semua</option>
                                            <option
                                                v-for="option in licenseStatusOptions"
                                                :key="`license-status-filter-${option.label}`"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </th>
                                <th
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold"
                                >
                                    <div class="space-y-1">
                                        <div>Status Fi Sejahtera</div>
                                        <select
                                            v-model="selectedFiSejahteraStatus"
                                            class="w-full rounded-md border border-input bg-background px-2 py-1 text-xs font-normal"
                                        >
                                            <option value="">Semua</option>
                                            <option
                                                v-for="option in fiSejahteraStatusOptions"
                                                :key="`fi-sejahtera-status-filter-${option.label}`"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </th>
                                <th
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold"
                                >
                                    Tindakan
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(application, index) in filteredApplications"
                                :key="application.id"
                            >
                                <td
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm text-slate-900 dark:text-slate-100"
                                >
                                    {{ index + 1 }}
                                </td>
                                <!-- <td
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.company_name || '-' }}
                                </td> -->
                                <td
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm text-slate-900 dark:text-slate-100"
                                >
                                    {{ application.hotel_name || '-' }}
                                </td>
                                <td
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm text-slate-900 dark:text-slate-100"
                                >
                                    {{ formatDate(application.expiry_date) }}
                                </td>
                                <td
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm"
                                >
                                    <span
                                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="application.status === 'Diluluskan'
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                            : application.status === 'Disekat'
                                                ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300'
                                            : application.status === 'Ditolak'
                                                ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                                                : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-100'"
                                    >
                                        {{ application.status || 'Dalam Proses' }}
                                    </span>
                                </td>
                                <td
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm"
                                >
                                    <span
                                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="application.payment_status === 'Berjaya'
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                            : application.payment_status === 'Gagal'
                                                ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                                                : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-100'"
                                    >
                                        {{ application.payment_status || 'Belum Dibayar' }}
                                    </span>
                                </td>
                                <td
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm"
                                >
                                    <span
                                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="application.license_status === 'Aktif'
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                            : application.license_status === 'Tamat Tempoh'
                                                ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                                                : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-100'"
                                    >
                                        {{ application.license_status || 'Belum Aktif' }}
                                    </span>
                                </td>
                                <td
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm"
                                >
                                    <span
                                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="application.fi_sejahtera_status === 'Dibayar'
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                            : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-100'"
                                    >
                                        {{ application.fi_sejahtera_status || 'Belum Dibayar' }}
                                    </span>
                                </td>
                                <td
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm"
                                >
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-if="isPbtAdmin && canApprove && application.status !== 'Diluluskan'"
                                            type="button"
                                            class="px-3 py-1 rounded-lg bg-green-600 text-white text-xs font-semibold hover:bg-green-700"
                                            @click="approve(application.id)"
                                        >
                                            Lulus
                                        </button>
                                        <button
                                            v-if="isBktAdmin && canApprove && application.status === 'Disekat'"
                                            type="button"
                                            class="px-3 py-1 rounded-lg bg-green-600 text-white text-xs font-semibold hover:bg-green-700"
                                            @click="approve(application.id)"
                                        >
                                            Lulus
                                        </button>
                                        <button
                                            v-if="isBktAdmin && canBlock && application.status !== 'Disekat' && application.status !== 'Ditolak' && application.fi_sejahtera_status === 'Belum Dibayar'"
                                            type="button"
                                            class="px-3 py-1 rounded-lg bg-orange-500 text-white text-xs font-semibold hover:bg-orange-600"
                                            @click="block(application.id)"
                                        >
                                            Sekat
                                        </button>
                                        <button
                                            v-if="isPbtAdmin && canReject && application.status !== 'Disekat' && application.payment_status !== 'Berjaya'"
                                            type="button"
                                            class="px-3 py-1 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-700"
                                            :disabled="application.status === 'Ditolak'"
                                            @click="reject(application.id)"
                                        >
                                            Tolak
                                        </button>
                                        <Link
                                            v-if="canViewDetails"
                                            class="px-3 py-1 rounded-lg bg-slate-700 text-white text-xs font-semibold hover:bg-slate-800"
                                            :href="`/admin/license-applications/${application.id}`"
                                        >
                                            Lihat
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!filteredApplications.length">
                                <td
                                    colspan="8"
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-6 text-center text-sm text-slate-600 dark:text-slate-400"
                                >
                                    Tiada permohonan ditemui.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
