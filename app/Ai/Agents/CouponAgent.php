<?php

namespace App\Ai\Agents;

use App\Models\Coupon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

use function Laravel\Ai\agent;

class CouponAgent
{
    private const FALLBACK_CODE = 'WELCOME10';

    private const FALLBACK_DISCOUNT = '10%';

    /**
     * @return array{code: string, discount: string}
     */
    public function handle(): array
    {
        $coupon = Coupon::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->inRandomOrder()
            ->first();

        $code = $coupon?->code ?? self::FALLBACK_CODE;
        $discount = $coupon ? (string) $coupon->discount : self::FALLBACK_DISCOUNT;

        $response = agent(
            instructions: $this->instructions(),
            schema: fn (JsonSchema $schema) => [
                'code' => $schema->string()->required(),
                'discount' => $schema->string()->required(),
            ],
        )->prompt($this->buildPrompt($code, $discount), provider: 'gemini');

        $payload = $this->decodeResponse($response);

        $validator = Validator::make($payload, [
            'code' => ['required', 'string'],
            'discount' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException('Invalid coupon agent response payload.');
        }

        $validated = $validator->validated();

        logger()->info('coupon-agent.handle', [
            'coupon_found' => $coupon !== null,
            'payload' => $validated,
        ]);

        return $validated;
    }

    /**
     * @return array{code: string, discount: string}
     */
    protected function decodeResponse(mixed $response): array
    {
        if (is_array($response)) {
            return $response;
        }

        $content = trim((string) $response);

        if ($content === '') {
            throw new InvalidArgumentException('Empty coupon agent response.');
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException('Invalid JSON from coupon agent.');
        }

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Invalid response payload type.');
        }

        return $decoded;
    }

    protected function instructions(): string
    {
        return <<<'PROMPT'
You are a discount and promotions assistant for an e-commerce system.
Return valid JSON only.
No explanations, markdown, or extra text.

Required JSON shape:
{
  "code": "string",
  "discount": "string"
}

Rules:
- Return the exact coupon code and discount value provided to you.
- Do not invent or modify coupon codes or discount values.
PROMPT;
    }

    protected function buildPrompt(string $code, string $discount): string
    {
        return "Return a coupon for the customer. Coupon code: {$code}. Discount: {$discount}. Return only the required JSON shape.";
    }
}
