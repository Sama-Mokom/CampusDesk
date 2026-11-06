<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Payment;
use App\Models\Programme;
use App\Models\Request as DocumentRequest;
use App\Models\RequestType;
use App\Models\User;
use App\Services\Payments\NotchPayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotchPayGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected Faculty $faculty;
    protected Department $department;
    protected Programme $programme;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faculty = Faculty::create([
            'name'             => 'Faculty of Science',
            'code'             => 'FS',
            'matricule_prefix' => 'SC',
        ]);

        $this->department = Department::create([
            'faculty_id' => $this->faculty->id,
            'name'       => 'Computer Science',
            'code'       => 'CSC',
            'type'       => 'academic',
        ]);

        $this->programme = Programme::create([
            'department_id' => $this->department->id,
            'name'          => 'BSc Computer Science',
            'code'          => 'BSCCSC',
            'degree_type'   => 'BACHELOR',
        ]);

        Config::set('payments.gateways.notchpay.public_key', 'pk_test_123456');
        Config::set('payments.gateways.notchpay.private_key', 'sk_test_654321');
        Config::set('payments.gateways.notchpay.base_url', 'https://api.notchpay.co');
        Config::set('payments.gateways.notchpay.webhook_secret', 'notch-webhook-hash-key');
    }

    public function test_notchpay_initiates_hosted_checkout_with_correct_payload_and_auth(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $type = RequestType::create([
            'name'                        => 'Transcript',
            'default_department_sequence' => [$this->department->id],
        ]);
        $tier = $type->tiers()->create([
            'code'            => 'SUPER_FAST',
            'name'            => 'Super Fast Mode',
            'fee_amount'      => 5000,
            'currency'        => 'XAF',
            'turnaround_days' => 3,
            'priority'        => 3,
            'is_active'       => true,
        ]);

        $request = DocumentRequest::create([
            'student_id'           => $student->id,
            'request_type_id'      => $type->id,
            'request_type_tier_id' => $tier->id,
            'tier_name_snapshot'   => $tier->name,
            'fee_amount'           => $tier->fee_amount,
            'currency'             => 'XAF',
            'status'               => 'awaiting_payment',
            'description'          => 'Test urgent transcript',
            'is_reopened'          => false,
        ]);

        $payment = Payment::create([
            'request_id'           => $request->id,
            'request_type_tier_id' => $tier->id,
            'external_id'          => 'PAY_NOTCH_TEST_01',
            'provider'             => 'notchpay',
            'payment_type'         => 'initial',
            'checkout_mode'        => 'hosted',
            'amount'               => 5000,
            'currency'             => 'XAF',
            'status'               => 'pending',
            'initiated_at'         => now(),
        ]);

        Http::fake([
            'https://api.notchpay.co/payments/initialize' => Http::response([
                'status'            => 'Accepted',
                'authorization_url' => 'https://checkout.notchpay.co/pay/NOTCH_REF_01',
                'transaction'       => [
                    'reference' => 'NOTCH_REF_01',
                    'status'    => 'pending',
                ],
            ], 200),
        ]);

        $gateway = new NotchPayGateway();
        $result = $gateway->initiateHosted($payment, $student);

        Http::assertSent(function ($request) use ($student) {
            return $request->url() === 'https://api.notchpay.co/payments/initialize'
                && $request->header('Authorization')[0] === 'sk_test_654321'
                && $request['amount'] === 5000
                && $request['currency'] === 'XAF'
                && $request['email'] === $student->email
                && $request['reference'] === 'PAY_NOTCH_TEST_01';
        });

        $this->assertSame('NOTCH_REF_01', $result['provider_transaction_id']);
        $this->assertSame('https://checkout.notchpay.co/pay/NOTCH_REF_01', $result['checkout_url']);
        $this->assertSame('pending', $result['status']);
    }

    public function test_notchpay_initiates_direct_push_prompt(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $type = RequestType::create([
            'name'                        => 'Certificate',
            'default_department_sequence' => [$this->department->id],
        ]);
        $tier = $type->tiers()->create([
            'code'            => 'STANDARD',
            'name'            => 'Standard Mode',
            'fee_amount'      => 2000,
            'currency'        => 'XAF',
            'turnaround_days' => 3,
            'priority'        => 2,
            'is_active'       => true,
        ]);

        $request = DocumentRequest::create([
            'student_id'           => $student->id,
            'request_type_id'      => $type->id,
            'request_type_tier_id' => $tier->id,
            'tier_name_snapshot'   => $tier->name,
            'fee_amount'           => $tier->fee_amount,
            'currency'             => 'XAF',
            'status'               => 'awaiting_payment',
            'description'          => 'Test notch push',
            'is_reopened'          => false,
        ]);

        $payment = Payment::create([
            'request_id'           => $request->id,
            'request_type_tier_id' => $tier->id,
            'external_id'          => 'PAY_NOTCH_PUSH_01',
            'provider'             => 'notchpay',
            'payment_type'         => 'initial',
            'checkout_mode'        => 'direct_push',
            'payment_method'       => 'orange_money',
            'payer_phone'          => '237699999999',
            'amount'               => 2000,
            'currency'             => 'XAF',
            'status'               => 'pending',
            'initiated_at'         => now(),
        ]);

        Http::fake([
            'https://api.notchpay.co/payments/initialize' => Http::response([
                'status'      => 'Accepted',
                'transaction' => [
                    'reference' => 'NOTCH_PUSH_REF_777',
                    'status'    => 'pending',
                ],
            ], 200),
        ]);

        $gateway = new NotchPayGateway();
        $result = $gateway->initiateDirectPush($payment, $student, '237699999999', 'orange_money');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.notchpay.co/payments/initialize'
                && $request['amount'] === 2000
                && $request['channel'] === 'cm.orange'
                && $request['phone'] === '237699999999'
                && $request['reference'] === 'PAY_NOTCH_PUSH_01';
        });

        $this->assertSame('NOTCH_PUSH_REF_777', $result['provider_transaction_id']);
        $this->assertSame('pending', $result['status']);
    }

    public function test_notchpay_fetches_status_and_maps_completed_and_failed_states(): void
    {
        $payment = new Payment([
            'external_id'             => 'EXT_NOTCH_123',
            'provider_transaction_id' => 'NOTCH_REF_123',
            'amount'                  => 5000,
            'currency'                => 'XAF',
        ]);

        Http::fake([
            'https://api.notchpay.co/payments/NOTCH_REF_123' => Http::sequence()
                ->push([
                    'status'      => 'Accepted',
                    'transaction' => [
                        'reference' => 'NOTCH_REF_123',
                        'status'    => 'complete',
                        'amount'    => 5000,
                    ],
                ], 200)
                ->push([
                    'status'      => 'Accepted',
                    'transaction' => [
                        'reference'      => 'NOTCH_REF_123',
                        'status'         => 'failed',
                        'amount'         => 5000,
                        'failure_reason' => 'Transaction timed out',
                    ],
                ], 200),
        ]);

        $gateway = new NotchPayGateway();

        // 1. Complete check
        $successResult = $gateway->fetchStatus($payment);
        $this->assertSame('successful', $successResult['status']);
        $this->assertSame(5000, $successResult['amount']);
        $this->assertNull($successResult['failure_reason']);

        // 2. Failed check
        $failedResult = $gateway->fetchStatus($payment);
        $this->assertSame('failed', $failedResult['status']);
        $this->assertSame('Transaction timed out', $failedResult['failure_reason']);
    }

    public function test_notchpay_webhook_hmac_signature_verification_and_reference_extraction(): void
    {
        $gateway = new NotchPayGateway();
        $secret = 'notch-webhook-hash-key';

        $payload = json_encode([
            'event' => 'payment.complete',
            'data'  => [
                'reference' => 'NOTCH_WEBHOOK_REF_01',
                'amount'    => 5000,
                'status'    => 'complete',
            ],
        ]);

        $validSignature = hash_hmac('sha256', $payload, $secret);

        $validRequest = HttpRequest::create(
            '/api/webhooks/payments/notchpay',
            'POST',
            [],
            [],
            [],
            [
                'HTTP_X-Notch-Signature' => $validSignature,
                'CONTENT_TYPE'           => 'application/json',
            ],
            $payload
        );

        $this->assertTrue($gateway->verifyWebhookSignature($validRequest));
        $this->assertSame('NOTCH_WEBHOOK_REF_01', $gateway->extractWebhookReference($validRequest));

        // Invalid signature
        $invalidRequest = HttpRequest::create(
            '/api/webhooks/payments/notchpay',
            'POST',
            [],
            [],
            [],
            ['HTTP_X-Notch-Signature' => 'invalid-hmac-sha256'],
            $payload
        );

        $this->assertFalse($gateway->verifyWebhookSignature($invalidRequest));
    }
}
