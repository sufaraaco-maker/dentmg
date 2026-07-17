<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'
import Card from 'primevue/card'
import { useAppointmentsStore } from '@/stores/appointments'
import { useAuthStore } from '@/stores/auth'
import AppointmentCard from '@/components/appointments/AppointmentCard.vue'
import AppointmentTimeline from '@/components/appointments/AppointmentTimeline.vue'
import AppointmentActionsBar from '@/components/appointments/AppointmentActionsBar.vue'
import PatientSummaryCard from '@/components/appointments/PatientSummaryCard.vue'
import FutureFeaturePlaceholder from '@/components/appointments/FutureFeaturePlaceholder.vue'
import AppointmentDialog from '@/components/appointments/AppointmentDialog.vue'
import type { Appointment, AppointmentStatus } from '@/types/appointment'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const appointmentsStore = useAppointmentsStore()
const auth = useAuthStore()

const TERMINAL_STATUSES: AppointmentStatus[] = ['completed', 'cancelled', 'no_show']

const appointmentId = computed(() => route.params.id as string)
const appointment = ref<Appointment | null>(null)
const loading = ref(true)
const notFound = ref(false)
const dialogVisible = ref(false)

const canEdit = computed(() => auth.canManageAppointments)
const isTerminal = computed(() =>
  appointment.value ? TERMINAL_STATUSES.includes(appointment.value.status) : false,
)

async function fetchAppointment() {
  loading.value = true
  notFound.value = false

  try {
    appointment.value = await appointmentsStore.fetchOne(appointmentId.value)
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }
}

function onSaved(updated: Appointment) {
  appointment.value = updated
}

function onActionCompleted(updated: Appointment) {
  appointment.value = updated
}

function goToPatient() {
  if (!appointment.value) return
  router.push({ name: 'patient-detail', params: { id: appointment.value.patient_id } })
}

onMounted(fetchAppointment)
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center gap-3">
      <Button icon="pi pi-arrow-left" text rounded @click="router.push({ name: 'appointments' })" />
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('appointments.title') }}
      </h1>
    </div>

    <Skeleton v-if="loading" height="20rem" />

    <Card v-else-if="notFound">
      <template #content>
        <p class="text-surface-600 dark:text-surface-300">{{ t('appointments.notFound') }}</p>
      </template>
    </Card>

    <template v-else-if="appointment">
      <AppointmentCard :appointment="appointment">
        <template #actions>
          <Button
            v-if="canEdit"
            :label="t('common.edit')"
            icon="pi pi-pencil"
            size="small"
            @click="dialogVisible = true"
          />
        </template>
      </AppointmentCard>
      <p v-if="canEdit && isTerminal" class="-mt-2 text-sm text-surface-500 dark:text-surface-400">
        {{ t('appointments.detail.editDisabledNote') }}
      </p>

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card>
          <template #title>{{ t('appointments.detail.timeline.title') }}</template>
          <template #content>
            <AppointmentTimeline :appointment="appointment" />
          </template>
        </Card>

        <div class="flex flex-col gap-4">
          <Card v-if="appointment.patient">
            <template #title>{{ t('appointments.detail.patientSection') }}</template>
            <template #content>
              <div class="flex flex-col gap-3">
                <PatientSummaryCard :patient="appointment.patient" />
                <Button
                  :label="t('appointments.detail.viewFullRecord')"
                  icon="pi pi-arrow-right"
                  text
                  size="small"
                  class="self-start"
                  @click="goToPatient"
                />
              </div>
            </template>
          </Card>

          <Card>
            <template #title>{{ t('appointments.detail.actionsTitle') }}</template>
            <template #content>
              <AppointmentActionsBar :appointment="appointment" @action-completed="onActionCompleted" />
            </template>
          </Card>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <FutureFeaturePlaceholder
          v-if="auth.isAdmin"
          icon="pi pi-history"
          title-key="appointments.detail.placeholders.auditHistory"
        />
        <FutureFeaturePlaceholder
          icon="pi pi-file-edit"
          title-key="appointments.detail.placeholders.treatmentPlan"
        />
        <FutureFeaturePlaceholder
          icon="pi pi-receipt"
          title-key="appointments.detail.placeholders.invoices"
        />
        <FutureFeaturePlaceholder
          icon="pi pi-book"
          title-key="appointments.detail.placeholders.clinicalNotes"
        />
        <FutureFeaturePlaceholder
          icon="pi pi-paperclip"
          title-key="appointments.detail.placeholders.attachments"
        />
      </div>
    </template>

    <AppointmentDialog
      v-if="appointment"
      v-model:visible="dialogVisible"
      :appointment="appointment"
      @saved="onSaved"
    />
  </div>
</template>
