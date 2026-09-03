<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackService
{
    private string $secretKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
        $this->baseUrl = config('services.paystack.base_url');
    }

    public function initialize(
        string $email,
        int $amount,
        string $reference
    ): array {
        $response = Http::withToken($this->secretKey)
            ->post(
                $this->baseUrl . '/transaction/initialize',
                [
                    'email' => $email,
                    'amount' => $amount,
                    'reference' => $reference,
                    'currency' => 'NGN',
                    'callback_url' => config('app.frontend_url').'/checkout/',
                ]
            );

        if (
            !$response->successful() ||
            !$response->json('status')
        ) {
            throw new RuntimeException(
                $response->json(
                    'message',
                    'Unable to initialize payment.'
                )
            );
        }

        return $response->json('data');
    }

    public function verify(string $reference): array
    {
        $response = Http::withToken($this->secretKey)
            ->get(
                $this->baseUrl .
                '/transaction/verify/' .
                urlencode($reference)
            );

        if (
            !$response->successful() ||
            !$response->json('status')
        ) {
            throw new RuntimeException(
                $response->json(
                    'message',
                    'Unable to verify payment.'
                )
            );
        }

        return $response->json('data');
    }

    /*
    |--------------------------------------------------------------------------
    | Transfer Recipient
    |--------------------------------------------------------------------------
    */

    public function createTransferRecipient(
        string $name,
        string $accountNumber,
        string $bankCode
    ): array {
        $response = Http::withToken($this->secretKey)
            ->post(
                $this->baseUrl . '/transferrecipient',
                [
                    'type' => 'nuban',
                    'name' => $name,
                    'account_number' => $accountNumber,
                    'bank_code' => $bankCode,
                    'currency' => 'NGN',
                ]
            );

        if (
            !$response->successful() ||
            !$response->json('status')
        ) {
            throw new RuntimeException(
                $response->json(
                    'message',
                    'Unable to create transfer recipient.'
                )
            );
        }

        return $response->json('data');
    }

    /*
    |--------------------------------------------------------------------------
    | Initiate Transfer
    |--------------------------------------------------------------------------
    */

    public function initiateTransfer(
        int $amount,
        string $recipientCode,
        string $reference,
        ?string $reason = null
    ): array {
        $payload = [
            'source' => 'balance',
            'amount' => $amount,
            'recipient' => $recipientCode,
            'reference' => $reference,
            'currency' => 'NGN',
        ];

        if ($reason !== null) {
            $payload['reason'] = $reason;
        }

        $response = Http::withToken($this->secretKey)
            ->post(
                $this->baseUrl . '/transfer',
                $payload
            );

        if (
            !$response->successful() ||
            !$response->json('status')
        ) {
            throw new RuntimeException(
                $response->json(
                    'message',
                    'Unable to initiate transfer.'
                )
            );
        }

        return $response->json('data');
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Transfer
    |--------------------------------------------------------------------------
    */

    public function verifyTransfer(
        string $reference
    ): array {
        $response = Http::withToken($this->secretKey)
            ->get(
                $this->baseUrl .
                '/transfer/verify/' .
                urlencode($reference)
            );

        if (
            !$response->successful() ||
            !$response->json('status')
        ) {
            throw new RuntimeException(
                $response->json(
                    'message',
                    'Unable to verify transfer.'
                )
            );
        }

        return $response->json('data');
    }


    public function getBanks(): array
    {
        $response = Http::withToken($this->secretKey)
            ->get(
                $this->baseUrl . '/bank',
                [
                    'country' => 'nigeria',
                    'perPage' => 100,
                ]
            );

        if (
            !$response->successful() ||
            !$response->json('status')
        ) {
            throw new RuntimeException(
                $response->json(
                    'message',
                    'Unable to retrieve banks.'
                )
            );
        }

        return $response->json('data', []);
    }

    public function resolveAccount(
        string $accountNumber,
        string $bankCode
    ): array {
        $response = Http::withToken($this->secretKey)
            ->get(
                $this->baseUrl . '/bank/resolve',
                [
                    'account_number' => $accountNumber,
                    'bank_code' => $bankCode,
                ]
            );

        if (
            !$response->successful() ||
            !$response->json('status')
        ) {
            throw new RuntimeException(
                $response->json(
                    'message',
                    'Unable to verify bank account.'
                )
            );
        }

        return $response->json('data');
    }

}