<?php

namespace App\Ai\Concerns;

use InvalidArgumentException;

trait DecodesAgentResponse
{
    /**
     * @return array<string, mixed>
     */
    protected function decodeResponse(mixed $response, string $emptyErrorMessage, string $jsonErrorMessage): array
    {
        if (is_array($response)) {
            return $response;
        }

        $content = trim((string) $response);

        if ($content === '') {
            throw new InvalidArgumentException($emptyErrorMessage);
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException($jsonErrorMessage);
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Invalid response payload type.');
        }

        return $decoded;
    }
}
