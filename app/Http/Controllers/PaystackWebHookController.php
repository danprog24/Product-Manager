<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SellerEarning;
use App\Models\SellerWithdrawal;
use App\Services\OrderService;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PaystackWebhookController extends Controller
{
    public function __construct(
        private PaystackService $paystackService,
        private OrderService $orderService
    ) {}

    public function handle(Request $request): Response
    {
        /*
        |--------------------------------------------------------------------------
        | Verify Paystack webhook signature
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | Customer payment
        |--------------------------------------------------------------------------
        */

        if ($event === 'charge.success') {
            return $this->handleChargeSuccess($request);
        }

        /*
        |--------------------------------------------------------------------------
        | Seller transfer
        |--------------------------------------------------------------------------
        */

        if (in_array($event, [
            'transfer.success',
            'transfer.failed',
            'transfer.reversed',
        ], true)) {
            return $this->handleTransfer($request);
        }

        /*
        |--------------------------------------------------------------------------
        | Ignore unsupported events
        |--------------------------------------------------------------------------
        */

        return response('OK', 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Handle successful customer payment
    |--------------------------------------------------------------------------
    */

    private function handleChargeSuccess(
        Request $request
    ): Response {
        $reference = $request->input(
            'data.reference'
        );

        if (!$reference) {
            return response(
                'Missing reference',
                400
            );
        }

        $order = Order::where(
            'reference',
            $reference
        )->first();

        if (!$order) {
            return response(
                'Order not found',
                404
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Idempotency
        |--------------------------------------------------------------------------
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

    /*
    |--------------------------------------------------------------------------
    | Handle seller transfer events
    |--------------------------------------------------------------------------
    */

    private function handleTransfer(
        Request $request
    ): Response {
        $event = $request->input('event');

        $reference = $request->input(
            'data.reference'
        );

        if (!$reference) {
            return response(
                'Missing transfer reference',
                400
            );
        }

        $withdrawal = SellerWithdrawal::where(
            'paystack_transfer_reference',
            $reference
        )->first();

        if (!$withdrawal) {
            return response(
                'Withdrawal not found',
                404
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Verify transfer amount
        |--------------------------------------------------------------------------
        */

        $transferAmount = (int) $request->input(
            'data.amount'
        );

        $expectedAmount = (int) round(
            (float) $withdrawal->amount * 100
        );

        if ($transferAmount !== $expectedAmount) {
            return response(
                'Invalid transfer amount',
                400
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Transfer successful
        |--------------------------------------------------------------------------
        */

        if ($event === 'transfer.success') {
            $this->markWithdrawalSuccessful(
                $withdrawal
            );

            return response('OK', 200);
        }

        /*
        |--------------------------------------------------------------------------
        | Transfer failed / reversed
        |--------------------------------------------------------------------------
        */

        if (in_array($event, [
            'transfer.failed',
            'transfer.reversed',
        ], true)) {
            $this->markWithdrawalFailed(
                $withdrawal,
                $request,
                $event
            );

            return response('OK', 200);
        }

        return response('OK', 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Mark withdrawal successful
    |--------------------------------------------------------------------------
    */

    private function markWithdrawalSuccessful(
        SellerWithdrawal $withdrawal
    ): void {
        DB::transaction(function () use ($withdrawal) {

            $withdrawal = SellerWithdrawal::query()
                ->lockForUpdate()
                ->find($withdrawal->id);

            if (!$withdrawal) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Withdrawal-level idempotency
            |--------------------------------------------------------------------------
            |
            | Paystack can send the same webhook more than once.
            |
            */

            if ($withdrawal->status === 'completed') {
                return;
            }

            $withdrawal->load('items');

            foreach ($withdrawal->items as $item) {

                /*
                |--------------------------------------------------------------------------
                | Item-level idempotency
                |--------------------------------------------------------------------------
                */

                if ($item->status === 'paid') {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Get earning
                |--------------------------------------------------------------------------
                */

                $earning = SellerEarning::query()
                    ->lockForUpdate()
                    ->find($item->seller_earning_id);

                if (!$earning) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Release the reservation
                |--------------------------------------------------------------------------
                */

                $newReservedAmount = max(
                    0,
                    (float) $earning->reserved_amount -
                    (float) $item->amount
                );

                $earning->update([
                    'reserved_amount' =>
                        round(
                            $newReservedAmount,
                            2
                        ),
                ]);

                /*
                |--------------------------------------------------------------------------
                | Mark this withdrawal allocation as paid
                |--------------------------------------------------------------------------
                */

                $item->update([
                    'status' => 'paid',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Determine whether the entire earning
                | has now been paid.
                |--------------------------------------------------------------------------
                */

                $totalPaid = $earning
                    ->withdrawalItems()
                    ->where('status', 'paid')
                    ->sum('amount');

                $totalPaid = round(
                    (float) $totalPaid,
                    2
                );

                $netAmount = round(
                    (float) $earning->net_amount,
                    2
                );

                /*
                |--------------------------------------------------------------------------
                | Only mark earning as paid when the
                | entire earning has been withdrawn.
                |--------------------------------------------------------------------------
                */

                if ($totalPaid >= $netAmount) {
                    $earning->update([
                        'status' => 'paid',
                        'reserved_amount' => 0,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Mark withdrawal completed
            |--------------------------------------------------------------------------
            */

            $withdrawal->update([
                'status' => 'completed',
                'failure_reason' => null,
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Mark withdrawal failed / reversed
    |--------------------------------------------------------------------------
    */

    private function markWithdrawalFailed(
        SellerWithdrawal $withdrawal,
        Request $request,
        string $event
    ): void {
        DB::transaction(function () use (
            $withdrawal,
            $request,
            $event
        ) {

            $withdrawal = SellerWithdrawal::query()
                ->lockForUpdate()
                ->find($withdrawal->id);

            if (!$withdrawal) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Withdrawal-level idempotency
            |--------------------------------------------------------------------------
            */

            if (in_array($withdrawal->status, [
                'failed',
                'cancelled',
            ], true)) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Do not release a completed withdrawal
            |--------------------------------------------------------------------------
            */

            if ($withdrawal->status === 'completed') {
                return;
            }

            $withdrawal->load('items');

            foreach ($withdrawal->items as $item) {

                /*
                |--------------------------------------------------------------------------
                | Item-level idempotency
                |--------------------------------------------------------------------------
                */

                if ($item->status === 'released') {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Do not release an allocation that was
                | already successfully paid.
                |--------------------------------------------------------------------------
                */

                if ($item->status === 'paid') {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Get earning
                |--------------------------------------------------------------------------
                */

                $earning = SellerEarning::query()
                    ->lockForUpdate()
                    ->find($item->seller_earning_id);

                if (!$earning) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Release reserved amount
                |--------------------------------------------------------------------------
                */

                $newReservedAmount = max(
                    0,
                    (float) $earning->reserved_amount -
                    (float) $item->amount
                );

                $earning->update([
                    'reserved_amount' =>
                        round(
                            $newReservedAmount,
                            2
                        ),

                    'status' => 'available',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Mark allocation as released
                |--------------------------------------------------------------------------
                */

                $item->update([
                    'status' => 'released',
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Determine failure reason
            |--------------------------------------------------------------------------
            */

            $failureReason = $request->input(
                'data.failures'
            ) ?? (
                $event === 'transfer.reversed'
                    ? 'Transfer was reversed by Paystack.'
                    : 'Transfer failed.'
            );

            if (is_array($failureReason)) {
                $failureReason = json_encode(
                    $failureReason
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Mark withdrawal failed
            |--------------------------------------------------------------------------
            */

            $withdrawal->update([
                'status' => 'failed',

                'failure_reason' =>
                    (string) $failureReason,
            ]);
        });
    }
}