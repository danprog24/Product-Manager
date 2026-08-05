<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductService
{
    public function getAll(Request $request)
    {
        $query = Product::query();

        if ($request->filled('search')) {
            $query->search(
                $request->search
            );
        }

        if ($request->filled('category_id')) {
            $query->category(
                $request->category_id
            );
        }

        if ($request->filled('min_price')) {
            $query->minPrice(
                $request->input('min_price')
            );
        }

        if ($request->filled('max_price')) {
            $query->maxPrice(
                $request->input('max_price')
            );
        }

      

        return $query->paginate($request->input('per_page', 10));
    }

    public function create(array $data)
    {
        return auth()->user()->products()->create($data);
    }

    public function update(Product $product, array $data)
    {
        $product->update($data);

        return $product;
    }

    public function delete(Product $product)
    {
        return $product->delete();
    }
}