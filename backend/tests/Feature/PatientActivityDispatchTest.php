<?php

namespace Tests\Feature;

use App\Events\PatientActivityOccurred;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\ClinicalNote;
use App\Models\Invoice;
use App\Models\LabCase;
use App\Models\Patient;
use App\Models\PatientImage;
use App\Models\Payment;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\ClinicalNoteService;
use App\Services\InvoiceService;
use App\Services\LabCaseService;
use App\Services\MedicalHistoryService;
use App\Services\PatientDocumentService;
use App\Services\PatientImageService;
use App\Services\PaymentService;
use App\Services\TreatmentPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Design doc §5/§13: one test per event in the first-cut inventory, asserting the service method
 * actually dispatches PatientActivityOccurred with the right event_type/category/subject — "worth
 * a test per event, not just per controller" (umbrella doc §18), since the acknowledged risk is
 * silently missing an event type.
 */
class PatientActivityDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([PatientActivityOccurred::class]);
        Storage::fake('local');
    }

    private function assertDispatched(string $eventType, string $category, object $subject): void
    {
        Event::assertDispatched(
            PatientActivityOccurred::class,
            fn (PatientActivityOccurred $event) => $event->eventType === $eventType
                && $event->category === $category
                && $event->subject->is($subject),
        );
    }

    // ---- Appointments (7) -----------------------------------------------------------------------

    /**
     * Added later, as a genuine gap fix: AppointmentService::create() never dispatched
     * PatientActivityOccurred at all, unlike every other status transition below.
     */
    public function test_appointment_created_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);

        $appointment = (new AppointmentService)->create([
            'patient_id' => Patient::factory()->create()->id,
            'dentist_id' => User::factory()->dentist()->create()->id,
            'appointment_type_id' => AppointmentType::factory()->create()->id,
            'start_at' => now()->addDay()->setTime(10, 0),
            'duration_minutes' => 30,
            'override_patient_conflict' => true,
            'override_outside_working_hours' => true,
        ]);

        $this->assertDispatched('appointment.created', 'appointments', $appointment);
    }

    public function test_appointment_confirmed_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);
        $appointment = Appointment::factory()->create();

        (new AppointmentService)->confirm($appointment);

        $this->assertDispatched('appointment.confirmed', 'appointments', $appointment);
    }

    public function test_appointment_checked_in_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);
        $appointment = Appointment::factory()->create();

        (new AppointmentService)->checkIn($appointment);

        $this->assertDispatched('appointment.checked_in', 'appointments', $appointment);
    }

    public function test_appointment_started_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);
        $appointment = Appointment::factory()->create(['status' => 'checked_in']);

        (new AppointmentService)->start($appointment);

        $this->assertDispatched('appointment.in_progress', 'appointments', $appointment);
    }

    public function test_appointment_completed_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);
        $appointment = Appointment::factory()->create(['status' => 'in_progress']);

        (new AppointmentService)->complete($appointment);

        $this->assertDispatched('appointment.completed', 'appointments', $appointment);
    }

    public function test_appointment_cancelled_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);
        $appointment = Appointment::factory()->create();

        (new AppointmentService)->cancel($appointment, 'Patient request');

        $this->assertDispatched('appointment.cancelled', 'appointments', $appointment);
    }

    public function test_appointment_no_show_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);
        $appointment = Appointment::factory()->create([
            'status' => 'confirmed',
            'start_at' => now()->subHour(),
            'end_at' => now()->subMinutes(30),
        ]);

        (new AppointmentService)->markNoShow($appointment);

        $this->assertDispatched('appointment.no_show', 'appointments', $appointment);
    }

    // ---- Treatment Plans (5) --------------------------------------------------------------------

    public function test_treatment_plan_presented_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->create();

        (new TreatmentPlanService)->present($plan, $actor);

        $this->assertDispatched('treatment_plan.presented', 'treatment_plans', $plan);
    }

    public function test_treatment_plan_accepted_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->presented()->create();

        (new TreatmentPlanService)->accept($plan, $actor);

        $this->assertDispatched('treatment_plan.accepted', 'treatment_plans', $plan);
    }

    public function test_treatment_plan_rejected_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->presented()->create();

        (new TreatmentPlanService)->reject($plan, $actor);

        $this->assertDispatched('treatment_plan.rejected', 'treatment_plans', $plan);
    }

    public function test_treatment_plan_completed_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->inProgress()->create();

        (new TreatmentPlanService)->complete($plan, $actor);

        $this->assertDispatched('treatment_plan.completed', 'treatment_plans', $plan);
    }

    public function test_treatment_plan_cancelled_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $plan = TreatmentPlan::factory()->create();

        (new TreatmentPlanService)->cancel($plan, $actor);

        $this->assertDispatched('treatment_plan.cancelled', 'treatment_plans', $plan);
    }

    // ---- Clinical Notes (1) ----------------------------------------------------------------------

    public function test_clinical_note_signed_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $note = ClinicalNote::factory()->create(['subjective' => 'Patient reports mild pain.']);

        (new ClinicalNoteService)->sign($note, $actor);

        $this->assertDispatched('clinical_note.signed', 'clinical_notes', $note);
    }

    public function test_signing_an_already_signed_note_does_not_double_dispatch(): void
    {
        $actor = User::factory()->admin()->create();
        $note = ClinicalNote::factory()->signed()->create();

        (new ClinicalNoteService)->sign($note, $actor);

        Event::assertNotDispatched(PatientActivityOccurred::class);
    }

    // ---- Billing (4) -----------------------------------------------------------------------------

    public function test_invoice_issued_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);
        $invoice = Invoice::factory()->create();

        (new InvoiceService)->issue($invoice);

        $this->assertDispatched('invoice.issued', 'billing', $invoice);
    }

    public function test_invoice_voided_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);
        $invoice = Invoice::factory()->issued()->create();

        (new InvoiceService)->void($invoice);

        $this->assertDispatched('invoice.voided', 'billing', $invoice);
    }

    public function test_payment_recorded_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $payment = (new PaymentService)->record([
            'patient_id' => $patient->id,
            'method' => 'cash',
            'amount' => '100.00',
        ], $actor);

        $this->assertDispatched('payment.recorded', 'billing', $payment);
    }

    public function test_payment_refunded_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $payment = Payment::factory()->create(['amount' => '100.00']);

        (new PaymentService)->refund($payment, '50.00', 'Partial refund', $actor);

        Event::assertDispatched(
            PatientActivityOccurred::class,
            fn (PatientActivityOccurred $event) => $event->eventType === 'payment.refunded'
                && $event->category === 'billing'
                && $event->subject->refunded_payment_id === $payment->id,
        );
    }

    // ---- Medical History (3) ---------------------------------------------------------------------

    public function test_medical_history_allergy_added_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $allergy = (new MedicalHistoryService)->addAllergy([
            'patient_id' => $patient->id,
            'allergen' => 'Penicillin',
        ], $actor);

        $this->assertDispatched('medical_history.allergy_added', 'medical_history', $allergy);
    }

    public function test_medical_history_condition_added_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $condition = (new MedicalHistoryService)->addCondition([
            'patient_id' => $patient->id,
            'condition_name' => 'Hypertension',
        ], $actor);

        $this->assertDispatched('medical_history.condition_added', 'medical_history', $condition);
    }

    public function test_medical_history_medication_added_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $medication = (new MedicalHistoryService)->addMedication([
            'patient_id' => $patient->id,
            'medication_name' => 'Amoxicillin',
        ], $actor);

        $this->assertDispatched('medical_history.medication_added', 'medical_history', $medication);
    }

    // ---- Laboratory (3) --------------------------------------------------------------------------

    public function test_lab_case_sent_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);
        $labCase = LabCase::factory()->create();

        (new LabCaseService)->send($labCase);

        $this->assertDispatched('lab_case.sent', 'laboratory', $labCase);
    }

    public function test_lab_case_received_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);
        $labCase = LabCase::factory()->sent()->create();

        (new LabCaseService)->receive($labCase);

        $this->assertDispatched('lab_case.received', 'laboratory', $labCase);
    }

    public function test_lab_case_quality_checked_dispatches(): void
    {
        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);
        $labCase = LabCase::factory()->received()->create();

        (new LabCaseService)->qualityCheck($labCase);

        $this->assertDispatched('lab_case.quality_checked', 'laboratory', $labCase);
    }

    // ---- Imaging (1) -----------------------------------------------------------------------------

    public function test_image_uploaded_dispatches(): void
    {
        $uploader = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $image = (new PatientImageService)->upload(
            $patient,
            [UploadedFile::fake()->image('xray.jpg')],
            ['image_type' => 'xray_periapical', 'taken_at' => now()->toDateString()],
            $uploader,
        )[0];

        $this->assertInstanceOf(PatientImage::class, $image);
        $this->assertDispatched('image.uploaded', 'imaging', $image);
    }

    // ---- Documents (1) ---------------------------------------------------------------------------

    public function test_document_uploaded_dispatches(): void
    {
        $uploader = User::factory()->admin()->create();
        $patient = Patient::factory()->create();

        $document = (new PatientDocumentService)->upload(
            $patient,
            UploadedFile::fake()->create('consent.pdf', 100),
            ['category' => 'consent_form', 'title' => 'Consent Form'],
            $uploader,
        );

        $this->assertDispatched('document.uploaded', 'documents', $document);
    }
}
