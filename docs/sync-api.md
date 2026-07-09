it pus# Sync API — Desktop Client Documentation

> For the developer building the desktop (JavaFX/Swing) client.
> All endpoints require `Authorization: Bearer <sanctum-token>` header.
> Base URL: `https://studdit.test/api/v1`

---

## Table of Contents

1. [Authentication](#1-authentication)
2. [GET /sync/pull — Delta Sync](#2-get-syncpull--delta-sync)
3. [POST /sync/push — Offline Message Upload](#3-post-syncpush--offline-message-upload)
4. [WebSocket — Real-Time When Online](#4-websocket--real-time-when-online)
5. [Error Handling](#5-error-handling)
6. [Sync Flow Example](#6-sync-flow-example)

---

## 1. Authentication

The desktop client authenticates using Laravel Sanctum tokens, the same as the REST API.

**Obtaining a token** (login):

```
POST /api/v1/login
Content-Type: application/json

{
    "email": "user@example.com",
    "password": "password"
}
```

**Response:**

```json
{
    "token": "1|abc123...",
    "user": {
        "id": 5,
        "full_name": "John Doe",
        "email": "user@example.com",
        "group_id": 3
    }
}
```

**Using the token:**

Include the token in every request header:

```
Authorization: Bearer 1|abc123...
```

Tokens are long-lived but can be revoked by the user from their profile settings.

---

## 2. GET /sync/pull — Delta Sync

Returns everything that changed since the device's last sync checkpoint — new conversations, messages, and status updates.

### Request

```
GET /api/v1/sync/pull?device_id=desktop-win-abc123
Authorization: Bearer <token>
```

| Parameter | Type | Required | Description |
|---|---|---|---|
| `device_id` | string | Yes | A unique identifier for this device installation (max 255 chars). Use a persistent value (e.g., machine hostname + UUID). **This is NOT the sync timestamp** — the server tracks that for you. |

### Response

```json
{
    "success": true,
    "data": {
        "conversations": [
            {
                "id": 1,
                "group_id": 3,
                "type": "direct",
                "name": null,
                "last_activity_at": "2026-07-10T14:30:00Z",
                "created_at": "2026-07-01T10:00:00Z",
                "updated_at": "2026-07-10T14:30:00Z",
                "participants": [
                    { "id": 1, "full_name": "Alice" },
                    { "id": 2, "full_name": "Bob" }
                ]
            }
        ],
        "messages": [
            {
                "id": 42,
                "conversation_id": 1,
                "sender_id": 1,
                "sender": { "id": 1, "full_name": "Alice" },
                "body": "Hello, are you coming to class?",
                "created_at": "2026-07-10T14:30:00Z",
                "updated_at": "2026-07-10T14:30:00Z"
            }
        ],
        "status_updates": [
            {
                "id": 100,
                "message_id": 42,
                "user_id": 2,
                "status": "read",
                "updated_at": "2026-07-10T14:31:00Z"
            }
        ],
        "synced_at": "2026-07-10T14:35:00Z"
    }
}
```

### Important: How Device Tracking Works

- The server maintains a **sync checkpoint** per device — a timestamp of the last successful sync.
- On the **first sync** for a new device, the server returns all data from the past year.
- On subsequent syncs, only records newer than the checkpoint are returned.
- **Store `synced_at`** from the response — it's informational only. The server advances the checkpoint automatically.

### Only returns data you have access to

- Conversations are filtered by **group membership** (you only see your group's conversations).
- Messages are filtered by **participant status** (you only see messages from conversations you're in).
- If you are moved to a different group, your next sync will only return conversations from the new group.

---

## 3. POST /sync/push — Offline Message Upload

Uploads messages the user composed while offline. Each message is validated, saved, and broadcast to other online participants.

### Request

```
POST /api/v1/sync/push
Authorization: Bearer <token>
Content-Type: application/json

{
    "messages": [
        {
            "client_id": "550e8400-e29b-41d4-a716-446655440000",
            "conversation_id": 1,
            "body": "Sorry, I was offline. Yes, I'll be there!"
        },
        {
            "client_id": "550e8400-e29b-41d4-a716-446655440001",
            "conversation_id": 1,
            "body": "I'll bring the notes."
        }
    ]
}
```

| Parameter | Type | Required | Description |
|---|---|---|---|
| `messages` | array | Yes | Array of message objects (max 100 per request) |
| `messages[].client_id` | string | Yes | Client-generated UUID to deduplicate retries |
| `messages[].conversation_id` | integer | Yes | ID of the conversation to post in |
| `messages[].body` | string | Yes | Message text (max 10,000 characters) |

### Response

```json
{
    "success": true,
    "data": {
        "results": [
            {
                "client_id": "550e8400-e29b-41d4-a716-446655440000",
                "success": true,
                "message_id": 101
            },
            {
                "client_id": "550e8400-e29b-41d4-a716-446655440001",
                "success": false,
                "error": "Conversation not found or not accessible."
            }
        ]
    }
}
```

### Per-Message Results

| Field | Description |
|---|---|
| `client_id` | Matches the `client_id` you sent — use this to correlate responses to your local messages |
| `success` | `true` if the message was saved, `false` if it was rejected |
| `message_id` | The server-assigned ID for the saved message (only present if `success = true`) |
| `error` | A description of why the message was rejected (only present if `success = false`) |

### Why Per-Message Error Reporting?

Each message is processed independently. If 3 out of 10 messages fail (e.g., the user was removed from one of the conversations), the other 7 are still saved. Your client should:

1. Match `client_id` from the response to your local draft
2. For `success = true`: replace the local `client_id` with the server's `message_id`
3. For `success = false`: show the error to the user (e.g., "Failed to send: Conversation not found")

### Deduplication

If you send the same `conversation_id + body + sender` combination twice within 5 minutes, the server detects the duplicate and returns the existing `message_id` instead of creating a new message. This handles the case where:

1. You send a batch
2. The server saves it and responds
3. The response is lost (network timeout)
4. You retry the same batch
5. The server finds the identical message and returns `success: true` with the original `message_id`

This is safe to rely on. You do not need to implement deduplication on the client side.

---

## 4. WebSocket — Real-Time When Online

When the desktop client is online, it can connect to Laravel Reverb for real-time message delivery using the **Pusher WebSocket Protocol**.

### Connection Details

| Property | Value |
|---|---|
| WebSocket Host | `ws://localhost:8080` (development) / `wss://studdit.test:8080` (production) |
| Protocol | Pusher WebSocket Protocol |
| Auth Endpoint | `POST /api/broadcasting/auth` |

### Authentication Flow

The standard Pusher auth flow applies:

1. Open a WebSocket connection to the Reverb server
2. Send a Pusher `subscribe` frame for a private channel
3. The server responds with a `pusher:error` asking you to authenticate
4. POST to `/api/broadcasting/auth` with:
   ```json
   {
       "socket_id": "<socket_id_from_step_2>",
       "channel_name": "private-conversation.42"
   }
   ```
5. Include the Sanctum token in the Authorization header
6. On success, the response contains `{ "auth": "<signature>" }`
7. Send the auth signature back over the WebSocket
8. The channel subscription is approved

### Channel Names

| Channel | Event | Data |
|---|---|---|
| `private-conversation.{id}` | `MessageSent` | `{ id, conversation_id, sender_id, sender_name, body, created_at }` |
| `private-conversation.{id}` | `MessagesRead` | `{ conversation_id, read_by_user_id }` |

### Example (Pseudocode)

```
socket = new WebSocket("ws://localhost:8080/app/<reverb-app-key>")

socket.onopen:
    subscribe("private-conversation.42")

socket.onmessage:
    if event == "pusher:error" && requires_auth:
        auth_response = http.post("/api/broadcasting/auth", {
            "socket_id": event.socket_id,
            "channel_name": "private-conversation.42"
        }, headers: { "Authorization": "Bearer <token>" })
        socket.send(auth_response.json.auth)

    if event.event == "App\\Events\\MessageSent":
        message = event.data
        append_to_chat(message)
```

---

## 5. Error Handling

### HTTP Status Codes

| Code | Meaning |
|---|---|
| 200 | Success |
| 400 | Malformed request (missing or invalid parameters) |
| 401 | Missing or expired token |
| 422 | Validation error (check the response body for details) |
| 429 | Rate limited (too many requests) |

### Rate Limiting

Sync endpoints are rate-limited to **60 requests per minute** (same as the rest of the API).

---

## 6. Sync Flow Example

### First Launch (No Previous Data)

```
1. User logs in → get token
2. GET /sync/pull?device_id=desktop-win-abc123
   → Server creates checkpoint, returns all conversations + messages from past year
   → Response includes: conversations[], messages[], status_updates[], synced_at
3. Store data locally
4. Connect to WebSocket for real-time updates
```

### Normal Operation (Online)

```
1. Maintain WebSocket connection to Reverb
2. Messages arrive in real time via MessageSent events
3. Periodically (every 5 min) call GET /sync/pull?device_id=...
   → Gets any missed messages (in case WebSocket dropped)
   → Gets any status updates
```

### Offline Period

```
1. WebSocket disconnects (no internet)
2. User types messages — store locally with client_id (UUID)
3. When internet returns:
   a. POST /sync/push with all queued messages
   b. Check results — mark failures for user review
   c. GET /sync/pull to catch up on missed messages
   d. Reconnect WebSocket
```
