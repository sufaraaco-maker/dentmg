<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\AvailableSlotsRequest;
use App\Http\Requests\Appointment\CancelAppointmentRequest;
use App\Http\Requests\Appointment\IndexAppointmentRequest;
use App\Http\Requests\Appointment\MarkNoShowAppointmentRequest;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    public function __construct(private AppointmentService $appointmentService) {}

    public function index(IndexAppointmentRequest $request)
    {
        $this->authorize('viewAny', Appointment::class);

        $appointments = $this->appointmentService->search($request->validated());

        return AppointmentResource::collection($appointments);
    }

    public function store(StoreAppointmentRequest $request)
    {
        $appointment = $this->appointmentService->create($request->validated());

        return (new AppointmentResource($appointment))->response()->setStatusCode(201);
    }

    public function show(Appointment $appointment)
    {
        $this->authorize('view', $appointment);

        $appointment->load(['patient', 'dentist', 'appointmentType']);

        return new AppointmentResource($appointment);
    }

    /**
     * Services both plain field edits and in-place reschedules (design doc §11/§16) — there is
     * no separate reschedule endpoint.
     */
    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        $appointment = $this->appointmentService->reschedule($appointment, $request->validated());

        return new AppointmentResource($appointment);
    }

    public function destroy(Appointment $appointment)
    {
        $this->authorize('delete', $appointment);

        $this->appointmentService->delete($appointment);

        return response()->noContent();
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment)
    {
        $appointment = $this->appointmentService->cancel($appointment, $request->validated()['cancellation_reason'] ?? null);

        return new AppointmentResource($appointment);
    }

    public function noShow(MarkNoShowAppointmentRequest $request, Appointment $appointment)
    {
        $appointment = $this->appointmentService->markNoShow($appointment, $request->boolean('override_early_no_show'));

        return new AppointmentResource($appointment);
    }

    public function confirm(Appointment $appointment)
    {
        $this->authorize('confirm', $appointment);

        $appointment = $this->appointmentService->confirm($appointment);

        return new AppointmentResource($appointment);
    }

    public function checkIn(Appointment $appointment)
    {
        $this->authorize('checkIn', $appointment);

        $appointment = $this->appointmentService->checkIn($appointment);

        return new AppointmentResource($appointment);
    }

    public function start(Appointment $appointment)
    {
        $this->authorize('start', $appointment);

        $appointment = $this->appointmentService->start($appointment);

        return new AppointmentResource($appointment);
    }

    public function complete(Appointment $appointment)
    {
        $this->authorize('complete', $appointment);

        $appointment = $this->appointmentService->complete($appointment);

        return new AppointmentResource($appointment);
    }

    /**
     * Top-level route (design doc §16), not nested under `/api/appointments/`.
     */
    public function availableSlots(AvailableSlotsRequest $request)
    {
        $this->authorize('viewAny', Appointment::class);

        $slots = $this->appointmentService->availableSlots(
            $request->string('dentist_id')->value(),
            Carbon::parse($request->string('date')->value()),
            $request->integer('duration_minutes')
        );

        return response()->json([
            'slots' => array_map(fn (Carbon $slot) => $slot->toIso8601String(), $slots),
        ]);
    }
}
