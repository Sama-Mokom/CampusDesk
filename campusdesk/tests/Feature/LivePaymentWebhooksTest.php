<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Payment;
use App\Models\Programme;
use App\Models\Request as DocumentRequest;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LivePaymentWebhooksTest extends TestCase
{
    use RefreshDatabase;

    protected Faculty $faculty;
    protected Department $department;
    protected Programme $programme;
    protected User $student;
    protected RequestType $type;

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

        $this->student = User::factory()->create(['role' => 'student']);

        $this->type = RequestType::create([
            'name'                        => 'Transcript',
            'default_department_sequence' => [$this->department->id],
        ]);

        Config::set('payments.gateways.fapshi.api_user', 'fapshi-user');
        Config::set('payments.gateways.fapshi.api_key', 'fapshi-key');
        Config::set('payments.gateways.fapshi.base_url', 'https://sandbox.fapshi.com');
        Config::set('payments.gateways.fapshi.webhook_secret', 'secret-fapshi-token');

        Config::set('payments.gateways.notchpay.public_key', 'pk_test_123');
        Config::set('payments.gateways.notchpay.private_key', 'sk_test_123');
        Config::set('payments.gateways.notchpay.base_url', 'https://api.notchpay.co');
        Config::set('payments.gateways.notchpay.webhook_secret', 'notch-hmac-secret');
    }

    public function test_fapshi_webhook_rejects_invalid_secret_and_accepts_valid_with_authoritative_sync(): void
    {
        $tier = $this->type->tiers()->create([
            'code'            => 'FAST',
            'name'            => 'Fast Mode',
            'fee_amount'      => 2500,
            'currency'        => 'XAF',
            'turnaround_days' => 7,
            'priority'        => 2,
            'is_active'       => true,
        ]);

        $request = DocumentRequest::create([
            'student_id'              => $this->student->id,
            'request_type_id'         => $this->type->id,
            'request_type_tier_id'    => $tier->id,
            'tier_name_snapshot'      => $tier->name,
            'fee_amount'              => $tier->fee_amount,
            'amount_paid'             => 0,
            'currency'                => 'XAF',
            'turnaround_days_snapshot'=> 7,
            'tier_priority'           => 2,
            'status'                  => 'awaiting_payment',
            'description'             => 'Awaiting fee payment',
            'is_reopened'             => false,
        ]);

        $payment = Payment::create([
            'request_id'              => $request->id,
            'request_type_tier_id'    => $tier->id,
            'external_id'             => 'FAP_PAY_ULID_01',
            'provider'                => 'fapshi',
            'provider_transaction_id' => 'TRANS_FAP_01',
            'payment_type'            => 'initial',
            'checkout_mode'           => 'hosted',
            'amount'                  => 2500,
            'currency'                => 'XAF',
            'status'                  => 'pending',
            'initiated_at'            => now(),
        ]);

        // 1. Invalid secret returns 401
        $this->postJson('/api/webhooks/payments/fapshi', [
            'transId'    => 'TRANS_FAP_01',
            'externalId' => 'FAP_PAY_ULID_01',
            'status'     => 'SUCCESSFUL',
        ], [
            'x-wh-secret' => 'wrong-secret',
        ])->assertStatus(401);

        // 2. Mock authoritative status check on Fapshi API
        Http::fake([
            'https://sandbox.fapshi.com/payment-status/TRANS_FAP_01' => Http::response([
                'status'  => 'SUCCESSFUL',
                'amount'  => 2500,
                'transId' => 'TRANS_FAP_01',
            ], 200),
        ]);

        // 3. Valid secret processes payment and updates request
        $response = $this->postJson('/api/webhooks/payments/fapshi', [
            'transId'    => 'TRANS_FAP_01',
            'externalId' => 'FAP_PAY_ULID_01',
            'status'     => 'SUCCESSFUL',
        ], [
            'x-wh-secret' => 'secret-fapshi-token',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status'         => 'processed',
            'payment_status' => 'successful',
        ]);

        $payment->refresh();
        $this->assertSame('successful', $payment->status);

        $request->refresh();
        $this->assertSame('pending', $request->status);
        $this->assertSame(2500, $request->amount_paid);
        $this->assertNotNull($request->paid_at);

        // 4. Idempotent re-send of webhook returns 200 without double-crediting
        $duplicateResponse = $this->postJson('/api/webhooks/payments/fapshi', [
            'transId'    => 'TRANS_FAP_01',
            'externalId' => 'FAP_PAY_ULID_01',
            'status'     => 'SUCCESSFUL',
        ], [
            'x-wh-secret' => 'secret-fapshi-token',
        ]);

        $duplicateResponse->assertStatus(200);
        $request->refresh();
        $this->assertSame(2500, $request->amount_paid);
    }

    public function test_notchpay_webhook_verifies_hmac_and_syncs_status_authoritatively(): void
    {
        $tier = $this->type->tiers()->create([
            'code'            => 'SUPER_FAST',
            'name'            => 'Super Fast Mode',
            'fee_amount'      => 5000,
            'currency'        => 'XAF',
            'turnaround_days' => 3,
            'priority'        => 3,
            'is_active'       => true,
        ]);

        $request = DocumentRequest::create([
            'student_id'              => $this->student->id,
            'request_type_id'         => $this->type->id,
            'request_type_tier_id'    => $tier->id,
            'tier_name_snapshot'      => $tier->name,
            'fee_amount'              => $tier->fee_amount,
            'amount_paid'             => 0,
            'currency'                => 'XAF',
            'turnaround_days_snapshot'=> 3,
            'tier_priority'           => 3,
            'status'                  => 'awaiting_payment',
            'description'             => 'Urgent certificate',
            'is_reopened'             => false,
        ]);

        $payment = Payment::create([
            'request_id'              => $request->id,
            'request_type_tier_id'    => $tier->id,
            'external_id'             => 'NOTCH_PAY_ULID_02',
            'provider'                => 'notchpay',
            'provider_transaction_id' => 'TRANS_NOTCH_02',
            'payment_type'            => 'initial',
            'checkout_mode'           => 'hosted',
            'amount'                  => 5000,
            'currency'                => 'XAF',
            'status'                  => 'pending',
            'initiated_at'            => now(),
        ]);

        $payload = json_encode([
            'event' => 'payment.complete',
            'data'  => [
                'reference' => 'NOTCH_PAY_ULID_02',
                'amount'    => 5000,
                'status'    => 'complete',
            ],
        ]);

        // 1. Invalid signature returns 401
        $this->call(
            'POST',
            '/api/webhooks/payments/notchpay',
            [],
            [],
            [],
            [
                'HTTP_X-Notch-Signature' => 'bogus-signature',
                'CONTENT_TYPE'           => 'application/json',
            ],
            $payload
        )->assertStatus(401);

        // 2. Authoritative check mock
        Http::fake([
            'https://api.notchpay.co/payments/TRANS_NOTCH_02' => Http::response([
                'status'      => 'Accepted',
                'transaction' => [
                    'reference' => 'TRANS_NOTCH_02',
                    'status'    => 'complete',
                    'amount'    => 5000,
                ],
            ], 200),
            'https://api.notchpay.co/payments/NOTCH_PAY_ULID_02' => Http::response([
                'status'      => 'Accepted',
                'transaction' => [
                    'reference' => 'NOTCH_PAY_ULID_02',
                    'status'    => 'complete',
                    'amount'    => 5000,
                ],
            ], 200),
        ]);

        // 3. Valid HMAC signature succeeds
        $validSignature = hash_hmac('sha256', $payload, 'notch-hmac-secret');

        $response = $this->call(
            'POST',
            '/api/webhooks/payments/notchpay',
            [],
            [],
            [],
            [
                'HTTP_X-Notch-Signature' => $validSignature,
                'CONTENT_TYPE'           => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertSame('successful', $payment->status);

        $request->refresh();
        $this->assertSame('pending', $request->status);
        $this->assertSame(5000, $request->amount_paid);
    }
}
