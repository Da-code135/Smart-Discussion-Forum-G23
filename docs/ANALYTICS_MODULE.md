# Smart Discussion Forum — Analytics, ML & Administration Module

> A senior engineer's guide to every file, every method, and every design decision.
> Written to help you understand not just *what* the code does, but *why* it was written that way,
> *what alternatives were considered*, and *how things can go wrong*.

---

## Table of Contents

1. [Before We Start — The Big Picture](#1-before-we-start--the-big-picture)
2. [Machine Learning & What We Actually Built](#2-machine-learning--what-we-actually-built)
3. [Person 1: Database Foundation & Statistics Dashboard](#3-person-1-database-foundation--statistics-dashboard)
4. [Person 2: Automatic Stats & Topic Classification](#4-person-2-automatic-stats--topic-classification)
5. [Person 3: Recommendations Engine & UI](#5-person-3-recommendations-engine--ui)
6. [Person 4: Notifications System](#6-person-4-notifications-system)
7. [Person 5: Admin Configuration & Testing](#7-person-5-admin-configuration--testing)
8. [The Complete Data Flow](#8-the-complete-data-flow)

---

## 1. Before We Start — The Big Picture

Think of this module as five layers that sit on top of the existing forum:

```
┌─────────────────────────────────────────────────────┐
│  Layer 5: Admin Config (Person 5)                   │
│  "How many days before a warning? Before blacklist?" │
├─────────────────────────────────────────────────────┤
│  Layer 4: Notifications (Person 4)                   │
│  "Hey, you've been inactive — come back!"            │
├─────────────────────────────────────────────────────┤
│  Layer 3: Recommendations (Person 3)                │
│  "You liked Python topics — here's a Django thread" │
├─────────────────────────────────────────────────────┤
│  Layer 2: Classification + Stats (Person 2)         │
│  "This topic is about APIs" + "42 members active"   │
├─────────────────────────────────────────────────────┤
│  Layer 1: Database Tables (Person 1)                │
│  statistics, recommendation_log, category_id...      │
└─────────────────────────────────────────────────────┘
```

Each layer depends on the ones below it. Person 1 built the foundation. Person 5 tweaks the knobs on top.

---

## 2. Machine Learning & What We Actually Built

Let me be completely honest with you: **this module does not use machine learning.**

I know the project brief says "ML Module." But what we built is **rule-based keyword matching**. Here's why that distinction matters and why it's the right call for an MVP.

### 2.1 What Real ML Looks Like

Real machine learning works like this:

1. You collect thousands of example topics that humans have already labeled (e.g., "What is an API endpoint?" → "APIs", "How to write Django models" → "Django")
2. You train a statistical model that learns patterns — not just keywords, but word order, synonyms, sentence structure, context
3. The model can generalize: it knows that "RESTful service" is related to "APIs" even if "api" never appears in the text

This requires: a training dataset, a data scientist to tune parameters, server infrastructure to run the model, and ongoing maintenance to keep it accurate.

### 2.2 What We Built Instead (And Why It's Fine)

```php
// app/Services/TopicClassificationService.php

private $categoryKeywords = [
    'Django'     => ['django', 'python', 'framework', 'views', 'models', 'templates'],
    'APIs'       => ['api', 'rest', 'endpoint', 'http', 'json', 'request'],
    'Database'   => ['database', 'sql', 'query', 'table', 'column', 'join', 'relational'],
    'JavaScript' => ['javascript', 'js', 'react', 'vue', 'node', 'npm'],
    'CSS'        => ['css', 'styling', 'bootstrap', 'tailwind', 'design', 'layout'],
    'General'    => [],  // Empty array = fallback when nothing else matches
];
```

**What this is:** A lookup table. Each category has a list of trigger words. When a topic is created, we count how many of those words appear in the title and description. The category with the most matches wins.

**Why this is the right call for an MVP:**

1. **Zero training data needed** — We don't have 10,000 pre-classified topics. Without data, you cannot train an ML model.
2. **Predictable and debuggable** — If a topic gets classified wrong, you can see exactly why: "Oh, 'framework' triggered Django but this was about Vue.js." You add 'vue' to JavaScript keywords and move on.
3. **No dependencies** — No Python process, no TensorFlow model files, no GPU servers. Runs in PHP, in the same request.
4. **Fast to implement** — This service was written in an afternoon. A real ML pipeline would take weeks.

**The trade-off:** Our system cannot generalize. A topic titled "Understanding Request-Response Cycles" won't be classified as APIs because it doesn't contain the literal word "api." Real ML would catch this. For an MVP, that's acceptable — you improve the keyword lists as users report misclassifications.

### 2.3 How the Algorithm Actually Works (Line by Line)

```php
public function classifyTopic(Topic $topic)
{
    // STEP 1: Combine title + description into one searchable string
    $text = strtolower($topic->title . ' ' . $topic->description);
```

**Why `strtolower`?** Because our keywords are all lowercase (`'django'`, `'api'`, `'rest'`). If the user writes "Django" or "DJANGO" or "dJango", we need to match it. Converting everything to lowercase first makes the matching case-insensitive. This is a simple trick — a real NLP system would use stemming or lemmatization, but for keyword counting, `strtolower` is sufficient.

```php
    // STEP 2: Score every category
    $scores = [];
    foreach ($this->categoryKeywords as $categoryName => $keywords) {
        $score = 0;
        foreach ($keywords as $keyword) {
            // substr_count counts EVERY occurrence, even inside other words
            // "django" appears in "django-rest-framework" → counted
            $score += substr_count($text, $keyword);
        }
        $scores[$categoryName] = $score;
    }
```

**Why `substr_count` instead of `str_contains`?** `str_contains` returns true/false — it tells you "yes, the word exists" but not how many times. A topic that mentions "Django" five times is probably more about Django than one that mentions it once. `substr_count` gives us a weighted score.

**Gotcha to watch for:** `substr_count` counts substrings, not words. The keyword "rest" would match inside "restaurant" or "forest." If this becomes a problem, you'd need word-boundary matching with regex (`\brest\b`). For now, the categories we chose don't have this problem — "api" won't accidentally match inside another word.

```php
    // STEP 3: Find the winner
    arsort($scores);  // Sort descending by score
    $bestCategory = array_key_first($scores);  // Get key of first element
```

**What `arsort` does:** The `a` stands for "associative" — it maintains the key-value association. After sorting:
```
Before: ['Django' => 4, 'APIs' => 3, 'Database' => 0, 'JavaScript' => 0, 'CSS' => 0]
After:  ['Django' => 4, 'APIs' => 3, 'CSS' => 0, 'Database' => 0, 'JavaScript' => 0]
```
`array_key_first` returns `'Django'` — the first key after sorting, which is the highest scored.

```php
    // STEP 4: If nothing matched, use General
    if ($scores[$bestCategory] === 0) {
        $bestCategory = 'General';
    }
```

**Why this check is necessary:** If the topic says "What time is lunch?", none of our keywords match. All scores are 0. `arsort` would put... well, all of them at 0, and `array_key_first` would return whichever key PHP stores first internally (probably 'Django' since it was defined first). We don't want an unrelated topic misclassified as Django. By checking if the best score is 0, we force it to 'General' instead.

```php
    // STEP 5: Find or create the category in the database
    $category = TopicCategory::firstOrCreate(
        [
            'group_id' => $topic->group_id,
            'category_name' => $bestCategory,
        ],
        [
            'keyword_hints' => implode(',', $this->categoryKeywords[$bestCategory]),
        ]
    );

    // STEP 6: Update the topic
    $topic->update(['category_id' => $category->id]);

    return $category;
}
```

**`firstOrCreate` explained:** Laravel looks for a row matching both conditions (`group_id = X AND category_name = 'Django'`). If found, it returns that row. If not found, it CREATES a new row using the second array as additional data. This means categories are auto-created the first time they're matched — you don't need to manually add "Django" to the database before it can be used.

**Important detail:** The unique constraint `unique(['group_id', 'category_name'])` on the table prevents duplicate categories. If two topics in the same group both get classified as "Django", `firstOrCreate` returns the same category row — it doesn't create a second one.

### 2.4 When Classification Happens

```php
// app/Models/Topic.php

protected static function booted()
{
    static::created(function ($topic) {
        app(TopicClassificationService::class)->classifyTopic($topic);
    });
}
```

**The `booted()` method** is Laravel's way of registering model lifecycle hooks. Think of it like setting up a motion sensor: it doesn't do anything on its own, but when motion happens (a topic is created), it triggers.

**Why `static::created` (past tense) and not `static::creating` (present tense)?** The difference is timing:
- `creating` fires BEFORE the topic is saved to the database. At this point, the topic has no ID yet.
- `created` fires AFTER the topic is saved. The topic has an ID, all fields are persisted, and we can safely update it.

If we used `creating`, the topic wouldn't exist yet when we try to `$topic->update(['category_id' => ...])`. That's why we use `created`.

**`app(TopicClassificationService::class)`** is Laravel's service container. It creates a new instance of `TopicClassificationService` and returns it. If the service had constructor dependencies (like a database repository), the container would automatically inject them. This is called "automatic resolution" — you don't need to manually instantiate classes.

---

## 3. Person 1: Database Foundation & Statistics Dashboard

### 3.1 The Migration Files — Designing the Schema

A migration is Laravel's way of version-controlling database changes. Think of it like Git for your database schema. Every migration file has an `up()` method (apply the change) and a `down()` method (reverse it).

#### The `statistics` Table

```php
// database/migrations/2026_07_07_000001_create_statistics_table.php

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
    $table->unique('group_id');
});
```

**Why a separate `statistics` table instead of computing on the fly?**

This is the classic "cache vs. compute" trade-off. You have two options:

**Option A — Compute on the fly (no statistics table):**
```php
$totalMembers = User::where('group_id', $groupId)->count();
$activeMembers = User::where('group_id', $groupId)
    ->where('last_active_at', '>=', now()->subWeek())->count();
// ... 4 more queries every page load
```
- **Pro:** Always up to date. No extra table to maintain.
- **Con:** Every admin dashboard load runs 6 database queries. With 100 admins refreshing constantly, that's 600 queries per minute.

**Option B — Pre-compute and cache (what we did):**
We run those 6 queries once per day (at 2 AM), store the results in a `statistics` row, and the dashboard just reads that row.
- **Pro:** Dashboard loads instantly with 1 query instead of 6. Scales to any number of admins.
- **Con:** Data is up to 24 hours old. Admin has to click "Recalculate" for live data.

**Why we chose Option B:** The stats dashboard is for *trends*, not real-time monitoring. Whether a group has 42 or 43 active members this week doesn't change any decision. But the performance difference between 1 query and 6 queries per page load is significant.

**Why `default(0)` on every column?** When a new group is created, there's no statistics row for it yet. The first time the dashboard loads, `firstOrCreate` creates a row with all zeros. Without defaults, that row would have NULL values, and the dashboard would display blank cards instead of "0."

**Why `unique('group_id')`?** This ensures there can never be two statistics rows for the same group. Without this constraint, a bug in the code could accidentally create duplicate rows, and the dashboard would show two cards for the same group. The `unique` constraint at the database level is a safety net — even if the code has a bug, the database won't allow duplicates.

**Why `onDelete('cascade')`?** If a group is deleted, we want its statistics row to be deleted automatically. Otherwise, we'd have orphaned rows referring to a group that no longer exists, and the dashboard would try to display a card for a deleted group.

#### The `recommendation_log` Table

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

**Why do we need this table?**

The recommendation engine needs to know: "Have we already shown topic X to user Y?" Without this table, every page load would recommend the same topics over and over. Users would see the same suggestions for weeks.

**The `unique(['user_id', 'topic_id'])` constraint** prevents duplicate recommendations at the database level. Even if the code has a race condition (two requests trying to recommend the same topic simultaneously), only one `INSERT` succeeds. The second one hits the unique constraint and throws an error. We use `updateOrCreate` instead of `create` to handle this gracefully — it tries to insert, and if the row already exists, it updates the `recommended_at` timestamp instead.

**The `index('user_id')`** is a performance optimization. Every recommendation query starts with `WHERE user_id = ?`. Without an index, the database has to scan every row in the table to find that user's records. With an index, it jumps directly to the relevant rows. For a table that grows by thousands of rows over time, this is essential.

#### Adding `category_id` to Topics

```php
Schema::table('topics', function (Blueprint $table) {
    $table->foreignId('category_id')
        ->nullable()
        ->after('post_type')
        ->constrained('topic_categories')
        ->onDelete('set null');
});
```

**Why `onDelete('set null')` instead of `onDelete('cascade')`?** If you delete a category (e.g., "CSS" is no longer needed), should all 50 topics classified as "CSS" also be deleted? Of course not. The topics are still valid — they just lose their category label. `set null` tells the database: "When the category is deleted, set `category_id` to NULL on all topics that reference it."

This is a critical design decision: **data should never be destroyed just because a label changes.** The topics table is the primary data; `category_id` is just metadata.

---

### 3.2 The Model Layer — The Eloquent Connection

```php
// app/Models/Statistics.php

class Statistics extends Model
{
    protected $table = 'statistics';
    protected $fillable = [
        'group_id', 'total_members', 'active_members_this_week',
        'total_topics', 'total_posts', 'unanswered_questions',
        'inactive_members_30days', 'last_calculated_at',
    ];

    protected $casts = [
        'last_calculated_at' => 'datetime',
    ];
```

**`$casts`** is one of Laravel's most useful features. By telling Laravel that `last_calculated_at` is a `datetime`, every time you access this property, it's automatically converted to a Carbon instance. This means you can write:

```php
$stats->last_calculated_at->diffForHumans();  // "3 hours ago"
$stats->last_calculated_at->format('Y-m-d');  // "2026-07-10"
$stats->last_calculated_at->isToday();         // true/false
$stats->last_calculated_at->addDays(7);        // Carbon arithmetic
```

Without the cast, `last_calculated_at` would be a plain string ("2026-07-10 02:00:00"), and calling `->diffForHumans()` on a string would crash with a "method not found" error.

```php
    public function activePercentage(): int
    {
        if ($this->total_members === 0) {
            return 0;  // <-- Guard against division by zero
        }
        return (int) round(($this->active_members_this_week / $this->total_members) * 100);
    }

    public function averagePostsPerTopic(): int
    {
        if ($this->total_topics === 0) {
            return 0;  // <-- Guard against division by zero
        }
        return (int) round($this->total_posts / $this->total_topics);
    }
}
```

**Why helper methods on the model instead of in the view?**

You could write `{{ round(($stats->active_members_this_week / $stats->total_members) * 100) }}%` directly in the Blade template. But:
1. It's ugly and hard to read
2. You'd need the division-by-zero guard in every template that uses it
3. If the formula changes (e.g., you decide to round to 1 decimal place), you'd have to update every template

Putting the logic on the model means: one place to maintain, and every view gets the correct value automatically.

**The division-by-zero guard:** If a group has 0 members (brand new group, statistics not yet calculated), `$active_members_this_week / $this->total_members` becomes `0 / 0`, which PHP handles as `NAN` (Not a Number). Using `intval(NAN)` gives 0 on some PHP versions but produces unexpected results on others. The explicit check `if ($this->total_members === 0) { return 0; }` is defensive programming — it handles the edge case explicitly instead of relying on PHP's behavior.

---

### 3.3 The Utility Layer — Shared Business Logic

```php
// app/Utilities/StatisticsUtility.php

class StatisticsUtility
{
    public function recalculate(int $groupId): Statistics
    {
        // 1. Total members
        $totalMembers = User::where('group_id', $groupId)->count();

        // 2. Active this week (last_active_at within last 7 days)
        $activeMembersThisWeek = User::where('group_id', $groupId)
            ->where('last_active_at', '>=', now()->subWeek())
            ->count();
```

**`now()->subWeek()`** is a Carbon helper. It returns a datetime representing exactly 7 days ago from the current moment. Carbon is Laravel's date library — think of it as PHP's DateTime on steroids. You can write:
- `now()->subDays(30)` — 30 days ago
- `now()->addHours(2)` — 2 hours from now
- `now()->startOfMonth()` — midnight on the 1st of this month

When used in an Eloquent query, `now()->subWeek()` is converted to a SQL-compatible datetime string inside the `WHERE` clause.

```php
        // 3. Total topics
        $totalTopics = Topic::where('group_id', $groupId)->count();

        // 4. Total posts (replies) — more complex because posts don't directly have group_id
        $totalPosts = Topic::where('group_id', $groupId)
            ->withCount('posts')
            ->get()
            ->sum('posts_count');
```

**Why is total posts more complex than total topics?** Look at the database schema:
- `topics` has `group_id` — so counting topics by group is a simple `WHERE group_id = X`
- `posts` does NOT have `group_id` — posts belong to topics, and topics belong to groups. To count posts in a group, you first need to find all topics in that group, then count all posts in those topics.

The `withCount('posts')` trick: This adds a subquery to the SELECT clause. Laravel generates:
```sql
SELECT *, (SELECT COUNT(*) FROM posts WHERE posts.topic_id = topics.id) AS posts_count
FROM topics WHERE group_id = ?
```
Then `->get()->sum('posts_count')` adds up all those counts in PHP memory.

```php
        // 5. Unanswered questions
        $unansweredQuestions = Topic::where('group_id', $groupId)
            ->where('post_type', 'question')
            ->whereDoesntHave('posts')
            ->count();
```

**`whereDoesntHave('posts')`** is Eloquent's way of saying: "only include topics that have zero related posts." Laravel generates a `NOT EXISTS` subquery:
```sql
SELECT COUNT(*) FROM topics
WHERE group_id = ?
  AND post_type = 'question'
  AND NOT EXISTS (SELECT 1 FROM posts WHERE posts.topic_id = topics.id)
```

This is the most efficient way to check for "has no children" in SQL. An alternative like `withCount('posts')->having('posts_count', 0)` would load all topic rows into memory and count them in PHP — much slower for large datasets.

```php
        // 6. Inactive members (30+ days since last activity)
        $inactiveMembers30days = User::where('group_id', $groupId)
            ->whereNotNull('last_active_at')  // Only users who have EVER been active
            ->where('last_active_at', '<', now()->subDays(30))
            ->count();

        // Save everything
        Statistics::updateOrCreate(
            ['group_id' => $groupId],
            [
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

**`updateOrCreate` is the star of this method.** It does two things:
1. Find a statistics row where `group_id = X`
2. If found → update the row with the new values
3. If not found → create a new row with `group_id = X` and the new values

This is called an **upsert** (UPDATE + INSERT). It means you can call `recalculate()` 100 times, and you'll always have exactly one row per group — never duplicates, never errors.

```php
    public function getStatsForUser(User $user): array
    {
        if ($user->isSystemAdmin()) {
            $groups = Group::all();
        } elseif ($user->isGroupAdmin()) {
            $adminGroupIds = $user->administeredGroups()->pluck('groups.id');
            $groups = Group::whereIn('id', $adminGroupIds)->get();
        } else {
            $groups = $user->group_id
                ? Group::where('id', $user->group_id)->get()
                : collect();
        }

        return $groups->map(function (Group $group) {
            $stats = Statistics::firstOrCreate(
                ['group_id' => $group->id],
                ['total_members' => 0, 'active_members_this_week' => 0,
                 'total_topics' => 0, 'total_posts' => 0,
                 'unanswered_questions' => 0, 'inactive_members_30days' => 0]
            );
            return ['group' => $group, 'stats' => $stats];
        })->toArray();
    }
}
```

**The role-based access control pattern here is important.** Notice the three branches:
1. **System Admin** → sees ALL groups
2. **Group Admin** → sees only groups they administer (queried from the `group_admins` pivot table)
3. **Regular user** → sees only their own group (or none if they have no group)

This isn't just about hiding data — it's about showing relevant data. A Group Admin for "BSSE Year 1" shouldn't see statistics for "BSSE Year 2." It would be confusing and potentially a privacy concern.

**Why `firstOrCreate` instead of just returning existing rows?** Because a brand-new group might never have had its statistics calculated yet. Without `firstOrCreate`, the new group simply wouldn't appear on the dashboard at all — it would be invisible. With `firstOrCreate`, the group appears with all zeros, and the admin can click "Recalculate" to populate real numbers.

---

### 3.4 The Controller Layer — Handling HTTP

```php
// app/Http/Controllers/Admin/StatisticsController.php

class StatisticsController extends Controller
{
    public function __construct(
        protected StatisticsUtility $statisticsUtility
    ) {}
```

**Constructor promotion (PHP 8+):** The `protected StatisticsUtility $statisticsUtility` parameter simultaneously declares a class property AND assigns the injected value to it. Before PHP 8, you'd write:

```php
private StatisticsUtility $statisticsUtility;

public function __construct(StatisticsUtility $statisticsUtility)
{
    $this->statisticsUtility = $statisticsUtility;
}
```

Constructor promotion saves the boilerplate. Laravel's container automatically resolves `StatisticsUtility` — you never call `new StatisticsUtility()` yourself.

```php
    public function index(): View
    {
        $groupStats = $this->statisticsUtility->getStatsForUser(Auth::user());
        return view('admin.statistics.index', compact('groupStats'));
    }

    public function recalculate(int $groupId): RedirectResponse
    {
        $user = Auth::user();
        $group = Group::findOrFail($groupId);

        // Why this extra check? Because the route is behind 'admin' middleware,
        // but a Group Admin shouldn't be able to recalculate stats for a group
        // they don't administer.
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

**Defense in depth:** The route is protected by two layers:
1. **Route middleware:** The admin prefix has `middleware('admin')` in the routes file, so only admins can reach this controller at all.
2. **Controller-level check:** Even though the user is an admin, `canAccessGroup` verifies they can access THIS specific group.

This is the principle of **defense in depth** — don't rely on a single security boundary. If someone accidentally removes the middleware from the route file, the controller-level check still prevents unauthorized access.

**`findOrFail` vs `find`:** `Group::find($groupId)` returns null if the group doesn't exist. `Group::findOrFail($groupId)` throws a `ModelNotFoundException`, which Laravel converts to a 404 response. Using `findOrFail` means you don't need to write:
```php
$group = Group::find($groupId);
if (!$group) {
    abort(404);
}
```

---

### 3.5 The View Layer — Displaying Data to Admins

```blade
{{-- resources/views/admin/statistics/index.blade.php --}}

@forelse ($groupStats as $item)
    @php
        $group = $item['group'];
        $stats = $item['stats'];
        $lastUpdated = $stats->last_calculated_at
            ? $stats->last_calculated_at->diffForHumans()
            : 'Not yet calculated';
    @endphp

    <section class="card" style="margin-bottom: 1.5rem;">
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

        <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
            <div class="dashboard-card">
                <h3>Total Members</h3>
                <div class="number">{{ $stats->total_members }}</div>
            </div>
            {{-- ... 5 more metric cards ... --}}
        </div>
    </section>
@empty
    {{-- If $groupStats is empty (user has no groups) --}}
    <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
        <span class="material-symbols-outlined" style="font-size: 3rem; color: var(--text-muted);">bar_chart</span>
        <h2>No groups available</h2>
        <p style="color: var(--text-muted);">You don't administer any groups yet.</p>
    </div>
@endforelse
```

**`@forelse` vs `@foreach`:** The difference is the `@empty` block. `@forelse` says: "try to loop through this collection, and if it's empty, show this fallback content instead." Without `@forelse`, you'd need:
```blade
@if ($groupStats->isEmpty())
    {{-- empty state --}}
@else
    @foreach ($groupStats as $item)
        {{-- normal content --}}
    @endforeach
@endif
```
`@forelse` is cleaner and expresses the intent more directly.

**The `@csrf` directive** generates a hidden input field with a CSRF token:
```html
<input type="hidden" name="_token" value="abc123...">
```
Laravel checks this token on every POST request. If the token is missing or invalid, the request is rejected. This prevents **Cross-Site Request Forgery** — an attack where a malicious site tricks a user's browser into making unwanted requests to another site.

---

## 4. Person 2: Automatic Stats & Topic Classification

### 4.1 The CalculateStatistics Command — Automation Through Scheduler

```php
// app/Console/Commands/CalculateStatistics.php

class CalculateStatistics extends Command
{
    protected $signature = 'app:calculate-statistics {groupId?}';
    protected $description = 'Calculate statistics for one or all groups';
```

**The `$signature` format:** `app:calculate-statistics` is the command name. `{groupId?}` is an optional argument (the `?` makes it optional). The user runs it as:
```bash
php artisan app:calculate-statistics     # All groups
php artisan app:calculate-statistics 5   # Only group ID 5
```

```php
    public function handle()
    {
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

**`$this->info()`** outputs green text to the console. Other console output methods:
- `$this->line()` — plain text (no color)
- `$this->warn()` — yellow text (warnings)
- `$this->error()` — red text (errors)
- `$this->newLine()` — blank line

#### Inside `calculateForGroup()` — Where the Counting Happens

```php
    private function calculateForGroup(Group $group)
    {
        // 1. Total members: straightforward count
        $totalMembers = User::where('group_id', $group->id)->count();

        // 2. Active this week: distinct users who posted in the last 7 days
        $activeMembersThisWeek = Post::whereIn('topic_id',
                Topic::where('group_id', $group->id)->pluck('id')
            )
            ->where('created_at', '>=', now()->subWeek())
            ->distinct('user_id')
            ->count();

        // 3. Total posts
        $totalPosts = Post::whereIn('topic_id',
                Topic::where('group_id', $group->id)->pluck('id')
            )->count();
```

**Note a key difference from `StatisticsUtility::recalculate()`:**

- `StatisticsUtility` counts active members using `User::where('last_active_at', '>=', now()->subWeek())` — it checks the user's last activity timestamp
- `CalculateStatistics` uses `Post::distinct('user_id')->where('created_at', '>=', now()->subWeek())` — it counts distinct users who actually posted in the last week

These can give different results! A user who visited the forum and read topics (updating `last_active_at`) but didn't post anything would be counted by `StatisticsUtility` but NOT by `CalculateStatistics`. Which is correct? It depends on what you mean by "active." The command uses a stricter definition (actually contributing content vs. just browsing).

```php
        // 4. Total topics
        $totalTopics = Topic::where('group_id', $group->id)->count();

        // 5. Unanswered questions
        $unansweredQuestions = Topic::where('group_id', $group->id)
            ->where('post_type', 'question')
            ->withCount('posts')
            ->get()
            ->filter(function ($topic) {
                return $topic->posts_count == 0;
            })
            ->count();
```

**Why `filter()` instead of `whereDoesntHave`?** This is actually less efficient than the approach in `StatisticsUtility`. `whereDoesntHave('posts')` runs as a SQL `NOT EXISTS` subquery, filtering at the database level. This approach loads ALL question topics into memory (with their post counts), then loops through them in PHP to count the zero-reply ones.

For a group with 500 question topics, both approaches work fine. For a group with 50,000 topics, the `whereDoesntHave` approach would be significantly faster.

```php
        // 6. Inactive members
        $inactiveMembers = User::where('group_id', $group->id)
            ->where(function ($q) {
                $q->where('last_active_at', '<', now()->subDays(30))
                  ->orWhereNull('last_active_at');  // Never even logged in
            })
            ->count();
```

**The `orWhereNull('last_active_at')` is a design decision.** New users who registered but never logged in have `last_active_at = NULL`. Should they be counted as "inactive 30+ days"? They haven't been active 30+ days — they've never been active at all. This code counts them as inactive, which makes sense for the purpose of sending engagement nudges.

```php
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
```

**Person 2 is reaching into Person 4's territory here.** The `CalculateStatistics` command calls `NotificationService::sendInactivityWarning()`. This is fine — services are meant to be composed. The notification service is a utility that anyone can call.

```php
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
}
```

### 4.2 The Scheduler — Making It Automatic

```php
// routes/console.php

Schedule::command('app:calculate-statistics')->dailyAt('02:00');
```

**How Laravel's scheduler works:**

1. You set up ONE cron job on your server that runs every minute:
   ```
   * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
   ```
2. Every minute, Laravel checks `routes/console.php` to see if any scheduled command is due.
3. At 2:00 AM, `app:calculate-statistics` is due → Laravel runs it.

**Why 2:00 AM?** This is a convention. 2-4 AM is typically the lowest traffic period for web applications. Running heavy calculations during peak usage would slow down the application for users.

---

### 4.3 The ClassifyTopics Command — Catching Up Old Topics

```php
// app/Console/Commands/ClassifyTopics.php

class ClassifyTopics extends Command
{
    protected $signature = 'app:classify-topics {groupId}';
    protected $description = 'Classify topics in a group';

    public function handle()
    {
        $groupId = $this->argument('groupId');

        if ($groupId) {
            $count = app(TopicClassificationService::class)->classifyGroupTopics($groupId);
            $this->info("Classified {$count} topics in group {$groupId}.");
        } else {
            $this->error('Please provide a group ID');
        }
    }
}
```

This command is meant to be run once after deploying the module, to classify all existing topics that were created before the auto-classification system existed:

```bash
php artisan app:classify-topics 1   # Classify all unclassified topics in group 1
php artisan app:classify-topics 2   # Group 2
```

After this initial run, new topics are classified automatically by the `booted()` hook on the Topic model.

---

## 5. Person 3: Recommendations Engine & UI

### 5.1 How the Recommendation Engine Works (The Algorithm)

The recommendation engine is the most algorithmically interesting piece of this module.

```php
// app/Services/RecommendationService.php

public function generateRecommendations(User $user, int $limit = 5)
{
    // ─── Step 1: What does this user like? ───────────────────────
    // Look at every topic the user has posted in, collect their category IDs
    $userEngagedCategoryIds = Topic::whereIn('id', function ($q) use ($user) {
        $q->select('topic_id')
            ->from('posts')
            ->where('user_id', $user->id);
    })
        ->whereNotNull('category_id')   // Skip unclassified topics
        ->pluck('category_id')
        ->unique()
        ->toArray();
```

**What this query does, in SQL:**

```sql
SELECT DISTINCT category_id FROM topics
WHERE id IN (
    SELECT topic_id FROM posts WHERE user_id = 5
)
AND category_id IS NOT NULL
```

**Translation:** "Find all the categories of topics that user 5 has posted in. Don't include uncategorized topics."

```php
    // ─── Step 2: New user with no history? Show popular topics ─────
    if (empty($userEngagedCategoryIds)) {
        return $this->getPopularTopics($user, $limit);
    }
```

**Why this exists:** A brand-new user who hasn't posted anything yet has no engagement history. Without this fallback, they'd get an empty recommendations section. Showing popular topics gives them something to start with.

```php
    // ─── Step 3: Find topics they'll like ───────────────────────
    $recommendations = Topic::whereIn('category_id', $userEngagedCategoryIds)
        ->where('status', 'active')
        ->when($user->group_id !== null, fn ($q) => $q->where('group_id', $user->group_id))
```

**`->when(condition, closure)`** is a conditional query builder. If the condition is true, the closure is applied. This lets you build conditional WHERE clauses without breaking the chain:
```php
// Without `when`:
$query = Topic::whereIn('category_id', $ids)->where('status', 'active');
if ($user->group_id !== null) {
    $query->where('group_id', $user->group_id);
}

// With `when`:
$query = Topic::whereIn('category_id', $ids)
    ->where('status', 'active')
    ->when($user->group_id !== null, fn ($q) => $q->where('group_id', $user->group_id));
```

For a system admin who might not have a group_id, the `when` prevents adding a `WHERE group_id = NULL` clause that would return zero results.

```php
        ->whereNotIn('id', function ($q) use ($user) {
            // Remove topics user already participated in
            $q->select('topic_id')->from('posts')->where('user_id', $user->id);
        })
        ->whereNotIn('id', function ($q) use ($user) {
            // Remove topics already recommended before
            $q->select('topic_id')->from('recommendation_log')->where('user_id', $user->id);
        })
```

**Two `whereNotIn` subqueries, two different exclusions:**

1. **Already posted in** — If the user already replied to "How to use Django ORM," why recommend it? They've already seen it.
2. **Already recommended** — If we recommended "Django REST Framework tutorial" last week and the user didn't click it, recommending it again would be annoying. The `recommendation_log` table prevents this.

**The generated SQL looks like:**
```sql
SELECT * FROM topics
WHERE category_id IN (1, 3)
  AND status = 'active'
  AND group_id = 2
  AND id NOT IN (SELECT topic_id FROM posts WHERE user_id = 5)
  AND id NOT IN (SELECT topic_id FROM recommendation_log WHERE user_id = 5)
ORDER BY created_at DESC
LIMIT 5
```

```php
        ->with('creator')     // Eager-load the topic creator
        ->with('category')    // Eager-load the category name
        ->withCount('posts')  // Add posts_count subquery
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get();
```

**Why eager-load (`with`)?** Without `with`, accessing `$topic->creator->name` in the view would trigger a NEW SQL query for each topic (the N+1 problem). With `with`, Laravel executes one query to get all 5 topics, then one query to get all 5 creators — 2 queries total instead of 6.

**Without eager loading:**
```
Query 1: SELECT * FROM topics WHERE ... LIMIT 5
Query 2: SELECT * FROM users WHERE id = ?  (for topic 1's creator)
Query 3: SELECT * FROM users WHERE id = ?  (for topic 2's creator)
...
```

**With eager loading:**
```
Query 1: SELECT * FROM topics WHERE ... LIMIT 5
Query 2: SELECT * FROM users WHERE id IN (?, ?, ?, ?, ?)  ← one query for all 5 creators
```

This is a massive performance difference at scale.

```php
    // ─── Step 4: Log the recommendations so they're never repeated ──
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

**`updateOrCreate` is critical here.** Consider what happens if the user refreshes the page while the recommendations are being generated:
- Without `updateOrCreate`: Two `INSERT` queries try to insert the same `(user_id, topic_id)` pair. The second one hits the unique constraint and throws a database error. The user sees a 500 error page.
- With `updateOrCreate`: The second query finds the existing row and updates `recommended_at` to the current time. No error.

#### The Fallback: Popular Topics

```php
private function getPopularTopics(User $user, int $limit = 5)
{
    $query = Topic::active()
        ->with('creator')
        ->with('category')
        ->withCount('posts');

    // Regular users only see popular topics in their own group
    if ($user->group_id !== null) {
        $query->forGroup($user->group_id);
    }

    return $query->orderBy('posts_count', 'desc')
        ->limit($limit)
        ->get();
}
```

**"Popular" = most replies.** A topic with 50 replies is clearly generating discussion and is more likely to interest a new user than a topic with 0 replies.

---

### 5.2 The Dashboard Controller

```php
// app/Http/Controllers/DashboardController.php

class DashboardController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        $recommendedTopics = collect();
        $recentTopics = collect();

        if ($user->isSystemAdmin() || $user->group_id) {
            $topicQuery = Topic::where('status', 'active');
```

**`collect()`** creates an empty Laravel Collection. By initializing `$recommendedTopics` and `$recentTopics` as empty collections, we ensure that the view always receives these variables — even for users who don't belong to any group.

```php
            if (! $user->isSystemAdmin()) {
                $topicQuery->whereIn('group_id', $user->accessibleGroupIds());
            }

            // 5 most recent topics
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
```

**Why `clone $topicQuery`?** Without `clone`, calling `->latest()->take(5)` would modify the original `$topicQuery` object. When we later use the query for recommendations, it would still have the `latest()->take(5)` constraints attached. `clone` creates a separate copy of the query builder object, so modifications to one don't affect the other.

**`optional($topic->creator)->full_name ?? 'Deleted User'`** — This handles a real edge case. What if the topic's creator account was deleted? `$topic->creator` would return null, and calling `->full_name` on null would throw:
```
Error: Call to a member function full_name on null
```

`optional()` returns null gracefully if the object is null. The `??` (null coalescing operator) substitutes 'Deleted User' if the result is null.

```php
            // 3 personalized recommendations
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

---

## 6. Person 4: Notifications System

### 6.1 The NotificationService — A Central Hub for All Notifications

```php
// app/Services/NotificationService.php

class NotificationService
{
    public function sendToUser(User $user, string $title, string $message,
                               string $type = 'info', array $extraData = []): Notification
    {
        $data = array_merge([
            'title' => $title,
            'message' => $message,
        ], $extraData);

        return Notification::create([
            'user_id' => $user->id,
            'group_id' => $user->group_id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,  // ← Populate the JSON field for backward compatibility
        ]);
    }
```

**Why populate BOTH the flat columns (`title`, `message`) AND the JSON `data` field?**

This is a backward-compatibility bridge. The notifications table originally had only `type` (string) and `data` (JSON). Old code (like the quiz notification center) reads from `$notification->data['title']`. New code reads from `$notification->title`.

By populating both, we can migrate gradually:
1. Old code that reads `$notification->data['title']` → still works
2. New code that reads `$notification->title` → works too
3. Eventually, when all old code is updated, we can stop populating `data`

This is called the **Strangler Fig pattern** — gradually replace a component without breaking the system.

```php
    public function sendInactivityWarning(User $user, int|string $daysInactive): void
    {
        $this->sendToUser(
            $user,
            'Inactivity Warning',
            "You haven't posted in {$daysInactive} days. Please re-engage with your group!",
            'warning',
        );
    }

    public function getUnreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->whereNull('read_at')  // Unread = read_at IS NULL
            ->count();
    }

    public function markAllAsRead(User $user): void
    {
        Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
```

**`whereNull('read_at')`** leverages the fact that `read_at` is nullable. When a notification is created, `read_at` defaults to NULL (unread). When the user reads it, we set `read_at` to the current timestamp. So `WHERE read_at IS NULL` means "give me all unread notifications."

**This is a common pattern** — simpler than having a separate `is_read` boolean column. With a boolean, you'd need to decide whether to default to 0 or 1. With a nullable timestamp, NULL = unread, any timestamp = read. Bonus: you know *when* it was read, not just *that* it was read.

### 6.2 The NotificationController — User-Facing Operations

```php
// app/Http/Controllers/NotificationController.php

public function index()
{
    $notifications = Notification::where('user_id', Auth::id())
        ->orderByRaw('read_at IS NULL DESC')  // Unread first
        ->orderByDesc('created_at')            // Then newest first
        ->paginate(20);

    return view('notifications.index', compact('notifications'));
}
```

**`orderByRaw('read_at IS NULL DESC')`** — This is a clever SQL trick worth understanding:

```sql
SELECT *, (read_at IS NULL) AS is_unread FROM notifications
WHERE user_id = 5
ORDER BY is_unread DESC, created_at DESC
```

`read_at IS NULL` evaluates to:
- 1 (true) when `read_at` IS NULL → unread
- 0 (false) when `read_at` has a value → read

Sorting DESC puts 1s before 0s, so unread notifications always appear first. Within unread, they're sorted by `created_at DESC` (newest first). Within read, same sort.

```php
public function read(int $id)
{
    $notification = Notification::findOrFail($id);

    // Ownership check — critical for privacy
    if ($notification->user_id !== Auth::id()) {
        abort(403, 'You are not authorized to update this notification.');
    }

    $notification->markAsRead();

    return redirect()->back()->with('success', 'Notification marked as read.');
}
```

**Why the ownership check?** Without it, user A could guess or brute-force notification IDs and mark user B's notifications as read. User B would miss important warnings. The check `$notification->user_id !== Auth::id()` ensures users can only modify their own notifications.

```php
public function readAll()
{
    // Single query marks everything at once — no loop needed
    Notification::where('user_id', Auth::id())
        ->whereNull('read_at')
        ->update(['read_at' => now()]);

    return redirect()->back()->with('success', 'All notifications marked as read.');
}
```

**Why a single `update()` query instead of looping?** Consider a user with 500 notifications. Looping would execute 501 queries (1 SELECT + 500 UPDATEs). The single `update()` executes 1 UPDATE query with a WHERE clause.

### 6.3 The Navbar Badge

```blade
{{-- resources/views/components/navbar.blade.php --}}

<a href="{{ route('notifications') }}" class="app-topbar-icon-btn"
   aria-label="Notifications" style="position: relative;">
    <span class="material-symbols-outlined">notifications</span>

    @php
        $unreadNotifCount = Auth::user()->notifications()->whereNull('read_at')->count();
    @endphp

    @if ($unreadNotifCount > 0)
        <span style="position: absolute; top: -5px; right: -5px;
                     background: #f44336; color: white; border-radius: 50%;
                     width: 20px; height: 20px; display: flex;
                     align-items: center; justify-content: center; font-size: 0.75rem;">
            {{ min($unreadNotifCount, 99) }}
        </span>
    @endif
</a>
```

**`min($unreadNotifCount, 99)`** — If a user has 150 unread notifications, showing "150" would overflow the badge. Capping at 99+ keeps the badge clean.

---

## 7. Person 5: Admin Configuration & Testing

### 7.1 What Already Existed Before Person 5

The admin config system was already in place.

```php
// app/Models/SystemConfig.php

class SystemConfig extends Model
{
    protected $fillable = ['config_key', 'config_value'];

    public static function getValue(string $key, $default = null)
    {
        $cacheKey = "system_config.{$key}";

        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $config = self::where('config_key', $key)->first();
            return $config ? $config->config_value : $default;
        });
    }
```

**`Cache::remember($cacheKey, 3600, $closure)`** — The most important detail here:
1. Check if `system_config.max_login_attempts` exists in the cache
2. If YES → return the cached value immediately (no database query)
3. If NO → run the closure (query the database), store the result in the cache for 3600 seconds (1 hour), and return it

This means the first request after a config change takes ~50ms (database query). The next 3599 requests for the same key take ~1ms (cache hit).

The existing `SystemConfigController`:

```php
public function update(Request $request)
{
    // ... auth check ...

    $validated = $request->validate([
        'max_login_attempts'      => 'required|integer|min:1',
        'lockout_minutes'         => 'required|integer|min:1',
        'inactivity_warning_days' => 'required|integer|min:1',
        'warning_response_days'   => 'required|integer|min:1',
        'blacklist_duration_days' => 'required|integer|min:1',
    ]);

    foreach ($validated as $key => $value) {
        SystemConfig::updateOrCreate(
            ['config_key' => $key],
            ['config_value' => $value]
        );
    }

    SystemConfig::clearAllCaches();
    $this->auditLogService->logSystemConfigUpdated($validated);

    return redirect()->back()->with('success', 'System configuration updated');
}
```

**The `foreach` loop is the key design insight here.** Instead of handling each config key individually, the loop handles ALL config keys automatically. When Person 5 adds three new keys, they only need to add them to the validation rules — the loop does the rest without modification.

### 7.2 Person 5's Additions — Three New Config Keys

#### The Migration

```php
// database/migrations/2026_07_08_000000_add_new_config_keys.php

public function up(): void
{
    $newConfigs = [
        ['config_key' => 'days_before_second_warning', 'config_value' => '14'],
        ['config_key' => 'days_before_blacklist',      'config_value' => '14'],
        ['config_key' => 'quiz_late_join_allowed',     'config_value' => '0'],
    ];

    foreach ($newConfigs as $config) {
        DB::table('system_configs')->updateOrInsert(
            ['config_key' => $config['config_key']],
            ['config_value' => $config['config_value'],
             'created_at' => now(), 'updated_at' => now()]
        );
    }
}
```

**`DB::table(...)` vs the Eloquent model:** This migration uses the Query Builder directly instead of the `SystemConfig` Eloquent model. Why? Because migrations run during deployment, before the application code is fully booted. Using the Query Builder is safer — it doesn't depend on any of Laravel's bootstrapping.

#### The Controller Update

```php
// Added to the validation rules in SystemConfigController::update():

$validated = $request->validate([
    // ... existing rules ...
    'days_before_second_warning' => 'required|integer|min:1',
    'days_before_blacklist'      => 'required|integer|min:1',
    'quiz_late_join_allowed'     => 'nullable|in:0,1',
]);
```

**`nullable|in:0,1`** — This is specific to checkbox behavior:
- When a checkbox is CHECKED, the browser submits `quiz_late_join_allowed=1`
- When a checkbox is UNCHECKED, the browser submits NOTHING for that field
- If the field is missing from the request, Laravel would fail the `required` validation
- `nullable` tells Laravel: "it's okay if this field isn't in the request — treat it as null"

### 7.3 The MonitorMemberActivity Update

The existing command implements a 3-step escalation:

```
Step 1: User inactive for N days → Issue Warning 1 (with response deadline)
Step 2: Warning 1 deadline passes → Issue Warning 2 (with response deadline)
Step 3: Warning 2 deadline passes → Blacklist user
```

**Before — both steps used the same 7-day deadline:**
```php
$warningResponseDays = (int) SystemConfig::getValue('warning_response_days', 7);
// Warning 1 deadline: 7 days
// Warning 2 deadline: 7 days
```

**After — each step has its own configurable deadline:**
```php
$secondWarningDays = (int) (SystemConfig::getValue('days_before_second_warning')
    ?: SystemConfig::getValue('warning_response_days', 7));    // Try new key, fallback to old

$blacklistDays = (int) (SystemConfig::getValue('days_before_blacklist')
    ?: SystemConfig::getValue('warning_response_days', 7));    // Same fallback pattern

// Warning 1 deadline: days_before_second_warning (14, configurable)
// Warning 2 deadline: days_before_blacklist (14, configurable)
```

**The `?:` (short ternary) fallback pattern:**

```php
SystemConfig::getValue('days_before_second_warning') ?: SystemConfig::getValue('warning_response_days', 7)
```

This reads as: "Use `days_before_second_warning`. If it's null, false, or empty, use `warning_response_days`. If THAT is also null, use 7."

**Why this matters for backwards compatibility:** The old migration only seeds `warning_response_days`. If Person 5's migration hasn't been run yet, `getValue('days_before_second_warning')` returns null, and the `?:` falls through to the existing key. The system keeps working during deployment — the new migration can run minutes after the code deploys, and no downtime occurs.

---

## 8. The Complete Data Flow

Here is how everything connects, from end to end, across all five persons' work:

### When a new topic is created:

```
1. User submits topic form
2. TopicController::store()
3. Topic::create([...]) ← Saves to database, fires "created" event
4. Topic::booted() fires
5. TopicClassificationService::classifyTopic($topic)
   ├── Lowercase title + description
   ├── Count keyword matches per category
   ├── Pick highest-scoring category
   ├── TopicCategory::firstOrCreate(...) ← Creates "Django" category if new
   └── $topic->update(['category_id' => ...]) ← Links topic to category
6. User is redirected to the new topic
```

### Every night at 2:00 AM (scheduled):

```
1. Scheduler runs app:calculate-statistics
2. For each group:
   ├── Count total members
   ├── Count active this week
   ├── Count total topics & posts
   ├── Count unanswered questions
   ├── Count inactive 30+ days
   ├── Send inactivity warnings via NotificationService
   └── Save snapshot to statistics table

3. Scheduler also runs monitor:activity (separate command)
   └── Checks each user's inactivity level
       ├── Inactive > threshold → Warning 1 (deadline from days_before_second_warning)
       ├── Warning 1 expired → Warning 2 (deadline from days_before_blacklist)
       └── Warning 2 expired → Blacklist
```

### When an admin visits the statistics dashboard:

```
1. GET /admin/statistics
2. StatisticsController@index
3. StatisticsUtility::getStatsForUser(Auth::user())
   ├── User is System Admin → return ALL groups
   ├── User is Group Admin → return their groups only
   └── User is regular → return only their own group (or none)
4. For each group:
   └── Statistics::firstOrCreate(['group_id' => $id])
       ├── Row exists → use it
       └── Row doesn't exist → create with zeros
5. Blade template renders 6 cards per group
```

### When a user visits the dashboard:

```
1. GET /dashboard
2. DashboardController@show
3. RecommendationService::generateRecommendations($user, 3)
   ├── Find categories user has posted in → [1, 3, 5]
   ├── If empty → return popular topics (fallback)
   ├── Find active topics in those categories
   ├── Exclude already-posted topics
   ├── Exclude already-recommended topics
   ├── Log each recommendation in recommendation_log
   └── Return 3 topics
4. Blade renders "Recommended for you" section
```

### When an admin changes config:

```
1. Admin visits /admin/system-config
2. Form loads with current values from system_configs table
3. Admin changes "Days Before Second Warning" from 14 → 21
4. PUT /admin/system-config
5. SystemConfigController@update
   ├── Validate: required|integer|min:1
   ├── Loop: foreach validated field → updateOrCreate
   ├── Clear all config caches
   └── Redirect back with success message
6. Next monitor:activity run reads new value
   └── Warning 1 deadline = now() + 21 days (was 14)
```

---

### Final File Inventory

| File | Person | Purpose |
|---|---|---|
| `database/migrations/*_create_statistics_table.php` | P1 | Statistics table (6 metrics, one row per group) |
| `database/migrations/*_create_recommendation_log_table.php` | P1 | Prevents duplicate recommendations |
| `database/migrations/*_add_category_id_to_topics_table.php` | P1 | Enables classification |
| `database/migrations/*_add_title_message_group_id_to_notifications.php` | P1 | Backward-compat notification columns |
| `database/migrations/*_add_new_config_keys.php` | P5 | 3 new config keys (escalation + quiz) |
| `app/Models/Statistics.php` | P1 | Model with activePercentage(), averagePostsPerTopic() |
| `app/Utilities/StatisticsUtility.php` | P1 | Shared stats computation logic |
| `app/Http/Controllers/Admin/StatisticsController.php` | P1 | Stats dashboard endpoints |
| `resources/views/admin/statistics/index.blade.php` | P1 | 6-card metric grid per group |
| `app/Console/Commands/CalculateStatistics.php` | P2 | Scheduled: computes stats + sends warnings |
| `app/Console/Commands/ClassifyTopics.php` | P2 | One-time: classify all unclassified topics |
| `app/Services/TopicClassificationService.php` | P2 | Keyword-matching "ML" classifier |
| `app/Models/Topic.php` (modified) | P2 | Added category_id + auto-classify hook |
| `app/Services/RecommendationService.php` | P3 | Personalized recommendation engine |
| `app/Http/Controllers/DashboardController.php` | P3 | Dashboard with recommendations |
| `resources/views/recommendations/index.blade.php` | P3 | Full recommendations page |
| `app/Services/NotificationService.php` | P4 | Central notification-sending service |
| `app/Http/Controllers/NotificationController.php` | P4 | Web notification center (CRUD) |
| `app/Models/Notification.php` (modified) | P4 | Added title, message, group_id, markAsRead() |
| `app/Models/SystemConfig.php` | P5 | Key-value config with 1-hour caching |
| `app/Http/Controllers/Admin/SystemConfigController.php` (modified) | P5 | Added 3 new config keys to validation |
| `resources/views/admin/system-config/index.blade.php` (modified) | P5 | Added escalation timing + quiz settings |
| `app/Console/Commands/MonitorMemberActivity.php` (modified) | P5 | Separate deadlines for W1 and W2 |
| `routes/console.php` (modified) | P2 | Added stats schedule |
| `routes/web.php` (modified) | All | All new routes |

---

### Testing Quick-Reference

1. **Statistics**: Login as System Admin → `/admin/statistics` → see cards → "Recalculate"
2. **Classification**: Create topic "How to use Django ORM" → check `topic_categories` table
3. **Recommendations**: Login as user who posted → `/dashboard` → "Recommended for you" section
4. **Notifications**: Run `php artisan app:calculate-statistics` → login as inactive user → `/notifications`
5. **Admin Config**: `/admin/system-config` → change value → save → run `monitor:activity --dry-run`

---

*End of Document — every line explained. Ask if something's still unclear.*
