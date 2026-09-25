<?php

namespace Tests\Feature\Auth;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Programme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_can_register(): void
    {
        $faculty = Faculty::create([
            'name' => 'Faculty of Science',
            'code' => 'SCI',
            'matricule_prefix' => 'SC',
        ]);
        $department = Department::create([
            'faculty_id' => $faculty->id,
            'name' => 'Computer Science',
            'code' => 'CSC',
            'type' => 'academic',
        ]);
        $programme = Programme::create([
            'department_id' => $department->id,
            'name' => 'Computer Science',
            'code' => 'BSC-CS',
            'degree_type' => 'BACHELOR',
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'matricule' => 'SC123456',
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'programme_id' => $programme->id,
            'level' => '100',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'role', 'student_profile'],
            ]);

        $token = $response->json('token');

        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('student_profiles', [
            'matricule' => 'SC123456',
            'faculty_id' => $faculty->id,
            'department_id' => $department->id,
            'programme_id' => $programme->id,
            'level' => '100',
        ]);

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', 'test@example.com');
    }
}
