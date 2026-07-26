# Smart Discussion Forum — Complete Technical Guide & Cheat Sheet

**Project:** Smart Discussion Forum (Group 23)
**Repository:** https://github.com/Da-code135/Smart-Discussion-Forum-G23
**Live URL:** https://smart-discussion-forum-g23.onrender.com
**Stack:** Laravel 13 (PHP 8.4) · Blade + Tailwind CSS v4 · JavaFX Desktop Client · Sanctum REST API · Supabase Postgres · Pusher WebSockets · Docker on Render

---

## Table of Contents

1. [System Architecture Overview](#1-system-architecture-overview)
2. [Web Application (Laravel 13)](#2-web-application-laravel-13)
3. [Desktop Client (JavaFX)](#3-desktop-client-javafx)
4. [REST API (v1, Sanctum)](#4-rest-api-v1-sanctum)
5. [Database Schema & Relationships](#5-database-schema--relationships)
6. [Authentication & Authorization](#6-authentication--authorization)
7. [Key Concepts Explained](#7-key-concepts-explained)
8. [Real-Time Features & Offline Sync](#8-real-time-features--offline-sync)
9. [Admin Governance & Audit Trails](#9-admin-governance--audit-trails)
10. [Hosting: Local vs Production](#10-hosting-local-vs-production)
11. [Cheat Sheet: Laravel / Artisan Commands](#11-cheat-sheet-laravel--artisan-commands)
12. [Cheat Sheet: Git Workflow](#12-cheat-sheet-git-workflow)
13. [Cheat Sheet: Database Operations](#13-cheat-sheet-database-operations)
14. [Cheat Sheet: API Endpoint Reference](#14-cheat-sheet-api-endpoint-reference)
15. [Cheat Sheet: Troubleshooting](#15-cheat-sheet-troubleshooting)
16. [Cheat Sheet: Deployment Procedures](#16-cheat-sheet-deployment-procedures)
17. [Step-by-Step: Common Tasks](#17-step-by-step-common-tasks)
18. [Testing, Debugging & Maintenance](#18-testing-debugging--maintenance)
19. [Demo Accounts & Quick Reference](#19-demo-accounts--quick-reference)

---

## 1. System Architecture Overview

Smart Discussion Forum is a **multi-tenant academic collaboration platform**. Two clients share one backend:

```
┌─────────────┐        ┌──────────────────┐
│   Browser    │──────▶│  Blade views +    │
│ (Blade UI)   │ session│  Web controllers  │
└─────────────┘        │                  │      ┌──────────────────┐
                        │   LARAVEL 13     │─────▶│ Supabase Postgres │
┌─────────────┐  token │                  │      │ (SQLite locally)  │
│  Desktop App │──────▶│  /api/v1 (~150   │      └──────────────────┘
│  (JavaFX)    │Sanctum │  REST endpoints) │
└─────────────┘        └──────┬───────────┘
       ▲                       │ broadcasts
       │  sync pull/push       ▼
       └──────────────  Pusher WebSockets  ◀──── Browser (Echo JS)
```

**Key architectural decisions:**

- **One backend, two frontends.** Web controllers render Blade views; API controllers (mirrored under `app/Http/Controllers/Api/`) return JSON. Both call the same Models, Services, and Policies, so business rules exist exactly once.
- **Multi-tenancy by `group_id`.** Every Topic, Quiz, and Conversation belongs to a Group. Query scopes (`scopeForGroup`, `scopeForUserInGroup`) enforce isolation. System Administrators transcend tenancy (`group_id = null`).
- **Service layer.** Complex logic lives in `app/Services/` (11 services: TopicClassificationService, RecommendationService, WarningService, AuditLogService, BulkOperationService, MessageStatusService, etc.), keeping controllers thin.
- **Event-driven real-time.** Chat and quiz events (`MessageSent`, `QuizWentLive`, etc.) are broadcast over Pusher private channels; authorization lives in `routes/channels.php`.
- **Free-tier-friendly hosting.** Docker on Render, Postgres on Supabase, WebSockets via Pusher, and scheduled jobs triggered by Supabase Cron hitting an internal HTTP endpoint (no long-running scheduler process needed).

**Directory map (web app):**

| Path | Purpose |
|---|---|
| `app/Http/Controllers/` | Web (Blade) controllers; `Api/` subfolder for JSON; `Admin/` and `Api/Admin/` for admin |
| `app/Models/` | 33 Eloquent models |
| `app/Services/` | Business logic services |
| `app/Policies/` | GroupPolicy, UserPolicy, WarningPolicy |
| `app/Http/Middleware/` | 7 custom middleware (role gates, IP whitelist, throttling, security headers) |
| `app/Console/Commands/` | 6 scheduled/CLI commands |
| `app/Events/` + `app/Listeners/` | Broadcast events and their listeners |
| `routes/` | `web.php`, `api.php`, `channels.php`, `console.php` |
| `database/migrations/` | 61 migrations (~45 tables) |
| `database/seeders/` | Roles, groups, super admin, categories, full demo data |
| `resources/views/` | Blade templates (Tailwind v4 via Vite) |
| `tests/` | 25 PHPUnit files, ~5,400 lines |

---

## 2. Web Application (Laravel 13)

### Feature modules

**Authentication & Onboarding** (`app/Http/Controllers/Auth/`)
- Register → mandatory onboarding rules page → *Agree* creates the user + `OnboardingAgreement` record; *Decline* abandons registration.
- Email verification via tokenized link (`EmailVerificationToken`); resend supported.
- Login/logout, forgot/reset password (web link flow), change password, profile edit + picture upload.
- Registration POST is throttled (3 per 60 minutes) to stop spam.

**Discussion Forum** (`ForumController`, ~608 lines)
- Topics are either **questions** or **discussions**; users reply with posts.
- Anti-flood: `ThrottlePosts` middleware limits members to 3 topics + 5 replies per minute (lecturers/admins bypass).
- Every new Topic is **auto-classified** into a category on creation (see §7).
- Per-post visibility: an author can exclude specific users from seeing a post (`PostVisibility`).
- Topics can be exported as PDF (throttled 5/min, logged in `export_logs`) and shared via expiring **signed URLs**.
- Content reporting feeds the admin moderation queue.

**Quizzes & Assessment** (`QuizController`, `StudentQuizController` ~657 lines)
- Lecturer creates a draft quiz (title, group, scheduled date/start time, duration) → adds questions/answers → publishes.
- The `quiz:activate` command (runs every minute) flips quizzes live at start time and fires `QuizWentLive` → students are notified.
- Students take a timed attempt: per-answer AJAX save, per-second status polling, manual submit, and JS auto-submit at expiry.
- **Late joiners never get extra time** — `Quiz::secondsRemainingFor()` clips the deadline to the scheduled close.
- Auto-grading: `StudentAttempt` → `StudentAnswer`s → one `Grade`. Lecturers get a results overview with stats and CSV export.

**Chat** (`ConversationController` ~438 lines, `MessageController`)
- Group-scoped conversations (direct or named group chats) with participants.
- Real-time delivery over Pusher; per-message delivered/read receipts (`MessageStatus`); unread counts per conversation.

**Admin Suite** (`Admin/` + `Api/Admin/`) — see §9.

**Analytics** — daily `app:calculate-statistics` computes per-group metrics (members, weekly active, posts, topics, unanswered questions, 30-day inactive) into the `statistics` table; admin dashboard shows them with a recalculate button. Participation marks for lecturers.

### Frontend build

- Tailwind CSS v4 compiled by **Vite** (`vite.config.js`, `resources/css`, `resources/js`).
- Laravel Echo (JS) subscribes to Pusher channels for live chat.
- After any frontend change locally: `npm run build` (or keep `npm run dev` running).
- `VITE_*` env vars are **baked into the JS bundle at build time** — changing them requires a rebuild.

---

## 3. Desktop Client (JavaFX)

**Location:** `C:\Users\LEGION\Documents\IdeaJ\Smart-Discussion-Forum-G23-DesktopApp` (Maven project, ~60 view classes).

| Component | Role |
|---|---|
| `App.java` | Entry point. Restores saved session; routes to Login or Dashboard; starts `SyncEngine` |
| `api/ApiClient.java` | HTTP wrapper for `https://.../api/v1`. Maps network failures to `ApiException` with `statusCode == 0` (the "offline" signal) |
| `api/AuthManager.java` | Sanctum token storage/restore (Java Preferences) |
| `api/SyncEngine.java` | Background sync every 30 s: pushes queued offline messages, pulls new data (see §8) |
| `views/DashboardView.java` | Shell with sidebar navigation; content swapped via `getContentArea().getChildren().setAll(...)` |
| `views/*View.java` | Login, Register, Home, Forum (TopicList), Quiz list, Conversations, plus 13 admin views |

- The UI is **pixel-aligned with the web theme**: accent `#59623e`, page background `#fff8f1`, surface `#fdf5ec`, border `#e9e1d8` — one design language across platforms.
- Navigation model: single Stage; `DashboardView` hosts a content area; each view class exposes a static `create(stage)`.
- Build/run: `mvnw.cmd compile` / `mvnw.cmd javafx:run` (or the IDE's *Build, release, run* task).

---

## 4. REST API (v1, Sanctum)

All endpoints live under `/api/v1` (defined in `routes/api.php`, ~150 endpoints), wrapped in `api.security` middleware (adds `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, HSTS, cache-control headers; strips `X-Powered-By`).

**Authentication flow:**
1. `POST /api/v1/login` with email/password → returns a **Sanctum personal access token**.
2. Client sends `Authorization: Bearer <token>` on every request.
3. Token lifecycle: `POST /logout`, `POST /token/refresh`, `GET /tokens`, `DELETE /tokens/{id}`.

**Route protection layers (outermost → innermost):**
`prefix v1` → `api.security` headers → `auth:sanctum` → (admin area) `admin` middleware → controller-level policy checks.

Public endpoints (no token): register, login, password forgot/reset, and signed-URL shared-topic access.

See §14 for the endpoint reference table.

---

## 5. Database Schema & Relationships

**~45 tables from 61 migrations.** Local dev uses SQLite (`database/database.sqlite`); production uses Supabase Postgres (`DB_CONNECTION=pgsql`, `DATABASE_URL`).

### Entity clusters

**Identity & tenancy**
```
roles (5 seeded) ──< users >── groups
                       │
                       ├──< onboarding_agreements
                       ├──< email_verification_tokens
                       ├──< personal_access_tokens (Sanctum)
                       └──< api_password_reset_otps
group_admins        (pivot: which Group Admins manage which groups)
lecturer_group_access (pivot: which groups a lecturer can teach)
```
- `User belongsTo Role, Group`. **System Administrators have `group_id = NULL`** — a `saving` model hook on `User` throws if any other role lacks a group.
- Seeded roles: 1 System Administrator, 2 Group Administrator, 3 Student, 4 Lecturer, 5 Member.

**Forum**
```
groups ──< topics ──< posts ──< post_visibility (per-user exclusions)
            │  │
            │  └── belongsTo topic_categories (set by auto-classifier)
            │      · classification_confidence (int %)
            │      · classification_needs_review (bool)
            ├──< reports (polymorphic: topics or posts)
            └──  moderation_logs, export_logs, recommendation_log
```

**Quizzes**
```
quizzes (PK quiz_id) ──< questions ──< answers (is_correct flag)
   │        └── hasOne quiz_configuration (notification_minutes_before)
   ├──< student_attempts ──< student_answers (attempt × question × answer)
   │            └── hasOne grades (score, percentage)
   └── belongsTo groups, users (lecturer_id)
```

**Chat & sync**
```
conversations ──< conversation_participants >── users
      │ (soft deletes)         (pivot: role, joined_at)
      ├──< messages (sender_id, is_removed) ──< message_status
      │                                        (per-recipient delivered/read)
      └── belongsTo groups
sync_checkpoints (user_id + device_id → last_synced_at)   ← desktop delta-sync cursor
```

**Governance**
```
warnings (warning_number 1/2, response_deadline, is_acknowledged, is_resolved)
blacklist_records (expires_at, lifted_at, lifted_by)
audit_logs · admin_ip_whitelist · system_configs (key/value) · notifications
```

**Analytics:** `statistics` (per-group cached metrics), `participation_activities`.

### Design highlights worth mentioning

- **Soft deletes** on groups and conversations (with restore flows).
- **Composite unique** constraint on topics; dedicated index migration (`add_missing_indexes_to_tables`) for hot paths.
- `system_configs` drives runtime behavior without redeploys (inactivity thresholds, classification review threshold, blacklist duration).
- Polymorphic `reports` table covers both topics and posts.

---

## 6. Authentication & Authorization

### Two auth systems, one user table
| | Web | API / Desktop |
|---|---|---|
| Mechanism | Session cookie (`auth` middleware) | Sanctum bearer token (`auth:sanctum`) |
| Login | `POST /login` (LoginController) | `POST /api/v1/login` (Api\AuthController) |
| Password reset | Email link | Email **OTP** (`api_password_reset_otps`) |

### Role-Based Access Control (RBAC)

Roles are rows in the `roles` table; helpers on `User` drive everything:
`isSystemAdmin()`, `isGroupAdmin()`, `isAdmin()`, `isLecturer()`, `isStudent()`, `canAdminGroup($group)`, `canAdminUser($user)`, `canTeachGroup($group)`, `accessibleGroupIds()`.

**The 7 custom middleware** (`app/Http/Middleware/`):

| Middleware | Alias | What it does |
|---|---|---|
| `IsAdmin` | `admin` | Any admin type (System or Group), else 403 |
| `IsSystemAdmin` | | System Administrator only |
| `IsGroupAdmin` | | At least Group Administrator |
| `CanAdminGroup` | `can-admin-group` | Resolves `{group}` route param, checks `canAdminGroup` for **that specific group** |
| `IpWhitelist` | | When `security.ip_whitelist_enabled` is on, blocks non-whitelisted IPs from the admin area and audit-logs the attempt |
| `ThrottlePosts` | `throttle.posts` | Anti-flood: 3 topics / 5 replies per minute per user; lecturers/admins bypass |
| `ApiSecurityHeaders` | `api.security` | Security headers on every API response |

**Policies** (fine-grained, used with `$this->authorize(...)`):
- `GroupPolicy` — create/delete/restore/assignAdmin = System Admin only; update/manageMembers = scoped by `canAdminGroup`.
- `UserPolicy` — Group Admins act only on users in their groups; `changeRole` and `delete` are System Admin only.
- `WarningPolicy` — any admin can issue; only System Admins resolve.

**Escalation ladder for misbehavior/inactivity:** Warning 1 → (deadline passes unacknowledged) → Warning 2 → Blacklist (time-boxed, liftable by admins). Users with unacknowledged warnings are gated to an acknowledgement page at login.

---

## 7. Key Concepts Explained

### Topic classification ("the ML feature")
`app/Services/TopicClassificationService.php` — runs automatically via a `created` model hook on Topic.

1. Concatenate title + description, lowercase.
2. Score each category by counting keyword hits (built-in keyword map, **merged with admin-editable `keyword_hints`** on each group's `topic_categories` rows).
3. Highest score wins; `confidence = winner hits / total hits × 100`.
4. If confidence < `classification_review_threshold` (SystemConfig, default 40), flag `classification_needs_review` for admins.
5. Category is `firstOrCreate`d per group; topic updated with category + metadata.

*Demo tip:* create a topic titled "How do SQL joins work with tables and queries?" → classified **Database** with a high confidence %.

### Recommendations
`RecommendationService` powers dashboard "recommended topics" (based on user activity/categories); every suggestion is logged to `recommendation_log`.

### Quiz lifecycle & timing
```
draft → publish (published_at set, QuizPublished event → announcement)
      → quiz:activate cron flips is_active at start (QuizWentLive → notifications)
      → students attempt (timer = min(personal deadline, scheduled close))
      → submit/auto-submit → auto-grade → Grade row → results & CSV export
```
Reminders: `quiz:send-reminders` (every minute) sends one-time notifications N minutes before start (per-quiz configurable, default 15, deduplicated per user).

### Real-time messaging
Message send → `MessageSent` event `broadcast()`s on private channel `conversation.{id}` → `routes/channels.php` authorizes **participants only** (non-admins additionally group-checked) → web client (Laravel Echo + Pusher JS) appends the bubble live; recipient posts back deliver/read receipts which broadcast `MessageDelivered` / `MessagesRead`.

---

## 8. Real-Time Features & Offline Sync

### Server sync contract (`SyncController`)

| Endpoint | Contract |
|---|---|
| `GET /api/v1/sync/pull?device_id={id}` | Returns `{success, data:{conversations[], messages[], status_updates[], synced_at}}` — everything **since this device's last checkpoint**. First sync defaults to 1 year back. Checkpoint (`sync_checkpoints`) advances only after the payload is built. |
| `POST /api/v1/sync/push` | Body `{messages:[{client_id, conversation_id, body}]}` (max 100). Per message: participant access check → **dedupe** (same sender+body within 5 min returns the existing id) → save → broadcast. Returns `data.results[] = {client_id, success, message_id|error}`. |

### Desktop `SyncEngine` (singleton)

- JavaFX `ScheduledService` fires **every 30 seconds**: push queued messages first, then pull.
- **Offline compose:** if a send fails with `ApiException.statusCode == 0` (network unreachable), the message is queued in Java Preferences (device-persistent, survives restarts) and shown as a dimmed "⏳ Pending — sends when back online" bubble.
- **Batch settle semantics:** a 200 response settles the whole batch (per-message failures are permanent → logged and dropped); only transport-level failures keep the batch queued. Server dedupe makes retries safe.
- Pulled data is delivered to the visible chat view via a callback on the JavaFX thread; views re-register the callback when created and ignore it when detached.
- Lifecycle: started on dashboard load (fresh login or restored session), stopped on logout.
- Device identity: `{hostname}-{8-char UUID}` stored in Preferences → per-device checkpoints server-side.

**Demo flow:** disconnect Wi-Fi → send message → pending bubble → reconnect → within 30 s the batch pushes, server saves + broadcasts, next pull confirms, bubble becomes a real message.

---

## 9. Admin Governance & Audit Trails

| Area | Web controller | Capabilities |
|---|---|---|
| Users | `Admin/UserManagementController` (501 lines) | Search/list, detail with warning+blacklist history, create/edit, change role (SysAdmin), reset password, blacklist/lift, warn |
| Groups | `Admin/GroupController` | CRUD, soft-delete + trashed + restore, member roster, bulk assign, group-admin assignment |
| Moderation | `Admin/ModerationController` | Queue of reported posts → remove or ignore (logged to `moderation_logs`) |
| Warnings/Blacklist | `Admin/WarningController`, `BlacklistController` | Issue/resolve warnings; time-boxed blacklists with lift |
| Audit logs | `Admin/AuditLogController` | Filterable viewer + export; `AuditLogService` writes entries (incl. blocked admin IPs) |
| IP whitelist | `Admin/IpWhitelistController` | CRUD + activate/deactivate; enforced by `IpWhitelist` middleware when enabled |
| System config | `Admin/SystemConfigController` | Runtime key/value editor (thresholds, durations) |
| Statistics | `Admin/StatisticsController` | Per-group dashboards + on-demand recalculation |

API-only power tools (`Api/Admin/`): **bulk operations** (7 actions: change-roles, change-status, assign-group, blacklist, lift-blacklist, warn, assign-group-admins) and **advanced search** across users/groups/audit-logs/warnings with suggestions.

**Automated governance:** `monitor:activity` (daily 02:00) walks active/warned users, measures inactivity against `inactivity_warning_days`, and applies the 3-step escalation. Supports `--dry-run`.

---

## 10. Hosting: Local vs Production

| Concern | Local (dev) | Production (Render) |
|---|---|---|
| Server | Laravel Herd (`https://smart-discussion-forum-g23.test`) | Docker: PHP 8.4 + Apache, doc root `/public` |
| Database | SQLite `database/database.sqlite` | Supabase **Postgres** (`DB_CONNECTION=pgsql`, `DATABASE_URL`) |
| Frontend assets | `npm run dev` (hot) or `npm run build` | Built inside the Docker image (`npm ci && npm run build`) |
| Broadcasting | Pusher (same as prod) / Reverb possible | **Pusher** (Reverb can't run as a 2nd process on free tier) |
| Scheduler | `php artisan schedule:work` | **Supabase Cron** → `POST /api/internal/run-schedule` with `X-Cron-Secret` header |
| Queue | sync | sync (`QUEUE_CONNECTION=sync`) |
| Cache/Sessions | database | database |
| Mail | SMTP (Brevo/Resend) | same |
| Logs | `storage/logs/laravel.log` | stderr → Render log stream |
| Debug | `APP_DEBUG=true` | `false`, `LOG_LEVEL=error` |
| OPcache | default | `validate_timestamps=0` (code immutable in container) |

**Deploy pipeline:** push to `main` → Render auto-builds the Docker image (composer install → npm build → asset bake) → on boot: `config:cache`, `view:cache`, `migrate --force`, optional one-shot seed when `RUN_SEED=true`. Health check: `/up`.

**Gotchas:**
- `VITE_*` vars are compile-time: changing Pusher keys requires a **rebuild**, not just a restart.
- Render's own free Postgres expires after ~30 days — that's why the DB lives on Supabase.
- The current working branch is `chat-and-sync`; **only `main` auto-deploys**.

---

## 11. Cheat Sheet: Laravel / Artisan Commands

```bash
# ---- Everyday ----
php artisan route:list --except-vendor        # all routes
php artisan route:list --path=api --method=GET
php artisan config:show database.default      # read config value
php artisan about                             # env summary

# ---- Database ----
php artisan migrate                           # run pending migrations
php artisan migrate --force                   # in production (no prompt)
php artisan migrate:fresh --seed              # DANGER: rebuild DB + seed
php artisan db:seed                           # run DatabaseSeeder
php artisan db:seed --class=DemoDataSeeder    # one seeder
php artisan make:migration create_x_table     # new migration
php artisan make:model X -mfs                 # model + migration + factory + seeder

# ---- Project-specific commands ----
php artisan quiz:activate                     # flip due quizzes live
php artisan quiz:send-reminders               # pre-start reminders
php artisan monitor:activity --dry-run        # inactivity escalation (safe preview)
php artisan app:calculate-statistics          # per-group stats
php artisan app:classify-topics {groupId}     # bulk classify a group
php artisan posts:flag {postId}               # flag post for moderation

# ---- Tinker (REPL) ----
php artisan tinker --execute 'User::count();'
php artisan tinker --execute 'User::where("email","student1@example.com")->first();'

# ---- Testing & quality ----
php artisan test --compact                    # full suite
php artisan test --compact tests/Feature/Chat/SyncTest.php
php artisan test --compact --filter=testName
vendor/bin/pint --dirty                       # format changed PHP files

# ---- Caches (fix weird behavior) ----
php artisan optimize:clear                    # clears config/route/view/event caches
php artisan config:cache && php artisan view:cache   # rebuild (production)

# ---- Frontend ----
npm run dev                                   # Vite dev server (hot reload)
npm run build                                 # production build
composer run dev                              # app + queue + vite together

# ---- Scheduler (local) ----
php artisan schedule:list
php artisan schedule:work                     # run scheduler in foreground
```

---

## 12. Cheat Sheet: Git Workflow

**Branch model:** `main` (deployable, auto-deploys to Render) + feature branches:
`feature/user-management`, `feature/discussion-forum-core`, `feature/quiz-and-assessment`, `feature/Analytics-ML&Administration`, `chat-and-sync` (current).

```bash
# ---- Daily flow ----
git status -sb                        # what changed
git add app/Services/Foo.php          # stage specific files (avoid add .)
git commit -m "Add X validation to Foo service"
git push origin chat-and-sync

# ---- Branching ----
git checkout -b feature/my-feature    # new branch
git switch main                       # move between branches
git pull origin main                  # update local main
git merge main                        # bring main into current branch

# ---- Releasing to production ----
git switch main
git pull origin main
git merge chat-and-sync               # or open a PR on GitHub (preferred)
git push origin main                  # ← triggers Render auto-deploy

# ---- Inspecting history / stats ----
git log --oneline -15
git shortlog -sne --all               # commits per contributor
git log --author="Kevin" --oneline
git diff main..chat-and-sync --stat   # what differs from main
git blame app/Models/Quiz.php         # who wrote each line
```

**Best practices used in this repo:**
- One feature per branch, named `feature/<area>`.
- Imperative, descriptive commit messages ("Add quiz auto-submit endpoint", not "fixes").
- Merge to `main` only when demo-ready (it deploys automatically).
- Never commit `.env`, `node_modules/`, `vendor/` (already gitignored).

---

## 13. Cheat Sheet: Database Operations

### Editing records — 3 ways

**1. PhpStorm Database tool (fastest live demo):** the `database.sqlite` datasource is preconfigured → open a table → double-click a cell → edit → Submit (Ctrl+Enter).

**2. Tinker:**
```bash
php artisan tinker --execute 'User::where("email","student1@example.com")->update(["full_name" => "Bob Renamed"]);'
php artisan tinker --execute 'Topic::latest()->first();'
php artisan tinker --execute 'Quiz::with("questions.answers")->find(1);'
```

**3. Production:** Supabase dashboard → Table Editor.

### Common Eloquent queries

```php
// Users by role
User::whereHas('role', fn($q) => $q->where('role_name','Student'))->get();

// Topics in a group with reply counts
Topic::forGroup(3)->withCount('posts')->latest()->get();

// Unanswered questions
Topic::where('post_type','question')->doesntHave('posts')->get();

// A student's quiz grades
Grade::where('student_id', $id)->with('quiz:quiz_id,title')->get();

// Unread messages per conversation for a user
MessageStatus::where('user_id',$id)->whereNull('read_at')->count();

// Avoid N+1: always eager-load
Conversation::with(['participants','lastMessage.sender'])->get();
```

### Raw SQL (SQLite locally)

```sql
SELECT u.full_name, r.role_name, g.group_name
FROM users u
JOIN roles r ON r.id = u.role_id
LEFT JOIN groups g ON g.id = u.group_id;

SELECT t.title, tc.category_name, t.classification_confidence
FROM topics t LEFT JOIN topic_categories tc ON tc.id = t.category_id;

UPDATE system_configs SET value = '21' WHERE key = 'inactivity_warning_days';
```

---

## 14. Cheat Sheet: API Endpoint Reference

Base URL: `https://smart-discussion-forum-g23.onrender.com/api/v1` · Auth: `Authorization: Bearer <token>`

| Area | Method & Path | Notes |
|---|---|---|
| **Auth** | `POST /register`, `POST /login` | public; returns token |
| | `POST /logout`, `POST /token/refresh`, `GET /tokens` | token lifecycle |
| | `POST /password/forgot`, `POST /password/reset` | OTP flow |
| **Me** | `GET /me`, `POST /profile`, `POST /profile/picture`, `POST /password/change` | |
| **Forum** | `GET/POST /topics`, `GET/PUT/DELETE /topics/{id}` | create is throttled |
| | `GET /topics/{id}/posts`, `POST /topics/{id}/posts` | replies (throttled) |
| | `GET /topics/{id}/export/pdf`, `POST /topics/{id}/share` | export & signed share |
| | `POST /posts/{id}/visibility/exclude` | hide post from a user |
| | `GET /categories`, `GET /recommendations`, `POST /search/topics` | |
| **Quizzes** | `GET /quizzes/upcoming`, `GET /quizzes/live` | student discovery |
| | `GET /my-quizzes`, `POST /quizzes/{q}/attempt`, `POST /quizzes/{q}/answer`, `POST /quizzes/{q}/answers/batch`, `POST /quizzes/{q}/submit`, `POST /quizzes/{q}/auto-submit`, `GET /quizzes/{q}/status` | attempt flow |
| | `GET /quizzes/{q}/result`, `GET /me/quiz-history` | results |
| | `GET/POST /quizzes`, `POST /quizzes/{q}/publish`, CRUD questions/answers | lecturer |
| | `GET /lecturer/quizzes/{q}/grades`, `.../grades/export` | grades + CSV |
| **Chat** | `GET/POST /conversations`, `GET/DELETE /conversations/{id}` | |
| | `POST /conversations/{id}/participants` | add participant |
| | `GET/POST /conversations/{id}/messages`, `PUT/DELETE /messages/{id}` | |
| | `POST /messages/{id}/deliver`, `POST /conversations/{id}/read`, `GET /me/unread-counts` | receipts |
| **Sync** | `GET /sync/pull?device_id=`, `POST /sync/push` | desktop offline sync |
| **Notifications** | `GET /me/notifications`, `GET /me/notifications/unread-count`, `POST /notifications/{id}/read` | |
| **Admin** | `GET /admin/users`, `POST /admin/users/{id}/warn`, `.../blacklist`, `.../change-role` | `admin` middleware |
| | `GET /admin/moderation`, `POST /admin/moderation/{post}/remove` | |
| | `GET /admin/groups`, `POST /admin/groups/{id}/restore` | soft-delete aware |
| | `POST /admin/bulk/*` (7 ops), `POST /admin/search/*` | power tools |
| | `GET /admin/audit-logs`, `GET /admin/dashboard`, `GET /admin/group-statistics` | |
| **Internal** | `POST /api/internal/run-schedule` | `X-Cron-Secret` header; Supabase Cron |

**Quick test with curl:**
```bash
curl -X POST https://smart-discussion-forum-g23.onrender.com/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email":"student1@example.com","password":"password"}'

curl https://smart-discussion-forum-g23.onrender.com/api/v1/me \
  -H "Authorization: Bearer <TOKEN>"
```

---

## 15. Cheat Sheet: Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| "Unable to locate file in Vite manifest" | Frontend not built | `npm run build` (or run `npm run dev`) |
| CSS/JS changes not showing | Stale build / cached views | `npm run build`, then `php artisan view:clear`; hard-refresh browser |
| Config change ignored | Config is cached | `php artisan optimize:clear` |
| 419 Page Expired on forms | CSRF/session issue | Refresh page; check `SESSION_DRIVER=database` and sessions table exists |
| 403 on `/admin/*` | Not an admin, or IP whitelist enabled | Check role; check `security.ip_whitelist_enabled` + `admin_ip_whitelist` rows |
| 429 Too Many Requests posting | `ThrottlePosts` anti-flood | Wait 60 s (by design), or test as lecturer/admin |
| Real-time chat not updating | Pusher creds / bundle stale | Verify `PUSHER_*` + `VITE_PUSHER_*`; rebuild assets (VITE vars are compile-time) |
| Quiz never goes live | Scheduler not running | Local: `php artisan schedule:work`. Prod: check Supabase Cron hits `/api/internal/run-schedule` |
| Desktop app can't log in | Token/URL issue | Check `ApiClient.BASE_URL`; Render free tier cold-starts (~1 min) after idle |
| Desktop offline messages stuck | Sync engine not running | Re-login (SyncEngine starts on dashboard); check stderr logs |
| Migration fails in prod | Postgres vs SQLite syntax | Test migrations against pgsql; avoid DB-specific raw SQL |
| Emails not arriving | SMTP creds / spam folder | Check `MAIL_*` env; check provider dashboard (Brevo/Resend) |
| Render deploy fails | Docker build error | Check Render build logs; usually npm/composer step |
| Site slow on first request | Free-tier cold start | Expected; container spins down after idle |

**Where to look:**
- Local logs: `storage/logs/laravel.log` (live-tail with `php artisan pail`)
- Production logs: Render dashboard → Logs (app logs go to stderr)
- Browser: DevTools console + Network tab (Pusher frames visible under WS)

---

## 16. Cheat Sheet: Deployment Procedures

### Standard production deploy
```bash
# 1. Make sure the branch is green
php artisan test --compact
vendor/bin/pint --dirty

# 2. Merge to main (PR preferred)
git switch main && git pull origin main
git merge chat-and-sync
git push origin main        # ← Render auto-deploys from main

# 3. Watch the build on the Render dashboard (Docker build ~5-10 min)
# 4. Verify: https://smart-discussion-forum-g23.onrender.com/up  → 200
```

### What Render does automatically (Dockerfile CMD)
```
composer install --no-dev --optimize-autoloader
npm ci && npm run build          (VITE_* baked in here)
php artisan config:cache
php artisan view:cache
php artisan migrate --force
[db:seed --force  only if RUN_SEED=true]
apache2-foreground
```

### One-time / special operations
- **Seed production:** set env `RUN_SEED=true` in Render → redeploy → **set back to false**.
- **Change a secret (e.g. Pusher key):** update env var in Render; if it's a `VITE_*` var, trigger **Manual Deploy → Clear build cache & deploy** so the JS bundle is rebuilt.
- **Rollback:** Render dashboard → Deploys → pick a previous successful deploy → Rollback.

### Desktop app release
```bash
cd Smart-Discussion-Forum-G23-DesktopApp
mvnw.cmd clean package            # or IDE task "Build, release, run"
mvnw.cmd javafx:run               # run from source
```

---

## 17. Step-by-Step: Common Tasks

### Add a new field to a model (example: `bio` on users)
```bash
php artisan make:migration add_bio_to_users_table --no-interaction
```
```php
// migration
Schema::table('users', fn (Blueprint $t) => $t->text('bio')->nullable());
```
```bash
php artisan migrate
```
Then: add `'bio'` to `$fillable` in `app/Models/User.php`, add the input to the profile Blade + validation in `ProfileController`, and a line to the API `ProfileController@update`. Write/adjust a test, run `php artisan test --compact --filter=Profile`.

### Add a new API endpoint
1. Route in `routes/api.php` inside the `auth:sanctum` group.
2. Controller action in `app/Http/Controllers/Api/...` with validation + explicit return types.
3. Policy/middleware check if role-restricted.
4. Feature test in `tests/Feature/Api/`.

### Add a classification keyword (instant demo change)
Edit `$categoryKeywords` in `app/Services/TopicClassificationService.php` — or, without touching code, edit `keyword_hints` on a `topic_categories` row (admin-editable). Create a matching topic and watch it classify.

### Issue a warning manually (admin demo)
Web: Admin → Users → user detail → Warn. API: `POST /api/v1/admin/users/{id}/warn`. Then log in as that user → the acknowledgement gate appears.

### Change a runtime threshold without deploying
Admin → System Config → e.g. set `inactivity_warning_days` from 30 → 21. `monitor:activity --dry-run` shows the effect safely.

---

## 18. Testing, Debugging & Maintenance

### Testing (PHPUnit 12 — 25 files, ~5,400 lines)
- Structure: `tests/Feature/{Admin,Api,Chat,Console,View,Web}` + `tests/Unit`. Shared helper `CreatesTestUsers` builds users per role via factories.
- Heavyweights worth citing: `UserManagementTest` (533 lines), `SyncTest` (473 lines), `ForumExportAndShareTest` (347 lines).
- Run minimal scopes while developing:
```bash
php artisan test --compact tests/Feature/Chat/SyncTest.php
php artisan test --compact --filter=test_pull_returns_new_messages
```
- Convention: every change ships with a new/updated test; factories (with states) over hand-built models.

### Debugging toolbox
| Tool | Use |
|---|---|
| `php artisan pail` | Live-tail logs in terminal |
| `dd($var)` / `Log::info(...)` | Quick inspection (remove before commit) |
| `php artisan tinker` | Poke at models/services in app context |
| Laravel Boost MCP (`database-query`, `browser-logs`) | Read-only DB queries; recent browser errors |
| Browser DevTools → WS tab | Watch Pusher frames for chat debugging |
| `--dry-run` on `monitor:activity` | Preview escalation without writes |

### Code maintenance standards
- **Laravel Pint** formats all PHP (`vendor/bin/pint --dirty` before committing).
- PHP 8.4 idioms: constructor property promotion, explicit return types, typed params, PHPDoc with array shapes.
- Controllers thin → Services for logic → Policies for authorization → Models for relationships/scopes.
- Descriptive naming everywhere (`canAdminGroup`, `secondsRemainingFor`, `lecturerQuizzesWithGrades`).
- Known debt to be aware of: `routes/web.php` contains a duplicated admin route block (merge artifact, ~lines 442–711 repeated at 713–951) — harmless (later registrations win) but queued for cleanup.

---

## 19. Demo Accounts & Quick Reference

All seeded accounts use password **`password`**:

| Email | Role | Notes |
|---|---|---|
| `superadmin@example.com` | System Administrator | No group; sees everything |
| `groupadmin@example.com` | Group Administrator | Manages the student group |
| `lecturer@example.com` | Lecturer | Creates quizzes; taught-group access |
| `student1@example.com` / `student2@example.com` | Student | Forum + quizzes + chat |
| `member@example.com` | Member | Discussion features only |
| `student3@example.com` | Student | Deliberately 45 days inactive (escalation demo) |

**Key numbers to remember:** 2 clients · ~150 API endpoints · ~45 tables / 61 migrations · 33 models · 67 controllers · 11 services · 7 middleware · 3 policies · 6 scheduled commands · 5 roles · 25 test files (~5,400 lines) · 5-person team, ~460 commits across 6 branches.

**URLs:**
- Production: `https://smart-discussion-forum-g23.onrender.com` (health: `/up`)
- Local (Herd): `https://smart-discussion-forum-g23.test`
- Repo: `https://github.com/Da-code135/Smart-Discussion-Forum-G23`

---

*Generated July 2026 · Smart Discussion Forum G23 · Laravel 13 / PHP 8.4 / JavaFX / Supabase / Render*
