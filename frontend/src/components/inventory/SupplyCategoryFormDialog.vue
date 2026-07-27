<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useSupplyCategoriesStore } from '@/stores/supplyCategories'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import type { SupplyCategory } from '@/types/inventory'

/** Create/Edit a Supply Category (design doc §6/§11) — mirrors SupplierFormDialog exactly. */
const props = defineProps<{ visible: boolean; category?: SupplyCategory }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: [category: SupplyCategory]
}>()

const { t } = useI18n()
const toast = useToast()
const store = useSupplyCategoriesStore()

const isEditMode = computed(() => Boolean(props.category))
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

function emptyForm() {
  return { name: '', sort_order: 0, is_active: true }
}

const form = reactive(emptyForm())

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return

    errors.value = {}
    const source = props.category

    if (source) {
      Object.assign(form, { name: source.name, sort_order: source.sort_order, is_active: source.is_active })
    } else {
      Object.assign(form, emptyForm())
    }
  },
  { immediate: true },
)

useDialogFocusRestore(() => props.visible)

function validate(): boolean {
  const nextErrors: Record<string, string[]> = {}

  if (!form.name.trim()) nextErrors.name = [t('inventory.categories.fieldRequired')]

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (!validate()) return

  saving.value = true
  errors.value = {}

  try {
    const payload = { name: form.name.trim(), sort_order: form.sort_order, is_active: form.is_active }

    const saved = props.category ? await store.update(props.category.id, payload) : await store.create(payload)

    toast.add({ severity: 'success', summary: t('inventory.categories.saved'), life: 3000 })
    emit('saved', saved)
    emit('update:visible', false)
  } catch (err: unknown) {
    const response = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } })
      ?.response

    if (response?.status === 422) {
      errors.value = response.data?.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('inventory.categories.saveError'), life: 3000 })
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
    :header="isEditMode ? t('inventory.categories.editTitle') : t('inventory.categories.createTitle')"
    class="w-full max-w-sm"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label for="category-name" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.categories.name') }}
        </label>
        <InputText id="category-name" v-model="form.name" :invalid="!!errors.name" fluid />
        <Message v-if="errors.name" severity="error" size="small">{{ errors.name[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label for="category-sort" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.categories.sortOrder') }}
        </label>
        <InputNumber id="category-sort" v-model="form.sort_order" :min="0" fluid />
      </div>

      <div class="flex items-center gap-3">
        <ToggleSwitch v-model="form.is_active" input-id="category-active" />
        <label for="category-active" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.categories.active') }}
        </label>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
