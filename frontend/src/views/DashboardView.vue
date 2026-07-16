<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from 'primevue/card'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import { api } from '@/lib/api'

const { t } = useI18n()

interface DashboardSummary {
  total_patients: number
  today_appointments: number
  monthly_revenue: number
}

const summary = ref<DashboardSummary | null>(null)
const loading = ref(true)
const error = ref(false)

const statCards = [
  { key: 'total_patients' as const, icon: 'pi pi-users' },
  { key: 'today_appointments' as const, icon: 'pi pi-calendar' },
  { key: 'monthly_revenue' as const, icon: 'pi pi-wallet' },
]

onMounted(async () => {
  try {
    const { data } = await api.get<DashboardSummary>('/dashboard/summary')
    summary.value = data
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
      {{ t('dashboard.title') }}
    </h1>

    <Message v-if="error" severity="error">{{ t('dashboard.loadError') }}</Message>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <Card
        v-for="stat in statCards"
        :key="stat.key"
        class="transition-shadow duration-200 hover:shadow-md"
      >
        <template #content>
          <div class="flex items-center gap-3">
            <span
              class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-50 dark:bg-primary-400/10"
            >
              <i :class="[stat.icon, 'text-xl text-primary']" />
            </span>
            <div>
              <Skeleton v-if="loading" width="4rem" height="1.5rem" />
              <p v-else class="tabular-nums text-xl font-semibold text-surface-900 dark:text-surface-0">
                {{ summary?.[stat.key] ?? 0 }}
              </p>
              <p class="text-sm text-surface-500">{{ t(`dashboard.stats.${stat.key}`) }}</p>
            </div>
          </div>
        </template>
      </Card>
    </div>
  </div>
</template>
