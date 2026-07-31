<?php

namespace App\Exceptions\AiAssistant;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown by every `AiAssistantService` feature method when `ClinicSetting.ai_assistant_enabled`
 * is off (design doc §3/§5) — the general toggle, checked before any feature-specific gate.
 */
class AiAssistantDisabledException extends RuntimeException
{
    public function __construct(string $message = 'The AI Assistant is not enabled for this clinic.')
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'ai_assistant_disabled',
        ], 422);
    }
}
