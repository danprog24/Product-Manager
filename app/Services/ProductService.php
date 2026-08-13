<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\Request;
use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;

class ProductService
{
    public function getAll(Request $request)
    {
        $query = Product::query();

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('category_id')) {
            $query->category($request->category_id);
        }

        if ($request->filled('min_price')) {
            $query->minPrice($request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->maxPrice($request->input('max_price'));
        }

        return $query->paginate(
            $request->input('per_page', 10)
        );
    }

    public function create(array $data)
    {
        if (isset($data['image'])) {
            $imageData = $this->uploadImage($data['image']);

            $data['image_url'] = $imageData['url'];
            $data['image_public_id'] = $imageData['public_id'];

            unset($data['image']);
        }

        return Product::create($data);
    }

    public function update(Product $product, array $data)
    {
        if (isset($data['image'])) {

            //uploading the new image to cloudinary
            $imageData = $this->uploadImage($data['image']);
           
            // deleting the old image from cloudinary
            if ($product->image_public_id){
                $this->deleteImage($product->image_public_id);
            }

           

            $data['image_url'] = $imageData['url'];
            $data['image_public_id'] = $imageData['public_id'];

            unset($data['image']);

        }

        $product->update($data);

        return $product;
    }

    public function delete(Product $product)
    {

        if ($product->image_public_id) {
            $this->deleteImage($product->image_public_id);
        }

        return $product->delete();
    }

    public function getProductsByUser($user)
    {
        return $user->products()->paginate(
            request()->input('per_page', 10)
        );
    }

    public function uploadImage(UploadedFile $image): array
    {
        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
        ]);

        $result = $cloudinary->uploadApi()->upload(
            $image->getRealPath(),
            [
                'folder' => 'products',
                'transformation' => [
                    'width' => 800,
                    'height' => 800,
                    'crop' => 'limit',
                ],
            ]
        );

        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }

    // Delete image from Cloudinary
    public function deleteImage(string $publicId): void
    {
        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
        ]);

        $cloudinary->uploadApi()->destroy($publicId);
    }
}