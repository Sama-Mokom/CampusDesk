<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (User::where('role', 'student')->orderBy('id')->take(4)->get()->values() as $index => $student) {
            $student->notifications()->firstOrCreate(
                ['type' => 'seeded_demo', 'message' => 'Seeded dashboard notification #'.($index + 1).'.'],
                ['read' => $index % 2 === 1, 'read_at' => $index % 2 === 1 ? now() : null],
            );
        }
    }
}
