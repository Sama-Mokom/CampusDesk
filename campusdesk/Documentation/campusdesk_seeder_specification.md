
Campusdesk seeder specification · MD
# CampusDesk — Database Seeder Technical Specification
 
**Project:** CampusDesk (University Document Request & Tracking System)
**Author:** Nkeng Sama Mokom (FE23A118)
**Last updated:** 23 July 2026
**Status:** Tiers 0–1 fully specified and implementation-ready. Tiers 2–6 partially specified — see §8 "Open Items."
 
---
 
## 1. Purpose
 
This document is the single source of truth for how CampusDesk's database seeding system is built. It supersedes all prior scratch pseudocode discussed during development. Anything not explicitly written here should be treated as **not yet decided**, not assumed.
 
---
 
## 2. Required Schema Changes
 
These migrations must exist and have been run before any seeder in this document will work.
 
| Migration | Change |
|---|---|
| `add_matricule_prefix_to_faculties_table` | Adds `faculties.matricule_prefix` — `string`, `unique`, manually populated (not derivable from `code`). |
| `add_department_id_to_programmes_table` | Adds `programmes.department_id` — `foreignId`, `NOT NULL`, references `departments.id`, `restrictOnDelete()`. `programmes.faculty_id` is **retained** (accepted denormalization) and is auto-derived — see §5.3. |
| `change_enum_values_on_student_profiles_table` | Drops and re-adds `student_profiles.level` as `enum('100','200','300','400','500','600')` (plain numeric strings, not `L100`-style). `500`/`600` represent extended-duration undergraduates at University of Buea, **not** postgraduate tiers — CampusDesk seeding remains undergraduate-only. |
| `add_columns_to_departments_table` | Adds `departments.name` (gap-fill; was missing from the original ERD-matching migration). |
 
**Confirmed column constraints relevant to seeding:**
- `departments.code` — `string(20)`, `unique` (global, not per-faculty). A seeding run **may throw a duplicate-key exception** if two departments across the source data accidentally share a code. Not pre-validated — run the seeder and resolve on failure, per team decision (acceptable given a disposable dev database).
- `student_profiles.matricule` — `unique`.
---
 
## 3. Data Sources
 
Two markdown files hold real University of Buea structural data:
 
- `university_faculties_and_programs.md` — two-level (Faculty → Department) structure.
- `university_programs_structure__1_.md` — three-level (Faculty → Department → Programme) structure. **Confirmed superset** of the first file; the parser targets this file only.
Both files use a consistent heading format:
```
## Faculty Name - CODE
### Department Name - CODE
1. Programme Name - CODE
```
 
---
 
## 4. `FacultyMarkdownParser`
 
**Location:** `database/seeders/Support/FacultyMarkdownParser.php`
**Responsibility:** Read the markdown file once; return a fully nested, structured array. Performs no database access.
 
### 4.1 Regex Patterns
 
Faculty and department codes and programme codes contain characters beyond plain alphanumerics (`PUL-PUA`, `GS/PB`, `CST-LAW`, `TRA/INT`, `MBA-ACC`). The character class must include `/` and internal `-` in addition to `A-Z0-9_`. The separator itself is unambiguous because it is always surrounded by spaces (`\s+-\s+`), regardless of what characters appear inside the code.
 
```
FACULTY_PATTERN    = /^##\s+(?<name>.+?)\s+-\s+(?<code>[A-Z0-9_\/-]+)$/
DEPARTMENT_PATTERN = /^###\s+(?<name>.+?)\s+-\s+(?<code>[A-Z0-9_\/-]+)$/
PROGRAMME_PATTERN  = /^\d+\.\s+(?<name>.+)\s+-\s+(?<code>[A-Z0-9_\/-]+)$/
```
 
Note: the programme pattern's `name` group is **greedy** (`.+`, not `.+?`), which correctly resolves malformed lines with internal, unspaced dashes (e.g. `M.A. IN TRANSLATION (ENGLISH A, FRENCH B, KISWAHILI C - TRA`) by matching as much as possible before the final `- CODE` anchor.
 
### 4.2 State Machine Algorithm
 
The parser is a line-by-line state machine tracking two variables across iterations: `$currentFaculty` and `$currentDepartment`. Records are "flushed" into their parent (or into the master results array) either when a new heading of equal-or-higher level is encountered, or at end-of-file.
 
```
$faculties = []
$currentFaculty = null
$currentDepartment = null
 
FOR EACH line (1-indexed) in file:
    line = trim(rawLine)
    IF line is empty OR line == '---': CONTINUE
 
    IF line matches FACULTY_PATTERN:
        flush currentDepartment into currentFaculty (if both exist)
        flush currentFaculty into faculties (if exists)
        currentFaculty = { name, code, departments: [] }
        CONTINUE
 
    IF line matches DEPARTMENT_PATTERN:
        IF currentFaculty is null: warn "Department before Faculty context"; CONTINUE
        flush currentDepartment into currentFaculty (if exists)
        currentDepartment = { name, code, programmes: [] }
        CONTINUE
 
    IF line matches PROGRAMME_PATTERN:
        IF currentDepartment is null: warn "Programme outside Department context"; CONTINUE
        currentDepartment.programmes[] = { name, code }
        CONTINUE
 
    warn "Unrecognized line structure: {line}"
 
// EOF flush
flush currentDepartment into currentFaculty (if both exist)
flush currentFaculty into faculties (if exists)
 
RETURN faculties
```
 
### 4.3 Method Signature
 
```php
public function parse(string $fileContent, ?Illuminate\Console\Command $command = null): array
```
 
`$command` is nullable so the parser is callable outside a seeder context (Tinker, tests). Warnings are emitted via `$command?->warn(...)` — **never** via `Log::warning()`, per project convention: silent log-file warnings are rejected in favor of visible console output during `db:seed`, so anomalies are caught immediately rather than discovered later.
 
### 4.4 Return Shape
 
```php
[
    [
        'name' => 'Faculty of Engineering and Technology',
        'code' => 'FET',
        'departments' => [
            [
                'name' => 'COMPUTER ENGINEERING(FET)',
                'code' => 'CEN',
                'programmes' => [
                    ['name' => 'B.Tech Computer Engineering', 'code' => 'CET'],
                    // ...
                ],
            ],
            // ...
        ],
    ],
    // ...
]
```
 
---
 
## 5. Support Classes
 
### 5.1 `FacultyMatriculeMapper`
 
**Location:** `database/seeders/Support/FacultyMatriculeMapper.php`
**Responsibility:** Resolve a faculty's `code` (from the markdown, e.g. `FET`) to its registrar-assigned `matricule_prefix` (e.g. `FE`). This mapping is **not algorithmically derivable** — `FET → FE` drops the last letter, `FED → ED` drops the first — so it is hardcoded, manually verified institutional knowledge.
 
```php
class FacultyMatriculeMapper
{
    private const MAP = [
        'ASTI' => 'AS', 'COT' => 'CT', 'FAVM' => 'AV', 'FA' => 'AR',
        'FED' => 'ED', 'FET' => 'FE', 'FHS' => 'HS', 'FLPS' => 'LP',
        'FS' => 'SC', 'FSMS' => 'SM',
    ];
 
    public static function getPrefix(string $facultyCode): string
    {
        if (!isset(self::MAP[$facultyCode])) {
            throw new \InvalidArgumentException(
                "Unmapped faculty code '{$facultyCode}'. Matricule prefixes cannot be derived " .
                "algorithmically. Add '{$facultyCode}' to FacultyMatriculeMapper::MAP."
            );
        }
        return self::MAP[$facultyCode];
    }
}
```
 
**Design rule:** fails loudly (throws `InvalidArgumentException`) on an unmapped code rather than falling back to a guessed substring — consistent with project-wide convention of never silently generating plausible-but-wrong data. No caller-side guard is needed; the exception is sufficient and halts the run immediately.
 
### 5.2 `MATCH_DEGREE_TYPE` Classifier
 
**Location:** TBD — likely a static method on a `ProgrammeClassifier` support class (naming/location not yet finalized — see §8).
 
```
DEFINE MATCH_DEGREE_TYPE(programme_string):
    NO_DOTS = STR_REPLACE(['.'], '', programme_string)          // "M.A." -> "MA", "Ph.D" -> "PHD"
    CLEANED = UPPERCASE( STR_REPLACE(['-','(',')','*',','], ' ', NO_DOTS) )
 
    IF CLEANED MATCHES /\b(PHD|RESIDENT|DOCTOR OF)\b/          -> RETURN 'PHD'
    IF CLEANED MATCHES /\b(MA|MSC|MTECH|MENG|MED|LLM|MBA|MASTER|MASTERS|POSTGRADUATE)\b/ -> RETURN 'MASTER'
    IF CLEANED MATCHES /\b(BA|BSC|BTECH|BENG|BED|LLB|BBA|BACHELOR|BARRISTER)\b/          -> RETURN 'BACHELOR'
    IF CLEANED MATCHES /\b(DIPLOMA|CERTIFICATE|HIGHER CERTIFICATE)\b/                     -> RETURN 'CERTIFICATE'
    RETURN 'UNCLASSIFIED'
```
 
**Critical ordering note:** periods must be **stripped entirely**, not replaced with a space — replacing with a space breaks contiguous abbreviation matching (`"M.A."` → `"M A "` fails `\bMA\b`; must become `"MA"`).
 
`UNCLASSIFIED` results are skipped during seeding with a visible `$this->command->warn()`, not a fatal exception — this is source-data noise (e.g. `"Void - FAP"`), not a schema-integrity violation, so it does not halt the run.
 
**Decision — no MASTER/PHD filtering in `ProgrammeSeeder`:** every classified programme (`BACHELOR`, `CERTIFICATE`, `MASTER`, `PHD`) is persisted as-is. `MASTER`/`PHD` rows currently have no consumer — `UserFactory` never selects them, since the platform is undergraduate-only for now — but building an explicit filter to exclude them was judged unnecessary effort for the current development/testing phase. Only `UNCLASSIFIED` rows are skipped (with a warning), since those represent genuine source-data noise, not a valid-but-unused degree tier.
 
### 5.3 `Programme` Model — Auto-Derived `faculty_id`
 
**Location:** `app/Models/Programme.php`
 
```php
class Programme extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Programme $programme) {
            $programme->faculty_id = $programme->department->faculty_id;
        });
 
        static::updating(function (Programme $programme) {
            if ($programme->isDirty('department_id')) {
                $programme->faculty_id = $programme->department->faculty_id;
            }
        });
    }
}
```
 
**Rationale:** `programmes.faculty_id` is retained as a deliberate denormalization (avoids a join on faculty-scoped programme queries) but is never independently trusted at write time — it is always derived from `department_id` via a single enforcement point, eliminating the risk of contradictory data at any call site (seeder, admin panel, Tinker).
 
### 5.4 `Programme` Model — Degree Type Constants (Single Source of Truth)
 
**Location:** `app/Models/Programme.php` (same model as §5.3)
 
```php
class Programme extends Model
{
    /**
     * Degree types classified as undergraduate programmes.
     */
    public const UNDERGRADUATE_DEGREE_TYPES = ['BACHELOR', 'CERTIFICATE'];
 
    /**
     * Degree types classified as postgraduate programmes. Declared for
     * completeness/future-proofing; not consumed anywhere yet.
     */
    public const POSTGRADUATE_DEGREE_TYPES = ['MASTER', 'PHD'];
 
    // ... booted(), relationships, etc. (see §5.3)
}
```
 
**Rationale:** `['BACHELOR', 'CERTIFICATE']` was on the verge of being hardcoded independently in two places — `UserFactory`'s `ALLOWED_DEGREE_TYPES` and `StudentSeeder`'s `whereHas()` eligibility filter (see §7 and §7.5). Two independently-typed copies of the same list have already almost drifted apart once during design (a stray `'MASTER'` was briefly added to one copy "for redundancy," which would have silently defeated the eligibility filter's purpose — see design history). Both consumers must now reference `Programme::UNDERGRADUATE_DEGREE_TYPES` directly rather than re-declaring the array, so the two can never diverge again.
 
### 5.5 `Department` Model — Required Fixes
 
**Location:** `app/Models/Department.php`
 
Two corrections identified during design, both must be applied before seeding:
 
1. **`$fillable` was missing `'name'`.** `DepartmentSeeder` (§6.2) mass-assigns `name` via `updateOrCreate()`. Without `name` in `$fillable`, Eloquent silently discards that field on insert unless `Model::preventSilentlyDiscardingAttributes()` is explicitly enabled elsewhere — the exact "$fillable omission causes silent data discard" failure mode already known to recur in this project.
2. **`programmes()` relationship was missing entirely.** Required for `StudentSeeder`'s `whereHas('programmes', ...)` eligibility query (§7.5) to function — without it, calling `whereHas('programmes', ...)` throws a `BadMethodCallException` at runtime.
```php
class Department extends Model
{
    protected $fillable = [
        'faculty_id',
        'code',
        'name', // was missing — caused silent data discard on insert
    ];
 
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }
 
    public function programmes(): HasMany
    {
        return $this->hasMany(Programme::class); // was missing — required by StudentSeeder::whereHas()
    }
 
    public function requestStages(): HasMany
    {
        return $this->hasMany(RequestStage::class);
    }
 
    public function staffProfiles(): BelongsToMany
    {
        return $this->belongsToMany(StaffProfile::class);
    }
}
```
 
---
 
## 6. Seeders
 
All seeders use **constructor injection** for parsed data (not method-parameter injection) and are **manually instantiated** by `DatabaseSeeder` rather than invoked via `$this->call()`, since `call()` does not support passing constructor arguments. Manual instantiation means `setContainer()` and `setCommand()` must be called explicitly — these are not wired automatically outside of `$this->call()`.
 
### 6.1 `FacultySeeder`
 
**Location:** `database/seeders/FacultySeeder.php`
 
```php
class FacultySeeder extends Seeder
{
    public function __construct(private array $parsedFaculties) {}
 
    public function run(): void
    {
        DB::transaction(function () {
            foreach ($this->parsedFaculties as $parsedFaculty) {
                $prefix = FacultyMatriculeMapper::getPrefix($parsedFaculty['code']); // throws if unmapped
 
                Faculty::updateOrCreate(
                    ['code' => $parsedFaculty['code']],
                    ['name' => $parsedFaculty['name'], 'matricule_prefix' => $prefix]
                );
            }
        });
    }
}
```
 
**Transaction rationale:** `Seeder::run()` is not automatically transactional. Without an explicit `DB::transaction()` wrapper, a mid-loop exception (e.g. an unmapped faculty code) leaves previously-inserted rows committed, producing a partially-seeded table. Wrapping in a transaction guarantees all-or-nothing insertion — a failure rolls back cleanly, leaving the table in its pre-run state.
 
### 6.2 `DepartmentSeeder`
 
**Location:** `database/seeders/DepartmentSeeder.php`
 
```php
class DepartmentSeeder extends Seeder
{
    public function __construct(private array $parsedFaculties) {}
 
    public function run(): void
    {
        DB::transaction(function () {
            $facultyIdMap = Faculty::pluck('id', 'code')->all(); // single query, avoids N+1
 
            foreach ($this->parsedFaculties as $facultyData) {
                $facultyId = $facultyIdMap[$facultyData['code']] ?? null;
                if (!$facultyId) {
                    $this->command->warn("Skipping departments for unmapped faculty code: {$facultyData['code']}");
                    continue;
                }
 
                foreach ($facultyData['departments'] as $deptData) {
                    Department::updateOrCreate(
                        ['code' => $deptData['code']],
                        ['faculty_id' => $facultyId, 'name' => $deptData['name']]
                    );
                }
            }
        });
    }
}
```
 
### 6.3 `ProgrammeSeeder`
 
**Location:** `database/seeders/ProgrammeSeeder.php`
 
```php
class ProgrammeSeeder extends Seeder
{
    public function __construct(private array $parsedFaculties) {}
 
    public function run(): void
    {
        DB::transaction(function () {
            $departmentIdMap = Department::pluck('id', 'code')->all();
 
            foreach ($this->parsedFaculties as $facultyData) {
                foreach ($facultyData['departments'] as $deptData) {
                    $departmentId = $departmentIdMap[$deptData['code']] ?? null;
                    if (!$departmentId) {
                        $this->command->warn("Skipping programmes for unmapped department: {$deptData['code']}");
                        continue;
                    }
 
                    foreach ($deptData['programmes'] as $progData) {
                        $degreeType = MATCH_DEGREE_TYPE($progData['name']);
 
                        if ($degreeType === 'UNCLASSIFIED') {
                            $this->command->warn("Unclassified programme skipped: '{$progData['name']}'");
                            continue;
                        }
                        // MASTER/PHD rows are persisted with no filtering — accepted, no current consumer.
 
                        Programme::updateOrCreate(
                            ['code' => $progData['code']],
                            [
                                'department_id' => $departmentId,
                                'name' => $progData['name'],
                                'degree_type' => $degreeType,
                            ]
                        );
                    }
                }
            }
        });
    }
}
```
 
### 6.4 `DatabaseSeeder` — Tier 0–1 Orchestration
 
**Location:** `database/seeders/DatabaseSeeder.php`
 
```php
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Parse once ---
        $content = File::get(database_path('data/university_programs_structure.md'));
        $parsedFaculties = (new FacultyMarkdownParser())->parse($content, $this->command);
 
        // --- Tier 0-1: Faculties -> Departments -> Programmes (strict order) ---
        (new FacultySeeder($parsedFaculties))
            ->setContainer($this->container)->setCommand($this->command)->run();
 
        (new DepartmentSeeder($parsedFaculties))
            ->setContainer($this->container)->setCommand($this->command)->run();
 
        (new ProgrammeSeeder($parsedFaculties))
            ->setContainer($this->container)->setCommand($this->command)->run();
 
        // --- Tier 2+: see §8, not yet implemented ---
    }
}
```
 
---
 
## 7. `UserFactory` (Tier 2 — Users, Student/Staff Profiles)
 
**Location:** `database/factories/UserFactory.php`
 
### 7.1 Transient Properties
 
```php
private ?Faculty $transientFaculty = null;
private ?Department $transientDepartment = null;
private ?string $transientLevel = null;
private ?string $transientAdminLevel = null;
```
 
### 7.2 State Methods
 
```php
public function staff(?string $adminLevel = null): static
{
    $this->transientAdminLevel = $adminLevel;
    return $this->state(fn () => ['role' => 'staff']);
}
 
public function student(?Faculty $faculty = null, ?Department $department = null, ?string $level = null): static
{
    $this->transientFaculty = $faculty;
    $this->transientDepartment = $department;
    $this->transientLevel = $level;
    return $this->state(fn () => ['role' => 'student']);
}
```
 
### 7.3 `configure()` — Single Authoritative Profile-Creation Hook
 
**Design rule:** exactly one `afterCreating()` hook exists, registered once in `configure()`. State methods never register their own creation hooks — they only stash parameters as transient instance properties. This avoids duplicate-insert races between competing hooks (a bug caught and corrected during design — see project history).
 
```
afterCreating(user):
    IF user.role == 'staff':
        CREATE StaffProfile { user_id, staff_id: generate_unique(), admin_level: transientAdminLevel }
 
    ELSE IF user.role == 'student':
        // --- Resolve Faculty / Department / Programme (most-specific-wins) ---
        RESOLVED_LEVEL = transientLevel ?? RANDOM_ELEMENT(['100','200','300','400'])
        ALLOWED_DEGREE_TYPES = Programme::UNDERGRADUATE_DEGREE_TYPES   // shared constant, see §5.4 — never re-typed locally
 
        IF transientDepartment set:
            RESOLVED_DEPT = transientDepartment
            RESOLVED_FACULTY = RESOLVED_DEPT.faculty
        ELSE IF transientFaculty set:
            RESOLVED_FACULTY = transientFaculty
            RESOLVED_DEPT = RANDOM_RECORD_FROM(Departments WHERE faculty_id = RESOLVED_FACULTY.id)
            IF null: THROW RuntimeException("No Department found for Faculty '{name}'.")
        ELSE:
            RESOLVED_FACULTY = RANDOM_RECORD_FROM(Faculties)
            IF null: THROW RuntimeException("No Faculties found. Run FacultySeeder first.")
            RESOLVED_DEPT = RANDOM_RECORD_FROM(Departments WHERE faculty_id = RESOLVED_FACULTY.id)
            IF null: THROW RuntimeException("No Department found for randomly selected Faculty '{name}'.")
 
        RESOLVED_PROG = RANDOM_RECORD_FROM(
            Programmes WHERE department_id = RESOLVED_DEPT.id AND degree_type IN ALLOWED_DEGREE_TYPES
        )
        IF null: THROW RuntimeException("No undergraduate Programme found for Department '{name}'.")
 
        // --- Matricule Generation ---
        FACULTY_CODE = UPPERCASE(RESOLVED_FACULTY.matricule_prefix)   // NEVER .code
        SECTION_LETTER = "A"   // hardcoded — registrar-owned business rule, out of scope
        LEVEL_INT = CONVERT_TO_INT(RESOLVED_LEVEL)
        YEARS_SPENT = (LEVEL_INT / 100) - 1
        ENROLLMENT_YEAR_STR = SUBSTRING(STR(2026 - YEARS_SPENT), -2)
        PREFIX_MATCH = FACULTY_CODE + ENROLLMENT_YEAR_STR + SECTION_LETTER
 
        LAST = QUERY(StudentProfiles).WHERE(matricule LIKE PREFIX_MATCH + '%').ORDER_BY(matricule DESC).FIRST()
        NEXT_SEQ = LAST ? (INT(SUBSTRING(LAST.matricule, -3)) + 1) : 1
        MATRICULE = PREFIX_MATCH + PAD_LEFT(NEXT_SEQ, 3, '0')
 
        CREATE StudentProfile {
            user_id, faculty_id: RESOLVED_FACULTY.id, department_id: RESOLVED_DEPT.id,
            programme_id: RESOLVED_PROG.id, level: RESOLVED_LEVEL, matricule: MATRICULE
        }
```
 
### 7.4 `StudentDistributionHelper`
 
**Location:** `database/seeders/Support/StudentDistributionHelper.php`
**Responsibility:** Given a count `$n`, return an array of `$n` level assignments cycling through the four undergraduate levels. Deliberately narrow — knows nothing about departments, faculties, or `User`/`StudentProfile` creation. Static-only; no state or dependency to justify instantiation.
 
```php
namespace Database\Seeders\Support;
 
class StudentDistributionHelper
{
    private const LEVELS = ['100', '200', '300', '400'];
 
    /**
     * Returns an array of $n level assignments, cycling through LEVELS.
     * Exact evenness is not guaranteed when $n is not a multiple of count(LEVELS).
     */
    public static function distribute(int $n): array
    {
        $levels = [];
 
        for ($i = 0; $i < $n; $i++) {
            $levels[] = self::LEVELS[$i % count(self::LEVELS)];
        }
 
        return $levels;
    }
}
```
 
**Design rule:** the caller (`StudentSeeder`, §7.5) owns pairing each returned level with a specific department and actually creating records. This class only ever answers "given N, what's the level for the i-th student."
 
### 7.5 `StudentSeeder`
 
**Location:** `database/seeders/StudentSeeder.php`
 
**Responsibilities:**
- Determine which departments are eligible to receive seeded students (must have at least one `UNDERGRADUATE_DEGREE_TYPES`-classified programme).
- For each eligible department, use `StudentDistributionHelper` to get a level spread, then create one `User::factory()->student(...)` per level.
**Eligibility filter — single query, no N+1:**
 
```php
use App\Models\Department;
use App\Models\Programme;
use Illuminate\Database\Eloquent\Builder;
 
$departments = Department::whereHas('programmes', function (Builder $query) {
    $query->whereIn('degree_type', Programme::UNDERGRADUATE_DEGREE_TYPES);
})->get();
```
 
Uses `Programme::UNDERGRADUATE_DEGREE_TYPES` directly (§5.4) — never a locally re-typed array, since the two lists (this filter, and `UserFactory`'s `ALLOWED_DEGREE_TYPES`) must always match exactly. If they diverge, a department can pass this filter yet still cause `UserFactory` to throw, since `UserFactory` queries eligible programmes independently per student at creation time.
 
**Constructor:** takes `$studentsPerDepartment` (the "N students per department" constant) as a constructor property — consistent with how `$parsedFaculties` is injected into the Tier 0–1 seeders — rather than a hardcoded class constant.
 
**Transaction:** the full loop is wrapped in `DB::transaction()`. Unlike `StudentDistributionHelper` (a pure function, no DB interaction, no transaction needed), `StudentSeeder::run()` performs real inserts via `User::factory()->create()` — a mid-loop failure (e.g. a `RuntimeException` from `UserFactory` if a department unexpectedly has no eligible programme at student-creation time) should not leave a partially-seeded set of students committed.
 
**Transaction isolation — verified, not a risk:** `StudentSeeder`'s wrapping `DB::transaction()` raises the question of whether a student created mid-loop is visible to the matricule-sequence lookup for the *next* student created in the same loop. This has been explicitly traced through and confirmed safe:
 
- The relevant distinction is between (a) a transaction reading its **own** prior uncommitted writes, versus (b) two **concurrent** transactions reading each other's uncommitted writes. Only (a) applies here — `StudentSeeder` runs as a single sequential process on a single database connection inside a single open transaction; nothing else runs concurrently against the same rows during a seed run.
- Isolation levels (`READ COMMITTED`, `REPEATABLE READ`, `SERIALIZABLE`, etc.) govern visibility **between separate transactions** — none of them prevent a transaction from seeing its own prior writes within that same transaction. This is a basic guarantee of any ACID-compliant relational database, not something isolation level can override.
- Concretely: Student #1 is inserted with `matricule = 'FE26A001'` inside the open transaction. Student #2's `UserFactory` hook then runs `SELECT ... WHERE matricule LIKE 'FE26A%' ORDER BY matricule DESC` on the same connection, in the same transaction — this reliably sees `FE26A001` and correctly assigns `FE26A002`.
- The scenario where isolation level *would* matter — two separate `db:seed` processes running concurrently, each racing to claim the same next sequence number — does not apply to CampusDesk's seeding workflow, since seeders are run as single sequential admin scripts, never invoked concurrently from multiple processes.
**Conclusion:** no race condition or stale-read risk exists in `StudentSeeder`'s current design. This reasoning is distinct from the staff pickup-and-lock mechanic elsewhere in CampusDesk, where genuinely concurrent HTTP requests from different staff members *do* hit the same rows simultaneously — which is why that mechanic correctly requires an atomic `UPDATE ... WHERE` guard, a protection `StudentSeeder` does not need.
 
**Full implementation:** not yet written — see §8.
 
### 7.6 Accepted Trade-offs
 
| Limitation | Rationale |
|---|---|
| Section letter hardcoded `"A"` | Undergrad/postgrad section assignment is registrar-owned; not a seeding concern at current volumes. |
| Matricule sequence capped at 999 per faculty/year/section bucket | Accepted — seeding volumes not expected to approach this. |
| `L500`/`L600` levels use the same linear year-math as `L100`-`L400` | Approximation for extended-duration students; considered acceptable for seed realism. |
| Default `User::factory()->create()` (no state) → `role: student`, auto-creates a `StudentProfile` | Deliberate default reflecting real user distribution — not an oversight. |
 
---
 
## 8. `department_staff` (Tier 3)
 
### 8.1 Prerequisite — `StaffSeeder`
 
Tier 2 as originally specified only creates students (`StudentSeeder`, §7.5). Assigning departmental staff requires `staff`-role users to already exist, so a `StaffSeeder` must run before `DepartmentStaffSeeder`.
 
**Decisions:**
- Staff are seeded as a **flat total pool**, not a per-department count — `DepartmentStaffSeeder` (§8.3) is solely responsible for distributing that pool across departments.
- No `super_admin` staff are seeded. Every staff member created by `StaffSeeder` starts as plain staff (`admin_level: null`/baseline). `super_admin` accounts, if needed for testing, are created manually via Tinker — considered out of scope for automated seeding, consistent with other deliberately-deferred concerns (e.g. postgraduate levels, section-letter variation).
- `dept_admin` is **not** assigned at `StaffSeeder` time — it is assigned later, in `DepartmentStaffSeeder`, at the moment a staff member is selected as a department's primary (§8.4).
**Full `StaffSeeder` implementation:** not yet written — structurally parallel to `StudentSeeder` (constructor-injected total count, `DB::transaction()`-wrapped loop calling `User::factory()->staff()->create()`), but not yet specified in detail.
 
### 8.2 Model Fixes Required
 
**Location:** `app/Models/Department.php`
 
`staffProfiles()` must explicitly declare the pivot table name (Laravel's default alphabetical-pluralization convention would otherwise guess `department_staff_profile`, not the actual `department_staff` table) and expose the `is_primary` pivot attribute:
 
```php
public function staffProfiles(): BelongsToMany
{
    return $this->belongsToMany(StaffProfile::class, 'department_staff')
                ->withPivot('is_primary');
    // NOTE: no ->withTimestamps() — department_staff has no created_at/updated_at columns
    // (confirmed against the actual migration; calling withTimestamps() against a table
    // without those columns would fail on attach()).
}
```
 
**Location:** `app/Models/StaffProfile.php` — confirmed `admin_level` is present in `$fillable` (verified against the actual model; not assumed, given this project's recurring "$fillable omission causes silent discard" failure mode).
 
### 8.3 `admin_level` / `is_primary` Coupling Decision
 
`staff_profiles.admin_level` is a global property of the staff member; `department_staff.is_primary` is scoped per department-assignment row. These are structurally independent — a schema gap that could theoretically let a staff member carry a stale `dept_admin` title into a department they're only a secondary staff member for. Accepted as out-of-scope for seeding purposes (a real schema-design conversation for another day), on the basis that seeded staff are constrained to at most one department assignment (§8.4), making the gap unreachable in practice.
 
**Decision:** for seeding, `admin_level` is set to `'dept_admin'` at the exact moment a staff member is chosen as a department's primary — inline within `DepartmentStaffSeeder`'s Pass 1 (§8.4), not via `UserFactory`/`StaffSeeder`. This keeps "primary for this department" and "carries the dept_admin title" atomic within the seeding logic, even though the schema itself does not enforce that coupling.
 
### 8.4 `DepartmentStaffSeeder`
 
**Location:** `database/seeders/DepartmentStaffSeeder.php`
 
**Cardinality constraint (confirmed from migration):** `department_staff` has a unique constraint on `(staff_profile_id, department_id)` — the database itself prevents the same staff member from being assigned to the same department twice.
 
**Design decision — multi-department-primary staff is explicitly disallowed.** A staff member is never primary for more than one department. This was deliberately chosen over the alternative (allowing overflow when staff < departments) because multi-department-primary would: (a) not reflect a real UB business rule, purely a seeding artifact; (b) risk masking bugs in department-scoping tests, since a staff member "shouldn't have access to department Y" tests could pass for the wrong reason if that staff member legitimately does have access via a second primary assignment; (c) introduce non-determinism in which departments get "doubled up." Instead, a **fail-fast guardrail** is used.
 
**Guardrail:** `StaffSeeder`'s total count must always be ≥ total department count. Enforced at runtime, not just by picking "a safely large number," since a hardcoded total can silently fall out of sync if either count changes later (departments added, staff total tuned down for a faster local run).
 
**Two-pass assignment:**
1. **Pass 1 (primary guarantee):** iterate **departments** as the outer loop (not staff) — this is what structurally guarantees every department gets covered, rather than hoping random assignment happens to reach every department. For each department, pop one staff member from a shuffled pool and attach as `is_primary: true`; set that staff member's `admin_level` to `'dept_admin'` in the same step.
2. **Pass 2 (secondary distribution):** drain the remaining shuffled pool — each leftover staff member (never touched in Pass 1) is popped exactly once and attached to one random department as `is_primary: false`. Because Pass 1 and Pass 2 draw from a strictly partitioned pool (a staff member is popped by exactly one pass, never reconsidered), no duplicate-pair guard is needed — structural uniqueness is guaranteed by the pop-once mechanic itself, not by a lookup set.
**Transaction:** wrapped in `DB::transaction()`, consistent with `FacultySeeder`/`StudentSeeder` — a mid-loop failure (e.g. the guardrail exception, or any other exception at, say, department #30 of 50) must not leave a partially-seeded `department_staff` table, since re-running `db:seed` against partial state would hit unique/foreign-key constraint errors rather than cleanly re-seeding.
 
```php
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
            $departments = Department::all();
            $staffCount = StaffProfile::count();
            $departmentCount = $departments->count();
 
            // Fail-fast guardrail
            if ($staffCount < $departmentCount) {
                throw new RuntimeException(
                    "Cannot guarantee one primary staff per department: {$staffCount} staff exist, but " .
                    "{$departmentCount} departments require assignment. Increase StaffSeeder's total count."
                );
            }
 
            $staffPool = StaffProfile::all()->shuffle();
 
            // --- PASS 1: Guarantee 1 primary staff per department ---
            foreach ($departments as $department) {
                $primaryStaff = $staffPool->pop();
 
                $department->staffProfiles()->attach($primaryStaff->id, [
                    'is_primary' => true,
                ]);
 
                $primaryStaff->update(['admin_level' => 'dept_admin']);
            }
 
            // --- PASS 2: Distribute remaining staff as secondary (one assignment each) ---
            while ($staffPool->isNotEmpty()) {
                $secondaryStaff = $staffPool->pop();
                $randomDepartment = $departments->random();
 
                $randomDepartment->staffProfiles()->attach($secondaryStaff->id, [
                    'is_primary' => false,
                ]);
            }
        });
    }
}
```
 
---
 
## 9. Open Items — Not Yet Specified
 
The following are decided **in principle** but have no finalized code yet. Do not assume behavior beyond what's stated.
 
| Item | Decision made | Still needed |
|---|---|---|
| **`StaffSeeder` full implementation** | Design decided in §8.1 (flat total pool, no `super_admin`, `dept_admin` NOT set at this stage) | Actual code has not yet been written; total staff count must be chosen with §8.4's guardrail in mind (≥ total department count). |
| **`StudentSeeder::run()` full implementation** | Design fully specified and verified in §7.5 (eligibility filter, transaction, constructor property, distribution helper usage, transaction-isolation reasoning confirmed safe) | Actual code has not yet been written. |
| **Value of N (students per department)** | Passed as a constructor property on `StudentSeeder`, not hardcoded as a class constant | Actual numeric value to use has not yet been chosen. |
| **Requests & Stages (Tier 4–5)** | Hybrid `StageGenerationService` + `RequestObserver::created()` trigger, consistent with existing architecture | Confirm `Request::factory()->create()` alone is sufficient to trigger realistic stage generation; design a plan for seeding requests across varied stage-completion states, not just freshly-generated ones. |
| **`status_history`, `attachments`, `notifications` (Tier 6)** | Not discussed in detail | Full design pending Tier 4–5 completion. |
| **`ProgrammeClassifier` class location** | Logic finalized (§5.2) | Formal class/file placement not yet decided. |
| **`DatabaseSeeder::run()` — full tier orchestration** | Tier 0–3 call order and wiring pattern established (§6.4 shows Tier 0–1; Tier 2–3 follow the same manual-instantiation + `setContainer()`/`setCommand()` pattern) | `DatabaseSeeder::run()` has not yet been updated to actually include `StaffSeeder`, `StudentSeeder`, and `DepartmentStaffSeeder` calls. |
 
---
 
## 9. Next Steps
 
1. Implement `FacultyMarkdownParser`, `FacultyMatriculeMapper`, and the three Tier 0–1 seeders exactly as specified in §4–§6; run against real data; resolve any `departments.code` duplicate-key collisions if they occur.
2. Resolve the MASTER/PHD filtering question in §8 before finalizing `ProgrammeSeeder`.
3. Design and implement the Tier 2 distribution helper and finalize `UserFactory` end-to-end.
4. Design Tier 3 (`department_staff`) primary-assignment seeder.
5. Design Tier 4–5 (requests/stages) seeding strategy.
