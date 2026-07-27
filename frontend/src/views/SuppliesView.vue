<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import Tag from 'primevue/tag'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useSupplyCategoriesStore } from '@/stores/supplyCategories'
import SupplyFormDialog from '@/components/inventory/SupplyFormDialog.vue'
import type { Supply } from '@/types/inventory'

/**
 * Main Supplies list (design doc §11) — a real paginated screen (unlike the small Supplier/
 * Category catalogs), mirroring `PatientsView.vue`'s direct-`api`-call pattern rather than a
 * Pinia store: Supplies can realistically grow into the hundreds (design doc §13).
 */
interface PaginatedSupplies {
  data: Supply[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const auth = useAuthStore()
const categoriesStore = useSupplyCategoriesStore()

const canManage = auth.isAdmin || auth.isReceptionist

const supplies = ref<Supply[]>([])
const totalRecords = ref(0)
const perPage = ref(20)
const page = ref(1)
const search = ref('')
const categoryId = ref<string | null>(null)
const lowStockOnly = ref(route.query.low_stock_only === '1')
const loading = ref(false)

const dialogVisible = ref(false)
const editingSupply = ref<Supply | null>(null)

async function fetchSupplies() {
  loading.value = true
  try {
    const { data } = await api.get<PaginatedSupplies>('/supplies', {
      params: {
        search: search.value || undefined,
        category_id: categoryId.value || undefined,
        low_stock_only: lowStockOnly.value ? 1 : undefined,
        page: page.value,
      },
    })
    supplies.value = data.data
    totalRecords.value = data.meta.total
    perPage.value = data.meta.per_page
  } finally {
    loading.value = false
  }
}

function onPage(event: { page: number }) {
  page.value = event.page + 1
  fetchSupplies()
}

let searchTimeout: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    fetchSupplies()
  }, 300)
})

watch([categoryId, lowStockOnly], () => {
  page.value = 1
  fetchSupplies()
})

function openCreateDialog() {
  editingSupply.value = null
  dialogVisible.value = true
}

function openEditDialog(supply: Supply, event: Event) {
  event.stopPropagation()
  editingSupply.value = supply
  dialogVisible.value = true
}

function onSaved() {
  toast.add({ severity: 'success', summary: t('inventory.supplies.saved'), life: 3000 })
  fetchSupplies()
}

function viewSupply(supply: Supply) {
  router.push({ name: 'supply-detail', params: { id: supply.id } })
}

onMounted(async () => {
  await categoriesStore.fetchAll()
  await fetchSupplies()
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('inventory.supplies.title') }}
      </h1>
      <Button
        v-if="canManage"
        :label="t('inventory.supplies.new')"
        icon="pi pi-plus"
        @click="openCreateDialog"
      />
    </div>

    <div class="flex flex-wrap items-center gap-4">
      <IconField class="max-w-sm">
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" :placeholder="t('inventory.supplies.search')" class="w-full" />
      </IconField>
      <Select
        v-model="categoryId"
        :options="[{ id: null, name: t('inventory.supplies.allCategories') }, ...categoriesStore.items]"
        option-label="name"
        option-value="id"
        class="w-56"
      />
      <div class="flex items-center gap-2">
        <ToggleSwitch v-model="lowStockOnly" input-id="supplies-low-stock-only" />
        <label for="supplies-low-stock-only" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.supplies.lowStockOnly') }}
        </label>
      </div>
    </div>

    <DataTable
      :value="supplies"
      :loading="loading"
      lazy
      paginator
      :rows="perPage"
      :total-records="totalRecords"
      class="cursor-pointer"
      @page="onPage"
      @row-click="({ data }) => viewSupply(data)"
    >
      <template #empty>
        <span class="text-surface-500 dark:text-surface-400">{{ t('inventory.supplies.empty') }}</span>
      </template>

      <Column field="name" :header="t('inventory.supplies.name')" />
      <Column :header="t('inventory.supplies.category')">
        <template #body="{ data }">{{ data.category?.name ?? '—' }}</template>
      </Column>
      <Column :header="t('inventory.supplies.onHand')">
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <span>{{ data.quantity_on_hand }} {{ data.unit_of_measure }}</span>
            <Tag v-if="data.is_low_stock" :value="t('inventory.supplies.lowStock')" severity="warn" />
          </div>
        </template>
      </Column>
      <Column field="reorder_level" :header="t('inventory.supplies.reorderLevel')" />
      <Column :header="t('inventory.supplies.defaultSupplier')">
        <template #body="{ data }">{{ data.default_supplier?.name ?? '—' }}</template>
      </Column>
      <Column :header="t('inventory.supplies.status')">
        <template #body="{ data }">
          <Tag
            :value="data.is_active ? t('inventory.supplies.active') : t('inventory.supplies.inactive')"
            :severity="data.is_active ? 'success' : 'secondary'"
          />
        </template>
      </Column>
      <Column v-if="canManage" :header="t('inventory.supplies.actions')" style="width: 5rem">
        <template #body="{ data }">
          <Button
            icon="pi pi-pencil"
            text
            rounded
            :aria-label="t('common.edit')"
            @click="openEditDialog(data, $event)"
          />
        </template>
      </Column>
    </DataTable>

    <SupplyFormDialog v-model:visible="dialogVisible" :supply="editingSupply ?? undefined" @saved="onSaved" />
  </div>
</template>
