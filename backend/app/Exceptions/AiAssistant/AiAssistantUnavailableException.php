<?php

namespace App\Exceptions\AiAssistant;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when the feature is enabled in `ClinicSetting` but no `ANTHROPIC_API_KEY` is configured
 * (design doc §1: "Claude API integration is optional" — the module must be absent-by-default at
 * the code level too, not only reachable-then-failing). An admin enabling the toggle without an
 * operator having configured the key is a deployment misconfiguration, not a user error.
 */
class AiAssistantUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'The AI Assistant is enabled but not configured on this server. Contact your administrator.')
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'ai_assistant_unavailable',
        ], 503);
    }
}
