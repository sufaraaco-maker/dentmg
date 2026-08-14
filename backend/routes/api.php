<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AppointmentTypeController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingSettingController;
use App\Http\Controllers\Api\BillingSummaryController;
use App\Http\Controllers\Api\ClinicalNoteController;
use App\Http\Controllers\Api\ClinicSettingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DentalChartEntryController;
use App\Http\Controllers\Api\DentalConditionController;
use App\Http\Controllers\Api\DentistTimeOffController;
use App\Http\Controllers\Api\DentistWorkingHourController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\InvoiceItemController;
use App\Http\Controllers\Api\LabCaseController;
use App\Http\Controllers\Api\LabController;
use App\Http\Controllers\Api\MedicalHistoryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PatientActivityController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PatientDocumentController;
use App\Http\Controllers\Api\PatientImageController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\PurchaseOrderItemController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplyCategoryController;
use App\Http\Controllers\Api\SupplyController;
use App\Http\Controllers\Api\TreatmentPlanController;
use App\Http\Controllers\Api\TreatmentPlanItemController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json(['status' => 'ok']));

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/financial-summary', [DashboardController::class, 'financialSummary']);

    Route::apiResource('users', UserController::class);
    Route::post('users/{user}/avatar', [UserController::class, 'uploadAvatar']);
    Route::delete('users/{user}/avatar', [UserController::class, 'destroyAvatar']);

    // Phase 4 (Advanced Permissions & Audit) design doc §1.5 — admin-only (`manage-permissions`
    // Gate), never routed through the matrix these endpoints themselves manage.
    Route::get('permissions', [PermissionController::class, 'index']);
    Route::get('role-permissions', [RolePermissionController::class, 'index']);
    Route::put('role-permissions', [RolePermissionController::class, 'update']);

    // Phase 4 Step 3 design doc §2.6 — the general Audit Log viewer, admin-only.
    Route::get('audit-logs', [AuditLogController::class, 'index']);

    Route::get('patients/{patient}/audit-logs', [PatientController::class, 'auditLogs']);
    Route::apiResource('patients', PatientController::class);

    Route::get('available-slots', [AppointmentController::class, 'availableSlots']);

    Route::post('appointments/{appointment}/confirm', [AppointmentController::class, 'confirm']);
    Route::post('appointments/{appointment}/check-in', [AppointmentController::class, 'checkIn']);
    Route::post('appointments/{appointment}/start', [AppointmentController::class, 'start']);
    Route::post('appointments/{appointment}/complete', [AppointmentController::class, 'complete']);
    Route::post('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::post('appointments/{appointment}/no-show', [AppointmentController::class, 'noShow']);
    Route::apiResource('appointments', AppointmentController::class);

    Route::apiResource('appointment-types', AppointmentTypeController::class);

    Route::apiResource('dental-conditions', DentalConditionController::class);

    Route::get('patients/{patient}/dental-chart-entries', [DentalChartEntryController::class, 'index']);
    Route::post('patients/{patient}/dental-chart-entries', [DentalChartEntryController::class, 'store']);
    Route::post('dental-chart-entries/{dental_chart_entry}/complete', [DentalChartEntryController::class, 'complete']);
    Route::post('dental-chart-entries/{dental_chart_entry}/cancel', [DentalChartEntryController::class, 'cancel']);
    Route::put('dental-chart-entries/{dental_chart_entry}', [DentalChartEntryController::class, 'update']);
    Route::delete('dental-chart-entries/{dental_chart_entry}', [DentalChartEntryController::class, 'destroy']);

    Route::get('patients/{patient}/allergies', [MedicalHistoryController::class, 'indexAllergies']);
    Route::post('patients/{patient}/allergies', [MedicalHistoryController::class, 'storeAllergy']);
    Route::put('allergies/{allergy}', [MedicalHistoryController::class, 'updateAllergy']);
    Route::delete('allergies/{allergy}', [MedicalHistoryController::class, 'destroyAllergy']);

    Route::get('patients/{patient}/medical-conditions', [MedicalHistoryController::class, 'indexConditions']);
    Route::post('patients/{patient}/medical-conditions', [MedicalHistoryController::class, 'storeCondition']);
    Route::put('medical-conditions/{medical_condition}', [MedicalHistoryController::class, 'updateCondition']);
    Route::delete('medical-conditions/{medical_condition}', [MedicalHistoryController::class, 'destroyCondition']);

    Route::get('patients/{patient}/medications', [MedicalHistoryController::class, 'indexMedications']);
    Route::post('patients/{patient}/medications', [MedicalHistoryController::class, 'storeMedication']);
    Route::put('medications/{medication}', [MedicalHistoryController::class, 'updateMedication']);
    Route::delete('medications/{medication}', [MedicalHistoryController::class, 'destroyMedication']);

    Route::get('dentists/{user}/working-hours', [DentistWorkingHourController::class, 'index']);
    Route::post('dentists/{user}/working-hours', [DentistWorkingHourController::class, 'store']);
    Route::delete('dentists/{user}/working-hours/{workingHour}', [DentistWorkingHourController::class, 'destroy']);

    Route::get('dentists/{user}/time-off', [DentistTimeOffController::class, 'index']);
    Route::post('dentists/{user}/time-off', [DentistTimeOffController::class, 'store']);
    Route::delete('dentists/{user}/time-off/{timeOff}', [DentistTimeOffController::class, 'destroy']);

    Route::get('patients/{patient}/treatment-plans', [TreatmentPlanController::class, 'index']);
    Route::post('patients/{patient}/treatment-plans', [TreatmentPlanController::class, 'store']);
    Route::get('treatment-plans', [TreatmentPlanController::class, 'indexAll']);
    Route::get('treatment-plans/{treatment_plan}', [TreatmentPlanController::class, 'show']);
    Route::put('treatment-plans/{treatment_plan}', [TreatmentPlanController::class, 'update']);
    Route::post('treatment-plans/{treatment_plan}/present', [TreatmentPlanController::class, 'present']);
    Route::post('treatment-plans/{treatment_plan}/accept', [TreatmentPlanController::class, 'accept']);
    Route::post('treatment-plans/{treatment_plan}/reject', [TreatmentPlanController::class, 'reject']);
    Route::post('treatment-plans/{treatment_plan}/start', [TreatmentPlanController::class, 'start']);
    Route::post('treatment-plans/{treatment_plan}/complete', [TreatmentPlanController::class, 'complete']);
    Route::post('treatment-plans/{treatment_plan}/cancel', [TreatmentPlanController::class, 'cancel']);
    Route::post('treatment-plans/{treatment_plan}/revisions', [TreatmentPlanController::class, 'storeRevision']);
    Route::delete('treatment-plans/{treatment_plan}', [TreatmentPlanController::class, 'destroy']);

    Route::post('treatment-plans/{treatment_plan}/items', [TreatmentPlanItemController::class, 'store']);
    Route::put('treatment-plan-items/{treatment_plan_item}', [TreatmentPlanItemController::class, 'update']);
    Route::post('treatment-plan-items/{treatment_plan_item}/complete', [TreatmentPlanItemController::class, 'complete']);
    Route::post('treatment-plan-items/{treatment_plan_item}/cancel', [TreatmentPlanItemController::class, 'cancel']);
    Route::delete('treatment-plan-items/{treatment_plan_item}', [TreatmentPlanItemController::class, 'destroy']);

    Route::get('patients/{patient}/treatment-plan-items/billable', [InvoiceController::class, 'billableTreatmentPlanItems']);

    Route::get('patients/{patient}/billing-summary', [BillingSummaryController::class, 'show']);

    Route::get('patients/{patient}/invoices', [InvoiceController::class, 'index']);
    Route::post('patients/{patient}/invoices', [InvoiceController::class, 'store']);
    Route::get('invoices', [InvoiceController::class, 'indexAll']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::put('invoices/{invoice}', [InvoiceController::class, 'update']);
    Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue']);
    Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void']);
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy']);
    Route::get('invoices/{invoice}/payments', [PaymentController::class, 'forInvoice']);

    Route::post('invoices/{invoice}/items', [InvoiceItemController::class, 'store']);
    Route::put('invoice-items/{invoice_item}', [InvoiceItemController::class, 'update']);
    Route::delete('invoice-items/{invoice_item}', [InvoiceItemController::class, 'destroy']);

    Route::get('patients/{patient}/payments', [PaymentController::class, 'index']);
    Route::post('patients/{patient}/payments', [PaymentController::class, 'store']);
    Route::get('payments/{payment}', [PaymentController::class, 'show']);
    Route::put('payments/{payment}', [PaymentController::class, 'update']);
    Route::post('payments/{payment}/apply', [PaymentController::class, 'apply']);
    Route::post('payments/{payment}/refund', [PaymentController::class, 'refund']);
    Route::delete('payments/{payment}', [PaymentController::class, 'destroy']);

    Route::get('patients/{patient}/clinical-notes', [ClinicalNoteController::class, 'index']);
    Route::post('patients/{patient}/clinical-notes', [ClinicalNoteController::class, 'store']);
    Route::get('clinical-notes/{clinical_note}', [ClinicalNoteController::class, 'show']);
    Route::put('clinical-notes/{clinical_note}', [ClinicalNoteController::class, 'update']);
    Route::post('clinical-notes/{clinical_note}/sign', [ClinicalNoteController::class, 'sign']);
    Route::post('clinical-notes/{clinical_note}/addendums', [ClinicalNoteController::class, 'addAddendum']);
    Route::delete('clinical-notes/{clinical_note}', [ClinicalNoteController::class, 'destroy']);

    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('supply-categories', SupplyCategoryController::class);

    // Must precede the apiResource below — otherwise "low-stock" is captured by {supply}.
    Route::get('supplies/low-stock', [SupplyController::class, 'lowStock']);
    Route::apiResource('supplies', SupplyController::class);

    Route::get('supplies/{supply}/stock-movements', [StockMovementController::class, 'index']);
    Route::post('supplies/{supply}/stock-movements', [StockMovementController::class, 'store']);

    Route::post('purchase-orders/{purchase_order}/items', [PurchaseOrderItemController::class, 'store']);
    Route::put('purchase-order-items/{purchase_order_item}', [PurchaseOrderItemController::class, 'update']);
    Route::delete('purchase-order-items/{purchase_order_item}', [PurchaseOrderItemController::class, 'destroy']);
    Route::post('purchase-order-items/{purchase_order_item}/receive', [PurchaseOrderItemController::class, 'receive']);

    Route::post('purchase-orders/{purchase_order}/place', [PurchaseOrderController::class, 'place']);
    Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel']);
    Route::apiResource('purchase-orders', PurchaseOrderController::class);

    Route::apiResource('labs', LabController::class);

    // Must precede the apiResource below — otherwise "due" is captured by {lab_case}.
    Route::get('lab-cases/due', [LabCaseController::class, 'dueOrOverdue']);
    Route::post('lab-cases/{lab_case}/send', [LabCaseController::class, 'send']);
    Route::post('lab-cases/{lab_case}/receive', [LabCaseController::class, 'receive']);
    Route::post('lab-cases/{lab_case}/quality-check', [LabCaseController::class, 'qualityCheck']);
    Route::post('lab-cases/{lab_case}/cancel', [LabCaseController::class, 'cancel']);
    Route::apiResource('lab-cases', LabCaseController::class);

    // Patient Profile's Laboratory tab (Phase 2.4).
    Route::get('patients/{patient}/lab-cases', [LabCaseController::class, 'forPatient']);

    Route::get('patients/{patient}/images', [PatientImageController::class, 'index']);
    Route::post('patients/{patient}/images', [PatientImageController::class, 'store']);
    Route::get('images/{patient_image}/file', [PatientImageController::class, 'file'])->name('patient-images.file');
    Route::get('images/{patient_image}/thumbnail', [PatientImageController::class, 'thumbnail'])->name('patient-images.thumbnail');
    Route::put('images/{patient_image}', [PatientImageController::class, 'update']);
    Route::delete('images/{patient_image}', [PatientImageController::class, 'destroy']);

    // Patient Profile's Documents tab (Phase 2.5). Patient-scoped only — design doc §4.1/§16
    // decision 4, no flat GET /documents counterpart.
    Route::get('patients/{patient}/documents', [PatientDocumentController::class, 'index']);
    Route::post('patients/{patient}/documents', [PatientDocumentController::class, 'store']);
    Route::get('documents/{patient_document}/file', [PatientDocumentController::class, 'file'])->name('patient-documents.file');
    Route::put('documents/{patient_document}', [PatientDocumentController::class, 'update']);
    Route::delete('documents/{patient_document}', [PatientDocumentController::class, 'destroy']);

    // Patient Profile's Timeline tab (Phase 2.6a). Read-only — activity rows are written only by
    // RecordsPatientActivity, never via a user-facing endpoint.
    Route::get('patients/{patient}/activities', [PatientActivityController::class, 'index']);

    // Notification System (Phase 5A), design doc §7. Every route is self-scoped through
    // $request->user()->notifications() — `{notification}` is deliberately a plain string, NOT a
    // route-model-bound {notification}, so another user's row is never resolved in the first place
    // (design doc §8.1). No permission middleware by Decision D6: reading your own notifications is
    // structurally self-scoped, exactly like the My Account routes below.
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    // `->whereUuid()` (Laravel's own route-constraint helper, not custom parsing) rejects a
    // malformed id before the controller ever runs — without it, a non-UUID string reaches
    // `findOrFail()`'s underlying `WHERE id = ?` against the `notifications.id` uuid column and
    // Postgres throws `22P02: invalid input syntax for type uuid`, an uncaught QueryException that
    // becomes a 500. SQLite (the test suite's own connection) stores the column as untyped text and
    // never rejects it, which is why this needed a real Postgres-backed test, not just SQLite.
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->whereUuid('notification');

    Route::get('reports/production', [ReportController::class, 'production']);
    Route::get('reports/collections', [ReportController::class, 'collections']);
    Route::get('reports/ar-aging', [ReportController::class, 'arAging']);
    Route::get('reports/appointments', [ReportController::class, 'appointmentAnalytics']);
    Route::get('reports/treatment-plan-acceptance', [ReportController::class, 'treatmentPlanAcceptance']);
    Route::get('reports/new-patients', [ReportController::class, 'newPatients']);

    Route::get('clinic-settings', [ClinicSettingController::class, 'show']);
    Route::put('clinic-settings', [ClinicSettingController::class, 'update']);
    Route::post('clinic-settings/logo', [ClinicSettingController::class, 'uploadLogo']);
    Route::delete('clinic-settings/logo', [ClinicSettingController::class, 'destroyLogo']);

    Route::get('billing-settings', [BillingSettingController::class, 'show']);
    Route::put('billing-settings', [BillingSettingController::class, 'update']);

    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::put('profile/password', [ProfileController::class, 'updatePassword']);
});
