<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import NotificationCenter from '@/components/notifications/NotificationCenter.vue'

/**
 * Notification System (Phase 5A) design doc §10.1 — the full-page history view.
 *
 * Reachable from the header bell's panel and by direct URL. Mounts the very same
 * `NotificationCenter` the popover and mobile drawer use (just not `compact`), so filtering,
 * marking read, and deep-linking cannot behave differently here than in the panel.
 *
 * No `meta.roles` guard, deliberately: every role has notifications of their own, and the API is
 * self-scoped (design doc §8.1 / Decision D6). There is nothing role-specific to gate.
 */
const { t } = useI18n()
</script>

<template>
  <div class="flex flex-col gap-4">
    <h1 class="text-xl font-semibold text-surface-900 dark:text-surface-0">
      {{ t('notifications.title') }}
    </h1>

    <div
      class="overflow-hidden rounded-xl border border-surface-200 bg-surface-0 shadow-sm dark:border-surface-700 dark:bg-surface-900"
    >
      <NotificationCenter />
    </div>
  </div>
</template>
