<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import ToothChart from './ToothChart.vue'
import ChartEntryDialog from './ChartEntryDialog.vue'
import { useDentalChartEntriesStore } from '@/stores/dentalChartEntries'
import { useDentalConditionsStore } from '@/stores/dentalConditions'
import { useAuthStore } from '@/stores/auth'
import type { DentalChartEntry, ToothSurface as ToothSurfaceCode } from '@/types/dentalChart'

/**
 * Patient Detail's "Dental Chart" tab (implementation plan §2.3) — owns the per-patient data
 * fetch and the create/edit dialog; `ToothChart.vue` stays pure layout/orchestration (chart/list
 * toggle, filters), unaware of stores. Mirrors `PatientAppointmentsPanel.vue`'s role as the
 * store-aware host a tab's content delegates to.
 */
const props = defineProps<{ patientId: string }>()

const { t } = useI18n()
const toast = useToast()
const entriesStore = useDentalChartEntriesStore()
const conditionsStore = useDentalConditionsStore()
const auth = useAuthStore()

// Receptionists get clinic-wide read access but no charting rights (DentalChartEntryPolicy::create,
// admin/dentist only) — gates both the toolbar's Add Entry button and click-to-create on the chart.
const canWrite = computed(() => auth.isAdmin || auth.isDentist)

const dialogVisible = ref(false)
const editingEntry = ref<DentalChartEntry | null>(null)
const prefill = ref<{ toothNumber?: string; surfaces?: ToothSurfaceCode[] } | null>(null)

function openCreate(payload?: { toothNumber?: string; surfaces?: ToothSurfaceCode[] }) {
  editingEntry.value = null
  prefill.value = payload ?? null
  dialogVisible.value = true
}

function openEdit(entry: DentalChartEntry) {
  editingEntry.value = entry
  prefill.value = null
  dialogVisible.value = true
}

function onSurfaceClick(payload: { tooth: string; surface: ToothSurfaceCode }) {
  openCreate({ toothNumber: payload.tooth, surfaces: [payload.surface] })
}

function onToothClick(payload: { tooth: string }) {
  openCreate({ toothNumber: payload.tooth })
}

async function load() {
  await Promise.all([entriesStore.fetchForPatient(props.patientId), conditionsStore.fetchAll()])
}

// See PatientAppointmentsPanel.vue's identical watcher: a failed fetch otherwise fails silently —
// this tab would just render an empty chart, indistinguishable from "no entries on file".
watch(
  () => entriesStore.error,
  (error) => {
    if (error) toast.add({ severity: 'error', summary: t(error), life: 3000 })
  },
)

watch(() => props.patientId, load, { immediate: true })
</script>

<template>
  <ToothChart
    :entries="entriesStore.entries"
    :loading="entriesStore.loading"
    :can-write="canWrite"
    @surface-click="onSurfaceClick"
    @tooth-click="onToothClick"
    @edit-entry="openEdit"
    @add-entry="() => openCreate()"
  />

  <ChartEntryDialog v-model:visible="dialogVisible" :patient-id="patientId" :entry="editingEntry" :prefill="prefill" />
</template>
