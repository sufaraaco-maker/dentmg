<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\LabCase;
use App\Models\Notification;
use App\Models\PatientActivity;
use App\Models\Payment;
use App\Models\RolePermission;
use App\Models\TreatmentPlan;
use App\Models\User;
use App\Notifications\AppointmentCancelledNotification;
use App\Notifications\NotificationRules;
use App\Services\AppointmentService;
use App\Services\InvoiceService;
use App\Services\LabCaseService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Services\RecipientResolver;
use App\Services\TreatmentPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 5A design doc §12.1 — the write path: the whitelist, recipient resolution, the two
 * universal rules (actor exclusion, send-time authorization), and fail-open behaviour.
 *
 * These tests deliberately drive the **real services** rather than dispatching
 * PatientActivityOccurred by hand. That is the only way to prove what this phase actually claims:
 * that a second listener auto-discovered onto an event which already fires in 9 services produces
 * notifications with zero new dispatch call sites. Faking the event would prove nothing about the
 * wiring.
 */
class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function receptionist(): User
    {
        return User::factory()->create(['role' => UserRole::Receptionist]);
    }

    // ---------------------------------------------------------------------
    // The whitelist (design doc §5 / NotificationRules)
    // ---------------------------------------------------------------------

    public function test_whitelisted_appointment_cancellation_notifies_the_dentist_and_front_desk(): void
    {
        $dentist = User::factory()->dentist()->create();
        $admin = User::factory()->admin()->create();
        $actor = $this->receptionist();
        $otherReceptionist = $this->receptionist();

        $appointment = Appointment::factory()->create(['dentist_id' => $dentist->id]);

        $this->actingAs($actor);
        (new AppointmentService)->cancel($appointment, 'Patient called');

        $this->assertEqualsCanonicalizing(
            [$dentist->id, $admin->id, $otherReceptionist->id],
            Notification::query()->pluck('notifiable_id')->all(),
        );

        $notification = Notification::query()->where('notifiable_id', $dentist->id)->sole();
        $this->assertSame('appointments', $notification->category);
        $this->assertSame(Appointment::class, $notification->subject_type);
        $this->assertSame($appointment->id, $notification->subject_id);
        $this->assertSame($appointment->patient_id, $notification->patient_id);
        $this->assertNull($notification->read_at);
    }

    public function test_check_in_notifies_only_the_assigned_dentist(): void
    {
        $dentist = User::factory()->dentist()->create();
        User::factory()->admin()->create();
        $appointment = Appointment::factory()->create([
            'dentist_id' => $dentist->id,
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->receptionist());
        (new AppointmentService)->checkIn($appointment);

        $this->assertSame([$dentist->id], Notification::query()->pluck('notifiable_id')->all());
        $this->assertSame(
            'appointment.checked_in',
            Notification::query()->sole()->data['type'],
        );
    }

    public function test_no_show_notifies_the_dentist_and_admins_but_not_receptionists(): void
    {
        $dentist = User::factory()->dentist()->create();
        $admin = User::factory()->admin()->create();
        $otherReceptionist = $this->receptionist();

        $appointment = Appointment::factory()->create([
            'dentist_id' => $dentist->id,
            'start_at' => now()->subHour(),
            'end_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($this->receptionist());
        (new AppointmentService)->markNoShow($appointment);

        $recipients = Notification::query()->pluck('notifiable_id')->all();
        $this->assertEqualsCanonicalizing([$dentist->id, $admin->id], $recipients);
        $this->assertNotContains($otherReceptionist->id, $recipients);
    }

    public function test_treatment_plan_acceptance_notifies_dentist_and_front_desk(): void
    {
        $dentist = User::factory()->dentist()->create();
        $admin = User::factory()->admin()->create();
        $receptionist = $this->receptionist();

        $plan = TreatmentPlan::factory()->presented()->create(['dentist_id' => $dentist->id]);

        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);
        (new TreatmentPlanService)->accept($plan, $actor);

        $this->assertEqualsCanonicalizing(
            [$dentist->id, $admin->id, $receptionist->id],
            Notification::query()->pluck('notifiable_id')->all(),
        );
        $this->assertSame('treatment_plans', Notification::query()->first()->category);
    }

    public function test_treatment_plan_rejection_notifies_only_the_dentist(): void
    {
        $dentist = User::factory()->dentist()->create();
        User::factory()->admin()->create();
        $this->receptionist();

        $plan = TreatmentPlan::factory()->presented()->create(['dentist_id' => $dentist->id]);

        $actor = User::factory()->admin()->create();
        $this->actingAs($actor);
        (new TreatmentPlanService)->reject($plan, $actor);

        $this->assertSame([$dentist->id], Notification::query()->pluck('notifiable_id')->all());
    }

    public function test_lab_case_received_notifies_only_the_prescribing_dentist(): void
    {
        $dentist = User::factory()->dentist()->create();
        User::factory()->admin()->create();

        $labCase = LabCase::factory()->sent()->create(['dentist_id' => $dentist->id]);

        $this->actingAs($this->receptionist());
        (new LabCaseService)->receive($labCase);

        $this->assertSame([$dentist->id], Notification::query()->pluck('notifiable_id')->all());
        $this->assertSame('laboratory', Notification::query()->sole()->category);
    }

    /**
     * Asserted against the *actual* admin set rather than a single hand-made admin: the Invoice
     * and Payment factories each create their own admin for `created_by_id`, so "notify admins"
     * legitimately reaches more than one. Deriving the expectation from the database proves the
     * stronger property anyway — every admin, and nobody who is not an admin.
     *
     * @return list<string>
     */
    private function adminIds(): array
    {
        return User::query()->where('role', UserRole::Admin->value)->pluck('id')->all();
    }

    public function test_payment_refund_notifies_admins_only(): void
    {
        User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $actor = $this->receptionist();

        $payment = Payment::factory()->create(['amount' => '100.00']);

        $this->actingAs($actor);
        (new PaymentService)->refund($payment, '40.00', null, $actor);

        $recipients = Notification::query()->pluck('notifiable_id')->all();
        $this->assertEqualsCanonicalizing($this->adminIds(), $recipients);
        $this->assertNotContains($dentist->id, $recipients);
        $this->assertNotContains($actor->id, $recipients);
        $this->assertSame('payments', Notification::query()->first()->category);
    }

    public function test_invoice_void_notifies_admins_only(): void
    {
        User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();

        $invoice = Invoice::factory()->issued()->create();

        $this->actingAs($this->receptionist());
        (new InvoiceService)->void($invoice);

        $recipients = Notification::query()->pluck('notifiable_id')->all();
        $this->assertEqualsCanonicalizing($this->adminIds(), $recipients);
        $this->assertNotContains($dentist->id, $recipients);
        $this->assertSame('billing', Notification::query()->first()->category);
    }

    // ---------------------------------------------------------------------
    // Silence is the default — the allow-list is as important as the list
    // ---------------------------------------------------------------------

    /**
     * The whitelist covers 8 of the 24 live PatientActivityOccurred event types. The other 16 must
     * produce nothing at all — a Notification Center that fills with routine lifecycle events is
     * the failure mode this design explicitly guards against (design doc §5.1).
     */
    public function test_non_whitelisted_events_produce_no_notifications(): void
    {
        User::factory()->admin()->create();
        $dentist = User::factory()->dentist()->create();
        $this->actingAs($this->receptionist());

        // appointment.confirmed / .in_progress / .completed — routine lifecycle
        $appointment = Appointment::factory()->create(['dentist_id' => $dentist->id]);
        (new AppointmentService)->confirm($appointment);

        // invoice.issued — routine; only voiding is the exception worth surfacing
        (new InvoiceService)->issue(Invoice::factory()->create());

        // lab_case.sent — the actor's own action
        (new LabCaseService)->send(LabCase::factory()->create(['dentist_id' => $dentist->id]));

        $this->assertSame(0, Notification::query()->count());
        // ...but the Timeline still recorded every one of them — this phase changed nothing there.
        $this->assertGreaterThanOrEqual(3, PatientActivity::query()->count());
    }

    public function test_the_whitelist_contains_exactly_the_eight_approved_types(): void
    {
        $this->assertSame([
            'appointment.checked_in',
            'appointment.cancelled',
            'appointment.no_show',
            'treatment_plan.accepted',
            'treatment_plan.rejected',
            'lab_case.received',
            'payment.refunded',
            'invoice.voided',
        ], array_keys(NotificationRules::RULES));
    }

    // ---------------------------------------------------------------------
    // The two universal rules (design doc §3.2)
    // ---------------------------------------------------------------------

    public function test_the_actor_never_receives_a_notification_for_their_own_action(): void
    {
        // The dentist cancels their own appointment: they are both the assigned dentist (a
        // recipient by rule) and the actor. Actor exclusion must win.
        $dentist = User::factory()->dentist()->create();
        $appointment = Appointment::factory()->create(['dentist_id' => $dentist->id]);

        $this->actingAs($dentist);
        (new AppointmentService)->cancel($appointment, 'Dentist unavailable');

        $this->assertSame(0, Notification::query()->where('notifiable_id', $dentist->id)->count());
    }

    public function test_a_user_who_cannot_view_the_subject_is_never_notified(): void
    {
        // Send-time authorization (layer 3). Revoke lab_cases.view from dentists, then trigger a
        // lab_case.received whose only recipient is a dentist.
        $dentist = User::factory()->dentist()->create();
        RolePermission::query()
            ->where('role', UserRole::Dentist->value)
            ->where('permission_key', 'lab_cases.view')
            ->delete();
        RolePermission::flushCache();

        $labCase = LabCase::factory()->sent()->create(['dentist_id' => $dentist->id]);

        $this->actingAs($this->receptionist());
        (new LabCaseService)->receive($labCase);

        $this->assertSame(0, Notification::query()->count());
    }

    public function test_an_unresolvable_recipient_produces_no_rows_and_no_error(): void
    {
        // A lab case whose dentist no longer exists. The business operation must still succeed.
        $dentist = User::factory()->dentist()->create();
        $labCase = LabCase::factory()->sent()->create(['dentist_id' => $dentist->id]);
        $dentist->forceDelete();

        $this->actingAs($this->receptionist());
        $received = (new LabCaseService)->receive($labCase->fresh());

        $this->assertSame('received', $received->status->value);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_duplicate_recipients_are_de_duplicated(): void
    {
        // An admin who is also the assigned dentist on the appointment matches two recipient rules
        // at once, and must still receive exactly one notification.
        $adminDentist = User::factory()->admin()->create();
        $appointment = Appointment::factory()->create(['dentist_id' => $adminDentist->id]);

        $this->actingAs($this->receptionist());
        (new AppointmentService)->cancel($appointment, 'Test');

        $this->assertSame(
            1,
            Notification::query()->where('notifiable_id', $adminDentist->id)->count(),
        );
    }

    // ---------------------------------------------------------------------
    // Payload shape (design doc §6.2 / §11)
    // ---------------------------------------------------------------------

    public function test_the_payload_stores_translation_keys_and_raw_params_never_rendered_text(): void
    {
        $dentist = User::factory()->dentist()->create();
        $appointment = Appointment::factory()->create(['dentist_id' => $dentist->id]);
        $actor = $this->receptionist();

        $this->actingAs($actor);
        (new AppointmentService)->cancel($appointment, 'Patient called');

        $data = Notification::query()->where('notifiable_id', $dentist->id)->sole()->data;

        $this->assertSame('appointment.cancelled', $data['type']);
        $this->assertSame('notifications.types.appointment.cancelled.title', $data['titleKey']);
        $this->assertSame('notifications.types.appointment.cancelled.body', $data['bodyKey']);
        $this->assertSame($actor->name, $data['actorName']);
        $this->assertSame('appointment-detail', $data['route']['name']);
        $this->assertSame($appointment->id, $data['route']['params']['id']);

        // Raw ISO-8601, not a formatted/localized string — formatting belongs to lib/date.ts at
        // render time (design doc §11.2, and the project-wide datetime policy).
        $this->assertSame($appointment->start_at->toIso8601String(), $data['params']['startAt']);
        $this->assertSame($appointment->patient->full_name, $data['params']['patientName']);
    }

    public function test_a_refund_without_an_invoice_falls_back_to_the_patient_billing_tab(): void
    {
        User::factory()->admin()->create();
        $actor = $this->receptionist();
        // An unapplied/advance payment — nullable invoice_id, per the Payments design.
        $payment = Payment::factory()->create(['invoice_id' => null, 'amount' => '50.00']);

        $this->actingAs($actor);
        (new PaymentService)->refund($payment, '50.00', null, $actor);

        $route = Notification::query()->first()->data['route'];
        $this->assertSame('patient-detail', $route['name']);
        $this->assertSame('billing', $route['query']['tab']);
    }

    // ---------------------------------------------------------------------
    // Fail-open (design doc §9)
    // ---------------------------------------------------------------------

    /**
     * A notification failure must never break the business operation that triggered it —
     * cancelling an appointment has to succeed even when notifying the dentist does not. Same
     * posture Phase 4's AuditLogService already established.
     */
    public function test_a_failing_notification_does_not_break_the_business_operation(): void
    {
        $dentist = User::factory()->dentist()->create();
        $appointment = Appointment::factory()->create(['dentist_id' => $dentist->id]);

        $broken = Mockery::mock(NotificationService::class);
        $broken->shouldReceive('dispatchFor')->andThrow(new RuntimeException('boom'));
        $this->app->instance(NotificationService::class, $broken);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) {
                // The log records identity, never the payload — that carries patient names.
                return $message === 'Notification dispatch failed'
                    && $context['event_type'] === 'appointment.cancelled'
                    && ! array_key_exists('data', $context);
            });

        $this->actingAs($this->receptionist());
        $cancelled = (new AppointmentService)->cancel($appointment, 'Test');

        $this->assertSame('cancelled', $cancelled->status->value);
        $this->assertSame(0, Notification::query()->count());
    }

    // ---------------------------------------------------------------------
    // The additive columns actually get written (design doc §3.1 / Decision D1)
    // ---------------------------------------------------------------------

    public function test_the_custom_database_channel_populates_the_four_additive_columns(): void
    {
        $dentist = User::factory()->dentist()->create();
        $appointment = Appointment::factory()->create(['dentist_id' => $dentist->id]);

        (new NotificationService(new RecipientResolver))->dispatchFor(
            $appointment,
            null,
            'appointment.cancelled',
        );

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $dentist->id,
            'notifiable_type' => User::class,
            'type' => AppointmentCancelledNotification::class,
            'category' => 'appointments',
            'subject_type' => Appointment::class,
            'subject_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
        ]);
    }
}
