<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Services\OrderService;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private PaystackService $paystackService
    ) {}

    // Handle checkout and initialize Paystack payment
    public function checkout(
        CheckoutRequest $request
    ): JsonResponse {

        // Create the order
       $validated = $request->validated();

        $order = $this->orderService->createOrder(
            $validated['items'],
            auth()->id(),
            [
                'shipping_name' => $validated['shipping_name'],
                'shipping_phone' => $validated['shipping_phone'],
                'shipping_address' => $validated['shipping_address'],
                'shipping_city' => $validated['shipping_city'],
                'shipping_state' => $validated['shipping_state'],
            ]
        );

        // Paystack expects the amount in kobo
        $amount = (int) round(
            $order->total_amount * 100
        );

        // Initialize Paystack transaction
        $payment = $this->paystackService->initialize(
            auth()->user()->email,
            $amount,
            $order->reference
        );

        return response()->json([
            'message' => 'Checkout initialized successfully.',

            'data' => [
                'order' => $order,

                'payment' => [
                    'authorization_url' =>
                        $payment['authorization_url'],

                    'access_code' =>
                        $payment['access_code'],

                    'reference' =>
                        $payment['reference'],
                ],
            ],
        ], 201);
    }

    //
    public function verifyPayment(string $reference): JsonResponse
    {
        $payment = $this->paystackService->verify($reference);

        if (
            !isset($payment['status']) ||
            $payment['status'] !== 'success'
        ) {
            return response()->json([
                'message' => 'Payment was not successful.',
            ], 400);
        }

        $order = $this->orderService->markAsPaid(
            $reference,
            auth()->id()
        );

        return response()->json([
            'message' => 'Payment verified successfully.',
            'data' => [
                'order' => $order,
            ],
        ]);
    }

    // Get all orders for authenticated user
    public function myOrders(): JsonResponse
    {
        $orders = $this->orderService->getUserOrders(
            auth()->id()
        );

        return response()->json([
            'message' => 'Orders retrieved successfully.',
            'data' => $orders,
        ]);
    }

    // Get a single order
    public function show(int $id): JsonResponse
    {
        $order = $this->orderService->getUserOrder(
            $id,
            auth()->id()
        );

        return response()->json([
            'message' => 'Order retrieved successfully.',
            'data' => $order,
        ]);
    }

    public function updateStatus(
        Request $request,
        int $id
    ): JsonResponse {
        $request->validate([
            'status' => [
                'required',
                'in:processing,completed,cancelled',
            ],
        ]);

        $order = $this->orderService->updateOrderStatus(
            $id,
            $request->status
        );

        return response()->json([
            'message' => 'Order status updated successfully.',
            'data' => $order,
        ]);
    }

    public function allOrders(): JsonResponse
    {
        $orders = $this->orderService->getAllOrders();

        return response()->json([
            'message' => 'Orders retrieved successfully.',
            'data' => $orders,
        ]);
    }
    
}