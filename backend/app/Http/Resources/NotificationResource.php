<?php

namespace App\Http\Resources;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Never joins back to the subject table — `data` already carries everything a row needs to render
 * (translation keys, raw params, and its deep-link route), by design (design doc §6.2). Same
 * principle as PatientActivityResource.
 *
 * `data` is returned as-is rather than being flattened into the resource: it is a self-describing
 * payload the frontend renders through vue-i18n, and keeping it intact is what makes an old
 * notification still render correctly after a type is renamed.
 *
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'patient_id' => $this->patient_id,
            'data' => $this->data,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
