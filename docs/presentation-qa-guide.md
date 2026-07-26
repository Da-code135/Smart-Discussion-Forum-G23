# Smart Discussion Forum — Presentation Q&A Study Guide

> A Smart Discussion Forum built with Laravel. Group-isolated, multi-tenant discussion platform with quizzes, messaging, admin panel, and ML-powered topic classification.

---

## SECTION 1: INTERFACE (5 marks)

### Q1: What is Laravel?
**A:** Laravel is a free, open-source PHP web framework created by Taylor Otwell. It follows the **Model-View-Controller (MVC)** architectural pattern and provides elegant syntax, built-in tools for routing, authentication, database management (ORM), caching, and more. This project uses **Laravel 13**.

### Q2: What is MVC and how does this project use it?
**A:** MVC stands for **Model-View-Controller**:
- **Model**: Represents data and database logic (e.g. `User.php`, `Topic.php`, `Post.php`) — each database table has a corresponding Eloquent Model class.
- **View**: The HTML templates the user sees (`.blade.php` files in `resources/views/`). This project has views in `auth/`, `forum/`, `quizzes/`, `admin/`, `notifications/`, `conversations/`, and `profile/`.
- **Controller**: Handles requests, processes data, returns views (e.g. `ForumController.php`, `LoginController.php`, `QuizController.php`).

**Flow**: User makes a request -> Route calls Controller -> Controller talks to Model (database) -> Controller returns a View with data.

### Q3: What are Blade templates?
**A:** Blade is Laravel's powerful templating engine. Files end in `.blade.php`. Features used in this project:
- **Layouts**: `@extends('layouts.app')` — reusable page shells.
- **Sections**: `@section('content') ... @endsection` — inject content into layouts.
- **Components**: `<x-share-dropdown :topic="$topic" />` — reusable UI pieces.
- **PHP directly**: `@php $user = Auth::user(); @endphp` — inline PHP in templates.
- **Conditionals and loops**: `@if`, `@forelse`, `@foreach`, `@error`.

### Q4: What are the key views (pages) in this project?
**A:** Major views include:

| View | File | Purpose |
|------|------|---------|
| Login | `auth/login.blade.php` | Sign-in form with password visibility toggle |
| Register | `auth/register.blade.php` | New user registration |
| Forum Feed | `forum/index.blade.php` | Topic listing with Hot/New/Top sorting |
| Topic Detail | `forum/show.blade.php` | Topic with replies |
| Create Topic | `forum/create-topic.blade.php` | New discussion form |
| Dashboard | `auth/dashboard.blade.php` | Recent topics + recommendations |
| Admin Dashboard | `admin/dashboard.blade.php` | Admin overview panel |
| Quiz Attempt | `quizzes/attempt.blade.php` | Countdown timer + questions |
| Conversations | `conversations/index.blade.php` | Chat listing |

### Q5: How does the login page work?
**A:** The login page (`auth/login.blade.php`) extends a guest layout and submits a POST to `/login`. It includes:
- **CSRF token**: `@csrf` — security protection.
- **Email + Password**: Required input fields.
- **Error display**: Shows "Invalid email or password" or specific blacklist/warning messages.
- **Remember me** checkbox: Persists login for 30 days.
- **Password toggle**: JS button that switches password field between `text` and `password` type.
- **Validation**: Email format + password minimum 8 characters.

### Q6: Explain the CSRF token shown in the login form.
**A:** `@csrf` generates a hidden input with a CSRF (Cross-Site Request Forgery) token. Laravel automatically checks this token on every POST/PUT/DELETE request to ensure the request came from your own website, not a malicious third-party site. Without it, Laravel would reject the form submission with a 419 page expired error.

---

## SECTION 2: DATABASE (10 marks)

### Q7: How many database tables does this project have? Name them.
**A:** The project has approximately 15+ tables. Core ones include:

| Table | Purpose |
|-------|---------|
| `roles` | User roles (System Administrator, Group Administrator, Lecturer, Member/Student) |
| `groups` | Groups for multi-tenancy (group isolation) |
| `users` | Registered users with profile, status, and role/group assignments |
| `topics` | Forum discussion threads |
| `posts` | Replies within topics |
| `topic_categories` | Classification categories (per-group), with admin-editable `keyword_hints` |
| `recommendation_log` | Recommendation history per user, with `relevance_score` |
| `export_logs` | Log of every topic PDF export (topic, user, file type) |
| `quizzes` | Quiz metadata (title, schedule, duration) |
| `questions` | Questions within a quiz (MCQ, True/False, Short answer) |
| `answers` | Answer options, with `is_correct` flag |
| `student_attempts` | Tracks each student quiz attempt (timing, auto-submit) |
| `student_answers` | Student's selected answers per question |
| `grades` | Calculated scores (total, max, percentage, participation, final) |
| `quiz_configuration` | Quiz settings (late join, lock screen, show results) |
| `conversations` | Chat conversations (direct or group) |
| `messages` | Individual chat messages |
| `conversation_participants` | Many-to-many: users in conversations |
| `notifications` | User notifications |
| `reports` | Content reports (topics and posts) |
| `audit_logs` | Admin action tracking |
| `warnings` | User warnings |
| `blacklist_records` | User blacklist entries with expiry |
| `group_admins` | Which users are admins of which groups |

### Q8: What is a migration in Laravel?
**A:** A migration is like a version control system for your database schema. Each migration file defines changes (`up()` creates/modifies tables, `down()` reverses them). Examples from this project:
- `2026_06_23_203352_create_roles_table.php` — creates the `roles` table.
- `2026_06_23_203600_create_users_table.php` — creates `users`, `password_reset_tokens`, and `sessions` tables.
- `2026_07_05_211218_create_quizzes_table.php` — creates the `quizzes` table with custom primary key `quiz_id`.

Command: `php artisan migrate` runs all pending migrations. `php artisan migrate:rollback` reverses the last batch.

### Q9: Explain the User table structure.
**A:** The `users` table (`2026_06_23_203600_create_users_table.php`):

| Column | Type | Purpose |
|--------|------|---------|
| `id` | BIGINT (auto-increment) | Primary key |
| `full_name` | VARCHAR(100) | User's display name |
| `email` | VARCHAR(100) UNIQUE | Login identifier |
| `password` | VARCHAR(255) | bcrypt-hashed password |
| `role_id` | BIGINT FK -> roles | User role (System Admin, Group Admin, Lecturer, Member/Student) |
| `group_id` | BIGINT FK -> groups (nullable) | Which group they belong to (null for System Admins) |
| `account_status` | ENUM('active','warned','blacklisted') | Moderation status |
| `last_active_at` | TIMESTAMP (nullable) | Last login time |
| `email_verified_at` | TIMESTAMP (nullable) | Email verification |
| `profile_picture` | VARCHAR(255) (nullable) | Avatar URL/path |

### Q10: Explain the relationship between users, roles, and groups.
**A:**
- **User -> Role**: **BelongsTo** — each user has exactly one role. `$user->role()` returns the associated Role model.
- **User -> Group**: **BelongsTo** — each user belongs to one group (except System Admins who are group-agnostic).
- **Role -> Users**: **HasMany** — each role can have many users.
- **Group -> Users**: **HasMany** — each group can have many users.

The code enforces that non-admin users MUST have a group_id (in the `booted()` method of `User.php`):
```php
if ($roleName !== 'System Administrator') {
    throw new RuntimeException('Every non-admin user must belong to a group.');
}
```

### Q11: Explain the Topics/Posts relationship.
**A:**
- **Topic -> Posts**: **HasMany** — a topic has many replies. The `posts` table has `topic_id` foreign key.
- **Post -> Topic**: **BelongsTo** — each post belongs to one topic.
- **Post -> User**: **BelongsTo** — each post was written by one user.

Key: Posts ARE the replies — there is no separate "replies" table. The comment in the migration says: "Each row is an individual reply within a topic."

### Q12: Explain the Quiz/Question/Answer schema.
**A:** A hierarchical quiz system:
- **Quiz** (`quizzes`): Has `quiz_id` (custom PK), title, description, scheduled_date, start_time, duration_minutes, lecturer_id, group_id, is_active.
- **Question** (`questions`): Has `question_id`, `quiz_id` (FK), question_text, question_type (MCQ/TF/Short), marks, question_order.
- **Answer** (`answers`): Has `answer_id`, `question_id` (FK), answer_text, is_correct (boolean).
- **Student Attempt** (`student_attempts`): Tracks when a student started/submitted a quiz.
- **Student Answer** (`student_answers`): Records which answer the student picked.
- **Grade** (`grades`): Calculated score (total_score, max_score, percentage, final_grade).

One Quiz has many Questions. One Question has many Answers (for MCQ, only one is marked `is_correct = true`).

### Q13: What does `onDelete('cascade')` mean?
**A:** It means: "When the parent record is deleted, automatically delete all child records too." Example: If a topic is deleted, `$table->foreignId('topic_id')->constrained()->onDelete('cascade')` means all posts in that topic are also deleted. This prevents orphaned records.

### Q14: What are Laravel Eloquent relationships?
**A:** Eloquent is Laravel's built-in ORM (Object-Relational Mapping). It lets you work with database records as PHP objects. Relationships define how models connect:
- `belongsTo()` — child points to parent (e.g., Post belongs to User)
- `hasMany()` — parent has many children (e.g., Group has many Users)
- `belongsToMany()` — many-to-many via pivot table (e.g., Users <-> Conversations via `conversation_participants`)
- `hasOne()` — one-to-one (e.g., Quiz has one QuizConfiguration)
- `morphMany()` — polymorphic (e.g., both Topic and Post can have Reports)

### Q15: What indexes exist and why?
**A:** Indexes speed up database queries. Examples:
- `users`: `email` UNIQUE (for login lookups)
- `topics`: `group_id`, `created_by`, `status` (for filtering by group and active topics)
- `posts`: `topic_id`, `user_id`, `is_removed` (for loading replies, filtering removed posts)
- `audit_logs`: `user_id + created_at`, `action + created_at`, `target_type + target_id` (for admin search/audit)

---

## SECTION 3: FUNCTIONALITY (35 marks)

### Q16: What are the main features of this application?
**A:** The Smart Discussion Forum has 6 major feature areas:

1. **Authentication System** — Register, Login, Logout, Password Reset, Email Verification, Remember Me
2. **Forum (Discussion Board)** — Create topics, reply, search, sort by Hot/New/Top, post visibility exclusions, PDF export, topic sharing
3. **Quiz System** — Lecturer: Create/manage quizzes with MCQ/TF questions, schedule, publish, auto-grade. Student: Timed attempts with auto-submit, results
4. **Chat / Messaging** — Create conversations (direct or group), real-time messages
5. **Admin Panel** — User management, roles, groups, moderation, warnings, blacklist, audit logs, IP whitelist, system config, statistics
6. **Notifications** — In-app notifications for replies, quiz announcements, warnings

### Q17: Explain the user roles and their permissions.
**A:** There are **4 roles**:

| Role | Permissions |
|------|------------|
| **System Administrator** | Full access to everything: all groups, user creation/deletion, system config, audit logs, IP whitelist. Group-agnostic (no `group_id` needed). |
| **Group Administrator** | Admin of specific group(s). Can manage group members, moderate posts, issue warnings, view group statistics. |
| **Lecturer** | Can create quizzes for their group(s), teach groups they're assigned to, view results. Bypasses post rate-limiting. |
| **Member / Student** | Can create topics, reply, take quizzes, participate in conversations. Subject to rate limits. Must belong to a group. |

**Code enforcement**: The `User` model has helper methods: `isSystemAdmin()`, `isGroupAdmin()`, `isAdmin()`, `isLecturer()`, `isStudent()`. Middleware (`IsAdmin`, `IsSystemAdmin`, `CanAdminGroup`) gates routes.

### Q18: How does login work step by step?
**A:** (`LoginController@authenticate`):
1. **Rate limit check**: Max 5 attempts per email+IP combo. After 5, locked out for 30 seconds.
2. **Validate input**: Email must be valid format, password minimum 8 chars.
3. **Look up user** by email in the database.
4. **Check password** with `Hash::check()`.
5. **Blacklist gate**: If `account_status === 'blacklisted'`, show blacklist expiry message.
6. **Warning gate**: If `account_status === 'warned'` and has unacknowledged warnings, log in but redirect to warning acknowledgement page first.
7. **Login**: `Auth::login()`, regenerate session, update `last_active_at`.
8. **Redirect**: Admin users go to `/admin/dashboard`, everyone else to `/dashboard`.

### Q19: How does registration work step by step?
**A:** (`RegisterController`):
1. **Show registration form** (`GET /register`).
2. **Store registration data** (`POST /register`): Validates name, email (unique), password (min 8, mixed case, numbers, confirmed). Stores data in `session('registration_data')`.
3. **Show onboarding** (`GET /onboarding`): Shows platform rules and group selection dropdown (student groups only).
4. **Agree to terms** (`POST /onboarding/agree`): Creates the user account in the database, sends welcome email, logs them in.
5. **Decline terms** (`POST /onboarding/decline`): Clears session data, redirects to register.

Registration is rate-limited to **3 registrations per 60 minutes** (`throttle:3,60`).

### Q20: How does the forum feed work?
**A:** (`ForumController@index`):
- Only shows **active** topics (`status = 'active'`).
- **Group isolation**: Unless user is System Admin, only shows topics from groups the user can access.
- **3 sort options** via `?sort=` parameter:
  - **New** (default): Latest created first (`latest()`).
  - **Hot**: Topics with most recent replies (`withMax('posts', 'created_at')` — activity-based).
  - **Top**: Most replies/popular (`orderByDesc('posts_count')`).
- Paginated at **10 topics per page**.
- View shows: title, creator name, time posted, reply count, post type badge (Discussion vs Question).

### Q21: How does the quiz system work?
**A:** Two separate UIs for lecturers and students.

**Lecturer side** (`QuizController`):
- **CRUD**: Create, edit, delete quizzes with title, description, schedule, duration.
- **Questions**: Add MCQ or True/False questions with answers, marks, and correct answer marked.
- **Publish**: When published, event `QuizPublished` fires, students get notified.
- **Results**: View performance report with statistics (average, highest, lowest scores).

**Student side** (`StudentQuizController`):
- **My Quizzes**: Dashboard listing available quizzes.
- **Announcement page**: Shown before quiz (rules, duration, etc.).
- **Attempt**: Timed quiz with countdown timer (JS-driven), answers saved via AJAX as student selects.
- **Auto-submit**: When timer expires, answers are auto-submitted.
- **Result**: Shows score after submission.

### Q22: What is group isolation / multi-tenancy?
**A:** This is a **multi-tenant** application — multiple groups (classes, organizations) use the same platform but cannot see each other's data. Key implementation:
- Every topic, post, quiz, conversation has a `group_id`.
- Queries filter by `accessibleGroupIds()`:
  - **Regular users**: Only their own group.
  - **Group Admins**: Their own + administered groups.
  - **Lecturers**: Their own + taught groups.
  - **System Admins**: ALL groups (platform-wide oversight).
- The `User` model has `accessibleGroupIds()` and `canAccessGroup()` methods.

### Q23: Explain how post rate limiting works.
**A:** (`ThrottlePosts` middleware):
- **Students/Members**: Max 3 topics per 60 seconds, max 5 replies per 60 seconds.
- **Lecturers and Admins**: Bypass the limit entirely (unlimited posting).
- Uses Laravel's `RateLimiter` with a composite key: `throttle.posts.{action}.{user_id}`.
- Limits topics and replies independently.
- Returns HTTP 429 (Too Many Requests) with retry-after time when limit exceeded.

### Q24: What is the recommendation system?
**A:** (`RecommendationService`): Provides personalized topic recommendations based on keyword-classified categories.
- `TopicClassificationService` auto-classifies every new topic into a category, storing a `classification_confidence` score (0–100) and flagging low-confidence topics (`classification_needs_review`) for admin review when confidence is below the configurable threshold (default 40%). Admins can extend the keyword map via `keyword_hints` on categories.
- `RecommendationService::generateRecommendations()` returns topics matching the user's preferred categories, each with a `relevance_score` (share of the user's engagement in that category) and a `recommendation_reason` ("Based on similar topics you engaged with" or "Popular in your group").
- Popular-topic fallbacks are capped at 50% relevance; every recommendation is logged to `recommendation_log` with its score so the same topic isn't recommended twice.
- Displayed on the dashboard and a dedicated `/recommendations` page with "% match" badges.

### Q25: What is the reporting/moderation system?
**A:** Users can report inappropriate topics or posts. Admins see reported content in the **Moderation Panel** (`/admin/moderation`):
- **Remove post**: Marks `is_removed = true` (soft-delete, keeps audit trail).
- **Ignore report**: Dismisses the flag.
- All moderation actions are logged in `moderation_logs` and `audit_logs`.

### Q26: How does topic sharing work?
**A:** Topics can be shared via a **signed URL**:
1. User clicks share on a topic -> generates a temporary signed URL (`URL::temporarySignedRoute()`).
2. The URL includes `expires` timestamp and `signature` hash.
3. Recipient (logged out or different user) can view the topic via `/shared/topic/{topic}/{signedUserId}`.
4. Laravel verifies the signature is valid and not expired before showing the content.

### Q27: What is the PDF export feature?
**A:** Topics can be exported as PDF using `barryvdh/laravel-dompdf` package:
- Route: `GET /forum/{topic}/export-pdf` (rate limited: 5 requests per minute to prevent DoS).
- Uses `dompdf` to render the topic and its replies into a downloadable PDF.
- Controller: `ForumController@exportPDF`.
- Every export is recorded in the `export_logs` table (`topic_id`, `user_id`, `file_type`) and the audit log (`topic.exported`) for traceability.

### Q28: What is the conversation/chat system?
**A:** A private messaging module:
- **Types**: `direct` (1-on-1) or `group` (multiple participants).
- **Features**: Create conversation, add/remove participants, send messages, read status.
- Conversation list shows last message preview via `lastMessage()` relationship.
- Scoped to the user's group (can't message across groups).

### Q29: What is Sanctum and what does it do?
**A:** **Laravel Sanctum** provides API token authentication for the REST API (`/api/v1/*`). Features:
- Issues tokens on login (`POST /api/v1/login`).
- Tokens are stored in `personal_access_tokens` table.
- Can list, refresh, and revoke tokens.
- API routes use `auth:sanctum` middleware.
- Stateful cookie-based auth for SPA clients.

### Q30: How does the entity (role-based access control) work?
**A:** The project has a multi-layered permission system:
1. **Middleware aliases**: `admin` (IsAdmin), `system-admin` (IsSystemAdmin), `can-admin-group` (CanAdminGroup).
2. **Model methods**: `isAdmin()`, `isSystemAdmin()`, `canAdminGroup()`, `canAdminUser()`, `canTeachGroup()`.
3. **Route groups**: Admin routes are nested under `->middleware('admin')`, then sub-grouped with `->middleware('system-admin')` for sensitive actions.
4. **Group scoping**: Even authenticated users can only see data from their accessible groups.

### Q31: What is the audit log system?
**A:** Every admin action is logged in `audit_logs` table for accountability:
- Who did what (`user_id`, `action`).
- What was affected (`target_type`, `target_id`).
- Before/after values (`old_values`, `new_values` stored as JSON).
- When and from where (`created_at`, `ip_address`, `user_agent`).
- Logs are searchable, filterable, and exportable from the admin panel.

---

## SECTION 4: GIT CONTRIBUTIONS (5 marks)

### Q32: What is Git and why is it used?
**A:** Git is a distributed version control system that tracks changes in source code during development. It allows:
- Multiple developers to work simultaneously.
- Rolling back to previous versions.
- Branching for features without affecting the main codebase.
- Tracking who changed what and when.

### Tips for Git marks:
- Make frequent, small commits with clear messages
- Use branches for features (e.g., `feature/quiz-system`, `bugfix/login-error`)
- Push commits to show consistent contribution
- Use `git commit -m "Clear message about what changed"`
- Avoid huge commits that change everything at once

---

## SECTION 5: GOOD PROGRAMMING PRINCIPLES (5 marks)

### Q33: What naming conventions does this project follow?
**A:** 
- **Tables**: snake_case plural (`users`, `topic_categories`, `student_attempts`)
- **Models**: PascalCase singular (`User.php`, `TopicCategory.php`)
- **Controllers**: PascalCase + Controller (`ForumController.php`, `UserManagementController.php`)
- **Methods/functions**: camelCase (`isSystemAdmin()`, `accessibleGroupIds()`)
- **Variables**: camelCase (`$user`, `$topicQuery`, `$maxAttempts`)
- **Routes**: kebab-case (`/change-password`, `/reset-password/{token}`)
- **Route names**: dot-notation (`forum.index`, `quizzes.attempt`)

### Q34: What kind of comments are used?
**A:** The codebase uses:
- **PHPDoc blocks** — describe methods, parameters, return types:
  ```php
  /**
   * Show the main dashboard with recent topics and personalized recommendations.
   *
   * GET /dashboard
   */
  ```
- **Inline section headers** — group related code (e.g., `// ============ FORUM ROUTES ============`)
- **Inline explanations** — explain WHY, not what:
  ```php
  // Secondary: email-only key (prevents IP rotation bypass)
  $emailKey = 'login-attempts-email:'.$request->input('email');
  ```
- **Task references** — map code to requirements:
  ```php
  // Task 2a.3 — Forum Feed (Topic List)
  ```

### Q35: What security practices are implemented?
**A:**
1. **CSRF protection**: `@csrf` in all forms; Sanctum for API.
2. **SQL injection prevention**: Eloquent ORM with parameter binding (no raw SQL concatenation).
3. **Password hashing**: `Hash::make()` and `Hash::check()` with bcrypt (12 rounds).
4. **Rate limiting**: Login attempts (5/m), registrations (3/hour), posts (3 or 5/minute), PDF export (5/minute), API (60/minute).
5. **Authorization middleware**: `admin`, `system-admin`, `can-admin-group`.
6. **Input validation**: `$request->validate()` with rules.
7. **Group isolation**: Even authenticated users only see their own group's data.
8. **API security headers**: `ApiSecurityHeaders` middleware.
9. **Signed URLs**: For topic sharing (tamper-proof).
10. **IP Whitelist**: Restrict admin access to specific IPs.

### Q36: What is the MVC pattern and how does this project use it?
**A:** MVC separates code into three interconnected parts:
- **Model**: `app/Models/` — database logic (e.g., `User.php`, `Topic.php`)
- **View**: `resources/views/` — presentation layer (Blade templates)
- **Controller**: `app/Http/Controllers/` — business logic (processes requests, coordinates Model and View)

**Request lifecycle**: Browser hits URL -> `routes/web.php` maps to Controller method -> Controller uses Model to query DB -> Controller returns View with data -> Browser renders HTML.

### Q37: What is dependency injection?
**A:** Laravel automatically resolves class dependencies. Example from `ThrottlePosts`:
```php
public function __construct(RateLimiter $limiter)
{
    $this->limiter = $limiter;
}
```
Laravel's service container automatically creates and injects the `RateLimiter` instance when the middleware is called.

---

## SECTION 6: HOSTING SYSTEM (10 marks)

### Q38: How is this application deployed?
**A:** The project uses **Docker** for containerized deployment. The `Dockerfile` shows:

```dockerfile
FROM php:8.4-apache           # PHP 8.4 with Apache
# ... installs extensions, Composer, Node.js
COPY . .                      # Copy all project files
RUN composer install          # Install PHP dependencies
RUN npm ci && npm run build   # Build frontend assets
# Configures Apache for Laravel routing
CMD php artisan migrate &&    # Run migrations on container start
    php artisan db:seed &&    # Seed database
    apache2-foreground        # Start Apache
EXPOSE 80                     # Port 80
```

The `.env.example` shows default config (SQLite for simplicity). In production you'd use MySQL/PostgreSQL.

The `.renderignore` file suggests the app is deployed on **Render** (a cloud platform).

### Q39: What environment configurations are used?
**A:** From `.env.example`:
- `APP_ENV=production` — the app runs in production mode.
- `APP_DEBUG=false` — no detailed error messages shown to users.
- `DB_CONNECTION` — defaults to SQLite but can switch to MySQL/PostgreSQL.
- `SESSION_DRIVER=database` — sessions stored in database.
- `QUEUE_CONNECTION=database` — queue jobs stored in database.
- `CACHE_STORE=database` — cache stored in database.
- `MAIL_MAILER=log` — emails written to log file for development (can switch to SMTP).

### Q40: What about environment-specific files?
**A:** The project has:
- `.env` — local/actual environment (NOT committed to Git — contains actual secrets).
- `.env.example` — template with placeholder values (committed to Git).
- The setup script copies `.env.example` to `.env` automatically.

The `COMPOSER_NO_INTERACTION=1` env prevents Composer from asking questions during CI/CD.

---

## SECTION 7: INDIVIDUAL CONTRIBUTION (30 marks)

### Q41: Explain your part of the code in detail.

**If you worked on Authentication:**
- Login/Logout: `app/Http/Controllers/Auth/LoginController.php`
- Registration + Onboarding: `app/Http/Controllers/Auth/RegisterController.php`
- Password management: `app/Http/Controllers/Auth/PasswordController.php`
- Email verification: `app/Http/Controllers/Auth/EmailVerificationController.php`
- Profile: `app/Http/Controllers/Auth/ProfileController.php`
- Blame works: Rate limiting, blacklist gate, warning gate, password strength rules.

**If you worked on Forum:**
- `app/Http/Controllers/ForumController.php` (594 lines)
- Handles: Topic listing with sort tabs, topic creation/editing, replying, search, PDF export, topic sharing, visibility exclusions.
- Routes: `/forum/*` group in `routes/web.php`.
- Models: `Topic.php`, `Post.php`, `PostVisibility.php`.

**If you worked on the Quiz system:**
- Lecturer side: `app/Http/Controllers/QuizController.php`
- Student side: `app/Http/Controllers/StudentQuizController.php`
- Questions: `app/Http/Controllers/QuestionController.php`
- Answers: `app/Http/Controllers/AnswerController.php`
- Models: `Quiz.php`, `Question.php`, `Answer.php`, `StudentAttempt.php`, `Grade.php`, `QuizConfiguration.php`
- All migrations for quiz-related tables.

**If you worked on Admin Panel:**
- User management: `Admin/UserManagementController.php`
- Groups: `Admin/GroupController.php`
- Moderation: `Admin/ModerationController.php`
- Warnings/Blacklist: `Admin/WarningController.php`, `Admin/BlacklistController.php`
- Statistics: `Admin/StatisticsController.php`, `Admin/GroupStatisticsController.php`
- System Config: `Admin/SystemConfigController.php`
- IP Whitelist: `Admin/IpWhitelistController.php`
- Audit Logs: `Admin/AuditLogController.php`

**If you worked on the API:**
- `routes/api.php` defines all v1 API routes under `api/v1/`.
- API Controllers are in `app/Http/Controllers/Api/`.
- Uses **Sanctum** for token-based auth.
- All API routes are rate-limited (60 req/min).

**If you worked on Messaging/Chat:**
- Conversations: `app/Http/Controllers/ConversationController.php`
- Messages: `app/Http/Controllers/MessageController.php`
- Message Status: `app/Http/Controllers/Api/MessageStatusController.php`

**If you worked on Sync/Offline:**
- Sync controller: `app/Http/Controllers/SyncController.php`
- Pull endpoint: `GET /api/v1/sync/pull` — fetch data since last sync.
- Push endpoint: `POST /api/v1/sync/push` — sync local changes.

### Q42: Explain a specific line of code you wrote.
**A study strategy**: Pick 2-3 methods from your area and be able to explain:
1. What the method does (purpose).
2. Every line — what it does and why.
3. What would happen if a line was removed.
4. What security/database/performance concerns it addresses.

**Example (LoginController):**
```php
$key = 'login-attempts:'.$request->input('email').'|'.$request->ip();
$emailKey = 'login-attempts-email:'.$request->input('email');
```
- **Purpose**: Creates unique rate-limiting keys for login attempts.
- **Why two keys**: The first key (IP + email) prevents brute force from one IP. The second (email-only) prevents an attacker from rotating IPs to bypass the limit.
- **What if removed**: Without the `$emailKey`, an attacker could try different passwords from different IPs indefinitely.

### Q43: Why does the User model enforce group_id for non-admins?
**A:** The `booted()` method in `User.php` registers a `saving` event:
```php
static::saving(function (User $user) {
    if (is_null($user->group_id)) {
        $roleName = Role::where('id', $user->role_id)->value('role_name');
        if ($roleName !== 'System Administrator') {
            throw new RuntimeException('Every non-admin user must belong to a group.');
        }
    }
});
```
**Why**: The app is multi-tenant (group-isolated). Every user must belong to a group for:
1. Data isolation — knowing which group's data they can see.
2. Authorization — checking group-admin permissions.
3. Quiz targeting — quizzes are assigned to groups, not individuals.

System Administrators are exempt because they oversee ALL groups.

### Q44: How would you add a new feature?
**A:** Steps:
1. **Create migration** (`php artisan make:migration add_edited_at_to_posts_table`)
2. **Update model** (`$fillable` array, new relationship if needed)
3. **Add route** in `routes/web.php` or `routes/api.php`
4. **Create/edit controller** method
5. **Create/edit view** (Blade template)
6. **Run migration**: `php artisan migrate`
7. **Test**: `php artisan test` or manual testing

### Q45: What would break if you deleted a specific file?
**Practice scenario questions like these:**
- "What if I deleted `ForumController.php`?" — All forum routes would return 500 errors.
- "What if I dropped the `roles` table?" — User authentication would fail because `isAdmin()` and similar methods need it.
- "What if I removed `@csrf` from a form?" — All POST requests would fail with 419 error.
- "What if I removed `auth` middleware from the forum routes?" — Anyone could access the forum without logging in.

### Q46: What is your understanding of this project overall?
**A:** This is a **Smart Discussion Forum** — a web application for educational/group discussions. Key concepts:
- **Built with Laravel 13** (PHP framework, MVC pattern)
- **Multi-tenant** (groups can't see each other's data)
- **Roles** (System Admin, Group Admin, Lecturer, Student)
- **Features**: Discussion forum, timed quizzes, real-time chat, admin panel, moderation, ML-based topic classification
- **Database**: MySQL/PostgreSQL/SQLite with Eloquent ORM
- **Authentication**: Session-based (web) + Sanctum tokens (API)
- **Frontend**: Blade templates with CSS (likely Tailwind-based)
- **Deployment**: Docker/Apache, deployed on Render
- **API**: RESTful API at `/api/v1/`

---

## TIPS FOR THE PRESENTATION

### How to answer if you're asked about code you don't fully understand:
"I worked primarily on [your area], but I understand the overall structure. From what I can see, this code in [file] is doing [purpose]. The key parts are [line X] which does [explanation], and [line Y] which handles [something]. If I had to modify this, I would start by reading the related model and migration to understand the database structure."

### How to show you understand Laravel basics:
- Know that `php artisan` is the CLI command for Laravel tools.
- Know the folder structure: `app/` (code), `resources/views/` (templates), `routes/` (URLs), `database/migrations/` (schema), `config/` (settings).
- Know that Eloquent lets you write `User::find(1)` instead of `SELECT * FROM users WHERE id = 1`.
- Know that Blade uses `{{ $variable }}` for safe output and `{!! $variable !!}` for raw output.

### Common "trap" questions and their answers:
1. **"Did you copy this from AI?"** — "I used Bwat to help generate parts of the code, but I understand every line. For example, [explain one specific method or concept from YOUR area of the project]."
2. **"What does this line do?"** — Know your files. Pick 3-5 key methods from your area and study them line by line.
3. **"Why did you use X instead of Y?"** — Have a reason. "We used Eloquent instead of raw SQL because it prevents SQL injection and is more readable."
4. **"What would happen if the database server went down?"** — "Laravel would throw a database connection error. In production, we'd have a monitoring system to check database health."

### Key Laravel commands to know:
| Command | What it does |
|---------|-------------|
| `php artisan migrate` | Run pending database migrations |
| `php artisan make:model X` | Create a model class |
| `php artisan make:controller X` | Create a controller |
| `php artisan make:migration X` | Create a migration file |
| `php artisan route:list` | Show all registered routes |
| `php artisan tinker` | Interactive PHP REPL for testing |
| `php artisan serve` | Start development server |
| `php artisan db:seed` | Seed database with test data |
| `composer install` | Install PHP dependencies |
| `npm run build` | Build frontend assets |

### Database table naming conventions in Laravel:
- Tables are **snake_case plural** (`posts`, `topic_categories`)
- Pivot tables: **singular_alphabetical** (`group_admins`, `conversation_participants`)
- Primary key: `id` by default (or custom like `quiz_id`, `question_id`)
- Foreign keys: `snake_case singular + _id` (`user_id`, `topic_id`, `group_id`)
- Timestamps: `created_at`, `updated_at` (added by `$table->timestamps()`)

---

> **Last advice**: Before your presentation, open the actual files you claim to have worked on. Read through each method. Understand what each line does. Be honest about what you know vs. what you're unsure about. Your lecturer would rather hear "I'm not 100% sure, but I think it does X" than a confidently wrong answer.
