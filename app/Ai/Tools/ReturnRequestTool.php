<?php

namespace App\Ai\Tools;

use App\Models\Order;
use App\Models\ReturnRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ReturnRequestTool implements Tool
{
    private const RETURN_WINDOW_DAYS = 7;

    public function description(): Stringable|string
    {
        return 'Check return eligibility and create pending return requests.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'order_id' => $schema->string()->required(),
            'create_when_eligible' => $schema->boolean(),
        ];
    }

    public function handle(Request $request): string
    {
        $orderId = (string) $request['order_id'];
        $eligible = $this->isEligible($orderId);
        $createWhenEligible = (bool) ($request['create_when_eligible'] ?? false);

        if ($eligible && $createWhenEligible) {
            $this->createPending($orderId);
        }

        return json_encode([
            'eligible' => $eligible,
            'created' => $eligible && $createWhenEligible,
        ], JSON_THROW_ON_ERROR);
    }

    public function isEligible(string $orderId): bool
    {
        $order = Order::query()->where('id', $orderId)->Orwhere('order_number', $orderId)->first('created_at');

        if (! $order) {
            return false;
        }

        $orderIsWithinReturnWindow = $order->created_at->diffInDays(now()) <= self::RETURN_WINDOW_DAYS;

        if (! $orderIsWithinReturnWindow) {
            return false;
        }

        $existingRequest = ReturnRequest::query()
            ->where('order_id', $orderId)
            ->latest('requested_at')
            ->first();

        if ($existingRequest) {
            return false;
        }
        return true;
    }

    public function createPending(string $orderId): void
    {
        ReturnRequest::query()->create([
            'order_id' => $orderId,
            'status' => 'pending',
            'requested_at' => now(),
        ]);
    }
}
