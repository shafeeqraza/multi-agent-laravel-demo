<?php

namespace App\Ai\Agents;

use App\Models\ReturnRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

use function Laravel\Ai\agent;

class ReturnsAgent
{
    private const RETURN_WINDOW_DAYS = 7;

    /**
     * @param  array{order_id: string}  $input
     * @return array{eligible: bool, message: string}
     */
    public function handle(array $input): array
    {
        $validator = Validator::make($input, [
            'order_id' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException('Missing required field: order_id.');
        }

        $orderId = $input['order_id'];
        $eligible = $this->checkReturnEligibility($orderId);

        if ($eligible) {
            ReturnRequest::query()->create([
                'order_id' => $orderId,
                'status' => 'pending',
                'requested_at' => now(),
            ]);
        }

        $response = agent(
            instructions: $this->instructions(),
            schema: fn (JsonSchema $schema) => [
                'eligible' => $schema->boolean()->required(),
                'message' => $schema->string()->required(),
            ],
        )->prompt($this->buildPrompt($orderId, $eligible), provider: 'gemini');

        $payload = $this->decodeResponse($response);

        $resultValidator = Validator::make($payload, [
            'eligible' => ['required', 'boolean'],
            'message' => ['required', 'string'],
        ]);

        if ($resultValidator->fails()) {
            throw new InvalidArgumentException('Invalid returns agent response payload.');
        }

        $validated = $resultValidator->validated();

        logger()->info('returns-agent.handle', [
            'order_id' => $orderId,
            'eligible' => $eligible,
            'payload' => $validated,
        ]);

        return $validated;
    }

    protected function checkReturnEligibility(string $orderId): bool
    {
        $existingRequest = ReturnRequest::query()
            ->where('order_id', $orderId)
            ->latest('requested_at')
            ->first();

        if (! $existingRequest) {
            return false;
        }

        return $existingRequest->requested_at->diffInDays(now()) <= self::RETURN_WINDOW_DAYS;
    }

    /**
     * @return array{eligible: bool, message: string}
     */
    protected function decodeResponse(mixed $response): array
    {
        if (is_array($response)) {
            return $response;
        }

        $content = trim((string) $response);

        if ($content === '') {
            throw new InvalidArgumentException('Empty returns agent response.');
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException('Invalid JSON from returns agent.');
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Invalid response payload type.');
        }

        return $decoded;
    }

    protected function instructions(): string
    {
        return <<<'PROMPT'
You are a returns and refunds assistant for an e-commerce system.
Return valid JSON only.
No explanations, markdown, or extra text.

Required JSON shape:
{
  "eligible": true or false,
  "message": "string"
}

Rules:
- If eligible is true, message must be: "Return request created successfully"
- If eligible is false, message must be: "Return window has expired"
PROMPT;
    }

    protected function buildPrompt(string $orderId, bool $eligible): string
    {
        $eligibleText = $eligible ? 'true' : 'false';

        return "Process a return request for order_id: {$orderId}. The order is eligible for return: {$eligibleText}. Return only the required JSON shape.";
    }
}
