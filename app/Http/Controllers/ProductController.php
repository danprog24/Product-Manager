<?php

namespace App\Http\Controllers;
use App\Services\ProductService;
use Illuminate\Http\Request;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Http\Resources\ProductResource;
use App\Http\Traits\ApiResponse;

class ProductController extends Controller
{

    use ApiResponse;

    public function __construct(
    
        private ProductService $productService

    ){}

    public function index(Request $request)
    {
        return $this->successResponse(
            ProductResource::collection(
                $this->productService->getAll($request)
            ),
            'Products retrieved successfully.'
        );
    }

    public function store(StoreProductRequest $request)
    {
        $product = $this->productService->create(
            $request->validated()
        );

        return $this->successResponse(
            new ProductResource($product),
            'Product created successfully.',
            201
        );
    }

    public function show(Product $product)
    {
        return $this->successResponse(
            new ProductResource($product),
            'Product retrieved successfully.'
        );
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $updated = $this->productService->update(
            $product,
            $request->validated()
        );

        return $this->successResponse(
            new ProductResource($updated),
            'Product updated successfully.'
        );
    }

    public function destroy(Product $product)
    {
        $this->productService->delete($product);

        return response()->json([
            'message' => 'Product deleted successfully.'
        ]);
    }
}
