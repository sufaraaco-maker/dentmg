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
import LabFormDialog from '@/components/laboratory/LabFormDialog.vue'
import { useLabsStore } from '@/stores/labs'
import type { Lab } from '@/types/laboratory'

/**
 * Admin-only CRUD screen (design doc §5/§6/§7 decision 8) — mirrors SuppliersView.vue exactly:
 * client-side search/filter over the already-cached, unpaginated labs list.
 */
const { t } = useI18n()
const confirm = useConfirm()
const toast = useToast()
const store = useLabsStore()

const search = ref('')
const showInactive = ref(true)
const dialogVisible = ref(false)
const editingLab = ref<Lab | null>(null)

const loading = computed(() => store.loading && !store.loaded)

const filteredLabs = computed(() => {
  const query = search.value.trim().toLowerCase()

  return store.items.filter((lab) => {
    if (!showInactive.value && !lab.is_active) return false
    if (query && !lab.name.toLowerCase().includes(query)) return false
    return true
  })
})

function openCreateDialog() {
  editingLab.value = null
  dialogVisible.value = true
}

function openEditDialog(lab: Lab) {
  editingLab.value = lab
  dialogVisible.value = true
}

function confirmDeactivate(lab: Lab) {
  confirm.require({
    message: t('laboratory.labs.deactivateConfirmMessage', { name: lab.name }),
    header: t('laboratory.labs.deactivateConfirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await store.deactivate(lab.id)
        toast.add({ severity: 'success', summary: t('laboratory.labs.deactivated'), life: 3000 })
      } catch {
        toast.add({ severity: 'error', summary: t('laboratory.labs.deactivateError'), life: 3000 })
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
        {{ t('laboratory.labs.title') }}
      </h1>
      <Button :label="t('laboratory.labs.new')" icon="pi pi-plus" @click="openCreateDialog" />
    </div>

    <div class="flex flex-wrap items-center gap-4">
      <IconField class="max-w-sm">
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" :placeholder="t('laboratory.labs.search')" class="w-full" />
      </IconField>
      <div class="flex items-center gap-2">
        <ToggleSwitch v-model="showInactive" input-id="labs-show-inactive" />
        <label for="labs-show-inactive" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('laboratory.labs.showInactive') }}
        </label>
      </div>
    </div>

    <DataTable
      :value="filteredLabs"
      :loading="loading"
      sort-field="name"
      :sort-order="1"
      :paginator="filteredLabs.length > 20"
      :rows="20"
    >
      <template #empty>
        <span class="text-surface-500 dark:text-surface-400">{{ t('laboratory.labs.empty') }}</span>
      </template>

      <Column field="name" :header="t('laboratory.labs.name')" sortable />
      <Column field="contact_name" :header="t('laboratory.labs.contactName')" />
      <Column field="phone" :header="t('laboratory.labs.phone')">
        <template #body="{ data }"
          ><span dir="ltr">{{ data.phone ?? '—' }}</span></template
        >
      </Column>
      <Column field="default_turnaround_days" :header="t('laboratory.labs.defaultTurnaroundDays')">
        <template #body="{ data }">{{ data.default_turnaround_days ?? '—' }}</template>
      </Column>
      <Column field="is_active" :header="t('laboratory.labs.status')" sortable>
        <template #body="{ data }">
          <Tag
            :value="data.is_active ? t('laboratory.labs.active') : t('laboratory.labs.inactive')"
            :severity="data.is_active ? 'success' : 'secondary'"
          />
        </template>
      </Column>
      <Column :header="t('laboratory.labs.actions')" style="width: 8rem">
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

    <LabFormDialog v-model:visible="dialogVisible" :lab="editingLab ?? undefined" />
  </div>
</template>
