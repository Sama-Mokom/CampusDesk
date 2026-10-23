<?php

namespace App\Contracts;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Initiate hosted checkout.
     * Returns array with:
     * - 'provider_transaction_id' => string|null
     * - 'checkout_url' => string
     * - 'status' => string ('pending' | 'processing')
     * - 'raw' => array|null
     *
     * @return array{provider_transaction_id: ?string, checkout_url: string, status: string, raw?: ?array}
     */
    public function initiateHosted(Payment $payment, User $student): array;

    /**
     * Initiate direct push (USSD prompt) to mobile money handset.
     * Returns array with:
     * - 'provider_transaction_id' => string|null
     * - 'status' => string ('pending' | 'processing')
     * - 'raw' => array|null
     *
     * @return array{provider_transaction_id: ?string, status: string, raw?: ?array}
     */
    public function initiateDirectPush(Payment $payment, User $student, string $phone, string $method): array;

    /**
     * Query authoritative transaction status from provider API.
     * Returns array with:
     * - 'status' => 'successful' | 'failed' | 'expired' | 'pending' | 'processing'
     * - 'amount' => int (in XAF)
     * - 'currency' => string ('XAF')
     * - 'provider_transaction_id' => string
     * - 'failure_reason' => string|null
     * - 'raw' => array|null
     *
     * @return array{status: string, amount: int, currency: string, provider_transaction_id: string, failure_reason: ?string, raw?: ?array}
     */
    public function fetchStatus(Payment $payment): array;

    /**
     * Expire an active payment session if supported by provider.
     */
    public function expirePayment(Payment $payment): void;

    /**
     * Verify incoming webhook request signature / secret.
     */
    public function verifyWebhookSignature(Request $request): bool;

    /**
     * Extract the reference / external_id from webhook payload.
     */
    public function extractWebhookReference(Request $request): ?string;
}
