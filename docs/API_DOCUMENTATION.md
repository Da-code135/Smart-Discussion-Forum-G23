# Smart Discussion Forum — API Guide

> **For desktop app developers.**
> This guide explains how the API works, what each endpoint does, and how your desktop client should talk to the server. Code examples use HTTP directly so you can adapt them to any language (C#, Python, Java, Electron, Tauri, etc.).

**Base URL:** `http://localhost:8000/api/v1`

---

## Table of Contents

1. [What This App Is (in plain English)](#what-this-app-is-in-plain-english)
2. [How Users Are Organized — Groups & Roles](#how-users-are-organized--groups--roles)
3. [Authentication — How Your Desktop Client Logs In](#authentication--how-your-desktop-client-logs-in)
   - [The token lifecycle](#the-token-lifecycle)
   - [Sending the token with every request](#sending-the-token-with-every-request)
   - [Token management endpoints](#token-management-endpoints)
4. [Working with the Forum](#working-with-the-forum)
   - [Topics — the conversation starters](#topics--the-conversation-starters)
   - [Posts — the replies inside a topic](#posts--the-replies-inside-a-topic)
   - [Post visibility — hiding posts from specific people](#post-visibility--hiding-posts-from-specific-people)
   - [Categories — organising topics by subject](#categories--organising-topics-by-subject)
   - [Exporting and sharing topics](#exporting-and-sharing-topics)
5. [Browsing Groups](#browsing-groups)
6. [Notifications](#notifications)
7. [Quiz & Assessment System](#quiz--assessment-system)
   - [For lecturers/admins — creating and managing quizzes](#for-lecturersadmins--creating-and-managing-quizzes)
   - [For students — taking a quiz](#for-students--taking-a-quiz)
   - [Results and reports](#results-and-reports)
8. [Admin Features — Managing Users and Content](#admin-features--managing-users-and-content)
   - [User management](#user-management)
   - [Warnings](#warnings)
   - [Blacklist](#blacklist)
   - [Post moderation](#post-moderation)
   - [Bulk operations](#bulk-operations)
   - [Advanced search](#advanced-search)
   - [System configuration](#system-configuration)
   - [Audit logs](#audit-logs)
   - [IP whitelist](#ip-whitelist)
   - [Group management](#group-management)
9. [How the Desktop Client Should Work (End-to-End Flow)](#how-the-desktop-client-should-work-end-to-end-flow)
10. [Error Responses](#error-responses)
11. [Rate Limits — How Fast You Can Send Requests](#rate-limits--how-fast-you-can-send-requests)
12. [Security Headers](#security-headers)
13. [CORS Configuration](#cors-configuration)
14. [Activity Monitoring — Automatic Inactivity Handling](#activity-monitoring--automatic-inactivity-handling)
15. [Quick Reference — All Endpoints at a Glance](#quick-reference--all-endpoints-at-a-glance)

---

## What This App Is (in plain English)

This is a **discussion forum** where users sign up, join a group, create discussion topics, and reply to each other. Think of it like a private online community for a school, company, or organisation.

It also has a **quiz system** where lecturers can create timed quizzes, students can take them inside the app, and results get graded automatically.

Admins can **warn** or **blacklist** users who break the rules, moderate posts, and manage everything from a set of admin screens.

Your desktop app talks to this server through a REST API. Everything the server can do — registering users, posting replies, running quizzes, managing warnings — is available through this API.

---

## How Users Are Organized — Groups & Roles

This is the most important concept to understand, because **everything in the app is scoped to a group**.

### Groups

Every user belongs to exactly **one group**. A group is like a classroom, a department, or a team. When you're logged in, you can only see topics, posts, and members from **your own group** — the server enforces this on every single request (it's called **group isolation**). The only exception is System Administrators, who can see everything across all groups.

### Roles

Each user has one of these roles:

| Role | What they can do |
|------|------------------|
| **Member** | A regular user. Can create topics, reply to posts, take quizzes, and see content in their own group. This is the default role when someone registers. |
| **Group Administrator** | Can manage users, warnings, and blacklists **within the groups they administer**. They can also create and manage quizzes. They cannot see groups they don't admin. |
| **System Administrator** | Full access to everything across all groups. Can create/delete groups, change roles, manage any user, configure system settings. |
| **Lecturer** | Can create and manage quizzes, grade students, view reports. They bypass posting rate limits (more on that later). |

The front page of your desktop app should show different menus and options depending on the user's role. For example:
- A **Member** just sees the forum (topics, posts, categories) and any quizzes assigned to them.
- A **Group Admin** also sees admin options: manage users in their group, issue warnings, etc.
- A **System Admin** sees everything, including system configuration and group management.
- A **Lecturer** sees quiz creation tools and grade reports.

---

## Authentication — How Your Desktop Client Logs In

The API uses **token-based authentication** via Laravel Sanctum. Here's how it works end-to-end:

### Step 1: Register or Login

**Register** (first time — creates an account):

```
POST /api/v1/register
Content-Type: application/json

{
  "full_name": "John Doe",
  "email": "john@example.com",
  "password": "Password123",
  "password_confirmation": "Password123"
}
```

What happens on the server:
1. It validates your input (name, unique email, password with at least 8 chars including uppercase, lowercase, and a number).
2. It creates a user with the role **Member** in the **General** group.
3. It sends a verification email (the user can verify later — this is optional).
4. It generates an API token and sends it back.

**Response (201 Created):**
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
    "group": "General",
    "email_verified_at": null,
    "last_active_at": null
  }
}
```

**Login** (returning user):

```
POST /api/v1/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "Password123"
}
```

The server checks the credentials, then checks two gates:

1. **Blacklist gate** — if the user was blacklisted, login is blocked with a 403 error that says when the blacklist expires.
2. **Warning gate** — if the user has an unacknowledged warning, login returns a special 403 response. The user needs to acknowledge the warning before they can continue.

If everything is fine, you get back a token and user data:

**Response (200 OK):**
```json
{
  "message": "Login successful",
  "token": "1|abc123def456...",
  "user": {
    "id": 1,
    "full_name": "John Doe",
    "email": "john@example.com",
    "account_status": "active",
    "role": "Member",
    "group": "General",
    "email_verified_at": "2026-06-26T10:30:00.000000Z",
    "last_active_at": "2026-06-26T15:45:00.000000Z"
  }
}
```

**What your desktop client should do:** Save this token somewhere safe (a config file, keychain, or encrypted storage). Use it for every subsequent API call.

> **Registration rate limit:** 3 requests per 60 seconds per IP address.
> **Login rate limit:** 5 attempts per 30 seconds per email address. If you hit this, the server tells you how many seconds to wait.

### Step 2: Send the token with every request

Every protected endpoint requires the token in the `Authorization` header:

```
Authorization: Bearer 1|abc123def456...
```

Like this:

```
GET /api/v1/me
Authorization: Bearer 1|abc123def456...
```

If the token is missing, expired, or invalid, the server responds with `401 Unauthorized`.

### The token lifecycle

- A **token is created** when the user registers or logs in.
- A token has **no expiry by default** — it lasts until the user logs out or explicitly revokes it.
- A **user can have multiple active tokens** at the same time. This means they could be logged in on the desktop app and the web app simultaneously with two different tokens.
- When the user **logs out**, the current token is revoked (the server marks it as deleted from the database).
- When the user **resets their password**, **all tokens** are revoked. They must log in again.
- Each token records when it was **last used**, so you can see which tokens are active and which have gone stale.

### Token management endpoints

Your desktop client should offer a "sessions" or "devices" screen where users can see and manage their active tokens.

**List all active tokens:**

```
GET /api/v1/tokens
Authorization: Bearer 1|abc123def456...
```

Response includes each token's ID, when it was created, last used, and when it expires (if at all).

**Refresh the current token** (gets a new token, invalidates the old one):

```
POST /api/v1/token/refresh
Authorization: Bearer 1|abc123def456...
```

Returns a new token. Your desktop client should save this new token and discard the old one.

**Revoke a specific token** (e.g., if the user wants to log out a different device):

```
DELETE /api/v1/tokens/123
Authorization: Bearer 1|abc123def456...
```

### Logout

```
POST /api/v1/logout
Authorization: Bearer 1|abc123def456...
```

The server revokes the current token. Your desktop client should delete the saved token and return to the login screen.

### Forgot / Reset Password (Desktop Client Flow)

The password reset flow is designed to work **entirely inside your desktop app** — the user never has to open a browser.

**Step 1: Request a reset code**

```
POST /api/v1/password/forgot
Content-Type: application/json

{
  "email": "john@example.com"
}
```

The server sends a **6-digit OTP code** (like `482910`) to the user's email address. The code expires in 10 minutes.

Your desktop app should show an "enter the code" screen immediately after this call — the user reads the code from their email and types it into your app.

Rate limit: 3 requests per 15 minutes per email address.

**Step 2: Reset the password with the code**

```
POST /api/v1/password/reset
Content-Type: application/json

{
  "email": "john@example.com",
  "otp": "482910",
  "password": "MyNewPassword123",
  "password_confirmation": "MyNewPassword123"
}
```

The server validates the OTP, resets the password, and **revokes all existing tokens**. The user must log in again with their new password.

Rate limit: 5 OTP guess attempts per 10 minutes per email address (prevents brute-forcing the 6-digit code).

---

## Working with the Forum

### Topics — the conversation starters

A **topic** is a new conversation. Think of it like a forum thread. Every topic belongs to a group, so users only see topics from their own group.

Topics have a **type**: `discussion` (open conversation) or `question` (expects an answer). And a **status**: `active` (open for replies) or `archived` (closed, no more replies).

**List topics** (in your group, most recent first, paginated):

```
GET /api/v1/topics
Authorization: Bearer 1|abc123def456...
```

Returns a paginated list of topics with the creator's name and a count of replies.

**Get a single topic with its posts:**

```
GET /api/v1/topics/{topicId}
Authorization: Bearer 1|abc123def456...
```

Returns the topic detail plus all posts inside it (paginated, with the poster's name). Posts that are removed or that the current user has been excluded from seeing are filtered out automatically.

**Filter topics by type:**

```
GET /api/v1/topics/type/discussion
GET /api/v1/topics/type/question
```

**Create a new topic:**

```
POST /api/v1/topics
Authorization: Bearer 1|abc123def456...
Content-Type: application/json

{
  "title": "How do I reset my password?",
  "description": "I've been trying to reset my password but the email never arrives.",
  "post_type": "question"
}
```

- `title` (required) — max 255 characters, must be unique across all topics.
- `description` (required) — the body text, max 10,000 characters.
- `post_type` (optional) — `discussion` (default) or `question` (shows an answered/not-answered toggle).

**Anti-flood protection:** Regular users can create at most **3 topics per 60 seconds**. Admins and Lecturers bypass this limit.

**Update a topic** (only the creator or an admin can do this):

```
PUT /api/v1/topics/{topicId}
Authorization: Bearer 1|abc123def456...
```

You can update the title, description, status, and post_type.

**Archive a topic** (soft delete — sets status to `archived`):

```
DELETE /api/v1/topics/{topicId}
Authorization: Bearer 1|abc123def456...
```

Only the creator or an admin can archive a topic. Archived topics still exist in the database but are hidden from the topic list.

**Toggle "answered"** (for question-type topics only):

```
POST /api/v1/topics/{topicId}/toggle-answered
Authorization: Bearer 1|abc123def456...
```

**Toggle "pinned"** (pinned topics appear at the top):

```
POST /api/v1/topics/{topicId}/toggle-pinned
Authorization: Bearer 1|abc123def456...
```

### Posts — the replies inside a topic

A **post** is a reply someone writes inside a topic. Anyone in the same group can reply to an active topic.

**List posts in a topic** (same as getting the topic detail):

```
GET /api/v1/topics/{topicId}/posts
Authorization: Bearer 1|abc123def456...
```

**Create a reply:**

```
POST /api/v1/topics/{topicId}/posts
Authorization: Bearer 1|abc123def456...
Content-Type: application/json

{
  "content": "Check your spam folder — the email usually ends up there."
}
```

- `content` is required, max 10,000 characters.
- The topic must be in your group and have status `active`.
- **Anti-flood:** Regular users can post at most **5 replies per 60 seconds**. Admins and Lecturers bypass this.

**Edit your own post:**

```
PUT /api/v1/posts/{postId}
Authorization: Bearer 1|abc123def456...
```

Only the original author can edit a post. Posts that have been removed (soft-deleted by an admin) cannot be edited.

**Delete your own post** (soft delete — just hides it):

```
DELETE /api/v1/posts/{postId}
Authorization: Bearer 1|abc123def456...
```

The post author or an admin can delete a post. It's a soft delete — the `is_removed` flag is set to `true`, and the post is excluded from normal views.

### Post visibility — hiding posts from specific people

This is the "mute" feature. If you write a post and want to hide it from a specific person, you can exclude them. They won't see your post when they view the topic.

**Who can use this:** Only the post author. You cannot manage visibility of someone else's post.

**Exclude a user:**

```
POST /api/v1/posts/{postId}/visibility/exclude
Authorization: Bearer 1|abc123def456...
Content-Type: application/json

{
  "user_id": 3
}
```

- The excluded user must be in your group (you can't exclude someone from a different group).
- Returns 409 Conflict if the user is already excluded.

**Remove an exclusion:**

```
DELETE /api/v1/posts/{postId}/visibility/{userId}
Authorization: Bearer 1|abc123def456...
```

**See who you've excluded:**

```
GET /api/v1/posts/{postId}/visibility
Authorization: Bearer 1|abc123def456...
```

### Categories — organising topics by subject

Categories are like labels or folders. Each group can have its own set of categories. When a topic is created, it can be classified under a category. Categories have "keyword hints" to help auto-classify posts based on keywords in the content.

**List categories in your group:**

```
GET /api/v1/categories
Authorization: Bearer 1|abc123def456...
```

**List topics under a category:**

```
GET /api/v1/categories/{categoryId}/topics
Authorization: Bearer 1|abc123def456...
```

**Admin category management:** Only admins can create, update, or delete categories.

```
POST /api/v1/admin/categories       # Create
PUT /api/v1/admin/categories/{id}   # Update
DELETE /api/v1/admin/categories/{id} # Delete
```

- When creating: provide `group_id`, `category_name` (unique per group), and optional `keyword_hints` (comma-separated).
- Deleting a category sets `category_id = null` on all posts that used it (the posts are NOT deleted).

### Exporting and sharing topics

**Export a topic as PDF:**

```
GET /api/v1/topics/{topicId}/export/pdf
Authorization: Bearer 1|abc123def456...
```

Downloads a PDF file containing the topic and all its posts. Useful for printing or saving offline.

**Generate a shareable link:**

```
POST /api/v1/topics/{topicId}/share
Authorization: Bearer 1|abc123def456...
```

Generates a signed URL that anyone can open in a browser — no login required. The signed URL is cryptographically signed by the server so it can't be tampered with.

Your desktop app should open this URL in the user's default browser or display it as a link they can copy and share.

**Access a shared topic** (no authentication):

```
GET /api/v1/topics/{topicId}/shared?signature=...
```

This route is public — anyone with the signed URL can view the topic. Your desktop app doesn't need to call this; it's meant for the browser.

---

## Browsing Groups

These endpoints let users explore groups, topics, and members they have access to.

**Who can see what:**

| Role | Can see |
|------|---------|
| System Admin | All groups |
| Group Admin | Groups they administer + their own group |
| Regular user | Only their own group |

**List accessible groups:**

```
GET /api/v1/groups
Authorization: Bearer 1|abc123def456...
```

**Show group details:**

```
GET /api/v1/groups/{groupId}
Authorization: Bearer 1|abc123def456...
```

**List topics in a group:**

```
GET /api/v1/groups/{groupId}/topics
Authorization: Bearer 1|abc123def456...
```

**List members of a group:**
```
GET /api/v1/groups/{groupId}/members
Authorization: Bearer 1|abc123def456...
```

Returns paginated member list with each user's full name, email, role name, account status, and last active timestamp.

---

## Notifications

Users receive notifications for various events (someone replied to their topic, a quiz is upcoming, a warning was issued, etc.).

**List my notifications:**
```
GET /api/v1/me/notifications
Authorization: Bearer 1|abc123def456...
```

Returns a paginated list. Each notification has a `type` (string), `data` (JSON payload with details), and `read_at` (null if unread).

**Mark a notification as read:**
```
POST /api/v1/notifications/{id}/read
Authorization: Bearer 1|abc123def456...
```

Your desktop app should show unread notifications with a visual indicator (e.g., a badge on a bell icon) and mark them as read when the user taps them.

---

## Quiz & Assessment System

The quiz system has two sides:

- **Lecturer/Admin side:** Create quizzes, add questions, schedule them, publish, and view results.
- **Student side:** See announcements, start an attempt, answer questions, submit.

### For lecturers/admins — creating and managing quizzes

**List all quizzes** (paginated with question count and configuration):

```
GET /api/v1/quizzes?page=1&per_page=20
Authorization: Bearer admin-token
```

**Create a quiz:**

```
POST /api/v1/quizzes
Authorization: Bearer admin-token
Content-Type: application/json

{
  "title": "Midterm Exam - Laravel Basics",
  "description": "Covers routing, controllers, Eloquent",
  "target_category": "Student",
  "scheduled_date": "2026-07-15",
  "start_time": "14:00",
  "duration_minutes": 60
}
```

- `target_category` must be one of: `Student`, `Lecturer`, `Administrator`, `Member` — this controls who can take the quiz.
- `scheduled_date` must be today or later.
- `duration_minutes` can be 1 to 480 (8 hours).

A default configuration is created alongside the quiz. Default settings: `allow_late_join = false`, `lock_screen_on_start = true`, `show_results_after_close = true`, `show_correct_answers = false`.

**View a quiz** (with all questions, answers, and config):

```
GET /api/v1/quizzes/{quiz}
Authorization: Bearer admin-token
```

**Update a quiz:**

```
PUT /api/v1/quizzes/{quiz}
Authorization: Bearer admin-token
```

You can update any field plus the quiz configuration (e.g., `allow_late_join`, `show_correct_answers`, etc.). **Cannot update if the quiz is already published.**

**Delete a quiz:**

```
DELETE /api/v1/quizzes/{quiz}
Authorization: Bearer admin-token
```

**Cannot delete if published.**

**Publish a quiz** (makes it visible to students):

```
POST /api/v1/quizzes/{quiz}/publish
Authorization: Bearer admin-token
```

Validates that:
1. The quiz has at least 1 question.
2. It's scheduled in the future (not a past date/time).
3. It's not already published.

**View class performance report:**

```
GET /api/v1/quizzes/{quiz}/report
Authorization: Bearer admin-token
```

Returns average/min/max scores, attempt count, and a per-student breakdown.

#### Managing questions

Questions are nested under a quiz. Each question has a `question_type` (MCQ, True/False, or Short Answer), `marks`, and a set of answer options.

**Add a question with answers:**

```
POST /api/v1/quizzes/{quiz}/questions
Authorization: Bearer admin-token
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

Validation rules by question type:
- **TF** (True/False) — Must have exactly 2 answers, exactly 1 marked correct.
- **MCQ** — Must have at least 1 answer, at least 1 marked correct.
- **Short** (Short Answer) — No specific constraints on answers.

**List questions for a quiz:**

```
GET /api/v1/quizzes/{quiz}/questions
Authorization: Bearer admin-token
```

**Update a question:**

```
PUT /api/v1/quizzes/{quiz}/questions/{question}
Authorization: Bearer admin-token
```

**Delete a question** (deletes its answers too):

```
DELETE /api/v1/quizzes/{quiz}/questions/{question}
Authorization: Bearer admin-token
```

**Reorder questions:**

```
PUT /api/v1/quizzes/{quiz}/questions/reorder
Authorization: Bearer admin-token
Content-Type: application/json

{
  "questions": [
    { "id": 3, "order": 1 },
    { "id": 1, "order": 2 },
    { "id": 2, "order": 3 }
  ]
}
```

#### Managing answer options separately

You can also add/edit/delete answers independently of questions:

```
GET    /api/v1/questions/{question}/answers          # List answers
POST   /api/v1/questions/{question}/answers          # Add answer
PUT    /api/v1/answers/{answer}                      # Update answer
DELETE /api/v1/answers/{answer}                      # Delete answer
```

### For students — taking a quiz

**Before the quiz starts** — show the announcement/landing page:

```
GET /api/v1/quizzes/{quiz}/announcement
Authorization: Bearer student-token
```

Returns quiz title, description, duration, question count, and time until start (in seconds). Use this to show a "Quiz starts in X minutes" screen.

**Check real-time status** (useful for polling):

```
GET /api/v1/quizzes/{quiz}/status
Authorization: Bearer student-token
```

Returns `has_started`, `is_submitted`, `time_remaining`, and `time_until_start`. Your desktop app can poll this endpoint (e.g., every 5 seconds) to detect when the quiz becomes active.

**Start the quiz attempt:**

```
POST /api/v1/quizzes/{quiz}/attempt
Authorization: Bearer student-token
```

This creates an attempt record and returns the questions — **without exposing the correct answers**. It also returns `time_remaining_seconds` for the countdown timer.

A student cannot start the same quiz twice (returns 409 Conflict if they try).

**Resume an existing attempt:**

```
GET /api/v1/quizzes/{quiz}/attempt
Authorization: Bearer student-token
```

Returns the questions, the student's saved answers (so your app can restore their selections), and the remaining time. Useful if the student closes your app and comes back.

**Save a single answer** (call this whenever the student clicks an option):

```
POST /api/v1/quizzes/{quiz}/answer
Authorization: Bearer student-token
Content-Type: application/json

{
  "question_id": 1,
  "answer_id": 2
}
```

Pass `answer_id: null` to deselect/clear an answer.

**Save multiple answers at once** (more efficient):

```
POST /api/v1/quizzes/{quiz}/answers/batch
Authorization: Bearer student-token
Content-Type: application/json

{
  "answers": [
    { "question_id": 1, "answer_id": 2 },
    { "question_id": 2, "answer_id": null }
  ]
}
```

**Submit the quiz manually:**

```
POST /api/v1/quizzes/{quiz}/submit
Authorization: Bearer student-token
```

Triggers grading immediately. After submission, no more answers can be saved.

**Auto-submit** (when the countdown reaches 0):

```
POST /api/v1/quizzes/{quiz}/auto-submit
Authorization: Bearer student-token
```

Same as submit, but sets `is_auto_submit = true` for audit purposes (so you can distinguish "student finished early" from "timer expired").

> **Important:** Your desktop app should include a countdown timer that triggers auto-submit when it hits 0. Don't rely solely on the server — the server will also auto-submit, but the student should see their time run out locally first.

### Results and reports

**Student — view their own result:**

```
GET /api/v1/quizzes/{quiz}/result
Authorization: Bearer student-token
```

Returns the grade (total score, max score, percentage, participation mark, final grade) plus the quiz configuration flags (whether correct answers are shown, whether results are visible). Respects `show_results_after_close` — if that's false, this endpoint returns 403.

**List upcoming quizzes** (for the student dashboard):

```
GET /api/v1/quizzes/upcoming
Authorization: Bearer student-token
```

**List currently active (live) quizzes:**

```
GET /api/v1/quizzes/live
Authorization: Bearer student-token
```

**Student quiz history** (past attempts with grades):

```
GET /api/v1/me/quiz-history
Authorization: Bearer student-token
```

**Student quiz notifications:**

```
GET /api/v1/me/quiz-notifications
Authorization: Bearer student-token
```

**Lecturer — view all grades for a quiz:**

```
GET /api/v1/lecturer/quizzes/{quiz}/grades
Authorization: Bearer admin-token
```

**Lecturer — single grade detail with per-question breakdown:**

```
GET /api/v1/lecturer/grades/{grade}
Authorization: Bearer admin-token
```

**Lecturer — export grades as CSV:**

```
GET /api/v1/lecturer/quizzes/{quiz}/grades/export
Authorization: Bearer admin-token
```

Downloads a CSV file with columns: Student Name, Email, Score, Max, Percentage, Participation, Final Grade.

---

## Admin Features — Managing Users and Content

All admin endpoints are under `/api/v1/admin`. They require the `admin` middleware, which checks that the user is either a System Administrator or a Group Administrator.

**Important scope rules:**
- **System Administrators** can do everything across all groups.
- **Group Administrators** are scoped to the groups they administer. They can only see/manage users, warnings, blacklists, and content within those groups.
- Some actions (creating users, deleting users, changing roles, creating/deleting groups, system config, IP whitelist) are **System Admin only** and enforced in the controller.

### User management

**List users:**
```
GET /api/v1/admin/users
Authorization: Bearer admin-token
```

With optional query parameters: `?role=Member`, `?status=active`, `?group_id=1`, `?search=john`, etc.

**Show a single user:**
```
GET /api/v1/admin/users/{userId}
Authorization: Bearer admin-token
```

**Create a user** (System Admin only):
```
POST /api/v1/admin/users
Authorization: Bearer admin-token
```

**Update a user:**
```
PUT /api/v1/admin/users/{userId}
Authorization: Bearer admin-token
```

**Delete a user** (System Admin only — permanently deletes everything):
```
DELETE /api/v1/admin/users/{userId}
Authorization: Bearer admin-token
```

**Change a user's role** (System Admin only):
```
POST /api/v1/admin/users/{userId}/change-role
Authorization: Bearer admin-token
```

**Reset a user's password** (forces them to use the forgot-password flow):
```
POST /api/v1/admin/users/{userId}/reset-password
Authorization: Bearer admin-token
```

### Warnings

Warnings are how admins notify users about policy violations. They follow an escalation system: warnings are numbered 1, 2, 3. On the **3rd warning, the user is automatically blacklisted**.

**List warnings:**
```
GET /api/v1/admin/warnings?is_resolved=false&per_page=15
Authorization: Bearer admin-token
```

You can filter by `user_id`, `is_resolved`, `is_acknowledged`, and set items per page.

**Show a single warning:**
```
GET /api/v1/admin/warnings/{warningId}
Authorization: Bearer admin-token
```

**Issue a warning to a user:**
```
POST /api/v1/admin/users/{userId}/warnings
Authorization: Bearer admin-token
Content-Type: application/json

{
  "reason": "Repeated violation of forum rules",
  "response_deadline": "2026-07-07T23:59:59"
}
```

- `reason` (required) — max 500 characters.
- `response_deadline` (required) — ISO date, must be in the future. This is the deadline for the user to respond/acknowledge.
- The server automatically computes the warning number (1, 2, or 3).
- If this is warning #3, `auto_blacklisted` is `true` in the response, and the user is immediately blacklisted.

**Resolve a warning:**
```
POST /api/v1/admin/warnings/{warningId}/resolve
Authorization: Bearer admin-token
```

If no other unresolved warnings remain for the user, their status reverts from `warned` to `active`.

### Blacklist

Blacklisting blocks a user from logging in. It's either manual (admin blacklists someone) or automatic (3 warnings).

**List blacklist records:**
```
GET /api/v1/admin/blacklist-records?is_active=true
Authorization: Bearer admin-token
```

**Blacklist a user:**
```
POST /api/v1/admin/users/{userId}/blacklist
Authorization: Bearer admin-token
Content-Type: application/json

{
  "reason": "Severe violation of community guidelines",
  "duration_days": 30
}
```

- `duration_days` is optional. Omit for permanent, or set 1-365 for a timed blacklist.
- If the user is already blacklisted, returns 409 Conflict.

**Lift a blacklist:**
```
POST /api/v1/admin/blacklist-records/{recordId}/lift
Authorization: Bearer admin-token
```

If no other active blacklists remain for the user, their status reverts to `active`.

### Post moderation

Admins can moderate (remove) inappropriate posts and ignore reports.

**List reported content:**
```
GET /api/v1/admin/moderation
Authorization: Bearer admin-token
```

**Remove a post:**
```
POST /api/v1/admin/moderation/{post}/remove
Authorization: Bearer admin-token
```

**Ignore a report on a post:**
```
POST /api/v1/admin/moderation/{post}/ignore
Authorization: Bearer admin-token
```

### Bulk operations

These let admins act on many users at once. Useful for mass enrollment, semester rollover, etc.

```
POST /api/v1/admin/bulk/change-roles        # Change role for multiple users
POST /api/v1/admin/bulk/change-status        # Update account status
POST /api/v1/admin/bulk/assign-group         # Move users to a different group
POST /api/v1/admin/bulk/blacklist            # Blacklist multiple users
POST /api/v1/admin/bulk/lift-blacklist       # Lift blacklists for multiple users
POST /api/v1/admin/bulk/warn                 # Issue warnings to multiple users
POST /api/v1/admin/bulk/assign-group-admins  # Assign group admins in bulk
```

All require admin role. Each accepts a list of user IDs plus the relevant parameters (role ID, status, group ID, reason, etc.).

### Advanced search

These let admins search across the entire system with filters.

```
POST /api/v1/admin/search/users              # Search users with filters
POST /api/v1/admin/search/groups             # Search groups with filters
POST /api/v1/admin/search/audit-logs         # Search audit logs with filters
POST /api/v1/admin/search/warnings           # Search warnings with filters
GET  /api/v1/admin/search/options/{model}    # Get available filter options for a model
GET  /api/v1/admin/search/suggestions/{type} # Get search suggestions
```

### System configuration

System-wide settings stored in the database (System Admin only).

```
GET    /api/v1/admin/system-config          # List all config
GET    /api/v1/admin/system-config/{key}    # Get a specific config value
PUT    /api/v1/admin/system-config          # Update config values
```

Known config keys:
| Key | Default | What it controls |
|-----|---------|------------------|
| `inactivity_warning_days` | 30 | Days of inactivity before a warning is issued |
| `warning_response_days` | 7 | Days a user has to respond to a warning |
| `blacklist_duration_days` | 90 | Default days for blacklist expiry |

### Audit logs

Every important action (post created, warning issued, user deleted, etc.) is logged for audit.

```
GET /api/v1/admin/audit-logs                 # List logs (paginated)
GET /api/v1/admin/audit-logs/{logId}        # Show a single log entry
GET /api/v1/admin/audit-logs/actions        # Get list of all possible action types
GET /api/v1/admin/audit-logs/export/{format} # Export logs (e.g., CSV)
```

### IP whitelist

(For System Admin only) Manage which IP addresses are allowed to access the admin panel.

```
GET    /api/v1/admin/ip-whitelist                        # List all whitelisted IPs
GET    /api/v1/admin/ip-whitelist/{ipId}                 # Show a specific entry
GET    /api/v1/admin/ip-whitelist/check/{ip}             # Check if an IP is whitelisted
POST   /api/v1/admin/ip-whitelist                        # Add an IP to the whitelist
PUT    /api/v1/admin/ip-whitelist/{ipId}                 # Update an IP entry
DELETE /api/v1/admin/ip-whitelist/{ipId}                 # Remove an IP from the whitelist
POST   /api/v1/admin/ip-whitelist/{ipId}/activate        # Activate an IP entry
POST   /api/v1/admin/ip-whitelist/{ipId}/deactivate      # Deactivate an IP entry
```

### Group management

**View groups** (all admins):

```
GET /api/v1/admin/groups                    # List all groups
GET /api/v1/admin/groups/{groupId}          # Show a group
GET /api/v1/admin/groups/{groupId}/members  # List members
PUT /api/v1/admin/groups/{groupId}/members  # Update members
```

**Manage groups** (System Admin only):

```
POST   /api/v1/admin/groups                         # Create a group
PUT    /api/v1/admin/groups/{groupId}               # Update a group
DELETE /api/v1/admin/groups/{groupId}               # Delete a group (soft delete)
POST   /api/v1/admin/groups/{groupId}/admins        # Add a group admin
DELETE /api/v1/admin/groups/{groupId}/admins/{userId} # Remove a group admin
```

**Auto-promotion:** When the first user with a "Member" role joins a **student-type** group, they are automatically promoted to Group Admin for that group. This is so every student group has at least one admin to manage it.

---

## How the Desktop Client Should Work (End-to-End Flow)

Here's the typical flow a first-time user experiences through your desktop app:

### First Launch — No Account

1. **App opens** to a login screen with "Don't have an account? Register" link.
2. User fills in their name, email, password → taps **Register**.
3. Your app calls `POST /api/v1/register`.
4. Server returns a token and user data. **Save the token** to local storage / keychain.
5. Navigate to the **Forum** screen (list of topics in their group).

### Returning User

1. **App opens** → check if a saved token exists.
2. If yes, call `GET /api/v1/me` to verify the token is still valid.
   - If 200 → navigate to Forum.
   - If 401 → token expired/revoked. Clear saved token, show login screen.
3. If no saved token → show login screen.

### Navigation Structure

Once logged in, the app should have these main sections (visibility depends on role):

| Tab/Section | Member | Group Admin | System Admin | Lecturer |
|-------------|--------|-------------|--------------|----------|
| **Forum** (topics, posts, categories) | Yes | Yes | Yes | Yes |
| **Groups** (browse groups) | Yes (own group) | Yes (admin'd groups) | Yes (all) | Yes (own group) |
| **Profile** (edit name/email, change password) | Yes | Yes | Yes | Yes |
| **Quizzes** (take quiz) | Yes | Yes | Yes | Yes |
| **Notifications** | Yes | Yes | Yes | Yes |
| **Admin: Users** | No | Yes (scoped) | Yes (all) | No |
| **Admin: Warnings/Blacklist** | No | Yes (scoped) | Yes (all) | No |
| **Admin: Categories** | No | Yes | Yes | Yes |
| **Admin: Create Quizzes** | No | Yes | Yes | Yes |
| **Admin: Grades/Reports** | No | No | Yes | Yes |
| **Admin: System Config** | No | No | Yes | No |
| **Admin: Audit Logs** | No | Yes | Yes | No |
| **Admin: IP Whitelist** | No | No | Yes | No |
| **Admin: Groups** | No | Yes (scoped) | Yes (all) | No |

### Background Polling

Your desktop app should poll these endpoints in the background:

- `GET /api/v1/me` — keep the token alive and update user data. The server updates `last_active_at` when you hit this, which prevents automatic inactivity warnings.
- `GET /api/v1/quizzes/{quiz}/status` — when a quiz is about to start, poll every few seconds so the "Start" button appears as soon as the quiz goes live.
- `GET /api/v1/me/notifications` — check for new notifications periodically.

---

## Error Responses

All errors follow a consistent format:

```json
{
  "message": "Human-readable error message",
  "errors": {
    "field_name": ["Specific error details"]
  }
}
```

The `errors` object is only present for validation errors (422). Other errors just have `message`.

### HTTP Status Codes You'll See

| Code | Meaning | When it happens |
|------|---------|-----------------|
| **200** | OK | Request succeeded |
| **201** | Created | Resource was created (register, new topic, new post, etc.) |
| **400** | Bad Request | Invalid or expired token, wrong OTP |
| **401** | Unauthorized | Missing, invalid, or expired authentication token |
| **403** | Forbidden | Not allowed (wrong role, blacklisted, warned, group isolation, not the post author) |
| **404** | Not Found | Resource doesn't exist (topic, user, token, etc.) |
| **409** | Conflict | Duplicate (already exists, already submitted, already excluded) |
| **422** | Validation Error | Missing field, wrong format, password too weak, etc. |
| **429** | Too Many Requests | Rate limit exceeded (includes `Retry-After` header) |
| **500** | Server Error | Something broke on the server |

### Specific Error Responses Your App Should Handle

**Login — Blacklisted:**
```json
{
  "message": "Your account is blacklisted until Jul 15, 2026."
}
```
Your app should show this message and NOT proceed to the forum. The user cannot log in until the blacklist expires or an admin lifts it.

**Login — Warned (needs acknowledgement):**
```json
{
  "message": "Your account is warned. Please acknowledge the warning before continuing.",
  "requires_warning_acknowledgement": true,
  "user": { ... }
}
```
Your app should show the warning to the user and let them acknowledge it. Once acknowledged, they can log in normally.

**Registration — Missing Role/Group:**
```json
{
  "message": "Required role or group not found in database. Please contact administrator."
}
```
This happens if the "Member" role or "General" group haven't been seeded in the database. The server admin needs to run the database seeds.

---

## Rate Limits — How Fast You Can Send Requests

The server limits how many requests you can send to prevent abuse. Your desktop client should respect these limits and show appropriate messages when hit.

| Endpoint | Limit | Scope |
|----------|-------|-------|
| **All API endpoints** | 60 requests per minute | Per IP address |
| **Login** | 5 attempts per 30 seconds | Per email+IP AND per email (dual-key — prevents IP rotation bypass) |
| **Registration** | 3 requests per 60 seconds | Per IP address |
| **Forgot password** | 3 requests per 15 minutes | Per email address |
| **Reset password** | 5 OTP attempts per 10 minutes | Per email address |
| **Resend verification email** | 1 request per 60 seconds | Per authenticated user |
| **Create topic** | 3 per 60 seconds | Per regular user (admins/lecturers bypass) |
| **Create reply** | 5 per 60 seconds | Per regular user (admins/lecturers bypass) |

When you exceed a rate limit, the server responds with **429** and includes:
- `Retry-After` header (seconds until you can try again)
- `X-RateLimit-Limit` header (maximum requests allowed)
- `X-RateLimit-Remaining` header (how many you have left)

---

## Security Headers

Every API response includes these security headers:

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Cache-Control: no-store, no-cache, must-revalidate, max-age=0
Pragma: no-cache
Expires: 0
```

If you access the API over HTTPS, you'll also get:
```
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

These are set by the `ApiSecurityHeaders` middleware on every API route. Your desktop client doesn't need to do anything with them, but it's good to know they're there.

---

## CORS Configuration

CORS is configured to work with desktop clients running locally. Allowed origins include `localhost` on any port.

### Allowed Origins
- `http://localhost`
- `http://localhost:*` (any port)
- `http://127.0.0.1`
- `http://127.0.0.1:*` (any port)

### Allowed Methods
`GET`, `POST`, `PUT`, `DELETE`, `OPTIONS`

### Allowed Headers
`Content-Type`, `Authorization`, `X-Requested-With`, `Accept`, `Origin`

If your desktop app uses a custom protocol (e.g., `myapp://`) or a different origin, update `config/cors.php` on the server.

---

## Activity Monitoring — Automatic Inactivity Handling

The server runs a daily scheduled task that checks for inactive users:

**Command:** `php artisan monitor:activity`

**What it does:**
1. Finds users who haven't been active in X days (default: 30).
2. Issues a warning to them (warning #1).
3. Gives them Y days to respond (default: 7).
4. If they don't respond, issues warning #2, then #3, then auto-blacklists them.
5. The blacklist lasts Z days (default: 90).

These thresholds are configurable via the system config endpoints (see "System Configuration" above).

> **Note about `last_active_at`:** This timestamp is updated when the user calls `GET /api/v1/me` (or any authenticated endpoint that touches user data). Your desktop app should call `GET /api/v1/me` periodically to keep the user's "last active" timestamp current, preventing false inactivity warnings.

---

## Quick Reference — All Endpoints at a Glance

### Authentication & Profile

| Method | Endpoint | Auth | What it does |
|--------|----------|------|-------------|
| POST | `/register` | No | Create account, get token |
| POST | `/login` | No | Log in, get token |
| POST | `/password/forgot` | No | Send 6-digit OTP to email |
| POST | `/password/reset` | No | Reset password with OTP |
| POST | `/logout` | Yes | Revoke current token |
| POST | `/token/refresh` | Yes | Get new token, invalidate old one |
| GET | `/tokens` | Yes | List all your active tokens |
| DELETE | `/tokens/{id}` | Yes | Revoke a specific token |
| GET | `/me` | Yes | Get your user info |
| POST | `/profile` | Yes | Update name/email |
| POST | `/password/change` | Yes | Change your password |
| DELETE | `/account` | Yes | Delete your account permanently |
| POST | `/email/verify` | Yes | Verify email with token |
| POST | `/email/resend` | Yes | Resend verification email |

### Forum — Topics & Posts

| Method | Endpoint | Auth | What it does |
|--------|----------|------|-------------|
| GET | `/topics` | Yes | List topics in your group |
| GET | `/topics/type/{type}` | Yes | Filter by discussion/question |
| POST | `/topics` | Yes | Create a topic |
| GET | `/topics/{id}` | Yes | Topic detail with posts |
| PUT | `/topics/{id}` | Yes | Update topic (creator/admin) |
| DELETE | `/topics/{id}` | Yes | Archive topic (creator/admin) |
| GET | `/topics/{id}/posts` | Yes | List posts in a topic |
| POST | `/topics/{id}/posts` | Yes | Post a reply |
| PUT | `/posts/{id}` | Yes | Edit your post |
| DELETE | `/posts/{id}` | Yes | Delete your post |
| GET | `/topics/{id}/export/pdf` | Yes | Download topic as PDF |
| POST | `/topics/{id}/share` | Yes | Generate shareable link |
| POST | `/topics/{id}/toggle-answered` | Yes | Mark question as answered |
| POST | `/topics/{id}/toggle-pinned` | Yes | Pin/unpin topic |

### Forum — Post Visibility

| Method | Endpoint | Auth | What it does |
|--------|----------|------|-------------|
| GET | `/posts/{id}/visibility` | Yes | List excluded users |
| POST | `/posts/{id}/visibility/exclude` | Yes | Exclude a user from seeing your post |
| DELETE | `/posts/{id}/visibility/{userId}` | Yes | Remove an exclusion |

### Forum — Categories

| Method | Endpoint | Auth | What it does |
|--------|----------|------|-------------|
| GET | `/categories` | Yes | List categories in your group |
| GET | `/categories/{id}/topics` | Yes | Topics under a category |
| POST | `/admin/categories` | Admin | Create category |
| PUT | `/admin/categories/{id}` | Admin | Update category |
| DELETE | `/admin/categories/{id}` | Admin | Delete category |

### Groups

| Method | Endpoint | Auth | What it does |
|--------|----------|------|-------------|
| GET | `/groups` | Yes | List groups you can see |
| GET | `/groups/{id}` | Yes | Show group details |
| GET | `/groups/{id}/topics` | Yes | Topics in a group |
| GET | `/groups/{id}/members` | Yes | Members of a group |

### Notifications

| Method | Endpoint | Auth | What it does |
|--------|----------|------|-------------|
| GET | `/me/notifications` | Yes | List your notifications |
| POST | `/notifications/{id}/read` | Yes | Mark notification as read |

### Quizzes — Lecturer/Admin (CRUD)

| Method | Endpoint | Auth | What it does |
|--------|----------|------|-------------|
| GET | `/quizzes` | Admin | List all quizzes |
| POST | `/quizzes` | Admin | Create a quiz |
| GET | `/quizzes/{id}` | Admin | Show quiz with questions |
| PUT | `/quizzes/{id}` | Admin | Update quiz |
| DELETE | `/quizzes/{id}` | Admin | Delete quiz |
| POST | `/quizzes/{id}/publish` | Admin | Publish quiz |
| GET | `/quizzes/{id}/report` | Admin | Class performance report |
| GET | `/quizzes/{id}/questions` | Admin | List questions |
| POST | `/quizzes/{id}/questions` | Admin | Add question |
| PUT | `/quizzes/{id}/questions/{q}` | Admin | Update question |
| DELETE | `/quizzes/{id}/questions/{q}` | Admin | Delete question |
| PUT | `/quizzes/{id}/questions/reorder` | Admin | Reorder questions |
| GET | `/questions/{q}/answers` | Admin | List answer options |
| POST | `/questions/{q}/answers` | Admin | Add answer |
| PUT | `/answers/{a}` | Admin | Update answer |
| DELETE | `/answers/{a}` | Admin | Delete answer |

### Quizzes — Student (Take Quiz)

| Method | Endpoint | Auth | What it does |
|--------|----------|------|-------------|
| GET | `/quizzes/{id}/announcement` | Yes | Show pre-quiz info |
| GET | `/quizzes/{id}/status` | Yes | Poll for live status |
| POST | `/quizzes/{id}/attempt` | Yes | Start attempt |
| GET | `/quizzes/{id}/attempt` | Yes | Resume attempt |
| POST | `/quizzes/{id}/answer` | Yes | Save single answer |
| POST | `/quizzes/{id}/answers/batch` | Yes | Save multiple answers |
| POST | `/quizzes/{id}/submit` | Yes | Submit manually |
| POST | `/quizzes/{id}/auto-submit` | Yes | Auto-submit on timer expiry |

### Quizzes — Results & History

| Method | Endpoint | Auth | What it does |
|--------|----------|------|-------------|
| GET | `/quizzes/{id}/result` | Yes | View your result |
| GET | `/lecturer/quizzes/{id}/grades` | Admin | All grades for a quiz |
| GET | `/lecturer/grades/{id}` | Admin | Single grade breakdown |
| GET | `/lecturer/quizzes/{id}/grades/export` | Admin | Export grades as CSV |
| GET | `/quizzes/upcoming` | Yes | Upcoming quizzes |
| GET | `/quizzes/live` | Yes | Currently active quizzes |
| GET | `/me/quiz-history` | Yes | Your past attempts |
| GET | `/me/quiz-notifications` | Yes | Quiz notifications |

### Admin — User & Content Management

| Method | Endpoint | Auth | What it does |
|--------|----------|------|-------------|
| GET | `/admin/users` | Admin | List users |
| GET | `/admin/users/{id}` | Admin | Show user |
| POST | `/admin/users` | Admin* | Create user |
| PUT | `/admin/users/{id}` | Admin | Update user |
| DELETE | `/admin/users/{id}` | Admin* | Delete user |
| POST | `/admin/users/{id}/change-role` | Admin* | Change user role |
| POST | `/admin/users/{id}/reset-password` | Admin | Reset password |
| GET | `/admin/warnings` | Admin | List warnings |
| GET | `/admin/warnings/{id}` | Admin | Show warning |
| POST | `/admin/users/{id}/warnings` | Admin | Issue warning |
| POST | `/admin/warnings/{id}/resolve` | Admin | Resolve warning |
| GET | `/admin/blacklist-records` | Admin | List blacklist records |
| POST | `/admin/users/{id}/blacklist` | Admin | Blacklist user |
| POST | `/admin/blacklist-records/{id}/lift` | Admin | Lift blacklist |
| GET | `/admin/moderation` | Admin | List reported content |
| POST | `/admin/moderation/{post}/remove` | Admin | Remove post |
| POST | `/admin/moderation/{post}/ignore` | Admin | Ignore report |

\* System Admin only (enforced in controller, not middleware).

### Admin — Bulk Operations

| Method | Endpoint | Auth | What it does |
|--------|----------|------|-------------|
| POST | `/admin/bulk/change-roles` | Admin | Change roles for multiple users |
| POST | `/admin/bulk/change-status` | Admin | Update account status |
| POST | `/admin/bulk/assign-group` | Admin | Move users to a group |
| POST | `/admin/bulk/blacklist` | Admin | Blacklist multiple users |
| POST | `/admin/bulk/lift-blacklist` | Admin | Lift blacklists |
| POST | `/admin/bulk/warn` | Admin | Issue warnings |
| POST | `/admin/bulk/assign-group-admins` | Admin | Assign group admins |

### Admin — Search

| Method | Endpoint | Auth | What it does |
|--------|----------|------|-------------|
| POST | `/admin/search/users` | Admin | Search users |
| POST | `/admin/search/groups` | Admin | Search groups |
| POST | `/admin/search/audit-logs` | Admin | Search audit logs |
| POST | `/admin/search/warnings` | Admin | Search warnings |
| GET | `/admin/search/options/{model}` | Admin | Filter options for a model |
| GET | `/admin/search/suggestions/{type}` | Admin | Search suggestions |

### Admin — Groups

| Method | Endpoint | Auth | What it does |
|--------|----------|------|-------------|
| GET | `/admin/groups` | Admin | List groups |
| GET | `/admin/groups/{id}` | Admin | Show group |
| POST | `/admin/groups` | Admin* | Create group |
| PUT | `/admin/groups/{id}` | Admin | Update group |
| DELETE | `/admin/groups/{id}` | Admin* | Delete group |
| GET | `/admin/groups/{id}/members` | Admin | List members |
| PUT | `/admin/groups/{id}/members` | Admin | Update members |
| POST | `/admin/groups/{id}/admins` | Admin* | Add group admin |
| DELETE | `/admin/groups/{id}/admins/{userId}` | Admin* | Remove group admin |

\* System Admin only.

### Admin — System

| Method | Endpoint | Auth | What it does |
|--------|----------|------|-------------|
| GET | `/admin/system-config` | Admin* | List system config |
| GET | `/admin/system-config/{key}` | Admin* | Get config value |
| PUT | `/admin/system-config` | Admin* | Update config |
| GET | `/admin/audit-logs` | Admin | List audit logs |
| GET | `/admin/audit-logs/{id}` | Admin | Show log entry |
| GET | `/admin/audit-logs/actions` | Admin | List action types |
| GET | `/admin/audit-logs/export/{format}` | Admin | Export logs |
| GET | `/admin/ip-whitelist` | Admin* | List whitelisted IPs |
| GET | `/admin/ip-whitelist/{id}` | Admin* | Show IP entry |
| GET | `/admin/ip-whitelist/check/{ip}` | Admin* | Check IP |
| POST | `/admin/ip-whitelist` | Admin* | Add IP |
| PUT | `/admin/ip-whitelist/{id}` | Admin* | Update IP |
| DELETE | `/admin/ip-whitelist/{id}` | Admin* | Remove IP |
| POST | `/admin/ip-whitelist/{id}/activate` | Admin* | Activate IP |
| POST | `/admin/ip-whitelist/{id}/deactivate` | Admin* | Deactivate IP |

\* System Admin only.
