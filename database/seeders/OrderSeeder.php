<?php

namespace Database\Seeders;

use App\Models\Order;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $orders = [
            [
                'order_number' => 'ORD-001',
                'customer_name' => 'Alice Johnson',
                'customer_email' => 'alice@example.com',
                'total_amount' => 129.99,
                'status' => 'shipped',
                'estimated_delivery' => '2026-04-02',
            ],
            [
                'order_number' => 'ORD-002',
                'customer_name' => 'Bob Smith',
                'customer_email' => 'bob@example.com',
                'total_amount' => 249.50,
                'status' => 'processing',
                'estimated_delivery' => '2026-04-05',
            ],
            [
                'order_number' => 'ORD-003',
                'customer_name' => 'Carol White',
                'customer_email' => 'carol@example.com',
                'total_amount' => 74.00,
                'status' => 'delivered',
                'estimated_delivery' => '2026-03-20',
            ],
            [
                'order_number' => 'ORD-004',
                'customer_name' => 'David Brown',
                'customer_email' => 'david@example.com',
                'total_amount' => 399.00,
                'status' => 'pending',
                'estimated_delivery' => '2026-04-10',
            ],
            [
                'order_number' => 'ORD-005',
                'customer_name' => 'Eva Green',
                'customer_email' => 'eva@example.com',
                'total_amount' => 55.75,
                'status' => 'cancelled',
                'estimated_delivery' => null,
            ],
        ];

        foreach ($orders as $order) {
            Order::query()->updateOrCreate(
                ['order_number' => $order['order_number']],
                $order,
            );
        }
    }
}
