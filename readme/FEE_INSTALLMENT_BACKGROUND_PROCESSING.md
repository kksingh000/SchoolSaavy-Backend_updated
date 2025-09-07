# Fee Installment Background Processing

## Overview

The SchoolSavvy platform now uses background job processing for generating fee installments. This improves the performance of the API by offloading time-consuming tasks to a queue worker.

## Implementation

When a student fee plan is created or updated, the system now dispatches a background job (`GenerateStudentFeeInstallments`) instead of generating the installments synchronously. This job is placed in the `fee-processing` queue and processed by a queue worker.

### Key Components

1. **GenerateStudentFeeInstallments Job**
   - Handles the creation of fee installments based on a student fee plan
   - Includes retry logic for handling failures
   - Logs all operations for debugging and monitoring

2. **FeeManagementService**
   - Dispatches the job instead of generating installments synchronously
   - Maintains all other business logic for fee management

## Running the Queue Worker

To process the background jobs, you need to run a queue worker. In production, this should be managed by a process manager like Supervisor.

### Development Environment

```bash
# Run the queue worker
php artisan queue:work --queue=fee-processing,default

# Run with specific options
php artisan queue:work --queue=fee-processing,default --tries=3 --backoff=3
```

### Production Environment

In production, use Supervisor to ensure the queue worker runs continuously. A sample configuration is already included in the Docker setup.

## Monitoring

Failed jobs can be monitored using Laravel's built-in tools:

```bash
# View failed jobs
php artisan queue:failed

# Retry a specific failed job
php artisan queue:retry <job_id>

# Retry all failed jobs
php artisan queue:retry all

# Clear all failed jobs
php artisan queue:flush
```

## Benefits

1. **Improved API Response Time**: API endpoints return faster since installment generation happens asynchronously
2. **Better User Experience**: Users don't have to wait for installments to be generated
3. **Increased Reliability**: Retry logic ensures installments are generated even if there are temporary issues
4. **Scalability**: Queue workers can be scaled independently based on system load

## Technical Notes

- The job is dispatched to the `fee-processing` queue to separate it from other background jobs
- It's configured to retry 3 times with exponential backoff in case of failures
- Detailed logging is implemented for debugging and monitoring
