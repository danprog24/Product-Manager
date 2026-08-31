<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class SellerOrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    /**
     * Get orders containing products
     * belonging to the authenticated seller.
     */
    public function index(): JsonResponse
    {
        $orders = $this->orderService->getSellerOrders(
            auth()->id()
        );

        return response()->json([
            'message' => 'Seller orders retrieved successfully.',
            'data' => $orders,
        ]);
    }

    /**
     * Get a single order containing
     * products belonging to the seller.
     */
    public function show(int $id): JsonResponse
    {
        $order = $this->orderService->getSellerOrder(
            $id,
            auth()->id()
        );

        return response()->json([
            'message' => 'Seller order retrieved successfully.',
            'data' => $order,
        ]);
    }
}