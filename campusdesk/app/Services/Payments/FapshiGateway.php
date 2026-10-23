<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FapshiGateway implements PaymentGatewayInterface
{
    protected string $apiUser;
    protected string $apiKey;
    protected string $baseUrl;
    protected ?string $webhookSecret;

    public function __construct()
    {
        $this->apiUser = (string) config('payments.gateways.fapshi.api_user', '');
        $this->apiKey = (string) config('payments.gateways.fapshi.api_key', '');
        $this->baseUrl = rtrim((string) config('payments.gateways.fapshi.base_url', 'https://sandbox.fapshi.com'), '/');
        $this->webhookSecret = config('payments.gateways.fapshi.webhook_secret');
    }

    /**
     * Initiate hosted checkout.
     */
    public function initiateHosted(Payment $payment, User $student): array
    {
        $url = "{$this->baseUrl}/initiate-pay";
        $payload = [
            'amount'      => (int) $payment->amount,
            'email'       => $student->email,
            'userId'      => (string) $student->id,
            'externalId'  => $payment->external_id,
            'redirectUrl' => url("/student/requests/{$payment->request_id}"),
            'message'     => "Processing fee for Request #{$payment->request_id}",
        ];

        Log::channel('payments')->info('Fapshi initiateHosted request', [
            'payment_id'  => $payment->id,
            'external_id' => $payment->external_id,
            'endpoint'    => $url,
            'payload'     => $payload,
        ]);

        $response = Http::withHeaders($this->authHeaders())
            ->timeout(20)
            ->post($url, $payload);

        $json = $response->json() ?? [];

        Log::channel('payments')->info('Fapshi initiateHosted response', [
            'status' => $response->status(),
            'body'   => $json,
        ]);

        if (! $response->successful() || empty($json['link'])) {
            $msg = $json['message'] ?? 'Failed to initiate Fapshi payment.';
            throw new RuntimeException("Fapshi initiateHosted error: {$msg}");
        }

        return [
            'provider_transaction_id' => $json['transId'] ?? null,
            'checkout_url'            => $json['link'],
            'status'                  => 'pending',
            'raw'                     => $json,
        ];
    }

    /**
     * Initiate direct push (USSD prompt) to mobile money handset.
     */
    public function initiateDirectPush(Payment $payment, User $student, string $phone, string $method): array
    {
        $url = "{$this->baseUrl}/direct-pay";
        $cleanPhone = preg_replace('/\D/', '', $phone) ?? '';

        $payload = [
            'amount'     => (int) $payment->amount,
            'phone'      => $cleanPhone,
            'medium'     => 'mobile_money',
            'userId'     => (string) $student->id,
            'externalId' => $payment->external_id,
            'message'    => "Processing fee for Request #{$payment->request_id}",
        ];

        Log::channel('payments')->info('Fapshi initiateDirectPush request', [
            'payment_id'  => $payment->id,
            'external_id' => $payment->external_id,
            'endpoint'    => $url,
            'phone'       => substr($cleanPhone, 0, 4) . '***',
        ]);

        $response = Http::withHeaders($this->authHeaders())
            ->timeout(20)
            ->post($url, $payload);

        $json = $response->json() ?? [];

        Log::channel('payments')->info('Fapshi initiateDirectPush response', [
            'status' => $response->status(),
            'body'   => $json,
        ]);

        if (! $response->successful()) {
            $msg = $json['message'] ?? 'Failed to initiate Fapshi direct push.';
            throw new RuntimeException("Fapshi initiateDirectPush error: {$msg}");
        }

        return [
            'provider_transaction_id' => $json['transId'] ?? null,
            'status'                  => 'pending',
            'raw'                     => $json,
        ];
    }

    /**
     * Query authoritative transaction status from Fapshi API.
     */
    public function fetchStatus(Payment $payment): array
    {
        $transId = $payment->provider_transaction_id ?: $payment->external_id;
        $url = "{$this->baseUrl}/payment-status/{$transId}";

        Log::channel('payments')->info('Fapshi fetchStatus request', [
            'payment_id'  => $payment->id,
            'external_id' => $payment->external_id,
            'trans_id'    => $transId,
        ]);

        $response = Http::withHeaders($this->authHeaders())
            ->timeout(20)
            ->get($url);

        $json = $response->json() ?? [];

        Log::channel('payments')->info('Fapshi fetchStatus response', [
            'status' => $response->status(),
            'body'   => $json,
        ]);

        if (! $response->successful()) {
            $msg = $json['message'] ?? 'Failed to fetch status from Fapshi.';
            throw new RuntimeException("Fapshi fetchStatus error: {$msg}");
        }

        $rawStatus = strtoupper((string) ($json['status'] ?? 'PENDING'));
        $mappedStatus = match ($rawStatus) {
            'SUCCESSFUL', 'PAID' => 'successful',
            'FAILED'             => 'failed',
            'EXPIRED'            => 'expired',
            default              => 'pending',
        };

        return [
            'status'                  => $mappedStatus,
            'amount'                  => (int) ($json['amount'] ?? $payment->amount),
            'currency'                => 'XAF',
            'provider_transaction_id' => (string) ($json['transId'] ?? $transId),
            'failure_reason'          => $mappedStatus === 'failed' ? ($json['reason'] ?? $json['message'] ?? 'Payment failed.') : null,
            'raw'                     => $json,
        ];
    }

    /**
     * Expire an active payment session if supported by Fapshi.
     */
    public function expirePayment(Payment $payment): void
    {
        if (! $payment->provider_transaction_id) {
            return;
        }

        $url = "{$this->baseUrl}/expire-pay";
        try {
            Http::withHeaders($this->authHeaders())
                ->timeout(10)
                ->post($url, ['transId' => $payment->provider_transaction_id]);
        } catch (\Throwable $e) {
            Log::channel('payments')->warning('Fapshi expirePayment exception: ' . $e->getMessage());
        }
    }

    /**
     * Verify incoming webhook request signature / secret.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        if (empty($this->webhookSecret)) {
            // In testing or environments without a configured secret, allow verification
            return true;
        }

        $receivedSecret = $request->header('x-wh-secret') ?? $request->header('X-Wh-Secret');

        return hash_equals((string) $this->webhookSecret, (string) $receivedSecret);
    }

    /**
     * Extract the reference / external_id from webhook payload.
     */
    public function extractWebhookReference(Request $request): ?string
    {
        return $request->input('externalId')
            ?? $request->input('transId')
            ?? null;
    }

    /**
     * Fapshi API authentication headers.
     *
     * @return array<string, string>
     */
    protected function authHeaders(): array
    {
        return [
            'apiuser' => $this->apiUser,
            'apikey'  => $this->apiKey,
            'Accept'  => 'application/json',
        ];
    }
}
