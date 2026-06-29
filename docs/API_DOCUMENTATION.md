# Smart Discussion Forum - API Documentation (v1)

## Overview

This document provides comprehensive documentation for the Smart Discussion Forum API (version 1). The API is designed for desktop client integration and uses Laravel Sanctum for token-based authentication.

**Base URL**: `http://localhost:8000/api/v1`

**Authentication**: Bearer Token (Sanctum)

**Rate Limiting**: 60 requests per minute per IP

---

## Table of Contents

1. [Authentication Flow](#authentication-flow)
2. [API Endpoints](#api-endpoints)
   - [Public Endpoints](#public-endpoints)
     - [Register](#post-apiv1register)
     - [Login](#post-apiv1login)
     - [Forgot Password](#post-apiv1passwordforgot)
     - [Reset Password](#post-apiv1passwordreset)
   - [Protected Endpoints](#protected-endpoints)
     - [Logout](#post-apiv1logout)
     - [Get Current User](#get-apiv1me)
     - [Update Profile](#post-apiv1profile)
     - [Change Password](#post-apiv1passwordchange)
     - [Delete Account](#delete-apiv1account)
     - [Email Verification](#post-apiv1emailverify)
     - [Resend Verification](#post-apiv1emailresend)
     - [Token Management](#token-management)
3. [Error Responses](#error-responses)
4. [Rate Limiting](#rate-limiting)
5. [Security Headers](#security-headers)
6. [CORS Configuration](#cors-configuration)

---

## Authentication Flow

### Overview

The API uses Laravel Sanctum for token-based authentication. The flow is:

1. **Login** → Send credentials → Receive API token
2. **Use Token** → Include `Authorization: Bearer {token}` header in all subsequent requests
3. **Logout** → Invalidate token when done

### Token Usage

Include the token in the `Authorization` header:

```http
Authorization: Bearer your-token-here
```

### Token Lifecycle

- Tokens expire after 30 days (43,200 minutes)
- Tokens are invalidated on logout
- Each login creates a new token
- Users can have multiple active tokens
- Tokens can be refreshed before expiration
- Users can view and revoke their active tokens

---

## API Endpoints

### API Endpoints Summary

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/register` | ❌ | Register new user account |
| POST | `/api/v1/login` | ❌ | Login and get API token |
| POST | `/api/v1/password/forgot` | ❌ | Request password reset link |
| POST | `/api/v1/password/reset` | ❌ | Reset password with token |
| POST | `/api/v1/logout` | ✅ | Logout and revoke token |
| GET | `/api/v1/me` | ✅ | Get current user data |
| POST | `/api/v1/profile` | ✅ | Update profile information |
| POST | `/api/v1/password/change` | ✅ | Change password |
| DELETE | `/api/v1/account` | ✅ | Delete account permanently |
| POST | `/api/v1/email/verify` | ✅ | Verify email address |
| POST | `/api/v1/email/resend` | ✅ | Resend verification email |
| GET | `/api/v1/tokens` | ✅ | List all active tokens |
| POST | `/api/v1/token/refresh` | ✅ | Refresh current token |
| DELETE | `/api/v1/tokens/{id}` | ✅ | Revoke specific token |

---

### Public Endpoints

These endpoints do not require authentication.

---

### POST /api/v1/register

Register a new user account and receive an API token.

**Authentication**: Not required (public endpoint)

**Rate Limit**: 3 requests per 60 minutes

#### Request

```http
POST /api/v1/register
Content-Type: application/json

{
  "full_name": "John Doe",
  "email": "john@example.com",
  "password": "Password123",
  "password_confirmation": "Password123"
}
```

#### Request Parameters

| Parameter             | Type   | Required | Description                          |
|-----------------------|--------|----------|--------------------------------------|
| full_name             | string | Yes      | User's full name (max 100 chars)     |
| email                 | string | Yes      | User email address (must be unique)  |
| password              | string | Yes      | Password (min 8 chars)               |
| password_confirmation | string | Yes      | Must match password                  |

#### Success Response (201 Created)

```json
{
  "message": "Registration successful",
  "token": "1|abc123def456...",
  "user": {
    "id": 1,
    "full_name": "John Doe",
    "email": "john@example.com",
    "account_status": "active",
    "role": "Member",
    "group": "Default Group",
    "email_verified_at": null,
    "last_active_at": null
  }
}
```

#### Error Responses

**422 Validation Error**
```json
{
  "message": "The email has already been taken.",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

**500 Server Error - Missing Role/Group**
```json
{
  "message": "Required role or group not found in database. Please contact administrator."
}
```

**429 Too Many Requests**
```json
{
  "message": "Too many requests. Please try again later."
}
```

---

### POST /api/v1/login

Authenticate user and receive API token.

**Authentication**: Not required (public endpoint)

**Rate Limit**: 5 attempts per 30 seconds

#### Request

```http
POST /api/v1/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "MyPassword123"
}
```

#### Request Parameters

| Parameter | Type   | Required | Description              |
|-----------|--------|----------|--------------------------|
| email     | string | Yes      | User email address       |
| password  | string | Yes      | User password (min 8 chars) |

#### Success Response (200 OK)

```json
{
  "message": "Login successful",
  "token": "1|abc123def456...",
  "user": {
    "id": 1,
    "full_name": "John Doe",
    "email": "user@example.com",
    "account_status": "active",
    "role": "Member",
    "group": "General",
    "email_verified_at": "2026-06-26T10:30:00.000000Z",
    "last_active_at": "2026-06-26T15:45:00.000000Z"
  }
}
```

#### Error Responses

**401 Unauthorized - Invalid Credentials**
```json
{
  "message": "Invalid credentials."
}
```

**403 Forbidden - Account Blacklisted**
```json
{
  "message": "Your account is blacklisted until Jul 15, 2026."
}
```

**403 Forbidden - Warning Not Acknowledged**
```json
{
  "message": "Your account is warned. Please acknowledge the warning before continuing.",
  "requires_warning_acknowledgement": true,
  "user": {
    "id": 1,
    "full_name": "John Doe",
    "email": "user@example.com",
    "account_status": "warned",
    "role": "Member",
    "group": "General",
    "email_verified_at": null,
    "last_active_at": "2026-06-20T10:00:00.000000Z"
  }
}
```

**429 Too Many Requests**
```json
{
  "message": "Too many login attempts. Try again in 25 seconds."
}
```

**422 Validation Error**
```json
{
  "message": "The email field must be a valid email address.",
  "errors": {
    "email": ["The email field must be a valid email address."]
  }
}
```

---

### POST /api/v1/password/forgot

Request a password reset link via email.

**Authentication**: Not required (public endpoint)

#### Request

```http
POST /api/v1/password/forgot
Content-Type: application/json

{
  "email": "user@example.com"
}
```

#### Request Parameters

| Parameter | Type   | Required | Description              |
|-----------|--------|----------|--------------------------|
| email     | string | Yes      | User email address       |

#### Success Response (200 OK)

```json
{
  "message": "Password reset link sent to your email"
}
```

**Note**: For security, this endpoint returns success even if the email doesn't exist.

#### Error Responses

**422 Validation Error**
```json
{
  "message": "The email field must be a valid email address.",
  "errors": {
    "email": ["The email field must be a valid email address."]
  }
}
```

---

### POST /api/v1/password/reset

Reset password using the token received via email.

**Authentication**: Not required (public endpoint)

#### Request

```http
POST /api/v1/password/reset
Content-Type: application/json

{
  "token": "reset-token-from-email",
  "email": "user@example.com",
  "password": "NewPassword123",
  "password_confirmation": "NewPassword123"
}
```

#### Request Parameters

| Parameter             | Type   | Required | Description                          |
|-----------------------|--------|----------|--------------------------------------|
| token                 | string | Yes      | Reset token from email               |
| email                 | string | Yes      | User email address                   |
| password              | string | Yes      | New password (min 8 chars)           |
| password_confirmation | string | Yes      | Must match password                  |

#### Password Requirements

- Minimum 8 characters
- Must contain uppercase letters
- Must contain lowercase letters
- Must contain numbers

#### Success Response (200 OK)

```json
{
  "message": "Password reset successfully"
}
```

#### Error Responses

**400 Bad Request - Invalid Token**
```json
{
  "message": "Unable to reset password"
}
```

**422 Validation Error**
```json
{
  "message": "The password must be at least 8 characters.",
  "errors": {
    "password": ["The password must be at least 8 characters."]
  }
}
```

---

### POST /api/v1/logout

Revoke the current API token.

**Authentication**: Required (Bearer token)

#### Request

```http
POST /api/v1/logout
Authorization: Bearer your-token-here
```

#### Success Response (200 OK)

```json
{
  "message": "Logged out successfully"
}
```

#### Error Responses

**401 Unauthorized - Invalid/Expired Token**
```json
{
  "message": "Unauthenticated."
}
```

---

### GET /api/v1/me

Get authenticated user data with role and group information.

**Authentication**: Required (Bearer token)

#### Request

```http
GET /api/v1/me
Authorization: Bearer your-token-here
```

#### Success Response (200 OK)

```json
{
  "user": {
    "id": 1,
    "full_name": "John Doe",
    "email": "user@example.com",
    "account_status": "active",
    "role": {
      "id": 2,
      "name": "Member"
    },
    "group": {
      "id": 1,
      "name": "General"
    },
    "email_verified_at": "2026-06-26T10:30:00.000000Z",
    "last_active_at": "2026-06-26T15:45:00.000000Z",
    "profile_picture": "avatars/profile.jpg",
    "created_at": "2026-06-01T08:00:00.000000Z",
    "updated_at": "2026-06-26T15:45:00.000000Z"
  }
}
```

#### Error Responses

**401 Unauthorized**
```json
{
  "message": "Unauthenticated."
}
```

---

### POST /api/v1/profile

Update user profile information (full name and email).

**Authentication**: Required (Bearer token)

#### Request

```http
POST /api/v1/profile
Authorization: Bearer your-token-here
Content-Type: application/json

{
  "full_name": "John Updated",
  "email": "newemail@example.com"
}
```

#### Request Parameters

| Parameter | Type   | Required | Description                    |
|-----------|--------|----------|--------------------------------|
| full_name | string | Yes      | User's full name (max 255)     |
| email     | string | Yes      | New email address (must be unique) |

#### Success Response (200 OK)

```json
{
  "message": "Profile updated successfully",
  "email_verification_required": true,
  "user": {
    "id": 1,
    "full_name": "John Updated",
    "email": "newemail@example.com",
    "email_verified_at": null
  }
}
```

**Note**: If email is changed, `email_verified_at` is set to `null` and a verification email is sent.

#### Error Responses

**401 Unauthorized**
```json
{
  "message": "Unauthenticated."
}
```

**422 Validation Error - Email Already Exists**
```json
{
  "message": "The email has already been taken.",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

**422 Validation Error - Invalid Email**
```json
{
  "message": "The email must be a valid email address.",
  "errors": {
    "email": ["The email must be a valid email address."]
  }
}
```

---

### POST /api/v1/password/change

Change the authenticated user's password.

**Authentication**: Required (Bearer token)

#### Request

```http
POST /api/v1/password/change
Authorization: Bearer your-token-here
Content-Type: application/json

{
  "current_password": "OldPassword123",
  "new_password": "NewPassword456",
  "new_password_confirmation": "NewPassword456"
}
```

#### Request Parameters

| Parameter                   | Type   | Required | Description                          |
|-----------------------------|--------|----------|--------------------------------------|
| current_password            | string | Yes      | Current password                     |
| new_password                | string | Yes      | New password (see requirements below)|
| new_password_confirmation   | string | Yes      | Must match new_password              |

#### Password Requirements

- Minimum 8 characters
- Must contain uppercase letters
- Must contain lowercase letters
- Must contain numbers
- Must be different from current password

#### Success Response (200 OK)

```json
{
  "message": "Password updated successfully"
}
```

#### Error Responses

**401 Unauthorized**
```json
{
  "message": "Unauthenticated."
}
```

**422 Validation Error - Incorrect Current Password**
```json
{
  "message": "The current password is incorrect.",
  "errors": {
    "current_password": ["The current password is incorrect."]
  }
}
```

**422 Validation Error - Password Too Weak**
```json
{
  "message": "Password must be at least 8 characters with uppercase, lowercase, and numbers.",
  "errors": {
    "new_password": ["Password must be at least 8 characters with uppercase, lowercase, and numbers."]
  }
}
```

**422 Validation Error - Passwords Don't Match**
```json
{
  "message": "The passwords do not match.",
  "errors": {
    "new_password": ["The passwords do not match."]
  }
}
```

**422 Validation Error - Same as Current**
```json
{
  "message": "The new password must be different from your current password.",
  "errors": {
    "new_password": ["The new password must be different from your current password."]
  }
}
```

---

### DELETE /api/v1/account

Permanently delete the authenticated user's account and all associated data.

**Authentication**: Required (Bearer token)

#### Request

```http
DELETE /api/v1/account
Authorization: Bearer your-token-here
Content-Type: application/json

{
  "password": "YourPassword123"
}
```

#### Request Parameters

| Parameter | Type   | Required | Description              |
|-----------|--------|----------|--------------------------|
| password  | string | Yes      | Current password for verification |

#### Success Response (200 OK)

```json
{
  "message": "Account deleted successfully"
}
```

**Note**: This action is irreversible. All user data including warnings, blacklist records, and tokens will be permanently deleted.

#### Error Responses

**401 Unauthorized**
```json
{
  "message": "Unauthenticated."
}
```

**403 Forbidden - Invalid Password**
```json
{
  "message": "Invalid password"
}
```

**422 Validation Error**
```json
{
  "message": "The password field is required.",
  "errors": {
    "password": ["The password field is required."]
  }
}
```

---

### POST /api/v1/email/verify

Verify email address using the token received via email.

**Authentication**: Required (Bearer token)

#### Request

```http
POST /api/v1/email/verify
Authorization: Bearer your-token-here
Content-Type: application/json

{
  "token": "verification-token-from-email",
  "email": "user@example.com"
}
```

#### Request Parameters

| Parameter | Type   | Required | Description              |
|-----------|--------|----------|--------------------------|
| token     | string | Yes      | Verification token from email |
| email     | string | Yes      | User email address       |

#### Success Response (200 OK)

```json
{
  "message": "Email verified successfully"
}
```

#### Error Responses

**400 Bad Request - Invalid or Expired Token**
```json
{
  "message": "Invalid or expired verification token"
}
```

**422 Validation Error**
```json
{
  "message": "The token field is required.",
  "errors": {
    "token": ["The token field is required."]
  }
}
```

---

### POST /api/v1/email/resend

Resend email verification link.

**Authentication**: Required (Bearer token)

**Rate Limit**: 1 request per 60 seconds

#### Request

```http
POST /api/v1/email/resend
Authorization: Bearer your-token-here
```

#### Success Response (200 OK)

```json
{
  "message": "Verification email sent"
}
```

#### Error Responses

**400 Bad Request - Already Verified**
```json
{
  "message": "Email already verified"
}
```

**401 Unauthorized**
```json
{
  "message": "Unauthenticated."
}
```

**429 Too Many Requests**
```json
{
  "message": "Please wait 60 seconds before requesting another verification email"
}
```

---

### Token Management

#### GET /api/v1/tokens

List all active API tokens for the authenticated user.

**Authentication**: Required (Bearer token)

##### Request

```http
GET /api/v1/tokens
Authorization: Bearer your-token-here
```

##### Success Response (200 OK)

```json
{
  "tokens": [
    {
      "id": 1,
      "name": "desktop-client",
      "created_at": "2026-06-26T10:00:00.000000Z",
      "last_used_at": "2026-06-26T15:45:00.000000Z",
      "expires_at": "2026-07-26T10:00:00.000000Z"
    },
    {
      "id": 2,
      "name": "desktop-client",
      "created_at": "2026-06-20T08:00:00.000000Z",
      "last_used_at": "2026-06-25T12:30:00.000000Z",
      "expires_at": "2026-07-20T08:00:00.000000Z"
    }
  ]
}
```

##### Error Responses

**401 Unauthorized**
```json
{
  "message": "Unauthenticated."
}
```

---

#### POST /api/v1/token/refresh

Refresh the current API token. The old token is invalidated and a new one is issued.

**Authentication**: Required (Bearer token)

##### Request

```http
POST /api/v1/token/refresh
Authorization: Bearer your-token-here
```

##### Success Response (200 OK)

```json
{
  "message": "Token refreshed successfully",
  "token": "2|newtoken123abc..."
}
```

**Note**: The old token is immediately invalidated. Update your client with the new token.

##### Error Responses

**401 Unauthorized**
```json
{
  "message": "Unauthenticated."
}
```

---

#### DELETE /api/v1/tokens/{tokenId}

Revoke a specific API token.

**Authentication**: Required (Bearer token)

##### Request

```http
DELETE /api/v1/tokens/123
Authorization: Bearer your-token-here
```

##### Path Parameters

| Parameter | Type    | Required | Description              |
|-----------|---------|----------|--------------------------|
| tokenId   | integer | Yes      | ID of the token to revoke |

##### Success Response (200 OK)

```json
{
  "message": "Token revoked successfully"
}
```

##### Error Responses

**401 Unauthorized**
```json
{
  "message": "Unauthenticated."
}
```

**404 Not Found**
```json
{
  "message": "Token not found"
}
```

---

## Error Responses

### Standard Error Format

All error responses follow this format:

```json
{
  "message": "Human-readable error message",
  "errors": {
    "field_name": ["Specific error details"]
  }
}
```

### HTTP Status Codes

| Status Code | Meaning                    | Description                                    |
|-------------|----------------------------|------------------------------------------------|
| 200         | OK                         | Request successful                             |
| 201         | Created                    | Resource created successfully (e.g., registration) |
| 400         | Bad Request                | Invalid request (e.g., invalid token)          |
| 401         | Unauthorized               | Missing or invalid authentication token        |
| 403         | Forbidden                  | Account blacklisted or warned                  |
| 404         | Not Found                  | Resource not found (e.g., token not found)     |
| 422         | Unprocessable Entity       | Validation error                               |
| 429         | Too Many Requests          | Rate limit exceeded                            |
| 500         | Internal Server Error      | Server error                                   |

---

## Rate Limiting

### API-Wide Rate Limit

- **Limit**: 60 requests per minute
- **Scope**: Per IP address
- **Applies to**: All API endpoints

**Response when exceeded (429)**:
```json
{
  "message": "Too many requests. Please try again later."
}
```

**Headers included**:
```http
Retry-After: 45
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
```

### Login-Specific Rate Limit

- **Limit**: 5 attempts per 30 seconds
- **Scope**: Per email + IP combination AND per email address (dual-key)
- **Applies to**: `/api/v1/login` only
- **Note**: Both an email+IP key and an email-only key are checked. This prevents attackers from bypassing the limit by rotating IP addresses.

### Registration Rate Limit

- **Limit**: 3 requests per 60 seconds
- **Scope**: Per IP address
- **Applies to**: `/api/v1/register` only

### Email Verification Rate Limit

- **Limit**: 1 request per 60 seconds
- **Scope**: Per authenticated user
- **Applies to**: `/api/v1/email/resend` only

---

## Security Headers

All API responses include the following security headers:

```http
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Cache-Control: no-store, no-cache, must-revalidate, max-age=0
Pragma: no-cache
Expires: 0
```

**When using HTTPS**:
```http
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

---

## CORS Configuration

### Allowed Origins

- `http://localhost`
- `http://localhost:*` (any port)
- `http://127.0.0.1`
- `http://127.0.0.1:*` (any port)

**For production**, update `config/cors.php` with your desktop client URLs.

### Allowed Methods

- GET
- POST
- PUT
- DELETE
- OPTIONS

### Allowed Headers

- Content-Type
- Authorization
- X-Requested-With
- Accept
- Origin

### Credentials

CORS is configured to support credentials (`Access-Control-Allow-Credentials: true`), allowing cookies and authentication headers.

---

## Example Usage (Desktop Client)

### JavaScript/TypeScript Example

```typescript
const API_BASE_URL = 'http://localhost:8000/api/v1';

class ForumAPI {
  private token: string | null = null;

  async login(email: string, password: string) {
    const response = await fetch(`${API_BASE_URL}/login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ email, password }),
    });

    const data = await response.json();
    
    if (response.ok) {
      this.token = data.token;
      localStorage.setItem('api_token', data.token);
    }
    
    return data;
  }

  async logout() {
    if (!this.token) return;

    await fetch(`${API_BASE_URL}/logout`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${this.token}`,
      },
    });

    this.token = null;
    localStorage.removeItem('api_token');
  }

  async getCurrentUser() {
    const response = await fetch(`${API_BASE_URL}/me`, {
      headers: {
        'Authorization': `Bearer ${this.token}`,
      },
    });

    return await response.json();
  }

  async updateProfile(fullName: string, email: string) {
    const response = await fetch(`${API_BASE_URL}/profile`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${this.token}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ full_name: fullName, email }),
    });

    return await response.json();
  }

  async changePassword(currentPassword: string, newPassword: string, confirmation: string) {
    const response = await fetch(`${API_BASE_URL}/password/change`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${this.token}`,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        current_password: currentPassword,
        new_password: newPassword,
        new_password_confirmation: confirmation,
      }),
    });

    return await response.json();
  }
}
```

### cURL Examples

**Register**:
```bash
curl -X POST http://localhost:8000/api/v1/register \
  -H "Content-Type: application/json" \
  -d '{"full_name":"John Doe","email":"john@example.com","password":"Password123","password_confirmation":"Password123"}'
```

**Login**:
```bash
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"MyPassword123"}'
```

**Get User**:
```bash
curl -X GET http://localhost:8000/api/v1/me \
  -H "Authorization: Bearer your-token-here"
```

**Update Profile**:
```bash
curl -X POST http://localhost:8000/api/v1/profile \
  -H "Authorization: Bearer your-token-here" \
  -H "Content-Type: application/json" \
  -d '{"full_name":"John Updated","email":"new@example.com"}'
```

**Change Password**:
```bash
curl -X POST http://localhost:8000/api/v1/password/change \
  -H "Authorization: Bearer your-token-here" \
  -H "Content-Type: application/json" \
  -d '{"current_password":"Old123","new_password":"New456","new_password_confirmation":"New456"}'
```

**Forgot Password**:
```bash
curl -X POST http://localhost:8000/api/v1/password/forgot \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com"}'
```

**Reset Password**:
```bash
curl -X POST http://localhost:8000/api/v1/password/reset \
  -H "Content-Type: application/json" \
  -d '{"token":"reset-token","email":"user@example.com","password":"NewPass123","password_confirmation":"NewPass123"}'
```

**Verify Email**:
```bash
curl -X POST http://localhost:8000/api/v1/email/verify \
  -H "Authorization: Bearer your-token-here" \
  -H "Content-Type: application/json" \
  -d '{"token":"verification-token","email":"user@example.com"}'
```

**Resend Verification**:
```bash
curl -X POST http://localhost:8000/api/v1/email/resend \
  -H "Authorization: Bearer your-token-here"
```

**Delete Account**:
```bash
curl -X DELETE http://localhost:8000/api/v1/account \
  -H "Authorization: Bearer your-token-here" \
  -H "Content-Type: application/json" \
  -d '{"password":"YourPassword123"}'
```

**List Tokens**:
```bash
curl -X GET http://localhost:8000/api/v1/tokens \
  -H "Authorization: Bearer your-token-here"
```

**Refresh Token**:
```bash
curl -X POST http://localhost:8000/api/v1/token/refresh \
  -H "Authorization: Bearer your-token-here"
```

**Revoke Token**:
```bash
curl -X DELETE http://localhost:8000/api/v1/tokens/123 \
  -H "Authorization: Bearer your-token-here"
```

**Logout**:
```bash
curl -X POST http://localhost:8000/api/v1/logout \
  -H "Authorization: Bearer your-token-here"
```

---

## Activity Monitoring

The system automatically monitors user activity and issues warnings/blacklists based on inactivity.

### Configuration

Configure thresholds in the `system_config` table:

| Config Key                  | Default | Description                              |
|-----------------------------|---------|------------------------------------------|
| inactivity_warning_days     | 30      | Days before warning is issued            |
| warning_response_days       | 7       | Days to respond to warning               |
| blacklist_duration_days     | 90      | Days for blacklist to expire             |

### Automated Schedule

- **Command**: `php artisan monitor:activity`
- **Schedule**: Daily at 2:00 AM UTC
- **Dry Run**: `php artisan monitor:activity --dry-run`

---

## Support & Troubleshooting

### Common Issues

1. **401 Unauthenticated**
   - Check if token is included in Authorization header
   - Verify token format: `Bearer {token}`
   - Token may have been revoked (user logged out)

2. **403 Forbidden**
   - Account may be blacklisted (check expiry date)
   - Account may be warned (needs acknowledgement via web interface)

3. **429 Too Many Requests**
   - Wait for rate limit to reset (check Retry-After header)
   - Reduce request frequency

4. **422 Validation Error**
   - Check request body format
   - Verify all required fields are present
   - Ensure data types match requirements

### Testing the API

Use the dry-run mode to test activity monitoring:
```bash
php artisan monitor:activity --dry-run
```

Test the scheduler:
```bash
php artisan schedule:run
```

---

## Version History

| Version | Date       | Description                              |
|---------|------------|------------------------------------------|
| v1.2    | 2026-06-29 | Updated default registration role to Member, added dual-key rate limiter documentation |
| v1.1    | 2026-06-26 | Added registration, email verification, password reset, token management, and account deletion endpoints |
| v1      | 2026-06-26 | Initial API release                      |

---

## Contact

For API support or questions, contact the development team.
