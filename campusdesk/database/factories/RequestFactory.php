<?php

namespace Database\Factories;

use App\Models\Request;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Request> */
class RequestFactory extends Factory
{
    protected $model = Request::class;

    public function definition(): array
    {
        return ['description' => fake()->sentence(), 'status' => 'pending', 'is_reopened' => false];
    }
}
