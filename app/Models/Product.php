<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'quantity',
        'category_id',
    ];

    public function category():BelongTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * filter products by category
     */
    public function scopeCategory($query, $categoryId)
    {
        return $query->where(
            'category_id',
            $categoryId
        );
    }

    /**
     * Search products by name
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(
            'name',
            'ILIKE',
            '%' . $search . '%'
        );
    }

    /**
     * filter products by minimum price
     */
    public function scopeMinPrice($query, $minPrice)
    {
        return $query->where(
            'price', '>=', 
            $minPrice
        );
    }

    /**
     * filter products by maximum price
     */
    public function scopeMaxPrice($query, $maxPrice)
    {
        return $query->where(
            'price', '<=', 
            $maxPrice
        );
    }
}
