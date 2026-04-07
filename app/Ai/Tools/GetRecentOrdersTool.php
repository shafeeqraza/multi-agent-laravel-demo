<?php

namespace App\Ai\Tools;

use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetRecentOrdersTool implements Tool
{
    private const DEFAULT_LIMIT = 5;

    private const MAX_LIMIT = 10;

    public function __construct(protected int $userId) {}

    public function name(): string
    {
        return 'get_recent_orders';
    }

    public function description(): Stringable|string
    {
        return 'Returns the authenticated customer\'s most recent orders (id, order_number, status, created_at). Limit 1–10; default 5.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->default(self::DEFAULT_LIMIT)->required(),
        ];
    }

    public function handle(Request $request): string
    {
        if ($this->userId === 0) {
            return json_encode([], JSON_THROW_ON_ERROR);
        }

        $limit = $this->normalizeLimit($request['limit'] ?? null);

        $orders = Order::query()
            ->where('user_id', $this->userId)
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'order_number', 'status', 'created_at']);

        $payload = $orders->map(fn (Order $order): array => [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'created_at' => $order->created_at?->toIso8601String(),
        ])->values()->all();

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    private function normalizeLimit(mixed $value): int
    {
        if ($value === null || $value === '') {
            return self::DEFAULT_LIMIT;
        }

        $limit = is_numeric($value) ? (int) $value : self::DEFAULT_LIMIT;

        return max(1, min(self::MAX_LIMIT, $limit));
    }
}
