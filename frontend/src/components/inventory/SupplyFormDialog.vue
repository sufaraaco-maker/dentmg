<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { api } from '@/lib/api'
import { useSupplyCategoriesStore } from '@/stores/supplyCategories'
import { useSuppliersStore } from '@/stores/suppliers'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import type { Supply } from '@/types/inventory'

/** Create/Edit a Supply (design doc §6/§11) — the stock-item catalog itself. Calls the API
 *  directly rather than through a Pinia store, mirroring `PatientFormDialog.vue`'s identical
 *  pattern: Supplies is a real paginated list, not a small cached lookup. */
const props = defineProps<{ visible: boolean; supply?: Supply }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: [supply: Supply]
}>()

const { t } = useI18n()
const toast = useToast()
const categoriesStore = useSupplyCategoriesStore()
const suppliersStore = useSuppliersStore()

const isEditMode = computed(() => Boolean(props.supply))
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const categoryOptions = computed(() =>
  categoriesStore.items
    .filter((c) => c.is_active || c.id === props.supply?.category_id)
    .map((c) => ({ value: c.id, label: c.name })),
)
const supplierOptions = computed(() => [
  { value: null, label: t('inventory.supplies.noDefaultSupplier') },
  ...suppliersStore.items
    .filter((s) => s.is_active || s.id === props.supply?.default_supplier_id)
    .map((s) => ({ value: s.id, label: s.name })),
])

function emptyForm() {
  return {
    category_id: null as string | null,
    default_supplier_id: null as string | null,
    name: '',
    sku: '',
    unit_of_measure: '',
    unit_cost: null as number | null,
    reorder_level: 0,
    reorder_quantity: null as number | null,
    is_active: true,
  }
}

const form = reactive(emptyForm())

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return

    errors.value = {}
    const source = props.supply

    if (source) {
      Object.assign(form, {
        category_id: source.category_id,
        default_supplier_id: source.default_supplier_id,
        name: source.name,
        sku: source.sku ?? '',
        unit_of_measure: source.unit_of_measure,
        unit_cost: source.unit_cost !== null ? Number(source.unit_cost) : null,
        reorder_level: source.reorder_level,
        reorder_quantity: source.reorder_quantity,
        is_active: source.is_active,
      })
    } else {
      Object.assign(form, emptyForm())
    }
  },
  { immediate: true },
)

onMounted(() => {
  categoriesStore.fetchAll()
  suppliersStore.fetchAll()
})

useDialogFocusRestore(() => props.visible)

function validate(): boolean {
  const nextErrors: Record<string, string[]> = {}

  if (!form.name.trim()) nextErrors.name = [t('inventory.supplies.fieldRequired')]
  if (!form.category_id) nextErrors.category_id = [t('inventory.supplies.fieldRequired')]
  if (!form.unit_of_measure.trim()) nextErrors.unit_of_measure = [t('inventory.supplies.fieldRequired')]

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (!validate()) return

  saving.value = true
  errors.value = {}

  try {
    const payload = {
      category_id: form.category_id,
      default_supplier_id: form.default_supplier_id,
      name: form.name.trim(),
      sku: form.sku.trim() || null,
      unit_of_measure: form.unit_of_measure.trim(),
      unit_cost: form.unit_cost,
      reorder_level: form.reorder_level,
      reorder_quantity: form.reorder_quantity,
      is_active: form.is_active,
    }

    const { data } = props.supply
      ? await api.put<Supply>(`/supplies/${props.supply.id}`, payload)
      : await api.post<Supply>('/supplies', payload)

    toast.add({ severity: 'success', summary: t('inventory.supplies.saved'), life: 3000 })
    emit('saved', data)
    emit('update:visible', false)
  } catch (err: unknown) {
    const response = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } })
      ?.response

    if (response?.status === 422) {
      errors.value = response.data?.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('inventory.supplies.saveError'), life: 3000 })
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
    :header="isEditMode ? t('inventory.supplies.editTitle') : t('inventory.supplies.createTitle')"
    class="w-full max-w-lg"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label for="supply-name" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.supplies.name') }}
        </label>
        <InputText id="supply-name" v-model="form.name" :invalid="!!errors.name" fluid />
        <Message v-if="errors.name" severity="error" size="small">{{ errors.name[0] }}</Message>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label for="supply-category" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('inventory.supplies.category') }}
          </label>
          <Select
            v-model="form.category_id"
            input-id="supply-category"
            :options="categoryOptions"
            option-label="label"
            option-value="value"
            :placeholder="t('inventory.supplies.selectCategory')"
            :invalid="!!errors.category_id"
            fluid
          />
          <Message v-if="errors.category_id" severity="error" size="small">{{
            errors.category_id[0]
          }}</Message>
        </div>
        <div class="flex flex-col gap-2">
          <label for="supply-supplier" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('inventory.supplies.defaultSupplier') }}
          </label>
          <Select
            v-model="form.default_supplier_id"
            input-id="supply-supplier"
            :options="supplierOptions"
            option-label="label"
            option-value="value"
            fluid
          />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label for="supply-sku" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('inventory.supplies.sku') }}
          </label>
          <InputText id="supply-sku" v-model="form.sku" fluid dir="ltr" />
        </div>
        <div class="flex flex-col gap-2">
          <label for="supply-uom" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('inventory.supplies.unitOfMeasure') }}
          </label>
          <InputText
            id="supply-uom"
            v-model="form.unit_of_measure"
            :placeholder="t('inventory.supplies.unitOfMeasurePlaceholder')"
            :invalid="!!errors.unit_of_measure"
            fluid
          />
        </div>
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div class="flex flex-col gap-2">
          <label for="supply-cost" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('inventory.supplies.unitCost') }}
          </label>
          <InputNumber
            v-model="form.unit_cost"
            input-id="supply-cost"
            :min="0"
            :max-fraction-digits="2"
            fluid
          />
        </div>
        <div class="flex flex-col gap-2">
          <label for="supply-reorder-level" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('inventory.supplies.reorderLevel') }}
          </label>
          <InputNumber v-model="form.reorder_level" input-id="supply-reorder-level" :min="0" fluid />
        </div>
        <div class="flex flex-col gap-2">
          <label for="supply-reorder-qty" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('inventory.supplies.reorderQuantity') }}
          </label>
          <InputNumber v-model="form.reorder_quantity" input-id="supply-reorder-qty" :min="1" fluid />
        </div>
      </div>

      <div class="flex items-center gap-3">
        <ToggleSwitch v-model="form.is_active" input-id="supply-active" />
        <label for="supply-active" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.supplies.active') }}
        </label>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
