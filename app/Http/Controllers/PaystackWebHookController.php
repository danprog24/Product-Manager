<?php

// namespace App\Http\Controllers;

// use App\Models\Order;
// use App\Services\OrderService;
// use App\Services\PaystackService;
// use Illuminate\Http\Request;
// use Illuminate\Http\Response;

// class PaystackWebhookController extends Controller
// {
//     public function __construct(
//         private PaystackService $paystackService,
//         private OrderService $orderService
//     ) {}

//     public function handle(Request $request): Response
//     {
//         $signature = $request->header('x-paystack-signature');

//         if (!$signature) {
//             return response('Unauthorized', 401);
//         }

//         $expectedSignature = hash_hmac(
//             'sha512',
//             $request->getContent(),
//             config('services.paystack.secret_key')
//         );

//         if (!hash_equals($expectedSignature, $signature)) {
//             return response('Unauthorized', 401);
//         }

//         $event = $request->input('event');

//         if ($event !== 'charge.success') {
//             return response('OK', 200);
//         }

//         $reference = $request->input('data.reference');

//         $order = Order::where(
//             'reference',
//             $reference
//         )->first();

//         if (!$order) {
//             return response('Order not found', 404);
//         }

//         // Verify directly with Paystack
//         $payment = $this->paystackService->verify($reference);

//         if (
//             $payment['status'] !== 'success'
//             || $payment['reference'] !== $order->reference
//         ) {
//             return response('Payment not verified', 400);
//         }

//         $expectedAmount = (int) round(
//             $order->total_amount * 100
//         );

//         if ((int) $payment['amount'] !== $expectedAmount) {
//             return response('Invalid payment amount', 400);
//         }

//         $this->orderService->completeOrder($order);

//         return response('OK', 200);
//     }
// }




namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaystackWebhookController extends Controller
{
    public function __construct(
        private PaystackService $paystackService,
        private OrderService $orderService
    ) {}

    public function handle(Request $request): Response
    {
        $signature = $request->header(
            'x-paystack-signature'
        );

        if (!$signature) {
            return response('Unauthorized', 401);
        }

        $expectedSignature = hash_hmac(
            'sha512',
            $request->getContent(),
            config('services.paystack.secret_key')
        );

        if (!hash_equals(
            $expectedSignature,
            $signature
        )) {
            return response('Unauthorized', 401);
        }

        $event = $request->input('event');

        if ($event !== 'charge.success') {
            return response('OK', 200);
        }

        $reference = $request->input(
            'data.reference'
        );

        if (!$reference) {
            return response('Missing reference', 400);
        }

        $order = Order::where(
            'reference',
            $reference
        )->first();

        if (!$order) {
            return response('Order not found', 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Idempotency
        |--------------------------------------------------------------------------
        |
        | Paystack may send the same webhook more than once.
        |
        */

        if ($order->payment_status === 'paid') {
            return response('OK', 200);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify transaction directly with Paystack
        |--------------------------------------------------------------------------
        */

        $payment = $this->paystackService->verify(
            $reference
        );

        if (
            $payment['status'] !== 'success' ||
            $payment['reference'] !== $order->reference
        ) {
            return response(
                'Payment not verified',
                400
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Verify amount
        |--------------------------------------------------------------------------
        */

        $expectedAmount = (int) round(
            $order->total_amount * 100
        );

        if (
            (int) $payment['amount']
            !== $expectedAmount
        ) {
            return response(
                'Invalid payment amount',
                400
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Complete order
        |--------------------------------------------------------------------------
        */

        $this->orderService->completeOrder(
            $order
        );

        return response('OK', 200);
    }
}