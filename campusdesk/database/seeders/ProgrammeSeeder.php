<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Programme;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProgrammeSeeder extends Seeder
{
    public function __construct(private array $parsedFaculties) {}

    public function run(): void
    {
        DB::transaction(function () {
            // Single query to build department code → id map; avoids N+1.
            $departmentIdMap = Department::pluck('id', 'code')->all();

            foreach ($this->parsedFaculties as $facultyData) {
                foreach ($facultyData['departments'] as $deptData) {
                    $departmentId = $departmentIdMap[$deptData['code']] ?? null;

                    if (!$departmentId) {
                        $this->command->warn(
                            "Skipping programmes for unmapped department: {$deptData['code']}"
                        );
                        continue;
                    }

                    foreach ($deptData['programmes'] as $progData) {
                        $degreeType = $this->matchDegreeType($progData['name']);

                        if ($degreeType === 'UNCLASSIFIED') {
                            $this->command->warn(
                                "Unclassified programme skipped: '{$progData['name']}'"
                            );
                            continue;
                        }

                        // MASTER/PHD rows ARE persisted — no filtering. They currently have
                        // no consumer (UserFactory never selects them), but an explicit filter
                        // was judged unnecessary for now.
                        // Keyed on (code, department_id, degree_type).
                        // The source data uses the same code for BSc and MSc variants
                        // of the same programme within the same department
                        // (e.g. code 'EFA' → B.Ed, M.Ed, Ph.D all in EFA dept;
                        // code 'EDL' → B.Ed and Masters in EFA dept).
                        // All three columns are required to uniquely identify a row.
                        Programme::updateOrCreate(
                            [
                                'code'          => $progData['code'],
                                'department_id' => $departmentId,
                                'degree_type'   => $degreeType,
                            ],
                            [
                                'name' => $progData['name'],
                            ]
                        );
                    }
                }
            }
        });
    }

    /**
     * Classify a programme name string into a degree_type enum value.
     *
     * Cleaning pipeline (order matters):
     *  1. Strip periods entirely — "M.A." → "MA", "B.Sc." → "BSc" then "BSC" after upper.
     *     Never replace with a space — "M.A." → "M A" breaks \bMA\b.
     *  2. Replace punctuation/symbols (comma, dash, brackets, etc.) with spaces BEFORE
     *     the collapse step, so "M,Sc" → "M Sc" and step 3 can join it to "MSc".
     *  3. Collapse "B Ed", "B Eng", "B Sc" patterns (single letter + space + word) that
     *     result from dot-stripping "B." — collapse to contiguous form so \bBED\b etc. match.
     *  4. Collapse multiple spaces to one, then uppercase.
     *
     * Check order: PHD before CERTIFICATE before MASTER before BACHELOR.
     * DIPLOMA/CERTIFICATE is checked before POSTGRADUATE so "Postgraduate Diploma" →
     * CERTIFICATE, not MASTER.
     *
     * Pattern improvements:
     *  - "Double Major in..." → BACHELOR (interdisciplinary undergraduate)
     *  - "Professional Minor in..." → BACHELOR (minor qualification)
     *  - "Certified Public..." → CERTIFICATE
     *  - Known COT vocational programmes (Hardware Maintenance, etc.) → CERTIFICATE
     *  - Invalid entries ("Void") → UNCLASSIFIED (filtered)
     *  - Bare discipline names without degree keywords remain UNCLASSIFIED (acceptable)
     */
    private function matchDegreeType(string $programmeString): string
    {
        // Filter invalid/placeholder entries immediately
        $normalized = trim($programmeString);
        if (in_array($normalized, ['Void', ''], true)) {
            return 'UNCLASSIFIED';
        }

        // Step 1: strip all periods
        $noDots = str_replace('.', '', $programmeString);

        // Step 2: replace punctuation with spaces BEFORE collapsing abbreviations,
        // so "M,Sc" → "M Sc" and the collapse regex can then join it to "MSc".
        $noPunct = str_replace([',', '-', '(', ')', '*', "'"], ' ', $noDots);

        // Step 3: collapse "B Ed", "B Eng", "B Sc", "B Tech", "B A", "M Ed", "M Sc",
        // "M Tech", "M Eng", "M A" — single-letter + space + word that were originally
        // "B." / "M." abbreviations. Must run BEFORE uppercasing to avoid false matches.
        $collapsed = preg_replace('/\b([BbMm])\s+([A-Za-z])/', '$1$2', $noPunct);

        // Step 4: collapse multiple spaces, uppercase
        $cleaned = strtoupper(preg_replace('/\s+/', ' ', trim($collapsed)));

        // -----------------------------------------------------------------------
        // PATTERN-BASED CLASSIFICATION (before keyword matching)
        // -----------------------------------------------------------------------

        // Double Major programmes → BACHELOR (interdisciplinary undergrad degrees)
        if (preg_match('/\bDOUBLE\s+MAJOR\b/i', $cleaned)) {
            return 'BACHELOR';
        }

        // Professional Minor → BACHELOR (minor-level undergraduate qualification)
        if (preg_match('/\bPROFESSIONAL\s+MINOR\b/i', $cleaned)) {
            return 'BACHELOR';
        }

        // "Certified Public..." → CERTIFICATE (e.g. CPA, Certified Public Accounting)
        if (preg_match('/\bCERTIFIED\s+PUBLIC\b/i', $cleaned)) {
            return 'CERTIFICATE';
        }

        // COT vocational/technician programmes (no degree keyword) → CERTIFICATE
        // These are short-course technical qualifications from College of Technology.
        $cotVocational = [
            'HARDWARE MAINTENANCE',
            'SOFTWARE ENGINEERING AND COMPUTING',
            'INFORMATION AND COMMUNICATION TECHNOLOGY',
            'AIR CONDITIONING AND REFRIGERATION',
            'ELECTRIC POWER SYSTEM',
            'TELECOMMUNICATION',
            'MECHANICAL FABRICATION',
            'STRUCTURAL & METALLIC CONSTRUCTION',
            'STRUCTURAL  METALLIC CONSTRUCTION',  // handle double-space variants
            'THERMOFLUIDS ENGINEERING',
            'THERMO FLUIDS ENGINEERING',
            'WELDING TECHNOLOGY',
        ];
        if (in_array($cleaned, $cotVocational, true)) {
            return 'CERTIFICATE';
        }

        // -----------------------------------------------------------------------
        // KEYWORD-BASED CLASSIFICATION (standard degree detection)
        // -----------------------------------------------------------------------

        // PHD: checked first — "Doctor of X", "Resident in X", "Ph.D" variants
        if (preg_match('/\b(PHD|RESIDENT|DOCTOR OF)\b/', $cleaned)) {
            return 'PHD';
        }

        // CERTIFICATE: checked before MASTER so "Postgraduate Diploma" → CERTIFICATE
        // and "Professional Higher Education Certificate" → CERTIFICATE not MASTER
        if (preg_match('/\b(DIPLOMA|CERTIFICATE|HIGHER CERTIFICATE)\b/', $cleaned)) {
            return 'CERTIFICATE';
        }

        // MASTER: MA, MSC, MTECH, MENG, MED (M.Ed), LLM, MBA, MASTER/MASTERS, POSTGRADUATE
        // Also catches "M,Sc" → after comma→space and collapse: "MSC"
        if (preg_match('/\b(MA|MSC|MTECH|MENG|MED|LLM|MBA|MASTER|MASTERS|POSTGRADUATE)\b/', $cleaned)) {
            return 'MASTER';
        }

        // BACHELOR: BA, BSC, BTECH, BENG, BED, LLB, BBA, BACHELOR, BARRISTER
        // "B. Ed" → after step 3: "BEd" → after uppercase: "BED" ✓
        // "B. Eng" → after step 3: "BEng" → "BENG" ✓
        if (preg_match('/\b(BA|BSC|BTECH|BENG|BED|LLB|BBA|BACHELOR|BARRISTER)\b/', $cleaned)) {
            return 'BACHELOR';
        }

        // -----------------------------------------------------------------------
        // UNCLASSIFIED (bare discipline names with no degree keyword)
        // Acceptable: Soil Science, Francophone Literatures, Animal Production, etc.
        // These cannot be classified from the name alone and require faculty context
        // or manual review. The seeder correctly skips them.
        // -----------------------------------------------------------------------
        return 'UNCLASSIFIED';
    }
}
