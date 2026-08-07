<script setup lang="ts">
import type { Component } from 'vue'
import Button from 'primevue/button'
import { Inbox } from 'lucide-vue-next'

/**
 * First component in `components/common/` — a new, deliberate convention (Phase 2.1, design doc
 * §15.1). No such shared/generic folder existed before this: every reusable pattern in this app
 * was previously "copy the convention," not "import a base component" (Step 1 analysis). This is
 * referenced as already built in `frontend-visual-redesign-design.md` §6, but was never actually
 * created — every panel that needs an empty state (Imaging, Treatment Plans, Clinical Notes, and
 * this phase's Medical History/Documents/Timeline once those land) hand-rolled its own `<p>` text
 * instead. Callers pass already-translated strings (`t(...)` at the call site), matching every
 * other presentational component in this codebase (e.g. `InvoiceStatusChip.vue`) — this component
 * has no i18n dependency of its own.
 */
withDefaults(
  defineProps<{
    icon?: Component
    title: string
    description?: string
    actionLabel?: string
  }>(),
  { icon: () => Inbox, description: undefined, actionLabel: undefined },
)

const emit = defineEmits<{ action: [] }>()
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
    <component :is="icon" :size="32" class="text-surface-400 dark:text-surface-500" />
    <p class="text-sm font-medium text-surface-700 dark:text-surface-200">{{ title }}</p>
    <p v-if="description" class="max-w-sm text-sm text-surface-500">{{ description }}</p>
    <Button v-if="actionLabel" :label="actionLabel" size="small" text class="mt-1" @click="emit('action')" />
  </div>
</template>
