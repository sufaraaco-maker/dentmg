<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import PurchaseOrderStatusChip from '@/components/inventory/PurchaseOrderStatusChip.vue'
import PurchaseOrderActionsBar from '@/components/inventory/PurchaseOrderActionsBar.vue'
import AddPurchaseOrderItemDialog from '@/components/inventory/AddPurchaseOrderItemDialog.vue'
import ReceivePurchaseOrderItemDialog from '@/components/inventory/ReceivePurchaseOrderItemDialog.vue'
import { parseServerDateTime, parseLocalDate } from '@/lib/date'
import type { PurchaseOrder, PurchaseOrderItem } from '@/types/inventory'

/**
 * Purchase Order Detail (design doc §11) — overview, items table, and the full
 * draft→placed→partially_received→received lifecycle (design doc §5/§8), mirroring
 * `InvoiceDetailView.vue`'s overview-card + related-table + actions-bar shape.
 */
const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const auth = useAuthStore()

const orderId = computed(() => route.params.id as string)

const order = ref<PurchaseOrder | null>(null)
const loading = ref(false)
const notFound = ref(false)

const canManage = computed(() => auth.isAdmin || auth.isReceptionist)
const canDelete = computed(() => auth.isAdmin)
const isDraft = computed(() => order.value?.status === 'draft')
const canAddItem = computed(() => canManage.value && isDraft.value)
const canReceive = computed(
  () => canManage.value && (order.value?.status === 'placed' || order.value?.status === 'partially_received'),
)

const addItemDialogVisible = ref(false)
const receivingItem = ref<PurchaseOrderItem | null>(null)

async function load() {
  notFound.value = false
  loading.value = true
  try {
    const { data } = await api.get<PurchaseOrder>(`/purchase-orders/${orderId.value}`)
    order.value = data
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }
}

watch(orderId, load, { immediate: true })

function formatDate(value: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(parseLocalDate(value))
}

function formatDateTime(value: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(
    parseServerDateTime(value),
  )
}

function goToList() {
  router.push({ name: 'purchase-orders' })
}

function onUpdated(updated: PurchaseOrder) {
  order.value = updated
}

function onItemAdded(updated: PurchaseOrder) {
  order.value = updated
  toast.add({ severity: 'success', summary: t('inventory.purchaseOrders.items.added'), life: 3000 })
}

function openReceive(item: PurchaseOrderItem) {
  receivingItem.value = item
}

function onItemReceived(updated: PurchaseOrder) {
  order.value = updated
  toast.add({ severity: 'success', summary: t('inventory.purchaseOrders.items.received'), life: 3000 })
}

function confirmDelete() {
  if (!order.value) return

  confirm.require({
    message: t('inventory.purchaseOrders.confirmDeleteMessage'),
    header: t('inventory.purchaseOrders.confirmDeleteHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await api.delete(`/purchase-orders/${order.value!.id}`)
        toast.add({ severity: 'success', summary: t('inventory.purchaseOrders.deleted'), life: 3000 })
        goToList()
      } catch {
        toast.add({ severity: 'error', summary: t('inventory.purchaseOrders.deleteError'), life: 3000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" text rounded @click="goToList" />
        <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0" dir="ltr">
          {{ order?.order_number ?? t('inventory.purchaseOrders.title') }}
        </h1>
        <PurchaseOrderStatusChip v-if="order" :status="order.status" />
      </div>
      <div v-if="order && canDelete && isDraft" class="flex gap-2">
        <Button
          :label="t('common.delete')"
          icon="pi pi-trash"
          severity="danger"
          outlined
          size="small"
          @click="confirmDelete"
        />
      </div>
    </div>

    <PurchaseOrderActionsBar v-if="order" :purchase-order="order" @updated="onUpdated" />

    <Skeleton v-if="loading" height="20rem" />

    <Card v-else-if="notFound">
      <template #content>
        <p class="text-surface-600 dark:text-surface-300">{{ t('inventory.purchaseOrders.notFound') }}</p>
      </template>
    </Card>

    <template v-else-if="order">
      <Card>
        <template #title>{{ t('inventory.purchaseOrders.overview') }}</template>
        <template #content>
          <dl class="grid grid-cols-2 gap-y-3 text-sm md:grid-cols-4">
            <dt class="text-surface-500">{{ t('inventory.purchaseOrders.supplier') }}</dt>
            <dd>{{ order.supplier?.name ?? '—' }}</dd>

            <dt class="text-surface-500">{{ t('inventory.purchaseOrders.createdBy') }}</dt>
            <dd>{{ order.created_by?.name ?? '—' }}</dd>

            <dt class="text-surface-500">{{ t('inventory.purchaseOrders.orderedAt') }}</dt>
            <dd dir="ltr" class="text-start">{{ order.ordered_at ? formatDate(order.ordered_at) : '—' }}</dd>

            <dt class="text-surface-500">{{ t('inventory.purchaseOrders.expectedAt') }}</dt>
            <dd dir="ltr" class="text-start">
              {{ order.expected_at ? formatDate(order.expected_at) : '—' }}
            </dd>

            <dt class="text-surface-500">{{ t('inventory.purchaseOrders.createdAt') }}</dt>
            <dd dir="ltr" class="text-start">{{ formatDateTime(order.created_at) }}</dd>
          </dl>
          <p v-if="order.notes" class="mt-3 text-sm text-surface-600 dark:text-surface-300">
            {{ order.notes }}
          </p>
        </template>
      </Card>

      <Card>
        <template #title>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span>{{ t('inventory.purchaseOrders.items.title') }}</span>
            <Button
              v-if="canAddItem"
              :label="t('inventory.purchaseOrders.items.add')"
              icon="pi pi-plus"
              size="small"
              @click="addItemDialogVisible = true"
            />
          </div>
        </template>
        <template #content>
          <p v-if="!order.items?.length" class="text-sm text-surface-500">
            {{ t('inventory.purchaseOrders.items.empty') }}
          </p>
          <DataTable v-else :value="order.items">
            <Column :header="t('inventory.purchaseOrders.items.description')" field="description" />
            <Column :header="t('inventory.purchaseOrders.items.quantityOrdered')" field="quantity_ordered" />
            <Column
              :header="t('inventory.purchaseOrders.items.quantityReceived')"
              field="quantity_received"
            />
            <Column :header="t('inventory.purchaseOrders.items.unitCost')">
              <template #body="{ data }"
                ><span dir="ltr">{{ data.unit_cost }}</span></template
              >
            </Column>
            <Column :header="t('inventory.purchaseOrders.items.subtotal')">
              <template #body="{ data }"
                ><span dir="ltr">{{ data.subtotal }}</span></template
              >
            </Column>
            <Column v-if="canReceive" style="width: 10rem">
              <template #body="{ data }">
                <Button
                  v-if="data.quantity_remaining > 0"
                  :label="t('inventory.purchaseOrders.items.receive')"
                  icon="pi pi-inbox"
                  size="small"
                  outlined
                  @click="openReceive(data)"
                />
              </template>
            </Column>
          </DataTable>
        </template>
      </Card>
    </template>
  </div>

  <AddPurchaseOrderItemDialog
    v-if="order"
    v-model:visible="addItemDialogVisible"
    :purchase-order="order"
    @added="onItemAdded"
  />
  <ReceivePurchaseOrderItemDialog
    v-if="receivingItem"
    :visible="receivingItem !== null"
    :item="receivingItem"
    @update:visible="(v) => !v && (receivingItem = null)"
    @received="onItemReceived"
  />
</template>
