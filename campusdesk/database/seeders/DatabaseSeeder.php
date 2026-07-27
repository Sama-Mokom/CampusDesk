<?php

namespace Database\Seeders;

use Database\Seeders\Support\FacultyMarkdownParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Orchestration rules (per spec):
     *
     * - Seeders are manually instantiated (never $this->call()) so constructor
     *   arguments can be passed.
     * - setContainer() and setCommand() must be called explicitly on every instance
     *   because manual instantiation bypasses Laravel's automatic wiring.
     * - Every seeder's run() is wrapped in DB::transaction() — a mid-loop failure
     *   must not leave a partially-seeded table.
     * - WithoutModelEvents is intentionally NOT used here. The observer fires a
     *   notification job on RequestStage *update* (status change), not on create, so
     *   it poses no risk during stage-generation seeding. If Tier 4 seeding ever
     *   needs to suppress it, add WithoutModelEvents to the individual seeder, not here.
     *
     * Values: 60 staff, 10 students/dept.
     *   - Department count is ~55 across 11 faculties (10 real + Records Office).
     *     60 staff comfortably exceeds this, satisfying DepartmentStaffSeeder's
     *     guardrail (§6.6). Adjust if the parsed count differs significantly.
     *   - Confirm via: php artisan tinker → Department::count()
     */
    public function run(): void
    {
        // Parse once — FacultyMarkdownParser performs no database access.
        // DatabaseSeeder reads from database/seeders/support/ (canonical location).
        $content         = File::get(database_path('seeders/support/university_programs_structure.md'));
        $parsedFaculties = (new FacultyMarkdownParser())->parse($content, $this->command);

        // ------------------------------------------------------------------
        // Tier 0-1: reference data (strict order — each depends on the prior)
        // ------------------------------------------------------------------
        (new FacultySeeder($parsedFaculties))
            ->setContainer($this->container)->setCommand($this->command)->run();

        (new DepartmentSeeder($parsedFaculties))
            ->setContainer($this->container)->setCommand($this->command)->run();

        (new ProgrammeSeeder($parsedFaculties))
            ->setContainer($this->container)->setCommand($this->command)->run();

        // ------------------------------------------------------------------
        // Tier 2: users — Staff must exist before DepartmentStaffSeeder assigns them.
        // totalStaffCount must be >= total department count (guardrail in DepartmentStaffSeeder).
        // ------------------------------------------------------------------
        (new StaffSeeder(totalStaffCount: 80))
            ->setContainer($this->container)->setCommand($this->command)->run();

        (new StudentSeeder(studentsPerDepartment: 10))
            ->setContainer($this->container)->setCommand($this->command)->run();

        // ------------------------------------------------------------------
        // Tier 3: pivot assignments — depends on Staff + Departments both existing
        // ------------------------------------------------------------------
        (new DepartmentStaffSeeder())
            ->setContainer($this->container)->setCommand($this->command)->run();

        // ------------------------------------------------------------------
        // Tier 4 foundation: request types (depends on Departments for dept IDs)
        // ------------------------------------------------------------------
        (new RequestTypeSeeder())
            ->setContainer($this->container)->setCommand($this->command)->run();

        // Tier 4+ (RequestSeeder, stage progression) intentionally not called yet.
        // See spec §7.6 and §8 — stage-progression mechanic is NOT READY FOR IMPLEMENTATION.
    }
}
