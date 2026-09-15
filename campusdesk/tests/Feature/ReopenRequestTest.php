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

class ReopenRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeDepartment(string $suffix): Department
    {
        $faculty = Faculty::create([
            'name' => "Faculty {$suffix}",
            'code' => "FAC{$suffix}",
            'matricule_prefix' => "F{$suffix}",
        ]);

        return Department::create([
            'faculty_id' => $faculty->id,
            'name' => "Department {$suffix}",
            'code' => "DEP{$suffix}",
            'type' => 'academic',
        ]);
    }

    private function makeStudent(): User
    {
        return User::create([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);
    }

    private function makeStaff(?string $adminLevel = null): User
    {
        $user = User::create([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);

        StaffProfile::create([
            'user_id' => $user->id,
            'staff_id' => 'STAFF-' . strtoupper(fake()->unique()->bothify('??????')),
            'admin_level' => $adminLevel,
        ]);

        return $user;
    }

    /**
     * @return array{0: DocumentRequest, 1: RequestStage[], 2: User}
     */
    private function makeRejectedRequest(array $stageStatuses = ['approved', 'rejected']): array
    {
        $student = $this->makeStudent();
        $departments = [];

        foreach ($stageStatuses as $index => $status) {
            $departments[] = $this->makeDepartment("{$student->id}{$index}");
        }

        $typeId = DB::table('request_types')->insertGetId([
            'name' => 'Test request type ' . fake()->unique()->word(),
            'description' => 'Test request type',
            'default_department_sequence' => json_encode(array_column($departments, 'id')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = DocumentRequest::create([
            'student_id' => $student->id,
            'request_type_id' => $typeId,
            'description' => 'Reopen test request',
            'status' => 'rejected',
            'is_reopened' => false,
        ]);

        $handler = $this->makeStaff();
        $stages = [];

        foreach ($departments as $index => $department) {
            $stages[] = RequestStage::create([
                'request_id' => $request->id,
                'department_id' => $department->id,
                'sequence_order' => $index + 1,
                'status' => $stageStatuses[$index],
                'handled_by' => $handler->id,
                'staff_note' => $index === 0 ? 'Original approval remains intact.' : 'Missing supporting document.',
            ]);
        }

        return [$request, $stages, $student];
    }

    public function test_student_owner_reopens_a_rejected_request_and_preserves_other_stages(): void
    {
        [$request, [$approvedStage, $rejectedStage], $student] = $this->makeRejectedRequest();

        $response = $this->actingAs($student, 'sanctum')
            ->postJson("/api/requests/{$request->id}/reopen");

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.is_reopened', true);

        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'status' => 'pending',
            'is_reopened' => true,
        ]);
        $this->assertDatabaseHas('request_stages', [
            'id' => $rejectedStage->id,
            'status' => 'pending',
            'handled_by' => null,
            'staff_note' => 'Missing supporting document.',
        ]);
        $this->assertDatabaseHas('request_stages', [
            'id' => $approvedStage->id,
            'status' => 'approved',
            'handled_by' => $approvedStage->handled_by,
            'staff_note' => 'Original approval remains intact.',
        ]);
        $this->assertDatabaseHas('status_history', [
            'request_id' => $request->id,
            'old_status' => 'rejected',
            'new_status' => 'pending',
            'changed_by' => $student->id,
            'note' => 'Request reopened by student.',
        ]);
        $this->assertDatabaseHas('status_history', [
            'request_id' => $request->id,
            'request_stage_id' => $rejectedStage->id,
            'old_status' => 'rejected',
            'new_status' => 'pending',
            'changed_by' => $student->id,
        ]);
    }

    public function test_super_admin_can_reopen_another_students_rejected_request(): void
    {
        [$request, , $student] = $this->makeRejectedRequest();
        $admin = $this->makeStaff('super_admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/requests/{$request->id}/reopen")
            ->assertOk();

        $this->assertDatabaseHas('status_history', [
            'request_id' => $request->id,
            'changed_by' => $admin->id,
            'note' => 'Request reopened by administrator.',
        ]);
        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'student_id' => $student->id,
            'status' => 'pending',
        ]);
    }

    public function test_staff_department_admin_and_other_student_cannot_reopen_a_request(): void
    {
        [$request] = $this->makeRejectedRequest();

        foreach ([$this->makeStaff(), $this->makeStaff('dept_admin'), $this->makeStudent()] as $actor) {
            $this->actingAs($actor, 'sanctum')
                ->postJson("/api/requests/{$request->id}/reopen")
                ->assertForbidden();
        }

        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'status' => 'rejected',
            'is_reopened' => false,
        ]);
    }

    public function test_only_rejected_requests_can_be_reopened(): void
    {
        [$request, , $student] = $this->makeRejectedRequest();
        $request->update(['status' => 'ready']);

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/requests/{$request->id}/reopen")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only rejected requests can be reopened.');

        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'status' => 'ready',
            'is_reopened' => false,
        ]);
    }

    public function test_a_request_cannot_be_reopened_twice(): void
    {
        [$request, , $student] = $this->makeRejectedRequest();

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/requests/{$request->id}/reopen")
            ->assertOk();

        $this->actingAs($student, 'sanctum')
            ->postJson("/api/requests/{$request->id}/reopen")
            ->assertStatus(422);

        $this->assertSame(
            1,
            DB::table('status_history')
                ->where('request_id', $request->id)
                ->where('note', 'Request reopened by student.')
                ->count()
        );
    }

    public function test_stage_invariant_failure_returns_server_error_and_rolls_back_for_zero_or_multiple_rejections(): void
    {
        foreach ([['pending', 'approved'], ['rejected', 'rejected']] as $statuses) {
            [$request, $stages, $student] = $this->makeRejectedRequest($statuses);

            $this->actingAs($student, 'sanctum')
                ->postJson("/api/requests/{$request->id}/reopen")
                ->assertStatus(500);

            $this->assertDatabaseHas('requests', [
                'id' => $request->id,
                'status' => 'rejected',
                'is_reopened' => false,
            ]);

            foreach ($stages as $stage) {
                $this->assertDatabaseHas('request_stages', [
                    'id' => $stage->id,
                    'status' => $stage->status,
                ]);
            }

            $this->assertDatabaseMissing('status_history', [
                'request_id' => $request->id,
                'note' => 'Request reopened by student.',
            ]);
        }
    }
}
