export type UserRole = 'admin' | 'dentist' | 'receptionist'

export const USER_ROLES: UserRole[] = ['admin', 'dentist', 'receptionist']

export interface AuthUser {
  id: string
  name: string
  email: string
  role: UserRole
}
