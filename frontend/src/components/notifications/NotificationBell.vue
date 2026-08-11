<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Popover from 'primevue/popover'
import Drawer from 'primevue/drawer'
import { Bell } from 'lucide-vue-next'
import NotificationCenter from './NotificationCenter.vue'
import { useNotificationsStore } from '@/stores/notifications'

/**
 * Notification System (Phase 5A) design doc §10 — the header trigger, replacing the inert bell +
 * "No notifications yet" popover that `TECH_DEBT.md` records as placeholder UI awaiting a backend.
 *
 * Mobile-first (a standing project directive, not this module's invention): a `Popover` anchored to
 * the bell at `md:` and above, a full-height `Drawer` below it — the same swap-the-layout (rather
 * than shrink-it) treatment Phase 4's `PermissionsView` uses for its matrix.
 *
 * Polling, not websockets: this project has no broadcast layer at all (`BROADCAST_CONNECTION=log`,
 * no `config/broadcasting.php`, no Echo/Pusher client — verified at design time), so a 60s poll of
 * the dedicated count endpoint is the honest mechanism. It is gated on `document.visibilityState`
 * so a backgrounded tab costs nothing, and the store contract is deliberately shaped so a future
 * broadcast push can feed the same state with no change to this component (design doc §3.4).
 */
const POLL_INTERVAL_MS = 60_000
const MOBILE_BREAKPOINT_PX = 768

const { t } = useI18n()
const store = useNotificationsStore()

const popover = ref()
const drawerOpen = ref(false)
const isMobile = ref(false)

let pollTimer: ReturnType<typeof setInterval> | undefined

const badge = computed(() => (store.unreadCount > 9 ? '9+' : String(store.unreadCount)))

function syncViewport(): void {
  isMobile.value = window.innerWidth < MOBILE_BREAKPOINT_PX
}

function poll(): void {
  if (document.visibilityState === 'visible') void store.fetchUnreadCount()
}

function onVisibilityChange(): void {
  // Catch up immediately on refocus rather than waiting out the remainder of the interval.
  if (document.visibilityState === 'visible') void store.fetchUnreadCount()
}

function toggle(event: Event): void {
  if (isMobile.value) {
    drawerOpen.value = !drawerOpen.value
    return
  }
  popover.value?.toggle(event)
}

function close(): void {
  drawerOpen.value = false
  popover.value?.hide()
}

onMounted(() => {
  syncViewport()
  window.addEventListener('resize', syncViewport)
  document.addEventListener('visibilitychange', onVisibilityChange)

  void store.fetchUnreadCount()
  pollTimer = setInterval(poll, POLL_INTERVAL_MS)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', syncViewport)
  document.removeEventListener('visibilitychange', onVisibilityChange)
  if (pollTimer) clearInterval(pollTimer)
})
</script>

<template>
  <div class="relative">
    <Button
      text
      rounded
      :aria-label="t('common.notifications')"
      data-testid="notifications-bell"
      @click="toggle"
    >
      <template #icon="{ class: iconClass }">
        <Bell :size="18" :class="iconClass" />
      </template>
    </Button>

    <!-- aria-live so a screen reader announces the count changing without stealing focus. -->
    <span
      v-if="store.unreadCount > 0"
      class="pointer-events-none absolute -top-0.5 end-0 min-w-[1.15rem] rounded-full bg-red-500 px-1 text-center text-[0.65rem] font-bold leading-[1.15rem] text-white"
      aria-live="polite"
      data-testid="notifications-badge"
    >
      {{ badge }}
    </span>

    <Popover v-if="!isMobile" ref="popover">
      <NotificationCenter compact @navigated="close" />
    </Popover>

    <Drawer
      v-else
      v-model:visible="drawerOpen"
      position="bottom"
      style="height: 85vh"
      :header="t('notifications.title')"
    >
      <NotificationCenter @navigated="close" />
    </Drawer>
  </div>
</template>
