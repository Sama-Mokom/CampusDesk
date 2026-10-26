<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Programme;
use App\Models\RequestType;
use App\Models\RequestTypeTier;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RequestTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentRequestTierCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private RequestType $transcriptType;
    private RequestType $attestationType;

    protected function setUp(): void
    {
        parent::setUp();

        $faculty = Faculty::create(['name' => 'Faculty of Engineering', 'code' => 'FE', 'matricule_prefix' => 'FE']);
        $dept = Department::create(['faculty_id' => $faculty->id, 'code' => 'CE', 'name' => 'Computer Engineering', 'type' => 'academic']);
        Department::create(['faculty_id' => $faculty->id, 'code' => 'FERO', 'name' => 'FE Records', 'type' => 'records']);
        Department::create(['faculty_id' => $faculty->id, 'code' => 'TRD', 'name' => 'Transcript Dept', 'type' => 'admin']);
        Department::create(['faculty_id' => $faculty->id, 'code' => 'AOE', 'name' => 'Enrollment Dept', 'type' => 'admin']);
        Department::create(['faculty_id' => $faculty->id, 'code' => 'AOC', 'name' => 'Certificate Dept', 'type' => 'admin']);
        $prog = Programme::create(['department_id' => $dept->id, 'code' => 'BENG', 'name' => 'B.Eng', 'degree_type' => 'BACHELOR']);

        $this->student = User::factory()->create(['role' => 'student']);
        StudentProfile::create([
            'user_id' => $this->student->id,
            'faculty_id' => $faculty->id,
            'department_id' => $dept->id,
            'programme_id' => $prog->id,
            'matricule' => 'FE22A001',
            'level' => '300',
            'status' => 'active',
        ]);

        app(RequestTypeSeeder::class)->run();

        $this->transcriptType = RequestType::where('name', 'Transcript Request')->firstOrFail();
        $this->attestationType = RequestType::where('name', 'Attestation of Enrollment')->firstOrFail();
    }

    public function test_single_tier_request_type_auto_resolves_tier_when_not_provided(): void
    {
        Sanctum::actingAs($this->student);

        $response = $this->postJson('/api/requests', [
            'request_type_id' => $this->attestationType->id,
            'description' => 'Need enrollment attestation for visa application.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.request_type', 'Attestation of Enrollment');
        $response->assertJsonPath('data.tier_name', 'Standard');
        $response->assertJsonPath('data.fee_amount', 2000);
        $response->assertJsonPath('data.turnaround_days', 3);
        $response->assertJsonPath('data.tier_priority', 2);
        $response->assertJsonPath('data.currency', 'XAF');

        $this->assertDatabaseHas('requests', [
            'student_id' => $this->student->id,
            'request_type_id' => $this->attestationType->id,
            'tier_name_snapshot' => 'Standard',
            'fee_amount' => 2000,
            'turnaround_days_snapshot' => 3,
            'tier_priority' => 2,
            'currency' => 'XAF',
        ]);
    }

    public function test_multi_tier_request_type_requires_tier_selection(): void
    {
        Sanctum::actingAs($this->student);

        $response = $this->postJson('/api/requests', [
            'request_type_id' => $this->transcriptType->id,
            'description' => 'Transcript needed.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['request_type_tier_id']);
    }

    public function test_multi_tier_request_type_rejects_tier_from_another_request_type(): void
    {
        Sanctum::actingAs($this->student);

        $attestationTier = $this->attestationType->tiers->first();

        $response = $this->postJson('/api/requests', [
            'request_type_id' => $this->transcriptType->id,
            'request_type_tier_id' => $attestationTier->id,
            'description' => 'Transcript needed.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['request_type_tier_id']);
    }

    public function test_multi_tier_request_snapshots_selected_tier(): void
    {
        Sanctum::actingAs($this->student);

        $superFastTier = $this->transcriptType->tiers()->where('code', 'super_fast')->firstOrFail();

        $response = $this->postJson('/api/requests', [
            'request_type_id' => $this->transcriptType->id,
            'request_type_tier_id' => $superFastTier->id,
            'description' => 'Urgent transcript needed for scholarship deadline.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.request_type_tier_id', $superFastTier->id);
        $response->assertJsonPath('data.tier_name', 'Super Fast Mode');
        $response->assertJsonPath('data.fee_amount', 5000);
        $response->assertJsonPath('data.turnaround_days', 3);
        $response->assertJsonPath('data.tier_priority', 3);
        $response->assertJsonPath('data.currency', 'XAF');

        $this->assertDatabaseHas('requests', [
            'student_id' => $this->student->id,
            'request_type_id' => $this->transcriptType->id,
            'request_type_tier_id' => $superFastTier->id,
            'tier_name_snapshot' => 'Super Fast Mode',
            'fee_amount' => 5000,
            'turnaround_days_snapshot' => 3,
            'tier_priority' => 3,
            'currency' => 'XAF',
        ]);
    }
}
