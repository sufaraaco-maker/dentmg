<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Select from 'primevue/select'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useLabsStore } from '@/stores/labs'
import LabCaseStatusChip from '@/components/laboratory/LabCaseStatusChip.vue'
import CreateLabCaseDialog from '@/components/laboratory/CreateLabCaseDialog.vue'
import { parseServerDateTime } from '@/lib/date'
import type { LabCase, LabCaseStatus } from '@/types/laboratory'

/** Lab Cases list (design doc §6) — mirrors `PurchaseOrdersView.vue`'s direct-`api`-call,
 *  paginated pattern. */
interface PaginatedLabCases {
  data: LabCase[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

const STATUS_OPTIONS: LabCaseStatus[] = ['draft', 'sent', 'received', 'quality_checked', 'cancelled']

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const labsStore = useLabsStore()

const canCreate = auth.isAdmin || auth.isDentist

const cases = ref<LabCase[]>([])
const totalRecords = ref(0)
const perPage = ref(20)
const page = ref(1)
const labId = ref<string | null>(null)
const status = ref<LabCaseStatus | null>(null)
const loading = ref(false)
const createDialogVisible = ref(false)

async function fetchCases() {
  loading.value = true
  try {
    const { data } = await api.get<PaginatedLabCases>('/lab-cases', {
      params: {
        lab_id: labId.value || undefined,
        status: status.value || undefined,
        page: page.value,
      },
    })
    cases.value = data.data
    totalRecords.value = data.meta.total
    perPage.value = data.meta.per_page
  } finally {
    loading.value = false
  }
}

function onPage(event: { page: number }) {
  page.value = event.page + 1
  fetchCases()
}

watch([labId, status], () => {
  page.value = 1
  fetchCases()
})

function viewCase(labCase: LabCase) {
  router.push({ name: 'lab-case-detail', params: { id: labCase.id } })
}

function onCreated(labCase: LabCase) {
  router.push({ name: 'lab-case-detail', params: { id: labCase.id } })
}

function formatDateTime(value: string): string {
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(parseServerDateTime(value))
}

onMounted(async () => {
  await labsStore.fetchAll()
  await fetchCases()
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('laboratory.labCases.title') }}
      </h1>
      <Button
        v-if="canCreate"
        :label="t('laboratory.labCases.new')"
        icon="pi pi-plus"
        @click="createDialogVisible = true"
      />
    </div>

    <div class="flex flex-wrap items-center gap-4">
      <Select
        v-model="labId"
        :options="[{ id: null, name: t('laboratory.labCases.allLabs') }, ...labsStore.items]"
        option-label="name"
        option-value="id"
        class="w-56"
      />
      <Select
        v-model="status"
        :options="[
          { value: null, label: t('laboratory.labCases.allStatuses') },
          ...STATUS_OPTIONS.map((s) => ({ value: s, label: t(`laboratory.labCases.status.${s}`) })),
        ]"
        option-label="label"
        option-value="value"
        class="w-56"
      />
    </div>

    <DataTable
      :value="cases"
      :loading="loading"
      lazy
      paginator
      :rows="perPage"
      :total-records="totalRecords"
      class="cursor-pointer"
      @page="onPage"
      @row-click="({ data }) => viewCase(data)"
    >
      <template #empty>
        <span class="text-surface-500 dark:text-surface-400">{{ t('laboratory.labCases.empty') }}</span>
      </template>

      <Column :header="t('laboratory.labCases.caseNumber')">
        <template #body="{ data }"
          ><span dir="ltr">{{ data.case_number }}</span></template
        >
      </Column>
      <Column :header="t('laboratory.labCases.patient')">
        <template #body="{ data }">{{ data.patient?.full_name ?? '—' }}</template>
      </Column>
      <Column :header="t('laboratory.labCases.lab')">
        <template #body="{ data }">{{ data.lab?.name ?? '—' }}</template>
      </Column>
      <Column :header="t('laboratory.labCases.status.label')">
        <template #body="{ data }"><LabCaseStatusChip :status="data.status" /></template>
      </Column>
      <Column :header="t('laboratory.labCases.dueAt')">
        <template #body="{ data }">
          <span dir="ltr">{{ data.due_at ? formatDateTime(data.due_at) : '—' }}</span>
        </template>
      </Column>
    </DataTable>
  </div>

  <CreateLabCaseDialog v-model:visible="createDialogVisible" @created="onCreated" />
</template>
