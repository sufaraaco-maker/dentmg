<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Paginator from 'primevue/paginator'
import { AlertTriangle, HeartPulse, Pill } from 'lucide-vue-next'
import AllergyList from './AllergyList.vue'
import MedicalConditionList from './MedicalConditionList.vue'
import MedicationList from './MedicationList.vue'
import AllergyFormDialog from './AllergyFormDialog.vue'
import MedicalConditionFormDialog from './MedicalConditionFormDialog.vue'
import MedicationFormDialog from './MedicationFormDialog.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import { useMedicalHistoryStore } from '@/stores/medicalHistory'
import { useAuthStore } from '@/stores/auth'
import type { PatientAllergy, PatientMedicalCondition, PatientMedication } from '@/types/medicalHistory'

/**
 * Patient Detail's Medical History tab (design doc §6.3/§10) — owns the per-patient fetch for all
 * three sections and their create/edit dialogs, mirroring `PatientDentalChartPanel.vue`'s role as
 * the store-aware host a tab delegates to. `AllergyList`/`MedicalConditionList`/`MedicationList`
 * stay pure/presentational, matching `InvoiceListTable.vue`'s convention.
 */
const props = defineProps<{ patientId: string }>()

const { t } = useI18n()
const confirm = useConfirm()
const toast = useToast()
const store = useMedicalHistoryStore()
const auth = useAuthStore()

// Clinical judgment calls — Admin + Dentist only (design doc §6.4/§10), all staff can still read.
const canWrite = computed(() => auth.isAdmin || auth.isDentist)

const allergyDialogVisible = ref(false)
const editingAllergy = ref<PatientAllergy | null>(null)

const conditionDialogVisible = ref(false)
const editingCondition = ref<PatientMedicalCondition | null>(null)

const medicationDialogVisible = ref(false)
const editingMedication = ref<PatientMedication | null>(null)

function openCreateAllergy() {
  editingAllergy.value = null
  allergyDialogVisible.value = true
}

function openEditAllergy(allergy: PatientAllergy) {
  editingAllergy.value = allergy
  allergyDialogVisible.value = true
}

function confirmDeleteAllergy(allergy: PatientAllergy) {
  confirm.require({
    message: t('medicalHistory.allergies.confirmDelete', { name: allergy.allergen }),
    header: t('medicalHistory.dialog.confirmDeleteHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await store.removeAllergy(allergy.id)
        toast.add({ severity: 'success', summary: t('medicalHistory.dialog.deleted'), life: 3000 })
      } catch {
        toast.add({ severity: 'error', summary: t('medicalHistory.dialog.deleteError'), life: 3000 })
      }
    },
  })
}

function openCreateCondition() {
  editingCondition.value = null
  conditionDialogVisible.value = true
}

function openEditCondition(condition: PatientMedicalCondition) {
  editingCondition.value = condition
  conditionDialogVisible.value = true
}

function confirmDeleteCondition(condition: PatientMedicalCondition) {
  confirm.require({
    message: t('medicalHistory.conditions.confirmDelete', { name: condition.condition_name }),
    header: t('medicalHistory.dialog.confirmDeleteHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await store.removeCondition(condition.id)
        toast.add({ severity: 'success', summary: t('medicalHistory.dialog.deleted'), life: 3000 })
      } catch {
        toast.add({ severity: 'error', summary: t('medicalHistory.dialog.deleteError'), life: 3000 })
      }
    },
  })
}

function openCreateMedication() {
  editingMedication.value = null
  medicationDialogVisible.value = true
}

function openEditMedication(medication: PatientMedication) {
  editingMedication.value = medication
  medicationDialogVisible.value = true
}

function confirmDeleteMedication(medication: PatientMedication) {
  confirm.require({
    message: t('medicalHistory.medications.confirmDelete', { name: medication.medication_name }),
    header: t('medicalHistory.dialog.confirmDeleteHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await store.removeMedication(medication.id)
        toast.add({ severity: 'success', summary: t('medicalHistory.dialog.deleted'), life: 3000 })
      } catch {
        toast.add({ severity: 'error', summary: t('medicalHistory.dialog.deleteError'), life: 3000 })
      }
    },
  })
}

function onAllergyPage(event: { page: number }) {
  store.fetchAllergies(props.patientId, event.page + 1)
}

function onConditionPage(event: { page: number }) {
  store.fetchConditions(props.patientId, event.page + 1)
}

function onMedicationPage(event: { page: number }) {
  store.fetchMedications(props.patientId, event.page + 1)
}

// See PatientDentalChartPanel.vue's identical watcher: a failed fetch otherwise fails silently.
watch(
  () => store.error,
  (error) => {
    if (error) toast.add({ severity: 'error', summary: t(error), life: 3000 })
  },
)

watch(
  () => props.patientId,
  (patientId) => store.fetchForPatient(patientId),
  { immediate: true },
)
</script>

<template>
  <div class="flex flex-col gap-4">
    <Card>
      <template #title>
        <div class="flex items-center justify-between">
          <span>{{ t('medicalHistory.allergies.title') }}</span>
          <Button
            v-if="canWrite"
            :label="t('medicalHistory.allergies.add')"
            icon="pi pi-plus"
            size="small"
            @click="openCreateAllergy"
          />
        </div>
      </template>
      <template #content>
        <EmptyState
          v-if="!store.allergiesLoading && !store.allergies.length"
          :icon="AlertTriangle"
          :title="t('medicalHistory.allergies.empty')"
        />
        <AllergyList
          v-else
          :allergies="store.allergies"
          :loading="store.allergiesLoading"
          :can-write="canWrite"
          @edit="openEditAllergy"
          @delete="confirmDeleteAllergy"
        />
        <Paginator
          v-if="store.allergiesMeta.total > store.allergiesMeta.per_page"
          class="mt-4"
          :rows="store.allergiesMeta.per_page"
          :total-records="store.allergiesMeta.total"
          :first="(store.allergiesMeta.current_page - 1) * store.allergiesMeta.per_page"
          @page="onAllergyPage"
        />
      </template>
    </Card>

    <Card>
      <template #title>
        <div class="flex items-center justify-between">
          <span>{{ t('medicalHistory.conditions.title') }}</span>
          <Button
            v-if="canWrite"
            :label="t('medicalHistory.conditions.add')"
            icon="pi pi-plus"
            size="small"
            @click="openCreateCondition"
          />
        </div>
      </template>
      <template #content>
        <EmptyState
          v-if="!store.conditionsLoading && !store.conditions.length"
          :icon="HeartPulse"
          :title="t('medicalHistory.conditions.empty')"
        />
        <MedicalConditionList
          v-else
          :conditions="store.conditions"
          :loading="store.conditionsLoading"
          :can-write="canWrite"
          @edit="openEditCondition"
          @delete="confirmDeleteCondition"
        />
        <Paginator
          v-if="store.conditionsMeta.total > store.conditionsMeta.per_page"
          class="mt-4"
          :rows="store.conditionsMeta.per_page"
          :total-records="store.conditionsMeta.total"
          :first="(store.conditionsMeta.current_page - 1) * store.conditionsMeta.per_page"
          @page="onConditionPage"
        />
      </template>
    </Card>

    <Card>
      <template #title>
        <div class="flex items-center justify-between">
          <span>{{ t('medicalHistory.medications.title') }}</span>
          <Button
            v-if="canWrite"
            :label="t('medicalHistory.medications.add')"
            icon="pi pi-plus"
            size="small"
            @click="openCreateMedication"
          />
        </div>
      </template>
      <template #content>
        <EmptyState
          v-if="!store.medicationsLoading && !store.medications.length"
          :icon="Pill"
          :title="t('medicalHistory.medications.empty')"
        />
        <MedicationList
          v-else
          :medications="store.medications"
          :loading="store.medicationsLoading"
          :can-write="canWrite"
          @edit="openEditMedication"
          @delete="confirmDeleteMedication"
        />
        <Paginator
          v-if="store.medicationsMeta.total > store.medicationsMeta.per_page"
          class="mt-4"
          :rows="store.medicationsMeta.per_page"
          :total-records="store.medicationsMeta.total"
          :first="(store.medicationsMeta.current_page - 1) * store.medicationsMeta.per_page"
          @page="onMedicationPage"
        />
      </template>
    </Card>

    <AllergyFormDialog
      v-model:visible="allergyDialogVisible"
      :patient-id="patientId"
      :allergy="editingAllergy"
    />
    <MedicalConditionFormDialog
      v-model:visible="conditionDialogVisible"
      :patient-id="patientId"
      :condition="editingCondition"
    />
    <MedicationFormDialog
      v-model:visible="medicationDialogVisible"
      :patient-id="patientId"
      :medication="editingMedication"
    />
  </div>
</template>
