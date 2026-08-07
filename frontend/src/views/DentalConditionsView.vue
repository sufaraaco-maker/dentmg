<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
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
import DentalConditionFormDialog from '@/components/dental-chart/DentalConditionFormDialog.vue'
import { useDentalConditionsStore } from '@/stores/dentalConditions'
import { getContrastTextColor } from '@/lib/color'
import type { DentalCondition } from '@/types/dentalChart'

/**
 * Admin-only catalog CRUD screen (implementation plan §2.1). Client-side search/sort/filter over
 * the already-cached, unpaginated `dentalConditions.ts` list — mirrors `AppointmentTypesView.vue`
 * exactly, same reasoning: a small, rarely-changing clinic-configuration table.
 */
const { t } = useI18n()
const confirm = useConfirm()
const toast = useToast()
const store = useDentalConditionsStore()

const search = ref('')
const showInactive = ref(true)
const dialogVisible = ref(false)
const editingCondition = ref<DentalCondition | null>(null)

const loading = computed(() => store.loading && !store.loaded)

const filteredConditions = computed(() => {
  const query = search.value.trim().toLowerCase()

  return store.items.filter((condition) => {
    if (!showInactive.value && !condition.is_active) return false
    if (query && !condition.name.toLowerCase().includes(query)) return false
    return true
  })
})

function colorStyle(color: string) {
  return { backgroundColor: color, color: getContrastTextColor(color) }
}

function openCreateDialog() {
  editingCondition.value = null
  dialogVisible.value = true
}

function openEditDialog(condition: DentalCondition) {
  editingCondition.value = condition
  dialogVisible.value = true
}

function confirmDelete(condition: DentalCondition) {
  confirm.require({
    message: t('dentalChart.conditions.deleteConfirmMessage', { name: condition.name }),
    header: t('dentalChart.conditions.deleteConfirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await store.remove(condition.id)
        toast.add({ severity: 'success', summary: t('dentalChart.conditions.deleted'), life: 3000 })
      } catch {
        toast.add({ severity: 'error', summary: t('dentalChart.conditions.deleteError'), life: 3000 })
      }
    },
  })
}

onMounted(() => store.fetchAll())

// `fetchAll()` now catches and stores the error itself (docs/PROJECT_STATUS.md Phase 1 audit —
// standardizing on the store-owns-error-state pattern already used by appointments.ts/etc.) rather
// than rejecting, so this watches the store's own error state instead of a local try/catch.
watch(
  () => store.error,
  (error) => {
    if (error) toast.add({ severity: 'error', summary: t(error), life: 3000 })
  },
)
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('dentalChart.conditions.title') }}
      </h1>
      <Button :label="t('dentalChart.conditions.new')" icon="pi pi-plus" @click="openCreateDialog" />
    </div>

    <div class="flex flex-wrap items-center gap-4">
      <IconField class="max-w-sm">
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" :placeholder="t('dentalChart.conditions.search')" class="w-full" />
      </IconField>
      <div class="flex items-center gap-2">
        <ToggleSwitch v-model="showInactive" input-id="show-inactive" />
        <label for="show-inactive" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('dentalChart.conditions.showInactive') }}
        </label>
      </div>
    </div>

    <DataTable
      :value="filteredConditions"
      :loading="loading"
      sort-field="sort_order"
      :sort-order="1"
      :paginator="filteredConditions.length > 20"
      :rows="20"
    >
      <template #empty>
        <span class="text-surface-500 dark:text-surface-400">{{ t('dentalChart.conditions.empty') }}</span>
      </template>

      <Column field="name" :header="t('dentalChart.conditions.name')" sortable />
      <Column field="category" :header="t('dentalChart.conditions.category')" sortable>
        <template #body="{ data }">
          {{ t(`dentalChart.category.${data.category}`) }}
        </template>
      </Column>
      <Column field="applies_to_surface" :header="t('dentalChart.conditions.appliesToSurface')">
        <template #body="{ data }">
          <Tag
            :value="data.applies_to_surface ? t('common.yes') : t('common.no')"
            :severity="data.applies_to_surface ? 'info' : 'secondary'"
          />
        </template>
      </Column>
      <Column :header="t('dentalChart.conditions.color')">
        <template #body="{ data }">
          <Tag :style="colorStyle(data.default_color)" class="font-mono text-xs">
            <!-- The hex code is never Arabic text — isolating its direction keeps '#' from being
            bidi-reordered to the end inside an RTL row, same convention as AppointmentTypesView. -->
            <span dir="ltr">{{ data.default_color }}</span>
          </Tag>
        </template>
      </Column>
      <Column field="is_active" :header="t('dentalChart.conditions.status')" sortable>
        <template #body="{ data }">
          <Tag
            :value="
              data.is_active ? t('dentalChart.conditions.active') : t('dentalChart.conditions.inactive')
            "
            :severity="data.is_active ? 'success' : 'secondary'"
          />
        </template>
      </Column>
      <Column :header="t('dentalChart.conditions.actions')" style="width: 8rem">
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
              icon="pi pi-trash"
              text
              rounded
              severity="danger"
              :aria-label="t('common.delete')"
              @click="confirmDelete(data)"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <DentalConditionFormDialog v-model:visible="dialogVisible" :condition="editingCondition ?? undefined" />
  </div>
</template>
