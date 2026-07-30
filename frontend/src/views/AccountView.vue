<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import Skeleton from 'primevue/skeleton'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Message from 'primevue/message'
import Button from 'primevue/button'
import { useAuthStore } from '@/stores/auth'
import { getProfile, updateProfile, updateProfilePassword } from '@/services/settings'

/**
 * My Account (design doc §4.3/§7) — self-service profile + password change, available to every
 * role, reachable from the header user-avatar menu regardless of the admin-only Settings nav. A
 * profile form (name/email) plus a separate "Change Password" panel, matching the design doc's
 * two-panel layout.
 */
const { t } = useI18n()
const toast = useToast()
const auth = useAuthStore()

const loading = ref(true)
const savingProfile = ref(false)
const profileErrors = ref<Record<string, string[]>>({})
const profileForm = reactive({ name: '', email: '' })

const savingPassword = ref(false)
const passwordErrors = ref<Record<string, string[]>>({})
const passwordForm = reactive({ current_password: '', password: '', password_confirmation: '' })

async function load() {
  loading.value = true
  try {
    const profile = await getProfile()
    profileForm.name = profile.name
    profileForm.email = profile.email
  } catch {
    toast.add({ severity: 'error', summary: t('account.loadError'), life: 3000 })
  } finally {
    loading.value = false
  }
}

onMounted(load)

async function submitProfile() {
  savingProfile.value = true
  profileErrors.value = {}

  try {
    const saved = await updateProfile({
      name: profileForm.name.trim(),
      email: profileForm.email.trim(),
    })
    profileForm.name = saved.name
    profileForm.email = saved.email
    if (auth.user) {
      auth.user.name = saved.name
      auth.user.email = saved.email
    }
    toast.add({ severity: 'success', summary: t('account.profileSaved'), life: 3000 })
  } catch (err: unknown) {
    const response = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } })
      ?.response

    if (response?.status === 422) {
      profileErrors.value = response.data?.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('account.profileSaveError'), life: 3000 })
    }
  } finally {
    savingProfile.value = false
  }
}

function resetPasswordForm() {
  passwordForm.current_password = ''
  passwordForm.password = ''
  passwordForm.password_confirmation = ''
}

async function submitPassword() {
  savingPassword.value = true
  passwordErrors.value = {}

  try {
    await updateProfilePassword({ ...passwordForm })
    resetPasswordForm()
    toast.add({ severity: 'success', summary: t('account.passwordSaved'), life: 3000 })
  } catch (err: unknown) {
    const response = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } })
      ?.response

    if (response?.status === 422) {
      passwordErrors.value = response.data?.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('account.passwordSaveError'), life: 3000 })
    }
  } finally {
    savingPassword.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">{{ t('account.title') }}</h1>

    <Skeleton v-if="loading" height="20rem" />

    <template v-else>
      <Card class="max-w-2xl">
        <template #title>{{ t('account.profileTitle') }}</template>
        <template #content>
          <form class="flex flex-col gap-4" @submit.prevent="submitProfile">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div class="flex flex-col gap-2">
                <label for="account-name" class="text-sm text-surface-700 dark:text-surface-200">
                  {{ t('account.name') }}
                </label>
                <InputText id="account-name" v-model="profileForm.name" :invalid="!!profileErrors.name" fluid />
                <Message v-if="profileErrors.name" severity="error" size="small">
                  {{ profileErrors.name[0] }}
                </Message>
              </div>
              <div class="flex flex-col gap-2">
                <label for="account-email" class="text-sm text-surface-700 dark:text-surface-200">
                  {{ t('account.email') }}
                </label>
                <InputText
                  id="account-email"
                  v-model="profileForm.email"
                  type="email"
                  :invalid="!!profileErrors.email"
                  fluid
                  dir="ltr"
                />
                <Message v-if="profileErrors.email" severity="error" size="small">
                  {{ profileErrors.email[0] }}
                </Message>
              </div>
            </div>

            <div class="flex justify-end pt-2">
              <Button type="submit" :label="t('common.save')" :loading="savingProfile" />
            </div>
          </form>
        </template>
      </Card>

      <Card class="max-w-2xl">
        <template #title>{{ t('account.passwordTitle') }}</template>
        <template #content>
          <form class="flex flex-col gap-4" @submit.prevent="submitPassword">
            <div class="flex flex-col gap-2">
              <label for="account-current-password" class="text-sm text-surface-700 dark:text-surface-200">
                {{ t('account.currentPassword') }}
              </label>
              <Password
                id="account-current-password"
                v-model="passwordForm.current_password"
                :feedback="false"
                toggle-mask
                :invalid="!!passwordErrors.current_password"
                fluid
              />
              <Message v-if="passwordErrors.current_password" severity="error" size="small">
                {{ passwordErrors.current_password[0] }}
              </Message>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div class="flex flex-col gap-2">
                <label for="account-new-password" class="text-sm text-surface-700 dark:text-surface-200">
                  {{ t('account.newPassword') }}
                </label>
                <Password
                  id="account-new-password"
                  v-model="passwordForm.password"
                  toggle-mask
                  :invalid="!!passwordErrors.password"
                  fluid
                />
                <Message v-if="passwordErrors.password" severity="error" size="small">
                  {{ passwordErrors.password[0] }}
                </Message>
              </div>
              <div class="flex flex-col gap-2">
                <label for="account-confirm-password" class="text-sm text-surface-700 dark:text-surface-200">
                  {{ t('account.confirmPassword') }}
                </label>
                <Password
                  id="account-confirm-password"
                  v-model="passwordForm.password_confirmation"
                  :feedback="false"
                  toggle-mask
                  fluid
                />
              </div>
            </div>

            <div class="flex justify-end pt-2">
              <Button type="submit" :label="t('account.changePassword')" :loading="savingPassword" />
            </div>
          </form>
        </template>
      </Card>
    </template>
  </div>
</template>
