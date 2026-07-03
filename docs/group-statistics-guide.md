# Group Statistics Feature — Complete Guide

## What This Feature Does

This feature adds a **Group Statistics** page to the admin panel. The **System Administrator** (and only the System Administrator) can see it. It shows:

- A table listing **every group** with summary numbers (members, topics, posts, last activity)
- A **detail page** for any single group with 17 data points about that group's membership, topics, posts, engagement, and moderation

---

## Requirement You Were Solving

> *Requirement #7 from the spec: "The administrators should be able to see relevant statistics. Each group should get its own statistics."*

The decision was made that **only the System Administrator** (not Group Administrators) sees these stats — because group-level aggregate data across the whole platform is a system-level concern.

---

## Files Created

| File | Purpose |
|---|---|
| `app/Services/GroupStatisticsService.php` | All the database queries live here. One place to compute stats. |
| `app/Http/Controllers/Admin/GroupStatisticsController.php` | Thin controller — receives HTTP requests, asks the service for data, hands it to a view. |
| `resources/views/admin/group-statistics/index.blade.php` | The "list all groups" page. Shows a table with summary stats per group. |
| `resources/views/admin/group-statistics/show.blade.php` | The "detail" page. Shows all 17 data points for one group. |

## Files Modified

| File | What Changed |
|---|---|
| `app/Models/User.php` | Added the missing `posts()` relationship |
| `routes/web.php` | Added 2 new routes inside the `system-admin` middleware group |
| `resources/views/admin/dashboard.blade.php` | Added a "Group Statistics" link in the Management Tools section |

---

## File-by-File Explanation

### 1. `app/Services/GroupStatisticsService.php` — The Brain

**Why this exists:** All the number-crunching queries live in one class. If you want to add a new statistic later, you only touch this file. Both the web pages and the API (if you build one later) call this same service.

#### `allGroupsOverview()` method

```php
public function allGroupsOverview(): array
{
    $groups = Group::withCount('users')->orderBy('group_name')->get();

    return $groups->map(fn ($group) => [
        'id'            => $group->id,
        'group_name'    => $group->group_name,
        'total_members' => $group->users_count,
        'active_members'=> $group->users()->where('account_status', 'active')->count(),
        'total_topics'  => Topic::where('group_id', $group->id)->count(),
        'total_posts'   => Post::whereIn('topic_id',
            Topic::where('group_id', $group->id)->select('id')
        )->count(),
        'last_activity' => Post::whereIn('topic_id',
            Topic::where('group_id', $group->id)->select('id')
        )->max('created_at'),
    ])->toArray();
}
```

**What it does:**

- **Line 1** — `Group::withCount('users')` fetches every group from the database. The `withCount` part adds a virtual `users_count` column to each group — it runs a subquery that counts how many users have this group's `group_id`. `orderBy('group_name')` sorts alphabetically. `get()` executes the query.

- **Lines 3-12** — For every group, it builds an associative array with 6 values:
  - `id` — the group ID, so the "View Stats" button can link to the detail page
  - `group_name` — the group's name
  - `total_members` — uses the `users_count` that `withCount` already loaded (no extra query)
  - `active_members` — runs a fresh query counting users in this group with `account_status = 'active'`
  - `total_topics` — counts all topics belonging to this group
  - `total_posts` — counts all posts across all topics in this group. Uses `whereIn('topic_id', ...)` with a subquery to get all topic IDs for this group first
  - `last_activity` — finds the most recent post creation date (`max('created_at')`) across all topics in this group. Returns `null` if no posts exist.

- **Line 13** — `.toArray()` converts the collection to a plain PHP array so the view can loop over it with `@forelse`.

**Why the nested subquery for posts?** Because `Topic` and `Post` are separate tables. A post belongs to a topic, and a topic belongs to a group. To count posts for a group, you first need to know all the topic IDs in that group. The `whereIn('topic_id', Topic::where(...)->select('id'))` pattern does this in one SQL query instead of fetching topic IDs into PHP and looping.

#### `groupDetail()` method

```php
$allUsers = $group->users();
$totalMembers  = (clone $allUsers)->count();
$activeUsers   = (clone $allUsers)->where('account_status', 'active')->count();
$warnedUsers   = (clone $allUsers)->where('account_status', 'warned')->count();
$blacklisted   = (clone $allUsers)->whereNotNull('blacklisted_at')->count();
$inactiveUsers = $totalMembers - $activeUsers - $warnedUsers - $blacklisted;
```

**What it does:** `$group->users()` returns a **query builder** — it hasn't run yet. `clone` makes a copy of that query builder. Each `(clone $allUsers)->...->count()` modifies the copy (adding a `where` clause) and executes it. This avoids creating the base query from scratch four times. The "inactive" count is calculated by subtraction — members who don't fit into active/warned/blacklisted.

```php
$topicIds = Topic::where('group_id', $group->id)->pluck('id');
```

**What it does:** Fetches all topic IDs for this group into a simple array. This array is used multiple times later — to count posts, to find top members, to find lurkers. By fetching it once at the top and reusing the variable, you save repeated database queries.

```php
$totalTopics       = $topicIds->count();
$discussionTopics  = Topic::where('group_id', $group->id)->where('post_type', 'discussion')->count();
$questionTopics    = Topic::where('group_id', $group->id)->where('post_type', 'question')->count();
$unansweredQuestions = Topic::where('group_id', $group->id)
    ->where('post_type', 'question')
    ->whereDoesntHave('posts')
    ->count();
```

**What it does:**
- `$totalTopics` — just counts the IDs in the array (no extra query)
- `$discussionTopics` — counts topics with `post_type = 'discussion'`
- `$questionTopics` — counts topics with `post_type = 'question'`
- `$unansweredQuestions` — counts question-type topics that have **zero** replies. `whereDoesntHave('posts')` means "only include topics where no posts exist in the posts table"

```php
$totalPosts      = Post::whereIn('topic_id', $topicIds)->count();
$removedPosts    = Post::whereIn('topic_id', $topicIds)->where('is_removed', true)->count();
$reportedPosts   = Post::whereIn('topic_id', $topicIds)->where('is_reported', true)->count();
$avgPostsPerTopic = $totalTopics > 0 ? round($totalPosts / $totalTopics, 1) : 0;
$avgPostsPerMember = $totalMembers > 0 ? round($totalPosts / $totalMembers, 1) : 0;
```

**What it does:**
- All three post counts use the `$topicIds` array to scope queries to this group only
- `$avgPostsPerTopic` — total posts divided by total topics. The `$totalTopics > 0` check prevents division by zero
- `$avgPostsPerMember` — total posts divided by total members. Same zero-check

```php
$topMembers = (clone $allUsers)
    ->withCount(['posts' => fn ($q) => $q->whereIn('topic_id', $topicIds)])
    ->orderByDesc('posts_count')
    ->limit(10)
    ->get()
    ->map(fn ($u) => [
        'full_name'   => $u->full_name,
        'post_count'  => $u->posts_count,
        'last_active' => $u->last_active_at?->diffForHumans(),
    ]);
```

**What it does:**
- Starts from the `$allUsers` query builder (users in this group)
- `withCount(['posts' => ...])` adds a `posts_count` to each user — counting only posts within this group's topics (the closure filters the count)
- `orderByDesc('posts_count')` sorts by most posts first
- `limit(10)` keeps only the top 10
- `get()` runs the query
- `map(...)` transforms each user row into a simpler array with just `full_name`, `post_count`, and a human-readable `last_active` (e.g. "3 days ago")
- The `?->` operator (`last_active_at?->diffForHumans()`) is PHP 8's null-safe operator — if `last_active_at` is null, it returns null instead of crashing

**This is where the original bug happened.** The `withCount(['posts' => ...])` call requires a `posts()` relationship to exist on the User model. It didn't exist, so Laravel threw `BadMethodCallException`. The fix was adding the `posts()` relationship to `User.php`.

```php
$weeklyTopics = Topic::where('group_id', $group->id)
    ->select(DB::raw("strftime('%Y-%W', created_at) as week"), DB::raw('count(*) as total'))
    ->where('created_at', '>=', now()->subWeeks(12))
    ->groupBy('week')
    ->orderBy('week')
    ->get()
    ->map(fn ($r) => ['week' => $r->week, 'topics' => $r->total]);
```

**What it does:**
- Groups topics by the week they were created, for the last 12 weeks only
- `strftime('%Y-%W', created_at)` is a **SQLite function** that takes a date and returns the year + week number — e.g. `"2026-25"` means the 25th week of 2026
- `DB::raw(...)` tells Laravel "this is raw SQL, don't try to escape it"
- `where('created_at', '>=', now()->subWeeks(12))` limits to the last 12 weeks
- The result is an array of `{week, topics}` pairs — e.g. `[['week' => '2026-25', 'topics' => 4], ['week' => '2026-26', 'topics' => 7], ...]`

**If you switch to MySQL in the future**, replace `strftime('%Y-%W', created_at)` with `DATE_FORMAT(created_at, '%X-%V')`.

```php
$lurkers = (clone $allUsers)
    ->whereDoesntHave('posts', fn ($q) => $q->whereIn('topic_id', $topicIds))
    ->count();
```

**What it does:** Counts users who belong to this group but have **never created a post** in any of this group's topics. `whereDoesntHave('posts', ...)` means "users who don't have any posts matching the condition."

```php
return [
    'group'                => $group,
    'total_members'        => $totalMembers,
    // ... all the variables ...
];
```

**What it does:** Returns an associative array where every key becomes a **variable name** in the Blade view. So in the view, `$group` is the Group model, `$total_members` is the number, `$top_members` is the array, etc.

---

### 2. `app/Http/Controllers/Admin/GroupStatisticsController.php` — The Traffic Cop

```php
class GroupStatisticsController extends Controller
{
    public function __construct(
        protected GroupStatisticsService $statsService
    ) {}
```

**What it does:** This is **constructor property promotion** (PHP 8). It declares a property `$statsService` of type `GroupStatisticsService` and Laravel's dependency injection automatically creates an instance of `GroupStatisticsService` and passes it in. You don't need to manually instantiate anything.

```php
public function index()
{
    $groups = $this->statsService->allGroupsOverview();
    return view('admin.group-statistics.index', compact('groups'));
}
```

**What it does:** Calls the service to get the overview array, then returns the index Blade view with the data. `compact('groups')` is equivalent to `['groups' => $groups]`.

```php
public function show(Group $group)
{
    $stats = $this->statsService->groupDetail($group);
    return view('admin.group-statistics.show', $stats);
}
```

**What it does:** Uses **route-model binding** — Laravel sees `{group}` in the URL and automatically fetches the Group by ID. It calls the service and passes the result array directly to the view. Because `$stats` is an associative array with keys like `'total_members'`, `'top_members'`, etc., those all become individual variables in the view.

---

### 3. `resources/views/admin/group-statistics/index.blade.php` — The Group List Page

```blade
@extends('layouts.app')

@section('title', 'Group Statistics')
@section('admin')

@section('content')
<div class="container">
    <div class="admin-header">
        <h1>Group Statistics</h1>
        <p>Overview of all groups — click a group for detailed analytics</p>
    </div>
```

**What it does:**
- `@extends('layouts.app')` — this view inherits from the main app layout (which includes the navbar, footer, CSS, and JS)
- `@section('title', 'Group Statistics')` — sets the page title shown in the browser tab
- `@section('admin')` — tells the layout to load `admin.css` styles (the layout checks `@hasSection('admin')` to decide whether to include admin styles)
- `@section('content')` — everything between here and `@endsection` is placed into the layout's `@yield('content')` position

```blade
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Group Name</th>
                <th>Members</th>
                <th>Active Members</th>
                <th>Topics</th>
                <th>Posts</th>
                <th>Last Activity</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($groups as $group)
                <tr>
                    <td><strong>{{ $group['group_name'] }}</strong></td>
                    <td><span class="member-badge">{{ $group['total_members'] }}</span></td>
                    <td>{{ $group['active_members'] }}</td>
                    <td>{{ $group['total_topics'] }}</td>
                    <td>{{ $group['total_posts'] }}</td>
                    <td>
                        {{ $group['last_activity'] ? \Carbon\Carbon::parse($group['last_activity'])->diffForHumans() : 'No activity' }}
                    </td>
                    <td>
                        <a href="{{ route('admin.group-statistics.show', $group['id']) }}"
                           class="btn btn-primary btn-sm">
                            View Stats
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2rem;">
                        No groups found
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
```

**What it does:**
- `@forelse` is Laravel's "loop or show empty state" directive. If `$groups` has items, it loops. If empty, it renders the `@empty` block.
- Each `$group` is an array (from the service), so values are accessed with `$group['key']` syntax — this is NOT an Eloquent model, it's a plain PHP array.
- The **Last Activity** column shows `\Carbon\Carbon::parse(...)->diffForHumans()` — this converts a timestamp like `2026-07-15 14:30:00` into "3 days ago" or "2 weeks ago". The `? :` ternary shows "No activity" if the value is null.
- The **View Stats** button uses `route('admin.group-statistics.show', $group['id'])` — this generates a URL like `/admin/group-statistics/1`, and Laravel's route-model binding converts the `1` into a Group model in the controller.

---

### 4. `resources/views/admin/group-statistics/show.blade.php` — The Detail Page

```blade
@extends('layouts.app')

@section('title', "Stats - $group->group_name")
@section('admin')

@section('content')
<div class="container">
    <div class="admin-header">
        <h1>{{ $group->group_name }} — Statistics</h1>
        <a href="{{ route('admin.group-statistics.index') }}" class="btn btn-secondary">
            &larr; Back to all groups
        </a>
    </div>
```

**What it does:** Sets up the page with the group name in the title and a "Back" link to return to the index page.

```blade
{{-- Row 1: Membership --}}
<div class="dashboard-grid" style="margin-bottom: 2rem;">
    <div class="dashboard-card">
        <h3>Total Members</h3>
        <div class="number">{{ $total_members }}</div>
    </div>
    <div class="dashboard-card">
        <h3>Active</h3>
        <div class="number">{{ $active_members }}</div>
    </div>
    <div class="dashboard-card">
        <h3>Warned</h3>
        <div class="number">{{ $warned_members }}</div>
    </div>
    <div class="dashboard-card">
        <h3>Blacklisted</h3>
        <div class="number">{{ $blacklisted_members }}</div>
    </div>
    <div class="dashboard-card">
        <h3>Inactive</h3>
        <div class="number">{{ $inactive_members }}</div>
    </div>
    <div class="dashboard-card">
        <h3>Never Posted</h3>
        <div class="number">{{ $lurkers }}</div>
    </div>
</div>
```

**What it does:** Uses the same `dashboard-card` and `dashboard-grid` classes from the admin dashboard for visual consistency. Each card shows a label and a number. The variables (`$total_members`, `$active_members`, etc.) come from the service — they're the keys returned in the `groupDetail()` array.

```blade
{{-- Row 2: Topics & Posts --}}
...
    <div class="dashboard-card">
        <h3>Unanswered Questions</h3>
        <div class="number" style="color: {{ $unanswered_questions > 0 ? '#dc3545' : '#28a745' }};">
            {{ $unanswered_questions }}
        </div>
    </div>
```

**What it does:** The unanswered questions count changes color — red (`#dc3545`) if there are any unanswered questions (something to worry about), green (`#28a745`) if zero (all questions got answers).

```blade
{{-- Row 3: Moderation --}}
...
```

**What it does:** Shows removed posts, reported posts, and average posts per member.

```blade
{{-- Weekly Topic Trend --}}
<div class="admin-header">
    <h2>Topics Created Per Week (Last 12 Weeks)</h2>
</div>
<div class="table-container" style="margin-bottom: 2rem;">
    <table>
        <thead>
            <tr>
                <th>Week</th>
                <th>Topics Created</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($weekly_topics as $week)
                <tr>
                    <td>{{ $week['week'] }}</td>
                    <td>{{ $week['topics'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" style="text-align: center;">No topics in the last 12 weeks</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
```

**What it does:** Renders the weekly trend as a simple two-column table. Each `$week` is an array with `['week' => '2026-25', 'topics' => 4]`. Later you could replace this with a bar chart (Chart.js) — the data structure is already designed for it: x-axis = week, y-axis = count.

```blade
{{-- Most Active Members --}}
<div class="admin-header">
    <h2>Top 10 Most Active Members</h2>
</div>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Member</th>
                <th>Posts</th>
                <th>Last Active</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($top_members as $index => $member)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $member['full_name'] }}</td>
                    <td>{{ $member['post_count'] }}</td>
                    <td>{{ $member['last_active'] ?? 'Never' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center;">No posts yet</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
```

**What it does:** Lists the top 10 posters. `$index` comes from `@forelse ... as $index => $member` — it's the loop counter (0-based), so `$index + 1` gives 1-10. `$member['last_active'] ?? 'Never'` — if the user has never been active, show "Never" (the `??` operator catches null).

---

### 5. `routes/web.php` — The Two New Routes

```php
// System Config (#92) - System Admin only
Route::middleware(['system-admin'])->group(function () {
    Route::get('/system-config', [\App\Http\Controllers\Admin\SystemConfigController::class, 'index'])
        ->name('admin.system-config.index');
    Route::put('/system-config', [\App\Http\Controllers\Admin\SystemConfigController::class, 'update'])
        ->name('admin.system-config.update');

    // Group Statistics - System Admin only
    Route::get('/group-statistics', [\App\Http\Controllers\Admin\GroupStatisticsController::class, 'index'])
        ->name('admin.group-statistics.index');
    Route::get('/group-statistics/{group}', [\App\Http\Controllers\Admin\GroupStatisticsController::class, 'show'])
        ->name('admin.group-statistics.show');
});
```

**What it does:**

- **URLs:** These routes respond to `/admin/group-statistics` and `/admin/group-statistics/{group}` (where `{group}` is a numeric ID). The full URL includes `/admin` because this is nested inside `Route::prefix('admin')->middleware('admin')->group(...)` on line 225.

- **Middleware chain:** `admin` middleware (on the outer group) → `system-admin` middleware (on this inner group). This means:
  - User must be logged in
  - User must be an admin (either System Admin or Group Admin) — from the `admin` middleware
  - User must be a **System Administrator** specifically — from the `system-admin` middleware
  - If a Group Admin tries to access this URL, they get a 403 Forbidden error

- **Route names:** `admin.group-statistics.index` and `admin.group-statistics.show`. These are used in the Blade templates with `route('admin.group-statistics.index')` and `route('admin.group-statistics.show', $group['id'])`.

- **`{group}` parameter:** Laravel's route-model binding automatically fetches the `Group` model by ID when the controller method type-hints it: `public function show(Group $group)`.

- **Inside an `auth` middleware group:** The outer `Route::prefix('admin')->middleware('admin')` block (line 225) already ensures the user is authenticated. The `system-admin` middleware narrows it further.

---

### 6. `app/Models/User.php` — The Missing Relationship

```php
public function posts()
{
    return $this->hasMany(Post::class);
}
```

**What it does:** This was the bug fix. The `GroupStatisticsService` calls `->withCount(['posts' => ...])` and `->whereDoesntHave('posts', ...)` which both need a `posts()` relationship on the User model. The `Post` model already had `public function user()` (belongsTo), but the inverse `hasMany` was missing from `User`. Adding it tells Laravel: "A User can have many Posts, and the foreign key is `user_id` on the `posts` table."

This is a standard Laravel relationship — Eloquent automatically assumes the foreign key is `user_id` because the method is named `posts` and the related model is `Post`.

---

### 7. `resources/views/admin/dashboard.blade.php` — The Navigation Link

```blade
{{-- System Admin only links --}}
@if (auth()->user()->isSystemAdmin())
    <a href="{{ route('admin.group-statistics.index') }}" class="link-btn">
        📊 Group Statistics
    </a>

    <a href="{{ route('admin.system-config.index') }}" class="link-btn">
        ⚙️ System Configuration
    </a>

    <a href="{{ route('admin.ip-whitelist.index') }}" class="link-btn">
        🔒 IP Whitelist
    </a>
@endif
```

**What it does:** The `@if (auth()->user()->isSystemAdmin())` check ensures only System Administrators see these links. Regular admins and group admins who visit the dashboard will see User Management, Group Management, Moderation, and Audit Logs — but NOT Group Statistics, System Config, or IP Whitelist.

The link routes to `admin.group-statistics.index` which generates the URL `/admin/group-statistics`.

---

## How the Request Flows (End to End)

Here's what happens when you click the **"Group Statistics"** link:

```
1. Browser navigates to  /admin/group-statistics

2. Laravel Router checks:
   - Is user authenticated?          ← outer 'admin' middleware (line 225)
   - Is user an admin?               ← outer 'admin' middleware (line 225)
   - Is user a System Administrator? ← inner 'system-admin' middleware (line 262)

3. Route matched: GET /admin/group-statistics
   → GroupStatisticsController::index()

4. Controller calls:
   → GroupStatisticsService::allGroupsOverview()
     → Queries database for all groups with counts
     → Returns array of group stats

5. Controller renders:
   → resources/views/admin/group-statistics/index.blade.php
     → Table with all groups and their summary numbers

6. User clicks "View Stats" on a group row → /admin/group-statistics/1

7. Router checks same middleware chain

8. Route matched: GET /admin/group-statistics/{group}
   → Route-model binding fetches Group with ID=1
   → GroupStatisticsController::show(Group $group)

9. Controller calls:
   → GroupStatisticsService::groupDetail($group)
     → Queries database for all 17 data points
     → Returns array of stats

10. Controller renders:
    → resources/views/admin/group-statistics/show.blade.php
      → Cards for membership, topics, posts, moderation
      → Weekly trend table
      → Top 10 members table
```

---

## Summary of All 17 Data Points Per Group

| # | Variable | Source Table | What It Measures |
|---|---|---|---|
| 1 | `$total_members` | `users` | Everyone in this group |
| 2 | `$active_members` | `users.account_status` | Active users |
| 3 | `$warned_members` | `users.account_status` | Warned users |
| 4 | `$blacklisted_members` | `users.blacklisted_at` | Blacklisted users |
| 5 | `$inactive_members` | Calculated | Total - Active - Warned - Blacklisted |
| 6 | `$lurkers` | `users` + `posts` | Members who never posted |
| 7 | `$total_topics` | `topics` | All topics in the group |
| 8 | `$discussion_topics` | `topics.post_type` | Discussion-type topics |
| 9 | `$question_topics` | `topics.post_type` | Question-type topics |
| 10 | `$unanswered_questions` | `topics` + `posts` | Questions with zero replies |
| 11 | `$total_posts` | `posts` | All replies |
| 12 | `$removed_posts` | `posts.is_removed` | Moderated/removed posts |
| 13 | `$reported_posts` | `posts.is_reported` | Flagged posts |
| 14 | `$avg_posts_per_topic` | Calculated | Engagement per topic |
| 15 | `$avg_posts_per_member` | Calculated | Engagement per person |
| 16 | `$weekly_topics` | `topics.created_at` | Topics per week (12 weeks) |
| 17 | `$top_members` | `users` + `posts` | Top 10 by post count |

---

## No New Migrations or Schema Changes

Every statistic is computed from columns that already existed in the database:

| Column | Already Existed for |
|---|---|
| `users.group_id` | Group membership |
| `users.account_status` | User status (active/warned/blacklisted) |
| `users.blacklisted_at` | Blacklist tracking |
| `users.last_active_at` | Activity tracking |
| `topics.group_id` | Topic-to-group assignment |
| `topics.post_type` | Topic type (discussion/question) |
| `topics.created_at` | Topic creation date |
| `posts.topic_id` | Post-to-topic assignment |
| `posts.is_removed` | Moderation |
| `posts.is_reported` | Moderation |
| `posts.user_id` | Post-to-user assignment |

The `posts()` relationship on `User` was missing from the model (so Laravel didn't know about it), but the column `posts.user_id` has always existed in the database. Adding the relationship just told Laravel: "yes, this foreign key exists."
