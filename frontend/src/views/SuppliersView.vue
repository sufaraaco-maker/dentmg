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
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import SupplierFormDialog from '@/components/inventory/SupplierFormDialog.vue'
import { useSuppliersStore } from '@/stores/suppliers'
import type { Supplier } from '@/types/inventory'

/**
 * Admin-only CRUD screen (design doc §6/§10/§11) — mirrors AppointmentTypesView.vue exactly:
 * client-side search/filter over the already-cached, unpaginated suppliers list.
 */
const { t } = useI18n()
const confirm = useConfirm()
const toast = useToast()
const store = useSuppliersStore()

const search = ref('')
const showInactive = ref(true)
const dialogVisible = ref(false)
const editingSupplier = ref<Supplier | null>(null)

const loading = computed(() => store.loading && !store.loaded)

const filteredSuppliers = computed(() => {
  const query = search.value.trim().toLowerCase()

  return store.items.filter((supplier) => {
    if (!showInactive.value && !supplier.is_active) return false
    if (query && !supplier.name.toLowerCase().includes(query)) return false
    return true
  })
})

function openCreateDialog() {
  editingSupplier.value = null
  dialogVisible.value = true
}

function openEditDialog(supplier: Supplier) {
  editingSupplier.value = supplier
  dialogVisible.value = true
}

function confirmDeactivate(supplier: Supplier) {
  confirm.require({
    message: t('inventory.suppliers.deactivateConfirmMessage', { name: supplier.name }),
    header: t('inventory.suppliers.deactivateConfirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await store.deactivate(supplier.id)
        toast.add({ severity: 'success', summary: t('inventory.suppliers.deactivated'), life: 3000 })
      } catch {
        toast.add({ severity: 'error', summary: t('inventory.suppliers.deactivateError'), life: 3000 })
      }
    },
  })
}

onMounted(async () => {
  try {
    await store.fetchAll()
  } catch {
    toast.add({ severity: 'error', summary: t('inventory.suppliers.loadError'), life: 3000 })
  }
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('inventory.suppliers.title') }}
      </h1>
      <Button :label="t('inventory.suppliers.new')" icon="pi pi-plus" @click="openCreateDialog" />
    </div>

    <div class="flex flex-wrap items-center gap-4">
      <IconField class="max-w-sm">
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" :placeholder="t('inventory.suppliers.search')" class="w-full" />
      </IconField>
      <div class="flex items-center gap-2">
        <ToggleSwitch v-model="showInactive" input-id="suppliers-show-inactive" />
        <label for="suppliers-show-inactive" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.suppliers.showInactive') }}
        </label>
      </div>
    </div>

    <DataTable
      :value="filteredSuppliers"
      :loading="loading"
      sort-field="name"
      :sort-order="1"
      :paginator="filteredSuppliers.length > 20"
      :rows="20"
    >
      <template #empty>
        <span class="text-surface-500 dark:text-surface-400">{{ t('inventory.suppliers.empty') }}</span>
      </template>

      <Column field="name" :header="t('inventory.suppliers.name')" sortable />
      <Column field="contact_name" :header="t('inventory.suppliers.contactName')" />
      <Column field="phone" :header="t('inventory.suppliers.phone')">
        <template #body="{ data }"
          ><span dir="ltr">{{ data.phone ?? '—' }}</span></template
        >
      </Column>
      <Column field="email" :header="t('inventory.suppliers.email')">
        <template #body="{ data }"
          ><span dir="ltr">{{ data.email ?? '—' }}</span></template
        >
      </Column>
      <Column field="is_active" :header="t('inventory.suppliers.status')" sortable>
        <template #body="{ data }">
          <Tag
            :value="data.is_active ? t('inventory.suppliers.active') : t('inventory.suppliers.inactive')"
            :severity="data.is_active ? 'success' : 'secondary'"
          />
        </template>
      </Column>
      <Column :header="t('inventory.suppliers.actions')" style="width: 8rem">
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

    <SupplierFormDialog v-model:visible="dialogVisible" :supplier="editingSupplier ?? undefined" />
  </div>
</template>
