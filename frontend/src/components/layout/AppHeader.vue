<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Menu from 'primevue/menu'
import Popover from 'primevue/popover'
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

const notificationsPopover = ref()
function toggleNotifications(event: Event) {
  notificationsPopover.value?.toggle(event)
}

const userMenu = ref()
const userMenuItems = computed(() => [
  { label: t('nav.myAccount'), icon: 'pi pi-user', command: () => router.push({ name: 'account' }) },
  { label: t('nav.logout'), icon: 'pi pi-sign-out', command: onLogout },
])
function toggleUserMenu(event: Event) {
  userMenu.value?.toggle(event)
}
</script>

<template>
  <header
    class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-surface-200 bg-surface-0 px-4 py-3 shadow-sm dark:border-surface-700 dark:bg-surface-900 lg:px-6"
  >
    <div class="flex min-w-0 items-center gap-3">
      <Button
        class="lg:hidden"
        icon="pi pi-bars"
        text
        rounded
        :aria-label="t('nav.openMenu')"
        @click="ui.openMobileSidebar"
      />
      <span class="text-lg font-semibold text-surface-900 dark:text-surface-0 lg:hidden">
        {{ t('app.name') }}
      </span>

      <nav
        v-if="breadcrumbs.length > 0"
        class="hidden min-w-0 items-center gap-1.5 text-sm text-surface-500 dark:text-surface-400 lg:flex"
        :aria-label="t('breadcrumbs.label')"
      >
        <template v-for="(crumb, index) in breadcrumbs" :key="`${crumb.labelKey}-${index}`">
          <i
            v-if="index > 0"
            class="pi pi-angle-right rtl:rotate-180 text-xs text-surface-300 dark:text-surface-600"
          />
          <RouterLink
            v-if="crumb.routeName"
            :to="{ name: crumb.routeName }"
            class="truncate hover:text-surface-900 hover:underline dark:hover:text-surface-0"
          >
            {{ t(crumb.labelKey) }}
          </RouterLink>
          <span v-else class="truncate font-medium text-surface-900 dark:text-surface-0">
            {{ t(crumb.labelKey) }}
          </span>
        </template>
      </nav>
    </div>

    <div class="flex items-center gap-2">
      <button
        type="button"
        class="flex items-center gap-2 rounded-lg border border-surface-200 bg-surface-50 px-2.5 py-1.5 text-sm text-surface-400 transition-colors hover:border-surface-300 hover:text-surface-600 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-500 dark:hover:border-surface-600 dark:hover:text-surface-300 sm:px-3"
        :aria-label="t('commandPalette.title')"
        @click="commandPalette.show"
      >
        <i class="pi pi-search text-xs" />
        <span class="hidden sm:inline">{{ t('commandPalette.placeholder') }}</span>
        <kbd
          class="ms-4 hidden rounded border border-surface-200 px-1.5 py-0.5 text-xs dark:border-surface-700 sm:inline"
        >
          {{ t('commandPalette.shortcutHint') }}
        </kbd>
      </button>

      <Button
        icon="pi pi-bell"
        text
        rounded
        :aria-label="t('common.notifications')"
        @click="toggleNotifications"
      />
      <Popover ref="notificationsPopover">
        <div class="w-64 p-2 text-sm text-surface-500 dark:text-surface-400">
          {{ t('common.noNotifications') }}
        </div>
      </Popover>

      <Select
        :model-value="locale"
        :options="AVAILABLE_LOCALES"
        option-label="label"
        option-value="code"
        class="hidden w-36 sm:flex"
        @update:model-value="onLocaleChange"
      />
      <Button
        :icon="ui.isDark ? 'pi pi-sun' : 'pi pi-moon'"
        text
        rounded
        :aria-label="t('common.theme')"
        @click="ui.toggleTheme"
      />

      <Button text rounded class="gap-2 px-2" :aria-label="t('common.account')" @click="toggleUserMenu">
        <span
          class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-medium text-primary dark:bg-primary-400/10 dark:text-primary-300"
        >
          {{ initials }}
        </span>
        <span class="hidden text-start text-sm md:flex md:flex-col">
          <span class="font-medium text-surface-900 dark:text-surface-0">{{ auth.user?.name }}</span>
          <span class="text-xs text-surface-500 dark:text-surface-400">
            {{ auth.user ? t(`users.roles.${auth.user.role}`) : '' }}
          </span>
        </span>
      </Button>
      <Menu ref="userMenu" :model="userMenuItems" popup />
    </div>
  </header>
</template>
