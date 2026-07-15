<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import Card from 'primevue/card'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

async function onSubmit() {
  loading.value = true
  error.value = ''

  try {
    await auth.login(email.value, password.value)
    router.push({ name: 'dashboard' })
  } catch {
    error.value = t('auth.invalidCredentials')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-surface-50 p-4 dark:bg-surface-900">
    <Card class="w-full max-w-sm">
      <template #title>{{ t('auth.title') }}</template>
      <template #content>
        <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
          <Message v-if="error" severity="error">{{ error }}</Message>

          <div class="flex flex-col gap-2">
            <label for="email" class="text-sm text-surface-700 dark:text-surface-200">
              {{ t('auth.email') }}
            </label>
            <InputText id="email" v-model="email" type="email" required autofocus />
          </div>

          <div class="flex flex-col gap-2">
            <label for="password" class="text-sm text-surface-700 dark:text-surface-200">
              {{ t('auth.password') }}
            </label>
            <Password
              id="password"
              v-model="password"
              :feedback="false"
              toggle-mask
              fluid
              required
            />
          </div>

          <Button type="submit" :label="t('auth.submit')" :loading="loading" class="w-full" />
        </form>
      </template>
    </Card>
  </div>
</template>
