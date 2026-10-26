<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\RequestType;
use App\Models\RequestTypeTier;
use Database\Seeders\RequestTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestTypeTierSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_tiers_for_all_request_types_and_is_idempotent(): void
    {
        $faculty = Faculty::create(['name' => 'Records Office', 'code' => 'RO', 'matricule_prefix' => 'RO']);
        collect([
            'TRD' => 'Transcript Department',
            'AOE' => 'Attestation of Enrollment',
            'AOC' => 'Attestation of Certificate',
        ])->each(fn (string $name, string $code) => Department::create([
            'faculty_id' => $faculty->id,
            'code' => $code,
            'name' => $name,
            'type' => 'admin',
        ]));

        app(RequestTypeSeeder::class)->run();
        app(RequestTypeSeeder::class)->run();

        $transcript = RequestType::where('name', 'Transcript Request')->firstOrFail();
        $this->assertCount(3, $transcript->tiers);
        $this->assertCount(3, $transcript->activeTiers);

        $normal = $transcript->tiers()->where('code', 'normal')->firstOrFail();
        $this->assertSame('Normal Mode', $normal->name);
        $this->assertSame(1000, $normal->fee_amount);
        $this->assertSame(14, $normal->turnaround_days);
        $this->assertSame(1, $normal->priority);
        $this->assertSame('XAF', $normal->currency);
        $this->assertTrue($normal->is_active);

        $fast = $transcript->tiers()->where('code', 'fast')->firstOrFail();
        $this->assertSame('Fast Mode', $fast->name);
        $this->assertSame(2500, $fast->fee_amount);
        $this->assertSame(7, $fast->turnaround_days);
        $this->assertSame(2, $fast->priority);

        $superFast = $transcript->tiers()->where('code', 'super_fast')->firstOrFail();
        $this->assertSame('Super Fast Mode', $superFast->name);
        $this->assertSame(5000, $superFast->fee_amount);
        $this->assertSame(3, $superFast->turnaround_days);
        $this->assertSame(3, $superFast->priority);

        $singleTierTypes = [
            'Attestation of Enrollment',
            'Attestation of Completion of Degree',
            'Correction of Transcript',
        ];

        foreach ($singleTierTypes as $typeName) {
            $type = RequestType::where('name', $typeName)->firstOrFail();
            $this->assertCount(1, $type->tiers);
            $tier = $type->tiers->first();
            $this->assertSame('standard', $tier->code);
            $this->assertSame('Standard', $tier->name);
            $this->assertSame(2000, $tier->fee_amount);
            $this->assertSame(3, $tier->turnaround_days);
            $this->assertSame(2, $tier->priority);
            $this->assertSame('XAF', $tier->currency);
            $this->assertTrue($tier->is_active);
        }
    }
}
