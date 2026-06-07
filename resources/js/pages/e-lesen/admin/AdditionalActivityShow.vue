<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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

const showModal = ref(false);
const isEdit = ref(false);
const editId = ref<number | null>(null);

const form = useForm({
	type_name: '',
	min_area: '',
	max_area: '',
	amount: '',
});

const breadcrumbs: BreadcrumbItem[] = [
	{ title: 'Dashboard', href: '/dashboard' },
	{ title: 'Aktiviti Tambahan', href: '/admin/license-additional-activities' },
	{ title: props.activity.activity_name ?? 'Butiran', href: `/admin/license-additional-activities/${props.activity.id}` },
];

const submit = () => {
    if (isEdit.value && editId.value) {
        form.put(`/admin/license-additional-activities/rates/${editId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    } else {
        form.post(`/admin/license-additional-activities/${props.activity.id}/rates`, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    }
};

const deleteRate = (id: number) => {
    if (!confirm('Yakin nak buang kadar ini?')) return;

    form.delete(`/admin/license-additional-activities/rates/${id}`, {
        preserveScroll: true,
    });
};

const openCreateModal = () => {
    isEdit.value = false;
    editId.value = null;
    form.reset();
    showModal.value = true;
};

const openEditModal = (rate: any) => {
    isEdit.value = true;
    editId.value = rate.id;

    form.type_name = rate.type_name;
    form.min_area = rate.min_area;
    form.max_area = rate.max_area;
    form.amount = rate.amount;

    showModal.value = true;
}

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
                    <div class="mt-4">
                        <Link
                            href="/admin/license-additional-activities"
                            class="px-4 py-2 rounded-lg bg-slate-700 text-white text-sm font-semibold hover:bg-slate-800"
                        >
                            ← Kembali
                        </Link>
                    </div>
				</div>

				<div class="flex flex-wrap gap-3">
                    <button
                        @click="openCreateModal"
                        class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700"
                    >
                        + Tambah Kadar
                    </button>
                </div>
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
                            <th class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold">Tindakan</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="(rate, index) in rates" :key="rate.id">
							<td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm">{{ index + 1 }}</td>
							<td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm">{{ rate.type_name ?? '-' }}</td>
							<td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm">{{ rate.min_area ?? '-' }}</td>
							<td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm">{{ rate.max_area ?? '-' }}</td>
							<td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm">{{ rate.amount !== undefined ? (rate.amount / 100).toFixed(2) : '-' }}</td>
                            <td class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm">
                                <div class="flex gap-2">
                                    <button
                                        @click="openEditModal(rate)"
                                        class="px-3 py-1 rounded-lg bg-yellow-500 text-white text-xs font-semibold hover:bg-yellow-600"
                                    >
                                        Kemaskini
                                    </button>

                                    <button
                                        @click="deleteRate(rate.id)"
                                        class="px-3 py-1 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-700"
                                    >
                                        Buang
                                    </button>
                                </div>
                            </td>
						</tr>

						<tr v-if="rates.length === 0">
							<td colspan="6" class="border border-slate-200 dark:border-slate-700 px-3 py-6 text-center text-sm text-slate-600 dark:text-slate-400">Tiada kadar ditemui.</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        >
            <div class="w-full max-w-lg rounded-xl bg-white dark:bg-slate-900 p-6 shadow-xl">
                <h3 class="mb-4 text-lg font-bold text-slate-900 dark:text-slate-100">
                    Tambah Kadar
                </h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Jenis</label>
                        <input
                            v-model="form.type_name"
                            type="text"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 dark:bg-slate-800 dark:border-slate-700"
                        />
                        <p v-if="form.errors.type_name" class="text-sm text-red-500 mt-1">
                            {{ form.errors.type_name }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Min Keluasan</label>
                        <input
                            v-model="form.min_area"
                            type="number"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 dark:bg-slate-800 dark:border-slate-700"
                        />
                        <p v-if="form.errors.min_area" class="text-sm text-red-500 mt-1">
                            {{ form.errors.min_area }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Max Keluasan</label>
                        <input
                            v-model="form.max_area"
                            type="number"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 dark:bg-slate-800 dark:border-slate-700"
                        />
                        <p v-if="form.errors.max_area" class="text-sm text-red-500 mt-1">
                            {{ form.errors.max_area }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Amaun (RM)</label>
                        <input
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 dark:bg-slate-800 dark:border-slate-700"
                        />
                        <p v-if="form.errors.amount" class="text-sm text-red-500 mt-1">
                            {{ form.errors.amount }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        @click="showModal = false"
                        class="px-4 py-2 rounded-lg bg-slate-500 text-white hover:bg-slate-600"
                    >
                        Batal
                    </button>

                    <button
                        @click="submit"
                        :disabled="form.processing"
                        class="px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </div>
        </div>
	</AppLayout>
</template>

