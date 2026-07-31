<?php

namespace App\Exceptions\AiAssistant;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown for Clinical Notes draft-assist / Treatment Suggestions when
 * `ClinicSetting.ai_assistant_phi_features_acknowledged` is false (design doc §5 Layer 2, Approval
 * decision 1, 2026-07-31) — a hard product requirement, not a soft default. This is a
 * legal/compliance precondition (a signed BAA with Anthropic) this codebase cannot verify beyond
 * an honest admin checkbox.
 */
class AiAssistantPhiNotAcknowledgedException extends RuntimeException
{
    public function __construct(string $message = 'This feature sends patient clinical data to the Claude API and requires an administrator to first confirm a Business Associate Agreement with Anthropic is in place.')
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'ai_assistant_phi_not_acknowledged',
        ], 422);
    }
}
