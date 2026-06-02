<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import FiSejahteraNavbar from '@/components/fi-sejahtera/FiSejahteraNavbar.vue';
import FiSejahteraSidebar from '@/components/fi-sejahtera/FiSejahteraSidebar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type AppPageProps } from '@/types';

interface TaxSubmissionRow {
	id: number;
	hotel_id?: number;
	hotel_name?: string;
	month: string;
	year: number;
	amount?: number;
	payment_proof_url?: string | null;
	guest_report_url?: string | null;
	hotel_guest_list_url?: string | null;
	submitted_at?: string;
	status: string;
	verified_at?: string;
	verified_by_name?: string;
	remarks?: string;
}

const page = usePage<AppPageProps<{ submissions: TaxSubmissionRow[]; isAdmin: boolean; approverRole?: string | null }>>();

const submissions = computed<TaxSubmissionRow[]>(() => ((page.props as any).submissions ?? []) as TaxSubmissionRow[]);
const isAdmin = computed<boolean>(() => Boolean((page.props as any).isAdmin));
const approverRole = computed<string>(() => String((page.props as any).approverRole ?? ''));
const flashSuccess = computed<string>(() => ((page.props as any).flash?.success as string) ?? '');
const flashError = computed<string>(() => ((page.props as any).flash?.error as string) ?? '');
const hotelNameFilter = ref('');

const hotelNameOptions = computed<string[]>(() => {
	const options = submissions.value
		.map((submission) => submission.hotel_name?.trim() ?? '')
		.filter((name) => name.length > 0);

	return Array.from(new Set(options)).sort((a, b) => a.localeCompare(b));
});

const filteredSubmissions = computed<TaxSubmissionRow[]>(() => {
	if (!isAdmin.value) {
		return submissions.value;
	}

	if (!hotelNameFilter.value) {
		return submissions.value;
	}

	return submissions.value.filter((submission) => (submission.hotel_name ?? '') === hotelNameFilter.value);
});

const rejectTargetId = ref<number | null>(null);

const rejectForm = useForm({
	remarks: '',
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

function formatMonth(month: string) {
	return monthLabelMap[month] ?? month;
}

function formatStatusLabel(status: string) {
	if (status === 'submitted_to_pbt') {
		return 'Dihantar ke PBT';
	}

	if (status === 'submitted') {
		return 'Dihantar ke BKT';
	}

	if (status === 'paid') {
		return 'Bayaran Berjaya';
	}

	if (status === 'bkt_verified') {
		return 'Lulus BKT (Menunggu Bendahara)';
	}

	if (status === 'payment_pending') {
		return 'Menunggu Pembayaran';
	}

	if (status === 'verified') {
		return 'Disahkan';
	}

	if (status === 'rejected') {
		return 'Ditolak';
	}

	return status;
}

function statusBadgeClass(status: string) {
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

	if (status === 'payment_pending') {
		return 'bg-orange-100 text-orange-700';
	}

	if (status === 'bkt_verified') {
		return 'bg-indigo-100 text-indigo-700';
	}

	return 'bg-yellow-100 text-yellow-700';
}

function formatDate(dateTime?: string) {
	if (!dateTime) {
		return '-';
	}

	const date = new Date(dateTime);

	if (Number.isNaN(date.getTime())) {
		return dateTime;
	}

	const day = String(date.getDate()).padStart(2, '0');
	const month = String(date.getMonth() + 1).padStart(2, '0');
	const year = date.getFullYear();

	return `${day}/${month}/${year}`;
}

function formatAmount(amount?: number) {
	return new Intl.NumberFormat('ms-MY', {
		style: 'currency',
		currency: 'MYR',
		minimumFractionDigits: 2,
	}).format(Number(amount ?? 0));
}

function verifySubmission(id: number) {
	rejectTargetId.value = null;
	rejectForm.reset();
	rejectForm.clearErrors();

	rejectForm.patch(`/fi-sejahtera/tax/${id}/verify`);
}

function canApproveSubmission(submission: TaxSubmissionRow) {
	if (!isAdmin.value) {
		return false;
	}

	if (approverRole.value === 'pbt_admin') {
		return submission.status === 'submitted_to_pbt' || submission.status === 'rejected';
	}

	if (approverRole.value === 'bendahara_admin') {
		return submission.status === 'bkt_verified';
	}

	return submission.status === 'submitted' || submission.status === 'rejected';
}

function canRejectSubmission(submission: TaxSubmissionRow) {
	if (!isAdmin.value) {
		return false;
	}

	if (approverRole.value === 'pbt_admin') {
		return submission.status === 'submitted_to_pbt' || submission.status === 'rejected';
	}

	if (approverRole.value === 'bendahara_admin') {
		return submission.status === 'bkt_verified';
	}

	return submission.status === 'submitted' || submission.status === 'rejected';
}

function openReject(id: number) {
	rejectTargetId.value = id;
	rejectForm.remarks = '';
	rejectForm.clearErrors();
}

function cancelReject() {
	rejectTargetId.value = null;
	rejectForm.reset();
	rejectForm.clearErrors();
}

function submitReject() {
	if (!rejectTargetId.value) {
		return;
	}

	rejectForm.patch(`/fi-sejahtera/tax/${rejectTargetId.value}/reject`, {
		onSuccess: () => {
			cancelReject();
		},
	});
}

function resetFilter() {
	hotelNameFilter.value = '';
}
</script>

<template>
	<Head title="Rekod Pembayaran" />

	<div class="flex min-h-screen bg-muted/30">
		<FiSejahteraSidebar />

		<div class="flex min-h-screen flex-1 flex-col">
			<FiSejahteraNavbar />

			<main class="flex-1 space-y-6 p-6">
				<!-- Title + Filter Row -->
				<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
					
					<!-- Title -->
					<div>
						<h1 class="text-2xl font-bold text-foreground">Rekod Pembayaran</h1>
						<p class="text-sm text-muted-foreground">
							Status penghantaran bukti pembayaran dan laporan tetamu.
						</p>
					</div>

					<!-- Filter -->
					<div v-if="isAdmin" class="w-full sm:w-72">
						<label for="hotel_name_filter" class="mb-1 block text-sm font-medium text-foreground">
							Tapis Rumah Tumpangan
						</label>

						<div class="flex gap-2">
							<select
								id="hotel_name_filter"
								v-model="hotelNameFilter"
								class="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm"
							>
								<option value="">Semua Rumah Tumpangan</option>
								<option v-for="hotelName in hotelNameOptions" :key="hotelName" :value="hotelName">
									{{ hotelName }}
								</option>
							</select>

							<button
								@click="resetFilter"
								class="inline-flex items-center justify-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-gray-900"
							>
								Reset
							</button>
						</div>
					</div>
				</div>

				<!-- Flash Messages -->
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
						<CardTitle>Rekod Pembayaran</CardTitle>
					</CardHeader>
					<CardContent>
						<div class="overflow-x-auto">
							<table class="w-full text-left">
								<thead>
									<tr class="text-sm text-muted-foreground">
										<th class="p-2">Bil.</th>
										<th class="p-2">Hotel</th>
										<th class="p-2">Bulan/Tahun</th>
										<th class="p-2">Jumlah (RM)</th>
										<th class="p-2">Bukti Bayaran</th>
										<th class="p-2">Senarai Tetamu</th>
										<th class="p-2">Senarai Tetamu (Sistem Rumah Tumpangan)</th>
										<th class="p-2">Tarikh Hantar</th>
										<th class="p-2">Status</th>
										<th class="p-2">Pengesahan</th>
										<th class="p-2">Catatan</th>
										<th class="p-2">Tindakan</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="(submission, index) in filteredSubmissions" :key="submission.id" class="border-t align-top">
										<td class="p-2">{{ index + 1 }}</td>
										<td class="p-2">{{ submission.hotel_name ?? '-' }}</td>
										<td class="p-2">{{ formatMonth(submission.month) }} {{ submission.year }}</td>
										<td class="p-2">{{ formatAmount(submission.amount) }}</td>
										<td class="p-2">
											<a v-if="submission.payment_proof_url" :href="submission.payment_proof_url" target="_blank" class="text-blue-600 hover:underline">Lihat</a>
											<span v-else>-</span>
										</td>
										<td class="p-2">
											<a v-if="submission.guest_report_url" :href="submission.guest_report_url" target="_blank" class="text-blue-600 hover:underline">Lihat</a>
											<span v-else>-</span>
										</td>
										<td class="p-2">
											<a v-if="submission.hotel_guest_list_url" :href="submission.hotel_guest_list_url" target="_blank" class="text-blue-600 hover:underline">Lihat</a>
											<span v-else>-</span>
										</td>
										<td class="p-2">{{ formatDate(submission.submitted_at) }}</td>
										<td class="p-2">
											<span
												class="inline-flex items-center rounded-lg px-3 py-1 text-xs font-semibold"
												:class="statusBadgeClass(submission.status)"
											>
													{{ formatStatusLabel(submission.status) }}
											</span>
										</td>
										<td class="p-2">
											<div>{{ formatDate(submission.verified_at) }}</div>
											<div class="text-xs text-muted-foreground">{{ submission.verified_by_name ?? '-' }}</div>
										</td>
										<td class="p-2">{{ submission.remarks ?? '-' }}</td>
										<td class="p-2">
											<div v-if="rejectTargetId !== submission.id" class="flex gap-2">
												<template v-if="canApproveSubmission(submission) || canRejectSubmission(submission)">
													<Button
														type="button"
														size="sm"
														:disabled="rejectForm.processing"
														v-if="canApproveSubmission(submission)"
														@click="verifySubmission(submission.id)"
													>
														Lulus
													</Button>
													<Button
														type="button"
														size="sm"
														variant="destructive"
														:disabled="rejectForm.processing"
														v-if="canRejectSubmission(submission)"
														@click="openReject(submission.id)"
													>
														Tolak
													</Button>
												</template>
												<a
													v-else-if="submission.status === 'rejected'"
													:href="`/fi-sejahtera/payment?submission_id=${submission.id}&hotel_id=${submission.hotel_id ?? ''}&month=${submission.month}&year=${submission.year}`"
													class="inline-flex items-center rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700"
												>
													Edit & Hantar Semula
												</a>
											</div>

											<form v-else-if="isAdmin" class="space-y-2" @submit.prevent="submitReject">
												<textarea
													v-model="rejectForm.remarks"
													class="w-64 rounded-md border border-input bg-background px-3 py-2 text-sm"
													rows="3"
													placeholder="Masukkan sebab penolakan"
												/>
												<p v-if="rejectForm.errors.remarks" class="text-xs text-red-600">{{ rejectForm.errors.remarks }}</p>
												<div class="flex gap-2">
													<Button type="submit" size="sm" variant="destructive" :disabled="rejectForm.processing">
														Hantar Tolak
													</Button>
													<Button type="button" size="sm" variant="outline" :disabled="rejectForm.processing" @click="cancelReject">
														Batal
													</Button>
												</div>
											</form>
										</td>
									</tr>
									<tr v-if="filteredSubmissions.length === 0">
										<td colspan="12" class="p-3 text-sm text-muted-foreground">
											Tiada rekod penghantaran cukai ditemui.
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
