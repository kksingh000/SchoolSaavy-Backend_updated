# 🚀 Laravel Queue-Based Promotion System

## 🎯 Overview

The SchoolSavvy promotion system has been upgraded to use **Laravel Queues** for background processing, eliminating timeout issues and providing real-time progress tracking for bulk operations.

---

## ⚡ **Queue Architecture**

### **🔧 Queue Configuration**

```php
// config/queue.php
'default' => env('QUEUE_CONNECTION', 'database'),

// Promotion-specific queues
'promotion-evaluation'   // For bulk student evaluation
'promotion-application'  // For applying promotion decisions
```

### **📋 Queue Jobs**

1. **`ProcessBulkPromotionEvaluation`** - Evaluates hundreds of students in background
2. **`ProcessPromotionApplication`** - Applies promotion decisions (moves students to new classes)

---

## 🔄 **How Queue Processing Works**

### **1. Bulk Evaluation Process**

```php
// When admin clicks "Start Bulk Evaluation"
POST /api/promotions/bulk-evaluate
{
    "academic_year_id": 1,
    "class_ids": [10, 11, 12]
}
```

**What happens:**
1. ✅ **Immediate Response** - Creates `PromotionBatch` with status "queued"
2. ✅ **Queue Job Dispatched** - `ProcessBulkPromotionEvaluation` added to `promotion-evaluation` queue
3. ✅ **Background Processing** - Laravel queue worker processes students individually
4. ✅ **Real-time Updates** - Progress updated every 10 students in database
5. ✅ **Frontend Polling** - UI polls progress API every 5 seconds

```php
// Service method (now queue-based)
public function bulkEvaluateStudents($academicYearId, $classIds = null, $userId = null)
{
    // Create batch record immediately
    $batch = PromotionBatch::create([
        'school_id' => $schoolId,
        'academic_year_id' => $academicYearId,
        'status' => 'queued'  // Initially queued
    ]);

    // Dispatch to queue for background processing
    ProcessBulkPromotionEvaluation::dispatch(
        $batch->id, $academicYearId, $classIds, $userId, $schoolId
    )->onQueue('promotion-evaluation');

    return $batch; // Immediate response
}
```

### **2. Queue Job Processing**

```php
// ProcessBulkPromotionEvaluation Job
public function handle(PromotionService $promotionService)
{
    $batch = PromotionBatch::findOrFail($this->batchId);
    
    // Mark as processing
    $batch->markAsStarted($this->userId);
    
    // Get students
    $students = Student::where('school_id', $this->schoolId)
        ->whereHas('classes', function ($query) {
            $query->where('class_student.academic_year_id', $this->academicYearId)
                  ->where('class_student.is_active', true);
        })->get();
    
    // Process each student individually
    foreach ($students as $student) {
        $promotion = $promotionService->evaluateStudentForBatch(
            $student->id, $this->academicYearId, $this->userId
        );
        
        // Update progress every 10 students
        if ($processed % 10 === 0) {
            $batch->updateProgress($processed, $promoted, $failed, $pending);
            $batch->addToProcessingLog("Processed {$processed}/{$totalStudents}");
        }
    }
    
    // Mark as completed
    $batch->markAsCompleted();
}
```

---

## 📊 **Real-time Progress Tracking**

### **Status Flow:**

```
queued → processing → completed/failed
```

### **Progress API Endpoints:**

```http
# Get all batches with real-time status
GET /api/promotions/batches/{academicYearId}?status=processing

# Get detailed progress for specific batch
GET /api/promotions/batches/{batchId}/progress
```

**Response includes:**
```json
{
    "status": "success",
    "data": {
        "id": 15,
        "batch_name": "Bulk Evaluation - 2025-08-24 14:30",
        "status": "processing",        // queued/processing/completed/failed
        "total_students": 250,
        "processed_students": 180,     // Real-time counter
        "promoted_students": 120,
        "failed_students": 35,
        "pending_students": 25,
        "progress_percentage": 72.0,   // Calculated in real-time
        "processing_time": "15 minutes 32 seconds",
        "recent_logs": [
            {
                "timestamp": "2025-08-24T14:45:15Z",
                "type": "info", 
                "message": "Processed 180/250 students"
            }
        ]
    }
}
```

---

## 🛠️ **Queue Worker Management**

### **Starting Queue Workers**

```bash
# Start all promotion queues
php artisan queue:work-promotions

# Or start specific queues manually
php artisan queue:work --queue=promotion-evaluation --timeout=3600
php artisan queue:work --queue=promotion-application --timeout=1800
```

### **Supervisor Configuration (Production)**

```bash
# /etc/supervisor/conf.d/laravel-promotion-worker.conf
[program:laravel-promotion-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/schoolsavvy/artisan queue:work database --queue=promotion-evaluation,promotion-application --sleep=3 --tries=3 --max-time=3600
directory=/var/www/schoolsavvy
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/schoolsavvy/storage/logs/worker.log
```

---

## 🎮 **Frontend Implementation (Updated)**

### **React Queue Status Component**

```jsx
import React, { useState, useEffect } from 'react';

const QueuedPromotionTracker = ({ batchId }) => {
    const [batch, setBatch] = useState(null);
    const [isPolling, setIsPolling] = useState(true);

    useEffect(() => {
        const fetchBatch = async () => {
            const response = await fetch(`/api/promotions/batches/${batchId}/progress`);
            const data = await response.json();
            setBatch(data.data);

            // Stop polling if completed or failed
            if (['completed', 'failed'].includes(data.data.status)) {
                setIsPolling(false);
            }
        };

        fetchBatch();

        if (isPolling) {
            const interval = setInterval(fetchBatch, 5000); // Poll every 5 seconds
            return () => clearInterval(interval);
        }
    }, [batchId, isPolling]);

    if (!batch) return <div>Loading batch status...</div>;

    return (
        <div className="queued-promotion-tracker">
            <div className="status-header">
                <h3>{batch.batch_name}</h3>
                <StatusBadge status={batch.status} />
            </div>

            {/* Queue Status */}
            {batch.status === 'queued' && (
                <div className="queue-status">
                    <div className="queue-indicator">
                        <div className="spinner"></div>
                        <span>Queued for processing...</span>
                    </div>
                    <p>Your bulk evaluation has been queued. Processing will start shortly.</p>
                </div>
            )}

            {/* Processing Status */}
            {batch.status === 'processing' && (
                <div className="processing-status">
                    <ProgressBar 
                        percentage={batch.progress_percentage}
                        current={batch.processed_students}
                        total={batch.total_students}
                    />
                    
                    <div className="stats-grid">
                        <StatCard label="Promoted" value={batch.promoted_students} color="green" />
                        <StatCard label="Failed" value={batch.failed_students} color="red" />
                        <StatCard label="Pending" value={batch.pending_students} color="yellow" />
                    </div>

                    <div className="processing-info">
                        <span>⏱️ {batch.processing_time}</span>
                        <span>🔄 Processing at {batch.processing_rate} students/min</span>
                    </div>
                </div>
            )}

            {/* Completion Status */}
            {batch.status === 'completed' && (
                <div className="completion-status">
                    <div className="success-message">
                        ✅ Bulk evaluation completed successfully!
                    </div>
                    <CompletionSummary batch={batch} />
                </div>
            )}

            {/* Error Status */}
            {batch.status === 'failed' && (
                <div className="error-status">
                    <div className="error-message">
                        ❌ Bulk evaluation failed
                    </div>
                    <ErrorSummary errors={batch.error_summary} />
                </div>
            )}
        </div>
    );
};
```

---

## ⚡ **Performance Benefits**

### **Before (Synchronous Processing):**
- ❌ **Request Timeout**: 30-60 seconds max execution time
- ❌ **UI Blocking**: Users had to wait for completion
- ❌ **Memory Issues**: Processing all students in single request
- ❌ **No Progress**: No way to track progress during processing
- ❌ **Error Recovery**: Single failure could break entire batch

### **After (Queue-based Processing):**
- ✅ **No Timeouts**: Background processing with unlimited time
- ✅ **Immediate Response**: User gets batch ID instantly
- ✅ **Memory Efficient**: Students processed individually
- ✅ **Real-time Progress**: Live updates every 10 students
- ✅ **Error Handling**: Individual student failures don't break batch
- ✅ **Retry Logic**: Failed jobs automatically retried 3 times
- ✅ **Scalable**: Multiple workers can process different batches

---

## 📋 **API Changes Summary**

### **Bulk Evaluation Endpoint (Updated)**

```http
POST /api/promotions/bulk-evaluate
```

**Before Response:**
```json
{
    "status": "success",
    "data": {
        // Complete batch results after 30-60 seconds
        "id": 15,
        "status": "completed",
        "processed_students": 250,
        "promoted_students": 180
    }
}
```

**After Response (Immediate):**
```json
{
    "status": "success", 
    "message": "Bulk evaluation started successfully",
    "data": {
        "id": 15,
        "batch_name": "Bulk Evaluation - 2025-08-24 14:30",
        "status": "queued",           // ← Queued for background processing
        "total_students": 0,          // Will be updated when processing starts
        "created_at": "2025-08-24T14:30:00Z"
    }
}
```

### **Progress Tracking (New)**

```http
# Real-time progress polling
GET /api/promotions/batches/{batchId}/progress

# Returns live status every 5 seconds:
{
    "status": "processing",
    "processed_students": 180,    // Updates in real-time
    "progress_percentage": 72.0,
    "processing_time": "15 minutes",
    "recent_logs": [...]
}
```

---

## 🚀 **Production Deployment**

### **Queue Worker Setup**

1. **Install Supervisor**:
```bash
sudo apt install supervisor
```

2. **Configure Worker**:
```bash
sudo nano /etc/supervisor/conf.d/schoolsavvy-promotion-worker.conf
```

3. **Start Workers**:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start schoolsavvy-promotion-worker:*
```

### **Monitoring Commands**

```bash
# Check queue status
php artisan queue:monitor promotion-evaluation,promotion-application

# View failed jobs
php artisan queue:failed

# Restart queue workers  
php artisan queue:restart

# Process specific batch
php artisan queue:work --queue=promotion-evaluation --once
```

---

## ✅ **Testing the Queue System**

### **1. Test Bulk Evaluation**

```bash
# Start queue worker
php artisan queue:work --queue=promotion-evaluation

# In another terminal, make API request
curl -X POST http://localhost:8080/api/promotions/bulk-evaluate \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"academic_year_id": 1}'
```

### **2. Monitor Progress**

```bash
# Check batch progress
curl -X GET http://localhost:8080/api/promotions/batches/1/progress \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### **3. Verify Queue Processing**

```bash
# Check queue table
php artisan queue:work --queue=promotion-evaluation --once

# View logs
tail -f storage/logs/laravel.log
```

---

## 🎯 **Summary**

**✅ Queue-Based Benefits:**
- **No more timeouts** - Unlimited processing time
- **Immediate response** - Users get instant feedback
- **Real-time progress** - Live updates every 10 students
- **Better error handling** - Individual failures don't break batch
- **Scalable processing** - Multiple workers can run simultaneously
- **Resource efficient** - Memory usage optimized
- **Production ready** - Supervisor integration for reliability

**🔧 Technical Implementation:**
- Laravel Queue jobs with database driver
- Background processing with progress tracking
- Real-time API updates every 10 students
- Comprehensive error logging and recovery
- Frontend polling for live status updates
- Production-ready worker management

**The promotion system is now enterprise-ready with queue-based processing and real-time progress tracking!** 🚀
