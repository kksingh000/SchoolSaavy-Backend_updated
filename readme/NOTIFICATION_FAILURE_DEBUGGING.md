# Notification Failure Debugging Guide

## Common Notification Failure Issues & Solutions

### 🔥 **Firebase Configuration Issues**

#### **1. Missing Firebase Service Account**
```bash
# Check if service account file exists
php artisan tinker
>>> file_exists(config('services.firebase.service_account_path'))
# Should return true

# If false, create the service account file:
# 1. Go to Firebase Console > Project Settings > Service Accounts
# 2. Click "Generate new private key" 
# 3. Save as storage/app/firebase-service-account.json
```

#### **2. Missing Environment Variables**
```env
# Add to .env file:
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_SERVICE_ACCOUNT_PATH=/path/to/firebase-service-account.json
```

#### **3. Test Firebase Connection**
```bash
php artisan tinker
>>> $firebase = app(\App\Services\FirebaseService::class)
>>> $result = $firebase->sendToToken('test-token', ['title' => 'Test', 'body' => 'Test message'])
>>> dd($result)
```

### 📱 **Device Token Issues**

#### **1. Invalid/Expired Tokens**
Most common cause of notification failures:
```sql
-- Check for invalid tokens in database
SELECT COUNT(*) FROM user_device_tokens WHERE firebase_token IS NULL OR firebase_token = '';

-- Check token activity
SELECT 
    COUNT(*) as total_tokens,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_tokens,
    SUM(CASE WHEN last_used_at < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as stale_tokens
FROM user_device_tokens;
```

#### **2. Token Validation Script**
```bash
# Create artisan command to validate tokens
php artisan make:command ValidateDeviceTokens
```

### 🔍 **Debug Notification Failures**

#### **1. Enable Detailed Logging**
```php
// Add to NotificationService.php in sendFirebaseNotification method
Log::info('Sending Firebase notification', [
    'notification_id' => $notification->id,
    'tokens_count' => count($tokens),
    'tokens' => $tokens,
    'notification_data' => [
        'title' => $notification->title,
        'body' => $notification->message,
        'data' => $firebaseData
    ]
]);
```

#### **2. Check Delivery Status**
```sql
-- Check notification delivery statistics
SELECT 
    n.id,
    n.title,
    n.status,
    n.total_recipients,
    COUNT(nd.id) as deliveries_created,
    SUM(CASE WHEN nd.status = 'sent' THEN 1 ELSE 0 END) as sent_count,
    SUM(CASE WHEN nd.status = 'failed' THEN 1 ELSE 0 END) as failed_count,
    SUM(CASE WHEN nd.status = 'pending' THEN 1 ELSE 0 END) as pending_count
FROM notifications n
LEFT JOIN notification_deliveries nd ON n.id = nd.notification_id
WHERE n.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY n.id
ORDER BY n.created_at DESC;
```

#### **3. Check Failed Deliveries**
```sql
-- Get detailed failure reasons
SELECT 
    nd.id,
    nd.notification_id,
    nd.user_id,
    nd.status,
    nd.failure_reason,
    nd.sent_at,
    nd.created_at,
    u.name as user_name,
    u.email,
    n.title as notification_title
FROM notification_deliveries nd
JOIN users u ON nd.user_id = u.id
JOIN notifications n ON nd.notification_id = n.id
WHERE nd.status = 'failed'
ORDER BY nd.created_at DESC
LIMIT 20;
```

### 🛠️ **Common Error Messages & Solutions**

#### **"No active device tokens found"**
```php
// Solution: Ensure users have registered device tokens
$tokensCount = \App\Models\UserDeviceToken::where('is_active', true)->count();
echo "Active tokens in database: " . $tokensCount;

// Register device token for testing:
\App\Models\UserDeviceToken::create([
    'user_id' => 1,
    'device_id' => 'test-device',
    'firebase_token' => 'your-firebase-token',
    'device_type' => 'android',
    'is_active' => true,
    'last_used_at' => now()
]);
```

#### **"Firebase service account file not found"**
```bash
# Check file path
ls -la storage/app/firebase-service-account.json

# Create if missing:
# 1. Firebase Console > Project Settings > Service Accounts
# 2. Generate new private key
# 3. Save to storage/app/firebase-service-account.json
# 4. Set proper permissions: chmod 644 storage/app/firebase-service-account.json
```

#### **"Failed to get access token"**
```bash
# Check service account permissions:
# 1. Firebase Console > IAM & Admin
# 2. Ensure service account has "Firebase Admin SDK Administrator Service Agent" role
# 3. Verify project ID in service account JSON matches FIREBASE_PROJECT_ID
```

#### **"HTTP Error: 403 Forbidden"**
```bash
# Enable Firebase Cloud Messaging API:
# 1. Google Cloud Console > APIs & Services > Library
# 2. Search "Firebase Cloud Messaging API"
# 3. Click Enable
```

#### **"HTTP Error: 400 Bad Request - Invalid registration token"**
```php
// Clean up invalid tokens
\App\Models\UserDeviceToken::where('is_active', true)
    ->chunk(100, function ($tokens) {
        foreach ($tokens as $token) {
            $firebase = app(\App\Services\FirebaseService::class);
            $isValid = $firebase->validateToken($token->firebase_token);
            
            if (!$isValid) {
                $token->update(['is_active' => false]);
                Log::info('Deactivated invalid token', ['token_id' => $token->id]);
            }
        }
    });
```

### 📊 **Notification Health Check**

Create a health check endpoint:

```php
// Add to NotificationController.php
public function healthCheck()
{
    $checks = [
        'firebase_config' => $this->checkFirebaseConfig(),
        'service_account' => $this->checkServiceAccount(),
        'device_tokens' => $this->checkDeviceTokens(),
        'recent_deliveries' => $this->checkRecentDeliveries()
    ];
    
    return response()->json([
        'status' => collect($checks)->every(fn($check) => $check['status'] === 'ok') ? 'healthy' : 'issues',
        'checks' => $checks,
        'timestamp' => now()
    ]);
}

private function checkFirebaseConfig(): array
{
    try {
        $projectId = config('services.firebase.project_id');
        $serviceAccountPath = config('services.firebase.service_account_path');
        
        return [
            'status' => $projectId && $serviceAccountPath ? 'ok' : 'error',
            'details' => [
                'project_id_set' => !empty($projectId),
                'service_account_path_set' => !empty($serviceAccountPath)
            ]
        ];
    } catch (\Exception $e) {
        return ['status' => 'error', 'error' => $e->getMessage()];
    }
}

private function checkServiceAccount(): array
{
    try {
        $path = config('services.firebase.service_account_path');
        $exists = file_exists($path);
        
        return [
            'status' => $exists ? 'ok' : 'error',
            'details' => [
                'file_exists' => $exists,
                'path' => $path,
                'readable' => $exists && is_readable($path)
            ]
        ];
    } catch (\Exception $e) {
        return ['status' => 'error', 'error' => $e->getMessage()];
    }
}

private function checkDeviceTokens(): array
{
    try {
        $total = \App\Models\UserDeviceToken::count();
        $active = \App\Models\UserDeviceToken::where('is_active', true)->count();
        $recent = \App\Models\UserDeviceToken::where('last_used_at', '>', now()->subDays(7))->count();
        
        return [
            'status' => $active > 0 ? 'ok' : 'warning',
            'details' => [
                'total_tokens' => $total,
                'active_tokens' => $active,
                'recently_used' => $recent
            ]
        ];
    } catch (\Exception $e) {
        return ['status' => 'error', 'error' => $e->getMessage()];
    }
}

private function checkRecentDeliveries(): array
{
    try {
        $recent = \App\Models\NotificationDelivery::where('created_at', '>', now()->subHour())->count();
        $failed = \App\Models\NotificationDelivery::where('created_at', '>', now()->subHour())
            ->where('status', 'failed')->count();
        
        $failureRate = $recent > 0 ? ($failed / $recent) * 100 : 0;
        
        return [
            'status' => $failureRate < 50 ? 'ok' : 'warning',
            'details' => [
                'recent_deliveries' => $recent,
                'failed_deliveries' => $failed,
                'failure_rate_percent' => round($failureRate, 2)
            ]
        ];
    } catch (\Exception $e) {
        return ['status' => 'error', 'error' => $e->getMessage()];
    }
}
```

### 🚀 **Quick Fix Commands**

```bash
# 1. Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# 2. Check notification status
php artisan tinker
>>> \App\Models\Notification::where('status', 'failed')->count()

# 3. Retry failed notifications
php artisan tinker
>>> $notification = \App\Models\Notification::where('status', 'failed')->first()
>>> app(\App\Http\Controllers\NotificationController::class)->retryFailedNotification($notification->id)

# 4. Test Firebase connection
php artisan tinker
>>> $firebase = app(\App\Services\FirebaseService::class)
>>> $result = $firebase->sendToTopic('test', ['title' => 'Test', 'body' => 'Connection test'])
>>> dd($result)
```

### 🔧 **Environment Setup**

Make sure these are properly configured:

```env
# Required Firebase Settings
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_SERVICE_ACCOUNT_PATH=storage/app/firebase-service-account.json

# Optional but recommended
LOG_LEVEL=debug
DB_LOG_SLOW_QUERIES=true
```

### 📝 **Testing Notification Flow**

```bash
# Test complete notification flow
php artisan tinker

# 1. Create a test notification
$data = [
    'title' => 'Test Notification',
    'message' => 'Testing notification system',
    'type' => 'general',
    'priority' => 'normal',
    'target_type' => 'specific_users',
    'target_ids' => [1], // Replace with actual user ID
    'school_id' => 1     // Replace with actual school ID
];

# 2. Send notification
$service = app(\App\Services\NotificationService::class);
$result = $service->sendNotification($data);

# 3. Check result
dd($result);

# 4. Check delivery status
$notification = \App\Models\Notification::latest()->first();
$deliveries = $notification->deliveries;
foreach($deliveries as $delivery) {
    echo "User {$delivery->user_id}: {$delivery->status} - {$delivery->failure_reason}\n";
}
```

This guide covers the most common notification failure scenarios. Run through these checks to identify and fix the specific issue causing your notification failures.
