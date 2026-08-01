<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ChevronDown, Star } from 'lucide-vue-next'
import type { NavItem } from '@/config/navigation'
import { useAuthStore } from '@/stores/auth'
import { useSidebarPreferencesStore } from '@/stores/sidebarPreferences'

const props = withDefaults(
  defineProps<{
    item: NavItem
    /** Desktop rail is collapsed to icon-only. Never true on the mobile drawer. */
    collapsed?: boolean
    /** Nesting level, 0 = top-level. Recurses via this same component (Vue SFCs can reference
     *  themselves by filename), generalizing what was previously a hard one-level limit
     *  (frontend-ux-redesign design doc §5.1, resolves the matching TECH_DEBT.md entry). */
    depth?: number
  }>(),
  { collapsed: false, depth: 0 },
)

const emit = defineEmits<{ navigate: [] }>()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const sidebarPreferences = useSidebarPreferencesStore()

// Same role filter `AppSidebar.vue` already applies to its top-level items — children need the
// exact same check, otherwise a `roles`-restricted child (e.g. an admin-only report) still
// renders its link for every role, only to bounce to /forbidden on click (real bug found via
// Reports' own E2E suite, which was the first to assert nav-link visibility rather than just the
// destination route's guard).
const visibleChildren = computed(
  () =>
    props.item.children?.filter(
      (child) => !child.roles || (auth.user && child.roles.includes(auth.user.role)),
    ) ?? [],
)

const hasChildren = computed(() => visibleChildren.value.length > 0)

const isActive = computed(() => {
  if (props.item.routeName && route.name === props.item.routeName) return true
  return visibleChildren.value.some((child) => child.routeName === route.name)
})

const expanded = ref(isActive.value && hasChildren.value)

watch(
  () => route.name,
  () => {
    if (hasChildren.value && visibleChildren.value.some((child) => child.routeName === route.name)) {
      expanded.value = true
    }
  },
)

const canFavorite = computed(() => !props.collapsed && !!props.item.routeName && !props.item.comingSoon)
const isFavorite = computed(
  () => !!props.item.routeName && sidebarPreferences.isFavorite(props.item.routeName),
)

function toggleFavorite(event: Event) {
  event.stopPropagation()
  event.preventDefault()
  if (props.item.routeName) sidebarPreferences.toggleFavorite(props.item.routeName)
}

// Premium active state (frontend-visual-redesign-design.md §5): a filled rounded highlight
// spanning the full row (not just a border-tint), a thicker inline-start accent bar, semibold
// text, and a resting shadow — evolving design-system.md §6's original 3px accent bar rather than
// replacing the underlying mechanism.
const rowClasses = computed(() => [
  'group/navitem flex w-full items-center gap-3 rounded-xl border-s-4 px-3 py-2.5 text-sm transition-colors duration-150',
  props.collapsed && 'justify-center px-0',
  props.item.comingSoon
    ? 'border-transparent text-surface-400 cursor-not-allowed dark:text-surface-600'
    : isActive.value
      ? 'border-primary bg-primary-50 font-semibold text-primary shadow-sm dark:bg-primary-400/10 dark:text-primary-300'
      : 'border-transparent text-surface-600 hover:bg-surface-100 hover:text-surface-900 dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0',
])

function onParentClick() {
  if (props.collapsed) {
    // No room to show children inline on the icon-only rail: go straight to the group's own screen.
    if (props.item.routeName) {
      router.push({ name: props.item.routeName })
      emit('navigate')
    }
    return
  }
  expanded.value = !expanded.value
}
</script>

<template>
  <li>
    <!-- A `<button>` (favorite star) can never nest inside the `<RouterLink>` (`<a>`) below —
         interactive-inside-interactive is invalid HTML and browsers/screen readers handle it
         unpredictably — so the star is a flex sibling within this row `<div>` instead, not a
         RouterLink child. -->
    <div
      v-if="!hasChildren && item.routeName"
      :class="[rowClasses, depth > 0 && 'py-2 text-[0.8125rem]']"
      :style="depth > 0 ? { paddingInlineStart: `${depth * 1.5 + 0.75}rem` } : undefined"
    >
      <RouterLink
        v-tooltip.right="collapsed ? t(item.labelKey) : undefined"
        :to="{ name: item.routeName }"
        :aria-current="isActive ? 'page' : undefined"
        class="flex min-w-0 items-center gap-3"
        :class="!collapsed && 'flex-1'"
        @click="emit('navigate')"
      >
        <component :is="item.icon" :size="20" class="shrink-0" />
        <span v-if="!collapsed" class="flex-1 truncate text-start">{{ t(item.labelKey) }}</span>
      </RouterLink>
      <button
        v-if="canFavorite"
        type="button"
        class="shrink-0 rounded p-1 text-surface-300 opacity-0 transition-opacity hover:text-yellow-500 focus-visible:opacity-100 group-hover/navitem:opacity-100 dark:text-surface-600"
        :class="isFavorite && '!opacity-100 text-yellow-500'"
        :aria-label="t(isFavorite ? 'nav.unfavorite' : 'nav.favorite')"
        :aria-pressed="isFavorite"
        @click="toggleFavorite"
      >
        <Star :size="14" :fill="isFavorite ? 'currentColor' : 'none'" />
      </button>
    </div>

    <button
      v-else-if="hasChildren"
      v-tooltip.right="collapsed ? t(item.labelKey) : undefined"
      type="button"
      :class="rowClasses"
      :aria-expanded="expanded"
      @click="onParentClick"
    >
      <component :is="item.icon" :size="20" class="shrink-0" />
      <template v-if="!collapsed">
        <span class="flex-1 text-start truncate">{{ t(item.labelKey) }}</span>
        <ChevronDown
          :size="16"
          class="shrink-0 transition-transform duration-200"
          :class="expanded && 'rotate-180'"
        />
      </template>
    </button>

    <div
      v-else
      v-tooltip.right="collapsed ? t(item.labelKey) : undefined"
      :class="rowClasses"
      aria-disabled="true"
    >
      <component :is="item.icon" :size="20" class="shrink-0" />
      <template v-if="!collapsed">
        <span class="flex-1 truncate text-start">{{ t(item.labelKey) }}</span>
        <span
          class="rounded-full bg-surface-100 px-2 py-0.5 text-xs text-surface-500 dark:bg-surface-800 dark:text-surface-400"
        >
          {{ t('nav.comingSoon') }}
        </span>
      </template>
    </div>

    <Transition
      enter-active-class="motion-safe:transition-[grid-template-rows] motion-safe:duration-200 motion-safe:ease-out"
      leave-active-class="motion-safe:transition-[grid-template-rows] motion-safe:duration-200 motion-safe:ease-out"
      enter-from-class="grid-rows-[0fr]"
      enter-to-class="grid-rows-[1fr]"
      leave-from-class="grid-rows-[1fr]"
      leave-to-class="grid-rows-[0fr]"
    >
      <div v-if="hasChildren && expanded && !collapsed" class="grid">
        <ul class="mt-1 flex min-h-0 flex-col gap-1.5 overflow-hidden">
          <AppSidebarItem
            v-for="child in visibleChildren"
            :key="child.labelKey"
            :item="child"
            :collapsed="false"
            :depth="depth + 1"
            @navigate="emit('navigate')"
          />
        </ul>
      </div>
    </Transition>
  </li>
</template>
