<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'quantity',
        'category_id',
        'user_id',
        'image_url',
        'image_public_id',
    ];


    // Relationships
    public function category():BelongTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
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
