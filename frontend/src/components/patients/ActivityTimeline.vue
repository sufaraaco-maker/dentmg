<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'
import { History } from 'lucide-vue-next'
import EmptyState from '@/components/common/EmptyState.vue'
import { usePatientActivitiesStore } from '@/stores/patientActivities'
import { parseServerDateTime } from '@/lib/date'
import type { PatientActivityCategory } from '@/types/patientActivity'

/**
 * Patient Timeline (Phase 2.6b, patient-timeline-redesign-design.md §8/§13) — one embeddable
 * component backing three call sites (umbrella doc §9.4/§15.2): the `timeline` tab (full view,
 * category chips shown), Billing's Payment History sub-section (`category="billing"`, no chips —
 * a fixed single-category embed has nothing to filter), and an Overview preview card (`compact`,
 * no chips/no "Load more", just the newest `pageSize` rows).
 *
 * Read-only: Timeline has no create/update/delete actions (design doc §16 decision 7), so unlike
 * every sibling panel this never mutates `usePatientActivitiesStore`'s cache beyond fetching.
 */
const props = withDefaults(
  defineProps<{
    patientId: string
    category?: PatientActivityCategory | null
    pageSize?: number
    compact?: boolean
  }>(),
  { category: null, pageSize: 15, compact: false },
)

const emit = defineEmits<{ viewAll: [] }>()

const { t, locale } = useI18n()
const toast = useToast()
const store = usePatientActivitiesStore()

// A fixed `category` prop (Billing's embed) is an immutable filter, not user-changeable — chips
// only make sense for the unfiltered full Timeline tab, and never in `compact` mode.
const showFilter = computed(() => !props.compact && props.category === null)

const CATEGORY_ORDER: PatientActivityCategory[] = [
  'appointments',
  'treatment_plans',
  'clinical_notes',
  'billing',
  'medical_history',
  'laboratory',
  'imaging',
  'documents',
]

const categoryChipOptions = computed(() => [
  { value: null as PatientActivityCategory | null, label: t('patients.timelinePanel.categories.all') },
  ...CATEGORY_ORDER.map((value) => ({
    value: value as PatientActivityCategory | null,
    label: t(`patients.timelinePanel.categories.${value}`),
  })),
])

// The active filter: fixed at `props.category` for a Billing-style embed (chips hidden — see
// `showFilter` — so this never changes for that case); user-driven via chips for the full Timeline
// tab, seeded from `props.category` (always `null` there).
const activeCategory = ref<PatientActivityCategory | null>(props.category)

const activities = computed(() =>
  store.activitiesForQuery(props.patientId, activeCategory.value, props.pageSize),
)
const pageMeta = computed(() => store.pageMetaForQuery(props.patientId, activeCategory.value, props.pageSize))
const loading = computed(() => store.isLoadingQuery(props.patientId, activeCategory.value, props.pageSize))
const hasMore = computed(() => pageMeta.value.currentPage < pageMeta.value.lastPage)

watch(
  () => store.errorForQuery(props.patientId, activeCategory.value, props.pageSize),
  (error) => {
    if (error) toast.add({ severity: 'error', summary: t(error), life: 3000 })
  },
)

function load() {
  store.fetchNextPage(props.patientId, activeCategory.value, props.pageSize)
}

watch(
  () => [props.patientId, activeCategory.value, props.pageSize] as const,
  () => load(),
  { immediate: true },
)

function selectCategory(category: PatientActivityCategory | null) {
  if (category === activeCategory.value) return
  activeCategory.value = category
}

function formatDateTime(value: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(
    parseServerDateTime(value),
  )
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div v-if="showFilter" class="flex gap-2 overflow-x-auto pb-1 md:flex-wrap md:overflow-visible">
      <button
        v-for="option in categoryChipOptions"
        :key="option.value ?? 'all'"
        type="button"
        class="shrink-0 rounded-full border px-3 py-1 text-sm transition-colors"
        :class="
          activeCategory === option.value
            ? 'border-primary-500 bg-primary-500 text-white'
            : 'border-surface-200 text-surface-600 hover:border-primary-300 dark:border-surface-700 dark:text-surface-300'
        "
        @click="selectCategory(option.value)"
      >
        {{ option.label }}
      </button>
    </div>

    <div v-if="loading && !activities.length" class="flex flex-col gap-2">
      <Skeleton v-for="n in compact ? 3 : 5" :key="n" class="h-14 w-full" />
    </div>

    <EmptyState v-else-if="!activities.length" :icon="History" :title="t('patients.timelinePanel.empty')" />

    <div v-else class="flex flex-col gap-2">
      <div
        v-for="activity in activities"
        :key="activity.id"
        class="flex flex-col gap-1 rounded-border border border-surface-200 p-3 dark:border-surface-700"
      >
        <span class="text-sm text-surface-900 dark:text-surface-0">{{ activity.summary }}</span>
        <span class="text-xs text-surface-500 dark:text-surface-400">
          <span dir="ltr">{{ formatDateTime(activity.occurred_at) }}</span>
          <template v-if="activity.actor"> · {{ activity.actor.name }}</template>
        </span>
      </div>
    </div>

    <Button
      v-if="compact"
      :label="t('patients.timelinePanel.viewAll')"
      text
      size="small"
      class="self-start"
      @click="emit('viewAll')"
    />
    <Button
      v-else-if="hasMore"
      :label="t('patients.timelinePanel.loadMore')"
      :loading="loading && !!activities.length"
      outlined
      size="small"
      class="self-center"
      @click="load"
    />
  </div>
</template>
