<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Request as DocumentRequest;
use App\Models\RequestStage;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeptAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_only_primary_department_stages_and_can_reassign_a_claimed_case(): void
    {
        [$department, $otherDepartment] = $this->departments();
        $admin = $this->staff($department, 'dept_admin', true);
        $recipient = $this->staff($department);
        $otherHandler = $this->staff($otherDepartment);
        $student = $this->student();
        $typeId = DB::table('request_types')->insertGetId(['name' => 'Test type', 'description' => 'Test', 'default_department_sequence' => json_encode([]), 'created_at' => now(), 'updated_at' => now()]);
        $request = DocumentRequest::create(['student_id' => $student->id, 'request_type_id' => $typeId, 'description' => 'Test request', 'status' => 'in_review']);
        $stage = RequestStage::create(['request_id' => $request->id, 'department_id' => $department->id, 'sequence_order' => 1, 'status' => 'in_review', 'handled_by' => $admin->id]);
        $otherStage = RequestStage::create(['request_id' => $request->id, 'department_id' => $otherDepartment->id, 'sequence_order' => 2, 'status' => 'in_review', 'handled_by' => $otherHandler->id]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/dept-admin/requests')
            ->assertOk()->assertJsonPath('meta.department.id', $department->id)
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $stage->id);

        $this->actingAs($admin, 'sanctum')->patchJson("/api/dept-admin/stages/{$stage->id}/reassign", ['handled_by' => $recipient->id])
            ->assertOk()->assertJsonPath('data.handled_by', $recipient->id);

        $this->assertDatabaseHas('request_stages', ['id' => $stage->id, 'handled_by' => $recipient->id, 'status' => 'in_review']);
        $this->assertDatabaseHas('stage_reassignments', ['request_stage_id' => $stage->id, 'from_user_id' => $admin->id, 'to_user_id' => $recipient->id, 'reassigned_by' => $admin->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $recipient->id, 'type' => 'stage_reassigned']);
        $this->assertDatabaseMissing('stage_reassignments', ['request_stage_id' => $otherStage->id]);
    }

    public function test_pending_stages_cannot_be_directly_assigned(): void
    {
        [$department] = $this->departments();
        $admin = $this->staff($department, 'dept_admin', true);
        $recipient = $this->staff($department);
        $student = $this->student();
        $typeId = DB::table('request_types')->insertGetId(['name' => 'Test type', 'description' => 'Test', 'default_department_sequence' => json_encode([]), 'created_at' => now(), 'updated_at' => now()]);
        $request = DocumentRequest::create(['student_id' => $student->id, 'request_type_id' => $typeId, 'description' => 'Test request', 'status' => 'pending']);
        $stage = RequestStage::create(['request_id' => $request->id, 'department_id' => $department->id, 'sequence_order' => 1, 'status' => 'pending']);

        $this->actingAs($admin, 'sanctum')->patchJson("/api/dept-admin/stages/{$stage->id}/reassign", ['handled_by' => $recipient->id])
            ->assertUnprocessable()->assertJsonPath('message', 'Only claimed in-review stages can be reassigned.');
    }

    private function departments(): array
    {
        $faculty = Faculty::create(['name' => 'Faculty', 'code' => 'FAC', 'matricule_prefix' => 'FC']);
        return [Department::create(['faculty_id' => $faculty->id, 'name' => 'Primary', 'code' => 'PRI', 'type' => 'academic']), Department::create(['faculty_id' => $faculty->id, 'name' => 'Other', 'code' => 'OTH', 'type' => 'academic'])];
    }

    private function staff(Department $department, ?string $level = null, bool $primary = false): User
    {
        $user = User::factory()->staff($level)->create();
        $profile = $user->staffProfile;
        $profile->update(['admin_level' => $level]);
        $profile->departments()->attach($department->id, ['is_primary' => $primary]);
        return $user->fresh('staffProfile');
    }

    private function student(): User
    {
        return User::create([
            'name' => 'Student',
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);
    }
}
