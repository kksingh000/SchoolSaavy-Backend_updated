<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassRoom>
 */
class ClassRoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => \App\Models\School::factory(),
            'name' => $this->faker->randomElement(['Grade 1', 'Grade 2', 'Grade 3', 'Nursery', 'KG']),
            'section' => $this->faker->randomElement(['A', 'B', 'C', 'D']),
            'grade_level' => $this->faker->numberBetween(0, 12),
            'capacity' => $this->faker->numberBetween(20, 50),
            'class_teacher_id' => null,
            'description' => $this->faker->optional()->sentence,
            'is_active' => true,
        ];
    }
}
