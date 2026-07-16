<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import Card from 'primevue/card'

const { t } = useI18n()
</script>

<template>
  <Card>
    <template #title>{{ t('appointments.nav.schedule') }}</template>
    <template #content>
      <p class="text-surface-600 dark:text-surface-300">{{ t('appointments.comingSoon') }}</p>
    </template>
  </Card>
</template>
