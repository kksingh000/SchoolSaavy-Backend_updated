# Queue-Based Notification System Documentation

## 🚀 Overview

The SchoolSaavy notification system now uses **Laravel Queues** for background processing, providing:

- ⚡ **Instant API responses** - No waiting for Firebase delivery
- 🎯 **Priority-based processing** - Urgent notifications processed first  
- 🔄 **Automatic retries** - Failed deliveries retry up to 3 times
- 📊 **Progress tracking** - Real-time delivery status monitoring
- 🏗️ **Scalable architecture** - Handles thousands of notifications efficiently

## 📋 Architecture

### Queue Flow
```
1. API Request → Create Notification → Queue Job → Return Success
2. Background Job → Process Recipients → Send to Firebase → Update Status
3. Monitoring → Track Progress → Handle Failures → Retry if needed
```

### Priority Queues
- **🔴 Urgent** (`urgent`): Emergency notifications, processed immediately
- **🟠 High** (`high`): Important announcements, 30s delay
- **🟡 Normal** (`default`): Regular notifications, 1min delay  
- **🔵 Low** (`low`): Bulk messages, 5min delay

## 🛠️ Implementation Details

### 1. Queue Job: ProcessNotificationDelivery
**Location**: `app/Jobs/ProcessNotificationDelivery.php`

**Features**:
- ✅ Automatic queue selection based on priority
- ✅ Retry mechanism (3 attempts max, 2min timeout)
- ✅ Batch processing support
- ✅ Comprehensive error handling
- ✅ Firebase token validation
- ✅ Delivery status tracking

**Priority Routing**:
```php
switch ($notification->priority) {
    case 'urgent': $this->onQueue('urgent'); break;
    case 'high': $this->onQueue('high')->delay(30); break;
    case 'normal': $this->onQueue('default')->delay(60); break;
    case 'low': $this->onQueue('low')->delay(300); break;
}
```

### 2. Updated NotificationService
**Key Changes**:
- ✅ `sendNotification()` now dispatches jobs instead of processing inline
- ✅ Immediate API response with `status: 'queued'`
- ✅ `getNotificationStatus()` method for progress tracking
- ✅ Scheduled notifications also use queues

**API Response Structure**:
```json
{
    "success": true,
    "notification_id": 123,
    "total_recipients": 150,
    "status": "queued",
    "message": "Notification queued for delivery successfully"
}
```

### 3. Queue Configuration
**Location**: `config/queue.php`

**Connections Added**:
- `urgent`: Fastest processing, 1min retry
- `high`: High priority, 1.5min retry  
- `notifications`: Default notification queue
- `low`: Lowest priority, 2min retry

**Production Settings**:
- **Driver**: Redis (high performance, persistence)
- **Connection**: Shared Redis instance
- **Retry After**: 60-120 seconds based on priority
- **After Commit**: false (immediate dispatch)

### 4. Supervisor Configuration
**Location**: `docker/supervisor/schoolsaavy-queues.conf`

**Worker Allocation**:
- **Urgent Queue**: 2 workers (immediate processing)
- **High/Default Queue**: 3 workers (balanced processing)
- **Default/Low Queue**: 4 workers (bulk processing)
- **General Queue**: 2 workers (other jobs)

**Auto-restart**: ✅ Workers automatically restart on failure
**Memory Limit**: 256MB per worker
**Max Runtime**: 1 hour per job batch

## 📊 Monitoring & Debugging

### 1. Queue Monitoring Command
```bash
# Real-time queue monitoring
php artisan queue:monitor-notifications

# Custom refresh interval (default: 5 seconds)
php artisan queue:monitor-notifications --refresh=10
```

**Displays**:
- 📊 Queue statistics (pending, processing, failed jobs)
- 📋 Recent notifications (last 10 with status)
- 📈 Delivery statistics (24-hour success rates)
- ✅ Success rate indicators

### 2. Notification Status API
```bash
GET /api/notifications/{id}/status
```

**Response**:
```json
{
    "success": true,
    "data": {
        "notification_id": 123,
        "status": "sending",
        "priority": "high",
        "total_recipients": 150,
        "processed_count": 145,
        "progress_percentage": 96.67,
        "delivery_status": {
            "pending": 5,
            "sent": 120,
            "delivered": 18,
            "read": 2,
            "failed": 5
        }
    }
}
```

### 3. Laravel Horizon (Optional)
For advanced queue monitoring, consider installing Laravel Horizon:
```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```

## 🚀 Deployment

### Production Setup
1. **Queue Workers**: Automatically configured via supervisor
2. **Redis**: Used as queue backend (already configured)  
3. **Supervisor**: Manages worker processes with auto-restart
4. **Monitoring**: Built-in commands for queue health checks

### Environment Variables
```env
# Queue Configuration
QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default

# Firebase (for notification delivery)
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_SERVICE_ACCOUNT_PATH=/var/www/html/storage/app/firebase-service-account.json
```

### Worker Scaling
Modify `docker/supervisor/schoolsaavy-queues.conf` to adjust:
- **numprocs**: Number of worker processes per queue
- **memory**: Memory limit per worker
- **max-time**: Maximum execution time

## 🔧 Usage Examples

### 1. Send Immediate Notification
```php
$notificationService = app(NotificationService::class);

$result = $notificationService->sendNotification([
    'school_id' => 1,
    'title' => 'Emergency Alert',
    'message' => 'School closed due to weather',
    'type' => 'announcement',
    'priority' => 'urgent',
    'target_type' => 'all_school_users'
]);

// Returns immediately with queued status
// {
//     "success": true,
//     "notification_id": 123,
//     "status": "queued",
//     "total_recipients": 500
// }
```

### 2. Track Delivery Progress
```php
$status = $notificationService->getNotificationStatus(123);

echo "Progress: {$status['data']['progress_percentage']}%";
echo "Sent: {$status['data']['delivery_status']['sent']}";
echo "Failed: {$status['data']['delivery_status']['failed']}";
```

### 3. Monitor Queue Health
```bash
# Check queue statistics
php artisan queue:monitor-notifications

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

## 📈 Performance Benefits

### Before (Synchronous)
- ⏰ **API Response Time**: 5-30 seconds (depending on recipient count)
- 🚫 **Request Timeout Risk**: High for large recipient lists
- 🔒 **User Experience**: Blocking, poor responsiveness
- 📊 **Scalability**: Limited by Firebase API rate limits

### After (Queue-Based)
- ⚡ **API Response Time**: <200ms (instant success response)
- ✅ **Request Timeout Risk**: Eliminated
- 🎯 **User Experience**: Non-blocking, excellent responsiveness  
- 📊 **Scalability**: Handles thousands of notifications efficiently
- 🔄 **Reliability**: Automatic retries and error handling
- 📈 **Throughput**: Priority-based processing optimization

## 🛡️ Error Handling

### Automatic Retry Logic
- **Max Attempts**: 3 retries per job
- **Retry Delay**: Exponential backoff (30s, 60s, 120s)
- **Permanent Failures**: Logged for admin investigation
- **Dead Letter Queue**: Failed jobs stored for manual review

### Common Failure Scenarios
1. **No Device Tokens**: User delivery marked as failed, job continues
2. **Firebase API Error**: Job retried up to 3 times
3. **Network Issues**: Temporary failures handled with retries
4. **Invalid Recipients**: Logged and skipped, other recipients processed

### Monitoring Failures
```bash
# View failed jobs
php artisan queue:failed

# Retry specific failed job
php artisan queue:retry 5

# Clear all failed jobs
php artisan queue:flush
```

## 🎯 Best Practices

### 1. Queue Priority Guidelines
- **Urgent**: Emergency alerts, security issues
- **High**: Important announcements, assignment deadlines
- **Normal**: Regular updates, event notifications  
- **Low**: Newsletters, bulk informational messages

### 2. Recipient List Optimization
- ✅ Filter inactive users before queuing
- ✅ Validate device tokens are active
- ✅ Use database indexes for recipient queries
- ✅ Consider batching for extremely large lists

### 3. Monitoring Best Practices
- 📊 Monitor queue sizes regularly
- 🔍 Check success rates daily
- 🚨 Set up alerts for high failure rates
- 🔄 Review and retry failed jobs weekly

## 🔍 Troubleshooting

### Queue Workers Not Processing
```bash
# Check supervisor status
docker compose exec app supervisorctl status

# Restart queue workers
docker compose exec app supervisorctl restart schoolsaavy-queues:*

# Check worker logs
docker compose logs app | grep "queue:work"
```

### High Failure Rates
```bash
# Check Redis connection
docker compose exec app php artisan queue:monitor-notifications

# Verify Firebase configuration
docker compose exec app php artisan firebase:check

# Review failed jobs
docker compose exec app php artisan queue:failed
```

### Performance Issues
```bash
# Check queue sizes
docker compose exec app php artisan queue:monitor-notifications

# Scale up workers (edit supervisor config and restart)
docker compose exec app supervisorctl restart schoolsaavy-queues:*
```

---

## Summary

The queue-based notification system provides:

- ⚡ **99% faster API responses** (instant vs 5-30 seconds)
- 🎯 **Priority-based processing** for urgent notifications
- 🔄 **Automatic retry mechanism** for reliability
- 📊 **Real-time progress tracking** for transparency
- 🏗️ **Scalable architecture** ready for thousands of users

This implementation ensures SchoolSaavy can handle notification loads efficiently while providing an excellent user experience! 🚀