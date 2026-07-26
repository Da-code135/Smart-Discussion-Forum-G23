# Presentation Guide — Web Interface (Laravel)

> Use this document to prepare for your presentation. Read it the night before.
> The system is live at: https://smart-discussion-forum-g23.onrender.com

---

## Demo Credentials

| Role | Email | Password |
|---|---|---|
| System Administrator | superadmin@example.com | password |
| Lecturer | (create via admin panel) | (set when creating) |
| Student | (register via /register) | (set when registering) |

---

## Presentation Script — What to Show and Say

### Opening (1 minute)

> "We built the Smart Discussion Forum — a platform that manages group discussions, quizzes, messaging, and member monitoring. It has two interfaces: a web application built with Laravel, and a desktop application built in Java. Both connect to the same backend database, so data is shared in real time between them.
>
> Let me walk you through each requirement and show you where it's implemented."

---

## Requirement by Requirement

### Requirement 1 — Reporting irrelevant content

**What it does:** Any member can report a topic or post. Admins review reported content in the Moderation panel and can remove it or dismiss the report.

**How to demo:**
1. Log in as a student
2. Open any topic
3. Click the **Report** button on a post
4. Log out, log in as admin
5. Go to **Moderation** in the sidebar
6. Show the reported post with Remove / Ignore buttons

**How it works technically:**
- `POST /api/v1/reports` — creates a report (linked to a topic or post)
- `ReportController@store` saves it to the `reports` table
- `ModerationController@index` fetches posts where `is_reported = true`
- `ModerationController@removePost` soft-deletes the post
- `ModerationController@ignoreReport` clears the flag

**Database tables involved:** `reports`, `posts`, `topics`

---

### Requirement 2 — Marking questions as answered

**What it does:** Topics posted as "Questions" can be marked as Answered by the admin. The topic shows a green "Answered" badge so the original poster can easily find the response.

**How to demo:**
1. Create a new topic, select type **Question**
2. Log in as admin
3. Open the topic → click **Toggle Answered** button
4. Show the green "✓ Answered" badge on the topic card

**How it works technically:**
- `POST /api/v1/topics/{id}/toggle-answered` — flips `is_answered` boolean
- `TopicController@toggleAnswered` handles this
- The topic feed filters/badges based on `is_answered` field

**Database tables:** `topics` (column: `is_answered`)

---

### Requirement 3 — Post visibility / excluding people

**What it does:** When writing a reply, a member can exclude specific people from seeing it. The excluded person will not see that post in the topic.

**How to demo:**
1. Open a topic, submit a reply
2. On the reply, click **Visibility** (or show PostVisibilityView)
3. Exclude a specific user by ID
4. Log in as that user → show they cannot see the post

**How it works technically:**
- `POST /api/v1/posts/{id}/visibility/exclude` — adds user to exclusion list
- `DELETE /api/v1/posts/{id}/visibility/{userId}` — removes exclusion
- `PostVisibilityController` handles both
- When fetching posts, the backend filters out posts where the current user is excluded

**Database tables:** `post_visibility` (post_id, excluded_user_id)

---

### Requirement 4 — Inactivity warnings and automatic blacklisting

**What it does:** If a member hasn't been active for a configured number of days, they receive a warning. If they receive 3 warnings without responding, they are automatically blacklisted for a configured duration.

**How to demo:**
1. Log in as admin → go to **System Configuration**
2. Show the `inactivity_warning_days` and `blacklist_duration_days` settings
3. Go to **Warnings** → show the list of issued warnings
4. Go to **Blacklist** → show blacklisted users
5. Explain that the auto-blacklist triggers when a user gets 3 warnings

**How it works technically:**
- `WarningService@issueWarning()` — creates a warning record
- After each warning, `warningCount` is checked
- If `warningCount >= 3` → `WarningService@autoBlacklist()` is called automatically
- This creates a `blacklist_records` entry with `expires_at = now() + blacklist_duration_days`
- On login, `AuthController@login` checks `account_status` — blacklisted users get a 403 error

**Database tables:** `warnings` (warning_number, reason, response_deadline, is_acknowledged), `blacklist_records` (blacklisted_at, expires_at, lifted_at)

---

### Requirement 5 — Onboarding / platform rules agreement

**What it does:** When a new member registers, they must read and agree to the platform rules before their account is activated. If they decline, they are not registered.

**How to demo:**
1. Go to `/register`
2. Show the registration form — fill in details
3. Show the onboarding step — platform rules scroll
4. Click **Agree** to complete registration
5. Show that declining prevents account creation

**How it works technically:**
- `OnboardingController@agree` — records the agreement with timestamp and IP
- `OnboardingController@status` — checks if the user has agreed
- The `onboarding_agreements` table stores each agreement

**Database tables:** `onboarding_agreements` (user_id, agreed_at, ip_address, version)

---

### Requirement 6 — Topic detail view and PDF export

**What it does:** Any member can open a topic and see all its replies in one place. They can also export the full topic (question + all answers) as a PDF.

**How to demo:**
1. Open any topic from the forum
2. Show the full post and replies
3. Click **Export PDF** button
4. Show the downloaded PDF with the topic and all responses

**How it works technically:**
- `GET /api/v1/topics/{id}` — returns topic with all posts
- `GET /api/v1/topics/{id}/export/pdf` — generates PDF using `barryvdh/laravel-dompdf`
- `TopicController@exportPDF` builds the PDF from the topic data

**Database tables:** `topics`, `posts`

---

### Requirement 7 — Group statistics and admin dashboard

**What it does:** Administrators can see platform-wide statistics (total users, active users, warned, blacklisted) and per-group statistics (topics, posts, active members, unanswered questions).

**How to demo:**
1. Log in as admin
2. Go to **Admin Dashboard** — show stat cards
3. Go to **Group Statistics** — show per-group table
4. Click **View Stats** on a group
5. Click **Recalculate** to refresh live data
6. Go to **Platform Statistics** — show overall numbers

**How it works technically:**
- `DashboardController@index` — returns platform summary
- `GroupStatisticsController@index` — returns all groups with stats
- `GroupStatisticsController@recalculate` — triggers `StatisticsUtility@recalculate()`
- `StatisticsUtility` counts members, topics, posts, active users directly from database

**Database tables:** `statistics` (group_id, total_members, active_members_this_week, total_topics, total_posts, unanswered_questions, last_calculated_at)

---

### Requirement 8 — Real-time chat (web) and offline sync (desktop)

**What it does:** On the web, messages appear in real time using WebSockets (Laravel Reverb). On the desktop, if the user is offline, messages are queued and synced when they reconnect.

**How to demo (web side):**
1. Open two browser windows logged in as different users
2. Open the same conversation in both
3. Send a message in one window — it appears instantly in the other
4. Explain this uses Laravel Reverb (WebSocket server)

**How it works technically:**
- `MessageController@store` saves the message and fires `MessageSent` event
- Laravel Reverb broadcasts it to all users in the conversation channel
- The web frontend listens with Laravel Echo

**Database tables:** `conversations`, `messages`, `message_statuses`, `sync_checkpoints`

---

### Requirement 9 — Participation marks for discussion

**What it does:** Lecturers can configure quizzes to award participation marks. The system automatically calculates a participation mark based on the student's engagement (how many answers they submitted, time taken, etc.).

**How to demo:**
1. Log in as lecturer
2. Go to **Quizzes** → open a quiz
3. Show the **Participation criteria** setting
4. After students attempt, go to **Results** → show the Participation Mark column

**How it works technically:**
- `StudentQuizController@calculateParticipationMark()` — calculates the mark
- `GradeController@index` — returns grades with participation mark column
- The `grades` table stores `participation_mark` separately from `total_score`

**Database tables:** `grades` (total_score, max_score, percentage, participation_mark, final_grade)

---

### Requirement 10 — Quiz system (full flow)

**What it does:** Lecturers create quizzes with time, date, duration, and target group. Students see announcements before the quiz. At the scheduled time, the quiz appears. Timer counts down. Auto-submits on timeout. Results visible to all after the quiz ends.

**How to demo:**
1. Log in as lecturer → go to **Quizzes** → **Create Quiz**
2. Set title, date, time, duration, target group
3. Add questions (MCQ, True/False, Short Answer)
4. Click **Publish**
5. Log in as student → go to **Quizzes** → show the announcement card with countdown
6. Show the quiz interface (timer, questions, navigation palette)
7. Show the result after submission

**How it works technically:**
- `QuizController@store/publish` — creates and publishes the quiz
- `QuestionController/AnswerController` — CRUD for questions and answers
- `StudentQuizController@announcement` — returns quiz info before start
- `StudentQuizController@start` — creates a `StudentAttempt` record
- `StudentQuizController@saveAnswer` — saves each answer as student selects
- `StudentQuizController@submit` / `autoSubmit` — finalizes and grades
- `GradeController@myResult` — student views their result

**Database tables:** `quizzes`, `questions`, `answers`, `student_attempts`, `student_answers`, `grades`

---

### Requirement 11 — Topic classification and recommendations

**What it does:** When a topic is created, the system automatically classifies it into a category (Django, APIs, Database, JavaScript, CSS, General) based on keywords in the title and description. The recommendation engine then suggests relevant topics to users based on their past engagement.

**How to demo:**
1. Create a topic with title "How to use Django ORM"
2. Show it gets automatically classified as "Django"
3. Go to **Recommendations** — show personalised topic cards

**How it works technically:**
- `TopicClassificationService@classifyTopic()` — keyword matching against predefined category keywords
- Called automatically in `TopicController@store` after saving
- `RecommendationService@generateRecommendations()` — finds topics in categories the user has engaged with most
- Falls back to popular topics if not enough history
- Logs recommendations to prevent showing the same topic twice

**Database tables:** `topic_categories`, `topics` (category_id), `recommendation_logs`

---

### Requirement 12 — Share to social media

**What it does:** Any topic can be shared to social media platforms via a share link. The system generates a signed URL that gives public access to that topic without requiring login.

**How to demo:**
1. Open any topic
2. Click the **Share** dropdown
3. Show the options (copy link, share to WhatsApp, Twitter, etc.)
4. Copy the link and open it in an incognito window — shows the topic without login

**How it works technically:**
- `TopicController@share` — generates a signed URL using Laravel's `URL::signedRoute()`
- `TopicController@sharedAccess` — public endpoint, validates the signed URL
- The share dropdown on the frontend uses the Web Share API / direct social media links

**Database tables:** `topics` (no special table — uses signed URL)

---

## How the Database Connects to the UI

```
Browser/Desktop App
       │
       │  HTTP Request (with Bearer token)
       ▼
Laravel API (/api/v1/...)
       │
       │  Eloquent ORM queries
       ▼
  Database (PostgreSQL on Render / SQLite locally)
       │
       │  Returns JSON response
       ▼
Browser/Desktop App renders the UI
```

Every piece of data you see on screen made this round trip. The Bearer token (stored in a cookie on web, Windows Registry on desktop) tells the server who is making the request.

---

## How Authentication Works End to End

1. User fills in email + password on `/login`
2. `POST /api/v1/login` → `AuthController@login`
3. Laravel checks credentials against `users` table (bcrypt hash comparison)
4. Checks `account_status` — warned/blacklisted users get special 403 responses
5. On success: creates a Sanctum token → returns `{token, user}` JSON
6. Web: token stored in HTTP-only cookie + session
7. Desktop: token stored in Windows Registry via `TokenStorage`
8. Every subsequent request includes `Authorization: Bearer <token>` header
9. Laravel Sanctum middleware validates the token on every protected route

---

## Possible Questions You Will Be Asked (Web Interface)

**Q: How does the warning system work?**
A: When an admin issues a warning, `WarningService@issueWarning()` creates a record in the `warnings` table with a response deadline. The system counts warnings per user. If the count reaches 3, `autoBlacklist()` is called automatically, creating a `blacklist_records` entry. On next login, the `account_status` check in `AuthController@login` returns a 403 with message "blacklisted until [date]".

**Q: How does the real-time messaging work?**
A: We use Laravel Reverb, which is a WebSocket server. When a message is sent, `MessageController@store` saves it to the database and fires a `MessageSent` event. Reverb broadcasts this event to all connected clients subscribed to that conversation's channel. The frontend uses Laravel Echo to listen and update the UI without refreshing.

**Q: How is the recommendation system implemented?**
A: It uses keyword-based topic classification (`TopicClassificationService`) — not a machine learning model. Topics are classified into categories at creation time. The recommendation engine (`RecommendationService`) looks at which categories a user has engaged with (posted in, read) and recommends topics from those categories that they haven't seen yet. It falls back to globally popular topics if there's no engagement history.

**Q: What happens when a quiz timer runs out?**
A: The quiz page has a JavaScript countdown timer. When it reaches zero, it automatically calls `POST /api/v1/quizzes/{id}/auto-submit`. The `StudentQuizController@autoSubmit` method closes the attempt, grades it, and saves the result. The student cannot submit after this. The graded result is immediately available to the lecturer via `GradeController@index`.

**Q: How does post visibility exclusion work?**
A: When you exclude a user from a post, `PostVisibilityController@exclude` creates a record in `post_visibility` linking the post ID to the excluded user's ID. When any user fetches posts for a topic, the query includes a `whereDoesntHave` clause that filters out posts where the current user's ID is in the exclusion list for that post.

**Q: How is the database structured for quizzes?**
A: Quizzes have a one-to-many relationship with Questions, which have a one-to-many relationship with Answers. When a student starts a quiz, a `student_attempts` record is created. Each answer the student selects creates a `student_answers` record. On submission, `gradeQuiz()` compares `student_answers` to the correct `answers` and calculates a score, stored in `grades`.

**Q: How does onboarding work?**
A: After registration, the user is directed to `/onboarding`. They must scroll through the rules and check the agreement checkbox. Clicking Agree calls `POST /api/v1/onboarding/agree`, which creates an `onboarding_agreements` record with their user ID, IP address, and a timestamp. If they decline, no record is created and their account is not activated.

**Q: How is the system deployed?**
A: The Laravel backend is deployed on Render using Docker. We wrote a Dockerfile that installs PHP 8.4 with Apache, installs Composer dependencies, builds the frontend assets with Node.js, runs database migrations, and starts Apache. The database is PostgreSQL on Render's free tier. Auto-deploy is configured — every push to the `main` branch on GitHub automatically triggers a new deployment.

**Q: What is the role of the `sync_checkpoints` table?**
A: It stores the last sync timestamp per device per user. When the desktop app calls `GET /api/v1/sync/pull?device_id=...`, the server looks up the checkpoint for that device and returns only messages and conversations created/updated after that timestamp. After returning the data, it updates the checkpoint to now. This ensures each device only receives new data, not everything from the beginning.

**Q: Why PostgreSQL on Render instead of MySQL?**
A: Render's free database tier only supports PostgreSQL. MySQL is not available for free. Laravel supports both through its database abstraction layer (Eloquent ORM). We changed `DB_CONNECTION=pgsql` in the environment variables and fixed one MySQL-specific `DATE_FORMAT` query to use PostgreSQL's `TO_CHAR` equivalent. Everything else worked without changes.

---

## Evaluation Criteria — How We Address Each One

| Criteria | What to Show |
|---|---|
| **Interface** | Web app at live URL — all pages, responsive design, role-based UI |
| **Database** | Show ERD or explain table relationships: users→roles, topics→posts, quizzes→questions→answers, conversations→messages |
| **Functionality** | Walk through each of the 12 requirements above |
| **Deployment** | Live URL on Render, explain Dockerfile, auto-deploy from GitHub |
| **Variables** | Show `.env` structure, explain APP_KEY, DB_CONNECTION, SESSION_DRIVER |
| **Individual questions** | Use the Q&A section above |
