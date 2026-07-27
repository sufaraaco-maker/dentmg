<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Card from 'primevue/card'
import Skeleton from 'primevue/skeleton'
import Button from 'primevue/button'
import { api } from '@/lib/api'
import type { Supply } from '@/types/inventory'

/**
 * Dashboard widget (design doc §11) — mirrors `TodayScheduleWidget.vue`'s standalone,
 * self-fetching pattern rather than folding into `/dashboard/summary` (a shared,
 * Inventory-unaware endpoint no other module's widget touches directly either).
 */
const { t } = useI18n()
const router = useRouter()

const count = ref<number | null>(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await api.get<Supply[]>('/supplies/low-stock')
    count.value = data.length
  } catch {
    count.value = null
  } finally {
    loading.value = false
  }
})

function viewLowStock() {
  router.push({ name: 'supplies', query: { low_stock_only: '1' } })
}
</script>

<template>
  <Card>
    <template #title>{{ t('inventory.dashboard.lowStockTitle') }}</template>
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
          :label="t('inventory.dashboard.viewLowStock')"
          icon="pi pi-arrow-right"
          text
          size="small"
          class="mt-2 px-0"
          @click="viewLowStock"
        />
        <p v-else class="text-sm text-surface-500 dark:text-surface-400">
          {{ t('inventory.dashboard.noLowStock') }}
        </p>
      </template>
    </template>
  </Card>
</template>
