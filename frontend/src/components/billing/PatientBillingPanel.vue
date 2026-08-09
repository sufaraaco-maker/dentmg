<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import SelectButton from 'primevue/selectbutton'
import BillingSummaryCard from './BillingSummaryCard.vue'
import PatientInvoicesPanel from '@/components/invoices/PatientInvoicesPanel.vue'
import PatientPaymentsPanel from '@/components/payments/PatientPaymentsPanel.vue'
import ActivityTimeline from '@/components/patients/ActivityTimeline.vue'

/**
 * Billing tab shell (design doc §5.2/§17 Phase 2.2) — hosts the Outstanding Balance hero + Summary
 * row (`BillingSummaryCard.vue`) and a `SelectButton` switching between the existing
 * Invoices/Payments panels (reused as-is, unchanged) and Payment History, backed by
 * `ActivityTimeline.vue` filtered to `category="billing"` (Phase 2.6b). Purely a shell: no fetch
 * orchestration of its own beyond what the sub-panels already own.
 */
defineProps<{ patientId: string }>()

const { t } = useI18n()

type BillingSection = 'invoices' | 'payments' | 'paymentHistory'

const section = ref<BillingSection>('invoices')

const sectionOptions = [
  { label: t('patients.billingPanel.switcher.invoices'), value: 'invoices' as BillingSection },
  { label: t('patients.billingPanel.switcher.payments'), value: 'payments' as BillingSection },
  { label: t('patients.billingPanel.switcher.paymentHistory'), value: 'paymentHistory' as BillingSection },
]
</script>

<template>
  <div class="flex flex-col gap-4">
    <BillingSummaryCard :patient-id="patientId" />

    <SelectButton
      v-model="section"
      :options="sectionOptions"
      option-label="label"
      option-value="value"
      :allow-empty="false"
      :aria-label="t('patients.billingPanel.switcher.label')"
    />

    <PatientInvoicesPanel v-if="section === 'invoices'" :patient-id="patientId" />
    <PatientPaymentsPanel v-else-if="section === 'payments'" :patient-id="patientId" />
    <ActivityTimeline v-else :patient-id="patientId" category="billing" />
  </div>
</template>
