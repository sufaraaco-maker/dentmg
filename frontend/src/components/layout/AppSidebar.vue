<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import AppSidebarItem from './AppSidebarItem.vue'
import { NAV_SECTIONS, navigation, flattenNavItems, type NavItem, type NavSection } from '@/config/navigation'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useSidebarPreferencesStore } from '@/stores/sidebarPreferences'

const props = withDefaults(
  defineProps<{
    /** 'desktop': permanently docked, collapsible icon rail. 'drawer': hosted inside the mobile PrimeVue Drawer. */
    variant?: 'desktop' | 'drawer'
  }>(),
  { variant: 'desktop' },
)

const { t } = useI18n()
const auth = useAuthStore()
const ui = useUiStore()
const sidebarPreferences = useSidebarPreferencesStore()

const collapsed = computed(() => props.variant === 'desktop' && ui.sidebarCollapsed)

function visibleToCurrentRole(item: NavItem): boolean {
  return !item.roles || (!!auth.user && item.roles.includes(auth.user.role))
}

const filteredNavigation = computed(() => navigation.filter(visibleToCurrentRole))

/** Pinned, always-flat items (Dashboard/Patients — no `section`) — render above every group,
 *  never collapsible (design doc §5.1). */
const pinnedItems = computed(() => filteredNavigation.value.filter((item) => !item.section))

/** One entry per non-empty `NavSection`, in `NAV_SECTIONS`' declared order. */
const sections = computed(() =>
  NAV_SECTIONS.map((key) => ({
    key,
    items: filteredNavigation.value.filter((item) => item.section === key),
  })).filter((section) => section.items.length > 0),
)

/** Favorited items resolved back to real `NavItem`s (role- and comingSoon-filtered) — a stale
 *  favorite pointing at a route the current role can no longer see, or that got removed from the
 *  config, simply stops appearing rather than erroring (design doc §5.1). */
const favoriteItems = computed(() => {
  const lookup = new Map(flattenNavItems().map((item) => [item.routeName, item]))
  return sidebarPreferences.favorites
    .map((routeName) => lookup.get(routeName))
    .filter((item): item is NavItem => !!item && !item.comingSoon && visibleToCurrentRole(item))
})

function sectionLabelKey(section: NavSection): string {
  return `nav.sections.${section}`
}

function onNavigate() {
  if (props.variant === 'drawer') {
    ui.closeMobileSidebar()
  }
}
</script>

<template>
  <component
    :is="variant === 'desktop' ? 'aside' : 'div'"
    class="flex h-full flex-col bg-surface-0 dark:bg-surface-900"
    :class="[
      variant === 'desktop' &&
        'sticky top-0 h-screen shrink-0 border-e border-surface-200 transition-[width] duration-200 dark:border-surface-700',
      variant === 'desktop' && (collapsed ? 'w-[72px]' : 'w-72'),
    ]"
  >
    <div v-if="variant === 'desktop'" class="flex items-center justify-between gap-2 px-3 py-4">
      <span v-if="!collapsed" class="truncate text-lg font-semibold text-surface-900 dark:text-surface-0">
        {{ t('app.name') }}
      </span>
      <Button
        icon="pi pi-bars"
        text
        rounded
        :aria-label="t(collapsed ? 'nav.expand' : 'nav.collapse')"
        @click="ui.toggleSidebarCollapsed"
      />
    </div>

    <nav class="flex-1 overflow-y-auto px-2 py-2">
      <ul class="flex flex-col gap-1">
        <AppSidebarItem
          v-for="item in pinnedItems"
          :key="item.labelKey"
          :item="item"
          :collapsed="collapsed"
          @navigate="onNavigate"
        />
      </ul>

      <template v-if="!collapsed">
        <div v-if="favoriteItems.length > 0" class="mt-4">
          <h2 class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-surface-400 dark:text-surface-500">
            {{ t('nav.favorites') }}
          </h2>
          <ul class="flex flex-col gap-1">
            <AppSidebarItem
              v-for="item in favoriteItems"
              :key="`fav-${item.routeName}`"
              :item="item"
              :collapsed="false"
              @navigate="onNavigate"
            />
          </ul>
        </div>

        <div v-if="sidebarPreferences.recentItems.length > 0" class="mt-4">
          <h2 class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-surface-400 dark:text-surface-500">
            {{ t('nav.recent') }}
          </h2>
          <ul class="flex flex-col gap-1">
            <li v-for="entry in sidebarPreferences.recentItems" :key="`recent-${entry.routeName}-${JSON.stringify(entry.params)}`">
              <RouterLink
                :to="{ name: entry.routeName, params: entry.params }"
                class="flex items-center gap-3 rounded-lg border-s-[3px] border-transparent px-3 py-2 text-sm text-surface-600 transition-colors hover:bg-surface-100 hover:text-surface-900 dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0"
                @click="onNavigate"
              >
                <i :class="entry.icon" class="text-base" />
                <span class="truncate">{{ entry.isLiteral ? entry.label : t(entry.label) }}</span>
              </RouterLink>
            </li>
          </ul>
        </div>

        <div v-for="section in sections" :key="section.key" class="mt-4">
          <button
            type="button"
            class="flex w-full items-center justify-between gap-2 px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-surface-400 transition-colors hover:text-surface-600 dark:text-surface-500 dark:hover:text-surface-300"
            :aria-expanded="!sidebarPreferences.isSectionCollapsed(section.key)"
            @click="sidebarPreferences.toggleSection(section.key)"
          >
            <span>{{ t(sectionLabelKey(section.key)) }}</span>
            <i
              class="pi pi-chevron-down text-[0.65rem] transition-transform"
              :class="sidebarPreferences.isSectionCollapsed(section.key) && '-rotate-90 rtl:rotate-90'"
            />
          </button>
          <ul v-if="!sidebarPreferences.isSectionCollapsed(section.key)" class="flex flex-col gap-1">
            <AppSidebarItem
              v-for="item in section.items"
              :key="item.labelKey"
              :item="item"
              :collapsed="false"
              @navigate="onNavigate"
            />
          </ul>
        </div>
      </template>

      <!-- Collapsed rail: every non-pinned item still reachable, flat, icon-only — unchanged from
           the pre-redesign behavior (sections/favorites/recents need width to mean anything). -->
      <ul v-else class="mt-2 flex flex-col gap-1">
        <AppSidebarItem
          v-for="item in sections.flatMap((section) => section.items)"
          :key="item.labelKey"
          :item="item"
          :collapsed="true"
          @navigate="onNavigate"
        />
      </ul>
    </nav>
  </component>
</template>
