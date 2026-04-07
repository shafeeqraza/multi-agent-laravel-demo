<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\DecodesAgentResponse;
use App\Ai\Concerns\ResolvesChatAiTextProviders;
use App\Ai\Tools\CouponTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class CouponAgent implements Agent, HasStructuredOutput
{
    use DecodesAgentResponse, Promptable, ResolvesChatAiTextProviders;

    public function __construct(private CouponTool $couponTool) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{code: string, discount: string, message: string}
     */
    public function run(array $params): array
    {
        return $this->handle();
    }

    /**
     * @return array{code: string, discount: string, message: string}
     */
    public function handle(): array
    {
        $couponData = $this->couponTool->resolveCoupon();

        $response = $this->prompt($this->buildPrompt($couponData['code'], $couponData['discount']), provider: $this->chatAiTextProviders());

        $payload = $this->decodeResponse(
            response: $response,
            emptyErrorMessage: 'Empty coupon agent response.',
            jsonErrorMessage: 'Invalid JSON from coupon agent.',
        );

        $validator = Validator::make($payload, [
            'code' => ['required', 'string'],
            'discount' => ['required', 'string'],
            'message' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw new InvalidArgumentException('Invalid coupon agent response payload.');
        }

        $validated = $validator->validated();

        logger()->info('coupon-agent.handle', [
            'coupon_found' => $couponData['coupon_found'],
            'payload' => $validated,
        ]);

        return $validated;
    }

    public function instructions(): string
    {
        return <<<'PROMPT'
You are a discount and promotions assistant for an e-commerce system.
Return valid JSON only.
No explanations, markdown, or extra text.

Required JSON shape:
{
  "code": "string",
  "discount": "string",
  "message": "string"
}

Rules:
- Return the exact coupon code and discount value provided to you.
- Do not invent or modify coupon codes or discount values.
- message must be one or two friendly sentences for the customer that include the exact code and discount strings above.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->required(),
            'discount' => $schema->string()->required(),
            'message' => $schema->string()->required(),
        ];
    }

    protected function buildPrompt(string $code, string $discount): string
    {
        return "Return a coupon for the customer. Coupon code: {$code}. Discount: {$discount}. Return only the required JSON shape.";
    }
}
