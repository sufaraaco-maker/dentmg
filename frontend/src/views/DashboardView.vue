<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from 'primevue/card'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import Button from 'primevue/button'
import TodayScheduleWidget from '@/components/appointments/TodayScheduleWidget.vue'
import UpcomingAppointmentsWidget from '@/components/appointments/UpcomingAppointmentsWidget.vue'
import AppointmentDialog from '@/components/appointments/AppointmentDialog.vue'
import LowStockWidget from '@/components/inventory/LowStockWidget.vue'
import DueLabCasesWidget from '@/components/laboratory/DueLabCasesWidget.vue'
import FinancialSnapshotWidget from '@/components/dashboard/FinancialSnapshotWidget.vue'
import UnscheduledTreatmentWidget from '@/components/dashboard/UnscheduledTreatmentWidget.vue'
import AiQuestionBox from '@/components/aiAssistant/AiQuestionBox.vue'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'
import { askDashboardInsight } from '@/services/aiAssistant/aiAssistantApi'

const { t } = useI18n()
const auth = useAuthStore()
const dashboardStore = useDashboardStore()

// A dentist's dashboard shows their own schedule read-mostly; front desk/admin get the
// operational "all appointments" framing with Waiting/Late highlighting (design doc §8).
const scope = computed(() => (auth.isDentist ? 'own' : 'all'))

const newAppointmentDialogVisible = ref(false)

/**
 * Dashboard 2.0 (design doc §3.1): only 2 universal stat cards now, not 3 — `monthly_revenue`
 * moved to the admin-only `FinancialSnapshotWidget` below (§1.1's security fix means it's no
 * longer part of the open-to-all `/dashboard/summary` payload at all, so it can't be a plain stat
 * card everyone sees). Distinct tint per card, per the restyle brief.
 */
const statCards = [
  { key: 'total_patients' as const, icon: 'pi pi-users', tint: 'primary' as const },
  { key: 'today_appointments' as const, icon: 'pi pi-calendar', tint: 'blue' as const },
]

const TINT_CLASSES: Record<'primary' | 'blue', string> = {
  primary: 'bg-primary-50 text-primary dark:bg-primary-400/10',
  blue: 'bg-blue-50 text-blue-600 dark:bg-blue-400/10 dark:text-blue-400',
}

onMounted(() => {
  dashboardStore.fetchSummary()
  // Skip the network call entirely for a role that would get a 403 — not just hide the result.
  if (auth.canViewFinancials) dashboardStore.fetchFinancialSummary()
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('dashboard.title') }}
      </h1>
      <Button
        :label="t('dashboard.widgets.newAppointment')"
        icon="pi pi-plus"
        @click="newAppointmentDialogVisible = true"
      />
    </div>

    <Message v-if="dashboardStore.error" severity="error">{{ t(dashboardStore.error) }}</Message>
    <Message v-if="dashboardStore.financialError" severity="error">{{
      t(dashboardStore.financialError)
    }}</Message>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
      <Card v-for="stat in statCards" :key="stat.key" class="transition-shadow duration-200 hover:shadow-md">
        <template #content>
          <div class="flex items-center gap-3">
            <span
              class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full"
              :class="TINT_CLASSES[stat.tint]"
            >
              <i :class="[stat.icon, 'text-xl']" />
            </span>
            <div>
              <Skeleton
                v-if="dashboardStore.loading && !dashboardStore.summary"
                width="4rem"
                height="1.75rem"
              />
              <p
                v-else
                dir="ltr"
                class="text-start tabular-nums text-2xl font-semibold text-surface-900 dark:text-surface-0"
              >
                {{ dashboardStore.summary?.[stat.key] ?? 0 }}
              </p>
              <p class="text-sm text-surface-500">{{ t(`dashboard.stats.${stat.key}`) }}</p>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <FinancialSnapshotWidget
        v-if="auth.canViewFinancials"
        :summary="dashboardStore.financialSummary"
        :loading="dashboardStore.financialLoading"
      />
      <UnscheduledTreatmentWidget
        :data="dashboardStore.summary?.unscheduled_accepted_treatment ?? null"
        :loading="dashboardStore.loading"
      />
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <TodayScheduleWidget :scope="scope" @new-appointment="newAppointmentDialogVisible = true" />
      <UpcomingAppointmentsWidget :scope="scope" @new-appointment="newAppointmentDialogVisible = true" />
    </div>

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
      <LowStockWidget />
      <DueLabCasesWidget />
    </div>

    <AiQuestionBox
      :title="t('aiAssistant.dashboardInsight.title')"
      :placeholder="t('aiAssistant.dashboardInsight.placeholder')"
      :ask-fn="askDashboardInsight"
    />

    <AppointmentDialog v-model:visible="newAppointmentDialogVisible" />
  </div>
</template>
