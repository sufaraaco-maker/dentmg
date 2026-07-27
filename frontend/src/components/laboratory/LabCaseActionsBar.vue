<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import { api } from '@/lib/api'
import { isLabCaseError } from '@/services/laboratory'
import { useAuthStore } from '@/stores/auth'
import type { LabCase } from '@/types/laboratory'

/**
 * Lab Case status-transition buttons (design doc §4/§5) — Send (draft only, admin+dentist),
 * Receive/Quality-Check (admin+receptionist logistics), Cancel (draft/sent, admin+dentist —
 * reversing a clinical decision, not a logistics action). Mirrors
 * `PurchaseOrderActionsBar.vue`'s exact shape, emitting the updated case rather than reading from
 * a Pinia store cache — Lab Cases is a paginated list (design doc §6), not a small cache.
 */
const props = defineProps<{ labCase: LabCase }>()

const emit = defineEmits<{ updated: [labCase: LabCase] }>()

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const auth = useAuthStore()

const canPrescribe = computed(() => auth.isAdmin || auth.isDentist)
const canProcess = computed(() => auth.isAdmin || auth.isReceptionist)

const busy = ref(false)

const canSend = computed(() => canProcess.value && props.labCase.status === 'draft')
const canReceive = computed(() => canProcess.value && props.labCase.status === 'sent')
const canQualityCheck = computed(() => canProcess.value && props.labCase.status === 'received')
const canCancel = computed(() => canPrescribe.value && ['draft', 'sent'].includes(props.labCase.status))

async function handleError(err: unknown): Promise<void> {
  if (isLabCaseError(err)) {
    toast.add({ severity: 'error', summary: err.message, life: 4000 })
    return
  }

  const status = (err as { response?: { status?: number } })?.response?.status

  if (status === 403) {
    toast.add({ severity: 'error', summary: t('laboratory.labCases.forbidden'), life: 3000 })
  } else {
    toast.add({ severity: 'error', summary: t('laboratory.labCases.actionError'), life: 3000 })
  }
}

async function runAction(action: 'send' | 'receive' | 'quality-check' | 'cancel'): Promise<void> {
  busy.value = true
  try {
    const { data } = await api.post<LabCase>(`/lab-cases/${props.labCase.id}/${action}`)
    toast.add({ severity: 'success', summary: t('laboratory.labCases.actionSuccess'), life: 3000 })
    emit('updated', data)
  } catch (err) {
    await handleError(err)
  } finally {
    busy.value = false
  }
}

function confirmCancel(): void {
  confirm.require({
    message: t('laboratory.labCases.confirmCancelMessage'),
    header: t('laboratory.labCases.actions.cancel'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: t('laboratory.labCases.actions.cancel'),
    rejectLabel: t('common.cancel'),
    accept: () => runAction('cancel'),
  })
}
</script>

<template>
  <div v-if="canSend || canReceive || canQualityCheck || canCancel" class="flex flex-wrap gap-2 print:hidden">
    <Button
      v-if="canSend"
      :label="t('laboratory.labCases.actions.send')"
      icon="pi pi-send"
      severity="info"
      :loading="busy"
      size="small"
      outlined
      @click="runAction('send')"
    />
    <Button
      v-if="canReceive"
      :label="t('laboratory.labCases.actions.receive')"
      icon="pi pi-inbox"
      severity="warn"
      :loading="busy"
      size="small"
      outlined
      @click="runAction('receive')"
    />
    <Button
      v-if="canQualityCheck"
      :label="t('laboratory.labCases.actions.qualityCheck')"
      icon="pi pi-check-circle"
      severity="success"
      :loading="busy"
      size="small"
      outlined
      @click="runAction('quality-check')"
    />
    <Button
      v-if="canCancel"
      :label="t('laboratory.labCases.actions.cancel')"
      icon="pi pi-ban"
      severity="danger"
      :loading="busy"
      size="small"
      outlined
      @click="confirmCancel"
    />
  </div>
</template>
