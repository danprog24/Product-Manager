<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// class Order extends Model
// {
//     protected $fillable = [
//         'user_id',
//         'reference',
//         'total_amount',
//         'status',
//         'payment_method',
//         'payment_status',
//     ];

//     public function user(): BelongsTo
//     {
//         return $this->belongsTo(User::class);
//     }

//     public function items(): HasMany
//     {
//         return $this->hasMany(OrderItem::class);
//     }
// }



class Order extends Model
{
    protected $fillable = [
        'user_id',
        'reference',
        'total_amount',
        'status',
        'payment_method',
        'payment_status',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}