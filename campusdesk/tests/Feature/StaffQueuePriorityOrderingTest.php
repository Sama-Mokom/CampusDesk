<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Programme;
use App\Models\RequestType;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\RequestCreationService;
use Database\Seeders\RequestTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffQueuePriorityOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_queue_orders_by_tier_priority_descending_then_fifo(): void
    {
        $faculty = Faculty::create(['name' => 'Faculty of Science', 'code' => 'FS', 'matricule_prefix' => 'FS']);
        $dept = Department::create(['faculty_id' => $faculty->id, 'code' => 'CS', 'name' => 'Computer Science', 'type' => 'academic']);
        Department::create(['faculty_id' => $faculty->id, 'code' => 'FSRO', 'name' => 'FS Records', 'type' => 'records']);
        $trd = Department::create(['faculty_id' => $faculty->id, 'code' => 'TRD', 'name' => 'Transcript Dept', 'type' => 'admin']);
        Department::create(['faculty_id' => $faculty->id, 'code' => 'AOE', 'name' => 'Enrollment Dept', 'type' => 'admin']);
        Department::create(['faculty_id' => $faculty->id, 'code' => 'AOC', 'name' => 'Certificate Dept', 'type' => 'admin']);
        $prog = Programme::create(['department_id' => $dept->id, 'code' => 'BCS', 'name' => 'B.Sc CS', 'degree_type' => 'BACHELOR']);

        app(RequestTypeSeeder::class)->run();

        // Staff user belonging to Computer Science dept (stage 1)
        $staffUser = User::factory()->staff()->create();
        $staffUser->staffProfile->departments()->attach($dept->id, ['is_primary' => true]);

        // Students
        $studentA = User::create(['name' => 'Student A', 'email' => 'a@test.com', 'password' => bcrypt('password'), 'role' => 'student']);
        StudentProfile::create(['user_id' => $studentA->id, 'faculty_id' => $faculty->id, 'department_id' => $dept->id, 'programme_id' => $prog->id, 'matricule' => 'FS22A001', 'level' => '200', 'status' => 'active']);

        $studentB = User::create(['name' => 'Student B', 'email' => 'b@test.com', 'password' => bcrypt('password'), 'role' => 'student']);
        StudentProfile::create(['user_id' => $studentB->id, 'faculty_id' => $faculty->id, 'department_id' => $dept->id, 'programme_id' => $prog->id, 'matricule' => 'FS22A002', 'level' => '200', 'status' => 'active']);

        $studentC = User::create(['name' => 'Student C', 'email' => 'c@test.com', 'password' => bcrypt('password'), 'role' => 'student']);
        StudentProfile::create(['user_id' => $studentC->id, 'faculty_id' => $faculty->id, 'department_id' => $dept->id, 'programme_id' => $prog->id, 'matricule' => 'FS22A003', 'level' => '200', 'status' => 'active']);

        $transcriptType = RequestType::where('name', 'Transcript Request')->firstOrFail();
        $normalTier = $transcriptType->tiers()->where('code', 'normal')->firstOrFail(); // Priority 1
        $fastTier = $transcriptType->tiers()->where('code', 'fast')->firstOrFail(); // Priority 2
        $superFastTier = $transcriptType->tiers()->where('code', 'super_fast')->firstOrFail(); // Priority 3

        $creator = app(RequestCreationService::class);

        // Request 1: Normal (Priority 1) submitted earliest
        $reqNormal = $creator->createForStudent($studentA->fresh(), $transcriptType, 'Normal request', $normalTier->id);

        // Request 2: Fast (Priority 2) submitted middle
        $reqFast = $creator->createForStudent($studentB->fresh(), $transcriptType, 'Fast request', $fastTier->id);

        // Request 3: Super Fast (Priority 3) submitted last
        $reqSuperFast = $creator->createForStudent($studentC->fresh(), $transcriptType, 'Super Fast request', $superFastTier->id);

        Sanctum::actingAs($staffUser->fresh());

        $response = $this->getJson('/api/stages');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(3, $data);

        // Must be ordered: Super Fast (Priority 3) first, then Fast (Priority 2), then Normal (Priority 1)
        $this->assertSame($reqSuperFast->id, $data[0]['request_id']);
        $this->assertSame('Super Fast Mode', $data[0]['request']['tier_name']);
        $this->assertSame(3, $data[0]['request']['tier_priority']);

        $this->assertSame($reqFast->id, $data[1]['request_id']);
        $this->assertSame('Fast Mode', $data[1]['request']['tier_name']);
        $this->assertSame(2, $data[1]['request']['tier_priority']);

        $this->assertSame($reqNormal->id, $data[2]['request_id']);
        $this->assertSame('Normal Mode', $data[2]['request']['tier_name']);
        $this->assertSame(1, $data[2]['request']['tier_priority']);
    }
}
