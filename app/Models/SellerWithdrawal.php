<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerWithdrawal extends Model
{
    protected $fillable = [
        'seller_id',
        'amount',
        'status',
        'reference',

        'bank_name',
        'bank_code',
        'account_name',
        'account_number',

        'paystack_recipient_code',
        'paystack_transfer_reference',
        'paystack_transfer_code',
        'paystack_transfer_id',

        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'seller_id'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            SellerWithdrawalItem::class
        );
    }
}