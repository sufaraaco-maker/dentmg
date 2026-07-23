<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import TreatmentPlanStatusChip from './TreatmentPlanStatusChip.vue'
import { parseServerDateTime } from '@/lib/date'
import type { TreatmentPlan } from '@/types/treatmentPlan'

/**
 * Patient Treatment Plans tab's list table (design doc §11) — pure/presentational, mirroring
 * `AppointmentListTable.vue`'s client-paginated `DataTable` convention: `GET .../treatment-plans`
 * is deliberately unpaginated (design doc §9/§13, a patient's lifetime plan count stays small), so
 * this table fetches nothing itself and paginates client-side purely for a manageable row count.
 *
 * Row click navigates to the Plan Detail route (design doc §11/§16 item 2, Step 7) — the table
 * itself stays a pure/presentational emitter, same as `AppointmentListTable.vue`'s `row-click`.
 */
defineProps<{
  plans: TreatmentPlan[]
  loading: boolean
}>()

const emit = defineEmits<{ (e: 'row-click', planId: string): void }>()

const { t, locale } = useI18n()

function formatDate(createdAt: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(parseServerDateTime(createdAt))
}

function itemCount(plan: TreatmentPlan): number {
  return plan.items?.length ?? 0
}
</script>

<template>
  <DataTable
    :value="plans"
    :loading="loading"
    data-key="id"
    paginator
    :rows="10"
    sort-field="created_at"
    :sort-order="-1"
    class="cursor-pointer"
    @row-click="({ data }) => emit('row-click', data.id)"
  >
    <template #empty>{{ t('treatmentPlans.list.empty') }}</template>

    <Column field="title" :header="t('treatmentPlans.list.plan')">
      <template #body="{ data }">{{ data.title ?? t('treatmentPlans.list.untitled') }}</template>
    </Column>

    <Column :header="t('treatmentPlans.list.status')">
      <template #body="{ data }">
        <TreatmentPlanStatusChip :status="data.status" size="small" />
      </template>
    </Column>

    <Column :header="t('treatmentPlans.list.dentist')">
      <template #body="{ data }">{{ data.dentist?.name ?? '—' }}</template>
    </Column>

    <Column field="created_at" :header="t('treatmentPlans.list.createdAt')" sortable>
      <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
    </Column>

    <Column :header="t('treatmentPlans.list.items')">
      <template #body="{ data }">{{ itemCount(data) }}</template>
    </Column>

    <Column :header="t('treatmentPlans.list.estimatedCost')">
      <template #body="{ data }">
        <span dir="ltr">{{ data.estimated_cost ?? '0.00' }}</span>
      </template>
    </Column>
  </DataTable>
</template>
