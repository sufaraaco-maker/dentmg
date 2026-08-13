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
        'ai_assistant_enabled',
        'ai_assistant_phi_features_acknowledged',
        'ai_assistant_api_key',
        'logo_disk',
        'logo_path',
    ];

    protected $casts = [
        'ai_assistant_enabled' => 'boolean',
        'ai_assistant_phi_features_acknowledged' => 'boolean',
        // Laravel's built-in AES-256-CBC cast (APP_KEY-derived) — encrypts on write, decrypts on
        // read automatically. Never returned by `ClinicSettingResource` (only a `configured`
        // boolean + last-4 derived from the decrypted value) and excluded from the audit trail
        // (`AuditLogService::EXCLUDED_KEYS`) — see
        // docs/modules/ai-assistant-settings-api-key-design.md §4/§8 for why both matter.
        'ai_assistant_api_key' => 'encrypted',
    ];
}
