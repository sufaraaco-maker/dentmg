<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'
import type { AppointmentActionKind } from '@/types/appointment'

/**
 * Presentational confirmation UX for one status-transition action (design doc §4.6/§9). Never
 * calls the API itself — only confirms user intent and emits `confirmed`; the actual store call,
 * and any 403/422 handling if the backend disagrees, live in `AppointmentActionsBar`. Keeping
 * this component free of API/store access is what lets it stay a dumb, reusable confirmation
 * button — it has no business rules of its own to duplicate.
 */
const props = defineProps<{
  action: AppointmentActionKind
  requiresReason?: boolean
  loading?: boolean
}>()

const emit = defineEmits<{ confirmed: [reason?: string] }>()

const { t } = useI18n()
const confirm = useConfirm()

const reasonDialogVisible = ref(false)
const reason = ref('')

type ButtonSeverity = 'secondary' | 'success' | 'info' | 'warn' | 'danger' | undefined

const BUTTON_CONFIG: Record<AppointmentActionKind, { icon: string; severity: ButtonSeverity }> = {
  confirm: { icon: 'pi pi-check', severity: 'info' },
  checkIn: { icon: 'pi pi-sign-in', severity: 'secondary' },
  start: { icon: 'pi pi-play', severity: undefined },
  complete: { icon: 'pi pi-check-circle', severity: 'success' },
  cancel: { icon: 'pi pi-times', severity: 'danger' },
  noShow: { icon: 'pi pi-user-minus', severity: 'warn' },
}

const config = BUTTON_CONFIG[props.action]

// Full, distinct phrasing ("Cancel Appointment" / "Mark as No Show") — never the bare action verb
// ("Cancel") also used by the dismiss button below, which would otherwise show two
// identically-labeled "Cancel" buttons side by side (found during Step 5's real-browser pass).
const dialogHeader = computed(() =>
  props.action === 'cancel' ? t('appointments.detail.cancelHeader') : t('appointments.detail.noShowHeader'),
)

function openConfirmation() {
  if (props.requiresReason) {
    reason.value = ''
    reasonDialogVisible.value = true
    return
  }

  confirm.require({
    message: t('appointments.detail.confirmAction', {
      action: t(`appointments.detail.actions.${props.action}`),
    }),
    header: t(`appointments.detail.actions.${props.action}`),
    icon: 'pi pi-question-circle',
    acceptLabel: t(`appointments.detail.actions.${props.action}`),
    rejectLabel: t('common.cancel'),
    acceptClass: config.severity === 'danger' ? 'p-button-danger' : undefined,
    accept: () => emit('confirmed'),
  })
}

function submitReason() {
  reasonDialogVisible.value = false
  emit('confirmed', reason.value || undefined)
}
</script>

<template>
  <Button
    :label="t(`appointments.detail.actions.${action}`)"
    :icon="config.icon"
    :severity="config.severity"
    :loading="loading"
    size="small"
    outlined
    @click="openConfirmation"
  />

  <Dialog
    v-if="requiresReason"
    v-model:visible="reasonDialogVisible"
    modal
    :header="dialogHeader"
    class="w-full max-w-md"
  >
    <div class="flex flex-col gap-2">
      <label class="text-sm text-surface-700 dark:text-surface-200">
        {{ t('appointments.detail.reasonOptional') }}
      </label>
      <Textarea v-model="reason" rows="3" auto-resize />
    </div>
    <template #footer>
      <Button
        type="button"
        :label="t('appointments.detail.keepAppointment')"
        text
        @click="reasonDialogVisible = false"
      />
      <Button type="button" :label="dialogHeader" severity="danger" @click="submitReason" />
    </template>
  </Dialog>
</template>
