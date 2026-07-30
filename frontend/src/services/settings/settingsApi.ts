import { api } from '@/lib/api'
import type { AuthUser } from '@/types/user'
import type {
  BillingSetting,
  ClinicSetting,
  UpdateBillingSettingPayload,
  UpdateClinicSettingPayload,
  UpdateProfilePasswordPayload,
  UpdateProfilePayload,
} from '@/types/settings'

export function getClinicSettings() {
  return api.get<ClinicSetting>('/clinic-settings').then((r) => r.data)
}

export function updateClinicSettings(payload: UpdateClinicSettingPayload) {
  return api.put<ClinicSetting>('/clinic-settings', payload).then((r) => r.data)
}

export function getBillingSettings() {
  return api.get<BillingSetting>('/billing-settings').then((r) => r.data)
}

export function updateBillingSettings(payload: UpdateBillingSettingPayload) {
  return api.put<BillingSetting>('/billing-settings', payload).then((r) => r.data)
}

export function getProfile() {
  return api.get<AuthUser>('/profile').then((r) => r.data)
}

export function updateProfile(payload: UpdateProfilePayload) {
  return api.put<AuthUser>('/profile', payload).then((r) => r.data)
}

export function updateProfilePassword(payload: UpdateProfilePasswordPayload) {
  return api.put<void>('/profile/password', payload).then((r) => r.data)
}
