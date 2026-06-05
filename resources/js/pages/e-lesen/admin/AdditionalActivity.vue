<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Aktiviti Tambahan', href: '/admin/additional-activity' },
];

const showAddModal = ref(false);
const activityName = ref('');

const props = defineProps<{
    activities?: { id: number; activity_name: string }[];
}>();

function openAddModal() {
    showAddModal.value = true;
}

function closeAddModal() {
    showAddModal.value = false;
    activityName.value = '';
}

function addActivity() {
    if (!activityName.value.trim()) {
        return;
    }

    router.post('/admin/license-additional-activities', {
        activity_name: activityName.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closeAddModal();
            router.reload();
        },
    });
}

const activities = ref(props.activities ?? []);

watch(() => props.activities, (v) => {
    activities.value = v ?? [];
});

const showEditModal = ref(false);
const editActivityName = ref('');
const editingActivityId = ref<number | null>(null);

function openEditModal(activity: { id: number; activity_name: string }) {
    editingActivityId.value = activity.id;
    editActivityName.value = activity.activity_name ?? '';
    showEditModal.value = true;
}

function closeEditModal() {
    showEditModal.value = false;
    editActivityName.value = '';
    editingActivityId.value = null;
}

function submitEdit() {
    if (!editingActivityId.value) return;

    router.post(`/admin/license-additional-activities/${editingActivityId.value}`, {
        _method: 'PATCH',
        activity_name: editActivityName.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            const idx = activities.value.findIndex((a: any) => a.id === editingActivityId.value);
            if (idx !== -1) {
                activities.value[idx].activity_name = editActivityName.value;
            }
            closeEditModal();
        },
    });
}

function deleteActivity(activity: { id: number }) {
    if (!confirm('Padam aktiviti ini?')) return;

    router.post(`/admin/license-additional-activities/${activity.id}`, {
        _method: 'DELETE',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            activities.value = activities.value.filter((a: any) => a.id !== activity.id);
        },
    });
}
</script>

<template>
    <Head title="Aktiviti Tambahan" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="w-full h-full flex flex-col p-6 bg-white dark:bg-black rounded-xl shadow dark:shadow-black/30"
        >
            <div class="flex-1 overflow-auto flex flex-col gap-6">

                <!-- Page Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h2
                            class="text-xl font-bold text-slate-900 dark:text-slate-100"
                        >
                            Aktiviti Tambahan
                        </h2>
                        <p class="text-sm text-slate-600 dark:text-slate-400">
                            Senarai aktiviti tambahan dalam PBT.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                        @click="openAddModal"
                    >
                        Tambah Aktiviti
                    </button>
                </div>

                <!-- Table -->
                <div
                    class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 p-4 overflow-x-auto"
                >
                    <table class="w-full table-auto border-collapse">
                        <thead>
                            <tr class="bg-slate-100 dark:bg-slate-800">
                                <th
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold"
                                >
                                    Bil
                                </th>

                                <th
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-left text-sm font-semibold"
                                >
                                    Nama Aktiviti
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
                                v-for="(activity, index) in activities"
                                :key="activity.id"
                            >
                                <td
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm"
                                >
                                    {{ index + 1 }}
                                </td>

                                <td
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm"
                                >
                                    {{ activity.activity_name }}
                                </td>

                                <td
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-2 text-sm"
                                >
                                    <div class="flex gap-2">
                                        <Link
                                            :href="`/admin/license-additional-activities/${activity.id}`"
                                            class="px-3 py-1 rounded-lg bg-gray-500 text-white text-xs font-semibold hover:bg-gray-600"
                                        >
                                            Lihat
                                        </Link>

                                        <button
                                            type="button"
                                            class="px-3 py-1 rounded-lg bg-yellow-500 text-white text-xs font-semibold hover:bg-yellow-600"
                                            @click="openEditModal(activity)"
                                        >
                                            Kemaskini
                                        </button>

                                        <button
                                            type="button"
                                            class="px-3 py-1 rounded-lg bg-red-600 text-white text-xs font-semibold hover:bg-red-700"
                                            @click="deleteActivity(activity)"
                                        >
                                            Padam
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="activities.length === 0">
                                <td
                                    colspan="3"
                                    class="border border-slate-200 dark:border-slate-700 px-3 py-6 text-center text-sm text-slate-600 dark:text-slate-400"
                                >
                                    Tiada aktiviti ditemui.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    
                <div
                v-if="showEditModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                @click.self="closeEditModal"
            >
                <div
                    class="w-full max-w-md rounded-xl bg-white dark:bg-slate-900 p-6 shadow-xl"
                >
                    <h3
                        class="text-lg font-semibold text-slate-900 dark:text-slate-100"
                    >
                        Kemaskini Aktiviti
                    </h3>

                    <div class="mt-4">
                        <label
                            class="mb-2 block text-sm font-medium text-slate-900 dark:text-slate-100"
                        >
                            Nama Aktiviti
                        </label>

                        <input
                            v-model="editActivityName"
                            type="text"
                            placeholder="Masukkan nama aktiviti"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                        />
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-lg bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-300 dark:bg-slate-700 dark:text-slate-100 dark:hover:bg-slate-600"
                            @click="closeEditModal"
                        >
                            Batal
                        </button>

                        <button
                            type="button"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                            :disabled="!editActivityName.trim()"
                            @click="submitEdit()"
                        >
                            Simpan
                        </button>
                    </div>
                </div>
            </div>
    </AppLayout>

    <div
    v-if="showAddModal"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
    @click.self="closeAddModal"
>
    <div
        class="w-full max-w-md rounded-xl bg-white dark:bg-slate-900 p-6 shadow-xl"
    >
        <h3
            class="text-lg font-semibold text-slate-900 dark:text-slate-100"
        >
            Tambah Aktiviti
        </h3>

        <div class="mt-4">
            <label
                class="mb-2 block text-sm font-medium text-slate-900 dark:text-slate-100"
            >
                Nama Aktiviti
            </label>

            <input
                v-model="activityName"
                type="text"
                placeholder="Masukkan nama aktiviti"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
            />
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <button
                type="button"
                class="rounded-lg bg-slate-200 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-300 dark:bg-slate-700 dark:text-slate-100 dark:hover:bg-slate-600"
                @click="closeAddModal"
            >
                Batal
            </button>

            <button
                type="button"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                :disabled="!activityName.trim()"
                @click="addActivity"
            >
                Tambah
            </button>
        </div>
    </div>
</div>
</template>