<?php

namespace App\Ai\Tools;

use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ResolveOrderReferenceTool implements Tool
{
    /**
     * @var list<string>
     */
    private const VALID_TOKENS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
        'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun',
    ];

    /**
     * @var array<string, string>
     */
    private const TOKEN_TO_FULL = [
        'mon' => 'monday',
        'tue' => 'tuesday',
        'wed' => 'wednesday',
        'thu' => 'thursday',
        'fri' => 'friday',
        'sat' => 'saturday',
        'sun' => 'sunday',
    ];

    public function __construct(protected int $userId) {}

    public function name(): string
    {
        return 'resolve_order_reference';
    }

    public function description(): Stringable|string
    {
        return 'Maps a fuzzy phrase to the customer\'s order id and order_number. Pass reference (user text). Pass day as empty string unless they mention a weekday (monday, fri, etc.); then set day to that weekday so the correct calendar day is used.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reference' => $schema->string()->required(),
            'day' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): string
    {
        if ($this->userId === 0) {
            return json_encode(['order_id' => null, 'order_number' => null], JSON_THROW_ON_ERROR);
        }

        $reference = mb_strtolower(trim((string) $request['reference']));
        $dayArg = mb_strtolower(trim((string) ($request['day'] ?? '')));

        $order = $this->resolveOrderForRequest($reference, $dayArg);

        $payload = $order === null
            ? ['order_id' => null, 'order_number' => null]
            : [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ];

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    private function resolveOrderForRequest(string $reference, string $dayArg): ?Order
    { 
        $token = $dayArg !== '' ? $dayArg : $this->extractDayFromReference($reference);

        if ($token !== null) {
            try {
                $normalized = $this->normalizeWeekdayInput($token);
            } catch (InvalidArgumentException) {
                if ($dayArg !== '') {
                    throw new InvalidArgumentException(
                        "Invalid day '{$dayArg}'. Use a weekday such as monday, tuesday, or fri."
                    );
                }

                $normalized = null;
            }

            if ($normalized !== null) {
                return $this->resolveOrderOnLastWeekday($normalized) ?? $this->resolveLatestOrder();
            }
        }

        return $this->resolveLatestOrder();
    }

    /**
     * Most recent occurrence of the weekday in the app timezone: [startOfDay, endOfDay].
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function getLastDayDate(string $day): array
    {
        $normalized = $this->normalizeWeekdayInput($day);
        $tz = (string) config('app.timezone');
        $anchor = Carbon::parse('last '.$normalized, $tz)->startOfDay();
        $end = $anchor->copy()->endOfDay();

        return [$anchor, $end];
    }

    /**
     * @throws InvalidArgumentException
     */
    private function normalizeWeekdayInput(string $day): string
    {
        $day = strtolower(trim($day));

        if (! in_array($day, self::VALID_TOKENS, true)) {
            throw new InvalidArgumentException("Invalid weekday token: {$day}");
        }

        return self::TOKEN_TO_FULL[$day] ?? $day;
    }

    private function extractDayFromReference(string $reference): ?string
    {
        $tokens = self::VALID_TOKENS;
        usort($tokens, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($tokens as $token) {
            if (str_contains($reference, $token)) {
                return $token;
            }
        }

        return null;
    }

    private function resolveOrderOnLastWeekday(string $normalizedDay): ?Order
    {
        [$start, $end] = $this->getLastDayDate($normalizedDay);

        return Order::query()
            ->where('user_id', $this->userId)
            ->whereBetween('created_at', [$start, $end])
            ->latest('id')
            ->first(['id', 'order_number']);
    }

    private function resolveLatestOrder(): ?Order
    {
        return Order::query()
            ->where('user_id', $this->userId)
            ->latest('id')
            ->first(['id', 'order_number']);
    }
}
