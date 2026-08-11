<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import Button from 'primevue/button'
import ProgressSpinner from 'primevue/progressspinner'
import { BellOff, CheckCheck } from 'lucide-vue-next'
import NotificationItem from './NotificationItem.vue'
import { useNotificationsStore } from '@/stores/notifications'
import { categoryLabelKey } from '@/config/notificationTypes'
import { NOTIFICATION_CATEGORIES, type AppNotification } from '@/types/notification'

/**
 * Notification System (Phase 5A) design doc §10 — the panel itself.
 *
 * Embeddable rather than layout-bound: the header popover (desktop), the mobile drawer, and the
 * full `/notifications` page all mount this same component, so the three surfaces can never drift
 * apart in behaviour. Same single-component principle `ActivityTimeline.vue` follows for Timeline.
 */
const props = withDefaults(defineProps<{ compact?: boolean }>(), { compact: false })

const emit = defineEmits<{ (e: 'navigated'): void }>()

const { t } = useI18n()
const router = useRouter()
const store = useNotificationsStore()

onMounted(() => {
  if (store.items.length === 0) void store.fetch()
})

const isEmpty = computed(() => !store.loading && store.items.length === 0)

/**
 * Open → mark read → navigate (design doc §10.2). Marking is optimistic in the store, so the row
 * updates before the route change rather than flashing unread on the way out.
 */
async function onSelect(notification: AppNotification): Promise<void> {
  void store.markAsRead(notification.id)
  emit('navigated')

  await router.push({
    name: notification.data.route.name,
    params: notification.data.route.params,
    query: notification.data.route.query,
  })
}
</script>

<template>
  <div class="flex flex-col" :class="props.compact ? 'max-h-[70vh] w-[22rem] max-w-[90vw]' : 'w-full'">
    <div
      class="flex items-center justify-between gap-2 border-b border-surface-200 px-4 py-3 dark:border-surface-700"
    >
      <h2 class="text-sm font-semibold text-surface-900 dark:text-surface-0">
        {{ t('notifications.title') }}
      </h2>
      <Button
        v-if="store.unreadCount > 0"
        text
        size="small"
        :label="t('notifications.markAllRead')"
        data-testid="notifications-mark-all"
        @click="store.markAllAsRead()"
      >
        <template #icon="{ class: iconClass }">
          <CheckCheck :size="14" :class="iconClass" />
        </template>
      </Button>
    </div>

    <!-- Status + category filters. Chips rather than a Select: they stay one tap on touch, and
         they keep the active filter visible instead of hidden behind a closed dropdown. -->
    <div class="flex flex-wrap gap-1.5 border-b border-surface-200 px-3 py-2 dark:border-surface-700">
      <button
        v-for="option in ['all', 'unread'] as const"
        :key="option"
        type="button"
        class="rounded-full px-3 py-1 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400"
        :class="
          store.status === option
            ? 'bg-primary-500 text-white'
            : 'bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:hover:bg-surface-700'
        "
        :aria-pressed="store.status === option"
        :data-testid="`notifications-status-${option}`"
        @click="store.setStatus(option)"
      >
        {{ t(`notifications.status.${option}`) }}
      </button>

      <span class="mx-1 w-px bg-surface-200 dark:bg-surface-700" aria-hidden="true" />

      <button
        v-for="cat in NOTIFICATION_CATEGORIES"
        :key="cat"
        type="button"
        class="rounded-full px-3 py-1 text-xs font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400"
        :class="
          store.category === cat
            ? 'bg-primary-500 text-white'
            : 'bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:hover:bg-surface-700'
        "
        :aria-pressed="store.category === cat"
        :data-testid="`notifications-category-${cat}`"
        @click="store.setCategory(store.category === cat ? null : cat)"
      >
        {{ t(categoryLabelKey(cat)) }}
      </button>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto">
      <p
        v-if="store.error"
        class="px-4 py-6 text-center text-sm text-red-600 dark:text-red-400"
        data-testid="notifications-error"
      >
        {{ t(store.error) }}
      </p>

      <div v-else-if="store.loading && store.items.length === 0" class="flex justify-center py-8">
        <ProgressSpinner style="width: 2rem; height: 2rem" :aria-label="t('notifications.loading')" />
      </div>

      <div
        v-else-if="isEmpty"
        class="flex flex-col items-center gap-2 px-4 py-10 text-center"
        data-testid="notifications-empty"
      >
        <BellOff :size="24" class="text-surface-300 dark:text-surface-600" />
        <p class="text-sm text-surface-500 dark:text-surface-400">
          {{ t('common.noNotifications') }}
        </p>
      </div>

      <template v-else>
        <NotificationItem
          v-for="notification in store.items"
          :key="notification.id"
          :notification="notification"
          @select="onSelect"
        />

        <div v-if="store.hasMore" class="p-2">
          <Button
            text
            fluid
            size="small"
            :loading="store.loading"
            :label="t('notifications.loadMore')"
            data-testid="notifications-load-more"
            @click="store.loadMore()"
          />
        </div>
      </template>
    </div>

    <!-- Only in the panel: the full page already *is* the destination. -->
    <RouterLink
      v-if="props.compact"
      :to="{ name: 'notifications' }"
      class="border-t border-surface-200 px-4 py-2.5 text-center text-sm font-medium text-primary transition-colors hover:bg-surface-100 dark:border-surface-700 dark:hover:bg-surface-800"
      data-testid="notifications-view-all"
      @click="emit('navigated')"
    >
      {{ t('notifications.viewAll') }}
    </RouterLink>
  </div>
</template>
