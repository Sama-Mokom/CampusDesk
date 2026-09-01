<?php

namespace Database\Seeders\Support;

use Illuminate\Console\Command;

class FacultyMarkdownParser
{
    // Codes contain hyphens and forward-slashes (e.g. PUL-PUA, GS/PB, MBA-ACC).
    // The separator \s+-\s+ is unambiguous regardless of code content because it is
    // anchored by surrounding spaces.
    private const FACULTY_PATTERN    = '/^##\s+(?P<name>.+?)\s+-\s+(?P<code>[A-Z0-9_\/\-]+)$/';
    private const DEPARTMENT_PATTERN = '/^###\s+(?P<name>.+?)\s+-\s+(?P<code>[A-Z0-9_\/\-]+)$/';

    // Programme name group is greedy (.+, not .+?) — required to correctly resolve
    // lines with internal, unspaced dashes like "B.Eng in Electrical and Electronic
    // Engineering (Duration One Year) - EEN".
    private const PROGRAMME_PATTERN  = '/^\d+\.\s+(?P<name>.+)\s+-\s+(?P<code>[A-Z0-9_\/\-]+)$/';

    /**
     * Parse the content of university_programs_structure.md into a nested array.
     *
     * @param  string        $fileContent  Raw file content.
     * @param  Command|null  $command      Optional command instance for console warnings.
     *                                    Nullable so the parser is callable outside a seeder
     *                                    (Tinker, tests). Warnings go to visible console output —
     *                                    never Log::warning() (project convention).
     * @return array<int, array{name: string, code: string, departments: array}>
     */
    public function parse(string $fileContent, ?Command $command = null): array
    {
        $faculties         = [];
        $currentFaculty    = null;
        $currentDepartment = null;

        $lines = explode("\n", $fileContent);

        foreach ($lines as $lineNumber => $rawLine) {
            $line = trim($rawLine);

            // Skip blank lines, horizontal rules, and H1 title lines (# ...).
            // The source file has a "# University Faculties..." title at line 1
            // that is neither a faculty (##) nor anything parseable — silently skip it.
            if ($line === '' || $line === '---' || str_starts_with($line, '# ')) {
                continue;
            }

            // --- Faculty heading (## ...) ---
            if (preg_match(self::FACULTY_PATTERN, $line, $matches)) {
                // Flush pending department into its faculty
                if ($currentDepartment !== null && $currentFaculty !== null) {
                    $currentFaculty['departments'][] = $currentDepartment;
                    $currentDepartment = null;
                }
                // Flush pending faculty into the master array
                if ($currentFaculty !== null) {
                    $faculties[] = $currentFaculty;
                }
                $currentFaculty = [
                    'name'        => trim($matches['name']),
                    'code'        => trim($matches['code']),
                    'departments' => [],
                ];
                continue;
            }

            // --- Department heading (### ...) ---
            if (preg_match(self::DEPARTMENT_PATTERN, $line, $matches)) {
                if ($currentFaculty === null) {
                    $command?->warn(
                        "Line " . ($lineNumber + 1) . ": Department before Faculty context — skipping: \"{$line}\""
                    );
                    continue;
                }
                // Flush pending department into current faculty
                if ($currentDepartment !== null) {
                    $currentFaculty['departments'][] = $currentDepartment;
                }
                $currentDepartment = [
                    'name'       => trim($matches['name']),
                    'code'       => trim($matches['code']),
                    'programmes' => [],
                ];
                continue;
            }

            // --- Programme line (N. Name - CODE) ---
            if (preg_match(self::PROGRAMME_PATTERN, $line, $matches)) {
                if ($currentDepartment === null) {
                    $command?->warn(
                        "Line " . ($lineNumber + 1) . ": Programme outside Department context — skipping: \"{$line}\""
                    );
                    continue;
                }
                $currentDepartment['programmes'][] = [
                    'name' => trim($matches['name']),
                    'code' => trim($matches['code']),
                ];
                continue;
            }

            // Unrecognized line — warn but continue
            $command?->warn("Line " . ($lineNumber + 1) . ": Unrecognized line structure: \"{$line}\"");
        }

        // EOF flush
        if ($currentDepartment !== null && $currentFaculty !== null) {
            $currentFaculty['departments'][] = $currentDepartment;
        }
        if ($currentFaculty !== null) {
            $faculties[] = $currentFaculty;
        }

        return $faculties;
    }
}
