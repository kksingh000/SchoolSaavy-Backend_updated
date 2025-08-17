<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Assessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'assessment_type_id',
        'class_id',
        'subject_id',
        'teacher_id',
        'title',
        'code',
        'description',
        'assessment_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'total_marks',
        'passing_marks',
        'marking_scheme',
        'syllabus_covered',
        'topics',
        'instructions',
        'status',
        'academic_year',
        'term',
        'is_active',
        'custom_fields',
    ];

    protected $casts = [
        'assessment_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'marking_scheme' => 'json',
        'topics' => 'json',
        'instructions' => 'json',
        'is_active' => 'boolean',
        'custom_fields' => 'json',
    ];

    // Relationships
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function assessmentType()
    {
        return $this->belongsTo(AssessmentType::class);
    }

    public function class()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function results()
    {
        return $this->hasMany(AssessmentResult::class);
    }

    public function scopeForAcademicYear($query, $academicYearId)
    {
        return $query->where('academic_year_id', $academicYearId);
    }

    public function scopeCurrentYear($query)
    {
        return $query->whereHas('academicYear', function ($q) {
            $q->where('is_current', true);
        });
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeUpcoming($query, $days = 7)
    {
        return $query->where('assessment_date', '>=', Carbon::today())
            ->where('assessment_date', '<=', Carbon::today()->addDays($days));
    }

    public function scopeByAcademicYear($query, $year)
    {
        return $query->where('academic_year', $year);
    }

    public function scopeByTerm($query, $term)
    {
        return $query->where('term', $term);
    }

    // Accessors & Mutators
    public function getIsUpcomingAttribute()
    {
        return $this->assessment_date >= Carbon::today();
    }

    public function getIsOverdueAttribute()
    {
        return $this->assessment_date < Carbon::today() && $this->status === 'scheduled';
    }

    public function getDurationHoursAttribute()
    {
        return round($this->duration_minutes / 60, 2);
    }

    public function getPassingPercentageAttribute()
    {
        return $this->total_marks > 0 ? round(($this->passing_marks / $this->total_marks) * 100, 2) : 0;
    }

    // Helper methods
    public function canBeEdited()
    {
        return in_array($this->status, ['scheduled']) && $this->assessment_date >= Carbon::today();
    }

    public function canBeDeleted()
    {
        return $this->status === 'scheduled' && $this->results()->count() === 0;
    }

    public function canAcceptResults()
    {
        return in_array($this->status, ['completed', 'in_progress']);
    }

    public function getClassPerformance()
    {
        $results = $this->results()->with('student')->get();

        if ($results->isEmpty()) {
            return [
                'total_students' => 0,
                'appeared' => 0,
                'passed' => 0,
                'failed' => 0,
                'absent' => 0,
                'pass_percentage' => 0,
                'class_average' => 0,
                'highest_marks' => 0,
                'lowest_marks' => 0,
            ];
        }

        $totalStudents = $results->count();
        $appeared = $results->where('is_absent', false)->count();
        $passed = $results->where('result_status', 'pass')->count();
        $failed = $results->where('result_status', 'fail')->count();
        $absent = $results->where('is_absent', true)->count();

        $appearedResults = $results->where('is_absent', false);
        $classAverage = $appearedResults->avg('percentage') ?? 0;
        $highestMarks = $appearedResults->max('marks_obtained') ?? 0;
        $lowestMarks = $appearedResults->min('marks_obtained') ?? 0;

        return [
            'total_students' => $totalStudents,
            'appeared' => $appeared,
            'passed' => $passed,
            'failed' => $failed,
            'absent' => $absent,
            'pass_percentage' => $appeared > 0 ? round(($passed / $appeared) * 100, 2) : 0,
            'class_average' => round($classAverage, 2),
            'highest_marks' => $highestMarks,
            'lowest_marks' => $lowestMarks,
        ];
    }

    public function generateCode()
    {
        if (empty($this->code)) {
            $typeCode = strtoupper(substr($this->assessmentType->name, 0, 3));
            $subjectCode = strtoupper(substr($this->subject->code ?? $this->subject->name, 0, 3));
            $year = Carbon::parse($this->assessment_date)->year;
            $sequence = $this->class->assessments()
                ->where('assessment_type_id', $this->assessment_type_id)
                ->where('subject_id', $this->subject_id)
                ->where('academic_year', $this->academic_year)
                ->count() + 1;

            $this->code = "{$typeCode}{$sequence}-{$subjectCode}-{$year}";
            $this->save();
        }

        return $this->code;
    }
}
