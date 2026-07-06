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
   - [Forum Endpoints](#forum-endpoints)
     - [Topics (T1-T7)](#topics)
     - [Posts (P1-P3)](#posts)
   - [Post Visibility Endpoints](#post-visibility-endpoints)
     - [Exclude User (P5)](#post-apiv1postspostidvisibilityexclude)
     - [Remove Exclusion (P6)](#delete-apiv1postspostidvisibilityuserid)
     - [List Exclusions (P7)](#get-apiv1postspostidvisibility)
   - [Category Endpoints](#category-endpoints)
     - [List Categories (C1)](#get-apiv1categories)
     - [Category Topics (C2)](#get-apiv1categoriescategoryidtopics)
     - [Admin Category CRUD (C3-C5)](#admin-category-management)
   - [Group Browsing Endpoints](#group-browsing-endpoints)
     - [List Groups (G1)](#get-apiv1groups)
     - [Show Group (G2)](#get-apiv1groupsgroupid)
     - [Group Topics (G3)](#get-apiv1groupsgroupidtopics)
     - [Group Members (G4)](#get-apiv1groupsgroupidmembers)
   - [Admin Warning Management](#admin-warning-management)
     - [List Warnings (W1)](#get-apiv1adminwarnings)
     - [Show Warning (W2)](#get-apiv1adminwarningswarningid)
     - [Issue Warning (W3)](#post-apiv1adminusersuseridwarnings)
     - [Resolve Warning (W4)](#post-apiv1adminwarningswarningidresolve)
   - [Admin Blacklist Management](#admin-blacklist-management)
     - [List Records (W5)](#get-apiv1adminblacklist-records)
     - [Blacklist User (W6)](#post-apiv1adminusersuseridblacklist)
     - [Lift Blacklist (W7)](#post-apiv1adminblacklist-recordsrecordidlift)
    - [Admin Bulk Operations](#admin-bulk-operations)
    - [Admin Advanced Search](#admin-advanced-search)
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

- Tokens are created per login and can be refreshed manually
- Tokens are invalidated on logout or when explicitly revoked
- Users can have multiple active tokens
- Token records expose `last_used_at` and nullable `expires_at` metadata
- Users can view and revoke their active tokens

---

## API Endpoints

### API Endpoints Summary

#### Authentication & User Management

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

#### Forum - Topics & Posts

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/topics` | ✅ | List active topics in user's group |
| GET | `/api/v1/topics/type/{type}` | ✅ | Filter topics by type (discussion/question) |
| POST | `/api/v1/topics` | ✅ | Create a new topic |
| GET | `/api/v1/topics/{topicId}` | ✅ | Get topic detail with posts |
| PUT | `/api/v1/topics/{topicId}` | ✅ | Update topic (creator or admin) |
| DELETE | `/api/v1/topics/{topicId}` | ✅ | Archive topic (creator or admin) |
| GET | `/api/v1/topics/{topicId}/posts` | ✅ | List posts in a topic |
| POST | `/api/v1/topics/{topicId}/posts` | ✅ | Create a reply in a topic |
| PUT | `/api/v1/posts/{postId}` | ✅ | Update own post |
| DELETE | `/api/v1/posts/{postId}` | ✅ | Soft-delete own post |

#### Forum - Post Visibility

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/posts/{postId}/visibility` | ✅ | List users excluded from post |
| POST | `/api/v1/posts/{postId}/visibility/exclude` | ✅ | Exclude user from seeing post |
| DELETE | `/api/v1/posts/{postId}/visibility/{userId}` | ✅ | Remove user exclusion |

#### Forum - Categories

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/categories` | ✅ | List categories in user's group |
| GET | `/api/v1/categories/{categoryId}/topics` | ✅ | List topics under a category |
| POST | `/api/v1/admin/categories` | ✅🔑 | Create category (admin) |
| PUT | `/api/v1/admin/categories/{categoryId}` | ✅🔑 | Update category (admin) |
| DELETE | `/api/v1/admin/categories/{categoryId}` | ✅🔑 | Delete category (admin) |

#### Forum - Group Browsing

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/groups` | ✅ | List accessible groups |
| GET | `/api/v1/groups/{groupId}` | ✅ | Show group details |
| GET | `/api/v1/groups/{groupId}/topics` | ✅ | List topics in a group |
| GET | `/api/v1/groups/{groupId}/members` | ✅ | List members of a group |

#### Admin - Warning Management

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/admin/warnings` | ✅🔑 | List warnings (group-scoped) |
| GET | `/api/v1/admin/warnings/{warningId}` | ✅🔑 | Show warning detail |
| POST | `/api/v1/admin/users/{userId}/warnings` | ✅🔑 | Issue warning to user |
| POST | `/api/v1/admin/warnings/{warningId}/resolve` | ✅🔑 | Resolve a warning |

#### Admin - Blacklist Management

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/admin/blacklist-records` | ✅🔑 | List blacklist records (group-scoped) |
| POST | `/api/v1/admin/users/{userId}/blacklist` | ✅🔑 | Blacklist a user |
| POST | `/api/v1/admin/blacklist-records/{recordId}/lift` | ✅🔑 | Lift a blacklist |

#### Admin Bulk Operations

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/admin/bulk/change-roles` | ✅🔑 | Change roles for multiple users |
| POST | `/api/v1/admin/bulk/change-status` | ✅🔑 | Update account status for multiple users |
| POST | `/api/v1/admin/bulk/assign-group` | ✅🔑 | Move multiple users to a group |
| POST | `/api/v1/admin/bulk/blacklist` | ✅🔑 | Blacklist multiple users |
| POST | `/api/v1/admin/bulk/lift-blacklist` | ✅🔑 | Lift blacklists for multiple users |
| POST | `/api/v1/admin/bulk/warn` | ✅🔑 | Issue warnings to multiple users |
| POST | `/api/v1/admin/bulk/assign-group-admins` | ✅🔑 | Assign group admins in bulk |

#### Admin Advanced Search

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/admin/search/users` | ✅🔑 | Search users with filters |
| POST | `/api/v1/admin/search/groups` | ✅🔑 | Search groups with filters |
| POST | `/api/v1/admin/search/audit-logs` | ✅🔑 | Search audit logs with filters |
| POST | `/api/v1/admin/search/warnings` | ✅🔑 | Search warnings with filters |
| GET | `/api/v1/admin/search/options/{model}` | ✅🔑 | Get filter options for a model |
| GET | `/api/v1/admin/search/suggestions/{type}` | ✅🔑 | Get search suggestions |

> ✅ = Authentication required | 🔑 = Admin role required (System Admin or Group Admin)

#### Quiz & Assessment — Lecturer/Admin CRUD

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/quizzes` | ✅🔑 | List all quizzes (paginated, with config + lecturer) |
| POST | `/api/v1/quizzes` | ✅🔑 | Create a new quiz with default configuration |
| GET | `/api/v1/quizzes/{quiz}` | ✅🔑 | Show quiz with questions, answers, config |
| PUT | `/api/v1/quizzes/{quiz}` | ✅🔑 | Update quiz + configuration (blocked if published) |
| DELETE | `/api/v1/quizzes/{quiz}` | ✅🔑 | Delete quiz (blocked if published) |
| POST | `/api/v1/quizzes/{quiz}/publish` | ✅🔑 | Publish quiz announcement (validates questions + future date) |
| GET | `/api/v1/quizzes/{quiz}/report` | ✅🔑 | Class performance report with student breakdown |
| GET | `/api/v1/quizzes/{quiz}/questions` | ✅🔑 | List questions with answers, ordered by position |
| POST | `/api/v1/quizzes/{quiz}/questions` | ✅🔑 | Add question with answer options |
| PUT | `/api/v1/quizzes/{quiz}/questions/{question}` | ✅🔑 | Update question text/type/marks |
| DELETE | `/api/v1/quizzes/{quiz}/questions/{question}` | ✅🔑 | Delete a question (cascades to answers) |
| PUT | `/api/v1/quizzes/{quiz}/questions/reorder` | ✅🔑 | Batch reorder questions |
| GET | `/api/v1/questions/{question}/answers` | ✅🔑 | List answer options for a question |
| POST | `/api/v1/questions/{question}/answers` | ✅🔑 | Add an answer option |
| PUT | `/api/v1/answers/{answer}` | ✅🔑 | Update an answer |
| DELETE | `/api/v1/answers/{answer}` | ✅🔑 | Delete an answer option |

#### Quiz & Assessment — Student Execution

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/quizzes/{quiz}/announcement` | ✅ | Show quiz announcement with timing info |
| GET | `/api/v1/quizzes/{quiz}/status` | ✅ | Real-time quiz status (JS polling) |
| POST | `/api/v1/quizzes/{quiz}/attempt` | ✅ | Start a quiz attempt (returns questions without correct answers) |
| GET | `/api/v1/quizzes/{quiz}/attempt` | ✅ | Show current attempt state (questions, answers, time remaining) |
| POST | `/api/v1/quizzes/{quiz}/answer` | ✅ | Save a single answer |
| POST | `/api/v1/quizzes/{quiz}/answers/batch` | ✅ | Save multiple answers at once |
| POST | `/api/v1/quizzes/{quiz}/submit` | ✅ | Manually submit quiz (triggers grading) |
| POST | `/api/v1/quizzes/{quiz}/auto-submit` | ✅ | Auto-submit when timer expires |

#### Quiz & Assessment — Results & Notifications

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/quizzes/{quiz}/result` | ✅ | Show the authenticated student's result |
| GET | `/api/v1/lecturer/quizzes/{quiz}/grades` | ✅🔑 | List all grades for a quiz (lecturer report) |
| GET | `/api/v1/lecturer/grades/{grade}` | ✅🔑 | Single grade detail with per-question breakdown |
| GET | `/api/v1/lecturer/quizzes/{quiz}/grades/export` | ✅🔑 | Export grades as CSV |
| GET | `/api/v1/quizzes/upcoming` | ✅ | List upcoming published quizzes |
| GET | `/api/v1/quizzes/live` | ✅ | List currently active quizzes |
| GET | `/api/v1/me/quiz-history` | ✅ | List the student's past quiz attempts with grades |
| GET | `/api/v1/me/quiz-notifications` | ✅ | List quiz-related notifications |
| POST | `/api/v1/notifications/{id}/read` | ✅ | Mark a notification as read |

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
    "group": "Default Group",
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
    "group": "Default Group",
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

Send a 6-digit OTP to the user's email address to begin the password reset flow.

The desktop client calls this endpoint, then immediately shows an OTP input screen inside the app. The user reads the code from their email and types it in — they never leave the application to click a link in a browser.

> **Web interface note:** The web app (`/forgot-password`) continues to use the original token-link flow. This OTP flow is **API / desktop client only**.

**Authentication**: Not required (public endpoint)  
**Rate limit**: 3 requests per 15 minutes per email address

#### Request

```http
POST /api/v1/password/forgot
Content-Type: application/json

{
  "email": "user@example.com"
}
```

#### Request Parameters

| Parameter | Type   | Required | Description        |
|-----------|--------|----------|--------------------|
| email     | string | Yes      | Registered email   |

#### Success Response (200 OK)

```json
{
  "message": "A 6-digit reset code has been sent to your email. It expires in 10 minutes."
}
```

#### Error Responses

**429 Too Many Requests**
```json
{
  "message": "Too many reset requests. Try again in 847 seconds."
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

### POST /api/v1/password/reset

Validate the 6-digit OTP and set a new password.

On success all existing API tokens for the account are revoked — the user must log in again with the new password.

**Authentication**: Not required (public endpoint)  
**Rate limit**: 5 guess attempts per 10 minutes per email address (prevents brute-forcing the OTP)

#### Request

```http
POST /api/v1/password/reset
Content-Type: application/json

{
  "email": "user@example.com",
  "otp": "482910",
  "password": "NewPassword123",
  "password_confirmation": "NewPassword123"
}
```

#### Request Parameters

| Parameter             | Type   | Required | Description                                   |
|-----------------------|--------|----------|-----------------------------------------------|
| email                 | string | Yes      | Registered email address                      |
| otp                   | string | Yes      | 6-digit code from the reset email             |
| password              | string | Yes      | New password (see requirements below)         |
| password_confirmation | string | Yes      | Must match `password`                         |

#### Password Requirements

- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number

#### Success Response (200 OK)

```json
{
  "message": "Password reset successfully. Please log in with your new password."
}
```

> All existing Sanctum tokens are revoked on success. The client must call `POST /api/v1/login` to obtain a new token before making any further authenticated requests.

#### Error Responses

**400 Bad Request — wrong OTP**
```json
{
  "message": "Invalid reset code. Please check the code and try again."
}
```

**400 Bad Request — expired OTP**
```json
{
  "message": "This reset code has expired. Please request a new one."
}
```

**429 Too Many Requests**
```json
{
  "message": "Too many attempts. Try again in 523 seconds."
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
      "name": "Default Group"
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

## Forum Endpoints

All forum endpoints enforce **group isolation**: users can only access topics, posts, and data within their own group. Admins may have cross-group visibility depending on their role.

### Topics

---

#### GET /api/v1/topics

**T1**: List active topics in the authenticated user's group, paginated and ordered by most recent.

**Authentication**: Required (Bearer token)

##### Request

```http
GET /api/v1/topics
Authorization: Bearer your-token-here
```

##### Success Response (200 OK)

```json
{
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "title": "Welcome to the Forum",
        "description": "Introduce yourself here",
        "status": "active",
        "post_type": "discussion",
        "group_id": 1,
        "created_by": 1,
        "creator": {
          "id": 1,
          "full_name": "John Doe"
        },
        "posts_count": 5,
        "created_at": "2026-06-30T10:00:00.000000Z",
        "updated_at": "2026-06-30T10:00:00.000000Z"
      }
    ],
    "last_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

---

#### GET /api/v1/topics/type/{type}

**T7**: Filter topics by post type (`discussion` or `question`).

**Authentication**: Required (Bearer token)

##### Request

```http
GET /api/v1/topics/type/question
Authorization: Bearer your-token-here
```

##### Path Parameters

| Parameter | Type   | Required | Description                        |
|-----------|--------|----------|------------------------------------|
| type      | string | Yes      | Either `discussion` or `question`  |

##### Success Response (200 OK)

Same format as [GET /api/v1/topics](#get-apiv1topics), filtered by type.

##### Error Responses

**422 Invalid Type**
```json
{
  "message": "Invalid type. Must be \"discussion\" or \"question\"."
}
```

---

#### POST /api/v1/topics

**T3**: Create a new topic. The topic is automatically scoped to the user's group.

**Authentication**: Required (Bearer token)

##### Request

```http
POST /api/v1/topics
Authorization: Bearer your-token-here
Content-Type: application/json

{
  "title": "How to use the forum?",
  "description": "A guide for new members on how to participate in discussions.",
  "post_type": "discussion"
}
```

##### Request Parameters

| Parameter   | Type   | Required | Description                              |
|-------------|--------|----------|------------------------------------------|
| title       | string | Yes      | Topic title (max 255, must be unique)    |
| description | string | Yes      | Topic body (max 10000 chars)             |
| post_type   | string | No       | `discussion` (default) or `question`     |

##### Success Response (201 Created)

```json
{
  "message": "Topic created successfully.",
  "data": {
    "topic": {
      "id": 2,
      "title": "How to use the forum?",
      "description": "A guide for new members...",
      "post_type": "discussion",
      "status": "active",
      "group_id": 1,
      "creator": {
        "id": 1,
        "full_name": "John Doe"
      },
      "created_at": "2026-06-30T12:00:00.000000Z",
      "updated_at": "2026-06-30T12:00:00.000000Z"
    }
  }
}
```

---

#### GET /api/v1/topics/{topicId}

**T2**: Get topic detail with its posts (paginated). Posts are filtered by visibility and moderation status.

**Authentication**: Required (Bearer token)

##### Request

```http
GET /api/v1/topics/1
Authorization: Bearer your-token-here
```

##### Success Response (200 OK)

```json
{
  "data": {
    "topic": {
      "id": 1,
      "title": "Welcome to the Forum",
      "description": "Introduce yourself here",
      "status": "active",
      "post_type": "discussion",
      "group_id": 1,
      "creator": {
        "id": 1,
        "full_name": "John Doe"
      },
      "posts_count": 5,
      "created_at": "2026-06-30T10:00:00.000000Z",
      "updated_at": "2026-06-30T10:00:00.000000Z"
    },
    "posts": {
      "current_page": 1,
      "data": [
        {
          "id": 1,
          "topic_id": 1,
          "content": "Hello everyone!",
          "user_id": 1,
          "is_removed": false,
          "user": {
            "id": 1,
            "full_name": "John Doe"
          },
          "created_at": "2026-06-30T10:05:00.000000Z"
        }
      ],
      "per_page": 20,
      "total": 5
    }
  }
}
```

##### Error Responses

**403 Forbidden - Group Isolation**
```json
{
  "message": "You do not have access to this topic."
}
```

---

#### PUT /api/v1/topics/{topicId}

**T4**: Update a topic. Only the topic creator or an admin can update.

**Authentication**: Required (Bearer token)

##### Request

```http
PUT /api/v1/topics/1
Authorization: Bearer your-token-here
Content-Type: application/json

{
  "title": "Updated Title",
  "description": "Updated description"
}
```

##### Request Parameters

| Parameter   | Type   | Required | Description                          |
|-------------|--------|----------|--------------------------------------|
| title       | string | No       | New title (max 255, must be unique)  |
| description | string | No       | New body (max 10000 chars)           |
| status      | string | No       | `active` or `archived`               |
| post_type   | string | No       | `discussion` or `question`           |

##### Success Response (200 OK)

```json
{
  "message": "Topic updated successfully.",
  "data": {
    "topic": {
      "id": 1,
      "title": "Updated Title",
      "description": "Updated description",
      "status": "active",
      "post_type": "discussion",
      "group_id": 1,
      "creator": { "id": 1, "full_name": "John Doe" },
      "created_at": "2026-06-30T10:00:00.000000Z",
      "updated_at": "2026-06-30T12:30:00.000000Z"
    }
  }
}
```

##### Error Responses

**403 Forbidden**
```json
{
  "message": "You are not authorized to update this topic."
}
```

---

#### DELETE /api/v1/topics/{topicId}

**T5**: Archive (soft-delete) a topic. Sets status to `archived`. Only the creator or admin can delete.

**Authentication**: Required (Bearer token)

##### Request

```http
DELETE /api/v1/topics/1
Authorization: Bearer your-token-here
```

##### Success Response (200 OK)

```json
{
  "message": "Topic archived successfully."
}
```

##### Error Responses

**403 Forbidden**
```json
{
  "message": "You are not authorized to delete this topic."
}
```

---

#### GET /api/v1/topics/{topicId}/posts

**T6**: List posts in a topic (paginated, filtered by visibility and moderation).

**Authentication**: Required (Bearer token)

##### Request

```http
GET /api/v1/topics/1/posts
Authorization: Bearer your-token-here
```

##### Success Response (200 OK)

```json
{
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "topic_id": 1,
        "content": "Hello everyone!",
        "user_id": 1,
        "is_removed": false,
        "user": { "id": 1, "full_name": "John Doe" },
        "created_at": "2026-06-30T10:05:00.000000Z"
      }
    ],
    "per_page": 20,
    "total": 5
  }
}
```

---

### Posts

---

#### POST /api/v1/topics/{topicId}/posts

**P1**: Create a reply in a topic. Topic must be active and in the user's group.

**Authentication**: Required (Bearer token)

##### Request

```http
POST /api/v1/topics/1/posts
Authorization: Bearer your-token-here
Content-Type: application/json

{
  "content": "Thanks for the welcome! I'm excited to join."
}
```

##### Request Parameters

| Parameter | Type   | Required | Description                  |
|-----------|--------|----------|------------------------------|
| content   | string | Yes      | Post content (max 10000 chars) |

##### Success Response (201 Created)

```json
{
  "message": "Reply posted successfully.",
  "data": {
    "post": {
      "id": 6,
      "topic_id": 1,
      "content": "Thanks for the welcome! I'm excited to join.",
      "user": {
        "id": 2,
        "full_name": "Jane Smith"
      },
      "created_at": "2026-06-30T14:00:00.000000Z",
      "updated_at": "2026-06-30T14:00:00.000000Z"
    }
  }
}
```

##### Error Responses

**403 Forbidden - Topic Closed**
```json
{
  "message": "This topic is closed for replies."
}
```

---

#### PUT /api/v1/posts/{postId}

**P2**: Update own post content. Only the post author can edit.

**Authentication**: Required (Bearer token)

##### Request

```http
PUT /api/v1/posts/6
Authorization: Bearer your-token-here
Content-Type: application/json

{
  "content": "Updated: Thanks for the warm welcome!"
}
```

##### Success Response (200 OK)

```json
{
  "message": "Post updated successfully.",
  "data": {
    "post": {
      "id": 6,
      "topic_id": 1,
      "content": "Updated: Thanks for the warm welcome!",
      "user": { "id": 2, "full_name": "Jane Smith" },
      "created_at": "2026-06-30T14:00:00.000000Z",
      "updated_at": "2026-06-30T14:15:00.000000Z"
    }
  }
}
```

##### Error Responses

**403 Forbidden - Not Author**
```json
{
  "message": "You can only edit your own posts."
}
```

**403 Forbidden - Post Removed**
```json
{
  "message": "This post has been removed and cannot be edited."
}
```

---

#### DELETE /api/v1/posts/{postId}

**P3**: Soft-delete a post (sets `is_removed = true`). Only the post author or an admin can delete.

**Authentication**: Required (Bearer token)

##### Request

```http
DELETE /api/v1/posts/6
Authorization: Bearer your-token-here
```

##### Success Response (200 OK)

```json
{
  "message": "Post deleted successfully."
}
```

##### Error Responses

**403 Forbidden**
```json
{
  "message": "You are not authorized to delete this post."
}
```

---

## Post Visibility Endpoints

Post visibility allows a post author to exclude specific users from seeing their post. Only users in the same group can be excluded.

---

### POST /api/v1/posts/{postId}/visibility/exclude

**P5**: Exclude a user from seeing a post. Only the post author can manage visibility.

**Authentication**: Required (Bearer token)

#### Request

```http
POST /api/v1/posts/1/visibility/exclude
Authorization: Bearer your-token-here
Content-Type: application/json

{
  "user_id": 3
}
```

#### Request Parameters

| Parameter | Type    | Required | Description                    |
|-----------|---------|----------|--------------------------------|
| user_id   | integer | Yes      | ID of user to exclude          |

#### Success Response (201 Created)

```json
{
  "message": "User excluded from post successfully.",
  "data": {
    "visibility": {
      "id": 1,
      "post_id": 1,
      "excluded_user": {
        "id": 3,
        "full_name": "Bob Wilson"
      },
      "created_at": "2026-06-30T15:00:00.000000Z"
    }
  }
}
```

#### Error Responses

**403 Forbidden - Not Author**
```json
{
  "message": "Only the post author can manage visibility."
}
```

**409 Conflict - Already Excluded**
```json
{
  "message": "This user is already excluded from this post."
}
```

**422 Validation Error**
```json
{
  "message": "The specified user is not in your group."
}
```

---

### DELETE /api/v1/posts/{postId}/visibility/{userId}

**P6**: Remove a user from the post's exclusion list. Only the post author can remove exclusions.

**Authentication**: Required (Bearer token)

#### Request

```http
DELETE /api/v1/posts/1/visibility/3
Authorization: Bearer your-token-here
```

#### Success Response (200 OK)

```json
{
  "message": "User exclusion removed successfully."
}
```

---

### GET /api/v1/posts/{postId}/visibility

**P7**: List all users excluded from seeing a post. Only the post author can view the exclusion list.

**Authentication**: Required (Bearer token)

#### Request

```http
GET /api/v1/posts/1/visibility
Authorization: Bearer your-token-here
```

#### Success Response (200 OK)

```json
{
  "data": {
    "post_id": 1,
    "excluded_users_count": 1,
    "excluded_users": [
      {
        "id": 1,
        "post_id": 1,
        "excluded_user": {
          "id": 3,
          "full_name": "Bob Wilson"
        },
        "excluded_at": "2026-06-30T15:00:00.000000Z"
      }
    ]
  }
}
```

---

## Category Endpoints

Categories allow organizing posts within topics. Each group can have its own set of categories.

---

### GET /api/v1/categories

**C1**: List all categories in the authenticated user's group.

**Authentication**: Required (Bearer token)

#### Request

```http
GET /api/v1/categories
Authorization: Bearer your-token-here
```

#### Success Response (200 OK)

```json
{
  "data": [
    {
      "id": 1,
      "group_id": 1,
      "category_name": "General Discussion",
      "keyword_hints": "general,chat,talk",
      "posts_count": 12,
      "created_at": "2026-06-30T08:00:00.000000Z",
      "updated_at": "2026-06-30T08:00:00.000000Z"
    }
  ]
}
```

---

### GET /api/v1/categories/{categoryId}/topics

**C2**: List all active topics that have posts classified under a given category.

**Authentication**: Required (Bearer token)

#### Request

```http
GET /api/v1/categories/1/topics
Authorization: Bearer your-token-here
```

#### Success Response (200 OK)

```json
{
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "title": "Welcome to the Forum",
        "description": "Introduce yourself here",
        "status": "active",
        "post_type": "discussion",
        "group_id": 1,
        "creator": { "id": 1, "full_name": "John Doe" },
        "posts_count": 5,
        "created_at": "2026-06-30T10:00:00.000000Z"
      }
    ],
    "per_page": 20,
    "total": 1
  }
}
```

---

### Admin Category Management

> These endpoints require **admin** role (System Administrator or Group Administrator). Group Admins can only manage categories in groups they administer.

---

#### POST /api/v1/admin/categories

**C3**: Create a new category (admin only).

**Authentication**: Required (Bearer token) + Admin role

##### Request

```http
POST /api/v1/admin/categories
Authorization: Bearer admin-token-here
Content-Type: application/json

{
  "group_id": 1,
  "category_name": "Q&A",
  "keyword_hints": "question,answer,help"
}
```

##### Request Parameters

| Parameter     | Type   | Required | Description                    |
|---------------|--------|----------|--------------------------------|
| group_id      | int    | Yes      | Group to create category in    |
| category_name | string | Yes      | Category name (max 100, unique per group) |
| keyword_hints | string | No       | Comma-separated hints (max 5000) |

##### Success Response (201 Created)

```json
{
  "message": "Category created successfully.",
  "data": {
    "category": {
      "id": 2,
      "group_id": 1,
      "category_name": "Q&A",
      "keyword_hints": "question,answer,help",
      "created_at": "2026-06-30T16:00:00.000000Z",
      "updated_at": "2026-06-30T16:00:00.000000Z"
    }
  }
}
```

##### Error Responses

**409 Conflict**
```json
{
  "message": "A category with this name already exists in this group."
}
```

---

#### PUT /api/v1/admin/categories/{categoryId}

**C4**: Update a category (admin only).

**Authentication**: Required (Bearer token) + Admin role

##### Request

```http
PUT /api/v1/admin/categories/2
Authorization: Bearer admin-token-here
Content-Type: application/json

{
  "category_name": "Questions & Answers"
}
```

##### Success Response (200 OK)

```json
{
  "message": "Category updated successfully.",
  "data": {
    "category": {
      "id": 2,
      "group_id": 1,
      "category_name": "Questions & Answers",
      "keyword_hints": "question,answer,help",
      "created_at": "2026-06-30T16:00:00.000000Z",
      "updated_at": "2026-06-30T16:30:00.000000Z"
    }
  }
}
```

---

#### DELETE /api/v1/admin/categories/{categoryId}

**C5**: Delete a category (admin only). Posts classified under this category will have `category_id` set to null.

**Authentication**: Required (Bearer token) + Admin role

##### Request

```http
DELETE /api/v1/admin/categories/2
Authorization: Bearer admin-token-here
```

##### Success Response (200 OK)

```json
{
  "message": "Category deleted successfully.",
  "data": {
    "affected_posts": 3
  }
}
```

---

## Group Browsing Endpoints

Browse groups, their topics, and members. Access is controlled by group membership and admin role.

---

### GET /api/v1/groups

**G1**: List groups accessible to the authenticated user.

- **System Admin**: sees all groups
- **Group Admin**: sees groups they administer + their own group
- **Regular user**: sees only their own group

**Authentication**: Required (Bearer token)

#### Request

```http
GET /api/v1/groups
Authorization: Bearer your-token-here
```

#### Success Response (200 OK)

```json
{
  "data": [
    {
      "id": 1,
      "group_name": "Default Group",
      "description": "The default user group",
      "users_count": 25,
      "created_at": "2026-06-23T00:00:00.000000Z",
      "updated_at": "2026-06-23T00:00:00.000000Z"
    }
  ]
}
```

---

### GET /api/v1/groups/{groupId}

**G2**: Show a single group's details.

**Authentication**: Required (Bearer token)

#### Request

```http
GET /api/v1/groups/1
Authorization: Bearer your-token-here
```

#### Success Response (200 OK)

```json
{
  "data": {
    "id": 1,
    "group_name": "Default Group",
    "description": "The default user group",
    "members_count": 25,
    "created_by": {
      "id": 1,
      "full_name": "System Admin"
    },
    "created_at": "2026-06-23T00:00:00.000000Z",
    "updated_at": "2026-06-23T00:00:00.000000Z"
  }
}
```

#### Error Responses

**403 Forbidden**
```json
{
  "message": "You do not have access to this group."
}
```

---

### GET /api/v1/groups/{groupId}/topics

**G3**: List active topics in a specific group.

**Authentication**: Required (Bearer token)

#### Request

```http
GET /api/v1/groups/1/topics
Authorization: Bearer your-token-here
```

#### Success Response (200 OK)

Same paginated format as [GET /api/v1/topics](#get-apiv1topics).

---

### GET /api/v1/groups/{groupId}/members

**G4**: List members of a specific group.

**Authentication**: Required (Bearer token)

#### Request

```http
GET /api/v1/groups/1/members
Authorization: Bearer your-token-here
```

#### Success Response (200 OK)

```json
{
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "full_name": "John Doe",
        "email": "john@example.com",
        "role_id": 2,
        "account_status": "active",
        "last_active_at": "2026-06-30T15:45:00.000000Z",
        "profile_picture": null,
        "created_at": "2026-06-01T08:00:00.000000Z",
        "role": {
          "id": 2,
          "role_name": "Member"
        }
      }
    ],
    "per_page": 20,
    "total": 25
  }
}
```

---

## Admin Warning Management

> All warning endpoints require **admin** role (System Administrator or Group Administrator).
> - **System Admin**: can manage warnings for all users
> - **Group Admin**: can only manage warnings for users in their administered groups

---

### GET /api/v1/admin/warnings

**W1**: List all warnings, with optional filtering. Group-scoped for Group Admins.

**Authentication**: Required (Bearer token) + Admin role

#### Request

```http
GET /api/v1/admin/warnings?is_resolved=false&per_page=15
Authorization: Bearer admin-token-here
```

#### Query Parameters

| Parameter       | Type    | Required | Description                          |
|-----------------|---------|----------|--------------------------------------|
| user_id         | integer | No       | Filter by specific user              |
| is_resolved     | boolean | No       | Filter by resolved status            |
| is_acknowledged | boolean | No       | Filter by acknowledged status        |
| per_page        | integer | No       | Items per page (default: 15)         |

#### Success Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 3,
      "warning_number": 1,
      "reason": "Inactivity for 30 days",
      "response_deadline": "2026-07-07T00:00:00.000000Z",
      "is_acknowledged": false,
      "is_resolved": false,
      "resolved_at": null,
      "created_by": 1,
      "created_at": "2026-06-30T02:00:00.000000Z",
      "user": { "id": 3, "full_name": "Bob Wilson" },
      "createdBy": { "id": 1, "full_name": "System Admin" }
    }
  ],
  "pagination": {
    "total": 1,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1
  }
}
```

---

### GET /api/v1/admin/warnings/{warningId}

**W2**: Show a specific warning's details.

**Authentication**: Required (Bearer token) + Admin role

#### Request

```http
GET /api/v1/admin/warnings/1
Authorization: Bearer admin-token-here
```

#### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "id": 1,
    "user_id": 3,
    "warning_number": 1,
    "reason": "Inactivity for 30 days",
    "response_deadline": "2026-07-07T00:00:00.000000Z",
    "is_acknowledged": false,
    "is_resolved": false,
    "resolved_at": null,
    "created_by": 1,
    "created_at": "2026-06-30T02:00:00.000000Z",
    "user": { "id": 3, "full_name": "Bob Wilson" },
    "createdBy": { "id": 1, "full_name": "System Admin" }
  }
}
```

---

### POST /api/v1/admin/users/{userId}/warnings

**W3**: Issue a warning to a user. Automatically computes the escalating warning number (1→2→3). On the 3rd warning, the user is **automatically blacklisted**.

**Authentication**: Required (Bearer token) + Admin role

#### Request

```http
POST /api/v1/admin/users/3/warnings
Authorization: Bearer admin-token-here
Content-Type: application/json

{
  "reason": "Repeated violation of forum rules",
  "response_deadline": "2026-07-07T23:59:59"
}
```

#### Request Parameters

| Parameter         | Type   | Required | Description                          |
|-------------------|--------|----------|--------------------------------------|
| reason            | string | Yes      | Reason for warning (max 500 chars)   |
| response_deadline | string | Yes      | Deadline to respond (ISO date, must be future) |

#### Success Response (201 Created)

```json
{
  "success": true,
  "message": "Warning #2 issued successfully.",
  "data": {
    "warning": {
      "id": 2,
      "user_id": 3,
      "warning_number": 2,
      "reason": "Repeated violation of forum rules",
      "response_deadline": "2026-07-07T23:59:59.000000Z",
      "is_acknowledged": false,
      "is_resolved": false,
      "created_by": 1,
      "created_at": "2026-06-30T16:00:00.000000Z"
    },
    "warning_number": 2,
    "auto_blacklisted": false
  }
}
```

**On 3rd warning (auto-blacklist)**:
```json
{
  "success": true,
  "message": "Warning #3 issued. User has been automatically blacklisted (3 warnings reached).",
  "data": {
    "warning": { ... },
    "warning_number": 3,
    "auto_blacklisted": true
  }
}
```

---

### POST /api/v1/admin/warnings/{warningId}/resolve

**W4**: Resolve a warning. If no unresolved warnings remain for the user, their status reverts from `warned` to `active`.

**Authentication**: Required (Bearer token) + Admin role

#### Request

```http
POST /api/v1/admin/warnings/1/resolve
Authorization: Bearer admin-token-here
```

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Warning resolved successfully.",
  "data": {
    "warning": {
      "id": 1,
      "user_id": 3,
      "warning_number": 1,
      "reason": "Inactivity for 30 days",
      "is_resolved": true,
      "resolved_at": "2026-06-30T17:00:00.000000Z"
    },
    "remaining_unresolved": 0,
    "user_status": "active"
  }
}
```

#### Error Responses

**409 Conflict - Already Resolved**
```json
{
  "message": "This warning is already resolved."
}
```

---

## Admin Blacklist Management

> All blacklist endpoints require **admin** role (System Administrator or Group Administrator).
> - **System Admin**: can manage blacklists for all users
> - **Group Admin**: can only manage blacklists for users in their administered groups

---

### GET /api/v1/admin/blacklist-records

**W5**: List all blacklist records, with optional filtering. Group-scoped for Group Admins.

**Authentication**: Required (Bearer token) + Admin role

#### Request

```http
GET /api/v1/admin/blacklist-records?is_active=true&per_page=15
Authorization: Bearer admin-token-here
```

#### Query Parameters

| Parameter  | Type    | Required | Description                          |
|------------|---------|----------|--------------------------------------|
| user_id    | integer | No       | Filter by specific user              |
| is_active  | boolean | No       | `true` = not yet lifted, `false` = lifted |
| per_page   | integer | No       | Items per page (default: 15)         |

#### Success Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 3,
      "reason": "Automatic blacklist: 3 warnings issued",
      "expires_at": null,
      "lifted_at": null,
      "lifted_by": null,
      "created_at": "2026-06-30T16:00:00.000000Z",
      "user": { "id": 3, "full_name": "Bob Wilson" },
      "liftedBy": null
    }
  ],
  "pagination": {
    "total": 1,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1
  }
}
```

---

### POST /api/v1/admin/users/{userId}/blacklist

**W6**: Blacklist a user. Creates a blacklist record and sets user status to `blacklisted`.

**Authentication**: Required (Bearer token) + Admin role

#### Request

```http
POST /api/v1/admin/users/3/blacklist
Authorization: Bearer admin-token-here
Content-Type: application/json

{
  "reason": "Severe violation of community guidelines",
  "duration_days": 30
}
```

#### Request Parameters

| Parameter     | Type    | Required | Description                          |
|---------------|---------|----------|--------------------------------------|
| reason        | string  | Yes      | Reason for blacklisting (max 500)    |
| duration_days | integer | No       | Blacklist duration in days (1-365). Omit for permanent. |

#### Success Response (201 Created)

```json
{
  "success": true,
  "message": "User has been blacklisted successfully.",
  "data": {
    "blacklist_record": {
      "id": 2,
      "user_id": 3,
      "reason": "Severe violation of community guidelines",
      "expires_at": "2026-07-30T16:00:00.000000Z",
      "lifted_at": null,
      "lifted_by": null,
      "created_at": "2026-06-30T16:00:00.000000Z"
    },
    "expires_at": "2026-07-30T16:00:00.000000Z",
    "is_permanent": false
  }
}
```

#### Error Responses

**409 Conflict - Already Blacklisted**
```json
{
  "message": "User is already blacklisted."
}
```

---

### POST /api/v1/admin/blacklist-records/{recordId}/lift

**W7**: Lift a blacklist record. Sets `lifted_at` and `lifted_by`. If no other active blacklists remain for the user, their status reverts to `active`.

**Authentication**: Required (Bearer token) + Admin role

#### Request

```http
POST /api/v1/admin/blacklist-records/1/lift
Authorization: Bearer admin-token-here
```

#### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Blacklist lifted successfully.",
  "data": {
    "blacklist_record": {
      "id": 1,
      "user_id": 3,
      "reason": "Automatic blacklist: 3 warnings issued",
      "expires_at": null,
      "lifted_at": "2026-06-30T18:00:00.000000Z",
      "lifted_by": 1,
      "user": { "id": 3, "full_name": "Bob Wilson" },
      "liftedBy": { "id": 1, "full_name": "System Admin" }
    },
    "remaining_active_blacklists": 0,
    "user_status": "active"
  }
}
```

#### Error Responses

**409 Conflict - Already Lifted**
```json
{
  "message": "This blacklist record has already been lifted."
}
```

---

## Quiz & Assessment Endpoints

### Quiz CRUD (Lecturer/Admin)

#### GET /api/v1/quizzes

List all quizzes with question count, configuration, and lecturer info.

**Request:**
```http
GET /api/v1/quizzes?page=1&per_page=20 HTTP/1.1
Authorization: Bearer {token}
```

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | integer | 1 | Page number |
| `per_page` | integer | 20 | Items per page |

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "quizzes": [
      {
        "quiz_id": 1,
        "lecturer_id": 2,
        "title": "Laravel Basics Quiz",
        "description": "Test your understanding of Laravel",
        "target_category": "Student",
        "scheduled_date": "2026-07-10",
        "start_time": "10:00:00",
        "duration_minutes": 30,
        "is_active": false,
        "published_at": null,
        "questions_count": 2,
        "configuration": {
          "config_id": 1,
          "allow_late_join": false,
          "notification_minutes_before": 15,
          "lock_screen_on_start": true,
          "show_results_after_close": true,
          "show_correct_answers": false
        },
        "lecturer": {
          "id": 2,
          "full_name": "Dr. Smith"
        }
      }
    ]
  },
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

---

#### POST /api/v1/quizzes

Create a new quiz with default configuration.

**Request:**
```http
POST /api/v1/quizzes HTTP/1.1
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Midterm - Laravel Basics",
  "description": "Covers routing, controllers, Eloquent",
  "target_category": "Student",
  "scheduled_date": "2026-07-15",
  "start_time": "14:00",
  "duration_minutes": 60
}
```

**Request Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `title` | string | ✅ | Quiz title (max 255 chars) |
| `description` | string | ❌ | Optional description |
| `target_category` | string | ✅ | One of: `Student`, `Lecturer`, `Administrator`, `Member` |
| `scheduled_date` | string | ✅ | Date in Y-m-d format, must be today or later |
| `start_time` | string | ✅ | Time in H:i format (24-hour) |
| `duration_minutes` | integer | ✅ | Duration in minutes (1–480) |

**Success Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "quiz": {
      "quiz_id": 1,
      "title": "Midterm - Laravel Basics",
      "description": "Covers routing, controllers, Eloquent",
      "target_category": "Student",
      "scheduled_date": "2026-07-15",
      "start_time": "14:00:00",
      "duration_minutes": 60,
      "is_active": false,
      "published_at": null,
      "configuration": { ... },
      "lecturer": { ... }
    }
  },
  "message": "Quiz created"
}
```

**Error Responses:**
- `422` — Validation failed (missing fields, invalid date, etc.)

---

#### GET /api/v1/quizzes/{quiz}

Show a single quiz with all questions, answers, and configuration.

**Request:**
```http
GET /api/v1/quizzes/1 HTTP/1.1
Authorization: Bearer {token}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "quiz": {
      "quiz_id": 1,
      "title": "Laravel Basics Quiz",
      "questions": [
        {
          "question_id": 1,
          "question_text": "What is Laravel?",
          "question_type": "MCQ",
          "marks": 5,
          "question_order": 1,
          "answers": [
            { "answer_id": 1, "answer_text": "A PHP framework", "is_correct": true },
            { "answer_id": 2, "answer_text": "A JavaScript library", "is_correct": false }
          ]
        }
      ],
      "configuration": { ... },
      "lecturer": { ... }
    }
  }
}
```

---

#### PUT /api/v1/quizzes/{quiz}

Update quiz details and configuration. Cannot update if quiz is published.

**Request:**
```http
PUT /api/v1/quizzes/1 HTTP/1.1
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Updated Quiz Title",
  "description": "Updated description",
  "target_category": "Student",
  "scheduled_date": "2026-07-20",
  "start_time": "09:30",
  "duration_minutes": 45,
  "allow_late_join": true,
  "lock_screen_on_start": true,
  "show_results_after_close": true,
  "show_correct_answers": true
}
```

**Error Responses:**
- `422` — Cannot update a published quiz
- `422` — Validation failed

---

#### DELETE /api/v1/quizzes/{quiz}

Delete a quiz. Cannot delete if published.

**Request:**
```http
DELETE /api/v1/quizzes/1 HTTP/1.1
Authorization: Bearer {token}
```

**Success Response (200 OK):**
```json
{ "success": true, "message": "Quiz deleted" }
```

**Error Responses:**
- `422` — Cannot delete a published quiz

---

#### POST /api/v1/quizzes/{quiz}/publish

Publish the quiz announcement. Validates that the quiz has at least 1 question and is scheduled in the future.

**Request:**
```http
POST /api/v1/quizzes/1/publish HTTP/1.1
Authorization: Bearer {token}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": { "quiz": { ... } },
  "message": "Quiz published"
}
```

**Error Responses:**
- `422` — Quiz is already published
- `422` — Cannot publish a quiz with no questions
- `422` — Cannot publish with a past scheduled date/time

---

#### GET /api/v1/quizzes/{quiz}/report

Class performance report with aggregate stats and per-student breakdown.

**Request:**
```http
GET /api/v1/quizzes/1/report HTTP/1.1
Authorization: Bearer {token}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "summary": {
      "average_score": 72.5,
      "max_score": 95.0,
      "min_score": 45.0,
      "attempt_count": 20
    },
    "students": [
      {
        "student": { "id": 3, "full_name": "Jane Doe", "email": "jane@example.com" },
        "total_score": 85.0,
        "max_score": 100.0,
        "percentage": 85.0,
        "participation_mark": 5.0,
        "final_grade": 90.0
      }
    ]
  }
}
```

---

### Question Management

#### GET /api/v1/quizzes/{quiz}/questions

List all questions for a quiz, ordered by `question_order`.

**Request:**
```http
GET /api/v1/quizzes/1/questions HTTP/1.1
Authorization: Bearer {token}
```

---

#### POST /api/v1/quizzes/{quiz}/questions

Add a question with its answer options in one request.

**Request:**
```http
POST /api/v1/quizzes/1/questions HTTP/1.1
Authorization: Bearer {token}
Content-Type: application/json

{
  "question_text": "What is Laravel?",
  "question_type": "MCQ",
  "marks": 5,
  "answers": [
    { "answer_text": "A PHP framework", "is_correct": true },
    { "answer_text": "A JavaScript library", "is_correct": false }
  ]
}
```

**Validation rules by type:**
- `TF` — Exactly 2 answers, exactly 1 correct
- `MCQ` — At least 1 answer, at least 1 correct
- `Short` — No specific constraints

---

#### PUT /api/v1/quizzes/{quiz}/questions/{question}

Update question text, type, or marks.

---

#### DELETE /api/v1/quizzes/{quiz}/questions/{question}

Delete a question (cascades to its answers).

---

#### PUT /api/v1/quizzes/{quiz}/questions/reorder

Batch reorder questions.

**Request:**
```http
PUT /api/v1/quizzes/1/questions/reorder HTTP/1.1
Authorization: Bearer {token}
Content-Type: application/json

{
  "questions": [
    { "id": 3, "order": 1 },
    { "id": 1, "order": 2 },
    { "id": 2, "order": 3 }
  ]
}
```

---

### Answer Management

#### GET /api/v1/questions/{question}/answers

List all answers for a question.

---

#### POST /api/v1/questions/{question}/answers

Add an answer option.

**Request:**
```http
POST /api/v1/questions/1/answers HTTP/1.1
Authorization: Bearer {token}
Content-Type: application/json

{
  "answer_text": "A PHP framework",
  "is_correct": true
}
```

---

#### PUT /api/v1/answers/{answer}

Update an answer's text or correctness.

---

#### DELETE /api/v1/answers/{answer}

Delete an answer option.

---

### Student Quiz Execution

#### GET /api/v1/quizzes/{quiz}/announcement

Shows quiz metadata and timing information before the quiz starts. Used by the pre-quiz landing page.

**Request:**
```http
GET /api/v1/quizzes/1/announcement HTTP/1.1
Authorization: Bearer {token}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "quiz": {
      "id": 1,
      "title": "Laravel Basics Quiz",
      "description": "Test your understanding of Laravel",
      "duration_minutes": 30,
      "question_count": 2
    },
    "timing": {
      "scheduled_time": "2026-07-10T10:00:00+00:00",
      "time_until_start_seconds": 900,
      "has_started": false
    }
  }
}
```

**Error Responses:**
- `403` — Quiz not available for the user's role
- `404` — Quiz announcement not published yet

---

#### POST /api/v1/quizzes/{quiz}/attempt

Start a new quiz attempt. Creates a `StudentAttempt` record and returns the questions (without exposing correct answers).

**Request:**
```http
POST /api/v1/quizzes/1/attempt HTTP/1.1
Authorization: Bearer {token}
```

**Success Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "attempt": {
      "attempt_id": 5,
      "start_time": "2026-07-10T10:00:05+00:00",
      "is_late": false
    },
    "questions": [
      {
        "question_id": 1,
        "question_text": "What is Laravel?",
        "question_type": "MCQ",
        "marks": 5,
        "question_order": 1,
        "answers": [
          { "answer_id": 1, "answer_text": "A PHP framework" },
          { "answer_id": 2, "answer_text": "A JavaScript library" }
        ]
      }
    ],
    "time_remaining_seconds": 1795
  }
}
```

> **Note:** `is_correct` is intentionally stripped from answers to prevent cheating.

**Error Responses:**
- `403` — Quiz not currently active, or wrong role
- `409` — Student has already started this quiz

---

#### GET /api/v1/quizzes/{quiz}/attempt

Resume an existing attempt. Returns the current state (questions, saved answers, time remaining).

**Request:**
```http
GET /api/v1/quizzes/1/attempt HTTP/1.1
Authorization: Bearer {token}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "attempt": {
      "attempt_id": 5,
      "start_time": "2026-07-10T10:00:05+00:00",
      "submit_time": null,
      "is_late": false,
      "is_auto_submit": false,
      "is_submitted": false
    },
    "questions": [ ... ],
    "student_answers": {
      "1": 2,
      "2": 4
    },
    "time_remaining_seconds": 1200
  }
}
```

`student_answers` is a map of `question_id → selected_answer_id` for restoring the student's selections.

**Error Responses:**
- `404` — No attempt found

---

#### POST /api/v1/quizzes/{quiz}/answer

Save a single answer selection. Called via AJAX when the student clicks an option. Answers are saved immediately (not just on submit).

**Request:**
```http
POST /api/v1/quizzes/1/answer HTTP/1.1
Authorization: Bearer {token}
Content-Type: application/json

{
  "question_id": 1,
  "answer_id": 2
}
```

Pass `answer_id: null` to deselect/clear an answer.

**Success Response (200 OK):**
```json
{ "success": true }
```

**Error Responses:**
- `422` — Quiz already submitted (cannot change answers)

---

#### POST /api/v1/quizzes/{quiz}/answers/batch

Save multiple answers in a single request. More efficient than individual calls.

**Request:**
```http
POST /api/v1/quizzes/1/answers/batch HTTP/1.1
Authorization: Bearer {token}
Content-Type: application/json

{
  "answers": [
    { "question_id": 1, "answer_id": 2 },
    { "question_id": 2, "answer_id": null },
    { "question_id": 3, "answer_id": 10 }
  ]
}
```

**Success Response (200 OK):**
```json
{ "success": true, "message": "Answers saved" }
```

---

#### POST /api/v1/quizzes/{quiz}/submit

Manually submit the quiz. Triggers grading immediately.

**Request:**
```http
POST /api/v1/quizzes/1/submit HTTP/1.1
Authorization: Bearer {token}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "attempt": {
      "attempt_id": 5,
      "submit_time": "2026-07-10T10:30:00+00:00",
      "is_auto_submit": false
    }
  },
  "message": "Quiz submitted successfully"
}
```

**Error Responses:**
- `422` — Quiz already submitted

---

#### POST /api/v1/quizzes/{quiz}/auto-submit

Auto-submit when the timer expires. Called by the JavaScript countdown when it reaches 0. Sets `is_auto_submit = true` for audit purposes.

**Request:**
```http
POST /api/v1/quizzes/1/auto-submit HTTP/1.1
Authorization: Bearer {token}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "attempt": {
      "attempt_id": 5,
      "submit_time": "2026-07-10T10:30:05+00:00",
      "is_auto_submit": true
    }
  },
  "message": "Time expired. Quiz auto-submitted."
}
```

---

#### GET /api/v1/quizzes/{quiz}/status

Real-time quiz status endpoint polled by JavaScript. Returns different data depending on attempt state.

**Request:**
```http
GET /api/v1/quizzes/1/status HTTP/1.1
Authorization: Bearer {token}
```

**Response — No attempt yet:**
```json
{
  "success": true,
  "data": {
    "has_started": false,
    "is_submitted": false,
    "time_remaining": null,
    "time_until_start": 900
  }
}
```

**Response — In progress:**
```json
{
  "success": true,
  "data": {
    "has_started": true,
    "is_submitted": false,
    "time_remaining": 1200
  }
}
```

**Response — Already submitted:**
```json
{
  "success": true,
  "data": {
    "has_started": true,
    "is_submitted": true,
    "submitted_at": "2026-07-10T10:30:00+00:00",
    "time_remaining": 0
  }
}
```

---

### Results & Reports

#### GET /api/v1/quizzes/{quiz}/result

Show the authenticated student's grade and per-question review for a completed quiz.

**Request:**
```http
GET /api/v1/quizzes/1/result HTTP/1.1
Authorization: Bearer {token}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "grade": {
      "grade_id": 1,
      "total_score": 8.0,
      "max_score": 10.0,
      "percentage": 80.0,
      "participation_mark": 5.0,
      "final_grade": 13.0,
      "graded_at": "2026-07-10T10:30:00+00:00"
    },
    "config": {
      "show_correct_answers": true,
      "show_results_after_close": true
    }
  }
}
```

**Error Responses:**
- `403` — Results not yet available (`show_results_after_close` is false)

---

#### GET /api/v1/lecturer/quizzes/{quiz}/grades

List all grades for a quiz (lecturer report).

**Request:**
```http
GET /api/v1/lecturer/quizzes/1/grades HTTP/1.1
Authorization: Bearer {token}
```

---

#### GET /api/v1/lecturer/grades/{grade}

Single grade detail with per-question breakdown.

---

#### GET /api/v1/lecturer/quizzes/{quiz}/grades/export

Export all grades as a CSV file download.

**Request:**
```http
GET /api/v1/lecturer/quizzes/1/grades/export HTTP/1.1
Authorization: Bearer {token}
```

**Response:** CSV file with columns: `Student Name, Email, Score, Max, Percentage, Participation, Final Grade`

---

### Quiz Notifications

#### GET /api/v1/quizzes/upcoming

List upcoming published quizzes that haven't started yet.

---

#### GET /api/v1/quizzes/live

List currently active quizzes.

---

#### GET /api/v1/me/quiz-history

List the authenticated student's past quiz attempts with grades.

---

#### GET /api/v1/me/quiz-notifications

List quiz-related notifications (upcoming, live, results).

---

#### POST /api/v1/notifications/{id}/read

Mark a notification as read.

**Request:**
```http
POST /api/v1/notifications/5/read HTTP/1.1
Authorization: Bearer {token}
```

**Success Response (200 OK):**
```json
{ "success": true, "message": "Marked as read" }
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
