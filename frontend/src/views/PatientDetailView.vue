<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Skeleton from 'primevue/skeleton'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { parseLocalDate, parseServerDateTime } from '@/lib/date'
import type { Patient, PatientAuditLog } from '@/types/patient'
import PatientFormDialog from '@/components/patients/PatientFormDialog.vue'
import PatientAppointmentsPanel from '@/components/appointments/PatientAppointmentsPanel.vue'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const auth = useAuthStore()

function formatDate(value: string) {
  return parseLocalDate(value).toLocaleDateString(locale.value)
}

function formatDateTime(value: string) {
  // `value` (an audit log's `created_at`) is the backend's naive-digits-labeled timestamp
  // (lib/date.ts) — must be read via `parseServerDateTime`, not a raw `new Date(...)`, or this
  // displays the wrong time in a browser whose OS timezone isn't UTC.
  return parseServerDateTime(value).toLocaleString(locale.value)
}

const canManage = ['admin', 'receptionist'].includes(auth.user?.role ?? '')
const canDelete = auth.isAdmin

const patientId = computed(() => route.params.id as string)
const patient = ref<Patient | null>(null)
const loading = ref(true)
const dialogVisible = ref(false)

const auditLogs = ref<PatientAuditLog[]>([])
const auditLoading = ref(false)

async function fetchPatient() {
  loading.value = true
  try {
    const { data } = await api.get<Patient>(`/patients/${patientId.value}`)
    patient.value = data
  } finally {
    loading.value = false
  }
}

async function fetchAuditLogs() {
  if (!auth.isAdmin) return

  auditLoading.value = true
  try {
    const { data } = await api.get<{ data: PatientAuditLog[] }>(`/patients/${patientId.value}/audit-logs`)
    auditLogs.value = data.data
  } finally {
    auditLoading.value = false
  }
}

function onSaved(updated: Patient) {
  patient.value = updated
  toast.add({ severity: 'success', summary: t('patients.saved'), life: 3000 })
  fetchAuditLogs()
}

function confirmDelete() {
  if (!patient.value) return

  confirm.require({
    message: t('patients.confirmDelete', { name: patient.value.full_name }),
    header: t('patients.deleteHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await api.delete(`/patients/${patientId.value}`)
        toast.add({ severity: 'success', summary: t('patients.deleted'), life: 3000 })
        router.push({ name: 'patients' })
      } catch {
        toast.add({ severity: 'error', summary: t('patients.deleteError'), life: 3000 })
      }
    },
  })
}

onMounted(() => {
  fetchPatient()
  fetchAuditLogs()
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" text rounded @click="router.push({ name: 'patients' })" />
        <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
          {{ patient?.full_name ?? t('patients.title') }}
        </h1>
        <Tag v-if="patient" :value="patient.patient_code" severity="secondary" />
      </div>
      <div v-if="patient" class="flex gap-2">
        <Button v-if="canManage" :label="t('common.edit')" icon="pi pi-pencil" @click="dialogVisible = true" />
        <Button
          v-if="canDelete"
          :label="t('common.delete')"
          icon="pi pi-trash"
          severity="danger"
          outlined
          @click="confirmDelete"
        />
      </div>
    </div>

    <Skeleton v-if="loading" height="20rem" />

    <div v-else-if="patient" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
      <Card>
        <template #title>{{ t('patients.sections.demographics') }}</template>
        <template #content>
          <dl class="grid grid-cols-2 gap-y-3 text-sm">
            <dt class="text-surface-500">{{ t('patients.dateOfBirth') }}</dt>
            <dd>{{ formatDate(patient.date_of_birth) }}</dd>
            <dt class="text-surface-500">{{ t('patients.gender') }}</dt>
            <dd>{{ t(`patients.genders.${patient.gender}`) }}</dd>
            <dt class="text-surface-500">{{ t('patients.bloodType') }}</dt>
            <dd>{{ patient.blood_type ?? '—' }}</dd>
            <dt class="text-surface-500">{{ t('patients.nationalId') }}</dt>
            <dd>{{ patient.national_id ?? '—' }}</dd>
          </dl>
        </template>
      </Card>

      <Card>
        <template #title>{{ t('patients.sections.contact') }}</template>
        <template #content>
          <dl class="grid grid-cols-2 gap-y-3 text-sm">
            <dt class="text-surface-500">{{ t('patients.phone') }}</dt>
            <dd>{{ patient.phone }}</dd>
            <dt class="text-surface-500">{{ t('patients.email') }}</dt>
            <dd>{{ patient.email ?? '—' }}</dd>
            <dt class="text-surface-500">{{ t('patients.address') }}</dt>
            <dd>{{ patient.address ?? '—' }}</dd>
            <dt class="text-surface-500">{{ t('patients.emergencyContactName') }}</dt>
            <dd>{{ patient.emergency_contact_name ?? '—' }}</dd>
            <dt class="text-surface-500">{{ t('patients.emergencyContactPhone') }}</dt>
            <dd>{{ patient.emergency_contact_phone ?? '—' }}</dd>
          </dl>
        </template>
      </Card>

      <Card>
        <template #title>{{ t('patients.sections.medical') }}</template>
        <template #content>
          <dl class="flex flex-col gap-3 text-sm">
            <div>
              <dt class="text-surface-500">{{ t('patients.allergies') }}</dt>
              <dd>{{ patient.allergies ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-surface-500">{{ t('patients.medicalHistory') }}</dt>
              <dd>{{ patient.medical_history ?? '—' }}</dd>
            </div>
          </dl>
        </template>
      </Card>

      <Card>
        <template #title>{{ t('patients.sections.insurance') }}</template>
        <template #content>
          <dl class="grid grid-cols-2 gap-y-3 text-sm">
            <dt class="text-surface-500">{{ t('patients.insuranceProvider') }}</dt>
            <dd>{{ patient.insurance_provider ?? '—' }}</dd>
            <dt class="text-surface-500">{{ t('patients.insuranceNumber') }}</dt>
            <dd>{{ patient.insurance_number ?? '—' }}</dd>
          </dl>
          <p v-if="patient.notes" class="mt-3 text-sm text-surface-500">{{ patient.notes }}</p>
        </template>
      </Card>

      <PatientAppointmentsPanel class="lg:col-span-2" :patient-id="patientId" :patient="patient" />

      <Card v-if="auth.isAdmin" class="lg:col-span-2">
        <template #title>{{ t('patients.sections.history') }}</template>
        <template #content>
          <DataTable :value="auditLogs" :loading="auditLoading" size="small">
            <Column field="action" :header="t('patients.history.action')" style="width: 8rem">
              <template #body="{ data }">
                <Tag :value="t(`patients.history.actions.${data.action}`)" />
              </template>
            </Column>
            <Column :header="t('patients.history.by')">
              <template #body="{ data }">{{ data.user?.name ?? t('patients.history.system') }}</template>
            </Column>
            <Column :header="t('patients.history.when')">
              <template #body="{ data }">{{ formatDateTime(data.created_at) }}</template>
            </Column>
          </DataTable>
        </template>
      </Card>
    </div>

    <PatientFormDialog v-model:visible="dialogVisible" :patient="patient" @saved="onSaved" />
  </div>
</template>
