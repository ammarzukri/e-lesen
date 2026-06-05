<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import FiSejahteraNavbar from '@/components/fi-sejahtera/FiSejahteraNavbar.vue';
import FiSejahteraSidebar from '@/components/fi-sejahtera/FiSejahteraSidebar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type AppPageProps } from '@/types';

interface HotelOption {
    id: number;
    name: string;
}

interface StaffUser {
    id?: number;
    name?: string;
    email?: string;
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
}

interface StaffRow {
    id: number;
    hotel_id: number;
    position: string;
    user: StaffUser;
}

interface StaffFilters {
    search?: string;
    hotel_id?: string;
}

const page = usePage<AppPageProps<{ staff: StaffRow[]; hasHotel: boolean; hotels: HotelOption[]; filters: StaffFilters; success?: string }>>();

const hotels = computed<HotelOption[]>(() => ((page.props as any).hotels ?? []) as HotelOption[]);
const search = ref<string>((page.props as any).filters?.search ?? '');
const selectedHotelId = ref<string>((page.props as any).filters?.hotel_id ?? '');

const hotelNameMap = computed(() => {
    return new Map(hotels.value.map((hotel) => [hotel.id, hotel.name]));
});

const isAddDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const editingStaff = ref<StaffRow | null>(null);

const malaysiaStates = [
    'Johor',
    'Kedah',
    'Kelantan',
    'Melaka',
    'Negeri Sembilan',
    'Pahang',
    'Perak',
    'Perlis',
    'Pulau Pinang',
    'Sabah',
    'Sarawak',
    'Selangor',
    'Terengganu',
    'Wilayah Persekutuan Kuala Lumpur',
    'Wilayah Persekutuan Putrajaya',
    'Wilayah Persekutuan Labuan',
];

const baseGenderOptions = [
    { value: 'lelaki', label: 'Lelaki' },
    { value: 'perempuan', label: 'Perempuan' },
];

const districtMap: Record<string, string[]> = {
    Johor: ['Batu Pahat', 'Johor Bahru', 'Kluang', 'Kota Tinggi', 'Mersing', 'Muar', 'Pontian', 'Segamat', 'Tangkak'],
    Kedah: ['Baling', 'Bandar Baharu', 'Kota Setar', 'Kuala Muda', 'Kubang Pasu', 'Kulim', 'Langkawi', 'Padang Terap', 'Pendang', 'Pokok Sena', 'Sik', 'Yan'],
    Kelantan: ['Kota Bharu', 'Bachok', 'Pasir Mas', 'Tanah Merah', 'Tumpat', 'Kuala Krai', 'Machang', 'Gua Musang', 'Jeli'],
    Melaka: ['Alor Gajah', 'Jasin', 'Melaka Tengah'],
    'Negeri Sembilan': ['Jelebu', 'Jempol', 'Kuala Pilah', 'Port Dickson', 'Rembau', 'Seremban', 'Tampin'],
    Pahang: ['Bentong', 'Bera', 'Cameron Highlands', 'Jerang', 'Kuantan', 'Lipis', 'Maran', 'Pekan', 'Raub', 'Rompin', 'Temerloh'],
    Perak: ['Batang Padang', 'Hilir Perak', 'Hulu Perak', 'Kampar', 'Kerian', 'Kinta', 'Kuala Kangsar', 'Larut, Matang dan Selama', 'Manjung', 'Mualim', 'Perak Tengah', 'Bagan Datuk'],
    Perlis: ['Kangar'],
    'Pulau Pinang': ['Seberang Perai Utara', 'Seberang Perai Tengah', 'Seberang Perai Selatan', 'Timur Laut', 'Barat Daya'],
    Sabah: ['Beaufort', 'Beluran', 'Keningau', 'Kinabatangan', 'Kota Belud', 'Kota Kinabalu', 'Kota Marudu', 'Kuala Penyu', 'Kudat', 'Kunak', 'Lahad Datu', 'Nabawan', 'Papar', 'Penampang', 'Pitas', 'Putatan', 'Ranau', 'Sandakan', 'Semporna', 'Sipitang', 'Tambunan', 'Tawau', 'Telupid', 'Tenom', 'Tongod', 'Tuaran'],
    Sarawak: ['Asajaya', 'Bau', 'Belaga', 'Betong', 'Bintulu', 'Dalat', 'Daro', 'Julau', 'Kabong', 'Kanowit', 'Kapit', 'Kuching', 'Lawas', 'Limbang', 'Lubok Antu', 'Lundu', 'Marudi', 'Matu', 'Meradong', 'Miri', 'Mukah', 'Pakan', 'Samarahan', 'Saratok', 'Sarikei', 'Selangau', 'Serian', 'Sibu', 'Simunjan', 'Song', 'Sri Aman', 'Subis', 'Tatau', 'Tebedu', 'Telang Usan'],
    Selangor: ['Gombak', 'Hulu Langat', 'Hulu Selangor', 'Klang', 'Kuala Langat', 'Kuala Selangor', 'Petaling', 'Sabak Bernam', 'Sepang'],
    Terengganu: ['Besut', 'Dungun', 'Hulu Terengganu', 'Kemaman', 'Kuala Nerus', 'Kuala Terengganu', 'Marang'],
    'Wilayah Persekutuan Kuala Lumpur': ['Kuala Lumpur'],
    'Wilayah Persekutuan Putrajaya': ['Putrajaya'],
    'Wilayah Persekutuan Labuan': ['Labuan'],
};

const knownReligionOptions = ['Islam', 'Buddha', 'Hindu', 'Kristian'];
const knownEthnicityOptions = ['Melayu', 'Cina', 'India'];

const addReligionSelection = ref('');
const addCustomReligion = ref('');
const addEthnicitySelection = ref('');
const addCustomEthnicity = ref('');

const editReligionSelection = ref('');
const editCustomReligion = ref('');
const editEthnicitySelection = ref('');
const editCustomEthnicity = ref('');

const defaultHotelId = computed<string>(() => {
    const firstHotel = hotels.value[0];
    return firstHotel ? String(firstHotel.id) : '';
});

function createStaffFormPayload() {
    return {
        hotel_id: defaultHotelId.value,
        position: '',
        name: '',
        email: '',
        password: '',
        ic_no: '',
        birth_date: '',
        birth_place: '',
        gender: '',
        citizenship: '',
        religion: '',
        ethnicity: '',
        maritial_status: '',
        occupation: '',
        income: '',
        home_address: '',
        postcode: '',
        state: '',
        district: '',
        phone_number: '',
    };
}

const addForm = useForm(createStaffFormPayload());
const editForm = useForm(createStaffFormPayload());

const addDistrictOptions = computed(() => districtMap[addForm.state] ?? []);
const editDistrictOptions = computed(() => districtMap[editForm.state] ?? []);
const editBirthPlaceOptions = computed(() => {
    const currentValue = editForm.birth_place?.trim() ?? '';
    const hasCurrentValue = currentValue.length > 0;
    const existsInStates = malaysiaStates.includes(currentValue);

    if (!hasCurrentValue || existsInStates) {
        return malaysiaStates;
    }

    return [currentValue, ...malaysiaStates];
});

const editGenderOptions = computed(() => {
    const currentValue = editForm.gender?.trim() ?? '';
    const hasCurrentValue = currentValue.length > 0;
    const existsInOptions = baseGenderOptions.some((option) => option.value === currentValue);

    if (!hasCurrentValue || existsInOptions) {
        return baseGenderOptions;
    }

    return [
        { value: currentValue, label: currentValue },
        ...baseGenderOptions,
    ];
});

function normalizeGenderValue(gender?: string) {
    const normalized = (gender ?? '').trim().toLowerCase();

    if (normalized === 'lelaki' || normalized === 'male' || normalized === 'm') {
        return 'lelaki';
    }

    if (normalized === 'perempuan' || normalized === 'female' || normalized === 'f') {
        return 'perempuan';
    }

    return (gender ?? '').trim();
}

watch(
    () => addForm.state,
    (state) => {
        if (!districtMap[state]?.includes(addForm.district)) {
            addForm.district = '';
        }
    },
);

watch(
    () => editForm.state,
    (state) => {
        if (!districtMap[state]?.includes(editForm.district)) {
            editForm.district = '';
        }
    },
);

watch([addReligionSelection, addCustomReligion], ([selection, custom]) => {
    if (selection === 'Lain-lain') {
        addForm.religion = custom;
        return;
    }

    addForm.religion = selection;
    if (addCustomReligion.value) {
        addCustomReligion.value = '';
    }
});

watch([addEthnicitySelection, addCustomEthnicity], ([selection, custom]) => {
    if (selection === 'Lain-lain') {
        addForm.ethnicity = custom;
        return;
    }

    addForm.ethnicity = selection;
    if (addCustomEthnicity.value) {
        addCustomEthnicity.value = '';
    }
});

watch([editReligionSelection, editCustomReligion], ([selection, custom]) => {
    if (selection === 'Lain-lain') {
        editForm.religion = custom;
        return;
    }

    editForm.religion = selection;
    if (editCustomReligion.value) {
        editCustomReligion.value = '';
    }
});

watch([editEthnicitySelection, editCustomEthnicity], ([selection, custom]) => {
    if (selection === 'Lain-lain') {
        editForm.ethnicity = custom;
        return;
    }

    editForm.ethnicity = selection;
    if (editCustomEthnicity.value) {
        editCustomEthnicity.value = '';
    }
});

function openAddDialog() {
    addForm.reset();
    addForm.clearErrors();
    addForm.hotel_id = defaultHotelId.value;
    addReligionSelection.value = '';
    addCustomReligion.value = '';
    addEthnicitySelection.value = '';
    addCustomEthnicity.value = '';
    isAddDialogOpen.value = true;
}

function openEditDialog(staff: StaffRow) {
    editingStaff.value = staff;
    editForm.reset();
    editForm.clearErrors();

    editForm.hotel_id = String(staff.hotel_id ?? '');
    editForm.position = staff.position ?? '';
    editForm.name = staff.user?.name ?? '';
    editForm.email = staff.user?.email ?? '';
    editForm.password = '';
    editForm.ic_no = staff.user?.ic_no ?? '';
    editForm.birth_date = staff.user?.birth_date ?? '';
    editForm.birth_place = (staff.user?.birth_place ?? '').trim();
    editForm.gender = normalizeGenderValue(staff.user?.gender);
    editForm.citizenship = staff.user?.citizenship ?? '';
    editForm.religion = staff.user?.religion ?? '';
    editForm.ethnicity = staff.user?.ethnicity ?? '';
    editForm.maritial_status = staff.user?.maritial_status ?? '';
    editForm.occupation = staff.user?.occupation ?? '';
    editForm.income = staff.user?.income ?? '';
    editForm.home_address = staff.user?.home_address ?? '';
    editForm.postcode = staff.user?.postcode ?? '';
    editForm.state = staff.user?.state ?? '';
    editForm.district = staff.user?.district ?? '';
    editForm.phone_number = staff.user?.phone_number ?? '';

    if (editForm.religion && knownReligionOptions.includes(editForm.religion)) {
        editReligionSelection.value = editForm.religion;
        editCustomReligion.value = '';
    } else if (editForm.religion) {
        editReligionSelection.value = 'Lain-lain';
        editCustomReligion.value = editForm.religion;
    } else {
        editReligionSelection.value = '';
        editCustomReligion.value = '';
    }

    if (editForm.ethnicity && knownEthnicityOptions.includes(editForm.ethnicity)) {
        editEthnicitySelection.value = editForm.ethnicity;
        editCustomEthnicity.value = '';
    } else if (editForm.ethnicity) {
        editEthnicitySelection.value = 'Lain-lain';
        editCustomEthnicity.value = editForm.ethnicity;
    } else {
        editEthnicitySelection.value = '';
        editCustomEthnicity.value = '';
    }

    isEditDialogOpen.value = true;
}

function submitStaff() {
    addForm.post('/fi-sejahtera/staff', {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            addForm.hotel_id = defaultHotelId.value;
            isAddDialogOpen.value = false;
        },
    });
}

function submitEditStaff() {
    if (!editingStaff.value) {
        return;
    }

    editForm.patch(`/fi-sejahtera/staff/${editingStaff.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditDialogOpen.value = false;
            editingStaff.value = null;
        },
    });
}

function deleteStaff(staff: StaffRow) {
    if (!confirm(`Padam staf ${staff.user?.name ?? ''}?`)) {
        return;
    }

    router.delete(`/fi-sejahtera/staff/${staff.id}`, {
        preserveScroll: true,
    });
}

function applyFilters() {
    router.get(
        '/fi-sejahtera/staff',
        {
            search: search.value || undefined,
            hotel_id: selectedHotelId.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function resetFilters() {
    search.value = '';
    selectedHotelId.value = '';
    applyFilters();
}
</script>

<template>
    <Head title="Tetapan Staf" />

    <div class="flex min-h-screen bg-muted/30">
        <FiSejahteraSidebar />

        <div class="flex min-h-screen flex-1 flex-col">
            <FiSejahteraNavbar />

            <main class="flex-1 space-y-6 p-6">
                <div>
                    <h1 class="text-2xl font-bold text-foreground">Tetapan Staf</h1>
                    <p class="text-sm text-muted-foreground">Senarai staf hotel dan pendaftaran staf baharu.</p>
                </div>

                <div
                    v-if="(page.props as any).success"
                    class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-green-700"
                >
                    {{ (page.props as any).success }}
                </div>

                <Card v-if="!(page.props as any).hasHotel">
                    <CardContent class="pt-6 text-sm text-muted-foreground">
                        Hotel tidak ditemui untuk akaun ini. Lengkapkan data hotel terlebih dahulu di modul E-Lesen.
                    </CardContent>
                </Card>

                <template v-else>
                    <Card>
                        <CardHeader>
                            <CardTitle>Tapis Staf</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form class="grid grid-cols-1 gap-4 md:grid-cols-3" @submit.prevent="applyFilters">
                                <div class="md:col-span-2">
                                    <Label for="search_staff" class="mb-1">Carian</Label>
                                    <Input
                                        id="search_staff"
                                        v-model="search"
                                        placeholder="Cari nama, emel, no kad pengenalan, no telefon atau jawatan"
                                    />
                                </div>

                                <div>
                                    <Label for="staff_hotel_filter" class="mb-1">Rumah Tumpangan</Label>
                                    <select
                                        id="staff_hotel_filter"
                                        v-model="selectedHotelId"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">Semua Rumah Tumpangan</option>
                                        <option v-for="hotel in hotels" :key="hotel.id" :value="String(hotel.id)">
                                            {{ hotel.name }}
                                        </option>
                                    </select>
                                </div>

                                <div class="md:col-span-3 flex items-center gap-2">
                                    <Button type="submit">Cari</Button>
                                    <Button type="button" variant="outline" @click="resetFilters">Reset</Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0">
                            <CardTitle>Senarai Staf</CardTitle>
                            <Button type="button" @click="openAddDialog">Tambah Staf</Button>
                        </CardHeader>
                        <CardContent>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left">
                                    <thead>
                                        <tr class="text-sm text-muted-foreground">
                                            <th class="p-2">Bil.</th>
                                            <th class="p-2">Nama</th>
                                            <th class="p-2">Emel</th>
                                            <th class="p-2">Rumah Tumpangan</th>
                                            <th class="p-2">Jawatan</th>
                                            <th class="p-2">No Telefon</th>
                                            <th class="p-2">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(staff, index) in (page.props as any).staff"
                                            :key="staff.id"
                                            class="border-t"
                                            :class="Number(index) % 2 === 0 ? 'bg-background' : 'bg-muted'"
                                        >
                                            <td class="p-2">{{ Number(index) + 1 }}</td>
                                            <td class="p-2">{{ staff.user?.name || '-' }}</td>
                                            <td class="p-2">{{ staff.user?.email || '-' }}</td>
                                            <td class="p-2">{{ hotelNameMap.get(staff.hotel_id) || '-' }}</td>
                                            <td class="p-2">{{ staff.position || '-' }}</td>
                                            <td class="p-2">{{ staff.user?.phone_number || '-' }}</td>
                                            <td class="p-2">
                                                <div class="flex items-center gap-2">
                                                    <Button type="button" variant="outline" size="sm" @click="openEditDialog(staff)">
                                                        Edit
                                                    </Button>
                                                    <Button type="button" variant="destructive" size="sm" @click="deleteStaff(staff)">
                                                        Delete
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="((page.props as any).staff || []).length === 0" class="border-t">
                                            <td colspan="7" class="p-2 text-sm text-muted-foreground">
                                                Tiada staf didaftarkan.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <Dialog :open="isAddDialogOpen" @update:open="isAddDialogOpen = $event">
                        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-4xl">
                            <DialogHeader>
                                <DialogTitle>Tambah Staf</DialogTitle>
                                <DialogDescription>Lengkapkan maklumat staf baharu.</DialogDescription>
                            </DialogHeader>

                            <form class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-2 [&_input:not([type='radio'])]:mt-1 [&_select]:mt-1" @submit.prevent="submitStaff">
                                <div>
                                    <Label for="add_hotel_id">Nama Rumah Tumpangan</Label>
                                    <select
                                        id="add_hotel_id"
                                        v-model="addForm.hotel_id"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="" disabled>-- Pilih Rumah Tumpangan --</option>
                                        <option v-for="hotel in hotels" :key="hotel.id" :value="String(hotel.id)">
                                            {{ hotel.name }}
                                        </option>
                                    </select>
                                    <div v-if="addForm.errors.hotel_id" class="mt-1 text-xs text-red-600">{{ addForm.errors.hotel_id }}</div>
                                </div>

                                <div>
                                    <Label for="add_position">Jawatan</Label>
                                    <Input id="add_position" v-model="addForm.position" />
                                    <div v-if="addForm.errors.position" class="mt-1 text-xs text-red-600">{{ addForm.errors.position }}</div>
                                </div>

                                <div>
                                    <Label for="add_name">Nama</Label>
                                    <Input id="add_name" v-model="addForm.name" />
                                    <div v-if="addForm.errors.name" class="mt-1 text-xs text-red-600">{{ addForm.errors.name }}</div>
                                </div>

                                <div>
                                    <Label for="add_email">Emel</Label>
                                    <Input id="add_email" type="email" v-model="addForm.email" />
                                    <div v-if="addForm.errors.email" class="mt-1 text-xs text-red-600">{{ addForm.errors.email }}</div>
                                </div>

                                <div>
                                    <Label for="add_password">Kata Laluan</Label>
                                    <Input id="add_password" type="password" v-model="addForm.password" />
                                    <div v-if="addForm.errors.password" class="mt-1 text-xs text-red-600">{{ addForm.errors.password }}</div>
                                </div>

                                <div>
                                    <Label for="add_ic_no">No Kad Pengenalan</Label>
                                    <Input id="add_ic_no" v-model="addForm.ic_no" />
                                </div>

                                <div>
                                    <Label for="add_birth_date">Tarikh Lahir</Label>
                                    <Input id="add_birth_date" type="date" v-model="addForm.birth_date" />
                                </div>

                                <div>
                                    <Label for="add_birth_place">Tempat Lahir</Label>
                                    <select
                                        id="add_birth_place"
                                        v-model="addForm.birth_place"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">-- Pilih Negeri --</option>
                                        <option v-for="state in malaysiaStates" :key="`add-birth-${state}`" :value="state">{{ state }}</option>
                                    </select>
                                </div>

                                <div>
                                    <Label for="add_gender">Jantina</Label>
                                    <select
                                        id="add_gender"
                                        v-model="addForm.gender"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">-- Pilih Jantina --</option>
                                        <option value="lelaki">Lelaki</option>
                                        <option value="perempuan">Perempuan</option>
                                    </select>
                                </div>

                                <div>
                                    <Label for="add_citizenship">Kewarganegaraan</Label>
                                    <select
                                        id="add_citizenship"
                                        v-model="addForm.citizenship"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">-- Pilih Warganegara --</option>
                                        <option value="Warganegara">Warganegara</option>
                                        <option value="Bukan Warganegara">Bukan Warganegara</option>
                                    </select>
                                </div>

                                <div>
                                    <Label>Agama</Label>
                                    <div class="mt-2 space-y-2">
                                        <label v-for="option in knownReligionOptions" :key="`add-religion-${option}`" class="flex items-center gap-2 text-sm">
                                            <input type="radio" :value="option" v-model="addReligionSelection" />
                                            <span>{{ option }}</span>
                                        </label>
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="radio" value="Lain-lain" v-model="addReligionSelection" />
                                            <span>Lain-lain</span>
                                        </label>
                                        <Input v-if="addReligionSelection === 'Lain-lain'" v-model="addCustomReligion" placeholder="Sila nyatakan" />
                                    </div>
                                </div>

                                <div>
                                    <Label>Bangsa</Label>
                                    <div class="mt-2 space-y-2">
                                        <label v-for="option in knownEthnicityOptions" :key="`add-ethnicity-${option}`" class="flex items-center gap-2 text-sm">
                                            <input type="radio" :value="option" v-model="addEthnicitySelection" />
                                            <span>{{ option }}</span>
                                        </label>
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="radio" value="Lain-lain" v-model="addEthnicitySelection" />
                                            <span>Lain-lain</span>
                                        </label>
                                        <Input v-if="addEthnicitySelection === 'Lain-lain'" v-model="addCustomEthnicity" placeholder="Sila nyatakan" />
                                    </div>
                                </div>

                                <div>
                                    <Label for="add_maritial_status">Status Perkahwinan</Label>
                                    <select
                                        id="add_maritial_status"
                                        v-model="addForm.maritial_status"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">-- Pilih Status --</option>
                                        <option value="Bujang">Bujang</option>
                                        <option value="Berkahwin">Berkahwin</option>
                                        <option value="Duda">Duda</option>
                                        <option value="Janda">Janda</option>
                                        <option value="Balu">Balu</option>
                                    </select>
                                </div>

                                <div>
                                    <Label for="add_occupation">Pekerjaan</Label>
                                    <Input id="add_occupation" v-model="addForm.occupation" />
                                </div>

                                <div>
                                    <Label for="add_income">Pendapatan</Label>
                                    <Input id="add_income" v-model="addForm.income" />
                                </div>

                                <div class="md:col-span-2">
                                    <Label for="add_home_address">Alamat Rumah</Label>
                                    <Input id="add_home_address" v-model="addForm.home_address" />
                                </div>

                                <div>
                                    <Label for="add_postcode">Poskod</Label>
                                    <Input id="add_postcode" v-model="addForm.postcode" />
                                </div>

                                <div>
                                    <Label for="add_state">Negeri</Label>
                                    <select
                                        id="add_state"
                                        v-model="addForm.state"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">-- Pilih Negeri --</option>
                                        <option v-for="state in malaysiaStates" :key="`add-state-${state}`" :value="state">{{ state }}</option>
                                    </select>
                                </div>

                                <div>
                                    <Label for="add_district">Daerah</Label>
                                    <select
                                        id="add_district"
                                        v-model="addForm.district"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">-- Pilih Daerah --</option>
                                        <option v-for="district in addDistrictOptions" :key="`add-district-${district}`" :value="district">{{ district }}</option>
                                    </select>
                                </div>

                                <div>
                                    <Label for="add_phone_number">No Telefon</Label>
                                    <Input id="add_phone_number" v-model="addForm.phone_number" />
                                </div>

                                <DialogFooter class="md:col-span-2">
                                    <Button type="button" variant="outline" @click="isAddDialogOpen = false">Tutup</Button>
                                    <Button type="submit" :disabled="addForm.processing">
                                        {{ addForm.processing ? 'Menyimpan...' : 'Tambah Staf' }}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>

                    <Dialog :open="isEditDialogOpen" @update:open="isEditDialogOpen = $event">
                        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-4xl">
                            <DialogHeader>
                                <DialogTitle>Edit Staf</DialogTitle>
                                <DialogDescription>Kemas kini maklumat staf.</DialogDescription>
                            </DialogHeader>

                            <form class="mt-2 grid grid-cols-1 gap-4 md:grid-cols-2 [&_input:not([type='radio'])]:mt-1 [&_select]:mt-1" @submit.prevent="submitEditStaff">
                                <div>
                                    <Label for="edit_hotel_id">Nama Rumah Tumpangan</Label>
                                    <select
                                        id="edit_hotel_id"
                                        v-model="editForm.hotel_id"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="" disabled>-- Pilih Rumah Tumpangan --</option>
                                        <option v-for="hotel in hotels" :key="hotel.id" :value="String(hotel.id)">
                                            {{ hotel.name }}
                                        </option>
                                    </select>
                                    <div v-if="editForm.errors.hotel_id" class="mt-1 text-xs text-red-600">{{ editForm.errors.hotel_id }}</div>
                                </div>

                                <div>
                                    <Label for="edit_position">Jawatan</Label>
                                    <Input id="edit_position" v-model="editForm.position" />
                                    <div v-if="editForm.errors.position" class="mt-1 text-xs text-red-600">{{ editForm.errors.position }}</div>
                                </div>

                                <div>
                                    <Label for="edit_name">Nama</Label>
                                    <Input id="edit_name" v-model="editForm.name" />
                                    <div v-if="editForm.errors.name" class="mt-1 text-xs text-red-600">{{ editForm.errors.name }}</div>
                                </div>

                                <div>
                                    <Label for="edit_email">Emel</Label>
                                    <Input id="edit_email" type="email" v-model="editForm.email" />
                                    <div v-if="editForm.errors.email" class="mt-1 text-xs text-red-600">{{ editForm.errors.email }}</div>
                                </div>

                                <!-- <div>
                                    <Label for="edit_password">Kata Laluan Baru (Opsyenal)</Label>
                                    <Input id="edit_password" type="password" v-model="editForm.password" />
                                    <div v-if="editForm.errors.password" class="mt-1 text-xs text-red-600">{{ editForm.errors.password }}</div>
                                </div> -->

                                <div>
                                    <Label for="edit_ic_no">No Kad Pengenalan</Label>
                                    <Input id="edit_ic_no" v-model="editForm.ic_no" />
                                </div>

                                <div>
                                    <Label for="edit_birth_date">Tarikh Lahir</Label>
                                    <Input id="edit_birth_date" type="date" v-model="editForm.birth_date" />
                                </div>

                                <div>
                                    <Label for="edit_birth_place">Tempat Lahir</Label>
                                    <select
                                        id="edit_birth_place"
                                        v-model="editForm.birth_place"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">-- Pilih Negeri --</option>
                                        <option v-for="state in editBirthPlaceOptions" :key="`edit-birth-${state}`" :value="state">{{ state }}</option>
                                    </select>
                                </div>

                                <div>
                                    <Label for="edit_gender">Jantina</Label>
                                    <select
                                        id="edit_gender"
                                        v-model="editForm.gender"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">-- Pilih Jantina --</option>
                                        <option
                                            v-for="option in editGenderOptions"
                                            :key="`edit-gender-${option.value}`"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <Label>Bangsa</Label>
                                    <div class="mt-2 space-y-2">
                                        <label v-for="option in knownEthnicityOptions" :key="`edit-ethnicity-${option}`" class="flex items-center gap-2 text-sm">
                                            <input type="radio" :value="option" v-model="editEthnicitySelection" />
                                            <span>{{ option }}</span>
                                        </label>
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="radio" value="Lain-lain" v-model="editEthnicitySelection" />
                                            <span>Lain-lain</span>
                                        </label>
                                        <Input v-if="editEthnicitySelection === 'Lain-lain'" v-model="editCustomEthnicity" placeholder="Sila nyatakan" />
                                    </div>
                                </div>

                                <div>
                                    <Label>Agama</Label>
                                    <div class="mt-2 space-y-2">
                                        <label v-for="option in knownReligionOptions" :key="`edit-religion-${option}`" class="flex items-center gap-2 text-sm">
                                            <input type="radio" :value="option" v-model="editReligionSelection" />
                                            <span>{{ option }}</span>
                                        </label>
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="radio" value="Lain-lain" v-model="editReligionSelection" />
                                            <span>Lain-lain</span>
                                        </label>
                                        <Input v-if="editReligionSelection === 'Lain-lain'" v-model="editCustomReligion" placeholder="Sila nyatakan" />
                                    </div>
                                </div>

                                <div>
                                    <Label for="edit_citizenship">Kewarganegaraan</Label>
                                    <select
                                        id="edit_citizenship"
                                        v-model="editForm.citizenship"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">-- Pilih Warganegara --</option>
                                        <option value="Warganegara">Warganegara</option>
                                        <option value="Bukan Warganegara">Bukan Warganegara</option>
                                    </select>
                                </div>

                                <div>
                                    <Label for="edit_maritial_status">Status Perkahwinan</Label>
                                    <select
                                        id="edit_maritial_status"
                                        v-model="editForm.maritial_status"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">-- Pilih Status --</option>
                                        <option value="Bujang">Bujang</option>
                                        <option value="Berkahwin">Berkahwin</option>
                                        <option value="Duda">Duda</option>
                                        <option value="Janda">Janda</option>
                                        <option value="Balu">Balu</option>
                                    </select>
                                </div>

                                <div>
                                    <Label for="edit_occupation">Pekerjaan</Label>
                                    <Input id="edit_occupation" v-model="editForm.occupation" />
                                </div>

                                <div>
                                    <Label for="edit_income">Pendapatan</Label>
                                    <Input id="edit_income" v-model="editForm.income" />
                                </div>

                                <div class="md:col-span-2">
                                    <Label for="edit_home_address">Alamat Rumah</Label>
                                    <Input id="edit_home_address" v-model="editForm.home_address" />
                                </div>

                                <div>
                                    <Label for="edit_postcode">Poskod</Label>
                                    <Input id="edit_postcode" v-model="editForm.postcode" />
                                </div>

                                <div>
                                    <Label for="edit_state">Negeri</Label>
                                    <select
                                        id="edit_state"
                                        v-model="editForm.state"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">-- Pilih Negeri --</option>
                                        <option v-for="state in malaysiaStates" :key="`edit-state-${state}`" :value="state">{{ state }}</option>
                                    </select>
                                </div>

                                <div>
                                    <Label for="edit_district">Daerah</Label>
                                    <select
                                        id="edit_district"
                                        v-model="editForm.district"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">-- Pilih Daerah --</option>
                                        <option v-for="district in editDistrictOptions" :key="`edit-district-${district}`" :value="district">{{ district }}</option>
                                    </select>
                                </div>

                                <div>
                                    <Label for="edit_phone_number">No Telefon</Label>
                                    <Input id="edit_phone_number" v-model="editForm.phone_number" />
                                </div>

                                <DialogFooter class="md:col-span-2">
                                    <Button type="button" variant="outline" @click="isEditDialogOpen = false">Tutup</Button>
                                    <Button type="submit" :disabled="editForm.processing">
                                        {{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </template>
            </main>
        </div>
    </div>
</template>
