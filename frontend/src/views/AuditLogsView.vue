<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Message from 'primevue/message'
import { parseLocalDate, parseServerDateTime, toLocalDateString } from '@/lib/date'
import { useAuditLogsStore } from '@/stores/auditLogs'
import { AUDITABLE_TYPES, auditableTypeLabelKey } from '@/config/auditableTypes'
import { AUDIT_LOG_ACTIONS, type AuditLog, type AuditLogAction, type AuditLogFilters } from '@/types/auditLog'

/** Phase 4 Step 4 (design doc §2.6) — the general, non-patient-scoped Audit Log viewer. */
const { t, locale } = useI18n()
const store = useAuditLogsStore()

const userId = ref<string | null>(null)
const auditableType = ref<string | null>(null)
const action = ref<AuditLogAction | null>(null)
const dateFrom = ref<string | null>(null)
const dateTo = ref<string | null>(null)

const dateFromValue = computed({
  get: () => (dateFrom.value ? parseLocalDate(dateFrom.value) : null),
  set: (value: Date | null) => (dateFrom.value = value ? toLocalDateString(value) : null),
})
const dateToValue = computed({
  get: () => (dateTo.value ? parseLocalDate(dateTo.value) : null),
  set: (value: Date | null) => (dateTo.value = value ? toLocalDateString(value) : null),
})

const actionOptions = computed(() =>
  AUDIT_LOG_ACTIONS.map((value) => ({ value, label: t(`auditLog.actions.${value}`) })),
)
const resourceTypeOptions = computed(() =>
  AUDITABLE_TYPES.map((entry) => ({
    value: entry.value,
    label: t(`auditLog.resourceTypes.${entry.labelKey}`),
  })),
)
const userOptions = computed(() => store.filterUsers.map((user) => ({ value: user.id, label: user.name })))

function currentFilters(): AuditLogFilters {
  return {
    user_id: userId.value ?? undefined,
    auditable_type: auditableType.value ?? undefined,
    action: action.value ?? undefined,
    date_from: dateFrom.value ?? undefined,
    date_to: dateTo.value ?? undefined,
  }
}

function applyFilters() {
  store.fetch(currentFilters(), 1)
}

function onPage(event: { page: number }) {
  store.fetch(currentFilters(), event.page + 1)
}

const expandedRows = ref<AuditLog[]>([])

const ACTION_SEVERITY: Record<AuditLogAction, 'success' | 'info' | 'danger' | 'secondary' | 'warn'> = {
  created: 'success',
  updated: 'info',
  deleted: 'danger',
  login_succeeded: 'success',
  login_failed: 'danger',
  logged_out: 'secondary',
  role_permissions_updated: 'warn',
}

function actorLabel(log: AuditLog): string {
  if (log.user) return log.user.name
  const attemptedEmail = log.context?.email
  if (log.action === 'login_failed' && typeof attemptedEmail === 'string') return attemptedEmail
  return t('auditLog.systemActor')
}

function targetLabel(log: AuditLog): string {
  const labelKey = auditableTypeLabelKey(log.auditable_type)
  if (!labelKey) return t('auditLog.unknownTarget')
  const typeLabel = t(`auditLog.resourceTypes.${labelKey}`)
  return log.auditable_id ? `${typeLabel} · ${log.auditable_id.slice(0, 8)}` : typeLabel
}

function formatDateTime(value: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(
    parseServerDateTime(value),
  )
}

function formatValue(value: unknown): string {
  if (value === null || value === undefined) return '—'
  if (Array.isArray(value)) return value.length ? value.join(', ') : '—'
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

function diffKeys(log: AuditLog): string[] {
  return Array.from(new Set([...Object.keys(log.changes ?? {}), ...Object.keys(log.old_values ?? {})]))
}

function contextKeys(log: AuditLog): string[] {
  return Object.keys(log.context ?? {})
}

onMounted(() => {
  store.fetchFilterUsers()
  store.fetch(currentFilters(), 1)
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">{{ t('auditLog.title') }}</h1>

    <div class="flex flex-wrap items-end gap-3">
      <div class="flex flex-col gap-1">
        <label for="audit-filter-user" class="text-sm text-surface-600 dark:text-surface-400">
          {{ t('auditLog.filters.user') }}
        </label>
        <Select
          id="audit-filter-user"
          v-model="userId"
          :options="userOptions"
          option-label="label"
          option-value="value"
          :placeholder="t('auditLog.filters.allUsers')"
          show-clear
          class="w-52"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="audit-filter-type" class="text-sm text-surface-600 dark:text-surface-400">
          {{ t('auditLog.filters.resourceType') }}
        </label>
        <Select
          id="audit-filter-type"
          v-model="auditableType"
          :options="resourceTypeOptions"
          option-label="label"
          option-value="value"
          :placeholder="t('auditLog.filters.allResourceTypes')"
          show-clear
          class="w-52"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="audit-filter-action" class="text-sm text-surface-600 dark:text-surface-400">
          {{ t('auditLog.filters.action') }}
        </label>
        <Select
          id="audit-filter-action"
          v-model="action"
          :options="actionOptions"
          option-label="label"
          option-value="value"
          :placeholder="t('auditLog.filters.allActions')"
          show-clear
          class="w-48"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="audit-filter-from" class="text-sm text-surface-600 dark:text-surface-400">
          {{ t('auditLog.filters.dateFrom') }}
        </label>
        <DatePicker
          id="audit-filter-from"
          v-model="dateFromValue"
          date-format="yy-mm-dd"
          :max-date="dateToValue ?? undefined"
          show-icon
          show-button-bar
          class="w-44"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="audit-filter-to" class="text-sm text-surface-600 dark:text-surface-400">
          {{ t('auditLog.filters.dateTo') }}
        </label>
        <DatePicker
          id="audit-filter-to"
          v-model="dateToValue"
          date-format="yy-mm-dd"
          :min-date="dateFromValue ?? undefined"
          show-icon
          show-button-bar
          class="w-44"
        />
      </div>

      <Button :label="t('auditLog.filters.apply')" icon="pi pi-filter" @click="applyFilters" />
    </div>

    <Message v-if="store.error" severity="error">{{ t(store.error) }}</Message>

    <div class="overflow-x-auto">
      <DataTable
        v-model:expanded-rows="expandedRows"
        :value="store.logs"
        :loading="store.loading"
        lazy
        paginator
        :rows="store.perPage"
        :total-records="store.total"
        data-key="id"
        @page="onPage"
      >
        <template #empty>
          <span class="text-surface-500 dark:text-surface-400">{{ t('auditLog.empty') }}</span>
        </template>

        <Column expander style="width: 3rem" />

        <Column :header="t('auditLog.table.when')">
          <template #body="{ data }: { data: AuditLog }">
            <span dir="ltr">{{ formatDateTime(data.created_at) }}</span>
          </template>
        </Column>

        <Column :header="t('auditLog.table.actor')">
          <template #body="{ data }: { data: AuditLog }">{{ actorLabel(data) }}</template>
        </Column>

        <Column :header="t('auditLog.table.action')">
          <template #body="{ data }: { data: AuditLog }">
            <Tag :value="t(`auditLog.actions.${data.action}`)" :severity="ACTION_SEVERITY[data.action]" />
          </template>
        </Column>

        <Column :header="t('auditLog.table.target')">
          <template #body="{ data }: { data: AuditLog }">{{ targetLabel(data) }}</template>
        </Column>

        <Column field="ip_address" :header="t('auditLog.table.ipAddress')">
          <template #body="{ data }: { data: AuditLog }">
            <span dir="ltr">{{ data.ip_address ?? '—' }}</span>
          </template>
        </Column>

        <template #expansion="{ data }: { data: AuditLog }">
          <div class="flex flex-col gap-3 p-3">
            <div v-if="diffKeys(data).length" class="flex flex-col gap-1">
              <table class="w-full max-w-2xl text-sm">
                <thead>
                  <tr class="text-start text-xs uppercase text-surface-500">
                    <th class="p-1 text-start"></th>
                    <th class="p-1 text-start">{{ t('auditLog.oldValue') }}</th>
                    <th class="p-1 text-start">{{ t('auditLog.newValue') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="key in diffKeys(data)" :key="key">
                    <td class="p-1 font-medium text-surface-700 dark:text-surface-200">{{ key }}</td>
                    <td class="p-1 text-surface-500">{{ formatValue(data.old_values?.[key]) }}</td>
                    <td class="p-1 text-surface-900 dark:text-surface-0">
                      {{ formatValue(data.changes?.[key]) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else class="text-sm text-surface-500">{{ t('auditLog.noChanges') }}</p>

            <div v-if="contextKeys(data).length" class="flex flex-col gap-1">
              <p class="text-xs font-semibold uppercase text-surface-500">{{ t('auditLog.context') }}</p>
              <p
                v-for="key in contextKeys(data)"
                :key="key"
                class="text-sm text-surface-700 dark:text-surface-200"
              >
                {{ key }}: {{ formatValue(data.context?.[key]) }}
              </p>
            </div>
          </div>
        </template>
      </DataTable>
    </div>
  </div>
</template>
