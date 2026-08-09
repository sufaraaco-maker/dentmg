<script setup lang="ts">
import { computed, onMounted, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import Button from 'primevue/button'
import ToggleSwitch from 'primevue/toggleswitch'
import Accordion from 'primevue/accordion'
import AccordionPanel from 'primevue/accordionpanel'
import AccordionHeader from 'primevue/accordionheader'
import AccordionContent from 'primevue/accordioncontent'
import { Lock } from 'lucide-vue-next'
import { usePermissionsStore } from '@/stores/permissions'
import { USER_ROLES, type UserRole } from '@/types/user'
import type { Permission, RolePermissionMatrix } from '@/types/permission'

/**
 * Phase 4 Step 4 (design doc §1.6) — the admin-configurable role<->permission matrix. `users.manage`
 * on the Admin role is the one server-enforced self-lockout rule
 * (`UpdateRolePermissionsRequest::withValidator`) — rendered disabled/checked here so the UI
 * explains the rule instead of letting an admin uncheck it and only find out on save (§1.4).
 */
const LOCKED_ROLE: UserRole = 'admin'
const LOCKED_KEY = 'users.manage'

const { t } = useI18n()
const toast = useToast()
const store = usePermissionsStore()

const draft = reactive<Record<UserRole, Set<string>>>({
  admin: new Set(),
  dentist: new Set(),
  receptionist: new Set(),
})

function loadDraftFromMatrix(matrix: RolePermissionMatrix) {
  for (const role of USER_ROLES) {
    draft[role] = new Set(matrix[role])
  }
}

watch(
  () => store.matrix,
  (matrix) => {
    if (matrix) loadDraftFromMatrix(matrix)
  },
  { immediate: true },
)

const groupedCatalog = computed(() => {
  const groups = new Map<string, Permission[]>()
  for (const permission of store.catalog) {
    if (!groups.has(permission.group)) groups.set(permission.group, [])
    groups.get(permission.group)!.push(permission)
  }
  return Array.from(groups.entries()).map(([group, permissions]) => ({ group, permissions }))
})

const dirty = computed(() => {
  if (!store.matrix) return false
  return USER_ROLES.some((role) => {
    const saved = new Set(store.matrix![role])
    const current = draft[role]
    if (saved.size !== current.size) return true
    for (const key of current) if (!saved.has(key)) return true
    return false
  })
})

function isLocked(role: UserRole, key: string): boolean {
  return role === LOCKED_ROLE && key === LOCKED_KEY
}

function isChecked(role: UserRole, key: string): boolean {
  return draft[role].has(key)
}

function cellAriaLabel(role: UserRole, permission: Permission): string {
  return `${t(`users.roles.${role}`)} — ${t(`permissions.catalog.${permission.key}`)}`
}

function toggle(role: UserRole, key: string): void {
  if (isLocked(role, key)) return
  const set = draft[role]
  if (set.has(key)) set.delete(key)
  else set.add(key)
}

function discard(): void {
  if (store.matrix) loadDraftFromMatrix(store.matrix)
}

async function save(): Promise<void> {
  const assignments = {
    admin: Array.from(draft.admin),
    dentist: Array.from(draft.dentist),
    receptionist: Array.from(draft.receptionist),
  } satisfies RolePermissionMatrix

  const success = await store.updateMatrix(assignments)
  if (success) {
    toast.add({ severity: 'success', summary: t('permissions.saved'), life: 3000 })
  } else {
    toast.add({ severity: 'error', summary: t(store.saveError!), life: 4000 })
  }
}

onMounted(() => store.fetchAll())
</script>

<template>
  <div class="flex flex-col gap-4">
    <div>
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('permissions.title') }}
      </h1>
      <p class="text-sm text-surface-500">{{ t('permissions.description') }}</p>
    </div>

    <Message v-if="store.error" severity="error">{{ t(store.error) }}</Message>

    <div v-if="store.loading && !store.matrix" class="flex flex-col gap-2">
      <Skeleton v-for="n in 6" :key="n" class="h-10 w-full" />
    </div>

    <template v-else-if="store.matrix">
      <div class="flex items-center gap-2 text-xs text-surface-500">
        <Lock :size="12" />
        <span>{{ t('permissions.lockedHint') }}</span>
      </div>

      <!-- Desktop: one matrix table, group header rows, one toggle column per role -->
      <div
        class="hidden overflow-x-auto rounded-border border border-surface-200 md:block dark:border-surface-700"
      >
        <table class="w-full text-start text-sm">
          <thead>
            <tr class="border-b border-surface-200 dark:border-surface-700">
              <th class="p-3 text-start font-medium text-surface-600 dark:text-surface-300">
                {{ t('permissions.permissionColumn') }}
              </th>
              <th
                v-for="role in USER_ROLES"
                :key="role"
                class="p-3 text-center font-medium text-surface-600 dark:text-surface-300"
              >
                {{ t(`users.roles.${role}`) }}
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="group in groupedCatalog" :key="group.group">
              <tr class="bg-surface-50 dark:bg-surface-800">
                <th colspan="4" class="p-2 text-start text-xs font-semibold uppercase text-surface-500">
                  {{ t(`permissions.groups.${group.group}`) }}
                </th>
              </tr>
              <tr
                v-for="permission in group.permissions"
                :key="permission.key"
                class="border-b border-surface-100 last:border-b-0 dark:border-surface-800"
              >
                <td class="p-3 text-surface-900 dark:text-surface-0">
                  {{ t(`permissions.catalog.${permission.key}`) }}
                </td>
                <td v-for="role in USER_ROLES" :key="role" class="p-3 text-center">
                  <ToggleSwitch
                    :model-value="isChecked(role, permission.key)"
                    :disabled="isLocked(role, permission.key)"
                    :aria-label="cellAriaLabel(role, permission)"
                    @update:model-value="toggle(role, permission.key)"
                  />
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Mobile: a grid of toggles doesn't fit a phone width — stacked accordion, one panel per role -->
      <Accordion class="md:hidden" value="admin">
        <AccordionPanel v-for="role in USER_ROLES" :key="role" :value="role">
          <AccordionHeader>{{ t(`users.roles.${role}`) }}</AccordionHeader>
          <AccordionContent>
            <div v-for="group in groupedCatalog" :key="group.group" class="mb-3 last:mb-0">
              <p class="mb-1 text-xs font-semibold uppercase text-surface-500">
                {{ t(`permissions.groups.${group.group}`) }}
              </p>
              <div
                v-for="permission in group.permissions"
                :key="permission.key"
                class="flex items-center justify-between border-b border-surface-100 py-2 last:border-b-0 dark:border-surface-800"
              >
                <span class="text-sm text-surface-900 dark:text-surface-0">
                  {{ t(`permissions.catalog.${permission.key}`) }}
                </span>
                <ToggleSwitch
                  :model-value="isChecked(role, permission.key)"
                  :disabled="isLocked(role, permission.key)"
                  @update:model-value="toggle(role, permission.key)"
                />
              </div>
            </div>
          </AccordionContent>
        </AccordionPanel>
      </Accordion>

      <div
        class="sticky bottom-0 -mx-4 flex items-center justify-end gap-3 border-t border-surface-200 bg-surface-0 p-4 dark:border-surface-700 dark:bg-surface-900"
      >
        <span v-if="dirty" class="me-auto text-sm text-surface-500">{{
          t('permissions.unsavedChanges')
        }}</span>
        <Button :label="t('permissions.discard')" text :disabled="!dirty || store.saving" @click="discard" />
        <Button :label="t('permissions.save')" :disabled="!dirty" :loading="store.saving" @click="save" />
      </div>
    </template>
  </div>
</template>
