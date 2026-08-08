<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Paginator from 'primevue/paginator'
import Skeleton from 'primevue/skeleton'
import { FileStack, Upload } from 'lucide-vue-next'
import AttachmentList from './AttachmentList.vue'
import AttachmentUpload from './AttachmentUpload.vue'
import EditDocumentDialog from './EditDocumentDialog.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import { usePatientDocumentsStore } from '@/stores/patientDocuments'
import { useAuthStore } from '@/stores/auth'
import type { DocumentCategory, PatientDocument } from '@/types/documents'

/**
 * Patient Detail's Documents tab (Phase 2.5, patient-documents-redesign-design.md §4.5/§8) — last
 * tab, after Billing. Structurally mirrors `PatientLabCasesPanel.vue` (list + filter +
 * `EmptyState.vue`/`Skeleton` + `Paginator` + inline upload dialog), not `PatientImagingPanel.vue`'s
 * grid, per `AttachmentList.vue`'s own row-list rationale.
 */
const props = defineProps<{ patientId: string }>()

const { t } = useI18n()
const confirm = useConfirm()
const toast = useToast()
const auth = useAuthStore()
const documentsStore = usePatientDocumentsStore()

// Matches PatientDocumentPolicy exactly (design doc §9/§16 decision 2).
const canWrite = computed(() => auth.isAdmin || auth.isDentist || auth.isReceptionist)
const canDelete = computed(() => auth.isAdmin)

const CATEGORIES: DocumentCategory[] = [
  'consent_form',
  'insurance',
  'referral',
  'clinical_summary',
  'correspondence',
  'other',
]

const categoryFilter = ref<DocumentCategory | null>(null)
const page = ref(1)
const uploadDialogVisible = ref(false)
const editingDocument = ref<PatientDocument | null>(null)

const categoryFilterOptions = computed(() => [
  { label: t('documents.allCategories'), value: null },
  ...CATEGORIES.map((value) => ({ label: t(`documents.categories.${value}`), value })),
])

const documents = computed(() => documentsStore.documentsForPatient(props.patientId))
const pageMeta = computed(() => documentsStore.pageMetaForPatient(props.patientId))
const loading = computed(() => documentsStore.loading)

watch(
  () => documentsStore.error,
  (error) => {
    if (error) toast.add({ severity: 'error', summary: t(error), life: 3000 })
  },
)

function fetchDocuments(force = false) {
  documentsStore.fetchForPatient(props.patientId, page.value, categoryFilter.value, force)
}

watch(
  () => props.patientId,
  () => {
    page.value = 1
    fetchDocuments(true)
  },
  { immediate: true },
)

watch(categoryFilter, () => {
  page.value = 1
  fetchDocuments(true)
})

function onPage(event: { page: number }) {
  page.value = event.page + 1
  fetchDocuments()
}

function onUploaded() {
  // AttachmentUpload.vue already emits update:visible=false on its own successful submit — set it
  // explicitly here too, matching PatientLabCasesPanel.vue's onCreated() precedent.
  page.value = 1
  uploadDialogVisible.value = false
  toast.add({ severity: 'success', summary: t('documents.uploaded'), life: 3000 })
}

function onUpdated() {
  toast.add({ severity: 'success', summary: t('documents.updated'), life: 3000 })
}

function askDelete(document: PatientDocument) {
  confirm.require({
    message: t('documents.deleteConfirmMessage'),
    header: t('documents.deleteConfirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: t('common.delete'),
    rejectLabel: t('common.cancel'),
    accept: async () => {
      try {
        await documentsStore.remove(document.id)
        toast.add({ severity: 'success', summary: t('documents.deleted'), life: 3000 })
      } catch {
        toast.add({ severity: 'error', summary: t('documents.deleteError'), life: 3000 })
      }
    },
  })
}
</script>

<template>
  <Card v-bind="$attrs">
    <template #title>
      <div class="flex flex-wrap items-center justify-between gap-2">
        <span>{{ t('patients.documentsPanel.title') }}</span>
        <Button
          v-if="canWrite"
          :label="t('patients.documentsPanel.upload')"
          size="small"
          @click="uploadDialogVisible = true"
        >
          <template #icon="{ class: iconClass }">
            <Upload :size="16" :class="iconClass" />
          </template>
        </Button>
      </div>
    </template>
    <template #content>
      <div class="mb-4 flex flex-wrap items-end gap-3">
        <div class="flex min-w-40 flex-col gap-1">
          <label for="documents-filter-category" class="text-xs text-surface-500">{{
            t('documents.category')
          }}</label>
          <Select
            v-model="categoryFilter"
            input-id="documents-filter-category"
            :options="categoryFilterOptions"
            option-label="label"
            option-value="value"
          />
        </div>
      </div>

      <div v-if="loading && !documents.length" class="flex flex-col gap-2">
        <Skeleton v-for="n in 3" :key="n" class="h-16 w-full" />
      </div>

      <EmptyState
        v-else-if="!documents.length"
        :icon="FileStack"
        :title="t('patients.documentsPanel.empty')"
      />

      <div v-else class="flex flex-col gap-2">
        <AttachmentList
          v-for="document in documents"
          :key="document.id"
          :document="document"
          :can-edit="canWrite"
          :can-delete="canDelete"
          @edit="editingDocument = document"
          @delete="askDelete(document)"
        />
      </div>

      <Paginator
        v-if="pageMeta.total > pageMeta.perPage"
        class="mt-4"
        :rows="pageMeta.perPage"
        :total-records="pageMeta.total"
        :first="(page - 1) * pageMeta.perPage"
        @page="onPage"
      />
    </template>
  </Card>

  <AttachmentUpload v-model:visible="uploadDialogVisible" :patient-id="patientId" @uploaded="onUploaded" />

  <EditDocumentDialog
    :visible="!!editingDocument"
    :document="editingDocument"
    @update:visible="(v) => !v && (editingDocument = null)"
    @updated="onUpdated"
  />
</template>
