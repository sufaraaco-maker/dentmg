<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import ToggleSwitch from 'primevue/toggleswitch'
import SupplyCategoryFormDialog from '@/components/inventory/SupplyCategoryFormDialog.vue'
import { useSupplyCategoriesStore } from '@/stores/supplyCategories'
import type { SupplyCategory } from '@/types/inventory'

/**
 * Admin-only CRUD screen (design doc §6/§10/§11) — mirrors SuppliersView.vue exactly.
 */
const { t } = useI18n()
const confirm = useConfirm()
const toast = useToast()
const store = useSupplyCategoriesStore()

const showInactive = ref(true)
const dialogVisible = ref(false)
const editingCategory = ref<SupplyCategory | null>(null)

const loading = computed(() => store.loading && !store.loaded)

const filteredCategories = computed(() => {
  if (showInactive.value) return store.items
  return store.items.filter((category) => category.is_active)
})

function openCreateDialog() {
  editingCategory.value = null
  dialogVisible.value = true
}

function openEditDialog(category: SupplyCategory) {
  editingCategory.value = category
  dialogVisible.value = true
}

function confirmDeactivate(category: SupplyCategory) {
  confirm.require({
    message: t('inventory.categories.deactivateConfirmMessage', { name: category.name }),
    header: t('inventory.categories.deactivateConfirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await store.deactivate(category.id)
        toast.add({ severity: 'success', summary: t('inventory.categories.deactivated'), life: 3000 })
      } catch {
        toast.add({ severity: 'error', summary: t('inventory.categories.deactivateError'), life: 3000 })
      }
    },
  })
}

onMounted(async () => {
  try {
    await store.fetchAll()
  } catch {
    toast.add({ severity: 'error', summary: t('inventory.categories.loadError'), life: 3000 })
  }
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('inventory.categories.title') }}
      </h1>
      <Button :label="t('inventory.categories.new')" icon="pi pi-plus" @click="openCreateDialog" />
    </div>

    <div class="flex items-center gap-2">
      <ToggleSwitch v-model="showInactive" input-id="categories-show-inactive" />
      <label for="categories-show-inactive" class="text-sm text-surface-700 dark:text-surface-200">
        {{ t('inventory.categories.showInactive') }}
      </label>
    </div>

    <DataTable
      :value="filteredCategories"
      :loading="loading"
      sort-field="sort_order"
      :sort-order="1"
      :paginator="filteredCategories.length > 20"
      :rows="20"
    >
      <template #empty>
        <span class="text-surface-500 dark:text-surface-400">{{ t('inventory.categories.empty') }}</span>
      </template>

      <Column field="name" :header="t('inventory.categories.name')" sortable />
      <Column field="sort_order" :header="t('inventory.categories.sortOrder')" sortable style="width: 8rem" />
      <Column field="is_active" :header="t('inventory.categories.status')" sortable>
        <template #body="{ data }">
          <Tag
            :value="data.is_active ? t('inventory.categories.active') : t('inventory.categories.inactive')"
            :severity="data.is_active ? 'success' : 'secondary'"
          />
        </template>
      </Column>
      <Column :header="t('inventory.categories.actions')" style="width: 8rem">
        <template #body="{ data }">
          <div class="flex gap-2">
            <Button
              icon="pi pi-pencil"
              text
              rounded
              :aria-label="t('common.edit')"
              @click="openEditDialog(data)"
            />
            <Button
              v-if="data.is_active"
              icon="pi pi-ban"
              text
              rounded
              severity="danger"
              :aria-label="t('common.deactivate')"
              @click="confirmDeactivate(data)"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <SupplyCategoryFormDialog v-model:visible="dialogVisible" :category="editingCategory ?? undefined" />
  </div>
</template>
