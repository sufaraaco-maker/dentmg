<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'
import Card from 'primevue/card'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import LabCaseStatusChip from '@/components/laboratory/LabCaseStatusChip.vue'
import LabCaseActionsBar from '@/components/laboratory/LabCaseActionsBar.vue'
import { parseServerDateTime } from '@/lib/date'
import { toothDisplayName } from '@/lib/teeth'
import type { LabCase } from '@/types/laboratory'

/**
 * Lab Case Detail (design doc §6) — overview card, status-transition actions bar, and a
 * browser-printable slip (design doc §7 decision 2 — `@media print` CSS only, no PDF library),
 * mirroring `PurchaseOrderDetailView.vue`'s overview-card + actions-bar shape. No items table:
 * unlike a Purchase Order, a Lab Case is a single prescription record (design doc §3).
 */
const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const auth = useAuthStore()

const caseId = computed(() => route.params.id as string)

const labCase = ref<LabCase | null>(null)
const loading = ref(false)
const notFound = ref(false)

const canDelete = computed(() => auth.isAdmin)
const isDraft = computed(() => labCase.value?.status === 'draft')

async function load() {
  notFound.value = false
  loading.value = true
  try {
    const { data } = await api.get<LabCase>(`/lab-cases/${caseId.value}`)
    labCase.value = data
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }
}

watch(caseId, load, { immediate: true })

function formatDateTime(value: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(
    parseServerDateTime(value),
  )
}

function goToList() {
  router.push({ name: 'lab-cases' })
}

function onUpdated(updated: LabCase) {
  labCase.value = updated
}

function printSlip() {
  window.print()
}

function confirmDelete() {
  if (!labCase.value) return

  confirm.require({
    message: t('laboratory.labCases.confirmDeleteMessage'),
    header: t('laboratory.labCases.confirmDeleteHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await api.delete(`/lab-cases/${labCase.value!.id}`)
        toast.add({ severity: 'success', summary: t('laboratory.labCases.deleted'), life: 3000 })
        goToList()
      } catch {
        toast.add({ severity: 'error', summary: t('laboratory.labCases.deleteError'), life: 3000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" text rounded @click="goToList" />
        <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0" dir="ltr">
          {{ labCase?.case_number ?? t('laboratory.labCases.title') }}
        </h1>
        <LabCaseStatusChip v-if="labCase" :status="labCase.status" />
      </div>
      <div v-if="labCase" class="flex gap-2">
        <Button
          :label="t('laboratory.labCases.print')"
          icon="pi pi-print"
          outlined
          size="small"
          @click="printSlip"
        />
        <Button
          v-if="canDelete && isDraft"
          :label="t('common.delete')"
          icon="pi pi-trash"
          severity="danger"
          outlined
          size="small"
          @click="confirmDelete"
        />
      </div>
    </div>

    <LabCaseActionsBar v-if="labCase" :lab-case="labCase" @updated="onUpdated" />

    <Skeleton v-if="loading" height="20rem" />

    <Card v-else-if="notFound">
      <template #content>
        <p class="text-surface-600 dark:text-surface-300">{{ t('laboratory.labCases.notFound') }}</p>
      </template>
    </Card>

    <Card v-else-if="labCase">
      <template #title>
        <div class="flex items-center justify-between gap-2 print:justify-start">
          <span>{{ t('laboratory.labCases.overview') }}</span>
          <span class="hidden text-lg font-normal print:inline" dir="ltr">{{ labCase.case_number }}</span>
        </div>
      </template>
      <template #content>
        <dl class="grid grid-cols-2 gap-y-3 text-sm md:grid-cols-4">
          <dt class="text-surface-500">{{ t('laboratory.labCases.patient') }}</dt>
          <dd>{{ labCase.patient?.full_name ?? '—' }}</dd>

          <dt class="text-surface-500">{{ t('laboratory.labCases.lab') }}</dt>
          <dd>{{ labCase.lab?.name ?? '—' }}</dd>

          <dt class="text-surface-500">{{ t('laboratory.labCases.dentist') }}</dt>
          <dd>{{ labCase.dentist?.name ?? '—' }}</dd>

          <dt class="text-surface-500">{{ t('laboratory.labCases.caseType') }}</dt>
          <dd>{{ labCase.case_type ?? '—' }}</dd>

          <dt class="text-surface-500">{{ t('laboratory.labCases.toothNumbers') }}</dt>
          <dd>
            <span v-if="labCase.tooth_numbers?.length" dir="ltr">
              {{ labCase.tooth_numbers.map((code) => `${code} (${toothDisplayName(code)})`).join(', ') }}
            </span>
            <span v-else>—</span>
          </dd>

          <dt class="text-surface-500">{{ t('laboratory.labCases.shade') }}</dt>
          <dd dir="ltr" class="text-start">{{ labCase.shade ?? '—' }}</dd>

          <dt class="text-surface-500">{{ t('laboratory.labCases.material') }}</dt>
          <dd>{{ labCase.material ?? '—' }}</dd>

          <dt class="text-surface-500">{{ t('laboratory.labCases.fee') }}</dt>
          <dd dir="ltr" class="text-start">{{ labCase.fee ?? '—' }}</dd>

          <dt class="text-surface-500">{{ t('laboratory.labCases.trackingNumber') }}</dt>
          <dd dir="ltr" class="text-start">{{ labCase.tracking_number ?? '—' }}</dd>

          <dt class="text-surface-500">{{ t('laboratory.labCases.sentAt') }}</dt>
          <dd dir="ltr" class="text-start">{{ labCase.sent_at ? formatDateTime(labCase.sent_at) : '—' }}</dd>

          <dt class="text-surface-500">{{ t('laboratory.labCases.dueAt') }}</dt>
          <dd dir="ltr" class="text-start">{{ labCase.due_at ? formatDateTime(labCase.due_at) : '—' }}</dd>

          <dt class="text-surface-500">{{ t('laboratory.labCases.receivedAt') }}</dt>
          <dd dir="ltr" class="text-start">
            {{ labCase.received_at ? formatDateTime(labCase.received_at) : '—' }}
          </dd>
        </dl>
        <p v-if="labCase.instructions" class="mt-3 text-sm text-surface-600 dark:text-surface-300">
          <span class="font-medium">{{ t('laboratory.labCases.instructions') }}:</span>
          {{ labCase.instructions }}
        </p>
      </template>
    </Card>
  </div>
</template>
