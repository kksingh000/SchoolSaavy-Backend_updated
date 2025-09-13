# Firebase Configuration Setup Guide

## Overview

This guide explains how to configure Firebase for push notifications in SchoolSaavy SaaS.

## Prerequisites

1. **Firebase Project**: Create a project in [Firebase Console](https://console.firebase.google.com/)
2. **Service Account**: Generate a service account key file
3. **GitHub Repository Secrets**: Store the service account securely

## Step 1: Generate Firebase Service Account Key

1. Go to your Firebase project in [Firebase Console](https://console.firebase.google.com/)
2. Navigate to **Project Settings** → **Service accounts**
3. Click **Generate new private key**
4. Download the JSON file

## Step 2: Add GitHub Repository Secret

1. Go to your GitHub repository
2. Navigate to **Settings** → **Secrets and variables** → **Actions**
3. Click **New repository secret**
4. Name: `FIREBASE_SERVICE_ACCOUNT`
5. Value: Copy the entire contents of the downloaded JSON file
6. Click **Add secret**

## Step 3: Set Environment Variables

Add these environment variables to your production `.env` file:

```env
# Firebase Configuration
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_SERVICE_ACCOUNT_PATH=/var/www/html/storage/app/firebase-service-account.json
FIREBASE_DATABASE_URL=https://your-project-id-default-rtdb.firebaseio.com/
```

## Step 4: Deploy

The GitHub Actions workflow will automatically:
1. Create `storage/app/firebase-service-account.json` from the secret
2. Set proper file permissions (`chmod 600`)
3. Verify the file exists and contains valid JSON
4. Test the Firebase configuration

## Verification

After deployment, you can verify the configuration by running:

```bash
# SSH into your server
ssh your-user@your-server

# Navigate to the project directory
cd /var/www/schoolsaavy

# Check Firebase configuration
docker compose exec app php artisan firebase:check
```

This command will verify:
- ✅ Project ID configuration
- ✅ Service account file exists and is readable
- ✅ JSON structure is valid
- ✅ All required fields are present
- ✅ Firebase service can be initialized

## File Structure

```
storage/
├── app/
│   └── firebase-service-account.json    # Created during deployment
└── ...
```

## Security Notes

- ⚠️ **Never commit the service account file to Git**
- ✅ File permissions are set to `600` (owner read/write only)
- ✅ File is stored in `storage/app/` which is not web-accessible
- ✅ GitHub secret is encrypted and only accessible during deployment

## Troubleshooting

### Common Issues

1. **Secret not set**
   ```
   ❌ WARNING: FIREBASE_SERVICE_ACCOUNT secret is not set!
   ```
   **Solution**: Add `FIREBASE_SERVICE_ACCOUNT` secret in GitHub repository settings

2. **Invalid JSON**
   ```
   ❌ Service account file is not valid JSON
   ```
   **Solution**: Ensure the GitHub secret contains the complete, unmodified JSON from Firebase

3. **File not found**
   ```
   ❌ Service account file does not exist
   ```
   **Solution**: Check that deployment workflow ran successfully and created the file

4. **Permission denied**
   ```
   ❌ Service account file is not readable
   ```
   **Solution**: File permissions should be automatically set during deployment

### Debug Commands

```bash
# Check if file exists
docker compose exec app ls -la /var/www/html/storage/app/firebase-service-account.json

# Validate JSON structure
docker compose exec app python3 -m json.tool /var/www/html/storage/app/firebase-service-account.json

# Test Firebase service
docker compose exec app php artisan firebase:check

# Check container logs
docker compose logs app
```

## Testing Notifications

Once configured, you can test notifications through:

1. **Admin Panel**: Create and send announcements
2. **API Endpoint**: `POST /api/admin/notifications/send`
3. **Event System**: Notifications are automatically sent for events

## Configuration Reference

### Environment Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `FIREBASE_PROJECT_ID` | Your Firebase project ID | `schoolsaavy-20f0b` |
| `FIREBASE_SERVICE_ACCOUNT_PATH` | Path to service account file | `/var/www/html/storage/app/firebase-service-account.json` |
| `FIREBASE_DATABASE_URL` | Firebase Realtime Database URL | `https://project-id-default-rtdb.firebaseio.com/` |

### Service Account JSON Structure

The service account file should contain:
```json
{
  "type": "service_account",
  "project_id": "your-project-id",
  "private_key_id": "key-id",
  "private_key": "-----BEGIN PRIVATE KEY-----\n...",
  "client_email": "firebase-adminsdk-...@project-id.iam.gserviceaccount.com",
  "client_id": "...",
  "auth_uri": "https://accounts.google.com/o/oauth2/auth",
  "token_uri": "https://oauth2.googleapis.com/token",
  "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
  "client_x509_cert_url": "...",
  "universe_domain": "googleapis.com"
}
```

---

## Need Help?

If you encounter issues:
1. Run `php artisan firebase:check` to diagnose problems
2. Check the deployment logs in GitHub Actions
3. Verify your Firebase project settings
4. Ensure all environment variables are set correctly