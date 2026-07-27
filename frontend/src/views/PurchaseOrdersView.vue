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
import { useSuppliersStore } from '@/stores/suppliers'
import PurchaseOrderStatusChip from '@/components/inventory/PurchaseOrderStatusChip.vue'
import CreatePurchaseOrderDialog from '@/components/inventory/CreatePurchaseOrderDialog.vue'
import { parseLocalDate } from '@/lib/date'
import type { PurchaseOrder, PurchaseOrderStatus } from '@/types/inventory'

/** Purchase Orders list (design doc §11) — mirrors `SuppliesView.vue`'s direct-`api`-call,
 *  paginated pattern. */
interface PaginatedOrders {
  data: PurchaseOrder[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

const STATUS_OPTIONS: PurchaseOrderStatus[] = ['draft', 'placed', 'partially_received', 'received', 'cancelled']

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const suppliersStore = useSuppliersStore()

const canCreate = auth.isAdmin || auth.isReceptionist

const orders = ref<PurchaseOrder[]>([])
const totalRecords = ref(0)
const perPage = ref(20)
const page = ref(1)
const supplierId = ref<string | null>(null)
const status = ref<PurchaseOrderStatus | null>(null)
const loading = ref(false)
const createDialogVisible = ref(false)

async function fetchOrders() {
  loading.value = true
  try {
    const { data } = await api.get<PaginatedOrders>('/purchase-orders', {
      params: { supplier_id: supplierId.value || undefined, status: status.value || undefined, page: page.value },
    })
    orders.value = data.data
    totalRecords.value = data.meta.total
    perPage.value = data.meta.per_page
  } finally {
    loading.value = false
  }
}

function onPage(event: { page: number }) {
  page.value = event.page + 1
  fetchOrders()
}

watch([supplierId, status], () => {
  page.value = 1
  fetchOrders()
})

function viewOrder(order: PurchaseOrder) {
  router.push({ name: 'purchase-order-detail', params: { id: order.id } })
}

function onCreated(order: PurchaseOrder) {
  router.push({ name: 'purchase-order-detail', params: { id: order.id } })
}

onMounted(async () => {
  await suppliersStore.fetchAll()
  await fetchOrders()
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('inventory.purchaseOrders.title') }}
      </h1>
      <Button
        v-if="canCreate"
        :label="t('inventory.purchaseOrders.new')"
        icon="pi pi-plus"
        @click="createDialogVisible = true"
      />
    </div>

    <div class="flex flex-wrap items-center gap-4">
      <Select
        v-model="supplierId"
        :options="[{ id: null, name: t('inventory.purchaseOrders.allSuppliers') }, ...suppliersStore.items]"
        option-label="name"
        option-value="id"
        class="w-56"
      />
      <Select
        v-model="status"
        :options="[
          { value: null, label: t('inventory.purchaseOrders.allStatuses') },
          ...STATUS_OPTIONS.map((s) => ({ value: s, label: t(`inventory.purchaseOrders.status.${s}`) })),
        ]"
        option-label="label"
        option-value="value"
        class="w-56"
      />
    </div>

    <DataTable
      :value="orders"
      :loading="loading"
      lazy
      paginator
      :rows="perPage"
      :total-records="totalRecords"
      class="cursor-pointer"
      @page="onPage"
      @row-click="({ data }) => viewOrder(data)"
    >
      <template #empty>
        <span class="text-surface-500 dark:text-surface-400">{{ t('inventory.purchaseOrders.empty') }}</span>
      </template>

      <Column :header="t('inventory.purchaseOrders.orderNumber')">
        <template #body="{ data }"><span dir="ltr">{{ data.order_number }}</span></template>
      </Column>
      <Column :header="t('inventory.purchaseOrders.supplier')">
        <template #body="{ data }">{{ data.supplier?.name ?? '—' }}</template>
      </Column>
      <Column :header="t('inventory.purchaseOrders.status.label')">
        <template #body="{ data }"><PurchaseOrderStatusChip :status="data.status" /></template>
      </Column>
      <Column :header="t('inventory.purchaseOrders.orderedAt')">
        <template #body="{ data }">
          <span dir="ltr">
            {{ data.ordered_at ? new Intl.DateTimeFormat().format(parseLocalDate(data.ordered_at)) : '—' }}
          </span>
        </template>
      </Column>
      <Column :header="t('inventory.purchaseOrders.itemCount')">
        <template #body="{ data }">{{ data.items?.length ?? 0 }}</template>
      </Column>
    </DataTable>
  </div>

  <CreatePurchaseOrderDialog v-model:visible="createDialogVisible" @created="onCreated" />
</template>
