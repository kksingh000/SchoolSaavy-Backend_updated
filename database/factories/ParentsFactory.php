<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Parents>
 */
class ParentsFactory extends Factory
{
    protected $model = \App\Models\Parents::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'phone' => fake()->phoneNumber,
            'alternate_phone' => fake()->optional()->phoneNumber,
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'occupation' => fake()->jobTitle,
            'profile_photo' => fake()->optional()->imageUrl(200, 200, 'people'),
            'address' => fake()->address,
            'relationship' => fake()->randomElement(['father', 'mother', 'guardian']),
        ];
    }
}
