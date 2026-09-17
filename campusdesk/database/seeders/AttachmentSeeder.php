<?php

namespace Database\Seeders;

use App\Models\Request;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class AttachmentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Request::orderBy('id')->take(3)->get() as $request) {
            $contents = "Seeded attachment for request {$request->id}.\n";
            $path = "attachments/seeded/request-{$request->id}.txt";
            Storage::disk('local')->put($path, $contents);
            $request->attachments()->firstOrCreate(['file_path' => $path], [
                'original_name' => "seeded-request-{$request->id}.txt",
                'mime_type' => 'text/plain', 'file_size' => strlen($contents),
            ]);
        }
    }
}
