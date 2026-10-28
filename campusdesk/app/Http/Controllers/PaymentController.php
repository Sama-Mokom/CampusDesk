<?php

namespace App\Http\Controllers;

use App\Http\Resources\PaymentResource;
use App\Http\Resources\RequestResource;
use App\Models\Payment;
use App\Models\Request as DocumentRequest;
use App\Models\RequestTypeTier;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    /**
     * Initiate a payment session (hosted or direct push).
     */
    public function initiate(
        Request $request,
        DocumentRequest $docRequest,
        PaymentService $paymentService,
    ): JsonResource|JsonResponse {
        abort_unless($docRequest->student_id === $request->user()->id, 403);

        $validated = $request->validate([
            'checkout_mode'  => ['required', Rule::in(['hosted', 'direct_push'])],
            'payment_method' => ['required_if:checkout_mode,direct_push', 'nullable', Rule::in(['mtn_momo', 'orange_money'])],
            'phone'          => ['required_if:checkout_mode,direct_push', 'nullable', 'string', 'min:8', 'max:20'],
        ]);

        $payment = $paymentService->initiate(
            $docRequest,
            $validated['checkout_mode'],
            $validated['payment_method'] ?? null,
            $validated['phone'] ?? null,
        );

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Poll / verify payment status from provider.
     */
    public function verify(
        Request $request,
        DocumentRequest $docRequest,
        Payment $payment,
        PaymentService $paymentService,
    ): JsonResponse {
        abort_unless($docRequest->student_id === $request->user()->id, 403);
        abort_unless($payment->request_id === $docRequest->id, 404);

        if (config('payments.default') === 'fake' && $request->has('fake_outcome')) {
            $outcome = $request->input('fake_outcome');
            if (in_array($outcome, ['successful', 'failed', 'expired'], true)) {
                $reason = $outcome === 'failed' ? 'Simulated failure from tester UI.' : null;
                FakePaymentGateway::setSimulatedOutcome($payment->external_id, $outcome, $reason);
            }
        }

        $synced = $paymentService->syncStatus($payment);

        return response()->json([
            'data' => [
                'payment' => new PaymentResource($synced),
                'request' => new RequestResource(
                    $docRequest->fresh([
                        'requestType',
                        'requestStages.department',
                        'payments',
                        'pendingUpgradeTier',
                        'statusHistories',
                    ])
                ),
            ],
        ]);
    }

    /**
     * Switch tier while awaiting payment.
     */
    public function switchTier(
        Request $request,
        DocumentRequest $docRequest,
        PaymentService $paymentService,
    ): JsonResponse {
        abort_unless($docRequest->student_id === $request->user()->id, 403);

        $validated = $request->validate([
            'request_type_tier_id' => ['required', 'integer', 'exists:request_type_tiers,id'],
        ]);

        $newTier = RequestTypeTier::findOrFail($validated['request_type_tier_id']);

        $updated = $paymentService->switchTierWhileAwaitingPayment($docRequest, $newTier);

        return response()->json([
            'data' => new RequestResource(
                $updated->fresh([
                    'requestType',
                    'requestStages.department',
                    'payments',
                    'pendingUpgradeTier',
                    'statusHistories',
                ])
            ),
        ]);
    }
}
