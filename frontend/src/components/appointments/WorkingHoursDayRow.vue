<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import ToggleSwitch from 'primevue/toggleswitch'
import Dialog from 'primevue/dialog'
import MultiSelect from 'primevue/multiselect'
import type { DentistWorkingHour, WorkingHourDraft } from '@/types/appointment'

/**
 * One day's shift rows — add/edit/remove/copy (design doc §5, §9). Presentational: no store or
 * `lib/api.ts` access of its own. `WorkingHoursEditor` owns persistence — this component only
 * emits the *desired* end state (`update:shifts`) once the user explicitly commits a change (a
 * per-shift checkmark, or the day-level active toggle), never on every keystroke. The backend
 * only exposes create/delete for `dentist_working_hours` (no update), so an edited shift is
 * represented the same way a removed-then-re-added one would be — the parent is the one that
 * diffs this against real persisted rows to decide what to create/delete.
 */
const props = defineProps<{
  dayOfWeek: number
  shifts: DentistWorkingHour[]
  editable?: boolean
  saving?: boolean
}>()

const emit = defineEmits<{
  'update:shifts': [drafts: WorkingHourDraft[]]
  'copy-to': [targetDays: number[]]
}>()

const { t } = useI18n()

const DAY_NUMBERS = [0, 1, 2, 3, 4, 5, 6]

let tempCounter = 0
function nextKey(): string {
  tempCounter += 1
  return `new-${props.dayOfWeek}-${tempCounter}`
}

function toDraft(shift: DentistWorkingHour): WorkingHourDraft & { key: string } {
  return {
    key: shift.id,
    id: shift.id,
    day_of_week: shift.day_of_week,
    start_time: shift.start_time,
    end_time: shift.end_time,
    is_active: shift.is_active,
  }
}

const drafts = ref<(WorkingHourDraft & { key: string })[]>(props.shifts.map(toDraft))

// Keys the user has added locally via "Add shift" but not yet committed (Saved). `props.shifts`
// can change for reasons unrelated to a commit this row triggered — most notably the initial
// background fetch resolving after the user already clicked "Add shift", or an unrelated day's
// edit forcing the parent's whole shiftsByDay map (and therefore every row's `shifts` prop) to
// recompute. Only skip the resync while one of THESE uncommitted drafts is pending; once a draft
// has been committed, a still-`id`-less entry means its create request is merely in flight, and
// the resync must still be allowed through so the temporary draft gets replaced by the real,
// server-issued row once that request resolves (skipping unconditionally on `!id` would strand
// it as "never persisted" forever, so a later delete would only ever act locally).
const uncommittedKeys = reactive(new Set<string>())

watch(
  () => props.shifts,
  (shifts) => {
    if (drafts.value.some((draft) => uncommittedKeys.has(draft.key))) return
    drafts.value = shifts.map(toDraft)
  },
)

function timeStringToDate(time: string): Date {
  const [hours, minutes] = time.split(':').map(Number)
  const date = new Date()
  date.setHours(hours, minutes, 0, 0)
  return date
}

function dateToTimeString(date: Date): string {
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${hours}:${minutes}`
}

// The backend returns "HH:mm:ss" (Laravel's time cast) — the DatePicker round-trip already
// strips seconds for the editable view, so the read-only view formats it the same way here.
function formatTime(time: string): string {
  return time.slice(0, 5)
}

function isValid(draft: WorkingHourDraft): boolean {
  return draft.start_time < draft.end_time
}

function isDirty(draft: WorkingHourDraft): boolean {
  if (!draft.id) return true

  const original = props.shifts.find((shift) => shift.id === draft.id)
  if (!original) return true

  return (
    original.start_time !== draft.start_time ||
    original.end_time !== draft.end_time ||
    original.is_active !== draft.is_active
  )
}

function overlaps(a: WorkingHourDraft, b: WorkingHourDraft): boolean {
  return a.start_time < b.end_time && b.start_time < a.end_time
}

const overlapKeys = computed(() => {
  const keys = new Set<string>()

  for (let i = 0; i < drafts.value.length; i += 1) {
    for (let j = i + 1; j < drafts.value.length; j += 1) {
      if (overlaps(drafts.value[i], drafts.value[j])) {
        keys.add(drafts.value[i].key)
        keys.add(drafts.value[j].key)
      }
    }
  }

  return keys
})

const dayActive = computed(() => drafts.value.length > 0 && drafts.value.every((draft) => draft.is_active))

function stripKey(draft: WorkingHourDraft & { key: string }): WorkingHourDraft {
  const { id, day_of_week, start_time, end_time, is_active } = draft
  return { id, day_of_week, start_time, end_time, is_active }
}

function commit(nextDrafts: (WorkingHourDraft & { key: string })[]) {
  // These are now submitted to the parent for persisting — any that were pending-add are no
  // longer "uncommitted", even though they won't have a real `id` until the create resolves.
  for (const draft of nextDrafts) uncommittedKeys.delete(draft.key)
  emit('update:shifts', nextDrafts.map(stripKey))
}

function addShift() {
  const last = drafts.value[drafts.value.length - 1]
  const startTime = last ? last.end_time : '09:00'
  const [hours, minutes] = startTime.split(':').map(Number)
  const endHours = Math.min(hours + 1, 23)
  const endTime = `${String(endHours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`

  const key = nextKey()
  uncommittedKeys.add(key)
  drafts.value = [
    ...drafts.value,
    {
      key,
      id: null,
      day_of_week: props.dayOfWeek,
      start_time: startTime,
      end_time: endTime,
      is_active: true,
    },
  ]
}

function removeShift(key: string) {
  const draft = drafts.value.find((entry) => entry.key === key)
  if (!draft) return

  const remaining = drafts.value.filter((entry) => entry.key !== key)

  if (!draft.id) {
    // Never persisted — just drop the local draft, nothing for the parent to delete.
    uncommittedKeys.delete(key)
    drafts.value = remaining
    return
  }

  commit(remaining)
}

function commitShift(key: string) {
  const draft = drafts.value.find((entry) => entry.key === key)
  if (!draft || !isValid(draft)) return

  commit(drafts.value)
}

function toggleDayActive(value: boolean) {
  drafts.value = drafts.value.map((draft) => ({ ...draft, is_active: value }))
  commit(drafts.value)
}

const copyDialogVisible = ref(false)
const copyTargets = ref<number[]>([])

const otherDayOptions = computed(() =>
  DAY_NUMBERS.filter((day) => day !== props.dayOfWeek).map((day) => ({
    value: day,
    label: t(`appointments.schedule.days.${day}`),
  })),
)

function openCopyDialog() {
  copyTargets.value = []
  copyDialogVisible.value = true
}

function confirmCopy() {
  emit('copy-to', copyTargets.value)
  copyDialogVisible.value = false
}
</script>

<template>
  <div class="flex flex-col gap-2 rounded-lg border border-surface-200 p-3 dark:border-surface-700">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="flex items-center gap-2">
        <ToggleSwitch
          v-if="editable && drafts.length"
          :model-value="dayActive"
          :disabled="saving"
          @update:model-value="toggleDayActive"
        />
        <span class="font-medium text-surface-900 dark:text-surface-0">
          {{ t(`appointments.schedule.days.${dayOfWeek}`) }}
        </span>
      </div>
      <div v-if="editable" class="flex items-center gap-2">
        <Button
          v-if="drafts.length"
          :label="t('appointments.schedule.workingHours.copyTo')"
          text
          size="small"
          :disabled="saving"
          @click="openCopyDialog"
        />
        <Button
          icon="pi pi-plus"
          :label="t('appointments.schedule.workingHours.addShift')"
          text
          size="small"
          :disabled="saving"
          @click="addShift"
        />
      </div>
    </div>

    <p v-if="!drafts.length" class="text-sm text-surface-500 dark:text-surface-400">
      {{ t('appointments.schedule.workingHours.notSet') }}
    </p>

    <div v-for="draft in drafts" :key="draft.key" class="flex flex-wrap items-center gap-2">
      <template v-if="editable">
        <DatePicker
          :model-value="timeStringToDate(draft.start_time)"
          time-only
          hour-format="24"
          class="w-28"
          :disabled="saving"
          @update:model-value="(value) => (draft.start_time = dateToTimeString(value as Date))"
        />
        <span class="text-surface-400">–</span>
        <DatePicker
          :model-value="timeStringToDate(draft.end_time)"
          time-only
          hour-format="24"
          class="w-28"
          :disabled="saving"
          @update:model-value="(value) => (draft.end_time = dateToTimeString(value as Date))"
        />
        <Button
          icon="pi pi-check"
          text
          rounded
          size="small"
          :disabled="!isDirty(draft) || !isValid(draft) || saving"
          :loading="saving"
          :aria-label="t('appointments.schedule.workingHours.save')"
          @click="commitShift(draft.key)"
        />
        <Button
          icon="pi pi-trash"
          text
          rounded
          size="small"
          severity="danger"
          :disabled="saving"
          :aria-label="t('common.delete')"
          @click="removeShift(draft.key)"
        />
        <span v-if="!draft.is_active" class="text-xs text-surface-400">
          ({{ t('appointments.schedule.workingHours.inactive') }})
        </span>
        <span v-if="!isValid(draft)" class="text-xs text-red-500">
          {{ t('appointments.schedule.workingHours.endAfterStart') }}
        </span>
        <span v-else-if="overlapKeys.has(draft.key)" class="text-xs text-amber-500">
          {{ t('appointments.schedule.workingHours.overlapWarning') }}
        </span>
      </template>
      <template v-else>
        <span class="text-surface-700 dark:text-surface-200"
          >{{ formatTime(draft.start_time) }} – {{ formatTime(draft.end_time) }}</span
        >
        <span v-if="!draft.is_active" class="text-xs text-surface-400">
          ({{ t('appointments.schedule.workingHours.inactive') }})
        </span>
      </template>
    </div>

    <Dialog
      v-model:visible="copyDialogVisible"
      modal
      :header="
        t('appointments.schedule.workingHours.copyToTitle', {
          day: t(`appointments.schedule.days.${dayOfWeek}`),
        })
      "
      class="w-full max-w-sm"
    >
      <MultiSelect
        v-model="copyTargets"
        :options="otherDayOptions"
        option-label="label"
        option-value="value"
        display="chip"
        fluid
      />
      <template #footer>
        <Button type="button" :label="t('common.cancel')" text @click="copyDialogVisible = false" />
        <Button
          type="button"
          :label="t('appointments.schedule.workingHours.copyToConfirm')"
          :disabled="!copyTargets.length"
          @click="confirmCopy"
        />
      </template>
    </Dialog>
  </div>
</template>
