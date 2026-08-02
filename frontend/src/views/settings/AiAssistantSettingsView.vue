<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import Card from 'primevue/card'
import Skeleton from 'primevue/skeleton'
import ToggleSwitch from 'primevue/toggleswitch'
import Message from 'primevue/message'
import Button from 'primevue/button'
import Password from 'primevue/password'
import { getClinicSettings, updateClinicSettings } from '@/services/settings'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import type { ClinicSetting } from '@/types/settings'

/**
 * AI Assistant Settings (design doc §5/§7, key management per
 * ai-assistant-settings-api-key-design.md §7) — the admin-only gate for the whole module. The
 * general toggle controls the three zero-PHI features; the PHI acknowledgment toggle is a hard,
 * separate gate for Clinical Notes draft-assist/Treatment Suggestions (Approval decision 1,
 * 2026-07-31) — kept as its own confirmation, not folded into the general toggle, so enabling the
 * zero-PHI features never implicitly acknowledges a legal precondition the admin didn't actually
 * confirm. The API key section is a third, independent action (its own Save button) — setting a
 * key must not require also touching the toggles, and vice versa.
 */
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const aiAssistantStore = useAiAssistantStore()

const loading = ref(true)
const saving = ref(false)

const form = reactive({ ai_assistant_enabled: false, ai_assistant_phi_features_acknowledged: false })

const apiKeyConfigured = ref(false)
const apiKeyLast4 = ref<string | null>(null)
// Never pre-filled from a GET response — the API never returns the raw key
// (ai-assistant-settings-api-key-design.md §5). `null` = showing the "configured" chip;
// non-null (starting at '') = the input is open for a new value.
const apiKeyInput = ref<string | null>(null)
const savingApiKey = ref(false)

function applySettings(settings: ClinicSetting) {
  form.ai_assistant_enabled = settings.ai_assistant_enabled
  form.ai_assistant_phi_features_acknowledged = settings.ai_assistant_phi_features_acknowledged
  apiKeyConfigured.value = settings.ai_assistant_api_key_configured
  apiKeyLast4.value = settings.ai_assistant_api_key_last4
  apiKeyInput.value = null
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

const canSaveApiKey = computed(() => !!apiKeyInput.value)

function startReplaceApiKey() {
  apiKeyInput.value = ''
}

function cancelApiKeyEdit() {
  apiKeyInput.value = null
}

async function saveApiKey() {
  if (!apiKeyInput.value) return
  savingApiKey.value = true
  try {
    const saved = await updateClinicSettings({ ai_assistant_api_key: apiKeyInput.value })
    applySettings(saved)
    await aiAssistantStore.load(true)
    toast.add({ severity: 'success', summary: t('settings.aiAssistant.apiKeySaved'), life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: t('settings.aiAssistant.apiKeySaveError'), life: 3000 })
  } finally {
    savingApiKey.value = false
  }
}

function confirmRemoveApiKey() {
  confirm.require({
    message: t('settings.aiAssistant.apiKeyRemoveConfirmMessage'),
    header: t('settings.aiAssistant.apiKeyRemoveConfirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: removeApiKey,
  })
}

async function removeApiKey() {
  savingApiKey.value = true
  try {
    const saved = await updateClinicSettings({ ai_assistant_api_key: null })
    applySettings(saved)
    await aiAssistantStore.load(true)
    toast.add({ severity: 'success', summary: t('settings.aiAssistant.apiKeyRemoved'), life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: t('settings.aiAssistant.apiKeyRemoveError'), life: 3000 })
  } finally {
    savingApiKey.value = false
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

        <div
          v-if="form.ai_assistant_enabled"
          class="mt-2 flex flex-col gap-2 border-t border-surface-200 pt-5 dark:border-surface-700"
        >
          <p class="font-medium text-surface-900 dark:text-surface-0">
            {{ t('settings.aiAssistant.apiKeyLabel') }}
          </p>
          <p class="text-sm text-surface-500">{{ t('settings.aiAssistant.apiKeyHint') }}</p>

          <div
            v-if="apiKeyConfigured && apiKeyInput === null"
            class="flex items-center justify-between gap-4"
          >
            <span class="text-sm text-surface-700 dark:text-surface-200">
              {{ t('settings.aiAssistant.apiKeyConfigured', { last4: apiKeyLast4 }) }}
            </span>
            <div class="flex shrink-0 gap-2">
              <Button
                text
                size="small"
                :label="t('settings.aiAssistant.apiKeyReplace')"
                @click="startReplaceApiKey"
              />
              <Button
                text
                severity="danger"
                size="small"
                :label="t('settings.aiAssistant.apiKeyRemove')"
                :loading="savingApiKey"
                @click="confirmRemoveApiKey"
              />
            </div>
          </div>

          <div v-else class="flex flex-col gap-3">
            <Password
              v-model="apiKeyInput"
              :feedback="false"
              toggle-mask
              fluid
              :placeholder="t('settings.aiAssistant.apiKeyPlaceholder')"
            />
            <div class="flex justify-end gap-2">
              <Button
                v-if="apiKeyConfigured"
                text
                :label="t('common.cancel')"
                :disabled="savingApiKey"
                @click="cancelApiKeyEdit"
              />
              <Button
                :label="t('settings.aiAssistant.apiKeySave')"
                :disabled="!canSaveApiKey"
                :loading="savingApiKey"
                @click="saveApiKey"
              />
            </div>
          </div>
        </div>
      </template>
    </Card>
  </div>
</template>
