<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useSuppliersStore } from '@/stores/suppliers'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import type { Supplier } from '@/types/inventory'

/** Create/Edit a Supplier (design doc §6/§11) — mirrors AppointmentTypeFormDialog's
 *  watch-props.visible-to-reset-and-prefill convention. */
const props = defineProps<{ visible: boolean; supplier?: Supplier }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: [supplier: Supplier]
}>()

const { t } = useI18n()
const toast = useToast()
const store = useSuppliersStore()

const isEditMode = computed(() => Boolean(props.supplier))
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

function emptyForm() {
  return {
    name: '',
    contact_name: '',
    phone: '',
    email: '',
    address: '',
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
    const source = props.supplier

    if (source) {
      Object.assign(form, {
        name: source.name,
        contact_name: source.contact_name ?? '',
        phone: source.phone ?? '',
        email: source.email ?? '',
        address: source.address ?? '',
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

  if (!form.name.trim()) nextErrors.name = [t('inventory.suppliers.fieldRequired')]

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
      notes: form.notes.trim() || null,
      is_active: form.is_active,
    }

    const saved = props.supplier
      ? await store.update(props.supplier.id, payload)
      : await store.create(payload)

    toast.add({ severity: 'success', summary: t('inventory.suppliers.saved'), life: 3000 })
    emit('saved', saved)
    emit('update:visible', false)
  } catch (err: unknown) {
    const response = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } })
      ?.response

    if (response?.status === 422) {
      errors.value = response.data?.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('inventory.suppliers.saveError'), life: 3000 })
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
    :header="isEditMode ? t('inventory.suppliers.editTitle') : t('inventory.suppliers.createTitle')"
    class="w-full max-w-md"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label for="supplier-name" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.suppliers.name') }}
        </label>
        <InputText id="supplier-name" v-model="form.name" :invalid="!!errors.name" fluid />
        <Message v-if="errors.name" severity="error" size="small">{{ errors.name[0] }}</Message>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label for="supplier-contact" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('inventory.suppliers.contactName') }}
          </label>
          <InputText id="supplier-contact" v-model="form.contact_name" fluid />
        </div>
        <div class="flex flex-col gap-2">
          <label for="supplier-phone" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('inventory.suppliers.phone') }}
          </label>
          <InputText id="supplier-phone" v-model="form.phone" fluid dir="ltr" />
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <label for="supplier-email" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.suppliers.email') }}
        </label>
        <InputText id="supplier-email" v-model="form.email" type="email" fluid dir="ltr" />
        <Message v-if="errors.email" severity="error" size="small">{{ errors.email[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label for="supplier-address" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.suppliers.address') }}
        </label>
        <Textarea id="supplier-address" v-model="form.address" auto-resize rows="2" fluid />
      </div>

      <div class="flex flex-col gap-2">
        <label for="supplier-notes" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.suppliers.notes') }}
        </label>
        <Textarea id="supplier-notes" v-model="form.notes" auto-resize rows="2" fluid />
      </div>

      <div class="flex items-center gap-3">
        <ToggleSwitch v-model="form.is_active" input-id="supplier-active" />
        <label for="supplier-active" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.suppliers.active') }}
        </label>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
