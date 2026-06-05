<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { AppPageProps, BreadcrumbItem } from '@/types'

type RenewalRecord = {
    id: number
    status?: string
    payment_status?: string
    current_expiry_date?: string
    renewed_until_date?: string
    created_at?: string
    user?: {
        name?: string
        email?: string
    }
    license?: {
        license_number?: string
        hotel?: {
            name?: string
            company_name?: string
        }
    }
}

const props = defineProps<{
    renewals?: RenewalRecord[]
    permissions?: {
        canApprove?: boolean
        canReject?: boolean
        canView?: boolean
    }
}>()

const page = usePage<
    AppPageProps<{
        flash?: {
            renewal?: {
                status?: string
                message?: string
            }
        }
    }>
>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pembaharuan Lesen', href: '/admin/license-renewals' },
]

const renewals = computed(() => props.renewals ?? [])
const canApprove = computed(() => Boolean(props.permissions?.canApprove))
const canReject = computed(() => Boolean(props.permissions?.canReject))
const canView = computed(() => Boolean(props.permissions?.canView))
const renewalFlash = computed(() => page.props.flash?.renewal)
const selectedHotelName = ref<string>('')
const appliedHotelName = ref<string>('')
const hotelSearch = ref<string>('')
const hotelDropdownOpen = ref<boolean>(false)
const hotelDropdownRef = ref<HTMLElement | null>(null)
const selectedPaymentStatus = ref<string>('')
const selectedApplicationStatus = ref<string>('')

function normalizeHotelName(renewal: RenewalRecord): string {
    const hotelName = renewal.license?.hotel?.name?.trim() ?? ''
    return hotelName || 'Tidak Dinyatakan'
}

const hotelOptions = computed(() => {
    const unique = new Set(renewals.value.map((renewal) => normalizeHotelName(renewal)))
    return Array.from(unique).sort((a, b) => a.localeCompare(b))
})

const filteredHotelOptions = computed(() => {
    const keyword = hotelSearch.value.trim().toLowerCase()

    if (!keyword) {
        return hotelOptions.value
    }

    return hotelOptions.value.filter((hotelName) => hotelName.toLowerCase().includes(keyword))
})

const selectedHotelLabel = computed(() => selectedHotelName.value || 'Semua Hotel')

const paymentStatusOptions = computed(() => {
    const unique = new Set(renewals.value.map((renewal) => renewal.payment_status?.trim() ?? ''))
    return Array.from(unique)
        .sort((a, b) => a.localeCompare(b))
        .map((value) => ({
            value,
            label: value || 'Belum Dibayar',
        }))
})

const applicationStatusOptions = computed(() => {
    const unique = new Set(renewals.value.map((renewal) => renewal.status?.trim() ?? ''))
    return Array.from(unique)
        .sort((a, b) => a.localeCompare(b))
        .map((value) => ({
            value,
            label: value || 'Dalam Proses',
        }))
})

const filteredRenewals = computed(() => {
    return renewals.value.filter((renewal) => {
        const matchHotel = !appliedHotelName.value || normalizeHotelName(renewal) === appliedHotelName.value
        const matchPaymentStatus = !selectedPaymentStatus.value || (renewal.payment_status?.trim() ?? '') === selectedPaymentStatus.value
        const matchApplicationStatus = !selectedApplicationStatus.value || (renewal.status?.trim() ?? '') === selectedApplicationStatus.value

        return matchHotel && matchPaymentStatus && matchApplicationStatus
    })
})

function toggleHotelDropdown() {
    hotelDropdownOpen.value = !hotelDropdownOpen.value
}

function chooseHotel(hotelName?: string) {
    selectedHotelName.value = hotelName ?? ''
    hotelDropdownOpen.value = false
}

function applyHotelFilter() {
    appliedHotelName.value = selectedHotelName.value
}

function resetAllFilters(): void {
    selectedHotelName.value = ''
    appliedHotelName.value = ''
    hotelSearch.value = ''
    hotelDropdownOpen.value = false
    selectedPaymentStatus.value = ''
    selectedApplicationStatus.value = ''
}

function handleOutsideClick(event: MouseEvent) {
    if (!hotelDropdownRef.value) {
        return
    }

    const target = event.target as Node

    if (!hotelDropdownRef.value.contains(target)) {
        hotelDropdownOpen.value = false
    }
}

function formatDate(dateString?: string): string {
    if (!dateString) return '-'

    const parsedDate = new Date(dateString)
    if (Number.isNaN(parsedDate.getTime())) return '-'

    return parsedDate.toLocaleDateString('ms-MY', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    })
}

function approve(renewalId: number): void {
    router.post(`/admin/license-renewals/${renewalId}/approve`)
}

function reject(renewalId: number): void {
    router.post(`/admin/license-renewals/${renewalId}/reject`)
}

onMounted(() => {
    document.addEventListener('click', handleOutsideClick)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleOutsideClick)
})
</script>

<template>
    <Head title="Pembaharuan Lesen" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="w-full h-full flex flex-col p-6 bg-white dark:bg-black rounded-xl shadow dark:shadow-black/30">
            <div class="flex-1 overflow-auto space-y-6">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">Pembaharuan Lesen</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400">Permohonan pembaharuan lesen yang memerlukan tindakan admin.</p>
                    </div>
                </div>

                <div
                    v-if="renewalFlash?.message"
                    :class="[
                        'rounded-xl px-4 py-3 border text-sm',
                        renewalFlash?.status === 'success'
                            ? 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-900'
                            : 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-900',
                    ]"
                >
                    {{ renewalFlash.message }}
                </div>

                <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 p-4 overflow-x-auto">
                    <div class="mb-3 flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-end">
                        <div class="w-full md:min-w-64 lg:w-auto">
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
                                <th class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold">No</th>
                                <th class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold">Pemohon</th>
                                <th class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold">No Lesen</th>
                                <th class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold">Hotel / Syarikat</th>
                                <th class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold">Tamat Semasa</th>
                                <th class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold">
                                    <div class="space-y-1">
                                        <div>Status Bayaran</div>
                                        <select
                                            v-model="selectedPaymentStatus"
                                            class="w-full rounded-md border border-input bg-background px-2 py-1 text-xs font-normal"
                                        >
                                            <option value="">Semua</option>
                                            <option
                                                v-for="option in paymentStatusOptions"
                                                :key="`renewal-payment-filter-${option.label}`"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </th>
                                <th class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold">
                                    <div class="space-y-1">
                                        <div>Status Permohonan</div>
                                        <select
                                            v-model="selectedApplicationStatus"
                                            class="w-full rounded-md border border-input bg-background px-2 py-1 text-xs font-normal"
                                        >
                                            <option value="">Semua</option>
                                            <option
                                                v-for="option in applicationStatusOptions"
                                                :key="`renewal-status-filter-${option.label}`"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </div>
                                </th>
                                <th class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(renewal, index) in filteredRenewals" :key="renewal.id">
                                <td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm text-slate-900 dark:text-slate-100">{{ index + 1 }}</td>
                                <td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm text-slate-900 dark:text-slate-100">
                                    <div class="font-medium">{{ renewal.user?.name || '-' }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ renewal.user?.email || '-' }}</div>
                                </td>
                                <td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm text-slate-900 dark:text-slate-100">{{ renewal.license?.license_number || '-' }}</td>
                                <td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm text-slate-900 dark:text-slate-100">
                                    <div>{{ renewal.license?.hotel?.name || '-' }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ renewal.license?.hotel?.company_name || '-' }}</div>
                                </td>
                                <td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm text-slate-900 dark:text-slate-100">{{ formatDate(renewal.current_expiry_date) }}</td>
                                <td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm">
                                    <span
                                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="renewal.payment_status === 'Berjaya'
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                            : renewal.payment_status === 'Gagal'
                                                ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                                                : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-100'"
                                    >
                                        {{ renewal.payment_status || 'Belum Dibayar' }}
                                    </span>
                                </td>
                                <td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm">
                                    <span
                                        class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="renewal.status === 'Diluluskan'
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                            : renewal.status === 'Ditolak'
                                                ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                                                : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-100'"
                                    >
                                        {{ renewal.status || 'Dalam Proses' }}
                                    </span>
                                </td>
                                <td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm">
                                    <div class="flex flex-wrap gap-2">
                                        <Link
                                            v-if="canView"
                                            :href="`/admin/license-renewals/${renewal.id}`"
                                            class="px-3 py-1 rounded-lg bg-slate-700 text-white text-xs font-semibold hover:bg-slate-800"
                                        >
                                            Lihat
                                        </Link>
                                        <button
                                            v-if="canApprove"
                                            type="button"
                                            class="px-3 py-1 rounded-lg bg-green-600 text-white text-xs font-semibold hover:bg-green-700 disabled:bg-slate-400 disabled:cursor-not-allowed"
                                            :disabled="renewal.status !== 'Dalam Proses' || renewal.payment_status !== 'Berjaya'"
                                            @click="approve(renewal.id)"
                                        >
                                            Lulus
                                        </button>
                                        <button
                                            v-if="canReject"
                                            type="button"
                                            class="px-3 py-1 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-700 disabled:bg-slate-400 disabled:cursor-not-allowed"
                                            :disabled="renewal.status !== 'Dalam Proses'"
                                            @click="reject(renewal.id)"
                                        >
                                            Tolak
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!filteredRenewals.length">
                                <td colspan="8" class="border border-slate-200 dark:border-slate-700 px-3 py-6 text-center text-sm text-slate-600 dark:text-slate-400">
                                    Tiada permohonan pembaharuan ditemui.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
