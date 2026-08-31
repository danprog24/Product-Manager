<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    /**
     * Create a new order.
     */
    public function createOrder(
        array $items,
        int $userId,
        array $shippingData
    ): Order {
        return DB::transaction(function () use (
            $items,
            $userId,
            $shippingData
        ) {

            $totalAmount = 0;

            $order = Order::create([
                'user_id' => $userId,
                'reference' => 'ORD-' . Str::upper(
                    Str::random(16)
                ),
                'total_amount' => 0,
                'status' => 'pending',
                'payment_method' => 'paystack',
                'payment_status' => 'unpaid',

                // Shipping information
                'shipping_name' =>
                    $shippingData['shipping_name'],

                'shipping_phone' =>
                    $shippingData['shipping_phone'],

                'shipping_address' =>
                    $shippingData['shipping_address'],

                'shipping_city' =>
                    $shippingData['shipping_city'],

                'shipping_state' =>
                    $shippingData['shipping_state'],
            ]);

            foreach ($items as $item) {

                $product = Product::findOrFail(
                    $item['product_id']
                );

                if (
                    $product->quantity <
                    $item['quantity']
                ) {
                    throw new \Exception(
                        "Insufficient stock for {$product->name}."
                    );
                }

                $quantity = $item['quantity'];
                $unitPrice = $product->price;
                $subtotal = $unitPrice * $quantity;

                $totalAmount += $subtotal;

                $order->items()->create([
                    'product_id' => $product->id,
                    'seller_id' => $product->user_id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);
            }

            $order->update([
                'total_amount' => $totalAmount,
            ]);

            return $order->load(
                'items.product'
            );
        });
    }

    /**
     * Get orders belonging to a user.
     */
    public function getUserOrders(int $userId)
    {
        return Order::query()
            ->where('user_id', $userId)
            ->with('items.product')
            ->latest()
            ->get();
    }

    /**
     * Get a specific user order.
     */
    public function getUserOrder(
        int $orderId,
        int $userId
    ) {
        return Order::query()
            ->where('id', $orderId)
            ->where('user_id', $userId)
            ->with('items.product')
            ->firstOrFail();
    }

    /**
     * Get user order by payment reference.
     */
    public function getUserOrderByReference(
        string $reference,
        int $userId
    ): Order {
        return Order::query()
            ->where('reference', $reference)
            ->where('user_id', $userId)
            ->with('items.product')
            ->firstOrFail();
    }

    /**
     * Mark an order as paid after Paystack verification.
     */
    public function markAsPaid(
        string $reference,
        int $userId
    ): Order {

        $order = $this->getUserOrderByReference(
            $reference,
            $userId
        );

        /*
         * Prevent processing the same payment twice.
         */
        if ($order->payment_status === 'paid') {
            return $order;
        }

        $this->completeOrder($order);

        return $order->fresh(
            'items.product'
        );
    }

    /**
     * Complete the payment processing.
     *
     * This deducts stock and marks the payment
     * as paid.
     */
    public function completeOrder(
        Order $order
    ): void {

        DB::transaction(function () use ($order) {

            if ($order->payment_status === 'paid') {
                return;
            }

            $order->load('items');

            foreach ($order->items as $item) {

                $product = Product::lockForUpdate()
                    ->findOrFail(
                        $item->product_id
                    );

                if (
                    $product->quantity <
                    $item->quantity
                ) {
                    throw new \Exception(
                        "Insufficient stock for {$product->name}."
                    );
                }

                $product->decrement(
                    'quantity',
                    $item->quantity
                );
            }

            $order->update([
                'payment_status' => 'paid',
                'status' => 'processing',
            ]);
        });
    }

    /**
     * Get all orders for admin.
     */
    public function getAllOrders()
    {
        return Order::query()
            ->with([
                'user',
                'items.product',
            ])
            ->latest()
            ->get();
    }

    /**
     * Get any order by ID for admin.
     */
    public function getOrder(
        int $orderId
    ): Order {
        return Order::query()
            ->with([
                'user',
                'items.product',
            ])
            ->findOrFail($orderId);
    }

    /**
     * Update order status.
     */
    public function updateOrderStatus(
        int $orderId,
        string $status
    ): Order {

        $order = Order::findOrFail(
            $orderId
        );

        $order->update([
            'status' => $status,
        ]);

        return $order->fresh([
            'user',
            'items.product',
        ]);
    }

    /**
     * Get orders containing products
     * belonging to the authenticated seller.
     */
    public function getSellerOrders(int $sellerId)
    {
        return Order::query()
            ->whereHas('items', function ($query) use ($sellerId) {
                $query->where('seller_id', $sellerId);
            })
            ->with([
                'user',
                'items' => function ($query) use ($sellerId) {
                    $query->where('seller_id', $sellerId)
                        ->with('product');
                },
            ])
            ->latest()
            ->get();
    }


    /**
     * Get a specific order containing
     * products belonging to the seller.
     */
    public function getSellerOrder(
        int $orderId,
        int $sellerId
    ): Order {
        return Order::query()
            ->where('id', $orderId)
            ->whereHas('items', function ($query) use ($sellerId) {
                $query->where('seller_id', $sellerId);
            })
            ->with([
                'user',
                'items' => function ($query) use ($sellerId) {
                    $query->where('seller_id', $sellerId)
                        ->with('product');
                },
            ])
            ->firstOrFail();
    }

}