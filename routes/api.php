<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MyProductController;

// Authentication
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// Public
Route::apiResource('products', ProductController::class)
    ->only(['index', 'show']);

Route::get('categories', [CategoryController::class, 'index']);

// Protected
// Any authenticated user
Route::middleware('auth:api')->group(function () {

    Route::get('profile', [AuthController::class, 'profile']);

    Route::post('logout', [AuthController::class, 'logout']);

});

// Admin + Seller
Route::middleware(['auth:api', 'role:admin,seller'])->group(function () {

    Route::apiResource('products', ProductController::class)
        ->only(['store', 'update', 'destroy']);

    Route::get('my-products', [ProductController::class, 'myProducts']);

});