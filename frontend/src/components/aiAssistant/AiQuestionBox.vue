<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from 'primevue/card'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import { aiAssistantErrorCode } from '@/services/aiAssistant/aiAssistantApi'
import type { AiQaResponse } from '@/types/aiAssistant'

/**
 * Shared "ask a question, get a text answer" shape used by both Dashboard Insights and Smart
 * Search (design doc §4.1/§4.2) — identical UI, different endpoint. Self-hides entirely when the
 * AI Assistant is off (design doc §2: absent, not just disabled, until enabled) rather than
 * rendering a disabled placeholder.
 */
const props = defineProps<{
  title: string
  placeholder: string
  askFn: (question: string) => Promise<AiQaResponse>
}>()

const { t } = useI18n()
const aiAssistantStore = useAiAssistantStore()

const question = ref('')
const answer = ref<string | null>(null)
const asking = ref(false)
const errorMessage = ref<string | null>(null)

onMounted(() => aiAssistantStore.load())

async function ask() {
  if (!question.value.trim()) return

  asking.value = true
  answer.value = null
  errorMessage.value = null
  try {
    const result = await props.askFn(question.value.trim())
    answer.value = result.answer
  } catch (err) {
    errorMessage.value =
      aiAssistantErrorCode(err) === 'ai_assistant_unavailable'
        ? t('aiAssistant.unavailableError')
        : t('aiAssistant.genericError')
  } finally {
    asking.value = false
  }
}
</script>

<template>
  <Card v-if="aiAssistantStore.loaded && aiAssistantStore.enabled">
    <template #title>
      <div class="flex items-center gap-2">
        <i class="pi pi-sparkles text-primary" />
        <span>{{ title }}</span>
      </div>
    </template>
    <template #content>
      <form class="flex flex-col gap-3" @submit.prevent="ask">
        <Textarea v-model="question" :placeholder="placeholder" rows="2" auto-resize maxlength="1000" fluid />
        <div class="flex justify-end">
          <Button
            type="submit"
            :label="t('aiAssistant.ask')"
            icon="pi pi-send"
            size="small"
            :loading="asking"
            :disabled="!question.trim()"
          />
        </div>

        <Message v-if="errorMessage" severity="error" size="small">{{ errorMessage }}</Message>

        <div
          v-if="answer"
          class="rounded-lg bg-surface-50 p-3 text-sm text-surface-700 dark:bg-surface-800 dark:text-surface-200"
        >
          {{ answer }}
        </div>
      </form>
    </template>
  </Card>
</template>
