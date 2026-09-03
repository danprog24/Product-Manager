<?php

namespace App\Http\Controllers;

use App\Services\SellerWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerWalletController extends Controller
{
    public function __construct(
        private SellerWalletService $sellerWalletService
    ) {}

    /**
     * Get authenticated seller wallet.
     */
    public function wallet(Request $request): JsonResponse
    {
        $wallet = $this->sellerWalletService->getWallet(
            $request->user()->id
        );

        return response()->json([
            'message' => 'Wallet retrieved successfully.',
            'data' => $wallet,
        ]);
    }

    /**
     * Get Nigerian banks supported by Paystack.
     */
    public function banks(): JsonResponse
    {
        try {
            $banks = $this->sellerWalletService->getBanks();

            return response()->json([
                'message' => 'Banks retrieved successfully.',
                'data' => $banks,
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Verify a seller's bank account.
     */
    public function verifyAccount(
        Request $request
    ): JsonResponse {

        $validated = $request->validate([
            'account_number' => [
                'required',
                'string',
                'size:10',
            ],

            'bank_code' => [
                'required',
                'string',
                'max:20',
            ],
        ]);

        try {
            $account =
                $this->sellerWalletService->verifyAccount(
                    $validated['account_number'],
                    $validated['bank_code']
                );

            return response()->json([
                'message' =>
                    'Bank account verified successfully.',

                'data' => $account,
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Request a seller withdrawal.
     */
    public function withdraw(
        Request $request
    ): JsonResponse {

        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'bank_name' => [
                'required',
                'string',
                'max:255',
            ],

            'bank_code' => [
                'required',
                'string',
                'max:20',
            ],

            'account_number' => [
                'required',
                'string',
                'size:10',
            ],
        ]);

        try {

            $withdrawal =
                $this->sellerWalletService->requestWithdrawal(
                    $request->user()->id,

                    (float) $validated['amount'],

                    [
                        'bank_name' =>
                            $validated['bank_name'],

                        'bank_code' =>
                            $validated['bank_code'],

                        'account_number' =>
                            $validated['account_number'],
                    ]
                );

            return response()->json([
                'message' =>
                    'Withdrawal request submitted successfully.',

                'data' => $withdrawal,
            ], 201);

        } catch (\Exception $e) {

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get authenticated seller withdrawal history.
     */
    public function withdrawals(
        Request $request
    ): JsonResponse {

        $withdrawals = $request->user()
            ->sellerWithdrawals()
            ->latest()
            ->get();

        return response()->json([
            'message' =>
                'Withdrawal history retrieved successfully.',

            'data' => $withdrawals,
        ]);
    }

    /**
     * Get a specific seller withdrawal.
     */
    public function showWithdrawal(
        Request $request,
        int $id
    ): JsonResponse {

        $withdrawal = $request->user()
            ->sellerWithdrawals()
            ->findOrFail($id);

        return response()->json([
            'message' =>
                'Withdrawal retrieved successfully.',

            'data' => $withdrawal,
        ]);
    }
}