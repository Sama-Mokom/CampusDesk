<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class NotchPayGateway implements PaymentGatewayInterface
{
    protected string $publicKey;
    protected string $privateKey;
    protected string $baseUrl;
    protected ?string $webhookSecret;

    public function __construct()
    {
        $this->publicKey = (string) config('payments.gateways.notchpay.public_key', '');
        $this->privateKey = (string) config('payments.gateways.notchpay.private_key', '');
        $this->baseUrl = rtrim((string) config('payments.gateways.notchpay.base_url', 'https://api.notchpay.co'), '/');
        $this->webhookSecret = config('payments.gateways.notchpay.webhook_secret');
    }

    /**
     * Initiate hosted checkout.
     */
    public function initiateHosted(Payment $payment, User $student): array
    {
        $url = "{$this->baseUrl}/payments/initialize";
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
        $payload = [
            'amount'      => (int) $payment->amount,
            'currency'    => 'XAF',
            'email'       => $student->email,
            'reference'   => $payment->external_id,
            'callback'    => "{$frontendUrl}/student/requests/{$payment->request_id}",
            'description' => "Processing fee for Request #{$payment->request_id}",
        ];

        Log::channel('payments')->info('NotchPay initiateHosted request', [
            'payment_id'  => $payment->id,
            'external_id' => $payment->external_id,
            'endpoint'    => $url,
        ]);

        $response = Http::withHeaders($this->authHeaders())
            ->timeout(20)
            ->post($url, $payload);

        $json = $response->json() ?? [];

        Log::channel('payments')->info('NotchPay initiateHosted response', [
            'status' => $response->status(),
            'body'   => $json,
        ]);

        $authUrl = $json['authorization_url'] ?? $json['payment_url'] ?? null;
        if (! $response->successful() || empty($authUrl)) {
            $msg = $json['message'] ?? 'Failed to initialize Notch Pay payment.';
            throw new RuntimeException("NotchPay initiateHosted error: {$msg}");
        }

        $transRef = $json['transaction']['reference'] ?? $payment->external_id;

        return [
            'provider_transaction_id' => $transRef,
            'checkout_url'            => $authUrl,
            'status'                  => 'pending',
            'raw'                     => $json,
        ];
    }

    /**
     * Initiate direct push (USSD prompt) to mobile money handset.
     */
    public function initiateDirectPush(Payment $payment, User $student, string $phone, string $method): array
    {
        $url = "{$this->baseUrl}/payments/initialize";
        $cleanPhone = preg_replace('/\D/', '', $phone) ?? '';

        $channel = match ($method) {
            'orange_money' => 'cm.orange',
            default        => 'cm.mtn',
        };

        $payload = [
            'amount'      => (int) $payment->amount,
            'currency'    => 'XAF',
            'email'       => $student->email,
            'reference'   => $payment->external_id,
            'channel'     => $channel,
            'phone'       => $cleanPhone,
            'description' => "Processing fee for Request #{$payment->request_id}",
        ];

        Log::channel('payments')->info('NotchPay initiateDirectPush request', [
            'payment_id'  => $payment->id,
            'external_id' => $payment->external_id,
            'endpoint'    => $url,
            'channel'     => $channel,
        ]);

        $response = Http::withHeaders($this->authHeaders())
            ->timeout(20)
            ->post($url, $payload);

        $json = $response->json() ?? [];

        Log::channel('payments')->info('NotchPay initiateDirectPush response', [
            'status' => $response->status(),
            'body'   => $json,
        ]);

        if (! $response->successful()) {
            $msg = $json['message'] ?? 'Failed to initialize Notch Pay direct push.';
            throw new RuntimeException("NotchPay initiateDirectPush error: {$msg}");
        }

        $transRef = $json['transaction']['reference'] ?? $payment->external_id;

        return [
            'provider_transaction_id' => $transRef,
            'status'                  => 'pending',
            'raw'                     => $json,
        ];
    }

    /**
     * Query authoritative transaction status from Notch Pay API.
     */
    public function fetchStatus(Payment $payment): array
    {
        $reference = $payment->provider_transaction_id ?: $payment->external_id;
        $url = "{$this->baseUrl}/payments/{$reference}";

        Log::channel('payments')->info('NotchPay fetchStatus request', [
            'payment_id' => $payment->id,
            'reference'  => $reference,
        ]);

        $response = Http::withHeaders($this->authHeaders())
            ->timeout(20)
            ->get($url);

        $json = $response->json() ?? [];

        Log::channel('payments')->info('NotchPay fetchStatus response', [
            'status' => $response->status(),
            'body'   => $json,
        ]);

        if (! $response->successful()) {
            $msg = $json['message'] ?? 'Failed to fetch status from Notch Pay.';
            throw new RuntimeException("NotchPay fetchStatus error: {$msg}");
        }

        $transaction = $json['transaction'] ?? $json;
        $rawStatus = strtolower((string) ($transaction['status'] ?? 'pending'));

        $mappedStatus = match ($rawStatus) {
            'complete', 'completed', 'paid' => 'successful',
            'failed'                        => 'failed',
            'canceled', 'cancelled'         => 'cancelled',
            'expired'                       => 'expired',
            default                         => 'pending',
        };

        return [
            'status'                  => $mappedStatus,
            'amount'                  => (int) ($transaction['amount'] ?? $payment->amount),
            'currency'                => 'XAF',
            'provider_transaction_id' => (string) ($transaction['reference'] ?? $reference),
            'failure_reason'          => $mappedStatus === 'failed' ? ($transaction['failure_reason'] ?? $json['message'] ?? 'Payment failed.') : null,
            'raw'                     => $json,
        ];
    }

    /**
     * Expire payment session if supported.
     */
    public function expirePayment(Payment $payment): void
    {
        // Notch Pay handles expiry via session TTL
    }

    /**
     * Verify incoming webhook request signature.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        if (empty($this->webhookSecret)) {
            return true;
        }

        $headerSignature = $request->header('X-Notch-Signature');
        if (empty($headerSignature)) {
            return false;
        }

        $calculatedSignature = hash_hmac('sha256', $request->getContent(), $this->webhookSecret);

        return hash_equals($calculatedSignature, (string) $headerSignature);
    }

    /**
     * Extract the reference from webhook payload.
     */
    public function extractWebhookReference(Request $request): ?string
    {
        return $request->input('data.reference')
            ?? $request->input('reference')
            ?? $request->json('data.reference')
            ?? $request->json('reference')
            ?? null;
    }

    /**
     * Notch Pay API authentication headers.
     *
     * @return array<string, string>
     */
    protected function authHeaders(): array
    {
        $key = $this->privateKey ?: $this->publicKey;

        return [
            'Authorization'   => $key,
            'X-Authorization' => $this->publicKey,
            'Accept'          => 'application/json',
        ];
    }
}
