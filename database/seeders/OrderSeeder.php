<?php

namespace Database\Seeders;

use App\Models\Order;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    private const JOHN_USER_ID = 1;

    public function run(): void
    {
        $orders = [
            [
                'order_number' => 'ORD-001',
                'user_id' => 1,
                'customer_name' => 'John Snow',
                'customer_email' => 'john@snow.com',
                'total_amount' => 129.99,
                'status' => 'shipped',
                'estimated_delivery' => '2026-04-02',
            ],
            [
                'order_number' => 'ORD-002',
                'user_id' => 1,
                'customer_name' => 'John Snow',
                'customer_email' => 'john@snow.com',
                'total_amount' => 249.50,
                'status' => 'processing',
                'estimated_delivery' => '2026-04-05',
            ],
            [
                'order_number' => 'ORD-003',
                'user_id' => 1,
                'customer_name' => 'John Snow',
                'customer_email' => 'john@snow.com',
                'total_amount' => 74.00,
                'status' => 'delivered',
                'estimated_delivery' => '2026-03-20',
            ],
            [
                'order_number' => 'ORD-004',
                'user_id' => 1,
                'customer_name' => 'John Snow',
                'customer_email' => 'john@snow.com',
                'total_amount' => 399.00,
                'status' => 'pending',
                'estimated_delivery' => '2026-04-10',
            ],
            [
                'order_number' => 'ORD-005',
                'user_id' => 1,
                'customer_name' => 'John Snow',
                'customer_email' => 'john@snow.com',
                'total_amount' => 55.75,
                'status' => 'cancelled',
                'estimated_delivery' => null,
            ],
        ];

        foreach ($orders as $order) {

            Order::query()->create($order);
        }
    }
}
