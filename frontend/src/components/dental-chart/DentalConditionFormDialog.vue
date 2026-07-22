<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import ColorPicker from 'primevue/colorpicker'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useDentalConditionsStore } from '@/stores/dentalConditions'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import { DENTAL_CONDITION_CATEGORIES, type DentalCondition } from '@/types/dentalChart'

const HEX_COLOR_PATTERN = /^#[0-9A-Fa-f]{6}$/

/**
 * Create/Edit a dental condition catalog entry (implementation plan §1.6/§2.1). Mirrors
 * AppointmentTypeFormDialog's watch-props.visible-to-reset-and-prefill convention exactly.
 */
const props = defineProps<{ visible: boolean; condition?: DentalCondition }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: [condition: DentalCondition]
}>()

const { t } = useI18n()
const toast = useToast()
const store = useDentalConditionsStore()

const isEditMode = computed(() => Boolean(props.condition))
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const categoryOptions = computed(() =>
  DENTAL_CONDITION_CATEGORIES.map((category) => ({
    value: category,
    label: t(`dentalChart.category.${category}`),
  })),
)

function emptyForm() {
  return {
    name: '',
    category: 'finding' as (typeof DENTAL_CONDITION_CATEGORIES)[number],
    applies_to_surface: false,
    default_color: '#DC2626',
    icon_key: '',
    sort_order: null as number | null,
    is_active: true,
  }
}

const form = reactive(emptyForm())

// PrimeVue's ColorPicker binds a bare 6-hex-digit string (no '#'), same proxy pattern as
// AppointmentTypeFormDialog's color field.
const colorPickerValue = computed({
  get: () => form.default_color.replace('#', ''),
  set: (hex: string) => {
    form.default_color = `#${hex}`
  },
})

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return

    errors.value = {}
    const source = props.condition

    if (source) {
      Object.assign(form, {
        name: source.name,
        category: source.category,
        applies_to_surface: source.applies_to_surface,
        default_color: source.default_color,
        icon_key: source.icon_key ?? '',
        sort_order: source.sort_order ?? null,
        is_active: source.is_active,
      })
    } else {
      Object.assign(form, emptyForm())
    }
  },
  { immediate: true },
)

useDialogFocusRestore(() => props.visible)

function validate(): boolean {
  const nextErrors: Record<string, string[]> = {}

  if (!form.name.trim()) nextErrors.name = [t('dentalChart.conditions.fieldRequired')]
  if (!HEX_COLOR_PATTERN.test(form.default_color)) {
    nextErrors.default_color = [t('dentalChart.conditions.colorInvalid')]
  }

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (!validate()) return

  saving.value = true
  errors.value = {}

  try {
    const payload = {
      name: form.name.trim(),
      category: form.category,
      applies_to_surface: form.applies_to_surface,
      default_color: form.default_color,
      icon_key: form.icon_key.trim() || null,
      sort_order: form.sort_order,
      is_active: form.is_active,
    }

    const saved = props.condition
      ? await store.update(props.condition.id, payload)
      : await store.create(payload)

    toast.add({ severity: 'success', summary: t('dentalChart.conditions.saved'), life: 3000 })
    emit('saved', saved)
    emit('update:visible', false)
  } catch (err: unknown) {
    const response = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } })
      ?.response

    if (response?.status === 422) {
      errors.value = response.data?.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('dentalChart.conditions.saveError'), life: 3000 })
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
    :header="isEditMode ? t('dentalChart.conditions.editTitle') : t('dentalChart.conditions.createTitle')"
    class="w-full max-w-md"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label for="condition-name" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('dentalChart.conditions.name') }}
        </label>
        <InputText
          id="condition-name"
          v-model="form.name"
          :placeholder="t('dentalChart.conditions.namePlaceholder')"
          :invalid="!!errors.name"
          fluid
        />
        <Message v-if="errors.name" severity="error" size="small">{{ errors.name[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('dentalChart.conditions.category') }}
        </label>
        <Select
          v-model="form.category"
          :options="categoryOptions"
          option-label="label"
          option-value="value"
          fluid
        />
        <Message v-if="errors.category" severity="error" size="small">{{ errors.category[0] }}</Message>
      </div>

      <div class="flex items-center gap-3">
        <ToggleSwitch v-model="form.applies_to_surface" input-id="condition-applies-to-surface" />
        <label for="condition-applies-to-surface" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('dentalChart.conditions.appliesToSurface') }}
        </label>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('dentalChart.conditions.color') }}
        </label>
        <div class="flex items-center gap-3">
          <ColorPicker v-model="colorPickerValue" />
          <InputText
            v-model="form.default_color"
            class="w-32 font-mono"
            maxlength="7"
            :invalid="!!errors.default_color"
          />
        </div>
        <Message v-if="errors.default_color" severity="error" size="small">{{
          errors.default_color[0]
        }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label for="condition-icon-key" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('dentalChart.conditions.iconKey') }}
        </label>
        <InputText
          id="condition-icon-key"
          v-model="form.icon_key"
          :placeholder="t('dentalChart.conditions.iconKeyPlaceholder')"
          maxlength="100"
          :invalid="!!errors.icon_key"
          fluid
        />
        <Message v-if="errors.icon_key" severity="error" size="small">{{ errors.icon_key[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label for="condition-sort-order" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('dentalChart.conditions.sortOrder') }}
        </label>
        <InputNumber
          v-model="form.sort_order"
          input-id="condition-sort-order"
          :min="0"
          show-buttons
          :invalid="!!errors.sort_order"
          fluid
        />
        <Message v-if="errors.sort_order" severity="error" size="small">{{ errors.sort_order[0] }}</Message>
      </div>

      <div class="flex items-center gap-3">
        <ToggleSwitch v-model="form.is_active" input-id="condition-active" />
        <label for="condition-active" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('dentalChart.conditions.active') }}
        </label>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
