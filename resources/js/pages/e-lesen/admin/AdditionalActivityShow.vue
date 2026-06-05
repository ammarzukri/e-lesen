<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
	activity: {
		id: number;
		activity_name?: string;
		rates?: Array<{
			id: number;
			type_name?: string;
			min_area?: number;
			max_area?: number;
			amount?: number;
		}>;
	};
}>();

const breadcrumbs: BreadcrumbItem[] = [
	{ title: 'Dashboard', href: '/dashboard' },
	{ title: 'Aktiviti Tambahan', href: '/admin/license-additional-activities' },
	{ title: props.activity.activity_name ?? 'Butiran', href: `/admin/license-additional-activities/${props.activity.id}` },
];

const rates = computed(() => props.activity.rates ?? []);
</script>

<template>
	<Head :title="props.activity.activity_name ?? 'Aktiviti'" />

	<AppLayout :breadcrumbs="breadcrumbs">
		<div class="w-full h-full flex flex-col p-6 bg-white dark:bg-black rounded-xl shadow dark:shadow-black/30">
			<div class="flex items-center justify-between mb-4">
				<div>
					<h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">{{ props.activity.activity_name }}</h2>
					<p class="text-sm text-slate-600 dark:text-slate-400">Senarai kadar aktiviti tambahan.</p>
				</div>

				<Link href="/admin/license-additional-activities" class="inline-flex items-center rounded-lg bg-slate-200 px-4 py-2 text-sm font-semibold">Kembali</Link>
			</div>

			<div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 p-4 overflow-x-auto">
				<table class="w-full table-auto border-collapse">
					<thead>
						<tr class="bg-slate-100 dark:bg-slate-800">
							<th class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold">Bil</th>
							<th class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold">Jenis</th>
							<th class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold">Min Kawasan</th>
							<th class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold">Max Kawasan</th>
							<th class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold">Amaun (RM)</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="(rate, index) in rates" :key="rate.id">
							<td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm">{{ index + 1 }}</td>
							<td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm">{{ rate.type_name ?? '-' }}</td>
							<td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm">{{ rate.min_area ?? '-' }}</td>
							<td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm">{{ rate.max_area ?? '-' }}</td>
							<td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm">{{ rate.amount !== undefined ? (rate.amount / 100).toFixed(2) : '-' }}</td>
						</tr>

						<tr v-if="rates.length === 0">
							<td colspan="5" class="border border-slate-200 dark:border-slate-700 px-3 py-6 text-center text-sm text-slate-600 dark:text-slate-400">Tiada kadar ditemui.</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</AppLayout>
</template>

