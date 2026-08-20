<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function createOrder(array $items, int $userId): Order
    {
        return DB::transaction(function () use ($items, $userId) {

            $totalAmount = 0;

            $order = Order::create([
                'user_id' => $userId,
                'reference' => 'ORD-' . Str::upper(Str::random(16)),
                'total_amount' => 0,
                'status' => 'pending',
                'payment_method' => 'paystack',
                'payment_status' => 'unpaid',
            ]);

            foreach ($items as $item) {

                $product = Product::findOrFail(
                    $item['product_id']
                );

                if ($product->quantity < $item['quantity']) {
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

            return $order->load('items.product');
        });
    }

    public function getUserOrders(int $userId)
    {
        return Order::query()
            ->where('user_id', $userId)
            ->with('items.product')
            ->latest()
            ->get();
    }

    public function getUserOrder(int $orderId, int $userId)
    {
        return Order::query()
            ->where('id', $orderId)
            ->where('user_id', $userId)
            ->with('items.product')
            ->firstOrFail();
    }

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

    public function markAsPaid(
        string $reference,
        int $userId
    ): Order {

        $order = $this->getUserOrderByReference(
            $reference,
            $userId
        );

        if ($order->payment_status === 'paid') {
            return $order;
        }

        $this->completeOrder($order);

        return $order->fresh('items.product');
    }

    public function completeOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {

            if ($order->payment_status === 'paid') {
                return;
            }

            $order->load('items');

            foreach ($order->items as $item) {

                $product = Product::lockForUpdate()
                    ->findOrFail($item->product_id);

                if ($product->quantity < $item->quantity) {
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

    public function updateOrderStatus(
        int $orderId,
        string $status
    ) {
        $order = Order::findOrFail($orderId);

        $order->update([
            'status' => $status,
        ]);

        return $order->fresh([
            'items.product',
        ]);
    }
}