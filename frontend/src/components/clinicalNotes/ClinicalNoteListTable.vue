<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import ClinicalNoteStatusChip from './ClinicalNoteStatusChip.vue'
import { parseServerDateTime } from '@/lib/date'
import type { ClinicalNote } from '@/types/clinicalNote'

/**
 * Patient Clinical Notes tab's list table — mirrors `TreatmentPlanListTable.vue`'s pure/
 * presentational, client-paginated `DataTable` convention exactly:
 * `GET .../clinical-notes` is deliberately unpaginated (design doc §9, a patient's lifetime note
 * count stays small), so this table fetches nothing itself. Row click navigates to Note Detail.
 */
defineProps<{
  notes: ClinicalNote[]
  loading: boolean
}>()

const emit = defineEmits<{ (e: 'row-click', noteId: string): void }>()

const { t, locale } = useI18n()

function formatDateTime(value: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(
    parseServerDateTime(value),
  )
}

function truncate(value: string | null, max = 60): string {
  if (!value) return '—'
  return value.length > max ? `${value.slice(0, max)}…` : value
}
</script>

<template>
  <DataTable
    :value="notes"
    :loading="loading"
    data-key="id"
    paginator
    :rows="10"
    sort-field="created_at"
    :sort-order="-1"
    class="cursor-pointer"
    @row-click="({ data }) => emit('row-click', data.id)"
  >
    <template #empty>{{ t('clinicalNotes.list.empty') }}</template>

    <Column field="created_at" :header="t('clinicalNotes.list.date')" sortable>
      <template #body="{ data }">{{ formatDateTime(data.created_at) }}</template>
    </Column>

    <Column :header="t('clinicalNotes.list.type')">
      <template #body="{ data }">{{ t(`clinicalNotes.type.${data.note_type}`) }}</template>
    </Column>

    <Column :header="t('clinicalNotes.list.status')">
      <template #body="{ data }">
        <ClinicalNoteStatusChip :status="data.status" size="small" />
      </template>
    </Column>

    <Column :header="t('clinicalNotes.list.dentist')">
      <template #body="{ data }">{{ data.dentist?.name ?? '—' }}</template>
    </Column>

    <Column :header="t('clinicalNotes.list.chiefComplaint')">
      <template #body="{ data }">{{ truncate(data.chief_complaint) }}</template>
    </Column>
  </DataTable>
</template>
