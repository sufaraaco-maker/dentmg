<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useLabsStore } from '@/stores/labs'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import type { Lab } from '@/types/laboratory'

/** Create/Edit a Lab (design doc §3/§6) — mirrors SupplierFormDialog's
 *  watch-props.visible-to-reset-and-prefill convention. */
const props = defineProps<{ visible: boolean; lab?: Lab }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: [lab: Lab]
}>()

const { t } = useI18n()
const toast = useToast()
const store = useLabsStore()

const isEditMode = computed(() => Boolean(props.lab))
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

function emptyForm() {
  return {
    name: '',
    contact_name: '',
    phone: '',
    email: '',
    address: '',
    default_turnaround_days: null as number | null,
    notes: '',
    is_active: true,
  }
}

const form = reactive(emptyForm())

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return

    errors.value = {}
    const source = props.lab

    if (source) {
      Object.assign(form, {
        name: source.name,
        contact_name: source.contact_name ?? '',
        phone: source.phone ?? '',
        email: source.email ?? '',
        address: source.address ?? '',
        default_turnaround_days: source.default_turnaround_days,
        notes: source.notes ?? '',
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

  if (!form.name.trim()) nextErrors.name = [t('laboratory.labs.fieldRequired')]

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
      contact_name: form.contact_name.trim() || null,
      phone: form.phone.trim() || null,
      email: form.email.trim() || null,
      address: form.address.trim() || null,
      default_turnaround_days: form.default_turnaround_days,
      notes: form.notes.trim() || null,
      is_active: form.is_active,
    }

    const saved = props.lab ? await store.update(props.lab.id, payload) : await store.create(payload)

    toast.add({ severity: 'success', summary: t('laboratory.labs.saved'), life: 3000 })
    emit('saved', saved)
    emit('update:visible', false)
  } catch (err: unknown) {
    const response = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } })
      ?.response

    if (response?.status === 422) {
      errors.value = response.data?.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('laboratory.labs.saveError'), life: 3000 })
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
    :header="isEditMode ? t('laboratory.labs.editTitle') : t('laboratory.labs.createTitle')"
    class="w-full max-w-md"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label for="lab-name" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('laboratory.labs.name') }}
        </label>
        <InputText id="lab-name" v-model="form.name" :invalid="!!errors.name" fluid />
        <Message v-if="errors.name" severity="error" size="small">{{ errors.name[0] }}</Message>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label for="lab-contact" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('laboratory.labs.contactName') }}
          </label>
          <InputText id="lab-contact" v-model="form.contact_name" fluid />
        </div>
        <div class="flex flex-col gap-2">
          <label for="lab-phone" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('laboratory.labs.phone') }}
          </label>
          <InputText id="lab-phone" v-model="form.phone" fluid dir="ltr" />
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <label for="lab-email" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('laboratory.labs.email') }}
        </label>
        <InputText id="lab-email" v-model="form.email" type="email" fluid dir="ltr" />
        <Message v-if="errors.email" severity="error" size="small">{{ errors.email[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label for="lab-address" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('laboratory.labs.address') }}
        </label>
        <Textarea id="lab-address" v-model="form.address" auto-resize rows="2" fluid />
      </div>

      <div class="flex flex-col gap-2">
        <label for="lab-turnaround" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('laboratory.labs.defaultTurnaroundDays') }}
        </label>
        <InputNumber
          v-model="form.default_turnaround_days"
          input-id="lab-turnaround"
          :min="1"
          :max="180"
          show-buttons
          fluid
        />
      </div>

      <div class="flex flex-col gap-2">
        <label for="lab-notes" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('laboratory.labs.notes') }}
        </label>
        <Textarea id="lab-notes" v-model="form.notes" auto-resize rows="2" fluid />
      </div>

      <div class="flex items-center gap-3">
        <ToggleSwitch v-model="form.is_active" input-id="lab-active" />
        <label for="lab-active" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('laboratory.labs.active') }}
        </label>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
