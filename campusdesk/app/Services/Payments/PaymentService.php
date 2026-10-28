<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Payment;
use App\Models\Request as DocumentRequest;
use App\Models\RequestTypeTier;
use App\Services\RequestEventRecorder;
use App\Services\RequestStatusNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayInterface $gateway,
        protected RequestEventRecorder $events,
        protected RequestStatusNotificationService $notifications,
    ) {}

    /**
     * Initiate a payment session (hosted or direct push).
     */
    public function initiate(
        DocumentRequest $request,
        string $checkoutMode,
        ?string $paymentMethod = null,
        ?string $phone = null,
    ): Payment {
        if ($request->status !== DocumentRequest::STATUS_AWAITING_PAYMENT) {
            throw new HttpException(422, 'Payments can only be initiated for requests awaiting payment.');
        }

        $amountDue = $request->amountDue();
        if ($amountDue <= 0) {
            throw new HttpException(422, 'This request does not require any fee payment.');
        }

        if ($checkoutMode === 'direct_push') {
            if (! $phone || ! $paymentMethod) {
                throw new HttpException(422, 'Phone number and payment method are required for direct push.');
            }
        }

        $targetTier = $request->pendingUpgradeTier ?: $request->requestTypeTier;
        if (! $targetTier) {
            throw new HttpException(422, 'No active service tier found for this request.');
        }

        $paymentType = ($request->is_reopened && $request->pending_upgrade_tier_id)
            ? 'reopen_upgrade'
            : 'initial';

        return DB::transaction(function () use ($request, $targetTier, $checkoutMode, $paymentMethod, $phone, $amountDue, $paymentType) {
            // Expire previous active pending/processing payments
            $request->payments()
                ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])
                ->update(['status' => Payment::STATUS_EXPIRED]);

            $payment = Payment::create([
                'request_id'           => $request->id,
                'request_type_tier_id' => $targetTier->id,
                'external_id'          => (string) Str::ulid(),
                'provider'             => config('payments.default', 'fake'),
                'payment_type'         => $paymentType,
                'checkout_mode'        => $checkoutMode,
                'payment_method'       => $paymentMethod,
                'payer_phone'          => $phone ? trim($phone) : null,
                'amount'               => $amountDue,
                'currency'             => 'XAF',
                'status'               => Payment::STATUS_PENDING,
                'initiated_at'         => now(),
                'expires_at'           => now()->addMinutes(30),
            ]);

            $student = $request->student;

            if ($checkoutMode === 'direct_push') {
                $res = $this->gateway->initiateDirectPush($payment, $student, (string) $phone, (string) $paymentMethod);
            } else {
                $res = $this->gateway->initiateHosted($payment, $student);
            }

            $payment->update([
                'provider_transaction_id' => $res['provider_transaction_id'] ?? null,
                'checkout_url'            => $res['checkout_url'] ?? null,
                'status'                  => $res['status'] ?? Payment::STATUS_PENDING,
                'raw_last_payload'        => $res['raw'] ?? null,
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Switch tier while awaiting payment.
     */
    public function switchTierWhileAwaitingPayment(DocumentRequest $request, RequestTypeTier $newTier): DocumentRequest
    {
        return DB::transaction(function () use ($request, $newTier) {
            $lockedRequest = DocumentRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== DocumentRequest::STATUS_AWAITING_PAYMENT) {
                throw new HttpException(422, 'Tier can only be changed while awaiting payment.');
            }

            if ($newTier->request_type_id !== $lockedRequest->request_type_id) {
                throw new HttpException(422, 'The selected tier does not belong to this document type.');
            }

            // Expire active payments
            $lockedRequest->payments()
                ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])
                ->update(['status' => Payment::STATUS_EXPIRED]);

            if ($lockedRequest->is_reopened) {
                if ($newTier->id === $lockedRequest->request_type_tier_id) {
                    // Cancelling upgrade, resuming with current paid tier
                    $lockedRequest->pending_upgrade_tier_id = null;

                    if ($lockedRequest->amount_paid >= $lockedRequest->fee_amount) {
                        $lockedRequest->status = DocumentRequest::STATUS_PENDING;
                        $lockedRequest->save();

                        $lockedRequest->requestStages()
                            ->where('status', 'rejected')
                            ->update(['status' => 'pending', 'handled_by' => null]);

                        $lockedRequest->statusHistories()->create([
                            'old_status' => DocumentRequest::STATUS_AWAITING_PAYMENT,
                            'new_status' => DocumentRequest::STATUS_PENDING,
                            'changed_by' => $lockedRequest->student_id,
                            'note'       => "Upgrade cancelled; reopened with {$lockedRequest->tier_name_snapshot} tier.",
                        ]);

                        $this->events->recordRequestStatusChanged($lockedRequest);
                        $this->notifications->notifyStudent($lockedRequest, 'pending');

                        return $lockedRequest->fresh(['requestType', 'requestTypeTier', 'pendingUpgradeTier']);
                    }
                } else {
                    $lockedRequest->pending_upgrade_tier_id = $newTier->id;
                }
            } else {
                // Initial request: update snapshot
                $lockedRequest->request_type_tier_id = $newTier->id;
                $lockedRequest->tier_name_snapshot = $newTier->name;
                $lockedRequest->fee_amount = $newTier->fee_amount;
                $lockedRequest->turnaround_days_snapshot = $newTier->turnaround_days;
                $lockedRequest->tier_priority = $newTier->priority;
            }

            $lockedRequest->save();

            return $lockedRequest->fresh(['requestType', 'requestTypeTier', 'pendingUpgradeTier']);
        });
    }

    /**
     * Idempotently synchronize status from gateway.
     */
    public function syncStatus(Payment $payment, ?array $overrideResult = null): Payment
    {
        return DB::transaction(function () use ($payment, $overrideResult) {
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedRequest = DocumentRequest::query()
                ->whereKey($lockedPayment->request_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Terminal payments are immutable
            if ($lockedPayment->isTerminal()) {
                return $lockedPayment;
            }

            $res = $overrideResult ?: $this->gateway->fetchStatus($lockedPayment);

            if ($res['status'] === Payment::STATUS_SUCCESSFUL) {
                $lockedPayment->update([
                    'status'           => Payment::STATUS_SUCCESSFUL,
                    'completed_at'     => now(),
                    'raw_last_payload' => $res['raw'] ?? null,
                ]);

                if ($lockedPayment->payment_type === 'reopen_upgrade' && $lockedRequest->pending_upgrade_tier_id) {
                    $newTier = RequestTypeTier::findOrFail($lockedRequest->pending_upgrade_tier_id);

                    $lockedRequest->requestStages()
                        ->where('status', 'rejected')
                        ->update(['status' => 'pending', 'handled_by' => null]);

                    $lockedRequest->update([
                        'request_type_tier_id'     => $newTier->id,
                        'pending_upgrade_tier_id' => null,
                        'tier_name_snapshot'       => $newTier->name,
                        'fee_amount'               => $newTier->fee_amount,
                        'turnaround_days_snapshot' => $newTier->turnaround_days,
                        'tier_priority'            => $newTier->priority,
                        'amount_paid'              => $lockedRequest->amount_paid + $lockedPayment->amount,
                        'paid_at'                  => now(),
                        'target_ready_at'          => now()->addDays($newTier->turnaround_days),
                        'status'                   => DocumentRequest::STATUS_PENDING,
                        'is_reopened'              => true,
                    ]);

                    $lockedRequest->statusHistories()->create([
                        'old_status' => DocumentRequest::STATUS_AWAITING_PAYMENT,
                        'new_status' => DocumentRequest::STATUS_PENDING,
                        'changed_by' => $lockedRequest->student_id,
                        'note'       => "Tier upgraded to {$newTier->name} and fee confirmed ({$lockedPayment->amount} XAF via {$lockedPayment->provider}). Request returned to queue.",
                    ]);

                    $this->events->recordRequestStatusChanged($lockedRequest);
                    $this->notifications->notifyStudent($lockedRequest, 'pending');
                } else {
                    $lockedRequest->update([
                        'amount_paid'     => $lockedRequest->amount_paid + $lockedPayment->amount,
                        'paid_at'         => now(),
                        'target_ready_at' => now()->addDays($lockedRequest->turnaround_days_snapshot),
                        'status'          => DocumentRequest::STATUS_PENDING,
                    ]);

                    $lockedRequest->statusHistories()->create([
                        'old_status' => DocumentRequest::STATUS_AWAITING_PAYMENT,
                        'new_status' => DocumentRequest::STATUS_PENDING,
                        'changed_by' => $lockedRequest->student_id,
                        'note'       => "Processing fee paid ({$lockedPayment->amount} XAF via {$lockedPayment->provider}). Request entered queue.",
                    ]);

                    $firstDepartmentId = $lockedRequest->requestStages()->orderBy('sequence_order')->value('department_id');
                    if ($firstDepartmentId) {
                        $this->events->recordRequestCreated($lockedRequest, $firstDepartmentId);
                    }
                    $this->notifications->notifyStudent($lockedRequest, 'pending');
                }
            } elseif (in_array($res['status'], [Payment::STATUS_FAILED, Payment::STATUS_EXPIRED, Payment::STATUS_CANCELLED], true)) {
                $lockedPayment->update([
                    'status'           => $res['status'],
                    'failure_reason'   => $res['failure_reason'] ?? null,
                    'completed_at'     => now(),
                    'raw_last_payload' => $res['raw'] ?? null,
                ]);
            } else {
                $lockedPayment->update([
                    'status'           => $res['status'],
                    'raw_last_payload' => $res['raw'] ?? null,
                ]);
            }

            return $lockedPayment->fresh();
        });
    }
}
