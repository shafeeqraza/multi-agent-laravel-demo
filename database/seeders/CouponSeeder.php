<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $coupons = [
            [
                'code' => 'WELCOME10',
                'discount' => '10%',
                'is_active' => true,
                'expires_at' => null,
            ],
            [
                'code' => 'SUMMER20',
                'discount' => '20%',
                'is_active' => true,
                'expires_at' => now()->addMonths(3),
            ],
            [
                'code' => 'FLASH50',
                'discount' => '50%',
                'is_active' => true,
                'expires_at' => now()->addDays(2),
            ],
            [
                'code' => 'LOYAL15',
                'discount' => '15%',
                'is_active' => true,
                'expires_at' => now()->addYear(),
            ],
            [
                'code' => 'EXPIRED25',
                'discount' => '25%',
                'is_active' => false,
                'expires_at' => now()->subMonth(),
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::query()->updateOrCreate(
                ['code' => $coupon['code']],
                $coupon,
            );
        }
    }
}
