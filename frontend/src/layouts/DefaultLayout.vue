<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Drawer from 'primevue/drawer'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'
import { useUiStore } from '@/stores/ui'
import { RTL_LOCALES, type SupportedLocale } from '@/locales'

const { t, locale } = useI18n()
const ui = useUiStore()

const isRtl = computed(() => RTL_LOCALES.includes(locale.value as SupportedLocale))
</script>

<template>
  <div class="flex min-h-screen bg-surface-50 dark:bg-surface-950">
    <AppSidebar class="hidden lg:flex" variant="desktop" />

    <Drawer
      v-model:visible="ui.mobileSidebarOpen"
      :position="isRtl ? 'right' : 'left'"
      :header="t('app.name')"
      class="w-72"
    >
      <AppSidebar variant="drawer" />
    </Drawer>

    <div class="flex min-h-screen min-w-0 flex-1 flex-col">
      <AppHeader />
      <main class="flex-1 p-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
