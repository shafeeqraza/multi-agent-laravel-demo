<?php

namespace App\Ai\Agents;

use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

use function Laravel\Ai\agent;

class OrderStatusAgent
{
    /**
     * @return array{status: string, estimated_delivery: string}
     */
    public function handle(string $orderId): array
    {
        $order = Order::where('order_number', $orderId)->first();

        if (! $order) {
            logger()->info('order-status.not-found', ['order_id' => $orderId]);

            return [
                'status' => 'not_found',
                'estimated_delivery' => now()->addDays(7)->format('Y-m-d'),
            ];
        }

        $orderData = [
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'status' => $order->status,
            'estimated_delivery' => $order->getRawOriginal('estimated_delivery'),
            'total_amount' => (float) $order->total_amount,
        ];

        $response = agent(
            instructions: $this->instructions(),
            schema: fn (JsonSchema $schema) => [
                'status' => $schema->string()->required(),
                'estimated_delivery' => $schema->string()->required(),
            ],
        )->prompt($this->buildPrompt($orderData), provider: 'gemini');

        $payload = $this->decodeResponse($response);

        $validator = Validator::make($payload, [
            'status' => ['required', 'string'],
            'estimated_delivery' => ['required', 'date_format:Y-m-d'],
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException('Invalid order status payload.');
        }

        logger()->info('order-status.handle', [
            'order_id' => $orderId,
            'payload' => $payload,
        ]);

        return $validator->validated();
    }

    /**
     * @return array{status: string, estimated_delivery: string}
     */
    protected function decodeResponse(mixed $response): array
    {
        if (is_array($response)) {
            return $response;
        }

        $content = trim((string) $response);

        if ($content === '') {
            throw new InvalidArgumentException('Empty order status response.');
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException('Invalid JSON from order status agent.');
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Invalid response payload type.');
        }

        return $decoded;
    }

    protected function instructions(): string
    {
        return <<<'PROMPT'
You are an order status assistant for an e-commerce system.
You will be given real order data from the database.
Based ONLY on the provided data, return a structured JSON response.
Do NOT invent, assume, or hallucinate any values.

Required JSON shape:
{
  "status": "string",
  "estimated_delivery": "YYYY-MM-DD"
}

Rules:
- Copy the status and estimated_delivery exactly from the provided order data.
- estimated_delivery must always be a valid YYYY-MM-DD date string.
- Return only valid JSON, no prose or markdown.
PROMPT;
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
