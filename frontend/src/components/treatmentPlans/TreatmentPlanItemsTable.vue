<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import AppointmentStatusChip from '@/components/appointments/AppointmentStatusChip.vue'
import { useTreatmentPlansStore } from '@/stores/treatmentPlans'
import { useAuthStore } from '@/stores/auth'
import { isTreatmentPlanError } from '@/services/treatmentPlans'
import { parseServerDateTime } from '@/lib/date'
import { toothDisplayName } from '@/lib/teeth'
import type { TreatmentPlanItem, TreatmentPlanItemStatus } from '@/types/treatmentPlan'

/**
 * Plan Detail's item list (design doc §11, §21 Step 8) — row actions mirror
 * `ChartEntryListTable.vue`'s exact pattern (local `canWrite`/`canDelete` gates, edit emits to the
 * parent's dialog, complete/cancel call the store directly, delete goes through a `ConfirmDialog`)
 * rather than embedding transitions in `TreatmentPlanItemDialog.vue` itself — this module has no
 * per-item detail page, so the row is the only place these actions can live, same reasoning
 * `ChartEntryListTable.vue`'s own docblock gives. A plain, unpaginated `DataTable` (design doc
 * §21 Step 7's original choice) still holds: a plan's item count stays small (§13).
 */
defineProps<{ items: TreatmentPlanItem[] }>()

const emit = defineEmits<{ 'edit-item': [item: TreatmentPlanItem] }>()

const ITEM_STATUS_SEVERITY: Record<TreatmentPlanItemStatus, 'warn' | 'success' | 'secondary'> = {
  planned: 'warn',
  completed: 'success',
  cancelled: 'secondary',
}

const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const treatmentPlansStore = useTreatmentPlansStore()
const auth = useAuthStore()

// Matches every other write ability in this module (design doc §10): admin + dentist can
// edit/transition, delete is admin-only (data correction, not a clinical action).
const canWrite = () => auth.isAdmin || auth.isDentist
const canDelete = () => auth.isAdmin

// Adding items is draft-only (`TreatmentPlanService::addItem()`), but editing a still-`planned`
// item's non-offer fields (notes/phase) stays available on a presented/accepted/in-progress plan
// (§8) — so the Edit action here isn't gated on the parent plan's status at all, only on the item
// still being `planned`; `TreatmentPlanItemDialog.vue` itself decides which fields that plan
// status still allows (a terminal item falls back to its own notes-only submit path there).
function canEdit(item: TreatmentPlanItem): boolean {
  return canWrite() && item.status === 'planned'
}

function formatDateTime(value: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(
    parseServerDateTime(value),
  )
}

function surfaceLabel(surface: string): string {
  return t(`dentalChart.chart.surface.${surface}`)
}

async function completeItem(item: TreatmentPlanItem) {
  try {
    await treatmentPlansStore.completeItem(item.id)
    toast.add({ severity: 'success', summary: t('treatmentPlans.items.dialog.saved'), life: 3000 })
  } catch (err) {
    handleActionError(err)
  }
}

async function cancelItem(item: TreatmentPlanItem) {
  try {
    await treatmentPlansStore.cancelItem(item.id)
    toast.add({ severity: 'success', summary: t('treatmentPlans.items.dialog.saved'), life: 3000 })
  } catch (err) {
    handleActionError(err)
  }
}

function handleActionError(err: unknown) {
  if (isTreatmentPlanError(err)) {
    toast.add({ severity: 'error', summary: err.message, life: 4000 })
  } else {
    toast.add({ severity: 'error', summary: t('treatmentPlans.items.dialog.saveError'), life: 3000 })
  }
}

function confirmDelete(item: TreatmentPlanItem) {
  confirm.require({
    message: t('treatmentPlans.items.deleteConfirmMessage'),
    header: t('treatmentPlans.items.deleteConfirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await treatmentPlansStore.removeItem(item.id, item.treatment_plan_id)
        toast.add({ severity: 'success', summary: t('treatmentPlans.items.deleted'), life: 3000 })
      } catch {
        toast.add({ severity: 'error', summary: t('treatmentPlans.items.deleteError'), life: 3000 })
      }
    },
  })
}
</script>

<template>
  <DataTable :value="items" data-key="id">
    <template #empty>{{ t('treatmentPlans.items.empty') }}</template>

    <Column :header="t('treatmentPlans.items.procedure')">
      <template #body="{ data }">
        <div class="flex flex-col">
          <span>{{ data.procedure_name }}</span>
          <span v-if="data.procedure_description" class="text-xs text-surface-500 dark:text-surface-400">
            {{ data.procedure_description }}
          </span>
        </div>
      </template>
    </Column>

    <Column :header="t('treatmentPlans.items.tooth')">
      <template #body="{ data }">
        <span v-if="data.tooth_number"
          >{{ data.tooth_number }} — {{ toothDisplayName(data.tooth_number) }}</span
        >
        <span v-else>—</span>
      </template>
    </Column>

    <Column :header="t('treatmentPlans.items.surfaces')">
      <template #body="{ data }">
        <span v-if="data.surfaces?.length">{{ data.surfaces.map(surfaceLabel).join(', ') }}</span>
        <span v-else>—</span>
      </template>
    </Column>

    <Column :header="t('treatmentPlans.items.quantity')">
      <template #body="{ data }">{{ data.quantity }}</template>
    </Column>

    <Column :header="t('treatmentPlans.items.unitCost')">
      <template #body="{ data }"
        ><span dir="ltr">{{ data.unit_cost }}</span></template
      >
    </Column>

    <Column :header="t('treatmentPlans.items.subtotal')">
      <template #body="{ data }"
        ><span dir="ltr">{{ data.estimated_cost }}</span></template
      >
    </Column>

    <Column :header="t('treatmentPlans.items.status')">
      <template #body="{ data }">
        <Tag
          :value="t(`treatmentPlans.itemStatus.${data.status}`)"
          :severity="ITEM_STATUS_SEVERITY[data.status as TreatmentPlanItemStatus]"
        />
      </template>
    </Column>

    <Column :header="t('treatmentPlans.items.linked')">
      <template #body="{ data }">
        <div class="flex flex-col items-start gap-1">
          <Tag
            v-if="data.diagnosis_entry"
            severity="secondary"
            :value="
              t('treatmentPlans.items.diagnosisLink', {
                tooth: data.diagnosis_entry.tooth_number,
                status: t(`dentalChart.status.${data.diagnosis_entry.status}`),
              })
            "
          />
          <div v-if="data.appointment" class="flex items-center gap-1">
            <span class="text-xs text-surface-500 dark:text-surface-400" dir="ltr">{{
              formatDateTime(data.appointment.start_at)
            }}</span>
            <AppointmentStatusChip :status="data.appointment.status" size="small" />
          </div>
          <span v-if="!data.diagnosis_entry && !data.appointment" class="text-surface-400">—</span>
        </div>
      </template>
    </Column>

    <Column :header="t('treatmentPlans.items.actions')" style="width: 10rem">
      <template #body="{ data }">
        <div class="flex gap-1">
          <Button
            v-if="canEdit(data)"
            icon="pi pi-pencil"
            text
            rounded
            :aria-label="t('common.edit')"
            @click="emit('edit-item', data)"
          />
          <Button
            v-if="canWrite() && data.status === 'planned'"
            icon="pi pi-check"
            text
            rounded
            severity="success"
            :aria-label="t('treatmentPlans.items.completeItem')"
            @click="completeItem(data)"
          />
          <Button
            v-if="canWrite() && data.status === 'planned'"
            icon="pi pi-times"
            text
            rounded
            severity="danger"
            :aria-label="t('treatmentPlans.items.cancelItem')"
            @click="cancelItem(data)"
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
