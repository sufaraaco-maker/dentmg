<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Card from 'primevue/card'

/**
 * Settings landing page (design doc §7) — a card grid linking to each admin-only settings screen,
 * mirroring `ReportsHomeView.vue`'s exact card-grid pattern (design doc §0's CareStack-inspired
 * decision). The whole `nav.settings` entry is already admin-gated, so no per-card role filter is
 * needed here, unlike Reports.
 */
interface SettingsCard {
  routeName: string
  titleKey: string
  descriptionKey: string
  icon: string
}

const SETTINGS_CARDS: SettingsCard[] = [
  {
    routeName: 'settings-practice',
    titleKey: 'settings.nav.practice',
    descriptionKey: 'settings.home.practiceDescription',
    icon: 'pi pi-building',
  },
  {
    routeName: 'settings-billing',
    titleKey: 'settings.nav.billing',
    descriptionKey: 'settings.home.billingDescription',
    icon: 'pi pi-wallet',
  },
]

const { t } = useI18n()
const router = useRouter()
</script>

<template>
  <div class="flex flex-col gap-4">
    <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">{{ t('settings.title') }}</h1>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <Card
        v-for="card in SETTINGS_CARDS"
        :key="card.routeName"
        class="cursor-pointer transition-shadow duration-200 hover:shadow-md"
        role="link"
        tabindex="0"
        :aria-label="t(card.titleKey)"
        @click="router.push({ name: card.routeName })"
        @keydown.enter="router.push({ name: card.routeName })"
      >
        <template #content>
          <div class="flex items-start gap-3">
            <span
              class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-400/10"
            >
              <i :class="[card.icon, 'text-xl text-primary']" />
            </span>
            <div>
              <p class="font-semibold text-surface-900 dark:text-surface-0">{{ t(card.titleKey) }}</p>
              <p class="text-sm text-surface-500">{{ t(card.descriptionKey) }}</p>
            </div>
          </div>
        </template>
      </Card>
    </div>
  </div>
</template>
