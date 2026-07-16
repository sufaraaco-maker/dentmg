<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import AppSidebarItem from './AppSidebarItem.vue'
import { navigation } from '@/config/navigation'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'

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

const collapsed = computed(() => props.variant === 'desktop' && ui.sidebarCollapsed)

const filteredNavigation = computed(() =>
  navigation.filter((item) => !item.roles || (auth.user && item.roles.includes(auth.user.role))),
)

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
          v-for="item in filteredNavigation"
          :key="item.labelKey"
          :item="item"
          :collapsed="collapsed"
          @navigate="onNavigate"
        />
      </ul>
    </nav>
  </component>
</template>
