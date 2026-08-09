/**
 * `auditable_type` is the raw FQCN Laravel stores on the polymorphic column (e.g.
 * `App\Models\Patient`) — this maps every value that can actually appear (the 21 `Auditable`
 * models per Phase 4 Step 3's audit, plus `RolePermission`, which is audited via
 * `AuditLogService::recordEvent()` rather than the trait) to a stable i18n key, so the Audit Log
 * viewer's resource-type filter/column never renders a raw PHP class name to the user.
 */
export const AUDITABLE_TYPES: { value: string; labelKey: string }[] = [
  { value: 'App\\Models\\User', labelKey: 'user' },
  { value: 'App\\Models\\Patient', labelKey: 'patient' },
  { value: 'App\\Models\\PatientDocument', labelKey: 'patientDocument' },
  { value: 'App\\Models\\LabCase', labelKey: 'labCase' },
  { value: 'App\\Models\\PatientMedication', labelKey: 'patientMedication' },
  { value: 'App\\Models\\PatientMedicalCondition', labelKey: 'patientMedicalCondition' },
  { value: 'App\\Models\\PatientAllergy', labelKey: 'patientAllergy' },
  { value: 'App\\Models\\ClinicSetting', labelKey: 'clinicSetting' },
  { value: 'App\\Models\\AiInteractionLog', labelKey: 'aiInteractionLog' },
  { value: 'App\\Models\\PatientImage', labelKey: 'patientImage' },
  { value: 'App\\Models\\PurchaseOrder', labelKey: 'purchaseOrder' },
  { value: 'App\\Models\\ClinicalNoteAddendum', labelKey: 'clinicalNoteAddendum' },
  { value: 'App\\Models\\ClinicalNote', labelKey: 'clinicalNote' },
  { value: 'App\\Models\\TreatmentPlanItem', labelKey: 'treatmentPlanItem' },
  { value: 'App\\Models\\TreatmentPlan', labelKey: 'treatmentPlan' },
  { value: 'App\\Models\\Payment', labelKey: 'payment' },
  { value: 'App\\Models\\InvoiceItem', labelKey: 'invoiceItem' },
  { value: 'App\\Models\\Invoice', labelKey: 'invoice' },
  { value: 'App\\Models\\BillingSetting', labelKey: 'billingSetting' },
  { value: 'App\\Models\\DentalChartEntry', labelKey: 'dentalChartEntry' },
  { value: 'App\\Models\\Appointment', labelKey: 'appointment' },
  { value: 'App\\Models\\RolePermission', labelKey: 'rolePermission' },
]

export function auditableTypeLabelKey(auditableType: string | null): string | null {
  return AUDITABLE_TYPES.find((entry) => entry.value === auditableType)?.labelKey ?? null
}
