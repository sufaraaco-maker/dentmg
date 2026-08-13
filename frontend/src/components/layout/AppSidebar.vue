<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Menu, ChevronDown } from 'lucide-vue-next'
import Button from 'primevue/button'
import AppSidebarItem from './AppSidebarItem.vue'
import AppLogo from './AppLogo.vue'
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
      variant === 'desktop' && (collapsed ? 'w-[72px]' : 'w-80'),
    ]"
  >
    <div v-if="variant === 'desktop'" class="flex items-center justify-between gap-2 px-3 py-4">
      <div v-if="!collapsed" class="flex min-w-0 items-center gap-2">
        <AppLogo :size="24" />
        <span class="truncate text-lg font-semibold text-surface-900 dark:text-surface-0">
          {{ t('app.name') }}
        </span>
      </div>
      <Button
        text
        rounded
        :aria-label="t(collapsed ? 'nav.expand' : 'nav.collapse')"
        @click="ui.toggleSidebarCollapsed"
      >
        <template #icon="{ class: iconClass }">
          <Menu :size="20" :class="iconClass" />
        </template>
      </Button>
    </div>

    <nav class="flex-1 overflow-y-auto px-2.5 py-2">
      <ul class="flex flex-col gap-1.5">
        <AppSidebarItem
          v-for="item in pinnedItems"
          :key="item.labelKey"
          :item="item"
          :collapsed="collapsed"
          @navigate="onNavigate"
        />
      </ul>

      <template v-if="!collapsed">
        <div v-if="favoriteItems.length > 0" class="mt-6">
          <h2
            class="border-b border-surface-100 px-3 pb-2 pt-1 text-xs font-semibold uppercase tracking-wide text-surface-400 dark:border-surface-800 dark:text-surface-500"
          >
            {{ t('nav.favorites') }}
          </h2>
          <ul class="mt-2 flex flex-col gap-1.5">
            <AppSidebarItem
              v-for="item in favoriteItems"
              :key="`fav-${item.routeName}`"
              :item="item"
              :collapsed="false"
              @navigate="onNavigate"
            />
          </ul>
        </div>

        <div v-for="section in sections" :key="section.key" class="mt-6">
          <button
            type="button"
            class="flex w-full items-center justify-between gap-2 border-b border-surface-100 px-3 pb-2 pt-1 text-xs font-semibold uppercase tracking-wide text-surface-400 transition-colors dark:border-surface-800 dark:text-surface-500"
            :aria-expanded="!sidebarPreferences.isSectionCollapsed(section.key)"
            @click="sidebarPreferences.toggleSection(section.key)"
          >
            <span>{{ t(sectionLabelKey(section.key)) }}</span>
            <ChevronDown
              :size="14"
              class="shrink-0 transition-transform duration-200"
              :class="sidebarPreferences.isSectionCollapsed(section.key) && '-rotate-90 rtl:rotate-90'"
            />
          </button>
          <Transition
            enter-active-class="motion-safe:transition-[grid-template-rows] motion-safe:duration-200 motion-safe:ease-out"
            leave-active-class="motion-safe:transition-[grid-template-rows] motion-safe:duration-200 motion-safe:ease-out"
            enter-from-class="grid-rows-[0fr]"
            enter-to-class="grid-rows-[1fr]"
            leave-from-class="grid-rows-[1fr]"
            leave-to-class="grid-rows-[0fr]"
          >
            <div v-if="!sidebarPreferences.isSectionCollapsed(section.key)" class="grid">
              <ul class="mt-2 flex min-h-0 flex-col gap-1.5 overflow-hidden">
                <AppSidebarItem
                  v-for="item in section.items"
                  :key="item.labelKey"
                  :item="item"
                  :collapsed="false"
                  @navigate="onNavigate"
                />
              </ul>
            </div>
          </Transition>
        </div>
      </template>

      <!-- Collapsed rail: every non-pinned item still reachable, flat, icon-only — unchanged from
           the pre-redesign behavior (sections/favorites need width to mean anything). -->
      <ul v-else class="mt-2 flex flex-col gap-1.5">
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
