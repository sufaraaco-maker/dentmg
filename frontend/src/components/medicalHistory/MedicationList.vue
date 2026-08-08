<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import { Pencil, Trash2 } from 'lucide-vue-next'
import type { PatientMedication } from '@/types/medicalHistory'

defineProps<{
  medications: PatientMedication[]
  loading: boolean
  canWrite: boolean
}>()

const emit = defineEmits<{ edit: [medication: PatientMedication]; delete: [medication: PatientMedication] }>()

const { t } = useI18n()
</script>

<template>
  <DataTable :value="medications" :loading="loading" data-key="id" size="small">
    <Column field="medication_name" :header="t('medicalHistory.medications.medicationName')" />
    <Column :header="t('medicalHistory.medications.dosage')">
      <template #body="{ data }">{{ data.dosage ?? '—' }}</template>
    </Column>
    <Column :header="t('medicalHistory.medications.frequency')">
      <template #body="{ data }">{{ data.frequency ?? '—' }}</template>
    </Column>
    <Column :header="t('medicalHistory.medications.status')">
      <template #body="{ data }">
        <Tag
          :value="
            data.is_current
              ? t('medicalHistory.medications.current')
              : t('medicalHistory.medications.discontinued')
          "
          :severity="data.is_current ? 'success' : 'secondary'"
        />
      </template>
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
