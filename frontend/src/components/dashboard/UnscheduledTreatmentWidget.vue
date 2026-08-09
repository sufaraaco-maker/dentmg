<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'
import { CalendarClock } from 'lucide-vue-next'
import EmptyState from '@/components/common/EmptyState.vue'
import { parseServerDateTime } from '@/lib/date'
import type { DashboardSummary } from '@/types/dashboard'

/**
 * Open to every role (design doc §1.1/§3.2) — accepted treatment that's neither scheduled nor
 * completed, capped and summary-only (a full drill-down report is out of scope, design doc §7).
 * Data comes from the parent's `dashboard.summary` fetch, not a self-fetch (unlike
 * `DueLabCasesWidget.vue`'s standalone pattern) — this widget's data is already bundled into the
 * one `/dashboard/summary` call.
 */
const props = defineProps<{
  data: DashboardSummary['unscheduled_accepted_treatment'] | null
  loading: boolean
}>()

const { t, locale } = useI18n()
const router = useRouter()

function formatAcceptedAt(value: string | null): string {
  if (!value) return ''
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(parseServerDateTime(value))
}

function viewPlan(patientId: string) {
  router.push({ name: 'patient-detail', params: { id: patientId }, query: { tab: 'treatmentPlans' } })
}
</script>

<template>
  <Card>
    <template #title>{{ t('dashboard.unscheduledTreatment.title') }}</template>
    <template #content>
      <div v-if="props.loading && !props.data" class="flex flex-col gap-3">
        <Skeleton height="1.5rem" />
        <Skeleton height="1.5rem" />
        <Skeleton height="1.5rem" />
      </div>
      <EmptyState
        v-else-if="props.data && props.data.count === 0"
        :icon="CalendarClock"
        :title="t('dashboard.unscheduledTreatment.emptyTitle')"
        :description="t('dashboard.unscheduledTreatment.emptyDescription')"
      />
      <ul v-else-if="props.data" class="flex flex-col divide-y divide-surface-200 dark:divide-surface-700">
        <li
          v-for="item in props.data.items"
          :key="`${item.treatment_plan_id}-${item.item_description}`"
          class="flex items-center justify-between gap-3 py-2"
        >
          <div class="min-w-0">
            <p class="truncate text-sm font-medium text-surface-900 dark:text-surface-0">
              {{ item.patient }}
            </p>
            <p class="truncate text-xs text-surface-500">
              {{ item.item_description }}
              <span v-if="item.accepted_at">· {{ formatAcceptedAt(item.accepted_at) }}</span>
            </p>
          </div>
          <Button
            :label="t('dashboard.unscheduledTreatment.viewPlan')"
            text
            size="small"
            class="shrink-0"
            @click="viewPlan(item.patient_id)"
          />
        </li>
      </ul>
    </template>
  </Card>
</template>
