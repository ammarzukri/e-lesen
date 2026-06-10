<script setup lang="ts">
import { ref, watch, computed, onBeforeUnmount, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import { apply as licenseApply } from '@/routes/license'
import type { BreadcrumbItem } from '@/types'

interface ApplicantInfo {
  name: string
  ic_no: string
  birth_date: string
  birth_place: string
  gender: string
  ethnicity: string
  religion: string
  citizenship: string
  marital_status: string
  occupation: string
  income: string
  home_address: string
  postcode: string
  state: string
  district: string
  phone_number: string
  email: string
}

const props = defineProps<{
  currentUserId?: number | null
  currentUserDistrictId?: number | null
  districts?: Array<{
    id: number
    district_name: string
  }>
  initialApplicantInfo?: Partial<ApplicantInfo> | null
  processFeePayment?: {
    status?: string | null
    paid?: boolean | null
    amount?: number | null
    bill_code?: string | null
    paid_at?: string | null
  } | null
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: dashboard().url },
  { title: 'Permohonan Lesen', href: licenseApply().url },
]

const step = ref(1)
const isSubmitting = ref(false)
const submitSuccess = ref('')
const submitError = ref('')
let redirectTimeout: ReturnType<typeof setTimeout> | null = null

const stepTitles = [
  'Pilih PBT',
  'Maklumat Pemohon',
  'Maklumat Perniagaan / Syarikat',
  'Maklumat Tambahan',
  'Dokumen Sokongan',
  'Bayaran Fi Proses',
  'Pengesahan',
]

const rawProcessingFeeAmount = Number(props.processFeePayment?.amount ?? 10000)
const processingFeeAmountInCents = rawProcessingFeeAmount > 1000 ? rawProcessingFeeAmount : rawProcessingFeeAmount * 100
const processingFeeAmount = processingFeeAmountInCents / 100
const formattedProcessingFeeAmount = computed(() => processingFeeAmount.toFixed(2))
const processFeeStatus = ref<string>(props.processFeePayment?.status ?? (props.processFeePayment?.paid ? 'paid' : 'unpaid'))
const processFeeBillCode = ref<string>(props.processFeePayment?.bill_code ?? '')
const isProcessFeeLoading = ref(false)
const processFeeStatusMessage = ref('')
let processFeeStatusInterval: ReturnType<typeof setInterval> | null = null
let draftAutosaveTimeout: ReturnType<typeof setTimeout> | null = null
const LEGACY_LICENSE_APPLICATION_DRAFT_KEY = 'license-application-create-draft-v1'

const currentUserDraftKey = computed(() => {
  const userId = props.currentUserId
  return userId ? `license-application-create-draft-v1-user-${userId}` : LEGACY_LICENSE_APPLICATION_DRAFT_KEY
})

const availableDistricts = computed(() => props.districts ?? [])

const districtNameById = computed(() => new Map(availableDistricts.value.map((district) => [district.id, district.district_name])))

function getDistrictIdByName(districtName: string) {
  return availableDistricts.value.find((district) => district.district_name === districtName)?.id ?? null
}

const selectedDistrictName = computed(() => {
  return form.value.district_id === null
    ? ''
    : districtNameById.value.get(form.value.district_id) ?? ''
})

function goToStep(targetStep: number) {
  if (targetStep < 1 || targetStep > stepTitles.length) return
  step.value = targetStep
}

const districtLogoByName: Record<string, string> = {
  'Majlis Bandaraya Kuala Terengganu': '/images/mbkt.png',
  'Majlis Perbandaran Kemaman': '/images/mpk.png',
  'Majlis Perbandaran Dungun': '/images/mpd.png',
  'Majlis Daerah Besut': '/images/mdb.jpg',
  'Majlis Daerah Hulu Terengganu': '/images/mdht.png',
  'Majlis Daerah Marang': '/images/mdm.png',
  'Majlis Daerah Setiu': '/images/mds.jpg',
}

const categoryCards = computed(() => {
  return availableDistricts.value.map((district) => ({
    id: district.id,
    name: district.district_name,
    logo: districtLogoByName[district.district_name] ?? '/images/mbkt.png',
  }))
})

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
  'Wilayah Persekutuan Labuan',
  'Wilayah Persekutuan Kuala Lumpur',
  'Wilayah Persekutuan Putrajaya',
]

interface AdditionalActivityRateOption {
  id: number
  type_name: string
  min_area: number | null
  max_area: number | null
  amount: number | null
}

interface AdditionalActivityOption {
  id: number
  district_id: number
  activity_name: string
  rates: AdditionalActivityRateOption[]
}

interface AdditionalActivityRow {
  type_name: string
  rate_id: number | null
}

const additionalActivities = ref<AdditionalActivityOption[]>([])
const additionalActivitiesLoading = ref(false)
const additionalActivitiesMessage = ref('')
const isRestoringFormDraft = ref(true)
const hasLoadedAdditionalActivities = ref(false)

const activityById = computed(() => {
  return new Map(additionalActivities.value.map((activity) => [activity.id, activity]))
})

const selectedAdditionalActivities = computed(() => {
  return form.value.advertisment_info.selected_activity_ids
    .map((activityId) => activityById.value.get(activityId))
    .filter((activity): activity is AdditionalActivityOption => Boolean(activity))
})

function syncSelectedAdditionalActivitiesWhenReady() {
  if (isRestoringFormDraft.value || !hasLoadedAdditionalActivities.value) {
    return
  }

  syncSelectedAdditionalActivitiesWithLoadedOptions()
}

function createEmptyActivityRow(): AdditionalActivityRow {
  return {
    type_name: '',
    rate_id: null,
  }
}

function getActivityRows(activityId: number): AdditionalActivityRow[] {
  return form.value.advertisment_info.activity_rows[activityId] ?? []
}

function getJenisOptions(activity: AdditionalActivityOption): string[] {
  return Array.from(new Set(activity.rates.map((rate) => rate.type_name).filter(Boolean)))
}

function getKeluasanOptions(activity: AdditionalActivityOption, row: AdditionalActivityRow): AdditionalActivityRateOption[] {
  if (!row.type_name) {
    return []
  }

  return activity.rates.filter((rate) => rate.type_name === row.type_name)
}

function getRowRate(activity: AdditionalActivityOption, row: AdditionalActivityRow): AdditionalActivityRateOption | null {
  if (!row.rate_id) {
    return null
  }

  return activity.rates.find((rate) => rate.id === row.rate_id) ?? null
}

function getSelectedRowsForActivity(activity: AdditionalActivityOption) {
  return getActivityRows(activity.id)
    .map((row) => ({
      row,
      rate: getRowRate(activity, row),
    }))
    .filter((item): item is { row: AdditionalActivityRow; rate: AdditionalActivityRateOption } => Boolean(item.rate))
}

function onRowJenisChange(activity: AdditionalActivityOption, row: AdditionalActivityRow) {
  const selectedRate = getRowRate(activity, row)

  if (selectedRate && selectedRate.type_name === row.type_name) {
    return
  }

  row.rate_id = null
}

function addActivityRow(activityId: number) {
  const rows = getActivityRows(activityId)

  rows.push(createEmptyActivityRow())
  form.value.advertisment_info.activity_rows[activityId] = rows
}

function removeActivityRow(activityId: number, index: number) {
  const rows = getActivityRows(activityId)

  if (rows.length <= 1) {
    return
  }

  rows.splice(index, 1)
  form.value.advertisment_info.activity_rows[activityId] = rows
}

function formatAreaRange(minArea: number | null, maxArea: number | null) {
  if (minArea === null && maxArea === null) {
    return '-'
  }

  if (maxArea === null) {
    return `${formatAmount(minArea ?? 0)} ke atas`
  }

  return `${formatAmount(minArea ?? 0)} - ${formatAmount(maxArea)}`
}

function formatAmount(value: number) {
  return new Intl.NumberFormat('ms-MY', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value)
}

const additionalActivitiesTotalAmount = computed(() => {
  return selectedAdditionalActivities.value.reduce((total, activity) => {
    const rowsTotal = getSelectedRowsForActivity(activity).reduce((rowTotal, item) => {
      return rowTotal + Number(item.rate.amount ?? 0)
    }, 0)

    return total + rowsTotal
  }, 0)
})

const districtMap: Record<string, string[]> = {
  Johor: ['Batu Pahat', 'Johor Bahru', 'Kluang', 'Kota Tinggi', 'Kulai', 'Mersing', 'Muar', 'Pontian', 'Segamat', 'Tangkak'],
  Kedah: ['Baling', 'Bandar Baharu', 'Kota Setar', 'Kuala Muda', 'Kubang Pasu', 'Kulim', 'Langkawi', 'Padang Terap', 'Pendang', 'Pokok Sena', 'Sik', 'Yan'],
  Kelantan: ['Bachok', 'Gua Musang', 'Jeli', 'Kecil Lojing', 'Kota Bharu', 'Kuala Krai', 'Machang', 'Pasir Mas', 'Pasir Puteh', 'Tanah Merah', 'Tumpat'],
  Melaka: ['Alor Gajah', 'Jasin', 'Melaka Tengah'],
  'Negeri Sembilan': ['Jelebu', 'Jempol', 'Kuala Pilah', 'Port Dickson', 'Rembau', 'Seremban', 'Tampin'],
  Pahang: ['Bentong', 'Bera', 'Cameron Highlands', 'Jerang', 'Jerantut', 'Kuantan', 'Lipis', 'Maran', 'Pekan', 'Raub', 'Rompin', 'Temerloh'],
  Perak: ['Bagan Datuk', 'Batang Padang', 'Hilir Perak', 'Hulu Perak', 'Kampar', 'Kerian', 'Kinta', 'Kuala Kangsar', 'Larut dan Matang', 'Manjung', 'Mualim', 'Perak Tengah', 'Selama'],
  Perlis: ['Kangar'],
  'Pulau Pinang': ['Barat Daya', 'Seberang Perai Selatan', 'Seberang Perai Tengah', 'Seberang Perai Utara', 'Timur Laut'],
  Sabah: ['Beaufort', 'Beluran', 'Kalabakan', 'Keningau', 'Kinabatangan',  'Kota Belud', 'Kota Kinabalu', 'Kota Marudu', 'Kuala Penyu', 'Kudat', 'Kunak', 'Lahad Datu', 'Nabawan', 'Papar', 'Penampang', 'Pitas', 'Putatan', 'Ranau', 'Sandakan', 'Semporna', 'Sipitang', 'Tambunan', 'Tawau', 'Telupid', 'Tenom', 'Tongod', 'Tuaran'],
  Sarawak: ['Asajaya', 'Bau', 'Belaga', 'Beluru', 'Betong', 'Bintulu', 'Bukit Mabong', 'Dalat', 'Daro', 'Julau', 'Kabong', 'Kanowit', 'Kapit', 'Kuching', 'Lawas', 'Limbang', 'Lubok Antu', 'Lundu', 'Maradong', 'Marudi', 'Matu', 'Meradong', 'Miri', 'Mukah', 'Pakan', 'Pusa', 'Samarahan', 'Saratok', 'Sarikei', 'Sebauh', 'Selangau', 'Serian', 'Sibu', 'Simunjan', 'Song', 'Sri Aman', 'Subis', 'Tanjung Manis', 'Tatau', 'Tebedu', 'Telang Usan'],
  Selangor: ['Gombak', 'Klang', 'Kuala Langat', 'Kuala Selangor', 'Petaling', 'Sabak Bernam', 'Sepang', 'Ulu Langat', 'Ulu Selangor'],
  Terengganu: ['Besut', 'Dungun', 'Hulu Terengganu', 'Kemaman', 'Kuala Nerus', 'Kuala Terengganu', 'Marang', 'Setiu'],
  'Wilayah Persekutuan Labuan': ['Labuan'],
  'Wilayah Persekutuan Kuala Lumpur': ['Kuala Lumpur'],
  'Wilayah Persekutuan Putrajaya': ['Putrajaya'],
}


const form = ref({
  district_id: props.currentUserDistrictId ?? null,
  applicant_info: {
    name: '',
    ic_no: '',
    birth_date: '',
    birth_place: '',
    gender: '',
    ethnicity: '',
    religion: '',
    citizenship: '',
    marital_status: '',
    occupation: '',
    income: '',
    home_address: '',
    postcode: '',
    state: '',
    district: '',
    phone_number: '',
    email: '',
  },
  company_info: {
    name: '',
    ic: '',
    company_name: '',
    hotel_name: '',
    company_address: '',
    company_postcode: '',
    company_state: 'Terengganu',
    company_district: '',
    company_phone: '',
    company_registration_number: '',
    company_registration_date: '',
    company_registration_expiry_date: '',
    company_category: '',
    company_premises_location: '',
    license_type_selected: '',
    room_count: '',
    employee_malay: '',
    employee_chinese: '',
    employee_indian: '',
    employee_others: '',
    company_operation_start: '',
    company_operation_end: '',
    company_address_hq: '',
    company_postcode_hq: '',
    company_state_hq: '',
    company_district_hq: '',
    company_phone_hq: '',
    company_license_type: '',
    company_license_i: '',
    company_license_ii: '',
    company_license_iii: '',
  },
  advertisment_info: {
    address: '',
    selected_activity_ids: [] as number[],
    activity_rows: {} as Record<number, AdditionalActivityRow[]>,
  },
  declaration: {
    agree: false,
  },
  processing_fee_paid: Boolean(props.processFeePayment?.paid),
  document1File: null as File | null,
  document1Name: '',
  document2File: null as File | null,
  document2Name: '',
  document3File: null as File | null,
  document3Name: '',
  document4File: null as File | null,
  document4Name: '',
  document5File: null as File | null,
  document5Name: '',
  document6File: null as File | null,
  document6Name: '',
  document7File: null as File | null,
  document7Name: '',
  document8File: null as File | null,
  document8Name: '',
  document9File: null as File | null,
  document9Name: '',
  document10File: null as File | null,
  document10Name: '',
})

const knownReligionOptions = ['Islam', 'Buddha', 'Hindu', 'Kristian']
const knownEthnicityOptions = ['Melayu', 'Cina', 'India']

const isHydratingApplicantInfo = ref(true)

function hydrateApplicantInfoFromDatabase() {
  const initial = props.initialApplicantInfo ?? {}

  Object.assign(form.value.applicant_info, {
    ...form.value.applicant_info,
    name: initial.name ?? '',
    ic_no: initial.ic_no ?? '',
    birth_date: initial.birth_date ?? '',
    birth_place: initial.birth_place ?? '',
    gender: initial.gender ?? '',
    ethnicity: initial.ethnicity ?? '',
    religion: initial.religion ?? '',
    citizenship: initial.citizenship ?? '',
    marital_status: initial.marital_status ?? '',
    occupation: initial.occupation ?? '',
    income: initial.income ?? '',
    home_address: initial.home_address ?? '',
    postcode: initial.postcode ?? '',
    state: initial.state ?? '',
    district: initial.district ?? '',
    phone_number: initial.phone_number ?? '',
    email: initial.email ?? '',
  })
}

hydrateApplicantInfoFromDatabase()

const religionSelection = ref('')
const customReligion = ref('')
const ethnicitySelection = ref('')
const customEthnicity = ref('')

const initialReligion = form.value.applicant_info.religion
if (initialReligion) {
  if (knownReligionOptions.includes(initialReligion)) {
    religionSelection.value = initialReligion
  } else {
    religionSelection.value = 'Lain-lain'
    customReligion.value = initialReligion
  }
}

const initialEthnicity = form.value.applicant_info.ethnicity
if (initialEthnicity) {
  if (knownEthnicityOptions.includes(initialEthnicity)) {
    ethnicitySelection.value = initialEthnicity
  } else {
    ethnicitySelection.value = 'Lain-lain'
    customEthnicity.value = initialEthnicity
  }
}

watch([religionSelection, customReligion], ([selection, custom]) => {
  if (selection === 'Lain-lain') {
    form.value.applicant_info.religion = custom
  } else {
    form.value.applicant_info.religion = selection
    if (customReligion.value) customReligion.value = ''
  }
})

watch([ethnicitySelection, customEthnicity], ([selection, custom]) => {
  if (selection === 'Lain-lain') {
    form.value.applicant_info.ethnicity = custom
  } else {
    form.value.applicant_info.ethnicity = selection
    if (customEthnicity.value) customEthnicity.value = ''
  }
})

const applicantDistrictOptions = computed(() => districtMap[form.value.applicant_info.state] || [])
const companyDistrictOptions = computed(() => districtMap.Terengganu || [])
const companyHqDistrictOptions = computed(() => districtMap[form.value.company_info.company_state_hq] || [])

watch(() => form.value.applicant_info.state, (state) => {
  if (!districtMap[state]?.includes(form.value.applicant_info.district)) {
    form.value.applicant_info.district = ''
  }
})

watch(() => form.value.company_info.company_state, (state) => {
  if (state !== 'Terengganu') {
    form.value.company_info.company_state = 'Terengganu'
  }

  if (!districtMap.Terengganu.includes(form.value.company_info.company_district)) {
    form.value.company_info.company_district = ''
  }
})

watch(() => form.value.company_info.company_state_hq, (state) => {
  if (!districtMap[state]?.includes(form.value.company_info.company_district_hq)) {
    form.value.company_info.company_district_hq = ''
  }
})

watch(
  () => form.value.district_id,
  (districtId) => {
    void fetchAdditionalActivitiesByDistrict(districtId)
  },
  { immediate: true },
)

watch(
  () => form.value.advertisment_info.selected_activity_ids,
  () => {
    syncSelectedAdditionalActivitiesWhenReady()
  },
  { deep: true },
)

let applicantAutosaveTimeout: ReturnType<typeof setTimeout> | null = null
let applicantAutosaveController: AbortController | null = null

function getCsrfToken() {
  const token = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null
  return token?.content ?? ''
}

function createFormDraftSnapshot() {
  return {
    ...form.value,
    document1File: null,
    document2File: null,
    document3File: null,
    document4File: null,
    document5File: null,
    document6File: null,
    document7File: null,
    document8File: null,
    document9File: null,
    document10File: null,
  }
}

function persistFormDraft() {
  try {
    const payload = {
      userId: props.currentUserId ?? null,
      step: step.value,
      form: createFormDraftSnapshot(),
      processFeeStatus: processFeeStatus.value,
      processFeeBillCode: processFeeBillCode.value,
      updatedAt: new Date().toISOString(),
    }

    localStorage.setItem(currentUserDraftKey.value, JSON.stringify(payload))
  } catch (error) {
    console.error('Failed to save license application draft', error)
  }
}

function restoreFormDraft() {
  try {
    const raw = localStorage.getItem(currentUserDraftKey.value)
    if (!raw) return

    const draft = JSON.parse(raw) as {
      userId?: number | null
      step?: number
      form?: Record<string, unknown>
      processFeeStatus?: string
      processFeeBillCode?: string
    }

    if ((draft.userId ?? null) !== (props.currentUserId ?? null)) {
      localStorage.removeItem(currentUserDraftKey.value)
      return
    }

    if (draft.form) {
      const draftForm = draft.form as typeof form.value
      const legacyDraftForm = draft.form as typeof form.value & { pbt_name?: string }

      if (draftForm.district_id == null && legacyDraftForm.pbt_name) {
        draftForm.district_id = getDistrictIdByName(legacyDraftForm.pbt_name)
      }

      Object.assign(form.value, {
        ...draftForm,
        applicant_info: {
          ...form.value.applicant_info,
          ...(draftForm.applicant_info ?? {}),
        },
        company_info: {
          ...form.value.company_info,
          ...(draftForm.company_info ?? {}),
        },
        advertisment_info: {
          ...form.value.advertisment_info,
          ...(draftForm.advertisment_info ?? {}),
        },
        declaration: {
          ...form.value.declaration,
          ...(draftForm.declaration ?? {}),
        },
      })
    }

    if (typeof draft.processFeeStatus === 'string') {
      processFeeStatus.value = draft.processFeeStatus
    }

    if (typeof draft.processFeeBillCode === 'string') {
      processFeeBillCode.value = draft.processFeeBillCode
    }

    const returnFromProcessFeePayment = new URLSearchParams(window.location.search).get('process_fee_return') === '1'

    if (returnFromProcessFeePayment) {
      step.value = 6
    } else {
      step.value = 1
    }

    isRestoringFormDraft.value = false

    if (hasLoadedAdditionalActivities.value) {
      syncSelectedAdditionalActivitiesWithLoadedOptions()
    }
  } catch (error) {
    console.error('Failed to restore license application draft', error)
    isRestoringFormDraft.value = false
  }
}

function clearFormDraft() {
  try {
    localStorage.removeItem(currentUserDraftKey.value)
  } catch (error) {
    console.error('Failed to clear license application draft', error)
  }
}

function syncSelectedAdditionalActivitiesWithLoadedOptions() {
  const validActivityIds = new Set(additionalActivities.value.map((activity) => activity.id))

  const filteredActivityIds = form.value.advertisment_info.selected_activity_ids
    .filter((activityId) => validActivityIds.has(activityId))

  if (filteredActivityIds.length !== form.value.advertisment_info.selected_activity_ids.length) {
    form.value.advertisment_info.selected_activity_ids = filteredActivityIds
  }

  const sanitizedRows: Record<number, AdditionalActivityRow[]> = {}

  for (const activityId of filteredActivityIds) {
    const activity = activityById.value.get(activityId)

    if (!activity) {
      continue
    }

    const validRateIds = new Set(activity.rates.map((rate) => rate.id))
    const existingRows = form.value.advertisment_info.activity_rows[activityId] ?? []

    const normalizedRows = existingRows.map((row) => {
      const nextRow: AdditionalActivityRow = {
        type_name: String(row?.type_name ?? ''),
        rate_id: typeof row?.rate_id === 'number' ? row.rate_id : null,
      }

      if (nextRow.rate_id !== null && !validRateIds.has(nextRow.rate_id)) {
        nextRow.rate_id = null
      }

      if (nextRow.rate_id !== null) {
        const selectedRate = activity.rates.find((rate) => rate.id === nextRow.rate_id) ?? null

        if (!selectedRate) {
          nextRow.rate_id = null
        } else if (nextRow.type_name && nextRow.type_name !== selectedRate.type_name) {
          nextRow.rate_id = null
        } else if (!nextRow.type_name) {
          nextRow.type_name = selectedRate.type_name
        }
      }

      return nextRow
    })

    sanitizedRows[activityId] = normalizedRows.length > 0
      ? normalizedRows
      : [createEmptyActivityRow()]
  }

  form.value.advertisment_info.activity_rows = sanitizedRows
}

async function fetchAdditionalActivitiesByDistrict(districtId: number | null) {
  additionalActivitiesMessage.value = ''

  if (!districtId) {
    additionalActivities.value = []
    form.value.advertisment_info.selected_activity_ids = []
    form.value.advertisment_info.activity_rows = {}
    return
  }

  additionalActivitiesLoading.value = true

  try {
    const response = await fetch(`/license/additional-activities?district_id=${districtId}`, {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
      },
    })

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`)
    }

    const payload = await response.json()

    additionalActivities.value = Array.isArray(payload?.activities)
      ? payload.activities
      : []
    hasLoadedAdditionalActivities.value = true

    syncSelectedAdditionalActivitiesWhenReady()
  } catch (error) {
    additionalActivities.value = []
    form.value.advertisment_info.selected_activity_ids = []
    form.value.advertisment_info.activity_rows = {}
    additionalActivitiesMessage.value = 'Senarai aktiviti tambahan tidak dapat dimuatkan sekarang.'
    console.error('Failed to load additional activities by district', error)
  } finally {
    additionalActivitiesLoading.value = false
  }
}

async function persistApplicantInfo() {
  const applicantInfoSnapshot = { ...form.value.applicant_info }

  if (applicantAutosaveController) {
    applicantAutosaveController.abort()
  }

  applicantAutosaveController = new AbortController()

  try {
    const response = await fetch('/license/applicant-info', {
      method: 'PATCH',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: JSON.stringify({
        applicant_info: applicantInfoSnapshot,
      }),
      signal: applicantAutosaveController.signal,
    })

    if (!response.ok) {
      throw new Error(`Applicant autosave failed with status ${response.status}`)
    }
  } catch (error) {
    if ((error as Error).name !== 'AbortError') {
      console.error('Applicant info autosave failed', error)
    }
  }
}

watch(
  () => form.value.applicant_info,
  () => {
    if (isHydratingApplicantInfo.value) {
      return
    }

    if (applicantAutosaveTimeout) {
      clearTimeout(applicantAutosaveTimeout)
    }

    applicantAutosaveTimeout = setTimeout(() => {
      void persistApplicantInfo()
    }, 700)
  },
  { deep: true },
)

isHydratingApplicantInfo.value = false

onBeforeUnmount(() => {
  if (redirectTimeout) {
    clearTimeout(redirectTimeout)
  }

  if (applicantAutosaveTimeout) {
    clearTimeout(applicantAutosaveTimeout)
  }

  if (applicantAutosaveController) {
    applicantAutosaveController.abort()
  }

  if (processFeeStatusInterval) {
    clearInterval(processFeeStatusInterval)
    processFeeStatusInterval = null
  }

  if (draftAutosaveTimeout) {
    clearTimeout(draftAutosaveTimeout)
    draftAutosaveTimeout = null
  }
})

function updateProcessFeeState(payload: { status?: string; paid?: boolean; bill_code?: string | null }) {
  if (payload.status) {
    processFeeStatus.value = payload.status
  }

  if (typeof payload.paid === 'boolean') {
    form.value.processing_fee_paid = payload.paid
  }

  if (payload.bill_code !== undefined && payload.bill_code !== null) {
    processFeeBillCode.value = payload.bill_code
  }
}

async function refreshProcessFeeStatus() {
  if (isProcessFeeLoading.value) return

  isProcessFeeLoading.value = true
  processFeeStatusMessage.value = ''

  try {
    const response = await fetch('/license/process-fee/status', {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
      },
    })

    if (!response.ok) {
      throw new Error(`Semakan status bayaran gagal (HTTP ${response.status})`)
    }

    const payload = await response.json()
    updateProcessFeeState(payload)

    if (payload.paid) {
      processFeeStatusMessage.value = 'Bayaran berjaya disahkan secara automatik.'
    }
  } catch (error) {
    processFeeStatusMessage.value = 'Tidak dapat menyemak status bayaran sekarang. Sila cuba semula.'
    console.error('Process fee status sync failed', error)
  } finally {
    isProcessFeeLoading.value = false
  }
}

async function startProcessFeePayment() {
  if (isProcessFeeLoading.value || form.value.processing_fee_paid) return

  isProcessFeeLoading.value = true
  processFeeStatusMessage.value = ''

  try {
    const billPhone = (form.value.applicant_info.phone_number || '').trim()
    const startUrl = billPhone
      ? `/license/process-fee/start?bill_phone=${encodeURIComponent(billPhone)}`
      : '/license/process-fee/start'

    const response = await fetch(startUrl, {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
      },
    })

    const payload = await response.json().catch(() => ({}))

    if (!response.ok) {
      processFeeStatusMessage.value = payload?.message || 'Gagal memulakan bayaran. Sila cuba semula.'
      return
    }

    updateProcessFeeState(payload)

    if (payload.paid) {
      processFeeStatusMessage.value = 'Bayaran telah direkodkan.'
      return
    }

    if (payload.payment_url) {
      persistFormDraft()
      window.location.assign(payload.payment_url)
      return
    }

    processFeeStatusMessage.value = 'URL bayaran tidak dijumpai. Sila cuba semula.'
  } catch (error) {
    processFeeStatusMessage.value = 'Tidak dapat menghubungi pelayan bayaran. Sila cuba semula.'
    console.error('Start process fee payment failed', error)
  } finally {
    isProcessFeeLoading.value = false
  }
}

watch(
  () => step.value,
  (currentStep) => {
    if (currentStep === 6 && processFeeStatus.value === 'pending' && !form.value.processing_fee_paid) {
      void refreshProcessFeeStatus()

      if (!processFeeStatusInterval) {
        processFeeStatusInterval = setInterval(() => {
          void refreshProcessFeeStatus()
        }, 5000)
      }

      return
    }

    if (processFeeStatusInterval) {
      clearInterval(processFeeStatusInterval)
      processFeeStatusInterval = null
    }
  },
  { immediate: true },
)

watch(
  () => form.value.processing_fee_paid,
  (paid) => {
    if (paid && processFeeStatusInterval) {
      clearInterval(processFeeStatusInterval)
      processFeeStatusInterval = null
    }
  },
)

watch(
  [() => form.value, () => step.value, processFeeBillCode, processFeeStatus],
  () => {
    if (draftAutosaveTimeout) {
      clearTimeout(draftAutosaveTimeout)
    }

    draftAutosaveTimeout = setTimeout(() => {
      persistFormDraft()
    }, 400)
  },
  { deep: true },
)

onMounted(() => {
  // Cleanup pre-user-scoped draft key from older implementation.
  if (LEGACY_LICENSE_APPLICATION_DRAFT_KEY !== currentUserDraftKey.value) {
    localStorage.removeItem(LEGACY_LICENSE_APPLICATION_DRAFT_KEY)
  }

  restoreFormDraft()

  if (props.processFeePayment?.paid) {
    form.value.processing_fee_paid = true
    processFeeStatus.value = 'paid'
  }

  if (props.processFeePayment?.bill_code) {
    processFeeBillCode.value = props.processFeePayment.bill_code
  }

  if (!form.value.processing_fee_paid && processFeeStatus.value === 'pending') {
    void refreshProcessFeeStatus()
  }
})

function nextStep() {
  // Allow progressing through steps without completing fields
  if (step.value < stepTitles.length) step.value++
}

function prevStep() {
  if (step.value > 1) step.value--
}

function submitForm() {
  submitSuccess.value = ''
  submitError.value = ''

  if (!form.value.processing_fee_paid) {
    submitError.value = `Sila buat bayaran fi proses RM${formattedProcessingFeeAmount.value} sebelum menghantar permohonan.`
    step.value = 6
    return
  }

  const documentTypes = [
    'memorandum',
    'pelan_lokasi',
    'pelan_lantai',
    'surat_perjanjian',
    'salinan_geran_tanah',
    'sijil_menduduki_bangunan',
    'gambar_pemohon',
    'salinan_kad_pengenalan_pemohon',
    'senarai_nama_semua_pengendali_makanan',
    'carta_proses_pengeluaran',
  ]

  const documentFiles = [
    form.value.document1File,
    form.value.document2File,
    form.value.document3File,
    form.value.document4File,
    form.value.document5File,
    form.value.document6File,
    form.value.document7File,
    form.value.document8File,
    form.value.document9File,
    form.value.document10File,
  ]

  const documents = documentTypes
    .map((document_type, index) => ({
      document_type,
      file: documentFiles[index] || null,
    }))
    .filter((doc) => doc.file)

  const companyInfo = form.value.company_info

  const additionalInfoPayload = selectedAdditionalActivities.value
    .flatMap((activity) => {
      return getSelectedRowsForActivity(activity).map((item) => ({
        additional_activity_id: activity.id,
        additional_activity_rate_id: item.rate.id,
        activity_name: activity.activity_name,
        keluasan_mps: formatAreaRange(item.rate.min_area, item.rate.max_area),
        type_name: item.rate.type_name,
        amount: item.rate.amount,
      }))
    })

  const payload = {
    district_id: form.value.district_id,
    applicant_info: form.value.applicant_info,
    company_info: companyInfo,
    additional_info: additionalInfoPayload,
    processing_fee: {
      amount: processingFeeAmountInCents,
      paid: form.value.processing_fee_paid,
      bill_code: processFeeBillCode.value,
    },
    documents,
  }

  router.post(licenseApply().url, payload, {
    forceFormData: true,
    preserveScroll: true,
    onStart: () => {
      isSubmitting.value = true
      if (redirectTimeout) {
        clearTimeout(redirectTimeout)
        redirectTimeout = null
      }
    },
    onFinish: () => {
      isSubmitting.value = false
    },
    onSuccess: () => {
      clearFormDraft()
      submitSuccess.value = 'Permohonan berjaya dihantar.'
      if (redirectTimeout) clearTimeout(redirectTimeout)
      redirectTimeout = setTimeout(() => {
        router.visit(dashboard().url)
      }, 3000)
    },
    onError: () => {
      submitError.value = 'Permohonan gagal dihantar. Sila semak semula borang.'
      if (redirectTimeout) {
        clearTimeout(redirectTimeout)
        redirectTimeout = null
      }
    },
  })
}

const licenseTypeOptions = [
  { value: 'homestay_island', label: '"Homestay", "Kampungstay", dan "Townstay" di pulau, tasik, atau seumpamanya' },
  { value: 'homestay_land', label: '"Homestay", "Kampungstay", dan "Townstay" selain di pulau, tasik, atau seumpamanya' },
  { value: 'campsite_island', label: 'Tapak perkhemahan dan tapak perkhemahan mewah di pulau, tasik, atau seumpamanya' },
  { value: 'campsite_land', label: 'Tapak perkhemahan dan tapak perkhemahan mewah selain di pulau, tasik, atau seumpamanya' },
  { value: 'rv_site', label: 'Tapak kenderaan rekreasi' },
  { value: 'houseboat_raft_kelong', label: 'Rumah bot, rumah rakit, dan kelong' },
  { value: 'others_island', label: 'Mana-mana rumah tumpangan lain di pulau, tasik, atau seumpamanya' },
  { value: 'others_land', label: 'Mana-mana rumah tumpangan lain selain di pulau, tasik, atau seumpamanya' },
]

const selectedLicenseTypeLabel = computed(() => {
  const selected = licenseTypeOptions.find((option) => option.value === form.value.company_info.license_type_selected)
  return selected?.label ?? ''
})

// const advertismentOptions1 = [
//   'Bersinar',
//   'Tidak Bersinar',
// ]

// const advertismentOptions2 = [
//   'Pada Bangunan',
//   'Menganjur',
//   'Tepi Jalan',
// ]

// type CompanyCategoryInfo = {
//   title: string
//   description?: string
//   points?: string[]
// }

// const companyCategoryDescriptions: Record<string, CompanyCategoryInfo> = {
//   t1: {
//     title: 'Kategori T-1',
//     description: 'Mempunyai kesemua atau sekurang-kurangnya sembilan (9) kemudahan:',
//     points: [
//       'Bar',
//       'Lounge',
//       'Dewan / Banquet / Bilik Mesyuarat',
//       'Aktiviti Pementasan / Persembahan Hiburan',
//       'Restoran / Coffee House / Kafe',
//       'Kolam Renang (Kanak-Kanak dan Dewasa)',
//       'Tempat Letak Kenderaan',
//       'Perkhidmatan Dobi',
//       'Kelab Kesihatan / Gimnasium / Sauna / Kemudahan Rekreasi dan Riadah',
//       'Salun Kecantikan',
//       'Kedai / Kiosk / Pasaraya',
//       'Papan Iklan',
//       'Kelab Golf',
//       'Taman Tema',
//       'Pusat Beli Belah'
//     ],
//   },
//   t2: {
//     title: 'Kategori T-2',
//     description: 'Mempunyai kesemua atau sekurang-kurangnya lapan (8) kemudahan:',
//     points: [
//       'Bar',
//       'Lounge',
//       'Dewan / Banquet / Bilik Mesyuarat',
//       'Aktiviti Pementasan / Persembahan Hiburan',
//       'Restoran / Coffee House / Kafe',
//       'Kolam Renang (Kanak-Kanak dan Dewasa)',
//       'Tempat Letak Kenderaan',
//       'Perkhidmatan Dobi',
//       'Kelab Kesihatan / Gimnasium / Sauna / Kemudahan Rekreasi dan Riadah',
//       'Salun Kecantikan',
//       'Kedai / Kiosk',
//       'Papan Iklan',
//       'Kelab Golf / Padang Golf',
//       'Taman Tema',
//     ],
//   },
//   t3: {
//     title: 'Kategori T-3',
//     description: 'Mempunyai kesemua atau sekurang-kurangnya enam (6) kemudahan:',
//     points: [
//       'Bar',
//       'Lounge',
//       'Dewan / Banquet / Bilik Mesyuarat / Dewan Terbuka / Dewan Makan',
//       'Aktiviti Pementasan / Persembahan Hiburan',
//       'Restoran / Coffee House / Kafe',
//       'Kolam Renang',
//       'Tempat Letak Kenderaan',
//       'Perkhidmatan Dobi',
//       'Tandas Awam',
//       'Lobi / Kaunter Pendaftaran',
//       'Kedai / Kiosk',
//       'Papan Iklan',
//     ],
//   },
//   t4: {
//     title: 'Kategori T-4',
//     description: 'Mempunyai kesemua atau sekurang-kurangnya satu (1) kemudahan:',
//     points: [
//       'Tempat Letak Kenderaan',
//       'Perkhidmatan Dobi',
//       'Kedai / Kiosk',
//       'Papan Iklan',
//       'Tandas Awam',
//       'Lobi / Kaunter Pendaftaran',
//       'Dewan / Banquet / Bilik Mesyuarat / Dewan Terbuka / Dewan Makan',
//       'Kolam Renang',
//     ],
//   },
//   t5: {
//     title: 'Kategori T-5',
//     points: [
//       'Asrama',
//       'Motel', 
//       'Rumah Tumpangan', 
//       'Rumah Permalaman', 
//       'Rumah Tetamu', 
//       'Rumah Bot', 
//       'Homestay', 
//       'Townstay', 
//       'Kampungstay', 
//       'Tapak Perkhemahan atau Kenderaan atau mana-mana Rumah Tumpangan yang digunakan sebagai tempat menginap yang berbayar.'],
//   },
// }

// const selectedCompanyCategoryInformation = computed(() => {
//   const selectedCategory = form.value.company_info.company_category

//   if (!selectedCategory) {
//     return null
//   }

//   return companyCategoryDescriptions[selectedCategory] ?? null
// })

function handleDocumentChange(index: number, event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files && input.files[0] ? input.files[0] : null
  const name = file ? file.name : ''
  switch (index) {
    case 1:
      form.value.document1File = file
      form.value.document1Name = name
      break
    case 2:
      form.value.document2File = file
      form.value.document2Name = name
      break
    case 3:
      form.value.document3File = file
      form.value.document3Name = name
      break
    case 4:
      form.value.document4File = file
      form.value.document4Name = name
      break
    case 5:
      form.value.document5File = file
      form.value.document5Name = name
      break
    case 6:
      form.value.document6File = file
      form.value.document6Name = name
      break
    case 7:
      form.value.document7File = file
      form.value.document7Name = name
      break
    case 8:
      form.value.document8File = file
      form.value.document8Name = name
      break
    case 9:
      form.value.document9File = file
      form.value.document9Name = name
      break
    case 10:
      form.value.document10File = file
      form.value.document10Name = name
      break
  }
}

function hasUploadedDocument(index: number) {
  switch (index) {
    case 1:
      return Boolean(form.value.document1File || form.value.document1Name)
    case 2:
      return Boolean(form.value.document2File || form.value.document2Name)
    case 3:
      return Boolean(form.value.document3File || form.value.document3Name)
    case 4:
      return Boolean(form.value.document4File || form.value.document4Name)
    case 5:
      return Boolean(form.value.document5File || form.value.document5Name)
    case 6:
      return Boolean(form.value.document6File || form.value.document6Name)
    case 7:
      return Boolean(form.value.document7File || form.value.document7Name)
    case 8:
      return Boolean(form.value.document8File || form.value.document8Name)
    case 9:
      return Boolean(form.value.document9File || form.value.document9Name)
    case 10:
      return Boolean(form.value.document10File || form.value.document10Name)
    default:
      return false
  }
}

function getDocumentButtonClass(index: number) {
  return hasUploadedDocument(index)
    ? 'bg-green-600 hover:bg-green-700 text-white dark:bg-green-500 dark:hover:bg-green-600 dark:text-white'
    : 'bg-gray-200 hover:bg-gray-300 text-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 dark:text-slate-100'
}

function enforceNumericValue(event: Event) {
  const target = event.target as HTMLInputElement
  const sanitized = target.value.replace(/\D+/g, '')
  if (target.value !== sanitized) target.value = sanitized
  return sanitized
}

function formatDate(dateStr: string | null | undefined) {
  if (!dateStr) return ''
  // Expecting date in YYYY-MM-DD from <input type="date"> — convert to dd/mm/YYYY
  const parts = String(dateStr).split('-')
  if (parts.length === 3) {
    return `${parts[2]}/${parts[1]}/${parts[0]}`
  }
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return String(dateStr)
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yyyy = d.getFullYear()
  return `${dd}/${mm}/${yyyy}`
}

function formatGender(value: string | null | undefined) {
  if (!value) return '-'
  if (value === 'lelaki') return 'Lelaki'
  if (value === 'perempuan') return 'Perempuan'
  return value
}

function formatIncome(value: string | null | undefined) {
  if (!value) return '-'
  const numeric = Number(String(value).replace(/,/g, ''))
  if (Number.isNaN(numeric)) return value
  return new Intl.NumberFormat('ms-MY').format(numeric)
}

function formatCompanyCategory(value: string | null | undefined) {
  if (!value) return '-'
  return `Kategori ${String(value).toUpperCase()}`
}

function formatPremisesLocation(value: string | null | undefined) {
  if (!value) return '-'
  if (value === 'land') return 'Darat'
  if (value === 'island') return 'Pulau'
  return value
}

function getPbtCardClass(index: number) {
  const total = categoryCards.value.length
  const isSingleCardLastRow = total % 3 === 1 && index === total - 1

  return isSingleCardLastRow ? 'lg:col-start-2' : ''
}
</script>

<template>
  <Head title="Permohonan Lesen" />

  <AppLayout :breadcrumbs="breadcrumbs">

    <div class="w-full h-full flex flex-col p-6 bg-white dark:bg-black rounded-xl shadow dark:shadow-black/30">

      <!-- Step Indicator -->
      <div class="mb-3 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-7">
        <button
          v-for="n in stepTitles.length"
          :key="n"
          type="button"
          class="rounded-xl border px-3 py-3 text-left transition"
          :class="step === n
            ? 'border-blue-500 bg-blue-50 dark:border-blue-400 dark:bg-blue-950/30'
            : step > n
              ? 'border-blue-200 bg-blue-50/60 hover:border-blue-300 dark:border-blue-900 dark:bg-blue-950/20 dark:hover:border-blue-700'
              : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900/60 dark:hover:border-slate-500'"
          @click="goToStep(n)"
        >
          <div class="font-semibold" :class="step >= n ? 'text-blue-600 dark:text-blue-300' : 'text-gray-500 dark:text-slate-400'">Langkah {{ n }}</div>
          <div class="text-sm mt-1" :class="step >= n ? 'text-blue-500 dark:text-blue-200' : 'text-gray-500 dark:text-slate-400'">{{ stepTitles[n-1] }}</div>
        </button>
      </div>

      <div class="flex-1 overflow-auto space-y-6">
        <div v-if="submitSuccess" class="rounded-xl bg-green-50 text-green-700 border border-green-200 px-4 py-3 dark:bg-green-950/40 dark:text-green-300 dark:border-green-900">
          {{ submitSuccess }}
        </div>
        <div v-if="submitError" class="rounded-xl bg-red-50 text-red-700 border border-red-200 px-4 py-3 dark:bg-red-950/40 dark:text-red-300 dark:border-red-900">
          {{ submitError }}
        </div>
        <!-- STEP 1 -->
        <div v-if="step === 1">
          <hr class="my-6 border-t border-gray-200" />
          <h2 class="text-xl font-bold mb-4 text-slate-900 dark:text-slate-100">{{ stepTitles[0] }}</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
              v-for="(card, cardIndex) in categoryCards"
              :key="card.name"
              class="flex items-center gap-4 rounded-2xl border p-4 shadow-sm cursor-pointer transition"
              :class="[
                getPbtCardClass(cardIndex),
                form.district_id === card.id
                  ? 'border-blue-500 bg-blue-50 dark:border-blue-400 dark:bg-blue-950/30'
                  : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 hover:border-blue-300 dark:hover:border-blue-500',
              ]"
              @click="form.district_id = card.id"
            >
              <img :src="card.logo" :alt="card.name" class="h-12 w-12 object-contain" />
              <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ card.name }}</div>
            </div>
          </div>
          <div v-if="selectedDistrictName" class="mt-4 text-sm text-slate-600 dark:text-slate-300">
            PBT dipilih: <span class="font-semibold text-slate-900 dark:text-slate-100">{{ selectedDistrictName }}</span>
          </div>
          <hr class="my-6 border-t border-gray-200" />
        </div>

        <!-- STEP 2 -->
        <div v-if="step === 2">
          <hr class="my-6 border-t border-gray-200" />
          <h2 class="text-xl font-bold mb-4 text-slate-900 dark:text-slate-100">Maklumat Pemohon</h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Nama Pemohon</label>
              <input v-model="form.applicant_info.name"
                class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                     placeholder=" Masukkan Nama Pemohon" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">No Kad Pengenalan</label>
              <input v-model="form.applicant_info.ic_no"
                     inputmode="numeric"
                     pattern="[0-9]*"
                     @input="form.applicant_info.ic_no = enforceNumericValue($event)"
                class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                     placeholder="Masukkan No Kad Pengenalan tanpa (-)" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Tarikh Lahir</label>
              <input type="date"
                     v-model="form.applicant_info.birth_date"
                class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Tempat Lahir</label>
              <select v-model="form.applicant_info.birth_place"
                      class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700">
                <option value="">-- Pilih Negeri --</option>
                <option v-for="state in malaysiaStates" :key="state" :value="state">{{ state }}</option>
              </select>
            </div>

            <div>
                    <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Jantina</label>
              <select v-model="form.applicant_info.gender"
                      class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700">
                <option value="">-- Pilih jantina --</option>
                <option value="lelaki">Lelaki</option>
                <option value="perempuan">Perempuan</option>
              </select>
            </div>

            <div>
                    <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Warganegara</label>
              <select v-model="form.applicant_info.citizenship"
                      class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700">
                <option value="">-- Pilih Warganegara --</option>
                <option value="Warganegara">Warganegara</option>
                <option value="Bukan Warganegara">Bukan Warganegara</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Agama</label>
              <div class="flex flex-col space-y-2">
                <label class="flex items-center">
                  <input class="mr-2" type="radio" value="Islam" v-model="religionSelection" />
                  <span class="text-slate-700 dark:text-slate-200">Islam</span>
                </label>
                <label class="flex items-center">
                  <input class="mr-2" type="radio" value="Buddha" v-model="religionSelection" />
                  <span class="text-slate-700 dark:text-slate-200">Buddha</span>
                </label>
                <label class="flex items-center">
                  <input class="mr-2" type="radio" value="Hindu" v-model="religionSelection" />
                  <span class="text-slate-700 dark:text-slate-200">Hindu</span>
                </label>
                <label class="flex items-center">
                  <input class="mr-2" type="radio" value="Kristian" v-model="religionSelection" />
                  <span class="text-slate-700 dark:text-slate-200">Kristian</span>
                </label>
                <label class="flex items-center">
                  <input class="mr-2" type="radio" value="Lain-lain" v-model="religionSelection" />
                  <span class="text-slate-700 dark:text-slate-200">Lain-lain</span>
                </label>
                <div v-if="religionSelection === 'Lain-lain'" class="mt-2">
                  <input v-model="customReligion"
                         placeholder="Sila nyatakan"
                         class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700" />
                </div>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Bangsa</label>
              <div class="flex flex-col space-y-2">
                <label class="flex items-center">
                  <input class="mr-2" type="radio" value="Melayu" v-model="ethnicitySelection" />
                  <span class="text-slate-700 dark:text-slate-200">Melayu</span>
                </label>
                <label class="flex items-center">
                  <input class="mr-2" type="radio" value="Cina" v-model="ethnicitySelection" />
                  <span class="text-slate-700 dark:text-slate-200">Cina</span>
                </label>
                <label class="flex items-center">
                  <input class="mr-2" type="radio" value="India" v-model="ethnicitySelection" />
                  <span class="text-slate-700 dark:text-slate-200">India</span>
                </label>
                <label class="flex items-center">
                  <input class="mr-2" type="radio" value="Lain-lain" v-model="ethnicitySelection" />
                  <span class="text-slate-700 dark:text-slate-200">Lain-lain</span>
                </label>
                <div v-if="ethnicitySelection === 'Lain-lain'" class="mt-2">
                  <input v-model="customEthnicity"
                         placeholder="Sila nyatakan"
                         class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700" />
                </div>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Status Perkahwinan</label>
              <select v-model="form.applicant_info.marital_status"
                      class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700">
                <option value="">-- Pilih Status --</option>
                <option value="Bujang">Bujang</option>
                <option value="Berkahwin">Berkahwin</option>
                <option value="Duda">Duda</option>
                <option value="Janda">Janda</option>
                <option value="Balu">Balu</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Pekerjaan</label>
              <input v-model="form.applicant_info.occupation"
                class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                     placeholder="Masukkan Pekerjaan" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Pendapatan (RM)</label>
              <input v-model="form.applicant_info.income"
                     inputmode="numeric"
                     pattern="[0-9]*"
                     @input="form.applicant_info.income = enforceNumericValue($event)"
                class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                     placeholder="Masukkan Pendapatan (contoh: 2,000)" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Alamat Kediaman Pemohon</label>
              <input v-model="form.applicant_info.home_address"
                class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                     placeholder="Masukkan Alamat Kediaman Pemohon" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Poskod</label>
              <input v-model="form.applicant_info.postcode"
                     inputmode="numeric"
                     pattern="[0-9]*"
                     @input="form.applicant_info.postcode = enforceNumericValue($event)"
                class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                     placeholder="Masukkan Poskod" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Negeri</label>
              <select v-model="form.applicant_info.state"
                      class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700">
                <option value="">-- Pilih Negeri --</option>
                <option v-for="state in malaysiaStates" :key="`applicant-${state}`" :value="state">{{ state }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Daerah</label>
              <select v-model="form.applicant_info.district"
                      class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700">
                <option value="">-- Pilih Daerah --</option>
                <option v-for="district in applicantDistrictOptions" :key="`applicant-district-${district}`" :value="district">
                  {{ district }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">No Telefon</label>
              <input v-model="form.applicant_info.phone_number"
                     inputmode="numeric"
                     pattern="[0-9]*"
                     @input="form.applicant_info.phone_number = enforceNumericValue($event)"
                class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                     placeholder="Masukkan No Telefon" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Emel</label>
              <input v-model="form.applicant_info.email"
                class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                     placeholder="Masukkan Emel" />
            </div>
          </div>
          <hr class="my-6 border-t border-gray-200" />
        </div>

        <!-- STEP 3 -->
        <div v-if="step === 3">
          <hr class="my-6 border-t border-gray-200 dark:border-slate-700" />
          <h2 class="text-xl font-bold mb-4 text-slate-900 dark:text-slate-100">Maklumat Perniagaan / Syarikat</h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Nama Perniagaan / Syarikat</label>
              <input v-model="form.company_info.company_name"
                class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                     placeholder="Masukkan Nama Perniagaan / Syarikat" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Nama Rumah Tumpangan</label>
              <input v-model="form.company_info.hotel_name"
                class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                     placeholder="Masukkan Nama Rumah Tumpangan" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Alamat Perniagaan (Premis)</label>
              <input v-model="form.company_info.company_address"
                class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                     placeholder="Masukkan Alamat Perniagaan (Premis)" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Poskod</label>
              <input v-model="form.company_info.company_postcode"
                     inputmode="numeric"
                     pattern="[0-9]*"
                     @input="form.company_info.company_postcode = enforceNumericValue($event)"
                class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                     placeholder="Masukkan Poskod" />
            </div>

            <div>
                    <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Negeri</label>
              <select v-model="form.company_info.company_state"
                  disabled
                      class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700">
                <option value="Terengganu">Terengganu</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Daerah</label>
              <select v-model="form.company_info.company_district"
                      class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700">
                <option value="">-- Pilih Daerah --</option>
                <option v-for="district in companyDistrictOptions" :key="`company-district-${district}`" :value="district">
                  {{ district }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">No Telefon</label>
              <input v-model="form.company_info.company_phone"
                     inputmode="numeric"
                     pattern="[0-9]*"
                     @input="form.company_info.company_phone = enforceNumericValue($event)"
                class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                     placeholder="Masukkan No Telefon" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">No Pendaftaran Perniagaan / Syarikat</label>
              <input v-model="form.company_info.company_registration_number"
                class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                     placeholder="Masukkan No Pendaftaran Perniagaan / Syarikat" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Tarikh Daftar</label>
              <input type="date"
                     v-model="form.company_info.company_registration_date"
                class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Tarikh Tamat</label>
              <input type="date"
                     v-model="form.company_info.company_registration_expiry_date"
                class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700" />
            </div>

            <!-- <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Kategori Premis</label>
              <select v-model="form.company_info.company_category"
                  class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700">
                <option value="">-- Pilih kategori --</option>
                <option value="t1">Kategori T-1</option>
                <option value="t2">Kategori T-2</option>
                <option value="t3">Kategori T-3</option>
                <option value="t4">Kategori T-4</option>
                <option value="t5">Kategori T-5</option>
              </select>
              <div class="mt-2 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200">
                <template v-if="!selectedCompanyCategoryInformation">
                  Penerangan Kategori
                </template>
                <template v-else>
                  <div class="font-semibold">{{ selectedCompanyCategoryInformation.title }}</div>
                  <div class="mt-1">{{ selectedCompanyCategoryInformation.description }}</div>
                  <ul v-if="selectedCompanyCategoryInformation.points?.length" class="mt-2 list-disc pl-5 space-y-1">
                    <li v-for="(point, pointIndex) in selectedCompanyCategoryInformation.points" :key="`category-point-${pointIndex}`">
                      {{ point }}
                    </li>
                  </ul>
                </template>
              </div>
            </div> -->

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Lokasi Premis</label>
              <select v-model="form.company_info.company_premises_location"
                  class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700">
                <option value="">-- Pilih lokasi premis --</option>
                <option value="land">Darat</option>
                <option value="island">Pulau</option>
              </select>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Bilangan Pekerja</label>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-center">
                  <label class="w-28 text-sm text-slate-700 dark:text-slate-200">Melayu</label>
                  <input type="number" min="0" v-model="form.company_info.employee_malay"
                         class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700" />
                </div>

                <div class="flex items-center">
                    <label class="w-28 text-sm text-slate-700 dark:text-slate-200">Cina</label>
                  <input type="number" min="0" v-model="form.company_info.employee_chinese"
                      class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700" />
                </div>

                <div class="flex items-center">
                    <label class="w-28 text-sm text-slate-700 dark:text-slate-200">India</label>
                  <input type="number" min="0" v-model="form.company_info.employee_indian"
                      class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700" />
                </div>

                <div class="flex items-center">
                  <label class="w-28 text-sm text-slate-700 dark:text-slate-200">Lain-lain</label>
                  <input type="number" min="0" v-model="form.company_info.employee_others"
                         class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700" />
                </div>
              </div>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Waktu Beroperasi</label>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs mb-1 text-slate-600 dark:text-slate-300">Mula</label>
                  <input type="time"
                         v-model="form.company_info.company_operation_start"
                         class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700" />
                </div>

                <div>
                  <label class="block text-xs mb-1 text-slate-600 dark:text-slate-300">Tamat</label>
                  <input type="time"
                         v-model="form.company_info.company_operation_end"
                         class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700" />
                </div>
              </div>
            </div>
          </div>

          <hr class="my-6 border-t border-gray-200 dark:border-slate-700" />
          <h2 class="text-xl font-bold mb-4 text-slate-900 dark:text-slate-100">Maklumat Ibu Pejabat</h2>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Alamat Perniagaan (Ibu Pejabat)</label>
              <input v-model="form.company_info.company_address_hq"
                      class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                      placeholder="Masukkan Alamat Perniagaan (Ibu Pejabat)" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Poskod</label>
              <input v-model="form.company_info.company_postcode_hq"
                     inputmode="numeric"
                     pattern="[0-9]*"
                     @input="form.company_info.company_postcode_hq = enforceNumericValue($event)"
                      class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                      placeholder="Masukkan Poskod" />
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Negeri</label>
              <select v-model="form.company_info.company_state_hq"
                      class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700">
                <option value="">-- Pilih Negeri --</option>
                <option v-for="state in malaysiaStates" :key="`company-hq-${state}`" :value="state">{{ state }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">Daerah</label>
              <select v-model="form.company_info.company_district_hq"
                      class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700">
                <option value="">-- Pilih Daerah --</option>
                <option v-for="district in companyHqDistrictOptions" :key="`company-hq-district-${district}`" :value="district">
                  {{ district }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2 text-slate-700 dark:text-slate-200">No Telefon</label>
              <input v-model="form.company_info.company_phone_hq"
                     inputmode="numeric"
                     pattern="[0-9]*"
                     @input="form.company_info.company_phone_hq = enforceNumericValue($event)"
                      class="input border border-gray-300 w-full px-3 py-2 rounded-xl dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                      placeholder="Masukkan No Telefon" />
            </div>
          </div>
          <hr class="my-6 border-t border-gray-200 dark:border-slate-700" />
        </div>

        <!-- STEP 4 -->
        <div v-if="step === 4">
          <hr class="my-6 border-t border-gray-200 dark:border-slate-700" />
          <h2 class="text-xl font-bold mb-4 text-slate-900 dark:text-slate-100">Maklumat Tambahan</h2>
          <hr class="my-6 border-t border-gray-200 dark:border-slate-700" />

          <div class="mt-4 space-y-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-2">Kategori Lesen Yang Dipohon</h2>
                <select
                  v-model="form.company_info.license_type_selected"
                  class="w-full px-3 py-2 rounded-xl border border-gray-300 dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                >
                  <option value="">-- Pilih Kategori Lesen --</option>
                  <option v-for="option in licenseTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
              </div>

              <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-2">Bilangan Bilik</h2>
                <input
                  v-model="form.company_info.room_count"
                  inputmode="numeric"
                  pattern="[0-9]*"
                  @input="form.company_info.room_count = enforceNumericValue($event)"
                  class="w-full px-3 py-2 rounded-xl border border-gray-300 dark:bg-slate-800 dark:text-slate-100 dark:border-slate-700"
                  placeholder="Masukkan bilangan bilik"
                />
              </div>
            </div>

            <hr class="my-6 border-t border-gray-200 dark:border-slate-700" />

            <div class="space-y-4">
              <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Aktiviti Tambahan</h2>

              <div v-if="!form.district_id" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                Sila pilih PBT pada Langkah 1 untuk melihat senarai aktiviti tambahan.
              </div>

              <div v-else-if="additionalActivitiesLoading" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                Memuatkan senarai aktiviti tambahan...
              </div>

              <div v-else-if="additionalActivitiesMessage" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">
                {{ additionalActivitiesMessage }}
              </div>

              <div v-else-if="additionalActivities.length === 0" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                Tiada aktiviti tambahan ditetapkan untuk PBT yang dipilih.
              </div>

              <div class="grid grid-cols-1 gap-2 md:grid-cols-2 lg:grid-cols-3">
                <label
                  v-for="activity in additionalActivities"
                  :key="activity.id"
                  class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                >
                  <input
                    v-model="form.advertisment_info.selected_activity_ids"
                    type="checkbox"
                    :value="activity.id"
                    class="h-4 w-4"
                  />
                  <span>{{ activity.activity_name }}</span>
                </label>
              </div>

              <div
                v-for="activity in selectedAdditionalActivities"
                :key="`table-${activity.id}`"
                class="overflow-auto rounded-xl border border-slate-200 dark:border-slate-700"
              >
                <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-900">
                  <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ activity.activity_name }}</h3>
                  <button
                    type="button"
                    class="rounded-xl bg-blue-600 px-3 py-1 text-sm text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                    @click="addActivityRow(activity.id)"
                  >
                    + Tambah
                  </button>
                </div>

                <table class="w-full table-auto border-collapse text-slate-900 dark:text-slate-100">
                  <thead>
                    <tr class="bg-gray-100 dark:bg-slate-800">
                      <th class="border px-2 py-1 text-center">No</th>
                      <th class="border px-2 py-1 text-left">Jenis</th>
                      <th class="border px-2 py-1 text-left">Keluasan (MPS)</th>
                      <th class="border px-2 py-1 text-right">Amaun (RM)</th>
                      <th class="border px-2 py-1 text-center">Tindakan</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(row, rowIndex) in getActivityRows(activity.id)"
                      :key="`${activity.id}-row-${rowIndex}`"
                    >
                      <td class="border px-2 py-1 text-center">
                        {{ rowIndex + 1 }}
                      </td>
                      <td class="border px-2 py-1">
                        <select
                          v-model="row.type_name"
                          class="w-full rounded-xl border border-gray-300 px-2 py-1 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                          @change="onRowJenisChange(activity, row)"
                        >
                          <option value="">-- Pilih Jenis --</option>
                          <option
                            v-for="jenis in getJenisOptions(activity)"
                            :key="`${activity.id}-jenis-${jenis}`"
                            :value="jenis"
                          >
                            {{ jenis }}
                          </option>
                        </select>
                      </td>
                      <td class="border px-2 py-1">
                        <select
                          v-model="row.rate_id"
                          class="w-full rounded-xl border border-gray-300 px-2 py-1 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                          :disabled="!row.type_name"
                        >
                          <option :value="null">-- Pilih Keluasan --</option>
                          <option
                            v-for="rateOption in getKeluasanOptions(activity, row)"
                            :key="`${activity.id}-rate-option-${rateOption.id}`"
                            :value="rateOption.id"
                          >
                            {{ formatAreaRange(rateOption.min_area, rateOption.max_area) }}
                          </option>
                        </select>
                      </td>
                      <td class="border px-2 py-1 text-right font-semibold tabular-nums">
                        {{ formatAmount(getRowRate(activity, row)?.amount ?? 0) }}
                      </td>
                      <td class="border px-2 py-1 text-center">
                        <button
                          type="button"
                          class="rounded-xl bg-red-600 px-2 py-1 text-white hover:bg-red-700 disabled:opacity-50 dark:bg-red-500 dark:hover:bg-red-600"
                          :disabled="getActivityRows(activity.id).length <= 1"
                          @click="removeActivityRow(activity.id, rowIndex)"
                        >
                          Buang
                        </button>
                      </td>
                    </tr>
                    <tr v-if="activity.rates.length === 0">
                      <td colspan="5" class="border px-2 py-3 text-center text-sm text-slate-600 dark:text-slate-300">
                        Tiada kadar ditetapkan untuk aktiviti ini.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-right dark:border-slate-700 dark:bg-slate-900/50">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Jumlah Amaun Aktiviti Tambahan:</span>
                <span class="ml-2 text-base font-bold tabular-nums text-slate-900 dark:text-slate-100">RM{{ formatAmount(additionalActivitiesTotalAmount) }}</span>
              </div>
            </div>
          </div>
          <hr class="my-6 border-t border-gray-200 dark:border-slate-700" />
        </div>

        <!-- STEP 5 -->
        <div v-if="step === 5">
          <hr class="my-6 border-t border-gray-200 dark:border-slate-700" />
          <h2 class="text-xl font-bold mb-4 text-slate-900 dark:text-slate-100">Dokumen Sokongan</h2>
          <div class="grid grid-cols-2 gap-6">
            <div class="space-y-4">
              <div class="flex flex-col">
                <label class="text-sm font-medium mb-2 text-slate-700 dark:text-slate-200 leading-6">
                  
                  <p>
                    i) (a) Memorandum dan Perkara Persatuan Bagi Syarikat
                    <span class="italic">
                      (Memorandum and Articles of Association)
                    </span>
                    Borang 49
                    <span class="italic">
                      (Return Giving Particulars)
                    </span>
                    Mengikut Akta Pendaftaran Syarikat 1965.
                  </p>

                  <p class="my-2 font-semibold text-center">ATAU</p>

                  <p>
                    (b) Borang A
                    <span class="italic">(Pendaftaran Perniagaan)</span>
                    atau Borang B
                    <span class="italic">(Pendaftaran Perubahan Dalam Perniagaan)</span>
                    dan Borang D
                    <span class="italic">(Perakuan Pendaftaran Perniagaan)</span>
                    mengikut Akta Pendaftaran Perniagaan 1956 (Pindaan 1978).
                  </p>

                </label>

                <div class="flex items-center gap-3 mt-2">
                  <label
                    class="inline-flex w-fit items-center px-3 py-1 rounded-xl cursor-pointer transition-colors"
                    :class="getDocumentButtonClass(1)"
                  >
                    <input
                      type="file"
                      class="hidden"
                      @change="(e) => handleDocumentChange(1, e)"
                    />
                    <span>Choose File</span>
                  </label>

                  <span class="text-xs text-gray-600 dark:text-slate-400">
                    {{ form.document1Name || 'No file chosen' }}
                  </span>
                </div>
              </div>

              <div class="flex flex-col">
                <label class="text-sm font-medium mb-1 text-slate-700 dark:text-slate-200">ii) Pelan lokasi premis perniagaan beserta gambar premis</label>
                <div class="flex items-center gap-3">
                  <label class="inline-flex w-fit items-center px-3 py-1 rounded-xl cursor-pointer transition-colors" :class="getDocumentButtonClass(2)">
                    <input type="file" class="hidden" @change="(e) => handleDocumentChange(2, e)" />
                    <span>Choose File</span>
                  </label>
                  <span class="text-xs text-gray-600 dark:text-slate-400">{{ form.document2Name || 'No file chosen' }}</span>
                </div>
              </div>

              <div class="flex flex-col">
                <label class="text-sm font-medium mb-1 text-slate-700 dark:text-slate-200">iii) Pelan lantai premis / kawasan (ukuran dalam meter persegi)</label>
                <div class="flex items-center gap-3">
                  <label class="inline-flex w-fit items-center px-3 py-1 rounded-xl cursor-pointer transition-colors" :class="getDocumentButtonClass(3)">
                    <input type="file" class="hidden" @change="(e) => handleDocumentChange(3, e)" />
                    <span>Choose File</span>
                  </label>
                  <span class="text-xs text-gray-600 dark:text-slate-400">{{ form.document3Name || 'No file chosen' }}</span>
                </div>
              </div>

              <div class="flex flex-col">
                <label class="text-sm font-medium mb-1 text-slate-700 dark:text-slate-200">iv) Surat perjanjian atau kebenaran tuan bangunan / tanah yang disetemkan (jika bangunan / tanah yang disewa)</label>
                <div class="flex items-center gap-3">
                  <label class="inline-flex w-fit items-center px-3 py-1 rounded-xl cursor-pointer transition-colors" :class="getDocumentButtonClass(4)">
                    <input type="file" class="hidden" @change="(e) => handleDocumentChange(4, e)" />
                    <span>Choose File</span>
                  </label>
                  <span class="text-xs text-gray-600 dark:text-slate-400">{{ form.document4Name || 'No file chosen' }}</span>
                </div>
              </div>

              <div class="flex flex-col">
                <label class="text-sm font-medium mb-1 text-slate-700 dark:text-slate-200">v) Salinan geran tanah / Lesen Pendudukan Sementara (LPS) / lain-lain dokumen yang berkaitan</label>
                <div class="flex items-center gap-3">
                  <label class="inline-flex w-fit items-center px-3 py-1 rounded-xl cursor-pointer transition-colors" :class="getDocumentButtonClass(5)">
                    <input type="file" class="hidden" @change="(e) => handleDocumentChange(5, e)" />
                    <span>Choose File</span>
                  </label>
                  <span class="text-xs text-gray-600 dark:text-slate-400">{{ form.document5Name || 'No file chosen' }}</span>
                </div>
              </div>
            </div>

            <div class="space-y-4">
              <div class="flex flex-col">
                <label class="text-sm font-medium mb-1 text-slate-700 dark:text-slate-200">vi) Salinan Sijil Kelayakan Menduduki Bangunan / Sementara (CF/CCC) (TCF)</label>
                <div class="flex items-center gap-3">
                  <label class="inline-flex w-fit items-center px-3 py-1 rounded-xl cursor-pointer transition-colors" :class="getDocumentButtonClass(6)">
                    <input type="file" class="hidden" @change="(e) => handleDocumentChange(6, e)" />
                    <span>Choose File</span>
                  </label>
                  <span class="text-xs text-gray-600 dark:text-slate-400">{{ form.document6Name || 'No file chosen' }}</span>
                </div>
              </div>

              <div class="flex flex-col">
                <label class="text-sm font-medium mb-1 text-slate-700 dark:text-slate-200">vii) Gambar pemohon berukuran passport</label>
                <div class="flex items-center gap-3">
                  <label class="inline-flex w-fit items-center px-3 py-1 rounded-xl cursor-pointer transition-colors" :class="getDocumentButtonClass(7)">
                    <input type="file" class="hidden" @change="(e) => handleDocumentChange(7, e)" />
                    <span>Choose File</span>
                  </label>
                  <span class="text-xs text-gray-600 dark:text-slate-400">{{ form.document7Name || 'No file chosen' }}</span>
                </div>
              </div>

              <div class="flex flex-col">
                <label class="text-sm font-medium mb-1 text-slate-700 dark:text-slate-200">viii) Salinan Kad Pengenalan pemohon</label>
                <div class="flex items-center gap-3">
                  <label class="inline-flex w-fit items-center px-3 py-1 rounded-xl cursor-pointer transition-colors" :class="getDocumentButtonClass(8)">
                    <input type="file" class="hidden" @change="(e) => handleDocumentChange(8, e)" />
                    <span>Choose File</span>
                  </label>
                  <span class="text-xs text-gray-600 dark:text-slate-400">{{ form.document8Name || 'No file chosen' }}</span>
                </div>
              </div>

              <div class="flex flex-col">
                <label class="text-sm font-medium mb-1 text-slate-700 dark:text-slate-200">ix) Senarai nama semua Pengendali Makanan / Pembantu (Perniagaan Makanan) serta Kad Pengenalan dan gambar berukuran passport bagi setiap orang (jika berkenaan)</label>
                <div class="flex items-center gap-3">
                  <label class="inline-flex w-fit items-center px-3 py-1 rounded-xl cursor-pointer transition-colors" :class="getDocumentButtonClass(9)">
                    <input type="file" class="hidden" @change="(e) => handleDocumentChange(9, e)" />
                    <span>Choose File</span>
                  </label>
                  <span class="text-xs text-gray-600 dark:text-slate-400">{{ form.document9Name || 'No file chosen' }}</span>
                </div>
              </div>

              <div class="flex flex-col">
                <label class="text-sm font-medium mb-1 text-slate-700 dark:text-slate-200">x) Carta Proses Pengeluaran Pengilangan / Pemerosesan (jika ada)</label>
                <div class="flex items-center gap-3">
                  <label class="inline-flex w-fit items-center px-3 py-1 rounded-xl cursor-pointer transition-colors" :class="getDocumentButtonClass(10)">
                    <input type="file" class="hidden" @change="(e) => handleDocumentChange(10, e)" />
                    <span>Choose File</span>
                  </label>
                  <span class="text-xs text-gray-600 dark:text-slate-400">{{ form.document10Name || 'No file chosen' }}</span>
                </div>
              </div>
            </div>
          </div>
          <hr class="my-6 border-t border-gray-200 dark:border-slate-700" />
        </div>

        <!-- STEP 6 -->
        <div v-if="step === 6">
          <hr class="my-6 border-t border-gray-200 dark:border-slate-700" />
          <h2 class="text-xl font-bold mb-4 text-slate-900 dark:text-slate-100">Bayaran Fi Proses</h2>

          <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 p-6 space-y-4">
            <div>
              <div class="text-sm text-slate-600 dark:text-slate-300">Jumlah perlu dibayar</div>
              <div class="text-3xl font-bold text-slate-900 dark:text-slate-100">RM{{ formattedProcessingFeeAmount }}</div>
              <div class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                Permohonan hanya boleh dihantar selepas Bayaran Fi Proses telah dibayar.
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
              <button
                type="button"
                @click="startProcessFeePayment"
                :disabled="isProcessFeeLoading || form.processing_fee_paid"
                class="px-4 py-2 bg-[#2563EB] hover:bg-[#154dc5] disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl"
              >
                {{ isProcessFeeLoading ? 'Membuka bayaran...' : form.processing_fee_paid ? 'Bayaran Selesai' : 'Bayar Sekarang' }}
              </button>

              <button
                type="button"
                @click="refreshProcessFeeStatus"
                :disabled="isProcessFeeLoading"
                class="px-4 py-2 bg-gray-200 hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed rounded-xl dark:bg-slate-700 dark:hover:bg-slate-600 dark:text-slate-100"
              >
                Semak Status Bayaran
              </button>
            </div>

            <div class="text-sm" :class="form.processing_fee_paid ? 'text-green-700 dark:text-green-300' : processFeeStatus === 'pending' ? 'text-amber-700 dark:text-amber-300' : 'text-red-700 dark:text-red-300'">
              <template v-if="form.processing_fee_paid">
                Bayaran diterima. Anda boleh teruskan ke langkah pengesahan.
              </template>
              <template v-else-if="processFeeStatus === 'pending'">
                Bayaran sedang diproses. Status akan dikemas kini secara automatik selepas pembayaran berjaya.
              </template>
              <template v-else>
                Bayaran belum dibuat. Sila buat Bayaran Fi Proses untuk teruskan penghantaran.
              </template>
            </div>

            <div v-if="processFeeStatusMessage" class="text-sm text-slate-600 dark:text-slate-300">
              {{ processFeeStatusMessage }}
            </div>
          </div>
          <hr class="my-6 border-t border-gray-200 dark:border-slate-700" />
        </div>

        <!-- STEP 7 -->
        <div v-if="step === 7">
          <hr class="my-6 border-t border-gray-200 dark:border-slate-700" />
          <h2 class="text-xl font-bold mb-4 text-slate-900 dark:text-slate-100">Pengesahan</h2>

          <div class="mb-6 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/60 p-6">
            <div class="space-y-8">
              <section>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">{{ stepTitles[0] }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">PBT Dipilih</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ selectedDistrictName || '-' }}</div>
                  </div>
                </div>
              </section>
              <section>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">{{ stepTitles[1] }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Nama Pemohon</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.applicant_info.name || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">No Kad Pengenalan</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.applicant_info.ic_no || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Tarikh Lahir</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ formatDate(form.applicant_info.birth_date) || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Tempat Lahir</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.applicant_info.birth_place || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Jantina</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ formatGender(form.applicant_info.gender) }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Warganegara</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.applicant_info.citizenship || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Agama</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.applicant_info.religion || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Bangsa</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.applicant_info.ethnicity || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Status Perkahwinan</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.applicant_info.marital_status || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Pekerjaan</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.applicant_info.occupation || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Pendapatan (RM)</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ formatIncome(form.applicant_info.income) }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Alamat Kediaman</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.applicant_info.home_address || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Poskod</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.applicant_info.postcode || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Negeri</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.applicant_info.state || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Daerah</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.applicant_info.district || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">No Telefon</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.applicant_info.phone_number || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Emel</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.applicant_info.email || '-' }}</div>
                  </div>
                </div>
              </section>

              <section>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">{{ stepTitles[2] }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Nama Perniagaan / Syarikat</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.company_name || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Nama Rumah Tumpangan</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.hotel_name || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Alamat Perniagaan (Premis)</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.company_address || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Poskod</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.company_postcode || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Negeri</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.company_state || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Daerah</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.company_district || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">No Telefon</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.company_phone || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">No Pendaftaran</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.company_registration_number || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Tarikh Daftar</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ formatDate(form.company_info.company_registration_date) || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Tarikh Tamat</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ formatDate(form.company_info.company_registration_expiry_date) || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Kategori Syarikat</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ formatCompanyCategory(form.company_info.company_category) }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Lokasi Premis</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ formatPremisesLocation(form.company_info.company_premises_location) }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Bilangan Pekerja Melayu</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.employee_malay || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Bilangan Pekerja Cina</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.employee_chinese || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Bilangan Pekerja India</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.employee_indian || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Bilangan Pekerja Lain-lain</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.employee_others || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Waktu Beroperasi (Mula)</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.company_operation_start || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Waktu Beroperasi (Tamat)</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.company_operation_end || '-' }}</div>
                  </div>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Alamat Perniagaan (Ibu Pejabat)</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.company_address_hq || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Poskod (Ibu Pejabat)</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.company_postcode_hq || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Negeri (Ibu Pejabat)</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.company_state_hq || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Daerah (Ibu Pejabat)</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.company_district_hq || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">No Telefon (Ibu Pejabat)</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.company_phone_hq || '-' }}</div>
                  </div>
                </div>
              </section>

              <section>
                
              </section>

              <section>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">{{ stepTitles[3] }}</h3>
                <div v-if="additionalActivitiesTotalAmount <= 0" class="text-sm text-slate-600 dark:text-slate-300">
                  Tiada aktiviti tambahan dipilih.
                </div>
                <div v-else class="space-y-3">
                  <h4 class="text-md font-semibold text-slate-900 dark:text-slate-100 mb-4">Kategori Lesen Yang Dipohon</h4>
                  <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                      <div>
                        <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Kategori Lesen</div>
                        <div class="text-sm text-slate-900 dark:text-slate-100">{{ selectedLicenseTypeLabel || '-' }}</div>
                      </div>
                      <div>
                        <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Bilangan Bilik</div>
                        <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.company_info.room_count || '-' }}</div>
                      </div>
                    </div>
                  </div>

                  <h4 class="text-md font-semibold text-slate-900 dark:text-slate-100 mb-4">Aktiviti Tambahan</h4>
                  <div
                    v-for="activity in selectedAdditionalActivities"
                    :key="`summary-${activity.id}`"
                    class="rounded-xl border border-slate-200 p-3 dark:border-slate-700"
                  >
                    <div class="mb-2 text-xs font-semibold text-slate-600 dark:text-slate-400">{{ activity.activity_name }}</div>
                    <div
                      v-for="(item, idx) in getSelectedRowsForActivity(activity)"
                      :key="`summary-${activity.id}-${idx}-${item.rate.id}`"
                      class="grid grid-cols-1 gap-3 border-t border-slate-200 py-2 first:border-t-0 dark:border-slate-700 md:grid-cols-4"
                    >
                      <div>
                        <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">No</div>
                        <div class="text-sm text-slate-900 dark:text-slate-100">{{ idx + 1 }}</div>
                      </div>
                      <div>
                        <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Jenis</div>
                        <div class="text-sm text-slate-900 dark:text-slate-100">{{ item.rate.type_name || '-' }}</div>
                      </div>
                      <div>
                        <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Keluasan (MPS)</div>
                        <div class="text-sm text-slate-900 dark:text-slate-100">{{ formatAreaRange(item.rate.min_area, item.rate.max_area) }}</div>
                      </div>
                      <div>
                        <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Amaun (RM)</div>
                        <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ formatAmount(item.rate.amount ?? 0) }}</div>
                      </div>
                    </div>
                  </div>

                  <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-right dark:border-slate-700 dark:bg-slate-900/50">
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-400">Jumlah Amaun Aktiviti Tambahan:</span>
                    <span class="ml-2 text-sm font-bold text-slate-900 dark:text-slate-100">RM{{ formatAmount(additionalActivitiesTotalAmount) }}</span>
                  </div>
                </div>
              </section>

              <section>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">{{ stepTitles[4] }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Memorandum / Borang A / Borang B / Borang D</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.document1Name || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Pelan lokasi premis beserta gambar premis</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.document2Name || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Pelan lantai premis / kawasan</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.document3Name || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Surat perjanjian / kebenaran tuan bangunan</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.document4Name || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Salinan geran tanah / LPS</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.document5Name || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Salinan CF/CCC (TCF)</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.document6Name || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Gambar pemohon berukuran passport</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.document7Name || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Salinan Kad Pengenalan pemohon</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.document8Name || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Senarai nama semua Pengendali Makanan</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.document9Name || '-' }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Carta Proses Pengeluaran</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">{{ form.document10Name || '-' }}</div>
                  </div>
                </div>
              </section>

              <section>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100 mb-4">{{ stepTitles[5] }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Fi Proses</div>
                    <div class="text-sm text-slate-900 dark:text-slate-100">RM{{ formattedProcessingFeeAmount }}</div>
                  </div>
                  <div>
                    <div class="text-xs font-semibold text-slate-600 dark:text-slate-400">Status Bayaran</div>
                    <div class="text-sm" :class="form.processing_fee_paid ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'">
                      {{ form.processing_fee_paid ? 'Sudah dibayar' : 'Belum dibayar' }}
                    </div>
                  </div>
                </div>
              </section>
            </div>
          </div>

          <label class="flex items-center gap-2 text-slate-700 dark:text-slate-200">
            <input type="checkbox" v-model="form.declaration.agree" />
            Dengan ini saya mengaku bahawa semua maklumat yang tercatat di dalam borang ini dan Lampiran-lampiran yang disertakan adalah betul dan benar.<br> Saya berjanji akan mematuhi segala syarat-syarat lesen yang ditetapkan.
          </label>
          <hr class="my-6 border-t border-gray-200 dark:border-slate-700" />
        </div>
      </div>

      <!-- Navigation -->
      <div class="mt-6 flex items-center">
        <div>
            <button v-if="step > 1"
                  @click="prevStep"
              class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-xl cursor-pointer dark:bg-slate-700 dark:hover:bg-slate-600 dark:text-slate-100">
            ← Kembali
          </button>
        </div>

        <div class="ml-auto">
          <button v-if="step < stepTitles.length"
                  @click="nextStep"
                  class="px-4 py-2 bg-[#2563EB] hover:bg-[#154dc5] dark:bg-[#60A5FA] text-white rounded-xl cursor-pointer">
            Seterusnya →
          </button>

          <div v-if="step === stepTitles.length" class="flex flex-col items-end gap-2">
            <button
              @click="submitForm"
              :disabled="!form.declaration.agree || !form.processing_fee_paid || isSubmitting"
              class="px-4 py-2 bg-black text-white rounded-xl cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed dark:bg-slate-100 dark:text-slate-900"
            >
              {{ isSubmitting ? 'Sedang dihantar...' : 'Hantar Permohonan' }}
            </button>

            <p
              v-if="!form.processing_fee_paid"
              class="text-sm text-amber-700 dark:text-amber-300"
            >
              Sila buat bayaran fi proses RM{{ formattedProcessingFeeAmount }} pada langkah "Bayaran Fi Proses" terlebih dahulu.
            </p>
          </div>
        </div>
      </div>

    </div>

  </AppLayout>
</template>
