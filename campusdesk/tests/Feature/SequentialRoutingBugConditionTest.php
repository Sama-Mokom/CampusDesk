<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Request as DocumentRequest;
use App\Models\RequestStage;
use App\Models\RequestType;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bug Condition Exploration Tests — Sequential Routing Race Condition
 *
 * These tests are EXPECTED TO FAIL against the unfixed RequestStageController.
 * Failure confirms the two defects described in the bugfix spec:
 *
 *   Defect 1 — Queue leakage:
 *     The general queue path in index() does not apply the sequential-order guard,
 *     so downstream stages are exposed before their upstream predecessor is approved.
 *
 *   Defect 2 — Concurrent claim race:
 *     The claim() method is not wrapped in a DB transaction with lockForUpdate(),
 *     allowing two concurrent requests to both pass the availability check and both
 *     succeed when only one should.
 *
 * When BOTH tests fail this test class has done its job — the counterexamples
 * confirm the root cause analysis from the bugfix spec.
 */
class SequentialRoutingBugConditionTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Shared seeding helpers
    // -------------------------------------------------------------------------

    /**
     * Create a minimal Faculty + Department pair without relying on the
     * UserFactory's random-selection logic (which requires seeders).
     */
    private function makeDepartment(string $suffix = 'A'): Department
    {
        $faculty = Faculty::create([
            'name'             => "Faculty {$suffix}",
            'code'             => "FAC{$suffix}",
            'matricule_prefix' => "F{$suffix}",
        ]);

        return Department::create([
            'faculty_id' => $faculty->id,
            'code'       => "DEPT{$suffix}",
            'name'       => "Department {$suffix}",
            'type'       => 'academic',
        ]);
    }

    /**
     * Create a staff user and attach them to the given department.
     */
    private function makeStaffInDepartment(Department $department): User
    {
        $user = User::create([
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => bcrypt('password'),
            'role'              => 'staff',
        ]);

        $staffProfile = StaffProfile::create([
            'user_id'     => $user->id,
            'staff_id'    => 'STAFF-' . strtoupper(\Illuminate\Support\Str::random(6)),
            'admin_level' => null,
        ]);

        $staffProfile->departments()->attach($department->id, ['is_primary' => true]);

        return $user;
    }

    /**
     * Create a minimal student user (without full profile — only the user row
     * is needed to satisfy the foreign key on requests).
     */
    private function makeStudentUser(): User
    {
        return User::create([
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => bcrypt('password'),
            'role'              => 'student',
        ]);
    }

    /**
     * Insert a RequestType row directly to avoid the missing $fillable on the model.
     */
    private function makeRequestType(string $name, array $deptSequence): RequestType
    {
        $id = DB::table('request_types')->insertGetId([
            'name'                        => $name,
            'description'                 => 'For testing',
            'default_department_sequence' => json_encode($deptSequence),
            'created_at'                  => now(),
            'updated_at'                  => now(),
        ]);

        return RequestType::find($id);
    }

    /**
     * Seed a two-department sequential routing scenario:
     *   Stage 1 → DeptA (sequence_order = 1)
     *   Stage 2 → DeptB (sequence_order = 2)
     *
     * Returns [$docRequest, $stageA, $stageB].
     */
    private function seedTwoStageRequest(
        Department $deptA,
        Department $deptB,
        string $stageAStatus = 'in_review'
    ): array {
        $student = $this->makeStudentUser();

        $requestType = $this->makeRequestType('Test Request Type', [$deptA->id, $deptB->id]);

        $docRequest = DocumentRequest::create([
            'student_id'      => $student->id,
            'request_type_id' => $requestType->id,
            'description'     => 'Test request',
            'status'          => 'in_review',
        ]);

        $stageA = RequestStage::create([
            'request_id'    => $docRequest->id,
            'department_id' => $deptA->id,
            'sequence_order' => 1,
            'status'        => $stageAStatus,
            'handled_by'    => null,
        ]);

        $stageB = RequestStage::create([
            'request_id'    => $docRequest->id,
            'department_id' => $deptB->id,
            'sequence_order' => 2,
            'status'        => 'pending',
            'handled_by'    => null,
        ]);

        return [$docRequest, $stageA, $stageB];
    }

    // =========================================================================
    // DEFECT 1 — Queue Leakage
    //
    // A staff member in DeptB should NOT see DeptB's stage in the general queue
    // while DeptA's stage is still in_review (not approved).
    //
    // Expected on UNFIXED code: FAILS — DeptB's stage appears in the queue.
    // =========================================================================

    /**
     * Bug Condition Exploration — Queue Leakage
     *
     * Validates bugfix.md §1.1, §1.2 and expected behaviour §2.1, §2.2
     */
    public function test_downstream_stage_is_not_visible_in_queue_while_predecessor_is_in_review(): void
    {
        $deptA = $this->makeDepartment('A');
        $deptB = $this->makeDepartment('B');

        // Dept A's stage is in_review (claimed but not yet approved)
        [, $stageA, $stageB] = $this->seedTwoStageRequest($deptA, $deptB, 'in_review');

        // Sanity: stage A is not pending/unclaimed; stage B is pending and unclaimed
        $this->assertEquals('in_review', $stageA->fresh()->status);
        $this->assertEquals('pending',   $stageB->fresh()->status);
        $this->assertNull($stageB->fresh()->handled_by);

        $staffB = $this->makeStaffInDepartment($deptB);

        // DeptB staff fetches the general queue
        $response = $this->actingAs($staffB, 'sanctum')
                         ->getJson('/api/stages');

        $response->assertStatus(200);

        $stageIds = collect($response->json('data'))->pluck('id')->toArray();

        // EXPECTED (correct): DeptB's stage should NOT be in the queue because its
        // predecessor (DeptA's stage) is still in_review, not approved.
        //
        // BUG: The unfixed code WILL include DeptB's stage, so this assertion fails.
        $this->assertNotContains(
            $stageB->id,
            $stageIds,
            "Defect 1 confirmed: DeptB stage (id={$stageB->id}) appeared in the queue " .
            "even though DeptA's stage (id={$stageA->id}) is still in_review, not approved. " .
            "The general queue path is missing the sequential-order guard."
        );
    }

    // =========================================================================
    // DEFECT 2 — Concurrent Claim Race (structural — TOCTOU window)
    //
    // Even though a serial double-claim is blocked by the conditional WHERE,
    // the absence of lockForUpdate() + DB::transaction means the check-then-act
    // is not atomic. This test documents the STRUCTURAL defect and verifies
    // that sequential (non-concurrent) claims still work correctly (the second
    // serial attempt is blocked by the conditional update returning 0).
    //
    // NOTE: True concurrent races require multi-process / multi-connection
    // execution which is not feasible in a single-process PHPUnit run.
    // The absence of lockForUpdate() is a structural defect documented here;
    // its fix will be verified in the fix-checking tests.
    // =========================================================================

    /**
     * Bug Condition Exploration — Claim structural atomicity check
     *
     * Verifies that serial double-claim is blocked (returns 409) — this confirms
     * the conditional-update path does block serial re-claims, and documents the
     * structural gap (no DB::transaction + lockForUpdate) that makes concurrent
     * claims unsafe even though serial ones are blocked.
     *
     * Validates bugfix.md §2.4, §2.5
     */
    public function test_second_serial_claim_on_same_stage_returns_409(): void
    {
        $deptA = $this->makeDepartment('C');

        $student      = $this->makeStudentUser();
        $requestType  = $this->makeRequestType('Single Stage Type', [$deptA->id]);

        $docRequest = DocumentRequest::create([
            'student_id'      => $student->id,
            'request_type_id' => $requestType->id,
            'description'     => 'Race condition test request',
            'status'          => 'pending',
        ]);

        $stage = RequestStage::create([
            'request_id'     => $docRequest->id,
            'department_id'  => $deptA->id,
            'sequence_order' => 1,
            'status'         => 'pending',
            'handled_by'     => null,
        ]);

        $staffUser1 = $this->makeStaffInDepartment($deptA);
        $staffUser2 = $this->makeStaffInDepartment($deptA);

        // First claim — must succeed
        $response1 = $this->actingAs($staffUser1, 'sanctum')
                          ->postJson("/api/requests/{$docRequest->id}/stages/{$stage->id}/claim");

        $response1->assertStatus(200);

        // Second claim (serial, stage already claimed) — must return 409
        $response2 = $this->actingAs($staffUser2, 'sanctum')
                          ->postJson("/api/requests/{$docRequest->id}/stages/{$stage->id}/claim");

        $response2->assertStatus(409);

        // The stage must be owned by only the first claimant
        $freshStage = $stage->fresh();
        $this->assertEquals('in_review', $freshStage->status);
        $this->assertEquals($staffUser1->id, $freshStage->handled_by);
    }

    // =========================================================================
    // DEFECT 1 (Variant) — Out-of-order claim attempt
    //
    // Posting directly to claim DeptB's stage while DeptA's is still in_review
    // should return 422. The existing sequential guard in claim() already catches
    // this, so this test verifies the guard is present and working correctly.
    // =========================================================================

    /**
     * Bug Condition Exploration — Out-of-Order Claim Attempt
     *
     * Validates bugfix.md §1.4 and expected behaviour §2.6
     * Note: The sequential guard in claim() already exists in unfixed code;
     * this test confirms the claim-level guard is intact and serves as a
     * regression anchor for the fix.
     */
    public function test_claiming_downstream_stage_before_predecessor_approved_returns_422(): void
    {
        $deptA = $this->makeDepartment('D');
        $deptB = $this->makeDepartment('E');

        // DeptA's stage is in_review (not approved) — DeptB cannot be claimed
        [$docRequest, $stageA, $stageB] = $this->seedTwoStageRequest($deptA, $deptB, 'in_review');

        $staffB = $this->makeStaffInDepartment($deptB);

        $response = $this->actingAs($staffB, 'sanctum')
                         ->postJson("/api/requests/{$docRequest->id}/stages/{$stageB->id}/claim");

        // EXPECTED (correct): 422 — predecessor is not yet approved.
        // The sequential guard in claim() is present in unfixed code, so this
        // returns 422 even before the fix. This test serves as a regression anchor.
        $response->assertStatus(422);

        // The stage must remain untouched
        $this->assertEquals('pending', $stageB->fresh()->status);
        $this->assertNull($stageB->fresh()->handled_by);
    }
}
