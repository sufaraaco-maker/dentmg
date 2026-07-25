<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import { useTreatmentPlansStore } from '@/stores/treatmentPlans'
import { useAuthStore } from '@/stores/auth'
import { isTreatmentPlanError } from '@/services/treatmentPlans'
import type { TreatmentPlan, TreatmentPlanStatus } from '@/types/treatmentPlan'

/**
 * Plan-level status transition buttons (design doc §5/§10/§11, §21 Step 8) — mirrors
 * `AppointmentActionsBar.vue`'s "visibility approximates the backend, backend is still the real
 * guard" shape, simplified for this module: no per-action "reason" text (no field exists to carry
 * one) and no local emit, since `treatmentPlansStore` already upserts every mutation response
 * directly into the same reactive cache `TreatmentPlanDetailView.vue`'s `plan` computed reads from
 * (that view's own docblock) — a resync after a stale-state 422 needs no event, just a refetch.
 */
const props = defineProps<{ plan: TreatmentPlan }>()

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const treatmentPlansStore = useTreatmentPlansStore()
const auth = useAuthStore()

const busy = ref(false)

// Matches every other write ability in this module (design doc §10): admin + dentist only, no
// per-dentist-ownership restriction (unlike Appointments).
const canManage = computed(() => auth.isAdmin || auth.isDentist)

type PlanAction = 'present' | 'accept' | 'reject' | 'start' | 'complete' | 'cancel'

type ButtonSeverity = 'secondary' | 'success' | 'info' | 'warn' | 'danger' | undefined

interface ActionRule {
  action: PlanAction
  visibleStatuses: TreatmentPlanStatus[]
  icon: string
  severity: ButtonSeverity
  requiresConfirm?: boolean
}

const NON_TERMINAL: TreatmentPlanStatus[] = ['draft', 'presented', 'accepted', 'in_progress']

// Order mirrors the forward workflow (design doc §5); `cancel` last since it's available from
// every non-terminal status and reads as the "escape hatch," not the next expected step.
const RULES: ActionRule[] = [
  { action: 'present', visibleStatuses: ['draft'], icon: 'pi pi-send', severity: 'info' },
  { action: 'accept', visibleStatuses: ['presented'], icon: 'pi pi-check', severity: 'success' },
  {
    action: 'reject',
    visibleStatuses: ['presented'],
    icon: 'pi pi-times-circle',
    severity: 'danger',
    requiresConfirm: true,
  },
  { action: 'start', visibleStatuses: ['accepted'], icon: 'pi pi-play', severity: undefined },
  { action: 'complete', visibleStatuses: ['in_progress'], icon: 'pi pi-check-circle', severity: 'success' },
  {
    action: 'cancel',
    visibleStatuses: NON_TERMINAL,
    icon: 'pi pi-ban',
    severity: 'danger',
    requiresConfirm: true,
  },
]

const visibleRules = computed(() =>
  canManage.value ? RULES.filter((rule) => rule.visibleStatuses.includes(props.plan.status)) : [],
)

async function callAction(action: PlanAction): Promise<void> {
  const id = props.plan.id

  switch (action) {
    case 'present':
      await treatmentPlansStore.present(id)
      break
    case 'accept':
      await treatmentPlansStore.accept(id)
      break
    case 'reject':
      await treatmentPlansStore.reject(id)
      break
    case 'start':
      await treatmentPlansStore.start(id)
      break
    case 'complete':
      await treatmentPlansStore.complete(id)
      break
    case 'cancel':
      await treatmentPlansStore.cancel(id)
      break
  }
}

async function run(action: PlanAction): Promise<void> {
  busy.value = true

  try {
    await callAction(action)
    toast.add({ severity: 'success', summary: t('treatmentPlans.detail.actionSuccess'), life: 3000 })
  } catch (err) {
    await handleError(err)
  } finally {
    busy.value = false
  }
}

async function handleError(err: unknown): Promise<void> {
  if (isTreatmentPlanError(err)) {
    if (err.code === 'treatment_plan_has_open_items') {
      toast.add({ severity: 'error', summary: t('treatmentPlans.detail.openItemsError'), life: 4000 })
    } else {
      toast.add({ severity: 'error', summary: err.message, life: 4000 })
    }

    if (err.code === 'invalid_treatment_plan_status_transition') {
      // Our visibility table was a stale approximation of the backend's real state (same class of
      // race as AppointmentActionsBar's identical comment) — resync so the UI stops offering an
      // action the backend just rejected.
      await treatmentPlansStore.fetchOne(props.plan.id)
    }

    return
  }

  const status = (err as { response?: { status?: number } })?.response?.status

  if (status === 403) {
    toast.add({ severity: 'error', summary: t('treatmentPlans.forbidden'), life: 3000 })
  } else {
    toast.add({ severity: 'error', summary: t('treatmentPlans.detail.actionError'), life: 3000 })
  }
}

function confirmAndRun(action: PlanAction): void {
  confirm.require({
    message: t(`treatmentPlans.detail.confirm.${action}Message`),
    header: t(`treatmentPlans.detail.actions.${action}`),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: t(`treatmentPlans.detail.actions.${action}`),
    rejectLabel: t('common.cancel'),
    accept: () => run(action),
  })
}

function trigger(rule: ActionRule): void {
  if (rule.requiresConfirm) {
    confirmAndRun(rule.action)
  } else {
    run(rule.action)
  }
}
</script>

<template>
  <div v-if="visibleRules.length" class="flex flex-wrap gap-2">
    <Button
      v-for="rule in visibleRules"
      :key="rule.action"
      :label="t(`treatmentPlans.detail.actions.${rule.action}`)"
      :icon="rule.icon"
      :severity="rule.severity"
      :loading="busy"
      size="small"
      outlined
      @click="trigger(rule)"
    />
  </div>
</template>
