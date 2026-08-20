<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    /**
     * Get all orders.
     */
    public function index(): JsonResponse
    {
        $orders = $this->orderService->getAllOrders();

        return response()->json([
            'message' => 'Orders retrieved successfully.',
            'data' => $orders,
        ]);
    }

    /**
     * Get a single order.
     */
    public function show(
        int $id
    ): JsonResponse {

        $order = $this->orderService->getOrder(
            $id
        );

        return response()->json([
            'message' => 'Order retrieved successfully.',
            'data' => $order,
        ]);
    }

    /**
     * Update order status.
     */
    public function updateStatus(
        Request $request,
        int $id
    ): JsonResponse {

        $validated = $request->validate([
            'status' => [
                'required',
                'in:pending,processing,completed,cancelled',
            ],
        ]);

        $order = $this->orderService->updateOrderStatus(
            $id,
            $validated['status']
        );

        return response()->json([
            'message' => 'Order status updated successfully.',
            'data' => $order,
        ]);
    }
}