<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Menu from 'primevue/menu'
import Popover from 'primevue/popover'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import { AVAILABLE_LOCALES, type SupportedLocale } from '@/locales'

const { t, locale } = useI18n()
const ui = useUiStore()
const auth = useAuthStore()
const router = useRouter()

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
    <div class="flex items-center gap-3">
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
    </div>

    <div class="flex items-center gap-2">
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
