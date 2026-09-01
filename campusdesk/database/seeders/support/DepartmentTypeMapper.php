<?php

namespace Database\Seeders\Support;

class DepartmentTypeMapper
{
    /**
     * Real Records departments present in university_faculties_and_programs.md but
     * ABSENT from university_programs_structure.md (the file actually parsed — see §1).
     * Currently matches zero rows in real parsed data; retained in case the source
     * file is corrected later.
     */
    private const RECORDS_DEPARTMENT_CODES = [
        'REC-COT', 'REF', 'RECFHS', 'RCS', 'RECSMS',
    ];

    /**
     * Synthetic sub-departments of the "Records Office" faculty (code: RO), appended
     * directly to the source markdown. Route document requests internally by request
     * type; never assigned students.
     */
    private const ADMIN_DEPARTMENT_CODES = [
        'TRD', 'AOE', 'AOC',
    ];

    /**
     * Resolve the departments.type value for a given department code.
     * Returns 'records', 'admin', or 'academic'.
     *
     * Codes matching the pattern REC-{FACULTY_CODE} (e.g. REC-FS, REC-FET)
     * are the per-faculty secretariat/records offices seeded by DepartmentSeeder
     * and always resolve to 'records'.
     */
    public static function resolveType(string $code): string
    {
        $normalized = strtoupper($code);

        if (in_array($normalized, self::RECORDS_DEPARTMENT_CODES, true)) {
            return 'records';
        }

        // Per-faculty records offices follow the pattern REC-{anything}
        if (str_starts_with($normalized, 'REC-')) {
            return 'records';
        }

        if (in_array($normalized, self::ADMIN_DEPARTMENT_CODES, true)) {
            return 'admin';
        }

        return 'academic';
    }
}
