<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Programme;
use App\Models\Faculty;

class ProgrammeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faculty = Faculty::where('code', 'FET')->first();
        //
        Programme::create([
        'faculty_id' => $faculty->id,
        'name' => 'BEng Computer Engineering',
        'code' => 'BENG-CE',
        'degree_type' => 'BEng',
    ]);

    Programme::create([
        'faculty_id' => $faculty->id,
        'name' => 'BEng Electricsl Engineering',
        'code' => 'BENG-EE',
        'degree_type' => 'BEng',
    ]);

    Programme::create([
        'faculty_id' => $faculty->id,
        'name' => 'BEng Civil Engineering',
        'code' => 'BENG-CIV',
        'degree_type' => 'BEng',
    ]);
    }
}
