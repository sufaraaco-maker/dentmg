<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import StatusActionButton from './StatusActionButton.vue'
import ConflictAlert from './ConflictAlert.vue'
import { useAppointmentsStore } from '@/stores/appointments'
import { useAuthStore } from '@/stores/auth'
import { isAppointmentConflictError } from '@/services/appointments'
import type {
  Appointment,
  AppointmentActionKind,
  AppointmentConflictError,
  AppointmentStatus,
} from '@/types/appointment'

/**
 * Container for the six status-transition buttons (design doc §4.6/§9). This component decides
 * ONLY visibility, disabled state, and UX messaging — never a business rule. The status/role/
 * ownership table below exists purely to hide a button that would obviously fail (e.g. a 422
 * `invalid_status_transition`); the backend's own state machine and policies remain the single
 * source of truth and are never trusted as bypassed just because a button was shown. If the
 * backend disagrees with what this table assumed (a stale cache, a race with another user), the
 * real API call still fails and this component re-syncs to the backend's actual state below
 * rather than pretending the action succeeded.
 */
const props = defineProps<{ appointment: Appointment }>()

const emit = defineEmits<{ 'action-completed': [appointment: Appointment] }>()

const { t } = useI18n()
const toast = useToast()
const appointmentsStore = useAppointmentsStore()
const auth = useAuthStore()

const busy = ref(false)
const noShowConflict = ref<AppointmentConflictError | null>(null)

const NON_TERMINAL: AppointmentStatus[] = ['scheduled', 'confirmed', 'checked_in', 'in_progress']

function isOwnDentist(appointment: Appointment): boolean {
  return auth.isDentist && auth.user?.id === appointment.dentist_id
}

interface ActionRule {
  action: AppointmentActionKind
  visibleStatuses: AppointmentStatus[]
  requiresReason?: boolean
  allowed: (appointment: Appointment) => boolean
}

const RULES: ActionRule[] = [
  { action: 'confirm', visibleStatuses: ['scheduled'], allowed: () => auth.canManageAppointments },
  {
    action: 'checkIn',
    visibleStatuses: ['scheduled', 'confirmed'],
    allowed: () => auth.canManageAppointments,
  },
  {
    action: 'start',
    visibleStatuses: ['checked_in'],
    allowed: (appointment) => auth.canManageAppointments || isOwnDentist(appointment),
  },
  {
    action: 'complete',
    visibleStatuses: ['in_progress'],
    allowed: (appointment) => auth.canManageAppointments || isOwnDentist(appointment),
  },
  {
    action: 'cancel',
    visibleStatuses: NON_TERMINAL,
    requiresReason: true,
    allowed: () => auth.canManageAppointments,
  },
  {
    action: 'noShow',
    visibleStatuses: ['scheduled', 'confirmed'],
    allowed: () => auth.canManageAppointments,
  },
]

const visibleRules = computed(() =>
  RULES.filter(
    (rule) => rule.visibleStatuses.includes(props.appointment.status) && rule.allowed(props.appointment),
  ),
)

async function callAction(action: AppointmentActionKind, reason?: string): Promise<Appointment> {
  const id = props.appointment.id

  switch (action) {
    case 'confirm':
      return appointmentsStore.confirm(id)
    case 'checkIn':
      return appointmentsStore.checkIn(id)
    case 'start':
      return appointmentsStore.start(id)
    case 'complete':
      return appointmentsStore.complete(id)
    case 'cancel':
      return appointmentsStore.cancel(id, { cancellation_reason: reason ?? null })
    case 'noShow':
      return appointmentsStore.noShow(id, { override_early_no_show: false })
  }
}

async function handleConfirmed(action: AppointmentActionKind, reason?: string) {
  busy.value = true
  if (action === 'noShow') noShowConflict.value = null

  try {
    const updated = await callAction(action, reason)
    toast.add({ severity: 'success', summary: t('appointments.detail.actionSuccess'), life: 3000 })
    emit('action-completed', updated)
  } catch (err) {
    await handleActionError(action, err)
  } finally {
    busy.value = false
  }
}

async function handleActionError(action: AppointmentActionKind, err: unknown) {
  if (action === 'noShow' && isAppointmentConflictError(err)) {
    noShowConflict.value = err
    return
  }

  const status = (err as { response?: { status?: number } })?.response?.status

  if (status === 403) {
    toast.add({ severity: 'error', summary: t('appointments.forbidden'), life: 3000 })
  } else {
    toast.add({ severity: 'error', summary: t('appointments.detail.actionError'), life: 3000 })
  }

  if (status === 422) {
    // Our visibility table was a stale approximation of the backend's real state (§4.6) — resync
    // the parent's copy so the UI stops offering an action the backend just rejected.
    const fresh = await appointmentsStore.fetchOne(props.appointment.id)
    emit('action-completed', fresh)
  }
}

async function overrideNoShow() {
  busy.value = true
  try {
    const updated = await appointmentsStore.noShow(props.appointment.id, { override_early_no_show: true })
    noShowConflict.value = null
    toast.add({ severity: 'success', summary: t('appointments.detail.actionSuccess'), life: 3000 })
    emit('action-completed', updated)
  } catch (err) {
    await handleActionError('noShow', err)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <ConflictAlert :error="noShowConflict" @override="overrideNoShow" />
    <p v-if="!visibleRules.length" class="text-sm text-surface-500 dark:text-surface-400">
      {{ t('appointments.detail.noActionsAvailable') }}
    </p>
    <div v-else class="flex flex-wrap gap-2">
      <StatusActionButton
        v-for="rule in visibleRules"
        :key="rule.action"
        :action="rule.action"
        :requires-reason="rule.requiresReason"
        :loading="busy"
        @confirmed="(reason) => handleConfirmed(rule.action, reason)"
      />
    </div>
  </div>
</template>
