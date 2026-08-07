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
import AppointmentTypeFormDialog from '@/components/appointments/AppointmentTypeFormDialog.vue'
import { useAppointmentTypesStore } from '@/stores/appointmentTypes'
import { getContrastTextColor } from '@/lib/color'
import type { AppointmentType } from '@/types/appointment'

/**
 * Admin-only CRUD screen (design doc §7). Client-side search/sort/filter over the already-cached,
 * unpaginated `appointmentTypes.ts` list — no server round-trip, since this is realistically a
 * 5-20 row clinic-configuration table, same reasoning as `AppointmentTypeSelect`'s dropdown source.
 */
const { t } = useI18n()
const confirm = useConfirm()
const toast = useToast()
const store = useAppointmentTypesStore()

const search = ref('')
const showInactive = ref(true)
const dialogVisible = ref(false)
const editingType = ref<AppointmentType | null>(null)

const loading = computed(() => store.loading && !store.loaded)

const filteredTypes = computed(() => {
  const query = search.value.trim().toLowerCase()

  return store.items.filter((type) => {
    if (!showInactive.value && !type.is_active) return false
    if (query && !type.name.toLowerCase().includes(query)) return false
    return true
  })
})

function colorStyle(color: string) {
  return { backgroundColor: color, color: getContrastTextColor(color) }
}

function openCreateDialog() {
  editingType.value = null
  dialogVisible.value = true
}

function openEditDialog(type: AppointmentType) {
  editingType.value = type
  dialogVisible.value = true
}

function confirmDelete(type: AppointmentType) {
  confirm.require({
    message: t('appointments.types.deleteConfirmMessage', { name: type.name }),
    header: t('appointments.types.deleteConfirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await store.remove(type.id)
        toast.add({ severity: 'success', summary: t('appointments.types.deleted'), life: 3000 })
      } catch {
        toast.add({ severity: 'error', summary: t('appointments.types.deleteError'), life: 3000 })
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
        {{ t('appointments.types.title') }}
      </h1>
      <Button :label="t('appointments.types.new')" icon="pi pi-plus" @click="openCreateDialog" />
    </div>

    <div class="flex flex-wrap items-center gap-4">
      <IconField class="max-w-sm">
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" :placeholder="t('appointments.types.search')" class="w-full" />
      </IconField>
      <div class="flex items-center gap-2">
        <ToggleSwitch v-model="showInactive" input-id="show-inactive" />
        <label for="show-inactive" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('appointments.types.showInactive') }}
        </label>
      </div>
    </div>

    <DataTable
      :value="filteredTypes"
      :loading="loading"
      sort-field="name"
      :sort-order="1"
      :paginator="filteredTypes.length > 20"
      :rows="20"
    >
      <template #empty>
        <span class="text-surface-500 dark:text-surface-400">{{ t('appointments.types.empty') }}</span>
      </template>

      <Column field="name" :header="t('appointments.types.name')" sortable />
      <Column field="default_duration_minutes" :header="t('appointments.types.duration')" sortable>
        <template #body="{ data }">
          {{ t('appointments.types.durationMinutes', { n: data.default_duration_minutes }) }}
        </template>
      </Column>
      <Column :header="t('appointments.types.color')">
        <template #body="{ data }">
          <Tag :style="colorStyle(data.color)" class="font-mono text-xs">
            <!-- The hex code is never Arabic text — isolating its direction keeps '#' from being
            bidi-reordered to the end (e.g. 'F97316#') inside an RTL row. -->
            <span dir="ltr">{{ data.color }}</span>
          </Tag>
        </template>
      </Column>
      <Column field="is_active" :header="t('appointments.types.status')" sortable>
        <template #body="{ data }">
          <Tag
            :value="data.is_active ? t('appointments.types.active') : t('appointments.types.inactive')"
            :severity="data.is_active ? 'success' : 'secondary'"
          />
        </template>
      </Column>
      <Column :header="t('appointments.types.actions')" style="width: 8rem">
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

    <AppointmentTypeFormDialog v-model:visible="dialogVisible" :appointment-type="editingType ?? undefined" />
  </div>
</template>
