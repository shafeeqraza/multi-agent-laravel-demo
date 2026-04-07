<?php

namespace App\Http\Responses;

use Illuminate\Support\Carbon;

final class ChatEnvelope
{
    public const SCHEMA_VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public static function success(mixed $result, ?Carbon $at = null): array
    {
        $at ??= now();
        $normalized = self::normalizeResult($result);
        $msg = $normalized['message'] ?? '';

        return [
            'report' => [
                'schema_version' => self::SCHEMA_VERSION,
                'outcome' => 'success',
                'recorded_at' => $at->toIso8601String(),
            ],
            'result' => $normalized,
            'message' => is_string($msg) ? $msg : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function providerOverloaded(?Carbon $at = null): array
    {
        $at ??= now();
        $userMessage = 'The AI service is temporarily busy. Please try again in a moment.';

        return [
            'report' => [
                'schema_version' => self::SCHEMA_VERSION,
                'outcome' => 'error',
                'recorded_at' => $at->toIso8601String(),
                'error' => [
                    'code' => 'ai_provider_overloaded',
                    'category' => 'provider_capacity',
                    'http_status' => 503,
                    'user_message' => $userMessage,
                    'retry_recommended' => true,
                ],
            ],
            'message' => $userMessage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function providerRateLimited(?Carbon $at = null): array
    {
        $at ??= now();
        $userMessage = 'The assistant is receiving too many requests. Please wait a moment and try again.';

        return [
            'report' => [
                'schema_version' => self::SCHEMA_VERSION,
                'outcome' => 'error',
                'recorded_at' => $at->toIso8601String(),
                'error' => [
                    'code' => 'ai_provider_rate_limited',
                    'category' => 'provider_rate_limit',
                    'http_status' => 429,
                    'user_message' => $userMessage,
                    'retry_recommended' => true,
                ],
            ],
            'message' => $userMessage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeResult(mixed $result): array
    {
        if (is_array($result)) {
            return $result;
        }

        if (is_object($result) && $result instanceof \JsonSerializable) {
            $decoded = $result->jsonSerialize();

            return is_array($decoded) ? $decoded : ['value' => $decoded];
        }

        if (is_object($result) && method_exists($result, 'toArray')) {
            return $result->toArray();
        }

        return ['value' => $result];
    }
}
