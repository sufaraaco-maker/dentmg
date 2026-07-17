<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from 'primevue/card'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
import DentistSelect from '@/components/appointments/DentistSelect.vue'
import WorkingHoursEditor from '@/components/appointments/WorkingHoursEditor.vue'
import TimeOffCalendar from '@/components/appointments/TimeOffCalendar.vue'
import TimeOffFormDialog from '@/components/appointments/TimeOffFormDialog.vue'
import { useAuthStore } from '@/stores/auth'
import { useProvidersStore } from '@/stores/providers'

/**
 * Working Hours + Time Off management (design doc §5, §6, §20 Step 6). Admin can view/manage any
 * dentist's schedule; a dentist-role user only ever sees their own (no dentist selector shown to
 * them, per §1.3/§14 of the backend design — this is a page-level UX convenience only, the real
 * boundary is `DentistWorkingHourPolicy`/`DentistTimeOffPolicy` on the API).
 */
const { t } = useI18n()
const auth = useAuthStore()
const providers = useProvidersStore()

const selectedDentistId = ref<string | null>(auth.isDentist ? (auth.user?.id ?? null) : null)
const timeOffDialogVisible = ref(false)

onMounted(() => {
  if (auth.isAdmin) providers.fetchAll()
})

// Minimal-clicks default: land the admin straight on a schedule instead of an empty prompt,
// once the dentist list has loaded (never overrides a selection the admin already made).
watch(
  () => providers.items,
  (items) => {
    if (auth.isAdmin && !selectedDentistId.value && items.length) {
      selectedDentistId.value = items[0].id
    }
  },
)
</script>

<template>
  <div class="flex flex-col gap-4">
    <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
      {{ t('appointments.schedule.title') }}
    </h1>

    <DentistSelect v-if="auth.isAdmin" v-model="selectedDentistId" class="max-w-xs" />

    <Card v-if="!selectedDentistId">
      <template #content>
        <p class="text-surface-600 dark:text-surface-300">
          {{ t('appointments.schedule.selectDentistPrompt') }}
        </p>
      </template>
    </Card>

    <template v-else>
      <Card>
        <template #content>
          <Tabs value="workingHours">
            <TabList>
              <Tab value="workingHours">{{ t('appointments.schedule.tabs.workingHours') }}</Tab>
              <Tab value="timeOff">{{ t('appointments.schedule.tabs.timeOff') }}</Tab>
            </TabList>
            <TabPanels>
              <TabPanel value="workingHours">
                <div class="pt-2">
                  <WorkingHoursEditor :key="selectedDentistId" :dentist-id="selectedDentistId" />
                </div>
              </TabPanel>
              <TabPanel value="timeOff">
                <div class="pt-2">
                  <TimeOffCalendar
                    :key="selectedDentistId"
                    :dentist-id="selectedDentistId"
                    @add-clicked="timeOffDialogVisible = true"
                  />
                </div>
              </TabPanel>
            </TabPanels>
          </Tabs>
        </template>
      </Card>

      <TimeOffFormDialog v-model:visible="timeOffDialogVisible" :dentist-id="selectedDentistId" />
    </template>
  </div>
</template>
