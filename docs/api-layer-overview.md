# API Layer — Desktop App Integration

> **Generated:** July 2026
> **Purpose:** Explains the entire API (desktop app) layer — what it is, why it exists, what each part does, and how it connects to the existing web application.

---

## Table of Contents

1. [What Is the API Layer?](#what-is-the-api-layer)
2. [Why Two Versions of Everything?](#why-two-versions-of-everything)
3. [The Big Picture: How It All Connects](#the-big-picture-how-it-all-connects)
4. [P1 — Quiz CRUD API + Questions & Answers API](#p1--quiz-crud-api--questions--answers-api)
5. [P2 — Student Quiz Execution API](#p2--student-quiz-execution-api)
6. [P3 — Results, Reports & Notifications API](#p3--results-reports--notifications-api)
7. [P4 — Admin API: User CRUD + Moderation](#p4--admin-api-user-crud--moderation)
8. [P5 — Admin API: Dashboard + Group Statistics](#p5--admin-api-dashboard--group-statistics)
9. [All API Controllers at a Glance](#all-api-controllers-at-a-glance)
10. [Common Patterns Across All API Controllers](#common-patterns-across-all-api-controllers)

---

## What Is the API Layer?

The API layer is a set of **JSON endpoints** designed specifically for a **desktop client application**. It lives alongside the existing **web (Blade) layer** and uses the **same database, same models, and same business logic** — but speaks a different language.

| Layer | Used By | Returns | Route File |
|---|---|---|---|
| **Web (Blade)** | Browser-based users | HTML pages, redirects | `routes/web.php` |
| **API** | Desktop app (Electron/Tauri/etc.) | JSON data | `routes/api.php` |

The desktop app never renders Blade HTML templates. Instead:
1. The desktop app calls an API endpoint (e.g. `GET /api/v1/users`)
2. The server returns JSON (raw data, no styling)
3. The desktop app takes that JSON and builds its own interface

This is confirmed directly in the code. For example, `AuthController.php` says:

```php
/**
 * API Registration endpoint for desktop client.
 */
```

And the token is explicitly named for the desktop:

```php
$token = $user->createToken('desktop-client')->plainTextToken;
```

There's also a 3,600+ line API documentation file at `docs/API_DOCUMENTATION.md` that describes every endpoint in detail.

---

## Why Two Versions of Everything?

The web interface and the desktop app look and behave differently, even though they do the same things:

### Example: Creating a user

**Web (Blade) flow:**
```
Browser → POST /admin/users → Server validates → Server creates user
    → Server redirects browser to /admin/users
    → Browser shows a full HTML page with a green "User created" banner
```

**Desktop app flow:**
```
Desktop app → POST /api/v1/admin/users → Server validates → Server creates user
    → Server returns: {"success": true, "message": "User created.", "data": {...}}
    → Desktop app reads the JSON and shows its own success popup
```

The logic is the same (validate data, create user, log to audit). But the **response** is completely different:
- The web layer returns **HTML pages and redirects** intended for a browser
- The API layer returns **JSON data** intended for a desktop app to process

This is why they have separate controllers and separate routes. The `/api/` prefix in the URL is the distinguisher:

| Action | Web URL | API URL |
|---|---|---|
| List quizzes | `GET /quizzes` | `GET /api/v1/quizzes` |
| Create quiz | `POST /quizzes` | `POST /api/v1/quizzes` |
| List users | `GET /admin/users` | `GET /api/v1/admin/users` |
| Create user | (via Blade form) | `POST /api/v1/admin/users` |
| Moderate posts | `GET /admin/moderation` | `GET /api/v1/admin/moderation` |

**They never conflict** because the `/api/` prefix routes requests to entirely different files (`routes/api.php` vs `routes/web.php`).

### What they share

Even though the controllers are separate, these are **shared** between web and API:

- **Database tables** — both read and write to the same `quizzes`, `users`, `grades`, `notifications` tables
- **Eloquent models** — both use `App\Models\Quiz`, `App\Models\User`, etc.
- **Events** — when the API publishes a quiz, it fires the same `QuizPublished` event that Person 5 built for the web layer
- **Listeners** — the same `SendQuizAnnouncement` listener creates notifications for both web and API users
- **Scheduled commands** — `quiz:activate` and `quiz:send-reminders` run regardless of which interface triggered the quiz creation

---

## The Big Picture: How It All Connects

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                         THE FULL APPLICATION                                │
│                                                                             │
│  ┌─────────────────────────────┐    ┌──────────────────────────────────┐    │
│  │     WEB INTERFACE            │    │       DESKTOP APP               │    │
│  │     (Browser, Blade)         │    │       (Electron/Tauri/etc.)     │    │
│  │                              │    │                                  │    │
│  │  Visits URLs like:           │    │  Calls API endpoints like:       │    │
│  │  /quizzes                    │    │  /api/v1/quizzes                │    │
│  │  /admin/users                │    │  /api/v1/admin/users            │    │
│  │  /quizzes/5/attempt          │    │  /api/v1/quizzes/5/attempt      │    │
│  └──────────────┬──────────────┘    └──────────────┬───────────────────┘    │
│                 │                                  │                         │
│                 ▼                                  ▼                         │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                      ROUTING LAYER                                   │   │
│  │                                                                      │   │
│  │  routes/web.php  ─── routes to Blade controllers                     │   │
│  │                     e.g. QuizController@index → returns HTML page    │   │
│  │                                                                      │   │
│  │  routes/api.php   ─── routes to API controllers                     │   │
│  │                     e.g. Api\QuizController@index → returns JSON    │   │
│  │                                                                      │   │
│  │  The /api/ prefix in the URL is what tells Laravel which to use.     │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│                 │                                  │                         │
│                 └────────────────┬─────────────────┘                         │
│                                  ▼                                           │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │                     SHARED LAYER                                     │   │
│  │                                                                      │   │
│  │  ┌────────────────┐  ┌────────────────┐  ┌────────────────────┐      │   │
│  │  │  Models        │  │  Database      │  │  Events &         │      │   │
│  │  │  Quiz          │  │  quizzes       │  │  Listeners        │      │   │
│  │  │  User          │  │  users         │  │  QuizPublished    │      │   │
│  │  │  Grade         │  │  grades        │  │  QuizWentLive     │      │   │
│  │  │  Notification  │  │  notifications │  │  SendAnnouncement │      │   │
│  │  └────────────────┘  └────────────────┘  └────────────────────┘      │   │
│  │                                                                      │   │
│  │  Both web and API read/write to the same tables, use the same        │   │
│  │  model classes, and trigger the same events.                         │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────────────────────────────┘
```

---

## P1 — Quiz CRUD API + Questions & Answers API

**Role:** Foundation of the quiz API. Every other quiz API person depends on these endpoints existing.

### Controllers Created

#### `app/Http/Controllers/Api/QuizController.php`

7 methods for full quiz management via the API:

| Method | Route | What It Does |
|---|---|---|
| `index()` | `GET /api/v1/quizzes` | Lists all quizzes with question count, configuration, and lecturer info. If the user is a Group Admin, only quizzes in their administered groups are returned. Paginated (20 per page). |
| `store()` | `POST /api/v1/quizzes` | Creates a new quiz. Takes title, description, target category, scheduled date, start time, and duration. Also creates a default `QuizConfiguration` record. Lecturer ID is set to the currently authenticated user. |
| `show()` | `GET /api/v1/quizzes/{quiz}` | Returns a single quiz with all its questions, answers, configuration, and lecturer details fully loaded. |
| `update()` | `PUT /api/v1/quizzes/{quiz}` | Updates quiz details and configuration. Cannot update a quiz that has already been published (returns 422 error). |
| `destroy()` | `DELETE /api/v1/quizzes/{quiz}` | Deletes a quiz. Cannot delete a published quiz (returns 422). Cascade deletes removes all questions, answers, attempts, and grades. |
| `publish()` | `POST /api/v1/quizzes/{quiz}/publish` | Publishes a quiz so students can see it. Guards: must have at least 1 question, datetime must be in the future, must not already be published. Sets `published_at` and dispatches `QuizPublished` event — the same event that Person 5 built for the web layer. |
| `report()` | `GET /api/v1/quizzes/{quiz}/report` | Returns class performance data: average, highest, and lowest percentage scores, attempt count, and a student-by-student breakdown. |

#### `app/Http/Controllers/Api/QuestionController.php`

5 methods for managing questions within quizzes:

| Method | Route | What It Does |
|---|---|---|
| `index()` | `GET /api/v1/quizzes/{quiz}/questions` | Lists all questions for a quiz, ordered by `question_order`, with their answer options. |
| `store()` | `POST /api/v1/quizzes/{quiz}/questions` | Creates a question and its answer options in one request. Accepts `question_text`, `question_type`, `marks`, and an `answers` array with each answer's text and correctness flag. Auto-calculates the next order number. |
| `update()` | `PUT /api/v1/quizzes/{quiz}/questions/{question}` | Updates the question text, type, and marks. |
| `destroy()` | `DELETE /api/v1/quizzes/{quiz}/questions/{question}` | Deletes a question and its answer options (cascade). |
| `reorder()` | `PUT /api/v1/quizzes/{quiz}/questions/reorder` | Changes the display order of questions. Accepts an array of `{id, order}` pairs and updates them in a database transaction. |

#### `app/Http/Controllers/Api/AnswerController.php`

4 methods for managing individual answer options:

| Method | Route | What It Does |
|---|---|---|
| `index()` | `GET /api/v1/questions/{question}/answers` | Lists all answer options for a question. |
| `store()` | `POST /api/v1/questions/{question}/answers` | Creates a new answer option with text and correctness flag. |
| `update()` | `PUT /api/v1/answers/{answer}` | Updates an answer option's text or correctness. |
| `destroy()` | `DELETE /api/v1/answers/{answer}` | Deletes an answer option. |

### Routes (registered in `routes/api.php`)

All inside the `admin` middleware group (requires admin privileges + Sanctum auth):

```
GET    /api/v1/quizzes                     → QuizController@index
POST   /api/v1/quizzes                     → QuizController@store
GET    /api/v1/quizzes/{quiz}              → QuizController@show
PUT    /api/v1/quizzes/{quiz}              → QuizController@update
DELETE /api/v1/quizzes/{quiz}              → QuizController@destroy
POST   /api/v1/quizzes/{quiz}/publish      → QuizController@publish
GET    /api/v1/quizzes/{quiz}/report       → QuizController@report

GET    /api/v1/quizzes/{quiz}/questions         → QuestionController@index
POST   /api/v1/quizzes/{quiz}/questions         → QuestionController@store
PUT    /api/v1/quizzes/{quiz}/questions/{question} → QuestionController@update
DELETE /api/v1/quizzes/{quiz}/questions/{question} → QuestionController@destroy
PUT    /api/v1/quizzes/{quiz}/questions/reorder → QuestionController@reorder

GET    /api/v1/questions/{question}/answers → AnswerController@index
POST   /api/v1/questions/{question}/answers → AnswerController@store
PUT    /api/v1/answers/{answer}            → AnswerController@update
DELETE /api/v1/answers/{answer}            → AnswerController@destroy
```

### How It Connects to the Existing Code

- Uses the same `Quiz`, `Question`, `Answer`, `QuizConfiguration`, and `Grade` models that Person 1 created for the web layer
- Fires the same `QuizPublished` event that Person 5 built — so when a quiz is published via the API, the `SendQuizAnnouncement` listener runs and creates notifications, just like in the web layer
- Uses the same validation rules (date must be today or future, duration 1–480 min, etc.)

---

## P2 — Student Quiz Execution API

**Role:** The student's real-time quiz experience via the desktop app — starting a quiz, answering questions, submitting, and auto-submit on timeout. This is the most time-sensitive code (timers must be accurate, answers must save instantly).

### Controller Created

#### `app/Http/Controllers/Api/StudentQuizController.php`

8 methods:

| Method | Route | What It Does |
|---|---|---|
| `announcement()` | `GET /api/v1/quizzes/{quiz}/announcement` | Returns quiz metadata for the pre-quiz landing page: title, description, duration, question count, and timing info (scheduled time, seconds until start, whether it has started). **Does NOT return questions or correct answers** — students only see this before they start. |
| `start()` | `POST /api/v1/quizzes/{quiz}/attempt` | Creates a new attempt and returns the first set of questions. This is the "Start Quiz" action. Guards: quiz must be active, student must not already have an attempt (409 if they try again). Strips `is_correct` from answer options so the desktop app cannot expose the correct answers. Returns `time_remaining_seconds` for the timer. |
| `showAttempt()` | `GET /api/v1/quizzes/{quiz}/attempt` | Resumes an existing attempt (e.g. after a network disconnect). Returns the same data shape as `start()` but doesn't create a new attempt. |
| `saveAnswer()` | `POST /api/v1/quizzes/{quiz}/answer` | Saves a single answer immediately when the student selects an option. Cannot save after submission. |
| `saveAnswersBatch()` | `POST /api/v1/quizzes/{quiz}/answers/batch` | Saves multiple answers at once in a database transaction. Useful when the desktop app wants to sync all answers at once (e.g. on submit or periodic sync). |
| `submit()` | `POST /api/v1/quizzes/{quiz}/submit` | Manual submission by the student. Sets `submit_time`, marks `is_auto_submit = false`, runs the grading algorithm, creates a Grade record. |
| `autoSubmit()` | `POST /api/v1/quizzes/{quiz}/auto-submit` | Auto-submission when the timer expires. Same as `submit()` but marks `is_auto_submit = true`. |
| `status()` | `GET /api/v1/quizzes/{quiz}/status` | Returns real-time quiz status: whether the quiz has started, whether the student has submitted, time remaining in seconds, the submitted timestamp (if submitted). Used by the desktop app's JavaScript timer to stay in sync with the server. |

### Security Measures Specific to the API

**The most critical security rule is: never expose correct answers during an active quiz.**

In `start()` and `showAttempt()`, each answer option is stripped of `is_correct` before being sent to the desktop app:

```php
$questions = $quiz->questions()->with('answers')->orderBy('question_order')->get();

$questions->each(function ($question) {
    $question->answers->each(function ($answer) {
        unset($answer->is_correct);  // ← HIDDEN from the desktop client
    });
});
```

If this weren't done, a student could inspect the API response and see which answers are correct without actually taking the quiz.

### Routes

All inside the `auth:sanctum` middleware group (any authenticated user, not just admin):

```
GET    /api/v1/quizzes/{quiz}/announcement   → announcement()
POST   /api/v1/quizzes/{quiz}/attempt         → start()
GET    /api/v1/quizzes/{quiz}/attempt         → showAttempt()
POST   /api/v1/quizzes/{quiz}/answer          → saveAnswer()
POST   /api/v1/quizzes/{quiz}/answers/batch   → saveAnswersBatch()
POST   /api/v1/quizzes/{quiz}/submit          → submit()
POST   /api/v1/quizzes/{quiz}/auto-submit     → autoSubmit()
GET    /api/v1/quizzes/{quiz}/status          → status()
```

### How It Connects to the Existing Code

- Uses the same `StudentAttempt` and `StudentAnswer` models from Person 1
- Contains a copy of the grading logic (`gradeQuiz()` + `calculateParticipationMark()`) that matches the Blade version in `StudentQuizController` — same math, same thresholds, same Grade record creation
- Reads `QuizConfiguration` settings (late join, lock screen, etc.) that the lecturer set via Person 2's web interface

---

## P3 — Results, Reports & Notifications API

**Role:** The feedback loop — students see their results, lecturers export grades, and the system surfaces upcoming/live quizzes and notifications.

### Controller 1: `app/Http/Controllers/Api/GradeController.php`

4 methods:

| Method | Route | What It Does |
|---|---|---|
| `myResult()` | `GET /api/v1/quizzes/{quiz}/result` | Returns the authenticated student's grade for a quiz. Includes per-question breakdown (question text, student's answer, correct answer only if `show_correct_answers` is enabled, marks earned/possible). Respects the `show_results_after_close` config setting. |
| `index()` | `GET /api/v1/lecturer/quizzes/{quiz}/grades` | Returns all grades for a quiz (lecturer-facing). Includes student names and emails. Admin middleware. |
| `show()` | `GET /api/v1/lecturer/grades/{grade}` | Returns a single grade with full breakdown (attempt details, question-by-question answers). Admin middleware. |
| `exportCsv()` | `GET /api/v1/lecturer/quizzes/{quiz}/grades/export` | Downloads all grades as a CSV file with columns: Student Name, Email, Score, Max, Percentage, Participation, Final Grade. Admin middleware. |

### Controller 2: `app/Http/Controllers/Api/QuizNotificationController.php`

5 methods:

| Method | Route | What It Does |
|---|---|---|
| `upcoming()` | `GET /api/v1/quizzes/upcoming` | Returns the next 10 published, not-yet-active quizzes scheduled from today onwards. Any authenticated user can see these. |
| `live()` | `GET /api/v1/quizzes/live` | Returns all currently active quizzes. Any authenticated user can see these. |
| `history()` | `GET /api/v1/me/quiz-history` | Returns the authenticated user's past quiz attempts with quiz title and grade. Paginated. |
| `quizNotifications()` | `GET /api/v1/me/quiz-notifications` | Returns quiz-related notifications (announcements, reminders, live alerts) for the authenticated user. Paginated. |
| `markRead()` | `POST /api/v1/notifications/{id}/read` | Marks a single notification as read by setting `read_at` to the current timestamp. The notification must belong to the authenticated user. |

**Bug fixed:** The original `quizNotifications()` query used `LIKE 'quiz.%'` which didn't match the actual stored notification types (`quiz_announcement`, `quiz_reminder`, `quiz_live`). This was corrected to use `WHERE IN (... )` — matching exactly what Person 5's listeners store and what the Blade view uses.

### Routes

```
GET  /api/v1/quizzes/{quiz}/result                     → GradeController@myResult
GET  /api/v1/lecturer/quizzes/{quiz}/grades              → GradeController@index
GET  /api/v1/lecturer/quizzes/{quiz}/grades/export       → GradeController@exportCsv
GET  /api/v1/lecturer/grades/{grade}                     → GradeController@show

GET  /api/v1/quizzes/upcoming                            → QuizNotificationController@upcoming
GET  /api/v1/quizzes/live                                → QuizNotificationController@live
GET  /api/v1/me/quiz-history                             → QuizNotificationController@history
GET  /api/v1/me/quiz-notifications                       → QuizNotificationController@quizNotifications
POST /api/v1/notifications/{id}/read                     → QuizNotificationController@markRead
```

### How It Connects to the Existing Code

- Reads Grade records created by the grading logic in `StudentQuizController` (both web and API versions create the same `Grade` model)
- Reads Notification records created by Person 5's listeners (`SendQuizAnnouncement`, `SendQuizReminders`, `NotifyQuizLive`)
- Respects quiz configuration settings (`show_results_after_close`, `show_correct_answers`) that the lecturer set via Person 2's web interface

---

## P4 — Admin API: User CRUD + Moderation

**Role:** Complete admin functionality for managing users and moderating forum content via the desktop app.

### Controller 1: `app/Http/Controllers/Api/Admin/AdminUserController.php` (modified)

4 methods added to the existing controller:

| Method | Route | What It Does | Who Can Use It |
|---|---|---|---|
| `store()` | `POST /api/v1/admin/users` | Creates a new user with full_name, email, password, role_name, and optional group_id. Looks up the role by name. Sets account_status to 'active'. Logs to audit. | System Admin only |
| `update()` | `PUT /api/v1/admin/users/{userId}` | Updates user's name, email, group_id, or role_id. Group Admins can only edit users in their administered groups and **cannot change roles**. System Admins can change everything. Logs which fields were updated. | System Admin (all fields), Group Admin (limited) |
| `destroy()` | `DELETE /api/v1/admin/users/{userId}` | Permanently deletes a user account. Guards: cannot delete yourself (422 error). Logs to audit. | System Admin only |
| `resetPassword()` | `POST /api/v1/admin/users/{userId}/reset-password` | Sends a password reset email to the user using Laravel's built-in password broker. Logs to audit. | System Admin only |

**What the controller already had before P4 (unchanged):**

| Method | Route | What It Does |
|---|---|---|
| `index()` | `GET /api/v1/admin/users` | Lists users with search, filter, and pagination. Group-scoped. |
| `show()` | `GET /api/v1/admin/users/{userId}` | Gets a single user with role, group, warnings, and blacklist records. |
| `changeRole()` | `POST /api/v1/admin/users/{userId}/change-role` | Changes a user's role. System Admin only. Guards against removing the last System Admin. |
| `liftBlacklist()` | `POST /api/v1/admin/users/{userId}/lift-blacklist` | Removes a user from the blacklist and reactivates their account. |
| `warn()` | `POST /api/v1/admin/users/{userId}/warn` | Issues a warning to a user with a reason and response deadline. |

### Controller 2: `app/Http/Controllers/Api/Admin/ModerationController.php` (new)

3 methods:

| Method | Route | What It Does |
|---|---|---|
| `index()` | `GET /api/v1/admin/moderation` | Lists all reported posts with reporter info, topic title, and post creator. Group-scoped: Group Admins only see reports from their groups. |
| `removePost()` | `POST /api/v1/admin/moderation/{post}/remove` | Marks a post as removed (hidden from public view). Clears the reported flag. Logs to both `ModerationLog` and `AuditLog`. The admin can provide a reason. |
| `ignoreReport()` | `POST /api/v1/admin/moderation/{post}/ignore` | Dismisses a report without removing the post. Clears the reported flag so it disappears from the moderation queue. Logs to audit. |

### Routes

All inside the existing `Route::prefix("admin")->middleware("admin")` group:

```
POST   /api/v1/admin/users                         → AdminUserController@store
PUT    /api/v1/admin/users/{userId}                → AdminUserController@update
DELETE /api/v1/admin/users/{userId}                → AdminUserController@destroy
POST   /api/v1/admin/users/{userId}/reset-password → AdminUserController@resetPassword

GET    /api/v1/admin/moderation                     → ModerationController@index
POST   /api/v1/admin/moderation/{post}/remove       → ModerationController@removePost
POST   /api/v1/admin/moderation/{post}/ignore       → ModerationController@ignoreReport
```

### How It Connects to the Existing Code

- User CRUD reads from and writes to the same `users` table used by the web interface
- Uses the same `Role` model to look up roles by name
- The `ModerationController` mirrors the Blade `ModerationController` at `app/Http/Controllers/Admin/ModerationController.php` — same logic (group isolation, marking posts as removed), but returns JSON instead of HTML
- Logs to the same `ModerationLog` and `AuditLog` tables that the web interface uses
- Uses the existing `AuditLogService` that was already set up in the admin API controllers

---

## P5 — Admin API: Dashboard + Group Statistics

**Role:** High-level monitoring — the dashboard gives admins a pulse on the platform, and group statistics helps system admins understand how each group is performing.

### Controller 1: `app/Http/Controllers/Api/Admin/DashboardController.php` (not yet built)

**Planned endpoint:**

| Method | Route | What It Will Do |
|---|---|---|
| `index()` | `GET /api/v1/admin/dashboard` | Returns aggregate platform statistics. For System Admin: total users, active users (last 30 days), total groups, total topics, total posts, reported posts count, quizzes scheduled for today, recent registrations (last 7 days), and recent topics. For Group Admin: same stats scoped to their administered groups. |

### Controller 2: `app/Http/Controllers/Api/Admin/GroupStatisticsController.php` (not yet built)

**Planned endpoints:**

| Method | Route | What It Will Do |
|---|---|---|
| `index()` | `GET /api/v1/admin/group-statistics` | Returns per-group statistics: member count, topic count, post count, and active members in the last 30 days. System Admin only. |
| `show()` | `GET /api/v1/admin/group-statistics/{group}` | Returns detailed data for a single group: all members with their last active date, and the 20 most recent topics with post counts. System Admin only. |

### How It Connects to the Existing Code

- Reads from the same `users`, `topics`, `posts`, `groups`, `quizzes` tables
- The dashboard stats mirror what the Blade admin dashboard (`resources/views/admin/dashboard.blade.php`) already shows to web admins
- The group statistics mirror the Blade `GroupStatisticsController` that already exists in the web layer

---

## All API Controllers at a Glance

| # | Controller | File | Methods | Status |
|---|---|---|---|---|
| P1 | `Api\QuizController` | NEW | index, store, show, update, destroy, publish, report | Built |
| P1 | `Api\QuestionController` | NEW | index, store, update, destroy, reorder | Built |
| P1 | `Api\AnswerController` | NEW | index, store, update, destroy | Built |
| P2 | `Api\StudentQuizController` | NEW | announcement, start, showAttempt, saveAnswer, saveAnswersBatch, submit, autoSubmit, status | Built |
| P3 | `Api\GradeController` | NEW | myResult, index, show, exportCsv | Built |
| P3 | `Api\QuizNotificationController` | NEW | upcoming, live, history, quizNotifications, markRead | Built |
| P4 | `Api\Admin\AdminUserController` | **EDITED** (+4) | store, update, destroy, resetPassword (added to existing 5 methods) | Built |
| P4 | `Api\Admin\ModerationController` | NEW | index, removePost, ignoreReport | Built |
| P5 | `Api\Admin\DashboardController` | Not yet built | index | Pending |
| P5 | `Api\Admin\GroupStatisticsController` | Not yet built | index, show | Pending |

**Controllers that existed before the API work** (forum, auth, groups, etc.):

| Controller | Purpose |
|---|---|
| `Api\AuthController` | Registration, login, logout, token management |
| `Api\UserController` | Get current user (`/me`) |
| `Api\ProfileController` | Update profile |
| `Api\PasswordController` | Forgot/reset/change password |
| `Api\EmailVerificationController` | Email verification |
| `Api\TopicController` | Forum topic CRUD, export, share |
| `Api\PostController` | Forum post CRUD |
| `Api\PostVisibilityController` | Post visibility exclusions |
| `Api\CategoryController` | Category listing (user + admin) |
| `Api\GroupBrowseController` | Group browsing |
| `Api\NotificationController` | User notification listing |
| `Api\Admin\GroupController` | Group management (admin) |
| `Api\Admin\SystemConfigController` | System configuration |
| `Api\Admin\AuditLogController` | Audit log viewing/export |
| `Api\Admin\IpWhitelistController` | IP whitelist management |
| `Api\Admin\WarningController` | Warning management |
| `Api\Admin\BlacklistController` | Blacklist management |
| `Api\Admin\BulkOperationController` | Bulk user operations |
| `Api\Admin\SearchController` | Advanced search |

---

## Common Patterns Across All API Controllers

All API controllers follow the same conventions:

### JSON Response Envelope

Every endpoint returns a consistent JSON structure:

```json
{
    "success": true,
    "data": { ... },
    "message": "Optional success/error message"
}
```

Error responses use the same envelope with `"success": false` and appropriate HTTP status codes:

| Situation | Status Code |
|---|---|
| Success | 200 |
| Created | 201 |
| Unauthorized (not logged in) | 401 |
| Forbidden (wrong role) | 403 |
| Not found | 404 |
| Validation error | 422 |
| Business rule violation (e.g. can't edit published quiz) | 422 |
| Cannot delete yourself | 422 |
| Server error | 500 |

### Pagination

Index/list endpoints use a consistent pagination format:

```json
{
    "success": true,
    "data": { ... items ... },
    "pagination": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 20,
        "total": 96
    }
}
```

### Authentication

- Most endpoints require `auth:sanctum` middleware (valid API token)
- Admin endpoints additionally require `admin` middleware (user must have an admin role)
- Some public endpoints (register, login, forgot password) require no authentication

### Authorization

Role-based checks happen inside the controller methods, not just at the route level:

- `isSystemAdmin()` — full access to everything
- `isGroupAdmin()` — access scoped to administered groups
- `isAdmin()` — access to admin areas but may be further scoped
- Regular users can only access their own data

---

*End of document.*
