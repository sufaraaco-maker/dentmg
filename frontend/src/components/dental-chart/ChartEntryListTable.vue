<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import { useDentalChartEntriesStore } from '@/stores/dentalChartEntries'
import { useAuthStore } from '@/stores/auth'
import { parseServerDateTime } from '@/lib/date'
import type {
  DentalChartEntry,
  DentalChartEntryStatus,
  ToothSurface as ToothSurfaceCode,
} from '@/types/dentalChart'

/**
 * Accessible non-visual-equivalent to the odontogram (design draft §18) — every fact the chart
 * shows must be reachable and fully operable from this table alone, so (unlike
 * `AppointmentListTable.vue`, which just routes a row-click to a separate detail page) row actions
 * live here directly: there is no per-entry detail page in this module. Renders whatever the parent
 * has already fetched/filtered — fetches nothing itself, mirrors `AppointmentListTable.vue`'s
 * client-paginated, sortable convention otherwise.
 */
defineProps<{
  entries: DentalChartEntry[]
  loading: boolean
}>()

const emit = defineEmits<{ 'edit-entry': [entry: DentalChartEntry] }>()

const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const entriesStore = useDentalChartEntriesStore()
const auth = useAuthStore()

const STATUS_SEVERITY: Record<DentalChartEntryStatus, 'secondary' | 'danger' | 'warn' | 'success'> = {
  existing: 'secondary',
  active: 'danger',
  planned: 'warn',
  completed: 'success',
  cancelled: 'secondary',
}

function surfaceLabel(surface: ToothSurfaceCode) {
  return t(`dentalChart.chart.surface.${surface}`)
}

function surfacesText(entry: DentalChartEntry): string {
  if (!entry.surfaces || entry.surfaces.length === 0) return t('dentalChart.dialog.wholeTooth')
  return entry.surfaces.map(surfaceLabel).join(', ')
}

function formatDate(recordedAt: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(
    parseServerDateTime(recordedAt),
  )
}

// Mirrors AppointmentActionsBar's "visibility approximates the backend, backend is still the real
// guard" note — these gate which buttons render, never a substitute for the Policy/Service checks
// already enforced server-side.
const canWrite = () => auth.isAdmin || auth.isDentist
const canDelete = () => auth.isAdmin

async function completeEntry(entry: DentalChartEntry) {
  try {
    await entriesStore.complete(entry.id)
    toast.add({ severity: 'success', summary: t('dentalChart.dialog.saved'), life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: t('dentalChart.dialog.saveError'), life: 3000 })
  }
}

async function cancelEntry(entry: DentalChartEntry) {
  try {
    await entriesStore.cancel(entry.id)
    toast.add({ severity: 'success', summary: t('dentalChart.dialog.saved'), life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: t('dentalChart.dialog.saveError'), life: 3000 })
  }
}

function confirmDelete(entry: DentalChartEntry) {
  confirm.require({
    message: t('dentalChart.list.deleteConfirmMessage'),
    header: t('dentalChart.list.deleteConfirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await entriesStore.remove(entry.id)
        toast.add({ severity: 'success', summary: t('dentalChart.list.deleted'), life: 3000 })
      } catch {
        toast.add({ severity: 'error', summary: t('dentalChart.list.deleteError'), life: 3000 })
      }
    },
  })
}
</script>

<template>
  <DataTable
    :value="entries"
    :loading="loading"
    data-key="id"
    paginator
    :rows="20"
    sort-field="tooth_number"
    :sort-order="1"
  >
    <template #empty>{{ t('dentalChart.list.empty') }}</template>

    <Column field="tooth_number" :header="t('dentalChart.dialog.tooth')" sortable />

    <Column :header="t('dentalChart.list.condition')">
      <template #body="{ data }">{{ data.dental_condition?.name ?? '—' }}</template>
    </Column>

    <Column :header="t('dentalChart.dialog.surfaces')">
      <template #body="{ data }">{{ surfacesText(data) }}</template>
    </Column>

    <Column field="status" :header="t('dentalChart.list.status')" sortable>
      <template #body="{ data }">
        <Tag
          :value="t(`dentalChart.status.${data.status}`)"
          :severity="STATUS_SEVERITY[data.status as DentalChartEntryStatus]"
        />
      </template>
    </Column>

    <Column :header="t('dentalChart.dialog.dentist')">
      <template #body="{ data }">{{ data.dentist?.name ?? '—' }}</template>
    </Column>

    <Column field="recorded_at" :header="t('dentalChart.list.recordedAt')" sortable>
      <template #body="{ data }">{{ formatDate(data.recorded_at) }}</template>
    </Column>

    <Column :header="t('dentalChart.conditions.actions')" style="width: 12rem">
      <template #body="{ data }">
        <div class="flex gap-1">
          <Button
            v-if="canWrite()"
            icon="pi pi-pencil"
            text
            rounded
            :aria-label="t('common.edit')"
            @click="emit('edit-entry', data)"
          />
          <Button
            v-if="canWrite() && data.status === 'planned'"
            icon="pi pi-check"
            text
            rounded
            severity="success"
            :aria-label="t('dentalChart.dialog.completeEntry')"
            @click="completeEntry(data)"
          />
          <Button
            v-if="canWrite() && data.status === 'planned'"
            icon="pi pi-times"
            text
            rounded
            severity="danger"
            :aria-label="t('dentalChart.dialog.cancelEntry')"
            @click="cancelEntry(data)"
          />
          <Button
            v-if="canDelete()"
            icon="pi pi-trash"
            text
            rounded
            severity="danger"
            :aria-label="t('common.delete')"
            @click="confirmDelete(data)"
          />
        </div>
      </template>
    </Column>
  </DataTable>
</template>
