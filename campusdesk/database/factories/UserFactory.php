<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\Programme;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    // -------------------------------------------------------------------------
    // Transient properties — stashed by state methods, consumed in configure().
    // State methods never register their own afterCreating() hooks; exactly one
    // hook exists in configure(). This avoids duplicate-insert races between
    // competing hooks.
    // -------------------------------------------------------------------------

    private ?Faculty    $transientFaculty    = null;
    private ?Department $transientDepartment = null;
    private ?string     $transientLevel      = null;
    private ?string     $transientAdminLevel = null;

    // -------------------------------------------------------------------------
    // Default state — role: student, so a StudentProfile is auto-created.
    // This reflects real user distribution (students vastly outnumber staff).
    // -------------------------------------------------------------------------

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
            'role'              => 'student',
        ];
    }

    // -------------------------------------------------------------------------
    // State methods — only stash parameters, never register hooks
    // -------------------------------------------------------------------------

    /**
     * Produce a staff user.
     *
     * @param  string|null  $adminLevel  'dept_admin', 'super_admin', or null for plain staff.
     */
    public function staff(?string $adminLevel = null): static
    {
        $this->transientAdminLevel = $adminLevel;

        return $this->state(fn () => ['role' => 'staff']);
    }

    /**
     * Produce a student user, optionally scoped to a faculty, department, and/or level.
     */
    public function student(
        ?Faculty    $faculty    = null,
        ?Department $department = null,
        ?string     $level      = null,
    ): static {
        $this->transientFaculty    = $faculty;
        $this->transientDepartment = $department;
        $this->transientLevel      = $level;

        return $this->state(fn () => ['role' => 'student']);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Single authoritative afterCreating hook
    // -------------------------------------------------------------------------

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if ($user->role === 'staff') {
                $this->createStaffProfile($user);
            } elseif ($user->role === 'student') {
                $this->createStudentProfile($user);
            }

            // Reset transient state so this factory instance can be reused cleanly.
            $this->transientFaculty    = null;
            $this->transientDepartment = null;
            $this->transientLevel      = null;
            $this->transientAdminLevel = null;
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function createStaffProfile(User $user): void
    {
        StaffProfile::create([
            'user_id'     => $user->id,
            'staff_id'    => $this->generateUniqueStaffId(),
            'admin_level' => $this->transientAdminLevel, // null = plain staff
        ]);
    }

    private function createStudentProfile(User $user): void
    {
        // Resolve level
        $resolvedLevel = $this->transientLevel
            ?? fake()->randomElement(['100', '200', '300', '400']);

        // Resolve faculty and department.
        // Random-path queries are scoped to type='academic' so they can never land on
        // a records/admin department that has no undergraduate programmes.
        if ($this->transientDepartment !== null) {
            $resolvedDepartment = $this->transientDepartment;
            $resolvedFaculty    = $resolvedDepartment->faculty;
        } elseif ($this->transientFaculty !== null) {
            $resolvedFaculty    = $this->transientFaculty;
            // When a specific faculty is provided, select a department that:
            // 1. Belongs to that faculty
            // 2. Is type='academic' (not records/admin)
            // 3. Has at least one undergraduate programme (not postgraduate-only like CIV/DVM/MEDICINE)
            $resolvedDepartment = Department::where('faculty_id', $resolvedFaculty->id)
                ->where('type', 'academic')
                ->whereHas('programmes', function ($query) {
                    $query->whereIn('degree_type', Programme::UNDERGRADUATE_DEGREE_TYPES);
                })
                ->inRandomOrder()
                ->first()
                ?? throw new RuntimeException(
                    "No academic Department with undergraduate programmes found for Faculty '{$resolvedFaculty->name}'. " .
                    "Run ProgrammeSeeder before creating students."
                );
        } else {
            // Fully random selection:
            // 1. Pick a faculty that has at least one academic department
            //    (excludes Records Office which has only admin departments)
            // 2. Within that faculty, pick a department that is academic AND has undergrad programmes
            //    (excludes postgraduate-only departments like CIV, DVM, MEDICINE, WGS)
            $resolvedFaculty = Faculty::whereHas('departments', function ($query) {
                $query->where('type', 'academic')
                      ->whereHas('programmes', function ($subQuery) {
                          $subQuery->whereIn('degree_type', Programme::UNDERGRADUATE_DEGREE_TYPES);
                      });
            })->inRandomOrder()->first()
                ?? throw new RuntimeException(
                    "No Faculties with academic departments that have undergraduate programmes found. " .
                    "Run FacultySeeder, DepartmentSeeder, and ProgrammeSeeder first."
                );
            
            $resolvedDepartment = Department::where('faculty_id', $resolvedFaculty->id)
                ->where('type', 'academic')
                ->whereHas('programmes', function ($query) {
                    $query->whereIn('degree_type', Programme::UNDERGRADUATE_DEGREE_TYPES);
                })
                ->inRandomOrder()
                ->first()
                ?? throw new RuntimeException(
                    "No academic Department with undergraduate programmes found for Faculty '{$resolvedFaculty->name}'. " .
                    "This should not happen after faculty selection — check data integrity."
                );
        }

        // Resolve undergraduate programme — must reference Programme::UNDERGRADUATE_DEGREE_TYPES
        // directly, never a locally re-typed array, so this filter cannot silently drift.
        $resolvedProgramme = Programme::where('department_id', $resolvedDepartment->id)
            ->whereIn('degree_type', Programme::UNDERGRADUATE_DEGREE_TYPES)
            ->inRandomOrder()
            ->first()
            ?? throw new RuntimeException(
                "No undergraduate Programme found for Department '{$resolvedDepartment->name}'. " .
                "Run ProgrammeSeeder before creating students."
            );

        // -----------------------------------------------------------------------
        // Matricule generation
        //
        // Format: {FACULTY_PREFIX}{ENROLLMENT_YEAR_2}{SECTION}{SEQ_3}
        //   e.g.  FE24A001
        //
        // FACULTY_PREFIX  = matricule_prefix (never .code)
        // ENROLLMENT_YEAR = 2-digit year derived from current level:
        //                   years_spent = (level_int / 100) - 1
        //                   enrollment_year = 2026 - years_spent
        // SECTION         = "A" (hardcoded — registrar-owned business rule, out of scope)
        // SEQ             = next available 3-digit sequence in this prefix/year/section bucket
        //
        // Accepted trade-offs (deliberate — do not "fix"):
        //   - Section letter hardcoded "A"
        //   - Sequence capped at 999 per bucket (seeding volumes never approach this)
        //   - L500/L600 use the same linear year-math as L100–L400 (accepted approximation)
        //   - Transaction isolation: safe because seeders run as a single sequential process;
        //     isolation levels only govern concurrent transactions, which don't apply here.
        // -----------------------------------------------------------------------
        $facultyPrefix   = strtoupper($resolvedFaculty->matricule_prefix);
        $sectionLetter   = 'A';
        $levelInt        = (int) $resolvedLevel;
        $yearsSpent      = ($levelInt / 100) - 1;
        $enrollmentYear  = substr((string) (2026 - (int) $yearsSpent), -2);
        $prefixMatch     = $facultyPrefix . $enrollmentYear . $sectionLetter;

        $last      = StudentProfile::where('matricule', 'like', $prefixMatch . '%')
                         ->orderByDesc('matricule')
                         ->first();
        $nextSeq   = $last ? ((int) substr($last->matricule, -3) + 1) : 1;
        $matricule = $prefixMatch . str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);

        StudentProfile::create([
            'user_id'       => $user->id,
            'faculty_id'    => $resolvedFaculty->id,
            'department_id' => $resolvedDepartment->id,
            'programme_id'  => $resolvedProgramme->id,
            'level'         => $resolvedLevel,
            'matricule'     => $matricule,
        ]);
    }

    private function generateUniqueStaffId(): string
    {
        do {
            $staffId = 'STAFF-' . strtoupper(Str::random(6));
        } while (StaffProfile::where('staff_id', $staffId)->exists());

        return $staffId;
    }
}
