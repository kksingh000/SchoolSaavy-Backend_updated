# Token Renewal & Management Guide

## 🔄 Token Renewal System

Your API now supports comprehensive token management with multiple renewal strategies:

## 📋 Available Endpoints

### 1. **Login** (Get Initial Token)
```bash
POST /api/auth/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "password",
    "user_type": "admin"
}
```

**Response:**
```json
{
    "message": "Login successful",
    "user": { ... },
    "token": "1|abc123...",
    "expires_at": "2025-08-02T08:30:00.000000Z"
}
```

### 2. **Manual Token Refresh**
```bash
POST /api/auth/refresh
Authorization: Bearer 1|abc123...
```

**Response:**
```json
{
    "message": "Token refreshed successfully",
    "user": { ... },
    "token": "2|def456...",
    "expires_at": "2025-08-02T08:30:00.000000Z"
}
```

### 3. **Check Token Status**
```bash
GET /api/auth/check
Authorization: Bearer 1|abc123...
```

**Response:**
```json
{
    "valid": true,
    "user": { ... },
    "token_expires_at": "2025-08-02T08:30:00.000000Z",
    "expires_in_minutes": 120
}
```

### 4. **User Info**
```bash
GET /api/auth/me
Authorization: Bearer 1|abc123...
```

### 5. **Logout** (Revoke Token)
```bash
POST /api/auth/logout
Authorization: Bearer 1|abc123...
```

## 🔄 Token Renewal Strategies

### **Strategy 1: Manual Refresh**
Client explicitly calls `/api/auth/refresh` when needed:

```javascript
// Check if token expires soon
const response = await fetch('/api/auth/check', {
    headers: { 'Authorization': `Bearer ${token}` }
});

const { expires_in_minutes } = await response.json();

// Refresh if expires within 30 minutes
if (expires_in_minutes < 30) {
    const refreshResponse = await fetch('/api/auth/refresh', {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` }
    });
    
    const { token: newToken } = await refreshResponse.json();
    localStorage.setItem('token', newToken);
}
```

### **Strategy 2: Automatic Refresh (Optional Middleware)**
The `RefreshTokenIfNeeded` middleware can automatically refresh tokens:

```php
// Add to protected routes in routes/api.php
Route::middleware(['auth:sanctum', 'refresh.token'])->group(function () {
    // Your protected routes
});
```

When enabled, responses include headers:
- `X-New-Token`: New token if refreshed
- `X-Token-Refreshed`: true if token was refreshed
- `X-Token-Expires-At`: New expiration time

### **Strategy 3: Interceptor Pattern**
Use HTTP interceptors to handle token refresh automatically:

```javascript
// Axios interceptor example
axios.interceptors.response.use(
    (response) => {
        // Check for new token in headers
        const newToken = response.headers['x-new-token'];
        if (newToken) {
            localStorage.setItem('token', newToken);
        }
        return response;
    },
    async (error) => {
        if (error.response?.status === 401) {
            // Token expired, try to refresh
            try {
                const refreshResponse = await axios.post('/api/auth/refresh');
                const { token } = refreshResponse.data;
                localStorage.setItem('token', token);
                
                // Retry original request
                error.config.headers.Authorization = `Bearer ${token}`;
                return axios.request(error.config);
            } catch (refreshError) {
                // Refresh failed, redirect to login
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);
```

## ⚙️ Configuration

### Token Expiration Settings:
```bash
# .env
SANCTUM_TOKEN_EXPIRATION=1440  # 24 hours (in minutes)
```

### Middleware Auto-Refresh Settings:
- **Refresh Threshold**: 2 hours before expiration
- **Only on Success**: Only refreshes on 200 responses
- **Headers**: New token provided in response headers

## 🔒 Security Features

1. **Old Token Invalidation**: When refreshing, old token is immediately deleted
2. **User Validation**: Refresh checks if user is still active
3. **Expiration Tracking**: Exact expiration times provided
4. **Graceful Handling**: Proper error responses for invalid/expired tokens

## 📱 Client Implementation Examples

### React/JavaScript
```javascript
class TokenManager {
    static async refreshToken() {
        const token = localStorage.getItem('token');
        const response = await fetch('/api/auth/refresh', {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` }
        });
        
        if (response.ok) {
            const { token: newToken } = await response.json();
            localStorage.setItem('token', newToken);
            return newToken;
        }
        
        throw new Error('Token refresh failed');
    }
    
    static async checkTokenValidity() {
        const token = localStorage.getItem('token');
        const response = await fetch('/api/auth/check', {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        
        return response.ok;
    }
}
```

### Flutter/Dart
```dart
class TokenService {
  static Future<String> refreshToken() async {
    final token = await storage.read(key: 'token');
    final response = await dio.post('/api/auth/refresh',
      options: Options(headers: {'Authorization': 'Bearer $token'})
    );
    
    if (response.statusCode == 200) {
      final newToken = response.data['token'];
      await storage.write(key: 'token', value: newToken);
      return newToken;
    }
    
    throw Exception('Token refresh failed');
  }
}
```

## 🚨 Error Handling

### Token Expired (401)
```json
{
    "message": "Unauthenticated."
}
```
**Action**: Try refresh, if fails → redirect to login

### Refresh Failed (401)
```json
{
    "message": "Token refresh failed",
    "error": "Invalid or expired token."
}
```
**Action**: Clear stored token → redirect to login

### User Inactive
```json
{
    "message": "Token refresh failed",
    "error": "Your account is inactive. Please contact the administrator."
}
```
**Action**: Show message → redirect to login

## 🎯 Best Practices

1. **Proactive Refresh**: Check token status periodically
2. **Background Refresh**: Refresh in background before expiration
3. **Fallback Handling**: Always handle refresh failures gracefully
4. **Secure Storage**: Store tokens securely (HttpOnly cookies for web, Keychain for mobile)
5. **Logout on Failure**: Clear everything if refresh fails multiple times

Your token renewal system is now ready for production! 🚀
