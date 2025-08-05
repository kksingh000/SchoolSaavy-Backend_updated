# Token-Based Authentication Test

## Overview
Your Laravel API is now configured for pure token-based authentication without cookies.

## Key Changes Made:

### 1. Sanctum Configuration (`config/sanctum.php`)
- ✅ **Stateful domains**: Empty array `[]` (no cookie-based authentication)
- ✅ **Guard**: Changed from `['web']` to `['sanctum']` 
- ✅ **Token expiration**: Set to 1440 minutes (24 hours)

### 2. Session Configuration (`.env`)
- ✅ **SESSION_DRIVER**: Changed from `database` to `array` (stateless)
- ✅ **CACHE_STORE**: Using `redis` for performance
- ✅ **SANCTUM_STATEFUL_DOMAINS**: Empty (no stateful authentication)

### 3. Environment Variables
```bash
SESSION_DRIVER=array                    # No server-side sessions
SANCTUM_STATEFUL_DOMAINS=              # No cookie authentication  
SANCTUM_TOKEN_EXPIRATION=1440          # 24-hour token expiration
CACHE_STORE=redis                      # Redis for caching only
```

## How to Use Token Authentication:

### 1. **Login Request**
```bash
POST http://localhost:8080/api/auth/login
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
    "token": "1|abc123token456def"
}
```

### 2. **Authenticated Requests**
```bash
GET http://localhost:8080/api/auth/me
Authorization: Bearer 1|abc123token456def
```

### 3. **Logout Request**
```bash
POST http://localhost:8080/api/auth/logout
Authorization: Bearer 1|abc123token456def
```

## Benefits of This Configuration:

✅ **Stateless**: No server-side session storage  
✅ **Scalable**: Works across multiple servers/workers  
✅ **Mobile-Friendly**: Perfect for mobile apps  
✅ **API-First**: Pure JSON responses, no HTML redirects  
✅ **Secure**: Token-based with configurable expiration  
✅ **Performance**: Redis caching, no session database queries  

## Security Features:

- 🔒 **Token Expiration**: 24-hour automatic expiration
- 🔒 **Bearer Authentication**: Industry-standard `Authorization: Bearer` header
- 🔒 **No Cookies**: Eliminates CSRF vulnerabilities
- 🔒 **Revocable Tokens**: Logout invalidates tokens immediately

## Your API Routes Structure:

```
Public Routes:
- POST /api/auth/login
- GET  /api/health

Protected Routes (require Bearer token):
- GET  /api/auth/me
- POST /api/auth/logout
- All routes under middleware('auth:sanctum')
```

## Testing Authentication:

Your API now expects all protected requests to include:
```
Authorization: Bearer {token}
```

No cookies, no sessions, pure token-based authentication! 🚀
