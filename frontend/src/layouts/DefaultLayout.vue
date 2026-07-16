<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useUiStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import { AVAILABLE_LOCALES, type SupportedLocale } from '@/locales'
import Select from 'primevue/select'
import Button from 'primevue/button'

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
</script>

<template>
  <div class="flex min-h-screen flex-col bg-surface-50 dark:bg-surface-900">
    <header
      class="flex items-center justify-between border-b border-surface-200 px-6 py-3 dark:border-surface-700"
    >
      <div class="flex items-center gap-6">
        <span class="text-lg font-semibold text-surface-900 dark:text-surface-0">
          {{ t('app.name') }}
        </span>

        <nav class="flex items-center gap-4">
          <RouterLink
            :to="{ name: 'dashboard' }"
            class="text-sm text-surface-600 hover:text-primary dark:text-surface-300"
            active-class="!text-primary font-medium"
          >
            {{ t('nav.dashboard') }}
          </RouterLink>
          <RouterLink
            :to="{ name: 'patients' }"
            class="text-sm text-surface-600 hover:text-primary dark:text-surface-300"
            active-class="!text-primary font-medium"
          >
            {{ t('nav.patients') }}
          </RouterLink>
          <RouterLink
            :to="{ name: 'appointments' }"
            class="text-sm text-surface-600 hover:text-primary dark:text-surface-300"
            active-class="!text-primary font-medium"
          >
            {{ t('nav.appointments') }}
          </RouterLink>
          <RouterLink
            :to="{ name: 'users' }"
            class="text-sm text-surface-600 hover:text-primary dark:text-surface-300"
            active-class="!text-primary font-medium"
          >
            {{ t('nav.users') }}
          </RouterLink>
        </nav>
      </div>

      <div class="flex items-center gap-3">
        <Select
          :model-value="locale"
          :options="AVAILABLE_LOCALES"
          option-label="label"
          option-value="code"
          class="w-36"
          @update:model-value="onLocaleChange"
        />
        <Button
          :icon="ui.isDark ? 'pi pi-sun' : 'pi pi-moon'"
          text
          rounded
          :aria-label="t('common.theme')"
          @click="ui.toggleTheme"
        />
        <Button
          icon="pi pi-sign-out"
          text
          rounded
          :aria-label="t('nav.logout')"
          @click="onLogout"
        />
      </div>
    </header>

    <main class="flex-1 p-6">
      <RouterView />
    </main>
  </div>
</template>
