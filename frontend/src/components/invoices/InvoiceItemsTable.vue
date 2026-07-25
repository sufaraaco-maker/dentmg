<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import { useInvoicesStore } from '@/stores/invoices'
import { useAuthStore } from '@/stores/auth'
import { isInvoiceError } from '@/services/invoices'
import type { Invoice, InvoiceItem, InvoiceItemKind } from '@/types/invoice'

/**
 * Invoice Detail's line-item list (backend design doc §11) — row actions mirror
 * `TreatmentPlanItemsTable.vue`'s exact pattern (local `canWrite`/`canDelete` gates, edit emits to
 * the parent's dialog, delete goes through a `ConfirmDialog` and applies the returned Invoice
 * itself rather than needing a follow-up fetch — backend design doc §9's deliberate divergence
 * from Treatment Plan Items' `204`). Charge/discount/tax are visually distinguished per severity
 * tag (design doc §11) rather than a one-off color: discount amounts are shown with a leading
 * minus sign for clarity even though the stored `amount` is always positive (§6).
 */
const props = defineProps<{ invoice: Invoice; items: InvoiceItem[] }>()

const emit = defineEmits<{ 'edit-item': [item: InvoiceItem] }>()

const KIND_SEVERITY: Record<InvoiceItemKind, 'secondary' | 'success' | 'info'> = {
  charge: 'secondary',
  discount: 'success',
  tax: 'info',
}

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const invoicesStore = useInvoicesStore()
const auth = useAuthStore()

// Matches every other write ability in this module (backend design doc §10): admin + receptionist
// can add/edit, delete is the same pair too (unlike Treatment Plans, item removal here isn't
// gated admin-only — InvoiceItemPolicy has no separate cancel/void action on an item, so removing
// a still-draft line is normal invoice-building work, not a stricter data-correction action).
const canWrite = () => auth.isAdmin || auth.isReceptionist
// Items are only ever mutable while the parent invoice is still draft
// (`InvoiceService::assertEditable()`).
const canEdit = () => canWrite() && props.invoice.status === 'draft'

function displayAmount(item: InvoiceItem): string {
  return item.kind === 'discount' ? `-${item.amount}` : item.amount
}

function confirmDelete(item: InvoiceItem) {
  confirm.require({
    message: t('invoices.items.deleteConfirmMessage'),
    header: t('invoices.items.deleteConfirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await invoicesStore.removeItem(item.id)
        toast.add({ severity: 'success', summary: t('invoices.items.deleted'), life: 3000 })
      } catch (err) {
        if (isInvoiceError(err)) {
          toast.add({ severity: 'error', summary: err.message, life: 4000 })
        } else {
          toast.add({ severity: 'error', summary: t('invoices.items.deleteError'), life: 3000 })
        }
      }
    },
  })
}
</script>

<template>
  <DataTable :value="items" data-key="id">
    <template #empty>{{ t('invoices.items.empty') }}</template>

    <Column :header="t('invoices.items.kindLabel')" style="width: 8rem">
      <template #body="{ data }">
        <Tag
          :value="t(`invoices.items.kind.${data.kind}`)"
          :severity="KIND_SEVERITY[data.kind as InvoiceItemKind]"
        />
      </template>
    </Column>

    <Column :header="t('invoices.items.description')">
      <template #body="{ data }">
        <div class="flex flex-col">
          <span>{{ data.description }}</span>
          <span v-if="data.treatment_plan_item" class="text-xs text-surface-500 dark:text-surface-400">
            {{ t('invoices.items.linkedFromPlan') }}
          </span>
        </div>
      </template>
    </Column>

    <Column :header="t('invoices.items.quantity')">
      <template #body="{ data }">{{ data.quantity }}</template>
    </Column>

    <Column :header="t('invoices.items.unitAmount')">
      <template #body="{ data }"
        ><span dir="ltr">{{ data.unit_amount }}</span></template
      >
    </Column>

    <Column :header="t('invoices.items.amount')">
      <template #body="{ data }">
        <span dir="ltr" :class="data.kind === 'discount' ? 'text-green-600 dark:text-green-400' : undefined">
          {{ displayAmount(data) }}
        </span>
      </template>
    </Column>

    <Column :header="t('invoices.items.actions')" style="width: 8rem">
      <template #body="{ data }">
        <div class="flex gap-1">
          <Button
            v-if="canEdit()"
            icon="pi pi-pencil"
            text
            rounded
            :aria-label="t('common.edit')"
            @click="emit('edit-item', data)"
          />
          <Button
            v-if="canEdit()"
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
