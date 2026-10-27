<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Programme;
use App\Models\Request as DocumentRequest;
use App\Models\RequestType;
use App\Models\RequestTypeTier;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReopenTierUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected User $staff;
    protected Faculty $faculty;
    protected Department $department;
    protected Programme $programme;
    protected RequestType $requestType;
    protected RequestTypeTier $normalTier;
    protected RequestTypeTier $superFastTier;
    protected DocumentRequest $docRequest;

    protected function setUp(): void
    {
        parent::setUp();
        FakePaymentGateway::resetSimulatedOutcomes();

        $this->faculty = Faculty::create([
            'name' => 'Faculty of Arts',
            'code' => 'FA',
            'matricule_prefix' => 'AR',
        ]);

        $this->department = Department::create([
            'faculty_id' => $this->faculty->id,
            'name' => 'History Department',
            'code' => 'HIS',
            'type' => 'academic',
        ]);

        $this->programme = Programme::create([
            'department_id' => $this->department->id,
            'name' => 'BA History',
            'code' => 'BAHIS',
            'degree_type' => 'BACHELOR',
        ]);

        $this->student = User::factory()->create([
            'name' => 'Student Evans',
            'email' => 'evans@test.ac.cm',
            'role' => 'student',
        ]);

        $this->student->studentProfile->update([
            'faculty_id' => $this->faculty->id,
            'department_id' => $this->department->id,
            'programme_id' => $this->programme->id,
            'matricule' => 'AR24A010',
            'level' => '400',
            'status' => 'active',
        ]);

        $this->staff = User::factory()->staff()->create([
            'name' => 'Staff Reviewer',
            'email' => 'reviewer@test.ac.cm',
        ]);
        $this->staff->staffProfile->departments()->attach($this->department->id, ['is_primary' => true]);

        $this->requestType = RequestType::create([
            'name' => 'Attestation Request',
            'description' => 'Degree attestation',
            'default_department_sequence' => [$this->department->id],
        ]);

        $this->normalTier = RequestTypeTier::create([
            'request_type_id' => $this->requestType->id,
            'code' => 'normal',
            'name' => 'Normal Mode',
            'fee_amount' => 1000,
            'currency' => 'XAF',
            'turnaround_days' => 14,
            'priority' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->superFastTier = RequestTypeTier::create([
            'request_type_id' => $this->requestType->id,
            'code' => 'super_fast',
            'name' => 'Super Fast Mode',
            'fee_amount' => 5000,
            'currency' => 'XAF',
            'turnaround_days' => 3,
            'priority' => 3,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // Student creates request with Normal Mode (1,000 XAF)
        $createRes = $this->actingAs($this->student, 'sanctum')->postJson('/api/requests', [
            'request_type_id' => $this->requestType->id,
            'request_type_tier_id' => $this->normalTier->id,
        ]);

        $this->docRequest = DocumentRequest::findOrFail($createRes->json('data.id'));

        // Pay for Normal Mode
        $payRes = $this->actingAs($this->student, 'sanctum')->postJson(
            "/api/requests/{$this->docRequest->id}/payments",
            ['checkout_mode' => 'hosted']
        );
        $paymentId = $payRes->json('data.id');
        $this->actingAs($this->student, 'sanctum')->postJson(
            "/api/requests/{$this->docRequest->id}/payments/{$paymentId}/verify"
        );

        $this->docRequest->refresh();
        $this->assertSame('pending', $this->docRequest->status);
        $this->assertSame(1000, $this->docRequest->amount_paid);

        // Staff claims and rejects request
        $stage = $this->docRequest->requestStages()->first();
        $this->actingAs($this->staff, 'sanctum')->postJson("/api/requests/{$this->docRequest->id}/stages/{$stage->id}/claim");
        $this->actingAs($this->staff, 'sanctum')->patchJson("/api/requests/{$this->docRequest->id}/stages/{$stage->id}/resolve", [
            'status' => 'rejected',
            'staff_note' => 'Please provide clearer passport photo.',
        ]);

        $this->docRequest->refresh();
        $this->assertSame('rejected', $this->docRequest->status);
    }

    public function test_reopening_with_same_tier_is_free_and_returns_to_pending(): void
    {
        $response = $this->actingAs($this->student, 'sanctum')->postJson(
            "/api/requests/{$this->docRequest->id}/reopen"
        );

        $response->assertOk();
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.is_reopened', true);
        $response->assertJsonPath('data.tier_name', 'Normal Mode');
        $response->assertJsonCount(2, 'data.available_tiers');
        $response->assertJsonPath('data.available_tiers.0.name', 'Normal Mode');
        $response->assertJsonPath('data.available_tiers.1.name', 'Super Fast Mode');

        $showRes = $this->actingAs($this->student, 'sanctum')->getJson("/api/requests/{$this->docRequest->id}");
        $showRes->assertOk();
        $showRes->assertJsonCount(2, 'data.available_tiers');
        $response->assertJsonPath('data.amount_paid', 1000);
        $response->assertJsonPath('data.amount_due', 0);

        $this->assertDatabaseHas('requests', [
            'id' => $this->docRequest->id,
            'status' => 'pending',
            'is_reopened' => true,
        ]);
    }

    public function test_reopening_with_higher_tier_requires_upgrade_payment(): void
    {
        // Reopen with Super Fast Mode (5,000 XAF, student already paid 1,000 XAF, so difference is 4,000 XAF)
        $reopenRes = $this->actingAs($this->student, 'sanctum')->postJson(
            "/api/requests/{$this->docRequest->id}/reopen",
            ['request_type_tier_id' => $this->superFastTier->id]
        );

        $reopenRes->assertOk();
        $reopenRes->assertJsonPath('data.status', 'awaiting_payment');
        $reopenRes->assertJsonPath('data.pending_upgrade_tier_id', $this->superFastTier->id);
        $reopenRes->assertJsonPath('data.amount_paid', 1000);
        $reopenRes->assertJsonPath('data.amount_due', 4000);

        // Student initiates payment for top-up
        $payRes = $this->actingAs($this->student, 'sanctum')->postJson(
            "/api/requests/{$this->docRequest->id}/payments",
            ['checkout_mode' => 'hosted']
        );
        $payRes->assertStatus(201);
        $payRes->assertJsonPath('data.amount', 4000);
        $payRes->assertJsonPath('data.payment_type', 'reopen_upgrade');

        $paymentId = $payRes->json('data.id');

        // Confirm payment
        $verifyRes = $this->actingAs($this->student, 'sanctum')->postJson(
            "/api/requests/{$this->docRequest->id}/payments/{$paymentId}/verify"
        );

        $verifyRes->assertOk();
        $verifyRes->assertJsonPath('data.request.status', 'pending');
        $verifyRes->assertJsonPath('data.request.tier_name', 'Super Fast Mode');
        $verifyRes->assertJsonPath('data.request.fee_amount', 5000);
        $verifyRes->assertJsonPath('data.request.amount_paid', 5000);
        $verifyRes->assertJsonPath('data.request.amount_due', 0);
        $verifyRes->assertJsonPath('data.request.tier_priority', 3);
        $this->assertNull($verifyRes->json('data.request.pending_upgrade_tier_id'));

        $this->assertDatabaseHas('requests', [
            'id' => $this->docRequest->id,
            'status' => 'pending',
            'request_type_tier_id' => $this->superFastTier->id,
            'pending_upgrade_tier_id' => null,
            'fee_amount' => 5000,
            'amount_paid' => 5000,
            'tier_priority' => 3,
        ]);
    }

    public function test_student_can_cancel_upgrade_and_reopen_at_current_tier(): void
    {
        // Reopen with upgrade
        $this->actingAs($this->student, 'sanctum')->postJson(
            "/api/requests/{$this->docRequest->id}/reopen",
            ['request_type_tier_id' => $this->superFastTier->id]
        );

        $this->docRequest->refresh();
        $this->assertSame('awaiting_payment', $this->docRequest->status);

        // Student changes mind and cancels upgrade
        $cancelRes = $this->actingAs($this->student, 'sanctum')->postJson(
            "/api/requests/{$this->docRequest->id}/reopen",
            ['cancel_upgrade' => true]
        );

        $cancelRes->assertOk();
        $cancelRes->assertJsonPath('data.status', 'pending');
        $cancelRes->assertJsonPath('data.tier_name', 'Normal Mode');
        $cancelRes->assertJsonPath('data.amount_paid', 1000);
        $cancelRes->assertJsonPath('data.amount_due', 0);
        $this->assertNull($cancelRes->json('data.pending_upgrade_tier_id'));

        $this->assertDatabaseHas('requests', [
            'id' => $this->docRequest->id,
            'status' => 'pending',
            'request_type_tier_id' => $this->normalTier->id,
            'pending_upgrade_tier_id' => null,
            'amount_paid' => 1000,
        ]);
    }
}
