<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Card from 'primevue/card'
import { useAuthStore } from '@/stores/auth'

/**
 * Reports landing page — a card grid of every report the current role can see (design doc §7).
 * Financial reports (admin-only) are simply omitted for non-admins, mirroring how the sidebar nav
 * already hides them (`config/navigation.ts`) — this view applies the same rule again so a direct
 * URL visit to `/reports` shows a consistent list, not just the guarded sub-routes.
 */
interface ReportCard {
  routeName: string
  titleKey: string
  descriptionKey: string
  icon: string
  adminOnly?: boolean
}

const REPORT_CARDS: ReportCard[] = [
  {
    routeName: 'report-production',
    titleKey: 'reports.nav.production',
    descriptionKey: 'reports.home.productionDescription',
    icon: 'pi pi-chart-line',
    adminOnly: true,
  },
  {
    routeName: 'report-collections',
    titleKey: 'reports.nav.collections',
    descriptionKey: 'reports.home.collectionsDescription',
    icon: 'pi pi-wallet',
    adminOnly: true,
  },
  {
    routeName: 'report-ar-aging',
    titleKey: 'reports.nav.arAging',
    descriptionKey: 'reports.home.arAgingDescription',
    icon: 'pi pi-exclamation-circle',
    adminOnly: true,
  },
  {
    routeName: 'report-appointments',
    titleKey: 'reports.nav.appointments',
    descriptionKey: 'reports.home.appointmentsDescription',
    icon: 'pi pi-calendar',
  },
  {
    routeName: 'report-treatment-plan-acceptance',
    titleKey: 'reports.nav.treatmentPlanAcceptance',
    descriptionKey: 'reports.home.treatmentPlanAcceptanceDescription',
    icon: 'pi pi-clipboard',
  },
  {
    routeName: 'report-new-patients',
    titleKey: 'reports.nav.newPatients',
    descriptionKey: 'reports.home.newPatientsDescription',
    icon: 'pi pi-user-plus',
  },
]

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const visibleCards = computed(() => REPORT_CARDS.filter((card) => !card.adminOnly || auth.isAdmin))
</script>

<template>
  <div class="flex flex-col gap-4">
    <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">{{ t('reports.title') }}</h1>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <Card
        v-for="card in visibleCards"
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
