<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Faculty;
use Database\Seeders\Support\DepartmentTypeMapper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    public function __construct(private array $parsedFaculties) {}

    public function run(): void
    {
        DB::transaction(function () {
            // Single query to build faculty code → id map; avoids N+1.
            $facultyIdMap = Faculty::pluck('id', 'code')->all();

            foreach ($this->parsedFaculties as $facultyData) {
                $facultyId = $facultyIdMap[$facultyData['code']] ?? null;

                if (!$facultyId) {
                    $this->command->warn(
                        "Skipping departments for unmapped faculty code: {$facultyData['code']}"
                    );
                    continue;
                }

                foreach ($facultyData['departments'] as $deptData) {
                    Department::updateOrCreate(
                        ['code' => $deptData['code']],
                        [
                            'faculty_id' => $facultyId,
                            'name'       => $deptData['name'],
                            'type'       => DepartmentTypeMapper::resolveType($deptData['code']),
                        ]
                    );
                }
            }
        });
    }
}
