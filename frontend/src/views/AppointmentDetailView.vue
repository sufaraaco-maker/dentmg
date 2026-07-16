<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Card from 'primevue/card'
import Button from 'primevue/button'

const { t } = useI18n()
const router = useRouter()
</script>

<template>
  <Card>
    <template #title>{{ t('appointments.title') }}</template>
    <template #content>
      <p class="text-surface-600 dark:text-surface-300">{{ t('appointments.comingSoon') }}</p>
      <Button
        class="mt-4"
        :label="t('appointments.backToAppointments')"
        text
        @click="router.push({ name: 'appointments' })"
      />
    </template>
  </Card>
</template>
