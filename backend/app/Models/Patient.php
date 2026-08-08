<?php

namespace App\Models;

use App\Enums\BloodType;
use App\Enums\PatientGender;
use App\Models\Concerns\Auditable;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use Auditable, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'sequence_number',
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'phone',
        'email',
        'address',
        'national_id',
        'emergency_contact_name',
        'emergency_contact_phone',
        'blood_type',
        'allergies',
        'medical_history',
        'insurance_provider',
        'insurance_number',
        'notes',
    ];

    protected $appends = ['patient_code', 'full_name'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => PatientGender::class,
            'blood_type' => BloodType::class,
        ];
    }

    public function getPatientCodeAttribute(): string
    {
        return 'P-'.str_pad((string) $this->sequence_number, 5, '0', STR_PAD_LEFT);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function dentalChartEntries(): HasMany
    {
        return $this->hasMany(DentalChartEntry::class);
    }

    public function treatmentPlans(): HasMany
    {
        return $this->hasMany(TreatmentPlan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function clinicalNotes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PatientImage::class);
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(PatientAllergy::class);
    }

    public function labCases(): HasMany
    {
        return $this->hasMany(LabCase::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PatientDocument::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(PatientActivity::class);
    }

    public function medicalConditions(): HasMany
    {
        return $this->hasMany(PatientMedicalCondition::class);
    }

    public function medications(): HasMany
    {
        return $this->hasMany(PatientMedication::class);
    }
}
