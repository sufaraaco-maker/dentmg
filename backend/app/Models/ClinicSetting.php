<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\ClinicSettingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A single-implicit-row settings table (design doc §3), same shape and reasoning as
 * `BillingSetting`: clinic-scoped-ready even though nothing reads/writes a `clinic_id` column yet.
 * No SoftDeletes — a settings row is configuration, not a real-world record a clinic recovers, same
 * exception class `database-design.md` already carves out for lookup/config tables.
 */
class ClinicSetting extends Model
{
    /** @use HasFactory<ClinicSettingFactory> */
    use Auditable, HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'phone',
        'address',
        'email',
    ];
}
