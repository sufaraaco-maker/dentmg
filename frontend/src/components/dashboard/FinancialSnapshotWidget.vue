<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import Skeleton from 'primevue/skeleton'
import type { DashboardFinancialSummary } from '@/types/dashboard'

/**
 * Admin-only Dashboard 2.0 widget (design doc §3.2) — one card, not three, to avoid crowding the
 * grid with admin-only tiles most roles never see. Data is passed in via prop, not self-fetched:
 * it's already bundled in one `GET /dashboard/financial-summary` call the parent view makes, so a
 * per-widget fetch here would duplicate that request.
 */
defineProps<{
  summary: DashboardFinancialSummary | null
  loading: boolean
}>()

const { t } = useI18n()

/** `null` renders no badge at all — never a fabricated "0%" for a period with no prior data. */
function trendSeverity(changePct: number | null): 'success' | 'danger' | undefined {
  if (changePct === null) return undefined
  return changePct >= 0 ? 'success' : 'danger'
}

function trendLabel(changePct: number | null): string {
  if (changePct === null) return ''
  const sign = changePct >= 0 ? '+' : ''
  return `${sign}${changePct.toFixed(1)}%`
}
</script>

<template>
  <Card>
    <template #title>{{ t('dashboard.financial.title') }}</template>
    <template #content>
      <div v-if="loading && !summary" class="flex flex-col gap-4">
        <Skeleton height="2.5rem" />
        <Skeleton height="1.5rem" />
        <Skeleton height="4rem" />
      </div>
      <div v-else-if="summary" class="flex flex-col gap-4">
        <div class="flex items-baseline gap-2">
          <div>
            <p class="text-sm text-surface-500">{{ t('dashboard.stats.monthly_revenue') }}</p>
            <p dir="ltr" class="text-2xl font-semibold tabular-nums text-surface-900 dark:text-surface-0">
              {{ summary.monthly_revenue }}
            </p>
          </div>
          <Tag
            v-if="summary.collections_trend.change_pct !== null"
            :value="trendLabel(summary.collections_trend.change_pct)"
            :severity="trendSeverity(summary.collections_trend.change_pct)"
          />
          <span class="text-xs text-surface-500">{{ t('dashboard.financial.vsLastMonth') }}</span>
        </div>

        <div class="flex items-baseline gap-2 border-t border-surface-200 pt-3 dark:border-surface-700">
          <div>
            <p class="text-sm text-surface-500">{{ t('dashboard.financial.production') }}</p>
            <p dir="ltr" class="text-lg font-medium tabular-nums text-surface-900 dark:text-surface-0">
              {{ summary.production_trend.current }}
            </p>
          </div>
          <Tag
            v-if="summary.production_trend.change_pct !== null"
            :value="trendLabel(summary.production_trend.change_pct)"
            :severity="trendSeverity(summary.production_trend.change_pct)"
          />
        </div>

        <div class="border-t border-surface-200 pt-3 dark:border-surface-700">
          <div class="mb-2 flex items-baseline justify-between">
            <p class="text-sm text-surface-500">{{ t('reports.nav.arAging') }}</p>
            <p dir="ltr" class="font-medium tabular-nums text-surface-900 dark:text-surface-0">
              {{ summary.ar_aging.total }}
            </p>
          </div>
          <div class="grid grid-cols-5 gap-2 text-center text-xs">
            <div v-for="bucket in ['current', '1_30', '31_60', '61_90', '90_plus'] as const" :key="bucket">
              <p class="text-surface-500">{{ t(`reports.arAging.buckets.${bucket}`) }}</p>
              <p dir="ltr" class="tabular-nums font-medium text-surface-900 dark:text-surface-0">
                {{ summary.ar_aging.buckets[bucket] }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </template>
  </Card>
</template>
