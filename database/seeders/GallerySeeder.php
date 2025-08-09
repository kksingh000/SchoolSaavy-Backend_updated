<?php

namespace Database\Seeders;

use App\Models\GalleryAlbum;
use App\Models\GalleryMedia;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GallerySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schoolId = 7;
        $classIds = [66, 67, 68, 69, 70, 71, 72]; // Multiple class IDs starting from 66

        // Get a teacher from school 7 and then get their user
        $teacher = \App\Models\Teacher::where('school_id', $schoolId)->first();
        if (!$teacher) {
            $this->command->warn('No teachers found for school ID 7. Please ensure teachers exist before running this seeder.');
            return;
        }

        $creator = $teacher->user;
        if (!$creator) {
            $this->command->warn('No user found for teacher in school ID 7. Please ensure teacher has a user record.');
            return;
        }

        // Use database transaction to ensure data consistency
        DB::transaction(function () use ($schoolId, $classIds, $creator) {
            $this->createGalleryData($schoolId, $classIds, $creator);
        });

        $this->command->info('Gallery seeder completed successfully with database transaction!');
    }

    /**
     * Create gallery data within transaction
     */
    private function createGalleryData($schoolId, $classIds, $creator)
    {

        $albums = [
            [
                'title' => 'Independence Day Celebration 2025',
                'description' => 'Students performing various cultural activities and flag hoisting ceremony',
                'class_id' => 66,
                'event_id' => null,
                'event_date' => '2025-08-15',
                'photos' => [
                    [
                        'title' => 'Flag Hoisting Ceremony',
                        'description' => 'Students and teachers participating in flag hoisting',
                        'url' => 'https://images.unsplash.com/photo-1628191081028-a4e5f59c6d56?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Cultural Dance Performance',
                        'description' => 'Students performing traditional Indian dance',
                        'url' => 'https://images.unsplash.com/photo-1594736797933-d0401ba2fe65?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Patriotic Song Competition',
                        'description' => 'Students singing patriotic songs',
                        'url' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Prize Distribution',
                        'description' => 'Winners receiving prizes from principal',
                        'url' => 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=800&h=600&fit=crop'
                    ]
                ],
                'videos' => [
                    [
                        'title' => 'Independence Day Highlights',
                        'description' => 'Complete highlights of Independence Day celebration',
                        'url' => 'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4'
                    ]
                ]
            ],
            [
                'title' => 'Annual Sports Day 2025',
                'description' => 'Inter-house sports competition and athletic events',
                'class_id' => 67,
                'event_id' => null,
                'event_date' => '2025-07-25',
                'photos' => [
                    [
                        'title' => 'Opening Ceremony',
                        'description' => 'Students marching in the opening parade',
                        'url' => 'https://images.unsplash.com/photo-1578662996442-48f60103fc96?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => '100m Sprint Race',
                        'description' => 'Students competing in 100m sprint',
                        'url' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Long Jump Competition',
                        'description' => 'Student performing long jump',
                        'url' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Relay Race',
                        'description' => 'Inter-house relay race competition',
                        'url' => 'https://images.unsplash.com/photo-1594736797933-d0401ba2fe65?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Victory Ceremony',
                        'description' => 'Winners receiving medals and trophies',
                        'url' => 'https://images.unsplash.com/photo-1593079831268-3381b0db4a77?w=800&h=600&fit=crop'
                    ]
                ],
                'videos' => [
                    [
                        'title' => 'Sports Day Compilation',
                        'description' => 'Best moments from sports day events',
                        'url' => 'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_2mb.mp4'
                    ]
                ]
            ],
            [
                'title' => 'Science Exhibition 2025',
                'description' => 'Students showcasing innovative science projects and experiments',
                'class_id' => 68,
                'event_id' => null,
                'event_date' => '2025-06-15',
                'photos' => [
                    [
                        'title' => 'Solar System Model',
                        'description' => 'Student explaining solar system working model',
                        'url' => 'https://images.unsplash.com/photo-1446776877081-d282a0f896e2?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Chemistry Experiments',
                        'description' => 'Students demonstrating chemical reactions',
                        'url' => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Robotics Project',
                        'description' => 'Student-built robot demonstration',
                        'url' => 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Physics Models',
                        'description' => 'Various physics working models display',
                        'url' => 'https://images.unsplash.com/photo-1636466497217-26a8cbeaf0aa?w=800&h=600&fit=crop'
                    ]
                ],
                'videos' => [
                    [
                        'title' => 'Science Fair Highlights',
                        'description' => 'Best projects from science exhibition',
                        'url' => 'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4'
                    ]
                ]
            ],
            [
                'title' => 'Cultural Fest - Kaleidoscope 2025',
                'description' => 'Annual cultural festival featuring music, dance, and drama performances',
                'class_id' => 69,
                'event_id' => null,
                'event_date' => '2025-05-20',
                'photos' => [
                    [
                        'title' => 'Classical Dance Performance',
                        'description' => 'Students performing Bharatanatyam',
                        'url' => 'https://images.unsplash.com/photo-1518834107812-67b0b7c58434?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Orchestra Performance',
                        'description' => 'School orchestra playing classical music',
                        'url' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Drama Competition',
                        'description' => 'Students performing in inter-house drama',
                        'url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Art Exhibition',
                        'description' => 'Student artwork and paintings display',
                        'url' => 'https://images.unsplash.com/photo-1541961017774-22349e4a1262?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Fashion Show',
                        'description' => 'Students showcasing eco-friendly fashion',
                        'url' => 'https://images.unsplash.com/photo-1469334031218-e382a71b716b?w=800&h=600&fit=crop'
                    ]
                ],
                'videos' => [
                    [
                        'title' => 'Cultural Fest Highlights',
                        'description' => 'Best performances from Kaleidoscope 2025',
                        'url' => 'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_2mb.mp4'
                    ]
                ]
            ],
            [
                'title' => 'Environment Day Activities',
                'description' => 'Tree plantation and environmental awareness activities',
                'class_id' => 70,
                'event_id' => null,
                'event_date' => '2025-06-05',
                'photos' => [
                    [
                        'title' => 'Tree Plantation Drive',
                        'description' => 'Students planting saplings in school garden',
                        'url' => 'https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Eco-friendly Posters',
                        'description' => 'Students creating environmental awareness posters',
                        'url' => 'https://images.unsplash.com/photo-1611273426858-450d8e3c9fce?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Recycling Workshop',
                        'description' => 'Learning to make useful items from waste',
                        'url' => 'https://images.unsplash.com/photo-1532996122724-e3c354a0b15b?w=800&h=600&fit=crop'
                    ]
                ],
                'videos' => [
                    [
                        'title' => 'Environment Day Compilation',
                        'description' => 'Environmental activities and awareness programs',
                        'url' => 'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4'
                    ]
                ]
            ],
            [
                'title' => 'Annual Day 2024 - Dreams Come True',
                'description' => 'Grand annual day celebration with spectacular performances',
                'class_id' => 71,
                'event_id' => null,
                'event_date' => '2024-12-15',
                'photos' => [
                    [
                        'title' => 'Opening Act',
                        'description' => 'Grand opening performance with synchronized dance',
                        'url' => 'https://images.unsplash.com/photo-1540039155733-5bb30b53aa14?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Principal Welcome Address',
                        'description' => 'Principal addressing the gathering',
                        'url' => 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Musical Extravaganza',
                        'description' => 'Students performing in the choir',
                        'url' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Award Ceremony',
                        'description' => 'Top performers receiving awards',
                        'url' => 'https://images.unsplash.com/photo-1559827260-dc66d52bef19?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Grand Finale',
                        'description' => 'All students together for final performance',
                        'url' => 'https://images.unsplash.com/photo-1506157786151-b8491531f063?w=800&h=600&fit=crop'
                    ]
                ],
                'videos' => [
                    [
                        'title' => 'Annual Day Full Performance',
                        'description' => 'Complete annual day celebration highlights',
                        'url' => 'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_2mb.mp4'
                    ]
                ]
            ],
            [
                'title' => 'Mathematics Olympiad Preparation',
                'description' => 'Students preparing for national mathematics olympiad',
                'class_id' => 72,
                'event_id' => null,
                'event_date' => '2025-04-10',
                'photos' => [
                    [
                        'title' => 'Problem Solving Session',
                        'description' => 'Students working on complex mathematical problems',
                        'url' => 'https://images.unsplash.com/photo-1596495578065-6e0763fa1178?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Team Discussion',
                        'description' => 'Students discussing problem-solving strategies',
                        'url' => 'https://images.unsplash.com/photo-1427504494785-3a9ca7044f45?w=800&h=600&fit=crop'
                    ],
                    [
                        'title' => 'Math Workshop',
                        'description' => 'Special workshop by mathematics expert',
                        'url' => 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?w=800&h=600&fit=crop'
                    ]
                ],
                'videos' => [
                    [
                        'title' => 'Math Olympiad Training',
                        'description' => 'Training sessions for mathematics olympiad',
                        'url' => 'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4'
                    ]
                ]
            ]
        ];

        try {
            foreach ($albums as $albumData) {
                // Create album
                $album = GalleryAlbum::create([
                    'school_id' => $schoolId,
                    'class_id' => $albumData['class_id'],
                    'event_id' => $albumData['event_id'],
                    'created_by' => $creator->id,
                    'title' => $albumData['title'],
                    'description' => $albumData['description'],
                    'event_date' => $albumData['event_date'],
                    'status' => 'active',
                    'is_public' => true,
                    'media_count' => count($albumData['photos']) + count($albumData['videos']),
                ]);

                $sortOrder = 1;

                // Add photos
                foreach ($albumData['photos'] as $photo) {
                    $fileSize = rand(500000, 3000000); // Random file size between 500KB and 3MB

                    GalleryMedia::create([
                        'album_id' => $album->id,
                        'uploaded_by' => $creator->id,
                        'type' => 'photo',
                        'title' => $photo['title'],
                        'description' => $photo['description'],
                        'file_path' => $photo['url'], // Using external URL directly
                        'file_name' => $photo['title'] . '.jpg',
                        'mime_type' => 'image/jpeg',
                        'file_size' => $fileSize,
                        'thumbnail_path' => $photo['url'], // Same URL for thumbnail
                        'metadata' => [
                            'dimensions' => [
                                'width' => 800,
                                'height' => 600
                            ]
                        ],
                        'views_count' => rand(5, 50),
                        'downloads_count' => rand(0, 10),
                        'sort_order' => $sortOrder++,
                        'is_featured' => $sortOrder === 2, // Make second item featured
                        'status' => 'active',
                    ]);
                }

                // Add videos
                foreach ($albumData['videos'] as $video) {
                    $fileSize = rand(5000000, 25000000); // Random file size between 5MB and 25MB

                    GalleryMedia::create([
                        'album_id' => $album->id,
                        'uploaded_by' => $creator->id,
                        'type' => 'video',
                        'title' => $video['title'],
                        'description' => $video['description'],
                        'file_path' => $video['url'], // Using external URL directly
                        'file_name' => $video['title'] . '.mp4',
                        'mime_type' => 'video/mp4',
                        'file_size' => $fileSize,
                        'thumbnail_path' => 'https://images.unsplash.com/photo-1574267432553-4b4628081c31?w=400&h=300&fit=crop', // Generic video thumbnail
                        'metadata' => [
                            'duration' => rand(30, 300), // Duration in seconds
                            'dimensions' => [
                                'width' => 1280,
                                'height' => 720
                            ]
                        ],
                        'views_count' => rand(10, 100),
                        'downloads_count' => rand(0, 5),
                        'sort_order' => $sortOrder++,
                        'is_featured' => false,
                        'status' => 'active',
                    ]);
                }

                // Set cover image to first photo
                if (!empty($albumData['photos'])) {
                    $album->update(['cover_image' => $albumData['photos'][0]['url']]);
                }

                $this->command->info("✓ Created album: {$albumData['title']} with " . (count($albumData['photos']) + count($albumData['videos'])) . " media items");
            }

            $this->command->info('✓ Created ' . count($albums) . ' albums with photos and videos using public URLs');
            $this->command->info('✓ School ID: ' . $schoolId);
            $this->command->info('✓ Class IDs: ' . implode(', ', $classIds));
        } catch (\Exception $e) {
            $this->command->error('✗ Error creating gallery data: ' . $e->getMessage());
            throw $e; // Re-throw to trigger transaction rollback
        }
    }
}
