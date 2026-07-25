# Full Demo Script — Smart Discussion Forum
# All Roles, All Modules, Web + Desktop

> This is the master navigation script for your presentation.
> It covers every role, every module, and what to say and show at each step.
> The "Behind the scenes" sections explain the logic — use these when asked how something works.

---

## Roles in the System

| Role | What they can do |
|---|---|
| **System Administrator** | Everything — user management, group management, all admin panels, system config |
| **Group Administrator** | Moderation within their group, warnings, blacklist, group member management |
| **Lecturer** | Create and manage quizzes, view results, post topics, send messages |
| **Student** | Post topics, reply, take quizzes, send messages, view recommendations |
| **Member** | Basic access — post topics, reply, send messages (no quizzes) |

---

## MODULE 1 — Registration and Authentication

### Step 1: A new user registers (Web)

**Navigate to:** `https://smart-discussion-forum-g23.onrender.com/register`

**What to show:**
1. Fill in: Full Name, Email, Password, Confirm Password
2. Click **Register**
3. The app redirects to the **Onboarding** page

**Say:**
> "When a new user registers, they are not immediately active. They are directed to an onboarding screen where they must read and agree to the platform rules. This fulfils Requirement 5."

**Onboarding screen:**
1. Show the rules text scroll
2. Select a group from the dropdown
3. Check the agreement checkbox
4. Click **Agree**
5. Account is now active → redirected to the forum

**Behind the scenes:**
- `POST /api/v1/register` → `AuthController@register` creates the user with `account_status = active` and the default role (Member or Student based on configuration)
- `POST /api/v1/onboarding/agree` → `OnboardingController@agree` creates a record in `onboarding_agreements` with the user's ID, IP address, and timestamp
- Database: `users` table gets a new row, `onboarding_agreements` gets a new row linked to that user

---

### Step 2: Logging in (Web)

**Navigate to:** `/login`

**What to show:**
1. Enter email and password
2. Click **Sign In**
3. Redirected to the home/dashboard

**Behind the scenes:**
> "When you log in, the server checks your email and password against the `users` table using bcrypt hash comparison. If correct, it generates a Sanctum Bearer token — a long random string. This token is stored in a cookie in your browser. Every request you make after this includes the token in the Authorization header so the server knows who you are."

- `POST /api/v1/login` → `AuthController@login`
- Checks `account_status` — if `warned`, returns 403 with `requires_warning_acknowledgement: true`
- If `blacklisted`, returns 403 with "blacklisted until [date]"
- On success: creates token in `personal_access_tokens` table, returns `{token, user}`

---

### Step 3: Logging in on the desktop app

**Open the desktop app.**

**What to show:**
1. App opens — shows login screen
2. Enter `superadmin@example.com` / `password`
3. App shows loading spinner (network call running in background)
4. Dashboard appears

**Behind the scenes:**
> "On the desktop, login is identical — same API endpoint. The difference is the token isn't stored in a cookie. Instead, it's saved to the Windows Registry using Java's Preferences API. When you close and reopen the app, it reads the token from the Registry and validates it by calling `GET /api/v1/me`. If valid, login is skipped entirely."

- `TokenStorage.saveToken(token)` → writes to `HKCU\Software\JavaSoft\Prefs\com\yourforum`
- `App.java` on startup: `AuthManager.restoreSession()` → loads token → calls `/me` → if OK, go to dashboard

---

### Step 4: Warning acknowledgement on login (both web and desktop)

**Explain (or demo if you have a warned account):**

> "If an account has been warned and the user hasn't acknowledged the warning, the login returns a special 403 error. On the web, the user is redirected to a warning acknowledgement page. On the desktop, a dialog box appears asking the user to acknowledge. Once they confirm, the app calls `POST /api/v1/warnings/acknowledge` and then retries the login automatically."

---

## MODULE 2 — Forum (Topics and Posts)

### For all roles — viewing the forum

**Web:** Click **Forum** in the sidebar
**Desktop:** Click **Forum** in the sidebar

**What to show:**
- List of topic cards
- Each card shows: type badge (Discussion or Question), title, author, date, reply count
- Answered questions show a green "✓ Answered" badge

**Behind the scenes:**
- `GET /api/v1/topics` → `TopicController@index`
- Returns paginated topics filtered to the user's group
- System Administrators see all groups, everyone else sees only their group

---

### Creating a new topic

**Web:** Click **New Topic** button (top right of forum page)
**Desktop:** Click **New Topic** button in the Forum header

**What to show:**
1. Fill in: Title, select type (Discussion or Question), write description
2. Click **Create**
3. Topic appears in the feed

**Behind the scenes:**
> "When a topic is created, two things happen automatically. First, it's saved to the `topics` table with the user's group ID so it only appears to their group. Second, `TopicClassificationService@classifyTopic()` runs immediately — it scans the title and description for keywords and assigns a category. For example, a topic mentioning 'Django' or 'ORM' gets classified as Django. This powers the recommendation engine later."

- `POST /api/v1/topics` → `TopicController@store`
- Calls `TopicClassificationService@classifyTopic($topic)` → updates `topics.category_id`
- Anti-flood middleware: `throttle.posts:topic` prevents submitting multiple topics too quickly
- Database: `topics` table (title, description, post_type, group_id, category_id, is_answered, is_pinned)

---

### Opening a topic and replying

**Web:** Click any topic card → full topic page opens
**Desktop:** Click any topic card → `TopicDetailView` opens

**What to show:**
1. Full topic description at the top
2. All replies below in a thread
3. Type a reply in the composer → Submit
4. Reply appears immediately

**Behind the scenes:**
- `GET /api/v1/topics/{id}` → returns topic with post count
- `GET /api/v1/topics/{id}/posts` → returns all replies (paginated)
- `POST /api/v1/topics/{id}/posts` → `PostController@store` creates the reply
- Anti-flood middleware: `throttle.posts:reply` prevents spam replies
- Database: `posts` table (topic_id, user_id, body, is_reported)

---

### Marking a question as answered (Admin/Group Admin)

**Web:** Inside a topic, click **Toggle Answered**
**Desktop:** Inside `TopicDetailView`, click **Mark Answered** button

**Behind the scenes:**
- `POST /api/v1/topics/{id}/toggle-answered` → `TopicController@toggleAnswered`
- Flips `is_answered` boolean in `topics` table
- Topic card now shows green "✓ Answered" badge in the feed

---

### Post visibility — excluding users (Requirement 3)

**Web:** On a post, click the **visibility** options → exclude a user
**Desktop:** `PostVisibilityView` from the topic detail

**What to show:**
1. Click to exclude a specific user from seeing a post
2. Log in as that user → the post is no longer visible

**Behind the scenes:**
> "This works through a filter on every post query. When the backend fetches posts for a topic, it excludes any post where the current user's ID appears in the `post_visibility` table for that post. The excluded user sees all other posts normally — they just can't see the one they were excluded from."

- `POST /api/v1/posts/{id}/visibility/exclude` → `PostVisibilityController@exclude`
- Creates record in `post_visibility` (post_id, excluded_user_id)
- All post queries include: `whereDoesntHave('excludedUsers', fn($q) => $q->where('user_id', auth()->id()))`

---

### Reporting a post (Requirement 1)

**Web:** On any post → click **Report**
**Desktop:** In `TopicDetailView` → click **Report** on a reply

**Behind the scenes:**
- `POST /api/v1/reports` → `ReportController@store`
- Creates record in `reports` (reportable_type, reportable_id, reported_by, reason)
- Sets `posts.is_reported = true`
- Admin sees it in Moderation panel

---

### Exporting a topic to PDF (Requirement 6)

**Web only:** Inside a topic → click **Export PDF**

**Behind the scenes:**
- `GET /api/v1/topics/{id}/export/pdf` → `TopicController@exportPDF`
- Uses `barryvdh/laravel-dompdf` to render the topic and all posts as a PDF
- Returns the PDF as a file download

---

### Sharing a topic (Requirement 12)

**Web:** Inside a topic → click **Share** dropdown → copy link / share to social media

**Behind the scenes:**
- `POST /api/v1/topics/{id}/share` → `TopicController@share`
- Laravel's `URL::signedRoute()` generates a tamper-proof URL with an expiry signature
- Anyone with the link can view the topic at `/api/v1/topics/{id}/shared` without logging in
- The signed URL contains a hash — if modified, Laravel rejects it

---

## MODULE 3 — Conversations / Messaging (Requirement 8)

### Viewing conversations

**Web:** Click **Messages** in sidebar
**Desktop:** Click **Messages** in sidebar

**What to show:**
- List of conversation cards (direct messages and group chats)
- Each card shows: name/participants, last message preview, timestamp

---

### Starting a new conversation

**Web:** Click **New conversation** → select Direct or Group → select participants → Start
**Desktop:** Click **New Conversation** → choose Direct/Group → select participants from checkboxes → Start

**Behind the scenes:**
- `POST /api/v1/conversations` → `ConversationController@store`
- Creates `conversations` record (type: direct/group, name if group)
- Creates `conversation_participants` records linking each participant
- Database: `conversations` (type, name, group_id), `conversation_participants`

---

### Sending a message (Web — real-time)

**Web:** Open any conversation → type message → Send

**What to show:**
1. Open same conversation in two browser tabs (different users)
2. Send from one tab
3. Message appears instantly in the other tab

**Behind the scenes:**
> "This is real-time messaging using WebSockets. When a message is sent, `MessageController@store` saves it to the database and fires a `MessageSent` event. Laravel Reverb — our WebSocket server — broadcasts this event to all users currently subscribed to that conversation's private channel. The browser receives it via Laravel Echo and appends the message to the UI without any page refresh."

- `POST /api/v1/conversations/{id}/messages` → `MessageController@store`
- Saves to `messages` table
- Fires `MessageSent` event → Reverb broadcasts on `conversation.{id}` channel
- Frontend: `Echo.private('conversation.{id}').listen('MessageSent', callback)`

---

### Sending a message (Desktop — with offline sync)

**Desktop:** Open any conversation → type message → Enter or Send button

**Behind the scenes:**
> "On the desktop, messages go through the same API. But if the device is offline, the message is queued locally in the Windows Registry. Every 30 seconds, the `SyncEngine` background service checks — if there are queued messages and the connection is restored, it calls `POST /api/v1/sync/push` to deliver them. It also calls `GET /api/v1/sync/pull?device_id=...` to fetch any new messages that arrived while offline."

- `SyncEngine@queueOfflineMessage()` → stores in Windows Registry
- `SyncEngine@pushOfflineMessages()` → sends queued messages to `/sync/push`
- `SyncEngine@pullNewData()` → fetches new data since last `SyncCheckpoint`
- `SyncCheckpoints` table: per device, per user, stores `last_synced_at`

---

## MODULE 4 — Quizzes (Requirement 10)

### Lecturer creates a quiz

**Web:** Log in as Lecturer → Quizzes → Create Quiz

**What to show:**
1. Fill in: Title, Description, Target group (which students)
2. Set: Date, Start Time, Duration (in minutes)
3. Click **Create Quiz** — quiz is in Draft state
4. Add questions: click **Add Question** → choose MCQ/True-False/Short Answer
5. For MCQ: add answer options, mark the correct one
6. Set points per question
7. Click **Publish** — quiz becomes visible to students

**Behind the scenes:**
- `POST /api/v1/quizzes` → `QuizController@store` creates quiz record
- `POST /api/v1/quizzes/{id}/questions` → `QuestionController@store` adds questions
- `POST /api/v1/quizzes/{id}/questions/{q}/answers` (or via batch) → `AnswerController@store`
- `POST /api/v1/quizzes/{id}/publish` → sets `published_at = now()`
- Database: `quizzes` (title, duration_minutes, scheduled_date, start_time, target_category, published_at), `questions` (type, question_text, points), `answers` (answer_text, is_correct)

---

### Student sees quiz announcement

**Web/Desktop:** Log in as Student → Quizzes → Upcoming tab

**What to show:**
1. Quiz card appears with status "Upcoming" and countdown
2. Click the quiz card → Announcement page
3. Shows: title, date, time, duration, instructions
4. If quiz hasn't started: countdown timer ticking
5. When time comes: **Join Quiz** button appears

**Behind the scenes:**
- `GET /api/v1/quizzes/{id}/announcement` → `StudentQuizController@announcement`
- Returns quiz metadata + current server time
- Frontend calculates time remaining from `scheduled_date + start_time - now()`

---

### Student takes a quiz

**Web/Desktop:** Click **Join Quiz** on announcement page

**What to show:**
1. Quiz screen opens (full screen overlay on web, full content area on desktop)
2. Timer counting down in top right
3. Question 1 shows with answer options
4. Select an answer → it auto-saves immediately
5. Click **Next** → next question
6. Question palette shows: grey (unanswered), green (answered), blue (current)
7. Submit before timer runs out
8. Confirmation dialog: "You have X unanswered questions. Submit anyway?"
9. Click Submit → result screen

**Behind the scenes:**
> "When the student clicks Join, a `StudentAttempt` record is created with status `in_progress`. Every time they select an answer, `POST /api/v1/quizzes/{id}/answer` is called immediately — answers are saved to the server as they go. This means if the connection drops or browser closes, their answers are not lost. When the timer hits zero, the frontend calls `POST /api/v1/quizzes/{id}/auto-submit`. The backend grades the quiz by comparing `student_answers` to the correct `answers` and calculates a percentage and participation mark."

- `POST /api/v1/quizzes/{id}/attempt` → creates `StudentAttempt`
- `POST /api/v1/quizzes/{id}/answer` → saves each `StudentAnswer` immediately
- `POST /api/v1/quizzes/{id}/submit` or `auto-submit` → `StudentQuizController@submit`
  - Scores all answers
  - Calculates participation mark
  - Creates `Grade` record
- Database: `student_attempts` (quiz_id, student_id, status, started_at), `student_answers` (attempt_id, question_id, answer_id or answer_text), `grades` (total_score, max_score, percentage, participation_mark)

---

### Lecturer views results

**Web:** Quizzes → click a quiz → Results tab
**Desktop:** Quizzes → lecturer view → click a quiz → Results

**What to show:**
- Table: Student Name, Email, Score, Percentage, Participation Mark, Final Grade
- Summary stats at top: Average %, Highest %, Lowest %, Total Attempts

**Behind the scenes:**
- `GET /api/v1/lecturer/quizzes/{id}/grades` → `GradeController@index`
- Returns all grades for that quiz with student details

---

## MODULE 5 — Notifications

### Viewing notifications

**Web:** Click the bell icon in the navbar (shows unread count badge)
**Desktop:** Click **Notifications** in sidebar

**What to show:**
- List of notifications with type icons
- Unread notifications highlighted
- **Mark as read** button per notification
- **Mark all as read** button

**Behind the scenes:**
- `GET /api/v1/me/notifications` → `NotificationController@index`
- `GET /api/v1/me/notifications/unread-count` → returns the badge count
- `POST /api/v1/notifications/{id}/read` → marks one as read
- `POST /api/v1/notifications/read-all` → marks all as read
- Notifications are created by the backend when: quiz is announced, warning is issued, recommendation is generated

---

## MODULE 6 — Recommendations (Requirement 11)

**Web:** Click **Recommendations** in sidebar
**Desktop:** Click **Recommendations** in sidebar

**What to show:**
- Cards of recommended topics with category badges
- Click a card → opens the full topic

**What to say:**
> "The recommendation engine looks at which categories of topics a user has engaged with — topics they've posted in or replied to. It then finds unread topics in those same categories and recommends them. If the user has no engagement history, it falls back to the most popular topics globally. Each recommendation is logged so the same topic isn't recommended twice. The topic classification that feeds this uses keyword matching — for example, a topic with 'React' or 'useState' in the title gets classified as JavaScript."

**Behind the scenes:**
- `GET /api/v1/recommendations` → `RecommendationController@index`
- Calls `RecommendationService@generateRecommendations($user)`
- Looks at `topics` the user has `posts` in → gets their `category_id` distribution
- Finds topics in those categories where user has no post
- Falls back to `getPopularTopics()` if insufficient personal history
- Logs to `recommendation_logs` to avoid repeats
- `TopicClassificationService@classifyTopic()` uses keyword arrays per category

---

## MODULE 7 — Groups

### User-facing group view

**Web/Desktop:** Click **Groups** (for non-admin users)

**What to show:**
- Cards of groups the user belongs to
- Click a group → see group details, members list, topics in that group

**Behind the scenes:**
- `GET /api/v1/groups` → `GroupBrowseController@index` returns only groups the user belongs to
- `GET /api/v1/groups/{id}/members` → returns member list
- `GET /api/v1/groups/{id}/topics` → returns topics scoped to that group

---

## MODULE 8 — Admin Panel (System Administrator)

### Accessing the admin panel

**Web:** Admin section visible in sidebar after login as System Administrator
**Desktop:** Admin section appears in sidebar — Dashboard, Users, Groups, Moderation, Warnings, Blacklist, Audit Logs, IP Whitelist, System Config, Group Stats, Statistics

---

### User Management

**Web/Desktop:** Click **Users** in admin sidebar

**What to show:**
1. Table of all users with columns: ID, Name, Email, Role, Status, Group, Last Active
2. Search by name/email
3. Filter by status (active/warned/blacklisted)
4. Filter by role
5. Per-row actions: **View** (full profile), **Password** (reset), **Blacklist**, **Lift Blacklist**, **Change Role**
6. **Create user** button (top right, system admin only)

**Demonstrate:**
- Search for a user → show results filter
- Click **View** on a user → show full profile with warning history and blacklist history
- Click **Change Role** → select new role → confirm → row updates

**Behind the scenes:**
- `GET /api/v1/admin/users?search=...&account_status=...&role=...` → `AdminUserController@index`
- `POST /api/v1/admin/users/{id}/change-role` → `AdminUserController@changeRole`
- `POST /api/v1/admin/users/{id}/blacklist` → `AdminUserController@blacklist` → calls `WarningService`
- `POST /api/v1/admin/users/{id}/lift-blacklist` → `AdminUserController@liftBlacklist`
- Role changes are logged to `audit_logs` automatically

---

### Warnings (Requirement 4)

**Web/Desktop:** Click **Warnings** in admin sidebar

**What to show:**
1. Table of all issued warnings
2. Click **Issue Warning** → select user, enter reason, set response deadline
3. Show that after 3 warnings, auto-blacklist triggers

**Behind the scenes:**
> "Every warning increments the user's warning count. `WarningService@issueWarning()` creates a `warnings` record and then checks the total count. If it reaches 3, `autoBlacklist()` is called automatically — no admin action needed. This creates a `blacklist_records` entry with `expires_at = now() + configured_days`. The user's `account_status` is set to `blacklisted`. On their next login attempt, the server returns a 403 error."

- `POST /api/v1/admin/users/{id}/warnings` → `WarningController@store` → `WarningService@issueWarning()`
- `WarningService@issueWarning()`:
  1. Creates `Warning` record
  2. Counts total warnings: `Warning::where('user_id', $userId)->count()`
  3. If count >= 3: calls `autoBlacklist($user)`
  4. `autoBlacklist()` creates `BlacklistRecord`, sets `user.account_status = blacklisted`

---

### Blacklist Management

**Web/Desktop:** Click **Blacklist** in admin sidebar

**What to show:**
1. Table of all blacklisted users with reason, start date, expiry date
2. **Lift Blacklist** button per row
3. Show lifting a blacklist → user's status returns to active

**Behind the scenes:**
- `GET /api/v1/admin/blacklist-records` → `BlacklistController@index`
- `POST /api/v1/admin/blacklist-records/{id}/lift` → `BlacklistController@lift`
- Sets `blacklist_records.lifted_at = now()`, sets `user.account_status = active`

---

### Moderation

**Web/Desktop:** Click **Moderation** in admin sidebar

**What to show:**
1. List of reported posts awaiting review
2. **Remove Post** → post is hidden from all users
3. **Ignore Report** → clears the report, post remains visible

**Behind the scenes:**
- `GET /api/v1/admin/moderation` → `ModerationController@index` returns posts where `is_reported = true`
- `POST /api/v1/admin/moderation/{id}/remove` → soft-deletes the post
- `POST /api/v1/admin/moderation/{id}/ignore` → clears `is_reported` flag

---

### System Configuration (Requirement 4)

**Web/Desktop:** Click **System Config** in admin sidebar

**What to show:**
Form with settings:
- Max Login Attempts / Lockout Duration
- Inactivity Warning Days (days before first warning)
- Warning Response Days (days to respond before escalation)
- Blacklist Duration Days
- Quiz late join toggle

**What to say:**
> "All the thresholds for the warning and blacklist system are configurable here. The admin doesn't need to change code — they just update these values and the system adjusts. For example, setting `inactivity_warning_days` to 30 means users get a warning after 30 days of no activity."

**Behind the scenes:**
- `GET /api/v1/admin/system-config` → returns all config key-value pairs from `system_configs` table
- `PUT /api/v1/admin/system-config` → `SystemConfigController@update` updates each key
- `WarningService` reads these values at runtime: `SystemConfig::getValue('blacklist_duration_days')`

---

### Audit Logs

**Web/Desktop:** Click **Audit Logs** in admin sidebar

**What to show:**
1. Table: Timestamp, User, Action, Description, IP Address
2. Filter by action type (dropdown)
3. Filter by date range
4. Click **Details** on a log entry → see old and new values
5. Export as CSV or JSON (web only)

**Behind the scenes:**
> "Every admin action is automatically logged. When an admin changes a user's role, the `AuditLogService` records: who did it, what they did, the IP address, and the old and new values. This creates a complete audit trail — you can see exactly what was changed, when, and by whom."

- `AuditLogService@log($user, $action, $description, $oldValues, $newValues)` called automatically
- Stores in `audit_logs` (user_id, action, description, old_values, new_values, ip_address, user_agent)

---

### Group Management (Admin)

**Web/Desktop:** Click **Groups** in admin sidebar (as admin)

**What to show:**
1. Table: Group Name, Description, Members count, Created By, Date, Actions
2. Search and sort controls
3. **Create Group** → dialog/form
4. **Members** button → view/manage members
5. **Edit** button (system admin) → rename, change description
6. **Delete** button (system admin, not General) → soft-deletes, members reassigned to General
7. **Deleted Groups** button → shows soft-deleted groups with Restore option

**Behind the scenes:**
- `GET /api/v1/admin/groups` → `AdminGroupController@index`
- `DELETE /api/v1/admin/groups/{id}` → soft-delete via `SoftDeletes` trait, users reassigned to General group
- `POST /api/v1/admin/groups/{id}/restore` → restores the group
- Database uses `deleted_at` column (soft-delete pattern) — deleted groups are not permanently removed

---

### Group Statistics and Platform Statistics (Requirement 7)

**Web/Desktop:** Click **Group Stats** → table of all groups with stats → **View Stats** per group

**What to show:**
1. Group stats table: Members, Topics, Posts, Active (30d), Unanswered Questions
2. **View Stats** → detailed stat cards for that group
3. **Recalculate** → refreshes live data from database
4. Platform Statistics → overall platform numbers

**Behind the scenes:**
- `StatisticsUtility@recalculate($groupId)` runs live SQL queries:
  - `User::where('group_id', $groupId)->count()` → total members
  - `User::where('group_id')->where('last_active_at', '>=', now()->subDays(30))->count()` → active
  - `Topic::where('group_id')->count()` → total topics
  - Results stored in `statistics` table for fast retrieval
- `GroupStatisticsController@index` → returns cached statistics
- `GroupStatisticsController@recalculate` → triggers fresh calculation

---

### IP Whitelist (System Admin only)

**Web/Desktop:** Click **IP Whitelist** in admin sidebar

**What to show:**
1. List of whitelisted IP addresses
2. Add a new IP → only that IP can access the admin panel
3. Activate/Deactivate without deleting

**Behind the scenes:**
- `IpWhitelist` middleware checks if the requesting IP is in the `admin_ip_whitelists` table (if feature is enabled in system config)
- If IP not whitelisted and feature is on → 403 Forbidden

---

## MODULE 9 — Profile Management

### Editing profile

**Web:** Click **Profile** in sidebar → edit name, email → Save
**Desktop:** Click **Profile** in sidebar → two-column layout → edit → Save Changes

**What to show:**
1. Current name, email, role badge, group badge, status badge
2. Change name → Save → confirmation
3. Click **Change Password** → current password, new password, confirm → Save

**Behind the scenes:**
- `POST /api/v1/profile` → `ProfileController@update`
- `POST /api/v1/password/change` → `PasswordController@change` — verifies current password with `Hash::check()` before updating

---

## End-to-End Flow Summary (say this to close your demo)

> "To summarise the full flow: A user registers and agrees to platform rules. Their data is stored in the `users` table. They log in — the server generates a token. Every request they make includes that token so the server knows who they are.
>
> On the web, everything is real-time — messages arrive via WebSocket, the UI updates without refreshing. On the desktop, the same API is used — but a background sync engine runs every 30 seconds to push offline messages and pull new ones.
>
> All data — topics, posts, quizzes, grades, messages — lives in the same PostgreSQL database on Render. Whether you use the web or the desktop, you're reading and writing to the same data.
>
> Admins have full visibility through the admin panel — they can manage users, issue warnings, configure the system, view audit logs, and see statistics for every group."

---

## Recommended Demo Order (15-minute presentation)

| Time | What to show |
|---|---|
| 0:00 – 1:00 | Opening statement, show live URL, explain two interfaces |
| 1:00 – 2:30 | Register a new user, show onboarding, log in |
| 2:30 – 4:00 | Forum: create topic, classify it, reply, mark answered, report |
| 4:00 – 5:30 | Conversations: create chat, send messages (web real-time demo) |
| 5:30 – 7:00 | Quizzes: lecturer creates quiz, student takes it, timer, auto-submit |
| 7:00 – 8:00 | Recommendations: show classified topics, recommendation cards |
| 8:00 – 9:30 | Admin: user management, warnings, blacklist, system config |
| 9:30 – 10:30 | Admin: group stats, recalculate, view detail |
| 10:30 – 12:00 | Desktop app: show same data, chat bubbles, quiz attempt, sync |
| 12:00 – 13:00 | Deployment: show Render dashboard, explain Dockerfile, auto-deploy |
| 13:00 – 15:00 | Q&A buffer / individual questions |
