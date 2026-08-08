<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import { Pencil, Trash2 } from 'lucide-vue-next'
import { parseLocalDate } from '@/lib/date'
import type { MedicalConditionStatus, PatientMedicalCondition } from '@/types/medicalHistory'

defineProps<{
  conditions: PatientMedicalCondition[]
  loading: boolean
  canWrite: boolean
}>()

const emit = defineEmits<{
  edit: [condition: PatientMedicalCondition]
  delete: [condition: PatientMedicalCondition]
}>()

const { t, locale } = useI18n()

const STATUS_SEVERITY: Record<MedicalConditionStatus, 'warn' | 'success' | 'info'> = {
  active: 'warn',
  resolved: 'success',
  chronic: 'info',
}

function formatDate(value: string) {
  return parseLocalDate(value).toLocaleDateString(locale.value)
}
</script>

<template>
  <DataTable :value="conditions" :loading="loading" data-key="id" size="small">
    <Column field="condition_name" :header="t('medicalHistory.conditions.conditionName')" />
    <Column :header="t('medicalHistory.conditions.status')">
      <template #body="{ data }">
        <Tag
          :value="t(`medicalHistory.conditions.statuses.${data.status}`)"
          :severity="STATUS_SEVERITY[data.status as MedicalConditionStatus]"
        />
      </template>
    </Column>
    <Column :header="t('medicalHistory.conditions.diagnosedDate')">
      <template #body="{ data }">{{ data.diagnosed_date ? formatDate(data.diagnosed_date) : '—' }}</template>
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
