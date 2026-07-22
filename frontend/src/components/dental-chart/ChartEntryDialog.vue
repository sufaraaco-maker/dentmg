<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Tag from 'primevue/tag'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import ToothSurface from './ToothSurface.vue'
import DentistSelect from '@/components/appointments/DentistSelect.vue'
import { useDentalChartEntriesStore } from '@/stores/dentalChartEntries'
import { useDentalConditionsStore } from '@/stores/dentalConditions'
import { isDentalChartEntryError } from '@/services/dentalChart'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import { parseServerDateTime, toLocalDateTimeString } from '@/lib/date'
import { TOOTH_CODES, toothDisplayName } from '@/lib/teeth'
import { WHOLE_TOOTH_PATH, surfaceRegionsFor } from '@/lib/toothGeometry'
import type {
  CreatableDentalChartEntryStatus,
  DentalChartEntry,
  DentalConditionCategory,
  ToothSurface as ToothSurfaceCode,
} from '@/types/dentalChart'

const NOTES_MAX = 2000
const CREATABLE_STATUSES: CreatableDentalChartEntryStatus[] = ['existing', 'active', 'planned', 'completed']

/**
 * Create/Edit dialog — Diagnosis/Procedure tabs gate which catalog condition is selectable
 * (implementation plan §2.1), matching `AppointmentDialog.vue`'s exact Tabs/TabList/Tab/TabPanels/
 * TabPanel usage (§2.3). The patient is always already fixed by the host tab, unlike Appointments'
 * dialog, so there is no patient-picker field here at all.
 */
const props = defineProps<{
  visible: boolean
  patientId: string
  entry?: DentalChartEntry | null
  prefill?: { toothNumber?: string; surfaces?: ToothSurfaceCode[] } | null
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: [entry: DentalChartEntry]
}>()

const { t } = useI18n()
const toast = useToast()
const entriesStore = useDentalChartEntriesStore()
const conditionsStore = useDentalConditionsStore()

const isEditMode = computed(() => Boolean(props.entry))
// Terminal entries stay visible but locked (notes excepted) — backend rejects any other field edit
// on a completed/cancelled entry (implementation plan §1.6/§3.3).
const isLocked = computed(
  () => isEditMode.value && (props.entry!.status === 'completed' || props.entry!.status === 'cancelled'),
)

const activeCategory = ref<DentalConditionCategory>('finding')
const saving = ref(false)
const transitioning = ref(false)
const errors = ref<Record<string, string[]>>({})

const toothOptions = computed(() =>
  TOOTH_CODES.map((code) => ({ label: `${code} — ${toothDisplayName(code)}`, value: code })),
)

function conditionsFor(category: DentalConditionCategory) {
  return conditionsStore.items
    .filter((c) => c.is_active && c.category === category)
    .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
    .map((c) => ({ label: c.name, value: c.id }))
}

const findingOptions = computed(() => conditionsFor('finding'))
const procedureOptions = computed(() => conditionsFor('procedure'))

function emptyForm() {
  return {
    tooth_number: null as string | null,
    dentist_id: null as string | null,
    dental_condition_id: null as string | null,
    surfaces: [] as ToothSurfaceCode[],
    status: 'existing' as CreatableDentalChartEntryStatus,
    notes: '',
    recorded_at: null as Date | null,
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
    const source = props.entry

    if (source) {
      Object.assign(form, {
        tooth_number: source.tooth_number,
        dentist_id: source.dentist?.id ?? null,
        dental_condition_id: source.dental_condition?.id ?? null,
        surfaces: source.surfaces ? [...source.surfaces] : [],
        status: source.status as CreatableDentalChartEntryStatus,
        notes: source.notes ?? '',
        recorded_at: source.recorded_at ? parseServerDateTime(source.recorded_at) : null,
      })
      activeCategory.value = source.dental_condition?.category ?? 'finding'
    } else {
      Object.assign(form, emptyForm())
      activeCategory.value = 'finding'
      if (props.prefill?.toothNumber) form.tooth_number = props.prefill.toothNumber
      if (props.prefill?.surfaces) form.surfaces = [...props.prefill.surfaces]
    }
  },
  { immediate: true },
)

// Switching tabs changes which conditions are even selectable — a condition chosen under the other
// category would silently mismatch its own catalog category, so it's cleared, not carried over.
watch(activeCategory, () => {
  form.dental_condition_id = null
})

useDialogFocusRestore(() => props.visible)

const surfaceRegions = computed(() => (form.tooth_number ? surfaceRegionsFor(form.tooth_number) : []))

function toggleSurface(surface: ToothSurfaceCode) {
  if (isLocked.value) return
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

  if (!form.tooth_number) nextErrors.tooth_number = [t('dentalChart.dialog.fieldRequired')]
  if (!form.dentist_id) nextErrors.dentist_id = [t('dentalChart.dialog.fieldRequired')]
  if (!form.dental_condition_id) nextErrors.dental_condition_id = [t('dentalChart.dialog.fieldRequired')]
  if (requiresSurfaces.value && form.surfaces.length === 0) {
    nextErrors.surfaces = [t('dentalChart.dialog.surfacesRequired')]
  }
  if (!requiresSurfaces.value && form.surfaces.length > 0) {
    nextErrors.surfaces = [t('dentalChart.dialog.surfacesNotAllowed')]
  }

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  // A terminal (completed/cancelled) entry only ever accepts a notes-only payload — the backend
  // rejects the request outright if any other key is present, even unchanged (Service::update()'s
  // array_diff_key guard), so `validate()` (which requires tooth/dentist/condition) is skipped here.
  if (isLocked.value) {
    await submitNotesOnly()
    return
  }
  if (!validate()) return

  saving.value = true
  errors.value = {}

  try {
    const surfaces = requiresSurfaces.value ? form.surfaces : undefined
    let saved: DentalChartEntry

    if (isEditMode.value) {
      saved = await entriesStore.update(props.entry!.id, {
        dental_condition_id: form.dental_condition_id!,
        dentist_id: form.dentist_id!,
        tooth_number: form.tooth_number!,
        surfaces,
        notes: form.notes || null,
        recorded_at: form.recorded_at ? toLocalDateTimeString(form.recorded_at) : undefined,
      })
    } else {
      saved = await entriesStore.create(props.patientId, {
        dental_condition_id: form.dental_condition_id!,
        dentist_id: form.dentist_id!,
        tooth_number: form.tooth_number!,
        surfaces,
        status: form.status,
        notes: form.notes || null,
        recorded_at: form.recorded_at ? toLocalDateTimeString(form.recorded_at) : null,
      })
    }

    toast.add({ severity: 'success', summary: t('dentalChart.dialog.saved'), life: 3000 })
    emit('saved', saved)
    emit('update:visible', false)
  } catch (err: unknown) {
    if (isDentalChartEntryError(err)) {
      toast.add({ severity: 'error', summary: err.message, life: 4000 })
    } else if ((err as { response?: { status?: number } })?.response?.status === 422) {
      errors.value =
        (err as { response: { data: { errors?: Record<string, string[]> } } }).response.data.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('dentalChart.dialog.saveError'), life: 3000 })
    }
  } finally {
    saving.value = false
  }
}

async function submitNotesOnly() {
  saving.value = true
  errors.value = {}

  try {
    const saved = await entriesStore.update(props.entry!.id, { notes: form.notes || null })
    toast.add({ severity: 'success', summary: t('dentalChart.dialog.saved'), life: 3000 })
    emit('saved', saved)
    emit('update:visible', false)
  } catch {
    toast.add({ severity: 'error', summary: t('dentalChart.dialog.saveError'), life: 3000 })
  } finally {
    saving.value = false
  }
}

async function transition(action: 'complete' | 'cancel') {
  if (!props.entry) return
  transitioning.value = true

  try {
    const updated =
      action === 'complete'
        ? await entriesStore.complete(props.entry.id)
        : await entriesStore.cancel(props.entry.id)
    toast.add({ severity: 'success', summary: t('dentalChart.dialog.saved'), life: 3000 })
    emit('saved', updated)
    emit('update:visible', false)
  } catch {
    toast.add({ severity: 'error', summary: t('dentalChart.dialog.saveError'), life: 3000 })
  } finally {
    transitioning.value = false
  }
}
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    :header="isEditMode ? t('dentalChart.dialog.editTitle') : t('dentalChart.dialog.createTitle')"
    class="w-full max-w-2xl"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('dentalChart.dialog.tooth')
        }}</label>
        <Select
          v-model="form.tooth_number"
          :options="toothOptions"
          option-label="label"
          option-value="value"
          filter
          :placeholder="t('dentalChart.dialog.selectTooth')"
          :disabled="isEditMode"
          :invalid="!!errors.tooth_number"
          fluid
        />
        <Message v-if="errors.tooth_number" severity="error" size="small">{{
          errors.tooth_number[0]
        }}</Message>
        <p v-if="isEditMode" class="text-sm text-surface-500 dark:text-surface-400">
          {{ t('dentalChart.dialog.toothLockedNote') }}
        </p>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('dentalChart.dialog.dentist')
        }}</label>
        <DentistSelect v-model="form.dentist_id" :disabled="isLocked" :invalid="!!errors.dentist_id" />
        <Message v-if="errors.dentist_id" severity="error" size="small">{{ errors.dentist_id[0] }}</Message>
      </div>

      <Tabs v-model:value="activeCategory">
        <TabList>
          <Tab value="finding" :disabled="isLocked">{{ t('dentalChart.dialog.tabs.diagnosis') }}</Tab>
          <Tab value="procedure" :disabled="isLocked">{{ t('dentalChart.dialog.tabs.procedure') }}</Tab>
        </TabList>
        <TabPanels>
          <TabPanel value="finding">
            <div class="flex flex-col gap-2 pt-2">
              <Select
                v-model="form.dental_condition_id"
                :options="findingOptions"
                option-label="label"
                option-value="value"
                filter
                :placeholder="t('dentalChart.dialog.selectCondition')"
                :disabled="isLocked"
                :invalid="!!errors.dental_condition_id"
                fluid
              />
            </div>
          </TabPanel>
          <TabPanel value="procedure">
            <div class="flex flex-col gap-2 pt-2">
              <Select
                v-model="form.dental_condition_id"
                :options="procedureOptions"
                option-label="label"
                option-value="value"
                filter
                :placeholder="t('dentalChart.dialog.selectCondition')"
                :disabled="isLocked"
                :invalid="!!errors.dental_condition_id"
                fluid
              />
            </div>
          </TabPanel>
        </TabPanels>
      </Tabs>
      <Message v-if="errors.dental_condition_id" severity="error" size="small">
        {{ errors.dental_condition_id[0] }}
      </Message>

      <div v-if="requiresSurfaces && form.tooth_number" class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('dentalChart.dialog.surfaces')
        }}</label>
        <svg width="140" height="140" viewBox="0 0 100 100" dir="ltr" class="overflow-visible">
          <ToothSurface
            v-for="region in surfaceRegions"
            :key="region.key"
            :path="region.path"
            :glyph-center="region.center"
            :fill="form.surfaces.includes(region.surface) ? 'var(--p-primary-400)' : 'var(--p-surface-100)'"
            :selected="form.surfaces.includes(region.surface)"
            :interactive="!isLocked"
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

      <div v-if="!isEditMode" class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('dentalChart.dialog.status')
        }}</label>
        <Select
          v-model="form.status"
          :options="CREATABLE_STATUSES.map((s) => ({ label: t(`dentalChart.status.${s}`), value: s }))"
          option-label="label"
          option-value="value"
          fluid
        />
      </div>
      <div v-else class="flex items-center gap-2">
        <span class="text-sm text-surface-700 dark:text-surface-200">{{
          t('dentalChart.dialog.status')
        }}</span>
        <Tag :value="t(`dentalChart.status.${entry!.status}`)" />
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('dentalChart.dialog.recordedAt')
        }}</label>
        <DatePicker
          v-model="form.recorded_at"
          show-time
          hour-format="24"
          date-format="yy-mm-dd"
          show-icon
          fluid
          :disabled="isLocked"
        />
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('dentalChart.dialog.notes')
        }}</label>
        <Textarea v-model="form.notes" rows="3" auto-resize maxlength="2000" />
        <span v-if="showNotesCounter" class="text-xs text-surface-400">
          {{ t('dentalChart.dialog.charactersRemaining', { n: notesRemaining }) }}
        </span>
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
        <template v-if="isEditMode && entry!.status === 'planned'">
          <Button
            type="button"
            :label="t('dentalChart.dialog.cancelEntry')"
            severity="danger"
            text
            :loading="transitioning"
            @click="transition('cancel')"
          />
          <Button
            type="button"
            :label="t('dentalChart.dialog.completeEntry')"
            severity="success"
            :loading="transitioning"
            @click="transition('complete')"
          />
        </template>
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
