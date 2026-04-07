<?php

namespace App\Ai\Tools;

use App\Models\Coupon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CouponTool implements Tool
{
    private const FALLBACK_CODE = 'WELCOME10';

    private const FALLBACK_DISCOUNT = '10%';

    public function description(): Stringable|string
    {
        return 'Resolve an active coupon or provide fallback coupon details.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        return json_encode($this->resolveCoupon(), JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{code: string, discount: string, coupon_found: bool}
     */
    public function resolveCoupon(): array
    {
        $coupon = Coupon::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->inRandomOrder()
            ->first();

        return [
            'code' => $coupon?->code ?? self::FALLBACK_CODE,
            'discount' => $coupon ? (string) $coupon->discount : self::FALLBACK_DISCOUNT,
            'coupon_found' => $coupon !== null,
        ];
    }
}
