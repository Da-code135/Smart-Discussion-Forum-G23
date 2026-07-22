# Smart Discussion Forum — API Guide for JavaFX Desktop Clients

> **Audience:** Java / JavaFX developers building the companion desktop app.
> **Companion doc:** For exhaustive request/response field tables, see [`API-SPECIFICATION.md`](../API-SPECIFICATION.md) in the project root.

**Base URL (local):** `http://127.0.0.1:8000/api/v1`  
**Auth:** Laravel Sanctum Bearer token  
**Format:** JSON (`Content-Type: application/json`, `Accept: application/json`)  
**Global rate limit:** 60 requests/minute per IP

---

## Table of Contents

1. [What you are building](#1-what-you-are-building)
2. [Domain model you must understand](#2-domain-model-you-must-understand)
3. [Recommended JavaFX project setup](#3-recommended-javafx-project-setup)
4. [HTTP client skeleton (copy this first)](#4-http-client-skeleton-copy-this-first)
5. [Session & token storage](#5-session--token-storage)
6. [Authentication flows](#6-authentication-flows)
7. [Core forum flows](#7-core-forum-flows)
8. [Warnings, blacklist & account gates](#8-warnings-blacklist--account-gates)
9. [Suggested JavaFX screens](#9-suggested-javafx-screens)
10. [Threading rules (critical)](#10-threading-rules-critical)
11. [Error handling](#11-error-handling)
12. [Rate limits](#12-rate-limits)
13. [Endpoint quick reference](#13-endpoint-quick-reference)
14. [Testing checklist](#14-testing-checklist)

---

## 1. What you are building

A **desktop client** that talks to this Laravel REST API. The web UI and your JavaFX app share the same backend.

Your app should support at least:

| Feature | Priority | Endpoints |
|---------|----------|-----------|
| Register / Login / Logout | Required | `/register`, `/login`, `/logout` |
| Browse topics & replies | Required | `/topics`, `/topics/{id}`, `/topics/{id}/posts` |
| Create topic & reply | Required | `POST /topics`, `POST /topics/{id}/posts` |
| Password reset (OTP) | Required | `/password/forgot`, `/password/reset` |
| Warning acknowledgement | Required | `/warnings/unacknowledged`, `/warnings/acknowledge` |
| Profile | Recommended | `/me`, `/profile`, `/password/change` |
| PDF export / share | Recommended | `/topics/{id}/export/pdf`, `/topics/{id}/share` |
| Post visibility | Optional | `/posts/{id}/visibility/*` |
| Quizzes, chat, admin | Later | See quick reference + `API-SPECIFICATION.md` |

---

## 2. Domain model you must understand

### Groups (isolation)

- Every user belongs to **one group**.
- Topics/posts are scoped to a group.
- The server returns **403** if you try to access another group's content.
- System Admins can access all groups.

### Roles

| Role | Desktop UI should show |
|------|------------------------|
| `Member` | Forum, profile, quizzes assigned to them |
| `Student` | Same as Member + take quizzes |
| `Lecturer` | Forum + quiz creation / grades |
| `Group Administrator` | Forum + group-scoped admin screens |
| `System Administrator` | Full admin |

Use `GET /me` after login and branch your navigation on `user.role`.

### Account status

| Status | Meaning | Client behaviour |
|--------|---------|------------------|
| `active` | Normal | Proceed to home |
| `warned` | Has warning to acknowledge | Show warning screen before forum |
| `blacklisted` | Blocked | Show message; do **not** enter the app |

### Topics & posts

- A **Topic** is a thread (`title`, `description`, `post_type`: `discussion` | `question`).
- A **Post** is a reply inside a topic.
- Soft-deleted / moderated posts have `is_removed = true` and are hidden from normal lists.
- Authors can **exclude** specific users from seeing a reply (post visibility).

---

## 3. Recommended JavaFX project setup

### Dependencies (Maven)

```xml
<!-- Java 17+ recommended -->
<dependencies>
  <!-- JavaFX (use your preferred JavaFX Maven plugin / SDK) -->
  <dependency>
    <groupId>org.openjfx</groupId>
    <artifactId>javafx-controls</artifactId>
    <version>21</version>
  </dependency>
  <dependency>
    <groupId>org.openjfx</groupId>
    <artifactId>javafx-fxml</artifactId>
    <version>21</version>
  </dependency>

  <!-- JSON -->
  <dependency>
    <groupId>com.fasterxml.jackson.core</groupId>
    <artifactId>jackson-databind</artifactId>
    <version>2.17.2</version>
  </dependency>
  <dependency>
    <groupId>com.fasterxml.jackson.datatype</groupId>
    <artifactId>jackson-datatype-jsr310</artifactId>
    <version>2.17.2</version>
  </dependency>
</dependencies>
```

Use **Java 11+ `java.net.http.HttpClient`** (built-in). You do **not** need Apache HttpClient.

### Suggested package layout

```
com.forum.desktop
├── App.java
├── api
│   ├── ApiClient.java          // all HTTP calls
│   ├── ApiException.java
│   └── dto/                    // LoginRequest, TopicDto, UserDto, ...
├── session
│   └── SessionManager.java     // token + current user
├── ui
│   ├── LoginController.java
│   ├── ForumController.java
│   ├── TopicDetailController.java
│   └── ...
└── util
    └── FxTasks.java            // helper for background + Platform.runLater
```

---

## 4. HTTP client skeleton (copy this first)

Every request (except login/register/forgot/reset/shared) must send:

```http
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

### Minimal `ApiClient`

```java
package com.forum.desktop.api;

import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.fasterxml.jackson.datatype.jsr310.JavaTimeModule;
import com.forum.desktop.session.SessionManager;

import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.time.Duration;

public class ApiClient {
    private static final String BASE_URL = "http://127.0.0.1:8000/api/v1";

    private final HttpClient http = HttpClient.newBuilder()
            .connectTimeout(Duration.ofSeconds(10))
            .build();

    private final ObjectMapper mapper = new ObjectMapper()
            .registerModule(new JavaTimeModule());

    public JsonNode post(String path, Object body) throws Exception {
        return send("POST", path, body, true);
    }

    public JsonNode get(String path) throws Exception {
        return send("GET", path, null, true);
    }

    public JsonNode postPublic(String path, Object body) throws Exception {
        return send("POST", path, body, false);
    }

    private JsonNode send(String method, String path, Object body, boolean auth) throws Exception {
        HttpRequest.Builder builder = HttpRequest.newBuilder()
                .uri(URI.create(BASE_URL + path))
                .timeout(Duration.ofSeconds(30))
                .header("Accept", "application/json");

        if (auth) {
            String token = SessionManager.get().getToken();
            if (token == null || token.isBlank()) {
                throw new ApiException(401, "Not logged in");
            }
            builder.header("Authorization", "Bearer " + token);
        }

        if (body != null) {
            String json = mapper.writeValueAsString(body);
            builder.header("Content-Type", "application/json");
            builder.method(method, HttpRequest.BodyPublishers.ofString(json));
        } else {
            builder.method(method, HttpRequest.BodyPublishers.noBody());
        }

        HttpResponse<String> response = http.send(builder.build(), HttpResponse.BodyHandlers.ofString());
        int code = response.statusCode();
        JsonNode root = response.body().isBlank()
                ? mapper.createObjectNode()
                : mapper.readTree(response.body());

        if (code >= 200 && code < 300) {
            return root;
        }

        String message = root.has("message") ? root.get("message").asText() : "Request failed";
        throw new ApiException(code, message, root);
    }

    /** Download binary (e.g. PDF export). */
    public byte[] getBytes(String path) throws Exception {
        HttpRequest request = HttpRequest.newBuilder()
                .uri(URI.create(BASE_URL + path))
                .header("Authorization", "Bearer " + SessionManager.get().getToken())
                .header("Accept", "application/pdf")
                .GET()
                .build();

        HttpResponse<byte[]> response = http.send(request, HttpResponse.BodyHandlers.ofByteArray());
        if (response.statusCode() != 200) {
            throw new ApiException(response.statusCode(), "Download failed");
        }
        return response.body();
    }
}
```

### `ApiException`

```java
package com.forum.desktop.api;

import com.fasterxml.jackson.databind.JsonNode;

public class ApiException extends Exception {
    private final int status;
    private final JsonNode body;

    public ApiException(int status, String message) {
        this(status, message, null);
    }

    public ApiException(int status, String message, JsonNode body) {
        super(message);
        this.status = status;
        this.body = body;
    }

    public int getStatus() { return status; }
    public JsonNode getBody() { return body; }

    public boolean requiresWarningAck() {
        return body != null
                && body.path("requires_warning_acknowledgement").asBoolean(false);
    }
}
```

---

## 5. Session & token storage

After successful login/register, persist:

1. `token` (string) — Sanctum bearer token  
2. `user` (id, full_name, email, role, group, account_status)

### Simple file-based store (dev)

```java
// Prefer Preferences / encrypted store for production
Preferences prefs = Preferences.userRoot().node("smart-discussion-forum");
prefs.put("token", token);
prefs.put("userJson", mapper.writeValueAsString(user));
```

### Rules

- On **logout** → call `POST /logout`, then clear local token.
- On **401** from any request → clear session and show Login screen.
- On **password reset** → server revokes all tokens; force re-login.
- Do **not** put the token in logs or UI labels.

---

## 6. Authentication flows

### 6.1 Register

```http
POST /api/v1/register
Content-Type: application/json

{
  "full_name": "John Doe",
  "email": "john@example.com",
  "password": "Password123",
  "password_confirmation": "Password123"
}
```

**Rules:** `full_name` max 100, email unique, password min 8 + must match confirmation.

**201 response:**
```json
{
  "message": "Registration successful",
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "full_name": "John Doe",
    "email": "john@example.com",
    "account_status": "active",
    "role": "Member",
    "group": "General",
    "email_verified_at": null,
    "last_active_at": null
  }
}
```

**JavaFX action:** save token + user → open Forum home.

### 6.2 Login

```http
POST /api/v1/login

{ "email": "john@example.com", "password": "Password123" }
```

**Success (200):** same shape as register (`token` + `user`).

**Handle these failures in UI:**

| Status | Condition | UI |
|--------|-----------|-----|
| 401 | Bad credentials | Show "Invalid email or password" |
| 403 | Blacklisted | Show `message` (includes expiry date); block app |
| 403 + `requires_warning_acknowledgement: true` | Warned | Go to Warning screen (see §8) |
| 429 | Too many attempts | Show wait time from message / `Retry-After` |

**Java example:**

```java
public void login(String email, String password) {
    Map<String, String> body = Map.of(
            "email", email,
            "password", password
    );

    FxTasks.run(() -> api.postPublic("/login", body), json -> {
        String token = json.get("token").asText();
        JsonNode user = json.get("user");
        SessionManager.get().save(token, user);
        navigateToHome();
    }, this::handleAuthError);
}

private void handleAuthError(Throwable t) {
    if (t instanceof ApiException ex) {
        if (ex.getStatus() == 403 && ex.requiresWarningAck()) {
            // Optional: still store user info from body for display
            navigateToWarningScreen(ex.getBody());
            return;
        }
        showError(ex.getMessage());
    } else {
        showError("Cannot reach server. Is Laravel running?");
    }
}
```

### 6.3 Logout

```http
POST /api/v1/logout
Authorization: Bearer {token}
```

Then clear local session and show Login.

### 6.4 Current user

```http
GET /api/v1/me
Authorization: Bearer {token}
```

Call this on app start if a saved token exists. If 401 → Login.

### 6.5 Password reset (OTP — desktop friendly)

There is **no browser redirect**. Flow:

1. User enters email → `POST /password/forgot`
2. User receives **6-digit OTP** by email
3. User enters OTP + new password → `POST /password/reset`

```http
POST /api/v1/password/forgot
{ "email": "john@example.com" }

POST /api/v1/password/reset
{
  "email": "john@example.com",
  "otp": "482193",
  "password": "NewPass123",
  "password_confirmation": "NewPass123"
}
```

OTP expires in **10 minutes**. After success, all tokens are revoked → user must log in again.

### 6.6 Change password (logged in)

```http
POST /api/v1/password/change
Authorization: Bearer {token}

{
  "current_password": "Password123",
  "password": "NewPass123",
  "password_confirmation": "NewPass123"
}
```

---

## 7. Core forum flows

### 7.1 List topics (forum feed)

```http
GET /api/v1/topics
Authorization: Bearer {token}
```

Returns paginated topics for the user's accessible group(s), newest first.

**Typical JSON:**
```json
{
  "data": {
    "data": [
      {
        "id": 1,
        "title": "How do I use Eloquent?",
        "description": "...",
        "status": "active",
        "post_type": "question",
        "creator": { "id": 1, "full_name": "John Doe" },
        "posts_count": 3,
        "created_at": "2026-07-18T10:00:00.000000Z"
      }
    ],
    "current_page": 1,
    "last_page": 3,
    "per_page": 20,
    "total": 42
  }
}
```

> Laravel pagination nests a `data` array inside the payload. In Jackson, read `root.path("data").path("data")` for the list, and `root.path("data").path("current_page")` for paging.

**Filter by type:**
```http
GET /api/v1/topics/type/discussion
GET /api/v1/topics/type/question
```

### 7.2 Create a topic

```http
POST /api/v1/topics
Authorization: Bearer {token}

{
  "title": "Normalization help",
  "description": "Can someone explain 3NF?",
  "post_type": "question"
}
```

- `post_type` optional: `discussion` (default) or `question`
- Title must be unique **within the user's group**
- Rate limited for normal users (3 topics / 60s)

### 7.3 Open a topic (detail)

```http
GET /api/v1/topics/{topicId}
Authorization: Bearer {token}
```

Includes posts filtered by visibility + moderation.

### 7.4 List / create replies

```http
GET  /api/v1/topics/{topicId}/posts
POST /api/v1/topics/{topicId}/posts

{ "content": "Here is my answer..." }
```

Reply rate limit: 5 / 60s for regular users.

### 7.5 Update / delete own post

```http
PUT    /api/v1/posts/{postId}   { "content": "edited..." }
DELETE /api/v1/posts/{postId}
```

Delete is a soft remove (`is_removed`).

### 7.6 Export PDF

```http
GET /api/v1/topics/{topicId}/export/pdf
Authorization: Bearer {token}
Accept: application/pdf
```

Returns **binary PDF** (`topic-{id}.pdf`). Save to disk and open with `Desktop.getDesktop().open(file)`.

```java
byte[] pdf = api.getBytes("/topics/" + topicId + "/export/pdf");
Path out = Path.of(System.getProperty("user.home"), "Downloads", "topic-" + topicId + ".pdf");
Files.write(out, pdf);
Desktop.getDesktop().open(out.toFile());
```

### 7.7 Generate share link

```http
POST /api/v1/topics/{topicId}/share

{ "expires_in": 1440 }
```

`expires_in` = minutes (default 1440 = 24h, max 10080 = 7 days).

**201 response:**
```json
{
  "message": "Share link generated successfully.",
  "data": {
    "url": "http://127.0.0.1:8000/api/v1/topics/5/shared?expires=...&signature=...",
    "expires_at": "2026-07-19T10:00:00+00:00",
    "expires_in_minutes": 1440
  }
}
```

Show `data.url` in a text field + Copy button. Opening that URL does **not** require a token (signature authorizes access).

### 7.8 Post visibility (hide reply from one user)

```http
POST   /api/v1/posts/{postId}/visibility/exclude   { "user_id": 3 }
GET    /api/v1/posts/{postId}/visibility
DELETE /api/v1/posts/{postId}/visibility/{userId}
```

Only the **post author** can manage exclusions. Same-group users only. Duplicate exclude → **409**.

---

## 8. Warnings, blacklist & account gates

### On login

1. If blacklisted → show message, stay on login.
2. If warned with unacknowledged warning → navigate to Warning screen.

### Warning acknowledgement API

```http
GET  /api/v1/warnings/unacknowledged
POST /api/v1/warnings/acknowledge
```

Both require a valid Bearer token. After acknowledge, reload `/me` and enter the forum if `account_status` is no longer blocking.

**Login 403 warned body:**
```json
{
  "message": "Your account is warned. Please acknowledge the warning before continuing.",
  "requires_warning_acknowledgement": true,
  "user": { "...": "..." }
}
```

Note: this 403 does **not** always include a token. Your client should either:
- prompt the user to acknowledge after a successful login path your backend provides, **or**
- call unacknowledged/acknowledge once a token is available after a normal login when warnings were previously acknowledged.

> Practical approach: if login returns 200, still call `GET /warnings/unacknowledged` before showing the forum. If any remain, show the Warning screen first.

---

## 9. Suggested JavaFX screens

Build navigation around this map:

```
┌─────────────┐     success      ┌──────────────┐
│ Login / Reg │ ───────────────► │ Home / Forum │
└─────────────┘                  └──────┬───────┘
       │                                │
       │ forgot                         ├─► Topic Detail (replies)
       ▼                                ├─► Create Topic
┌─────────────┐                         ├─► Profile / Change Password
│ OTP Reset   │                         ├─► Share / Export
└─────────────┘                         └─► Admin (role-gated)
       ▲
       │ if warned
┌─────────────┐
│ Acknowledge │
│  Warning    │
└─────────────┘
```

### Role-based menus

```java
String role = SessionManager.get().getRole();
boolean isAdmin = "System Administrator".equals(role)
        || "Group Administrator".equals(role);
adminMenu.setVisible(isAdmin);
```

---

## 10. Threading rules (critical)

**Never call the API on the JavaFX Application Thread.**

Use `Task` / `Service`, then update UI with `Platform.runLater`:

```java
public final class FxTasks {
    public static <T> void run(Callable<T> work, Consumer<T> onSuccess, Consumer<Throwable> onError) {
        Task<T> task = new Task<>() {
            @Override protected T call() throws Exception {
                return work.call();
            }
        };
        task.setOnSucceeded(e -> onSuccess.accept(task.getValue()));
        task.setOnFailed(e -> onError.accept(task.getException()));
        Thread t = new Thread(task, "api-call");
        t.setDaemon(true);
        t.start();
    }
}
```

Disable submit buttons while a request is in flight to prevent double posts.

---

## 11. Error handling

### Standard error body

```json
{
  "message": "Human-readable error message",
  "errors": {
    "email": ["The email has already been taken."]
  }
}
```

`errors` appears mainly on **422** validation failures. Flatten them for form labels:

```java
if (ex.getStatus() == 422 && ex.getBody() != null && ex.getBody().has("errors")) {
    JsonNode errors = ex.getBody().get("errors");
    errors.fields().forEachRemaining(entry ->
            showFieldError(entry.getKey(), entry.getValue().get(0).asText()));
}
```

### Status codes

| Code | Meaning | Client action |
|------|---------|---------------|
| 200 / 201 | Success | Update UI |
| 401 | Bad/missing token | Clear session → Login |
| 403 | Forbidden / warned / blacklisted / group isolation | Show message; maybe special screen |
| 404 | Missing resource | Toast + go back |
| 409 | Duplicate | Show conflict message |
| 410 | Shared topic inactive | Show "link expired/unavailable" |
| 422 | Validation | Highlight fields |
| 429 | Rate limited | Disable button; retry after `Retry-After` seconds |
| 500 | Server error | Generic "try again later" |

### Connectivity

If `HttpClient` throws `ConnectException` / `HttpTimeoutException`, show:

> Cannot reach server at `http://127.0.0.1:8000`. Start Laravel (`php artisan serve` or Herd) and try again.

---

## 12. Rate limits

| Endpoint | Limit |
|----------|-------|
| All API | 60 / minute / IP |
| Login | 5 / 30s (email+IP and email-only) |
| Register | 3 / 60s / IP |
| Forgot password | 3 / 15 min / email |
| Reset password | 5 OTP guesses / 10 min / email |
| Create topic | 3 / 60s (admins/lecturers bypass) |
| Create reply | 5 / 60s (admins/lecturers bypass) |

On **429**, read `Retry-After` header when present.

---

## 13. Endpoint quick reference

All paths below are relative to `/api/v1`.  
`Auth = Yes` means send `Authorization: Bearer {token}`.

### Auth & account

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| POST | `/register` | No | Create account + token |
| POST | `/login` | No | Login + token |
| POST | `/logout` | Yes | Revoke current token |
| POST | `/password/forgot` | No | Email 6-digit OTP |
| POST | `/password/reset` | No | Reset with OTP |
| POST | `/password/change` | Yes | Change password |
| GET | `/me` | Yes | Current user |
| POST | `/profile` | Yes | Update name/email |
| POST | `/profile/picture` | Yes | Upload avatar (multipart) |
| DELETE | `/account` | Yes | Delete account (`password` required) |
| POST | `/token/refresh` | Yes | Rotate token |
| GET | `/tokens` | Yes | List tokens |
| DELETE | `/tokens/{id}` | Yes | Revoke token |
| POST | `/email/verify` | Yes | Verify email |
| POST | `/email/resend` | Yes | Resend verification |

### Forum

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| GET | `/topics` | Yes | List topics |
| GET | `/topics/type/{type}` | Yes | Filter discussion/question |
| POST | `/topics` | Yes | Create topic |
| GET | `/topics/{id}` | Yes | Topic detail |
| PUT | `/topics/{id}` | Yes | Update topic |
| DELETE | `/topics/{id}` | Yes | Archive topic |
| GET | `/topics/{id}/posts` | Yes | List replies |
| POST | `/topics/{id}/posts` | Yes | Create reply |
| PUT | `/posts/{id}` | Yes | Edit reply |
| DELETE | `/posts/{id}` | Yes | Delete reply |
| GET | `/topics/{id}/export/pdf` | Yes | Download PDF |
| POST | `/topics/{id}/share` | Yes | Signed share URL |
| GET | `/topics/{id}/shared` | Signed query | Public shared access |
| GET | `/posts/{id}/visibility` | Yes | List exclusions |
| POST | `/posts/{id}/visibility/exclude` | Yes | Exclude user |
| DELETE | `/posts/{id}/visibility/{userId}` | Yes | Remove exclusion |
| GET | `/categories` | Yes | List categories |
| GET | `/categories/{id}/topics` | Yes | Topics in category |
| GET | `/groups` | Yes | Browse groups |
| GET | `/groups/{id}` | Yes | Group detail |
| GET | `/groups/{id}/topics` | Yes | Group topics |
| GET | `/groups/{id}/members` | Yes | Group members |
| POST | `/reports` | Yes | Report content |
| GET | `/me/reports` | Yes | My reports |
| GET | `/warnings/unacknowledged` | Yes | Pending warnings |
| POST | `/warnings/acknowledge` | Yes | Acknowledge warning |

### Also available (build later)

Notifications, recommendations, conversations/messages, quizzes/grades, offline sync, and `/admin/*` management APIs exist. Full schemas: **[`API-SPECIFICATION.md`](../API-SPECIFICATION.md)**.

---

## 14. Testing checklist

Use this before demos:

- [ ] Register → token saved → forum loads
- [ ] Logout → token cleared → protected calls fail with 401
- [ ] Login with wrong password → 401 message shown
- [ ] Create topic → appears in feed
- [ ] Open topic → post reply → reply appears
- [ ] PDF export downloads a file
- [ ] Share returns a URL; opening it works without login
- [ ] Forgot password → OTP → reset → must login again
- [ ] App recovers from server-down with a clear message
- [ ] All network calls run off the JavaFX UI thread

### Local server

```bash
# From the Laravel project root
php artisan serve
# API base: http://127.0.0.1:8000/api/v1
```

If using Laravel Herd, use the `.test` URL instead and update `BASE_URL` in `ApiClient`.

---

## Appendix A — Common headers cheat sheet

```
Accept: application/json
Content-Type: application/json
Authorization: Bearer 1|your-token-here
```

For PDF downloads, set `Accept: application/pdf` (or `*/*`).

## Appendix B — Pagination tip

Laravel returns:

```json
{
  "data": {
    "current_page": 1,
    "data": [ /* items */ ],
    "last_page": 5,
    "per_page": 20,
    "total": 97
  }
}
```

Page through with `?page=2` on list endpoints that support it.

## Appendix C — Where to go next

| Need | Document |
|------|----------|
| Build JavaFX client (this file) | `docs/API_DOCUMENTATION.md` |
| Exact field-level API contracts | `API-SPECIFICATION.md` |
| Forum module behaviour (web) | `docs/FORUM_MODULE.md` |
| Full system overview | `docs/DOCUMENTATION.md` |

---

**Last updated:** July 2026 — aligned with Sanctum token auth, OTP password reset, topic share/export, and post visibility APIs.
