<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\RequestType;

class RequestTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        RequestType::create([
        'name' => 'Official Transcript',
        'description' => 'Official academic transcript for external use.',
        'default_department_sequence' => [1, 2],
    ]);
    }
}
