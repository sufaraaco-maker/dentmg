<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import type { NavItem } from '@/config/navigation'

const props = withDefaults(
  defineProps<{
    item: NavItem
    /** Desktop rail is collapsed to icon-only. Never true on the mobile drawer. */
    collapsed?: boolean
  }>(),
  { collapsed: false },
)

const emit = defineEmits<{ navigate: [] }>()

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const hasChildren = computed(() => !!props.item.children?.length)

const isActive = computed(() => {
  if (props.item.routeName && route.name === props.item.routeName) return true
  return !!props.item.children?.some((child) => child.routeName === route.name)
})

const expanded = ref(isActive.value && hasChildren.value)

watch(
  () => route.name,
  () => {
    if (hasChildren.value && props.item.children?.some((child) => child.routeName === route.name)) {
      expanded.value = true
    }
  },
)

const rowClasses = computed(() => [
  'flex w-full items-center gap-3 rounded-lg border-s-[3px] px-3 py-2 text-sm transition-colors',
  props.collapsed && 'justify-center px-0',
  props.item.comingSoon
    ? 'border-transparent text-surface-400 cursor-not-allowed dark:text-surface-600'
    : isActive.value
      ? 'border-primary bg-primary-50 font-medium text-primary dark:bg-primary-400/10 dark:text-primary-300'
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
    <RouterLink
      v-if="!hasChildren && item.routeName"
      v-tooltip.right="collapsed ? t(item.labelKey) : undefined"
      :to="{ name: item.routeName }"
      :class="rowClasses"
      @click="emit('navigate')"
    >
      <i :class="item.icon" class="text-base" />
      <span v-if="!collapsed" class="truncate">{{ t(item.labelKey) }}</span>
    </RouterLink>

    <button
      v-else-if="hasChildren"
      v-tooltip.right="collapsed ? t(item.labelKey) : undefined"
      type="button"
      :class="rowClasses"
      :aria-expanded="expanded"
      @click="onParentClick"
    >
      <i :class="item.icon" class="text-base" />
      <template v-if="!collapsed">
        <span class="flex-1 text-start truncate">{{ t(item.labelKey) }}</span>
        <i class="pi pi-chevron-down text-xs transition-transform" :class="expanded && 'rotate-180'" />
      </template>
    </button>

    <div
      v-else
      v-tooltip.right="collapsed ? t(item.labelKey) : undefined"
      :class="rowClasses"
      aria-disabled="true"
    >
      <i :class="item.icon" class="text-base" />
      <template v-if="!collapsed">
        <span class="flex-1 truncate text-start">{{ t(item.labelKey) }}</span>
        <span
          class="rounded-full bg-surface-100 px-2 py-0.5 text-xs text-surface-500 dark:bg-surface-800 dark:text-surface-400"
        >
          {{ t('nav.comingSoon') }}
        </span>
      </template>
    </div>

    <ul v-if="hasChildren && expanded && !collapsed" class="mt-1 flex flex-col gap-1 ps-9">
      <li v-for="child in item.children" :key="child.labelKey">
        <RouterLink
          v-if="child.routeName"
          :to="{ name: child.routeName }"
          active-class="bg-primary-50 font-medium text-primary dark:bg-primary-400/10 dark:text-primary-300"
          class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm text-surface-600 transition-colors hover:bg-surface-100 hover:text-surface-900 dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0"
          @click="emit('navigate')"
        >
          <i :class="child.icon" class="text-sm" />
          <span class="truncate">{{ t(child.labelKey) }}</span>
        </RouterLink>
      </li>
    </ul>
  </li>
</template>
