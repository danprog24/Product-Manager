<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProductController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\SellerOrderController;
use App\Http\Controllers\SellerWalletController;


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
// Public Routes
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

    // =================================================
    // Profile
    // =================================================

    Route::get(
        'profile',
        [AuthController::class, 'profile']
    );

    Route::post(
        'logout',
        [AuthController::class, 'logout']
    );


    // =================================================
    // Checkout
    // =================================================

    Route::post(
        'checkout',
        [OrderController::class, 'checkout']
    );


    // =================================================
    // Payment Verification
    // =================================================

    Route::post(
        'orders/payment/verify/{reference}',
        [OrderController::class, 'verifyPayment']
    );


    // =================================================
    // Buyer Orders
    // =================================================

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

    // =================================================
    // Product Management
    // =================================================

    Route::apiResource(
        'products',
        ProductController::class
    )->only([
        'store',
        'update',
        'destroy',
    ]);


    // =================================================
    // Seller Products
    // =================================================

    Route::get(
        'my-products',
        [ProductController::class, 'myProducts']
    );
});


// =====================================================
// Seller
// =====================================================

Route::middleware([
    'auth:api',
    'role:seller',
])->prefix('seller')->group(function () {

    // =================================================
    // Seller Orders
    // =================================================

    Route::get(
        'orders',
        [SellerOrderController::class, 'index']
    );

    Route::get(
        'orders/{id}',
        [SellerOrderController::class, 'show']
    );


    // =================================================
    // Seller Wallet
    // =================================================

    Route::get(
        'wallet',
        [SellerWalletController::class, 'wallet']
    );


    // =================================================
    // Seller Banks
    // =================================================

    Route::get(
        'banks',
        [SellerWalletController::class, 'banks']
    );


    // =================================================
    // Verify Seller Bank Account
    // =================================================

    Route::post(
        'bank-account/verify',
        [SellerWalletController::class, 'verifyAccount']
    );


    // =================================================
    // Seller Withdrawals
    // =================================================

    Route::post(
        'withdrawals',
        [SellerWalletController::class, 'withdraw']
    );

    Route::get(
        'withdrawals',
        [SellerWalletController::class, 'withdrawals']
    );

    Route::get(
        'withdrawals/{id}',
        [SellerWalletController::class, 'showWithdrawal']
    );
});


// =====================================================
// Admin
// =====================================================

Route::middleware([
    'auth:api',
    'role:admin',
])->prefix('admin')->group(function () {

    // =================================================
    // Admin Orders
    // =================================================

    Route::get(
        'orders',
        [AdminOrderController::class, 'index']
    );

    Route::get(
        'orders/{id}',
        [AdminOrderController::class, 'show']
    );

    Route::patch(
        'orders/{id}/status',
        [AdminOrderController::class, 'updateStatus']
    );
});


// =====================================================
// Buyer Wishlist
// =====================================================

Route::middleware([
    'auth:api',
    'role:buyer',
])->group(function () {

    Route::get(
        'wishlist',
        [WishlistController::class, 'index']
    );

    Route::post(
        'wishlist/{product}',
        [WishlistController::class, 'store']
    );

    Route::delete(
        'wishlist/{product}',
        [WishlistController::class, 'destroy']
    );
});