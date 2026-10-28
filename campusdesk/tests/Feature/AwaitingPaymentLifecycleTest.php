<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Programme;
use App\Models\Request as DocumentRequest;
use App\Models\RequestType;
use App\Models\RequestTypeTier;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AwaitingPaymentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected User $staff;
    protected Faculty $faculty;
    protected Department $department;
    protected Programme $programme;
    protected RequestType $requestType;
    protected RequestTypeTier $normalTier;
    protected RequestTypeTier $fastTier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faculty = Faculty::create([
            'name' => 'Faculty of Science',
            'code' => 'FS',
            'matricule_prefix' => 'SC',
        ]);

        $this->department = Department::create([
            'faculty_id' => $this->faculty->id,
            'name' => 'Computer Science',
            'code' => 'CSC',
            'type' => 'academic',
        ]);

        $this->programme = Programme::create([
            'department_id' => $this->department->id,
            'name' => 'BSc Computer Science',
            'code' => 'BSCCSC',
            'degree_type' => 'BACHELOR',
        ]);

        $this->student = User::factory()->create([
            'name' => 'Student Alice',
            'email' => 'alice@test.ac.cm',
            'role' => 'student',
        ]);

        $this->student->studentProfile->update([
            'faculty_id' => $this->faculty->id,
            'department_id' => $this->department->id,
            'programme_id' => $this->programme->id,
            'matricule' => 'SC24A001',
            'level' => '300',
            'status' => 'active',
        ]);

        $this->staff = User::factory()->staff()->create([
            'name' => 'Staff Bob',
            'email' => 'bob@test.ac.cm',
        ]);
        $this->staff->staffProfile->departments()->attach($this->department->id, ['is_primary' => true]);

        $this->requestType = RequestType::create([
            'name' => 'Transcript Request',
            'description' => 'Official academic transcript',
            'default_department_sequence' => [$this->department->id],
        ]);

        $this->normalTier = RequestTypeTier::create([
            'request_type_id' => $this->requestType->id,
            'code' => 'normal',
            'name' => 'Normal Mode',
            'description' => 'Processed in 14 working days',
            'fee_amount' => 1000,
            'currency' => 'XAF',
            'turnaround_days' => 14,
            'priority' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->fastTier = RequestTypeTier::create([
            'request_type_id' => $this->requestType->id,
            'code' => 'fast',
            'name' => 'Fast Mode',
            'description' => 'Processed in 7 working days',
            'fee_amount' => 2500,
            'currency' => 'XAF',
            'turnaround_days' => 7,
            'priority' => 2,
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }

    public function test_new_request_is_created_with_awaiting_payment_status(): void
    {
        $response = $this->actingAs($this->student, 'sanctum')->postJson('/api/requests', [
            'request_type_id' => $this->requestType->id,
            'request_type_tier_id' => $this->normalTier->id,
            'description' => 'Need my transcript',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'awaiting_payment');
        $response->assertJsonPath('data.fee_amount', 1000);
        $response->assertJsonPath('data.amount_paid', 0);
        $response->assertJsonPath('data.amount_due', 1000);
        $response->assertJsonPath('data.tier_name', 'Normal Mode');

        $this->assertDatabaseHas('requests', [
            'id' => $response->json('data.id'),
            'status' => 'awaiting_payment',
            'fee_amount' => 1000,
            'amount_paid' => 0,
        ]);

        $this->assertDatabaseHas('status_history', [
            'request_id' => $response->json('data.id'),
            'old_status' => null,
            'new_status' => 'awaiting_payment',
        ]);
    }

    public function test_unpaid_request_is_hidden_from_staff_claimable_queue(): void
    {
        $createRes = $this->actingAs($this->student, 'sanctum')->postJson('/api/requests', [
            'request_type_id' => $this->requestType->id,
            'request_type_tier_id' => $this->normalTier->id,
        ]);
        $requestId = $createRes->json('data.id');

        // Staff visits /stages (claimable queue)
        $queueRes = $this->actingAs($this->staff, 'sanctum')->getJson('/api/stages');
        $queueRes->assertOk();

        $idsInQueue = collect($queueRes->json('data'))->pluck('request_id')->all();
        $this->assertNotContains($requestId, $idsInQueue);
    }

    public function test_staff_cannot_claim_stage_while_request_is_awaiting_payment(): void
    {
        $createRes = $this->actingAs($this->student, 'sanctum')->postJson('/api/requests', [
            'request_type_id' => $this->requestType->id,
            'request_type_tier_id' => $this->normalTier->id,
        ]);
        $requestId = $createRes->json('data.id');
        $stageId = $createRes->json('data.stages.0.id');

        $claimRes = $this->actingAs($this->staff, 'sanctum')->postJson("/api/requests/{$requestId}/stages/{$stageId}/claim");
        $claimRes->assertStatus(422);
        $claimRes->assertJsonFragment([
            'message' => 'This request cannot be claimed until the processing fee is paid.',
        ]);
    }

    public function test_dept_admin_overview_marks_unpaid_request_as_not_claimable(): void
    {
        $deptAdmin = User::factory()->staff()->create([
            'name' => 'Dept Admin Carol',
            'email' => 'carol@test.ac.cm',
        ]);
        $deptAdmin->staffProfile->update(['admin_level' => 'dept_admin']);
        $deptAdmin->staffProfile->departments()->attach($this->department->id, ['is_primary' => true]);

        $createRes = $this->actingAs($this->student, 'sanctum')->postJson('/api/requests', [
            'request_type_id' => $this->requestType->id,
            'request_type_tier_id' => $this->normalTier->id,
        ]);
        $requestId = $createRes->json('data.id');

        $deptRes = $this->actingAs($deptAdmin, 'sanctum')->getJson('/api/dept-admin/requests');
        $deptRes->assertOk();

        $stageItem = collect($deptRes->json('data'))->firstWhere('request_id', $requestId);
        $this->assertNotNull($stageItem);
        $this->assertFalse($stageItem['is_claimable']);
        $this->assertSame('Waiting for student to complete fee payment.', $stageItem['blocked_reason']);
    }

    public function test_student_can_switch_tier_while_awaiting_payment(): void
    {
        $createRes = $this->actingAs($this->student, 'sanctum')->postJson('/api/requests', [
            'request_type_id' => $this->requestType->id,
            'request_type_tier_id' => $this->normalTier->id,
        ]);
        $requestId = $createRes->json('data.id');

        // Student switches to Fast Mode (2,500 XAF)
        $switchRes = $this->actingAs($this->student, 'sanctum')->patchJson("/api/requests/{$requestId}/tier", [
            'request_type_tier_id' => $this->fastTier->id,
        ]);

        $switchRes->assertOk();
        $switchRes->assertJsonPath('data.tier_name', 'Fast Mode');
        $switchRes->assertJsonPath('data.fee_amount', 2500);
        $switchRes->assertJsonPath('data.amount_due', 2500);
        $switchRes->assertJsonPath('data.turnaround_days', 7);
        $switchRes->assertJsonPath('data.tier_priority', 2);

        $this->assertDatabaseHas('requests', [
            'id' => $requestId,
            'request_type_tier_id' => $this->fastTier->id,
            'fee_amount' => 2500,
            'turnaround_days_snapshot' => 7,
            'tier_priority' => 2,
        ]);
    }
}
