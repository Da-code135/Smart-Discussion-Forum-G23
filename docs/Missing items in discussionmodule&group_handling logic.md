# Smart Discussion Forum — Implementation Summary

## Overview

This document summarizes everything built across all 5 work streams (Person 1–5).
The project is a **Laravel 13** discussion forum with group isolation, role-based
access control, moderation tooling, notifications, rate limiting, PDF export, and
a REST API.

---

## Person 1 — Database & Schema Foundation

### Goal
All database changes and seeders so the rest of the team can build on a working schema.

### Migrations Created

| File | Purpose |
|---|---|
| `2026_07_04_173500_add_group_type_to_groups_table.php` | Adds `group_type` enum (`sysadmin`, `lecturer`, `student`) to the `groups` table. Default: `student`. |
| `2026_07_04_173600_create_lecturer_group_access_table.php` | Pivot table `lecturer_group_access` with `lecturer_id`, `group_id`, unique composite key. *(Not wired to any model or controller — reserved for future use.)* |
| `2026_07_04_173700_create_notifications_table.php` | `notifications` table: `id`, `user_id`, `type`, `data` (JSON), `read_at`, timestamps. |
| `2026_07_04_173800_add_is_answered_and_is_pinned_to_topics_table.php` | Adds `is_answered` (boolean, default false) and `is_pinned` (boolean, default false) to `topics`. |

### Seeders

**GroupSeeder** — Creates exactly 3 groups (no "General" group):

| group_name | group_type |
|---|---|
| Platform Administrators | `sysadmin` |
| Faculty | `lecturer` |
| Students | `student` |

**RoleSeeder** — Creates 5 roles:

| id | role_name |
|---|---|
| 1 | System Administrator |
| 2 | Group Administrator |
| 3 | Student |
| 4 | Lecturer |
| 5 | Member |

**SuperAdminSeeder** — Creates a System Admin user (`superadmin@example.com` /
`password`) assigned to the `sysadmin` group as both role and `group_id`.

### Acceptance
`php artisan migrate:fresh --seed` produces exactly 3 groups (no General),
the super admin belongs to the sysadmin group, and a user without `group_id`
cannot exist.

---

## Person 2 — Notifications & Question Answered

### Goal
When someone replies to a question topic, the original asker is notified.
Topics can be marked as answered.

### Models
- **`Notification`** — `user_id`, `type`, `data` (JSON), `read_at`, `created_at`.
  Scopes: `unread()`.

### API Endpoints
| Method | Path | Description |
|---|---|---|
| GET | `/api/v1/me/notifications` | List user's notifications (unread first) |
| POST | `/api/v1/notifications/{id}/read` | Mark notification as read |

### Web Routes
| Method | Path | Description |
|---|---|---|
| GET | `/notifications` | Notifications page (Blade view) |
| POST | `/notifications/{id}/read` | Mark as read from web |

### Notification Logic
In **both** `PostController::store()` (API) and `ForumController::replyStore()` (web):
- If the topic `post_type === 'question'` and the replier is **not** the topic creator,
  a `question_answered` notification is created for the original asker.

### Auto-Mark Answered
In both reply controllers, when a reply is posted to a question topic,
`is_answered` is automatically set to `true`.

### Manual Toggle
- **API**: `POST /api/v1/topics/{topicId}/toggle-answered` — only topic creator
  or admin can toggle.
- **Web**: Equivalent route/controller access via `ForumController::edit/update`.
- Validation: Only question-type topics can be toggled.

---

## Person 3 — Anti-Flood & Rate Limiting

### Goal
Prevent a single user from flooding topics with replies.

### Middleware
**`ThrottlePosts`** (`app/Http/Middleware/ThrottlePosts.php`)

| Action | Limit | Window |
|---|---|---|
| `reply` | 5 posts | 60 seconds |
| `topic` | 3 topics | 60 seconds |

**Bypass**: System Admins, Group Admins, and Lecturers are never throttled.

Uses Laravel's `RateLimiter` facade with per-user, per-action cache keys.

### Registration
Middleware alias: `throttle.posts` — registered in `bootstrap/app.php`.

### Applied Routes
**API** (`routes/api.php`):
- `POST /api/v1/topics` (topic creation)
- `POST /api/v1/topics/{topicId}/posts` (reply creation)

**Web** (`routes/web.php`):
- `POST /forum` (topic creation)
- `POST /forum/{topic}/reply` (reply creation)

### 429 Response
```json
{"message": "You are posting too fast. Please wait."}
```

### Acceptance
6 replies in 1 minute → 429 on the 6th. Admins/lecturers never blocked.

---

## Person 4 — Onboarding & Atomic Registration

### Goal
Registration is a strict, atomic gate. No General group fallback. Decline the
rules → no account.

### Key Changes
1. **GroupSeeder** — No "General" group created.
2. **RegisterController::showOnboarding()** — Queries only student-type groups
   (`Group::where('group_type', 'student')->get()`).
3. **Onboarding view** (`onboarding.blade.php`):
   - Shows platform rules in a scrollable box.
   - A `<select>` dropdown of student groups — "Agree" button is disabled until
     both the checkbox is checked AND a group is selected.
4. **Atomic registration** — `agreeOnboarding()` wraps user creation + agreement
   in `DB::transaction()`:
   ```php
   DB::transaction(function () use (...) {
       $group = Group::findOrFail($validated['group_id']);
       $user = User::create([...]);        // with group_id
       OnboardingAgreement::create([...]); // agreed = true
       return $user;
   });
   ```
   Any failure (invalid group, DB exception) rolls back entirely — no partial
   user record.
5. **Decline path** — Clears session data. No User or OnboardingAgreement record
   is created. Redirects back to register.

### Acceptance
Register → fill form → see rules + group dropdown → pick group → agree →
account created in chosen group. Decline → no row exists.

---

## Person 5 — Access Control & Auto-Promotion

### Goal
Auto-promote the first Member-role student in a student group to Group Admin.
Guarantee every user has a valid `group_id`.

### Auto-Promotion Logic
A new method on the **Group model**:
```php
Group::autoPromoteFirstStudent(User $user, ?int $assignedBy = null): void
```
- Only fires when `group_type === 'student'`.
- Counts users with the "Member" role in the group.
- If exactly **1** (the user just assigned), adds them to `group_admins` pivot,
  making them a Group Administrator for that group.

### Where Auto-Promotion is Triggered

| Location | File | When |
|---|---|---|
| User registration | `RegisterController::agreeOnboarding()` | After user created in transaction |
| Admin creates user | `Admin/UserManagementController::store()` | After user record created |
| Admin updates user group | `Admin/UserManagementController::update()` | When `group_id` changes |
| Admin manages group members (web) | `Admin/GroupController::updateMembers()` | For each newly added member |
| Admin bulk-assigns users (web) | `Admin/GroupController::bulkAssign()` | For each assigned user |
| Admin manages group members (API) | `Api/Admin/GroupController::updateMembers()` | For each newly synced user |
| Bulk operation (API) | `BulkOperationService::bulkAssignToGroup()` | For each user in the bulk batch |

### Null group_id Guard

**User model** — A `saving` event hook prevents any user from being saved
without a `group_id`:
```php
static::booted(): void
{
    static::saving(function (User $user) {
        if (is_null($user->group_id)) {
            throw new \RuntimeException(
                'Every user must belong to a group. A group_id is required.'
            );
        }
    });
}
```
This fires on both `create` and `update` operations, covering **all** code
paths — controllers, services, tinker, and future code.

### What Was NOT Changed (By Design)

After discussion, the following Person 5 tasks from the original plan were
de-scoped because Lecturers do not need cross-group access:

| Task | Status | Reason |
|---|---|---|
| `canAccessGroup()` on User | **Not implemented** | Lecturers see only their own group, like students |
| `isLecturer()` helper | **Not implemented** | No special access tier needed |
| Update ForumController isolation checks | **Not needed** | Existing `group_id` check is correct |
| Update API TopicController/PostController | **Not needed** | Existing group isolation is correct |
| Update GroupBrowseController | **Not needed** | Group admins + users already scoped correctly |

Lecturers' only special treatment is the **rate-limit bypass** (Person 3),
which already works.

---

## Architecture Recap

1. **No General / default waiting pen** — onboarding is a binary gate.
2. **Hidden sysadmin group** — seeded by default; regular users cannot find it.
3. **SysAdmin sees everything** — all users, all groups, all content.
4. **Lecturers** — belong to their assigned group; see only its content;
   bypass rate limits.
5. **First student auto-promotion** — first Member-role user in a student
   group is automatically added as a Group Admin via the `group_admins` pivot.
6. **Students** — see only their own group.
7. **Every user has a group** — enforced at the model level.
