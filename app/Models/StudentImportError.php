<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentImportError extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_import_id',
        'row_number',
        'row_data',
        'errors',
    ];

    protected $casts = [
        'row_data' => 'json',
        'errors' => 'json',
        'row_number' => 'integer',
    ];

    // Relationships
    public function studentImport()
    {
        return $this->belongsTo(StudentImport::class);
    }
}
