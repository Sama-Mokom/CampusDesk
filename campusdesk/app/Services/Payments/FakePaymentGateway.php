<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FakePaymentGateway implements PaymentGatewayInterface
{
    /**
     * @var array<string, array{status: string, failure_reason?: string}>
     */
    protected static array $simulatedOutcomes = [];

    public static function setSimulatedOutcome(string $externalId, string $status, ?string $failureReason = null): void
    {
        self::$simulatedOutcomes[$externalId] = [
            'status' => $status,
            'failure_reason' => $failureReason,
        ];
    }

    public static function resetSimulatedOutcomes(): void
    {
        self::$simulatedOutcomes = [];
    }

    public function initiateHosted(Payment $payment, User $student): array
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
        $transId = 'fake_' . Str::random(16);

        return [
            'provider_transaction_id' => $transId,
            'checkout_url' => "{$frontendUrl}/student/requests/{$payment->request_id}?payment=return&external_id={$payment->external_id}",
            'status' => 'pending',
            'raw' => [
                'driver' => 'fake',
                'checkout_mode' => 'hosted',
                'trans_id' => $transId,
            ],
        ];
    }

    public function initiateDirectPush(Payment $payment, User $student, string $phone, string $method): array
    {
        $transId = 'fake_' . Str::random(16);

        // Magic numbers for automated failure testing:
        // Phone ending with 00 simulates immediate failure
        $isFailure = str_ends_with(trim($phone), '00');

        return [
            'provider_transaction_id' => $transId,
            'status' => $isFailure ? 'failed' : 'processing',
            'raw' => [
                'driver' => 'fake',
                'checkout_mode' => 'direct_push',
                'phone' => $phone,
                'method' => $method,
                'trans_id' => $transId,
            ],
        ];
    }

    public function fetchStatus(Payment $payment): array
    {
        // 1. Check if an explicit test/simulation outcome was queued
        if (isset(self::$simulatedOutcomes[$payment->external_id])) {
            $sim = self::$simulatedOutcomes[$payment->external_id];

            return [
                'status' => $sim['status'],
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'provider_transaction_id' => $payment->provider_transaction_id ?? ('fake_' . Str::random(12)),
                'failure_reason' => $sim['failure_reason'] ?? null,
                'raw' => ['simulated' => true, 'source' => 'explicit_override'],
            ];
        }

        // 2. Check magic phone numbers
        if ($payment->payer_phone && str_ends_with(trim($payment->payer_phone), '00')) {
            return [
                'status' => 'failed',
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'provider_transaction_id' => $payment->provider_transaction_id ?? ('fake_' . Str::random(12)),
                'failure_reason' => 'Insufficient funds on mobile money account (simulated).',
                'raw' => ['simulated' => true, 'source' => 'magic_phone_failed'],
            ];
        }

        // 3. Default fake behavior: payment succeeds on verify
        return [
            'status' => 'successful',
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'provider_transaction_id' => $payment->provider_transaction_id ?? ('fake_' . Str::random(12)),
            'failure_reason' => null,
            'raw' => ['simulated' => true, 'source' => 'default_success'],
        ];
    }

    public function expirePayment(Payment $payment): void
    {
        // No remote session to invalidate in fake driver
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return true;
    }

    public function extractWebhookReference(Request $request): ?string
    {
        return $request->input('external_id')
            ?? $request->input('reference')
            ?? $request->input('externalId');
    }
}
