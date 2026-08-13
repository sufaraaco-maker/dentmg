<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Menu from 'primevue/menu'
import { ChevronRight, LogOut, Menu as MenuIcon, Moon, Search, Sun, User } from 'lucide-vue-next'
import NotificationBell from '@/components/notifications/NotificationBell.vue'
import AppLogo from '@/components/layout/AppLogo.vue'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import { useCommandPaletteStore } from '@/stores/commandPalette'
import { useBreadcrumbs } from '@/composables/useBreadcrumbs'
import { AVAILABLE_LOCALES, type SupportedLocale } from '@/locales'

const { t, locale } = useI18n()
const ui = useUiStore()
const auth = useAuthStore()
const router = useRouter()
const commandPalette = useCommandPaletteStore()
const breadcrumbs = useBreadcrumbs()

function onLocaleChange(value: SupportedLocale) {
  ui.changeLocale(value)
}

async function onLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}

const initials = computed(() => {
  const name = auth.user?.name ?? ''
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
})

/** `iconComponent` is a Lucide component rendered via the `#itemicon` slot below — PrimeVue's own
 *  `MenuItem.icon` field expects a CSS class string (font-icon convention), so it's left unset;
 *  `MenuItem`'s index signature allows this extra field without a cast (frontend-visual-redesign-
 *  design.md §4/§7 — icon migration extends to header/menu icons too, not just the sidebar). */
const userMenu = ref()
const userMenuItems = computed(() => [
  { label: t('nav.myAccount'), iconComponent: User, command: () => router.push({ name: 'account' }) },
  { label: t('nav.logout'), iconComponent: LogOut, command: onLogout },
])
function toggleUserMenu(event: Event) {
  userMenu.value?.toggle(event)
}
</script>

<template>
  <header
    class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-surface-200 bg-surface-0 px-4 py-3.5 shadow-sm dark:border-surface-700 dark:bg-surface-900 lg:px-6"
  >
    <div class="flex min-w-0 items-center gap-3">
      <Button class="lg:hidden" text rounded :aria-label="t('nav.openMenu')" @click="ui.openMobileSidebar">
        <template #icon="{ class: iconClass }">
          <MenuIcon :size="20" :class="iconClass" />
        </template>
      </Button>
      <span
        class="flex items-center gap-2 text-lg font-semibold text-surface-900 dark:text-surface-0 lg:hidden"
      >
        <AppLogo :size="22" />
        {{ t('app.name') }}
      </span>

      <nav
        v-if="breadcrumbs.length > 0"
        class="hidden min-w-0 flex-1 items-center gap-2 text-sm text-surface-500 dark:text-surface-400 lg:flex"
        :aria-label="t('breadcrumbs.label')"
      >
        <template v-for="(crumb, index) in breadcrumbs" :key="`${crumb.labelKey}-${index}`">
          <ChevronRight
            v-if="index > 0"
            :size="14"
            class="shrink-0 text-surface-300 rtl:rotate-180 dark:text-surface-600"
          />
          <RouterLink
            v-if="crumb.routeName"
            :to="{ name: crumb.routeName }"
            class="truncate rounded transition-colors hover:text-surface-900 dark:hover:text-surface-0"
          >
            {{ t(crumb.labelKey) }}
          </RouterLink>
          <span v-else class="truncate font-medium text-surface-900 dark:text-surface-0">
            {{ t(crumb.labelKey) }}
          </span>
        </template>
      </nav>
    </div>

    <div class="flex shrink-0 items-center gap-2">
      <button
        type="button"
        class="flex shrink-0 items-center gap-2 rounded-xl border border-surface-200 bg-surface-50 px-3 py-2 text-sm text-surface-400 shadow-sm transition-colors hover:border-surface-300 hover:text-surface-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-500 dark:hover:border-surface-600 dark:hover:text-surface-300"
        :aria-label="t('commandPalette.title')"
        @click="commandPalette.show"
      >
        <Search :size="16" class="shrink-0" />
        <span class="hidden whitespace-nowrap sm:inline">{{ t('commandPalette.placeholder') }}</span>
        <kbd
          class="ms-4 hidden whitespace-nowrap rounded-md border border-surface-200 bg-surface-0 px-1.5 py-0.5 text-xs shadow-sm dark:border-surface-700 dark:bg-surface-900 sm:inline"
        >
          {{ t('commandPalette.shortcutHint') }}
        </kbd>
      </button>

      <!-- Phase 5A: replaces the inert bell + "No notifications yet" popover that
           TECH_DEBT.md recorded as placeholder UI awaiting a real notification backend. -->
      <NotificationBell />

      <Select
        :model-value="locale"
        :options="AVAILABLE_LOCALES"
        option-label="label"
        option-value="code"
        class="hidden w-36 sm:flex"
        @update:model-value="onLocaleChange"
      />
      <Button text rounded :aria-label="t('common.theme')" @click="ui.toggleTheme">
        <template #icon="{ class: iconClass }">
          <Sun v-if="ui.isDark" :size="18" :class="iconClass" />
          <Moon v-else :size="18" :class="iconClass" />
        </template>
      </Button>

      <Button text rounded class="gap-2 px-2" :aria-label="t('common.account')" @click="toggleUserMenu">
        <img
          v-if="auth.user?.avatar_url"
          :src="auth.user.avatar_url"
          :alt="auth.user.name"
          class="h-10 w-10 shrink-0 rounded-full object-cover"
        />
        <span
          v-else
          class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-semibold text-primary dark:bg-primary-400/10 dark:text-primary-300"
        >
          {{ initials }}
        </span>
        <span class="hidden text-start text-sm md:flex md:flex-col">
          <span class="whitespace-nowrap font-medium text-surface-900 dark:text-surface-0">{{
            auth.user?.name
          }}</span>
          <span class="whitespace-nowrap text-xs text-surface-500 dark:text-surface-400">
            {{ auth.user ? t(`users.roles.${auth.user.role}`) : '' }}
          </span>
        </span>
      </Button>
      <Menu ref="userMenu" :model="userMenuItems" popup>
        <template #itemicon="{ item }">
          <component
            :is="(item as { iconComponent?: unknown }).iconComponent"
            :size="16"
            class="p-menuitem-icon"
          />
        </template>
      </Menu>
    </div>
  </header>
</template>
