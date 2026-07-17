<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import Card from 'primevue/card'

/**
 * Generic "coming soon" card — reused across the Detail view's future-module slots (Treatment
 * Plan, Invoices, Clinical Notes, Attachments) and the Audit History slot (§4.2, §4.7). Renders
 * as an honest disabled-looking card, not a fake tab with simulated content.
 */
const props = defineProps<{
  icon: string
  titleKey: string
  moduleName?: string
}>()

const { t } = useI18n()
</script>

<template>
  <Card class="opacity-70 grayscale-[30%]">
    <template #content>
      <div class="flex flex-col items-center gap-2 py-4 text-center">
        <i :class="[props.icon, 'text-2xl text-surface-400 dark:text-surface-500']" />
        <span class="font-medium text-surface-600 dark:text-surface-300">{{ t(props.titleKey) }}</span>
        <span class="text-xs text-surface-400 dark:text-surface-500">
          {{ t('appointments.detail.placeholders.comingSoon') }}
        </span>
      </div>
    </template>
  </Card>
</template>
