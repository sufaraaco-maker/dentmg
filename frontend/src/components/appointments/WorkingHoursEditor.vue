<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import WorkingHoursDayRow from './WorkingHoursDayRow.vue'
import { useWorkingHoursStore } from '@/stores/workingHours'
import { useAuthStore } from '@/stores/auth'
import type { DentistWorkingHour, WorkingHourDraft } from '@/types/appointment'

/**
 * Weekly working-hours grid editor (design doc §5, §9) — `DentistScheduleView`'s first tab.
 * Owns `workingHours.ts` and all persistence; `WorkingHoursDayRow` stays presentational and only
 * tells this component the desired end state for one day via `update:shifts`. Write access is
 * admin-only regardless of which dentist is selected (`DentistWorkingHourPolicy::create/delete`)
 * — a dentist viewing their own schedule always gets the read-only rendering.
 */
const props = defineProps<{ dentistId: string }>()

const { t } = useI18n()
const toast = useToast()
const workingHours = useWorkingHoursStore()
const auth = useAuthStore()

const editable = computed(() => auth.isAdmin)
const savingDays = reactive(new Set<number>())

const DAY_NUMBERS = [0, 1, 2, 3, 4, 5, 6]

const shiftsByDay = computed<Record<number, DentistWorkingHour[]>>(() => {
  const all = workingHours.byDentist.get(props.dentistId) ?? []
  const grouped: Record<number, DentistWorkingHour[]> = { 0: [], 1: [], 2: [], 3: [], 4: [], 5: [], 6: [] }

  for (const shift of all) {
    grouped[shift.day_of_week] = [...grouped[shift.day_of_week], shift]
  }

  for (const day of DAY_NUMBERS) {
    grouped[day] = grouped[day].sort((a, b) => a.start_time.localeCompare(b.start_time))
  }

  return grouped
})

watch(
  () => props.dentistId,
  (id) => {
    if (id) workingHours.fetchForDentist(id)
  },
  { immediate: true },
)

async function handleUpdateShifts(day: number, drafts: WorkingHourDraft[]) {
  const original = shiftsByDay.value[day] ?? []
  const draftById = new Map(drafts.filter((draft) => draft.id).map((draft) => [draft.id as string, draft]))

  const changed = (shift: DentistWorkingHour, draft: WorkingHourDraft) =>
    shift.start_time !== draft.start_time ||
    shift.end_time !== draft.end_time ||
    shift.is_active !== draft.is_active

  const toDelete = original.filter((shift) => {
    const draft = draftById.get(shift.id)
    return !draft || changed(shift, draft)
  })

  const toCreate = drafts.filter((draft) => {
    if (!draft.id) return true
    const source = original.find((shift) => shift.id === draft.id)
    return !source || changed(source, draft)
  })

  savingDays.add(day)

  try {
    await Promise.all(toDelete.map((shift) => workingHours.remove(props.dentistId, shift.id)))
    await Promise.all(
      toCreate.map((draft) =>
        workingHours.create(props.dentistId, {
          day_of_week: day,
          start_time: draft.start_time,
          end_time: draft.end_time,
          is_active: draft.is_active,
        }),
      ),
    )
  } catch {
    toast.add({ severity: 'error', summary: t('appointments.schedule.workingHours.saveError'), life: 3000 })
  } finally {
    savingDays.delete(day)
  }
}

async function handleCopyTo(sourceDay: number, targetDays: number[]) {
  const sourceShifts = shiftsByDay.value[sourceDay] ?? []
  if (!sourceShifts.length || !targetDays.length) return

  targetDays.forEach((day) => savingDays.add(day))

  try {
    await Promise.all(
      targetDays.flatMap((targetDay) =>
        sourceShifts.map((shift) =>
          workingHours.create(props.dentistId, {
            day_of_week: targetDay,
            start_time: shift.start_time,
            end_time: shift.end_time,
            is_active: shift.is_active,
          }),
        ),
      ),
    )
    toast.add({
      severity: 'success',
      summary: t('appointments.schedule.workingHours.copySuccess'),
      life: 3000,
    })
  } catch {
    toast.add({ severity: 'error', summary: t('appointments.schedule.workingHours.copyError'), life: 3000 })
  } finally {
    targetDays.forEach((day) => savingDays.delete(day))
  }
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <p v-if="!editable" class="text-sm text-surface-500 dark:text-surface-400">
      {{ t('appointments.schedule.workingHours.viewOnlyNote') }}
    </p>
    <WorkingHoursDayRow
      v-for="day in DAY_NUMBERS"
      :key="day"
      :day-of-week="day"
      :shifts="shiftsByDay[day]"
      :editable="editable"
      :saving="savingDays.has(day)"
      @update:shifts="(drafts) => handleUpdateShifts(day, drafts)"
      @copy-to="(targets) => handleCopyTo(day, targets)"
    />
  </div>
</template>
