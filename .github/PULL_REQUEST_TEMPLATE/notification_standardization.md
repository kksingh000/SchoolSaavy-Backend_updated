# Notification Job Standardization PR

## Job Information
- **Job Name:** [Name of the notification job]
- **Purpose:** [Brief description of what this notification does]
- **Recipients:** [Who receives this notification]

## Standardization Checklist
- [ ] Uses constructor property promotion
- [ ] Uses dependency injection for NotificationService
- [ ] Has correct handle method signature with void return type
- [ ] Implements proper try-catch block with logging
- [ ] Re-throws exceptions for job retry
- [ ] Follows standard notification data structure
- [ ] Has proper class documentation
- [ ] Uses dedicated method to build message
- [ ] Passes all notification unit tests

## Changes Made
- [List the specific changes made to standardize this job]

## Testing Verification
- [ ] Job can be instantiated with all required parameters
- [ ] Job properly constructs notification data
- [ ] Job properly sends notification when triggered
- [ ] Error handling correctly logs and retries on failure
- [ ] Message is properly formatted with all variable data

## Additional Notes
[Any additional information or special considerations]