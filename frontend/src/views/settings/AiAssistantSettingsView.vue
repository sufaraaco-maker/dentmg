<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import Skeleton from 'primevue/skeleton'
import ToggleSwitch from 'primevue/toggleswitch'
import Message from 'primevue/message'
import Button from 'primevue/button'
import { getClinicSettings, updateClinicSettings } from '@/services/settings'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import type { ClinicSetting } from '@/types/settings'

/**
 * AI Assistant Settings (design doc §5/§7) — the admin-only gate for the whole module. The general
 * toggle controls the three zero-PHI features; the PHI acknowledgment toggle is a hard, separate
 * gate for Clinical Notes draft-assist/Treatment Suggestions (Approval decision 1, 2026-07-31) —
 * kept as its own confirmation, not folded into the general toggle, so enabling the zero-PHI
 * features never implicitly acknowledges a legal precondition the admin didn't actually confirm.
 */
const { t } = useI18n()
const toast = useToast()
const aiAssistantStore = useAiAssistantStore()

const loading = ref(true)
const saving = ref(false)

const form = reactive({ ai_assistant_enabled: false, ai_assistant_phi_features_acknowledged: false })

function applySettings(settings: ClinicSetting) {
  form.ai_assistant_enabled = settings.ai_assistant_enabled
  form.ai_assistant_phi_features_acknowledged = settings.ai_assistant_phi_features_acknowledged
}

async function load() {
  loading.value = true
  try {
    applySettings(await getClinicSettings())
  } catch {
    toast.add({ severity: 'error', summary: t('settings.aiAssistant.loadError'), life: 3000 })
  } finally {
    loading.value = false
  }
}

onMounted(load)

// Sends only the two toggle fields it actually owns — the backend's `name` validation only
// applies `required` when the field is present (`UpdateClinicSettingRequest`), so this screen
// never needs to know or resend Practice Settings' own fields, and never fails to save just
// because a fresh clinic hasn't set a practice name yet.
async function submit() {
  saving.value = true
  try {
    const saved = await updateClinicSettings({
      ai_assistant_enabled: form.ai_assistant_enabled,
      ai_assistant_phi_features_acknowledged: form.ai_assistant_phi_features_acknowledged,
    })
    applySettings(saved)
    await aiAssistantStore.load(true)
    toast.add({ severity: 'success', summary: t('settings.aiAssistant.saved'), life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: t('settings.aiAssistant.saveError'), life: 3000 })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
      {{ t('settings.aiAssistant.title') }}
    </h1>

    <Skeleton v-if="loading" height="20rem" />

    <Card v-else class="max-w-2xl">
      <template #content>
        <form class="flex flex-col gap-5" @submit.prevent="submit">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="font-medium text-surface-900 dark:text-surface-0">
                {{ t('settings.aiAssistant.enableLabel') }}
              </p>
              <p class="text-sm text-surface-500">{{ t('settings.aiAssistant.enableHint') }}</p>
            </div>
            <ToggleSwitch v-model="form.ai_assistant_enabled" />
          </div>

          <div
            v-if="form.ai_assistant_enabled"
            class="flex flex-col gap-3 rounded-lg border border-surface-200 p-4 dark:border-surface-700"
          >
            <Message severity="warn" :closable="false">
              {{ t('settings.aiAssistant.phiWarning') }}
            </Message>

            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="font-medium text-surface-900 dark:text-surface-0">
                  {{ t('settings.aiAssistant.phiAckLabel') }}
                </p>
                <p class="text-sm text-surface-500">{{ t('settings.aiAssistant.phiAckHint') }}</p>
              </div>
              <ToggleSwitch v-model="form.ai_assistant_phi_features_acknowledged" />
            </div>
          </div>

          <div class="flex justify-end pt-2">
            <Button type="submit" :label="t('common.save')" :loading="saving" />
          </div>
        </form>
      </template>
    </Card>
  </div>
</template>
