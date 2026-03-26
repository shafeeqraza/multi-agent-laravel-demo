<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'customer_name',
        'customer_email',
        'total_amount',
        'status',
        'estimated_delivery',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'estimated_delivery' => 'date:Y-m-d',
        ];
    }
}
