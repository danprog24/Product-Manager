<?php

namespace App\Services;

use App\Models\SellerEarning;
use App\Models\SellerWithdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use App\Models\SellerWithdrawalItem;

class SellerWalletService
{
    public function __construct(
        private PaystackService $paystackService
    ) {}

    public function getWallet(int $sellerId): array
    {
        $pending = SellerEarning::query()
            ->where('seller_id', $sellerId)
            ->where('status', 'pending')
            ->sum('net_amount');

        $available = SellerEarning::query()
            ->where('seller_id', $sellerId)
            ->where('status', 'available')
            ->selectRaw(
                'COALESCE(SUM(net_amount - reserved_amount), 0) as balance'
            )
            ->value('balance');

        $reserved = SellerEarning::query()
            ->where('seller_id', $sellerId)
            ->sum('reserved_amount');

        $paid = SellerWithdrawalItem::query()
            ->whereHas('withdrawal', function ($query) use ($sellerId) {
                $query->where('seller_id', $sellerId);
            })
            ->where('status', 'paid')
            ->sum('amount');

        $withdrawals = SellerWithdrawal::query()
            ->where('seller_id', $sellerId)
            ->whereIn('status', [
                'pending',
                'processing',
            ])
            ->sum('amount');

        return [
            'pending' => round((float) $pending, 2),
            'available' => round((float) $available, 2),
            'reserved' => round((float) $reserved, 2),
            'paid' => round((float) $paid, 2),
            'pending_withdrawals' => round((float) $withdrawals, 2),
        ];
    }

    public function requestWithdrawal(
        int $sellerId,
        float $amount,
        array $bankData
    ): SellerWithdrawal {
        /*
        |--------------------------------------------------------------------------
        | Step 1: Create withdrawal and reserve earnings
        |--------------------------------------------------------------------------
        |
        | This transaction contains ONLY database operations.
        | We deliberately do not call Paystack while this transaction is open.
        |
        */

        $withdrawal = DB::transaction(function () use (
            $sellerId,
            $amount,
            $bankData
        ) {
            if ($amount <= 0) {
                throw new RuntimeException(
                    'Withdrawal amount must be greater than zero.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Lock available seller earnings
            |--------------------------------------------------------------------------
            */

            $earnings = SellerEarning::query()
                ->where('seller_id', $sellerId)
                ->where('status', 'available')
                ->whereRaw(
                    'reserved_amount < net_amount'
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $available = 0;

            foreach ($earnings as $earning) {
                $available +=
                    (float) $earning->net_amount -
                    (float) $earning->reserved_amount;
            }

            $available = round($available, 2);

            if ($amount > $available) {
                throw new RuntimeException(
                    'Insufficient available balance.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Generate Paystack transfer reference
            |--------------------------------------------------------------------------
            |
            | Paystack transfer references should contain lowercase characters,
            | numbers, "-" or "_".
            |
            */

            $paystackTransferReference =
                'wdr_' . Str::lower((string) Str::uuid());

            /*
            |--------------------------------------------------------------------------
            | Create withdrawal
            |--------------------------------------------------------------------------
            |
            | account_name is initially taken from the request, but it will be
            | verified again through Paystack before the transfer is created.
            |
            */

            $withdrawal = SellerWithdrawal::create([
                'seller_id' => $sellerId,

                'amount' => round($amount, 2),

                'status' => 'pending',

                'reference' => 'WDR-' . Str::upper(
                    Str::random(16)
                ),

                'bank_name' =>
                    $bankData['bank_name'] ?? null,

                'bank_code' =>
                    $bankData['bank_code'] ?? null,

                'account_name' =>
                    $bankData['account_name'] ?? null,

                'account_number' =>
                    $bankData['account_number'] ?? null,

                'paystack_transfer_reference' =>
                    $paystackTransferReference,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Reserve earnings
            |--------------------------------------------------------------------------
            */

            $remaining = $amount;

            foreach ($earnings as $earning) {
                if ($remaining <= 0) {
                    break;
                }

                $earningAvailable =
                    (float) $earning->net_amount -
                    (float) $earning->reserved_amount;

                if ($earningAvailable <= 0) {
                    continue;
                }

                $reserveAmount = min(
                    $remaining,
                    $earningAvailable
                );

                $newReservedAmount =
                    (float) $earning->reserved_amount +
                    $reserveAmount;

                /*
                |--------------------------------------------------------------------------
                | Do NOT change earning status to "reserved".
                |--------------------------------------------------------------------------
                |
                | Your database enum only supports:
                |
                | pending
                | available
                | paid
                | cancelled
                |
                | reserved_amount represents the reservation.
                |
                */

                $earning->update([
                    'reserved_amount' =>
                        round($newReservedAmount, 2),

                    'status' => 'available',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Record exactly how much of this earning belongs
                | to this withdrawal.
                |--------------------------------------------------------------------------
                */

                $withdrawal->items()->create([
                    'seller_earning_id' =>
                        $earning->id,

                    'amount' =>
                        round($reserveAmount, 2),

                    'status' => 'pending',
                ]);

                $remaining = round(
                    $remaining - $reserveAmount,
                    2
                );
            }

            if ($remaining > 0) {
                throw new RuntimeException(
                    'Unable to reserve the requested withdrawal amount.'
                );
            }

            return $withdrawal;
        });

        /*
        |--------------------------------------------------------------------------
        | Step 2: Verify bank account with Paystack
        |--------------------------------------------------------------------------
        |
        | Never trust account_name supplied by the frontend.
        |
        | Paystack resolves the account number + bank code and returns the
        | actual account name.
        |
        */

        try {
            $verifiedAccount =
                $this->paystackService->resolveAccount(
                    $withdrawal->account_number,
                    $withdrawal->bank_code
                );

            $verifiedAccountName =
                $verifiedAccount['account_name'] ?? null;

            if (!$verifiedAccountName) {
                throw new RuntimeException(
                    'Paystack did not return an account name.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Update withdrawal with the verified account name
            |--------------------------------------------------------------------------
            */

            $withdrawal->update([
                'account_name' => $verifiedAccountName,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Step 3: Create Paystack transfer recipient
            |--------------------------------------------------------------------------
            */

            $recipient =
                $this->paystackService->createTransferRecipient(
                    $verifiedAccountName,
                    $withdrawal->account_number,
                    $withdrawal->bank_code
                );

            $recipientCode = $recipient['recipient_code']
                ?? null;

            if (!$recipientCode) {
                throw new RuntimeException(
                    'Paystack did not return a recipient code.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Save Paystack recipient
            |--------------------------------------------------------------------------
            */

            $withdrawal->update([
                'paystack_recipient_code' =>
                    $recipientCode,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Step 4: Initiate Paystack transfer
            |--------------------------------------------------------------------------
            |
            | Paystack expects NGN amounts in kobo.
            |
            */

            $amountInKobo = (int) round(
                $amount * 100
            );

            $transfer =
                $this->paystackService->initiateTransfer(
                    $amountInKobo,
                    $recipientCode,
                    $withdrawal->paystack_transfer_reference,
                    'Seller withdrawal ' .
                    $withdrawal->reference
                );

            /*
            |--------------------------------------------------------------------------
            | Save Paystack transfer information
            |--------------------------------------------------------------------------
            */

            $withdrawal->update([
                'status' => 'processing',

                'paystack_transfer_code' =>
                    $transfer['transfer_code'] ?? null,

                'paystack_transfer_id' =>
                    $transfer['id'] ?? null,
            ]);

            return $withdrawal->fresh('items');

        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Paystack failed before the transfer was successfully initiated.
            |--------------------------------------------------------------------------
            |
            | Release the reservation and mark the withdrawal as failed.
            |
            */

            $this->failWithdrawal(
                $withdrawal,
                $e->getMessage()
            );

            throw new RuntimeException(
                'Unable to process withdrawal: ' .
                $e->getMessage()
            );
        }
    }

    /**
     * Release reserved earnings when a withdrawal fails.
     */
    private function failWithdrawal(
        SellerWithdrawal $withdrawal,
        string $reason
    ): void {
        DB::transaction(function () use (
            $withdrawal,
            $reason
        ) {
            $withdrawal = SellerWithdrawal::query()
                ->lockForUpdate()
                ->find($withdrawal->id);

            if (!$withdrawal) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent processing an already completed withdrawal
            |--------------------------------------------------------------------------
            */

            if ($withdrawal->status === 'completed') {
                return;
            }

            $withdrawal->load('items');

            foreach ($withdrawal->items as $item) {
                /*
                |------------------------------------------------------------------
                | Do not release an item that has already been paid.
                |------------------------------------------------------------------
                */

                if ($item->status === 'paid') {
                    continue;
                }

                if ($item->status === 'released') {
                    continue;
                }

                $earning = SellerEarning::query()
                    ->lockForUpdate()
                    ->find($item->seller_earning_id);

                if (!$earning) {
                    continue;
                }

                $newReservedAmount = max(
                    0,
                    (float) $earning->reserved_amount -
                    (float) $item->amount
                );

                $earning->update([
                    'reserved_amount' =>
                        round($newReservedAmount, 2),

                    'status' => 'available',
                ]);

                $item->update([
                    'status' => 'released',
                ]);
            }

            $withdrawal->update([
                'status' => 'failed',
                'failure_reason' => $reason,
            ]);
        });
    }

    /**
     * Get the Nigerian banks supported by Paystack.
     */
    public function getBanks(): array
    {
        return $this->paystackService->getBanks();
    }

    /**
     * Resolve a Nigerian bank account through Paystack.
     *
     * Returns the verified account information, including account_name.
     */
    public function verifyAccount(
        string $accountNumber,
        string $bankCode
    ): array {
        return $this->paystackService->resolveAccount(
            $accountNumber,
            $bankCode
        );
    }
}