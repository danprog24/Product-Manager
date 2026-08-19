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
}