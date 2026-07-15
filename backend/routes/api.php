<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AppointmentTypeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DentistTimeOffController;
use App\Http\Controllers\Api\DentistWorkingHourController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json(['status' => 'ok']));

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    Route::apiResource('users', UserController::class);

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

    Route::get('dentists/{user}/working-hours', [DentistWorkingHourController::class, 'index']);
    Route::post('dentists/{user}/working-hours', [DentistWorkingHourController::class, 'store']);
    Route::delete('dentists/{user}/working-hours/{workingHour}', [DentistWorkingHourController::class, 'destroy']);

    Route::get('dentists/{user}/time-off', [DentistTimeOffController::class, 'index']);
    Route::post('dentists/{user}/time-off', [DentistTimeOffController::class, 'store']);
    Route::delete('dentists/{user}/time-off/{timeOff}', [DentistTimeOffController::class, 'destroy']);
});
