<?php

namespace App\Ai\Tools;

use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class OrderStatusTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Retrieve order details by order number.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'order_number' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): string
    {
        return json_encode($this->findByOrderNumber((string) $request['order_number']), JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByOrderNumber(string $orderNumber): ?array
    {
        $order = Order::query()->where('order_number', $orderNumber)->orWhere('id', $orderNumber)->first();

        if (! $order) {
            return null;
        }

        return [
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'status' => $order->status,
            'estimated_delivery' => $order->getRawOriginal('estimated_delivery'),
            'total_amount' => (float) $order->total_amount,
        ];
    }
}
