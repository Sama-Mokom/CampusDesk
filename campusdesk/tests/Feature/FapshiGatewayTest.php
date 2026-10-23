<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Payment;
use App\Models\Programme;
use App\Models\Request as DocumentRequest;
use App\Models\RequestType;
use App\Models\User;
use App\Services\Payments\FapshiGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FapshiGatewayTest extends TestCase
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

        Config::set('payments.gateways.fapshi.api_user', 'test-user-id');
        Config::set('payments.gateways.fapshi.api_key', 'test-api-key');
        Config::set('payments.gateways.fapshi.base_url', 'https://sandbox.fapshi.com');
        Config::set('payments.gateways.fapshi.webhook_secret', 'secret-wh-token');
    }

    public function test_fapshi_initiates_hosted_checkout_with_correct_payload_and_headers(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $type = RequestType::create([
            'name' => 'Transcript',
            'default_department_sequence' => [$this->department->id],
        ]);
        $tier = $type->tiers()->create([
            'code'            => 'FAST',
            'name'            => 'Fast Mode',
            'fee_amount'      => 2500,
            'currency'        => 'XAF',
            'turnaround_days' => 7,
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
            'description'          => 'Test transcript',
            'is_reopened'          => false,
        ]);

        $payment = Payment::create([
            'request_id'           => $request->id,
            'request_type_tier_id' => $tier->id,
            'external_id'          => 'PAY_FAPSHI_TEST_01',
            'provider'             => 'fapshi',
            'payment_type'         => 'initial',
            'checkout_mode'        => 'hosted',
            'amount'               => 2500,
            'currency'             => 'XAF',
            'status'               => 'pending',
            'initiated_at'         => now(),
        ]);

        Http::fake([
            'https://sandbox.fapshi.com/initiate-pay' => Http::response([
                'link'    => 'https://sandbox.fapshi.com/pay/FAP_TRANS_123',
                'transId' => 'FAP_TRANS_123',
                'message' => 'Success',
            ], 200),
        ]);

        $gateway = new FapshiGateway();
        $result = $gateway->initiateHosted($payment, $student);

        Http::assertSent(function ($request) use ($student) {
            return $request->url() === 'https://sandbox.fapshi.com/initiate-pay'
                && $request->header('apiuser')[0] === 'test-user-id'
                && $request->header('apikey')[0] === 'test-api-key'
                && $request['amount'] === 2500
                && $request['email'] === $student->email
                && $request['externalId'] === 'PAY_FAPSHI_TEST_01';
        });

        $this->assertSame('FAP_TRANS_123', $result['provider_transaction_id']);
        $this->assertSame('https://sandbox.fapshi.com/pay/FAP_TRANS_123', $result['checkout_url']);
        $this->assertSame('pending', $result['status']);
    }

    public function test_fapshi_initiates_direct_push_prompt(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $type = RequestType::create([
            'name' => 'Enrollment Letter',
            'default_department_sequence' => [$this->department->id],
        ]);
        $tier = $type->tiers()->create([
            'code'            => 'NORMAL',
            'name'            => 'Normal Mode',
            'fee_amount'      => 1000,
            'currency'        => 'XAF',
            'turnaround_days' => 14,
            'priority'        => 1,
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
            'description'          => 'Test direct push',
            'is_reopened'          => false,
        ]);

        $payment = Payment::create([
            'request_id'           => $request->id,
            'request_type_tier_id' => $tier->id,
            'external_id'          => 'PAY_FAPSHI_PUSH_01',
            'provider'             => 'fapshi',
            'payment_type'         => 'initial',
            'checkout_mode'        => 'direct_push',
            'payment_method'       => 'mtn_momo',
            'payer_phone'          => '237670000001',
            'amount'               => 1000,
            'currency'             => 'XAF',
            'status'               => 'pending',
            'initiated_at'         => now(),
        ]);

        Http::fake([
            'https://sandbox.fapshi.com/direct-pay' => Http::response([
                'transId' => 'FAP_PUSH_TRANS_999',
                'status'  => 'CREATED',
                'message' => 'Prompt dispatched to phone',
            ], 200),
        ]);

        $gateway = new FapshiGateway();
        $result = $gateway->initiateDirectPush($payment, $student, '237670000001', 'mtn_momo');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sandbox.fapshi.com/direct-pay'
                && $request['amount'] === 1000
                && $request['phone'] === '237670000001'
                && $request['externalId'] === 'PAY_FAPSHI_PUSH_01';
        });

        $this->assertSame('FAP_PUSH_TRANS_999', $result['provider_transaction_id']);
        $this->assertSame('pending', $result['status']);
    }

    public function test_fapshi_fetches_status_and_maps_successful_and_failed_states(): void
    {
        $payment = new Payment([
            'external_id'             => 'EXT_123',
            'provider_transaction_id' => 'FAP_TRANS_123',
            'amount'                  => 2500,
            'currency'                => 'XAF',
        ]);

        Http::fake([
            'https://sandbox.fapshi.com/payment-status/FAP_TRANS_123' => Http::sequence()
                ->push([
                    'status'  => 'SUCCESSFUL',
                    'amount'  => 2500,
                    'transId' => 'FAP_TRANS_123',
                ], 200)
                ->push([
                    'status'  => 'FAILED',
                    'amount'  => 2500,
                    'transId' => 'FAP_TRANS_123',
                    'reason'  => 'Insufficient funds',
                ], 200),
        ]);

        $gateway = new FapshiGateway();

        // 1. Success check
        $successResult = $gateway->fetchStatus($payment);
        $this->assertSame('successful', $successResult['status']);
        $this->assertSame(2500, $successResult['amount']);
        $this->assertNull($successResult['failure_reason']);

        // 2. Failed check
        $failedResult = $gateway->fetchStatus($payment);
        $this->assertSame('failed', $failedResult['status']);
        $this->assertSame('Insufficient funds', $failedResult['failure_reason']);
    }

    public function test_fapshi_webhook_verification_and_reference_extraction(): void
    {
        $gateway = new FapshiGateway();

        // Valid secret header
        $validRequest = HttpRequest::create('/api/webhooks/payments/fapshi', 'POST', [
            'transId'    => 'FAP_WEBHOOK_01',
            'externalId' => 'PAY_EXT_REF_01',
            'status'     => 'SUCCESSFUL',
        ], [], [], ['HTTP_x-wh-secret' => 'secret-wh-token']);

        $this->assertTrue($gateway->verifyWebhookSignature($validRequest));
        $this->assertSame('PAY_EXT_REF_01', $gateway->extractWebhookReference($validRequest));

        // Invalid secret header
        $invalidRequest = HttpRequest::create('/api/webhooks/payments/fapshi', 'POST', [], [], [], [
            'HTTP_x-wh-secret' => 'wrong-secret',
        ]);
        $this->assertFalse($gateway->verifyWebhookSignature($invalidRequest));
    }
}
