<?php

namespace App\Services;

use App\Models\Department;
use App\Models\StudentProfile;
use RuntimeException;

class StageGenerationService
{
    /**
     * Resolve a single entry from a request type's default_department_sequence
     * to a concrete department ID for the given student.
     *
     * Sequence entries are one of:
     *   'STUDENT_DEPARTMENT'  — the student's own department
     *   'FACULTY_RECORDS'     — the records-type department within the student's faculty
     *   int / numeric string  — a literal department ID (e.g. the Records Office sub-dept)
     *
     * Architecture: hybrid Service + Observer. RequestObserver::created() calls into
     * this service so stage generation fires identically regardless of entry point
     * (HTTP, Tinker, seeder).
     *
     * Caveat: the three-stage routing model (own department → faculty Records →
     * central Records Office, sub-routed by request type) is an approximation of
     * the University of Buea's real administrative process, not a verified fact.
     * Revisitable later.
     *
     * @throws RuntimeException  On an unresolvable placeholder or an invalid token.
     */
    public function resolveDepartmentId(mixed $entry, StudentProfile $student): int
    {
        return match (true) {
            $entry === 'STUDENT_DEPARTMENT' => $student->department_id,

            $entry === 'FACULTY_RECORDS' => (function () use ($student): int {
                // Uses $student->faculty_id directly (denormalized column already on the
                // student profile) — never $student->department->faculty_id (unnecessary
                // extra relationship traversal).
                $id = Department::where('faculty_id', $student->faculty_id)
                    ->where('type', 'records')
                    ->value('id');

                if ($id === null) {
                    throw new RuntimeException(
                        "Cannot resolve stage sequence: no 'records' department exists " .
                        "for Faculty ID {$student->faculty_id}."
                    );
                }

                return $id;
            })(),

            is_numeric($entry) => (int) $entry,

            default => throw new RuntimeException(
                "Invalid sequence token encountered: '{$entry}'."
            ),
        };
    }
}
