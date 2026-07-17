<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import DatePicker from 'primevue/datepicker'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useTimeOffStore } from '@/stores/timeOff'
import { useAppointmentsStore } from '@/stores/appointments'
import { parseServerDateTime, toLocalDateTimeString } from '@/lib/date'
import type { DentistTimeOff } from '@/types/appointment'

const REASON_MAX = 255
const TIME_OFF_CATEGORIES = ['vacation', 'conference', 'sick_leave', 'emergency', 'other'] as const
type TimeOffCategory = (typeof TIME_OFF_CATEGORIES)[number]

/**
 * Create a time-off entry, with a non-blocking appointment-conflict warning list (design doc §6,
 * §9). Create-only — the backend exposes no update endpoint for `dentist_time_off`, and the
 * design doesn't call for editing an existing entry (cancel + re-add covers corrections, same as
 * appointments). The category select is a client-side-only convenience: its label is written into
 * the free-text `reason` field on submit, not a real backend column (§6) — never treated as a
 * structured value anywhere downstream.
 */
const props = defineProps<{ visible: boolean; dentistId: string }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: [timeOff: DentistTimeOff]
}>()

const { t, locale } = useI18n()
const toast = useToast()
const timeOffStore = useTimeOffStore()
const appointmentsStore = useAppointmentsStore()

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const categoryOptions = TIME_OFF_CATEGORIES.map((category) => ({
  value: category,
  label: t(`appointments.schedule.timeOff.categories.${category}`),
}))

function emptyForm() {
  return {
    category: null as TimeOffCategory | null,
    reasonDetail: '',
    start_at: null as Date | null,
    end_at: null as Date | null,
  }
}

const form = reactive(emptyForm())

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return
    errors.value = {}
    Object.assign(form, emptyForm())
  },
)

function buildReason(): string | null {
  const detail = form.reasonDetail.trim()
  const categoryLabel =
    form.category && form.category !== 'other'
      ? categoryOptions.find((c) => c.value === form.category)?.label
      : null

  if (categoryLabel && detail) return `${categoryLabel}: ${detail}`
  if (categoryLabel) return categoryLabel
  return detail || null
}

const reasonPreview = computed(() => buildReason())
const reasonRemaining = computed(() => REASON_MAX - (reasonPreview.value?.length ?? 0))
const showReasonCounter = computed(() => (reasonPreview.value?.length ?? 0) > REASON_MAX * 0.8)

const overlappingAppointments = computed(() => {
  if (!form.start_at || !form.end_at) return []

  const startTime = form.start_at.getTime()
  const endTime = form.end_at.getTime()

  return Array.from(appointmentsStore.cache.values())
    .filter((appointment) => {
      if (appointment.dentist_id !== props.dentistId) return false
      if (appointment.status === 'cancelled' || appointment.status === 'no_show') return false

      const appointmentStart = parseServerDateTime(appointment.start_at).getTime()
      const appointmentEnd = parseServerDateTime(appointment.end_at).getTime()

      return appointmentStart < endTime && startTime < appointmentEnd
    })
    .sort((a, b) => parseServerDateTime(a.start_at).getTime() - parseServerDateTime(b.start_at).getTime())
})

watch(
  () => [form.start_at, form.end_at],
  ([start, end]) => {
    if (start instanceof Date && end instanceof Date && start < end) {
      appointmentsStore.fetchRange(start, end)
    }
  },
)

function formatAppointmentTime(iso: string): string {
  return parseServerDateTime(iso).toLocaleString(locale.value, { dateStyle: 'medium', timeStyle: 'short' })
}

function validate(): boolean {
  const nextErrors: Record<string, string[]> = {}

  if (!form.start_at) nextErrors.start_at = [t('appointments.schedule.timeOff.fieldRequired')]
  if (!form.end_at) nextErrors.end_at = [t('appointments.schedule.timeOff.fieldRequired')]
  if (form.start_at && form.end_at && form.end_at <= form.start_at) {
    nextErrors.end_at = [t('appointments.schedule.timeOff.endAfterStart')]
  }
  if ((reasonPreview.value?.length ?? 0) > REASON_MAX) {
    nextErrors.reason = [t('appointments.schedule.timeOff.reasonMax')]
  }

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (!validate()) return

  saving.value = true
  errors.value = {}

  try {
    const created = await timeOffStore.create(props.dentistId, {
      start_at: toLocalDateTimeString(form.start_at!),
      end_at: toLocalDateTimeString(form.end_at!),
      reason: buildReason(),
    })

    toast.add({ severity: 'success', summary: t('appointments.schedule.timeOff.saved'), life: 3000 })
    emit('saved', created)
    emit('update:visible', false)
  } catch (err: unknown) {
    const response = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } })
      ?.response

    if (response?.status === 422) {
      errors.value = response.data?.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('appointments.schedule.timeOff.saveError'), life: 3000 })
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    :header="t('appointments.schedule.timeOff.createTitle')"
    class="w-full max-w-lg"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('appointments.schedule.timeOff.startAt') }}
          </label>
          <DatePicker
            v-model="form.start_at"
            show-time
            hour-format="24"
            date-format="yy-mm-dd"
            show-icon
            fluid
            :invalid="!!errors.start_at"
          />
          <Message v-if="errors.start_at" severity="error" size="small">{{ errors.start_at[0] }}</Message>
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('appointments.schedule.timeOff.endAt') }}
          </label>
          <DatePicker
            v-model="form.end_at"
            show-time
            hour-format="24"
            date-format="yy-mm-dd"
            show-icon
            fluid
            :invalid="!!errors.end_at"
          />
          <Message v-if="errors.end_at" severity="error" size="small">{{ errors.end_at[0] }}</Message>
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('appointments.schedule.timeOff.category') }}
        </label>
        <Select
          v-model="form.category"
          :options="categoryOptions"
          option-label="label"
          option-value="value"
          show-clear
          fluid
        />
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('appointments.schedule.timeOff.reason') }}
        </label>
        <Textarea
          v-model="form.reasonDetail"
          rows="2"
          auto-resize
          :placeholder="t('appointments.schedule.timeOff.reasonPlaceholder')"
        />
        <span v-if="showReasonCounter" class="text-xs text-surface-400">
          {{ t('appointments.dialog.charactersRemaining', { n: reasonRemaining }) }}
        </span>
        <Message v-if="errors.reason" severity="error" size="small">{{ errors.reason[0] }}</Message>
      </div>

      <Message v-if="overlappingAppointments.length" severity="warn" :closable="false">
        <div class="flex flex-col gap-1">
          <span class="font-medium">{{ t('appointments.schedule.timeOff.conflictWarningTitle') }}</span>
          <ul class="list-disc pl-4">
            <li v-for="appointment in overlappingAppointments" :key="appointment.id">
              {{ formatAppointmentTime(appointment.start_at) }}
              <template v-if="appointment.patient"> — {{ appointment.patient.full_name }}</template>
            </li>
          </ul>
        </div>
      </Message>

      <div class="flex justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
