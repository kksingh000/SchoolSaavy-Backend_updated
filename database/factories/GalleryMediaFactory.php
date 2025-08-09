<?php

namespace Database\Factories;

use App\Models\GalleryMedia;
use App\Models\GalleryAlbum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GalleryMediaFactory extends Factory
{
    protected $model = GalleryMedia::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['photo', 'video']);
        $isPhoto = $type === 'photo';

        return [
            'album_id' => GalleryAlbum::factory(),
            'uploaded_by' => User::factory(),
            'type' => $type,
            'title' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'file_path' => $isPhoto
                ? 'gallery/photos/' . $this->faker->uuid() . '.jpg'
                : 'gallery/videos/' . $this->faker->uuid() . '.mp4',
            'file_name' => $isPhoto
                ? $this->faker->word() . '.jpg'
                : $this->faker->word() . '.mp4',
            'mime_type' => $isPhoto ? 'image/jpeg' : 'video/mp4',
            'file_size' => $isPhoto
                ? $this->faker->numberBetween(100000, 5000000) // 100KB to 5MB
                : $this->faker->numberBetween(1000000, 50000000), // 1MB to 50MB
            'thumbnail_path' => $isPhoto ? null : 'gallery/thumbnails/' . $this->faker->uuid() . '_thumb.jpg',
            'metadata' => $isPhoto
                ? [
                    'dimensions' => [
                        'width' => $this->faker->numberBetween(800, 4000),
                        'height' => $this->faker->numberBetween(600, 3000)
                    ]
                ]
                : [
                    'duration' => $this->faker->numberBetween(30, 600), // 30 seconds to 10 minutes
                    'dimensions' => [
                        'width' => $this->faker->randomElement([1920, 1280, 720]),
                        'height' => $this->faker->randomElement([1080, 720, 480])
                    ]
                ],
            'views_count' => $this->faker->numberBetween(0, 100),
            'downloads_count' => $this->faker->numberBetween(0, 20),
            'sort_order' => $this->faker->numberBetween(1, 100),
            'is_featured' => $this->faker->boolean(20), // 20% chance of being featured
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];
    }

    public function photo(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'photo',
            'file_path' => 'gallery/photos/' . $this->faker->uuid() . '.jpg',
            'file_name' => $this->faker->word() . '.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => $this->faker->numberBetween(100000, 5000000),
            'thumbnail_path' => null,
            'metadata' => [
                'dimensions' => [
                    'width' => $this->faker->numberBetween(800, 4000),
                    'height' => $this->faker->numberBetween(600, 3000)
                ]
            ],
        ]);
    }

    public function video(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'video',
            'file_path' => 'gallery/videos/' . $this->faker->uuid() . '.mp4',
            'file_name' => $this->faker->word() . '.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => $this->faker->numberBetween(1000000, 50000000),
            'thumbnail_path' => 'gallery/thumbnails/' . $this->faker->uuid() . '_thumb.jpg',
            'metadata' => [
                'duration' => $this->faker->numberBetween(30, 600),
                'dimensions' => [
                    'width' => $this->faker->randomElement([1920, 1280, 720]),
                    'height' => $this->faker->randomElement([1080, 720, 480])
                ]
            ],
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
        ]);
    }
}
