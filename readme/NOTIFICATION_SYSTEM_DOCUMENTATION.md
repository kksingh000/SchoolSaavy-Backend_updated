# SchoolSavvy Notification System Documentation

## 🔔 Overview

The SchoolSavvy Notification System provides a comprehensive, centralized solution for sending push notifications to parents and teachers. It integrates with Firebase Cloud Messaging (FCM) and includes both in-app notifications and push notifications with detailed delivery tracking.

## 🏗️ System Architecture

### Core Components

1. **Database Layer**
   - `notifications` - Main notification records
   - `notification_deliveries` - Individual delivery tracking
   - `user_device_tokens` - Firebase token management

2. **Service Layer**
   - `NotificationService` - Core business logic
   - `FirebaseService` - Firebase FCM integration

3. **API Layer**
   - `NotificationController` - Admin/teacher endpoints
   - Mobile API endpoints in parent/teacher routes

4. **Background Processing**
   - `ProcessScheduledNotifications` - Queue job
   - Console command for scheduled processing

## 📊 Database Schema

### Notifications Table
```sql
notifications (
    id, school_id, title, message, type, priority,
    sender_id, target_type, target_ids, target_classes,
    total_recipients, sent_count, delivered_count, read_count,
    status, scheduled_at, data, firebase_tokens,
    firebase_message_id, firebase_response, timestamps
)
```

### Notification Deliveries Table
```sql
notification_deliveries (
    id, notification_id, user_id, firebase_token, status,
    sent_at, delivered_at, read_at, acknowledged_at,
    firebase_response, error_message, retry_count, timestamps
)
```

### User Device Tokens Table
```sql
user_device_tokens (
    id, user_id, device_id, firebase_token, device_type,
    app_version, device_name, is_active, last_used_at, timestamps
)
```

## 🎯 Notification Types

- **General** - General announcements and communications
- **Assignment** - Assignment notifications and updates
- **Assessment** - Test/exam notifications and results
- **Attendance** - Attendance alerts and reports
- **Event** - School events and activities
- **Fee** - Fee reminders and payment notifications
- **Result** - Academic results and report cards
- **Announcement** - Important school announcements

## 🎪 Target Types

- **All Parents** - Send to all parents in the school
- **All Teachers** - Send to all teachers in the school
- **Specific Users** - Send to selected individual users
- **Class Parents** - Send to parents of students in specific classes
- **Class Teachers** - Send to teachers assigned to specific classes

## 📱 Priority Levels

- **Low** - Non-urgent information
- **Normal** - Standard communications (default)
- **High** - Important notifications requiring attention
- **Urgent** - Critical notifications requiring immediate attention

## 🔧 Setup Instructions

### 1. Database Migration
```bash
php artisan migrate
```

### 2. Firebase Configuration
1. Create Firebase project at [Firebase Console](https://console.firebase.google.com)
2. Generate service account key
3. Place service account JSON in `storage/app/firebase-service-account.json`
4. Add to `.env`:
```env
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_SERVICE_ACCOUNT_PATH=storage/app/firebase-service-account.json
FIREBASE_DATABASE_URL=https://your-project.firebaseio.com
```

### 3. Install Firebase PHP SDK
```bash
composer require google/apiclient
composer require guzzlehttp/guzzle
```

### 4. Queue Configuration
```bash
php artisan queue:work --queue=default
```

### 5. Scheduled Notifications (Optional)
Add to `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('notifications:process')->everyMinute();
}
```

## 📡 API Endpoints

### Admin/Teacher APIs

#### Send Notification
```http
POST /api/notifications/send
Content-Type: application/json

{
    "title": "Assignment Reminder",
    "message": "Math homework due tomorrow",
    "type": "assignment",
    "priority": "normal",
    "target_type": "class_parents",
    "target_classes": [1, 2, 3],
    "data": {
        "assignment_id": "123",
        "due_date": "2024-01-15"
    }
}
```

#### Schedule Notification
```http
POST /api/notifications/schedule
Content-Type: application/json

{
    "title": "Parent-Teacher Meeting",
    "message": "PTM scheduled for next week",
    "type": "event",
    "priority": "high",
    "target_type": "all_parents",
    "scheduled_at": "2024-01-20 09:00:00"
}
```

#### Get Notifications
```http
GET /api/notifications?page=1&per_page=15&type=assignment&status=sent
```

#### Get Statistics
```http
GET /api/notifications/stats?date_from=2024-01-01&date_to=2024-01-31
```

### Mobile APIs (Parents/Teachers)

#### Get User Notifications
```http
GET /api/parent/notifications?unread_only=true&per_page=10
```

#### Register Device Token
```http
POST /api/parent/device/register-token
Content-Type: application/json

{
    "device_id": "unique-device-identifier",
    "firebase_token": "fcm-token-from-client",
    "device_type": "android",
    "app_version": "1.2.0",
    "device_name": "Samsung Galaxy"
}
```

#### Mark as Read
```http
PATCH /api/parent/notifications/123/read
```

#### Get Unread Count
```http
GET /api/parent/notifications/unread-count
```

## 🚀 Usage Examples

### Sending Immediate Notification

```php
use App\Services\NotificationService;

$notificationService = app(NotificationService::class);

$result = $notificationService->sendNotification([
    'school_id' => 1,
    'title' => 'School Closure',
    'message' => 'School will be closed tomorrow due to weather',
    'type' => 'announcement',
    'priority' => 'urgent',
    'target_type' => 'all_parents',
    'data' => ['closure_date' => '2024-01-15']
]);
```

### Scheduling Notification

```php
$result = $notificationService->scheduleNotification([
    'school_id' => 1,
    'title' => 'Fee Reminder',
    'message' => 'Monthly fee payment due in 3 days',
    'type' => 'fee',
    'priority' => 'normal',
    'target_type' => 'all_parents',
    'scheduled_at' => '2024-01-12 10:00:00'
]);
```

### Registering Device Token

```php
$result = $notificationService->registerDeviceToken([
    'user_id' => 123,
    'device_id' => 'unique-device-id',
    'firebase_token' => 'fcm-token',
    'device_type' => 'android'
]);
```

## 🔄 Notification Flow

1. **Creation** - Admin/teacher creates notification via API
2. **Target Resolution** - System identifies recipient users
3. **Token Retrieval** - Active Firebase tokens fetched
4. **Firebase Delivery** - Notification sent to FCM
5. **Status Tracking** - Delivery status recorded
6. **User Interaction** - Read/acknowledge status updated

## 📊 Delivery Tracking

The system tracks detailed delivery metrics:
- **Total Recipients** - Number of users targeted
- **Sent Count** - Successfully sent to Firebase
- **Delivered Count** - Delivered to devices
- **Read Count** - Opened by users
- **Delivery Rate** - Success percentage
- **Read Rate** - Engagement percentage

## 🔍 Monitoring & Analytics

### Notification Statistics
```http
GET /api/notifications/stats
```
Returns:
- Total notifications sent
- Delivery rates by type
- Read rates and engagement
- Failed notification counts
- Performance metrics

### Individual Delivery Tracking
```http
GET /api/notifications/123
```
Includes detailed delivery information for each recipient.

## 🛠️ Troubleshooting

### Common Issues

1. **Firebase Token Invalid**
   - User needs to refresh app and re-register token
   - Check Firebase project configuration

2. **No Device Tokens Found**
   - User hasn't registered for notifications
   - Device token might be expired

3. **High Failure Rate**
   - Check Firebase service account permissions
   - Verify project ID and configuration

4. **Scheduled Notifications Not Sending**
   - Ensure queue worker is running
   - Check console command scheduling

### Debug Commands

```bash
# Process scheduled notifications manually
php artisan notifications:process

# Check queue jobs
php artisan queue:work --verbose

# Test Firebase connectivity
php artisan tinker
> app(\App\Services\FirebaseService::class)->validateToken('test-token')
```

## 🔐 Security Considerations

1. **Authentication** - All endpoints require proper authentication
2. **School Isolation** - Users can only access their school's notifications
3. **Permission Checks** - Only admins/teachers can send notifications
4. **Token Management** - Device tokens are securely stored and managed
5. **Data Validation** - All inputs are properly validated

## 🚀 Performance Optimizations

1. **Batch Processing** - Multiple tokens processed efficiently
2. **Queue Jobs** - Background processing for large notifications
3. **Caching** - Statistics and counts cached for performance
4. **Database Indexing** - Optimized queries with proper indexes
5. **Firebase Batching** - Efficient FCM API usage

## 📱 Mobile Integration

### Android Setup
```kotlin
// Add to dependencies
implementation 'com.google.firebase:firebase-messaging:23.0.0'

// Register token
FirebaseMessaging.getInstance().token
    .addOnCompleteListener { task ->
        val token = task.result
        // Send to API
    }
```

### iOS Setup
```swift
// Add to dependencies
import FirebaseMessaging

// Register token
Messaging.messaging().token { token, error in
    // Send to API
}
```

## 🔄 Future Enhancements

1. **Rich Notifications** - Images, buttons, actions
2. **Notification Templates** - Pre-defined templates
3. **A/B Testing** - Compare notification effectiveness
4. **Analytics Dashboard** - Visual analytics and reporting
5. **Localization** - Multi-language support
6. **Advanced Targeting** - More granular targeting options

## 📝 API Response Examples

### Successful Notification Send
```json
{
    "status": "success",
    "message": "Notification sent successfully",
    "data": {
        "notification_id": 123,
        "total_recipients": 45,
        "sent_count": 43,
        "failed_count": 2
    }
}
```

### Notification List Response
```json
{
    "status": "success",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 123,
                "title": "Assignment Reminder",
                "message": "Math homework due tomorrow",
                "type": "assignment",
                "priority": "normal",
                "status": "sent",
                "delivery_rate": 95.5,
                "read_rate": 78.2,
                "created_at": "2024-01-10 10:30:00"
            }
        ],
        "total": 150,
        "per_page": 15
    }
}
```

This comprehensive notification system provides SchoolSavvy with professional-grade communication capabilities, ensuring reliable delivery of important school information to parents and teachers.
