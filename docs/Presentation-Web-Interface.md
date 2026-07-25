# Presentation Guide — Web Interface (Laravel)

> Use this document to prepare for your presentation. Read it the night before.
> The system is live at: https://smart-discussion-forum-g23.onrender.com
>
> **This document focuses ONLY on the web interface.**
> Each section shows: the files involved, the step-by-step code logic, and how data flows from database to screen.

---

## Demo Credentials

| Role | Email | Password |
|---|---|---|
| System Administrator | superadmin@example.com | password |
| Lecturer | (create via admin panel) | (set when creating) |
| Student | (register via /register) | (set when registering) |

---

## How the Web Interface is Structured

Before the requirements, understand this: the web interface follows Laravel's MVC pattern exactly.

```
Route (routes/web.php)
    → calls Controller method
    → Controller queries database via Eloquent Models
    → Controller passes data to Blade View
    → Blade View renders HTML + CSS
    → User sees the page
```

**There are TWO separate sets of controllers:**
- **Web controllers** (in `app/Http/Controllers/`) — return Blade views (HTML pages)
- **API controllers** (in `app/Http/Controllers/Api/`) — return JSON (for the desktop app)

When you click a link on the web interface, you hit a **web controller**, not an API controller. This document covers only the web controllers and their Blade views.

---

## Opening (1 minute)

> "We built the Smart Discussion Forum — a platform that manages group discussions, quizzes, messaging, and member monitoring. It has two interfaces: a web application built with Laravel, and a desktop application built in Java. Both connect to the same backend database, so data is shared in real time between them.
>
> The web interface uses Laravel's MVC architecture. Every page you'll see is built from three files: a route, a controller, and a Blade template. Let me walk you through each requirement and show you where it's implemented in the code."

---

## Requirement 1 — Reporting irrelevant content

### What it does
Any member can report a topic or post. Admins review reported content in the Moderation panel and can remove it or dismiss the report.

### How to demo
1. Log in as a student
2. Open any topic
3. Click the **Report** button on a post
4. Log out, log in as admin
5. Go to **Moderation** in the sidebar
6. Show the reported post with Remove / Ignore buttons

### Files involved

| Layer | File | What it does |
|---|---|---|
| Route | `routes/web.php` (lines for report + moderation) | Maps URLs to controllers |
| Controller (student) | `app/Http/Controllers/ReportController.php` | Handles the report form submission |
| Controller (admin) | `app/Http/Controllers/Admin/ModerationController.php` | Lists reported posts, handles remove/ignore |
| Model | `app/Models/Post.php` | The `is_reported` column and `notRemoved()` scope |
| Model | `app/Models/Report.php` | The report record |
| View (student) | `resources/views/forum/show.blade.php` | The "Report" button on each post |
| View (admin) | `resources/views/admin/moderation/index.blade.php` | The moderation panel page |

### Step-by-step logic — Student reports a post

**Step 1:** Student opens a topic at `/forum/{topic}`.

The route at `web.php` line 121 calls `ForumController@show`. The controller loads the topic and its posts from the database and returns the view `resources/views/forum/show.blade.php`.

In this Blade view, each post has a "Report" button:

```blade
{{-- Inside the post loop in forum/show.blade.php --}}
<form action="{{ route('reports.store') }}" method="POST">
    @csrf
    <input type="hidden" name="reportable_type" value="App\Models\Post">
    <input type="hidden" name="reportable_id" value="{{ $post->id }}">
    <button type="submit" class="btn btn-sm btn-warning">Report</button>
</form>
```

**Step 2:** Student clicks Report. The form submits a POST request.

**Step 3:** `routes/web.php` has this route:
```php
Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
```

It calls `ReportController@store`.

**Step 4:** `ReportController@store` (in `app/Http/Controllers/ReportController.php`):

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'reportable_type' => 'required|string',
        'reportable_id' => 'required|integer',
        'reason' => 'nullable|string|max:500',
    ]);

    Report::create([
        'reportable_type' => $validated['reportable_type'],
        'reportable_id' => $validated['reportable_id'],
        'reported_by' => auth()->id(),
        'reason' => $validated['reason'] ?? null,
    ]);

    // Also flag the post itself so the moderation panel can find it
    $post = Post::findOrFail($validated['reportable_id']);
    $post->update(['is_reported' => true]);

    return back()->with('success', 'Post reported. An admin will review it.');
}
```

**Step 5:** The user is redirected back with a success message.

### Step-by-step logic — Admin reviews reports

**Step 1:** Admin navigates to `/admin/moderation`.

**Step 2:** The route calls `ModerationController@index`:

```php
// In app/Http/Controllers/Admin/ModerationController.php
public function index()
{
    // Fetch all posts where is_reported = true
    $reportedPosts = Post::where('is_reported', true)
        ->with('topic', 'user')
        ->paginate(20);

    return view('admin.moderation.index', compact('reportedPosts'));
}
```

**Step 3:** The Blade view `resources/views/admin/moderation/index.blade.php` displays each reported post with two buttons:

```blade
@foreach($reportedPosts as $post)
    <div class="card">
        <p>Post by: {{ $post->user->full_name }}</p>
        <p>In topic: {{ $post->topic->title }}</p>
        <p>{{ $post->body }}</p>

        <form action="{{ route('admin.moderation.remove', $post) }}" method="POST" style="display:inline">
            @csrf
            <button class="btn btn-danger">Remove Post</button>
        </form>

        <form action="{{ route('admin.moderation.ignore', $post) }}" method="POST" style="display:inline">
            @csrf
            <button class="btn btn-secondary">Ignore Report</button>
        </form>
    </div>
@endforeach
```

**Step 4 (Remove):** `ModerationController@removePost`:
```php
public function removePost(Post $post)
{
    $post->update([
        'is_removed' => true,    // Soft-delete — post is hidden from all users
        'removed_at' => now(),
        'removed_by' => auth()->id(),
    ]);

    return back()->with('success', 'Post removed.');
}
```

After this, the `notRemoved()` scope in `app/Models/Post.php` excludes the post:
```php
public function scopeNotRemoved($query)
{
    return $query->where('is_removed', false);
}
```

This scope is applied in `ForumController@show` when loading replies, so the removed post disappears from everyone's view.

**Step 4 (Ignore):** `ModerationController@ignoreReport`:
```php
public function ignoreReport(Post $post)
{
    $post->update(['is_reported' => false]);
    // The post stays visible. The report is dismissed.
    return back()->with('success', 'Report ignored.');
}
```

### Database tables
```
reports: id, reportable_type (model class), reportable_id (post/topic ID), reported_by (user ID), reason, created_at
posts:   id, body, is_reported (boolean), is_removed (boolean), removed_by, removed_at
```

---

## Requirement 2 — Marking questions as answered

### Files involved
| File | What it does |
|---|---|
| `routes/web.php` (forum group) | Route for toggling answered |
| `app/Http/Controllers/ForumController.php` (`toggleAnswered` method) | Flips the `is_answered` boolean |
| `resources/views/forum/show.blade.php` | The "Toggle Answered" button + badge display |

### Step-by-step logic
**Step 1:** Admin opens a question-type topic at `/forum/{topic}`.

**Step 2:** The Blade view checks if the current user can toggle answered:
```blade
@if($topic->post_type === 'question' && auth()->user()->isAdmin())
    <form action="{{ route('forum.toggle-answered', $topic) }}" method="POST">
        @csrf
        <button class="btn btn-success">
            {{ $topic->is_answered ? 'Mark Unanswered' : 'Mark Answered' }}
        </button>
    </form>
@endif
```

**Step 3:** The form calls `ForumController@toggleAnswered`:
```php
public function toggleAnswered(Topic $topic)
{
    if ($topic->post_type !== 'question') {
        return back()->with('error', 'Only questions can be toggled.');
    }

    $topic->update(['is_answered' => !$topic->is_answered]);

    $status = $topic->is_answered ? 'answered' : 'unanswered';
    return back()->with('success', "Topic marked as {$status}.");
}
```

**Step 4:** In the forum feed (`resources/views/forum/index.blade.php`), each topic card shows:
```blade
@if($topic->is_answered)
    <span class="badge bg-success">✓ Answered</span>
@endif
```

---

## Requirement 3 — Post visibility / excluding people

### Files involved
| File | What it does |
|---|---|
| `routes/web.php` | Route: `forum.post.visibility.exclude` |
| `app/Http/Controllers/ForumController.php` (`excludeUser` method) | Creates the exclusion record |
| `app/Models/PostVisibility.php` | The pivot model for exclusions |
| `app/Models/Post.php` (`scopeVisibleToUser`) | Filters posts at query time |

### Step-by-step logic
**Step 1:** On a post they wrote, the author clicks "Exclude User" and enters a user ID.

**Step 2:** `ForumController@excludeUser` runs:
```php
public function excludeUser(Request $request, Post $post)
{
    // Only the post author can exclude people
    if ($post->user_id !== auth()->id()) {
        return back()->with('error', 'Only the post author can manage visibility.');
    }

    $validated = $request->validate([
        'user_id' => 'required|exists:users,id',
    ]);

    PostVisibility::create([
        'post_id' => $post->id,
        'excluded_user_id' => $validated['user_id'],
    ]);

    return back()->with('success', 'User excluded from this post.');
}
```

**Step 3:** When ANY user opens the topic to read it, `ForumController@show` fetches posts:

```php
$posts = $topic->posts()
    ->notRemoved()
    ->visibleToUser(auth()->id())   // ← THIS IS THE KEY LINE
    ->with('user')
    ->paginate(20);
```

**Step 4:** The `visibleToUser` scope (in `app/Models/Post.php`):
```php
public function scopeVisibleToUser($query, int $userId)
{
    return $query->whereDoesntHave('visibilityExclusions', function ($q) use ($userId) {
        $q->where('excluded_user_id', $userId);
    });
}
```

This generates SQL:
```sql
SELECT * FROM posts
WHERE NOT EXISTS (
    SELECT 1 FROM post_visibility
    WHERE post_visibility.post_id = posts.id
    AND post_visibility.excluded_user_id = 5
)
```

The excluded user simply doesn't see the post — no error, no message. The post is invisible to them.

---

## Requirement 4 — Inactivity warnings and automatic blacklisting

### Files involved
| File | What it does |
|---|---|
| `app/Services/WarningService.php` | Core logic: issue warning, auto-blacklist |
| `app/Http/Controllers/Admin/WarningController.php` | Web controller for admin warning management |
| `app/Http/Controllers/Admin/BlacklistController.php` | Web controller for blacklist management |
| `app/Http/Controllers/Admin/SystemConfigController.php` | Manages the configurable thresholds |
| `resources/views/admin/warnings/index.blade.php` | Warning list view |
| `resources/views/admin/blacklist/index.blade.php` | Blacklist view |
| `resources/views/admin/system-config/index.blade.php` | System config form |

### The warning lifecycle — step by step

**1. Admin configures thresholds** at `/admin/system-config`:
```
inactivity_warning_days  → 30 (warn if inactive for 30 days)
warning_response_days   → 7  (deadline to respond)
blacklist_duration_days → 14 (auto-blacklist lasts 14 days)
```
These are stored in the `system_configs` table as key-value pairs.

**2. The daily check (automated):** A Laravel scheduled task runs daily:
```php
// In app/Console/Kernel.php
$schedule->call(function () {
    app(WarningService::class)->checkInactivity();
})->daily();
```

`WarningService@checkInactivity()` queries:
```php
$inactiveUsers = User::where('last_active_at', '<', now()->subDays($warningDays))
    ->where('account_status', 'active')
    ->whereDoesntHave('warnings', function ($q) {
        $q->whereNull('is_resolved');
    })
    ->get();

foreach ($inactiveUsers as $user) {
    $this->issueWarning($user, 'Inactivity: No activity for ' . $warningDays . ' days.');
}
```

**3. Warning issued:** `WarningService@issueWarning()`:
```php
public function issueWarning(User $user, string $reason)
{
    Warning::create([
        'user_id' => $user->id,
        'issued_by' => auth()->id() ?? 1,  // system if automated
        'reason' => $reason,
        'response_deadline' => now()->addDays($this->getConfig('warning_response_days')),
    ]);

    $user->update(['account_status' => 'warned']);

    // Check if auto-blacklist should trigger
    $warningCount = Warning::where('user_id', $user->id)->count();
    if ($warningCount >= 3) {
        $this->autoBlacklist($user);
    }
}
```

**4. Auto-blacklist:** `autoBlacklist()`:
```php
public function autoBlacklist(User $user)
{
    $durationDays = SystemConfig::getValue('blacklist_duration_days') ?? 14;

    BlacklistRecord::create([
        'user_id' => $user->id,
        'blacklisted_at' => now(),
        'expires_at' => now()->addDays((int) $durationDays),
        'reason' => 'Automatic: 3 warnings reached.',
    ]);

    $user->update(['account_status' => 'blacklisted']);
}
```

**5. On next login attempt:** `LoginController@authenticate` checks:
```php
if ($user->account_status === 'blacklisted') {
    return back()->withErrors([
        'email' => 'Your account is blacklisted until ' . $blacklistRecord->expires_at->format('M d, Y') . '.',
    ]);
}

if ($user->account_status === 'warned') {
    return redirect()->route('warning-acknowledgement');
}
```

---

## Requirement 5 — Onboarding / platform rules agreement

### Files involved
| File | What it does |
|---|---|
| `routes/web.php` | Registration + onboarding routes |
| `app/Http/Controllers/Auth/RegisterController.php` | Handles registration form |
| `app/Http/Controllers/Auth/OnboardingController.php` | Handles the agreement step |
| `resources/views/auth/register.blade.php` | Registration form |
| `resources/views/onboarding/agree.blade.php` | Rules display + agreement checkbox |
| `app/Models/OnboardingAgreement.php` | Stores the agreement record |

### Step-by-step logic
**Step 1:** User goes to `/register`. The route calls `RegisterController@showRegistrationForm()` which returns `resources/views/auth/register.blade.php`.

**Step 2:** User fills in name, email, password, and submits.

**Step 3:** `RegisterController@register()` validates the input, creates the user, logs them in, then redirects to `/onboarding`:
```php
public function register(Request $request)
{
    $validated = $request->validate([...]);

    $user = User::create([
        'full_name' => $validated['full_name'],
        'email' => $validated['email'],
        'password' => Hash::make($validated['password']),
        'role_id' => Role::where('role_name', 'Member')->first()->id,
        'group_id' => Group::where('group_name', 'General')->first()->id,
    ]);

    Auth::login($user);

    return redirect()->route('onboarding.show');
}
```

**Step 4:** The onboarding page (`resources/views/onboarding/agree.blade.php`) shows the platform rules and an agreement checkbox.

**Step 5:** User checks the box and clicks Agree. `POST /onboarding/agree` calls `OnboardingController@agree`:

```php
public function agree(Request $request)
{
    OnboardingAgreement::create([
        'user_id' => auth()->id(),
        'ip_address' => $request->ip(),
        'agreed_at' => now(),
        'version' => 1,
    ]);

    return redirect()->route('dashboard')->with('success', 'Welcome to the forum!');
}
```

If the user declines, no record is created, and they are redirected to a page that says they cannot access the forum without agreeing.

---

## Requirement 6 — Topic detail view and PDF export

### Files involved
| File | What it does |
|---|---|
| `app/Http/Controllers/ForumController.php` (`show`, `exportPDF` methods) | Loads topic + replies, generates PDF |
| `resources/views/forum/show.blade.php` | Topic detail page with all replies |
| `resources/views/forum/export-pdf.blade.php` | PDF-specific template |

### Step-by-step logic — Viewing a topic
**Step 1:** User clicks a topic link, goes to `/forum/{topic}`.

**Step 2:** The route binds `{topic}` to a `Topic` model via Laravel's implicit route model binding. `ForumController@show` is called:

```php
public function show(Topic $topic)
{
    // Eager load creator
    $topic->load('creator');

    // Load visible, non-removed replies
    $posts = $topic->posts()
        ->notRemoved()
        ->visibleToUser(auth()->id())
        ->with('user')
        ->orderBy('created_at', 'asc')
        ->paginate(20);

    return view('forum.show', compact('topic', 'posts'));
}
```

**Step 3:** The view (`resources/views/forum/show.blade.php`) renders:
- The topic title, description, author at the top
- Each reply in a threaded list below
- A reply form at the bottom

### Step-by-step logic — Exporting to PDF
**Step 1:** User clicks "Export PDF" on a topic.

**Step 2:** `ForumController@exportPDF`:
```php
public function exportPDF(Topic $topic)
{
    $topic->load('creator');
    $replies = $topic->posts()
        ->notRemoved()
        ->visibleToUser(auth()->id())
        ->with('user')
        ->orderBy('created_at', 'asc')
        ->get();

    $pdf = PDF::loadView('forum.export-pdf', compact('topic', 'replies'));
    return $pdf->download('topic-' . $topic->id . '.pdf');
}
```

**Step 3:** `resources/views/forum/export-pdf.blade.php` is a simplified HTML template (no navigation, no sidebar — just the topic content). The `barryvdh/laravel-dompdf` package converts this HTML to a PDF file.

**Step 4:** The browser downloads the PDF.

---

## Requirement 7 — Group statistics and admin dashboard

### Files involved
| File | What it does |
|---|---|
| `app/Http/Controllers/Admin/DashboardController.php` | Main admin dashboard |
| `app/Http/Controllers/Admin/GroupStatisticsController.php` | Per-group statistics |
| `app/Services/StatisticsUtility.php` | Calculates live statistics |
| `resources/views/admin/dashboard/index.blade.php` | Dashboard view with stat cards |
| `resources/views/admin/groups/stats.blade.php` | Group statistics view |
| `app/Models/Statistics.php` | Cached statistics model |

### Step-by-step logic
**Step 1:** Admin navigates to `/admin/dashboard`.

**Step 2:** `DashboardController@index`:
```php
public function index()
{
    $totalUsers = User::count();
    $activeToday = User::where('last_active_at', '>=', now()->subDay())->count();
    $totalTopics = Topic::count();
    $totalPosts = Post::count();
    $warnedUsers = User::where('account_status', 'warned')->count();
    $blacklistedUsers = User::where('account_status', 'blacklisted')->count();

    return view('admin.dashboard.index', compact(
        'totalUsers', 'activeToday', 'totalTopics', 'totalPosts',
        'warnedUsers', 'blacklistedUsers'
    ));
}
```

These are direct database counts. The view displays them as cards.

**Step 3:** Per-group statistics use `StatisticsUtility@recalculate()`:
```php
public function recalculate(int $groupId)
{
    $stats = [
        'total_members' => User::where('group_id', $groupId)->count(),
        'active_members_this_week' => User::where('group_id', $groupId)
            ->where('last_active_at', '>=', now()->subWeek())->count(),
        'total_topics' => Topic::where('group_id', $groupId)->count(),
        'total_posts' => Post::whereIn('topic_id',
            Topic::where('group_id', $groupId)->pluck('id')
        )->count(),
        'unanswered_questions' => Topic::where('group_id', $groupId)
            ->where('post_type', 'question')
            ->where('is_answered', false)->count(),
    ];

    Statistics::updateOrCreate(
        ['group_id' => $groupId],
        [...$stats, 'last_calculated_at' => now()]
    );
}
```

The statistics are cached in the `statistics` table so the dashboard doesn't run expensive queries every time.

---

## Requirement 8 — Real-time chat (web)

### Files involved
| File | What it does |
|---|---|
| `routes/web.php` | Conversation + message routes |
| `app/Http/Controllers/ConversationController.php` | CRUD for conversations |
| `app/Http/Controllers/MessageController.php` | Sending messages |
| `resources/views/conversations/index.blade.php` | Conversation list |
| `resources/views/conversations/show.blade.php` | Chat interface with Echo listener |
| `app/Events/MessageSent.php` | Event broadcast via Reverb |
| `resources/js/echo.js` | Laravel Echo configuration |

### Step-by-step logic
**Step 1:** User opens `/conversations`. `ConversationController@index` returns the list.

**Step 2:** User clicks a conversation → `/conversations/{id}` → `ConversationController@show`:
```php
public function show(int $id)
{
    $conversation = Conversation::forUserInGroup(auth()->user())
        ->whereHas('participants', fn($q) => $q->where('user_id', auth()->id()))
        ->with('participants:id,full_name')
        ->findOrFail($id);

    $messages = $conversation->messages()
        ->with('sender:id,full_name')
        ->orderBy('created_at')
        ->paginate(50);

    return view('conversations.show', compact('conversation', 'messages'));
}
```

**Step 3:** The view `resources/views/conversations/show.blade.php` includes JavaScript that listens for new messages:

```javascript
// Using Laravel Echo (configured in resources/js/echo.js)
Echo.private('conversation.' + conversationId)
    .listen('MessageSent', (e) => {
        // Append the new message to the chat UI
        let messageHtml = `<div class="message">
            <strong>${e.message.sender.full_name}</strong>
            <p>${e.message.body}</p>
        </div>`;
        document.getElementById('messages-container').innerHTML += messageHtml;
    });
```

**Step 4:** When the user sends a message, `MessageController@store`:
```php
public function store(Request $request, int $conversationId)
{
    $conversation = Conversation::findOrFail($conversationId);
    $message = $conversation->messages()->create([
        'sender_id' => auth()->id(),
        'body' => $request->input('body'),
    ]);

    // Broadcast to other participants via Reverb WebSocket
    broadcast(new MessageSent($message))->toOthers();

    return back();
}
```

**Step 5:** The `MessageSent` event (in `app/Events/MessageSent.php`) implements `ShouldBroadcast`:
```php
class MessageSent implements ShouldBroadcast
{
    public function broadcastOn()
    {
        return new PrivateChannel('conversation.' . $this->message->conversation_id);
    }
}
```

**Step 6:** Laravel Reverb (the WebSocket server) receives the event and pushes it to all connected clients subscribed to that private channel. The Echo listener in Step 3 receives it and appends the message to the DOM without a page refresh.

**The difference between web and desktop for messaging:**
- **Web:** Real-time via WebSockets (Laravel Reverb + Echo). Messages appear instantly.
- **Desktop:** Polling via `SyncEngine` every 30 seconds. Messages eventually appear after the next pull cycle.

---

## Requirement 9 — Participation marks for discussion

### Files involved
| File | What it does |
|---|---|
| `app/Http/Controllers/StudentQuizController.php` | Calculates participation mark |
| `app/Http/Controllers/GradeController.php` | Displays grades |
| `resources/views/quizzes/results.blade.php` | Results view with participation column |

### Step-by-step logic
When a student submits a quiz, `StudentQuizController@submit` calculates the participation mark:

```php
private function calculateParticipationMark(StudentAttempt $attempt)
{
    $totalQuestions = Question::where('quiz_id', $attempt->quiz_id)->count();
    $answeredQuestions = StudentAnswer::where('attempt_id', $attempt->id)->count();

    if ($totalQuestions === 0) return 0;

    return round(($answeredQuestions / $totalQuestions) * 100);
}
```

This is stored in the `grades` table as `participation_mark` — separate from the actual score. A student who answers every question gets 100% participation even if all answers are wrong. This encourages attempting quizzes regardless of knowledge level.

---

## Requirement 10 — Quiz system (full flow)

### Files involved
| File | What it does |
|---|---|
| `app/Http/Controllers/QuizController.php` (web) | Lecturer creates/manages quizzes |
| `app/Http/Controllers/QuestionController.php` | CRUD for questions |
| `app/Http/Controllers/AnswerController.php` | CRUD for answers |
| `app/Http/Controllers/StudentQuizController.php` | Student takes quiz |
| `app/Http/Controllers/GradeController.php` | Results |
| `resources/views/quizzes/*.blade.php` | All quiz views |

### Step-by-step logic — Lecturer creates a quiz

**Step 1:** Lecturer goes to `/quizzes/create`. `QuizController@create` returns the form view.

**Step 2:** Lecturer fills in title, description, scheduled date, start time, duration, target category, and submits.

**Step 3:** `QuizController@store`:
```php
public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'scheduled_date' => 'required|date|after_or_equal:today',
        'start_time' => 'required|date_format:H:i',
        'duration_minutes' => 'required|integer|min:1|max:480',
        'target_category' => 'required|in:Student,Lecturer,Administrator,Member',
        'group_id' => 'nullable|integer|exists:groups,id',
    ]);

    $quiz = Quiz::create([...]);
    QuizConfiguration::create(['quiz_id' => $quiz->quiz_id]); // Default config

    return redirect()->route('quizzes.show', $quiz);
}
```

**Step 4:** Lecturer adds questions via `QuestionController@store`, then answers via `AnswerController@store`.

**Step 5:** Lecturer clicks "Publish" → `QuizController@publish`:
```php
public function publish(Quiz $quiz)
{
    $quiz->update(['published_at' => now()]);
    event(new QuizPublished($quiz));

    return back()->with('success', 'Quiz published. Students can now see it.');
}
```

### Step-by-step logic — Student takes the quiz

**Step 1:** Student sees the quiz announcement at `/quizzes/{quiz}/announcement`. The countdown shows time until start.

**Step 2:** When the quiz is live, "Join Quiz" button calls `POST /quizzes/{quiz}/attempt`:
```php
// StudentQuizController@start
$attempt = StudentAttempt::create([
    'quiz_id' => $quiz->quiz_id,
    'student_id' => auth()->id(),
    'status' => 'in_progress',
    'started_at' => now(),
]);

return redirect()->route('quizzes.attempt', ['quiz' => $quiz, 'attempt' => $attempt]);
```

**Step 3:** The attempt page (`resources/views/quizzes/attempt.blade.php`) includes:
- A JavaScript countdown timer
- One question displayed at a time
- Navigation (previous/next buttons)
- Question palette (shows answered/unanswered)

**Step 4:** Each answer selection triggers an AJAX call:
```javascript
// In the attempt view's JavaScript
function saveAnswer(questionId, answerText) {
    fetch(`/quizzes/${quizId}/answer`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ question_id: questionId, answer_text: answerText })
    });
}
```

This calls `StudentQuizController@saveAnswer` which writes to `student_answers`.

**Step 5:** When the timer expires, JavaScript auto-submits:
```javascript
if (remainingSeconds <= 0) {
    document.getElementById('auto-submit-form').submit();
}
```

This calls `StudentQuizController@autoSubmit` which grades the quiz:
```php
public function autoSubmit(Quiz $quiz)
{
    $attempt = StudentAttempt::where('quiz_id', $quiz->quiz_id)
        ->where('student_id', auth()->id())
        ->where('status', 'in_progress')
        ->firstOrFail();

    $this->gradeAttempt($attempt);
    $attempt->update(['status' => 'completed', 'completed_at' => now()]);

    return redirect()->route('quizzes.result', ['quiz' => $quiz, 'attempt' => $attempt]);
}
```

**Step 6:** The grading logic:
```php
private function gradeAttempt(StudentAttempt $attempt)
{
    $totalScore = 0;
    $maxScore = $attempt->quiz->questions->sum('points');

    foreach ($attempt->studentAnswers as $studentAnswer) {
        $correctAnswer = Answer::where('question_id', $studentAnswer->question_id)
            ->where('is_correct', true)
            ->first();

        if ($correctAnswer && $studentAnswer->answer_text === $correctAnswer->answer_text) {
            $totalScore += $studentAnswer->question->points;
        }
    }

    $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100) : 0;
    $participation = $this->calculateParticipationMark($attempt);

    Grade::create([
        'attempt_id' => $attempt->id,
        'total_score' => $totalScore,
        'max_score' => $maxScore,
        'percentage' => $percentage,
        'participation_mark' => $participation,
    ]);
}
```

**Result:** The student sees their score, percentage, and participation mark. The lecturer sees all results for the quiz.

---

## Requirement 11 — Topic classification and recommendations

### Files involved
| File | What it does |
|---|---|
| `app/Services/TopicClassificationService.php` | Classifies topics by keywords |
| `app/Http/Controllers/ForumController.php` (`store` method) | Calls classification after topic creation |
| `app/Services/RecommendationService.php` | Generates recommendations |
| `resources/views/recommendations/index.blade.php` | Recommendations page |

### Step-by-step logic — Classification
In `ForumController@store`, after saving the topic:
```php
$topic = Topic::create([...]);
app(TopicClassificationService::class)->classifyTopic($topic);
```

The service (`app/Services/TopicClassificationService.php`):
```php
public function classifyTopic(Topic $topic)
{
    $keywords = [
        'Django' => ['django', 'orm', 'mvc', 'template', 'model', 'view'],
        'JavaScript' => ['react', 'vue', 'angular', 'node', 'npm', 'webpack'],
        'Database' => ['sql', 'mysql', 'postgresql', 'query', 'join', 'index'],
        'APIs' => ['api', 'rest', 'graphql', 'endpoint', 'json', 'postman'],
        'CSS' => ['css', 'flexbox', 'grid', 'responsive', 'bootstrap', 'tailwind'],
    ];

    $text = strtolower($topic->title . ' ' . $topic->description);

    foreach ($keywords as $category => $words) {
        foreach ($words as $word) {
            if (str_contains($text, $word)) {
                $categoryId = TopicCategory::where('category_name', $category)->first()->id;
                $topic->update(['category_id' => $categoryId]);
                return; // First match wins
            }
        }
    }
}
```

### Step-by-step logic — Recommendations
At `/recommendations`, `DashboardController@showRecommendations` calls:
```php
$recommendations = app(RecommendationService::class)
    ->generateRecommendations(auth()->user(), 10);
```

The service (`app/Services/RecommendationService.php`):
```php
public function generateRecommendations(User $user, int $limit)
{
    // 1. Find categories the user engages with most
    $engagedCategories = Topic::whereIn('id',
        Post::where('user_id', $user->id)->pluck('topic_id')
    )->pluck('category_id')->countBy()->sortDesc()->keys();

    // 2. Find topics in those categories the user hasn't seen
    $alreadyRecommended = RecommendationLog::where('user_id', $user->id)
        ->pluck('topic_id');

    $recommendations = collect();
    if ($engagedCategories->isNotEmpty()) {
        $recommendations = Topic::whereIn('category_id', $engagedCategories)
            ->whereNotIn('id', $alreadyRecommended)
            ->where('status', 'active')
            ->limit($limit)
            ->get();
    }

    // 3. Fallback to popular topics if not enough
    if ($recommendations->count() < $limit) {
        $popular = Topic::withCount('posts')
            ->orderBy('posts_count', 'desc')
            ->whereNotIn('id', $alreadyRecommended)
            ->where('status', 'active')
            ->limit($limit - $recommendations->count())
            ->get();
        $recommendations = $recommendations->concat($popular);
    }

    // 4. Log these recommendations
    foreach ($recommendations as $topic) {
        RecommendationLog::create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
        ]);
    }

    return $recommendations;
}
```

The view displays each recommended topic as a card with its category badge.

---

## Requirement 12 — Share to social media

### Files involved
| File | What it does |
|---|---|
| `app/Http/Controllers/ForumController.php` (`shareTopic` method) | Generates signed URL |
| `app/Http/Controllers/SharedTopicController.php` | Public endpoint to view shared topics |
| `resources/views/forum/show.blade.php` | Share button in the topic view |
| `routes/web.php` | Route for shared access |

### Step-by-step logic
**Step 1:** User clicks "Share" on a topic.

**Step 2:** `ForumController@shareTopic`:
```php
public function shareTopic(Request $request, Topic $topic)
{
    $expiresInMinutes = $request->input('expires_in', 1440); // default 24 hours

    $signedUrl = URL::temporarySignedRoute(
        'shared.topic.show',
        now()->addMinutes($expiresInMinutes),
        ['topic' => $topic->id, 'signedUserId' => auth()->id()]
    );

    return response()->json(['url' => $signedUrl]);
}
```

**Step 3:** The generated URL looks like:
```
https://forum.example.com/shared/topic/5/1?expires=1712345678&signature=abc123...
```

The signature is an HMAC-SHA256 hash of the URL parameters signed with the application key.

**Step 4:** Anyone with the link can open it in a browser. The `SharedTopicController@show` validates the signature:
```php
public function show(Topic $topic, $signedUserId)
{
    if (! request()->hasValidSignature()) {
        abort(403, 'Invalid or expired share link.');
    }

    $posts = $topic->posts()->notRemoved()->with('user')->get();
    return view('shared-topic.show', compact('topic', 'posts'));
}
```

If the URL is modified in any way, the signature validation fails and a 403 is returned.

---

## How Authentication Works — Web vs Desktop

| Aspect | Web Interface | Desktop App |
|---|---|---|
| **Login endpoint** | `POST /login` (web route) | `POST /api/v1/login` (API route) |
| **Auth mechanism** | Laravel sessions + session cookie | Sanctum Bearer token |
| **Controller** | `LoginController@authenticate` | `AuthController@login` (API) |
| **Token storage** | HTTP-only cookie in browser | Windows Registry via Preferences API |
| **Logout** | Clears session, deletes cookie | Clears token from memory + Registry |
| **Protected routes** | `auth` middleware checks session | `auth:sanctum` middleware checks token |

The web login flow:
```
1. User submits email + password to POST /login
2. LoginController@authenticate calls Auth::attempt()
3. Laravel hashes the password, compares with stored bcrypt hash
4. If match: creates a session (stored in sessions table), sends session cookie
5. If account warned: redirects to /warning-acknowledgement
6. If blacklisted: returns error "Account blacklisted until [date]"
```

---

## Database Connection Flow

```
Browser
   │
   │  HTTP Request (with session cookie)
   ▼
Apache Web Server (inside Docker container on Render)
   │
   │  Passes request to PHP-FPM
   ▼
Laravel (routes → middleware → controller → model → view)
   │
   │  Eloquent ORM queries
   ▼
PostgreSQL Database (Render's managed database service)
   │
   │  Returns query results
   ▼
Laravel assembles Blade view as HTML
   │
   │  HTTP Response (HTML page)
   ▼
Browser renders the page
```

---

## Possible Questions You Will Be Asked

**Q: What's the difference between the web login and the desktop login?**
A: The web login uses Laravel sessions. When you log in on the web, Laravel creates a session record in the `sessions` table and sends your browser a session cookie. The browser sends this cookie with every request, and Laravel looks up the session to identify you. The desktop login uses Sanctum tokens instead — there's no browser to manage cookies, so the app stores the token in the Windows Registry and sends it as an `Authorization: Bearer` header.

**Q: How does the warning system work?**
A: When an admin issues a warning, `WarningService@issueWarning()` creates a record in the `warnings` table. If the user accumulates 3 warnings, `autoBlacklist()` is called automatically — no admin action needed. This creates a `blacklist_records` entry with an expiration date. On next login, the `LoginController` checks `account_status` and blocks blacklisted users with a message showing the expiry date. The warning thresholds (inactivity days, response deadline, blacklist duration) are all configurable from the System Config panel in the admin section.

**Q: How does real-time messaging work on the web?**
A: We use Laravel Reverb — a WebSocket server that runs alongside the main Laravel app. When a message is sent, `MessageController@store` saves it to the database and fires a `MessageSent` event. This event is broadcast on a private channel specific to that conversation. The frontend uses Laravel Echo (a JavaScript library) to listen on that channel. When Echo receives the broadcast, it appends the new message to the chat view without any page refresh. This creates the real-time effect.

**Q: How does the quiz timer work? What happens when time runs out?**
A: The quiz page has a JavaScript countdown timer. When it reaches zero, JavaScript automatically submits a form that calls `StudentQuizController@autoSubmit`. This grades the attempt (comparing student answers to correct answers), calculates a percentage and participation mark, and stores the result in the `grades` table. The student cannot continue after this — the attempt is marked as completed. Students who join late do not get extra time; the deadline is calculated from `started_at + duration_minutes`, and the server enforces this.

**Q: How does the recommendation system work?**
A: It's not machine learning in the modern sense — it's a rule-based system. When a topic is created, `TopicClassificationService` scans the title and description for keywords and assigns a category (Django, JavaScript, Database, APIs, CSS, or General). The `RecommendationService` then looks at which categories a user has engaged with most (by counting their posts in each category) and recommends unread topics from those categories. If the user has no engagement history, it falls back to the most popular topics globally. Each recommendation is logged so the same topic isn't shown twice.

**Q: How does post visibility exclusion work?**
A: When you exclude someone from a post, a record is created in the `post_visibility` table linking the post ID to the excluded user's ID. The actual filtering happens on every query that fetches posts — the `visibleToUser()` scope adds a `WHERE NOT EXISTS` clause that checks the `post_visibility` table. The excluded user doesn't see the post at all. They don't get an error message; the post is simply invisible to them. This is Requirement 3.

**Q: What happens when a user registers?**
A: The `RegisterController@register` creates a user with the default role "Member" and group "General". The password is hashed with bcrypt before storage. The user is then redirected to the onboarding page where they must read the platform rules and click "Agree." This creates an `onboarding_agreements` record. Only after this agreement is their account fully active and they can access the forum. This is Requirement 5.

**Q: How does the PDF export work?**
A: `ForumController@exportPDF` loads the topic and its visible replies, passes them to a Blade template (`forum/export-pdf.blade.php`), and renders it to HTML. The `barryvdh/laravel-dompdf` package converts this HTML to a PDF using the Dompdf library, which embeds a CSS renderer. The PDF is returned as a download response.

**Q: How is the system deployed?**
A: The Laravel app runs on Render using Docker. The Dockerfile starts from a PHP 8.4 + Apache base image, installs the PostgreSQL driver and Composer dependencies, builds frontend assets with Node.js, and starts Apache. The database is PostgreSQL on Render's free tier. Every push to the `main` branch on GitHub triggers an automatic redeployment via Render's GitHub integration.

---

## Evaluation Criteria — How We Address Each One

| Criteria | What to Show |
|---|---|
| **Interface** | Web app at live URL — all pages, role-based UI (admin sees admin panel, student sees forum) |
| **Database** | Show table relationships: `users→roles`, `topics→posts`, `quizzes→questions→answers`, `conversations→messages→message_statuses`, `warnings→blacklist_records` |
| **Functionality** | Walk through each of the 12 requirements above — each one is implemented and demoable |
| **Deployment** | Live URL on Render, explain Dockerfile, auto-deploy from GitHub |
| **Variables** | Show `.env` structure: `APP_KEY`, `DB_CONNECTION`, `SESSION_DRIVER`, mail config |
| **Code quality** | Explain MVC separation: routes in `web.php`, logic in controllers, database in models, HTML in Blade views |
