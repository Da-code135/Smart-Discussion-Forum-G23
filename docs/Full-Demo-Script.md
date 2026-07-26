# Full Demo Script — Smart Discussion Forum
# All Roles, All Modules, Web + Desktop

> This is the master navigation script for your presentation.
> It covers every role, every module, and what to say and show at each step.
> **"Behind the scenes" sections are exam-ready explanations** — read these if you want to understand the real mechanics, not just the surface-level flow.
>
> **How to use this script in the presentation:**
> - **Web demo** means the browser-based Laravel interface.
> - **Desktop demo** means the JavaFX app.
> - **Behind the scenes** means the shared backend logic, database, API, and services.
> - If a feature is only implemented on one interface, say that clearly instead of blending the two together.
> - Use the same speaking pattern for every feature:
>   - **What to show**: the clicks or screens.
>   - **What to say**: the short explanation for the audience.
>   - **Behind the scenes**: the real logic that makes it work.
> - Keep the explanation simple first, then add technical detail only if someone asks.

## Presentation Guide

Use this script as a speaking guide, not as something you read word for word.

When you present each module, explain it in this order:
1. Show the feature working.
2. Say what problem it solves.
3. Explain the web version.
4. Explain the desktop version if there is one.
5. Finish with the shared backend logic.

This order makes it easier for listeners to understand the feature before hearing the technical details.

For code-level explanation, jump to the **Backend Flow Appendix** near the end of this document. It shows the UI file, route, middleware, controller, service, and model path for the main requirements.

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
> "A new user does not become active immediately. They first read the platform rules and must agree before the account is created completely."

**Onboarding screen:**
1. Show the rules text scroll
2. Select a group from the dropdown
3. Check the agreement checkbox
4. Click **Agree**
5. Account is now active → redirected to the forum

**Behind the scenes — how registration works:**
- The browser sends a `POST` request to `/register`, and the API sends a `POST` request to `/api/v1/register`.
- `AuthController@register` checks the name, email, and password first.
- The new user is assigned the Member role and the General group.
- The password is hashed before it is saved, so the system never stores plain text passwords.
- After registration, the user is sent to the onboarding page to accept the rules.
- When the user agrees, the agreement is saved and the account becomes fully usable.

**Files to mention:**
- `app/Http/Controllers/Auth/RegisterController.php`
- `app/Http/Controllers/Api/AuthController.php`
- `app/Models/User.php`
- `routes/web.php`
- `routes/api.php`

**Database tables involved:**
```
users:        id, full_name, email, password (bcrypt hash), role_id, group_id, account_status
roles:        id, role_name
groups:       id, group_name
onboarding_agreements: id, user_id, ip_address, agreed_at, group_id
```

---

### Step 2: Logging in (Web)

**Navigate to:** `/login`

**What to show:**
1. Enter email and password
2. Click **Sign In**
3. Redirected to the home/dashboard

**Behind the scenes — the web login flow:**
-- The login form sends a `POST` request to `/login`.
-- The web app uses session login, so the browser keeps a session cookie after a successful sign-in.
-- Laravel checks the email and password, then creates the session if the credentials are correct.
-- The browser sends that session cookie on later requests, so the user stays logged in.
-- The API does not use browser sessions. It uses a Sanctum token instead.

**Simple distinction to say in the presentation:**
- **Web** remembers the user with a session cookie.
- **API/Desktop** remembers the user with a bearer token.

**Files to mention:**
- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/Api/AuthController.php`
- `app/Models/User.php`
- `bootstrap/app.php`

**The login controller also checks account status:**
- If `account_status === 'warned'` and there is an unacknowledged warning → returns 403 with `requires_warning_acknowledgement = true`. The user is redirected to acknowledge the warning before they can proceed.
- If `account_status === 'blacklisted'` → returns 403 with a message like "Your account is blacklisted until January 15, 2025."
- If account is active → creates a session, redirects to dashboard.

**Behind the scenes — rate limiting:**
- The login endpoint implements rate limiting. Each failed login increments a counter keyed by email + IP address. After 5 failed attempts, the user is locked out for 30 seconds. The counter uses Laravel's `RateLimiter` facade which stores counts in the cache (or database if configured). This prevents brute-force password guessing.

---

### Step 3: Logging in on the desktop app

**Open the desktop app.**

**What to show:**
1. App opens — shows login screen
2. Enter `superadmin@example.com` / `password`
3. App shows loading spinner (network call running in background)
4. Dashboard appears

**Behind the scenes — the desktop login flow:**
- The desktop app sends the same email and password to `/api/v1/login`, but as JSON.
- The server returns a token and user details.
- The desktop app saves that token, reuses it for later requests, and restores it when the app opens again.
- The login request runs in the background so the UI stays responsive.

**Simple distinction to say in the presentation:**
- The desktop app does not use cookies.
- It uses a saved API token instead.
- It also keeps the app responsive by doing network calls in the background.

**Why two different auth mechanisms (sessions vs tokens):**
```
Web browser:
  POST /login → Laravel creates session → sends session cookie
  Browser stores cookie automatically → sends it with every request
  Server looks up session in sessions table → identifies user

Desktop app:
  POST /api/v1/login → Laravel creates Sanctum token → returns token string
  App stores token in memory + Registry → sends as Authorization header
  Server looks up token in personal_access_tokens → identifies user
```
The web uses sessions because browsers handle cookies natively. The desktop uses tokens because there's no browser to manage cookies — we need explicit control over authentication headers.

---

### Step 4: Warning acknowledgement on login (both web and desktop)

**Explain (or demo if you have a warned account):**

> "If an account has been warned and the user hasn't acknowledged the warning, the login returns a special 403 error. On the web, the user is redirected to a warning acknowledgement page. On the desktop, a dialog box appears asking the user to acknowledge. Once they confirm, the app calls `POST /api/v1/warnings/acknowledge` and then retries the login automatically."

**Behind the scenes — the warned flow in detail:**
- When a warned user logs in, the system first checks whether there is an unacknowledged warning.
- If there is, the login is paused until the user acknowledges it.
- On the web, the user is sent to a warning acknowledgement page.
- On the desktop app, the user gets a confirmation dialog.
- After acknowledgement, the same login flow continues.

**Simple distinction to say in the presentation:**
- Warning acknowledgement is a safety step, not a separate account.
- The user must confirm the warning before the system lets them continue.

**Files to mention:**
- `app/Http/Controllers/Auth/WarningAcknowledgementController.php`
- `app/Http/Controllers/Api/WarningAcknowledgementController.php`
- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/Api/AuthController.php`

---

## MODULE 2 — Forum (Topics and Posts)

### For all roles — viewing the forum

**Web:** Click **Forum** in the sidebar
**Desktop:** Click **Forum** in the sidebar

**What to show:**
- List of topic cards
- Each card shows: type badge (Discussion or Question), title, author, date, reply count
- Answered questions show a green "✓ Answered" badge

**Behind the scenes — how topics are filtered by group:**
- `GET /api/v1/topics` loads the topic list for the current user.
- The system only shows topics from groups the user is allowed to see.
- System admins can see everything.
- Regular users only see their own group.
- Group admins and lecturers can see the groups they are responsible for.
- The response also includes useful extras like the author and reply count.

**Simple distinction to say in the presentation:**
- The topic feed is not global for everyone.
- It is filtered by group so users only see the discussions they are allowed to see.

**Files to mention:**
- `app/Http/Controllers/ForumController.php`
- `app/Http/Controllers/Api/TopicController.php`
- `app/Models/Topic.php`
- `app/Models/User.php`
- `routes/web.php`
- `routes/api.php`

---

### Creating a new topic

**Web:** Click **New Topic** button (top right of forum page)
**Desktop:** Click **New Topic** button in the Forum header

**What to show:**
1. Fill in: Title, select type (Discussion or Question), write description
2. Click **Create**
3. Topic appears in the feed

**Behind the scenes — the full creation flow:**
- `POST /api/v1/topics` creates the topic after validation.
- The topic is stored with the current user as the creator.
- It is placed inside the user’s group by default.
- After saving, the system tries to classify the topic into a category using keywords.

**Simple distinction to say in the presentation:**
- The user creates the topic once.
- The backend validates it, stores it, and classifies it automatically.

**How classification works in simple terms:**
  ```php
  // Simplified example of how classification works:
  $keywords = [
      'Django' => ['django', 'orm', 'mvc', 'python web'],
      'JavaScript' => ['react', 'vue', 'node', 'useState', 'useEffect'],
      'Database' => ['sql', 'mysql', 'query', 'join', 'index'],
      // ... more categories, plus admin-defined keyword_hints per category
  ];
  // Count keyword matches per category in the title + description
  foreach ($keywords as $category => $words) {
      $scores[$category] = countMatches($title.' '.$description, $words);
  }
  // Winner = category with the most matches (falls back to General)
  $best = array_key_of_max($scores);
  // Confidence = winner's matches ÷ total matches × 100
  $confidence = round($scores[$best] / array_sum($scores) * 100);
  $topic->update([
      'category_id' => $categoryId,
      'classification_confidence' => $confidence,
      'classification_needs_review' => $confidence < 40, // configurable threshold
  ]);
  ```
- The real implementation lives in `TopicClassificationService` and runs automatically when the topic is created. Admins can extend the keyword map by adding comma-separated `keyword_hints` to any category, and low-confidence topics are flagged for admin review (`classification_needs_review = true`).
- **Anti-flood protection:** The route has `throttle.posts:topic` middleware. This is a custom rate limiter that restricts topic creation to prevent spam. It's separate from the general API rate limiter (60 requests/minute). The limit is configured in `App\Http\Kernel` or via a custom `RateLimiter` definition.
- The response returns the created topic with its creator info: `{ "message": "Topic created successfully.", "data": { "topic": { ... } } }`.

**Files to mention:**
- `app/Http/Controllers/ForumController.php`
- `app/Http/Controllers/Api/TopicController.php`
- `app/Services/TopicClassificationService.php`
- `app/Models/Topic.php`
- `bootstrap/app.php`

---

### Opening a topic and replying

**Web:** Click any topic card → full topic page opens
**Desktop:** Click any topic card → `TopicDetailView` opens

**What to show:**
1. Open a topic.
2. Show the topic description and the replies underneath it.
3. Type a reply and submit it.
4. Show that the reply appears in the thread.

**Behind the scenes — how replies are loaded and filtered:**
- The system first checks that the user is allowed to view the topic’s group.
- When it loads replies, it hides removed posts.
- It also hides any post the current user was explicitly excluded from seeing.
- New replies are saved through the backend and are rate limited to prevent spam.

**Simple distinction to say in the presentation:**
- The page only shows replies the user is allowed to see.
- The reply box saves a new response to the backend and then refreshes the thread.

**Files to mention:**
- `app/Http/Controllers/ForumController.php`
- `app/Http/Controllers/Api/TopicController.php`
- `app/Http/Controllers/Api/PostController.php`
- `app/Models/Post.php`

---

### Marking a question as answered (Admin/Group Admin)

**Web:** Inside a topic, click **Toggle Answered**
**Desktop:** Inside `TopicDetailView`, click **Mark Answered** button

**Behind the scenes:**
- `POST /api/v1/topics/{id}/toggle-answered` → checks that the topic is of type 'question' (discussion topics cannot be marked as answered). Only the topic creator or an admin can toggle this.
- Flips `is_answered` boolean in `topics` table. When `true`, the topic shows a green "Answered" badge in the feed.
- The database column is a tiny integer (boolean). No additional logic — just `$topic->update(['is_answered' => !$topic->is_answered])`.

---

### Post visibility — excluding users (Requirement 3)

**Web:** On a post, click the **visibility** options → exclude a user
**Desktop:** `PostVisibilityView` from the topic detail

**What to show:**
1. Click to exclude a specific user from seeing a post
2. Log in as that user → the post is no longer visible

**Behind the scenes — how exclusion works at the database level:**
- The post author chooses which user should not see the post.
- The system saves that rule in a visibility table.
- The post itself is not deleted.
- Instead, every time the topic loads, the backend checks whether the current user was excluded.

**Simple distinction to say in the presentation:**
- Exclusion hides the post for one specific user.
- Everyone else can still see it normally.

**The actual filtering happens on READ, not on WRITE:** Every query that fetches posts applies a scope:
  ```php
  // Post model scope:
  public function scopeVisibleToUser($query, $userId) {
      return $query->whereDoesntHave('visibilityExclusions', function($q) use ($userId) {
          $q->where('excluded_user_id', $userId);
      });
  }
  ```
  This generates SQL like:
  ```sql
  SELECT * FROM posts
  WHERE NOT EXISTS (
      SELECT 1 FROM post_visibility
      WHERE post_visibility.post_id = posts.id
      AND post_visibility.excluded_user_id = 5
  )
  ```
- The excluded user sees all other posts normally — they just can't see the one they were excluded from. The exclusion is invisible to them; they don't even know the post exists.

**Files to mention:**
- `app/Http/Controllers/ForumController.php`
- `app/Http/Controllers/Api/PostVisibilityController.php`
- `app/Models/Post.php`
- `app/Models/PostVisibility.php`

---

### Reporting a post (Requirement 1 — flooding with irrelevant materials)

**Web:** On any post → click **Report**
**Desktop:** In `TopicDetailView` → click **Report** on a reply

**Behind the scenes — the reporting mechanism:**
- `POST /api/v1/reports` creates a polymorphic report record. The `reports` table has `reportable_type` (the model class name, e.g. `App\Models\Post`), `reportable_id` (the post's ID), `reported_by` (the reporting user's ID), and `reason` (a text field for the user to explain why they're reporting).
- **Polymorphic means the same reports table can handle reports on multiple content types** — topics, posts, messages, even users. The `reportable_type` and `reportable_id` together form a pointer to any row in any table.
- The reported post has `is_reported` set to `true`. This flag is what the Moderation panel queries to find items needing review.
- An admin in the Moderation panel can either **Remove Post** (soft-delete by setting `removed_at` timestamp) or **Ignore Report** (clears the `is_reported` flag). Removing a post hides it from all users via the `notRemoved()` scope.

**Files to mention:**
- `app/Http/Controllers/Api/ReportController.php`
- `app/Utilities/ReportUtility.php`
- `app/Http/Controllers/Admin/ModerationController.php`
- `app/Http/Controllers/Api/Admin/ModerationController.php`
- `app/Models/Post.php`

---

### Exporting a topic to PDF (Requirement 6)

**Web demo:** Inside a topic → click **Export PDF**

**What to say during the demo:**
> "This feature lets someone download a topic thread, including the topic and its replies, as a PDF. The browser interface handles this directly."

**Behind the scenes — PDF generation:**
- **Shared backend endpoint:** `GET /api/v1/topics/{id}/export/pdf` is the API route that generates the PDF file.
- The controller loads the topic, its creator, and all visible replies that have not been removed.
- It passes that data to a Blade export template such as `forum/export-pdf.blade.php`, which renders the topic thread as HTML.
- `barryvdh/laravel-dompdf` then converts the HTML into a PDF response.
- The response is not JSON. It is a file download with `Content-Type: application/pdf`.
- The export is also written to the audit trail with `AuditLogService@log('topic.exported', ...)`, and a row is added to the `export_logs` table (`topic_id`, `user_id`, `file_type`) so every export is traceable.

**Desktop app note:**
- The desktop app would need a separate file-download flow to use this feature.
- If you are presenting the current system, describe PDF export as a web feature backed by the same backend.
- If someone asks whether the desktop app also supports it, say that it would need a binary download and file-open step.

**Files to mention:**
- `app/Http/Controllers/ForumController.php`
- `app/Http/Controllers/Api/TopicController.php`
- `resources/views/forum/export-pdf.blade.php`
- `routes/web.php`
- `routes/api.php`

---

### Sharing a topic to social media (Requirement 12)

**Web demo:** Inside a topic → click **Share** dropdown → copy the link or share it to social media

**What to say during the demo:**
> "This feature generates a secure link that can be opened by anyone within the allowed time window."

**Behind the scenes — signed URLs:**
- **Shared backend endpoint:** `POST /api/v1/topics/{id}/share` generates a temporary signed URL with Laravel's `URL::temporarySignedRoute()`.
- The signed URL contains an expiry timestamp and a signature, for example: `https://forum.example.com/api/v1/topics/5/shared?expires=1712345678&signature=abc123...`
- The signature is an HMAC-SHA256 hash of the URL parameters using the application key (`APP_KEY`). If the link is changed after generation, Laravel rejects it with 403.
- The link expires after a configurable number of minutes. After that time, the backend refuses access even if the URL looks correct.
- Anyone with the link can view the topic without logging in because the shared-access route skips normal auth and checks only the signature.

**Desktop app note:**
- The desktop app can open the same signed link if needed.
- The important point is that the server, not the front end, decides whether the link is still valid.

**Files to mention:**
- `app/Http/Controllers/ForumController.php`
- `app/Http/Controllers/SharedTopicController.php`
- `app/Http/Controllers/Api/TopicController.php`
- `routes/web.php`
- `routes/api.php`

---

## MODULE 3 — Conversations / Messaging (Requirement 8)

### Viewing conversations

**Web:** Click **Messages** in sidebar
**Desktop:** Click **Messages** in sidebar

**What to show:**
- List of conversation cards.
- Each card shows who is in the chat, the last message, and the time.

**Behind the scenes — conversation scoping:**
- Users only see conversations from their own group.
- They also have to be a participant in the chat.
- The newest active chats appear first.

**Simple distinction to say in the presentation:**
- Conversations are private to the group and the participants.

**Files to mention:**
- `app/Http/Controllers/ConversationController.php`
- `app/Models/Conversation.php`
- `routes/api.php`

---

### Starting a new conversation

**Web:** Click **New conversation** → select Direct or Group → select participants → Start
**Desktop:** Click **New Conversation** → choose Direct/Group → select participants from checkboxes → Start

**Behind the scenes — the conversation creation logic:**
- The system supports two conversation types: direct chats and group chats.
- Direct chats are between two people.
- Group chats have three or more people.
- If a direct chat already exists, the backend reuses it instead of creating a duplicate.
- The system keeps conversations inside the same group.

**Simple distinction to say in the presentation:**
- The backend prevents duplicate direct chats and keeps chats inside the correct group.

**Files to mention:**
- `app/Http/Controllers/ConversationController.php`
- `app/Models/Conversation.php`
- `app/Models/User.php`

---

### Sending a message (Web — real-time)

**Web:** Open any conversation → type message → Send

**What to show:**
1. Open same conversation in two browser tabs (different users)
2. Send from one tab
3. Message appears instantly in the other tab

**Behind the scenes — real-time messaging with WebSockets:**
- The message is saved in the database.
- A broadcast event is fired after saving.
- The web app listens to that event through WebSockets.
- Only people in the conversation can subscribe.

**Simple distinction to say in the presentation:**
- The web gets instant chat updates through WebSockets.
- The message is still stored in the database first, so it is not just a temporary screen update.

**Files to mention:**
- `app/Http/Controllers/MessageController.php`
- `app/Services/MessageEventManager.php`
- `app/Events/MessageSent.php`
- `routes/channels.php`

**On the web frontend:** Laravel Echo (a JavaScript library) listens on the private channel:
  ```javascript
  Echo.private('conversation.' + conversationId)
      .listen('MessageSent', (message) => {
          // Append the new message to the chat UI
          messages.push(message);
      });
  ```
- When Echo receives the broadcast, it appends the message to the conversation view without any page refresh. This creates the real-time effect.
- **Important:** Broadcasting uses `->toOthers()` so the sender doesn't receive their own message twice (once from the HTTP response, once from the WebSocket).

---

### Sending a message (Desktop — with offline sync)

**Desktop:** Open any conversation → type message → Enter or Send button

**Behind the scenes — the SyncEngine and offline queue:**
- The desktop app uses the same message endpoint when the internet is available.
- If the connection fails, it stores the message locally first.
- A background sync process keeps trying to send saved messages again later.
- When the internet comes back, the app also pulls newer server data.

**Simple distinction to say in the presentation:**
- Web chat is live when online.
- Desktop chat can also work offline and sync later.
- That is the main difference between the two interfaces.

**Sync checkpoint and device identity in simple terms:**
- Each device remembers the last sync point so it only downloads new changes.
- The desktop also keeps a device ID so the server knows which device is syncing.

**Files to mention:**
- `app/Http/Controllers/SyncController.php`
- `app/Models/SyncCheckpoint.php`
- `app/Services/MessageEventManager.php`

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

**Behind the scenes — quiz creation lifecycle:**
- The lecturer first creates the quiz in draft form.
- Then they add questions and correct answers.
- When the quiz is published, it becomes visible to the correct student group.
- Publishing also triggers notifications.

**Simple distinction to say in the presentation:**
- A quiz is prepared first and published later.
- Students only see it after publication.

**Files to mention:**
- `app/Http/Controllers/Api/QuizController.php`
- `app/Http/Controllers/Api/QuestionController.php`
- `app/Http/Controllers/Api/AnswerController.php`
- `app/Events/QuizPublished.php`
- `app/Listeners/SendQuizAnnouncement.php`

---

### Student sees quiz announcement

**Web/Desktop:** Log in as Student → Quizzes → Upcoming tab

**What to show:**
1. Quiz card appears with status "Upcoming" and countdown
2. Click the quiz card → Announcement page
3. Shows: title, date, time, duration, instructions
4. If quiz hasn't started: countdown timer ticking
5. When time comes: **Join Quiz** button appears

**Behind the scenes — the announcement system:**
- The announcement page shows the quiz schedule before the quiz starts.
- The client uses the server time to count down.
- When the start time arrives, the join button appears.

**Simple distinction to say in the presentation:**
- The announcement is like a preview.
- The quiz itself only opens at the scheduled time.

**Files to mention:**
- `app/Http/Controllers/Api/StudentQuizController.php`
- `app/Http/Controllers/Api/QuizController.php`
- `app/Events/QuizWentLive.php`
- `app/Listeners/NotifyQuizLive.php`

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

**Behind the scenes — the quiz-taking mechanics:**

**Starting the attempt:**
- The backend creates one attempt record for the student.
- It only allows the attempt if the quiz is currently live.
- It also checks that the student belongs to the correct group or category.

**Saving answers:**
- Each answer is saved immediately after the student selects it.
- If the student changes the answer, the backend updates the saved response.
- This protects the student’s work if the app closes unexpectedly.

**Simple distinction to say in the presentation:**
- Answers are not only stored at the end.
- They are saved as the student works through the quiz.

**The timer:**
- The timer is shown on the client, but the server still controls the real deadline.
- When time runs out, the quiz auto-submits.
- The server rejects late work based on the recorded start time.

**Simple distinction to say in the presentation:**
- The visible timer is for user experience.
- The server is what really enforces the time limit.

**Submitting:**
- The backend marks the attempt as completed.
- It grades the answers.
- It stores the result and participation mark.

**Simple distinction to say in the presentation:**
- The result is calculated on the backend, not by the client.

**Files to mention:**
- `app/Http/Controllers/Api/StudentQuizController.php`
- `app/Http/Controllers/Api/GradeController.php`
- `app/Models/Quiz.php`
- `app/Models/QuizAttempt.php`

**Late joiners:**
- If a student joins after the quiz has started, they do NOT get extra time. The `completed_at` deadline is calculated from `started_at + duration_minutes`, regardless of when they joined. The server enforces this.

---

### Lecturer views results

**Web:** Quizzes → click a quiz → Results tab
**Desktop:** Quizzes → lecturer view → click a quiz → Results

**What to show:**
- Table: Student Name, Email, Score, Percentage, Participation Mark, Final Grade
- Summary stats at top: Average %, Highest %, Lowest %, Total Attempts

**Behind the scenes:**
- `GET /api/v1/lecturer/quizzes/{id}/grades` queries the `grades` table joined with `users` and `student_attempts` to return a complete picture of student performance.
- The participation mark is calculated separately from the score. It's based on whether the student attempted the quiz (not on how well they performed), to encourage participation. The formula is defined in the system configuration.

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

**Behind the scenes — how notifications are stored and delivered:**
- Notifications are saved with a type and some extra data.
- The same table can store topic replies, quiz announcements, warnings, and recommendations.
- The unread count comes from the notifications that do not have a read timestamp yet.

**Simple distinction to say in the presentation:**
- Notifications are one shared system for many different events.
- The content changes, but the storage pattern stays the same.

**Files to mention:**
- `app/Services/NotificationService.php`
- `app/Http/Controllers/NotificationController.php`
- `app/Http/Controllers/Api/NotificationController.php`
- `app/Http/Controllers/Api/QuizNotificationController.php`

---

## MODULE 6 — Recommendations (Requirement 11 — Machine Learning)

**Web:** Click **Recommendations** in sidebar
**Desktop:** Click **Recommendations** in sidebar

**What to show:**
- Cards of recommended topics with category badges, a "% match" relevance badge, and a reason line
- Click a card → opens the full topic

**What to say:**
> "The recommendation engine looks at which categories of topics a user has engaged with — topics they've posted in or replied to. It then finds unread topics in those same categories and recommends them, each with a relevance score showing how strongly it matches the user's interests and a reason like 'Based on similar topics you engaged with'. If the user has no engagement history, it falls back to the most popular topics, capped at 50% relevance with the reason 'Popular in your group'. Each recommendation is logged with its score so the same topic isn't recommended twice. The topic classification that feeds this uses keyword matching — for example, a topic with 'React' or 'useState' in the title gets classified as JavaScript — and every classification carries a confidence score, with low-confidence topics flagged for admin review."

**Behind the scenes — the recommendation algorithm (not true ML, but a rule-based system):**
- The system looks at the kinds of topics the user has already engaged with.
- It then recommends similar topics.
- If there is not enough history, it falls back to popular topics.

**Simple distinction to say in the presentation:**
- This is recommendation logic based on behavior, not a trained AI model.
- If someone asks, be honest and say it is rule-based classification and suggestion logic.

**Files to mention:**
- `app/Services/RecommendationService.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/Api/RecommendationController.php`
- `app/Services/TopicClassificationService.php`

**Stage 1 — Build interest profile:**
```php
// Look at all topics the user has posted in
$engagedTopics = Topic::whereIn('id', Post::where('user_id', $user->id)
    ->pluck('topic_id'))->get();

// Count how many engagements per category
$categoryCounts = $engagedTopics->groupBy('category_id')
    ->map->count();

// Sort by most engaged categories
$preferredCategories = $categoryCounts->sortDesc()->keys();
```

**Stage 2 — Find recommendable topics:**
```php
// Find topics in preferred categories that user hasn't engaged with
$recommendations = Topic::whereIn('category_id', $preferredCategories)
    ->whereNotIn('id', $alreadyRecommended)  // from recommendation_log
    ->whereNotIn('id', $alreadyEngaged)       // user hasn't posted in these
    ->orderBy('created_at', 'desc')
    ->limit($limit)
    ->get();
```

**Stage 3 — Fallback:**
```php
// If not enough recommendations, fill with popular topics
if ($recommendations->count() < $limit) {
    $popularTopics = Topic::withCount('posts')
        ->orderBy('posts_count', 'desc')
        ->whereNotIn('id', $alreadyRecommended)
        ->limit($limit - $recommendations->count())
        ->get();
    $recommendations = $recommendations->concat($popularTopics);
}
```
Popular fallbacks get a relevance score proportional to their reply count, capped at 50% (they can never outrank a personalised match), with the reason "Popular in your group".

**Stage 4 — Score and log recommendations:**
- Personalised picks get `relevance_score` = the share of the user's engagement that falls in the topic's category (0–100) and `recommendation_reason` = "Based on similar topics you engaged with".
- Each recommended topic is logged in `recommendation_log` with `user_id`, `topic_id`, and `relevance_score` so the same topic is never recommended twice to the same user.
- The score and reason are shown in the UI as a "% match" badge and an italic reason line, and returned by `GET /api/v1/recommendations`.

**Is this machine learning?** Technically, no — it's a rule-based recommendation system. It doesn't use neural networks or statistical models. However, it achieves the functional requirement (recommending relevant topics based on past engagement) without the complexity of training an ML model. The assessment rubric for Requirement 11 accepts this approach as "machine learning" in the broad sense of "adaptive content suggestion based on user behavior."

---

## MODULE 7 — Groups

### User-facing group view

**Web/Desktop:** Click **Groups** (for non-admin users)

**What to show:**
- Cards of groups the user belongs to
- Click a group → see group details, members list, topics in that group

**Behind the scenes — group isolation:**
- Group pages are also filtered by role.
- Admins can see more than normal users.
- Regular users only see the group they belong to.

**Simple distinction to say in the presentation:**
- Groups keep the forum organized and prevent users from seeing unrelated content.

**Files to mention:**
- `app/Http/Controllers/Api/GroupBrowseController.php`
- `app/Http/Controllers/Admin/GroupController.php`
- `app/Http/Controllers/Api/Admin/GroupController.php`
- `app/Models/Group.php`

---

## MODULE 8 — Admin Panel (System Administrator)

### Accessing the admin panel

**Web:** Admin section visible in sidebar after login as System Administrator
**Desktop:** Admin section appears in sidebar — Dashboard, Users, Groups, Moderation, Warnings, Blacklist, Audit Logs, IP Whitelist, System Config, Group Stats, Statistics

**Behind the scenes — admin middleware:**
- Admin routes are protected.
- Some actions are for System Administrators only.
- Group Administrators can access some admin tools, but not everything.

**Simple distinction to say in the presentation:**
- The app uses layered security, not one single check.

**Files to mention:**
- `bootstrap/app.php`
- `app/Http/Middleware/IsAdmin.php`
- `app/Http/Middleware/IsSystemAdmin.php`
- `app/Http/Middleware/IsGroupAdmin.php`

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

**Behind the scenes — how role changes work:**
- Role changes are checked by a policy before the controller runs:
  ```php
  public function changeRole(User $currentUser, User $targetUser): bool
  {
      return $currentUser->isSystemAdmin();
  }
  ```
- Only a System Administrator can change another user’s role.
- The change is logged for accountability.

**Simple distinction to say in the presentation:**
- This is not just a UI action.
- The backend checks permission first, then records the change.

**Files to mention:**
- `app/Http/Controllers/Admin/UserManagementController.php`
- `app/Http/Controllers/Api/Admin/AdminUserController.php`
- `app/Models/User.php`
- `app/Policies/UserPolicy.php`

---

### Warnings and Blacklist (Requirement 4 — inactivity warnings)

**Web/Desktop:** Click **Warnings** in admin sidebar

**What to show:**
1. Table of all issued warnings
2. Click **Issue Warning** → select user, enter reason, set response deadline
3. Show that after 3 warnings, auto-blacklist triggers

**Behind the scenes — the warning lifecycle:**
- `WarningService@issueWarning()` does three things:
  1. Creates a `Warning` record: `{ user_id, issued_by, reason, response_deadline, is_acknowledged: false }`
  2. Counts all warnings for this user: `Warning::where('user_id', $userId)->count()`
  3. If count >= 3: calls `autoBlacklist($user)` automatically — no admin intervention needed
- **Auto-blacklist** creates a `BlacklistRecord` with `expires_at = now() + config('forum.blacklist_duration_days') days` and sets `user.account_status = 'blacklisted'`.
- The blacklist duration is read from `SystemConfig::getValue('blacklist_duration_days')` — it's configurable from the System Config panel.
- **Lifting a blacklist** sets `blacklist_records.lifted_at = now()` and restores `user.account_status = 'active'`.

**Files to mention:**
- `app/Services/WarningService.php`
- `app/Http/Controllers/Admin/WarningController.php`
- `app/Http/Controllers/Admin/BlacklistController.php`
- `app/Http/Controllers/Api/Admin/WarningController.php`
- `app/Http/Controllers/Api/Admin/BlacklistController.php`
- `app/Http/Controllers/Auth/WarningAcknowledgementController.php`
- `app/Http/Controllers/Api/WarningAcknowledgementController.php`

---

### Moderation (Requirement 1 — handling irrelevant materials)

**Web/Desktop:** Click **Moderation** in admin sidebar

**What to show:**
1. List of reported posts awaiting review
2. **Remove Post** → post is hidden from all users
3. **Ignore Report** → clears the report, post remains visible

**Behind the scenes — moderation actions:**
- `POST /api/v1/admin/moderation/{id}/remove` sets `removed_at = now()` on the post. This is a **soft delete** — the record stays in the database but all queries apply the `notRemoved()` scope to exclude it.
- `POST /api/v1/admin/moderation/{id}/ignore` sets `posts.is_reported = false` without removing the post. The report is effectively dismissed.
- The moderator can also optionally send a notification to the post author explaining why their post was removed.

**Files to mention:**
- `app/Http/Controllers/Admin/ModerationController.php`
- `app/Http/Controllers/Api/Admin/ModerationController.php`
- `app/Models/Post.php`
- `app/Utilities/ReportUtility.php`

---

### System Configuration (Requirement 4 — configurable thresholds)

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

**Behind the scenes — how config values are used:**
- Values are stored in the `system_configs` table as key-value pairs: `{ key: 'inactivity_warning_days', value: '30' }`.
- At runtime, services read these values: `SystemConfig::getValue('inactivity_warning_days')`.
- A scheduled task (`artisan forum:check-inactivity`) runs daily. It queries for users whose `last_active_at` is older than `inactivity_warning_days` days and who have no active warnings, then issues a warning automatically.

**Files to mention:**
- `app/Http/Controllers/Admin/SystemConfigController.php`
- `app/Http/Controllers/Api/Admin/SystemConfigController.php`
- `app/Models/SystemConfig.php`
- `app/Http/Controllers/Admin/StatisticsController.php`
- `app/Http/Controllers/Admin/GroupStatisticsController.php`
- `app/Services/GroupStatisticsService.php`
- `app/Utilities/StatisticsUtility.php`

---

## Backend Flow Appendix

Use this section when someone asks, "What happens in the backend when I click that button?"

The snippets below are shortened on purpose. They show the flow you need to explain, not every single implementation detail.

### UI files to keep in mind

- Web login and registration start in [resources/views/auth/login.blade.php](resources/views/auth/login.blade.php) and [resources/views/auth/register.blade.php](resources/views/auth/register.blade.php).
- Topic creation starts in [resources/views/forum/create-topic.blade.php](resources/views/forum/create-topic.blade.php).
- Topic detail, export, share, exclusion, and reply actions start in [resources/views/forum/show.blade.php](resources/views/forum/show.blade.php).
- PDF output is rendered by [resources/views/forum/export-pdf.blade.php](resources/views/forum/export-pdf.blade.php).
- Notifications start in [resources/views/notifications/index.blade.php](resources/views/notifications/index.blade.php).

### 1) Authentication and registration

**UI flow:**
- The web form posts from [resources/views/auth/login.blade.php](resources/views/auth/login.blade.php) to the `login` route.
- The registration form posts from [resources/views/auth/register.blade.php](resources/views/auth/register.blade.php) to the registration route.

**Route layer:**
```php
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'authenticate']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/register', [RegisterController::class, 'showRegister'])->name('register');
Route::post('/register', [RegisterController::class, 'storeRegister'])->name('register.store');
```

**Controller flow:**
```php
public function authenticate(Request $request)
{
    $key = 'login-attempts:'.$request->input('email').'|'.$request->ip();
    $emailKey = 'login-attempts-email:'.$request->input('email');
    $seconds = max(RateLimiter::availableIn($key), RateLimiter::availableIn($emailKey));

    if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($emailKey, 5)) {
        throw ValidationException::withMessages([
            'email' => 'Too many login attempts. Try again in '.$seconds.' seconds.',
        ]);
    }

    $user = User::where('email', $request->input('email'))->first();

    if (! $user || ! Hash::check($request->input('password'), $user->password)) {
        RateLimiter::hit($key, 30);
        RateLimiter::hit($emailKey, 30);
        throw ValidationException::withMessages([
            'password' => 'These credentials do not match our records.',
        ]);
    }

    if ($user->account_status === 'blacklisted') {
        return redirect()->back();
    }

    Auth::login($user, $request->input('remember'));
    session()->regenerate();
    $user->update(['last_active_at' => now()]);
}
```

**How to explain it:**
- The view sends the form.
- The route sends it to the controller.
- The controller validates, rate-limits, checks the password, and blocks warned or blacklisted users.
- If the login succeeds, Laravel creates a session for the web app.

**Desktop equivalent:**
```php
public function login(Request $request)
{
    $user = User::where('email', $request->input('email'))->first();

    if (! $user || ! Hash::check($request->input('password'), $user->password)) {
        return response()->json(['message' => 'Invalid credentials.'], 401);
    }

    $token = $user->createToken('desktop-client')->plainTextToken;

    return response()->json([
        'message' => 'Login successful',
        'token' => $token,
        'user' => $this->formatUserResponse($user),
    ], 200);
}
```

**How to explain it:**
- The desktop app sends JSON instead of a browser form.
- The API returns a Sanctum token.
- The desktop stores that token and uses it for later requests.

### 2) Forum feed and topic creation

**UI flow:**
- The forum page is rendered from [resources/views/forum/index.blade.php](resources/views/forum/index.blade.php).
- The create topic form is rendered from [resources/views/forum/create-topic.blade.php](resources/views/forum/create-topic.blade.php).

**Route layer:**
```php
Route::get('/forum', [ForumController::class, 'index'])->name('forum.index');
Route::get('/forum/create', [ForumController::class, 'create'])->name('forum.create');
Route::post('/forum', [ForumController::class, 'store'])
    ->middleware('throttle.posts:topic')
    ->name('forum.store');
Route::get('/forum/{topic}', [ForumController::class, 'show'])->name('forum.show');
Route::post('/forum/{topic}/reply', [ForumController::class, 'replyStore'])
    ->middleware('throttle.posts:reply')
    ->name('forum.reply.store');
```

**Controller flow:**
```php
public function store(Request $request)
{
    $user = Auth::user();
    $validated = $request->validate([
        'title' => 'required|max:255',
        'description' => 'required|string|max:10000',
        'post_type' => 'sometimes|in:discussion,question',
    ]);

    $targetGroupId = $user->isSystemAdmin()
        ? $validated['group_id']
        : $user->group_id;

    Topic::create([
        'title' => $validated['title'],
        'description' => $validated['description'],
        'post_type' => $validated['post_type'] ?? 'discussion',
        'created_by' => $user->id,
        'group_id' => $targetGroupId,
        'status' => 'active',
    ]);
}
```

**How to explain it:**
- The view posts the topic.
- The controller validates the title, description, type, and group.
- The backend stores the topic in the current user’s group.
- The `throttle.posts` middleware prevents spam.

**Topic listing and reply loading:**
```php
$query = Topic::where('status', 'active')->with('creator');

if (! $user->isSystemAdmin()) {
    $query->whereIn('group_id', $user->accessibleGroupIds());
}

$posts = Post::where('topic_id', $topic->id)
    ->notRemoved()
    ->visibleToUser(Auth::id())
    ->with('user')
    ->orderBy('created_at', 'asc')
    ->paginate(20);
```

**How to explain it:**
- The backend does group filtering first.
- Then it removes deleted posts.
- Then it removes posts excluded from that user.
- That is why the UI only shows the allowed thread.

### 3) Post visibility, reports, and moderation

**UI flow:**
- Excluding a user starts in [resources/views/forum/show.blade.php](resources/views/forum/show.blade.php).
- Reporting a post also starts there.

**Route layer:**
```php
Route::post('/forum/post/{post}/visibility/exclude', [ForumController::class, 'excludeUser'])
    ->name('forum.visibility.exclude');
```

**Controller flow:**
```php
public function excludeUser(Request $request, Post $post)
{
    if ($post->user_id !== Auth::id()) {
        abort(403, 'Only the post author can exclude users.');
    }

    $userToExclude = User::findOrFail($request->user_id);

    if ($userToExclude->group_id !== Auth::user()->group_id) {
        abort(403, 'You can only exclude users in your own group.');
    }

    PostVisibility::create([
        'post_id' => $post->id,
        'excluded_user_id' => $request->user_id,
    ]);
}
```

**How to explain it:**
- The post author chooses a user to exclude.
- The system saves that exclusion in a pivot table.
- The post is not deleted.
- When the topic loads again, the hidden post is filtered out by the model scope.

**Model behavior to mention:**
```php
public function scopeVisibleToUser($query, $userId) {
    return $query->whereDoesntHave('visibilityExclusions', function($q) use ($userId) {
        $q->where('excluded_user_id', $userId);
    });
}
```

**Reports and moderation:**
```php
public function store(Request $request)
{
    // reportable_type + reportable_id + reason
}

public function removePost(...)
{
    // sets removed_at so the post disappears from normal views
}
```

**How to explain it:**
- Reporting marks content for review.
- Moderation either hides it or clears the report.
- The content stays in the database unless it is soft-deleted.

### 4) PDF export and share links

**UI flow:**
- Export PDF starts from [resources/views/forum/show.blade.php](resources/views/forum/show.blade.php).
- The PDF content is rendered by [resources/views/forum/export-pdf.blade.php](resources/views/forum/export-pdf.blade.php).

**Controller flow:**
```php
public function exportPDF(Topic $topic)
{
    $replies = Post::where('topic_id', $topic->id)
        ->notRemoved()
        ->visibleToUser(Auth::id())
        ->with('user')
        ->orderBy('created_at', 'asc')
        ->get();

    $pdf = Pdf::loadView('forum.export-pdf', [
        'topic' => $topic,
        'replies' => $replies,
        'exportedBy' => Auth::user(),
    ]);

    return $pdf->download('topic-'.$topic->id.'.pdf');
}
```

**How to explain it:**
- The backend builds the thread first.
- It passes the thread to a Blade PDF template.
- DomPDF converts the HTML to a PDF file.
- The browser downloads the file.

**Share link flow:**
```php
public function shareTopic(Request $request, Topic $topic)
{
    $signedUrl = URL::temporarySignedRoute('shared.topic.show', $expires, [
        'topic' => $topic->id,
        'signedUserId' => Auth::id(),
    ]);

    return back()->with('share_url', $signedUrl);
}
```

**How to explain it:**
- The server creates a time-limited signed link.
- Anyone with the link can open it until it expires.
- The signature is what protects the link from tampering.

### 5) Messages and offline sync

**Backend entry points:**
- The web and desktop chat flows both rely on conversation and message controllers.
- Offline sync is handled by [app/Http/Controllers/SyncController.php](app/Http/Controllers/SyncController.php).

**Sync pull flow:**
```php
public function pull(Request $request): JsonResponse
{
    $checkpoint = SyncCheckpoint::firstOrCreate(
        ['user_id' => $user->id, 'device_id' => $validated['device_id']],
        ['last_synced_at' => now()->subYear()],
    );

    $newMessages = Message::whereIn('conversation_id', $conversationIds)
        ->where('created_at', '>', $since)
        ->with('sender:id,full_name')
        ->orderBy('created_at')
        ->get();

    $checkpoint->update(['last_synced_at' => now()]);
}
```

**Sync push flow:**
```php
public function push(Request $request): JsonResponse
{
    foreach ($validated['messages'] as $msg) {
        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $msg['body'],
        ]);

        broadcast(new MessageSent($message))->toOthers();
    }
}
```

**How to explain it:**
- The desktop stores messages locally if it is offline.
- When the connection returns, it pushes the queued messages.
- It then pulls anything new from the server.
- The checkpoint tells the client what it already synced.

### 6) Quizzes

**UI flow:**
- Lecturer quiz creation starts in the quiz Blade or desktop screens.
- Student quiz viewing starts in the quiz announcement page.

**Publish flow:**
```php
public function publish(Request $request, Quiz $quiz)
{
    if ($quiz->questions()->count() === 0) {
        return response()->json([
            'success' => false,
            'message' => 'Cannot publish a quiz with no questions',
        ], 422);
    }

    $quiz->update(['published_at' => now()]);
    event(new QuizPublished($quiz));
}
```

**Student attempt flow:**
```php
public function start(Quiz $quiz)
{
    if (! $quiz->is_active) {
        return response()->json([
            'success' => false,
            'message' => 'Quiz has not started yet.',
        ], 403);
    }

    $attempt = StudentAttempt::create([
        'quiz_id' => $quiz->quiz_id,
        'student_id' => $user->id,
        'start_time' => now(),
    ]);
}
```

**How to explain it:**
- The lecturer publishes first.
- That triggers notifications and live status changes.
- The student can only start when the quiz is active and allowed for their group/role.
- The server stores the attempt start time, so the deadline is enforced by the backend.

### 7) Notifications, recommendations, groups, admin, warnings, and config

**Notifications:**
```php
public function sendQuizAnnouncement(User $user, string $quizTitle, Carbon $startTime, array $quizData = []): void
{
    $this->sendToUser(
        $user,
        'Quiz Announcement',
        "A new quiz '{$quizTitle}' is scheduled for {$startTime->format('M d, Y \\a\\t g:ia')}",
        'alert',
        $quizData,
    );
}
```

**Recommendations:**
```php
public function generateRecommendations(User $user, int $limit = 5)
{
    $userEngagedCategoryIds = Topic::whereIn('id', function ($q) use ($user) {
        $q->select('topic_id')->from('posts')->where('user_id', $user->id);
    })
    ->whereNotNull('category_id')
    ->pluck('category_id')
    ->unique()
    ->toArray();
}
```

**Warnings and blacklist:**
```php
public function issueWarning(User $user, User $admin, string $reason): Warning
{
    return DB::transaction(function () use ($user, $admin, $reason, $warningNumber) {
        $warning = Warning::create([
            'user_id' => $user->id,
            'warning_number' => $warningNumber,
            'reason' => $reason,
            'response_deadline' => now()->addDays(3),
            'created_by' => $admin->id,
        ]);

        if ($warningNumber >= 3) {
            $this->autoBlacklist($user, $admin);
        }

        return $warning;
    });
}
```

**System config:**
```php
SystemConfig::getValue('inactivity_warning_days');
```

**How to explain it:**
- Notifications are created by the backend for many different events.
- Recommendations are built from past user engagement.
- Warnings are transactional, and the third warning can auto-blacklist a user.
- System config stores the thresholds so the admin can change behavior without changing code.

### Quick speaking rule

When explaining any feature, use this pattern:
1. Point to the UI file.
2. Point to the route.
3. Mention the middleware if there is one.
4. Name the controller method.
5. Mention the service or model that enforces the rule.
6. End with the reason the rule exists.

## End-to-End Data Flow Summary

**Web request flow (e.g., creating a topic):**
```
Browser → POST /topics (with session cookie)
    → Laravel router → web middleware (sessions, CSRF)
    → TopicController@store
    → Validates input
    → Inserts into PostgreSQL `topics` table
    → TopicClassificationService classifies it
    → Returns redirect to topic page
```

**API request flow (desktop app creating a topic):**
```
Desktop App → POST /api/v1/topics (with Authorization: Bearer token)
    → Laravel router → api middleware (Sanctum auth, rate limit)
    → TopicController@store
    → Same validation, same insert
    → Returns JSON: { "message": "Topic created", "data": { "topic": {...} } }
    → Desktop app parses JSON and updates the UI
```

**Simple way to explain it:**
- Both interfaces talk to the same backend.
- The web sends normal form requests and shows page redirects.
- The desktop sends JSON requests and updates its own screens.
- The database is the shared source of truth.

---

## Recommended Demo Order (15-minute presentation)

| Time | What to show |
|---|---|
| 0:00 – 1:00 | Opening statement: "Two interfaces, one database. We built a Laravel web app AND a JavaFX desktop app that share the same backend." Show live URL and open the desktop app side by side. |
| 1:00 – 2:30 | Register a new user on the web, show onboarding agreement, log in. Then log in on the desktop with the same credentials. Show that the dashboard appears on both. |
| 2:30 – 4:00 | Forum: create a topic on the web, show it appearing in the desktop's topic list. Reply on the desktop, show the reply on the web. Demonstrate post visibility (exclude a user). |
| 4:00 – 5:30 | Conversations: start a direct message on the web, send a message, show it appearing on the desktop. Then disconnect the desktop from the internet, compose a message (offline), reconnect and show the message being synced. |
| 5:30 – 7:30 | Quizzes: log in as Lecturer on the web, create a quiz, add questions, publish it. Switch to a Student account on the desktop, show the announcement, take the quiz, submit. Show results on the lecturer's view. |
| 7:30 – 9:00 | Admin: log in as System Administrator on the desktop, show the admin dashboard. Demonstrate: search for a user, change their role, issue a warning. Show the warning appearing on the web when the user tries to log in. Show audit logs. |
| 9:00 – 10:00 | Recommendations: log in as a Student who has engaged with several topics. Show the Recommendations page with suggested topics. Click one and show it opens correctly. |
| 10:00 – 11:00 | Group statistics: show the admin Group Stats page. Recalculate statistics for a group. Show the numbers updating. |
| 11:00 – 12:00 | Export and share: export a topic to PDF on the web, then generate a share link and open it in incognito to show public access through a signed URL. |
| 12:00 – 13:00 | Profile: edit your name on the web, then show the updated account on the desktop. Change password if time allows. |
| 13:00 – 14:00 | Notifications: show the notifications panel on both interfaces, then mark one as read so the badge count changes. |
| 14:00 – 15:00 | Closing: "Same backend, two interfaces, one shared database. Questions?" |
