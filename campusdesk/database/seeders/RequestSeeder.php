<?php

namespace Database\Seeders;

use App\Models\RequestType;
use App\Models\User;
use App\Services\RequestCreationService;
use App\Services\SeedRequestProgressionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RequestSeeder extends Seeder
{
    public function __construct(private readonly int $requestCount) {}

    public function run(): void
    {
        DB::transaction(function () {
            $students = User::where('role', 'student')->with('studentProfile')->orderBy('id')->get();
            $types = RequestType::orderBy('id')->get();
            if ($students->isEmpty() || $types->isEmpty()) {
                throw new RuntimeException('RequestSeeder requires seeded students and request types.');
            }

            $creator = app(RequestCreationService::class);
            $progression = app(SeedRequestProgressionService::class);
            $targets = ['pending', 'in_review', 'forwarded', 'ready', 'rejected', 'collected'];

            for ($index = 0; $index < $this->requestCount; $index++) {
                $request = $creator->createForStudent(
                    $students[$index % $students->count()],
                    $types[$index % $types->count()],
                    'Seeded request #'.($index + 1),
                );
                $progression->progress($request, $targets[$index % count($targets)]);
            }
        });
    }
}
