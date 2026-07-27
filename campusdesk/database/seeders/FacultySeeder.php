<?php

namespace Database\Seeders;

use App\Models\Faculty;
use Database\Seeders\Support\FacultyMatriculeMapper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FacultySeeder extends Seeder
{
    public function __construct(private array $parsedFaculties) {}

    public function run(): void
    {
        DB::transaction(function () {
            foreach ($this->parsedFaculties as $parsedFaculty) {
                // Throws InvalidArgumentException immediately if code is unmapped —
                // halts the run rather than silently producing wrong matricules.
                $prefix = FacultyMatriculeMapper::getPrefix($parsedFaculty['code']);

                Faculty::updateOrCreate(
                    ['code' => $parsedFaculty['code']],
                    [
                        'name'             => $parsedFaculty['name'],
                        'matricule_prefix' => $prefix,
                    ]
                );
            }
        });
    }
}
