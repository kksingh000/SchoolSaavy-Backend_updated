<?php

namespace App\Services;

use App\Models\ClassSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TimetableService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = ClassSchedule::class;
    }

    public function createClassSchedule(array $data)
    {
        DB::beginTransaction();
        try {
            // Add school_id to data
            $data['school_id'] = request()->school_id;

            // Validate for schedule conflicts
            $this->validateScheduleConflicts($data);

            $schedule = $this->create($data);

            DB::commit();
            return $schedule->load(['class', 'subject', 'teacher.user']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateClassSchedule($id, array $data)
    {
        DB::beginTransaction();
        try {
            $schedule = $this->find($id);

            // If time or day is being updated, validate conflicts
            if (isset($data['start_time']) || isset($data['end_time']) || isset($data['day_of_week']) || isset($data['teacher_id'])) {
                $conflictData = array_merge($schedule->toArray(), $data);
                $this->validateScheduleConflicts($conflictData, $id);
            }

            $schedule->update($data);

            DB::commit();
            return $schedule->fresh(['class', 'subject', 'teacher.user']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteClassSchedule($id)
    {
        DB::beginTransaction();
        try {
            $schedule = $this->find($id);
            $schedule->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getClassTimetable($classId)
    {
        $schedules = ClassSchedule::where('class_id', $classId)
            ->active()
            ->with(['subject', 'teacher.user'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        // Group by day of week for easier frontend consumption
        $timetable = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($days as $day) {
            $timetable[$day] = $schedules->filter(function ($schedule) use ($day) {
                return $schedule->day_of_week === $day;
            })->values();
        }

        return $timetable;
    }

    public function getTeacherTimetable($teacherId)
    {
        $schedules = ClassSchedule::where('teacher_id', $teacherId)
            ->active()
            ->with(['class', 'subject'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        // Group by day of week
        $timetable = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($days as $day) {
            $timetable[$day] = $schedules->filter(function ($schedule) use ($day) {
                return $schedule->day_of_week === $day;
            })->values();
        }

        return $timetable;
    }

    public function getWeeklyOverview(array $filters = [])
    {
        $schoolId = request()->school_id;

        $query = ClassSchedule::where('school_id', $schoolId)
            ->active()
            ->with(['class', 'subject', 'teacher.user']);

        // Apply filters
        if (!empty($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (!empty($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (!empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (!empty($filters['day_of_week'])) {
            $query->where('day_of_week', $filters['day_of_week']);
        }

        $schedules = $query->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        // Group by day for overview
        $overview = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($days as $day) {
            $daySchedules = $schedules->filter(function ($schedule) use ($day) {
                return $schedule->day_of_week === $day;
            })->values();

            $overview[$day] = [
                'total_classes' => $daySchedules->count(),
                'schedules' => $daySchedules
            ];
        }

        // Add summary information
        $summary = [
            'total_schedules' => $schedules->count(),
            'active_classes' => $schedules->unique('class_id')->count(),
            'active_teachers' => $schedules->unique('teacher_id')->count(),
            'active_subjects' => $schedules->unique('subject_id')->count(),
            'filters_applied' => array_filter($filters) // Only show applied filters
        ];

        return [
            'summary' => $summary,
            'weekly_schedule' => $overview
        ];
    }

    /**
     * Get filter options for dropdowns (classes, teachers, subjects)
     */
    public function getFilterOptions()
    {
        $schoolId = request()->school_id;

        // Get classes that have scheduled sessions
        $classes = \App\Models\ClassRoom::where('school_id', $schoolId)
            ->whereHas('schedules') // Only classes with schedules
            ->select('id', 'name', 'section')
            ->get()
            ->map(function ($class) {
                return [
                    'id' => $class->id,
                    'name' => $class->name . ($class->section ? ' - ' . $class->section : ''),
                    'full_name' => $class->name . ($class->section ? ' (' . $class->section . ')' : '')
                ];
            });

        // Get teachers that have scheduled sessions
        $teachers = \App\Models\Teacher::where('school_id', $schoolId)
            ->whereHas('schedules') // Only teachers with schedules
            ->with('user:id,name')
            ->select('id', 'user_id', 'employee_id')
            ->get()
            ->map(function ($teacher) {
                return [
                    'id' => $teacher->id,
                    'name' => $teacher->user->name ?? 'Unknown Teacher',
                    'employee_id' => $teacher->employee_id
                ];
            });

        // Get subjects that are scheduled
        $subjects = \App\Models\Subject::where('school_id', $schoolId)
            ->whereHas('schedules') // Only subjects with schedules
            ->select('id', 'name', 'code')
            ->get()
            ->map(function ($subject) {
                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'display_name' => $subject->name . ($subject->code ? ' (' . $subject->code . ')' : '')
                ];
            });

        $days = [
            ['value' => 'monday', 'label' => 'Monday'],
            ['value' => 'tuesday', 'label' => 'Tuesday'],
            ['value' => 'wednesday', 'label' => 'Wednesday'],
            ['value' => 'thursday', 'label' => 'Thursday'],
            ['value' => 'friday', 'label' => 'Friday'],
            ['value' => 'saturday', 'label' => 'Saturday'],
            ['value' => 'sunday', 'label' => 'Sunday']
        ];

        return [
            'classes' => $classes,
            'teachers' => $teachers,
            'subjects' => $subjects,
            'days' => $days
        ];
    }

    protected function validateScheduleConflicts($data, $excludeId = null)
    {
        $query = ClassSchedule::where('teacher_id', $data['teacher_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where('is_active', true)
            ->where(function ($query) use ($data) {
                $query->where(function ($q) use ($data) {
                    // Check if start_time falls within existing slot
                    $q->where('start_time', '<=', $data['start_time'])
                        ->where('end_time', '>', $data['start_time']);
                })->orWhere(function ($q) use ($data) {
                    // Check if end_time falls within existing slot
                    $q->where('start_time', '<', $data['end_time'])
                        ->where('end_time', '>=', $data['end_time']);
                })->orWhere(function ($q) use ($data) {
                    // Check if new slot encompasses existing slot
                    $q->where('start_time', '>=', $data['start_time'])
                        ->where('end_time', '<=', $data['end_time']);
                });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new \Exception('Teacher schedule conflict detected for this time slot');
        }

        // Check for class schedule conflicts
        $classQuery = ClassSchedule::where('class_id', $data['class_id'])
            ->where('day_of_week', $data['day_of_week'])
            ->where('is_active', true)
            ->where(function ($query) use ($data) {
                $query->where(function ($q) use ($data) {
                    $q->where('start_time', '<=', $data['start_time'])
                        ->where('end_time', '>', $data['start_time']);
                })->orWhere(function ($q) use ($data) {
                    $q->where('start_time', '<', $data['end_time'])
                        ->where('end_time', '>=', $data['end_time']);
                })->orWhere(function ($q) use ($data) {
                    $q->where('start_time', '>=', $data['start_time'])
                        ->where('end_time', '<=', $data['end_time']);
                });
            });

        if ($excludeId) {
            $classQuery->where('id', '!=', $excludeId);
        }

        if ($classQuery->exists()) {
            throw new \Exception('Class schedule conflict detected for this time slot');
        }
    }

    /**
     * Create bulk timetable for a class
     */
    public function createBulkTimetable($classId, $schedules, $replaceExisting = false)
    {
        DB::beginTransaction();
        try {
            $schoolId = request()->school_id;
            $created = [];
            $errors = [];

            // If replace existing, delete all existing schedules for this class
            if ($replaceExisting) {
                ClassSchedule::where('class_id', $classId)
                    ->where('school_id', $schoolId)
                    ->delete();
            }

            foreach ($schedules as $index => $scheduleData) {
                try {
                    $data = array_merge($scheduleData, [
                        'school_id' => $schoolId,
                        'class_id' => $classId,
                        'is_active' => true
                    ]);

                    // Validate conflicts only if not replacing existing
                    if (!$replaceExisting) {
                        $this->validateScheduleConflicts($data);
                    }

                    $schedule = ClassSchedule::create($data);
                    $created[] = $schedule->load(['class', 'subject', 'teacher.user']);
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'schedule' => $scheduleData,
                        'error' => $e->getMessage()
                    ];
                }
            }

            if (!empty($errors) && empty($created)) {
                // If all schedules failed, rollback
                DB::rollBack();
                throw new \Exception('All schedules failed to create. Check errors for details.');
            }

            DB::commit();

            return [
                'success_count' => count($created),
                'error_count' => count($errors),
                'created_schedules' => $created,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update bulk timetable for a class
     */
    public function updateBulkTimetable($classId, $schedules)
    {
        DB::beginTransaction();
        try {
            $schoolId = request()->school_id;
            $created = [];
            $updated = [];
            $deleted = [];
            $errors = [];

            foreach ($schedules as $index => $scheduleData) {
                try {
                    $action = $scheduleData['action'] ?? 'create';

                    if ($action === 'delete' && isset($scheduleData['id'])) {
                        // Delete existing schedule
                        $schedule = ClassSchedule::where('id', $scheduleData['id'])
                            ->where('class_id', $classId)
                            ->where('school_id', $schoolId)
                            ->first();

                        if ($schedule) {
                            $schedule->delete();
                            $deleted[] = $scheduleData['id'];
                        }
                    } elseif ($action === 'update' && isset($scheduleData['id'])) {
                        // Update existing schedule
                        $schedule = ClassSchedule::where('id', $scheduleData['id'])
                            ->where('class_id', $classId)
                            ->where('school_id', $schoolId)
                            ->first();

                        if ($schedule) {
                            $updateData = array_merge($scheduleData, [
                                'school_id' => $schoolId,
                                'class_id' => $classId
                            ]);
                            unset($updateData['id'], $updateData['action']);

                            // Validate conflicts for updates
                            $this->validateScheduleConflicts($updateData, $schedule->id);

                            $schedule->update($updateData);
                            $updated[] = $schedule->fresh(['class', 'subject', 'teacher.user']);
                        }
                    } else {
                        // Create new schedule
                        $data = array_merge($scheduleData, [
                            'school_id' => $schoolId,
                            'class_id' => $classId,
                            'is_active' => $scheduleData['is_active'] ?? true
                        ]);
                        unset($data['id'], $data['action']);

                        $this->validateScheduleConflicts($data);

                        $schedule = ClassSchedule::create($data);
                        $created[] = $schedule->load(['class', 'subject', 'teacher.user']);
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'schedule' => $scheduleData,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            return [
                'created_count' => count($created),
                'updated_count' => count($updated),
                'deleted_count' => count($deleted),
                'error_count' => count($errors),
                'created_schedules' => $created,
                'updated_schedules' => $updated,
                'deleted_schedule_ids' => $deleted,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Replace entire timetable for a class
     */
    public function replaceTimetable($classId, $schedules)
    {
        DB::beginTransaction();
        try {
            $schoolId = request()->school_id;

            // Delete all existing schedules for this class
            ClassSchedule::where('class_id', $classId)
                ->where('school_id', $schoolId)
                ->delete();

            $created = [];
            $errors = [];

            // Create new schedules
            foreach ($schedules as $index => $scheduleData) {
                try {
                    $data = array_merge($scheduleData, [
                        'school_id' => $schoolId,
                        'class_id' => $classId,
                        'is_active' => true
                    ]);

                    $schedule = ClassSchedule::create($data);
                    $created[] = $schedule->load(['class', 'subject', 'teacher.user']);
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'schedule' => $scheduleData,
                        'error' => $e->getMessage()
                    ];
                }
            }

            if (!empty($errors) && empty($created)) {
                // If all schedules failed, rollback
                DB::rollBack();
                throw new \Exception('All schedules failed to create. Check errors for details.');
            }

            DB::commit();

            return [
                'success_count' => count($created),
                'error_count' => count($errors),
                'created_schedules' => $created,
                'errors' => $errors,
                'message' => 'Timetable replaced successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
