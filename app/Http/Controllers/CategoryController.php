<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Http\Traits\ApiResponse;

class CategoryController extends Controller
{
    use ApiResponse;

    public function index()
    {
        return $this->successResponse(
            Category::query()
                ->orderBy('name')
                ->get(),
            'Categories retrieved successfully.'
        );
    }
}