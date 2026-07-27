<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Programme;
use App\Models\User;
use Database\Seeders\Support\StudentDistributionHelper;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentSeeder extends Seeder
{
    public function __construct(private int $studentsPerDepartment) {}

    public function run(): void
    {
        DB::transaction(function () {
            // Fetch all academic departments and eager-load their undergraduate programmes.
            //
            // type = 'academic' is the only structural guard used here — it excludes records
            // and admin departments (TRD, AOE, AOC) by design. whereHas() is intentionally
            // NOT used: it queries the DB independently of the eager load, which opens a
            // stale-data race when the DB contains leftover rows from prior failed seed runs.
            //
            // Instead, the eager load IS the filter. If a department's undergraduate
            // programmes collection is empty after loading, it is skipped via the guard below.
            // This means the DB programmes table is the single source of truth, queried once.
            //
            // Must reference Programme::UNDERGRADUATE_DEGREE_TYPES directly (never a locally
            // re-typed array) so this filter cannot silently drift from UserFactory's own filter.
            //
            // Transaction-isolation note (confirmed safe, do not re-litigate): wrapping this
            // entire loop in one transaction does not risk stale reads on UserFactory's
            // matricule-sequence lookup. A transaction always sees its own prior writes;
            // isolation levels only govern visibility between separate, concurrent transactions,
            // and seeders run as a single sequential process.
            $departments = Department::where('type', 'academic')
                ->with(['programmes' => function (HasMany $query) {
                    $query->whereIn('degree_type', Programme::UNDERGRADUATE_DEGREE_TYPES);
                }])
                ->get();

            foreach ($departments as $department) {
                // Skip departments that have no undergraduate programmes (e.g. CIV, DVM,
                // MEDICINE, WGS — purely postgraduate or unclassifiable).
                if ($department->programmes->isEmpty()) {
                    continue;
                }

                $levels = StudentDistributionHelper::distribute($this->studentsPerDepartment);

                foreach ($levels as $level) {
                    User::factory()->student(department: $department, level: $level)->create();
                }
            }
        });
    }
}
