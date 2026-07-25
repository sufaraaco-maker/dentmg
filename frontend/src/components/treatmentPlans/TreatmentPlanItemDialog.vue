<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Message from 'primevue/message'
import ToothSurface from '@/components/dental-chart/ToothSurface.vue'
import { useTreatmentPlansStore } from '@/stores/treatmentPlans'
import { useDentalConditionsStore } from '@/stores/dentalConditions'
import { isTreatmentPlanError } from '@/services/treatmentPlans'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import { TOOTH_CODES, toothDisplayName } from '@/lib/teeth'
import { WHOLE_TOOTH_PATH, surfaceRegionsFor } from '@/lib/toothGeometry'
import type { TreatmentPlan, TreatmentPlanItem } from '@/types/treatmentPlan'
import type { ToothSurface as ToothSurfaceCode } from '@/types/dentalChart'

const NOTES_MAX = 2000

/**
 * Add/Edit Item dialog (design doc §11, §21 Step 8) — diagnosis-entry and appointment linkage are
 * explicitly out of scope here (Step 9); this dialog only ever writes the five "offer" fields
 * (`dental_condition_id`/`tooth_number`/`surfaces`/`quantity`/`unit_cost`) plus `phase`/`notes`.
 * Structurally closest to `ChartEntryDialog.vue` (tooth/surface picker, catalog select, locked-vs-
 * editable split) but simpler: no Diagnosis/Procedure tabs (every item is `category = procedure`),
 * no dentist/status/recorded-at fields, and two independent lock reasons instead of one — see
 * `isTerminal`/`isOfferLocked` below, matching `TreatmentPlanService::updateItem()` exactly.
 */
const props = defineProps<{
  visible: boolean
  plan: TreatmentPlan
  item?: TreatmentPlanItem | null
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: [plan: TreatmentPlan]
}>()

const { t } = useI18n()
const toast = useToast()
const treatmentPlansStore = useTreatmentPlansStore()
const conditionsStore = useDentalConditionsStore()

const isEditMode = computed(() => Boolean(props.item))
// A terminal item (completed/cancelled) only ever accepts a notes-only payload — the backend
// rejects any other field even unchanged (`TreatmentPlanService::updateItem()`'s array_diff_key
// guard, design doc §8).
const isTerminal = computed(() => isEditMode.value && props.item!.status !== 'planned')
// The five "offer" fields freeze once the parent plan leaves `draft` (design doc §6/§8, Decision
// 2) — independent of the item's own status, so a still-`planned` item on a `presented` plan can
// have its notes/phase edited but not its procedure/tooth/cost.
const isOfferLocked = computed(() => isEditMode.value && !isTerminal.value && props.plan.status !== 'draft')
const fieldsDisabled = computed(() => isTerminal.value || isOfferLocked.value)

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const costTouched = ref(false)

const procedureOptions = computed(() =>
  conditionsStore.items
    .filter((c) => c.is_active && c.category === 'procedure')
    .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
    .map((c) => ({ label: c.name, value: c.id })),
)

const toothOptions = computed(() =>
  TOOTH_CODES.map((code) => ({ label: `${code} — ${toothDisplayName(code)}`, value: code })),
)

function emptyForm() {
  return {
    dental_condition_id: null as string | null,
    tooth_number: null as string | null,
    surfaces: [] as ToothSurfaceCode[],
    quantity: 1,
    unit_cost: null as number | null,
    phase: 1 as number | null,
    notes: '',
  }
}

const form = reactive(emptyForm())

const selectedCondition = computed(() =>
  form.dental_condition_id ? conditionsStore.items.find((c) => c.id === form.dental_condition_id) : undefined,
)
const requiresSurfaces = computed(() => selectedCondition.value?.applies_to_surface === true)

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return

    errors.value = {}
    costTouched.value = false
    const source = props.item

    if (source) {
      Object.assign(form, {
        dental_condition_id: source.dental_condition_id,
        tooth_number: source.tooth_number,
        surfaces: source.surfaces ? [...source.surfaces] : [],
        quantity: source.quantity,
        unit_cost: Number(source.unit_cost),
        phase: source.phase,
        notes: source.notes ?? '',
      })
      costTouched.value = true
    } else {
      Object.assign(form, emptyForm())
    }
  },
  { immediate: true },
)

// Picking a procedure prefills its catalog default_cost (design doc §11) — but only while the
// user hasn't already typed a cost of their own; editing the cost field flips `costTouched` so a
// later procedure change never silently overwrites a deliberate manual entry.
watch(
  () => form.dental_condition_id,
  () => {
    if (fieldsDisabled.value || costTouched.value) return
    const defaultCost = selectedCondition.value?.default_cost
    form.unit_cost = defaultCost !== null && defaultCost !== undefined ? Number(defaultCost) : null
  },
)

function onCostInput() {
  costTouched.value = true
}

useDialogFocusRestore(() => props.visible)

const surfaceRegions = computed(() => (form.tooth_number ? surfaceRegionsFor(form.tooth_number) : []))

function toggleSurface(surface: ToothSurfaceCode) {
  if (fieldsDisabled.value) return
  const i = form.surfaces.indexOf(surface)
  if (i === -1) form.surfaces.push(surface)
  else form.surfaces.splice(i, 1)
}

function surfaceLabel(surface: ToothSurfaceCode) {
  return t(`dentalChart.chart.surface.${surface}`)
}

const notesRemaining = computed(() => NOTES_MAX - form.notes.length)
const showNotesCounter = computed(() => form.notes.length > NOTES_MAX * 0.8)

function validate(): boolean {
  const nextErrors: Record<string, string[]> = {}

  if (!form.dental_condition_id)
    nextErrors.dental_condition_id = [t('treatmentPlans.items.dialog.fieldRequired')]
  if (requiresSurfaces.value && !form.tooth_number) {
    // A surface-applicable procedure needs a tooth before the surface picker (and its own
    // `errors.surfaces` message) can even render (`v-if="requiresSurfaces && form.tooth_number"`
    // below) — flagging this on the always-visible tooth field instead of the hidden surfaces
    // block is what actually gets the user unstuck, mirroring the backend's real requirement
    // (`ValidDentalChartSurfaces` + `tooth_number`'s `required_with:surfaces`, design doc §8).
    nextErrors.tooth_number = [t('treatmentPlans.items.dialog.fieldRequired')]
  } else if (requiresSurfaces.value && form.surfaces.length === 0) {
    nextErrors.surfaces = [t('treatmentPlans.items.dialog.surfacesRequired')]
  }
  if (form.surfaces.length > 0 && !form.tooth_number) {
    nextErrors.tooth_number = [t('treatmentPlans.items.dialog.toothRequiredWithSurfaces')]
  }
  if (!form.quantity || form.quantity < 1) {
    nextErrors.quantity = [t('treatmentPlans.items.dialog.fieldRequired')]
  }

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (isTerminal.value) {
    await submitNotesOnly()
    return
  }

  if (isOfferLocked.value) {
    await submitUnlockedFieldsOnly()
    return
  }

  if (!validate()) return

  saving.value = true
  errors.value = {}

  try {
    const surfaces = requiresSurfaces.value ? form.surfaces : []
    const payload = {
      dental_condition_id: form.dental_condition_id!,
      tooth_number: form.tooth_number,
      surfaces,
      quantity: form.quantity,
      unit_cost: form.unit_cost,
      phase: form.phase,
      notes: form.notes || null,
    }

    const saved = isEditMode.value
      ? await treatmentPlansStore.updateItem(props.item!.id, payload)
      : await treatmentPlansStore.addItem(props.plan.id, payload)

    toast.add({ severity: 'success', summary: t('treatmentPlans.items.dialog.saved'), life: 3000 })
    emit('saved', saved)
    emit('update:visible', false)
  } catch (err: unknown) {
    handleSaveError(err)
  } finally {
    saving.value = false
  }
}

// Offer fields are frozen once the plan leaves draft — only phase/notes are still writable
// (`TreatmentPlanService::updateItem()`'s OFFER_FIELDS check, design doc §8).
async function submitUnlockedFieldsOnly() {
  saving.value = true
  errors.value = {}

  try {
    const saved = await treatmentPlansStore.updateItem(props.item!.id, {
      phase: form.phase,
      notes: form.notes || null,
    })
    toast.add({ severity: 'success', summary: t('treatmentPlans.items.dialog.saved'), life: 3000 })
    emit('saved', saved)
    emit('update:visible', false)
  } catch (err: unknown) {
    handleSaveError(err)
  } finally {
    saving.value = false
  }
}

async function submitNotesOnly() {
  saving.value = true
  errors.value = {}

  try {
    const saved = await treatmentPlansStore.updateItem(props.item!.id, { notes: form.notes || null })
    toast.add({ severity: 'success', summary: t('treatmentPlans.items.dialog.saved'), life: 3000 })
    emit('saved', saved)
    emit('update:visible', false)
  } catch (err: unknown) {
    handleSaveError(err)
  } finally {
    saving.value = false
  }
}

function handleSaveError(err: unknown): void {
  if (isTreatmentPlanError(err)) {
    toast.add({ severity: 'error', summary: err.message, life: 4000 })
  } else if ((err as { response?: { status?: number } })?.response?.status === 422) {
    errors.value =
      (err as { response: { data: { errors?: Record<string, string[]> } } }).response.data.errors ?? {}
  } else {
    toast.add({ severity: 'error', summary: t('treatmentPlans.items.dialog.saveError'), life: 3000 })
  }
}
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    :header="
      isEditMode ? t('treatmentPlans.items.dialog.editTitle') : t('treatmentPlans.items.dialog.createTitle')
    "
    class="w-full max-w-2xl"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('treatmentPlans.items.dialog.procedure')
        }}</label>
        <Select
          v-model="form.dental_condition_id"
          :options="procedureOptions"
          option-label="label"
          option-value="value"
          filter
          autofocus
          :placeholder="t('treatmentPlans.items.dialog.selectProcedure')"
          :disabled="fieldsDisabled"
          :invalid="!!errors.dental_condition_id"
          fluid
        />
        <Message v-if="errors.dental_condition_id" severity="error" size="small">{{
          errors.dental_condition_id[0]
        }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('treatmentPlans.items.dialog.tooth')
        }}</label>
        <Select
          v-model="form.tooth_number"
          :options="toothOptions"
          option-label="label"
          option-value="value"
          filter
          show-clear
          :placeholder="t('treatmentPlans.items.dialog.selectTooth')"
          :disabled="fieldsDisabled"
          :invalid="!!errors.tooth_number"
          fluid
        />
        <Message v-if="errors.tooth_number" severity="error" size="small">{{
          errors.tooth_number[0]
        }}</Message>
      </div>

      <div v-if="requiresSurfaces && form.tooth_number" class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('treatmentPlans.items.dialog.surfaces')
        }}</label>
        <svg width="140" height="140" viewBox="0 0 100 100" dir="ltr" class="overflow-visible">
          <ToothSurface
            v-for="region in surfaceRegions"
            :key="region.key"
            :path="region.path"
            :glyph-center="region.center"
            :fill="form.surfaces.includes(region.surface) ? 'var(--p-primary-400)' : 'var(--p-surface-100)'"
            :selected="form.surfaces.includes(region.surface)"
            :interactive="!fieldsDisabled"
            :label="surfaceLabel(region.surface)"
            @activate="toggleSurface(region.surface)"
          />
        </svg>
        <p class="text-sm text-surface-500 dark:text-surface-400">
          {{
            form.surfaces.length
              ? form.surfaces.map(surfaceLabel).join(', ')
              : t('dentalChart.dialog.noSurfacesSelected')
          }}
        </p>
        <Message v-if="errors.surfaces" severity="error" size="small">{{ errors.surfaces[0] }}</Message>
      </div>
      <div
        v-else-if="form.dental_condition_id && form.tooth_number"
        class="flex items-center gap-2 text-sm text-surface-500 dark:text-surface-400"
      >
        <svg width="28" height="28" viewBox="0 0 100 100" class="shrink-0">
          <ToothSurface
            :path="WHOLE_TOOTH_PATH"
            fill="var(--p-surface-200)"
            :interactive="false"
            :label="t('dentalChart.dialog.wholeTooth')"
          />
        </svg>
        {{ t('dentalChart.dialog.wholeTooth') }}
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">{{
            t('treatmentPlans.items.quantity')
          }}</label>
          <InputNumber
            v-model="form.quantity"
            :min="1"
            show-buttons
            :disabled="fieldsDisabled"
            :invalid="!!errors.quantity"
            fluid
          />
          <Message v-if="errors.quantity" severity="error" size="small">{{ errors.quantity[0] }}</Message>
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">{{
            t('treatmentPlans.items.unitCost')
          }}</label>
          <InputNumber
            v-model="form.unit_cost"
            :min="0"
            :min-fraction-digits="2"
            :max-fraction-digits="2"
            :disabled="fieldsDisabled"
            :invalid="!!errors.unit_cost"
            fluid
            @input="onCostInput"
          />
          <Message v-if="errors.unit_cost" severity="error" size="small">{{ errors.unit_cost[0] }}</Message>
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('treatmentPlans.items.dialog.phase')
        }}</label>
        <InputNumber
          v-model="form.phase"
          :min="1"
          show-buttons
          :disabled="isTerminal"
          :invalid="!!errors.phase"
          fluid
        />
        <Message v-if="errors.phase" severity="error" size="small">{{ errors.phase[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('treatmentPlans.items.dialog.notes')
        }}</label>
        <Textarea v-model="form.notes" rows="3" auto-resize maxlength="2000" />
        <span v-if="showNotesCounter" class="text-xs text-surface-400">
          {{ t('treatmentPlans.dialog.charactersRemaining', { n: notesRemaining }) }}
        </span>
      </div>

      <Message v-if="isOfferLocked" severity="info" :closable="false">
        {{ t('treatmentPlans.items.dialog.offerLockedNote') }}
      </Message>

      <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
