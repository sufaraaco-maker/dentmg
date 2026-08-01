<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { askSmartSearch } from '@/services/aiAssistant/aiAssistantApi'
import type { Patient } from '@/types/patient'
import PatientFormDialog from '@/components/patients/PatientFormDialog.vue'
import AiQuestionBox from '@/components/aiAssistant/AiQuestionBox.vue'

interface PaginatedPatients {
  data: Patient[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const auth = useAuthStore()

const canManage = ['admin', 'receptionist'].includes(auth.user?.role ?? '')
const canDelete = auth.isAdmin

const patients = ref<Patient[]>([])
const totalRecords = ref(0)
const perPage = ref(15)
const page = ref(1)
const search = ref('')
const loading = ref(false)

const dialogVisible = ref(false)
const editingPatient = ref<Patient | null>(null)

async function fetchPatients() {
  loading.value = true
  try {
    const { data } = await api.get<PaginatedPatients>('/patients', {
      params: { search: search.value || undefined, page: page.value },
    })
    patients.value = data.data
    totalRecords.value = data.meta.total
    perPage.value = data.meta.per_page
  } finally {
    loading.value = false
  }
}

function onPage(event: { page: number }) {
  page.value = event.page + 1
  fetchPatients()
}

let searchTimeout: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    fetchPatients()
  }, 300)
})

function openCreateDialog() {
  editingPatient.value = null
  dialogVisible.value = true
}

function openEditDialog(patient: Patient) {
  editingPatient.value = patient
  dialogVisible.value = true
}

function onSaved() {
  toast.add({ severity: 'success', summary: t('patients.saved'), life: 3000 })
  fetchPatients()
}

function viewPatient(patient: Patient) {
  router.push({ name: 'patient-detail', params: { id: patient.id } })
}

function confirmDelete(patient: Patient) {
  confirm.require({
    message: t('patients.confirmDelete', { name: patient.full_name }),
    header: t('patients.deleteHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await api.delete(`/patients/${patient.id}`)
        toast.add({ severity: 'success', summary: t('patients.deleted'), life: 3000 })
        await fetchPatients()
      } catch {
        toast.add({ severity: 'error', summary: t('patients.deleteError'), life: 3000 })
      }
    },
  })
}

onMounted(() => {
  fetchPatients()

  // Command Palette "New Patient" quick action (frontend-ux-redesign design doc §5.3) — a plain
  // `?new=1` query flag rather than a global create-dialog store, since this is the only view
  // wired to it in Phase 1; stripped immediately so a page refresh doesn't reopen the dialog.
  if (route.query.new === '1' && canManage) {
    openCreateDialog()
    router.replace({ query: {} })
  }
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('patients.title') }}
      </h1>
      <Button v-if="canManage" :label="t('patients.new')" icon="pi pi-plus" @click="openCreateDialog" />
    </div>

    <IconField class="max-w-sm">
      <InputIcon class="pi pi-search" />
      <InputText v-model="search" :placeholder="t('patients.search')" class="w-full" />
    </IconField>

    <AiQuestionBox
      :title="t('aiAssistant.smartSearch.title')"
      :placeholder="t('aiAssistant.smartSearch.placeholder')"
      :ask-fn="askSmartSearch"
    />

    <DataTable
      :value="patients"
      :loading="loading"
      lazy
      paginator
      :rows="perPage"
      :total-records="totalRecords"
      class="cursor-pointer"
      @page="onPage"
      @row-click="({ data }) => viewPatient(data)"
    >
      <Column field="patient_code" :header="t('patients.code')" style="width: 8rem" />
      <Column field="full_name" :header="t('patients.name')" />
      <Column field="phone" :header="t('patients.phone')" />
      <Column :header="t('patients.gender')" style="width: 8rem">
        <template #body="{ data }">
          <Tag :value="t(`patients.genders.${data.gender}`)" />
        </template>
      </Column>
      <Column :header="t('patients.actions')" style="width: 8rem">
        <template #body="{ data }">
          <div class="flex gap-2" @click.stop>
            <Button v-if="canManage" icon="pi pi-pencil" text rounded @click="openEditDialog(data)" />
            <Button
              v-if="canDelete"
              icon="pi pi-trash"
              text
              rounded
              severity="danger"
              @click="confirmDelete(data)"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <PatientFormDialog v-model:visible="dialogVisible" :patient="editingPatient" @saved="onSaved" />
  </div>
</template>
