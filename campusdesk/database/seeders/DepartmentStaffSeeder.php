<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\StaffProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DepartmentStaffSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $departments     = Department::all();
            $staffCount      = StaffProfile::count();
            $departmentCount = $departments->count();

            if ($staffCount < $departmentCount) {
                throw new RuntimeException(
                    "Cannot guarantee one primary staff per department: {$staffCount} staff exist, but " .
                    "{$departmentCount} departments require assignment. Increase StaffSeeder's total count."
                );
            }

            // Shuffle the full pool so assignment is random, not alphabetical.
            $staffPool = StaffProfile::all()->shuffle();

            // -----------------------------------------------------------------------
            // PASS 1: guarantee exactly one primary per department.
            //
            // Outer-looping departments (not staff) is what structurally guarantees
            // full department coverage. Multi-department-primary is explicitly
            // disallowed — avoids masking department-scoping bugs and produces
            // realistic, deterministic test data.
            // -----------------------------------------------------------------------
            foreach ($departments as $department) {
                /** @var StaffProfile $primaryStaff */
                $primaryStaff = $staffPool->pop();

                $department->staffProfiles()->attach($primaryStaff->id, [
                    'is_primary' => true,
                ]);

                $primaryStaff->update(['admin_level' => 'dept_admin']);
            }

            // -----------------------------------------------------------------------
            // PASS 2: drain remaining staff as secondary (one assignment each).
            //
            // No duplicate-pair guard is needed: Pass 1 and Pass 2 draw from a
            // strictly partitioned pool. A staff member popped in Pass 2 was never
            // touched in Pass 1, so structural uniqueness is already guaranteed.
            // -----------------------------------------------------------------------
            while ($staffPool->isNotEmpty()) {
                /** @var StaffProfile $secondaryStaff */
                $secondaryStaff = $staffPool->pop();

                $randomDepartment = $departments->random();

                $randomDepartment->staffProfiles()->attach($secondaryStaff->id, [
                    'is_primary' => false,
                ]);
            }
        });
    }
}
