<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import ColorPicker from 'primevue/colorpicker'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'
import Message from 'primevue/message'
import DurationInput from './DurationInput.vue'
import { useAppointmentTypesStore } from '@/stores/appointmentTypes'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import type { AppointmentType } from '@/types/appointment'

const HEX_COLOR_PATTERN = /^#[0-9A-Fa-f]{6}$/

/**
 * Create/Edit an appointment type (design doc §7). Mirrors AppointmentDialog's
 * watch-props.visible-to-reset-and-prefill convention rather than UsersView's
 * open{Create,Edit}Dialog methods, since this is a standalone dialog component here, not inline
 * view state.
 */
const props = defineProps<{ visible: boolean; appointmentType?: AppointmentType }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: [appointmentType: AppointmentType]
}>()

const { t } = useI18n()
const toast = useToast()
const store = useAppointmentTypesStore()

const isEditMode = computed(() => Boolean(props.appointmentType))
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

function emptyForm() {
  return {
    name: '',
    default_duration_minutes: 30,
    color: '#3B82F6',
    is_active: true,
  }
}

const form = reactive(emptyForm())

// PrimeVue's ColorPicker binds a bare 6-hex-digit string (no '#', its documented `format: 'hex'`
// default) — this proxy keeps the form's single source of truth as the '#RRGGBB' string the
// backend expects, matching AppointmentTypeSelect/AppointmentStatusChip's color convention.
const colorPickerValue = computed({
  get: () => form.color.replace('#', ''),
  set: (hex: string) => {
    form.color = `#${hex}`
  },
})

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return

    errors.value = {}
    const source = props.appointmentType

    if (source) {
      Object.assign(form, {
        name: source.name,
        default_duration_minutes: source.default_duration_minutes,
        color: source.color,
        is_active: source.is_active,
      })
    } else {
      Object.assign(form, emptyForm())
    }
  },
  // Also initializes correctly if a parent ever mounts this dialog already-visible, not just on
  // the false→true transition (matches AppointmentDialog's identical convention).
  { immediate: true },
)

// Design doc §14 (Focus management): returns focus to whatever opened this dialog once it closes.
useDialogFocusRestore(() => props.visible)

function validate(): boolean {
  const nextErrors: Record<string, string[]> = {}

  if (!form.name.trim()) nextErrors.name = [t('appointments.types.fieldRequired')]
  if (!HEX_COLOR_PATTERN.test(form.color)) nextErrors.color = [t('appointments.types.colorInvalid')]

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
      default_duration_minutes: form.default_duration_minutes,
      color: form.color,
      is_active: form.is_active,
    }

    const saved = props.appointmentType
      ? await store.update(props.appointmentType.id, payload)
      : await store.create(payload)

    toast.add({ severity: 'success', summary: t('appointments.types.saved'), life: 3000 })
    emit('saved', saved)
    emit('update:visible', false)
  } catch (err: unknown) {
    const response = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } })
      ?.response

    if (response?.status === 422) {
      errors.value = response.data?.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('appointments.types.saveError'), life: 3000 })
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
    :header="isEditMode ? t('appointments.types.editTitle') : t('appointments.types.createTitle')"
    class="w-full max-w-md"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label for="type-name" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('appointments.types.name') }}
        </label>
        <InputText
          id="type-name"
          v-model="form.name"
          :placeholder="t('appointments.types.namePlaceholder')"
          :invalid="!!errors.name"
          fluid
        />
        <Message v-if="errors.name" severity="error" size="small">{{ errors.name[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('appointments.types.duration') }}
        </label>
        <DurationInput v-model="form.default_duration_minutes" />
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('appointments.types.color') }}
        </label>
        <div class="flex items-center gap-3">
          <ColorPicker v-model="colorPickerValue" />
          <InputText v-model="form.color" class="w-32 font-mono" maxlength="7" :invalid="!!errors.color" />
        </div>
        <Message v-if="errors.color" severity="error" size="small">{{ errors.color[0] }}</Message>
      </div>

      <div class="flex items-center gap-3">
        <ToggleSwitch v-model="form.is_active" input-id="type-active" />
        <label for="type-active" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('appointments.types.active') }}
        </label>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
