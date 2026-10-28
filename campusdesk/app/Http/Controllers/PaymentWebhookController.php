<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function handle(
        Request $request,
        string $provider,
        PaymentGatewayManager $gatewayManager,
        PaymentService $paymentService,
    ): JsonResponse {
        $gateway = $gatewayManager->driver($provider);

        abort_unless($gateway->verifyWebhookSignature($request), 401, 'Invalid webhook signature.');

        $ref = $gateway->extractWebhookReference($request);
        abort_unless($ref, 400, 'Missing payment reference in webhook payload.');

        $payment = Payment::query()
            ->where('external_id', $ref)
            ->orWhere('provider_transaction_id', $ref)
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Payment reference not found.'], 404);
        }

        $synced = $paymentService->syncStatus($payment);

        return response()->json([
            'status'         => 'processed',
            'payment_status' => $synced->status,
        ]);
    }
}
