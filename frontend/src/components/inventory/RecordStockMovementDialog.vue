<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { api } from '@/lib/api'
import { isInventoryError } from '@/services/inventory'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import { toLocalDateString } from '@/lib/date'
import { STOCK_MOVEMENT_REASONS, type StockMovement, type StockMovementReason, type Supply } from '@/types/inventory'

/**
 * Records a manual Stock Movement against one Supply (design doc §2/§8/§9). `initial_stock` is
 * always an increase, `used`/`wasted`/`expired` are always a decrease, and `correction` is the only
 * reason where the direction is a real choice — the signed `quantity_delta` the backend expects is
 * computed here from a plain positive quantity + the reason-implied (or user-picked) direction, so
 * staff never has to think in signed numbers themselves.
 */
const props = defineProps<{ visible: boolean; supply: Supply }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  recorded: [movement: StockMovement]
}>()

const { t } = useI18n()
const toast = useToast()

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const reasonOptions = STOCK_MOVEMENT_REASONS.map((reason) => ({
  value: reason,
  label: t(`inventory.movements.reasons.${reason}`),
}))

function emptyForm() {
  return {
    reason: 'used' as StockMovementReason,
    quantity: null as number | null,
    direction: 'decrease' as 'increase' | 'decrease',
    expiration_date: null as Date | null,
    notes: '',
  }
}

const form = reactive(emptyForm())

// initial_stock is always an increase; used/wasted/expired are always a decrease; correction is
// the only reason where the user genuinely picks a direction (design doc §4/§8).
const directionIsFixed = computed(() => form.reason !== 'correction')
const impliedDirection = computed<'increase' | 'decrease'>(() =>
  form.reason === 'initial_stock' ? 'increase' : 'decrease',
)

watch(
  () => form.reason,
  (reason) => {
    if (reason !== 'correction') form.direction = impliedDirection.value
  },
)

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return
    errors.value = {}
    Object.assign(form, emptyForm())
  },
  { immediate: true },
)

useDialogFocusRestore(() => props.visible)

function validate(): boolean {
  const nextErrors: Record<string, string[]> = {}

  if (!form.quantity || form.quantity <= 0) {
    nextErrors.quantity = [t('inventory.movements.fieldRequired')]
  }

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (!validate()) return

  saving.value = true
  errors.value = {}

  try {
    const direction = directionIsFixed.value ? impliedDirection.value : form.direction
    const quantityDelta = direction === 'decrease' ? -(form.quantity as number) : (form.quantity as number)

    const payload = {
      reason: form.reason,
      quantity_delta: quantityDelta,
      expiration_date: form.expiration_date ? toLocalDateString(form.expiration_date) : null,
      notes: form.notes.trim() || null,
    }

    const { data } = await api.post<StockMovement>(`/supplies/${props.supply.id}/stock-movements`, payload)

    toast.add({ severity: 'success', summary: t('inventory.movements.recorded'), life: 3000 })
    emit('recorded', data)
    emit('update:visible', false)
  } catch (err: unknown) {
    if (isInventoryError(err)) {
      toast.add({ severity: 'error', summary: err.message, life: 4000 })
      return
    }

    const response = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } })
      ?.response

    if (response?.status === 422) {
      errors.value = response.data?.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('inventory.movements.recordError'), life: 3000 })
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
    :header="t('inventory.movements.recordTitle', { name: supply.name })"
    class="w-full max-w-md"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label for="movement-reason" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.movements.reason') }}
        </label>
        <Select
          id="movement-reason"
          v-model="form.reason"
          :options="reasonOptions"
          option-label="label"
          option-value="value"
          fluid
        />
      </div>

      <div v-if="!directionIsFixed" class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.movements.direction') }}
        </label>
        <Select
          v-model="form.direction"
          :options="[
            { value: 'increase', label: t('inventory.movements.increase') },
            { value: 'decrease', label: t('inventory.movements.decrease') },
          ]"
          option-label="label"
          option-value="value"
          fluid
        />
      </div>

      <div class="flex flex-col gap-2">
        <label for="movement-quantity" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.movements.quantity') }}
        </label>
        <InputNumber id="movement-quantity" v-model="form.quantity" :min="1" :invalid="!!errors.quantity" fluid />
        <Message v-if="errors.quantity" severity="error" size="small">{{ errors.quantity[0] }}</Message>
      </div>

      <div v-if="form.reason === 'initial_stock'" class="flex flex-col gap-2">
        <label for="movement-expiration" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.movements.expirationDate') }}
        </label>
        <DatePicker id="movement-expiration" v-model="form.expiration_date" show-icon fluid />
      </div>

      <div class="flex flex-col gap-2">
        <label for="movement-notes" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.movements.notes') }}
        </label>
        <Textarea id="movement-notes" v-model="form.notes" auto-resize rows="2" fluid />
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
