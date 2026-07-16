<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'

const { t } = useI18n()
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center gap-3 p-6 text-center">
    <span class="text-5xl font-bold text-surface-300 dark:text-surface-600">
      {{ t('errors.pageNotFound.code') }}
    </span>
    <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
      {{ t('errors.pageNotFound.heading') }}
    </h1>
    <p class="text-surface-500 dark:text-surface-400">{{ t('errors.pageNotFound.message') }}</p>
    <Button
      class="mt-2"
      :label="t('errors.pageNotFound.backToDashboard')"
      @click="$router.push({ name: 'dashboard' })"
    />
  </div>
</template>
