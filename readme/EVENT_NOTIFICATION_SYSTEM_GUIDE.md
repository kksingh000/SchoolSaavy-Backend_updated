# Event-Based Notification System Implementation Guide

## Overview

This implementation connects the Event Management system with the Notification system, automatically sending notifications to relevant users when events are created, updated, or cancelled. The system uses Laravel's queue system to handle notifications asynchronously, preventing delays in the user interface.

## Architecture

### Key Components

1. **SendEventNotificationJob** - Queue job that handles sending event notifications
2. **SendEventReminderJob** - Queue job for sending event reminders
3. **EventObserver** - Automatically triggers notifications when events change
4. **NotificationService** - Enhanced to support new target types
5. **Queue System** - Handles asynchronous notification processing

## Features

### Automatic Event Notifications

- **Event Created**: Sent when a new event is published
- **Event Updated**: Sent when significant changes are made to published events
- **Event Cancelled**: Sent when an event is deleted
- **Event Reminders**: Automatically scheduled based on event priority

### Smart Targeting

The system intelligently determines who should receive notifications based on:

- **Target Audience**: `all`, `students`, `teachers`, `parents`, `staff`
- **Affected Classes**: Specific classes involved in the event
- **Event Type**: Different notification priorities for different event types
- **Event Priority**: `low`, `medium`, `high`, `urgent`

### Queue-Based Processing

Notifications are processed asynchronously using Laravel queues:

- **Urgent notifications**: Processed immediately
- **High priority**: 30-second delay
- **Medium priority**: 2-minute delay  
- **Low priority**: 5-minute delay

## Configuration

### Queue Setup

The system uses multiple queues based on priority:

```php
// Queue names by priority
'urgent-notifications'    // Emergency and urgent events
'high-notifications'      // High priority events
'normal-notifications'    // Medium priority events  
'low-notifications'       // Low priority events
'reminder-notifications'  // Event reminders
```

### Environment Variables

Add these to your `.env` file:

```env
QUEUE_CONNECTION=database
# or for Redis
QUEUE_CONNECTION=redis
REDIS_QUEUE=default
```

## Usage

### Starting the Queue Worker

To process notifications, run the queue worker:

```bash
# Process all queues
php artisan queue:work

# Process specific queues with priority
php artisan queue:work --queue=urgent-notifications,high-notifications,normal-notifications,low-notifications,reminder-notifications

# With specific settings
php artisan queue:work --timeout=60 --tries=3 --delay=10
```

### Testing Event Notifications

Use the provided test command:

```bash
# Test notification for a specific event
php artisan test:event-notification {event_id}

# Test different notification types
php artisan test:event-notification 123 --type=created
php artisan test:event-notification 123 --type=updated
php artisan test:event-notification 123 --type=cancelled
```

### Processing Scheduled Notifications

Run scheduled notification processing:

```bash
php artisan notifications:process-scheduled
```

## Notification Types & Content

### Event Created
```
Title: "New [Event Type]: [Event Title]"
Message: "A new [type] has been scheduled for [date] at [time] at [location]. [Acknowledgment notice if required]"
```

### Event Updated
```
Title: "[Event Type] Updated: [Event Title]"
Message: "The [type] scheduled for [date] at [time] at [location] has been updated. Please check the details."
```

### Event Reminder
```
Title: "Reminder: [Event Title] [time_text]"
Message: "Don't forget: [Event Title] is [time_text] on [date] at [time] at [location]."
```

### Event Cancelled
```
Title: "[Event Type] Cancelled: [Event Title]"
Message: "The [type] '[Event Title]' scheduled for [date] has been cancelled."
```

## Target Audience Logic

### Audience to Target Type Mapping

| Event Audience | Target Type | Recipients |
|---------------|-------------|------------|
| `all` | `all_school_users` | All parents and teachers |
| `parents` | `all_parents` | All parents in school |
| `teachers` | `all_teachers` | All teachers in school |
| `parents + teachers` | `all_school_users` | All parents and teachers |
| `students` (with classes) | `class_parents` | Parents of students in affected classes |
| `teachers` (with classes) | `class_teachers` | Teachers assigned to affected classes |
| Multiple (with classes) | `class_all_users` | Both parents and teachers for affected classes |

## Reminder Scheduling

Reminders are automatically scheduled based on event priority:

### 1-Day Reminder
- **When**: Scheduled for all events (except low priority and recurring)
- **Condition**: Event is more than 1 day away

### 3-Hour Reminder  
- **When**: High and urgent priority events only
- **Condition**: Event is more than 3 hours away

### 1-Hour Reminder
- **When**: Urgent priority events only  
- **Condition**: Event is more than 1 hour away

## API Integration

### Device Token Registration

Users must register device tokens to receive push notifications:

```http
POST /api/device/register-token
Authorization: Bearer {token}
Content-Type: application/json

{
    "firebase_token": "device_firebase_token",
    "device_id": "unique_device_identifier", 
    "device_type": "android|ios",
    "device_name": "User Device Name",
    "app_version": "1.0.0"
}
```

### Manual Notification Sending

Send custom notifications via API:

```http
POST /api/admin/notifications/send
Authorization: Bearer {token}
Content-Type: application/json

{
    "title": "Custom Notification",
    "message": "Your custom message here",
    "type": "general",
    "priority": "normal",
    "target_type": "all_parents",
    "data": {
        "custom_field": "custom_value"
    }
}
```

## Monitoring & Debugging

### Queue Monitoring

Monitor queue status:

```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

### Logs

Check Laravel logs for notification processing:

```bash
tail -f storage/logs/laravel.log | grep "notification"
```

Key log messages:
- `"Event notification job dispatched"` - Job queued successfully
- `"Event notification sent successfully"` - Notification processed
- `"Failed to send event notification"` - Processing failed
- `"Event reminders scheduled"` - Reminders queued

### Database Monitoring

Check notification status in database:

```sql
-- Check recent notifications
SELECT * FROM notifications 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
ORDER BY created_at DESC;

-- Check notification deliveries
SELECT n.title, nd.status, nd.user_id, nd.created_at
FROM notifications n
JOIN notification_deliveries nd ON n.id = nd.notification_id
WHERE n.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
ORDER BY nd.created_at DESC;

-- Check queue jobs
SELECT * FROM jobs WHERE queue LIKE '%notification%';
```

## Performance Considerations

### Queue Workers

For production, use multiple queue workers:

```bash
# Run multiple workers for high-priority notifications
php artisan queue:work --queue=urgent-notifications &
php artisan queue:work --queue=high-notifications &
php artisan queue:work --queue=normal-notifications &
```

### Supervisor Configuration

Use Supervisor to manage queue workers:

```ini
[program:schoolsavvy-notifications]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/app/artisan queue:work --queue=urgent-notifications,high-notifications,normal-notifications,low-notifications,reminder-notifications
directory=/path/to/app
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/app/storage/logs/worker.log
```

## Troubleshooting

### Common Issues

1. **Notifications not sending**
   - Check queue worker is running
   - Verify Firebase service is configured
   - Check user has active device tokens

2. **Delayed notifications**
   - Check queue worker capacity
   - Monitor job processing time
   - Consider increasing workers

3. **Missing recipients**
   - Verify user belongs to correct school
   - Check target audience configuration
   - Validate class assignments

### Debug Commands

```bash
# Check event observer is working
php artisan tinker
>>> $event = App\Models\Event::find(1);
>>> $event->touch(); // Should trigger observer

# Test notification service directly
>>> $service = app(App\Services\NotificationService::class);
>>> $service->sendNotification([...]);
```

## Security Considerations

- All notifications are isolated by school (multi-tenant)
- Device tokens are validated and secured
- Target audience validation prevents cross-school notifications
- Queue jobs include proper error handling and retry logic

## Future Enhancements

1. **Email Notifications**: Add email fallback for users without device tokens
2. **SMS Integration**: Support SMS notifications for urgent events
3. **User Preferences**: Allow users to configure notification preferences
4. **Advanced Scheduling**: Support complex reminder schedules
5. **Analytics**: Track notification engagement and delivery rates
