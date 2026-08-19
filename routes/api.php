<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaystackWebhookController;

// =====================================================
// Authentication
// =====================================================

Route::post(
    'register',
    [AuthController::class, 'register']
);

Route::post(
    'login',
    [AuthController::class, 'login']
);


// =====================================================
// Public
// =====================================================

Route::apiResource(
    'products',
    ProductController::class
)->only([
    'index',
    'show',
]);

Route::get(
    'categories',
    [CategoryController::class, 'index']
);


// =====================================================
// Paystack Webhook
// =====================================================

Route::post(
    'payments/paystack/webhook',
    [PaystackWebhookController::class, 'handle']
);


// =====================================================
// Authenticated Users
// =====================================================

Route::middleware('auth:api')->group(function () {

    // Authentication

    Route::get(
        'profile',
        [AuthController::class, 'profile']
    );

    Route::post(
        'logout',
        [AuthController::class, 'logout']
    );


    // Checkout

    Route::post(
        'checkout',
        [OrderController::class, 'checkout']
    );


    // Payment verification

    Route::post(
        'orders/payment/verify/{reference}',
        [OrderController::class, 'verifyPayment']
    );


    // Orders

    Route::get(
        'orders',
        [OrderController::class, 'myOrders']
    );

    Route::get(
        'orders/{id}',
        [OrderController::class, 'show']
    );
});


// =====================================================
// Admin + Seller
// =====================================================

Route::middleware([
    'auth:api',
    'role:admin,seller',
])->group(function () {

    Route::apiResource(
        'products',
        ProductController::class
    )->only([
        'store',
        'update',
        'destroy',
    ]);

    Route::get(
        'my-products',
        [ProductController::class, 'myProducts']
    );
});