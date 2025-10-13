<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

// Attendance Events
use App\Events\Attendance\StudentMarkedAbsent;
use App\Events\Attendance\StudentMarkedLate;
use App\Events\Attendance\StudentLowAttendance;
use App\Events\Attendance\LowAttendanceAlert;
use App\Events\Attendance\ConsecutiveAbsencesAlert;

// Fee Management Events
use App\Events\FeeManagement\FeeInstallmentDue;
use App\Events\FeeManagement\PaymentReceived;
use App\Events\FeeManagement\PaymentOverdue;
use App\Events\FeeManagement\PaymentDueTomorrow;

// Assignment Management Events
use App\Events\AssignmentManagement\AssignmentCreated;
use App\Events\AssignmentManagement\AssignmentSubmitted;
use App\Events\AssignmentManagement\AssignmentGraded;
use App\Events\AssignmentManagement\AssignmentResubmissionRequested;

// Assessment Management Events
use App\Events\AssessmentManagement\AssessmentScheduled;
use App\Events\AssessmentManagement\AssessmentRescheduled;

// Communication Events
use App\Events\Communication\EmergencyAlert;

// Attendance Listeners
use App\Listeners\Attendance\SendAbsentNotification;
use App\Listeners\Attendance\SendLateArrivalNotification;
use App\Listeners\Attendance\SendLowAttendanceNotification;
use App\Listeners\Attendance\SendConsecutiveAbsenceNotification;

// Fee Management Listeners
use App\Listeners\FeeManagement\SendPaymentConfirmation;
use App\Listeners\FeeManagement\SendFeeDueNotification;
use App\Listeners\FeeManagement\SendPaymentOverdueNotification;
use App\Listeners\FeeManagement\SendPaymentReminderNotification;

// Assignment Management Listeners
use App\Listeners\AssignmentManagement\SendAssignmentCreatedNotification;
use App\Listeners\AssignmentManagement\SendAssignmentSubmittedNotification;
use App\Listeners\AssignmentManagement\SendAssignmentGradedNotification;
use App\Listeners\AssignmentManagement\SendAssignmentResubmissionRequestedNotification;

// Assessment Management Listeners
use App\Listeners\AssessmentManagement\SendAssessmentScheduledNotification;
use App\Listeners\AssessmentManagement\SendAssessmentRescheduledNotification;

// Communication Listeners
use App\Listeners\Communication\SendEmergencyNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // ==========================================
        // PHASE 1: CRITICAL EVENTS - ATTENDANCE
        // ==========================================
        
        // Event: Student marked absent
        StudentMarkedAbsent::class => [
            SendAbsentNotification::class,
        ],

        // Event: Student marked late
        StudentMarkedLate::class => [
            SendLateArrivalNotification::class,
        ],

        // Event: Low attendance alert (below threshold)
        StudentLowAttendance::class => [
            SendLowAttendanceNotification::class,
        ],

        // Event: Consecutive absences alert (3+ days)
        ConsecutiveAbsencesAlert::class => [
            SendConsecutiveAbsenceNotification::class,
        ],

        // ==========================================
        // PHASE 1: CRITICAL EVENTS - FEE MANAGEMENT
        // ==========================================
        
        // Event: New fee installment becomes due
        FeeInstallmentDue::class => [
            SendFeeDueNotification::class,
        ],

        // Event: Payment received successfully
        PaymentReceived::class => [
            SendPaymentConfirmation::class,
        ],

        // Event: Payment is overdue
        PaymentOverdue::class => [
            SendPaymentOverdueNotification::class,
        ],

        // Event: Payment due tomorrow (reminder)
        PaymentDueTomorrow::class => [
            SendPaymentReminderNotification::class,
        ],

        // ==========================================
        // PHASE 2: ASSIGNMENT MANAGEMENT
        // ==========================================
        
        // Event: New assignment created and published
        AssignmentCreated::class => [
            SendAssignmentCreatedNotification::class,
        ],

        // Event: Student submits assignment
        AssignmentSubmitted::class => [
            SendAssignmentSubmittedNotification::class,
        ],

        // Event: Teacher grades assignment submission
        AssignmentGraded::class => [
            SendAssignmentGradedNotification::class,
        ],

        // Event: Teacher requests resubmission
        AssignmentResubmissionRequested::class => [
            SendAssignmentResubmissionRequestedNotification::class,
        ],

        // ==========================================
        // PHASE 2: ASSESSMENT MANAGEMENT
        // ==========================================
        
        // Event: New assessment scheduled
        AssessmentScheduled::class => [
            SendAssessmentScheduledNotification::class,
        ],

        // Event: Assessment date/time rescheduled
        AssessmentRescheduled::class => [
            SendAssessmentRescheduledNotification::class,
        ],

        // ==========================================
        // PHASE 1: EMERGENCY COMMUNICATION
        // ==========================================
        
        // Event: Emergency alert broadcast
        EmergencyAlert::class => [
            SendEmergencyNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false; // We explicitly define events for better control
    }
}
