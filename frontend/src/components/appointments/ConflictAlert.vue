<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Message from 'primevue/message'
import Button from 'primevue/button'
import type { AppointmentConflictError } from '@/types/appointment'

/**
 * Renders a 409/422 conflict response thrown by an appointments store action (design doc §3.8).
 * `dentist_conflict` (and `invalid_status_transition`) carry no `overridable` flag at all — a
 * hard stop, no "Book Anyway" button. `patient_conflict`/`outside_working_hours`/`early_no_show`
 * are soft warnings: `overridable` + `override_field` (named by the API, not hardcoded here)
 * drive the optional override action.
 */
const props = defineProps<{ error: AppointmentConflictError | null }>()

const emit = defineEmits<{ override: [overrideField: string] }>()

const { t } = useI18n()

const severity = computed(() => (props.error?.overridable ? 'warn' : 'error'))
const canOverride = computed(() => Boolean(props.error?.overridable && props.error?.override_field))

function bookAnyway() {
  if (props.error?.override_field) emit('override', props.error.override_field)
}
</script>

<template>
  <Message
    v-if="error"
    :severity="severity"
    :closable="false"
    :pt="{
      root: {
        role: severity === 'warn' ? 'status' : 'alert',
        'aria-live': severity === 'warn' ? 'polite' : 'assertive',
      },
    }"
  >
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <span>{{ error.message }}</span>
      <Button
        v-if="canOverride"
        type="button"
        :label="t('appointments.dialog.bookAnyway')"
        size="small"
        severity="warn"
        outlined
        @click="bookAnyway"
      />
    </div>
  </Message>
</template>
