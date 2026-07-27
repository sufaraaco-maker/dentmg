<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import SupplyFormDialog from '@/components/inventory/SupplyFormDialog.vue'
import RecordStockMovementDialog from '@/components/inventory/RecordStockMovementDialog.vue'
import { parseServerDateTime, parseLocalDate } from '@/lib/date'
import type { Supply, StockMovement } from '@/types/inventory'

/**
 * Supply Detail (design doc §11) — overview card (reorder settings, computed on-hand), the
 * append-only Stock Movements ledger (design doc §4/§6), and the Record Usage/Adjustment action.
 * Mirrors `InvoiceDetailView.vue`'s overview-card + related-table shape.
 */
interface PaginatedMovements {
  data: StockMovement[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const auth = useAuthStore()

const supplyId = computed(() => route.params.id as string)

const supply = ref<Supply | null>(null)
const loading = ref(false)
const notFound = ref(false)

const movements = ref<StockMovement[]>([])
const movementsLoading = ref(false)
const movementsTotal = ref(0)
const movementsPerPage = ref(20)
const movementsPage = ref(1)

// Catalog management (edit/deactivate) is admin+receptionist (design doc §10); recording a
// movement is open to every authenticated role, including dentists logging their own usage
// (design doc §15 Decision 1) — StockMovementPolicy::create() has no role restriction at all.
const canManage = computed(() => auth.isAdmin || auth.isReceptionist)

const editDialogVisible = ref(false)
const movementDialogVisible = ref(false)

async function loadSupply() {
  notFound.value = false
  loading.value = true
  try {
    const { data } = await api.get<Supply>(`/supplies/${supplyId.value}`)
    supply.value = data
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }
}

async function loadMovements() {
  movementsLoading.value = true
  try {
    const { data } = await api.get<PaginatedMovements>(`/supplies/${supplyId.value}/stock-movements`, {
      params: { page: movementsPage.value },
    })
    movements.value = data.data
    movementsTotal.value = data.meta.total
    movementsPerPage.value = data.meta.per_page
  } finally {
    movementsLoading.value = false
  }
}

watch(
  supplyId,
  async () => {
    await loadSupply()
    await loadMovements()
  },
  { immediate: true },
)

function onMovementsPage(event: { page: number }) {
  movementsPage.value = event.page + 1
  loadMovements()
}

function formatDateTime(value: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(
    parseServerDateTime(value),
  )
}

function formatDate(value: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(parseLocalDate(value))
}

function goToSupplies() {
  router.push({ name: 'supplies' })
}

function onSaved(updated: Supply) {
  supply.value = updated
}

function onRecorded() {
  toast.add({ severity: 'success', summary: t('inventory.movements.recorded'), life: 3000 })
  movementsPage.value = 1
  loadMovements()
  loadSupply()
}

function confirmDeactivate() {
  if (!supply.value) return

  confirm.require({
    message: t('inventory.suppliers.deactivateConfirmMessage', { name: supply.value.name }),
    header: t('inventory.suppliers.deactivateConfirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await api.delete(`/supplies/${supply.value!.id}`)
        toast.add({ severity: 'success', summary: t('inventory.suppliers.deactivated'), life: 3000 })
        await loadSupply()
      } catch {
        toast.add({ severity: 'error', summary: t('inventory.suppliers.deactivateError'), life: 3000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" text rounded @click="goToSupplies" />
        <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
          {{ supply?.name ?? t('inventory.supplies.title') }}
        </h1>
        <Tag v-if="supply?.is_low_stock" :value="t('inventory.supplies.lowStock')" severity="warn" />
        <Tag
          v-if="supply && !supply.is_active"
          :value="t('inventory.supplies.inactive')"
          severity="secondary"
        />
      </div>
      <div v-if="supply && canManage" class="flex gap-2">
        <Button
          :label="t('common.edit')"
          icon="pi pi-pencil"
          outlined
          size="small"
          @click="editDialogVisible = true"
        />
        <Button
          v-if="supply.is_active"
          :label="t('common.deactivate')"
          icon="pi pi-ban"
          severity="danger"
          outlined
          size="small"
          @click="confirmDeactivate"
        />
      </div>
    </div>

    <Skeleton v-if="loading" height="20rem" />

    <Card v-else-if="notFound">
      <template #content>
        <p class="text-surface-600 dark:text-surface-300">{{ t('inventory.supplies.notFound') }}</p>
      </template>
    </Card>

    <template v-else-if="supply">
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <Card class="lg:col-span-2">
          <template #title>{{ t('inventory.supplies.detailOverview') }}</template>
          <template #content>
            <dl class="grid grid-cols-2 gap-y-3 text-sm">
              <dt class="text-surface-500">{{ t('inventory.supplies.category') }}</dt>
              <dd>{{ supply.category?.name ?? '—' }}</dd>

              <dt class="text-surface-500">{{ t('inventory.supplies.defaultSupplier') }}</dt>
              <dd>{{ supply.default_supplier?.name ?? '—' }}</dd>

              <dt class="text-surface-500">{{ t('inventory.supplies.sku') }}</dt>
              <dd dir="ltr" class="text-start">{{ supply.sku ?? '—' }}</dd>

              <dt class="text-surface-500">{{ t('inventory.supplies.unitOfMeasure') }}</dt>
              <dd>{{ supply.unit_of_measure }}</dd>

              <dt class="text-surface-500">{{ t('inventory.supplies.unitCost') }}</dt>
              <dd dir="ltr" class="text-start">{{ supply.unit_cost ?? '—' }}</dd>

              <dt class="text-surface-500">{{ t('inventory.supplies.reorderQuantity') }}</dt>
              <dd>{{ supply.reorder_quantity ?? '—' }}</dd>
            </dl>
          </template>
        </Card>

        <Card>
          <template #title>{{ t('inventory.supplies.onHand') }}</template>
          <template #content>
            <p class="text-2xl font-semibold">{{ supply.quantity_on_hand }} {{ supply.unit_of_measure }}</p>
            <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">
              {{ t('inventory.supplies.reorderLevel') }}: {{ supply.reorder_level }}
            </p>
            <Button
              :label="t('inventory.movements.record')"
              icon="pi pi-plus"
              size="small"
              class="mt-3"
              @click="movementDialogVisible = true"
            />
          </template>
        </Card>
      </div>

      <Card>
        <template #title>{{ t('inventory.movements.ledgerTitle') }}</template>
        <template #content>
          <p v-if="!movements.length && !movementsLoading" class="text-sm text-surface-500">
            {{ t('inventory.movements.empty') }}
          </p>
          <DataTable
            v-else
            :value="movements"
            :loading="movementsLoading"
            lazy
            paginator
            :rows="movementsPerPage"
            :total-records="movementsTotal"
            @page="onMovementsPage"
          >
            <Column :header="t('inventory.movements.occurredAt')">
              <template #body="{ data }"><span dir="ltr">{{ formatDateTime(data.occurred_at) }}</span></template>
            </Column>
            <Column :header="t('inventory.movements.reason')">
              <template #body="{ data }">{{ t(`inventory.movements.reasons.${data.reason}`) }}</template>
            </Column>
            <Column :header="t('inventory.movements.quantity')">
              <template #body="{ data }">
                <span
                  dir="ltr"
                  :class="data.quantity_delta < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'"
                >
                  {{ data.quantity_delta > 0 ? '+' : '' }}{{ data.quantity_delta }}
                </span>
              </template>
            </Column>
            <Column :header="t('inventory.movements.expirationDate')">
              <template #body="{ data }">
                <span dir="ltr">{{ data.expiration_date ? formatDate(data.expiration_date) : '—' }}</span>
              </template>
            </Column>
            <Column :header="t('inventory.movements.performedBy')">
              <template #body="{ data }">{{ data.performed_by?.name ?? '—' }}</template>
            </Column>
            <Column field="notes" :header="t('inventory.movements.notes')" />
          </DataTable>
        </template>
      </Card>
    </template>
  </div>

  <SupplyFormDialog v-if="supply" v-model:visible="editDialogVisible" :supply="supply" @saved="onSaved" />
  <RecordStockMovementDialog
    v-if="supply"
    v-model:visible="movementDialogVisible"
    :supply="supply"
    @recorded="onRecorded"
  />
</template>
