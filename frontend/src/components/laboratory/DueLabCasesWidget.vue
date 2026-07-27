<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Card from 'primevue/card'
import Skeleton from 'primevue/skeleton'
import Button from 'primevue/button'
import { api } from '@/lib/api'
import type { LabCase } from '@/types/laboratory'

/**
 * Dashboard widget (design doc §2/§6) — mirrors `LowStockWidget.vue`'s standalone,
 * self-fetching pattern rather than folding into `/dashboard/summary`.
 */
const { t } = useI18n()
const router = useRouter()

const count = ref<number | null>(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await api.get<LabCase[]>('/lab-cases/due')
    count.value = data.length
  } catch {
    count.value = null
  } finally {
    loading.value = false
  }
})

function viewDueCases() {
  router.push({ name: 'lab-cases', query: { status: 'sent' } })
}
</script>

<template>
  <Card>
    <template #title>{{ t('laboratory.dashboard.dueTitle') }}</template>
    <template #content>
      <Skeleton v-if="loading" height="3rem" />
      <template v-else-if="count !== null">
        <p
          class="text-3xl font-semibold"
          :class="count > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-surface-900 dark:text-surface-0'"
        >
          {{ count }}
        </p>
        <Button
          v-if="count > 0"
          :label="t('laboratory.dashboard.viewDue')"
          icon="pi pi-arrow-right"
          text
          size="small"
          class="mt-2 px-0"
          @click="viewDueCases"
        />
        <p v-else class="text-sm text-surface-500 dark:text-surface-400">
          {{ t('laboratory.dashboard.noDue') }}
        </p>
      </template>
    </template>
  </Card>
</template>
