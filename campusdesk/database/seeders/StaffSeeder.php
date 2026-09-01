<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StaffSeeder extends Seeder
{
    /**
     * @param  int  $totalStaffCount  Must be >= total department count, or DepartmentStaffSeeder
     *                                will throw. Pick a value comfortably above the department
     *                                count (~50+ departments across real faculties + Records Office).
     */
    public function __construct(private int $totalStaffCount) {}

    public function run(): void
    {
        DB::transaction(function () {
            // No admin_level argument — every seeded staff member starts as plain staff
            // (admin_level: null). dept_admin is assigned later by DepartmentStaffSeeder at
            // the moment a staff member becomes a department primary.
            // super_admin accounts are never seeded — create manually via Tinker if needed.
            User::factory()->count($this->totalStaffCount)->staff()->create();
        });
    }
}
