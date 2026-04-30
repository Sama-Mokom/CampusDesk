<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Faculty;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faculty = Faculty::where('code', 'FET')->first();
        //
        Department::create([
            'faculty_id' => $faculty->id,
            'name' => 'Computer Engineering',
            'code' => 'CE',
        ]);

        Department::create([
            'faculty_id' => $faculty->id,
            'name' => 'Registrar\'s Office',
            'code' => 'REG',
        ]);

        Department::create([
            'faculty_id' => $faculty->id,
            'name' => 'Electrical Engineering',
            'code' => 'EE',
        ]);

        Department::create([
            'faculty_id' => $faculty->id,
            'name' => 'Mechanical Engineering',
            'code' => 'MEC',
        ]);

        Department::create([
            'faculty_id' => $faculty->id,
            'name' => 'Civil Engineering',
            'code' => 'CIV',
        ]);

        Department::create([
            'faculty_id' => $faculty->id,
            'name' => 'Chemical Engineering',
            'code' => 'CHE',
        ]);
    }
}
