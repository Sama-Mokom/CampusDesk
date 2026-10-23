<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Payment;
use App\Models\Programme;
use App\Models\Request as DocumentRequest;
use App\Models\RequestType;
use App\Models\RequestTypeTier;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected User $staff;
    protected Faculty $faculty;
    protected Department $department;
    protected Programme $programme;
    protected RequestType $requestType;
    protected RequestTypeTier $tier;
    protected DocumentRequest $docRequest;

    protected function setUp(): void
    {
        parent::setUp();
        FakePaymentGateway::resetSimulatedOutcomes();

        $this->faculty = Faculty::create([
            'name' => 'Faculty of Engineering',
            'code' => 'FE',
            'matricule_prefix' => 'EN',
        ]);

        $this->department = Department::create([
            'faculty_id' => $this->faculty->id,
            'name' => 'Software Engineering',
            'code' => 'SWE',
            'type' => 'academic',
        ]);

        $this->programme = Programme::create([
            'department_id' => $this->department->id,
            'name' => 'BSc Software Engineering',
            'code' => 'BSCSWE',
            'degree_type' => 'BACHELOR',
        ]);

        $this->student = User::factory()->create([
            'name' => 'Student Dan',
            'email' => 'dan@test.ac.cm',
            'role' => 'student',
        ]);

        StudentProfile::create([
            'user_id' => $this->student->id,
            'faculty_id' => $this->faculty->id,
            'department_id' => $this->department->id,
            'programme_id' => $this->programme->id,
            'matricule' => 'EN24A005',
            'level' => '200',
            'status' => 'active',
        ]);

        $this->staff = User::factory()->staff()->create([
            'name' => 'Staff Officer',
            'email' => 'officer@test.ac.cm',
        ]);
        $this->staff->staffProfile->departments()->attach($this->department->id, ['is_primary' => true]);

        $this->requestType = RequestType::create([
            'name' => 'Certificate Request',
            'description' => 'Degree certificate',
            'default_department_sequence' => [$this->department->id],
        ]);

        $this->tier = RequestTypeTier::create([
            'request_type_id' => $this->requestType->id,
            'code' => 'fast',
            'name' => 'Fast Mode',
            'description' => 'Fast service',
            'fee_amount' => 2500,
            'currency' => 'XAF',
            'turnaround_days' => 5,
            'priority' => 2,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $createRes = $this->actingAs($this->student, 'sanctum')->postJson('/api/requests', [
            'request_type_id' => $this->requestType->id,
            'request_type_tier_id' => $this->tier->id,
        ]);

        $this->docRequest = DocumentRequest::findOrFail($createRes->json('data.id'));
    }

    public function test_student_can_initiate_hosted_checkout(): void
    {
        $response = $this->actingAs($this->student, 'sanctum')->postJson("/api/requests/{$this->docRequest->id}/payments", [
            'checkout_mode' => 'hosted',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.amount', 2500);
        $response->assertJsonPath('data.currency', 'XAF');
        $response->assertJsonPath('data.checkout_mode', 'hosted');
        $response->assertJsonPath('data.status', 'pending');
        $this->assertNotNull($response->json('data.checkout_url'));

        $this->assertDatabaseHas('payments', [
            'request_id' => $this->docRequest->id,
            'amount' => 2500,
            'checkout_mode' => 'hosted',
            'status' => 'pending',
        ]);
    }

    public function test_student_can_initiate_direct_push_payment(): void
    {
        $response = $this->actingAs($this->student, 'sanctum')->postJson("/api/requests/{$this->docRequest->id}/payments", [
            'checkout_mode' => 'direct_push',
            'payment_method' => 'mtn_momo',
            'phone' => '677123456',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.amount', 2500);
        $response->assertJsonPath('data.checkout_mode', 'direct_push');
        $response->assertJsonPath('data.payment_method', 'mtn_momo');
        $response->assertJsonPath('data.payer_phone', '677123456');
        $response->assertJsonPath('data.status', 'processing');

        $this->assertDatabaseHas('payments', [
            'request_id' => $this->docRequest->id,
            'amount' => 2500,
            'checkout_mode' => 'direct_push',
            'payment_method' => 'mtn_momo',
            'status' => 'processing',
        ]);
    }

    public function test_verifying_payment_transitions_request_to_pending_and_records_audit(): void
    {
        $initRes = $this->actingAs($this->student, 'sanctum')->postJson("/api/requests/{$this->docRequest->id}/payments", [
            'checkout_mode' => 'hosted',
        ]);
        $paymentId = $initRes->json('data.id');

        // Student verifies payment (returns from hosted checkout)
        $verifyRes = $this->actingAs($this->student, 'sanctum')->postJson(
            "/api/requests/{$this->docRequest->id}/payments/{$paymentId}/verify"
        );

        $verifyRes->assertOk();
        $verifyRes->assertJsonPath('data.payment.status', 'successful');
        $verifyRes->assertJsonPath('data.request.status', 'pending');
        $verifyRes->assertJsonPath('data.request.amount_paid', 2500);
        $verifyRes->assertJsonPath('data.request.amount_due', 0);
        $this->assertNotNull($verifyRes->json('data.request.paid_at'));
        $this->assertNotNull($verifyRes->json('data.request.target_ready_at'));

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'status' => 'successful',
        ]);

        $this->assertDatabaseHas('requests', [
            'id' => $this->docRequest->id,
            'status' => 'pending',
            'amount_paid' => 2500,
        ]);

        $this->assertDatabaseHas('status_history', [
            'request_id' => $this->docRequest->id,
            'old_status' => 'awaiting_payment',
            'new_status' => 'pending',
        ]);

        // Request now appears in staff queue and staff can claim it
        $queueRes = $this->actingAs($this->staff, 'sanctum')->getJson('/api/stages');
        $queueRes->assertOk();
        $idsInQueue = collect($queueRes->json('data'))->pluck('request_id')->all();
        $this->assertContains($this->docRequest->id, $idsInQueue);

        $stageId = $this->docRequest->requestStages()->first()->id;
        $claimRes = $this->actingAs($this->staff, 'sanctum')->postJson(
            "/api/requests/{$this->docRequest->id}/stages/{$stageId}/claim"
        );
        $claimRes->assertOk();
    }

    public function test_verifying_payment_is_idempotent(): void
    {
        $initRes = $this->actingAs($this->student, 'sanctum')->postJson("/api/requests/{$this->docRequest->id}/payments", [
            'checkout_mode' => 'hosted',
        ]);
        $paymentId = $initRes->json('data.id');

        // First verification
        $this->actingAs($this->student, 'sanctum')->postJson(
            "/api/requests/{$this->docRequest->id}/payments/{$paymentId}/verify"
        )->assertOk();

        $firstAmountPaid = $this->docRequest->fresh()->amount_paid;
        $this->assertSame(2500, $firstAmountPaid);

        // Duplicate verification call (e.g. late webhook or double submit)
        $this->actingAs($this->student, 'sanctum')->postJson(
            "/api/requests/{$this->docRequest->id}/payments/{$paymentId}/verify"
        )->assertOk();

        // Amount paid is NOT double-incremented
        $this->assertSame(2500, $this->docRequest->fresh()->amount_paid);
    }

    public function test_magic_failure_phone_simulates_failed_payment(): void
    {
        $initRes = $this->actingAs($this->student, 'sanctum')->postJson("/api/requests/{$this->docRequest->id}/payments", [
            'checkout_mode' => 'direct_push',
            'payment_method' => 'orange_money',
            'phone' => '699000000', // ends with 00 -> failure trigger
        ]);

        $paymentId = $initRes->json('data.id');

        $verifyRes = $this->actingAs($this->student, 'sanctum')->postJson(
            "/api/requests/{$this->docRequest->id}/payments/{$paymentId}/verify"
        );

        $verifyRes->assertOk();
        $verifyRes->assertJsonPath('data.payment.status', 'failed');
        $verifyRes->assertJsonPath('data.request.status', 'awaiting_payment');
        $verifyRes->assertJsonPath('data.request.amount_paid', 0);
    }
}
