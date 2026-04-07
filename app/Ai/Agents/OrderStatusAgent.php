<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\DecodesAgentResponse;
use App\Ai\Concerns\ResolvesChatAiTextProviders;
use App\Ai\Tools\OrderStatusTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class OrderStatusAgent implements Agent, HasStructuredOutput
{
    use DecodesAgentResponse, Promptable, ResolvesChatAiTextProviders;

    public function __construct(private OrderStatusTool $orderStatusTool) {}

    /**
     * @return array{status: string, estimated_delivery: string, message: string}
     */
    public function run(array $params): array
    {
        $orderData = $this->orderStatusTool->findByOrderNumber($params['order_id']);

        if (! $orderData) {
            logger()->info('order-status.not-found', ['order_id' => $params['order_id']]);

            return [
                'status' => 'not_found',
                'estimated_delivery' => now()->addDays(7)->format('Y-m-d'),
                'message' => 'We could not find an order with that number. Please double-check the order ID and try again.',
            ];
        }

        $response = $this->prompt($this->buildPrompt($orderData), provider: $this->chatAiTextProviders());

        $payload = $this->decodeResponse(
            response: $response,
            emptyErrorMessage: 'Empty order status response.',
            jsonErrorMessage: 'Invalid JSON from order status agent.',
        );

        $validator = Validator::make($payload, [
            'status' => ['required', 'string'],
            'estimated_delivery' => ['nullable', 'date_format:Y-m-d'],
            'message' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException('Invalid order status payload.');
        }

        logger()->info('order-status.handle', [
            'order_id' => $params['order_id'],
            'payload' => $payload,
        ]);

        return $validator->validated();
    }

    public function instructions(): string
    {
        return <<<'PROMPT'
You are an order status assistant for an e-commerce system.
You will be given real order data from the database.
Based ONLY on the provided data, return a structured JSON response.
Do NOT invent, assume, or hallucinate any values.

Required JSON shape:
{
  "status": "string",
  "estimated_delivery": "YYYY-MM-DD",
  "message": "string"
}

Rules:
- Copy the status and estimated_delivery exactly from the provided order data.
- estimated_delivery must always be a valid YYYY-MM-DD date string.
- message must be a short, customer-facing sentence that reflects only those facts (order status and delivery date). Do not invent details.
- Return only valid JSON, no prose or markdown outside the JSON.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->required(),
            'estimated_delivery' => $schema->string()->required(),
            'message' => $schema->string()->required(),
        ];
    }

    /**
     * @param  array<string, mixed>  $orderData
     */
    protected function buildPrompt(array $orderData): string
    {
        $context = json_encode($orderData, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        return "Here is the real order data from our database:\n\n{$context}\n\nReturn only the required JSON shape using this data.";
    }
}
