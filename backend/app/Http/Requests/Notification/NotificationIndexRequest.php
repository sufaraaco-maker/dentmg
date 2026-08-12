<?php

namespace App\Http\Requests\Notification;

use App\Policies\NotificationPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Design doc §8.1 / Decision D6: no `authorize()` permission check, deliberately. Reading your own
 * notifications is structurally self-scoped — the controller resolves every row through
 * `$request->user()->notifications()`, so there is nothing to authorize beyond being authenticated
 * (which the `auth:sanctum` route group already enforces). This matches ProfileController, the
 * other self-scoped surface in this codebase, which likewise has no permission key.
 *
 * The real authorization — the read-time category re-check — happens in the controller against
 * NotificationPolicy::allowedCategories(), because it filters the query rather than accepting or
 * rejecting the request wholesale.
 */
class NotificationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(['unread', 'read', 'all'])],
            'category' => ['sometimes', Rule::in(NotificationPolicy::allCategories())],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
