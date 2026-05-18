<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import license from '@/routes/license'
import type { AppPageProps, BreadcrumbItem } from '@/types'

type LicenseApplication = {
	id: number
	name?: string
	hotel_name?: string
	ic_no?: string
	company_name?: string
	status?: string
	payment_status?: string
	payment_amount?: number
	created_at?: string
	updated_at?: string
	license_types?: LicenseType[]
	hotel?: HotelInfo
}

type LicenseType = {
	aktiviti?: string
}

type HotelInfo = {
	license?: LicenseInfo
}

type LicenseInfo = {
	license_number?: string
	start_date?: string
	expiry_date?: string
	status?: string
	latest_renewal?: LicenseRenewalInfo
}

type LicenseRenewalInfo = {
	status?: string
	payment_status?: string
}

const props = defineProps<{
	applications?: LicenseApplication[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
	{ title: 'Dashboard', href: '/dashboard' },
	{ title: 'Status Permohonan', href: '#' },
]

const applications = computed(() => props.applications ?? [])

const page = usePage<
	AppPageProps<{
		flash?: {
			payment?: {
				status?: string
				message?: string
			}
			renewal?: {
				status?: string
				message?: string
			}
		}
	}>
>()
const paymentFlash = computed(() => page.props.flash?.payment)
const renewalFlash = computed(() => page.props.flash?.renewal)
const isStaff = computed(() => page.props.auth?.user?.role === 'staff')
const showPaymentSuccessOnce = computed(() => paymentFlash.value?.status === 'success')

function getActivityList(application: LicenseApplication): string[] {
	return (application.license_types ?? [])
		.map((item) => item.aktiviti)
		.filter((value): value is string => Boolean(value && value.trim()))
}

function getStatusClass(status?: string): string {
	if (status === 'Aktif' || status === 'Diluluskan') {
		return 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
	}

	if (status === 'Tamat Tempoh' || status === 'Ditolak') {
		return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
	}

	return 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-100'
}

function getDisplayStatus(application: LicenseApplication): string {
	return getLicense(application)?.status || application.status || 'Dalam Proses'
}

function getPaymentStatus(application: LicenseApplication): string {
	return application.payment_status || 'Belum Dibayar'
}

function paymentStartUrl(applicationId: number): string {
	return license.payment.start({ query: { application_id: applicationId } }).url
}

function getLicense(application: LicenseApplication): LicenseInfo | null {
	return application.hotel?.license ?? null
}

function parseDate(dateString?: string): Date | null {
	if (!dateString) return null

	const parsedDate = new Date(dateString)
	return Number.isNaN(parsedDate.getTime()) ? null : parsedDate
}

function canRenewLicense(application: LicenseApplication): boolean {
	const expiryDate = parseDate(getLicense(application)?.expiry_date)
	if (!expiryDate) return false

	const renewalStartDate = new Date(expiryDate)
	renewalStartDate.setMonth(renewalStartDate.getMonth() - 1)

	return new Date() >= renewalStartDate
}

function renewalUrl(applicationId: number): string {
	return `/license/renewals/payment/start?application_id=${applicationId}`
}

function hasPendingRenewal(application: LicenseApplication): boolean {
	const latestRenewal = getLicense(application)?.latest_renewal
	return latestRenewal?.status === 'Dalam Proses' && latestRenewal?.payment_status === 'Berjaya'
}

function isBlockedStatus(application: LicenseApplication): boolean {
	return application.status === 'Disekat'
}

function canClickRenewButton(application: LicenseApplication): boolean {
	return canRenewLicense(application) && !hasPendingRenewal(application) && !isBlockedStatus(application)
}

function formatDate(dateString?: string): string {
	if (!dateString) return '-'

	const parsedDate = new Date(dateString)
	if (Number.isNaN(parsedDate.getTime())) return dateString

	return parsedDate.toLocaleString('ms-MY', {
		year: 'numeric',
		month: '2-digit',
		day: '2-digit',
		// hour: '2-digit',
		// minute: '2-digit',
	})
}
</script>

<template>
	<Head title="Status Permohonan" />

	<AppLayout :breadcrumbs="breadcrumbs">
		<div class="w-full h-full flex flex-col p-6 bg-white dark:bg-black rounded-xl shadow dark:shadow-black/30">
			<div class="flex-1 overflow-auto space-y-6">
				<div class="rounded-xl bg-blue-50 text-blue-700 border border-blue-200 px-4 py-3 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900">
					Semua permohonan anda direkodkan di sini.
				</div>

				<div
					v-if="paymentFlash?.message"
					:class="[
						'rounded-xl px-4 py-3 border text-sm',
						paymentFlash?.status === 'success'
							? 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-900'
							: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-900',
					]"
				>
					{{ paymentFlash.message }}
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

				<div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 p-6">
					<div class="mb-6">
						<h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">Status Permohonan</h2>
						<p class="text-sm text-slate-600 dark:text-slate-400">Senarai semua permohonan anda</p>
					</div>

					<div v-if="applications.length" class="space-y-3">
						<details
							v-for="application in applications"
							:key="application.id"
							class="group rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950/40"
						>
							<summary class="list-none cursor-pointer px-4 py-3">
								<div class="flex items-center justify-between gap-3">
									<div class="min-w-0">
										<div class="text-sm font-semibold text-slate-900 dark:text-slate-100 truncate">
											{{ application.hotel_name || application.name || `Permohonan #${application.id}` }}
										</div>
										<div class="text-xs text-slate-500 dark:text-slate-400">Dihantar: {{ formatDate(application.created_at) }}</div>
										<div class="text-xs text-slate-500 dark:text-slate-400">Tamat Tempoh: {{ formatDate(getLicense(application)?.expiry_date) }}</div>
										<div
											v-if="!isStaff && application.status === 'Diluluskan' && getPaymentStatus(application) === 'Belum Dibayar'"
											class="mt-1 text-xs font-semibold text-amber-700 dark:text-amber-300"
										>
											Tindakan diperlukan: Sila buat pembayaran.
										</div>
									</div>
									<div :class="['px-3 py-1 rounded-full text-xs font-semibold', getStatusClass(getDisplayStatus(application))]">
										{{ getDisplayStatus(application) }}
									</div>
								</div>
								<div class="mt-2 flex justify-center text-slate-400 dark:text-slate-500">↓</div>
							</summary>

							<div class="px-4 pb-4 pt-1 border-t border-slate-200 dark:border-slate-700 space-y-6">
								<div
										v-if="getPaymentStatus(application) === 'Berjaya' && getLicense(application)?.license_number"
										class="mt-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/40 px-4 py-3 text-sm"
									>
										<div class="text-xs font-semibold text-slate-600 dark:text-slate-400">No Lesen</div>
										<div class="text-base font-bold text-slate-900 dark:text-slate-100">{{ getLicense(application)?.license_number }}</div>
										<div class="mt-2 text-xs text-slate-600 dark:text-slate-400">
											Tarikh mula {{ formatDate(getLicense(application)?.start_date) }}
										</div>
										<div class="mt-2 text-xs text-slate-600 dark:text-slate-400">
											Sah sehingga {{ formatDate(getLicense(application)?.expiry_date) }}
										</div>
									</div>
								<section>
									<h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Maklumat Pemohon</h3>
									<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
										<div>
											<div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Nama Pemohon</div>
											<div class="text-sm text-slate-900 dark:text-slate-100">{{ application.name || '-' }}</div>
										</div>
										<div>
											<div class="text-xs font-semibold text-slate-600 dark:text-slate-400">No Kad Pengenalan</div>
											<div class="text-sm text-slate-900 dark:text-slate-100">{{ application.ic_no || '-' }}</div>
										</div>
										<div>
											<div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Nama Perniagaan / Syarikat</div>
											<div class="text-sm text-slate-900 dark:text-slate-100">{{ application.company_name || '-' }}</div>
										</div>
										<div>
											<div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Status Pembayaran</div>
											<div class="text-sm text-slate-900 dark:text-slate-100">{{ getPaymentStatus(application) }}</div>
										</div>
									</div>
								</section>

								<section>
									<h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Lesen Yang Dipohon</h3>
									<div class="space-y-2">
										<div
											v-for="(activity, idx) in getActivityList(application)"
											:key="`application-${application.id}-activity-${idx}`"
											class="rounded-xl border border-slate-200 dark:border-slate-700 p-3"
										>
											<div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Lesen #{{ idx + 1 }}</div>
											<div class="text-sm text-slate-900 dark:text-slate-100">{{ activity }}</div>
										</div>
										<div v-if="!getActivityList(application).length" class="text-sm text-slate-600 dark:text-slate-400">
											Tiada lesen direkodkan.
										</div>
									</div>
								</section>

								<div v-if="!isStaff && ['Diluluskan', 'Disekat'].includes(application.status || '')" class="pt-1">
									<div v-if="getPaymentStatus(application) === 'Berjaya'" class="flex flex-col gap-3">
										<div
											v-if="showPaymentSuccessOnce"
											class="rounded-xl bg-green-50 text-green-700 border border-green-200 px-4 py-3 text-sm dark:bg-green-900/30 dark:text-green-300 dark:border-green-900"
										>
											Pembayaran berjaya diterima.
										</div>
										<div class="flex flex-col md:flex-row md:items-center gap-3">
											<Link
												:href="renewalUrl(application.id)"
												:class="[
													'inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold text-white shadow',
													canClickRenewButton(application)
														? 'bg-blue-600 hover:bg-blue-700'
														: 'bg-slate-400 cursor-not-allowed pointer-events-none',
												]"
											>
												Perbaharui Lesen
											</Link>
											<p v-if="isBlockedStatus(application)" class="text-xs text-slate-600 dark:text-slate-400">
												Anda mestilah membayar cukai Fi Sejahtera untuk membaharui lesen
											</p>
											<p v-else-if="hasPendingRenewal(application)" class="text-xs text-slate-600 dark:text-slate-400">
												Permohonan pembaharuan anda sedang menunggu kelulusan admin.
											</p>
											<p v-else-if="!canRenewLicense(application)" class="text-xs text-slate-600 dark:text-slate-400">
												Lesen boleh dibaharui bermula sebulan sebelum tarikh tamat
											</p>
										</div>
									</div>
									<div v-if="getPaymentStatus(application) !== 'Berjaya'" class="flex flex-col gap-3">
										<div
											v-if="getPaymentStatus(application) === 'Gagal'"
											class="rounded-xl bg-red-50 text-red-700 border border-red-200 px-4 py-3 text-sm dark:bg-red-900/30 dark:text-red-300 dark:border-red-900"
										>
											Pembayaran gagal. Sila cuba lagi.
										</div>
										<div
											v-if="getPaymentStatus(application) === 'Belum Dibayar'"
											class="rounded-xl bg-amber-50 text-amber-700 border border-amber-200 px-4 py-3 text-sm dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-900"
										>
											Permohonan telah diluluskan. Sila buat pembayaran untuk meneruskan proses lesen.
										</div>
										<Link
											:href="paymentStartUrl(application.id)"
											class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
										>
											Teruskan ke Pembayaran
										</Link>
										<p class="text-xs text-slate-500 dark:text-slate-400">Jumlah bayaran: RM100</p>
									</div>
								</div>
							</div>
						</details>
					</div>

					<div v-else class="text-sm text-slate-600 dark:text-slate-400">
						Tiada rekod permohonan ditemui.
					</div>
				</div>
			</div>
		</div>
	</AppLayout>
</template>
