# Smart Discussion Forum — Analytics, ML & Administration Module

> Complete system documentation with full code explanations. Use this to understand every file, every method, and every line that was written.

---

## Table of Contents

1. [Module Overview](#1-module-overview)
2. [Machine Learning & How This Module Implements It](#2-machine-learning--how-this-module-implements-it)
3. [Person 1: Database Setup & Statistics Dashboard](#3-person-1-database-setup--statistics-dashboard)
4. [Person 2: Backend Stats Logic & Topic Classification](#4-person-2-backend-stats-logic--topic-classification)
5. [Person 3: Recommendations Logic & UI](#5-person-3-recommendations-logic--ui)
6. [Person 4: Notifications Center & Sending Logic](#6-person-4-notifications-center--sending-logic)
7. [Person 5: Admin Config Panel & Testing](#7-person-5-admin-config-panel--testing)
8. [How Everything Connects](#8-how-everything-connects)

---

## 1. Module Overview

The Analytics, ML & Administration module adds five interconnected subsystems to the Smart Discussion Forum:

1. **Statistics Dashboard** — admins see engagement metrics per group
2. **Topic Classification** — topics are auto-tagged into categories
3. **Recommendations** — users see personalized topic suggestions
4. **Notifications** — system sends inactivity warnings and alerts
5. **Admin Config** — configurable thresholds for all of the above

**The dependency chain:**

```
Groups → Statistics → Admins see engagement
Topics → Classification → Topics get categories
Categories → Recommendations → Users see relevant content
Activity → Notifications → Users stay informed
Config → Monitoring → Admins stay in control
```

---

## 2. Machine Learning & How This Module Implements It

### 2.1 What Is Machine Learning?

Machine Learning (ML) means a computer learns patterns from data. Example: show a computer 10,000 labeled photos of cats and dogs, and it learns to recognize cats and dogs in photos it has never seen before. The more data it sees, the better it gets.

### 2.2 What This Module Actually Does

**This module does NOT use real machine learning.** It uses **rule-based keyword matching** — a simple, predictable approach that works well for an MVP. Here is the algorithm:

1. Each category has a hardcoded list of keywords (e.g., "Django" → django, python, framework, views, models, templates)
2. Take the topic's title and description, make everything lowercase
3. Count how many keywords from each category appear in that text
4. Pick the category with the highest count
5. If no keywords matched, assign "General"

This is **pattern matching**, not ML. No training, no probabilities, no statistical model.

### 2.3 The TopicCategory Table (Designed for Future ML)

The `topic_categories` table already exists with a `keyword_hints` column. In a future version, you could swap `keyword_hints` for an ML model ID and call a trained model instead of counting keywords — without changing the database schema.

---

## 3. Person 1: Database Setup & Statistics Dashboard

### 3.1 Migration Files (Database Tables)

All database changes live in `database/migrations/`. Five migrations were added for this module:

#### `statistics` table — pre-computed engagement snapshot per group

```php
Schema::create('statistics', function (Blueprint $table) {
    $table->id();
    $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
    $table->integer('total_members')->default(0);
    $table->integer('active_members_this_week')->default(0);
    $table->integer('total_topics')->default(0);
    $table->integer('total_posts')->default(0);
    $table->integer('unanswered_questions')->default(0);
    $table->integer('inactive_members_30days')->default(0);
    $table->timestamp('last_calculated_at')->nullable();
    $table->timestamps();
    $table->unique('group_id');  // One row per group — no duplicates
});
```

**What each column stores:**

| Column | Data | Example |
|---|---|---|
| `group_id` | Which group this row is for | 3 = "BSSE Year 2" |
| `total_members` | How many users belong to this group | 45 |
| `active_members_this_week` | Users who posted or were active in the last 7 days | 12 |
| `total_topics` | Total discussion topics ever created in this group | 87 |
| `total_posts` | Total replies ever created in this group | 412 |
| `unanswered_questions` | Topics of type 'question' with 0 replies | 5 |
| `inactive_members_30days` | Users who haven't posted in 30+ days | 8 |
| `last_calculated_at` | When this snapshot was last recomputed | 2026-07-10 02:00:00 |

**`unique('group_id')`** means there can never be two statistics rows for the same group. This is important because we always use `updateOrCreate` — it finds the existing row for a group and updates it, or creates a new one if one doesn't exist.

#### `recommendation_log` table — prevents recommending the same topic twice

```php
Schema::create('recommendation_log', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('topic_id')->constrained('topics')->onDelete('cascade');
    $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
    $table->timestamp('recommended_at');
    $table->string('reason', 255)->nullable();
    $table->timestamps();
    $table->unique(['user_id', 'topic_id']);
    $table->index('user_id');
});
```

Every time we recommend a topic to a user, we log it here. The `unique(['user_id', 'topic_id'])` constraint means the same topic can never be recommended to the same user twice — enforced at the database level.

#### `category_id` added to `topics` table

```php
Schema::table('topics', function (Blueprint $table) {
    $table->foreignId('category_id')
        ->nullable()
        ->after('post_type')
        ->constrained('topic_categories')
        ->onDelete('set null');
});
```

This lets every topic optionally belong to a category. When a category is deleted, `set null` means the topic's `category_id` becomes null (the topic is not deleted).

#### `title`, `message`, `group_id` added to the existing `notifications` table

```php
Schema::table('notifications', function (Blueprint $table) {
    $table->string('title')->nullable()->after('type');
    $table->text('message')->nullable()->after('title');
    $table->foreignId('group_id')->nullable()->constrained('groups')->after('message');
});
```

The original `notifications` table only had `type` and a JSON `data` column. Adding `title`, `message`, and `group_id` lets us create notifications with plain text fields that are easier to query and display.

---

### 3.2 Statistics Model — `app/Models/Statistics.php`

```php
class Statistics extends Model
{
    protected $table = 'statistics';  // Explicit table name

    protected $fillable = [
        'group_id',
        'total_members', 'active_members_this_week',
        'total_topics', 'total_posts',
        'unanswered_questions', 'inactive_members_30days',
        'last_calculated_at',
    ];

    protected $casts = [
        'last_calculated_at' => 'datetime',  // Automatically converts to Carbon
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function activePercentage(): int
    {
        if ($this->total_members === 0) {
            return 0;  // Avoid division by zero
        }
        return (int) round(($this->active_members_this_week / $this->total_members) * 100);
    }

    public function averagePostsPerTopic(): int
    {
        if ($this->total_topics === 0) {
            return 0;  // Avoid division by zero
        }
        return (int) round($this->total_posts / $this->total_topics);
    }
}
```

**Key points:**
- `$fillable` controls which columns can be set via mass-assignment (e.g., `Statistics::create([...])`)
- `$casts` tells Laravel to treat `last_calculated_at` as a Carbon date object automatically, so you can call `$stats->last_calculated_at->diffForHumans()` in the view
- `activePercentage()` and `averagePostsPerTopic()` are helper methods that the view calls directly. Both guard against division by zero.

---

### 3.3 StatisticsUtility — `app/Utilities/StatisticsUtility.php`

This is the shared logic layer. Both the web controller and any future API controller call the same utility, so behavior is identical everywhere.

#### `recalculate(int $groupId)` method

```php
public function recalculate(int $groupId): Statistics
{
    // 1. Total members in the group
    $totalMembers = User::where('group_id', $groupId)->count();

    // 2. Active members this week (last_active_at within the last 7 days)
    $activeMembersThisWeek = User::where('group_id', $groupId)
        ->where('last_active_at', '>=', now()->subWeek())
        ->count();

    // 3. Total topics in this group
    $totalTopics = Topic::where('group_id', $groupId)->count();

    // 4. Total posts (replies) in this group
    $totalPosts = Topic::where('group_id', $groupId)
        ->withCount('posts')
        ->get()
        ->sum('posts_count');

    // 5. Unanswered questions — topics of type 'question' with zero replies
    $unansweredQuestions = Topic::where('group_id', $groupId)
        ->where('post_type', 'question')
        ->whereDoesntHave('posts')
        ->count();

    // 6. Inactive members (30+ days since last_active_at)
    $inactiveMembers30days = User::where('group_id', $groupId)
        ->whereNotNull('last_active_at')
        ->where('last_active_at', '<', now()->subDays(30))
        ->count();

    // Persist the snapshot
    Statistics::updateOrCreate(
        ['group_id' => $groupId],          // Find by group_id
        [                                     // Update or create with these values
            'total_members' => $totalMembers,
            'active_members_this_week' => $activeMembersThisWeek,
            'total_topics' => $totalTopics,
            'total_posts' => $totalPosts,
            'unanswered_questions' => $unansweredQuestions,
            'inactive_members_30days' => $inactiveMembers30days,
            'last_calculated_at' => now(),
        ]
    );

    return Statistics::where('group_id', $groupId)->first();
}
```

**How the counting works, step by step:**

- **Total members**: Simple `count()` on the `users` table filtered by `group_id`. If the group has 45 users, this returns 45.
- **Active this week**: Filter users by `last_active_at >= 7 days ago`. If a user was active yesterday, they are counted. If their last activity was 10 days ago, they are not.
- **Total posts**: Gets all topic IDs for the group, then uses `withCount('posts')` to get the post count per topic, then `sum()` to add them all up. `withCount` runs a single subquery — it does not load every post into memory.
- **Total topics**: Simple `count()` — every topic with this `group_id`.
- **Unanswered questions**: Three conditions: (a) `post_type = 'question'`, (b) `whereDoesntHave('posts')` — means the topic has zero replies in the `posts` relationship, (c) `group_id` matches.
- **Inactive members**: Uses `whereNotNull('last_active_at')` to only check users who have ever been active, then `where('last_active_at', '<', now()->subDays(30))` to find users whose last activity was 30+ days ago.
- **`updateOrCreate`**: The first array `['group_id' => $groupId]` is the lookup key. If a row with this `group_id` exists, it is updated. If not, a new row is created. This is why the `unique('group_id')` migration constraint is important.

#### `getStatsForUser(User $user)` method

```php
public function getStatsForUser(User $user): array
{
    // Role-based group access
    if ($user->isSystemAdmin()) {
        $groups = Group::all();                          // System admin: all groups
    } elseif ($user->isGroupAdmin()) {
        $adminGroupIds = $user->administeredGroups()->pluck('groups.id');
        $groups = Group::whereIn('id', $adminGroupIds)->get();  // Only their groups
    } else {
        $groups = $user->group_id
            ? Group::where('id', $user->group_id)->get()
            : collect();  // Regular user: only their own group
    }

    return $groups->map(function (Group $group) {
        $stats = Statistics::firstOrCreate(
            ['group_id' => $group->id],
            [                                           // Default zero values
                'total_members' => 0,
                'active_members_this_week' => 0,
                'total_topics' => 0,
                'total_posts' => 0,
                'unanswered_questions' => 0,
                'inactive_members_30days' => 0,
            ]
        );
        return ['group' => $group, 'stats' => $stats];
    })->toArray();
}
```

**Key logic:** `firstOrCreate` either returns the existing statistics row for a group, or creates a new row with all zeros. This means the dashboard never shows an error for groups that haven't been calculated yet.

---

### 3.4 StatisticsController — `app/Http/Controllers/Admin/StatisticsController.php`

```php
class StatisticsController extends Controller
{
    public function __construct(
        protected StatisticsUtility $statisticsUtility   // Dependency injection
    ) {}

    public function index(): View
    {
        // Get stats for the currently logged-in user
        $groupStats = $this->statisticsUtility->getStatsForUser(Auth::user());

        return view('admin.statistics.index', compact('groupStats'));
    }

    public function recalculate(int $groupId): RedirectResponse
    {
        $user = Auth::user();
        $group = Group::findOrFail($groupId);   // Returns 404 if group doesn't exist

        // Extra authorisation check (beyond the admin middleware)
        if (! $user->canAccessGroup($groupId)) {
            abort(403, 'You do not have access to statistics for this group.');
        }

        $this->statisticsUtility->recalculate($groupId);

        return redirect()
            ->route('admin.statistics.index')
            ->with('success', "Statistics recalculated for {$group->group_name}.");
    }
}
```

**How dependency injection works here:** Laravel's container automatically creates a `StatisticsUtility` instance and passes it to the constructor. The `protected StatisticsUtility $statisticsUtility` property is then available in both methods.

**Why `findOrFail`?** If someone passes a non-existent group ID (e.g., `/admin/statistics/999/recalculate`), `findOrFail` throws a ModelNotFoundException which Laravel converts to a 404 response. No extra error-handling code needed.

**The `canAccessGroup` check:** Even though the route is behind the `admin` middleware (so only admins can reach it), a Group Admin should only recalculate stats for groups they administer, not every group. This check prevents privilege escalation.

---

### 3.5 Statistics View — `resources/views/admin/statistics/index.blade.php`

```blade
@extends('layouts.app')

@section('title', 'Statistics Dashboard')

@section('content')
<div class="page-stack">
    <header class="page-header">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <h1>Platform Statistics</h1>
                <p>Engagement metrics for all groups you administer</p>
            </div>
        </div>
    </header>

    @forelse ($groupStats as $item)
        @php
            $group = $item['group'];
            $stats = $item['stats'];
            $lastUpdated = $stats->last_calculated_at
                ? $stats->last_calculated_at->diffForHumans()
                : 'Not yet calculated';
        @endphp

        <section class="card" style="margin-bottom: 1.5rem;">
            {{-- Group header with Recalculate button --}}
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <div>
                    <h2 style="margin: 0;">{{ $group->group_name }}</h2>
                    <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--text-muted);">
                        Last updated: {{ $lastUpdated }}
                    </p>
                </div>
                <form method="POST" action="{{ route('admin.statistics.recalculate', $group->id) }}" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">
                        <span class="material-symbols-outlined" style="font-size: 1rem; vertical-align: middle;">refresh</span>
                        Recalculate
                    </button>
                </form>
            </div>

            {{-- 6 metric cards in a responsive grid --}}
            <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                <div class="dashboard-card">
                    <h3>Total Members</h3>
                    <div class="number">{{ $stats->total_members }}</div>
                </div>
                <div class="dashboard-card">
                    <h3>Active This Week</h3>
                    <div class="number" style="color: #4caf50;">
                        {{ $stats->active_members_this_week }}
                        <span style="font-size: 0.875rem; font-weight: 400; color: var(--text-muted);">
                            ({{ $stats->activePercentage() }}%)
                        </span>
                    </div>
                </div>
                <div class="dashboard-card">
                    <h3>Total Topics</h3>
                    <div class="number" style="color: #2196f3;">{{ $stats->total_topics }}</div>
                </div>
                <div class="dashboard-card">
                    <h3>Total Posts</h3>
                    <div class="number" style="color: #ff9800;">
                        {{ $stats->total_posts }}
                        <span style="font-size: 0.875rem;">
                            ({{ $stats->averagePostsPerTopic() }} avg/topic)
                        </span>
                    </div>
                </div>
                <div class="dashboard-card">
                    <h3>Unanswered Questions</h3>
                    <div class="number" style="color: #f44336;">{{ $stats->unanswered_questions }}</div>
                </div>
                <div class="dashboard-card">
                    <h3>Inactive 30+ Days</h3>
                    <div class="number" style="color: #9c27b0;">{{ $stats->inactive_members_30days }}</div>
                </div>
            </div>
        </section>
    @empty
        {{-- Empty state when no groups are available --}}
        <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
            <span class="material-symbols-outlined" style="font-size: 3rem; color: var(--text-muted);">bar_chart</span>
            <h2>No groups available</h2>
            <p style="color: var(--text-muted);">You don't administer any groups yet.</p>
        </div>
    @endforelse
</div>
@endsection
```

**Template logic explained:**
- `@forelse` is a Blade loop that also handles the empty case — if `$groupStats` is empty, the `@empty` block renders instead
- `$stats->last_calculated_at->diffForHumans()` converts the timestamp to a human-friendly string like "2 hours ago" or "3 days ago". The `? :` ternary checks if it's null and shows "Not yet calculated" instead of crashing
- The form uses `@csrf` to include Laravel's CSRF token (prevents cross-site request forgery attacks). The route is `admin.statistics.recalculate` which maps to `POST /admin/statistics/{group}/recalculate`
- `$stats->activePercentage()` calls the model helper method we defined earlier
- The `@empty` block shows an empty state with an icon and a message instead of a blank page

---

## 4. Person 2: Backend Stats Logic & Topic Classification

### 4.1 CalculateStatistics Command — `app/Console/Commands/CalculateStatistics.php`

This is an Artisan command that can be run manually or by the scheduler. It recalculates statistics from live data and sends inactivity warnings.

```php
class CalculateStatistics extends Command
{
    // The signature defines the command name and optional argument
    protected $signature = 'app:calculate-statistics {groupId?}';
    protected $description = 'Calculate statistics for one or all groups';

    public function handle()
    {
        // If a groupId is provided, only recalculate that group
        // Otherwise, recalculate ALL groups
        if ($groupId = $this->argument('groupId')) {
            $groups = Group::where('id', $groupId)->get();
        } else {
            $groups = Group::all();
        }

        foreach ($groups as $group) {
            $this->calculateForGroup($group);
        }

        $this->info('Statistics calculated successfully.');
    }
```

**`{groupId?}`** — the `?` makes it optional. So both of these work:
```
php artisan app:calculate-statistics        # All groups
php artisan app:calculate-statistics 3      # Only group ID 3
```

**`$this->info()`** outputs green text to the console. Also available: `$this->warn()` (yellow), `$this->error()` (red), `$this->line()` (plain).

#### The `calculateForGroup()` method — where the actual counting happens

```php
private function calculateForGroup(Group $group)
{
    // 1. Count total members
    $totalMembers = User::where('group_id', $group->id)->count();

    // 2. Active this week — distinct users who posted in the last 7 days
    $activeMembersThisWeek = Post::whereIn('topic_id',
            Topic::where('group_id', $group->id)->pluck('id')
        )
        ->where('created_at', '>=', now()->subWeek())
        ->distinct('user_id')
        ->count();

    // 3. Total posts
    $totalPosts = Post::whereIn('topic_id',
            Topic::where('group_id', $group->id)->pluck('id')
        )
        ->count();

    // 4. Total topics
    $totalTopics = Topic::where('group_id', $group->id)->count();

    // 5. Unanswered questions (questions with 0 replies)
    $unansweredQuestions = Topic::where('group_id', $group->id)
        ->where('post_type', 'question')
        ->withCount('posts')
        ->get()
        ->filter(function ($topic) {
            return $topic->posts_count == 0;
        })
        ->count();

    // 6. Inactive members (haven't posted in 30+ days)
    $inactiveMembers = User::where('group_id', $group->id)
        ->where(function ($q) {
            $q->where('last_active_at', '<', now()->subDays(30))
              ->orWhereNull('last_active_at');
        })
        ->count();

    // 7. Send inactivity warnings
    $inactiveUsers = User::where('group_id', $group->id)
        ->where(function ($q) {
            $q->where('last_active_at', '<', now()->subDays(30))
              ->orWhereNull('last_active_at');
        })
        ->get();

    foreach ($inactiveUsers as $user) {
        $daysInactive = $user->last_active_at
            ? now()->diffInDays($user->last_active_at)
            : 'many';
        app(NotificationService::class)->sendInactivityWarning($user, $daysInactive);
    }

    // 8. Save the snapshot
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

    $this->info("Calculated stats for: {$group->group_name}");
}
```

**Notable differences from StatisticsUtility's `recalculate()`:**

| Aspect | StatisticsUtility | CalculateStatistics command |
|---|---|---|
| Active members | Uses `last_active_at` on users table | Uses distinct `user_id` on posts table |
| Inactive members | Only counts users with `last_active_at` not null | Also counts users with null `last_active_at` (never posted) |
| Side effects | None — just stores stats | Also sends notifications |
| Context | Called from web controller | Called from scheduler |

Both approaches are valid. The command is more aggressive about detecting inactive users because it also sends warnings.

**`app(NotificationService::class)`** — this is Laravel's `app()` helper that resolves a class from the container. It creates a new `NotificationService` instance (with any dependencies injected automatically) and calls `sendInactivityWarning()` on it.

### 4.2 Scheduled Task — `routes/console.php`

```php
Schedule::command('app:calculate-statistics')->dailyAt('02:00');
```

This line is added to `routes/console.php` which Laravel reads when the scheduler runs. The scheduler checks every minute (via the server's cron job) whether any scheduled commands are due. At 2:00 AM, this command triggers.

**Existing scheduled commands in the same file:**

```php
Schedule::command('monitor:activity')->daily()->at('02:00');
Schedule::command('quiz:send-reminders')->everyMinute();
Schedule::command('quiz:activate')->everyMinute();
```

All three run independently. `monitor:activity` handles the Warning 1→Warning 2→Blacklist escalation. `calculate-statistics` computes the numbers for the dashboard. They don't interfere with each other.

---

### 4.3 TopicClassificationService — `app/Services/TopicClassificationService.php`

This is the "ML" engine. It uses keyword matching to automatically tag topics into categories.

#### Keyword definitions

```php
private $categoryKeywords = [
    'Django' => ['django', 'python', 'framework', 'views', 'models', 'templates'],
    'APIs' => ['api', 'rest', 'endpoint', 'http', 'json', 'request'],
    'Database' => ['database', 'sql', 'query', 'table', 'column', 'join', 'relational'],
    'JavaScript' => ['javascript', 'js', 'react', 'vue', 'node', 'npm'],
    'CSS' => ['css', 'styling', 'bootstrap', 'tailwind', 'design', 'layout'],
    'General' => [],   // Fallback — no keywords means it only matches when nothing else does
];
```

**Why hardcoded?** For the MVP, this keeps things simple. In production, these would be stored in the `topic_categories` table's `keyword_hints` column so admins can add/edit categories without touching code.

#### `classifyTopic(Topic $topic)` — the core classification algorithm

```php
public function classifyTopic(Topic $topic)
{
    // Step 1: Combine title + description into one lowercase string
    $text = strtolower($topic->title . ' ' . $topic->description);

    // Step 2: Score every category by counting keyword matches
    $scores = [];
    foreach ($this->categoryKeywords as $categoryName => $keywords) {
        $score = 0;
        foreach ($keywords as $keyword) {
            // substr_count counts how many times $keyword appears in $text
            $score += substr_count($text, $keyword);
        }
        $scores[$categoryName] = $score;
    }

    // Step 3: Find the category with the highest score
    arsort($scores);          // Sort descending by score (highest first)
    $bestCategory = array_key_first($scores);  // Get the key of the first element

    // Step 4: If no keywords matched at all, assign "General"
    if ($scores[$bestCategory] === 0) {
        $bestCategory = 'General';
    }

    // Step 5: Find or create the category in the database
    $category = TopicCategory::firstOrCreate(
        [
            'group_id' => $topic->group_id,
            'category_name' => $bestCategory,
        ],
        [
            'keyword_hints' => implode(',', $this->categoryKeywords[$bestCategory]),
        ]
    );

    // Step 6: Update the topic with the category_id
    $topic->update(['category_id' => $category->id]);

    return $category;
}
```

**Step-by-step example:**

A topic is created with title *"How to build a REST API with Django REST Framework"* and description *"I need help with Django views and serializers"*.

The algorithm:
1. Lowercases everything: `"how to build a rest api with django rest framework i need help with django views and serializers"`
2. Scores each category:
   - **Django**: "django" appears 2 times, "framework" appears 1 time, "views" 1 time → score = 4
   - **APIs**: "api" appears 1 time, "rest" appears 2 times → score = 3
   - **Database**: "sql" = 0, "query" = 0 → score = 0
   - **JavaScript**: 0
   - **CSS**: 0
3. `arsort($scores)` gives: `['Django' => 4, 'APIs' => 3, 'Database' => 0, 'JavaScript' => 0, 'CSS' => 0]`
4. `array_key_first($scores)` returns `'Django'` (score 4, highest)
5. `firstOrCreate` looks for a TopicCategory with `group_id = $topic->group_id` and `category_name = 'Django'`. If it doesn't exist, it creates one with `keyword_hints = 'django,python,framework,views,models,templates'`
6. `$topic->update(['category_id' => $category->id])` saves the category to the topic

#### `classifyGroupTopics($groupId)` — bulk classify existing topics

```php
public function classifyGroupTopics($groupId)
{
    // Only get topics that don't already have a category
    $topics = Topic::where('group_id', $groupId)
        ->whereNull('category_id')
        ->get();

    foreach ($topics as $topic) {
        $this->classifyTopic($topic);
    }

    return count($topics);  // Return how many were classified
}
```

**Why `whereNull('category_id')`?** This prevents re-classifying topics that already have a category. On the first run after installing the module, all old topics will have null `category_id`, so they all get classified. On subsequent runs, only newly created topics (which may have been created while the scheduler was off) get classified.

---

### 4.4 Auto-Classification on Topic Creation — `app/Models/Topic.php`

```php
class Topic extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id', 'created_by', 'title', 'description',
        'status', 'post_type', 'is_answered', 'is_pinned',
        'category_id',    // <-- Added for classification
    ];

    // The booted() method registers model event hooks
    protected static function booted()
    {
        // "created" fires after a new topic is saved to the database
        static::created(function ($topic) {
            // Automatically classify this topic using the keyword engine
            app(TopicClassificationService::class)->classifyTopic($topic);
        });
    }

    // Relationship to the category
    public function category()
    {
        return $this->belongsTo(TopicCategory::class, 'category_id');
    }

    // ... other relationships and scopes ...
}
```

**How `booted()` works:**

Every Eloquent model has lifecycle events: `retrieved`, `creating`, `created`, `updating`, `updated`, `saving`, `saved`, `deleting`, `deleted`. The `created` event fires right after a new record is inserted into the database.

The closure `function ($topic) { ... }` receives the newly created Topic instance. At this point, the topic has an ID and all its fields are saved. `app(TopicClassificationService::class)` resolves the service from Laravel's container, and `classifyTopic($topic)` runs the keyword matching and updates the topic's `category_id`.

**Important:** `booted()` is called on every request to the application, but the closure only runs when a topic is actually created — not on every page load.

---

## 5. Person 3: Recommendations Logic & UI

### 5.1 RecommendationService — `app/Services/RecommendationService.php`

This service generates personalized topic suggestions. Let's walk through it line by line.

#### `generateRecommendations(User $user, int $limit = 5)`

```php
public function generateRecommendations(User $user, int $limit = 5)
{
    // Step 1: Find categories the user has engaged with
    // We look at the topics the user has posted in, and collect their category IDs
    $userEngagedCategoryIds = Topic::whereIn('id', function ($q) use ($user) {
        $q->select('topic_id')
            ->from('posts')
            ->where('user_id', $user->id);
    })
        ->whereNotNull('category_id')
        ->pluck('category_id')
        ->unique()
        ->toArray();

    // Step 2: If user hasn't engaged with anything, return popular topics
    if (empty($userEngagedCategoryIds)) {
        return $this->getPopularTopics($user, $limit);
    }

    // Step 3: Find topics in those categories that the user hasn't seen
    $recommendations = Topic::whereIn('category_id', $userEngagedCategoryIds)
        ->where('status', 'active')
        ->when($user->group_id !== null, fn ($q) => $q->where('group_id', $user->group_id))
        ->whereNotIn('id', function ($q) use ($user) {
            // Exclude topics the user has already posted in
            $q->select('topic_id')
                ->from('posts')
                ->where('user_id', $user->id);
        })
        ->whereNotIn('id', function ($q) use ($user) {
            // Exclude topics already recommended before
            $q->select('topic_id')
                ->from('recommendation_log')
                ->where('user_id', $user->id);
        })
        ->with('creator')
        ->with('category')
        ->withCount('posts')
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get();

    // Step 4: Log every recommendation so they aren't repeated
    foreach ($recommendations as $topic) {
        RecommendationLog::updateOrCreate(
            ['user_id' => $user->id, 'topic_id' => $topic->id],
            [
                'group_id' => $user->group_id,
                'recommended_at' => now(),
                'reason' => 'Based on similar topics you engaged with',
            ]
        );
    }

    return $recommendations;
}
```

**Detailed breakdown of the subquery chain:**

The first subquery in Step 1 is a nested query:
```sql
-- Outer: Find topics that match these IDs
SELECT category_id FROM topics WHERE id IN (
    -- Inner: Find all topic IDs the user has posted in
    SELECT topic_id FROM posts WHERE user_id = 5
)
```
Laravel's query builder builds this as a single SQL query using the closure in `whereIn`.

The `whereNotIn` closures in Step 3 generate:
```sql
SELECT * FROM topics WHERE category_id IN (1, 3, 5)
  AND status = 'active'
  AND group_id = 2
  AND id NOT IN (SELECT topic_id FROM posts WHERE user_id = 5)           -- not posted
  AND id NOT IN (SELECT topic_id FROM recommendation_log WHERE user_id = 5)  -- not recommended
  ORDER BY created_at DESC
  LIMIT 5
```

This is pure SQL — no machine learning model involved. The "intelligence" comes from the logic: "find topics in categories the user already likes, exclude ones they've seen, recommend the newest first."

#### `getPopularTopics(User $user, int $limit = 5)` — fallback for new users

```php
private function getPopularTopics(User $user, int $limit = 5)
{
    $query = Topic::active()
        ->with('creator')
        ->with('category')
        ->withCount('posts');

    // System admins see popular topics across all groups
    // Regular users see only their own group
    if ($user->group_id !== null) {
        $query->forGroup($user->group_id);
    }

    return $query->orderBy('posts_count', 'desc')
        ->limit($limit)
        ->get();
}
```

**When is this used?** When a new user registers and hasn't posted in any topics yet, `$userEngagedCategoryIds` will be empty (no posts = no categories). Instead of showing nothing, we show the most active topics in their group, sorted by reply count.

**`$query->forGroup(...)`** calls the `scopeForGroup` scope defined in the `Topic` model, which adds `WHERE group_id = ?`. This is Eloquent's local scope system.

---

### 5.2 DashboardController — `app/Http/Controllers/DashboardController.php`

```php
class DashboardController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        $recommendedTopics = collect();   // Default: empty collection
        $recentTopics = collect();

        // System admins see all topics; others need a group
        if ($user->isSystemAdmin() || $user->group_id) {
            $topicQuery = Topic::where('status', 'active');

            if (! $user->isSystemAdmin()) {
                $topicQuery->whereIn('group_id', $user->accessibleGroupIds());
            }

            // Get 5 most recent topics
            $recentTopics = (clone $topicQuery)
                ->with('creator')->withCount('posts')
                ->latest()->take(5)->get()
                ->map(fn (Topic $topic) => [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'creator_name' => optional($topic->creator)->full_name ?? 'Deleted User',
                    'reply_count' => $topic->posts_count,
                    'created_at' => $topic->created_at,
                ]);

            // Get 3 personalized recommendations
            $recommendations = app(RecommendationService::class)
                ->generateRecommendations($user, 3);

            $recommendedTopics = $recommendations->map(
                fn (Topic $topic) => [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'member_count' => $topic->posts_count,
                ]
            );
        }

        return view('auth.dashboard', compact('recentTopics', 'recommendedTopics'));
    }
```

**Why `clone $topicQuery`?** Because `->get()` would consume the query builder. By cloning, we create a separate copy so the second query starts from the same base conditions without the `.latest()->take(5)` that was applied to the first.

**`optional($topic->creator)->full_name ?? 'Deleted User'`** — if the topic's creator was deleted, `$topic->creator` would be null, and calling `->full_name` on null would throw an error. `optional()` returns null instead, and the `??` operator substitutes 'Deleted User'.

---

## 6. Person 4: Notifications Center & Sending Logic

### 6.1 NotificationService — `app/Services/NotificationService.php`

A centralized service for creating notifications from anywhere in the application.

#### `sendToUser()` — the foundational method all others use

```php
public function sendToUser(User $user, string $title, string $message,
                           string $type = 'info', array $extraData = []): Notification
{
    // Merge any extra data into the title+message for the JSON field
    $data = array_merge([
        'title' => $title,
        'message' => $message,
    ], $extraData);

    // Create the notification with both structured columns AND JSON data
    return Notification::create([
        'user_id' => $user->id,
        'group_id' => $user->group_id,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'data' => $data,   // Populated for backward compatibility
    ]);
}
```

**Why both `title`/`message` columns AND `data` JSON?** The original notifications table only had a `data` JSON column. Old code that reads notifications (like the quiz notification center view) reads from `$notification->data['title']`. New code reads from `$notification->title`. By populating both, we keep everything working.

#### Convenience methods

```php
// Send to every user in a group at once
public function sendToGroup(Group $group, string $title, string $message,
                            string $type = 'info', array $extraData = []): void
{
    $userIds = User::where('group_id', $group->id)->pluck('id')->toArray();
    $this->sendToUsers($userIds, $title, $message, $type, $extraData);
}

// Specific notification types with pre-written messages
public function sendInactivityWarning(User $user, int|string $daysInactive): void
{
    $this->sendToUser($user,
        'Inactivity Warning',
        "You haven't posted in {$daysInactive} days. Please re-engage with your group!",
        'warning',
    );
}

public function sendQuizAnnouncement(User $user, string $quizTitle,
                                     Carbon $startTime, array $quizData = []): void
{
    $this->sendToUser($user,
        'Quiz Announcement',
        "A new quiz '{$quizTitle}' is scheduled for {$startTime->format('M d, Y \\a\\t g:ia')}",
        'alert',
        $quizData,
    );
}

// Count unread notifications (used by the navbar badge)
public function getUnreadCount(User $user): int
{
    return Notification::where('user_id', $user->id)
        ->whereNull('read_at')
        ->count();
}

// Mark everything as read (used by "Mark all as read" button)
public function markAllAsRead(User $user): void
{
    Notification::where('user_id', $user->id)
        ->whereNull('read_at')
        ->update(['read_at' => now()]);
}
```

**`sendToGroup`** does a single query to get all user IDs in the group, then loops through each and calls `sendToUser`. For a group with 50 users, this creates 50 notification records in a single request.

**`getUnreadCount`** is called every time the navbar renders. The `whereNull('read_at')` filter means: "count notifications where read_at IS NULL" — not yet read.

---

### 6.2 NotificationController — `app/Http/Controllers/NotificationController.php`

The web controller for the user-facing notification center.

```php
class NotificationController extends Controller
{
    // Show paginated notifications (unread first, then by recent)
    public function index()
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->orderByRaw('read_at IS NULL DESC')  // Unread first
            ->orderByDesc('created_at')            // Then newest first
            ->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    // Mark a single notification as read
    public function read(int $id)
    {
        $notification = Notification::findOrFail($id);

        // Ownership check — you can only read your own notifications
        if ($notification->user_id !== Auth::id()) {
            abort(403, 'You are not authorized to update this notification.');
        }

        $notification->markAsRead();   // Calls the model helper we created

        return redirect()->back()->with('success', 'Notification marked as read.');
    }

    // Mark ALL notifications as read at once
    public function readAll()
    {
        Notification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return redirect()->back()->with('success', 'All notifications marked as read.');
    }

    // Delete a notification
    public function delete(int $id)
    {
        $notification = Notification::findOrFail($id);

        // Ownership check
        if ($notification->user_id !== Auth::id()) {
            abort(403, 'You are not authorized to delete this notification.');
        }

        $notification->delete();

        return redirect()->back()->with('success', 'Notification deleted.');
    }
}
```

**`orderByRaw('read_at IS NULL DESC')`** — This is a clever SQL trick. `read_at IS NULL` evaluates to 1 (true) for unread notifications and 0 (false) for read ones. Sorting DESC puts 1s before 0s, so unread notifications always appear first, regardless of their creation date. Then `orderByDesc('created_at')` sorts within each group (newest unread first, newest read first).

**`$notification->markAsRead()`** calls the method we defined in the Notification model:
```php
public function markAsRead(): void
{
    $this->update(['read_at' => now()]);
}
```

---

### 6.3 Navbar Badge — `resources/views/components/navbar.blade.php`

```blade
<a href="{{ route('notifications') }}" class="app-topbar-icon-btn"
   aria-label="Notifications" style="position: relative;">
    <span class="material-symbols-outlined">notifications</span>

    @php
        // Count unread notifications for the badge
        $unreadNotifCount = Auth::user()->notifications()->whereNull('read_at')->count();
    @endphp

    @if ($unreadNotifCount > 0)
        <span style="position: absolute; top: -5px; right: -5px;
                     background: #f44336; color: white; border-radius: 50%;
                     width: 20px; height: 20px; display: flex;
                     align-items: center; justify-content: center; font-size: 0.75rem;">
            {{ min($unreadNotifCount, 99) }}    {{-- Cap at 99+ --}}
        </span>
    @endif
</a>
```

**How the badge works:**
- `Auth::user()->notifications()` accesses the user's notifications relationship (defined in the User model)
- `whereNull('read_at')` filters to only unread
- `count()` executes a `SELECT COUNT(*)` query — very fast, does not load the actual notifications
- If the count > 0, a red circle with the number appears. `min($unreadNotifCount, 99)` caps it at 99 so you don't see "152" overflowing the badge

---

## 7. Person 5: Admin Config Panel & Testing

### 7.1 SystemConfig Model — `app/Models/SystemConfig.php`

```php
class SystemConfig extends Model
{
    protected $fillable = ['config_key', 'config_value'];

    public static function getValue(string $key, $default = null)
    {
        $cacheKey = "system_config.{$key}";

        // Cache::remember stores the value for 3600 seconds (1 hour)
        // The closure only runs on a cache miss
        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $config = self::where('config_key', $key)->first();
            return $config ? $config->config_value : $default;
        });
    }

    public static function clearCache(string $key): void
    {
        Cache::forget("system_config.{$key}");
    }

    public static function clearAllCaches(): void
    {
        Cache::forget('system_configs.all');
        $configs = self::all();
        foreach ($configs as $config) {
            Cache::forget("system_config.{$config->config_key}");
        }
    }
}
```

**Why caching?** Every page load could potentially read config values. Without caching, each request would run a database query. With `Cache::remember`, after the first read, the value sits in memory (or Redis/file cache) for 1 hour. This reduces database load significantly.

**The `$default` parameter:** If a config key doesn't exist in the database, `getValue` returns null. But the caller can provide a fallback: `SystemConfig::getValue('nonexistent_key', 'fallback_value')` returns `'fallback_value'`.

**When does the cache clear?** The `SystemConfigController::update()` method calls `clearAllCaches()` after saving, so the next read fetches fresh data from the database.

---

### 7.2 SystemConfigController — `app/Http/Controllers/Admin/SystemConfigController.php`

```php
class SystemConfigController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    public function index()
    {
        // Double-check authorization (route also has system-admin middleware)
        if (! auth()->user()->isSystemAdmin()) {
            abort(403, 'Only System Administrators can access system configuration');
        }

        $configs = SystemConfig::all();

        return view('admin.system-config.index', ['configs' => $configs]);
    }

    public function update(Request $request)
    {
        if (! auth()->user()->isSystemAdmin()) {
            abort(403, 'Only System Administrators can update system configuration');
        }

        $validated = $request->validate([
            'max_login_attempts'           => 'required|integer|min:1',
            'lockout_minutes'              => 'required|integer|min:1',
            'inactivity_warning_days'      => 'required|integer|min:1',
            'warning_response_days'        => 'required|integer|min:1',
            'blacklist_duration_days'      => 'required|integer|min:1',
            // Person 5 added these three:
            'days_before_second_warning'   => 'required|integer|min:1',
            'days_before_blacklist'        => 'required|integer|min:1',
            'quiz_late_join_allowed'       => 'nullable|in:0,1',
        ]);

        // Loop through every validated field and save it
        foreach ($validated as $key => $value) {
            SystemConfig::updateOrCreate(
                ['config_key' => $key],        // Find by key
                ['config_value' => $value]      // Update the value
            );
        }

        // Invalidate cached values so the next read is fresh
        SystemConfig::clearAllCaches();

        // Log the change for audit trail
        $this->auditLogService->logSystemConfigUpdated($validated);

        return redirect()->back()->with('success', 'System configuration updated');
    }
}
```

**`request->validate([...])`** — If validation fails, Laravel automatically redirects back with error messages. The keys `required`, `integer`, `min:1`, `nullable`, `in:0,1` are Laravel's validation rules:
- `required` — the field must be present and not empty
- `integer` — must be a whole number
- `min:1` — must be at least 1
- `nullable` — field is optional (for the checkbox)
- `in:0,1` — the value must be either 0 or 1 (for the checkbox)

**The `foreach` loop is the key design decision here.** Instead of hardcoding each config key individually:
```php
// This would be tedious and require changes every time a config is added
SystemConfig::updateOrCreate(['config_key' => 'max_login_attempts'], ['config_value' => $validated['max_login_attempts']]);
SystemConfig::updateOrCreate(['config_key' => 'lockout_minutes'], ['config_value' => $validated['lockout_minutes']]);
// ... repeat for every key ...
```

The loop version `foreach ($validated as $key => $value)` automatically handles any config key. When Person 5 added three new validation rules, the loop handled them without modification. This is the **Open/Closed Principle** in practice — the method is open for extension (add new keys to validation) but closed for modification (the loop doesn't need changing).

---

### 7.3 Person 5's Additions — New Config Keys

#### Migration — `database/migrations/2026_07_08_000000_add_new_config_keys.php`

```php
public function up(): void
{
    $now = now();

    $newConfigs = [
        [
            'config_key' => 'days_before_second_warning',
            'config_value' => '14',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'config_key' => 'days_before_blacklist',
            'config_value' => '14',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'config_key' => 'quiz_late_join_allowed',
            'config_value' => '0',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ];

    foreach ($newConfigs as $config) {
        // updateOrInsert won't duplicate if the key already exists
        DB::table('system_configs')->updateOrInsert(
            ['config_key' => $config['config_key']],
            $config
        );
    }
}

public function down(): void
{
    DB::table('system_configs')->whereIn('config_key', [
        'days_before_second_warning',
        'days_before_blacklist',
        'quiz_late_join_allowed',
    ])->delete();
}
```

**Why `updateOrInsert` instead of `insert`?** If the migration runs twice (e.g., on another developer's machine where the keys already exist from a previous run), `insert` would throw a duplicate key error. `updateOrInsert` silently updates the existing row instead.

#### What the three config keys do

| Config Key | Default | Consumed By | Effect |
|---|---|---|---|
| `days_before_second_warning` | 14 | `MonitorMemberActivity` | Sets the response deadline on Warning 1. After this many days, if the user hasn't acknowledged, Warning 2 is issued. |
| `days_before_blacklist` | 14 | `MonitorMemberActivity` | Sets the response deadline on Warning 2. After this many days, if the user hasn't acknowledged, they are blacklisted. |
| `quiz_late_join_allowed` | 0 (false) | Not yet consumed | Reserved for future quiz module use. When enabled, students who join after quiz start time get the full duration. |

---

### 7.4 MonitorMemberActivity Update — `app/Console/Commands/MonitorMemberActivity.php`

The existing command implements a 3-step escalation. Person 5 modified it to use the new config keys for the deadlines.

#### Before and after comparison

```php
// BEFORE — both steps used the same config key
private function issueWarning(User $user): void
{
    $warningResponseDays = (int) SystemConfig::getValue('warning_response_days', 7);

    // Warning 1 deadline
    'response_deadline' => now()->addDays($warningResponseDays),
    // ...
    // Warning 2 deadline — same value
    'response_deadline' => now()->addDays($warningResponseDays),
}
```

```php
// AFTER — each step has its own config, with fallback
private function issueWarning(User $user): void
{
    // Try the new config key first, fall back to the legacy one
    $secondWarningDays = (int) (SystemConfig::getValue('days_before_second_warning')
        ?: SystemConfig::getValue('warning_response_days', 7));

    $blacklistDays = (int) (SystemConfig::getValue('days_before_blacklist')
        ?: SystemConfig::getValue('warning_response_days', 7));

    // Warning 1 uses second_warning deadline
    'response_deadline' => now()->addDays($secondWarningDays),

    // Warning 2 uses blacklist deadline
    'response_deadline' => now()->addDays($blacklistDays),
}
```

**The `?:` (ternary) fallback logic:**
```php
SystemConfig::getValue('days_before_second_warning') ?: SystemConfig::getValue('warning_response_days', 7)
```
This reads: "If `days_before_second_warning` has a value (not null, not empty), use it. Otherwise, fall back to `warning_response_days`. If that's also missing, use 7."

This means old installations that never ran Person 5's migration still work — the command gracefully falls back to the existing `warning_response_days` config.

#### The complete escalation flow

```
User is inactive for inactivity_warning_days (30 days by default)
    │
    ▼
Warning 1 issued ────────────────── response_deadline = days_before_second_warning (14 days)
    │
    ├── User acknowledges? → Warning resolved. No further action.
    │
    └── Deadline passes without acknowledgement
        │
        ▼
    Warning 2 issued ────────────────── response_deadline = days_before_blacklist (14 days)
        │
        ├── User acknowledges? → Warning resolved. No further action.
        │
        └── Deadline passes without acknowledgement
            │
            ▼
        User blacklisted for blacklist_duration_days (90 days)
```

---

### 7.5 View Update — `resources/views/admin/system-config/index.blade.php`

The two new sections added to the config form:

```blade
{{-- New Section 1: Escalation Timing --}}
<hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--border-color, #e5e7eb);">

<h3>Escalation Timing</h3>

<div class="form-group">
    <label for="days_before_second_warning" class="form-label">Days Before Second Warning</label>
    <input type="number" id="days_before_second_warning" name="days_before_second_warning"
           value="{{ $configs->firstWhere('config_key', 'days_before_second_warning')->config_value ?? 14 }}"
           min="1" class="form-control" required>
    <small class="form-text">Days after Warning 1 before issuing Warning 2</small>
</div>

<div class="form-group">
    <label for="days_before_blacklist" class="form-label">Days Before Blacklist</label>
    <input type="number" id="days_before_blacklist" name="days_before_blacklist"
           value="{{ $configs->firstWhere('config_key', 'days_before_blacklist')->config_value ?? 14 }}"
           min="1" class="form-control" required>
    <small class="form-text">Days after Warning 2 before automatic blacklist</small>
</div>

{{-- New Section 2: Quiz Settings --}}
<hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--border-color, #e5e7eb);">

<h3>Quiz Settings</h3>

<div class="form-group">
    <label style="display: flex; align-items: center; gap: 0.5rem;">
        <input type="checkbox" name="quiz_late_join_allowed" value="1"
               {{ $configs->firstWhere('config_key', 'quiz_late_join_allowed')->config_value === '1' ? 'checked' : '' }}>
        Allow late joins for quizzes
    </label>
    <small class="form-text">If checked, students who join after the quiz start time receive the full duration</small>
</div>
```

**How `firstWhere` works:** `$configs` is a Collection of all config rows. `firstWhere('config_key', 'days_before_second_warning')` finds the first element where `config_key` equals that value. If it doesn't exist, `?? 14` provides the default. This prevents the input from being blank if the migration hasn't been run yet.

**How the checkbox value works:** If checked, the form submits `quiz_late_join_allowed=1`. If unchecked, the checkbox is not submitted at all (HTML behavior). Laravel's `nullable|in:0,1` validation allows it to be absent (null) when unchecked.

---

## 8. How Everything Connects

Here is the complete data flow from end to end:

### Step 1: Database Foundation (Person 1)
All 5 tables are created by migrations. The `category_id` foreign key is added to topics. Without these tables, nothing else works.

### Step 2: Topics Are Created (Person 2)
When a user creates a topic, the `Topic::booted()` hook fires automatically. `TopicClassificationService` runs the keyword matching algorithm and updates the topic's `category_id`.

**Code path:**
```
User submits topic form → TopicController@store → Topic::create([...])
    → Topic::booted() fires "created" event
    → TopicClassificationService::classifyTopic($topic)
    → TopicCategory::firstOrCreate(...) → $topic->update(['category_id' => $id])
```

### Step 3: Statistics Are Computed (Person 2)
Every night at 2:00 AM, the scheduler runs `CalculateStatistics`. For each group, it counts members, posts, and topics, saves a row to the `statistics` table, and sends inactivity warnings via `NotificationService`.

**Code path:**
```
Server cron triggers Laravel scheduler
    → routes/console.php: Schedule::command('app:calculate-statistics')
    → CalculateStatistics::handle()
    → CalculateStatistics::calculateForGroup($group)
    → Statistics::updateOrCreate(...)
    → NotificationService::sendInactivityWarning(...)
```

### Step 4: Admin Views Statistics (Person 1)
The admin visits `/admin/statistics`. The `StatisticsController` calls `StatisticsUtility::getStatsForUser()` to get stats for all groups the admin can see, then renders the view with 6 metric cards per group.

**Code path:**
```
GET /admin/statistics
    → StatisticsController@index
    → StatisticsUtility::getStatsForUser(Auth::user())
    → view('admin.statistics.index', compact('groupStats'))
```

### Step 5: Recommendations Are Generated (Person 3)
The user visits the dashboard. `DashboardController::show()` calls `RecommendationService::generateRecommendations()` which finds categories the user has posted in, finds matching new topics, excludes already-recommended topics, logs each recommendation, and passes them to the view.

**Code path:**
```
GET /dashboard
    → DashboardController@show
    → RecommendationService::generateRecommendations($user, 3)
        → Find user's engaged categories (SQL subquery)
        → Find matching active topics (SQL)
        → RecommendationLog::updateOrCreate(...) for each
    → view('auth.dashboard', compact('recentTopics', 'recommendedTopics'))
```

### Step 6: Notifications Are Delivered (Person 4)
When `CalculateStatistics` runs, it calls `NotificationService::sendInactivityWarning()`. This creates a row in the `notifications` table. The next time the user loads any page, the navbar query `Auth::user()->notifications()->whereNull('read_at')->count()` shows the red badge. Clicking it opens the notifications page where the user can read, mark as read, or delete each notification.

**Code path (viewing notifications):**
```
GET /notifications
    → NotificationController@index
    → Notification::where('user_id', Auth::id())->orderByRaw(...)->paginate(20)
    → view('notifications.index', compact('notifications'))
```

### Step 7: Admin Configures the System (Person 5)
The admin visits `/admin/system-config`, changes values like "Days Before Second Warning", and saves. The `SystemConfigController::update()` method validates the input, loops through every key, calls `updateOrCreate` to save, clears the cache, and logs the audit trail. The next time `MonitorMemberActivity` runs, it reads the new values via `SystemConfig::getValue()` and uses them for escalation deadlines.

**Code path (saving config):**
```
PUT /admin/system-config
    → SystemConfigController@update($request)
    → $request->validate([...])  // All config keys validated here
    → foreach ($validated as $key => $value) {
          SystemConfig::updateOrCreate(['config_key' => $key], ['config_value' => $value])
      }
    → SystemConfig::clearAllCaches()
    → AuditLogService::logSystemConfigUpdated($validated)
```

---

### Complete File Inventory

| File | Person | Type | Purpose |
|---|---|---|---|
| `database/migrations/..._create_statistics_table.php` | 1 | Migration | Create statistics table |
| `database/migrations/..._create_recommendation_log_table.php` | 1 | Migration | Create recommendation_log table |
| `database/migrations/..._add_category_id_to_topics_table.php` | 1 | Migration | Add category_id FK to topics |
| `database/migrations/..._add_title_message_group_id_to_notifications_table.php` | 1 | Migration | Extend notifications table |
| `database/migrations/..._add_new_config_keys.php` | 5 | Migration | Seed 3 new config keys |
| `app/Models/Statistics.php` | 1 | Model | Statistics model with helper methods |
| `app/Utilities/StatisticsUtility.php` | 1 | Utility | Shared stats recalculation logic |
| `app/Http/Controllers/Admin/StatisticsController.php` | 1 | Controller | Stats dashboard endpoints |
| `resources/views/admin/statistics/index.blade.php` | 1 | View | 6-metric-card stats dashboard |
| `app/Console/Commands/CalculateStatistics.php` | 2 | Command | Scheduled stats calculation |
| `app/Console/Commands/ClassifyTopics.php` | 2 | Command | Bulk topic classification |
| `app/Services/TopicClassificationService.php` | 2 | Service | Keyword-matching classifier |
| `app/Models/Topic.php` (modified) | 2 | Model | Added category_id + booted() hook |
| `app/Services/RecommendationService.php` | 3 | Service | Personalized recommendation engine |
| `app/Http/Controllers/DashboardController.php` | 3 | Controller | Dashboard with recommendations |
| `resources/views/recommendations/index.blade.php` | 3 | View | Full recommendations page |
| `app/Services/NotificationService.php` | 4 | Service | Central notification-sending helper |
| `app/Http/Controllers/NotificationController.php` | 4 | Controller | Web notification center CRUD |
| `app/Models/Notification.php` (modified) | 4 | Model | Added title, message, group_id, helpers |
| `app/Models/SystemConfig.php` | 5 | Model | Key-value config with caching |
| `app/Http/Controllers/Admin/SystemConfigController.php` (modified) | 5 | Controller | Added 3 new config keys to validation |
| `resources/views/admin/system-config/index.blade.php` (modified) | 5 | View | Added escalation timing + quiz settings |
| `app/Console/Commands/MonitorMemberActivity.php` (modified) | 5 | Command | Separated W1/W2 deadlines |
| `routes/console.php` (modified) | 2 | Config | Added stats schedule |
| `routes/web.php` (modified) | All | Config | Added all new routes |

---

### Key Architectural Decisions Explained

1. **`updateOrCreate` pattern** — Used in multiple places (Statistics, SystemConfig, RecommendationLog). It means "find by these conditions, update if exists, create if not." This is idempotent — running the same operation twice doesn't create duplicates.

2. **Caching** — `SystemConfig::getValue()` caches for 1 hour. The dashboard (GroupStatisticsService) also caches. This prevents repeated DB queries on every page load. Cache is cleared when settings change.

3. **Fallback chains** — `SystemConfig::getValue('days_before_second_warning') ?: SystemConfig::getValue('warning_response_days', 7)` ensures backward compatibility. If a key doesn't exist, the system tries an older key, then a hardcoded default.

4. **Ownership checks** — Both `NotificationController` and `StatisticsController` verify that the current user owns or has access to the resource before allowing operations. This prevents users from reading or deleting other users' notifications.

5. **Separation of concerns** — `StatisticsUtility` contains the logic, `StatisticsController` handles HTTP concerns (auth, redirects), and the view handles presentation. This makes it easy to add an API endpoint later that reuses `StatisticsUtility`.

---

*End of Document*
