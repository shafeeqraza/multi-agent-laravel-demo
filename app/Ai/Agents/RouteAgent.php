<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class RouteAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
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

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->required(),
            'parameters' => $schema->object([
                'order_id' => $schema->string()->required(),
            ])->withoutAdditionalProperties()->required(),
        ];
    }
}
