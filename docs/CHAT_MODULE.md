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
8. [The Complete Data Flow](#8-the-complete-data-flow)

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

### 7.3 Testing — `tests/Feature/Chat/ChatTest.php`

A comprehensive test suite covering all 5 persons' work, with test methods organized by person:

**Person 1 — Schema integrity:**
- `test_create_conversation_requires_group_id()`
- `test_conversation_participants_unique_constraint()`
- `test_message_status_created_on_message_create()`

**Person 2 — Conversation management:**
- `test_user_can_list_own_conversations()`
- `test_cross_group_conversation_rejected()`
- `test_direct_conversation_reuses_existing()`
- `test_only_admin_can_manage_participants()`

**Person 3 — Messaging:**
- `test_participant_can_send_message()`
- `test_non_participant_cannot_send_message()`
- `test_message_broadcast_on_send()`
- `test_fetch_messages_paginated()`

**Person 4 — Status:**
- `test_message_status_progression_sent_to_delivered()`
- `test_message_status_progression_delivered_to_read()`
- `test_batch_read_on_conversation_open()`
- `test_unread_count_accuracy()`

**Person 5 — Sync:**
- `test_delta_sync_returns_only_new_data()`
- `test_checkpoint_not_updated_on_failed_request()`
- `test_offline_message_upload_validates_participants()`
- `test_group_change_reflected_in_sync()`

---

## 8. The Complete Data Flow

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
| `tests/Feature/Chat/ChatTest.php` | P5 | Comprehensive test suite |

---

*End of Document*
