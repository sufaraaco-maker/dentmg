<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import { aiAssistantErrorCode, getReportNarrative } from '@/services/aiAssistant/aiAssistantApi'

/**
 * "Explain this report" (design doc §4.3) — narrates the report's already-computed summary. The
 * backend recomputes the report itself from `reportType`/`params` rather than trusting a
 * client-supplied summary, so the params passed here are exactly what the report view's own filters
 * currently hold, not the fetched result.
 */
const props = defineProps<{
  reportType: string
  params: Record<string, string | null | undefined>
}>()

const { t } = useI18n()
const aiAssistantStore = useAiAssistantStore()

const narrative = ref<string | null>(null)
const loading = ref(false)
const errorMessage = ref<string | null>(null)

onMounted(() => aiAssistantStore.load())

async function explain() {
  loading.value = true
  narrative.value = null
  errorMessage.value = null
  try {
    const result = await getReportNarrative(props.reportType, props.params)
    narrative.value = result.narrative
  } catch (err) {
    errorMessage.value =
      aiAssistantErrorCode(err) === 'ai_assistant_unavailable'
        ? t('aiAssistant.unavailableError')
        : t('aiAssistant.genericError')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div v-if="aiAssistantStore.loaded && aiAssistantStore.enabled" class="flex flex-col gap-3">
    <Button
      :label="t('aiAssistant.reportNarrative.explain')"
      icon="pi pi-sparkles"
      severity="secondary"
      outlined
      size="small"
      :loading="loading"
      @click="explain"
    />

    <Message v-if="errorMessage" severity="error" size="small">{{ errorMessage }}</Message>

    <div
      v-if="narrative"
      class="rounded-lg bg-surface-50 p-3 text-sm text-surface-700 dark:bg-surface-800 dark:text-surface-200"
    >
      <i class="pi pi-sparkles me-1 text-primary" />{{ narrative }}
    </div>
  </div>
</template>
