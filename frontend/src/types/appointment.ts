export type AppointmentStatus =
  'scheduled' | 'confirmed' | 'checked_in' | 'in_progress' | 'completed' | 'cancelled' | 'no_show'

export const APPOINTMENT_STATUSES: AppointmentStatus[] = [
  'scheduled',
  'confirmed',
  'checked_in',
  'in_progress',
  'completed',
  'cancelled',
  'no_show',
]

export const APPOINTMENT_STATUS_COLORS: Record<AppointmentStatus, string> = {
  scheduled: '#64748b',
  confirmed: '#0ea5e9',
  checked_in: '#eab308',
  in_progress: '#8b5cf6',
  completed: '#22c55e',
  cancelled: '#94a3b8',
  no_show: '#ef4444',
}

export interface AppointmentPatientSummary {
  id: string
  patient_code: string
  full_name: string
}

export interface AppointmentDentistSummary {
  id: string
  name: string
}

export interface AppointmentType {
  id: string
  name: string
  default_duration_minutes: number
  color: string
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface Appointment {
  id: string
  patient_id: string
  dentist_id: string
  appointment_type_id: string
  start_at: string
  end_at: string
  duration_minutes: number
  status: AppointmentStatus
  reason: string | null
  notes: string | null
  cancellation_reason: string | null
  cancelled_at: string | null
  cancelled_by: string | null
  checked_in_at: string | null
  started_at: string | null
  completed_at: string | null
  no_show_at: string | null
  reschedule_count: number
  created_at: string
  updated_at: string
  patient?: AppointmentPatientSummary
  dentist?: AppointmentDentistSummary
  appointment_type?: AppointmentType
}

export interface DentistWorkingHour {
  id: string
  user_id: string
  day_of_week: number
  start_time: string
  end_time: string
  is_active: boolean
}

export interface DentistTimeOff {
  id: string
  user_id: string
  start_at: string
  end_at: string
  reason: string | null
}

export type AppointmentConflictCode =
  | 'dentist_conflict'
  | 'patient_conflict'
  | 'outside_working_hours'
  | 'early_no_show'
  | 'invalid_status_transition'

export interface AppointmentConflictError {
  message: string
  code: AppointmentConflictCode
  overridable?: boolean
  override_field?: string
}

export interface ValidationError {
  message: string
  errors: Record<string, string[]>
}

export interface CreateAppointmentPayload {
  patient_id: string
  dentist_id: string
  appointment_type_id: string
  start_at: string
  duration_minutes: number
  reason?: string | null
  notes?: string | null
  override_patient_conflict?: boolean
  override_outside_working_hours?: boolean
}

export interface UpdateAppointmentPayload {
  dentist_id?: string
  appointment_type_id?: string
  start_at?: string
  duration_minutes?: number
  reason?: string | null
  notes?: string | null
  override_patient_conflict?: boolean
  override_outside_working_hours?: boolean
}

export interface CancelAppointmentPayload {
  cancellation_reason?: string | null
}

export interface MarkNoShowPayload {
  override_early_no_show?: boolean
}

export interface IndexAppointmentParams {
  date_from: string
  date_to: string
  dentist_id?: string
  patient_id?: string
  status?: AppointmentStatus
}

export interface AvailableSlotsParams {
  dentist_id: string
  date: string
  duration_minutes: number
}

export interface CreateAppointmentTypePayload {
  name: string
  default_duration_minutes: number
  color: string
  is_active?: boolean
}

export type UpdateAppointmentTypePayload = Partial<CreateAppointmentTypePayload>

export interface CreateWorkingHourPayload {
  day_of_week: number
  start_time: string
  end_time: string
  is_active?: boolean
}

/**
 * Local editing state for one shift row in `WorkingHoursDayRow` (design doc §5) — `id: null`
 * marks a row not yet persisted. Not a backend response shape; the backend only exposes
 * create/delete for `dentist_working_hours` (no update), so `WorkingHoursEditor` diffs this
 * against the store's real `DentistWorkingHour[]` to decide what to create/delete.
 */
export interface WorkingHourDraft {
  id: string | null
  day_of_week: number
  start_time: string
  end_time: string
  is_active: boolean
}

export interface CreateTimeOffPayload {
  start_at: string
  end_at: string
  reason?: string | null
}

export type CalendarViewMode =
  'timeGridDay' | 'timeGridWeek' | 'dayGridMonth' | 'resourceTimeGridDay' | 'list'

/** The six status-transition endpoints `AppointmentActionsBar`/`StatusActionButton` can trigger (§4.6). */
export type AppointmentActionKind = 'confirm' | 'checkIn' | 'start' | 'complete' | 'cancel' | 'noShow'

/** What `AppointmentDialog` prefills when opened from a calendar slot-click, patient panel, or dashboard quick action. */
export interface AppointmentPrefill {
  dentist_id?: string
  start_at?: string
  patient_id?: string
}
