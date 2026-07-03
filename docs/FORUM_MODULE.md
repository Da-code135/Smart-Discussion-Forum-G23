A# Discussion Forum Module — Complete Walkthrough

> **Author:** Person 2 (Forum Feed, Topic Creation, Threading & Replies)  
> **Depends on:** Person 1's database schema (topics, posts, post_visibility, topic_categories tables)  
> **Last Updated:** June 2026

---

## Table of Contents

1. [What Was Built](#1-what-was-built)
2. [Files Changed and Created](#2-files-changed-and-created)
3. [Routes — The Entry Points](#3-routes--the-entry-points)
4. [ForumController — The Brain](#4-forumcontroller--the-brain)
   - [index() — The Forum Feed](#41-index--the-forum-feed)
   - [create() — The Create Topic Form](#42-create--show-the-form)
   - [store() — Save a New Topic](#43-store--save-the-topic)
   - [show() — Topic Detail (The Thread)](#44-show--topic-detail-with-replies)
   - [replyStore() — Post a Reply](#45-replystore--save-a-reply)
5. [The Views (Blade Templates)](#5-the-views-blade-templates)
   - [forum/index.blade.php — The Forum Feed Page](#51-forumindexbladephp)
   - [forum/create-topic.blade.php — The Create Form](#52-forumcreate-topicbladephp)
   - [forum/show.blade.php — The Topic Detail Page](#53-forumshowbladephp)
6. [Design Decisions Explained](#6-design-decisions-explained)
7. [How To Test It Manually](#7-how-to-test-it-manually)
8. [Common Mistakes to Avoid](#8-common-mistakes-to-avoid)

---

## 1. What Was Built

I built the **core discussion forum functionality** — everything a user needs to browse topics, create new discussions, read threads, and post replies.

Before my changes, the forum was a stub:
```php
Route::get('/forum', function () {
    return "Forum page (other module)";
});
```

Now it's a fully working forum with **5 routes, a 5-method controller, and 3 Blade views**.

### The Big Picture Mental Model

```
┌─────────────────────────────────────────────────────────┐
│                   THE FORUM SYSTEM                        │
│                                                          │
│  FORUM FEED (index)                                      │
│  ┌──────────────────────────────────────────────┐        │
│  │  Topic: "How do I use a for loop?"  → Click  │        │
│  │  Topic: "Quiz 3 results are out!" → Click    │        │
│  │  Topic: "Normalisation help"      → Click    │        │
│  │  ┌────────────────────────────────────┐       │        │
│  │  │         + Create New Topic         │       │        │
│  │  └────────────────────────────────────┘       │        │
│  └──────────────────────────────────────────────┘        │
│         │ Click a topic                    │ Click create │
│         ▼                                 ▼               │
│  TOPIC DETAIL                    CREATE TOPIC FORM        │
│  ┌──────────────────────┐       ┌──────────────────┐      │
│  │ Opening Post          │       │ Title: [      ]  │      │
│  │ "How do I use..."     │       │ Type: ○Discussion│      │
│  │                       │       │       ○Question  │      │
│  │ ──── Replies ────     │       │ Description:     │      │
│  │ Reply 1: "Use for()"  │       │ [              ] │      │
│  │ Reply 2: "Try foreach"│       │ ┌──────────────┐│      │
│  │                       │       │ │ Create Topic ││      │
│  │ ──── Reply Form ────  │       │ └──────────────┘│      │
│  │ [Write here...]       │       └──────────────────┘      │
│  │ [Post Reply]          │                                 │
│  └──────────────────────┘                                 │
└─────────────────────────────────────────────────────────┘
```

---

## 2. Files Changed and Created

### Changed (1 file)

| File | What Changed |
|---|---|
| `routes/web.php` | Replaced the stub `forum.index` route. Added 5 forum routes **inside** the `auth` middleware group. |

### Created (4 files)

| File | Purpose |
|---|---|
| `app/Http/Controllers/ForumController.php` | Contains all forum logic (5 methods) |
| `resources/views/forum/index.blade.php` | The forum feed page — list of topics |
| `resources/views/forum/create-topic.blade.php` | The form to create a new topic |
| `resources/views/forum/show.blade.php` | Topic detail page — shows the thread and reply form |

---

## 3. Routes — The Entry Points

Think of routes as the **address bar URLs** that Laravel listens for. When a user types a URL, Laravel matches it to a route, which calls a controller method.

### Where they live

Routes are defined in `routes/web.php`, inside the `auth` middleware group (lines 46-60). This means:

- **A user MUST be logged in** to access any forum page
- If a guest tries to visit `/forum`, Laravel redirects them to the login page
- The `auth` middleware handles this automatically — we don't write any login-checking code

### The 5 routes

```
GET  /forum              → ForumController@index      → Name: forum.index
GET  /forum/create       → ForumController@create     → Name: forum.create
POST /forum              → ForumController@store      → Name: forum.store
GET  /forum/{topic}      → ForumController@show       → Name: forum.show
POST /forum/{topic}/reply → ForumController@replyStore → Name: forum.reply.store
```

**Route naming convention:** I used `Route::prefix('forum')->name('forum.')->group(...)`. This means:
- The prefix `'forum'` is prepended to every URI
- The name prefix `'forum.'` is prepended to every route name
- So `Route::get('/', ...)->name('index')` becomes URI `/forum` with name `forum.index`

**Why this matters:** In your Blade views, you write links like:
```blade
<a href="{{ route('forum.create') }}">Create Topic</a>
```
If you ever change the URL structure (e.g., from `/forum` to `/discussions`), you only change the route file — the views still work because they use route names, not hardcoded URLs.

### Route Model Binding

Look at the route `GET /forum/{topic}`. The `{topic}` part is special — it's a **route parameter**. When Laravel sees this, it:
1. Extracts the ID from the URL (e.g., `/forum/42` extracts `42`)
2. Automatically runs `Topic::findOrFail(42)` before your controller method even starts
3. Passes the Topic object directly to your method as `$topic`

So in the controller, you write:
```php
public function show(Topic $topic)
```
And `$topic` is already the Topic model with ID 42. If no topic with ID 42 exists, Laravel returns a 404 page automatically. This is called **Route Model Binding** and it saves you from writing `$topic = Topic::findOrFail($id)` manually.

---

## 4. ForumController — The Brain

Created at `app/Http/Controllers/ForumController.php`. This file contains all the logic. Let me walk through each method.

### 4.1 index() — The Forum Feed

```php
public function index()
{
    $topics = Topic::where('group_id', auth()->user()->group_id)
                    ->where('status', 'active')
                    ->with('creator')
                    ->withCount('posts')
                    ->latest()
                    ->paginate(10);

    return view('forum.index', compact('topics'));
}
```

**What it does:** Shows a paginated list of all active topics in the user's group.

**Step by step:**
1. `Topic::where('group_id', auth()->user()->group_id)` — Only show topics from the **same group** as the logged-in user. This is the **group isolation** rule. A BSSE Year 3 student will never see BSSE Year 1's topics.
2. `->where('status', 'active')` — Only show topics that are open. Archived topics are hidden.
3. `->with('creator')` — **Eager load** the creator (the user who started the topic). Without this, the view would fire one extra database query per topic to load the creator's name (the N+1 problem). With this, one extra query loads ALL creators at once.
4. `->withCount('posts')` — Add a `posts_count` column to the result so we can show "5 replies" without running a separate count query per topic.
5. `->latest()` — Order by newest first.
6. `->paginate(10)` — Show 10 topics per page. Laravel automatically generates Previous/Next links.
7. `return view('forum.index', compact('topics'))` — Send the topics to the Blade template.

**The N+1 problem explained:**
```
Without with('creator'):      With with('creator'):
  1 query: SELECT * FROM topics     1 query: SELECT * FROM topics
  10 queries: SELECT * FROM users    1 query: SELECT * FROM users
  (one per topic)                    WHERE id IN (3, 5, 7, ...)
  = 11 total queries                 = 2 total queries
```
Every query costs time. The difference between 11 and 2 queries adds up when 100 people visit the page simultaneously.

### 4.2 create() — Show the Form

```php
public function create()
{
    return view('forum.create-topic');
}
```

**What it does:** Simply returns the form view. No logic needed — the form itself handles what fields to show.

### 4.3 store() — Save the Topic

```php
public function store(Request $request)
{
    $request->validate([
        'title'       => 'required|max:255|unique:topics,title',
        'description' => 'required|string|max:10000',
        'post_type'   => 'sometimes|in:discussion,question',
    ]);

    Topic::create([
        'title'       => $request->title,
        'description' => $request->description,
        'post_type'   => $request->post_type ?? 'discussion',
        'created_by'  => auth()->id(),
        'group_id'    => auth()->user()->group_id,
        'status'      => 'active',
    ]);

    return redirect()->route('forum.index')
                     ->with('success', 'Topic created successfully!');
}
```

**What it does:** Validates the form data, creates a Topic record, and redirects back to the forum feed.

**The validation rules explained:**
- `'required'` — Field must not be empty
- `'max:255'` — Title cannot exceed 255 characters (database column limit)
- `'unique:topics,title'` — Title must be unique in the topics table. No duplicate threads.
- `'string'` — Must be text
- `'max:10000'` — Description limit of 10,000 characters
- `'sometimes|in:discussion,question'` — Only validate if present; must be one of the two allowed values

**The most critical line:**
```php
'group_id' => auth()->user()->group_id,
```
This is a **security-critical line**. The `group_id` is taken from the authenticated user's record — NOT from the form data. If a malicious user sends a POST request with `group_id=5` (a group they don't belong to), Laravel ignores it. The topic is always created in the user's own group.

**How this prevents data leaks:**
1. User fills form → submits
2. Even if they tamper with the POST data (e.g., add `&group_id=2` in browser DevTools)
3. The controller ignores their tampered value
4. `auth()->user()->group_id` sets it to their real group
5. Topic is created safely in their own group

**The redirect with flash message:**
```php
return redirect()->route('forum.index')
                 ->with('success', 'Topic created successfully!');
```
The `->with('success', ...)` stores the message in the session. In `layouts/app.blade.php`, there's already code that checks for `session('success')` and displays it as a green alert box. So the user sees a green "Topic created successfully!" banner on the forum feed page.

### 4.4 show() — Topic Detail with Replies

```php
public function show(Topic $topic)
{
    // === GROUP ISOLATION CHECK ===
    if ($topic->group_id !== auth()->user()->group_id) {
        abort(403, 'You do not have access to this topic.');
    }

    // === NESTED EAGER LOADING ===
    $topic->load(['posts' => function ($query) {
        $query->notRemoved()
              ->visibleToUser(auth()->id())
              ->orderBy('created_at', 'asc')
              ->with('user');
    }]);

    return view('forum.show', compact('topic'));
}
```

**What it does:** Shows a single topic with all its replies. This is the "thread view" — the core reading experience.

**Two critical things happen:**

**1. Group Isolation Check (Defense in Depth)**

```php
if ($topic->group_id !== auth()->user()->group_id) {
    abort(403, 'You do not have access to this topic.');
}
```

This is a **second line of defense**. The first line is the `index()` method filtering by group_id. But what if a user guesses the URL `/forum/42` and topic 42 belongs to a different group? Without this check, they'd see the topic. With it, they get a 403 Forbidden page.

This is called **defense in depth** — never rely on just one check.

**2. Nested Eager Loading (Performance)**

```php
$topic->load(['posts' => function ($query) {
    $query->notRemoved()
          ->visibleToUser(auth()->id())
          ->orderBy('created_at', 'asc')
          ->with('user');
}]);
```

`$topic->load(...)` is **lazy eager loading**. Unlike `with()` which loads relationships at the time of the main query, `load()` loads them after the model is already retrieved. Both achieve the same thing — preventing N+1 queries.

Let me break down what's inside:

- **`notRemoved()`** — This is a **scope** defined in the Post model:
  ```php
  public function scopeNotRemoved($query)
  {
      return $query->where('is_removed', false);
  }
  ```
  It filters out posts that were removed by a moderator. The user never sees removed content.

- **`visibleToUser(auth()->id())`** — Another scope:
  ```php
  public function scopeVisibleToUser($query, int $userId)
  {
      return $query->whereDoesntHave('visibilityExclusions', function ($q) use ($userId) {
          $q->where('excluded_user_id', $userId);
      });
  }
  ```
  This checks the `post_visibility` table. If the current user has been excluded from seeing a specific post, that post is hidden from them. The `post_visibility` table is sparse — only has rows for exceptions.

- **`orderBy('created_at', 'asc')`** — Replies are ordered oldest-first, so the thread reads chronologically.

- **`with('user')`** — Eager load the author of each reply.

**Performance guarantee:** No matter how many replies a topic has (5, 50, or 500), this method runs exactly **3 queries total**:
1. The initial `Topic::find()` (from Route Model Binding)
2. One query to load all posts (replies) for this topic
3. One query to load all authors

### 4.5 replyStore() — Save a Reply

```php
public function replyStore(Request $request, Topic $topic)
{
    // === GROUP ISOLATION CHECK ===
    if ($topic->group_id !== auth()->user()->group_id) {
        abort(403, 'You do not have access to this topic.');
    }

    // === STATUS CHECK ===
    if ($topic->status !== 'active') {
        return back()->with('error', 'This topic is closed for replies.');
    }

    $request->validate([
        'content' => 'required|string|max:10000',
    ]);

    Post::create([
        'topic_id' => $topic->id,
        'user_id'  => auth()->id(),
        'content'  => $request->content,
    ]);

    return redirect()->route('forum.show', $topic->id)
                     ->with('success', 'Reply posted successfully!');
}
```

**What it does:** Saves a new reply to a topic.

**Three checks before saving:**

1. **Group isolation** — Same check as `show()`. User must belong to the topic's group.
2. **Status check** — If the topic is `archived`, the user gets an error. Archived topics are read-only.
3. **Validation** — Content is required, must be text, max 10,000 characters.

**Creating the Post:**

```php
Post::create([
    'topic_id' => $topic->id,
    'user_id'  => auth()->id(),
    'content'  => $request->content,
]);
```

This inserts one row into the `posts` table. Remember — there's no separate `replies` table. Every row in `posts` with a `topic_id` IS a reply.

The `is_removed` field defaults to `false` (set in the migration), and `category_id` is null (set later by the ML classifier, which is Person 5's job).

**Redirect behavior:**
```php
return redirect()->route('forum.show', $topic->id)
                 ->with('success', 'Reply posted successfully!');
```
After posting, the user is redirected back to the same topic detail page. They see their new reply at the bottom of the thread, with a green success banner at the top.

---

## 5. The Views (Blade Templates)

All views extend `layouts.app`, which provides the HTML skeleton, the persistent top navbar (brand, nav links, notifications, user avatar dropdown), and flash message alerts. Each view only provides the content that goes inside the `<main>` tag. See `docs/DOCUMENTATION.md` Section 1.11 for the shared Studdit design system (colors, typography, and component classes like `.card`, `.btn`, `.badge`) that these views are built from.

### 5.1 forum/index.blade.php

**File:** `resources/views/forum/index.blade.php`

**What the user sees:**
- Header: "BSSE Year 3 Forum" (their group name)
- "New Topic" button (opens the create form)
- List of topic cards
- Pagination links at the bottom (if more than 10 topics)

**The `@forelse` loop:**
```blade
@forelse ($topics as $topic)
    <a href="{{ route('forum.show', $topic->id) }}" class="discussion-item">
        ...
    </a>
@empty
    <div class="bento-card" style="text-align: center; padding: 3rem 2rem;">
        <span class="material-symbols-outlined">forum</span>
        <h3>No topics yet</h3>
        <p>Be the first to start a discussion!</p>
        <a href="{{ route('forum.create') }}" class="btn btn-primary">Create Your First Topic</a>
    </div>
@endforelse
```

`@forelse` is a Laravel convenience — it works like `@foreach` but also handles the empty case with `@empty`. No need for an `@if(count($topics) > 0)` check.

**What each topic card shows:**
- Title (clickable link to the topic detail page)
- Question badge (if `post_type === 'question'`)
- Shortened description (using `Str::limit($topic->description, 150)`)
- Creator name, reply count, relative time (using `diffForHumans()`)

**The `posts_count` mystery:**
```blade
<span>{{ $topic->posts_count }} {{ Str::plural('reply', $topic->posts_count) }}</span>
```
Where does `posts_count` come from? Look back at the controller:
```php
->withCount('posts')
```
This adds a `posts_count` property to each Topic model. Laravel automatically appends `_count` to the relationship name. So `$topic->posts_count` contains the number of Post records for that topic — calculated in a single subquery.

`Str::plural('reply', $topic->posts_count)` ensures it says "1 reply" or "5 replies" correctly.

### 5.2 forum/create-topic.blade.php

**File:** `resources/views/forum/create-topic.blade.php`

**What the user sees:**
- "Back to Forum" link (goes back to the feed)
- Title input (required, 255 char max)
- Topic Type radio buttons (Discussion or Question)
- Description textarea (required, 10,000 char max)
- Cancel and Create Topic buttons

**The CSRF token:**
```blade
<form method="POST" action="{{ route('forum.store') }}">
    @csrf
```
Every POST form in Laravel needs `@csrf`. This generates a hidden input with a CSRF token that Laravel validates on submission. It prevents **Cross-Site Request Forgery** attacks — a malicious site can't trick a logged-in user into submitting a form on your site.

**Validation errors:**
```blade
@error('title')
    <p class="form-error">
        <span class="material-symbols-outlined">error</span>
        {{ $message }}
    </p>
@enderror
```
If validation fails, Laravel automatically redirects back and stores the errors in the session. The `@error('title')` directive checks if there's an error for the `title` field. If there is, it displays the error message. The `<input>` also gets the class `is-invalid` when there's an error (for styling).

The `{{ old('title') }}` helper repopulates the input with the previously submitted value, so the user doesn't have to retype everything.

**Topic Type radio buttons:**
```blade
<label style="...">
    <input type="radio" name="post_type" value="discussion"
           {{ old('post_type', 'discussion') === 'discussion' ? 'checked' : '' }}>
    <strong>Discussion</strong>
    <p>Open conversation and opinions</p>
</label>
<label style="...">
    <input type="radio" name="post_type" value="question"
           {{ old('post_type') === 'question' ? 'checked' : '' }}>
    <strong>Question</strong>
    <p>Seek a specific answer</p>
</label>
```

The `old('post_type', 'discussion')` means: if there was a previous submission, use that value; otherwise default to `'discussion'`. The `checked` attribute is only added if the condition matches.

This distinction matters for the ML classifier (Person 5's feature) — questions can be auto-flagged for notification when answered.

### 5.3 forum/show.blade.php

**File:** `resources/views/forum/show.blade.php`

**What the user sees:**
- Back button (navigates to the forum feed)
- Group name and Question badge (if applicable)
- **Opening Post** — the topic title + description, with the creator's avatar and name
- **Reply Count** — "Replies (7)"
- **Replies List** — each reply as a card with avatar, author, timestamp, content, optional ML category
- **Reply Form** — textarea + Post Reply button (or lock message if topic is archived)

**The opening post:**
```blade
<article class="bento-card">
    <div class="app-topbar-avatar">
        {{ collect(explode(' ', $topic->creator->full_name))
            ->map(fn($w) => strtoupper(substr($w, 0, 1)))
            ->take(2)->join('') }}
    </div>
    <div>
        <strong>{{ $topic->creator->full_name }}</strong>
        <span>{{ $topic->created_at->format('M j, Y \a\t g:ia') }}</span>
        <h1>{{ $topic->title }}</h1>
        <div style="white-space: pre-wrap;">
            {{ $topic->description }}
        </div>
    </div>
</article>
```

**The avatar initials logic:**
```php
collect(explode(' ', $topic->creator->full_name))
    ->map(fn($w) => strtoupper(substr($w, 0, 1)))
    ->take(2)->join('')
```
Step by step:
1. `explode(' ', 'Brian Ssali')` → `['Brian', 'Ssali']`
2. `collect(...)` → Laravel collection
3. `->map(fn($w) => strtoupper(substr($w, 0, 1)))` → `['B', 'S']`
4. `->take(2)` → keep only first 2 initials
5. `->join('')` → `'BS'`

This matches the same initials logic used in the navbar (`components/navbar.blade.php`), so the avatar style is consistent.

**The `white-space: pre-wrap` style:**
```blade
<div style="white-space: pre-wrap;">
    {{ $topic->description }}
</div>
```
Without this, if the user types a paragraph with line breaks, the browser collapses it into a single line. `white-space: pre-wrap` preserves line breaks while still wrapping long lines. This is essential for readable forum posts.

**The reply card:**
```blade
<article class="bento-card">
    <div class="app-topbar-avatar" style="...background: var(--tertiary)...">
        {{ collect(explode(' ', $reply->user->full_name))->map(...)->take(2)->join('') }}
    </div>
    <div>
        <strong>{{ $reply->user->full_name }}</strong>
        <span>{{ $reply->created_at->format('M j, Y \a\t g:ia') }}</span>
        @if ($reply->created_at->ne($reply->updated_at))
            &middot; edited
        @endif

        <div style="white-space: pre-wrap;">
            {{ $reply->content }}
        </div>
    </div>
</article>
```

The `$reply->created_at->ne($reply->updated_at)` check compares two Carbon dates. If they're NOT equal (`ne() = not equals`), it means the post was edited after creation, so it shows "&middot; edited" next to the timestamp.

**The reply form gating:**
```blade
@if ($topic->status !== 'active')
    <div class="alert alert-warning">
        <span class="material-symbols-outlined">lock</span>
        This topic is closed for replies.
    </div>
@else
    <form method="POST" action="{{ route('forum.reply.store', $topic->id) }}">
        @csrf
        <textarea name="content" required maxlength="10000"></textarea>
        <button type="submit">Post Reply</button>
    </form>
@endif
```
If the topic is archived, the reply form is replaced by a lock message. This is the **UI-level check**. The controller also has a **server-level check** in `replyStore()` — defense in depth.

---

## 6. Design Decisions Explained

### Decision 1: No Separate Replies Table

**What the task document said:** Create a `replies` table with `post_id`, `user_id`, `reply_content`, etc.

**What Person 1 actually built:** The `posts` table serves double duty — it stores both the opening post content (via the topic's `description` field) and all replies (as rows in the `posts` table with `topic_id`).

**Why this is fine:** The `posts` table already has all the columns needed for replies:
- `topic_id` → which topic this reply belongs to
- `user_id` → who wrote it
- `content` → what they said
- `is_removed` → moderation flag
- `category_id` → ML classification

Adding a separate `replies` table would duplicate this structure and add complexity (joins, synchronization, more models). The current design is simpler and achieves the same result.

**How it works in practice:**
- Topic's `description` = the opening discussion content
- Each `Post` with the topic's ID = one reply

### Decision 2: Group Isolation at the Controller Level

Every controller method that deals with topics checks `$topic->group_id !== auth()->user()->group_id`. This is intentional redundancy:

| Layer | Check | What It Prevents |
|---|---|---|
| **Route** | `auth` middleware | Unauthenticated access |
| **Query** | `where('group_id', auth()->user()->group_id)` in `index()` | Cross-group topics in the feed |
| **Controller** | `if ($topic->group_id !== ...)` in `show()` / `replyStore()` | Guessed URLs, tampered POST data |
| **Database** | Foreign key constraint on `topics.group_id` | Orphaned topics |

If one check fails, the others catch it. This is **defense in depth** — a standard security practice.

### Decision 3: Eager Loading Everywhere

Anywhere a view loops through related data (topics with creators, posts with authors), the controller uses `->with()` or `->load()` to eager load. This prevents the classic **N+1 query problem**.

A quick rule of thumb: if you're inside a `@foreach` loop and accessing `$item->relationship->field`, you need eager loading.

### Decision 4: Flash Messages via Session

After every action (create topic, post reply), the controller stores a success message in the session:
```php
->with('success', 'Topic created successfully!')
```

The layout template (`layouts/app.blade.php`) already has code that checks for these session keys and renders colored alert boxes:
```blade
@if (session('success'))
    <div class="alert alert-success">...</div>
@endif
```

This means I don't need to write any alert-display code in the individual views. It's already handled centrally.

### Decision 5: Routes Inside the `auth` Middleware Group

The forum routes are inside `Route::middleware('auth')->group(...)`. This means:
- All forum routes require authentication
- If a guest visits `/forum`, they're redirected to `/login`
- No need to write `auth()->check()` checks in the controller

This is the Laravel convention — put protected routes inside the auth middleware, not scattered as middleware on individual routes.

### Decision 6: Consistent Styling with Existing Conventions

The views use:
- `@extends('layouts.app')` — same layout as the dashboard
- `@section('activeNav', 'topics')` — highlights the "My Topics" nav link
- `bento-card`, `btn btn-primary`, `material-symbols-outlined` — same CSS classes as existing pages
- `page-header` with `page-header-row` — same header pattern as the dashboard
- `discussion-item` and `discussion-meta` — same classes as the "Recent Discussions" section on the dashboard

This ensures the forum pages look and feel like part of the same application, not a tacked-on module.

---

## 7. How To Test It Manually

### Prerequisites
- A running Laravel dev server (`php artisan serve`)
- A registered user account (or use the seeder)
- The forum tables migrated (`php artisan migrate` — should already be done by Person 1)

### Test the Forum Feed

1. **Log in** at `/login`
2. **Navigate** to `/forum` (or click "My Topics" in the sidebar)
3. **Expected:** You see the forum feed. If no topics exist, you see the empty state with a "Create Your First Topic" button.

### Test Topic Creation

1. Click **"New Topic"** or the **"Create Your First Topic"** button
2. Fill in:
   - Title: `"Test Discussion Topic"`
   - Type: Keep as "Discussion"
   - Description: `"This is a test topic to verify the forum works."`
3. Click **"Create Topic"**
4. **Expected:** Redirected to the feed. Green success banner: "Topic created successfully!" Your new topic appears at the top of the list.

### Test Validation

1. Try creating a topic with an **empty title** — you should see a validation error
2. Try creating a topic with the **same title** again — you should see a "The title has already been taken" error

### Test Topic Detail

1. Click on the topic you just created
2. **Expected:** You see the opening post with your name, the title, and the description
3. Below it: "Replies (0)" and an empty state message

### Test Posting a Reply

1. In the reply form, type: `"This is my first reply on this topic."`
2. Click **"Post Reply"**
3. **Expected:** Page refreshes. Green success banner. Your reply appears below "Replies (1)".

### Test Group Isolation

1. Log out, then log in as a **different user in a different group** (you'll need two test accounts)
2. Visit `/forum/1` (where 1 is the ID of the topic created above)
3. **Expected:** You get a **403 Forbidden** page because you're not in the same group
4. Visit `/forum` — you should NOT see the topic in the feed

### Test the Question Type

1. Create a new topic with type **"Question"**
2. On the feed, it should display a "Question" badge
3. On the topic detail page, it should also show the badge

---

## 8. Common Mistakes to Avoid

### ❌ Forgetting the Group Isolation Check

If you add a new controller method that loads a topic, ALWAYS add the isolation check:
```php
if ($topic->group_id !== auth()->user()->group_id) {
    abort(403);
}
```

Without it, users can view and interact with topics from other groups just by guessing the URL.

### ❌ Forgetting Eager Loading

If you write a Blade loop like:
```blade
@foreach ($topics as $topic)
    {{ $topic->creator->full_name }}
@endforeach
```
Make sure the controller has `->with('creator')`. Without it, each iteration fires a separate query.

### ❌ Not Checking `$topic->status`

Always check if a topic is `active` before creating a reply. An archived topic should be read-only. The check exists in the controller but should also be considered in any future features (edit, delete, etc.).

### ❌ Hardcoding Route URLs

Don't write:
```blade
<a href="/forum/create">Create Topic</a>
```
Always use route names:
```blade
<a href="{{ route('forum.create') }}">Create Topic</a>
```
If the URL changes, hardcoded URLs break silently. Route names break loudly (Laravel throws an exception), so you catch them immediately.

### ❌ Forgetting `@csrf` on POST forms

Every POST form must have `@csrf`. Without it, Laravel rejects the submission with a 419 Page Expired error.

### ❌ Assuming `$topic->posts[0]` Exists

If you're accessing a relationship by index, always check that it exists first, or use a `??` fallback. In our design, posts ARE the replies, and the topic's description is the opening post — so we don't access `$topic->posts[0]->content` for the opening post. We use `$topic->description` instead.

---

## Quick Reference — Key Code Snippets

```blade
{{-- Link to forum feed --}}
<a href="{{ route('forum.index') }}">Forum</a>

{{-- Link to create topic --}}
<a href="{{ route('forum.create') }}">New Topic</a>

{{-- Link to topic detail --}}
<a href="{{ route('forum.show', $topic->id) }}">{{ $topic->title }}</a>

{{-- Form to post a reply --}}
<form method="POST" action="{{ route('forum.reply.store', $topic->id) }}">
    @csrf
    <textarea name="content"></textarea>
    <button type="submit">Post Reply</button>
</form>
```

```
┌──────────────────────────────────────────────────────────┐
│                    ROUTE NAME CHEAT SHEET                 │
├────────────┬─────────────────────┬────────────────────────┤
│ Method     │ URI                 │ Route Name             │
├────────────┼─────────────────────┼────────────────────────┤
│ GET        │ /forum              │ forum.index            │
│ GET        │ /forum/create       │ forum.create           │
│ POST       │ /forum              │ forum.store            │
│ GET        │ /forum/{topic}      │ forum.show             │
│ POST       │ /forum/{topic}/reply│ forum.reply.store      │
└────────────┴─────────────────────┴────────────────────────┘
```
