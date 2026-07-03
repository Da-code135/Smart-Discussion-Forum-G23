# PERSON 4: VISIBILITY RULES & TOPIC FILTERING
## Complete Implementation Guide for Studdit Project

---

## TABLE OF CONTENTS

1. **Overview & Context**
2. **Task 4.1: Selective Communication (Visibility Rules)**
3. **Task 4.2: Topic Filtering Verification**
4. **Implementation Steps (Quick Start)**
5. **Code Breakdown & Explanations**
6. **Testing Checklist**
7. **Common Pitfalls & Debugging**
8. **Integration with Person 2's Code**

---

## 1. OVERVIEW & CONTEXT

### What You're Building

Person 4 implements two critical features:

**Task 4.1: Selective Communication**
- Post authors can exclude specific users from seeing their replies
- Creates `PostVisibility` records in the database
- Exclusions are enforced everywhere: web views, PDFs, exports

**Task 4.2: Topic Filtering Verification**
- Ensures topics from other groups are never visible to a user
- Validates that group isolation is enforced correctly
- Provides debugging tools to catch breaches

### Why This Matters

- **Security**: Users in Group A cannot leak data to Group B
- **Privacy**: Members can control who sees sensitive replies
- **Compliance**: Maintains audit trail of who excluded whom

### Dependencies

Your work depends on:
- ✅ Person 1: Database schema (tables exist)
- ✅ Person 2: Models, controllers, views (forum works)

Your work unblocks:
- Person 5: PDF export must respect visibility rules
- Admin: Moderation must work within group isolation

---

## 2. TASK 4.1: SELECTIVE COMMUNICATION (VISIBILITY RULES)

### The Feature in Plain English

When Alice posts a reply, she can say "I don't want Bob to see this."

**Before Person 4's work:**
```
Topic: "How to debug?"
  ├─ Alice's reply: "Print statements are useful"
  │  └─ Bob can see this
  └─ Bob's reply: "I prefer breakpoints"
```

**After Person 4's work:**
```
Topic: "How to debug?"
  ├─ Alice's reply: "Print statements are useful"
  │  └─ Bob excluded (cannot see this)
  └─ Bob's reply: "I prefer breakpoints"
     └─ Alice can see this
```

### Database Record Structure

When Alice excludes Bob from her reply, this is stored:

```
Table: post_visibility
┌─────────────────────────────────────┐
│ id  │ post_id │ excluded_user_id    │
├─────┼─────────┼─────────────────────┤
│  1  │    42   │       5 (Bob)       │
└─────────────────────────────────────┘
```

This single record means:
- Post #42 (Alice's reply)
- User #5 (Bob) cannot see it

---

## 3. TASK 4.2: TOPIC FILTERING VERIFICATION

### Group Isolation Enforcement Points

Your code must enforce group isolation at **three layers**:

#### Layer 1: Database Query Filter
In the controller's `index()` method (inherited from Person 2a):
```php
$topics = Topic::where('group_id', auth()->user()->group_id)
               ->get();
```
**Layer 1 Rule**: Every topic query must have `where('group_id', ...)`

#### Layer 2: Authorization Check
In the controller's `show()` method (inherited from Person 2b):
```php
if ($topic->group_id !== auth()->user()->group_id) {
    abort(403, 'You do not have access to this topic.');
}
```
**Layer 2 Rule**: Validate the topic belongs to the user's group before rendering

#### Layer 3: View Isolation
In the Blade template, never display topics outside the queried group:
```blade
@foreach ($topics as $topic)
    {{-- Only topics from the user's group are in this loop --}}
    <h2>{{ $topic->title }}</h2>
@endforeach
```
**Layer 3 Rule**: Views only iterate over already-filtered collections

Your job as Person 4: **Verify all three layers exist**.

---

## 4. IMPLEMENTATION STEPS (QUICK START)

### Pre-Implementation Checklist

- [ ] Have Person 1's migrations run successfully (`php artisan migrate`)
- [ ] Have Person 2a's ForumController with `index()` method
- [ ] Have Person 2b's ForumController with `show()` method
- [ ] Have Post and Topic models with relationships
- [ ] Have `post_visibility` table from Person 1

### Step 1: Create PostVisibility Model

```bash
# Don't use make:model --migration, because migrations are already done by Person 1
php artisan make:model PostVisibility
```

Copy the provided `PostVisibility.php` into `app/Models/PostVisibility.php`.

**Key Points:**
- `$fillable = ['post_id', 'excluded_user_id']` (only these can be mass-assigned)
- Has relationships: `post()` and `excluded_user()`
- Has helper methods: `isExcluded()`, `getExcludedUsers()`

### Step 2: Update Post Model Relationship

Add this to your existing `Post.php` model:

```php
public function visibility_rules()
{
    return $this->hasMany(PostVisibility::class, 'post_id');
}
```

This lets you write: `$post->visibility_rules` to get all exclusion records.

### Step 3: Add Controller Methods to ForumController

Copy the provided `ForumController_Person4.php` methods into your existing `ForumController.php`:

- `excludeUser()` – Create an exclusion
- `removeExclusion()` – Delete an exclusion
- `getGroupUsers()` – Return JSON list of group members
- `excludeBatch()` – Exclude multiple users at once
- `viewVisibilityRules()` – Show all exclusions for a post
- `verifyTopicAccess()` – Check if user can access a topic
- `getVisibleReplies()` – Filter replies in application code
- `getVisibleRepliesOptimized()` – Filter replies at database level
- `debugTopicAccess()` – Debug authorization issues

**Implementation Note:**
Merge these into your existing `ForumController` class. Don't create a separate controller.

### Step 4: Add Routes

Copy the provided routes from `routes_person4.php` into your `routes/web.php` file.

Add inside the `Route::middleware('auth')->group(function () { ... })` block:

```php
Route::post('/post/{post}/visibility/exclude', [\App\Http\Controllers\ForumController::class, 'excludeUser'])->name('visibility.exclude');
Route::post('/forum/{post}/visibility/exclude', [ForumController::class, 'excludeUser'])
    ->name('forum.visibility.exclude');

Route::post('/forum/{post}/visibility/remove-exclusion', [ForumController::class, 'removeExclusion'])
    ->name('forum.visibility.remove-exclusion');

Route::get('/forum/visibility/users', [ForumController::class, 'getGroupUsers'])
    ->name('forum.visibility.users');

Route::post('/forum/{post}/visibility/exclude-batch', [ForumController::class, 'excludeBatch'])
    ->name('forum.visibility.exclude-batch');

Route::get('/forum/{post}/visibility/rules', [ForumController::class, 'viewVisibilityRules'])
    ->name('forum.visibility.rules');

Route::get('/debug/forum/{topic}/access', [ForumController::class, 'debugTopicAccess'])
    ->name('forum.debug.access');
```

### Step 5: Create Blade Views

Create three new files in `resources/views/forum/`:

**File 1: `resources/views/forum/partials/visibility-form.blade.php`**
- Shows current exclusions for a post
- Allows author to add/remove exclusions
- Dropdown to select users to exclude

**File 2: `resources/views/forum/visibility-rules.blade.php`**
- Full page for managing all exclusions of a post
- Table showing all excluded users
- Form to add new exclusions

**File 3: `resources/views/forum/partials/reply-with-visibility.blade.php`**
- Enhanced reply card for forum detail view
- Shows exclusion status
- Hides post from excluded users

Copy these from the provided `views_person4.blade.php` file.

### Step 6: Integrate Views into Forum Detail View

Update `resources/views/forum/show.blade.php` (Person 2b's file) to use the new visibility-aware reply display:

**Before (Person 2b):**
```blade
@forelse ($topic->posts[0]->replies as $reply)
    <div class="reply-card">
        <p><strong>{{ $reply->author->full_name }}</strong></p>
        <p>{{ $reply->reply_content }}</p>
    </div>
@empty
    <p>No replies yet.</p>
@endforelse
```

**After (Person 4):**
```blade
@forelse ($topic->posts[0]->replies as $reply)
    @include('forum.partials.reply-with-visibility', [
        'reply' => $reply,
        'post' => $topic->posts[0],
        'currentUser' => auth()->user()
    ])
@empty
    <p>No replies yet.</p>
@endforelse
```

---

## 5. CODE BREAKDOWN & EXPLANATIONS

### Method: excludeUser()

```php
public function excludeUser(Request $request, Post $post)
{
    // Step 1: Check authorization
    if ($post->user_id !== auth()->id()) {
        abort(403, 'Only the post author can exclude users.');
    }
    
    // Step 2: Validate input
    $request->validate([
        'user_id' => 'required|exists:users,id'
    ]);
    
    // Step 3: Check if rule already exists
    $existing = PostVisibility::where('post_id', $post->id)
                               ->where('excluded_user_id', $request->user_id)
                               ->first();
    
    if ($existing) {
        return back()->with('info', 'User already excluded.');
    }
    
    // Step 4: Create the visibility rule
    PostVisibility::create([
        'post_id' => $post->id,
        'excluded_user_id' => $request->user_id,
    ]);
    
    return back()->with('success', 'User excluded from this post.');
}
```

**How It Works:**
1. Verify the person making the request is the post author
2. Validate that the user_id they're excluding actually exists in the database
3. Check if an exclusion rule already exists (prevent duplicates)
4. Create the `PostVisibility` record
5. Redirect back with a success message

**Security Decisions:**
- `if ($post->user_id !== auth()->id())`: Only the author can exclude users
- `validate(['user_id' => 'exists:users,id'])`: Prevent excluding non-existent users
- Check for existing rules: Prevent duplicate records cluttering the database

---

### Method: getVisibleReplies()

This is a **helper method** that filters out excluded posts.

```php
public function getVisibleReplies(Post $post, User $user)
{
    // Get all non-removed replies
    $replies = $post->replies()
                    ->where('is_removed', false)
                    ->with('author')
                    ->get();

    // Filter out replies the user is excluded from
    $visibleReplies = $replies->filter(function ($reply) use ($user) {
        $isExcluded = PostVisibility::where('post_id', $reply->id)
                                     ->where('excluded_user_id', $user->id)
                                     ->exists();
        return !$isExcluded;  // Keep it if NOT excluded
    });

    return $visibleReplies;
}
```

**How It Works:**
1. Load all non-removed replies for the post
2. For each reply, check if a `PostVisibility` record exists
3. If a record exists, the user is excluded → filter them out
4. Return only visible replies

**Performance Note:**
This approach loads all replies into memory, then filters in application code.
This is fine for MVP (most topics have < 100 replies), but for scale, use `getVisibleRepliesOptimized()` which filters at the database level using `whereDoesntHave()`.

---

### Method: verifyTopicAccess()

A simple helper to check if a user can access a topic:

```php
public function verifyTopicAccess(Topic $topic)
{
    if ($topic->group_id !== auth()->user()->group_id) {
        abort(403, 'Access denied.');
    }
    return true;
}
```

**Why Separate This Out?**
If you have multiple methods that need to verify access (show, export PDF, share), you can reuse this method instead of repeating the check. DRY principle (Don't Repeat Yourself).

---

### Helper Model: PostVisibility::isExcluded()

This is a **static helper method** on the PostVisibility model:

```php
// In PostVisibility.php
public static function isExcluded($postId, $userId)
{
    return self::where('post_id', $postId)
               ->where('excluded_user_id', $userId)
               ->exists();
}
```

**Usage in Views:**
```blade
@if (PostVisibility::isExcluded($reply->id, auth()->id()))
    <p>This reply is hidden from you.</p>
@else
    <p>{{ $reply->reply_content }}</p>
@endif
```

This is cleaner than writing the full query every time.

---

## 6. TESTING CHECKLIST

### Unit Tests (Manual Testing)

#### Test 4.1.1: Create Exclusion
```
Scenario: Alice (author) excludes Bob from her reply

Setup:
  - User Alice is logged in
  - Alice has posted a reply (ID: 42)
  - User Bob exists in Alice's group

Test Steps:
  1. Alice visits /forum/1 (topic detail)
  2. Alice finds her reply
  3. Alice clicks "Exclude user" button
  4. Alice selects Bob from dropdown
  5. Alice clicks "Exclude"

Expected Result:
  ✓ Page redirects with "User excluded from this post."
  ✓ A new PostVisibility record is created:
    - post_id = 42
    - excluded_user_id = Bob's ID
  ✓ Bob's page now hides Alice's reply

Verification:
  php artisan tinker
  > PostVisibility::where('post_id', 42)->count()
  => 1  # One exclusion rule exists
```

#### Test 4.1.2: Cannot Exclude Non-Author
```
Scenario: Bob tries to exclude someone from Alice's reply

Setup:
  - Alice's reply (ID: 42)
  - Bob is NOT the author

Test Steps:
  1. Bob navigates to POST /forum/42/visibility/exclude
  2. Bob submits user_id in the form

Expected Result:
  ✓ 403 Forbidden error
  ✓ No PostVisibility record is created
  ✓ Error message: "Only the post author can exclude users"
```

#### Test 4.1.3: Cannot Exclude Cross-Group Users
```
Scenario: Alice (Group 1) tries to exclude Bob (Group 2)

Test Steps:
  1. Alice's reply in Group 1
  2. Alice tries to exclude Bob (Group 2 user)
  3. Submitform

Expected Result:
  ✓ Error message: "You can only exclude users from your own group"
  ✓ No PostVisibility record created
```

#### Test 4.2.1: Group Isolation in Forum Feed
```
Scenario: User from Group A tries to see topics from Group B

Setup:
  - User A in BSSE Year 3 (group_id = 1)
  - User B in BSSE Year 1 (group_id = 2)
  - Topic "Advanced Database Design" created by User B (group_id = 2)

Test Steps:
  1. Log in as User A
  2. Visit /forum (forum feed)

Expected Result:
  ✓ "Advanced Database Design" topic NOT visible
  ✓ Only topics from group_id = 1 are shown
  ✓ Database: verify query has where('group_id', 1)
```

#### Test 4.2.2: Group Isolation via Direct URL
```
Scenario: User A guesses Topic ID from Group B and tries direct access

Setup:
  - User A in Group 1
  - Topic #99 in Group 2

Test Steps:
  1. Log in as User A
  2. Visit /forum/99 directly (guessing the ID)

Expected Result:
  ✓ 403 Forbidden error
  ✓ Topic #99 content not displayed
  ✓ Error message shows in logs
```

#### Test 4.2.3: Visibility Rules Apply in Views
```
Scenario: Bob loads a topic where Alice excluded him from one reply

Setup:
  - Topic with 3 replies:
    - Reply 1 (Alice) – Bob excluded
    - Reply 2 (Carol) – Bob NOT excluded
    - Reply 3 (Dave) – Bob NOT excluded

Test Steps:
  1. Log in as Bob
  2. Visit the topic detail page

Expected Result:
  ✓ Bob sees Reply 2 and Reply 3
  ✓ Bob does NOT see Reply 1
  ✓ Page shows "2 replies" (not 3)
  ✓ If Bob clicks "View Rules", he doesn't see Alice's reply listed
```

### Integration Tests

#### Test 4.3.1: Visibility + PDF Export (with Person 5)
```
Scenario: Bob exports a topic as PDF where Alice excluded him

Setup:
  - Topic with replies
  - One reply from Alice (Bob is excluded)

Test Steps:
  1. Log in as Bob
  2. Click "Export as PDF" button
  3. Download PDF

Expected Result:
  ✓ PDF downloads successfully
  ✓ PDF shows only visible replies
  ✓ Alice's reply NOT in PDF
  ✓ Comment in PDF says "3 replies" not "4 replies"
```

#### Test 4.3.2: Visibility + Moderation (with Person 3)
```
Scenario: Admin removes a post that has visibility rules

Setup:
  - Post with visibility rules (2 users excluded)
  - Post is flagged for removal

Test Steps:
  1. Admin removes the post via /admin/moderation
  2. Check database cleanup

Expected Result:
  ✓ Post is marked is_removed = true
  ✓ Visibility rules should be cleaned up or kept (clarify with team)
  
Note: Add cleanup logic to Post model:
  public static function boot() {
      parent::boot();
      static::deleted(function ($post) {
          PostVisibility::where('post_id', $post->id)->delete();
      });
  }
```

---

## 7. COMMON PITFALLS & DEBUGGING

### Pitfall 1: N+1 Query Problem in Views

**Problem:**
```blade
@foreach ($replies as $reply)
    @if (PostVisibility::isExcluded($reply->id, auth()->id()))
        {{-- WRONG: This runs a query per reply! --}}
    @endif
@endforeach
```

Looping through 50 replies = 50 extra database queries.

**Solution:**
Use `getVisibleRepliesOptimized()` in the controller:

```php
$visibleReplies = $this->getVisibleRepliesOptimized($post, auth()->user())->get();
```

This filters at the database level with `whereDoesntHave()`. Only 1 extra query total.

---

### Pitfall 2: Forgetting Group Scoping

**Problem:**
```php
$excludedUsers = PostVisibility::where('post_id', 42)
                               ->with('excluded_user')
                               ->get();
// WRONG: This loads ALL excluded users, even from other groups
```

If Alice (Group 1) and Bob (Group 2) are both excluded from different posts, you might accidentally show Bob's name in Alice's admin panel.

**Solution:**
Always filter by group:

```php
$excludedUsers = PostVisibility::where('post_id', 42)
                               ->whereHas('post.topic', function ($q) {
                                   $q->where('group_id', auth()->user()->group_id);
                               })
                               ->with('excluded_user')
                               ->get();
```

---

### Pitfall 3: Race Condition (Two Users Exclude at Same Time)

**Scenario:**
- Alice and Bob both try to exclude Carol at the exact same time
- Both queries check `if ($existing)` and find none
- Both insert into database
- Result: duplicate PostVisibility records

**Solution:**
Use a **unique constraint** in the migration (Person 1 should have done this):

```php
Schema::create('post_visibility', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained('posts');
    $table->foreignId('excluded_user_id')->constrained('users');
    $table->timestamps();
    
    // This prevents duplicates
    $table->unique(['post_id', 'excluded_user_id']);
});
```

If Person 1 forgot this, add a migration:

```bash
php artisan make:migration add_unique_constraint_to_post_visibility
```

```php
Schema::table('post_visibility', function (Blueprint $table) {
    $table->unique(['post_id', 'excluded_user_id']);
});
```

---

### Pitfall 4: Showing Excluded Replies in Reply Count

**Problem:**
```blade
<h3>Replies ({{ count($topic->posts[0]->replies) }})</h3>
```

If Alice excluded Bob from 2 replies, Bob's counter still shows all 5 replies (not 3).

**Solution:**
Use the filtered collection:

```blade
@php
    $visibleReplies = $this->getVisibleReplies($topic->posts[0], auth()->user());
@endphp

<h3>Replies ({{ count($visibleReplies) }})</h3>
```

Or better, pass this from the controller:

```php
$visibleReplies = $this->getVisibleRepliesOptimized($post, auth()->user())->get();
return view('forum.show', compact('visibleReplies'));
```

---

### Debugging Tools

#### Check Visibility Rules in Tinker

```bash
php artisan tinker
```

```php
# See all exclusion rules
PostVisibility::all();

# See exclusion rules for post 42
PostVisibility::where('post_id', 42)->get();

# See if User 5 is excluded from Post 42
PostVisibility::isExcluded(42, 5);  // true or false

# See all users excluded from post 42
PostVisibility::getExcludedUsers(42);

# See all exclusions created by post author 10
PostVisibility::byPostAuthor(10)->get();

# Delete exclusion (testing only)
PostVisibility::where('post_id', 42)
              ->where('excluded_user_id', 5)
              ->delete();
```

#### Check Topic/Group Access

```bash
curl "http://localhost:8000/debug/forum/42/access" \
     -H "Authorization: Bearer YOUR_TOKEN"
```

Response:
```json
{
    "user_id": 1,
    "user_group_id": 1,
    "user_group_name": "BSSE Year 3",
    "topic_id": 42,
    "topic_group_id": 2,
    "topic_group_name": "BSSE Year 1",
    "can_access": false,
    "reason": "Different groups"
}
```

---

## 8. INTEGRATION WITH PERSON 2'S CODE

### Modifying Person 2's show() Method

Person 2b wrote a basic `show()` method. You need to enhance it to use visibility filtering:

**Person 2b's Version:**
```php
public function show(Topic $topic)
{
    if ($topic->group_id !== auth()->user()->group_id) {
        abort(403);
    }
    
    $topic->load(['posts' => function ($q) {
        $q->where('is_removed', false)
          ->with(['author', 'replies' => function ($q2) {
              $q2->where('is_removed', false)
                 ->with('author');
          }]);
    }]);
    
    return view('forum.show', compact('topic'));
}
```

**Person 4's Enhanced Version:**
```php
public function show(Topic $topic)
{
    // Layer 1: Group Isolation Check
    if ($topic->group_id !== auth()->user()->group_id) {
        abort(403);
    }
    
    $topic->load(['posts' => function ($q) {
        $q->where('is_removed', false)
          ->with(['author', 'replies' => function ($q2) {
              // Layer 2: Visibility Filter (optimized)
              $q2->where('is_removed', false)
                 ->whereDoesntHave('visibility_rules', function ($q3) {
                     $q3->where('excluded_user_id', auth()->id());
                 })
                 ->with('author');
          }]);
    }]);
    
    return view('forum.show', compact('topic'));
}
```

**Key Change:**
Added `whereDoesntHave('visibility_rules', ...)` to filter out replies the user is excluded from at the database level (more efficient).

### Modifying Person 2's show.blade.php View

Person 2b's template:
```blade
@forelse ($topic->posts[0]->replies as $reply)
    <div class="reply-card">
        <p><strong>{{ $reply->author->full_name }}</strong></p>
        <p>{{ $reply->reply_content }}</p>
    </div>
@empty
    <p>No replies yet.</p>
@endforelse
```

Person 4's enhanced template:
```blade
@forelse ($topic->posts[0]->replies as $reply)
    @include('forum.partials.reply-with-visibility', [
        'reply' => $reply,
        'post' => $topic->posts[0]
    ])
@empty
    <p>No replies yet.</p>
@endforelse
```

This partial handles visibility logic and shows the exclusion controls.

---

## FINAL CHECKLIST BEFORE PULL REQUEST

- [ ] PostVisibility model created with relationships and helpers
- [ ] Post model has `visibility_rules()` relationship
- [ ] All 8 controller methods added to ForumController
- [ ] All routes added to routes/web.php
- [ ] Three Blade views created in resources/views/forum/
- [ ] Person 2's show() method enhanced with visibility filtering
- [ ] Person 2's show.blade.php updated to use visibility-aware replies
- [ ] Manual tests 4.1.1 through 4.2.3 pass
- [ ] No errors in `php artisan tinker` with PostVisibility
- [ ] Unique constraint on post_visibility table exists
- [ ] Code review passed by another team member
- [ ] Branch merged into main

---

## KEY TAKEAWAYS FOR YOUR PRESENTATION

When asked to defend your work:

**"Why do we need visibility rules?"**
> "The SDD says members want to exclude specific people from certain communications. This might be for sensitive replies or privacy. Our implementation lets post authors choose which users can see their replies, and these rules are enforced everywhere—web views, PDF exports, and even in admin panels."

**"How do you prevent users from seeing excluded posts?"**
> "We use three layers: (1) Database-level filtering with `whereDoesntHave()` in queries, (2) Application-level checks in views, and (3) Helper methods like `PostVisibility::isExcluded()`. We never return a post to a user if they're excluded, even if they guess the ID."

**"What about group isolation—how do you enforce it?"**
> "Three layers again: (1) Every query has `where('group_id', auth()->user()->group_id)`, (2) Controllers verify group membership before rendering, and (3) Views only iterate over already-filtered collections. A user in Group A literally cannot see Group B's topics."

---

**CONGRATULATIONS!** You've now implemented the security backbone of the forum system. Person 5 will build on your work for PDF exports, and Persons 3 & 5 depend on your group isolation to function correctly.

