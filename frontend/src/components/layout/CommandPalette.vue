<script setup lang="ts">
import { computed, nextTick, ref, watch, type ComponentPublicInstance } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import { useCommandPaletteStore } from '@/stores/commandPalette'
import { useShortcutsHelpStore } from '@/stores/shortcutsHelp'
import { useSidebarPreferencesStore } from '@/stores/sidebarPreferences'
import { useAuthStore } from '@/stores/auth'
import { flattenNavItems } from '@/config/navigation'

interface PaletteAction {
  id: string
  label: string
  icon: string
  keywords: string
  run: () => void
}

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const commandPalette = useCommandPaletteStore()
const shortcutsHelp = useShortcutsHelpStore()
const sidebarPreferences = useSidebarPreferencesStore()

const query = ref('')
const activeIndex = ref(0)
const inputRef = ref<InstanceType<typeof InputText>>()

/** Navigation actions, role-filtered exactly like the Sidebar (`AppSidebar.vue`/
 *  `AppSidebarItem.vue`'s own checks) — every reachable route becomes a "Go to X" entry
 *  (design doc §5.3). Quick-create actions are hand-registered, not derived. */
const actions = computed<PaletteAction[]>(() => {
  const role = auth.user?.role
  const navActions = flattenNavItems()
    .filter((item) => item.routeName && !item.comingSoon)
    .filter((item) => !item.roles || (role && item.roles.includes(role)))
    .map<PaletteAction>((item) => ({
      id: `nav:${item.routeName}`,
      label: t('commandPalette.goTo', { page: t(item.labelKey) }),
      icon: item.icon,
      keywords: t(item.labelKey),
      run: () => router.push({ name: item.routeName! }),
    }))

  const quickActions: PaletteAction[] = []
  if (role === 'admin' || role === 'receptionist') {
    quickActions.push({
      id: 'action:new-patient',
      label: t('commandPalette.newPatient'),
      icon: 'pi pi-user-plus',
      keywords: t('commandPalette.newPatient'),
      run: () => router.push({ name: 'patients', query: { new: '1' } }),
    })
  }
  quickActions.push({
    id: 'action:shortcuts',
    label: t('commandPalette.shortcuts'),
    icon: 'pi pi-bolt',
    keywords: t('commandPalette.shortcuts'),
    run: () => shortcutsHelp.show(),
  })

  return [...quickActions, ...navActions]
})

const recentActions = computed<PaletteAction[]>(() =>
  sidebarPreferences.recentItems.map((item) => ({
    id: `recent:${item.routeName}:${JSON.stringify(item.params)}`,
    label: item.isLiteral ? item.label : t(item.label),
    icon: item.icon,
    keywords: item.isLiteral ? item.label : t(item.label),
    run: () => router.push({ name: item.routeName, params: item.params }),
  })),
)

// A generous defensive ceiling, not a functional one — today's full flattened nav list is under
// 30 items, so every "Go to X" entry stays reachable without typing a query (a real bug caught by
// this component's own test suite: an earlier `.slice(0, 20)` silently dropped Users/Settings,
// the two lowest-priority-by-declaration-order items, from the unfiltered default view).
const MAX_RESULTS = 60

const filtered = computed<PaletteAction[]>(() => {
  const needle = query.value.trim().toLowerCase()
  const pool = needle ? actions.value : [...recentActions.value, ...actions.value]
  if (!needle) return pool.slice(0, MAX_RESULTS)
  return pool.filter((action) => action.keywords.toLowerCase().includes(needle)).slice(0, MAX_RESULTS)
})

watch(filtered, () => {
  activeIndex.value = 0
})

watch(
  () => commandPalette.open,
  async (isOpen) => {
    if (isOpen) {
      query.value = ''
      activeIndex.value = 0
      await nextTick()
      // `InputText`'s public instance type doesn't declare `$el` (options-API component) — same
      // cast-through-`ComponentPublicInstance` pattern as `PatientSearchSelect.vue`'s identical
      // autofocus need, not a plain `as any`.
      const instance = inputRef.value as ComponentPublicInstance | undefined
      ;(instance?.$el as HTMLElement | undefined)?.focus()
    }
  },
)

function runAction(action: PaletteAction) {
  action.run()
  commandPalette.hide()
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    activeIndex.value = Math.min(activeIndex.value + 1, filtered.value.length - 1)
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    activeIndex.value = Math.max(activeIndex.value - 1, 0)
  } else if (event.key === 'Enter') {
    event.preventDefault()
    const action = filtered.value[activeIndex.value]
    if (action) runAction(action)
  }
}
</script>

<template>
  <Dialog
    v-model:visible="commandPalette.open"
    :show-header="false"
    modal
    dismissable-mask
    position="top"
    :draggable="false"
    class="mt-[10vh] w-full max-w-xl"
    content-class="!p-0"
    :aria-label="t('commandPalette.title')"
  >
    <div role="dialog" aria-modal="true" :aria-label="t('commandPalette.title')" class="flex flex-col">
      <div class="flex items-center gap-2 border-b border-surface-200 px-4 py-3 dark:border-surface-700">
        <i class="pi pi-search text-surface-400" />
        <InputText
          ref="inputRef"
          v-model="query"
          class="w-full border-none !shadow-none !outline-none"
          :placeholder="t('commandPalette.placeholder')"
          autofocus
          @keydown="onKeydown"
        />
        <kbd
          class="rounded border border-surface-200 px-1.5 py-0.5 text-xs text-surface-400 dark:border-surface-700"
        >
          Esc
        </kbd>
      </div>

      <ul class="max-h-96 overflow-y-auto p-2" role="listbox">
        <li v-if="filtered.length === 0" class="px-3 py-6 text-center text-sm text-surface-400">
          {{ t('commandPalette.empty') }}
        </li>
        <li v-for="(action, index) in filtered" :key="action.id">
          <button
            type="button"
            role="option"
            :aria-selected="index === activeIndex"
            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-start text-sm transition-colors"
            :class="
              index === activeIndex
                ? 'bg-primary-50 text-primary dark:bg-primary-400/10 dark:text-primary-300'
                : 'text-surface-700 dark:text-surface-200'
            "
            @mouseenter="activeIndex = index"
            @click="runAction(action)"
          >
            <i :class="action.icon" class="text-base" />
            <span class="truncate">{{ action.label }}</span>
          </button>
        </li>
      </ul>
    </div>
  </Dialog>
</template>
