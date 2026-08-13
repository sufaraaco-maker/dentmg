export type UserRole = 'admin' | 'dentist' | 'receptionist'

export const USER_ROLES: UserRole[] = ['admin', 'dentist', 'receptionist']

export interface AuthUser {
  id: string
  name: string
  email: string
  role: UserRole
  /** Optional rather than required: the API always sends it, but a large number of existing test
   *  fixtures construct `AuthUser` literals without it (role/permission tests that predate avatars
   *  and have nothing to do with them) — making it required would force unrelated edits across
   *  every one of those files for no behavioral gain, since every real consumer already treats a
   *  missing/undefined value the same as `null` (`v-if="user?.avatar_url"`). */
  avatar_url?: string | null
}
