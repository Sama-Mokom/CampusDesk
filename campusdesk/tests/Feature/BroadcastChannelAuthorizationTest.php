<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Programme;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        require base_path('routes/channels.php');
    }

    public function test_user_can_authorize_their_own_private_channel(): void
    {
        $user = $this->makeStudent('student-1');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => "private-users.{$user->id}",
                'socket_id' => '1234.5678',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_user_cannot_authorize_another_users_private_channel(): void
    {
        $user1 = $this->makeStudent('student-1');
        $user2 = $this->makeStudent('student-2');

        $response = $this->actingAs($user1, 'sanctum')
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => "private-users.{$user2->id}",
                'socket_id' => '1234.5678',
            ]);

        $response->assertForbidden();
    }

    public function test_staff_can_authorize_their_department_channel(): void
    {
        $department = $this->makeDepartment('CSC');
        $staff = $this->makeStaffInDepartment($department, 'staff-csc');

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => "private-departments.{$department->id}",
                'socket_id' => '1234.5678',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_staff_cannot_authorize_foreign_department_channel(): void
    {
        $dept1 = $this->makeDepartment('CSC');
        $dept2 = $this->makeDepartment('MTH');
        $staff = $this->makeStaffInDepartment($dept1, 'staff-csc');

        $response = $this->actingAs($staff, 'sanctum')
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => "private-departments.{$dept2->id}",
                'socket_id' => '1234.5678',
            ]);

        $response->assertForbidden();
    }

    public function test_super_admin_can_authorize_any_department_channel(): void
    {
        $department = $this->makeDepartment('CSC');
        $superAdmin = $this->makeSuperAdmin('super-admin');

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => "private-departments.{$department->id}",
                'socket_id' => '1234.5678',
            ]);

        $response->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_student_cannot_authorize_department_channel(): void
    {
        $department = $this->makeDepartment('CSC');
        $student = $this->makeStudent('student-csc');

        $response = $this->actingAs($student, 'sanctum')
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => "private-departments.{$department->id}",
                'socket_id' => '1234.5678',
            ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_request_to_broadcast_auth_is_unauthorized(): void
    {
        $response = $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-users.1',
            'socket_id' => '1234.5678',
        ]);

        $response->assertUnauthorized();
    }

    private function makeStudent(string $suffix): User
    {
        $faculty = Faculty::create([
            'name' => "Faculty {$suffix}",
            'code' => "FAC-{$suffix}",
            'matricule_prefix' => "F{$suffix}",
        ]);

        $department = Department::create([
            'faculty_id' => $faculty->id,
            'name' => "Department {$suffix}",
            'code' => "DEP-{$suffix}",
            'type' => 'academic',
        ]);

        $programme = Programme::create([
            'department_id' => $department->id,
            'name' => "Programme {$suffix}",
            'code' => "PROG-{$suffix}",
            'degree_type' => 'BACHELOR',
        ]);

        $student = User::create([
            'name' => "Student {$suffix}",
            'email' => "{$suffix}@example.test",
            'email_verified_at' => now(),
            'password' => 'password',
            'role' => 'student',
        ]);

        StudentProfile::create([
            'user_id' => $student->id,
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'programme_id' => $programme->id,
            'matricule' => "MAT-{$suffix}",
            'level' => '100',
            'status' => 'active',
        ]);

        return $student;
    }

    private function makeDepartment(string $code): Department
    {
        $faculty = Faculty::create([
            'name' => "Faculty {$code}",
            'code' => "FAC-{$code}",
            'matricule_prefix' => "F{$code}",
        ]);

        return Department::create([
            'faculty_id' => $faculty->id,
            'name' => "Department {$code}",
            'code' => "DEP-{$code}",
            'type' => 'academic',
        ]);
    }

    private function makeStaffInDepartment(Department $department, string $suffix): User
    {
        $staff = User::create([
            'name' => "Staff {$suffix}",
            'email' => "{$suffix}@example.test",
            'email_verified_at' => now(),
            'password' => 'password',
            'role' => 'staff',
        ]);

        $profile = $staff->staffProfile()->create([
            'staff_id' => "STAFF-{$suffix}",
            'admin_level' => null,
        ]);

        $profile->departments()->attach($department->id, ['is_primary' => true]);

        return $staff;
    }

    private function makeSuperAdmin(string $suffix): User
    {
        $superAdmin = User::create([
            'name' => "Super Admin {$suffix}",
            'email' => "{$suffix}@example.test",
            'email_verified_at' => now(),
            'password' => 'password',
            'role' => 'staff',
        ]);

        $superAdmin->staffProfile()->create([
            'staff_id' => "SUPER-{$suffix}",
            'admin_level' => 'super_admin',
        ]);

        return $superAdmin;
    }
}
