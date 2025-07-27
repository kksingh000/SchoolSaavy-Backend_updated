<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventAcknowledgment extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'acknowledged_at',
        'comments',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
    ];

    // Relationships
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
