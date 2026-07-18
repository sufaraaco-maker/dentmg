<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import Dialog from 'primevue/dialog'

/**
 * `?`-triggered shortcut reference (design doc §2.10) — purely informational, no store/API
 * access. Kept as a real dialog (not a tooltip) so it's reachable the same way for keyboard,
 * mouse, and screen-reader users alike.
 */
defineProps<{ visible: boolean }>()

const emit = defineEmits<{ 'update:visible': [value: boolean] }>()

const { t } = useI18n()

const SHORTCUTS = [
  { keys: 'N', labelKey: 'appointments.calendar.shortcuts.new' },
  { keys: '← / →', labelKey: 'appointments.calendar.shortcuts.navigate' },
  { keys: 'T', labelKey: 'appointments.calendar.shortcuts.today' },
  { keys: '1 – 5', labelKey: 'appointments.calendar.shortcuts.switchView' },
  { keys: '/', labelKey: 'appointments.calendar.shortcuts.focusSearch' },
  { keys: 'Esc', labelKey: 'appointments.calendar.shortcuts.closeDialog' },
  { keys: '?', labelKey: 'appointments.calendar.shortcuts.showHelp' },
] as const
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    :header="t('appointments.calendar.shortcutsHelp')"
    class="w-full max-w-md"
    @update:visible="(v) => emit('update:visible', v)"
  >
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
