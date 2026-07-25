<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import { useInvoicesStore } from '@/stores/invoices'
import { useAuthStore } from '@/stores/auth'
import { isInvoiceError } from '@/services/invoices'
import type { Invoice, InvoiceStatus } from '@/types/invoice'

/**
 * Invoice-level status transition buttons — mirrors `TreatmentPlanActionsBar.vue`'s exact shape:
 * visibility approximates the backend, the backend is still the real guard, no local emit needed
 * since `invoicesStore` already upserts every mutation response directly into the same reactive
 * cache `InvoiceDetailView.vue`'s `invoice` computed reads from.
 */
const props = defineProps<{ invoice: Invoice }>()

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const invoicesStore = useInvoicesStore()
const auth = useAuthStore()

// Matches every other write ability in this module (backend design doc §10): admin + receptionist
// only — billing is front-desk work, not a clinical action.
const canManage = computed(() => auth.isAdmin || auth.isReceptionist)

type InvoiceAction = 'issue' | 'void'

interface ActionRule {
  action: InvoiceAction
  visibleStatuses: InvoiceStatus[]
  icon: string
  severity: 'success' | 'danger'
  requiresConfirm: boolean
}

const RULES: ActionRule[] = [
  { action: 'issue', visibleStatuses: ['draft'], icon: 'pi pi-send', severity: 'success', requiresConfirm: true },
  { action: 'void', visibleStatuses: ['issued'], icon: 'pi pi-ban', severity: 'danger', requiresConfirm: true },
]

const busy = ref(false)

const visibleRules = computed(() =>
  canManage.value ? RULES.filter((rule) => rule.visibleStatuses.includes(props.invoice.status)) : [],
)

async function callAction(action: InvoiceAction): Promise<void> {
  const id = props.invoice.id

  if (action === 'issue') {
    await invoicesStore.issue(id)
  } else {
    await invoicesStore.voidInvoice(id)
  }
}

async function run(action: InvoiceAction): Promise<void> {
  busy.value = true

  try {
    await callAction(action)
    toast.add({ severity: 'success', summary: t('invoices.detail.actionSuccess'), life: 3000 })
  } catch (err) {
    await handleError(err)
  } finally {
    busy.value = false
  }
}

async function handleError(err: unknown): Promise<void> {
  if (isInvoiceError(err)) {
    toast.add({ severity: 'error', summary: err.message, life: 4000 })

    if (err.code === 'invalid_invoice_status_transition') {
      // Our visibility table was a stale approximation of the backend's real state (same class of
      // race as TreatmentPlanActionsBar's identical comment) — resync so the UI stops offering an
      // action the backend just rejected.
      await invoicesStore.fetchOne(props.invoice.id)
    }

    return
  }

  const status = (err as { response?: { status?: number } })?.response?.status

  if (status === 403) {
    toast.add({ severity: 'error', summary: t('invoices.forbidden'), life: 3000 })
  } else {
    toast.add({ severity: 'error', summary: t('invoices.detail.actionError'), life: 3000 })
  }
}

function trigger(rule: ActionRule): void {
  confirm.require({
    message: t(`invoices.detail.confirm.${rule.action}Message`),
    header: t(`invoices.detail.actions.${rule.action}`),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: rule.action === 'void' ? 'p-button-danger' : undefined,
    acceptLabel: t(`invoices.detail.actions.${rule.action}`),
    rejectLabel: t('common.cancel'),
    accept: () => run(rule.action),
  })
}
</script>

<template>
  <div v-if="visibleRules.length" class="flex flex-wrap gap-2">
    <Button
      v-for="rule in visibleRules"
      :key="rule.action"
      :label="t(`invoices.detail.actions.${rule.action}`)"
      :icon="rule.icon"
      :severity="rule.severity"
      :loading="busy"
      size="small"
      outlined
      @click="trigger(rule)"
    />
  </div>
</template>
