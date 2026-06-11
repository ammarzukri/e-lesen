<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Senarai Admin', href: '/super-admin/admins' },
];

const props = defineProps<{
    admins?: {
        id: number;
        name: string;
        email: string;
        role: string;
        district?: {
            id: number;
            district_name: string;
        };
    }[];
}>();

const admins = ref(props.admins ?? []);

const formatRole = (value: string) => {
    const role: Record<string, string> = {
        pbt_admin: 'Admin PBT',
        bkt_admin: 'Admin BKT',
        bendahara_admin: 'Admin Perbendaharaan',
        super_admin: 'Super Admin',
    }

    return role[value] ?? value
}

/* ---------------- CREATE MODAL ---------------- */
const showAddModal = ref(false);

const name = ref('');
const email = ref('');
const password = ref('');
const role = ref('pbt_clerk');

function openAddModal() {
    showAddModal.value = true;
}

function closeAddModal() {
    showAddModal.value = false;
    name.value = '';
    email.value = '';
    password.value = '';
    role.value = 'pbt_clerk';
}

function createAdmin() {
    router.post('/super-admin/admins', {
        name: name.value,
        email: email.value,
        password: password.value,
        role: role.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closeAddModal();
            router.reload();
        }
    });
}

/* ---------------- EDIT MODAL ---------------- */
const showEditModal = ref(false);

const editId = ref<number | null>(null);
const editName = ref('');
const editEmail = ref('');
const editRole = ref('');

function openEditModal(admin: any) {
    editId.value = admin.id;
    editName.value = admin.name;
    editEmail.value = admin.email;
    editRole.value = admin.role;

    showEditModal.value = true;
}

function closeEditModal() {
    showEditModal.value = false;

    editId.value = null;
    editName.value = '';
    editEmail.value = '';
    editRole.value = '';
}

function updateAdmin() {
    if (!editId.value) return;

    router.post(`/super-admin/admins/${editId.value}`, {
        _method: 'PATCH',
        name: editName.value,
        email: editEmail.value,
        role: editRole.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closeEditModal();
            router.reload();
        },
    });
}

/* ---------------- DELETE ---------------- */
function deleteAdmin(admin: { id: number }) {
    if (!confirm('Padam admin ini?')) return;

    router.post(`/super-admin/admins/${admin.id}`, {
        _method: 'DELETE',
    }, {
        preserveScroll: true,
        onSuccess: () => {
            admins.value = admins.value.filter(a => a.id !== admin.id);
        }
    });
}

watch(() => props.admins, (v) => {
    admins.value = v ?? [];
});
</script>

<template>
    <Head title="Admin Management" />

    <AppLayout :breadcrumbs="breadcrumbs">

        <div class="w-full h-full flex flex-col p-6 bg-white dark:bg-black rounded-xl shadow">

            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold">Senarai Admin</h2>
                    <p class="text-sm text-gray-500">Urus semua pentadbir sistem</p>
                </div>

                <button
                    @click="openAddModal"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700"
                >
                    + Tambah Admin
                </button>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full table-auto border-collapse text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-800">
                        <tr>
                            <th class="border border-gray-200 dark:border-gray-700 p-3 text-left">Bil</th>
                            <th class="border border-gray-200 dark:border-gray-700 p-3 text-left">Nama</th>
                            <th class="border border-gray-200 dark:border-gray-700 p-3 text-left">Email</th>
                            <th class="border border-gray-200 dark:border-gray-700 p-3 text-left">Peranan</th>
                            <th class="border border-gray-200 dark:border-gray-700 p-3 text-left">Daerah</th>
                            <th class="border border-gray-200 dark:border-gray-700 p-3 text-left">Tindakan</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="(admin, index) in admins" :key="admin.id">
                            <td class="border border-gray-200 dark:border-gray-700 p-3">{{ index + 1 }}</td>
                            <td class="border border-gray-200 dark:border-gray-700 p-3">{{ admin.name }}</td>
                            <td class="border border-gray-200 dark:border-gray-700 p-3">{{ admin.email }}</td>
                            <td class="border border-gray-200 dark:border-gray-700 p-3">{{ formatRole(admin.role) }}</td>
                            <td class="border border-gray-200 dark:border-gray-700 p-3">{{ admin.district?.district_name ?? '-' }}</td>

                            <td class="border border-gray-200 dark:border-gray-700 p-3">
                                <div class="flex gap-2">
                                    <button
                                        class="bg-yellow-500 text-white px-3 py-1 rounded-lg text-xs"
                                        @click="openEditModal(admin)"
                                    >
                                        Kemaskini
                                    </button>

                                    <button
                                        class="bg-red-600 text-white px-3 py-1 rounded-lg text-xs"
                                        @click="deleteAdmin(admin)"
                                    >
                                        Padam
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="admins.length === 0">
                            <td
                                colspan="6"
                                class="border border-gray-200 dark:border-gray-700 text-center p-6 text-gray-500"
                            >
                                Tiada pentadbir ditemui
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>

    <!-- CREATE MODAL -->
    <div
        v-if="showAddModal"
        class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4"
        @click.self="closeAddModal"
    >
        <div class="bg-white dark:bg-gray-900 p-6 rounded-xl w-full max-w-md">

            <h3 class="text-lg font-semibold mb-4">Tambah Admin</h3>

            <div class="space-y-4">

                <div>
                    <label
                        class="block text-sm font-medium mb-1 text-slate-900 dark:text-slate-100"
                    >
                        Nama
                    </label>
                    <input
                        v-model="name"
                        type="text"
                        placeholder="Masukkan nama"
                        class="w-full border p-2 rounded-lg"
                    />
                </div>

                <div>
                    <label
                        class="block text-sm font-medium mb-1 text-slate-900 dark:text-slate-100"
                    >
                        Emel
                    </label>
                    <input
                        v-model="email"
                        type="email"
                        placeholder="Masukkan emel"
                        class="w-full border p-2 rounded-lg"
                    />
                </div>

                <div>
                    <label
                        class="block text-sm font-medium mb-1 text-slate-900 dark:text-slate-100"
                    >
                        Kata Laluan
                    </label>
                    <input
                        v-model="password"
                        type="password"
                        placeholder="Biarkan kosong untuk jana automatik"
                        class="w-full border p-2 rounded-lg"
                    />
                </div>

                <div>
                    <label
                        class="block text-sm font-medium mb-1 text-slate-900 dark:text-slate-100"
                    >
                        Peranan
                    </label>
                    <select
                        v-model="role"
                        class="w-full border p-2 rounded-lg"
                    >
                        <option value="pbt_clerk">Kerani PBT</option>
                        <option value="pbt_license_officer">Pegawai Lesen PBT</option>
                        <option value="bkt_officer">Pegawai BKT</option>
                        <option value="accountant">Akauntan</option>
                        <option value="atd_atl_officer">Pegawai ATD/ATL</option>
                    </select>
                </div>

            </div>

            <div class="flex justify-end gap-2 mt-5">
                <button
                    class="px-4 py-2 bg-gray-300 rounded-lg"
                    @click="closeAddModal"
                >
                    Batal
                </button>
                
                <button
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg"
                    @click="createAdmin"
                >
                    Tambah
                </button>
            </div>

        </div>
    </div>

    <!-- EDIT MODAL -->
    <div
        v-if="showEditModal"
        class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4"
        @click.self="closeEditModal"
    >
        <div class="bg-white dark:bg-gray-900 p-6 rounded-xl w-full max-w-md">

            <h3 class="text-lg font-semibold mb-4">
                Kemaskini Admin
            </h3>

            <div class="space-y-4">

                <div>
                    <label class="block text-sm font-medium mb-1 text-slate-900 dark:text-slate-100">
                        Nama
                    </label>
                    <input
                        v-model="editName"
                        type="text"
                        class="w-full border p-2 rounded-lg"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1 text-slate-900 dark:text-slate-100">
                        Emel
                    </label>
                    <input
                        v-model="editEmail"
                        type="email"
                        class="w-full border p-2 rounded-lg"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1 text-slate-900 dark:text-slate-100">
                        Peranan
                    </label>
                    <select
                        v-model="editRole"
                        class="w-full border p-2 rounded-lg"
                    >
                        <option value="pbt_admin">Admin PBT</option>
                        <option value="bkt_admin">Admin BKT</option>
                        <option value="bendahara_admin">Admin Perbendaharaan</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>

            </div>

            <div class="flex justify-end gap-2 mt-5">
                <button
                    class="px-4 py-2 bg-gray-300 rounded-lg"
                    @click="closeEditModal"
                >
                    Batal
                </button>

                <button
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg"
                    @click="updateAdmin"
                >
                    Simpan
                </button>
            </div>

        </div>
    </div>
</template>