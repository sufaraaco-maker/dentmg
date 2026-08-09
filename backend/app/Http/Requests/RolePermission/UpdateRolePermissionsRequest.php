<?php

namespace App\Http\Requests\RolePermission;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Full-replace update of the role<->permission matrix (Phase 4 design doc §1.5). The payload is
 * the complete desired assignment for all 3 roles, keyed by role — not a diff — so there is never
 * an ambiguous "what happens to keys I didn't mention" case.
 */
class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-permissions');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assignments' => ['required', 'array'],
            'assignments.admin' => ['required', 'array'],
            'assignments.admin.*' => ['string', Rule::exists('permissions', 'key')],
            'assignments.dentist' => ['required', 'array'],
            'assignments.dentist.*' => ['string', Rule::exists('permissions', 'key')],
            'assignments.receptionist' => ['required', 'array'],
            'assignments.receptionist.*' => ['string', Rule::exists('permissions', 'key')],
        ];
    }

    /**
     * Design doc §1.4: `users.manage` can never be revoked from Admin — the only role-level
     * safeguard against a clinic locking itself out of user/role management. (`manage-permissions`
     * itself needs no such rule — it's never routed through this matrix in the first place, see
     * AppServiceProvider.)
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $adminKeys = $this->input('assignments.admin', []);

            if (is_array($adminKeys) && ! in_array('users.manage', $adminKeys, true)) {
                $validator->errors()->add(
                    'assignments.admin',
                    'The Admin role must always retain the "users.manage" permission — removing it would risk locking every admin out of user management.',
                );
            }
        });
    }
}
