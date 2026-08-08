<script setup lang="ts">
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import ProgressSpinner from 'primevue/progressspinner'
import { useBillingSummaryStore } from '@/stores/billingSummary'
import { parseLocalDate } from '@/lib/date'
import type { BillingStatus } from '@/types/billing'

/**
 * Billing tab's Outstanding Balance hero + Summary stat row (design doc §4/§6.3), fed by
 * `GET /patients/{patient}/billing-summary` via `billingSummaryStore`.
 */
const props = defineProps<{ patientId: string }>()

const { t, locale } = useI18n()
const toast = useToast()
const store = useBillingSummaryStore()

// Same severity-map convention as `InvoiceStatusChip.vue`: `overdue` mirrors `void`'s `danger`,
// `paid` mirrors `issued`'s `success`, `partial`/`no_activity` are neutral/inactive.
const STATUS_SEVERITY: Record<BillingStatus, 'secondary' | 'success' | 'warn' | 'danger'> = {
  no_activity: 'secondary',
  paid: 'success',
  partial: 'warn',
  overdue: 'danger',
}

const summary = computed(() => store.summaryForPatient(props.patientId))

function formatDate(value: string | null): string {
  if (!value) return t('patients.billingPanel.never')
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(parseLocalDate(value))
}

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
  <Card>
    <template #content>
      <div v-if="store.loading && !summary" class="flex justify-center py-6">
        <ProgressSpinner style="width: 2rem; height: 2rem" stroke-width="4" />
      </div>
      <div v-else-if="summary" class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-sm text-surface-500">{{ t('patients.billingPanel.outstandingBalance') }}</p>
            <p class="text-3xl font-semibold" dir="ltr">
              {{ summary.outstanding_balance }} {{ summary.currency_code }}
            </p>
          </div>
          <Tag
            :value="t(`patients.billingPanel.statuses.${summary.status}`)"
            :severity="STATUS_SEVERITY[summary.status]"
          />
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <div>
            <p class="text-xs text-surface-500">{{ t('patients.billingPanel.totalInvoiced') }}</p>
            <p class="font-medium" dir="ltr">{{ summary.total_invoiced }} {{ summary.currency_code }}</p>
          </div>
          <div>
            <p class="text-xs text-surface-500">{{ t('patients.billingPanel.totalPaid') }}</p>
            <p class="font-medium" dir="ltr">{{ summary.total_paid }} {{ summary.currency_code }}</p>
          </div>
          <div>
            <p class="text-xs text-surface-500">{{ t('patients.billingPanel.invoiceCount') }}</p>
            <p class="font-medium">{{ summary.invoice_count }}</p>
          </div>
          <div>
            <p class="text-xs text-surface-500">{{ t('patients.billingPanel.lastPaymentDate') }}</p>
            <p class="font-medium">{{ formatDate(summary.last_payment_date) }}</p>
          </div>
        </div>
      </div>
    </template>
  </Card>
</template>
