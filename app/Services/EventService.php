<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventAcknowledgment;
use App\Jobs\SendEventReminderJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EventService extends BaseService
{
    protected function initializeModel()
    {
        $this->model = Event::class;
    }

    public function createEvent(array $data)
    {
        DB::beginTransaction();
        try {
            // Add school_id and creator
            $data['school_id'] = Auth::user()->getSchool()->id;
            $data['created_by'] = Auth::id();

            // Set published_at if publishing immediately
            if ($data['is_published'] ?? true) {
                $data['published_at'] = now();
            }

            $event = $this->create($data);

            // Generate recurring events if needed
            if ($event->is_recurring) {
                $recurringEvents = $event->generateRecurringEvents();
                foreach ($recurringEvents as $recurringEventData) {
                    Event::create($recurringEventData);
                }
            }

            // Schedule automatic reminders for published events
            if ($event->is_published) {
                $this->scheduleEventReminders($event);
            }

            DB::commit();
            return $event->load(['creator', 'school']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateEvent($id, array $data)
    {
        DB::beginTransaction();
        try {
            $event = $this->find($id);

            // Handle publishing
            if (isset($data['is_published']) && $data['is_published'] && !$event->published_at) {
                $data['published_at'] = now();
            }

            $event->update($data);

            DB::commit();
            return $event->fresh(['creator', 'school']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getUpcomingEvents($filters = [])
    {
        $query = Event::where('school_id', Auth::user()->getSchool()->id)
            ->published()
            ->upcoming()
            ->with(['creator'])
            ->orderBy('event_date')
            ->orderBy('start_time');

        // Apply filters
        if (isset($filters['type'])) {
            $query->byType($filters['type']);
        }

        if (isset($filters['priority'])) {
            $query->byPriority($filters['priority']);
        }

        if (isset($filters['audience'])) {
            $query->forAudience($filters['audience']);
        }

        if (isset($filters['class_id'])) {
            $query->forClass($filters['class_id']);
        }

        if (isset($filters['date_from'])) {
            $query->where('event_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('event_date', '<=', $filters['date_to']);
        }

        return $query->get();
    }

    /**
     * Paginate events with optional search and upcoming filter
     */
    public function paginateEvents(array $filters = [], int $perPage = 15)
    {
        $schoolId = Auth::user()->getSchool()->id;
        $query = Event::where('school_id', $schoolId)
            ->published()
            ->with(['creator', 'school']);

        $upcoming = !empty($filters['upcoming_only']);
        if ($upcoming) {
            $query->whereDate('event_date', '>=', now()->toDateString());
        }

        // Filters (reuse logic similar to getUpcomingEvents)
        if (!empty($filters['type'])) {
            $query->byType($filters['type']);
        }
        if (!empty($filters['priority'])) {
            $query->byPriority($filters['priority']);
        }
        if (!empty($filters['audience'])) {
            $query->forAudience($filters['audience']);
        }
        if (!empty($filters['class_id'])) {
            $query->forClass($filters['class_id']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('event_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('event_date', '<=', $filters['date_to']);
        }

        // Search
        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%$term%")
                    ->orWhere('description', 'like', "%$term%");
            });
        }

        // Sorting
        if ($upcoming) {
            $query->orderBy('event_date')->orderBy('start_time');
        } else {
            $query->orderBy('event_date', 'desc')->orderBy('start_time', 'asc');
        }

        return $query->paginate($perPage);
    }

    public function getTodaysEvents()
    {
        return Event::where('school_id', Auth::user()->getSchool()->id)
            ->published()
            ->today()
            ->with(['creator'])
            ->orderBy('start_time')->get();
    }

    public function getEventCalendar($month = null, $year = null)
    {
        $month = $month ?? date('m');
        $year = $year ?? date('Y');

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $events = Event::where('school_id', Auth::user()->getSchool()->id)
            ->published()
            ->whereBetween('event_date', [$startDate, $endDate])
            ->with(['creator'])
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get();

        // Group by date
        $calendar = [];
        foreach ($events as $event) {
            $date = $event->event_date->format('Y-m-d');
            if (!isset($calendar[$date])) {
                $calendar[$date] = [];
            }
            $calendar[$date][] = $event;
        }

        return [
            'month' => $month,
            'year' => $year,
            'events' => $calendar,
            'total_events' => $events->count(),
        ];
    }

    public function acknowledgeEvent($eventId, $comments = null)
    {
        $event = Event::findOrFail($eventId);

        if (!$event->requires_acknowledgment) {
            throw new \Exception('This event does not require acknowledgment');
        }

        return $event->acknowledgeBy(Auth::user(), $comments);
    }

    public function getEventAcknowledments($eventId)
    {
        $event = Event::findOrFail($eventId);

        return $event->acknowledgments()
            ->with(['user'])
            ->orderBy('acknowledged_at', 'desc')
            ->get();
    }

    public function getUnacknowledgedEvents($userId = null)
    {
        $userId = $userId ?? Auth::id();

        $acknowledgedEventIds = EventAcknowledgment::where('user_id', $userId)
            ->pluck('event_id')
            ->toArray();

        return Event::where('school_id', Auth::user()->getSchool()->id)
            ->published()
            ->where('requires_acknowledgment', true)
            ->whereNotIn('id', $acknowledgedEventIds)
            ->with(['creator'])
            ->orderBy('event_date')
            ->get();
    }

    public function getEventsByType($type)
    {
        return Event::where('school_id', Auth::user()->getSchool()->id)
            ->published()
            ->byType($type)
            ->with(['creator'])
            ->orderBy('event_date', 'desc')
            ->get();
    }

    public function getEventStatistics()
    {
        $schoolId = Auth::user()->getSchool()->id;

        return [
            'total_events' => Event::where('school_id', $schoolId)->published()->count(),
            'upcoming_events' => Event::where('school_id', $schoolId)->published()->upcoming()->count(),
            'todays_events' => Event::where('school_id', $schoolId)->published()->today()->count(),
            'events_by_type' => Event::where('school_id', $schoolId)
                ->published()
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
            'events_by_priority' => Event::where('school_id', $schoolId)
                ->published()
                ->selectRaw('priority, COUNT(*) as count')
                ->groupBy('priority')
                ->pluck('count', 'priority'),
            'acknowledgment_pending' => Event::where('school_id', $schoolId)
                ->published()
                ->where('requires_acknowledgment', true)
                ->whereDoesntHave('acknowledgments', function ($query) {
                    $query->where('user_id', Auth::id());
                })
                ->count(),
        ];
    }

    public function deleteEvent($id)
    {
        DB::beginTransaction();
        try {
            $event = $this->find($id);

            // Soft delete the event
            $event->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function duplicateEvent($id, array $newData = [])
    {
        DB::beginTransaction();
        try {
            $originalEvent = $this->find($id);

            $eventData = $originalEvent->toArray();
            unset($eventData['id'], $eventData['created_at'], $eventData['updated_at'], $eventData['deleted_at']);

            // Merge with new data
            $eventData = array_merge($eventData, $newData);
            $eventData['created_by'] = Auth::id();
            $eventData['published_at'] = null; // Reset published status

            $newEvent = Event::create($eventData);

            DB::commit();
            return $newEvent->load(['creator', 'school']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Schedule automatic reminders for an event
     */
    private function scheduleEventReminders(Event $event): void
    {
        try {
            // Don't schedule reminders for past events
            if ($event->event_date->isPast()) {
                return;
            }

            // Don't schedule reminders for low-priority or recurring events  
            if ($event->priority === 'low' || $event->is_recurring) {
                return;
            }

            $eventDateTime = $event->event_date;
            if ($event->start_time) {
                $eventDateTime = $event->event_date->setTimeFromTimeString($event->start_time);
            }

            $now = now();

            // Schedule 1-day reminder (only if event is more than 1 day away)
            $oneDayBefore = $eventDateTime->copy()->subDay();
            if ($oneDayBefore->isFuture() && $oneDayBefore->diffInHours($now) >= 1) {
                SendEventReminderJob::dispatch($event, '1_day')
                    ->delay($oneDayBefore);
            }

            // Schedule 3-hour reminder for high/urgent priority events
            if (in_array($event->priority, ['high', 'urgent'])) {
                $threeHoursBefore = $eventDateTime->copy()->subHours(3);
                if ($threeHoursBefore->isFuture() && $threeHoursBefore->diffInMinutes($now) >= 30) {
                    SendEventReminderJob::dispatch($event, '3_hours')
                        ->delay($threeHoursBefore);
                }
            }

            // Schedule 1-hour reminder for urgent events only
            if ($event->priority === 'urgent') {
                $oneHourBefore = $eventDateTime->copy()->subHour();
                if ($oneHourBefore->isFuture() && $oneHourBefore->diffInMinutes($now) >= 10) {
                    SendEventReminderJob::dispatch($event, '1_hour')
                        ->delay($oneHourBefore);
                }
            }

            Log::info("Event reminders scheduled", [
                'event_id' => $event->id,
                'event_priority' => $event->priority,
                'event_date' => $event->event_date->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to schedule event reminders", [
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
