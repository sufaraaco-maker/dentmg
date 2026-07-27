<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { api } from '@/lib/api'
import { useSuppliersStore } from '@/stores/suppliers'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import { toLocalDateString } from '@/lib/date'
import type { PurchaseOrder } from '@/types/inventory'

/** Creates a new draft Purchase Order (design doc §3/§6/§9) — supplier is immutable after
 *  creation, so it's the one field this dialog genuinely needs up front. */
const props = defineProps<{ visible: boolean }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  created: [order: PurchaseOrder]
}>()

const { t } = useI18n()
const toast = useToast()
const suppliersStore = useSuppliersStore()

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

function emptyForm() {
  return { supplier_id: null as string | null, notes: '', expected_at: null as Date | null }
}

const form = reactive(emptyForm())

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return
    errors.value = {}
    Object.assign(form, emptyForm())
  },
  { immediate: true },
)

onMounted(() => {
  suppliersStore.fetchAll()
})

useDialogFocusRestore(() => props.visible)

function validate(): boolean {
  const nextErrors: Record<string, string[]> = {}

  if (!form.supplier_id) nextErrors.supplier_id = [t('inventory.purchaseOrders.fieldRequired')]

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (!validate()) return

  saving.value = true
  errors.value = {}

  try {
    const payload = {
      supplier_id: form.supplier_id,
      notes: form.notes.trim() || null,
      expected_at: form.expected_at ? toLocalDateString(form.expected_at) : null,
    }

    const { data } = await api.post<PurchaseOrder>('/purchase-orders', payload)

    emit('created', data)
    emit('update:visible', false)
  } catch (err: unknown) {
    const response = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } })
      ?.response

    if (response?.status === 422) {
      errors.value = response.data?.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('inventory.purchaseOrders.createError'), life: 3000 })
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
    :header="t('inventory.purchaseOrders.createTitle')"
    class="w-full max-w-md"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label for="po-supplier" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.purchaseOrders.supplier') }}
        </label>
        <Select
          id="po-supplier"
          v-model="form.supplier_id"
          :options="suppliersStore.items.filter((s) => s.is_active)"
          option-label="name"
          option-value="id"
          filter
          :placeholder="t('inventory.purchaseOrders.selectSupplier')"
          :invalid="!!errors.supplier_id"
          fluid
        />
        <Message v-if="errors.supplier_id" severity="error" size="small">{{ errors.supplier_id[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label for="po-expected" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.purchaseOrders.expectedAt') }}
        </label>
        <DatePicker id="po-expected" v-model="form.expected_at" show-icon fluid />
      </div>

      <div class="flex flex-col gap-2">
        <label for="po-notes" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.purchaseOrders.notes') }}
        </label>
        <Textarea id="po-notes" v-model="form.notes" auto-resize rows="2" fluid />
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.create')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
