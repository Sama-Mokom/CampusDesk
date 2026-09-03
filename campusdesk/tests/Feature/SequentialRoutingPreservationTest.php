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
 * Preservation Property Tests — Sequential Routing
 *
 * These tests document and guard the CORRECT behaviours that must be
 * unchanged by the bugfix. They are run against the UNFIXED controller
 * first (all must PASS) and then re-run after the fix to confirm no
 * regressions (Requirements 3.1–3.6).
 *
 * Preservation properties tested:
 *   P-3.1 — First-stage (sequence_order = 1) pending stage with no handler
 *            appears in the general queue.
 *   P-3.2 — Second-stage pending stage with an approved predecessor appears
 *            in the general queue.
 *   P-3.3 — A valid single claim returns 200, transitions stage to in_review,
 *            sets handled_by, creates a status_history entry, and sets the
 *            parent request to in_review.
 *   P-3.4 — The myCases endpoint returns all in_review stages assigned to
 *            the authenticated user.
 *   P-3.5 — The forRequest (/requests/{request}/stages) endpoint returns
 *            all stages for the given request (regardless of status) in
 *            ascending sequence_order.
 *   P-3.6 — Resolve (approve/reject): approving a non-final stage advances
 *            request to forwarded; approving the final stage advances it to
 *            ready; rejecting sets status to rejected.
 *
 * Property-based coverage: We generate three routing scenarios (N=1, N=2,
 * N=3 stages) and assert that only eligible stages appear in the queue, so
 * the property holds across a representative sample of routing configurations.
 *
 * Implementation note on requests.status values:
 *   Valid statuses for the `requests` table are:
 *   draft, pending, in_review, forwarded, ready, collected, rejected.
 *   When seeding "already-processed" stages (e.g. status = 'approved'), the
 *   parent request is set to 'forwarded' to satisfy the CHECK constraint.
 *
 * Implementation note on forRequest route binding:
 *   The route /requests/{request}/stages uses the placeholder {request}, but
 *   the controller method forRequest(DocumentRequest $docRequest) uses $docRequest.
 *   Laravel implicit binding requires the placeholder name to match the parameter
 *   name, so {request} does not bind to $docRequest. This means the method
 *   receives an unbound (empty) model and returns an empty collection. This is a
 *   pre-existing application bug unrelated to the bugfix scope. The P-3.5 tests
 *   below verify the current (unfixed) behaviour so that the fix does not alter it.
 */
class SequentialRoutingPreservationTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Shared seeding helpers (mirrored from SequentialRoutingBugConditionTest)
    // -------------------------------------------------------------------------

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
     * Derive a valid `requests.status` value from the stage statuses.
     *
     * The `requests` table only accepts: draft, pending, in_review, forwarded,
     * ready, collected, rejected. Stage statuses (pending, in_review, approved,
     * rejected) are not a 1:1 map, so we pick the most appropriate parent value:
     *
     *  - All stages pending          → parent is 'pending'
     *  - Any stage in_review         → parent is 'in_review'
     *  - Last stage approved, others → parent is 'forwarded'
     *  - Any stage rejected          → parent is 'rejected'
     */
    private function deriveRequestStatus(array $stageStatuses): string
    {
        if (in_array('rejected', $stageStatuses)) {
            return 'rejected';
        }
        if (in_array('in_review', $stageStatuses)) {
            return 'in_review';
        }
        if (in_array('approved', $stageStatuses)) {
            return 'forwarded';
        }
        return 'pending';
    }

    /**
     * Seed an N-stage request using the given department sequence.
     *
     * @param  Department[]  $departments
     * @param  string[]      $stageStatuses   stage status per department
     * @param  int[]|null[]  $handledBy       user IDs (null = unclaimed); same length as $departments
     * @return array{0: DocumentRequest, 1: RequestStage[]}
     */
    private function seedNStageRequest(
        array $departments,
        array $stageStatuses,
        array $handledBy = []
    ): array {
        $student      = $this->makeStudentUser();
        $deptIds      = array_map(fn(Department $d) => $d->id, $departments);
        $requestType  = $this->makeRequestType('N-Stage Type', $deptIds);
        $requestStatus = $this->deriveRequestStatus($stageStatuses);

        $docRequest = DocumentRequest::create([
            'student_id'      => $student->id,
            'request_type_id' => $requestType->id,
            'description'     => 'Preservation test request',
            'status'          => $requestStatus,
        ]);

        $stages = [];
        foreach ($departments as $i => $dept) {
            $stages[] = RequestStage::create([
                'request_id'     => $docRequest->id,
                'department_id'  => $dept->id,
                'sequence_order' => $i + 1,
                'status'         => $stageStatuses[$i],
                'handled_by'     => $handledBy[$i] ?? null,
            ]);
        }

        return [$docRequest, $stages];
    }

    // =========================================================================
    // P-3.1 — First-stage eligible stage appears in the general queue
    //
    // A stage at sequence_order = 1, status = pending, handled_by = null
    // must always be returned by the general queue endpoint.
    // =========================================================================

    /**
     * Preservation P-3.1 — Single-stage routing: first stage appears in queue
     *
     * Validates: Requirements 3.1
     */
    public function test_first_stage_pending_stage_appears_in_general_queue(): void
    {
        $deptA = $this->makeDepartment('P1A');
        [$docRequest, [$stageA]] = $this->seedNStageRequest([$deptA], ['pending']);

        $staffA = $this->makeStaffInDepartment($deptA);

        $response = $this->actingAs($staffA, 'sanctum')
                         ->getJson('/api/stages');

        $response->assertStatus(200);

        $stageIds = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertContains(
            $stageA->id,
            $stageIds,
            "P-3.1: First stage (id={$stageA->id}) should appear in the queue but was absent."
        );
    }

    /**
     * Preservation P-3.1 (property scenario N=2) — Two-stage routing:
     * stage 1 (sequence_order = 1) appears in dept-A queue even when
     * stage 2 is also pending.
     *
     * Validates: Requirements 3.1
     */
    public function test_first_stage_appears_when_second_stage_is_also_pending(): void
    {
        $deptA = $this->makeDepartment('P1B');
        $deptB = $this->makeDepartment('P1C');

        [$docRequest, [$stageA, $stageB]] = $this->seedNStageRequest(
            [$deptA, $deptB],
            ['pending', 'pending']
        );

        $staffA = $this->makeStaffInDepartment($deptA);

        $response = $this->actingAs($staffA, 'sanctum')
                         ->getJson('/api/stages');

        $response->assertStatus(200);

        $stageIds = collect($response->json('data'))->pluck('id')->toArray();

        // Stage 1 must be in the queue for staff in dept A
        $this->assertContains(
            $stageA->id,
            $stageIds,
            "P-3.1: Stage 1 (id={$stageA->id}) must be in dept-A queue."
        );
    }

    // =========================================================================
    // P-3.2 — Second-stage with approved predecessor appears in queue
    //
    // sequence_order = 2, status = pending, handled_by = null,
    // predecessor status = approved → must appear in the queue.
    // =========================================================================

    /**
     * Preservation P-3.2 — Two-stage routing: stage 2 appears when stage 1 is approved
     *
     * Validates: Requirements 3.2
     */
    public function test_second_stage_appears_in_queue_when_predecessor_is_approved(): void
    {
        $deptA = $this->makeDepartment('P2A');
        $deptB = $this->makeDepartment('P2B');

        [$docRequest, [$stageA, $stageB]] = $this->seedNStageRequest(
            [$deptA, $deptB],
            ['approved', 'pending']
        );

        $staffB = $this->makeStaffInDepartment($deptB);

        $response = $this->actingAs($staffB, 'sanctum')
                         ->getJson('/api/stages');

        $response->assertStatus(200);

        $stageIds = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertContains(
            $stageB->id,
            $stageIds,
            "P-3.2: Stage 2 (id={$stageB->id}) should appear in queue because its predecessor " .
            "stage 1 (id={$stageA->id}) is approved, but it was absent."
        );
    }

    /**
     * Preservation P-3.2 (property scenario N=3) — Three-stage routing:
     * stage 3 appears when stage 2 is approved (and stage 1 is also approved).
     *
     * Validates: Requirements 3.2
     */
    public function test_third_stage_appears_in_queue_when_second_is_approved(): void
    {
        $deptA = $this->makeDepartment('P2C');
        $deptB = $this->makeDepartment('P2D');
        $deptC = $this->makeDepartment('P2E');

        [$docRequest, [$stageA, $stageB, $stageC]] = $this->seedNStageRequest(
            [$deptA, $deptB, $deptC],
            ['approved', 'approved', 'pending']
        );

        $staffC = $this->makeStaffInDepartment($deptC);

        $response = $this->actingAs($staffC, 'sanctum')
                         ->getJson('/api/stages');

        $response->assertStatus(200);

        $stageIds = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertContains(
            $stageC->id,
            $stageIds,
            "P-3.2 (N=3): Stage 3 (id={$stageC->id}) should appear when its predecessor " .
            "(id={$stageB->id}) is approved, but it was absent."
        );
    }

    // =========================================================================
    // P-3.3 — Valid single claim: 200, stage→in_review, handled_by set,
    //          status history created, parent request→in_review
    // =========================================================================

    /**
     * Preservation P-3.3 — Valid single claim on a first-stage pending stage
     *
     * Validates: Requirements 3.3
     */
    public function test_valid_claim_returns_200_and_transitions_stage_correctly(): void
    {
        $deptA = $this->makeDepartment('P3A');
        [$docRequest, [$stageA]] = $this->seedNStageRequest([$deptA], ['pending']);

        $staffA = $this->makeStaffInDepartment($deptA);

        $response = $this->actingAs($staffA, 'sanctum')
                         ->postJson("/api/requests/{$docRequest->id}/stages/{$stageA->id}/claim");

        $response->assertStatus(200);

        // Stage must transition to in_review and be owned by the claimant
        $freshStage = $stageA->fresh();
        $this->assertEquals('in_review', $freshStage->status,
            "P-3.3: Stage status should be in_review after a valid claim.");
        $this->assertEquals($staffA->id, $freshStage->handled_by,
            "P-3.3: Stage handled_by should equal the claimant user ID.");

        // Parent request must be in_review
        $freshRequest = $docRequest->fresh();
        $this->assertEquals('in_review', $freshRequest->status,
            "P-3.3: Parent request status should be in_review after a valid claim.");

        // At least one status history entry must have been created for this stage
        $historyCount = DB::table('status_history')
            ->where('request_stage_id', $stageA->id)
            ->count();
        $this->assertGreaterThanOrEqual(1, $historyCount,
            "P-3.3: A status history entry should be created when a stage is claimed.");
    }

    /**
     * Preservation P-3.3 (N=2) — Valid claim on second-stage when predecessor approved
     *
     * Validates: Requirements 3.3
     */
    public function test_valid_claim_on_second_stage_with_approved_predecessor(): void
    {
        $deptA = $this->makeDepartment('P3B');
        $deptB = $this->makeDepartment('P3C');

        [$docRequest, [$stageA, $stageB]] = $this->seedNStageRequest(
            [$deptA, $deptB],
            ['approved', 'pending']
        );

        $staffB = $this->makeStaffInDepartment($deptB);

        $response = $this->actingAs($staffB, 'sanctum')
                         ->postJson("/api/requests/{$docRequest->id}/stages/{$stageB->id}/claim");

        $response->assertStatus(200);

        $freshStage = $stageB->fresh();
        $this->assertEquals('in_review', $freshStage->status);
        $this->assertEquals($staffB->id, $freshStage->handled_by);

        $freshRequest = $docRequest->fresh();
        $this->assertEquals('in_review', $freshRequest->status);

        $historyCount = DB::table('status_history')
            ->where('request_stage_id', $stageB->id)
            ->count();
        $this->assertGreaterThanOrEqual(1, $historyCount,
            "P-3.3: Status history entry should exist after claiming stage 2.");
    }

    // =========================================================================
    // P-3.4 — myCases endpoint returns all in_review stages for the auth user
    // =========================================================================

    /**
     * Preservation P-3.4 — myCases returns all in_review stages for the caller
     *
     * Validates: Requirements 3.6
     */
    public function test_my_cases_returns_in_review_stages_for_authenticated_staff(): void
    {
        $deptA = $this->makeDepartment('P4A');
        $deptB = $this->makeDepartment('P4B');

        $staffA = $this->makeStaffInDepartment($deptA);
        $staffB = $this->makeStaffInDepartment($deptB);

        // Seed two in_review stages assigned to staffA
        [$docRequest1, [$stage1]] = $this->seedNStageRequest(
            [$deptA],
            ['in_review'],
            [$staffA->id]
        );

        [$docRequest2, [$stage2]] = $this->seedNStageRequest(
            [$deptA],
            ['in_review'],
            [$staffA->id]
        );

        // Seed one in_review stage assigned to staffB — must NOT appear in staffA's cases
        [$docRequest3, [$stage3]] = $this->seedNStageRequest(
            [$deptB],
            ['in_review'],
            [$staffB->id]
        );

        $response = $this->actingAs($staffA, 'sanctum')
                         ->getJson('/api/stages/my-cases');

        $response->assertStatus(200);

        // myCases returns a plain JSON array (no 'data' wrapper)
        $returnedIds = collect($response->json())->pluck('id')->toArray();

        $this->assertContains($stage1->id, $returnedIds,
            "P-3.4: myCases should include stage1 assigned to staffA.");
        $this->assertContains($stage2->id, $returnedIds,
            "P-3.4: myCases should include stage2 assigned to staffA.");
        $this->assertNotContains($stage3->id, $returnedIds,
            "P-3.4: myCases should NOT include stage3 assigned to staffB.");
    }

    /**
     * Preservation P-3.4 (variant) — myCases returns empty when staff has no active cases
     *
     * Validates: Requirements 3.6
     */
    public function test_my_cases_returns_empty_when_no_in_review_stages(): void
    {
        $deptA  = $this->makeDepartment('P4C');
        $staffA = $this->makeStaffInDepartment($deptA);

        $response = $this->actingAs($staffA, 'sanctum')
                         ->getJson('/api/stages/my-cases');

        $response->assertStatus(200);
        $this->assertEmpty($response->json(),
            "P-3.4: myCases should return an empty array when staff has no in_review stages.");
    }

    // =========================================================================
    // P-3.5 — forRequest endpoint returns stages for a specific request
    //
    // Note: The route /requests/{request}/stages uses {request} as the
    // placeholder, but the controller method is forRequest(DocumentRequest
    // $docRequest). Since the placeholder name does not match the parameter
    // name, Laravel's implicit model binding does not resolve the model —
    // the method receives an unbound model and returns an empty collection.
    //
    // These tests document CURRENT (unfixed) behaviour: the endpoint always
    // returns HTTP 200 with an empty data array regardless of the request ID,
    // because the route-to-parameter binding is mismatched. The bugfix must
    // NOT change this behaviour.
    // =========================================================================

    /**
     * Preservation P-3.5 — forRequest returns HTTP 200 for a valid staff user
     *
     * Validates: Requirements 3.5 (current observable behaviour)
     */
    public function test_for_request_endpoint_returns_200_for_staff(): void
    {
        $deptA = $this->makeDepartment('P5A');
        [$docRequest, [$stageA]] = $this->seedNStageRequest([$deptA], ['pending']);

        $staffA = $this->makeStaffInDepartment($deptA);

        $response = $this->actingAs($staffA, 'sanctum')
                         ->getJson("/api/requests/{$docRequest->id}/stages");

        $response->assertStatus(200);
    }

    /**
     * Preservation P-3.5 (format) — forRequest response has a 'data' key
     *
     * Validates: Requirements 3.5 (current observable behaviour)
     */
    public function test_for_request_response_is_wrapped_in_data_key(): void
    {
        $deptA = $this->makeDepartment('P5B');
        [$docRequest, [$stageA]] = $this->seedNStageRequest([$deptA], ['pending']);

        $staffA = $this->makeStaffInDepartment($deptA);

        $response = $this->actingAs($staffA, 'sanctum')
                         ->getJson("/api/requests/{$docRequest->id}/stages");

        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'),
            "P-3.5: forRequest response must contain a 'data' key with an array.");
    }

    // =========================================================================
    // P-3.6 — Resolve flow: approve (non-final) → forwarded;
    //                        approve (final)     → ready;
    //                        reject              → rejected
    // =========================================================================

    /**
     * Preservation P-3.6a — Approving a non-final stage advances request to forwarded
     *
     * Validates: Requirements 3.4
     */
    public function test_approving_non_final_stage_advances_request_to_forwarded(): void
    {
        $deptA = $this->makeDepartment('P6A');
        $deptB = $this->makeDepartment('P6B');

        $staffA = $this->makeStaffInDepartment($deptA);

        // Stage 1 is in_review (claimed by staffA), stage 2 is pending
        [$docRequest, [$stageA, $stageB]] = $this->seedNStageRequest(
            [$deptA, $deptB],
            ['in_review', 'pending'],
            [$staffA->id, null]
        );

        $response = $this->actingAs($staffA, 'sanctum')
                         ->patchJson("/api/requests/{$docRequest->id}/stages/{$stageA->id}/resolve", [
                             'status' => 'approved',
                         ]);

        $response->assertStatus(200);

        $freshRequest = $docRequest->fresh();
        $this->assertEquals('forwarded', $freshRequest->status,
            "P-3.6a: Approving a non-final stage should advance request to forwarded.");

        $freshStageA = $stageA->fresh();
        $this->assertEquals('approved', $freshStageA->status,
            "P-3.6a: Stage status must be approved after resolve.");
    }

    /**
     * Preservation P-3.6b — Approving the final stage advances request to ready
     *
     * Validates: Requirements 3.4
     */
    public function test_approving_final_stage_advances_request_to_ready(): void
    {
        $deptA = $this->makeDepartment('P6C');

        $staffA = $this->makeStaffInDepartment($deptA);

        // Single-stage routing: stage 1 is the final stage
        [$docRequest, [$stageA]] = $this->seedNStageRequest(
            [$deptA],
            ['in_review'],
            [$staffA->id]
        );

        $response = $this->actingAs($staffA, 'sanctum')
                         ->patchJson("/api/requests/{$docRequest->id}/stages/{$stageA->id}/resolve", [
                             'status' => 'approved',
                         ]);

        $response->assertStatus(200);

        $freshRequest = $docRequest->fresh();
        $this->assertEquals('ready', $freshRequest->status,
            "P-3.6b: Approving the final (and only) stage should advance request to ready.");

        $freshStageA = $stageA->fresh();
        $this->assertEquals('approved', $freshStageA->status);
    }

    /**
     * Preservation P-3.6b (N=3) — Approving the last stage in a 3-stage chain
     * advances request to ready.
     *
     * Validates: Requirements 3.4
     */
    public function test_approving_final_stage_in_three_stage_chain_sets_request_to_ready(): void
    {
        $deptA = $this->makeDepartment('P6D');
        $deptB = $this->makeDepartment('P6E');
        $deptC = $this->makeDepartment('P6F');

        $staffC = $this->makeStaffInDepartment($deptC);

        // Stage 3 is the active/final stage (in_review, claimed by staffC)
        // Stages 1 and 2 are approved; parent request is 'forwarded' initially
        [$docRequest, [$stageA, $stageB, $stageC]] = $this->seedNStageRequest(
            [$deptA, $deptB, $deptC],
            ['approved', 'approved', 'in_review'],
            [null, null, $staffC->id]
        );

        $response = $this->actingAs($staffC, 'sanctum')
                         ->patchJson("/api/requests/{$docRequest->id}/stages/{$stageC->id}/resolve", [
                             'status' => 'approved',
                         ]);

        $response->assertStatus(200);

        $freshRequest = $docRequest->fresh();
        $this->assertEquals('ready', $freshRequest->status,
            "P-3.6b (N=3): Approving the final stage should set request to ready.");
    }

    /**
     * Preservation P-3.6c — Rejecting a stage sets request status to rejected
     *
     * Validates: Requirements 3.4
     */
    public function test_rejecting_a_stage_sets_request_to_rejected(): void
    {
        $deptA = $this->makeDepartment('P6G');

        $staffA = $this->makeStaffInDepartment($deptA);

        [$docRequest, [$stageA]] = $this->seedNStageRequest(
            [$deptA],
            ['in_review'],
            [$staffA->id]
        );

        $response = $this->actingAs($staffA, 'sanctum')
                         ->patchJson("/api/requests/{$docRequest->id}/stages/{$stageA->id}/resolve", [
                             'status'     => 'rejected',
                             'staff_note' => 'Missing required documentation.',
                         ]);

        $response->assertStatus(200);

        $freshRequest = $docRequest->fresh();
        $this->assertEquals('rejected', $freshRequest->status,
            "P-3.6c: Rejecting a stage should set request status to rejected.");

        $freshStageA = $stageA->fresh();
        $this->assertEquals('rejected', $freshStageA->status,
            "P-3.6c: Stage status must be rejected after reject resolve.");
    }

    /**
     * Preservation P-3.6c (non-final) — Rejecting stage 1 in a 2-stage chain
     * sets the request to rejected (not forwarded).
     *
     * Validates: Requirements 3.4
     */
    public function test_rejecting_non_final_stage_still_sets_request_to_rejected(): void
    {
        $deptA = $this->makeDepartment('P6H');
        $deptB = $this->makeDepartment('P6I');

        $staffA = $this->makeStaffInDepartment($deptA);

        [$docRequest, [$stageA, $stageB]] = $this->seedNStageRequest(
            [$deptA, $deptB],
            ['in_review', 'pending'],
            [$staffA->id, null]
        );

        $response = $this->actingAs($staffA, 'sanctum')
                         ->patchJson("/api/requests/{$docRequest->id}/stages/{$stageA->id}/resolve", [
                             'status'     => 'rejected',
                             'staff_note' => 'Does not meet criteria.',
                         ]);

        $response->assertStatus(200);

        $freshRequest = $docRequest->fresh();
        $this->assertEquals('rejected', $freshRequest->status,
            "P-3.6c (non-final): Rejecting stage 1 should still set request to rejected.");
    }

    // =========================================================================
    // Property-based scenario: N = 1, 2, 3
    //
    // For each routing configuration, assert only eligible (first-stage or
    // approved-predecessor) stages appear in the general queue, and stages
    // that are already in_review or have no approved predecessor do not appear.
    // =========================================================================

    /**
     * Property Scenario N=1 — Single-stage routing: the one pending stage
     * always appears in the queue for its department staff.
     *
     * Validates: Requirements 3.1
     */
    public function test_property_n1_only_eligible_stages_in_queue(): void
    {
        $deptA = $this->makeDepartment('PSA');
        [$docRequest, [$stage]] = $this->seedNStageRequest([$deptA], ['pending']);

        $staffA = $this->makeStaffInDepartment($deptA);

        $response = $this->actingAs($staffA, 'sanctum')
                         ->getJson('/api/stages');

        $response->assertStatus(200);

        $stageIds = collect($response->json('data'))->pluck('id')->toArray();

        // Single pending first-stage must appear
        $this->assertContains($stage->id, $stageIds,
            "Property N=1: Pending first-stage must appear in queue.");

        // Must not contain duplicates
        $this->assertCount(
            count(array_unique($stageIds)),
            $stageIds,
            "Property N=1: Duplicate stage IDs in response."
        );
    }

    /**
     * Property Scenario N=2 — Two-stage routing:
     *   Scenario 2a: Stage 1 pending, Stage 2 pending
     *     → Stage 1 appears in dept-A queue (sequence_order = 1, always eligible)
     *   Scenario 2b: Stage 1 approved, Stage 2 pending
     *     → Stage 2 appears in dept-B queue (predecessor approved)
     *     → Stage 1 does NOT appear (already approved, not pending)
     *
     * Validates: Requirements 3.1, 3.2
     */
    public function test_property_n2_only_eligible_stages_in_queue(): void
    {
        $deptA = $this->makeDepartment('PSB');
        $deptB = $this->makeDepartment('PSC');

        $staffA = $this->makeStaffInDepartment($deptA);
        $staffB = $this->makeStaffInDepartment($deptB);

        // Scenario 2a: Stage 1 pending, Stage 2 pending
        [$req2a, [$s1a, $s2a]] = $this->seedNStageRequest(
            [$deptA, $deptB],
            ['pending', 'pending']
        );

        // Scenario 2b: Stage 1 approved, Stage 2 pending
        [$req2b, [$s1b, $s2b]] = $this->seedNStageRequest(
            [$deptA, $deptB],
            ['approved', 'pending']
        );

        // -- Dept A staff queue --
        $responseA = $this->actingAs($staffA, 'sanctum')->getJson('/api/stages');
        $responseA->assertStatus(200);
        $idsForA = collect($responseA->json('data'))->pluck('id')->toArray();

        // Stage 1 from scenario 2a must appear (pending, sequence_order = 1)
        $this->assertContains($s1a->id, $idsForA,
            "Property N=2: Scenario 2a Stage 1 must appear in dept-A queue.");

        // Stage 1 from scenario 2b is approved (not pending) — must NOT appear as pending item
        $this->assertNotContains($s1b->id, $idsForA,
            "Property N=2: Scenario 2b Stage 1 (approved) must not appear as a pending queue item.");

        // -- Dept B staff queue --
        $responseB = $this->actingAs($staffB, 'sanctum')->getJson('/api/stages');
        $responseB->assertStatus(200);
        $idsForB = collect($responseB->json('data'))->pluck('id')->toArray();

        // Stage 2 from scenario 2b must appear (pending, predecessor approved)
        $this->assertContains($s2b->id, $idsForB,
            "Property N=2: Scenario 2b Stage 2 must appear in dept-B queue when predecessor approved.");
    }

    /**
     * Property Scenario N=3 — Three-stage routing:
     *   Stage 1 approved, Stage 2 approved, Stage 3 pending →
     *   only Stage 3 appears in dept-C queue.
     *
     * Validates: Requirements 3.2
     */
    public function test_property_n3_only_eligible_stages_in_queue(): void
    {
        $deptA = $this->makeDepartment('PSD');
        $deptB = $this->makeDepartment('PSE');
        $deptC = $this->makeDepartment('PSF');

        $staffC = $this->makeStaffInDepartment($deptC);

        [$docRequest, [$stageA, $stageB, $stageC]] = $this->seedNStageRequest(
            [$deptA, $deptB, $deptC],
            ['approved', 'approved', 'pending']
        );

        $response = $this->actingAs($staffC, 'sanctum')
                         ->getJson('/api/stages');

        $response->assertStatus(200);

        $stageIds = collect($response->json('data'))->pluck('id')->toArray();

        // Stage 3 must appear (pending, predecessor stage 2 is approved)
        $this->assertContains($stageC->id, $stageIds,
            "Property N=3: Stage 3 must appear in dept-C queue when stage 2 is approved.");

        // Stages 1 and 2 are approved (not pending) — must not appear
        $this->assertNotContains($stageA->id, $stageIds,
            "Property N=3: Approved stage 1 must not appear in the pending queue.");
        $this->assertNotContains($stageB->id, $stageIds,
            "Property N=3: Approved stage 2 must not appear in the pending queue.");
    }
}
