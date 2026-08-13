<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Message from 'primevue/message'
import ImagePickerField from '@/components/common/ImagePickerField.vue'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { USER_ROLES, type AuthUser, type UserRole } from '@/types/user'

type UserRow = AuthUser

interface PaginatedUsers {
  data: UserRow[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

const { t } = useI18n()
const confirm = useConfirm()
const toast = useToast()
const auth = useAuthStore()

const roleOptions = USER_ROLES.map((role) => ({ value: role, label: t(`users.roles.${role}`) }))

const users = ref<UserRow[]>([])
const totalRecords = ref(0)
const perPage = ref(15)
const page = ref(1)
const search = ref('')
const loading = ref(false)

const dialogVisible = ref(false)
const editingUser = ref<UserRow | null>(null)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const avatarUploading = ref(false)
const avatarError = ref<string | null>(null)

const form = reactive<{
  name: string
  email: string
  password: string
  password_confirmation: string
  role: UserRole
}>({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'receptionist',
})

async function fetchUsers() {
  loading.value = true
  try {
    const { data } = await api.get<PaginatedUsers>('/users', {
      params: { search: search.value || undefined, page: page.value },
    })
    users.value = data.data
    totalRecords.value = data.meta.total
    perPage.value = data.meta.per_page
  } finally {
    loading.value = false
  }
}

function onPage(event: { page: number }) {
  page.value = event.page + 1
  fetchUsers()
}

let searchTimeout: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    fetchUsers()
  }, 300)
})

function openCreateDialog() {
  editingUser.value = null
  errors.value = {}
  form.name = ''
  form.email = ''
  form.password = ''
  form.password_confirmation = ''
  form.role = 'receptionist'
  dialogVisible.value = true
}

function openEditDialog(user: UserRow) {
  editingUser.value = user
  errors.value = {}
  avatarError.value = null
  form.name = user.name
  form.email = user.email
  form.password = ''
  form.password_confirmation = ''
  form.role = user.role
  dialogVisible.value = true
}

function patchUserRow(updated: UserRow) {
  editingUser.value = updated
  const index = users.value.findIndex((u) => u.id === updated.id)
  if (index !== -1) users.value[index] = updated
}

async function onAvatarSelect(file: File) {
  if (!editingUser.value) return

  avatarUploading.value = true
  avatarError.value = null
  try {
    const formData = new FormData()
    formData.append('avatar', file)
    const { data } = await api.post<UserRow>(`/users/${editingUser.value.id}/avatar`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    patchUserRow(data)
  } catch {
    avatarError.value = t('users.avatarSaveError')
  } finally {
    avatarUploading.value = false
  }
}

async function onAvatarRemove() {
  if (!editingUser.value) return

  avatarUploading.value = true
  avatarError.value = null
  try {
    const { data } = await api.delete<UserRow>(`/users/${editingUser.value.id}/avatar`)
    patchUserRow(data)
  } catch {
    avatarError.value = t('users.avatarSaveError')
  } finally {
    avatarUploading.value = false
  }
}

async function onSave() {
  saving.value = true
  errors.value = {}

  try {
    if (editingUser.value) {
      const payload: Record<string, string> = { name: form.name, email: form.email, role: form.role }
      if (form.password) {
        payload.password = form.password
        payload.password_confirmation = form.password_confirmation
      }
      await api.put(`/users/${editingUser.value.id}`, payload)
    } else {
      await api.post('/users', form)
    }

    dialogVisible.value = false
    toast.add({ severity: 'success', summary: t('users.saved'), life: 3000 })
    await fetchUsers()
  } catch (err: unknown) {
    if ((err as { response?: { status?: number } })?.response?.status === 422) {
      errors.value =
        (err as { response: { data: { errors?: Record<string, string[]> } } }).response.data.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('users.saveError'), life: 3000 })
    }
  } finally {
    saving.value = false
  }
}

function confirmDelete(user: UserRow) {
  confirm.require({
    message: t('users.confirmDelete', { name: user.name }),
    header: t('users.deleteHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await api.delete(`/users/${user.id}`)
        toast.add({ severity: 'success', summary: t('users.deleted'), life: 3000 })
        await fetchUsers()
      } catch {
        toast.add({ severity: 'error', summary: t('users.deleteError'), life: 3000 })
      }
    },
  })
}

onMounted(fetchUsers)
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('users.title') }}
      </h1>
      <Button v-if="auth.isAdmin" :label="t('users.new')" icon="pi pi-plus" @click="openCreateDialog" />
    </div>

    <IconField class="max-w-sm">
      <InputIcon class="pi pi-search" />
      <InputText v-model="search" :placeholder="t('users.search')" class="w-full" />
    </IconField>

    <DataTable
      :value="users"
      :loading="loading"
      lazy
      paginator
      :rows="perPage"
      :total-records="totalRecords"
      @page="onPage"
    >
      <template #empty>
        <span class="text-surface-500 dark:text-surface-400">{{ t('users.empty') }}</span>
      </template>
      <Column :header="t('users.avatar')" style="width: 4rem">
        <template #body="{ data }">
          <img
            v-if="data.avatar_url"
            :src="data.avatar_url"
            :alt="data.name"
            class="h-9 w-9 rounded-full object-cover"
          />
          <span
            v-else
            class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary dark:bg-primary-400/10 dark:text-primary-300"
          >
            {{ data.name.slice(0, 1).toUpperCase() }}
          </span>
        </template>
      </Column>
      <Column field="name" :header="t('users.name')" />
      <Column field="email" :header="t('users.email')" />
      <Column :header="t('users.role')">
        <template #body="{ data }">
          <Tag :value="t(`users.roles.${data.role}`)" />
        </template>
      </Column>
      <Column v-if="auth.isAdmin" :header="t('users.actions')" style="width: 8rem">
        <template #body="{ data }">
          <div class="flex gap-2">
            <Button icon="pi pi-pencil" text rounded @click="openEditDialog(data)" />
            <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmDelete(data)" />
          </div>
        </template>
      </Column>
    </DataTable>

    <Dialog
      v-model:visible="dialogVisible"
      modal
      :header="editingUser ? t('users.editTitle') : t('users.createTitle')"
      class="w-full max-w-md"
    >
      <form class="flex flex-col gap-4" @submit.prevent="onSave">
        <div v-if="editingUser" class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('users.avatar') }}</label>
          <ImagePickerField
            :image-url="editingUser.avatar_url ?? null"
            :label="t('users.avatar')"
            shape="circle"
            :size="72"
            :uploading="avatarUploading"
            :error="avatarError"
            @select="onAvatarSelect"
            @remove="onAvatarRemove"
          />
        </div>
        <Message v-else severity="secondary" size="small" variant="simple">
          {{ t('users.avatarAfterCreate') }}
        </Message>

        <div class="flex flex-col gap-2">
          <label for="name" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('users.name') }}
          </label>
          <InputText id="name" v-model="form.name" required />
          <Message v-if="errors.name" severity="error" size="small">{{ errors.name[0] }}</Message>
        </div>

        <div class="flex flex-col gap-2">
          <label for="email" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('users.email') }}
          </label>
          <InputText id="email" v-model="form.email" type="email" required />
          <Message v-if="errors.email" severity="error" size="small">{{ errors.email[0] }}</Message>
        </div>

        <div class="flex flex-col gap-2">
          <label for="role" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('users.role') }}
          </label>
          <Select
            id="role"
            v-model="form.role"
            :options="roleOptions"
            option-label="label"
            option-value="value"
            class="w-full"
          />
          <Message v-if="errors.role" severity="error" size="small">{{ errors.role[0] }}</Message>
        </div>

        <div class="flex flex-col gap-2">
          <label for="password" class="text-sm text-surface-700 dark:text-surface-200">
            {{ editingUser ? t('users.newPassword') : t('users.password') }}
          </label>
          <Password
            id="password"
            v-model="form.password"
            :feedback="false"
            toggle-mask
            fluid
            :required="!editingUser"
          />
          <Message v-if="errors.password" severity="error" size="small">{{ errors.password[0] }}</Message>
        </div>

        <div v-if="form.password" class="flex flex-col gap-2">
          <label for="password_confirmation" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('users.confirmPassword') }}
          </label>
          <Password
            id="password_confirmation"
            v-model="form.password_confirmation"
            :feedback="false"
            toggle-mask
            fluid
          />
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <Button :label="t('common.cancel')" text @click="dialogVisible = false" />
          <Button type="submit" :label="t('common.save')" :loading="saving" />
        </div>
      </form>
    </Dialog>
  </div>
</template>
