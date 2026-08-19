<?php

// namespace App\Services;

// use App\Models\Order;
// use App\Models\Product;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Str;

// class OrderService
// {
//     public function createOrder(array $items, int $userId): Order
//     {
//         return DB::transaction(function () use ($items, $userId) {

//             $totalAmount = 0;

//             // Create the order
//             $order = Order::create([
//                 'user_id' => $userId,
//                 'reference' => 'ORD-' . Str::upper(Str::random(16)),
//                 'total_amount' => 0,
//                 'status' => 'pending',
//                 'payment_method' => 'credit_card',
//                 'payment_status' => 'unpaid',
//             ]);

//             // Process each item in the order
//             foreach ($items as $item) {

//                 $product = Product::findOrFail(
//                     $item['product_id']
//                 );

//                 // Check if the product has enough stock
//                 if ($product->quantity < $item['quantity']) {
//                     throw new \Exception(
//                         "Insufficient stock for {$product->name}."
//                     );
//                 }

//                 // Calculate subtotal and total amount
//                 $quantity = $item['quantity'];
//                 $unitPrice = $product->price;
//                 $subtotal = $unitPrice * $quantity;

//                 $totalAmount += $subtotal;

//                 // Create order item
//                 $order->items()->create([
//                     'product_id' => $product->id,
//                     'seller_id' => $product->user_id,
//                     'quantity' => $quantity,
//                     'unit_price' => $unitPrice,
//                     'subtotal' => $subtotal,
//                 ]);

//                 // Decrement the product quantity
//                 $product->decrement('quantity', $quantity);
//             }

//             // Update the total amount of the order
//             $order->update([
//                 'total_amount' => $totalAmount,
//             ]);

//             // Return the order with its items and associated products
//             return $order->load('items.product');
//         });
//     }

//     // Get all orders for a specific user
//     public function getUserOrders(int $userId)
//     {
//         return Order::query()
//             ->where('user_id', $userId)
//             ->with('items.product')
//             ->latest()
//             ->get();
//     }

//     // Get a specific order for a specific user
//     public function getUserOrder(int $orderId, int $userId)
//     {
//         return Order::query()
//             ->where('id', $orderId)
//             ->where('user_id', $userId)
//             ->with('items.product')
//             ->firstOrFail();
//     }

//     public function completeOrder(Order $order): void
//     {
//         DB::transaction(function () use ($order) {

//             // Idempotency protection
//             if ($order->payment_status === 'paid') {
//                 return;
//             }

//             $order->load('items');

//             foreach ($order->items as $item) {

//                 $product = Product::lockForUpdate()
//                     ->findOrFail($item->product_id);

//                 if ($product->quantity < $item->quantity) {
//                     throw new \Exception(
//                         "Insufficient stock for {$product->name}."
//                     );
//                 }

//                 $product->decrement(
//                     'quantity',
//                     $item->quantity
//                 );
//             }

//             $order->update([
//                 'payment_status' => 'paid',
//                 'status' => 'processing',
//             ]);
//         });
//     }

// }



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
}