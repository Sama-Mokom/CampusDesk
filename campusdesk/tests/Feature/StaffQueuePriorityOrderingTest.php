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
use App\Services\RequestCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffQueuePriorityOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_queue_orders_by_tier_priority_descending_then_fifo(): void
    {
        $faculty = Faculty::create(['name' => 'Faculty of Engineering', 'code' => 'FE', 'matricule_prefix' => 'FE']);
        $dept = Department::create(['faculty_id' => $faculty->id, 'name' => 'Computer Engineering', 'code' => 'CEN', 'type' => 'academic']);
        $programme = Programme::create(['department_id' => $dept->id, 'name' => 'BEng', 'code' => 'BENG', 'degree_type' => 'BACHELOR']);

        $transcriptType = RequestType::create([
            'name' => 'Transcript Request',
            'description' => 'Official academic transcript',
            'default_department_sequence' => [$dept->id],
        ]);

        $normalTier = RequestTypeTier::create([
            'request_type_id' => $transcriptType->id,
            'code' => 'normal',
            'name' => 'Normal Mode',
            'fee_amount' => 1000,
            'turnaround_days' => 14,
            'priority' => 1,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $fastTier = RequestTypeTier::create([
            'request_type_id' => $transcriptType->id,
            'code' => 'fast',
            'name' => 'Fast Mode',
            'fee_amount' => 2500,
            'turnaround_days' => 7,
            'priority' => 2,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $superFastTier = RequestTypeTier::create([
            'request_type_id' => $transcriptType->id,
            'code' => 'super_fast',
            'name' => 'Super Fast Mode',
            'fee_amount' => 5000,
            'turnaround_days' => 3,
            'priority' => 3,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $studentA = User::factory()->create(['role' => 'student']);
        $studentA->studentProfile->update(['faculty_id' => $faculty->id, 'department_id' => $dept->id, 'programme_id' => $programme->id, 'matricule' => 'FE24A001']);

        $studentB = User::factory()->create(['role' => 'student']);
        $studentB->studentProfile->update(['faculty_id' => $faculty->id, 'department_id' => $dept->id, 'programme_id' => $programme->id, 'matricule' => 'FE24A002']);

        $studentC = User::factory()->create(['role' => 'student']);
        $studentC->studentProfile->update(['faculty_id' => $faculty->id, 'department_id' => $dept->id, 'programme_id' => $programme->id, 'matricule' => 'FE24A003']);

        $staffUser = User::factory()->staff()->create();
        $staffUser->staffProfile->departments()->attach($dept->id, ['is_primary' => true]);

        $creator = app(RequestCreationService::class);

        // Request 1: Normal (Priority 1) submitted earliest
        $reqNormal = $creator->createForStudent($studentA->fresh(), $transcriptType, 'Normal request', $normalTier->id);

        // Request 2: Fast (Priority 2) submitted middle
        $reqFast = $creator->createForStudent($studentB->fresh(), $transcriptType, 'Fast request', $fastTier->id);

        // Request 3: Super Fast (Priority 3) submitted last
        $reqSuperFast = $creator->createForStudent($studentC->fresh(), $transcriptType, 'Super Fast request', $superFastTier->id);

        // Mark requests as paid so they enter staff claimable queue
        DocumentRequest::query()->whereIn('id', [$reqNormal->id, $reqFast->id, $reqSuperFast->id])
            ->update(['status' => 'pending', 'paid_at' => now()]);

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
