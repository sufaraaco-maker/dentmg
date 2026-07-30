<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import Skeleton from 'primevue/skeleton'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Message from 'primevue/message'
import Button from 'primevue/button'
import { getClinicSettings, updateClinicSettings } from '@/services/settings'
import type { ClinicSetting } from '@/types/settings'

/**
 * Practice Settings (design doc §4.1/§7) — a single-form edit screen for the clinic-identity
 * singleton, promoted to a full page instead of a modal since there's exactly one record, mirroring
 * `LabsView.vue`'s edit-dialog form content just without the dialog chrome.
 */
const { t } = useI18n()
const toast = useToast()

const loading = ref(true)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = reactive({ name: '', phone: '', address: '', email: '' })

function applySettings(settings: ClinicSetting) {
  form.name = settings.name
  form.phone = settings.phone ?? ''
  form.address = settings.address ?? ''
  form.email = settings.email ?? ''
}

async function load() {
  loading.value = true
  try {
    applySettings(await getClinicSettings())
  } catch {
    toast.add({ severity: 'error', summary: t('settings.practice.loadError'), life: 3000 })
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function submit() {
  saving.value = true
  errors.value = {}

  try {
    const saved = await updateClinicSettings({
      name: form.name.trim(),
      phone: form.phone.trim() || null,
      address: form.address.trim() || null,
      email: form.email.trim() || null,
    })
    applySettings(saved)
    toast.add({ severity: 'success', summary: t('settings.practice.saved'), life: 3000 })
  } catch (err: unknown) {
    const response = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } })
      ?.response

    if (response?.status === 422) {
      errors.value = response.data?.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('settings.practice.saveError'), life: 3000 })
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
      {{ t('settings.practice.title') }}
    </h1>

    <Skeleton v-if="loading" height="20rem" />

    <Card v-else class="max-w-2xl">
      <template #content>
        <form class="flex flex-col gap-4" @submit.prevent="submit">
          <div class="flex flex-col gap-2">
            <label for="practice-name" class="text-sm text-surface-700 dark:text-surface-200">
              {{ t('settings.practice.name') }}
            </label>
            <InputText id="practice-name" v-model="form.name" :invalid="!!errors.name" fluid />
            <Message v-if="errors.name" severity="error" size="small">{{ errors.name[0] }}</Message>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="flex flex-col gap-2">
              <label for="practice-phone" class="text-sm text-surface-700 dark:text-surface-200">
                {{ t('settings.practice.phone') }}
              </label>
              <InputText id="practice-phone" v-model="form.phone" :invalid="!!errors.phone" fluid dir="ltr" />
              <Message v-if="errors.phone" severity="error" size="small">{{ errors.phone[0] }}</Message>
            </div>
            <div class="flex flex-col gap-2">
              <label for="practice-email" class="text-sm text-surface-700 dark:text-surface-200">
                {{ t('settings.practice.email') }}
              </label>
              <InputText
                id="practice-email"
                v-model="form.email"
                type="email"
                :invalid="!!errors.email"
                fluid
                dir="ltr"
              />
              <Message v-if="errors.email" severity="error" size="small">{{ errors.email[0] }}</Message>
            </div>
          </div>

          <div class="flex flex-col gap-2">
            <label for="practice-address" class="text-sm text-surface-700 dark:text-surface-200">
              {{ t('settings.practice.address') }}
            </label>
            <Textarea
              id="practice-address"
              v-model="form.address"
              :invalid="!!errors.address"
              auto-resize
              rows="3"
              fluid
            />
            <Message v-if="errors.address" severity="error" size="small">{{ errors.address[0] }}</Message>
          </div>

          <div class="flex justify-end pt-2">
            <Button type="submit" :label="t('common.save')" :loading="saving" />
          </div>
        </form>
      </template>
    </Card>
  </div>
</template>
