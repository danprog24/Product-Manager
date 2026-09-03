<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerWithdrawalItem extends Model
{
    protected $fillable = [
        'seller_withdrawal_id',
        'seller_earning_id',
        'amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(
            SellerWithdrawal::class,
            'seller_withdrawal_id'
        );
    }

    public function earning(): BelongsTo
    {
        return $this->belongsTo(
            SellerEarning::class,
            'seller_earning_id'
        );
    }
}