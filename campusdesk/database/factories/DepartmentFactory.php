<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'type' => 'academic',
        ];
    }

    public function records(): static
    {
        return $this->state(fn () => ['type' => 'records']);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['type' => 'admin']);
    }
}
