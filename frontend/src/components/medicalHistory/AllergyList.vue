<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import { Pencil, Trash2 } from 'lucide-vue-next'
import type { AllergySeverity, PatientAllergy } from '@/types/medicalHistory'

/**
 * Allergies section list — pure/presentational, mirroring `InvoiceListTable.vue`'s convention:
 * fetches nothing itself, just renders what the panel hands it and emits back up.
 */
defineProps<{
  allergies: PatientAllergy[]
  loading: boolean
  canWrite: boolean
}>()

const emit = defineEmits<{ edit: [allergy: PatientAllergy]; delete: [allergy: PatientAllergy] }>()

const { t } = useI18n()

const SEVERITY_SEVERITY: Record<AllergySeverity, 'info' | 'warn' | 'danger'> = {
  mild: 'info',
  moderate: 'warn',
  severe: 'danger',
}
</script>

<template>
  <DataTable :value="allergies" :loading="loading" data-key="id" size="small">
    <Column field="allergen" :header="t('medicalHistory.allergies.allergen')" />
    <Column :header="t('medicalHistory.allergies.severity')">
      <template #body="{ data }">
        <Tag
          v-if="data.severity"
          :value="t(`medicalHistory.allergies.severities.${data.severity}`)"
          :severity="SEVERITY_SEVERITY[data.severity as AllergySeverity]"
        />
        <span v-else class="text-surface-400">—</span>
      </template>
    </Column>
    <Column field="reaction" :header="t('medicalHistory.allergies.reaction')">
      <template #body="{ data }">{{ data.reaction ?? '—' }}</template>
    </Column>
    <Column v-if="canWrite" :header="t('medicalHistory.list.actions')" style="width: 6rem">
      <template #body="{ data }">
        <div class="flex gap-1">
          <Button text rounded size="small" @click="emit('edit', data)">
            <template #icon="{ class: iconClass }"><Pencil :size="16" :class="iconClass" /></template>
          </Button>
          <Button text rounded size="small" severity="danger" @click="emit('delete', data)">
            <template #icon="{ class: iconClass }"><Trash2 :size="16" :class="iconClass" /></template>
          </Button>
        </div>
      </template>
    </Column>
  </DataTable>
</template>
