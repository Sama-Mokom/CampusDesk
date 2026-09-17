<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;
use App\Models\RequestType;
use App\Models\Request as DocumentRequest;
use App\Models\StatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->staff()->create();
        $user->staffProfile->update(['admin_level' => 'super_admin']);
        return $user->fresh('staffProfile');
    }

    public function test_gate_and_reference_crud_with_safe_deletion(): void
    {
        $staff = User::factory()->staff()->create();
        $this->actingAs($staff, 'sanctum')->getJson('/api/admin/faculties')->assertForbidden();
        $this->actingAs($this->admin(), 'sanctum');
        $faculty = $this->postJson('/api/admin/faculties', ['name' => 'Science', 'code' => 'SCI', 'matricule_prefix' => 'SC'])->assertCreated()->json('data');
        $this->getJson('/api/admin/faculties?per_page=1')->assertOk()->assertJsonPath('meta.total', 1);
        $this->patchJson('/api/admin/faculties/'.$faculty['id'], ['name' => 'Sciences'])->assertOk()->assertJsonPath('data.name', 'Sciences');
        $department = $this->postJson('/api/admin/departments', ['faculty_id' => $faculty['id'], 'name' => 'Math', 'code' => 'MTH', 'type' => 'academic'])->assertCreated()->json('data');
        $this->deleteJson('/api/admin/faculties/'.$faculty['id'])->assertStatus(409);
        $this->deleteJson('/api/admin/departments/'.$department['id'])->assertNoContent();
        $this->deleteJson('/api/admin/faculties/'.$faculty['id'])->assertNoContent();
    }

    public function test_user_creation_elevation_and_last_admin_guard(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'sanctum');
        $faculty = Faculty::create(['name' => 'Science', 'code' => 'SCI', 'matricule_prefix' => 'SC']);
        $department = Department::create(['faculty_id' => $faculty->id, 'name' => 'Math', 'code' => 'MTH', 'type' => 'academic']);
        $user = $this->postJson('/api/admin/users', ['name' => 'New Staff', 'email' => 'new@example.com', 'password' => 'password123', 'role' => 'staff', 'staff_id' => 'S-123', 'department_ids' => [$department->id], 'primary_department_id' => $department->id])->assertCreated()->json('data');
        $this->assertArrayNotHasKey('password', $user);
        $this->patchJson('/api/admin/users/'.$user['id'].'/admin-level', ['admin_level' => 'dept_admin'])->assertOk()->assertJsonPath('data.staff_profile.admin_level', 'dept_admin');
        $this->patchJson('/api/admin/users/'.$admin->id.'/admin-level', ['admin_level' => null])->assertStatus(409);
        $this->deleteJson('/api/admin/users/'.$admin->id)->assertStatus(409);
    }

    public function test_other_roles_are_denied_and_elevation_revokes_tokens(): void
    {
        $admin = $this->admin();
        $plain = User::factory()->staff()->create();
        $departmentAdmin = User::factory()->staff()->create();
        $departmentAdmin->staffProfile->update(['admin_level' => 'dept_admin']);
        foreach ([$plain, $departmentAdmin] as $user) {
            $this->actingAs($user, 'sanctum')->getJson('/api/admin/stats')->assertForbidden();
            $this->actingAs($user, 'sanctum')->postJson('/api/admin/faculties', ['name' => 'X'])->assertForbidden();
        }
        $token = $plain->createToken('test');
        $this->actingAs($admin, 'sanctum')->patchJson('/api/admin/users/'.$plain->id.'/admin-level', ['admin_level' => 'super_admin'])->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_programme_and_template_validation(): void
    {
        $this->actingAs($this->admin(), 'sanctum');
        $faculty = Faculty::create(['name' => 'Science', 'code' => 'SCI', 'matricule_prefix' => 'SC']);
        $department = Department::create(['faculty_id' => $faculty->id, 'name' => 'Math', 'code' => 'MTH', 'type' => 'academic']);
        $programme = $this->postJson('/api/admin/programmes', ['department_id' => $department->id, 'name' => 'Math BSc', 'code' => 'BSCM', 'degree_type' => 'BACHELOR', 'faculty_id' => 999])->assertCreated()->json('data');
        $this->assertSame($faculty->id, $programme['faculty_id']);
        $this->postJson('/api/admin/programmes', ['department_id' => $department->id, 'name' => 'Duplicate', 'code' => 'BSCM', 'degree_type' => 'BACHELOR'])->assertUnprocessable();
        $this->postJson('/api/admin/request-types', ['name' => 'Transcript', 'default_department_sequence' => []])->assertUnprocessable();
        $this->postJson('/api/admin/request-types', ['name' => 'Transcript', 'default_department_sequence' => ['UNKNOWN']])->assertUnprocessable();
        $this->postJson('/api/admin/request-types', ['name' => 'Transcript', 'default_department_sequence' => ['STUDENT_DEPARTMENT', $department->id]])->assertCreated();
    }

    public function test_student_profile_consistency_and_transaction_rollback(): void
    {
        $this->actingAs($this->admin(), 'sanctum');
        $faculty = Faculty::create(['name' => 'Science', 'code' => 'SCI', 'matricule_prefix' => 'SC']);
        $other = Faculty::create(['name' => 'Arts', 'code' => 'ART', 'matricule_prefix' => 'AR']);
        $department = Department::create(['faculty_id' => $faculty->id, 'name' => 'Math', 'code' => 'MTH', 'type' => 'academic']);
        $programme = \App\Models\Programme::create(['department_id' => $department->id, 'name' => 'Math BSc', 'code' => 'BSCM', 'degree_type' => 'BACHELOR']);
        $data = ['name' => 'Student', 'email' => 'student@example.com', 'password' => 'password123', 'role' => 'student', 'matricule' => 'SC123', 'faculty_id' => $other->id, 'department_id' => $department->id, 'programme_id' => $programme->id, 'level' => '100'];
        $this->postJson('/api/admin/users', $data)->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['email' => 'student@example.com']);
        $data['faculty_id'] = $faculty->id;
        $this->postJson('/api/admin/users', $data)->assertCreated()->assertJsonPath('data.student_profile.matricule', 'SC123');
    }

    public function test_stats_and_audit_are_paginated(): void
    {
        $this->actingAs($this->admin(), 'sanctum');
        $this->getJson('/api/admin/stats')->assertOk()->assertJsonPath('data.total', 0)->assertJsonPath('data.avg_resolution_hours', null);
        $this->getJson('/api/admin/audit-log?per_page=1')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/admin/requests?per_page=1')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_resolution_hours_and_nullable_audit_actor(): void
    {
        $this->actingAs($this->admin(), 'sanctum');
        $student = User::factory()->staff()->create();
        $type = RequestType::create(['name' => 'Transcript', 'default_department_sequence' => ['STUDENT_DEPARTMENT']]);
        $request = DocumentRequest::create(['student_id' => $student->id, 'request_type_id' => $type->id, 'status' => 'ready']);
        $request->forceFill(['created_at' => now()->subHours(4), 'updated_at' => now()->subHours(4)])->saveQuietly();
        $history = StatusHistory::create(['request_id' => $request->id, 'old_status' => 'in_review', 'new_status' => 'ready', 'changed_by' => null]);
        $history->changed_at = now();
        $history->save();
        $stats = $this->getJson('/api/admin/stats')->assertOk()->assertJsonPath('data.by_status.ready', 1);
        $this->assertEqualsWithDelta(4, $stats->json('data.avg_resolution_hours'), 0.1);
        $this->getJson('/api/admin/audit-log')->assertOk()->assertJsonPath('data.0.changed_by', null);
        $this->getJson('/api/admin/requests?status=ready')->assertOk()->assertJsonPath('meta.total', 1);
    }
}
