# Smart Discussion Forum — Chat Module & Offline Sync

> Complete documentation for the real-time chat system, including message delivery tracking and offline sync for desktop clients.

---

## Table of Contents

1. [What This Module Is](#1-what-this-module-is)
2. [Key Concepts Explained Simply](#2-key-concepts-explained-simply)
3. [Person 1: Database Foundation & Models](#3-person-1-database-foundation--models)
4. [Person 2: Conversation Management (Web + API)](#4-person-2-conversation-management-web--api)
5. [Person 3: Real-Time Messaging](#5-person-3-real-time-messaging)
6. [Person 4: Message Status & Notifications](#6-person-4-message-status--notifications)
7. [Person 5: Offline Sync & Testing](#7-person-5-offline-sync--testing)
8. [How Everything Connects — The Request Lifecycle](#8-how-everything-connects--the-request-lifecycle)
9. [The Complete Data Flow](#9-the-complete-data-flow)

---

## 1. What This Module Is

The Chat Module adds a real-time group messaging system to the Smart Discussion Forum. It is completely separate from the Forum module — forum posts are permanent, threaded discussions, while chat messages are ephemeral, linear conversations.

**What the module lets users do:**

- Start a **direct conversation** (1-to-1 chat) or a **group conversation** (3+ people)
- Send and receive messages in **real time** — no page refresh needed
- See when your message was **delivered** and when it was **read**
- Continue conversations on a **desktop app** that stays in sync even after being offline
- Know exactly how many **unread messages** you have in each conversation

### Why a Separate Module?

The forum module already lets users post topics and replies. Why build a separate chat system?

| Forum Posts | Chat Messages |
|---|---|
| Permanent — never deleted | Ephemeral — no edit/delete |
| Threaded (topic → replies) | Linear (flat list of messages) |
| Visible to everyone in a group | Only visible to conversation participants |
| No delivery tracking | Tracked: sent → delivered → read |
| No real-time updates | Instant delivery via WebSockets |
| Always-online | Works offline, syncs later |

They serve different purposes. Forum is for structured Q&A. Chat is for quick, informal conversation.

---

## 2. Key Concepts Explained Simply

Before diving into the code, you need to understand four technologies this module uses. I'll explain each one simply.

### 2.1 WebSockets (The "Real-Time" Magic)

**Normal web requests:** Your browser asks the server "any new messages?" The server says "no." Your browser asks again 2 seconds later. This is called **polling** — and it wastes a lot of bandwidth asking the same question over and over.

**WebSockets:** Your browser opens a single connection to the server and keeps it open. The server can push data to your browser at any time without being asked. When someone sends a chat message, the server immediately pushes it to everyone in the conversation. No polling, no delay.

Think of it like a phone call (WebSocket) vs. sending letters back and forth (normal HTTP). With letters, you write "any news?" and wait for a reply. With a phone call, the other person can speak whenever they want.

### 2.2 Reverb (Laravel's WebSocket Server)

Reverb is a WebSocket server that ships with Laravel. It sits alongside your main application and handles the real-time connections. Here is the architecture:

```
User A's browser                    User B's browser
      │                                   │
      │  WebSocket connection              │  WebSocket connection
      │  to Reverb                         │  to Reverb
      ▼                                   ▼
┌──────────────┐                   ┌──────────────┐
│   Reverb     │◄──────────────────│   Reverb     │
│   Server     │    broadcasts     │   Server     │
│   (port 8080)│    to all         │   (port 8080)│
└──────┬───────┘    participants   └──────┬───────┘
       │                                  │
       │  When a message is sent:         │
       │  1. Laravel saves it to DB       │
       │  2. Laravel tells Reverb:        │
       │     "broadcast this to channel   │
       │      conversation.42"            │
       │  3. Reverb pushes it to          │
       │     everyone connected to        │
       │     that channel                 │
       ▼                                  ▼
┌──────────────┐                   ┌──────────────┐
│   Laravel    │                   │   Laravel    │
│   HTTP App   │                   │   HTTP App   │
│   (port 8000)│                   │   (port 8000)│
└──────────────┘                   └──────────────┘
```

**Why Reverb instead of Pusher/Ably?** Those are paid third-party services. Reverb is free, built into Laravel, and runs on your own server. The desktop client connects to it directly using the standard WebSocket protocol.

### 2.3 Queue Worker (`queue:work`)

When a user sends a message, Laravel can do two things:

1. Save the message to the database
2. Broadcast it to other users via Reverb

Step 2 can be handled in two ways:

- **Synchronously** (ShouldBroadcastNow) — the user's browser waits for the broadcast to finish before getting a response. Adds ~5-15ms per message.
- **Via a queue** (ShouldBroadcast) — the broadcast is placed in a queue (a database table). A separate process (`queue:work`) picks it up a fraction of a second later and sends it.

The chat module uses **ShouldBroadcastNow** (synchronous) for messages because the delay is tiny and users expect instant delivery. Notifications and emails would use the queue because they can tolerate a delay.

**The `queue:work` command** is a background process that runs continuously:
```bash
php artisan queue:work --tries=3
```
It checks the `jobs` database table every second. When a new job appears (like "send email" or "send push notification"), it processes it. If the job fails, it retries up to 3 times.

### 2.4 Offline Sync (For the Desktop Client)

The desktop app (JavaFX/Swing) cannot run Laravel Echo (JavaScript-only) and cannot keep a WebSocket connection open 24/7. Instead, it uses a **sync API**:

1. **Pull** — When the desktop app comes online, it asks the server: "Give me everything that changed since my last sync." The server returns new messages, status updates, and conversation changes.
2. **Push** — Messages the user typed while offline are uploaded in a batch. The server saves them and broadcasts them to other online participants.

Each device (desktop app install) has a **sync checkpoint** — a timestamp stored in the database that tracks "the last time this device successfully synced." Only data newer than that timestamp is returned.

---

## 3. Person 1: Database Foundation & Models

Person 1 built the foundation that everyone else depends on: 5 database tables and 5 Eloquent models.

### 3.1 The Five Migrations

#### Migration 1: `conversations` table

```php
Schema::create('conversations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('group_id')->constrained()->onDelete('cascade');
    $table->enum('type', ['direct', 'group']);
    $table->string('name')->nullable();
    $table->timestamp('last_activity_at')->nullable();
    $table->timestamps();
});
```

**What each column does:**

| Column | Purpose |
|---|---|
| `id` | Unique identifier for this conversation |
| `group_id` | Every conversation belongs to a group (group isolation) |
| `type` | `direct` = 1-to-1 chat, `group` = 3+ people |
| `name` | The conversation name (null for direct chats, required for groups) |
| `last_activity_at` | When the last message was sent. Used to sort the conversation list |

**Why `group_id` is non-nullable:** This is the **group isolation** principle. A conversation can never exist without a group — enforced at the database level, not just in code. If a rogue controller forgets to add `where('group_id', ...)`, the foreign key constraint still prevents cross-group data.

**Why `last_activity_at` is denormalized (stored directly on the row):** Without it, sorting the conversation list by "most recent message" would require a correlated subquery: `Conversation::withMax('messages', 'created_at')`. This is slow for users with hundreds of conversations. By updating `last_activity_at` every time a message is sent, the sort becomes a simple `ORDER BY last_activity_at DESC`.

#### Migration 2: `conversation_participants` table

```php
Schema::create('conversation_participants', function (Blueprint $table) {
    $table->id();
    $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('role')->default('participant');
    $table->timestamp('joined_at')->useCurrent();
    $table->unique(['conversation_id', 'user_id']);
    $table->timestamps();
});
```

This is a **pivot table** — it links users to conversations. A user can be in many conversations, and a conversation can have many users.

**Why the `unique` constraint?** Without it, the "add participant" endpoint could be called twice for the same user, creating duplicate rows. That would break the unread count query (it would count the same user twice).

**Why `role` exists:** In group conversations, the creator is an `admin` and can add/remove participants. Regular members are `participant`. Direct conversations don't use roles.

#### Migration 3: `messages` table

```php
Schema::create('messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
    $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
    $table->text('body');
    $table->timestamps();
    $table->index(['conversation_id', 'created_at']);
});
```

**Why no `edited_at` or `deleted_at` columns:** Editing and deleting messages is explicitly out of scope for this build. If needed later, a migration can add them.

**Why the composite index:** The most common query is "get messages for conversation X, ordered by created_at." The index on `(conversation_id, created_at)` makes this query fast — without it, the database would scan every row.

#### Migration 4: `message_status` table

```php
Schema::create('message_status', function (Blueprint $table) {
    $table->id();
    $table->foreignId('message_id')->constrained()->onDelete('cascade');
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->enum('status', ['sent', 'delivered', 'read'])->default('sent');
    $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();
    $table->unique(['message_id', 'user_id']);
});
```

**Why no `created_at` column:** We only care about when the status last changed — not when the row was created. The row is created the moment the message is sent, so `created_at` would equal the initial `updated_at` anyway.

**The three statuses:**

```
sent ──────► delivered ──────► read
```

- **sent** — The message has been saved to the database. The initial status for every recipient.
- **delivered** — The recipient's client confirmed receiving the message (via WebSocket receipt or API fetch).
- **read** — The recipient opened the conversation and saw the message.

The `unique(['message_id', 'user_id'])` constraint ensures one status row per recipient per message.

#### Migration 5: `sync_checkpoints` table

```php
Schema::create('sync_checkpoints', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('device_id');
    $table->timestamp('last_synced_at')->useCurrent();
    $table->unique(['user_id', 'device_id']);
    $table->timestamps();
});
```

**What it does:** Each device (e.g., "Desktop-Windows-ABC123", "Laptop-Mac-DEF456") gets its own checkpoint — a timestamp of when it last successfully synced. When the device calls the sync API, only records newer than that timestamp are returned.

**Why `unique(['user_id', 'device_id'])`:** One checkpoint per device per user. If a user has a desktop app and a laptop app, each tracks its own sync position independently.

### 3.2 The Five Models

#### Conversation — `app/Models/Conversation.php`

```php
class Conversation extends Model
{
    protected $fillable = ['group_id', 'type', 'name', 'last_activity_at'];

    public function group()        { return $this->belongsTo(Group::class); }
    public function participants() { return $this->belongsToMany(User::class, 'conversation_participants')->withPivot('role', 'joined_at'); }
    public function messages()     { return $this->hasMany(Message::class); }
    public function lastMessage()  { return $this->hasOne(Message::class)->latest('id'); }

    // Everyone uses this scope instead of writing their own group_id check
    public function scopeForUserInGroup($query, User $user)
    {
        return $query->where('group_id', $user->group_id)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id));
    }
}
```

**`lastMessage()`** uses `hasOne` with `latest('id')` — this is an Eloquent trick that returns the most recent message for each conversation without loading all messages into memory. It generates a subquery: `(SELECT * FROM messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 1)`.

**`scopeForUserInGroup()`** is the single most important method in the entire module. It combines two filters:
1. `where('group_id', $user->group_id)` — group isolation
2. `whereHas('participants', ...)` — user must be a participant

Every controller uses this scope. There is no code path that bypasses it.

#### Message — `app/Models/Message.php`

```php
class Message extends Model
{
    protected $fillable = ['conversation_id', 'sender_id', 'body'];

    public function conversation() { return $this->belongsTo(Conversation::class); }
    public function sender()       { return $this->belongsTo(User::class, 'sender_id'); }
    public function statusRows()   { return $this->hasMany(MessageStatus::class); }

    protected static function booted(): void
    {
        static::created(function (Message $message) {
            // Auto-create status rows for all participants except the sender
            app(MessageStatusService::class)->createInitialStatusRows($message);
        });
    }
}
```

**The `booted()` hook** fires automatically after every message is created. It calls `MessageStatusService::createInitialStatusRows()` which creates one `message_status` row per participant (except the sender) with status = 'sent'. This is the handoff between Person 1 (who wrote the hook) and Person 4 (who wrote the service method).

#### MessageStatus — `app/Models/MessageStatus.php`

```php
class MessageStatus extends Model
{
    public const CREATED_AT = null;  // This table has no created_at column
    protected $table = 'message_status';
    protected $fillable = ['message_id', 'user_id', 'status'];
    protected $casts = ['updated_at' => 'datetime'];

    public function message() { return $this->belongsTo(Message::class); }
    public function user()    { return $this->belongsTo(User::class); }
}
```

**`CREATED_AT = null`** tells Eloquent: "This model does not have a `created_at` column." Laravel normally expects both `created_at` and `updated_at`. By setting `CREATED_AT` to null, Laravel stops trying to write to it.

#### SyncCheckpoint — `app/Models/SyncCheckpoint.php`

```php
class SyncCheckpoint extends Model
{
    protected $fillable = ['user_id', 'device_id', 'last_synced_at'];
    protected $casts = ['last_synced_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
}
```

A simple model. The `last_synced_at` cast to `datetime` means it's automatically converted to a Carbon instance, so you can call `$checkpoint->last_synced_at->diffForHumans()` or compare it to other dates.

#### User Model (Modified)

Two new relationships were added to the existing `User` model:

```php
public function conversations()
{
    return $this->belongsToMany(Conversation::class, 'conversation_participants')
        ->withPivot('role', 'joined_at');
}

public function chatMessages()
{
    return $this->hasMany(Message::class, 'sender_id');
}
```

**Why `conversations` uses `belongsToMany`:** A user can be in many conversations, and a conversation has many users. The `conversation_participants` pivot table links them. `withPivot('role', 'joined_at')` lets you access `$user->conversations->first()->pivot->role` to check if the user is an admin of that conversation.

---

## 4. Person 2: Conversation Management (Web + API)

Person 2 built the "who talks to whom" layer — the ability to start, view, and manage conversations. No messages yet (that's Person 3's job).

### 4.1 ConversationController — `app/Http/Controllers/ConversationController.php`

This controller handles everything related to managing conversations. It responds to both web requests (Blade views) and API requests (JSON), detected by `$request->is('api/*')`.

#### index() — List conversations

```php
public function index(Request $request): View|JsonResponse
{
    $conversations = Conversation::forUserInGroup(auth()->user())
        ->with(['participants:id,full_name', 'lastMessage:id,conversation_id,body,created_at'])
        ->orderByDesc('last_activity_at')
        ->paginate(20);

    if ($request->is('api/*')) {
        return response()->json(['data' => $conversations], 200);
    }
    return view('conversations.index', compact('conversations'));
}
```

**What it does:** Shows the user's conversation list — all conversations they participate in, sorted by most recent activity. Each conversation shows the participants' names and a preview of the last message.

**Why `forUserInGroup`:** This enforces both group isolation (only conversations in the user's group) AND participant restriction (only conversations the user is in). A user from Group A cannot see conversations from Group B, even if they guess the URL.

**Why eager-load `lastMessage`:** Without `->with('lastMessage')`, accessing `$conversation->lastMessage` in the Blade view would trigger a separate SQL query for every conversation in the list (the N+1 problem). With eager loading, it's one subquery for all conversations.

#### create() — Show the "new conversation" form

```php
public function create(Request $request): View
{
    $users = User::where('id', '!=', auth()->id())
        ->where('group_id', auth()->user()->group_id)
        ->whereNull('blacklisted_at')
        ->orderBy('full_name')
        ->get(['id', 'full_name']);

    return view('conversations.create', compact('users'));
}
```

**What it does:** Loads all users in the same group (excluding the current user and blacklisted users) so they can be selected as conversation participants. This is the web-only form view.

#### store() — Start a new conversation

This is the most complex method. It handles three scenarios:

**1. Cross-group check:**
```php
foreach ($validated['participant_ids'] as $userId) {
    $otherUser = User::findOrFail($userId);
    if ($otherUser->group_id !== $currentUserGroupId) {
        return response()->json([...], 422); // Or back()->withErrors(...)
    }
}
```
Every participant must be in the same group. Cross-group conversations are rejected with a clear error message.

**2. Duplicate direct conversation reuse:**
```php
if ($validated['type'] === 'direct') {
    $existing = Conversation::where('type', 'direct')
        ->where('group_id', $currentUserGroupId)
        ->whereHas('participants', fn ($q) => $q->where('user_id', auth()->id()))
        ->whereHas('participants', fn ($q) => $q->where('user_id', $otherUserId))
        ->whereDoesntHave('participants', fn ($q) => $q->whereNotIn('user_id', [auth()->id(), $otherUserId]))
        ->first();
    if ($existing) {
        return response()->json(['data' => $existing], 200); // Return existing
    }
}
```
If Alice and Bob already have a direct conversation, starting another one just returns the existing one. This prevents duplicate conversations between the same two people.

**3. Admin role assignment:**
```php
$creatorRole = $validated['type'] === 'group' ? 'admin' : 'participant';
$conversation->participants()->attach(auth()->id(), [
    'role' => $creatorRole,
    'joined_at' => now(),
]);
```
The conversation creator gets `role = 'admin'` for group conversations, giving them permission to add/remove participants later.

#### addParticipant() / removeParticipant() — Manage group members

Both methods share the same authorization pattern:

```php
// Only group conversations allow participant management
if ($conversation->type !== 'group') {
    abort(422, 'Cannot add/remove participants in a direct conversation.');
}

// Only admins can manage participants
$currentUser = $conversation->participants()->where('user_id', auth()->id())->first();
if (! $currentUser || $currentUser->pivot->role !== 'admin') {
    abort(403, 'Only conversation admins can manage participants.');
}

// Cross-group check (for add)
if ($newUser->group_id !== $conversation->group_id) {
    abort(422, 'Cannot add a user from a different group.');
}
```

**Why three separate checks:** Each enforces a different rule:
1. Type check — prevents adding people to a 1-to-1 chat (that would make it a group, which changes semantics)
2. Admin check — prevents unauthorized users from adding/removing people
3. Cross-group check — maintains group isolation

### 4.2 Routes

**Web routes** (inside the `auth` middleware group):

```php
Route::prefix('conversations')->name('conversations.')->group(function () {
    Route::get('/',             [ConversationController::class, 'index'])              ->name('index');
    Route::get('/create',       [ConversationController::class, 'create'])             ->name('create');
    Route::get('/{id}',         [ConversationController::class, 'show'])               ->name('show');
    Route::post('/',            [ConversationController::class, 'store'])              ->name('store');
    Route::post('/{id}/participants',     [ConversationController::class, 'addParticipant'])    ->name('participants.add');
    Route::delete('/{id}/participants/{userId}', [ConversationController::class, 'removeParticipant'])->name('participants.remove');
});
```

**API routes** (inside the `auth:sanctum` + API version prefix group):

```php
Route::get('/conversations',                                [ConversationController::class, 'index']);
Route::get('/conversations/{id}',                           [ConversationController::class, 'show']);
Route::post('/conversations',                               [ConversationController::class, 'store']);
Route::post('/conversations/{id}/participants',             [ConversationController::class, 'addParticipant']);
Route::delete('/conversations/{id}/participants/{userId}',  [ConversationController::class, 'removeParticipant']);
```

The API routes don't have named routes (the web ones do) because the desktop client uses URL paths directly, not route names.

### 4.3 Blade Views

Three views exist in `resources/views/conversations/`:

- **`index.blade.php`** — The conversation list page. Shows all conversations the user participates in, sorted by `last_activity_at`. Each entry shows the conversation name, participant avatars, and the last message preview.

- **`create.blade.php`** — A form to create a new conversation. Lets the user select a type (direct/group), pick participants from a list of group members, and optionally name the conversation (required for group chats).

- **`show.blade.php`** — The conversation detail page. Shows the conversation's metadata (name, participants, type) but NOT the messages — that's Person 3's job. The messages will be loaded dynamically on this page once Person 3 builds the message API and WebSocket integration.

---

## 5. Person 3: Real-Time Messaging

Person 3 builds the core feature: sending and receiving messages in real time. This is where WebSockets and Reverb come in.

### 5.1 MessageController — `app/Http/Controllers/MessageController.php`

#### index() — Fetch messages (paginated, oldest first)

```php
public function index(Request $request, int $conversationId)
{
    // Verify the user is a participant
    $conversation = Conversation::forUserInGroup(Auth::user())
        ->whereHas('participants', fn ($q) => $q->where('user_id', Auth::id()))
        ->findOrFail($conversationId);

    // Fetch messages in reverse chronological order, paginated
    $messages = $conversation->messages()
        ->with('sender:id,full_name')
        ->orderByDesc('created_at')
        ->paginate(50);

    if ($request->is('api/*')) {
        return response()->json(['data' => $messages], 200);
    }
    return view('conversations.messages', compact('conversation', 'messages'));
}
```

**Why `orderByDesc('created_at')` with pagination?** The UI shows the most recent page first (last 50 messages). When the user scrolls up, it loads the next 50 older messages. This is the same pattern used by WhatsApp, Slack, and Messenger.

**Why verify participant status again?** Even though Person 2's `show()` already checks this, the message `index()` is a separate code path. A user might be removed from a conversation after viewing it but before fetching messages. The check is repeated for defense in depth.

#### store() — Send a message

```php
public function store(Request $request, int $conversationId)
{
    $validated = $request->validate([
        'body' => 'required|string|max:10000',
    ]);

    // Verify participant status
    $conversation = Conversation::forUserInGroup(Auth::user())
        ->whereHas('participants', fn ($q) => $q->where('user_id', Auth::id()))
        ->findOrFail($conversationId);

    // Create the message
    $message = $conversation->messages()->create([
        'sender_id' => Auth::id(),
        'body' => $validated['body'],
    ]);

    // Update conversation's last_activity_at
    $conversation->update(['last_activity_at' => now()]);

    // Broadcast to other participants in real time
    broadcast(new MessageSent($message))->toOthers();

    // Return response
    if ($request->is('api/*')) {
        return response()->json(['data' => $message->load('sender:id,full_name')], 201);
    }
    return redirect()->back()->with('success', 'Message sent.');
}
```

**The flow when a message is sent:**

```
1. Validate input (body required, max 10,000 chars)
2. Check user is a participant
3. Save message to database
       │
       ▼
4. Message::booted() fires (Person 1's hook)
       │
       ▼
5. MessageStatusService::createInitialStatusRows()
   Creates 'sent' status rows for all recipients
       │
       ▼
6. $conversation->update(['last_activity_at' => now()])
   Updates the sort order for the conversation list
       │
       ▼
7. broadcast(new MessageSent($message))->toOthers()
   Sends the message to all connected participants via Reverb
       │
       ▼
8. Return JSON response (or redirect)
```

**`broadcast(...)->toOthers()`** means the sender does NOT receive their own message via WebSocket — they already see it in the UI because they typed it. Only other participants get the broadcast.

### 5.2 MessageSent Event — `app/Events/MessageSent.php`

```php
class MessageSent implements ShouldBroadcastNow
{
    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message->load('sender:id,full_name');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("conversation.{$this->message->conversation_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender->full_name,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
```

**`PrivateChannel("conversation.{id}")`** — This is a private channel. Only authenticated users who are authorized to listen can subscribe. The authorization is handled by `routes/channels.php`:

```php
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    return Conversation::where('id', $conversationId)
        ->where('group_id', $user->group_id)
        ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
        ->exists();
});
```

**Why the channel authorization check exists even though the controller already checks:** The controller check runs over HTTP. The channel authorization runs over WebSocket. A malicious user could connect directly to the WebSocket server and try to subscribe to a channel without going through the HTTP API. This closure is the only gate between the WebSocket connection and the channel data. It must independently verify:
1. The user belongs to the same group as the conversation (`group_id` check)
2. The user is a participant of the conversation (`whereHas('participants')` check)

**`ShouldBroadcastNow` vs `ShouldBroadcast`:** `ShouldBroadcastNow` broadcasts synchronously — the web request waits for the broadcast to complete. `ShouldBroadcast` dispatches a queued job. Chat messages use `ShouldBroadcastNow` because the delay is negligible (~5-15ms) and users expect instant delivery.

### 5.3 Client Side — Web (Laravel Echo)

In the web Blade view (`conversations/show.blade.php`), the JavaScript listens for new messages:

```javascript
import Echo from 'laravel-echo';
window.Pusher = require('pusher-js');

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    disableStats: true,
    authorizer: (channel) => {
        return {
            authorize: (socketId, callback) => {
                axios.post('/api/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channel.name,
                })
                .then(response => callback(false, response.data))
                .catch(error => callback(true, error));
            }
        };
    },
});

// Listen for new messages on this conversation's private channel
Echo.private(`conversation.${conversationId}`)
    .listen('MessageSent', (e) => {
        appendMessage(e);
    });
```

**Echo** is a JavaScript library that makes WebSocket connections easy. It handles subscribing to channels, sending authentication requests, and parsing events. It speaks the Pusher Wire Protocol, which Reverb also speaks — so Echo and Reverb are compatible.

**The `authorizer` callback** sends a POST request to `/api/broadcasting/auth` with the socket ID and channel name. Laravel's `Broadcast::channel()` callback in `routes/channels.php` handles this request and returns `true` or `false`. If `true`, the client is subscribed to the channel and will receive real-time events.

### 5.4 Desktop Client — No Echo

The desktop app (JavaFX/Swing) cannot run JavaScript. Instead, it connects to Reverb directly using the standard Pusher WebSocket protocol:

1. Authenticate via Sanctum token (same as the REST API)
2. Subscribe to the private channel by calling Reverb's Pusher subscription endpoint with the token
3. Handle JSON frames matching the Pusher protocol format

This is documented in Person 5's `docs/sync-api.md`.

---

## 6. Person 4: Message Status & Notifications

Person 4 builds the sent → delivered → read tracking system and unread counts.

### 6.1 MessageStatusService — `app/Services/MessageStatusService.php`

This is the single source of truth for status transitions. No controller updates `message_status` rows directly — they all call this service.

#### createInitialStatusRows() — Called automatically when a message is created

```php
public function createInitialStatusRows(Message $message): void
{
    // Get all participants EXCEPT the sender
    $participantIds = $message->conversation->participants()
        ->where('user_id', '!=', $message->sender_id)
        ->pluck('user_id');

    if ($participantIds->isEmpty()) {
        return; // No recipients (e.g., self-chat)
    }

    // Create status rows in a single INSERT
    $rows = $participantIds->map(fn (int $userId) => [
        'message_id' => $message->id,
        'user_id' => $userId,
        'status' => 'sent',
    ]);

    MessageStatus::insert($rows->toArray());
}
```

**Why `insert()` instead of creating models one-by-one:** For a group conversation with 50 participants, creating 50 Eloquent models would generate 50 separate INSERT queries. A single `insert()` generates 1. This method is called for EVERY message, so performance matters.

**Why exclude the sender:** The sender already knows they sent the message. They don't need a status row for themselves.

#### markAsDelivered() — Transition from 'sent' to 'delivered'

```php
public function markAsDelivered(int $messageId, int $userId): void
{
    MessageStatus::where('message_id', $messageId)
        ->where('user_id', $userId)
        ->where('status', 'sent')    // Only move forward — don't downgrade
        ->update(['status' => 'delivered', 'updated_at' => now()]);
}
```

**The `where('status', 'sent')` guard:** If the message is already 'read', we don't want to move it backward to 'delivered'. This ensures one-directional progress: sent → delivered → read.

#### markConversationAsRead() — Mark all messages as read in one batch

```php
public function markConversationAsRead(int $conversationId, int $userId): int
{
    $updated = MessageStatus::whereIn('message_id', function ($q) use ($conversationId) {
            $q->select('id')->from('messages')
                ->where('conversation_id', $conversationId);
        })
        ->where('user_id', $userId)
        ->whereIn('status', ['sent', 'delivered'])
        ->update(['status' => 'read', 'updated_at' => now()]);

    // Broadcast read receipts so the sender sees "Read" in real time
    if ($updated > 0) {
        broadcast(new MessagesRead($conversationId, $userId))->toOthers();
    }

    return $updated;
}
```

**Why one batch query instead of one per message:** When a user opens a conversation with 50 unread messages, we don't want to run 50 separate UPDATE queries. A single subquery + UPDATE handles all of them at once.

**Why broadcast `MessagesRead`:** Without this, the sender would only see "Read" when they refresh the page. With the broadcast, the read receipt appears in real time.

#### getUnreadCounts() — Return unread counts

```php
public function getUnreadCounts(int $userId): array
{
    $perConversation = MessageStatus::whereIn('message_id', function ($q) {
            $q->select('id')->from('messages');
        })
        ->where('user_id', $userId)
        ->whereIn('status', ['sent', 'delivered'])
        ->join('messages', 'message_status.message_id', '=', 'messages.id')
        ->groupBy('messages.conversation_id')
        ->selectRaw('messages.conversation_id, count(*) as unread_count')
        ->pluck('unread_count', 'conversation_id');

    return [
        'total' => $perConversation->sum(),
        'per_conversation' => $perConversation,
    ];
}
```

**What the SQL looks like:**
```sql
SELECT messages.conversation_id, COUNT(*) as unread_count
FROM message_status
JOIN messages ON message_status.message_id = messages.id
WHERE message_status.user_id = 5
  AND message_status.status IN ('sent', 'delivered')
GROUP BY messages.conversation_id
```

**How the UI uses this:**
- The **navbar badge** shows `total` — all unread messages across all conversations
- The **conversation list** shows `per_conversation` — how many unread messages in each conversation

### 6.2 MessagesRead Event — `app/Events/MessagesRead.php`

```php
class MessagesRead implements ShouldBroadcastNow
{
    public function __construct(
        public int $conversationId,
        public int $readByUserId
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("conversation.{$this->conversationId}")];
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'read_by_user_id' => $this->readByUserId,
        ];
    }
}
```

**Why a separate event from `MessageSent`:** They carry different data. `MessageSent` carries the message content. `MessagesRead` just says "User X read the conversation" — the recipient's UI uses this to update the "Read" indicator next to their messages.

### 6.3 Status API Endpoints

| Method | Endpoint | Controller | What It Does |
|---|---|---|---|
| POST | `/api/v1/messages/{id}/markDelivered` | MessageController | Marks one message as delivered for the current user |
| POST | `/api/v1/conversations/{id}/read` | MessageController | Marks ALL messages in the conversation as read |
| GET | `/api/v1/me/unread-counts` | MessageController | Returns total and per-conversation unread counts |

---

## 7. Person 5: Offline Sync & Testing

Person 5 builds the sync API that allows the desktop client to stay in sync without a permanent WebSocket connection.

### 7.1 SyncController — `app/Http/Controllers/SyncController.php`

#### pull() — Get everything that changed since last sync

```php
public function pull(Request $request)
{
    $validated = $request->validate([
        'device_id' => 'required|string|max:255',
    ]);

    $user = Auth::user();

    // Get or create the checkpoint for this device
    $checkpoint = SyncCheckpoint::firstOrCreate(
        ['user_id' => $user->id, 'device_id' => $validated['device_id']],
        ['last_synced_at' => now()->subYear()] // First sync: get everything from last year
    );

    $since = $checkpoint->last_synced_at;

    // Find all conversations the user has access to
    $conversationIds = Conversation::forUserInGroup($user)
        ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
        ->pluck('id');

    // Get new messages since checkpoint
    $newMessages = Message::whereIn('conversation_id', $conversationIds)
        ->where('created_at', '>', $since)
        ->with('sender:id,full_name')
        ->get();

    // Get status updates since checkpoint
    $statusUpdates = MessageStatus::whereIn('message_id', function ($q) use ($conversationIds) {
            $q->select('id')->from('messages')->whereIn('conversation_id', $conversationIds);
        })
        ->where('user_id', $user->id)
        ->where('updated_at', '>', $since)
        ->get();

    // Update the checkpoint AFTER building the response
    $checkpoint->update(['last_synced_at' => now()]);

    return response()->json([
        'success' => true,
        'data' => [
            'conversations' => Conversation::whereIn('id', $conversationIds)
                ->where('updated_at', '>', $since)
                ->with('participants:id,full_name')
                ->get(),
            'messages' => $newMessages,
            'status_updates' => $statusUpdates,
            'synced_at' => now()->toIso8601String(),
        ],
    ]);
}
```

**Why the checkpoint update is AFTER the response is built (not after it's sent):**

```
Scenario A: Update checkpoint BEFORE building response
  1. Checkpoint advances to 10:00 AM
  2. Server crashes while building response
  3. Client doesn't get the data, but checkpoint says 10:00 AM
  4. Next sync: client misses everything from 9:59 AM to 10:00 AM
  → DATA LOSS

Scenario B: Update checkpoint AFTER building response (what we do)
  1. Build response with messages from 9:00 AM to 10:00 AM
  2. Checkpoint advances to 10:00 AM
  3. Even if step 2 fails, client still has the data from this sync
  4. Next sync: client gets everything from 9:00 AM again (idempotent — same messages, no harm)
  → NO DATA LOSS
```

**The `now()->subYear()` default for first-time sync:** When a device syncs for the very first time, there's no checkpoint. By defaulting to "1 year ago," the first sync returns all messages from the past year. This is reasonable — conversations older than a year are probably not relevant.

#### push() — Upload offline messages

```php
public function push(Request $request)
{
    $validated = $request->validate([
        'messages' => 'required|array',
        'messages.*.client_id' => 'required|string',
        'messages.*.conversation_id' => 'required|integer',
        'messages.*.body' => 'required|string|max:10000',
    ]);

    $user = Auth::user();
    $results = [];

    foreach ($validated['messages'] as $msg) {
        // Verify participant status (same check as Person 3's store)
        $conversation = Conversation::forUserInGroup($user)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->find($msg['conversation_id']);

        if (! $conversation) {
            $results[] = [
                'client_id' => $msg['client_id'],
                'success' => false,
                'error' => 'Conversation not found or not accessible.',
            ];
            continue; // Move to next message — don't fail the whole batch
        }

        // Deduplicate by checking for identical recent messages
        $existing = Message::where('conversation_id', $conversation->id)
            ->where('sender_id', $user->id)
            ->where('body', $msg['body'])
            ->where('created_at', '>', now()->subMinutes(5))
            ->first();

        if ($existing) {
            $results[] = [
                'client_id' => $msg['client_id'],
                'success' => true,
                'message_id' => $existing->id,
            ];
            continue;
        }

        // Save the message (same path as Person 3)
        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $msg['body'],
        ]);
        $conversation->update(['last_activity_at' => now()]);
        broadcast(new MessageSent($message))->toOthers();

        $results[] = [
            'client_id' => $msg['client_id'],
            'success' => true,
            'message_id' => $message->id,
        ];
    }

    return response()->json([
        'success' => true,
        'data' => ['results' => $results],
    ]);
}
```

**Why `client_id` exists:** Each message the desktop app creates while offline gets a UUID (universally unique identifier) assigned by the client. When the batch is uploaded and the server responds, the client matches its local messages to the server responses using `client_id`. If the response never arrives (network timeout), the client retries the same batch — and the server deduplicates by checking for an identical message in the last 5 minutes.

**Why per-message error reporting instead of failing the whole batch:** If 5 out of 10 offline messages fail (e.g., the user was removed from a conversation), the other 5 should still be saved. The client uses the per-message `success` field to show the user which messages failed.

### 7.2 API Documentation — `docs/sync-api.md`

A documentation file for the desktop client developer, documenting:

- **Base URL:** `https://studdit.test/api/v1`
- **Authentication:** `Authorization: Bearer <sanctum-token>` header
- **`GET /sync/pull?device_id={deviceId}`** — Delta sync endpoint, returns conversations, messages, and status updates since the last checkpoint
- **`POST /sync/push`** — Upload offline messages, returns per-message success/failure
- **WebSocket connection** — How to connect to Reverb directly (since the desktop client can't use Laravel Echo)

### 7.3 Testing — `tests/Feature/Chat/SyncTest.php` and `tests/Feature/Chat/MessageTest.php`

**`SyncTest.php`** — Person 5's test suite for the offline sync endpoints. 22 test methods covering:

| Category | Test | What It Verifies |
|---|---|---|
| **Pull** | `test_pull_requires_authentication()` | Unauthenticated requests get 401 |
| | `test_pull_requires_device_id()` | Missing `device_id` gets 422 validation error |
| | `test_pull_returns_no_data_for_first_sync_when_no_activity()` | First sync with no data returns empty arrays |
| | `test_pull_returns_messages_after_checkpoint()` | Messages created after the checkpoint are returned |
| | `test_pull_creates_checkpoint_on_first_sync()` | First sync creates a row in `sync_checkpoints` |
| | `test_pull_updates_checkpoint_after_successful_sync()` | Subsequent syncs advance the timestamp |
| | `test_pull_returns_only_new_data()` | Old messages (before checkpoint) are NOT returned |
| | `test_pull_returns_status_updates()` | Status changes (sent→delivered→read) are included |
| | `test_pull_respects_group_isolation()` | A user from a different group sees nothing |
| | `test_different_devices_have_independent_checkpoints()` | Two devices track their own sync positions |
| **Push** | `test_push_requires_authentication()` | Unauthenticated requests get 401 |
| | `test_push_requires_messages_array()` | Missing `messages` field gets 422 |
| | `test_push_saves_message_and_returns_message_id()` | A valid message is saved and returns an ID |
| | `test_push_rejects_non_participant()` | Non-participant gets `success: false` |
| | `test_push_deduplicates_identical_messages()` | Same message sent twice = only one saved |
| | `test_push_batch_partial_failure()` | Mixed batch: some succeed, some fail independently |
| | `test_push_updates_last_activity_at()` | Conversation's `last_activity_at` advances |
| | `test_push_validates_body_length()` | Messages over 10,000 chars are rejected |
| | `test_push_max_100_messages()` | Batches over 100 messages are rejected |
| **Auth** | `test_sync_requires_authenticated_user()` | Both endpoints return 401 without a token |

**`MessageTest.php`** — Person 3's messaging tests (already existed, covers send/receive).

---

## 8. How Everything Connects — The Request Lifecycle

This section traces the complete journey of a request through the chat module, from the moment it hits the server to the response sent back. Understanding this flow is key to understanding how all the pieces fit together.

### 8.1 The Overall Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        Incoming Request                          │
│  (Browser visit /api call / WebSocket connection)                │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                     Laravel Application                          │
│                                                                  │
│  ┌──────────┐    ┌──────────────┐    ┌──────────────────────┐   │
│  │  Routes   │───►│  Middleware   │───►│    Controller        │   │
│  │  (web/,  │    │  (auth,      │    │  (Receives Request,  │   │
│  │  api/)   │    │   admin,     │    │   returns Response)   │   │
│  └──────────┘    │   sanctum)   │    └──────────┬───────────┘   │
│                  └──────────────┘               │                │
│                                                  ▼                │
│                                       ┌──────────────────────┐   │
│                                       │    Services Layer     │   │
│                                       │  Business logic that │   │
│                                       │  doesn't belong in   │   │
│                                       │  controllers         │   │
│                                       └──────────┬───────────┘   │
│                                                  ▼                │
│                                       ┌──────────────────────┐   │
│                                       │    Eloquent Models    │   │
│                                       │  (Conversation,      │   │
│                                       │   Message, etc.)     │   │
│                                       └──────────┬───────────┘   │
│                                                  ▼                │
│                                       ┌──────────────────────┐   │
│                                       │    Database Tables    │   │
│                                       │  (MySQL queries)      │   │
│                                       └──────────────────────┘   │
│                                                                  │
│  After response is built, events may be broadcast:               │
│  Controller ──► Event ──► Reverb ──► Other users' browsers       │
└─────────────────────────────────────────────────────────────────┘
```

### 8.2 Step 1: The Route File Determines Which Code Runs

When a request arrives at the server (e.g., `GET /api/v1/conversations`), Laravel needs to figure out: "Which controller method should handle this?"

**The route file is a lookup table.** It maps HTTP methods + URL patterns to controller methods:

```php
// routes/api.php — inside the auth:sanctum middleware group

Route::get('/conversations', [ConversationController::class, 'index']);
```

**This line means:**
```
When someone sends:  GET /api/v1/conversations
Run this middleware:  auth:sanctum (check for valid API token)
Call this method:     ConversationController::index()
```

The URL `/api/v1/conversations` is actually constructed from nested prefixes:

```php
// routes/api.php
Route::prefix('api')->group(function () {
    Route::prefix('v1')->group(function () {
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/conversations', [ConversationController::class, 'index']);
        });
    });
});
```

Each `Route::prefix()` or `Route::middleware()` wraps everything inside it. So the full URL path is `api + v1 + /conversations = /api/v1/conversations`, and the `auth:sanctum` middleware applies to all routes inside the group.

**Route parameters** are marked with `{curly_braces}`:

```php
Route::get('/conversations/{id}', [ConversationController::class, 'show']);
Route::post('/conversations/{id}/participants', [ConversationController::class, 'addParticipant']);
Route::delete('/conversations/{id}/participants/{userId}', [ConversationController::class, 'removeParticipant']);
```

When a request comes in for `GET /api/v1/conversations/42`, Laravel:
1. Matches the pattern `/conversations/{id}`
2. Extracts `id = 42`
3. Passes `42` as the first argument to `show()`
4. Calls `ConversationController::show(42)`

**Web routes use named routes** so Blade templates can reference them by name instead of hardcoding URLs:

```php
Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
```

In a Blade view, instead of writing `<a href="/conversations">`, you write:
```blade
<a href="{{ route('conversations.index') }}">
```

If the URL structure changes later, you only update the route file — every link updates automatically.

### 8.3 Step 2: Middleware Filters Every Request

**Middleware is code that runs before and after your controller.** Think of it as a security checkpoint — the request must pass through several checkpoints before reaching your controller, and the response passes through them again on the way out.

```
Request ──► Middleware 1 (auth:sanctum) ──► Middleware 2 (api.security) ──► Controller
                                                                              │
Response ◄── Middleware 1 (auth:sanctum) ◄── Middleware 2 (api.security) ◄───┘
```

**The `auth:sanctum` middleware** (used by all API routes):

```php
// Pseudocode of what the middleware does:
function handle($request, $next) {
    $token = $request->bearerToken();  // Get token from Authorization header

    if (! $token) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    $user = findUserByToken($token);   // Look up the token in the database

    if (! $user) {
        return response()->json(['message' => 'Invalid token'], 401);
    }

    $request->setUserResolver(fn () => $user);  // Attach user to request

    return $next($request);  // Continue to the controller
}
```

If the token is missing or invalid, the middleware **short-circuits** — it returns a 401 response immediately, and the controller never runs. This is why every controller method can safely call `$request->user()` or `auth()->user()` without checking if the user is logged in — the middleware already handled that.

**The `admin` middleware** checks if the user's role is an admin:

```php
if (! auth()->user()->isAdmin()) {
    abort(403);  // Forbidden — controller never runs
}
```

**Multiple middlewares stack up.** The request passes through all of them in order. If any middleware rejects the request, the controller never runs.

```
Request ──► auth:sanctum ──► admin ──► Controller
    │                            │
    ▼                            ▼
  401 if bad token            403 if not admin
```

### 8.4 Step 3: The Controller Receives and Handles the Request

After passing through all middleware, the request reaches the controller method. Laravel calls the method and passes it:

1. The **Request object** (if type-hinted in the method parameters)
2. Any **route parameters** (e.g., `{id}`, `{userId}`)

```php
// Example: DELETE /api/v1/conversations/42/participants/7
// Maps to: ConversationController::removeParticipant(Request $request, int $id, int $userId)

public function removeParticipant(Request $request, int $id, int $userId): JsonResponse|RedirectResponse
{
    // $id = 42 (from the URL {id})
    // $userId = 7 (from the URL {userId})
    // $request contains headers, query params, form data

    $conversation = Conversation::findOrFail($id);
    // ...
}
```

**Laravel matches route parameter names to method parameter names automatically.** The route has `{id}` and `{userId}`, so the method parameters `$id` and `$userId` receive those values. The order doesn't matter — Laravel matches by name, not position.

**Controllers that serve both web and API:**

The same controller method often handles both web requests (returning Blade views) and API requests (returning JSON). The `$request->is('api/*')` check distinguishes them:

```php
public function index(Request $request): View|JsonResponse
{
    $conversations = Conversation::forUserInGroup(auth()->user())->paginate(20);

    if ($request->is('api/*')) {
        // API call → return JSON
        return response()->json(['data' => $conversations], 200);
    }

    // Web call → return Blade view
    return view('conversations.index', compact('conversations'));
}
```

**What the controller does inside the method:**

Every controller method follows a similar pattern:

```
1. Validate input (if the request has form data)
       │
2. Fetch data from the database (using Eloquent models)
       │
3. Process the data (business logic, often delegated to Services)
       │
4. Return a response (JSON for API, redirect/view for web)
```

### 8.5 Step 4: Eloquent Models Bridge Code and Database

**An Eloquent model is a class that represents a database table.** Each model has a corresponding table, and each instance of the model represents one row in that table.

```
┌────────────────────────────────────────────────────────┐
│  Model: Conversation                                    │
│  Table: conversations                                   │
│                                                         │
│  $conversation = Conversation::find(42)                 │
│  // Runs SQL: SELECT * FROM conversations WHERE id = 42 │
│                                                         │
│  $conversation->name           // Reads the 'name' column │
│  $conversation->type           // Reads the 'type' column │
│  $conversation->group_id       // Reads the 'group_id'  │
│  $conversation->created_at     // Reads the 'created_at'│
└────────────────────────────────────────────────────────┘
```

**The `$fillable` array** controls which columns can be set via mass-assignment:

```php
protected $fillable = ['group_id', 'type', 'name', 'last_activity_at'];

// This works:
Conversation::create(['group_id' => 3, 'type' => 'direct']);

// This throws an error (group_id is mass-assignable, 'admin_only' is not):
Conversation::create(['group_id' => 3, 'admin_only' => true]);
```

This prevents a common security vulnerability called **mass assignment** — where a malicious user adds extra fields to a form submission (like `is_admin = true`) that get written to the database.

**The `$casts` array** tells Laravel to automatically convert column values to PHP types:

```php
protected $casts = [
    'last_activity_at' => 'datetime',  // String → Carbon instance
];

// Now this works:
$conversation->last_activity_at->diffForHumans();  // "2 hours ago"
$conversation->last_activity_at->format('Y-m-d');  // "2026-07-10"

// Without the cast, last_activity_at would be a plain string,
// and calling ->diffForHumans() on a string would crash.
```

#### How Relationships Work

Relationships tell Laravel how tables are connected. They let you navigate from one model to related models without writing JOIN queries manually.

**`belongsTo` — Child points to Parent:**

```php
// messages table has conversation_id → conversations table
class Message extends Model {
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}

// Usage:
$message = Message::find(100);
$conversation = $message->conversation;  // SELECT * FROM conversations WHERE id = 42
echo $conversation->name;  // Now you have the conversation data
```

**`hasMany` — Parent has many Children:**

```php
// A conversation has many messages
class Conversation extends Model {
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}

// Usage:
$conversation = Conversation::find(42);
$messages = $conversation->messages;  // SELECT * FROM messages WHERE conversation_id = 42
foreach ($messages as $message) {
    echo $message->body;
}
```

**`belongsToMany` — Many-to-Many (with a pivot table):**

```php
// A user can be in many conversations, a conversation can have many users
// The pivot table 'conversation_participants' links them
class Conversation extends Model {
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot('role', 'joined_at');  // Extra columns on the pivot table
    }
}

// Usage:
$conversation = Conversation::find(42);
foreach ($conversation->participants as $user) {
    echo $user->full_name;              // From the users table
    echo $user->pivot->role;            // From the pivot table (participant/admin)
    echo $user->pivot->joined_at;       // From the pivot table
}
```

**The pivot table** stores extra data about the relationship. Without `withPivot('role', 'joined_at')`, you could only access the user's data, not their role in the conversation.

#### How Scopes Work

**Scopes are pre-built query filters.** They keep your code DRY by packaging common WHERE clauses into reusable methods:

```php
class Conversation extends Model
{
    // Define a scope
    public function scopeForUserInGroup($query, User $user)
    {
        return $query->where('group_id', $user->group_id)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id));
    }
}

// Use it (notice: the method name drops "scope" and uses camelCase):
$conversations = Conversation::forUserInGroup($user)->get();

// Without the scope, every controller would have to write:
$conversations = Conversation::where('group_id', $user->group_id)
    ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
    ->get();
```

**Why scopes matter for security:** The `forUserInGroup` scope is used by EVERY controller that lists conversations. If a new developer adds a conversation feature and forgets to add the group isolation check, the scope prevents a data leak. The scope is the single source of truth for this filter — there's no code path that bypasses it.

#### How Eager Loading Works (The N+1 Problem)

Without eager loading, accessing a relationship for multiple items creates a separate SQL query for each item:

```php
// BAD: N+1 queries
$conversations = Conversation::all();  // 1 query
foreach ($conversations as $c) {
    echo $c->participants->count();    // 1 query PER conversation
    // If there are 50 conversations, that's 50 more queries
}
// Total: 51 queries for a simple list
```

With eager loading, Laravel gathers all the IDs and runs a single query for all related records:

```php
// GOOD: 2 queries total
$conversations = Conversation::with('participants')->get();  // 1 query
foreach ($conversations as $c) {
    echo $c->participants->count();    // Data already loaded — no extra query
}
// Total: 2 queries
```

The `with('participants')` runs: `SELECT * FROM conversation_participants WHERE conversation_id IN (1, 2, 3, ... 50)`, then Laravel matches them up in memory.

#### How Model Events (booted) Work

Eloquent fires events at specific points in a model's lifecycle. The `booted()` method lets you register listeners for those events.

```php
class Message extends Model
{
    protected static function booted(): void
    {
        // "created" fires AFTER a new message is saved to the database
        static::created(function (Message $message) {
            // At this point, $message has an ID and all fields are persisted
            // Automatically create status rows for all recipients
            app(MessageStatusService::class)->createInitialStatusRows($message);
        });
    }
}
```

**The sequence when a message is saved:**

```
$message = Message::create(['body' => 'Hello']);
       │
       ├── 1. "creating" event fires (before insert)
       │
       ├── 2. INSERT INTO messages ...
       │
       ├── 3. "created" event fires (after insert)
       │         └── Our booted() listener runs
       │               └── createInitialStatusRows() is called
       │
       └── 4. $message is returned with its ID
```

**Why this is important:** The booted hook is automatic. Every message that is created — whether through the web interface, the API, or the sync push — gets status rows created automatically. Nobody can forget to call `createInitialStatusRows()` because it's part of the model, not the controller.

### 8.6 Step 5: Services Contain Business Logic

**Services are classes that hold business logic that doesn't belong in controllers or models.** They keep your code organized:

| Layer | Responsibility |
|---|---|
| **Controller** | Handle HTTP concerns (validate input, return responses) |
| **Service** | Execute business logic (transition status, calculate unread counts) |
| **Model** | Represent data (relationships, scopes, casts) |

**`MessageStatusService`** is a good example. The controller doesn't update `message_status` rows directly — it calls the service:

```php
// In the controller:
public function markRead(int $conversationId)
{
    // Controller handles HTTP concerns
    $userId = auth()->id();

    // Service handles business logic
    $updated = app(MessageStatusService::class)
        ->markConversationAsRead($conversationId, $userId);

    return response()->json(['updated' => $updated], 200);
}
```

**Why not put the logic directly in the controller?**

```
// BAD: Controller knows too much
public function markRead(int $conversationId)
{
    $updated = MessageStatus::whereIn('message_id', function ($q) use ($conversationId) {
            $q->select('id')->from('messages')
                ->where('conversation_id', $conversationId);
        })
        ->where('user_id', auth()->id())
        ->whereIn('status', ['sent', 'delivered'])
        ->update(['status' => 'read', 'updated_at' => now()]);

    broadcast(new MessagesRead($conversationId, auth()->id()))->toOthers();

    return response()->json(['updated' => $updated], 200);
}

// GOOD: Controller delegates to Service
public function markRead(int $conversationId)
{
    $updated = app(MessageStatusService::class)
        ->markConversationAsRead($conversationId, auth()->id());

    return response()->json(['updated' => $updated], 200);
}
```

Reasons:
1. **Testability** — You can test the service in isolation without simulating HTTP requests
2. **Reusability** — If the sync push endpoint also needs to mark messages as read, it calls the same service method
3. **Consistency** — The service ensures status transitions always behave the same way (never go backward from 'read' to 'delivered')

### 8.7 Step 6: The Response Is Built and Sent

After the controller processes the request, it returns a response. Laravel converts the response into HTTP and sends it back to the client.

**For API routes — JSON response:**

```php
return response()->json(['data' => $conversations], 200);
```

This sends:
```
HTTP/1.1 200 OK
Content-Type: application/json

{"data": {"id": 42, "name": null, "type": "direct", ...}}
```

**For web routes — Blade view:**

```php
return view('conversations.index', compact('conversations'));
```

Laravel renders the Blade template into HTML and sends it:
```
HTTP/1.1 200 OK
Content-Type: text/html

<!DOCTYPE html>
<html>
...
```

**For redirects:**

```php
return redirect()->route('conversations.index')
    ->with('success', 'Conversation started successfully.');
```

This sends a `302 Found` response with a `Location` header pointing to the conversations list. The browser follows the redirect, and the success message is stored in the session flash data for one request.

### 8.8 Step 7: Broadcasting to Reverb (The Real-Time Part)

When a message is sent, the controller broadcasts an event after saving to the database:

```php
broadcast(new MessageSent($message))->toOthers();
```

**What `broadcast()` does:**

```
broadcast(new MessageSent($message))
       │
       ├── 1. Laravel serializes the MessageSent event into JSON
       │
       ├── 2. Reads BROADCAST_CONNECTION from .env
       │      (reverb, pusher, log, null)
       │
       ├── 3. If "reverb": connects to the local Reverb server
       │      and pushes the event to the Pusher protocol
       │
       ├── 4. If "log": writes the event to the log file instead
       │      (used in development — no actual broadcast happens)
       │
       └── 5. If "null": no-ops (broadcasting is disabled)
```

**The `->toOthers()` method** tells Laravel to NOT send the event back to the sender. Alice already sees her own message in the UI — she doesn't need to receive it again via WebSocket. Only other participants receive the broadcast.

**How the event class controls what is broadcast:**

```php
class MessageSent implements ShouldBroadcastNow
{
    public Message $message;

    public function broadcastOn(): array
    {
        // Which channel does this event go to?
        return [new PrivateChannel("conversation.{$this->message->conversation_id}")];
    }

    public function broadcastWith(): array
    {
        // What data is sent to the clients?
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender->full_name,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
```

**`PrivateChannel("conversation.42")`** means only authorized users can subscribe. The authorization check runs in `routes/channels.php`:

```php
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    return Conversation::where('id', $conversationId)
        ->where('group_id', $user->group_id)
        ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
        ->exists();
});
```

**The channel authorization closure** is called EVERY time a client tries to subscribe. It receives the authenticated user and the channel parameter (conversationId), and must return `true` (allow) or `false` (deny). This is the security gate for WebSocket subscriptions.

**`ShouldBroadcastNow` vs `ShouldBroadcast`:**

- **`ShouldBroadcastNow`** — Broadcasts synchronously, in the same request. The user's browser waits for the broadcast to finish before getting the response. Adds ~5-15ms. Used for chat messages where every millisecond matters.
- **`ShouldBroadcast`** — Dispatches a job to the queue. The `queue:work` process picks it up and broadcasts it. Adds ~100-500ms delay. Used for things like email notifications where real-time delivery isn't critical.

### 8.9 Authentication Flow (Sanctum Tokens)

The API uses **Sanctum token authentication**. Here's the complete flow:

```
1. Client sends: POST /api/v1/login
   Body: { "email": "alice@example.com", "password": "secret" }
       │
       ▼
2. Server validates credentials against the database
       │
       ├── Valid → Server generates a token: "2|abc123def456..."
       │            Returns: { "token": "2|abc123def456...", "user": { ... } }
       │
       └── Invalid → Returns: { "message": "Invalid credentials" }, 401
       │
       ▼
3. Client stores the token (securely, like a password)
       │
       ▼
4. For every subsequent request, client includes:
   Header: "Authorization: Bearer 2|abc123def456..."
       │
       ▼
5. The auth:sanctum middleware reads the header,
   looks up the token in the personal_access_tokens table,
   and attaches the user to the request.
       │
       ├── Valid token → Request proceeds to the controller
       └── Invalid/expired → Returns 401 Unauthorized
```

**For WebSocket connections, the same token is used:**

```
1. Client opens WebSocket to Reverb server
2. Client subscribes to a private channel
3. Reverb asks the client to authenticate
4. Client POSTs to /api/broadcasting/auth with:
   - socket_id (from the WebSocket connection)
   - channel_name ("private-conversation.42")
   - Authorization: Bearer <token>
5. Laravel verifies the token, then runs the channel authorization callback
6. If authorized, Laravel signs a response and sends it back
7. Client sends the signature to Reverb
8. Reverb subscribes the client to the channel
```

### 8.10 Request Lifecycle Summary — Complete Trace

Let's trace one complete request end-to-end: **Alice sends a message via the API**.

```
Alice's App                                Laravel Server                         Database / Reverb
     │                                          │                                     │
     │  POST /api/v1/conversations/42/messages   │                                     │
     │  Authorization: Bearer 2|abc...           │                                     │
     │  { "body": "Hello!" }                     │                                     │
     │─────────────────────────────────────────►│                                     │
     │                                          │                                     │
     │                                          │  ┌─ routes/api.php                    │
     │                                          │  │  Route::post('/conversations/      │
     │                                          │  │    {id}/messages', ...)            │
     │                                          │  └─────────────────────────────────┘ │
     │                                          │                                     │
     │                                          │  ┌─ Middleware: auth:sanctum          │
     │                                          │  │  Reads Bearer token                │
     │                                          │  │  Finds token in DB                 │
     │                                          │  │  Attaches Alice as $request->user()│
     │                                          │  └─────────────────────────────────┘ │
     │                                          │                                     │
     │                                          │  ┌─ ConversationController::store()   │
     │                                          │  │                                   │
     │                                          │  │  1. $request->validate(['body'...])│
     │                                          │  │                                   │
     │                                          │  │  2. Conversation::forUserInGroup()  │
     │                                          │  │     → SELECT * FROM conversations  │
     │                                          │  │       WHERE group_id = 3            │
     │                                          │  │       AND id = 42                   │
     │                                          │  │       AND EXISTS (participants)     │
     │                                          │  │                                   │
     │                                          │  │  3. $conversation->messages()->     │
     │                                          │  │     create(['body' => 'Hello!'])    │
     │                                          │  │     → INSERT INTO messages          │
     │                                          │───────────────────────────────────►  │
     │                                          │  │                                   │
     │                                          │  │  4. Message::booted() fires         │
     │                                          │  │     → MessageStatusService::        │
     │                                          │  │       createInitialStatusRows()     │
     │                                          │  │     → INSERT INTO message_status    │
     │                                          │───────────────────────────────────►  │
     │                                          │  │                                   │
     │                                          │  │  5. $conversation->update(...)      │
     │                                          │  │     → UPDATE conversations          │
     │                                          │  │       SET last_activity_at = NOW()  │
     │                                          │───────────────────────────────────►  │
     │                                          │  │                                   │
     │                                          │  │  6. broadcast(new MessageSent())    │
     │                                          │  │     → Reverb broadcasts to all     │
     │                                          │  │       subscribers of channel        │
     │                                          │  │       "conversation.42"             │
     │                                          │───────────────────────────────────►  │
     │                                          │  │                                   │
     │                                          │  │  7. Return JSON 201                 │
     │  ◄─────────────────────────────────────────│  { "data": { "id": 100, ... } }     │
     │                                          │                                     │
     │                                          │                                     │
     │  Bob's Browser (subscribed to channel     │                                     │
     │  "conversation.42") receives event:      │                                     │
     │  { "event": "App\\Events\\MessageSent",   │                                     │
     │    "data": { "id": 100, "body": "Hello!", │                                     │
     │              "sender_name": "Alice" } }    │                                     │
```

## 9. The Complete Data Flow

### When Alice sends a message to Bob (both online):

```
Alice types "Hello" and hits Send
       │
       ▼
POST /api/v1/conversations/42/messages
       │
       ▼
MessageController::store()
       │
       ├── 1. Validate: body = "Hello" (max 10,000 chars?) ✓
       │
       ├── 2. Check Alice is a participant ✓
       │
       ├── 3. Save message to database
       │      INSERT INTO messages (conversation_id=42, sender_id=1, body="Hello")
       │
       ├── 4. Message::booted() fires
       │      → MessageStatusService::createInitialStatusRows($message)
       │      → INSERT INTO message_status (message_id=100, user_id=2, status='sent')
       │      (Creates one 'sent' row for Bob)
       │
       ├── 5. Update conversation's last_activity_at
       │      UPDATE conversations SET last_activity_at = NOW() WHERE id = 42
       │
       ├── 6. Broadcast to Reverb
       │      broadcast(new MessageSent($message))->toOthers()
       │      → Reverb pushes to channel "conversation.42"
       │      → Bob's browser receives the event
       │      → JavaScript appends message to Bob's chat view
       │
       └── 7. Return JSON to Alice
           { "data": { "id": 100, "body": "Hello", ... } }
```

### When Bob's mobile app comes online after being offline:

```
Mobile app starts, connects to internet
       │
       ▼
GET /api/v1/sync/pull?device_id=mobile-abc123
       │
       ▼
SyncController::pull()
       │
       ├── 1. Find checkpoint for user=Bob, device="mobile-abc123"
       │      → Already exists: last_synced_at = 2026-07-07 15:00:00
       │
       ├── 2. Find all conversations Bob participates in
       │
       ├── 3. Query: SELECT * FROM messages WHERE conversation_id IN (...)
       │      AND created_at > '2026-07-07 15:00:00'
       │      → Returns the "Hello" message (created at 15:05)
       │
       ├── 4. Query: SELECT * FROM message_status WHERE user_id = Bob
       │      AND updated_at > '2026-07-07 15:00:00'
       │      → Returns the status row (sent → delivered? No, still sent)
       │
       ├── 5. Update checkpoint to now()
       │
       └── 6. Return JSON:
           {
             "messages": [{ "id": 100, "body": "Hello", ... }],
             "status_updates": [],
             "synced_at": "2026-07-07 15:10:00"
           }
```

### When Bob types a reply while offline and sends later:

```
Bob types "Hi Alice" on the mobile app (no internet)
       │
       ▼
Message saved locally on phone: { client_id: "uuid-xyz", body: "Hi Alice" }
       │
       ▼
(30 minutes later) Internet comes back
       │
       ▼
POST /api/v1/sync/push
{ "messages": [{ "client_id": "uuid-xyz", "conversation_id": 42, "body": "Hi Alice" }] }
       │
       ▼
SyncController::push()
       │
       ├── 1. Check Bob is a participant ✓
       │
       ├── 2. Deduplicate: check if identical message exists in last 5 minutes
       │      → No match — this is a new message
       │
       ├── 3. INSERT INTO messages (conversation_id=42, sender_id=2, body="Hi Alice")
       │
       ├── 4. Message::booted() fires → status rows created for Alice
       │
       ├── 5. Broadcast MessageSent to channel "conversation.42"
       │      → Alice receives it in real time (if she's online)
       │
       └── 6. Return:
           { "results": [{ "client_id": "uuid-xyz", "success": true, "message_id": 101 }] }
```

---

### File Inventory

| File | Person | Purpose |
|---|---|---|
| `database/migrations/*_create_conversations_table.php` | P1 | Conversations table |
| `database/migrations/*_create_conversation_participants_table.php` | P1 | Pivot: users ↔ conversations |
| `database/migrations/*_create_messages_table.php` | P1 | Messages table |
| `database/migrations/*_create_message_status_table.php` | P1 | Sent→delivered→read tracking |
| `database/migrations/*_create_sync_checkpoints_table.php` | P1 | Per-device sync position |
| `app/Models/Conversation.php` | P1 | Model with `forUserInGroup` scope |
| `app/Models/Message.php` | P1 | Model with `booted()` status hook |
| `app/Models/MessageStatus.php` | P1 | Status model (no created_at) |
| `app/Models/SyncCheckpoint.php` | P1 | Checkpoint model |
| `app/Models/User.php` (modified) | P1 | Added `conversations()` and `chatMessages()` |
| `app/Http/Controllers/ConversationController.php` | P2 | Conversation CRUD + participant management |
| `resources/views/conversations/index.blade.php` | P2 | Conversation list view |
| `resources/views/conversations/create.blade.php` | P2 | New conversation form |
| `resources/views/conversations/show.blade.php` | P2 | Conversation detail view |
| `routes/web.php` (modified) | P2 | Added conversation web routes |
| `routes/api.php` (modified) | P2 | Added conversation API routes |
| `app/Http/Controllers/MessageController.php` | P3 | Send + fetch messages |
| `app/Events/MessageSent.php` | P3 | Real-time message broadcast event |
| `routes/channels.php` (modified) | P3 | Channel authorization for chat |
| `app/Services/MessageStatusService.php` | P4 | Status transition business logic |
| `app/Events/MessagesRead.php` | P4 | Real-time read receipt broadcast |
| `app/Http/Controllers/SyncController.php` | P5 | Offline sync pull + push |
| `docs/sync-api.md` | P5 | API documentation for desktop client |
| `tests/Feature/Chat/SyncTest.php` | P5 | 22 tests for pull + push sync endpoints |
| `tests/Feature/Chat/MessageTest.php` | P3 | Tests for message send/fetch |

---

*End of Document*
