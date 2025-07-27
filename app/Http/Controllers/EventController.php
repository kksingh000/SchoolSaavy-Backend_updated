<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventService;
use App\Http\Resources\EventResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EventController extends BaseController
{
    protected $eventService;

    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
    }

    /**
     * Get all events with filters
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('communication')) {
            return $this->moduleAccessDenied();
        }

        try {
            $filters = $request->only([
                'type',
                'priority',
                'audience',
                'class_id',
                'date_from',
                'date_to',
                'upcoming_only'
            ]);

            if ($request->upcoming_only) {
                $events = $this->eventService->getUpcomingEvents($filters);
            } else {
                $events = $this->eventService->getAll($filters, ['creator', 'school']);
            }

            return $this->successResponse(
                EventResource::collection($events),
                'Events retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Create a new event
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('communication')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'type' => 'required|in:announcement,holiday,exam,meeting,sports,cultural,academic,emergency,other',
                'priority' => 'required|in:low,medium,high,urgent',
                'event_date' => 'required|date',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i|after:start_time',
                'location' => 'nullable|string|max:255',
                'target_audience' => 'required|array',
                'target_audience.*' => 'in:all,students,teachers,parents,staff',
                'affected_classes' => 'nullable|array',
                'affected_classes.*' => 'exists:classes,id',
                'is_recurring' => 'boolean',
                'recurrence_type' => 'nullable|in:daily,weekly,monthly,yearly',
                'recurrence_end_date' => 'nullable|date|after:event_date',
                'requires_acknowledgment' => 'boolean',
                'is_published' => 'boolean',
                'attachments' => 'nullable|array',
            ]);

            $event = $this->eventService->createEvent($request->all());

            return $this->successResponse(
                new EventResource($event),
                'Event created successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get a specific event
     */
    public function show($id): JsonResponse
    {
        if (!$this->checkModuleAccess('communication')) {
            return $this->moduleAccessDenied();
        }

        try {
            $event = $this->eventService->find($id, ['creator', 'school', 'acknowledgments.user']);

            return $this->successResponse(
                new EventResource($event),
                'Event retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Update an event
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (!$this->checkModuleAccess('communication')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'type' => 'sometimes|in:announcement,holiday,exam,meeting,sports,cultural,academic,emergency,other',
                'priority' => 'sometimes|in:low,medium,high,urgent',
                'event_date' => 'sometimes|date',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i|after:start_time',
                'location' => 'nullable|string|max:255',
                'target_audience' => 'sometimes|array',
                'target_audience.*' => 'in:all,students,teachers,parents,staff',
                'affected_classes' => 'nullable|array',
                'affected_classes.*' => 'exists:classes,id',
                'requires_acknowledgment' => 'boolean',
                'is_published' => 'boolean',
                'attachments' => 'nullable|array',
            ]);

            $event = $this->eventService->updateEvent($id, $request->validated());

            return $this->successResponse(
                new EventResource($event),
                'Event updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Delete an event
     */
    public function destroy($id): JsonResponse
    {
        if (!$this->checkModuleAccess('communication')) {
            return $this->moduleAccessDenied();
        }

        try {
            $this->eventService->deleteEvent($id);

            return $this->successResponse(
                null,
                'Event deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get today's events
     */
    public function todaysEvents(): JsonResponse
    {
        if (!$this->checkModuleAccess('communication')) {
            return $this->moduleAccessDenied();
        }

        try {
            $events = $this->eventService->getTodaysEvents();

            return $this->successResponse(
                EventResource::collection($events),
                'Today\'s events retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get upcoming events
     */
    public function upcomingEvents(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('communication')) {
            return $this->moduleAccessDenied();
        }

        try {
            $filters = $request->only(['type', 'priority', 'audience', 'class_id']);
            $events = $this->eventService->getUpcomingEvents($filters);

            return $this->successResponse(
                EventResource::collection($events),
                'Upcoming events retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get event calendar
     */
    public function calendar(Request $request): JsonResponse
    {
        if (!$this->checkModuleAccess('communication')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'month' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer|min:2020|max:2030',
            ]);

            $calendar = $this->eventService->getEventCalendar(
                $request->month,
                $request->year
            );

            return $this->successResponse(
                $calendar,
                'Event calendar retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Acknowledge an event
     */
    public function acknowledge(Request $request, $id): JsonResponse
    {
        if (!$this->checkModuleAccess('communication')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'comments' => 'nullable|string|max:500',
            ]);

            $acknowledgment = $this->eventService->acknowledgeEvent($id, $request->comments);

            return $this->successResponse(
                $acknowledgment,
                'Event acknowledged successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get event acknowledgments
     */
    public function acknowledgments($id): JsonResponse
    {
        if (!$this->checkModuleAccess('communication')) {
            return $this->moduleAccessDenied();
        }

        try {
            $acknowledgments = $this->eventService->getEventAcknowledments($id);

            return $this->successResponse(
                $acknowledgments,
                'Event acknowledgments retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get unacknowledged events for current user
     */
    public function unacknowledged(): JsonResponse
    {
        if (!$this->checkModuleAccess('communication')) {
            return $this->moduleAccessDenied();
        }

        try {
            $events = $this->eventService->getUnacknowledgedEvents();

            return $this->successResponse(
                EventResource::collection($events),
                'Unacknowledged events retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get events by type
     */
    public function byType($type): JsonResponse
    {
        if (!$this->checkModuleAccess('communication')) {
            return $this->moduleAccessDenied();
        }

        try {
            $events = $this->eventService->getEventsByType($type);

            return $this->successResponse(
                EventResource::collection($events),
                ucfirst($type) . ' events retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get event statistics
     */
    public function statistics(): JsonResponse
    {
        if (!$this->checkModuleAccess('communication')) {
            return $this->moduleAccessDenied();
        }

        try {
            $stats = $this->eventService->getEventStatistics();

            return $this->successResponse(
                $stats,
                'Event statistics retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Duplicate an event
     */
    public function duplicate(Request $request, $id): JsonResponse
    {
        if (!$this->checkModuleAccess('communication')) {
            return $this->moduleAccessDenied();
        }

        try {
            $request->validate([
                'title' => 'nullable|string|max:255',
                'event_date' => 'nullable|date',
                'is_published' => 'nullable|boolean',
            ]);

            $event = $this->eventService->duplicateEvent($id, $request->validated());

            return $this->successResponse(
                new EventResource($event),
                'Event duplicated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
}
