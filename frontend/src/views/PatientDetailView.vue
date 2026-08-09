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
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import Select from 'primevue/select'
import { ArrowLeft, Pencil, Trash2 } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { usePatientsStore } from '@/stores/patients'
import { parseLocalDate, parseServerDateTime } from '@/lib/date'
import type { Patient } from '@/types/patient'
import PatientFormDialog from '@/components/patients/PatientFormDialog.vue'
import MedicalHistoryPanel from '@/components/medicalHistory/MedicalHistoryPanel.vue'
import PatientAppointmentsPanel from '@/components/appointments/PatientAppointmentsPanel.vue'
import PatientDentalChartPanel from '@/components/dental-chart/PatientDentalChartPanel.vue'
import PatientImagingPanel from '@/components/imaging/PatientImagingPanel.vue'
import PatientLabCasesPanel from '@/components/laboratory/PatientLabCasesPanel.vue'
import PatientDocumentsPanel from '@/components/documents/PatientDocumentsPanel.vue'
import PatientTreatmentPlansPanel from '@/components/treatmentPlans/PatientTreatmentPlansPanel.vue'
import PatientClinicalNotesPanel from '@/components/clinicalNotes/PatientClinicalNotesPanel.vue'
import PatientBillingPanel from '@/components/billing/PatientBillingPanel.vue'
import ActivityTimeline from '@/components/patients/ActivityTimeline.vue'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const auth = useAuthStore()
const patientsStore = usePatientsStore()

// Design doc §10/§15 Decision D: receptionists have NO access to Clinical Notes at all — the tab
// itself must not render for them, not just its write actions, matching `ClinicalNotePolicy`'s
// `viewAny` exactly (defense in depth: the backend still enforces this regardless of this check).
const canAccessClinicalNotes = computed(() => auth.isAdmin || auth.isDentist)

/**
 * Config-driven tab list (Phase 2.1 structure cleanup, design doc §17 2.1) — each future tab this
 * redesign adds (Laboratory, Documents, Timeline) extends this array for its label/visibility
 * instead of duplicating a `v-if` in both `TabList` and `TabPanels` separately, the way
 * `clinicalNotes` had to before this. `TabPanel` content still can't be generalized the same way —
 * each panel hosts a genuinely different component with different props — so `TabPanels` below
 * stays explicit per tab, but reads the same `visible` flag from here rather than re-deriving it.
 *
 * Phase 2.2: the former separate `invoices`/`payments` entries collapse into one `billing` entry
 * (design doc §0/§3/§4) — `PatientBillingPanel.vue` now composes the two reused-as-is panels
 * internally via its own `SelectButton` switcher, instead of them being sibling tabs here.
 *
 * Phase 2.3: `medicalHistory` added right after `overview`, matching the design doc §4 tab order
 * (Overview → Medical History → Dental Chart → ...) — all staff can read (allergies are
 * front-desk safety-relevant), same visibility shape as Dental Chart/Treatment Plans.
 *
 * Phase 2.4: `laboratory` added right after `imaging` (patient-laboratory-redesign-design.md §8.1)
 * — the umbrella doc's own IA grouping puts Imaging/Laboratory together as diagnostic/workflow
 * support tabs; this is the smallest change that honors that without reordering `appointments`,
 * which stays a standalone tab until Phase 2.6 retires it into Timeline. All staff can read
 * (matches `LabCasePolicy::viewAny`, open to all roles — not the narrower `clinicalNotes` shape).
 *
 * Phase 2.5: `documents` added last, after `billing` (patient-documents-redesign-design.md §8.1) —
 * matches the umbrella doc's IA rationale grouping Billing/Documents as administrative tabs, and
 * requires no reordering since `billing` was already the last entry. All staff can read (matches
 * `PatientDocumentPolicy::viewAny`, open to all roles).
 *
 * Phase 2.6b: `timeline` added last, after `documents` (patient-timeline-redesign-design.md §8.1)
 * — the final sub-phase's own tab, deliberately last since every other tab already existed before
 * Timeline aggregates across them. All staff can read: `PatientActivityPolicy::viewAny` gates only
 * the endpoint itself, with per-category server-side filtering doing the real access control
 * (design doc §7/§9A) — so unlike `clinicalNotes` this tab needs no role-based visibility gate.
 */
const tabDefinitions = computed(() => [
  { key: 'overview', labelKey: 'patients.tabs.overview', visible: true },
  { key: 'medicalHistory', labelKey: 'patients.tabs.medicalHistory', visible: true },
  { key: 'appointments', labelKey: 'patients.tabs.appointments', visible: true },
  { key: 'dentalChart', labelKey: 'patients.tabs.dentalChart', visible: true },
  { key: 'imaging', labelKey: 'patients.tabs.imaging', visible: true },
  { key: 'laboratory', labelKey: 'patients.tabs.laboratory', visible: true },
  { key: 'treatmentPlans', labelKey: 'patients.tabs.treatmentPlans', visible: true },
  { key: 'clinicalNotes', labelKey: 'patients.tabs.clinicalNotes', visible: canAccessClinicalNotes.value },
  { key: 'billing', labelKey: 'patients.tabs.billing', visible: true },
  { key: 'documents', labelKey: 'patients.tabs.documents', visible: true },
  { key: 'timeline', labelKey: 'patients.tabs.timeline', visible: true },
])

const visibleTabs = computed(() => tabDefinitions.value.filter((tab) => tab.visible))

// Mobile dropdown switcher (design doc §17 2.2 — "needed now since Billing's merge changes the tab
// count"): this view had no <768px fallback for its `TabList` before Phase 2.2. `activeTab` drives
// both the desktop `TabList` and this `Select`, via `Tabs`' controlled `v-model:value`.
const activeTab = ref('overview')

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

const auditLogs = computed(() => patientsStore.auditLogsForPatient(patientId.value))
const auditLoading = computed(() => patientsStore.auditLoading)

async function fetchPatient() {
  loading.value = true
  try {
    patient.value = await patientsStore.fetchOne(patientId.value)
  } finally {
    loading.value = false
  }
}

async function fetchAuditLogs() {
  if (!auth.isAdmin) return

  await patientsStore.fetchAuditLogs(patientId.value)
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
        await patientsStore.remove(patientId.value)
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
        <Button text rounded @click="router.push({ name: 'patients' })">
          <template #icon="{ class: iconClass }">
            <ArrowLeft :size="18" :class="iconClass" />
          </template>
        </Button>
        <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
          {{ patient?.full_name ?? t('patients.title') }}
        </h1>
        <Tag v-if="patient" :value="patient.patient_code" severity="secondary" />
      </div>
      <div v-if="patient" class="flex gap-2">
        <Button v-if="canManage" :label="t('common.edit')" @click="dialogVisible = true">
          <template #icon="{ class: iconClass }">
            <Pencil :size="16" :class="iconClass" />
          </template>
        </Button>
        <Button
          v-if="canDelete"
          :label="t('common.delete')"
          severity="danger"
          outlined
          @click="confirmDelete"
        >
          <template #icon="{ class: iconClass }">
            <Trash2 :size="16" :class="iconClass" />
          </template>
        </Button>
      </div>
    </div>

    <Skeleton v-if="loading" height="20rem" />

    <Tabs v-else-if="patient" v-model:value="activeTab">
      <div class="hidden md:block">
        <TabList>
          <Tab v-for="tab in visibleTabs" :key="tab.key" :value="tab.key">{{ t(tab.labelKey) }}</Tab>
        </TabList>
      </div>
      <Select
        v-model="activeTab"
        class="md:hidden"
        :options="visibleTabs"
        option-label="labelKey"
        option-value="key"
        fluid
        :aria-label="t('patients.tabSwitcher.label')"
      >
        <template #option="{ option }">{{ t(option.labelKey) }}</template>
        <template #value="{ value }">{{
          t(visibleTabs.find((tab) => tab.key === value)?.labelKey ?? '')
        }}</template>
      </Select>
      <TabPanels>
        <TabPanel value="overview">
          <div class="grid grid-cols-1 gap-4 pt-2 lg:grid-cols-2">
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

            <Card class="lg:col-span-2">
              <template #title>{{ t('patients.timelinePanel.overviewTitle') }}</template>
              <template #content>
                <ActivityTimeline
                  :patient-id="patientId"
                  compact
                  :page-size="5"
                  @view-all="activeTab = 'timeline'"
                />
              </template>
            </Card>

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
                    <template #body="{ data }">{{
                      data.user?.name ?? t('patients.history.system')
                    }}</template>
                  </Column>
                  <Column :header="t('patients.history.when')">
                    <template #body="{ data }">{{ formatDateTime(data.created_at) }}</template>
                  </Column>
                </DataTable>
              </template>
            </Card>
          </div>
        </TabPanel>

        <TabPanel value="medicalHistory">
          <div class="pt-2">
            <MedicalHistoryPanel :patient-id="patientId" />
          </div>
        </TabPanel>

        <TabPanel value="appointments">
          <div class="pt-2">
            <PatientAppointmentsPanel :patient-id="patientId" :patient="patient" />
          </div>
        </TabPanel>

        <TabPanel value="dentalChart">
          <div class="pt-2">
            <PatientDentalChartPanel :patient-id="patientId" />
          </div>
        </TabPanel>

        <TabPanel value="imaging">
          <div class="pt-2">
            <PatientImagingPanel :patient-id="patientId" />
          </div>
        </TabPanel>

        <TabPanel value="laboratory">
          <div class="pt-2">
            <PatientLabCasesPanel :patient-id="patientId" />
          </div>
        </TabPanel>

        <TabPanel value="treatmentPlans">
          <div class="pt-2">
            <PatientTreatmentPlansPanel :patient-id="patientId" />
          </div>
        </TabPanel>

        <TabPanel v-if="canAccessClinicalNotes" value="clinicalNotes">
          <div class="pt-2">
            <PatientClinicalNotesPanel :patient-id="patientId" />
          </div>
        </TabPanel>

        <TabPanel value="billing">
          <div class="pt-2">
            <PatientBillingPanel :patient-id="patientId" />
          </div>
        </TabPanel>

        <TabPanel value="documents">
          <div class="pt-2">
            <PatientDocumentsPanel :patient-id="patientId" />
          </div>
        </TabPanel>

        <TabPanel value="timeline">
          <div class="pt-2">
            <ActivityTimeline :patient-id="patientId" />
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <PatientFormDialog v-model:visible="dialogVisible" :patient="patient" @saved="onSaved" />
  </div>
</template>
