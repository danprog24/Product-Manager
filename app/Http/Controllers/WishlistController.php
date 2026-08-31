<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Get authenticated user's wishlist.
     */
    public function index(Request $request): JsonResponse
    {
        $wishlists = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->with('product')
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Wishlist retrieved successfully.',
            'data' => $wishlists,
        ]);
    }

    /**
     * Add product to wishlist.
     */
    public function store(
        Request $request,
        Product $product
    ): JsonResponse {

        $user = $request->user();

        $wishlist = Wishlist::firstOrCreate([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $wishlist->load('product');

        return response()->json([
            'message' => 'Product added to wishlist.',
            'data' => $wishlist,
        ], 201);
    }

    /**
     * Remove product from wishlist.
     */
    public function destroy(
        Request $request,
        Product $product
    ): JsonResponse {

        Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->delete();

        return response()->json([
            'message' => 'Product removed from wishlist.',
        ]);
    }
}