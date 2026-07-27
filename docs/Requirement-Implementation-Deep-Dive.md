# Smart Discussion Forum: Requirement Implementation Deep Dive

This document explains **how the assignment requirements were implemented in code** across both projects:

- **Web + backend:** `smart-discussion-forum`
- **Desktop app:** `smart-discussion-forum-desktop`

This version is designed for **presentation preparation**, **code walkthroughs**, and **viva/oral defense**.

For each requirement, the explanation is organized into:

1. **What the requirement means**
2. **UI flow**
3. **Backend flow**
4. **Database flow**
5. **Desktop flow**
6. **What to say during presentation**
7. **Implementation status / honesty note**

Also, every code block now clearly shows **where the code is found**.

---

# 1. Overall system architecture

## 1.1 One backend, two interfaces

The project is one shared system with two user interfaces:

- a **Laravel web interface**
- a **JavaFX desktop interface**

Both rely on the same backend rules and the same database.

That means:
- the backend is the **source of truth**
- the web interface talks to Laravel through web routes and Blade views
- the desktop interface talks to Laravel through REST APIs
- the desktop app adds **offline caching and synchronization**

---

## 1.2 Code that proves this architecture

### Found in
- `smart-discussion-forum/bootstrap/app.php`

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    channels: __DIR__.'/../routes/channels.php',
)
```

### What this code does
This tells Laravel to load:
- browser routes from `web.php`
- API routes from `api.php`
- scheduled command registration from `console.php`
- realtime channel registration from `channels.php`

### Why this matters
This proves the system was designed as:
- a web app
- an API server
- a scheduled automation system
- a realtime-capable backend

---

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/api/ApiClient.java`

```java
private static final String BASE_URL = "https://smart-discussion-forum-g23.onrender.com/api/v1";
```

```java
public JsonObject get(String endpoint) throws ApiException
public JsonObject post(String endpoint, JsonObject body) throws ApiException
public JsonObject put(String endpoint, JsonObject body) throws ApiException
public JsonObject delete(String endpoint) throws ApiException
```

### What this code does
`ApiClient` is the networking gateway for the desktop app.
It knows the Laravel API base URL and sends requests to it.

### Why this matters
It proves the desktop app is not a standalone backend. It is a client of the shared Laravel server.

### What to say during presentation
> “We built one shared Laravel backend. The web UI uses Blade and web routes, while the desktop app uses `ApiClient.java` to call the same backend through REST APIs.”

---

# 2. Core system concepts you should understand first

## 2.1 Group isolation

A lot of the assignment requirements depend on **group-based visibility**. This system was built so users mostly see only the groups they are allowed to access.

### Found in
- `smart-discussion-forum/app/Models/User.php`

```php
public function accessibleGroupIds(): array
{
    if ($this->isSystemAdmin()) {
        return Group::pluck('id')->toArray();
    }

    $ids = $this->group_id ? [$this->group_id] : [];

    if ($this->isGroupAdmin()) {
        $ids = array_merge(
            $ids,
            $this->administeredGroups()->pluck('groups.id')->toArray(),
        );
    }

    $ids = array_merge(
        $ids,
        $this->taughtGroups()->pluck('groups.id')->toArray(),
    );

    return array_unique(array_filter($ids));
}
```

### What this code does
This calculates all group IDs the current user may access.

- system admins get all groups
- normal users get their own group
- group admins get their own + administered groups
- lecturers get their own + taught groups

### Why this matters
Instead of repeating access logic everywhere, the app centralizes it here.

---

### Found in
- `smart-discussion-forum/app/Models/User.php`

```php
public function canAccessGroup(int $groupId): bool
{
    if ($this->isSystemAdmin()) {
        return true;
    }

    return in_array($groupId, $this->accessibleGroupIds(), true);
}
```

### What this code does
This is the yes/no check used in controllers.

### Why this matters
It is the main security rule used by forum, chat, quizzes, reports, and admin/statistics features.

### What to say during presentation
> “The system is group-scoped. Access control is centralized in `User.php`, and controllers check `canAccessGroup()` before serving protected content.”

---

## 2.2 Scheduled automation

### Found in
- `smart-discussion-forum/routes/console.php`

```php
Schedule::command('monitor:activity')->daily()->at('02:00');
Schedule::command('quiz:send-reminders')->everyMinute();
Schedule::command('quiz:activate')->everyMinute();
Schedule::command('app:calculate-statistics')->dailyAt('02:00');
```

### What this code does
Registers automatic background tasks.

### Why this matters
Several assignment requirements are time-based:
- inactivity warnings
- blacklisting
- quiz reminders
- quiz activation
- statistics updates

This is where that automation is wired in.

### What to say during presentation
> “Time-dependent features are automated using Laravel’s scheduler. They don’t require an admin to manually trigger them each time.”

---

# 3. End-to-end UI -> route/API -> backend flow map

This section is specifically for understanding the **full flow** of the system.
It answers questions like:

- What does the user click?
- Which route or endpoint is triggered?
- Which controller handles it?
- Which service/model runs the logic?
- Which tables are affected?

This is very useful for code walkthroughs and presentation demos.

---

## 3.1 Web login flow

### User action in UI
The user opens the login page and clicks **Login** after entering email and password.

### UI file
- `smart-discussion-forum/resources/views/auth/login.blade.php`

### Route called
Found in `smart-discussion-forum/routes/web.php`:

```php
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'authenticate']);
```

### Backend flow
1. Browser loads `GET /login`
2. Laravel renders the login Blade page
3. User submits the form to `POST /login`
4. `LoginController@authenticate` validates credentials
5. If valid, Laravel creates the authenticated session
6. User is redirected into the protected area, usually dashboard

### Database / data involved
- `users`
- session/auth state

### Why this matters
This is the normal web-authentication entry point into the system.

---

## 3.2 Web registration + onboarding flow

### User action in UI
The user:
1. opens the register page
2. fills registration form
3. clicks continue/register
4. sees onboarding/platform rules
5. clicks agree or decline

### UI files
- `smart-discussion-forum/resources/views/auth/register.blade.php`
- `smart-discussion-forum/resources/views/auth/onboarding.blade.php`

### Routes called
Found in `smart-discussion-forum/routes/web.php`:

```php
Route::get('/register', [RegisterController::class, 'showRegister'])->name('register');
Route::post('/register', [RegisterController::class, 'storeRegister'])
    ->name('register.store')
    ->middleware('throttle:3,60');

Route::get('/onboarding', [
    RegisterController::class,
    'showOnboarding',
])->name('onboarding');

Route::post('/onboarding/agree', [
    RegisterController::class,
    'agreeOnboarding',
])->name('onboarding.agree');

Route::post('/onboarding/decline', [
    RegisterController::class,
    'declineOnboarding',
])->name('onboarding.decline');
```

### Backend flow
1. User loads `GET /register`
2. `RegisterController@showRegister` returns the form page
3. User submits `POST /register`
4. `RegisterController@storeRegister` validates name/email/password
5. Registration data is stored temporarily in session
6. User is redirected to `GET /onboarding`
7. `RegisterController@showOnboarding` loads the rules page and available groups
8. If user agrees, `POST /onboarding/agree` calls `agreeOnboarding`
9. Inside a DB transaction, the system creates:
   - the `users` row
   - the `onboarding_agreements` row
10. User is logged in and redirected to dashboard
11. If the user declines, `POST /onboarding/decline` clears the session and no user is created

### Database / data involved
- `users`
- `onboarding_agreements`
- `groups`
- `roles`
- session temporary registration data

### Why this matters
This is the clearest end-to-end implementation of the onboarding requirement.

---

## 3.3 Desktop login flow

### User action in UI
The user opens the desktop login screen and clicks **Login**.

### UI file
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/LoginView.java`

### Desktop code path
The login button triggers `AuthManager.login(email, password)`.

### Endpoint called
The desktop sends:
- `POST /api/v1/login`

Found in `smart-discussion-forum/routes/api.php`:

```php
Route::post('/login', [AuthController::class, 'login']);
```

### Backend controller
- `smart-discussion-forum/app/Http/Controllers/Api/AuthController.php`

### Backend flow
1. Desktop user enters email/password
2. `LoginView.java` calls `AuthManager.login(...)`
3. `AuthManager` builds JSON credentials
4. `ApiClient.post("/login", body)` sends the request
5. `AuthController@login` validates input, checks rate limit, checks credentials
6. If blacklisted, it returns `403`
7. If warned and acknowledgement is required, it returns `403` plus `requires_warning_acknowledgement`
8. If valid, it updates `last_active_at`, awards daily participation points, and returns a Sanctum token
9. Desktop stores the token and opens dashboard

### Database / data involved
- `users`
- `warnings`
- `blacklist_records`
- Sanctum tokens
- `participation_activities`

### Why this matters
This is the main desktop authentication path and also shows how warning/blacklist checks are enforced.

---

## 3.4 Web forum topic creation flow

### User action in UI
The user clicks **Create Topic**, fills the form, and submits it.

### UI files
- `smart-discussion-forum/resources/views/forum/create-topic.blade.php`
- `smart-discussion-forum/resources/views/forum/index.blade.php`

### Route called
Found in `smart-discussion-forum/routes/web.php`:

```php
Route::get('/create', [
    ForumController::class,
    'create',
])->name('create');
Route::post('/', [
    ForumController::class,
    'store',
])
    ->middleware('throttle.posts:topic')
    ->name('store');
```

### Backend flow
1. User opens forum create page through `GET /forum/create`
2. `ForumController@create` returns the form
3. User submits the form to `POST /forum`
4. `ThrottlePosts` checks whether the user is posting too fast
5. `ForumController@store` validates the topic input
6. A new row is inserted into `topics`
7. `ParticipationService::recordTopicCreated(...)` records participation marks
8. Because `Topic.php` has a `created` model hook, `TopicClassificationService` automatically classifies the topic
9. User is redirected back to the forum feed

### Database / data involved
- `topics`
- `participation_activities`
- `topic_categories` / topic classification fields

### Why this matters
This flow touches forum creation, anti-flooding, participation, and recommendation/classification logic all at once.

---

## 3.5 Desktop topic list -> topic detail flow

### User action in UI
The user clicks **Forum** in the desktop sidebar, then clicks a topic card.

### UI files
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/DashboardView.java`
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/TopicListView.java`
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/TopicDetailView.java`

### Endpoints called
- `GET /api/v1/topics`
- `GET /api/v1/topics/{topicId}`
- `GET /api/v1/topics/{topicId}/posts`

Found in `smart-discussion-forum/routes/api.php`:

```php
Route::get('/topics', [TopicController::class, 'index']);
Route::get('/topics/{topicId}', [TopicController::class, 'show']);
Route::get('/topics/{topicId}/posts', [
    TopicController::class,
    'posts',
]);
```

### Backend flow
1. User clicks Forum in desktop sidebar
2. `TopicListView` calls `GET /topics`
3. `Api/TopicController@index` loads accessible active topics for that user
4. Desktop caches topics in `LocalStore`
5. User clicks one topic card
6. `TopicDetailView` calls `GET /topics/{topicId}` and `GET /topics/{topicId}/posts`
7. `Api/TopicController@show` and `posts` return the topic plus filtered replies
8. Desktop caches both topic and posts locally
9. If network is unavailable later, the same view falls back to cached SQLite data

### Database / data involved
- `topics`
- `posts`
- desktop local SQLite tables `topics` and `posts`

### Why this matters
This shows both the normal online flow and the offline fallback flow.

---

## 3.6 Web reply-to-topic flow

### User action in UI
A user opens a topic and clicks **Submit Reply**.

### UI file
- `smart-discussion-forum/resources/views/forum/show.blade.php`

### Route called
Found in `smart-discussion-forum/routes/web.php`:

```php
Route::post('/{topic}/reply', [
    ForumController::class,
    'replyStore',
])
    ->middleware('throttle.posts:reply')
    ->name('reply.store');
```

### Backend flow
1. User types a reply in the topic page
2. Browser submits `POST /forum/{topic}/reply`
3. `ThrottlePosts` checks reply spam rate
4. `ForumController@replyStore` validates the reply text
5. A new `posts` row is inserted
6. `ParticipationService::recordReplyPosted(...)` awards participation
7. `AuditLogService` records the action
8. If the topic is a question, the original asker gets a notification
9. If the question was not yet answered, `is_answered` becomes `true`
10. Browser redirects back to the topic page

### Database / data involved
- `posts`
- `participation_activities`
- `notifications`
- `audit_logs`
- `topics.is_answered`

### Why this matters
This single flow implements replying, notifications, answered logic, and participation marks.

---

## 3.7 Desktop reply-to-topic flow

### User action in UI
The user opens a topic in the desktop app and clicks **Submit Reply**.

### UI file
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/TopicDetailView.java`

### Intended endpoint
The desktop tries to use:
- `POST /api/v1/topics/{topicId}/posts`

Found in `smart-discussion-forum/routes/api.php`:

```php
Route::post('/topics/{topicId}/posts', [
    PostController::class,
    'store',
])->middleware('throttle.posts:reply');
```

### Important implementation note
The desktop sends:
- `body`

but the backend expects:
- `content`

### Intended backend flow
1. User writes a reply in desktop topic detail
2. `TopicDetailView` builds a JSON payload and posts to `/topics/{topicId}/posts`
3. `Api/PostController@store` should validate the content and insert the post
4. Participation and notification logic should run exactly like on web
5. If network is down, desktop queues the reply locally for later sync

### Why this matters
This is a very good example of a feature whose **backend logic is correct**, but whose desktop request payload needs alignment.

---

## 3.8 Notification flow

### User action in UI
A user clicks the notifications page or bell icon.

### Web route
Found in `smart-discussion-forum/routes/web.php`:

```php
Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
```

### Desktop endpoint
Found in `smart-discussion-forum/routes/api.php`:

```php
Route::get('/me/notifications', [
    NotificationController::class,
    'index',
]);
Route::get('/me/notifications/unread-count', [
    NotificationController::class,
    'unreadCount',
]);
Route::post('/notifications/read-all', [
    NotificationController::class,
    'readAll',
]);
Route::post('/notifications/{id}/read', [
    NotificationController::class,
    'markAsRead',
]);
```

### Backend flow
1. User opens notification center
2. Notification controller loads notifications for `user_id = current user`
3. Notifications are sorted unread-first and newest-first
4. If user marks one as read, `read_at` is updated
5. If user marks all read, all unread rows are updated

### Database / data involved
- `notifications`

### Desktop flow
`NotificationListView.java` uses:
- `GET /me/notifications`
- `POST /notifications/{id}/read`
- `POST /notifications/read-all`

This is one of the more straightforward desktop flows.

---

## 3.9 Web selective visibility flow

### User action in UI
A post author chooses to exclude a user from seeing a post.

### Route called
Found in `smart-discussion-forum/routes/web.php`:

```php
Route::post('/post/{post}/visibility/exclude', [
    ForumController::class,
    'excludeUser',
])->name('visibility.exclude');
```

### Backend flow
1. Author submits the exclusion form from topic/post UI
2. `ForumController@excludeUser` checks that the current user owns the post
3. It validates the selected `user_id`
4. It ensures the target user is in the same group
5. It inserts a row into `post_visibility`
6. Later, when replies are loaded, `Post::visibleToUser(...)` hides the post from that user

### Database / data involved
- `post_visibility`
- `posts`
- `users`

### Why this matters
This is a complete example of “configuration now, filtering later.”
The exclusion is written first, then enforced each time content is retrieved.

---

## 3.10 Web report-content flow

### User action in UI
A user clicks **Report** on a topic or post.

### Web/API endpoint
Found in `smart-discussion-forum/routes/api.php`:

```php
Route::post('/reports', [ReportController::class, 'store']);
```

### Backend flow
1. User submits the report form
2. `Api/ReportController@store` validates the request
3. `ReportUtility@createReport` finds the content, enforces group isolation, creates the report, and flags the post if needed
4. Moderation tools can later surface reported content

### Database / data involved
- `reports`
- `posts.is_reported`

### Why this matters
This connects the user-facing report action to the moderation pipeline.

---

## 3.11 Web chat flow

### User action in UI
User opens conversations, selects one, and sends a message.

### Routes called
Found in `smart-discussion-forum/routes/api.php`:

```php
Route::get('/conversations', [ConversationController::class, 'index']);
Route::get('/conversations/{id}', [ConversationController::class, 'show']);
Route::post('/conversations/{id}/messages', [MessageController::class, 'store']);
```

### Backend flow
1. User opens conversation list
2. `ConversationController@index` loads conversation summaries
3. User opens one conversation
4. `ConversationController@show` loads the conversation and marks messages as read
5. User sends a message
6. `MessageController@store` validates and inserts it
7. message event manager handles follow-up realtime/event behavior

### Database / data involved
- `conversations`
- `conversation_participants`
- `messages`
- `message_status`

### Why this matters
This is the main online chat path.

---

## 3.12 Desktop offline chat flow

### User action in UI
User writes a message while offline in a conversation.

### Desktop files
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/api/LocalStore.java`
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/api/SyncEngine.java`
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/ConversationDetailView.java`

### Flow
1. User enters a message while network is unavailable
2. Desktop stores the message in local SQLite `outbound_queue`
3. Every 30 seconds, `SyncEngine` tries `POST /api/v1/sync/push`
4. Backend validates and saves those messages into `messages`
5. Desktop later pulls new server data through `GET /api/v1/sync/pull?device_id=...`
6. Local cache is refreshed

### Database / data involved
Backend:
- `messages`
- `sync_checkpoints`

Desktop local SQLite:
- `outbound_queue`
- `conversations`
- `messages`

### Why this matters
This is the core “offline desktop, then sync later” requirement.

---

## 3.13 Participation flow

### User action in UI
A user logs in, creates a topic, replies, or completes a quiz.
A lecturer/admin later opens the participation page.

### Routes/endpoints
Web route:

```php
Route::get('/participation/students', [
    ParticipationController::class,
    'students',
])->name('participation.students');
```

API route:

```php
Route::get('/participation/students', [
    ApiParticipationController::class,
    'students',
]);
```

### Backend flow
1. User action occurs
2. `ParticipationService` records the activity row
3. Lecturer/admin opens participation page
4. participation controller summarizes totals and breakdowns
5. UI displays participation metrics

### Database / data involved
- `participation_activities`

### Why this matters
This connects individual actions to lecturer-visible marks.

---

## 3.14 Lecturer quiz creation flow

### User action in UI
Lecturer opens quiz creation page, fills schedule/configuration, and clicks create/publish.

### Routes/endpoints
Found in `smart-discussion-forum/routes/api.php`:

```php
Route::prefix('quizzes')->name('quizzes.')->group(function () {
    Route::get('/', [QuizController::class, 'index']);
    Route::post('/', [QuizController::class, 'store']);
    Route::post('/{quiz}/publish', [QuizController::class, 'publish']);
});
```

### Backend flow
1. Lecturer creates quiz via `POST /api/v1/quizzes`
2. `QuizController@store` validates title, target category, group, date, time, duration
3. quiz row is inserted into `quizzes`
4. default config row is inserted into `quiz_configuration`
5. Lecturer later publishes it via `POST /api/v1/quizzes/{quiz}/publish`
6. publish logic checks there are questions and the date is valid
7. `published_at` is set
8. scheduler later reminds students and activates the quiz

### Database / data involved
- `quizzes`
- `quiz_configuration`
- `questions`
- `answers`

### Why this matters
This is the full lecturer-side lifecycle before students attempt the quiz.

---

## 3.15 Student quiz attempt flow

### User action in UI
Student sees a quiz, clicks start/join, answers questions, and submits.

### Endpoints called
Found in `smart-discussion-forum/routes/api.php`:

```php
Route::get('/{quiz}/announcement', [StudentQuizController::class, 'announcement']);
Route::get('/{quiz}/status', [StudentQuizController::class, 'status']);
Route::post('/{quiz}/attempt', [StudentQuizController::class, 'start']);
Route::get('/{quiz}/attempt', [StudentQuizController::class, 'showAttempt']);
Route::post('/{quiz}/answer', [StudentQuizController::class, 'saveAnswer']);
Route::post('/{quiz}/submit', [StudentQuizController::class, 'submit']);
Route::post('/{quiz}/auto-submit', [StudentQuizController::class, 'autoSubmit']);
```

### Backend flow
1. Student sees quiz announcement
2. system checks role

# Requirement 1
## Members flooding the group forum with irrelevant materials

## What the requirement means
The system should reduce spam, excessive posting, and irrelevant content.

---

## UI flow
### Web click path
1. User opens the forum feed in `smart-discussion-forum/resources/views/forum/index.blade.php`
2. If they click **Create Topic**, the browser goes to `GET /forum/create`
3. Laravel sends that route to `ForumController@create`, which returns `resources/views/forum/create-topic.blade.php`
4. When the user submits the create form, the browser sends `POST /forum`
5. Laravel sends that route to `ForumController@store`, and the `throttle.posts:topic` middleware runs before the controller
6. If the user opens one topic page, the browser goes to `GET /forum/{topic}` and Laravel sends it to `ForumController@show`
7. If the user replies inside the topic page, the form submits `POST /forum/{topic}/reply`
8. Laravel sends that route to `ForumController@replyStore`, and the `throttle.posts:reply` middleware runs before the controller
9. If the user reports irrelevant content, the browser submits `POST /report`
10. Laravel sends that route to `ReportController@store`, which then delegates the deeper logic to `ReportUtility`

Exact web route -> controller map:
- `GET /forum/create` -> `ForumController@create`
- `POST /forum` -> `ForumController@store`
- `GET /forum/{topic}` -> `ForumController@show`
- `POST /forum/{topic}/reply` -> `ForumController@replyStore`
- `POST /report` -> `ReportController@store`

### Desktop click path
1. User clicks **Forum** in `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/DashboardView.java`
2. `TopicListView.java` calls `GET /api/v1/topics`
3. Laravel routes that request to `Api\TopicController@index`, which returns the topic feed JSON
4. User clicks one topic card, and `TopicDetailView.java` loads:
   - `GET /api/v1/topics/{topicId}` -> `Api\TopicController@show`
   - `GET /api/v1/topics/{topicId}/posts` -> `Api\TopicController@posts`
5. If the user replies, `TopicDetailView.java` tries `POST /api/v1/topics/{topicId}/posts`
6. Laravel routes that request to `Api\PostController@store`, with `throttle.posts:reply` applied before the controller
7. If the user reports content, `ReportView.java` tries `POST /api/v1/reports`
8. Laravel routes that request to `Api\ReportController@store`, which then calls `ReportUtility`

Exact desktop/API endpoint -> controller map:
- `GET /api/v1/topics` -> `Api\TopicController@index`
- `GET /api/v1/topics/{topicId}` -> `Api\TopicController@show`
- `GET /api/v1/topics/{topicId}/posts` -> `Api\TopicController@posts`
- `POST /api/v1/topics` -> `Api\TopicController@store`
- `POST /api/v1/topics/{topicId}/posts` -> `Api\PostController@store`
- `POST /api/v1/reports` -> `Api\ReportController@store`

---

## Backend flow
There are two main backend protections:
1. **throttling** to limit spammy posting
2. **reporting + moderation flags** to identify irrelevant content

### A. Anti-flood middleware

#### Found in
- `smart-discussion-forum/app/Http/Middleware/ThrottlePosts.php`

```php
protected const LIMITS = [
    'reply' => ['max_attempts' => 5, 'decay_seconds' => 60],
    'topic' => ['max_attempts' => 3, 'decay_seconds' => 60],
];
```

#### What this code does
It defines two anti-flood rules:
- max **5 replies per minute**
- max **3 new topics per minute**

#### Why this matters
This directly tackles the “flooding the group” problem.

#### Bigger-picture explanation of the whole middleware
`smart-discussion-forum/app/Http/Middleware/ThrottlePosts.php` is the reusable anti-flood gate for posting.
It is attached to both web and API posting routes, so it protects:
- `POST /forum` for new topics
- `POST /forum/{topic}/reply` for web replies
- `POST /api/v1/topics` for API/desktop topic creation
- `POST /api/v1/topics/{topicId}/posts` for API/desktop replies

Its overall job is simple:
1. check that a user is authenticated
2. skip throttling for lecturers and administrators
3. decide whether this request is a `topic` action or a `reply` action
4. look up the correct limit from `LIMITS`
5. build a per-user per-action key like `throttle.posts.reply.15`
6. ask Laravel’s `RateLimiter` whether that user already exceeded the quota
7. if yes, stop the request with HTTP `429 Too Many Requests`
8. if no, record the attempt and allow the controller to run

In other words, this middleware does not decide whether content is semantically relevant; it controls how fast people are allowed to post. The idea is that reducing posting bursts makes it harder for normal members to spam or flood the discussion feed.

A few design choices in this file are worth understanding:
- the limits are different for topics and replies because new topics clutter the main feed faster than replies do
- the cache key contains both the action and the user ID, so each user gets their own quota and topic posting does not consume reply quota
- lecturers and admins bypass the rule so moderation, teaching, or official announcements are not blocked
- the middleware uses Laravel’s built-in `RateLimiter` instead of database rows, which is faster and better suited for temporary counters

One honest limitation: when the limit is exceeded, the middleware returns a JSON `429` response even for browser form submissions. So the backend protection is correct, but the web error experience may be less friendly than a redirect with a flash message.

---

#### Found in
- `smart-discussion-forum/app/Http/Middleware/ThrottlePosts.php`

```php
$key = 'throttle.posts.'.$action.'.'.$user->id;

if ($this->limiter->tooManyAttempts($key, $limits['max_attempts'])) {
    return response()->json([
        'message' => 'You are posting too fast. Please wait.',
        'retry_after' => $retryAfter,
    ], 429);
}

$this->limiter->hit($key, $limits['decay_seconds']);
```

#### What this code does
It builds a per-user, per-action key and uses Laravel’s rate limiter.

- `tooManyAttempts(...)` checks whether the user already exceeded the limit
- if yes, the request is blocked with HTTP `429`
- if no, the action is counted using `hit(...)`

#### Why this matters
This is the actual enforcement logic.
Without this block, the limits would just be numbers with no effect.

---

#### Found in
- `smart-discussion-forum/routes/web.php`

```php
Route::post('/', [ForumController::class, 'store'])
    ->middleware('throttle.posts:topic')
    ->name('store');

Route::post('/{topic}/reply', [ForumController::class, 'replyStore'])
    ->middleware('throttle.posts:reply')
    ->name('reply.store');
```

#### Found in
- `smart-discussion-forum/routes/api.php`

```php
Route::post('/topics', [TopicController::class, 'store'])
    ->middleware('throttle.posts:topic');

Route::post('/topics/{topicId}/posts', [PostController::class, 'store'])
    ->middleware('throttle.posts:reply');
```

#### What this code does
This attaches the anti-flood middleware to both:
- web topic/reply creation
- API topic/reply creation

#### Why this matters
So both browser users and desktop/API users are protected by the same anti-flooding rules.

---

### B. Reporting and moderation pipeline

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/ReportController.php`

```php
$validated = $request->validate([
    'reason' => 'required|string|max:1000',
    'type' => 'required|in:topic,post,reply',
    'id' => 'required|integer',
]);
```

#### What this code does
Checks that a report submission includes:
- the reason for reporting
- the content type
- the content ID

#### Why this matters
It ensures that reports are structured and valid.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/ReportController.php`

```php
$report = $this->reportUtility->createReport(
    $request->user(),
    $validated['type'],
    $validated['id'],
    $validated['reason']
);
```

#### What this code does
Instead of putting all report logic in the controller, it delegates the real logic to `ReportUtility`.

#### Why this matters
This keeps the implementation reusable and consistent.

---

#### Found in
- `smart-discussion-forum/app/Utilities/ReportUtility.php`

```php
$model = $class::findOrFail($id);
$this->enforceGroupIsolation($reporter, $model);

$report = new Report([
    'reason' => $reason,
    'user_id' => $reporter->id,
]);

$model->reports()->save($report);

if ($model instanceof Post) {
    $model->update(['is_reported' => true]);
}
```

#### What this code does
This is the real reporting pipeline:
1. find the target topic/post/reply
2. make sure the reporter is allowed to access that content
3. create the report
4. link the report to the content
5. if it is a post, mark it `is_reported = true`

#### Why this matters
This does more than store a complaint.
It also flags content for later moderation.

---

#### Found in
- `smart-discussion-forum/app/Models/Post.php`

```php
protected $fillable = [
    'topic_id',
    'user_id',
    'content',
    'is_removed',
    'is_reported',
    'category_id',
];
```

#### What this code does
This shows that posts already support moderation state directly in the data model.

- `is_reported` means “this post has been flagged”
- `is_removed` means “this post has been taken out of normal view”

#### Why this matters
It gives the moderation layer something concrete to work with.

---

#### Found in
- `smart-discussion-forum/app/Models/Post.php`

```php
public function scopeNotRemoved($query)
{
    return $query->where('is_removed', false);
}

public function scopeReported($query)
{
    return $query->where('is_reported', true);
}
```

#### What this code does
These scopes make it easy for the app to either:
- fetch only visible posts
- fetch only reported posts

#### Why this matters
It keeps moderation filtering consistent across views and controllers.

---

## Database flow
Tables involved:
- `posts`
- `topics`
- `reports`
- `audit_logs`

Flow:
1. user creates a topic or reply
2. rate limiter checks whether they are posting too fast
3. if the content is irrelevant, another user reports it
4. the report is stored in `reports`
5. posts can be flagged as `is_reported`
6. moderators can later remove or manage the content

---

## Desktop flow

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/ReportView.java`

```java
body.addProperty("content_type", contentType);
body.addProperty("content_id", contentId);
body.addProperty("reason", reason);
```

### What this code does
This is the desktop reporting payload.

### Important limitation
The backend expects:
- `type`
- `id`
- `reason`

But the desktop sends:
- `content_type`
- `content_id`
- `reason`

So the desktop report screen exists, but its request format does not match the API controller as currently written.

---

## What to say during presentation
> “We controlled flooding in two ways: first by limiting how often users can create topics and replies, and second by allowing irrelevant content to be reported and flagged for moderation.”

---

## Implementation status
- **Backend/web:** implemented well
- **Desktop:** report UI exists, but request format needs API alignment

---

# Requirement 2
## When a question goes unanswered, the member who asked it may fail to see the answer

## What the requirement means
If someone asks a question, the system should help them notice when another user answers it.

---

## UI flow
### Web click path
1. The asker opens `smart-discussion-forum/resources/views/forum/create-topic.blade.php`
2. The create form submits `POST /forum`
3. Laravel routes that request to `ForumController@store`, which accepts `post_type = question`
4. Another user later opens `GET /forum/{topic}` in `resources/views/forum/show.blade.php`
5. That user submits a reply form to `POST /forum/{topic}/reply`
6. Laravel routes that request to `ForumController@replyStore`
7. Inside `replyStore`, the backend inserts the reply, creates a `question_answered` notification for the original asker, and sets `is_answered = true`
8. When the original asker clicks Notifications, the browser goes to `GET /notifications`
9. Laravel routes that request to `NotificationController@index`, which loads the stored notification rows

Exact web route -> controller map:
- `POST /forum` -> `ForumController@store`
- `GET /forum/{topic}` -> `ForumController@show`
- `POST /forum/{topic}/reply` -> `ForumController@replyStore`
- `GET /notifications` -> `NotificationController@index`
- `POST /notifications/{id}/read` -> `NotificationController@read`

### Desktop click path
1. User opens Forum from `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/DashboardView.java`
2. `TopicListView.java` loads topics using `GET /api/v1/topics` -> `Api\TopicController@index`
3. User opens one topic in `TopicDetailView.java`
4. Desktop loads the thread using:
   - `GET /api/v1/topics/{topicId}` -> `Api\TopicController@show`
   - `GET /api/v1/topics/{topicId}/posts` -> `Api\TopicController@posts`
5. If the user replies, desktop sends `POST /api/v1/topics/{topicId}/posts`
6. Laravel routes that request to `Api\PostController@store`, which creates the reply, may create the notification, and may mark the topic answered
7. When the asker opens `NotificationListView.java`, desktop loads `GET /api/v1/me/notifications`
8. Laravel routes that request to `NotificationController@index` in the API layer and returns the stored alerts

Exact desktop/API endpoint -> controller map:
- `GET /api/v1/topics` -> `Api\TopicController@index`
- `GET /api/v1/topics/{topicId}` -> `Api\TopicController@show`
- `GET /api/v1/topics/{topicId}/posts` -> `Api\TopicController@posts`
- `POST /api/v1/topics/{topicId}/posts` -> `Api\PostController@store`
- `GET /api/v1/me/notifications` -> `Api\NotificationController@index`
- `POST /api/v1/topics/{topicId}/toggle-answered` -> `Api\TopicController@toggleAnswered`

---

## Backend flow

### A. Topics can be created as questions

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/TopicController.php`
- `smart-discussion-forum/app/Http/Controllers/ForumController.php`

```php
'post_type' => 'sometimes|in:discussion,question',
```

#### What this code does
Allows the topic type to be either a normal discussion or a question.

#### Why this matters
This is how the system knows which topics should participate in question-answer logic.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/TopicController.php`

```php
$topic = Topic::create([
    'title' => $validated['title'],
    'description' => $validated['description'],
    'post_type' => $validated['post_type'] ?? 'discussion',
    'created_by' => $user->id,
    'group_id' => $groupId,
    'status' => 'active',
]);
```

#### What this code does
Stores the topic with its title, text, type, author, group, and active status.

#### Why this matters
This is the moment where a normal discussion or a question is actually persisted in the database.

---

### B. Replies trigger question notifications

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/PostController.php`

```php
$post = Post::create([
    'topic_id' => $topic->id,
    'user_id' => $user->id,
    'content' => $validated['content'],
]);
```

#### What this code does
Creates a reply to the topic.

#### Why this matters
This is the event that may count as an answer.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/PostController.php`

```php
if (
    $topic->post_type === 'question' &&
    $topic->created_by !== $user->id
) {
    Notification::create([
        'user_id' => $topic->created_by,
        'type' => 'question_answered',
        'data' => ['topic_id' => $topic->id, 'post_id' => $post->id],
    ]);
}
```

#### What this code does
This checks whether:
- the topic is a question
- the reply is from someone other than the asker

If both are true, it creates a notification for the original asker.

#### Why this matters
This is the direct implementation of the requirement.
The user does not have to keep scanning all forum messages manually.

---

### C. Questions are auto-marked as answered

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/PostController.php`

```php
if ($topic->post_type === 'question' && ! $topic->is_answered) {
    $topic->update(['is_answered' => true]);
}
```

#### What this code does
The first reply to a question sets `is_answered = true`.

#### Why this matters
It gives the system a visible solved state.

---

### D. Manual answered / pinned toggles

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/TopicController.php`

```php
if ($topic->post_type !== 'question') {
    return response()->json([
        'message' => 'Only question topics can be marked as answered.',
    ], 422);
}
```

#### What this code does
Prevents non-question topics from being treated as answered.

#### Why this matters
It keeps the answered feature logically correct.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/TopicController.php`

```php
$topic->update(['is_answered' => ! $topic->is_answered]);
```

#### What this code does
Lets authorized users toggle answered/unanswered state.

#### Why this matters
It supports correction or moderation after automatic behavior.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/TopicController.php`

```php
if (! $user->isAdmin()) {
    return response()->json([
        'message' => 'Only administrators can pin topics.',
    ], 403);
}

$topic->update(['is_pinned' => ! $topic->is_pinned]);
```

#### What this code does
Restricts pinning to admins and toggles the pin state.

#### Why this matters
Pinned topics make important answers easier to notice.

---

## Database flow
Tables involved:
- `topics`
- `posts`
- `notifications`

Flow:
1. a topic is created with `post_type = question`
2. another user replies
3. the reply is stored in `posts`
4. the original asker gets a row in `notifications`
5. the topic’s `is_answered` field is updated

---

## Desktop flow

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/TopicListView.java`

```java
boolean isAnswered = t.has("is_answered") && !t.get("is_answered").isJsonNull()
        && t.get("is_answered").getAsBoolean();
if (isAnswered) {
    Label answeredBadge = new Label("✓ Answered");
}
```

### What this code does
If the backend says a topic is answered, the desktop list shows an answered badge.

### Why this matters
This gives visible feedback to users that a question has already been responded to.

---

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/NotificationListView.java`

```java
JsonObject response = ApiClient.getInstance().get("/me/notifications?per_page=50");
```

### What this code does
Loads notifications from the backend so the user can see answers and other events.

### Why this matters
This is the desktop UI side of the question-answer alert system.

---

## What to say during presentation
> “Questions are stored as a special topic type. When someone replies, the original asker gets a notification, and the topic is automatically marked as answered.”

---

## Implementation status
- **Backend/web:** strong
- **Desktop:** good overall notification/question display support

---

# Requirement 3
## Some members wish to exclude a few people from some communications

## What the requirement means
A user should be able to make a post visible to some people while excluding specific others.

---

## UI flow
### Web click path
1. User opens one topic in `smart-discussion-forum/resources/views/forum/show.blade.php`
2. `ForumController@show` loads the topic plus an `excludableUsers` list for that screen
3. The post author selects a person to exclude and submits the form to `POST /forum/post/{post}/visibility/exclude`
4. Laravel routes that request to `ForumController@excludeUser`
5. `excludeUser()` validates the selected `user_id`, checks that the current user owns the post, checks that the excluded person belongs to the same group, then writes to `post_visibility`
6. Later, when anyone reopens `GET /forum/{topic}`, `ForumController@show` reloads posts using `visibleToUser(Auth::id())`, so excluded users simply do not receive that post in the query result

Exact web route -> controller map:
- `GET /forum/{topic}` -> `ForumController@show`
- `POST /forum/post/{post}/visibility/exclude` -> `ForumController@excludeUser`

### Desktop click path
1. User opens `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/PostVisibilityView.java`
2. Desktop loads the current exclusions using `GET /api/v1/posts/{postId}/visibility`
3. Laravel routes that request to `Api\PostVisibilityController@index`
4. If the author excludes a user, desktop sends `POST /api/v1/posts/{postId}/visibility/exclude`
5. Laravel routes that request to `Api\PostVisibilityController@exclude`
6. If an exclusion is removed, desktop sends `DELETE /api/v1/posts/{postId}/visibility/{userId}`
7. Laravel routes that request to `Api\PostVisibilityController@removeExclusion`
8. When the topic is later reloaded, desktop uses `GET /api/v1/topics/{topicId}/posts` and the backend filters the hidden post out via `visibleToUser()` before JSON is returned
9. Some parts of `PostVisibilityView.java` are still placeholders, so the backend flow is stronger than the finished desktop UX

Exact desktop/API endpoint -> controller map:
- `GET /api/v1/posts/{postId}/visibility` -> `Api\PostVisibilityController@index`
- `POST /api/v1/posts/{postId}/visibility/exclude` -> `Api\PostVisibilityController@exclude`
- `DELETE /api/v1/posts/{postId}/visibility/{userId}` -> `Api\PostVisibilityController@removeExclusion`
- `GET /api/v1/topics/{topicId}/posts` -> `Api\TopicController@posts`

---

## Backend flow

### A. Visibility is enforced in the query layer

#### Found in
- `smart-discussion-forum/app/Models/Post.php`

```php
public function scopeVisibleToUser($query, int $userId)
{
    return $query->whereDoesntHave('visibilityExclusions', function ($q) use ($userId) {
        $q->where('excluded_user_id', $userId);
    });
}
```

#### What this code does
This removes posts from results if the current user appears in that post’s exclusion list.

#### Why this matters
This is very important: the rule is enforced by the backend query itself, not just hidden in the UI.

---

### B. The author can create an exclusion

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/PostVisibilityController.php`

```php
if ($post->user_id !== $user->id) {
    return response()->json([
        'message' => 'Only the post author can manage visibility.',
    ], 403);
}
```

#### What this code does
Only the author of the post is allowed to change who can see it.

#### Why this matters
It protects post visibility rules from being abused by other users.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/PostVisibilityController.php`

```php
$validated = $request->validate([
    'user_id' => 'required|integer|exists:users,id',
]);
```

#### What this code does
Validates that the excluded target is a real user.

#### Why this matters
It prevents invalid data from being stored.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/PostVisibilityController.php`

```php
if ((int) $validated['user_id'] === $post->user_id) {
    return response()->json([
        'message' => 'You cannot exclude yourself from your own post.',
    ], 422);
}
```

#### What this code does
Stops the author from excluding themselves.

#### Why this matters
This preserves logical consistency.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/PostVisibilityController.php`

```php
$visibility = PostVisibility::create([
    'post_id' => $post->id,
    'excluded_user_id' => $validated['user_id'],
]);
```

#### What this code does
Creates the database record that says: “this user should not see this post.”

#### Why this matters
This is the persistent implementation of selective communication.

---

### C. The filter is used when replies are loaded

#### Found in
- `smart-discussion-forum/app/Http/Controllers/ForumController.php`

```php
$posts = Post::where('topic_id', $topic->id)
    ->notRemoved()
    ->visibleToUser(Auth::id())
    ->with('user')
    ->orderBy('created_at', 'asc')
    ->paginate(20);
```

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/TopicController.php`

```php
$posts = $topic
    ->posts()
    ->notRemoved()
    ->visibleToUser($user->id)
    ->with('user')
    ->orderBy('created_at', 'asc')
    ->paginate(20);
```

#### What this code does
Whenever replies are displayed, the app only returns posts the current user is allowed to see.

#### Why this matters
This is where the exclusion rule becomes visible behavior.

---

## Database flow
Tables involved:
- `posts`
- `post_visibility`
- `users`

Flow:
1. post author excludes a user
2. backend inserts a row into `post_visibility`
3. later, when that excluded user loads the topic, `visibleToUser()` filters the post out

---

## Desktop flow

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/PostVisibilityView.java`

```java
ApiClient.getInstance().get("/posts/" + postId + "/visibility");
ApiClient.getInstance().post("/posts/" + postId + "/visibility/exclude", body);
ApiClient.getInstance().delete("/posts/" + postId + "/visibility/" + userId);
```

### What this code does
It shows the desktop UI was intended to support:
- viewing exclusions
- adding exclusions
- removing exclusions

---

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/PostVisibilityView.java`

```java
AlertHelper.showInfo("Info", "In a complete implementation, this would show users available to exclude.");
```

### What this code does
This is placeholder behavior, not a finished feature.

### Why this matters
It shows the desktop visibility flow is incomplete.

---

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/PostVisibilityView.java`

```java
return 1; // Placeholder
```

### What this code does
This hardcodes a topic ID when navigating back.

### Why this matters
This is another sign that the desktop screen is not fully production-ready.

---

## What to say during presentation
> “Selective visibility is implemented at the backend level using a `post_visibility` table and a `visibleToUser()` query scope. The web flow is solid. The desktop screen exists but is only partially completed.”

---

## Implementation status
- **Backend/web:** implemented
- **Desktop:** partial

---

# Requirement 4
## Administrators want inactivity warnings and automatic blacklisting

## What the requirement means
If a user stays inactive too long:
1. give warning 1
2. then warning 2
3. then blacklist if they still do not comply

---

## UI flow
### Web click path
1. Admin opens warning or blacklist pages from the admin interface
2. Warned users who log in are redirected into the warning acknowledgement flow
3. The acknowledgement form submits to `POST /warning-acknowledgement`

Routes involved:
- `GET /warning-acknowledgement`
- `POST /warning-acknowledgement`
- admin warning/blacklist routes through the web admin area

Relevant views:
- `resources/views/admin/warnings/index.blade.php`
- `resources/views/admin/blacklist/index.blade.php`
- `resources/views/auth/warning-acknowledgement.blade.php`

### Desktop click path
1. User opens desktop login screen in `views/LoginView.java`
2. Desktop sends `POST /api/v1/login`
3. If backend says acknowledgement is required, the login UI shows a confirmation dialog
4. Desktop then calls `POST /api/v1/warnings/acknowledge`
5. Admin users can browse warning/blacklist screens from:
   - `views/admin/WarningView.java`
   - `views/admin/BlacklistView.java`

---

## Backend flow

### A. Daily inactivity monitor

#### Found in
- `smart-discussion-forum/routes/console.php`

```php
Schedule::command('monitor:activity')->daily()->at('02:00');
```

#### What this code does
Runs the activity monitoring command automatically every day.

#### Why this matters
It makes the sanction process automatic.

---

#### Found in
- `smart-discussion-forum/app/Console/Commands/MonitorMemberActivity.php`

```php
$users = User::whereIn('account_status', ['active', 'warned'])->get();
```

#### What this code does
Fetches users who are either active or already in warned status.

#### Why this matters
These are the only users relevant to the escalation process.

---

#### Found in
- `smart-discussion-forum/app/Console/Commands/MonitorMemberActivity.php`

```php
$lastActive = $user->last_active_at ?? $user->created_at;
$daysInactive = now()->diffInDays($lastActive, absolute: true);
```

#### What this code does
Calculates how many days the user has been inactive.
If they never had activity, it falls back to their creation date.

#### Why this matters
This is the measurable basis for warning decisions.

---

#### Found in
- `smart-discussion-forum/app/Console/Commands/MonitorMemberActivity.php`

```php
$inactivityThreshold = (int) SystemConfig::getValue('inactivity_warning_days', 30);
```

#### What this code does
Reads the number of inactivity days allowed before warning.

#### Why this matters
The rule is configurable rather than hardcoded.

---

### B. Escalation to warnings and blacklist

#### Found in
- `smart-discussion-forum/app/Console/Commands/MonitorMemberActivity.php`

```php
Warning::create([
    'user_id' => $user->id,
    'warning_number' => 1,
    'reason' => 'Account inactivity - No activity for extended period',
    'response_deadline' => now()->addDays($secondWarningDays),
    'is_acknowledged' => false,
    'is_resolved' => false,
]);

$user->update(['account_status' => 'warned']);
```

#### What this code does
Creates Warning 1 and marks the user as warned.

#### Why this matters
This is the first formal sanction step.

---

#### Found in
- `smart-discussion-forum/app/Console/Commands/MonitorMemberActivity.php`

```php
Warning::create([
    'user_id' => $user->id,
    'warning_number' => 2,
    'reason' => 'Account inactivity - Failed to respond to Warning 1',
    'response_deadline' => now()->addDays($blacklistDays),
    'is_acknowledged' => false,
    'is_resolved' => false,
]);
```

#### What this code does
Creates Warning 2 when the first one expires without resolution.

#### Why this matters
This is the second step the assignment explicitly asked for.

---

#### Found in
- `smart-discussion-forum/app/Console/Commands/MonitorMemberActivity.php`

```php
BlacklistRecord::create([
    'user_id' => $user->id,
    'reason' => 'Inactivity - Failed to respond to warning',
    'expires_at' => now()->addDays($blacklistDuration),
    'lifted_at' => null,
    'lifted_by' => null,
]);

$user->update(['account_status' => 'blacklisted']);
```

#### What this code does
Creates a blacklist record with an expiry and changes the user account status to blacklisted.

#### Why this matters
This is the final automatic punishment phase.

---

### C. Login enforcement

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/AuthController.php`

```php
if ($user->account_status === 'blacklisted') {
    return response()->json([
        'message' => 'Your account is blacklisted until '.$blacklistRecord->expires_at->format('M d, Y').'.',
    ], 403);
}
```

#### What this code does
Blocks blacklisted users from logging in.

#### Why this matters
Blacklisting becomes a real restriction, not just a database note.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/AuthController.php`

```php
if ($user->account_status === 'warned') {
    $unacknowledgedWarning = Warning::where('user_id', $user->id)
        ->where('is_acknowledged', false)
        ->first();

    if ($unacknowledgedWarning) {
        return response()->json([
            'message' => 'Your account is warned. Please acknowledge the warning before continuing.',
            'requires_warning_acknowledgement' => true,
            'user' => $this->formatUserResponse($user),
        ], 403);
    }
}
```

#### What this code does
Warned users with an unacknowledged warning must acknowledge it before proceeding.

#### Why this matters
This makes warnings part of the live user journey.

---

## Database flow
Tables involved:
- `warnings`
- `blacklist_records`
- `users`
- `system_configs`
- `notifications`

Flow:
1. scheduler runs monitor command
2. inactivity is measured
3. warning 1 or warning 2 is inserted into `warnings`
4. if necessary, a row is created in `blacklist_records`
5. login is blocked based on `account_status`

---

## Desktop flow

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/api/AuthManager.java`

```java
if (errorJson.has("requires_warning_acknowledgement")
        && errorJson.get("requires_warning_acknowledgement").getAsBoolean()) {
    throw new WarnedException(e.getMessage(), warnedUser);
}
```

### What this code does
Desktop login detects the special warned response and raises a dedicated exception.

### Why this matters
This allows the UI to react with a warning acknowledgement dialog rather than a generic login error.

---

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/api/AuthManager.java`

```java
apiClient.post("/warnings/acknowledge", new JsonObject());
JsonObject retryResponse = apiClient.post("/login", body);
```

### What this code does
The desktop app acknowledges the warning first, then retries login.

### Why this matters
This completes the warning flow from the client side.

---

## What to say during presentation
> “A scheduled command checks inactivity daily. It escalates users through warning 1, warning 2, and blacklist, and the login controller enforces those states.”

---

## Implementation status
- **Backend/web:** implemented
- **Desktop:** implemented for login handling and admin visibility

---

# Requirement 5
## New members should receive platform rules, agree, then be registered; otherwise they are declined

## What the requirement means
The system should not fully register a user until they have accepted platform rules.

---

## UI flow
### Web click path
1. User opens `resources/views/auth/register.blade.php`
2. Browser loads `GET /register`
3. User submits the registration form to `POST /register`
4. System redirects user to `GET /onboarding`
5. If the user agrees, the onboarding form submits to `POST /onboarding/agree`
6. If the user declines, the onboarding form submits to `POST /onboarding/decline`

Views:
- `resources/views/auth/register.blade.php`
- `resources/views/auth/onboarding.blade.php`

### Desktop click path
1. User opens `views/RegisterView.java`
2. User fills registration form and clicks Continue
3. Desktop directly calls `POST /api/v1/register`
4. Unlike web, there is no full onboarding page in between

---

## Backend flow

### A. Registration form validation but no user yet

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Auth/RegisterController.php`

```php
$validated = $request->validate([
    'full_name' => 'required|string|max:255',
    'email' => 'required|email|unique:users,email',
    'password' => [
        'required',
        'confirmed',
        Password::min(8)
            ->mixedCase()
            ->numbers(),
    ],
]);
```

#### What this code does
Validates the initial registration input.

#### Why this matters
The system first ensures the input is acceptable before even moving to the onboarding step.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Auth/RegisterController.php`

```php
session(['registration_data' => [
    'full_name' => $validated['full_name'],
    'email' => $validated['email'],
    'password_hash' => Hash::make($validated['password']),
]]);

return redirect()->route('onboarding');
```

#### What this code does
Saves the registration data temporarily in session, then redirects the user to the onboarding page.

#### Why this matters
This is the actual mechanism that forces onboarding before account creation.
The user is still not in the database at this stage.

---

### B. Onboarding view setup

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Auth/RegisterController.php`

```php
$groups = Group::where('group_type', 'student')->get();
return view('auth.onboarding', compact('groups'));
```

#### What this code does
Loads valid student groups and shows the onboarding page.

#### Why this matters
The onboarding page is not just a message; it also collects the group needed for registration.

---

### C. Agreement creates the user atomically

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Auth/RegisterController.php`

```php
$user = DB::transaction(function () use ($registrationData, $role, $validated, $request) {
    $group = Group::findOrFail($validated['group_id']);

    $user = User::create([
        'full_name' => $registrationData['full_name'],
        'email' => $registrationData['email'],
        'password' => $registrationData['password_hash'],
        'role_id' => $role->id,
        'group_id' => $group->id,
    ]);

    OnboardingAgreement::create([
        'user_id' => $user->id,
        'agreed' => true,
        'ip_address' => $request->ip(),
        'agreement_version' => config('app.agreement_version', '1.0'),
    ]);

    $group->autoPromoteFirstStudent($user);

    return $user;
});
```

#### What this code does
Inside one database transaction, the controller:
1. verifies the selected group
2. creates the user
3. creates an onboarding agreement record
4. optionally auto-promotes the first student in a group

#### Why this matters
Using a transaction means either everything succeeds together or nothing is saved.
This prevents half-created users or missing agreement records.

---

### D. Decline path

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Auth/RegisterController.php`

```php
session()->forget('registration_data');

return redirect()->route('register')
    ->with('info', 'You have declined the platform rules. You can register again if you change your mind.');
```

#### What this code does
Deletes the temporary registration session data and sends the user back to register.

#### Why this matters
This implements the “otherwise they are declined” part of the assignment.
No account is created.

---

## Database flow
Tables involved:
- `users`
- `onboarding_agreements`
- `groups`
- `roles`

Flow:
1. registration data is first stored in session only
2. onboarding screen is shown
3. if user agrees, `users` and `onboarding_agreements` are both written
4. if user declines, nothing is created in `users`

---

## Desktop flow

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/RegisterView.java`

```java
JsonObject body = new JsonObject();
body.addProperty("full_name", name);
body.addProperty("email", email);
body.addProperty("password", password);
body.addProperty("password_confirmation", confirm);
```

```java
return ApiClient.getInstance().post("/register", body);
```

### What this code does
The desktop registration page directly posts registration data to the API.

### Why this matters
This bypasses the web onboarding page flow.

---

### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/AuthController.php`

```php
$user = User::create([
    'full_name' => $validated['full_name'],
    'email' => $validated['email'],
    'password' => Hash::make($validated['password']),
    'role_id' => $role->id,
    'group_id' => $group->id,
]);
```

### What this code does
The API registration endpoint creates the user immediately.

### Why this matters
This means the desktop registration path does not enforce the same onboarding agreement requirement as the web flow.

---

## What to say during presentation
> “The web flow fully enforces onboarding before account creation. The user is only inserted into the database after accepting the rules. The desktop registration path is simpler and does not yet mirror the same onboarding journey.”

---

## Implementation status
- **Web/backend:** implemented
- **Desktop:** partial

---

# Requirement 6
## If one is interested in a topic and all its responses, they can view only that topic and export it to PDF

## What the requirement means
The system should let the user isolate one discussion thread and optionally export it.

---

## UI flow
### Web click path
1. User opens the feed in `smart-discussion-forum/resources/views/forum/index.blade.php`
2. User clicks one topic card or title
3. The browser goes to `GET /forum/{topic}`
4. Laravel routes that request to `ForumController@show`, which loads only that topic and only that topic’s replies
5. `ForumController@show` returns `resources/views/forum/show.blade.php`
6. If the user clicks export, the browser goes to `GET /forum/{topic}/export-pdf`
7. Laravel routes that request to `ForumController@exportPDF`
8. `exportPDF()` loads the topic thread again, writes an `export_logs` row, renders `resources/views/forum/export-pdf.blade.php`, and returns a PDF download

Exact web route -> controller map:
- `GET /forum/{topic}` -> `ForumController@show`
- `GET /forum/{topic}/export-pdf` -> `ForumController@exportPDF`

Views involved:
- `smart-discussion-forum/resources/views/forum/index.blade.php`
- `smart-discussion-forum/resources/views/forum/show.blade.php`
- `smart-discussion-forum/resources/views/forum/export-pdf.blade.php`

### Desktop click path
1. User opens Forum in `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/TopicListView.java`
2. User clicks one topic card
3. `TopicDetailView.java` loads:
   - `GET /api/v1/topics/{topicId}` -> `Api\TopicController@show`
   - `GET /api/v1/topics/{topicId}/posts` -> `Api\TopicController@posts`
4. Those API handlers return only the selected topic and its replies, so the desktop also gets a topic-only view
5. If the user opens the share/export screen, `ShareView.java` builds the export URL for `GET /api/v1/topics/{topicId}/export/pdf`
6. On the backend, that endpoint is handled by `Api\TopicController@exportPDF`
7. The desktop currently treats this more like a URL handoff than a full native file-save workflow

Exact desktop/API endpoint -> controller map:
- `GET /api/v1/topics/{topicId}` -> `Api\TopicController@show`
- `GET /api/v1/topics/{topicId}/posts` -> `Api\TopicController@posts`
- `GET /api/v1/topics/{topicId}/export/pdf` -> `Api\TopicController@exportPDF`

---

## Backend flow

### A. Topic detail only loads one topic’s replies

#### Found in
- `smart-discussion-forum/app/Http/Controllers/ForumController.php`

```php
$posts = Post::where('topic_id', $topic->id)
    ->notRemoved()
    ->visibleToUser(Auth::id())
    ->with('user')
    ->orderBy('created_at', 'asc')
    ->paginate(20);
```

#### What this code does
Loads replies for one topic, while also:
- excluding removed posts
- respecting per-user visibility
- loading authors
- ordering the thread chronologically

#### Why this matters
This is the core of the “show only chats for that topic” requirement.

---

### B. API detail does the same for desktop/client use

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/TopicController.php`

```php
$posts = $topic
    ->posts()
    ->notRemoved()
    ->visibleToUser($user->id)
    ->with('user')
    ->orderBy('created_at', 'asc')
    ->paginate(20);
```

#### What this code does
This is the API version of the same topic-detail logic.

#### Why this matters
It allows desktop and other API clients to get the same filtered thread view.

---

### C. PDF export

#### Found in
- `smart-discussion-forum/app/Http/Controllers/ForumController.php`

```php
$replies = Post::where('topic_id', $topic->id)
    ->notRemoved()
    ->visibleToUser(Auth::id())
    ->with('user')
    ->orderBy('created_at', 'asc')
    ->get();
```

#### What this code does
Before export, it loads all valid visible replies for the topic.

#### Why this matters
The PDF respects the same visibility and moderation rules as the live thread.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/ForumController.php`

```php
ExportLog::create([
    'topic_id' => $topic->id,
    'user_id' => Auth::id(),
    'file_type' => 'pdf',
]);
```

#### What this code does
Logs who exported which topic and in what format.

#### Why this matters
This adds traceability and a history of exports.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/ForumController.php`

```php
$pdf = Pdf::loadView('forum.export-pdf', [
    'topic' => $topic,
    'replies' => $replies,
    'exportedBy' => Auth::user(),
]);

return $pdf->download('topic-'.$topic->id.'.pdf');
```

#### What this code does
Renders the topic thread using a Blade view and turns it into a downloadable PDF.

#### Why this matters
This is the real export implementation.
It is not just a link or placeholder message.

---

## Database flow
Tables involved:
- `topics`
- `posts`
- `export_logs`

Flow:
1. topic detail loads from `topics` and `posts`
2. export action builds a PDF from the same topic/reply data
3. export is recorded in `export_logs`

---

## Desktop flow

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/TopicDetailView.java`

```java
JsonObject response = ApiClient.getInstance().get("/topics/" + topicId);
JsonObject topic = response.getAsJsonObject("data").getAsJsonObject("topic");
LocalStore.getInstance().cacheTopic(topic);
```

### What this code does
Loads one topic and caches it locally.

### Why this matters
It supports both online viewing and offline re-viewing.

---

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/TopicDetailView.java`

```java
JsonObject response = ApiClient.getInstance().get("/topics/" + topicId + "/posts");
JsonArray posts = ApiHelper.extractDataArray(response);
LocalStore.getInstance().cachePosts(posts);
```

### What this code does
Loads replies for the topic and stores them in the local SQLite cache.

### Why this matters
This helps the desktop app keep topic threads available offline.

---

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/TopicDetailView.java`

```java
JsonObject cached = LocalStore.getInstance().getCachedTopic(topicId);
JsonArray cached = LocalStore.getInstance().getCachedPosts(topicId);
```

### What this code does
If online loading fails, the desktop falls back to locally cached data.

### Why this matters
This makes the topic detail feature part of the offline architecture too.

---

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/ShareView.java`

```java
String pdfUrl = ApiClient.getInstance().getBaseUrl() + "/topics/" + topicId + "/export/pdf";
AlertHelper.showInfo("PDF Export", "PDF export initiated. Download should start shortly from: " + pdfUrl);
```

### What this code does
Builds the PDF export URL and shows a message to the user.

### Why this matters
It is weaker than the web flow because it does not truly handle a desktop-native file download workflow.

---

## What to say during presentation
> “Users can isolate one discussion topic and view only that thread. On the web side, the backend generates a real PDF and logs the export. The desktop supports topic detail viewing strongly, but PDF handling there is more limited.”

---

## Implementation status
- **Topic-only view:** implemented on both
- **PDF export:** strong on web/backend, partial on desktop

---

# Requirement 7
## Administrators should be able to see relevant statistics, and each group should get its own statistics

## What the requirement means
Admins need summary information about participation and activity, broken down by group.

---

## UI flow
### Web click path
1. Admin logs in and enters the admin area
2. Admin opens dashboard/statistics/group statistics pages from the admin navigation
3. The page loads precomputed statistics from backend controllers

Views:
- `resources/views/admin/dashboard.blade.php`
- `resources/views/admin/group-statistics/index.blade.php`
- `resources/views/admin/statistics/index.blade.php`

### Desktop click path
1. Admin opens desktop dashboard shell in `views/DashboardView.java`
2. Admin clicks sidebar items like Dashboard, Group Stats, or Statistics
3. Desktop opens:
   - `views/admin/AdminDashboardView.java`
   - `views/admin/GroupStatisticsView.java`
   - `views/admin/StatisticsView.java`
4. These screens call admin/statistics endpoints and display the results

---

## Backend flow

### A. Scheduled statistics generation

#### Found in
- `smart-discussion-forum/routes/console.php`

```php
Schedule::command('app:calculate-statistics')->dailyAt('02:00');
```

#### What this code does
Runs statistics calculation every day.

#### Why this matters
The stats are maintained automatically.

---

### B. Metrics per group

#### Found in
- `smart-discussion-forum/app/Console/Commands/CalculateStatistics.php`

```php
$totalMembers = User::where('group_id', $group->id)->count();
```

#### What this code does
Counts the number of members in the group.

#### Why this matters
It gives the basic size of each group.

---

#### Found in
- `smart-discussion-forum/app/Console/Commands/CalculateStatistics.php`

```php
$activeMembersThisWeek = Post::whereIn('topic_id', Topic::where('group_id', $group->id)->pluck('id'))
    ->where('created_at', '>=', now()->subWeek())
    ->distinct('user_id')
    ->count();
```

#### What this code does
Counts the distinct users who posted within the last week in that group.

#### Why this matters
It is a meaningful activity indicator.

---

#### Found in
- `smart-discussion-forum/app/Console/Commands/CalculateStatistics.php`

```php
$unansweredQuestions = Topic::where('group_id', $group->id)
    ->where('post_type', 'question')
    ->withCount('posts')
    ->get()
    ->filter(function ($topic) {
        return $topic->posts_count == 0;
    })
    ->count();
```

#### What this code does
Counts question topics with zero replies.

#### Why this matters
This tells admins how many members’ questions still need attention.

---

#### Found in
- `smart-discussion-forum/app/Console/Commands/CalculateStatistics.php`

```php
Statistics::updateOrCreate(
    ['group_id' => $group->id],
    [
        'total_members' => $totalMembers,
        'active_members_this_week' => $activeMembersThisWeek,
        'total_posts' => $totalPosts,
        'total_topics' => $totalTopics,
        'unanswered_questions' => $unansweredQuestions,
        'inactive_members_30days' => $inactiveMembers,
        'last_calculated_at' => now(),
    ]
);
```

#### What this code does
Writes the calculated group statistics into the `statistics` table.

#### Why this matters
It makes statistics cheap to display later in dashboards.

---

## Database flow
Tables involved:
- `statistics`
- `groups`
- `users`
- `topics`
- `posts`

Flow:
1. scheduled command calculates stats from users/topics/posts
2. results are stored by `group_id` in `statistics`
3. dashboards read and display those precomputed values

---

## Desktop flow

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/DashboardView.java`

```java
Button adminDashBtn   = navBtn("dashboard", "Dashboard");
Button groupStatsBtn  = navBtn("insights", "Group Stats");
Button statisticsBtn  = navBtn("bar_chart", "Statistics");
```

### What this code does
Adds statistics-related admin pages into the desktop sidebar navigation.

### Why this matters
It shows that group statistics are meant to be accessible from the desktop admin interface too.

---

## What to say during presentation
> “A daily command computes group statistics like members, posts, active users, and unanswered questions, stores them in the `statistics` table, and the admin interfaces display them.”

---

## Implementation status
- **Backend/web:** implemented
- **Desktop:** admin UI support present

---

# Requirement 8
## Chatting should be possible using both web and desktop; online users get realtime messages; offline members use the desktop app and sync later

## What the requirement means
The platform should support:
- messaging on web and desktop
- live communication when online
- offline storage on desktop
- later synchronization when internet returns

---

## UI flow
### Web click path
1. User opens the messages area in `smart-discussion-forum/resources/views/conversations/index.blade.php`
2. The browser goes to `GET /conversations`
3. Laravel routes that request to `ConversationController@index`, which loads the user’s conversations
4. When the user clicks one conversation, the browser goes to `GET /conversations/{id}`
5. Laravel routes that request to `ConversationController@show`
6. The conversation detail page can also load message history from `GET /conversations/{id}/messages`
7. When the user sends a message, the form or JS sends `POST /conversations/{id}/messages`
8. Laravel routes that request to `MessageController@store`, which validates the body, inserts the message, and triggers realtime follow-up logic

Exact web route -> controller map:
- `GET /conversations` -> `ConversationController@index`
- `GET /conversations/{id}` -> `ConversationController@show`
- `GET /conversations/{id}/messages` -> `MessageController@index`
- `POST /conversations/{id}/messages` -> `MessageController@store`

Views involved:
- `smart-discussion-forum/resources/views/conversations/index.blade.php`
- `smart-discussion-forum/resources/views/conversations/show.blade.php`
- `smart-discussion-forum/resources/views/conversations/messages.blade.php`

### Desktop click path
1. User clicks **Messages** in `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/DashboardView.java`
2. `ConversationListView.java` calls `GET /api/v1/conversations`
3. Laravel routes that request to `ConversationController@index`
4. When the user opens one conversation, the desktop loads conversation detail and message history using:
   - `GET /api/v1/conversations/{id}` -> `ConversationController@show`
   - `GET /api/v1/conversations/{id}/messages` -> `MessageController@index`
5. If the user is online and sends a message, the desktop sends `POST /api/v1/conversations/{id}/messages`
6. Laravel routes that request to `MessageController@store`
7. If the device is offline, the message is stored locally by `api/LocalStore.java`
8. When internet returns, `api/SyncEngine.java` pushes queued messages through `POST /api/v1/sync/push`
9. `SyncEngine.java` also pulls missed server-side updates through `GET /api/v1/sync/pull`
10. Laravel routes those two sync endpoints to `SyncController@push` and `SyncController@pull`

Exact desktop/API endpoint -> controller map:
- `GET /api/v1/conversations` -> `ConversationController@index`
- `GET /api/v1/conversations/{id}` -> `ConversationController@show`
- `GET /api/v1/conversations/{id}/messages` -> `MessageController@index`
- `POST /api/v1/conversations/{id}/messages` -> `MessageController@store`
- `POST /api/v1/conversations/{id}/read` -> `Api\MessageStatusController@markConversationRead`
- `POST /api/v1/sync/push` -> `SyncController@push`
- `GET /api/v1/sync/pull` -> `SyncController@pull`

---

## Backend flow

### A. Conversation list

#### Found in
- `smart-discussion-forum/app/Http/Controllers/ConversationController.php`

```php
$conversations = Conversation::forUserInGroup(auth()->user())
    ->with([
        'participants:id,full_name',
        'lastMessage:id,conversation_id,body,created_at',
    ])
    ->orderByDesc('last_activity_at')
    ->paginate(20);
```

#### What this code does
Loads the conversations the user belongs to, plus participant names and last message preview.

#### Why this matters
This is the standard chat-list behavior needed in both web and desktop UIs.

---

### B. Message sending

#### Found in
- `smart-discussion-forum/app/Http/Controllers/MessageController.php`

```php
$validated = $request->validate([
    'body' => 'required|string|max:10000',
]);
```

#### What this code does
Validates the message body before saving it.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/MessageController.php`

```php
$message = $conversation->messages()->create([
    'sender_id' => auth()->id(),
    'body' => $validated['body'],
])->load('sender:id,full_name');
```

#### What this code does
Creates the message and loads sender information.

#### Why this matters
This is the central online chat write operation.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/MessageController.php`

```php
app(MessageEventManager::class)->messageSent($message);
```

#### What this code does
Triggers follow-up event handling after a message is sent.

#### Why this matters
This supports live/realtime behavior for online participants.

---

### C. Offline sync backend

#### Found in
- `smart-discussion-forum/app/Http/Controllers/SyncController.php`

```php
$checkpoint = SyncCheckpoint::firstOrCreate(
    ['user_id' => $user->id, 'device_id' => $validated['device_id']],
    ['last_synced_at' => now()->subYear()],
);
```

#### What this code does
Creates or retrieves a sync checkpoint for a specific user+device pair.

#### Why this matters
This is how the server remembers what that device has already synchronized.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/SyncController.php`

```php
return response()->json([
    'success' => true,
    'data' => [
        'conversations' => $updatedConversations,
        'messages' => $newMessages,
        'status_updates' => $statusUpdates,
        'synced_at' => now()->toIso8601String(),
    ],
]);
```

#### What this code does
Returns only changes since the last checkpoint.

#### Why this matters
This is what makes incremental sync possible instead of full re-downloads every time.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/SyncController.php`

```php
$validated = $request->validate([
    'messages' => 'required|array|max:100',
    'messages.*.client_id' => 'required|string|max:255',
    'messages.*.conversation_id' => 'required|integer|exists:conversations,id',
    'messages.*.body' => 'required|string|max:10000',
]);
```

#### What this code does
Validates a batch of offline-composed messages from the desktop client.

#### Why this matters
The backend is explicitly designed to receive queued offline chat data.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/SyncController.php`

```php
$existing = Message::where('conversation_id', $conversation->id)
    ->where('sender_id', $user->id)
    ->where('body', $msg['body'])
    ->where('created_at', '>', now()->subMinutes(5))
    ->first();
```

#### What this code does
Looks for a recently saved identical message from the same sender in the same conversation.

#### Why this matters
This deduplication protects against retries creating duplicate chat messages.

---

## Database flow
Tables involved:
- `conversations`
- `conversation_participants`
- `messages`
- `message_status`
- `sync_checkpoints`

Flow:
1. web/desktop online users create conversations and messages in backend tables
2. desktop offline actions are queued locally
3. sync endpoints upload queued messages and download deltas
4. server tracks device sync state in `sync_checkpoints`

---

## Desktop flow

### A. Local SQLite store

#### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/api/LocalStore.java`

```java
connection = DriverManager.getConnection("jdbc:sqlite:" + dir.resolve("local.db"));
createTables();
```

#### What this code does
Opens a local SQLite database on the desktop machine.

#### Why this matters
This is the persistence foundation for offline mode.

---

#### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/api/LocalStore.java`

```sql
CREATE TABLE IF NOT EXISTS outbound_queue (...)
CREATE TABLE IF NOT EXISTS conversations (...)
CREATE TABLE IF NOT EXISTS messages (...)
CREATE TABLE IF NOT EXISTS topics (...)
CREATE TABLE IF NOT EXISTS posts (...)
CREATE TABLE IF NOT EXISTS outbound_replies (...)
```

#### What this code does
Creates local tables for:
- queued offline chat messages
- cached conversations
- cached chat messages
- cached forum topics
- cached forum replies
- queued offline forum replies

#### Why this matters
It proves the offline mode is real, not just conceptual.

---

### B. Background sync engine

#### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/api/SyncEngine.java`

```java
syncService.setPeriod(Duration.seconds(30));
```

#### What this code does
Runs the background sync cycle every 30 seconds.

#### Why this matters
It gives regular sync opportunities without requiring the user to manually refresh.

---

#### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/api/SyncEngine.java`

```java
boolean flushed = pushOfflineMessages();
boolean repliesFlushed = pushOfflineReplies();
JsonObject pullData = pullNewData();
```

#### What this code does
Each sync cycle does three main things:
1. send offline chat messages
2. send offline forum replies
3. pull new server updates

#### Why this matters
This is the core logic that fulfills the offline/online synchronization requirement.

---

### C. Desktop conversation browsing

#### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/ConversationListView.java`

```java
JsonObject response = ApiClient.getInstance().get("/conversations");
JsonArray convs = ApiHelper.extractDataArray(response);
LocalStore.getInstance().cacheConversations(convs);
```

#### What this code does
When online, the desktop loads conversations from the server and stores a local snapshot.

#### Why this matters
The conversation list stays available later when offline.

---

#### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/ConversationListView.java`

```java
JsonArray cached = LocalStore.getInstance().getCachedConversations();
```

#### What this code does
If the server request fails, the desktop loads conversations from SQLite instead.

#### Why this matters
This is how offline members still access saved information.

---

## What to say during presentation
> “The backend stores all chat data centrally. The desktop app adds SQLite caching and a sync engine, so offline users can still browse saved data and later synchronize when internet returns.”

---

## Implementation status
- **Backend/web:** implemented
- **Desktop:** implemented strongly for offline architecture

---

# Requirement 9
## Lecturers want marks for participation depending on set criteria

## What the requirement means
The system should convert meaningful academic/forum activity into participation marks.

---

## UI flow
### Web click path
1. A lecturer/admin opens the participation page from the web interface
2. Browser loads the participation overview route
3. The backend returns student participation summaries

View:
- `resources/views/participation/students.blade.php`

### Desktop click path
1. Lecturer/admin clicks **Participation** in `views/DashboardView.java`
2. Desktop opens `views/ParticipationView.java`
3. That screen calls `GET /api/v1/participation/students`
4. Returned counts and totals are displayed in a table

---

## Backend flow

### A. Participation activity records

#### Found in
- `smart-discussion-forum/app/Services/ParticipationService.php`

```php
ParticipationActivity::firstOrCreate(
    [
        'user_id' => $user->id,
        'activity_type' => ParticipationActivity::TYPE_DAILY_LOGIN,
        'activity_date' => today(),
    ],
    ['points' => $this->pointsFor(ParticipationActivity::TYPE_DAILY_LOGIN)]
);
```

#### What this code does
Awards the daily login participation record once per day.

#### Why this matters
Daily activity contributes to marks, but duplicate logins on the same day do not inflate the score.

---

#### Found in
- `smart-discussion-forum/app/Services/ParticipationService.php`

```php
ParticipationActivity::firstOrCreate(
    [
        'user_id' => $user->id,
        'activity_type' => ParticipationActivity::TYPE_TOPIC_CREATED,
        'subject_type' => Topic::class,
        'subject_id' => $topic->id,
    ],
    [
        'points' => $this->pointsFor(ParticipationActivity::TYPE_TOPIC_CREATED),
        'activity_date' => today(),
    ]
);
```

#### What this code does
Awards participation marks for creating a topic.

#### Why this matters
Forum contribution becomes measurable.

---

#### Found in
- `smart-discussion-forum/app/Services/ParticipationService.php`

```php
ParticipationActivity::firstOrCreate(
    [
        'user_id' => $user->id,
        'activity_type' => ParticipationActivity::TYPE_REPLY_POSTED,
        'subject_type' => Post::class,
        'subject_id' => $post->id,
    ],
    [
        'points' => $this->pointsFor(ParticipationActivity::TYPE_REPLY_POSTED),
        'activity_date' => today(),
    ]
);
```

#### What this code does
Awards participation marks for replying.

#### Why this matters
This rewards meaningful engagement, not just posting new topics.

---

#### Found in
- `smart-discussion-forum/app/Services/ParticipationService.php`

```php
ParticipationActivity::firstOrCreate(
    [
        'user_id' => $user->id,
        'activity_type' => ParticipationActivity::TYPE_QUIZ_COMPLETED,
        'subject_type' => Quiz::class,
        'subject_id' => $quiz->quiz_id,
    ],
    [
        'points' => $this->pointsFor(ParticipationActivity::TYPE_QUIZ_COMPLETED),
        'activity_date' => today(),
    ]
);
```

#### What this code does
Awards participation marks for quiz completion.

#### Why this matters
It connects the forum and academic activity into one participation score.

---

### B. Participation is triggered during real actions

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/AuthController.php`

```php
app(ParticipationService::class)->recordDailyLogin($user);
```

#### What this code does
Gives the login participation point when a user logs in.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/TopicController.php`

```php
app(ParticipationService::class)->recordTopicCreated($user, $topic);
```

#### What this code does
Awards the topic-creation participation point at the time the topic is created.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/PostController.php`

```php
app(ParticipationService::class)->recordReplyPosted($user, $post);
```

#### What this code does
Awards the reply participation point at the time the reply is posted.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/StudentQuizController.php`

```php
app(ParticipationService::class)->recordQuizCompleted($attempt->student, $quiz);
```

#### What this code does
Awards participation when a quiz is completed.

#### Why all of these matter
They show participation marks are integrated into normal user actions rather than being calculated manually later.

---

## Database flow
Tables involved:
- `participation_activities`
- `users`
- `statistics`

Flow:
1. user logs in / creates topic / replies / completes quiz
2. `ParticipationService` inserts a participation activity row
3. lecturer/admin views summarize totals and counts

---

## Desktop flow

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/ParticipationView.java`

```java
return ApiClient.getInstance().get("/participation/students");
```

### What this code does
Loads participation summaries for students from the backend.

---

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/ParticipationView.java`

```java
weightsRow.getChildren().addAll(
        weightBadge("Quiz completed", weights, "quiz_completed"),
        weightBadge("Topic created", weights, "topic_created"),
        weightBadge("Reply posted", weights, "reply_posted"),
        weightBadge("Daily login", weights, "daily_login")
);
```

### What this code does
Shows the participation scoring criteria as visible weight badges in the UI.

### Why this matters
It makes the marking scheme transparent to lecturers/admins.

---

## What to say during presentation
> “Participation marks are recorded automatically when users log in, create topics, reply, and complete quizzes. These activities are stored in `participation_activities` and summarized for academic staff.”

---

## Implementation status
- **Backend/web:** implemented
- **Desktop:** implemented

---

# Requirement 10
## Lecturers want quizzes with time, date, duration, target category, announcements, restrictions, auto-submit, no extra time for late joiners, and reports

## What the requirement means
This is a full quiz subsystem including scheduling, student targeting, timing, attempt control, grading, and reporting.

---

## UI flow
### Web click path
#### Lecturer flow
1. Lecturer opens the quiz module in `smart-discussion-forum/resources/views/quizzes/index.blade.php`
2. The browser goes to `GET /quizzes`, which Laravel routes to `QuizController@index`
3. If the lecturer clicks **Create Quiz**, the browser goes to `GET /quizzes/create` -> `QuizController@create`
4. When the create form is submitted, the browser sends `POST /quizzes` -> `QuizController@store`
5. When questions are added, the browser sends `POST /quizzes/{quiz}/questions` -> `QuestionController@store`
6. When the lecturer publishes the quiz, the browser sends `POST /quizzes/{quiz}/publish` -> `QuizController@publish`
7. Later, if the lecturer wants results, the browser opens `GET /quizzes/{quiz}/report` -> `QuizController@showPerformanceReport`

#### Student flow
1. Student opens `GET /my-quizzes` -> `StudentQuizController@index`
2. Before the quiz opens, the student can open `GET /quizzes/{quiz}/announcement` -> `StudentQuizController@showAnnouncement`
3. When the quiz is live, the attempt screen opens with `GET /quizzes/{quiz}/attempt` -> `StudentQuizController@showQuiz`
4. Each answer selection sends `POST /quizzes/{quiz}/answer` -> `StudentQuizController@saveAnswer`
5. Clicking submit sends `POST /quizzes/{quiz}/submit` -> `StudentQuizController@submitQuiz`
6. If the timer expires first, frontend polling/JS triggers `POST /quizzes/{quiz}/auto-submit` -> `StudentQuizController@autoSubmit`
7. Status polling can use `GET /quizzes/{quiz}/status` -> `StudentQuizController@getStatus`
8. Results can be shown through `GET /quizzes/{quiz}/result` -> `StudentQuizController@showResult`

Relevant views:
- `smart-discussion-forum/resources/views/quizzes/index.blade.php`
- `smart-discussion-forum/resources/views/quizzes/create.blade.php`
- `smart-discussion-forum/resources/views/quizzes/edit.blade.php`
- `smart-discussion-forum/resources/views/quizzes/student-index.blade.php`
- `smart-discussion-forum/resources/views/quizzes/announcement.blade.php`
- `smart-discussion-forum/resources/views/quizzes/attempt.blade.php`
- `smart-discussion-forum/resources/views/quizzes/result.blade.php`
- `smart-discussion-forum/resources/views/quizzes/performance-report.blade.php`

### Desktop click path
1. User opens `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/QuizListView.java`
2. Desktop loads quiz lifecycle lists through:
   - `GET /api/v1/quizzes/live` -> `Api\QuizNotificationController@live`
   - `GET /api/v1/quizzes/upcoming` -> `Api\QuizNotificationController@upcoming`
   - `GET /api/v1/me/quiz-history` -> `Api\QuizNotificationController@history`
3. If the user opens one quiz, `QuizAttemptView.java` loads announcement/timing info using `GET /api/v1/quizzes/{quiz}/announcement` -> `Api\StudentQuizController@announcement`
4. To actually create or resume an attempt, the API design uses:
   - `POST /api/v1/quizzes/{quiz}/attempt` -> `Api\StudentQuizController@start`
   - `GET /api/v1/quizzes/{quiz}/attempt` -> `Api\StudentQuizController@showAttempt`
5. As the user answers, the desktop should call `POST /api/v1/quizzes/{quiz}/answer` -> `Api\StudentQuizController@saveAnswer`
6. Normal submission uses `POST /api/v1/quizzes/{quiz}/submit` -> `Api\StudentQuizController@submit`
7. Timeout submission uses `POST /api/v1/quizzes/{quiz}/auto-submit` -> `Api\StudentQuizController@autoSubmit`
8. Results can later be requested from `GET /api/v1/quizzes/{quiz}/result` -> `Api\GradeController@myResult`
9. The desktop quiz flow exists structurally, but some response-shape mismatches still need caution during presentation

---

## Backend flow

### A. Quiz configuration and creation

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/QuizController.php`

```php
$validated = $request->validate([
    'title' => 'required|string|max:255',
    'description' => 'nullable|string|max:1000',
    'target_category' => ['required', Rule::in(['Student', 'Lecturer', 'Administrator', 'Member'])],
    'group_id' => $groupRequired ? 'required|integer|exists:groups,id' : 'nullable|integer|exists:groups,id',
    'scheduled_date' => 'required|date|after_or_equal:today',
    'start_time' => 'required|date_format:H:i',
    'duration_minutes' => 'required|integer|min:1|max:480',
]);
```

#### What this code does
Validates all important quiz setup fields.

#### Why this matters
This is the pre-publication quiz configuration stage from the assignment.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/QuizController.php`

```php
QuizConfiguration::create([
    'quiz_id' => $quiz->quiz_id,
    'allow_late_join' => false,
    'notification_minutes_before' => 15,
    'lock_screen_on_start' => true,
    'show_results_after_close' => true,
    'show_correct_answers' => false,
]);
```

#### What this code does
Creates default runtime behavior for the quiz.

#### Why this matters
This stores quiz behavior such as late join policy, reminder window, and result visibility.

---

### B. Publish only valid quizzes

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/QuizController.php`

```php
if ($quiz->questions()->count() === 0) {
    return response()->json([
        'success' => false,
        'message' => 'Cannot publish a quiz with no questions',
    ], 422);
}
```

#### What this code does
Prevents publishing an empty quiz.

#### Why this matters
A quiz announcement should only exist if there is a real quiz to take.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/QuizController.php`

```php
$scheduledDateTime = $quiz->getScheduledDateTime();
if ($scheduledDateTime->isPast()) {
    return response()->json([
        'success' => false,
        'message' => 'Cannot publish a quiz with a past scheduled date/time',
    ], 422);
}
```

#### What this code does
Prevents publishing a quiz whose schedule is already in the past.

#### Why this matters
This enforces “configuration must be set before actual quiz time.”

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/QuizController.php`

```php
$quiz->update(['published_at' => now()]);
event(new QuizPublished($quiz));
```

#### What this code does
Marks the quiz as published and emits an event.

#### Why this matters
Publishing is the point where the quiz becomes an announced future activity.

---

### C. Quiz reminders and activation

#### Found in
- `smart-discussion-forum/app/Console/Commands/SendQuizReminders.php`

```php
$notificationWindow = $quiz->configuration?->notification_minutes_before ?? 15;
```

#### What this code does
Reads how many minutes before the quiz reminders should be sent.

---

#### Found in
- `smart-discussion-forum/app/Console/Commands/SendQuizReminders.php`

```php
Notification::create([
    'user_id' => $user->id,
    'type' => 'quiz_reminder',
    'data' => [
        'quiz_id' => $quiz->quiz_id,
        'title' => $quiz->title,
        'minutes_until_start' => $minutesUntilStart,
    ],
    'read_at' => null,
]);
```

#### What this code does
Creates reminder notifications for eligible users.

#### Why this matters
This is the automated announcement/reminder part of the requirement.

---

#### Found in
- `smart-discussion-forum/app/Console/Commands/ActivateQuizzes.php`

```php
if ($now->isAfter($scheduledTime)) {
    $quiz->update(['is_active' => true]);
    QuizWentLive::dispatch($quiz);
}
```

#### What this code does
When the scheduled time is reached, the quiz becomes active and a “quiz went live” event is dispatched.

#### Why this matters
This is what makes the quiz appear when it is supposed to open.

---

### D. Student attempt rules and no extra time for late joiners

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/StudentQuizController.php`

```php
$gracePeriodMinutes = 5;
$minutesLate = $scheduledTime->diffInMinutes($now, false);
$isSignificantlyLate = $minutesLate > $gracePeriodMinutes;

if ($isSignificantlyLate && ! $quiz->configuration?->allow_late_join) {
    return response()->json([
        'success' => false,
        'message' => 'Late joining is not allowed for this quiz.',
    ], 403);
}
```

#### What this code does
Determines whether the student is joining too late and blocks them if late joining is disabled.

#### Why this matters
This implements the policy side of late-entry control.

---

#### Found in
- `smart-discussion-forum/app/Models/Quiz.php`

```php
public function secondsRemainingFor(StudentAttempt $attempt, ?Carbon $now = null): int
{
    $now ??= now();

    $personalDeadline = $attempt->start_time->copy()->addMinutes($this->duration_minutes);
    $deadline = $personalDeadline->min($this->getScheduledEndDateTime());

    return (int) max(0, $now->diffInSeconds($deadline, false));
}
```

#### What this code does
Calculates remaining time using the earlier of:
- the student’s personal duration window
- the quiz’s official end time

#### Why this matters
This is the exact logic that ensures late joiners are not given extra time.

---

### E. Saving answers and submission

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/StudentQuizController.php`

```php
StudentAnswer::where('attempt_id', $attempt->attempt_id)
    ->where('question_id', $request->question_id)
    ->delete();

if ($request->answer_id) {
    StudentAnswer::create([
        'attempt_id' => $attempt->attempt_id,
        'question_id' => $request->question_id,
        'selected_answer_id' => $request->answer_id,
    ]);
}
```

#### What this code does
Removes any previous answer for that question, then stores the new one.

#### Why this matters
This supports answer updates and auto-saving behavior during a live attempt.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/StudentQuizController.php`

```php
$attempt->update([
    'submit_time' => now(),
    'is_auto_submit' => false,
]);

$this->gradeQuiz($attempt);
```

#### What this code does
Handles manual submission and then grades the attempt.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/StudentQuizController.php`

```php
$attempt->update([
    'submit_time' => now(),
    'is_auto_submit' => true,
]);

$this->gradeQuiz($attempt);
```

#### What this code does
Handles forced submission when time expires and then grades the attempt.

#### Why this matters
These two blocks implement both normal submit and auto-submit on timeout.

---

### F. Grading and performance report

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/StudentQuizController.php`

```php
$correctAnswer = $question->answers->firstWhere('is_correct', true);

if ($correctAnswer && (int) $studentAnswer->selected_answer_id === (int) $correctAnswer->answer_id) {
    $totalScore += $question->marks;
}
```

#### What this code does
Checks whether the student picked the correct answer and adds marks if they did.

#### Why this matters
This is the main scoring rule.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/StudentQuizController.php`

```php
Grade::updateOrCreate(
    ['attempt_id' => $attempt->attempt_id],
    [
        'student_id' => $attempt->student_id,
        'quiz_id' => $quiz->quiz_id,
        'total_score' => $totalScore,
        'max_score' => $maxScore,
        'percentage' => $percentage,
        'participation_mark' => $participationMark,
        'final_grade' => $finalGrade,
        'graded_at' => now(),
    ]
);
```

#### What this code does
Creates or updates the grade record for the quiz attempt.

#### Why this matters
This is how the system stores student performance reports.

---

## Database flow
Tables involved:
- `quizzes`
- `quiz_configuration`
- `questions`
- `answers`
- `student_attempts`
- `student_answers`
- `grades`
- `notifications`

Flow:
1. lecturer configures and publishes quiz
2. reminders are generated before start
3. scheduler activates quiz at the right time
4. student starts attempt
5. answers are saved
6. submission or auto-submission occurs
7. grading writes into `grades`
8. reports can be shown later

---

## Desktop flow

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/QuizListView.java`

```java
JsonArray live = ApiHelper.extractDataArray(ApiClient.getInstance().get("/quizzes/live"));
JsonArray upcoming = ApiHelper.extractDataArray(ApiClient.getInstance().get("/quizzes/upcoming"));
JsonArray history = ApiHelper.extractDataArray(ApiClient.getInstance().get("/me/quiz-history"));
```

### What this code does
Loads live quizzes, upcoming quizzes, and quiz history for students.

### Why this matters
It shows the desktop app was designed to expose the same quiz lifecycle states.

---

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/QuizAttemptView.java`

```java
JsonObject announcementResponse = ApiClient.getInstance().get("/quizzes/" + currentQuizId + "/announcement");
JsonObject attemptResponse = ApiClient.getInstance().get("/quizzes/" + currentQuizId + "/attempt");
```

### What this code does
The desktop attempt view tries to load both the announcement/timing info and the current attempt state.

### Important limitation
The desktop code expects response shapes that do not perfectly match the current backend JSON structure.

### Why this matters
The backend quiz module is strong, but the desktop attempt implementation should be presented more cautiously unless already runtime-tested by the team.

---

## What to say during presentation
> “The quiz backend supports configuration, announcement, scheduled activation, late-join control, timed attempts, auto-submit, grading, and reports. The desktop has the quiz screens too, but the backend is the strongest place to demonstrate the full quiz logic.”

---

## Implementation status
- **Backend/web:** strongly implemented
- **Desktop:** structurally implemented, but some API-field mismatches exist

---

# Requirement 11
## Using machine learning, the system should automatically classify topics and recommend topics based on past engagements

## What the requirement means
The system should automatically organize topics and suggest relevant ones to users.

---

## UI flow
### Web click path
1. User opens recommendations area or dashboard recommendation widget
2. Backend loads personalized recommendations for that user
3. User can click one recommended topic to open it

### Desktop click path
1. User opens `views/RecommendationView.java`
2. Desktop calls `GET /api/v1/recommendations?limit=10`
3. Recommended topics are displayed as cards
4. Clicking a card opens the related topic detail page

---

## Backend flow

### A. Classification runs automatically when a topic is created

#### Found in
- `smart-discussion-forum/app/Models/Topic.php`

```php
protected static function booted()
{
    static::created(function ($topic) {
        app(TopicClassificationService::class)->classifyTopic($topic);
    });
}
```

#### What this code does
Whenever a topic is inserted into the database, Laravel automatically calls the classification service.

#### Why this matters
Classification is automatic, not manual.

---

### B. Keyword-based category scoring

#### Found in
- `smart-discussion-forum/app/Services/TopicClassificationService.php`

```php
private $categoryKeywords = [
    'Django' => ['django', 'python', 'framework', 'views', 'models', 'templates'],
    'APIs' => ['api', 'rest', 'endpoint', 'http', 'json', 'request'],
    'Database' => ['database', 'sql', 'query', 'table', 'column', 'join', 'relational'],
    'JavaScript' => ['javascript', 'js', 'react', 'vue', 'node', 'npm'],
    'CSS' => ['css', 'styling', 'bootstrap', 'tailwind', 'design', 'layout'],
    'General' => [],
];
```

#### What this code does
Defines the default keyword vocabulary for each topic category.

#### Why this matters
This is the classifier’s knowledge base.

---

#### Found in
- `smart-discussion-forum/app/Services/TopicClassificationService.php`

```php
foreach ($keywords as $keyword) {
    $score += substr_count($text, $keyword);
}
```

#### What this code does
Counts keyword matches in the topic text for each category.

#### Why this matters
This is the actual classification scoring step.

---

#### Found in
- `smart-discussion-forum/app/Services/TopicClassificationService.php`

```php
arsort($scores);
$bestCategory = array_key_first($scores);
```

#### What this code does
Sorts category scores descending and picks the highest one.

#### Why this matters
This is how the final category is chosen.

---

#### Found in
- `smart-discussion-forum/app/Services/TopicClassificationService.php`

```php
$confidence = $totalMatches > 0
    ? (int) round($scores[$bestCategory] / $totalMatches * 100)
    : 0;
```

#### What this code does
Calculates how confident the classifier is.

#### Why this matters
This helps distinguish strong vs weak classifications.

---

#### Found in
- `smart-discussion-forum/app/Services/TopicClassificationService.php`

```php
$reviewThreshold = (int) SystemConfig::getValue('classification_review_threshold', 40);
$needsReview = $confidence < $reviewThreshold;
```

#### What this code does
Flags low-confidence topics for review.

#### Why this matters
This improves trustworthiness of automatic classification.

---

### C. Personalized recommendations

#### Found in
- `smart-discussion-forum/app/Services/RecommendationService.php`

```php
$recommendations = Topic::whereIn('category_id', $userEngagedCategoryIds)
    ->where('status', 'active')
    ->when(! $user->isSystemAdmin(), fn ($q) => $q->whereIn('group_id', $user->accessibleGroupIds()))
    ->whereNotIn('id', function ($q) use ($user) {
        $q->select('topic_id')->from('posts')->where('user_id', $user->id);
    })
    ->whereNotIn('id', function ($q) use ($user) {
        $q->select('topic_id')->from('recommendation_log')->where('user_id', $user->id);
    })
    ->with('creator')
    ->with('category')
    ->withCount('posts')
    ->orderBy('created_at', 'desc')
    ->limit($limit)
    ->get();
```

#### What this code does
This is the main recommendation query. It builds a list of recommended topics by:
1. keeping only topics in categories the user engaged with before
2. keeping only active topics
3. restricting results to groups the user may access
4. excluding topics the user already posted in
5. excluding topics already recommended before
6. eager-loading creator and category
7. adding reply counts
8. sorting by newest
9. limiting the final number returned

#### Why this matters
This is the real personalization logic behind recommendations.
It is not random; it is based on user history and category similarity.

---

#### Found in
- `smart-discussion-forum/app/Services/RecommendationService.php`

```php
$topic->relevance_score = (int) round(
    ($engagementByCategory[$topic->category_id] ?? 0) / max(1, $totalEngagement) * 100,
);
```

#### What this code does
Calculates how relevant each recommended topic is, based on how much the user engaged with that category before.

#### Why this matters
This gives the recommendation system a measurable relevance score.

---

#### Found in
- `smart-discussion-forum/app/Services/RecommendationService.php`

```php
RecommendationLog::updateOrCreate(
    ['user_id' => $user->id, 'topic_id' => $topic->id],
    [
        'group_id' => $topic->group_id,
        'recommended_at' => now(),
        'reason' => $topic->recommendation_reason,
        'relevance_score' => $topic->relevance_score,
    ],
);
```

#### What this code does
Stores the recommendation in the log so the same topic is not recommended repeatedly.

#### Why this matters
This keeps recommendations fresh and less repetitive.

---

## Database flow
Tables involved:
- `topics`
- `topic_categories`
- `posts`
- `recommendation_log`
- `system_configs`

Flow:
1. topic is created
2. classifier assigns category and confidence metadata
3. recommendation service looks at user engagement history
4. matching topics are selected and logged in `recommendation_log`

---

## Desktop flow

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/RecommendationView.java`

```java
JsonObject response = ApiClient.getInstance().get("/recommendations?limit=10");
return ApiHelper.extractDataArray(response);
```

### What this code does
The desktop recommendation page fetches recommended topics from the backend.

### Why this matters
This shows the recommendation engine is exposed on both interfaces.

---

## What to say during presentation
> “Topic classification happens automatically when a topic is created. Recommendations are then built from the user’s past engagement categories and filtered to topics they have not already interacted with.”

### Important honesty line
> “It is best described as an intelligent rule-based recommendation and classification system rather than advanced trained machine learning.”

---

## Implementation status
- **Backend/web:** implemented
- **Desktop:** implemented for viewing recommendations
- **Honesty note:** not advanced ML in the strict academic sense

---

# Requirement 12
## One should be able to forward a post to social media platforms of choice

## What the requirement means
Users should be able to share discussion content externally.

---

## UI flow
### Web click path
1. User opens a topic in `smart-discussion-forum/resources/views/forum/show.blade.php`
2. User clicks the share action on that topic page
3. The browser submits `POST /topics/{topic}/share`
4. Laravel routes that request to `ForumController@shareTopic`
5. `shareTopic()` validates `expires_in_days`, checks group access, then generates a temporary signed URL
6. The controller sends that signed URL back to the previous page in flash data as `share_url`
7. The user can then copy that link into WhatsApp, email, Facebook, X, or another platform
8. When the recipient opens the link, the browser goes to `GET /shared/topic/{topic}/{signedUserId}?expires=...&signature=...`
9. Laravel routes that request to `SharedTopicController@show`, which verifies the signature and then renders the shared topic view

Exact web route -> controller map:
- `POST /topics/{topic}/share` -> `ForumController@shareTopic`
- `GET /shared/topic/{topic}/{signedUserId}` -> `SharedTopicController@show`

Relevant views:
- `smart-discussion-forum/resources/views/forum/show.blade.php`
- `smart-discussion-forum/resources/views/forum/shared-topic.blade.php`

### Desktop click path
1. User opens `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/ShareView.java` from the topic screen
2. The intended API flow is `POST /api/v1/topics/{topicId}/share`
3. Laravel routes that request to `Api\TopicController@share`
4. The backend validates the expiry window, generates a signed API link, and returns it in the JSON response
5. If the shared link is opened later, the API access route is `GET /api/v1/topics/{topicId}/shared?...signature...`
6. Laravel routes that request to `Api\TopicController@sharedAccess`
7. The current desktop code sends fields that do not match the backend contract exactly, so the conceptual flow is correct but the shipped desktop mapping is incomplete

Exact desktop/API endpoint -> controller map:
- `POST /api/v1/topics/{topicId}/share` -> `Api\TopicController@share`
- `GET /api/v1/topics/{topicId}/shared` -> `Api\TopicController@sharedAccess`

---

## Backend flow

### A. Generate time-limited share link

#### Found in
- `smart-discussion-forum/app/Http/Controllers/ForumController.php`

```php
$validated = $request->validate([
    'expires_in_days' => 'required|integer|min:1|max:7',
]);
```

#### What this code does
Validates how long the shared link should stay active.

#### Why this matters
The link is intentionally temporary for safety.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/ForumController.php`

```php
$signedUrl = URL::temporarySignedRoute('shared.topic.show', $expires, [
    'topic' => $topic->id,
    'signedUserId' => Auth::id(),
]);
```

#### What this code does
Generates a temporary signed URL for the shared topic.

#### Why this matters
This is the actual sharing mechanism.
Users can forward this link through WhatsApp, email, Facebook, X, or any platform they choose.

---

### B. API share link version

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/TopicController.php`

```php
$validated = $request->validate([
    'expires_in' => 'sometimes|integer|min:1|max:10080',
]);
```

#### What this code does
Validates the share-link lifetime in minutes for API clients.

---

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/TopicController.php`

```php
$signedUrl = \URL::temporarySignedRoute(
    'topics.share.access',
    now()->addMinutes($expiresInMinutes),
    ['topicId' => $topic->id],
);
```

#### What this code does
Creates a temporary signed API share link.

#### Why this matters
This exposes sharing to API/desktop clients as well.

---

### C. Validate shared access

#### Found in
- `smart-discussion-forum/app/Http/Controllers/Api/TopicController.php`

```php
if (! $request->hasValidSignature()) {
    return response()->json([
        'message' => 'Invalid or expired share link.',
    ], 403);
}
```

#### What this code does
Rejects invalid or expired share URLs.

#### Why this matters
It keeps shared access controlled and secure.

---

## Database flow
Tables involved:
- `topics`
- `audit_logs`
- `export_logs` (related export history, though not the same as sharing)

Flow:
1. user requests a share link
2. backend generates a signed temporary URL
3. URL is shared externally
4. recipient can access content only while the signature remains valid

---

## Desktop flow

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/ShareView.java`

```java
body.addProperty("social_media", socialMedia);
body.addProperty("embed_allowed", embed);
if (expireDate != null) {
    body.addProperty("expires_at", expireDate.toString());
}
```

### What this code does
This is the payload the desktop app sends when trying to generate a share link.

### Why this matters
It reveals a mismatch with the backend API.
The backend expects `expires_in`, not these fields.

---

### Found in
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/views/ShareView.java`

```java
String shareLink = response.get("share_link").getAsString();
```

### What this code does
This expects a `share_link` field at the root of the response.

### Why this matters
The current backend returns `data.url`, so the desktop implementation is not aligned with the API contract.

---

## What to say during presentation
> “Sharing is implemented as secure time-limited signed links that can be forwarded through any social platform. The backend and web flow support this clearly. The desktop share screen exists, but its API mapping still needs alignment.”

---

## Implementation status
- **Backend/web:** implemented
- **Desktop:** partial/inconsistent

---

# 4. Main database tables by feature area

## Forum and moderation
- `topics`
- `posts`
- `post_visibility`
- `reports`
- `export_logs`
- `audit_logs`
- `topic_categories`
- `recommendation_log`

## Users and access
- `users`
- `roles`
- `groups`
- `group_admins`
- `lecturer_group_access`
- `onboarding_agreements`

## Notifications and sanctions
- `notifications`
- `warnings`
- `blacklist_records`
- `system_configs`

## Chat and sync
- `conversations`
- `conversation_participants`
- `messages`
- `message_status`
- `sync_checkpoints`

## Quizzes and academic tracking
- `quizzes`
- `quiz_configuration`
- `questions`
- `answers`
- `student_attempts`
- `student_answers`
- `grades`
- `participation_activities`
- `statistics`

---

# 5. Honest limitations found in code review

## Strongest areas to demo
- backend forum flow
- question notifications
- warning/blacklist automation
- participation marks
- statistics generation
- backend quiz logic
- desktop offline cache/sync architecture

## Areas to present carefully
- desktop report payload mismatch
- desktop post visibility placeholder logic
- desktop share request/response mismatch
- desktop forum reply payload mismatch (`body` vs backend `content`)
- desktop quiz response-field mismatches
- desktop onboarding weaker than web onboarding

## Safest wording
> “The Laravel backend and web app contain the most complete business logic implementation. The desktop app strongly demonstrates the shared architecture and offline design, though some desktop screens still need API contract alignment for complete parity.”

---

# 6. Best files to show during a live code walkthrough

## Forum / notifications
- `app/Http/Controllers/Api/TopicController.php`
- `app/Http/Controllers/Api/PostController.php`
- `app/Models/Post.php`

## Warnings / blacklist
- `app/Console/Commands/MonitorMemberActivity.php`
- `app/Http/Controllers/Api/AuthController.php`

## Participation
- `app/Services/ParticipationService.php`

## Recommendations / classification
- `app/Services/TopicClassificationService.php`
- `app/Services/RecommendationService.php`

## Desktop offline sync
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/api/LocalStore.java`
- `smart-discussion-forum-desktop/src/main/java/com/yourforum/api/SyncEngine.java`

## Quizzes
- `app/Http/Controllers/Api/QuizController.php`
- `app/Http/Controllers/Api/StudentQuizController.php`
- `app/Models/Quiz.php`

---

# 7. Final conclusion

The most accurate and defensible explanation of the project is:

- The **Laravel backend** is the center of the system and contains the strongest implementation of the assignment requirements.
- The **web interface** is the best place to demo the most complete end-to-end flows.
- The **desktop app** is strongest in showing that the same backend can support a second interface with offline caching and synchronization.
- Some desktop screens are complete, while others are partially implemented or need API contract alignment.

That is the clearest and safest way to explain the project during presentation or examiner questioning.
