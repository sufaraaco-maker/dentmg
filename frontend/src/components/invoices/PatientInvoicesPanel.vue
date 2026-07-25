<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import Button from 'primevue/button'
import InvoiceListTable from './InvoiceListTable.vue'
import { useInvoicesStore } from '@/stores/invoices'
import { useAuthStore } from '@/stores/auth'

/**
 * Patient Detail's Invoices tab — owns the per-patient fetch, mirroring
 * `PatientTreatmentPlansPanel.vue`'s role as the store-aware host a tab delegates to. Unlike
 * Treatment Plans (which requires a `dentist_id` up front, hence a creation dialog), a new
 * invoice has no required fields at all (backend design doc §9) — "New Invoice" creates the
 * `draft` immediately and navigates straight to Invoice Detail, matching the backend's own
 * documented workflow ("start a new invoice ... freely editable", §3) rather than interposing an
 * empty form first.
 */
const props = defineProps<{ patientId: string }>()

const { t } = useI18n()
const router = useRouter()
const toast = useToast()
const invoicesStore = useInvoicesStore()
const auth = useAuthStore()

// Billing is front-desk/administrative work, not a clinical action (backend design doc §10):
// admin + receptionist write, dentist read-only — the inverse split from Treatment Plans/Dental
// Chart, matching Patients' own identical permission split.
const canWrite = computed(() => auth.isAdmin || auth.isReceptionist)

const creating = ref(false)

const invoices = computed(() => invoicesStore.invoicesForPatient(props.patientId))

// See PatientAppointmentsPanel.vue's/PatientTreatmentPlansPanel.vue's identical watcher: a failed
// fetch otherwise fails silently — this tab would just render its empty state, indistinguishable
// from "no invoices on file".
watch(
  () => invoicesStore.error,
  (error) => {
    if (error) toast.add({ severity: 'error', summary: t(error), life: 3000 })
  },
)

function goToDetail(invoiceId: string) {
  router.push({ name: 'invoice-detail', params: { id: props.patientId, invoiceId } })
}

async function createInvoice() {
  creating.value = true
  try {
    const invoice = await invoicesStore.create(props.patientId)
    goToDetail(invoice.id)
  } catch {
    toast.add({ severity: 'error', summary: t('invoices.createError'), life: 3000 })
  } finally {
    creating.value = false
  }
}

watch(() => props.patientId, (patientId) => invoicesStore.fetchForPatient(patientId), { immediate: true })
</script>

<template>
  <Card v-bind="$attrs">
    <template #title>
      <div class="flex items-center justify-between">
        <span>{{ t('patients.invoicesPanel.title') }}</span>
        <Button
          v-if="canWrite"
          :label="t('patients.invoicesPanel.new')"
          icon="pi pi-plus"
          size="small"
          :loading="creating"
          @click="createInvoice"
        />
      </div>
    </template>
    <template #content>
      <p v-if="!invoicesStore.loading && !invoices.length" class="text-sm text-surface-500">
        {{ t('patients.invoicesPanel.empty') }}
      </p>
      <InvoiceListTable
        v-else
        :invoices="invoices"
        :loading="invoicesStore.loading"
        @row-click="goToDetail"
      />
    </template>
  </Card>
</template>
