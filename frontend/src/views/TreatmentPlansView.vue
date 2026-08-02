<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { parseServerDateTime } from '@/lib/date'
import { treatmentPlansApi } from '@/services/treatmentPlans'
import TreatmentPlanStatusChip from '@/components/treatmentPlans/TreatmentPlanStatusChip.vue'
import { TREATMENT_PLAN_STATUSES, type TreatmentPlan, type TreatmentPlanStatus } from '@/types/treatmentPlan'

/**
 * Clinic-wide Treatment Plans entry point, mirroring `InvoicesView.vue`'s exact pattern — the
 * Sidebar's Treatment Plans item pointed at `comingSoon` even though patient-scoped Treatment Plan
 * CRUD was already fully built; this is purely a cross-patient index over the same `TreatmentPlan`
 * model, mirroring `SuppliesView.vue`'s server-paginated lazy `DataTable` pattern.
 */
const { t, locale } = useI18n()
const router = useRouter()

const plans = ref<TreatmentPlan[]>([])
const totalRecords = ref(0)
const perPage = ref(15)
const page = ref(1)
const search = ref('')
const status = ref<TreatmentPlanStatus | null>(null)
const loading = ref(false)

async function fetchPlans() {
  loading.value = true
  try {
    const data = await treatmentPlansApi.listAll({
      search: search.value || undefined,
      status: status.value ?? undefined,
      page: page.value,
    })
    plans.value = data.data
    totalRecords.value = data.meta.total
    perPage.value = data.meta.per_page
  } finally {
    loading.value = false
  }
}

function onPage(event: { page: number }) {
  page.value = event.page + 1
  fetchPlans()
}

let searchTimeout: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    fetchPlans()
  }, 300)
})

watch(status, () => {
  page.value = 1
  fetchPlans()
})

function formatDate(createdAt: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(parseServerDateTime(createdAt))
}

function viewPlan(plan: TreatmentPlan) {
  if (!plan.patient) return
  router.push({ name: 'treatment-plan-detail', params: { id: plan.patient.id, planId: plan.id } })
}

onMounted(fetchPlans)
</script>

<template>
  <div class="flex flex-col gap-4">
    <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
      {{ t('treatmentPlans.index.title') }}
    </h1>

    <div class="flex flex-wrap items-center gap-4">
      <IconField class="max-w-sm">
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" :placeholder="t('treatmentPlans.index.search')" class="w-full" />
      </IconField>
      <Select
        v-model="status"
        :options="[null, ...TREATMENT_PLAN_STATUSES]"
        :placeholder="t('treatmentPlans.index.allStatuses')"
        class="w-48"
      >
        <template #value="{ value }">
          {{ value ? t(`treatmentPlans.status.${value}`) : t('treatmentPlans.index.allStatuses') }}
        </template>
        <template #option="{ option }">
          {{ option ? t(`treatmentPlans.status.${option}`) : t('treatmentPlans.index.allStatuses') }}
        </template>
      </Select>
    </div>

    <DataTable
      :value="plans"
      :loading="loading"
      lazy
      paginator
      :rows="perPage"
      :total-records="totalRecords"
      class="cursor-pointer"
      @page="onPage"
      @row-click="({ data }) => viewPlan(data)"
    >
      <template #empty>
        <span class="text-surface-500 dark:text-surface-400">{{ t('treatmentPlans.list.empty') }}</span>
      </template>

      <Column :header="t('treatmentPlans.index.patient')">
        <template #body="{ data }">
          {{ data.patient ? `${data.patient.first_name} ${data.patient.last_name}` : '—' }}
        </template>
      </Column>

      <Column field="title" :header="t('treatmentPlans.list.plan')">
        <template #body="{ data }">
          {{ data.title ?? t('treatmentPlans.list.untitled') }}
        </template>
      </Column>

      <Column :header="t('treatmentPlans.list.status')">
        <template #body="{ data }">
          <TreatmentPlanStatusChip :status="data.status" size="small" />
        </template>
      </Column>

      <Column field="created_at" :header="t('treatmentPlans.list.createdAt')">
        <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
      </Column>

      <Column :header="t('treatmentPlans.list.estimatedCost')">
        <template #body="{ data }">
          <span dir="ltr">{{ data.estimated_cost ?? '0.00' }}</span>
        </template>
      </Column>
    </DataTable>
  </div>
</template>
