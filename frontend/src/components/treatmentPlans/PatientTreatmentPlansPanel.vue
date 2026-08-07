<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Paginator from 'primevue/paginator'
import { ClipboardList } from 'lucide-vue-next'
import TreatmentPlanListTable from './TreatmentPlanListTable.vue'
import CreateTreatmentPlanDialog from './CreateTreatmentPlanDialog.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import { useTreatmentPlansStore } from '@/stores/treatmentPlans'
import { useAuthStore } from '@/stores/auth'

/**
 * Patient Detail's Treatment Plans tab (design doc §11) — owns the per-patient fetch and the
 * creation dialog; mirrors `PatientDentalChartPanel.vue`'s role as the store-aware host a tab
 * delegates to, since `treatmentPlansStore` (unlike `appointments.ts`'s date-range cache) already
 * exposes a single-patient `loading`/`error` pair well-suited to reading directly, no local
 * `loading` ref needed the way `PatientAppointmentsPanel.vue` requires one.
 *
 * Phase 2.1 (design doc §11/§14.4): the patient-scoped endpoint is now paginated — `page` here
 * mirrors `PatientImagingPanel.vue`'s `Paginator` idiom, the app's established pattern for a
 * patient-scoped paginated list.
 */
const props = defineProps<{ patientId: string }>()

const { t } = useI18n()
const router = useRouter()
const toast = useToast()
const treatmentPlansStore = useTreatmentPlansStore()
const auth = useAuthStore()

// Matches Dental Chart's identical matrix (design doc §10): admin + dentist can create/edit/
// transition plans, receptionist stays strictly read-only.
const canWrite = computed(() => auth.isAdmin || auth.isDentist)

const dialogVisible = ref(false)
const page = ref(1)

const plans = computed(() => treatmentPlansStore.plansForPatient(props.patientId))
const pageMeta = computed(() => treatmentPlansStore.pageMetaForPatient(props.patientId))

// See PatientAppointmentsPanel.vue's/PatientDentalChartPanel.vue's identical watcher: a failed
// fetch otherwise fails silently — this tab would just render its empty state, indistinguishable
// from "no plans on file".
watch(
  () => treatmentPlansStore.error,
  (error) => {
    if (error) toast.add({ severity: 'error', summary: t(error), life: 3000 })
  },
)

function goToDetail(planId: string) {
  router.push({ name: 'treatment-plan-detail', params: { id: props.patientId, planId } })
}

function onPage(event: { page: number }) {
  page.value = event.page + 1
  treatmentPlansStore.fetchForPatient(props.patientId, page.value)
}

function onSaved() {
  // `treatmentPlansStore.create()` already refreshes page 1 for this patient (a new plan always
  // sorts to the top) — reset the local page control to match what's now loaded.
  page.value = 1
  dialogVisible.value = false
}

watch(
  () => props.patientId,
  (patientId) => {
    page.value = 1
    treatmentPlansStore.fetchForPatient(patientId, 1)
  },
  { immediate: true },
)
</script>

<template>
  <Card v-bind="$attrs">
    <template #title>
      <div class="flex items-center justify-between">
        <span>{{ t('patients.treatmentPlansPanel.title') }}</span>
        <Button
          v-if="canWrite"
          :label="t('patients.treatmentPlansPanel.new')"
          icon="pi pi-plus"
          size="small"
          @click="dialogVisible = true"
        />
      </div>
    </template>
    <template #content>
      <EmptyState
        v-if="!treatmentPlansStore.loading && !plans.length"
        :icon="ClipboardList"
        :title="t('patients.treatmentPlansPanel.empty')"
      />
      <TreatmentPlanListTable
        v-else
        :plans="plans"
        :loading="treatmentPlansStore.loading"
        @row-click="goToDetail"
      />

      <Paginator
        v-if="pageMeta.total > pageMeta.perPage"
        class="mt-4"
        :rows="pageMeta.perPage"
        :total-records="pageMeta.total"
        :first="(page - 1) * pageMeta.perPage"
        @page="onPage"
      />
    </template>
  </Card>

  <CreateTreatmentPlanDialog v-model:visible="dialogVisible" :patient-id="patientId" @saved="onSaved" />
</template>
