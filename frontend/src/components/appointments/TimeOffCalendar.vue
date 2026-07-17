<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Card from 'primevue/card'
import { useTimeOffStore } from '@/stores/timeOff'
import { useAuthStore } from '@/stores/auth'
import { parseServerDateTime } from '@/lib/date'

/**
 * Chronological time-off list for a dentist (design doc §6, §9) — a plain list rather than a
 * mini calendar widget, since the Board's own time-off overlay (§2.5) already provides the
 * calendar visualization and a handful of entries per dentist per year is more scannable as a
 * list. Owns `timeOff.ts` directly (unlike `WorkingHoursDayRow`, which is deliberately
 * store-free) since deleting an entry has no meaningful "draft" state to hand back to a parent.
 */
const props = defineProps<{ dentistId: string }>()

const emit = defineEmits<{ 'add-clicked': [] }>()

const { t, locale } = useI18n()
const confirm = useConfirm()
const toast = useToast()
const timeOff = useTimeOffStore()
const auth = useAuthStore()

const canManage = computed(() => auth.isAdmin || (auth.isDentist && auth.user?.id === props.dentistId))

const entries = computed(() =>
  [...(timeOff.byDentist.get(props.dentistId) ?? [])].sort(
    (a, b) => parseServerDateTime(a.start_at).getTime() - parseServerDateTime(b.start_at).getTime(),
  ),
)

function formatRange(startIso: string, endIso: string): string {
  const start = parseServerDateTime(startIso)
  const end = parseServerDateTime(endIso)
  const dateTimeFormat: Intl.DateTimeFormatOptions = {
    dateStyle: 'medium',
    timeStyle: 'short',
  }

  return `${start.toLocaleString(locale.value, dateTimeFormat)} – ${end.toLocaleString(locale.value, dateTimeFormat)}`
}

function fetch() {
  if (props.dentistId) timeOff.fetchForDentist(props.dentistId)
}

onMounted(fetch)
watch(() => props.dentistId, fetch)

function remove(id: string) {
  confirm.require({
    message: t('appointments.schedule.timeOff.deleteConfirmMessage'),
    header: t('appointments.schedule.timeOff.deleteConfirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await timeOff.remove(props.dentistId, id)
      } catch {
        toast.add({ severity: 'error', summary: t('appointments.schedule.timeOff.deleteError'), life: 3000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <div class="flex justify-end">
      <Button
        v-if="canManage"
        icon="pi pi-plus"
        :label="t('appointments.schedule.timeOff.addTimeOff')"
        size="small"
        @click="emit('add-clicked')"
      />
    </div>

    <p v-if="!entries.length" class="text-sm text-surface-500 dark:text-surface-400">
      {{ t('appointments.schedule.timeOff.empty') }}
    </p>

    <Card v-for="entry in entries" :key="entry.id">
      <template #content>
        <div class="flex items-center justify-between gap-3">
          <div class="flex flex-col gap-1">
            <span class="font-medium text-surface-900 dark:text-surface-0">
              {{ formatRange(entry.start_at, entry.end_at) }}
            </span>
            <span v-if="entry.reason" class="text-sm text-surface-600 dark:text-surface-300">
              {{ entry.reason }}
            </span>
          </div>
          <Button
            v-if="canManage"
            icon="pi pi-trash"
            text
            rounded
            severity="danger"
            :aria-label="t('common.delete')"
            @click="remove(entry.id)"
          />
        </div>
      </template>
    </Card>
  </div>
</template>
