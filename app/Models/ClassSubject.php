<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ClassSubject extends Pivot
{
    protected $table = 'class_subject';

    protected $fillable = [
        'class_id',
        'subject_id',
    ];

    public $incrementing = true;

    // Relationships
    public function class()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}
