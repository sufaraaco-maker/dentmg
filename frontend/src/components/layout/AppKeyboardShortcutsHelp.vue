<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import Dialog from 'primevue/dialog'
import { useShortcutsHelpStore } from '@/stores/shortcutsHelp'

/**
 * Global `?`-triggered shortcut reference (frontend-ux-redesign design doc §5.4/§8) — the
 * app-wide counterpart to `appointments/KeyboardShortcutsHelp.vue` (which stays calendar-scoped
 * and untouched). Same "informational dialog, no store/API writes" shape.
 */
const { t } = useI18n()
const shortcutsHelp = useShortcutsHelpStore()

const SHORTCUTS = [
  { keys: 'Ctrl / ⌘ + K', labelKey: 'shortcuts.commandPalette' },
  { keys: 'G then D', labelKey: 'shortcuts.goToDashboard' },
  { keys: 'G then P', labelKey: 'shortcuts.goToPatients' },
  { keys: 'G then A', labelKey: 'shortcuts.goToAppointments' },
  { keys: 'G then R', labelKey: 'shortcuts.goToReports' },
  { keys: 'G then S', labelKey: 'shortcuts.goToSettings' },
  { keys: '?', labelKey: 'shortcuts.showHelp' },
] as const
</script>

<template>
  <Dialog v-model:visible="shortcutsHelp.open" modal :header="t('shortcuts.title')" class="w-full max-w-md">
    <dl class="flex flex-col gap-3">
      <div
        v-for="shortcut in SHORTCUTS"
        :key="shortcut.labelKey"
        class="flex items-center justify-between gap-4"
      >
        <dt class="text-sm text-surface-600 dark:text-surface-300">{{ t(shortcut.labelKey) }}</dt>
        <dd>
          <kbd
            class="rounded border border-surface-300 bg-surface-100 px-2 py-0.5 font-mono text-xs text-surface-700 dark:border-surface-600 dark:bg-surface-800 dark:text-surface-200"
          >
            {{ shortcut.keys }}
          </kbd>
        </dd>
      </div>
    </dl>
  </Dialog>
</template>
