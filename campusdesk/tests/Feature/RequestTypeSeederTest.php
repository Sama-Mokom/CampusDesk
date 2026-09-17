<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\RequestType;
use Database\Seeders\RequestTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestTypeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_all_request_type_fields_and_is_idempotent(): void
    {
        $faculty = Faculty::create(['name' => 'Records Office', 'code' => 'RO', 'matricule_prefix' => 'RO']);
        $departments = collect([
            'TRD' => 'Transcript Department',
            'AOE' => 'Attestation of Enrollment',
            'AOC' => 'Attestation of Certificate',
        ])->map(fn (string $name, string $code) => Department::create([
            'faculty_id' => $faculty->id, 'code' => $code, 'name' => $name, 'type' => 'admin',
        ]));

        app(RequestTypeSeeder::class)->run();
        app(RequestTypeSeeder::class)->run();

        $this->assertCount(4, RequestType::all());
        $expected = [
            'Transcript Request' => ['Official academic transcript.', $departments['TRD']->id],
            'Attestation of Enrollment' => ['Official attestation confirming current enrollment.', $departments['AOE']->id],
            'Attestation of Completion of Degree' => ['Official attestation confirming completion of degree.', $departments['AOC']->id],
            'Correction of Transcript' => ['Request to correct errors on an issued transcript.', $departments['TRD']->id],
        ];

        foreach ($expected as $name => [$description, $finalDepartment]) {
            $type = RequestType::where('name', $name)->firstOrFail();
            $this->assertSame($description, $type->description);
            $this->assertSame(['STUDENT_DEPARTMENT', 'FACULTY_RECORDS', $finalDepartment], $type->default_department_sequence);
        }
    }
}
