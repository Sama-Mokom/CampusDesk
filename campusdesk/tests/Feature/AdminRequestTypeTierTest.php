<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Programme;
use App\Models\RequestType;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\RequestTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRequestTypeTierTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private RequestType $transcriptType;

    protected function setUp(): void
    {
        parent::setUp();

        $faculty = Faculty::create(['name' => 'Records Office', 'code' => 'RO', 'matricule_prefix' => 'RO']);
        collect([
            'TRD' => 'Transcript Dept',
            'AOE' => 'Enrollment Dept',
            'AOC' => 'Certificate Dept',
        ])->each(fn ($name, $code) => Department::create(['faculty_id' => $faculty->id, 'code' => $code, 'name' => $name, 'type' => 'admin']));

        app(RequestTypeSeeder::class)->run();

        $user = User::factory()->staff()->create();
        $user->staffProfile->update(['admin_level' => 'super_admin']);
        $this->superAdmin = $user->fresh('staffProfile');
        $this->transcriptType = RequestType::where('name', 'Transcript Request')->firstOrFail();
    }

    public function test_reference_data_endpoint_includes_active_tiers(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->getJson('/api/request-types');
        $response->assertOk();

        $transcriptData = collect($response->json())->firstWhere('name', 'Transcript Request');
        $this->assertNotNull($transcriptData);
        $this->assertArrayHasKey('tiers', $transcriptData);
        $this->assertCount(3, $transcriptData['tiers']);
        $this->assertSame('Normal Mode', $transcriptData['tiers'][0]['name']);
        $this->assertSame(1000, $transcriptData['tiers'][0]['fee_amount']);
    }

    public function test_admin_reference_endpoint_includes_tiers(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->getJson('/api/admin/request-types');
        $response->assertOk();

        $data = $response->json('data');
        $transcriptRow = collect($data)->firstWhere('name', 'Transcript Request');
        $this->assertNotNull($transcriptRow);
        $this->assertArrayHasKey('tiers', $transcriptRow);
        $this->assertCount(3, $transcriptRow['tiers']);
    }

    public function test_super_admin_can_update_request_type_tiers(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->patchJson("/api/admin/request-types/{$this->transcriptType->id}", [
            'name' => 'Transcript Request',
            'tiers' => [
                [
                    'code' => 'normal',
                    'name' => 'Normal Mode',
                    'fee_amount' => 1200,
                    'turnaround_days' => 15,
                    'priority' => 1,
                    'is_active' => true,
                    'sort_order' => 1,
                ],
                [
                    'code' => 'fast',
                    'name' => 'Fast Mode',
                    'fee_amount' => 3000,
                    'turnaround_days' => 6,
                    'priority' => 2,
                    'is_active' => true,
                    'sort_order' => 2,
                ],
                [
                    'code' => 'super_fast',
                    'name' => 'Super Fast Mode',
                    'fee_amount' => 6000,
                    'turnaround_days' => 2,
                    'priority' => 3,
                    'is_active' => true,
                    'sort_order' => 3,
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('request_type_tiers', [
            'request_type_id' => $this->transcriptType->id,
            'code' => 'normal',
            'fee_amount' => 1200,
            'turnaround_days' => 15,
        ]);
        $this->assertDatabaseHas('request_type_tiers', [
            'request_type_id' => $this->transcriptType->id,
            'code' => 'fast',
            'fee_amount' => 3000,
            'turnaround_days' => 6,
        ]);
    }

    public function test_super_admin_cannot_save_tier_with_fee_below_100(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->patchJson("/api/admin/request-types/{$this->transcriptType->id}", [
            'tiers' => [
                [
                    'code' => 'normal',
                    'name' => 'Normal Mode',
                    'fee_amount' => 50, // Less than minimum 100 XAF
                    'turnaround_days' => 10,
                    'priority' => 1,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tiers.0.fee_amount']);
    }
}
