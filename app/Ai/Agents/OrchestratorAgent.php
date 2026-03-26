<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

use function Laravel\Ai\agent;

class OrchestratorAgent
{
    /**
     * @return array{action: string, parameters: array{order_id: ?string}}
     */
    public function route(string $userInput): array
    {
        $response = agent(
            instructions: $this->routingInstructions(),
            schema: fn (JsonSchema $schema) => [
                'action' => $schema->string()->required(),
                'parameters' => $schema->object([
                    'order_id' => $schema->string()->required(),
                ])->required(),
            ],
        )->prompt($userInput, provider: 'gemini');

        $payload = $this->normalizeRoutingPayload($response);

        logger()->info('orchestrator.route', [
            'user_input' => $userInput,
            'payload' => $payload,
        ]);

        return $payload;
    }

    public function finalize(array $agentResult): string
    {
        $response = agent(
            instructions: $this->finalizeInstructions(),
        )->prompt(json_encode($agentResult, JSON_THROW_ON_ERROR), provider: 'gemini');

        $text = trim((string) $response);

        logger()->info('orchestrator.finalize', [
            'result' => $agentResult,
            'message' => $text,
        ]);

        return $text !== '' ? $text : 'I could not format a response.';
    }

    /**
     * @return array{action: string, parameters: array{order_id: ?string}}
     */
    protected function normalizeRoutingPayload(mixed $response): array
    {
        $decoded = $this->decodeResponse($response);

        $payload = [
            'action' => Arr::get($decoded, 'action'),
            'parameters' => [
                'order_id' => $this->normalizeOrderId(Arr::get($decoded, 'parameters.order_id')),
            ],
        ];

        $validator = Validator::make($payload, [
            'action' => ['required', 'string', 'in:order_status,return_request,discount_check'],
            'parameters' => ['required', 'array'],
            'parameters.order_id' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException('Invalid orchestrator response.');
        }

        return $validator->validated();
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeResponse(mixed $response): array
    {
        if (is_array($response)) {
            return $response;
        }

        $content = trim((string) $response);

        if ($content === '') {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    protected function normalizeOrderId(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        if ($normalized === '' || strtolower($normalized) === 'null') {
            return null;
        }

        return $normalized;
    }

    protected function routingInstructions(): string
    {
        return <<<'PROMPT'
You are an orchestration agent for an e-commerce assistant.
Return valid JSON only.
No prose, no markdown, no explanations.

Output shape:
{
  "action": "order_status | return_request | discount_check",
  "parameters": {
    "order_id": "string|null"
  }
}

Rules:
- Determine the single best action from the allowed values.
- Extract order_id from user input when present.
- If no order_id exists, set order_id to null.
- Return only valid JSON.
PROMPT;
    }

    protected function finalizeInstructions(): string
    {
        return <<<'PROMPT'
You are a customer-facing e-commerce assistant.
You will receive JSON data.
Write one short, natural response for the customer.
Do not invent values.
If data is missing, politely ask for what is needed.
PROMPT;
    }
}
