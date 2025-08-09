<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class GalleryAlbum extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'class_id',
        'event_id',
        'created_by',
        'title',
        'description',
        'event_date',
        'status',
        'media_count',
        'cover_image',
        'is_public',
        'visibility_settings',
    ];

    protected $casts = [
        'event_date' => 'date',
        'is_public' => 'boolean',
        'visibility_settings' => 'array',
        'media_count' => 'integer',
    ];

    // Relationships
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function class()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function media()
    {
        return $this->hasMany(GalleryMedia::class, 'album_id');
    }

    public function photos()
    {
        return $this->hasMany(GalleryMedia::class, 'album_id')->where('type', 'photo');
    }

    public function videos()
    {
        return $this->hasMany(GalleryMedia::class, 'album_id')->where('type', 'video');
    }

    public function featuredMedia()
    {
        return $this->hasMany(GalleryMedia::class, 'album_id')
            ->where('is_featured', true)
            ->orderBy('sort_order');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeByClass($query, $classId)
    {
        return $query->where('class_id', $classId);
    }

    public function scopeByEvent($query, $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('event_date', 'desc')->limit($limit);
    }

    // Accessors
    public function getCoverImageUrlAttribute()
    {
        if ($this->cover_image) {
            return Storage::url($this->cover_image);
        }

        // Return first media item as cover if no cover is set
        $firstMedia = $this->media()->first();
        if ($firstMedia) {
            return $firstMedia->file_url;
        }

        return null;
    }

    public function getMediaStatsAttribute()
    {
        return [
            'total' => $this->media_count,
            'photos' => $this->photos()->count(),
            'videos' => $this->videos()->count(),
            'total_size' => $this->media()->sum('file_size'),
            'total_views' => $this->media()->sum('views_count'),
            'total_downloads' => $this->media()->sum('downloads_count'),
        ];
    }

    // Helper Methods
    public function updateMediaCount()
    {
        $this->update([
            'media_count' => $this->media()->where('status', 'active')->count()
        ]);
    }

    public function setCoverImage($mediaId)
    {
        $media = $this->media()->find($mediaId);
        if ($media) {
            $this->update(['cover_image' => $media->file_path]);
            return true;
        }
        return false;
    }

    public function canBeViewedBy($user)
    {
        // Public albums can be viewed by anyone
        if ($this->is_public) {
            return true;
        }

        // Check visibility settings
        if ($this->visibility_settings) {
            // Implement custom visibility logic based on settings
            // For example: check if user role is in allowed roles
            if (isset($this->visibility_settings['allowed_roles'])) {
                // Add your role checking logic here
            }
        }

        // Default: only school members can view
        return $user->school_id === $this->school_id;
    }

    public function canBeEditedBy($user)
    {
        // Creator can always edit
        if ($user->id === $this->created_by) {
            return true;
        }

        // School admins can edit
        if ($user->hasRole('school_admin') && $user->school_id === $this->school_id) {
            return true;
        }

        // Teachers can edit albums for their classes
        if ($user->hasRole('teacher')) {
            $teacher = Teacher::where('user_id', $user->id)->first();
            if ($teacher && $teacher->classes->contains($this->class_id)) {
                return true;
            }
        }

        return false;
    }

    public function archive()
    {
        $this->update(['status' => 'archived']);
        $this->media()->update(['status' => 'archived']);
    }

    public function restore()
    {
        $this->update(['status' => 'active']);
        $this->media()->where('status', 'archived')->update(['status' => 'active']);
    }
}
