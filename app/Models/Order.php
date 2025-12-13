<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 0;

    public const STATUS_FILLED = 1;

    public const STATUS_CANCELLED = 2;

    protected $fillable = [
        'user_id',
        'symbol',
        'side',
        'price',
        'amount',
        'status',
        'locked_usd',
        'locked_asset',
    ];

    protected $casts = [
        'price' => 'decimal:36',
        'amount' => 'decimal:36',
        'locked_usd' => 'decimal:36',
        'locked_asset' => 'decimal:36',
    ];
}
