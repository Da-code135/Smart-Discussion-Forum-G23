# User Management Module — End-to-End Guide

> **Project:** Smart-Discussion-Forum-G23  
> **Framework:** Laravel 13.16.1 | PHP 8.4.22  
> **Purpose:** Understand every layer of user management — the database, models, controllers, middleware, views, and how they connect from UI clicks to behind-the-scenes logic.

---

## Table of Contents

1. [What Is User Management?](#1-what-is-user-management)
2. [User Roles — Who's Who in the System](#2-user-roles--whos-who-in-the-system)
3. [Account Status — The Life of a User Account](#3-account-status--the-life-of-a-user-account)
4. [Database Tables — Where Data Lives](#4-database-tables--where-data-lives)
5. [The User Model (`User.php`)](#5-the-user-model-userphp)
6. [How Registration Works (The 3-Step Flow)](#6-how-registration-works-the-3-step-flow)
7. [How Login Works (Web)](#7-how-login-works-web)
8. [How Login Works (API / Desktop Client)](#8-how-login-works-api--desktop-client)
9. [Authorization — Who Can Do What](#9-authorization--who-can-do-what)
10. [Admin User Management — The Full CRUD](#10-admin-user-management--the-full-crud)
11. [Account Lifecycle — Warnings and Blacklisting](#11-account-lifecycle--warnings-and-blacklisting)
12. [Email Verification](#12-email-verification)
13. [Password Management](#13-password-management)
14. [File Connection Map — How Everything Connects](#14-file-connection-map--how-everything-connects)
15. [Code Explanations for Your Presentation](#15-code-explanations-for-your-presentation)

---

## 1. What Is User Management?

The User Management Module is responsible for everything related to user accounts on the Smart Discussion Forum. Think of it as three big parts:

**Part 1 — Joining the system (Registration & Login)**
- A visitor signs up, accepts rules, and creates an account
- A registered user logs in using their email and password
- The system decides: let them in, redirect them to acknowledge a warning, or block them

**Part 2 — Managing accounts (Admin CRUD)**
- Admins can view a list of all users
- Admins can create, edit, and delete user accounts
- Admins can reset passwords, blacklist users, change roles, and resolve warnings

**Part 3 — Keeping accounts healthy (Automated Lifecycle)**
- An automated system checks if users are active
- Inactive users get warnings, then get blacklisted if they don't respond

The system has **two doors** for users to enter:
- **Web (browser)** — Uses cookies/sessions (like most websites)
- **API (desktop app)** — Uses tokens (a secret key the app stores)

---

## 2. User Roles — Who's Who in the System

The system has 5 roles, arranged in a hierarchy. Each role determines what a user can see and do.

| # | Role | Who They Are | What They Can Do |
|---|------|-------------|------------------|
| 1 | **System Administrator** | The super-admin / owner | Everything. See all users, all groups, change settings. No limits. |
| 2 | **Group Administrator** | A manager of specific groups | See only users in their assigned groups. Edit those users (but can't change their role or status). |
| 3 | **Lecturer** | A teacher | Forum features, quizzes, marking. (Not an admin.) |
| 4 | **Student** | A learner | Forum discussions, quizzes, reports. (Not an admin.) |
| 5 | **Member** | Default role for new users | Basic forum access — discussions, topic filtering, PDF export. |

**Important:** Only System Admin and Group Admin are considered "admins." The other three roles are regular users who cannot access the admin panel at all.

The code checks roles by looking at the **role name** (the actual English word), not the ID number:

```php
// In User.php — this is how the system checks if you're a System Admin
public function isSystemAdmin(): bool {
    return $this->role && $this->role->role_name === 'System Administrator';
}
```

---

## 3. Account Status — The Life of a User Account

Every user has a status that changes over time. Think of it like traffic lights:

```
Green (active)  ──►  Yellow (warned)  ──►  Red (blacklisted)
   ▲                                                │
   └────────────────────────────────────────────────┘
                    (Admin lifts the blacklist)
```

| Status | What It Means | Can They Log In? |
|--------|---------------|-------------------|
| `active` | Normal account. Full access. | Yes |
| `warned` | User was inactive too long. Must acknowledge a warning. | Yes, but forced to see warning first |
| `blacklisted` | Account suspended. Blocked. | No — completely locked out |

A user starts as `active` when they register. The system automatically changes the status based on inactivity (see Section 11). Admins can also manually change it.

---

## 4. Database Tables — Where Data Lives

The user management system touches these database tables. Each is like a spreadsheet with rows and columns.

### The Main Table: `users`

This is the most important table. Every user account is one row here.

| Column | What It Stores | Example |
|--------|---------------|---------|
| `id` | Unique number for each user | 1, 2, 3... |
| `full_name` | The user's display name | "John Doe" |
| `email` | Email address (must be unique) | john@example.com |
| `password` | The hashed (scrambled) password | `$2y$10$...` (not readable) |
| `role_id` | Which role this user has (1-5) | 5 = Member, 1 = System Admin |
| `group_id` | Which group they belong to | Can be NULL only for System Admin |
| `account_status` | active / warned / blacklisted | 'active' |
| `last_active_at` | When they last did something | 2026-07-01 14:30:00 |
| `email_verified_at` | When they verified their email | NULL if not verified |

### Supporting Tables

| Table | What It Stores | Why It Matters |
|-------|---------------|----------------|
| `roles` | The 5 role names (System Admin, Group Admin, etc.) | Used to check permissions |
| `groups` | Discussion groups (e.g., "Computer Science 101") | Users belong to groups |
| `group_admins` | Which admins manage which groups (pivot table) | This is how Group Admins are scoped |
| `warnings` | Warnings issued to users (max 2 before blacklist) | Tracks the warning lifecycle |
| `blacklist_records` | Records of users who were blacklisted | Tracks who, when, why, and expiry |
| `onboarding_agreements` | Records of users accepting/rejecting platform rules | Created during registration Step 3 |
| `email_verification_tokens` | One-time tokens for email verification | Expires after 24 hours |
| `audit_logs` | Log of every admin action on users | For accountability |

### How Tables Are Connected

Think of it like this:

```
A User HAS ONE Role (role_id points to roles.id)
A User HAS ONE Group (group_id points to groups.id)
A User HAS MANY Warnings (user_id in warnings table)
A User HAS MANY BlacklistRecords (user_id in blacklist_records table)
A Group HAS MANY Users (group_id in users table)
A Group HAS MANY Admins through group_admins (pivot table)
```

---

## 5. The User Model (`User.php`)

**File:** [app/Models/User.php](app/Models/User.php)

The User model is the PHP class that represents a user. Every time the code works with a user, it uses this class. Think of it as the blueprint for a user object.

### What It Extends

```php
class User extends Authenticatable
```

`Authenticatable` is a Laravel built-in class that gives the user login/logout capability. It's what makes `Auth::login()` and `Auth::check()` work.

### Traits (Add-on Features)

```php
use HasApiTokens, HasFactory, Notifiable;
```

- **HasApiTokens** — allows the user to have API tokens (for the desktop app login)
- **HasFactory** — allows creating fake users for testing
- **Notifiable** — allows sending emails to the user

### Fillable Fields (What Can Be Mass-Assigned)

```php
protected $fillable = [
    'full_name', 'email', 'password', 'role_id', 'group_id',
    'account_status', 'last_active_at', 'profile_picture',
    'is_warned', 'blacklisted_at', 'email_verified_at',
];
```

**Why this exists:** It's a security measure. If someone tries to sneak extra fields into a form (like `is_admin = true`), Laravel ignores them because they're not in this list.

### Hidden Fields (Never Shown in API Responses)

```php
protected $hidden = ['password', 'remember_token'];
```

When the API returns user data as JSON, the password is automatically removed. You'll never accidentally leak a password.

### Casts (Automatic Type Conversion)

```php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',  // Converts to a date object automatically
        'password' => 'hashed',              // Auto-hashes when you set a password
        'last_active_at' => 'datetime',      // Converts to a date object automatically
    ];
}
```

**The `hashed` cast is magic:** When you write `$user->password = 'newpass'`, Laravel automatically runs the hashing function. You never manually hash passwords in the code.

### The Boot Method (Safety Check Before Saving)

```php
protected static function booted(): void
{
    static::saving(function (User $user) {
        if (is_null($user->group_id)) {
            $roleName = Role::where('id', $user->role_id)->value('role_name');
            if ($roleName !== 'System Administrator') {
                throw new \RuntimeException(
                    'Every non-admin user must belong to a group. A group_id is required.'
                );
            }
        }
    });
}
```

**Plain English:** Before every save, check: if the user has no group, are they a System Admin? If yes, it's okay (System Admins don't need a group). If no, throw an error. This prevents creating users who don't belong to any group.

### Relationships (How Users Connect to Other Data)

```php
public function role() { return $this->belongsTo(Role::class); }
public function group() { return $this->belongsTo(Group::class); }
public function warnings() { return $this->hasMany(Warning::class); }
public function blacklistRecords() { return $this->hasMany(BlacklistRecord::class); }
public function onboardingAgreements() { return $this->hasMany(OnboardingAgreement::class); }
```

- `belongsTo` means "I have a foreign key pointing to that table" (user has a `role_id` column)
- `hasMany` means "that table has a foreign key pointing to me" (warnings has a `user_id` column)

### Role-Checking Methods

```php
public function isSystemAdmin(): bool {
    // Returns true if the user's role name is exactly 'System Administrator'
    return $this->role && $this->role->role_name === 'System Administrator';
}

public function isGroupAdmin(): bool {
    return $this->role && $this->role->role_name === 'Group Administrator';
}

public function isAdmin(): bool {
    // "Is this user any kind of admin?" — checks both
    return $this->isSystemAdmin() || $this->isGroupAdmin();
}
```

### Permission-Checking Methods (The Most Important Part)

**`canAdminUser()`** — Can this admin manage a specific user?

```php
public function canAdminUser(User $targetUser): bool
{
    // System Admin: YES — can manage ANY user
    if ($this->isSystemAdmin()) {
        return true;
    }

    // Group Admin: Only if the target user is in THEIR groups
    if ($this->isGroupAdmin()) {
        // Get IDs of groups this admin manages: e.g. [2, 4]
        $adminGroupIds = $this->administeredGroups()->pluck('groups.id');
        
        // Is the target user's group in that list?
        return $adminGroupIds->contains($targetUser->group_id);
    }

    // Anyone else: NO
    return false;
}
```

**`canAdminGroup()`** — Can this admin manage a specific group?

```php
public function canAdminGroup(Group $group): bool
{
    // System Admin: YES
    if ($this->isSystemAdmin()) return true;

    // Group Admin: Check if this group is in their assigned list
    if ($this->isGroupAdmin()) {
        return $this->administeredGroups()
            ->where('groups.id', $group->id)
            ->exists();
    }

    return false;
}
```

---

## 6. How Registration Works (The 3-Step Flow)

**Controller:** [app/Http/Controllers/Auth/RegisterController.php](app/Http/Controllers/Auth/RegisterController.php)

The system deliberately uses 3 steps. No user record exists in the database until step 3.

### Step 1: Fill the Form

**What the user sees:** A registration form asking for name, email, and password.

**What happens behind the scenes:**

```
User fills form and clicks "Register"
        │
        ▼
System checks:
  ✓ Name is not empty and under 100 characters
  ✓ Email looks real and isn't already taken
  ✓ Password is at least 8 chars, has uppercase + lowercase + numbers
  ✓ Password confirmation matches
        │
        ▼
System hashes the password (scrambles it so it can't be read)
        │
        ▼
System stores data in a TEMPORARY session (like a sticky note):
  session(['registration_data' => [
      'full_name'    => 'John Doe',
      'email'        => 'john@example.com',
      'password_hash' => '$2y$10$...',  // ALREADY SCRAMBLED
  ]]);
        │
        ▼
User is redirected to Step 2 (the rules page)
```

**Security win:** The password is scrambled before being stored in the session. Even if someone steals the session data, they can't recover the original password.

### Step 2: Read and Accept the Rules

**What the user sees:** The platform rules/terms and a dropdown to pick a student group.

**What happens behind the scenes:** The system checks that step 1 data exists in the session. If not (session expired), redirects back to registration.

### Step 3: Accept and Create Account

**What the user sees:** They click "I Agree."

**What happens behind the scenes:**

```
                    ┌─────────────────────────────────────┐
                    │   DATABASE TRANSACTION               │
                    │   (Both succeed OR both roll back)   │
                    │                                      │
User clicks         │  1. Create the user account:         │
"I Agree" ─────────►     User::create([                    │
                    │       full_name, email, password,    │
                    │       role = "Member",               │
                    │       group = chosen group,          │
                    │       status = 'active'              │
                    │     ])                                │
                    │                                      │
                    │  2. Create onboarding agreement       │
                    │     OnboardingAgreement::create([     │
                    │       user_id, agreed=true,           │
                    │       ip_address, version            │
                    │     ])                                │
                    │                                      │
                    │  3. Auto-promote if first member      │
                    │     (If this is the first person      │
                    │      in a student group, they become  │
                    │      a Group Admin automatically)     │
                    └──────────┬───────────────────────────┘
                               │
                               ▼
                    Clear the session data
                    Log the user in automatically
                    Send a welcome email
                               │
                               ▼
                    User arrives at dashboard
```

**Why a database transaction?** Imagine the user account is created but the agreement record fails (server crash). You'd have a user with no agreement — an inconsistent state. The transaction ensures both are saved as one atomic operation. If either fails, neither is saved.

### What If the User Declines?

They click "I Decline" → session is cleared → no record is created → they're sent back to the registration page. Clean slate.

---

## 7. How Login Works (Web)

**Controller:** [app/Http/Controllers/Auth/LoginController.php](app/Http/Controllers/Auth/LoginController.php)

When a user logs in through the browser, this flow runs:

### The Complete Login Flow

```
User types email + password and clicks "Login"
        │
        ▼
┌── Step 1: Rate Limiter Check ──────────────────────────┐
│  "Has this email been tried too many times?"            │
│                                                         │
│  Two counters are checked:                              │
│    ① Email + IP address together (5 tries max)          │
│    ② Email only (5 tries max, regardless of IP)         │
│                                                         │
│  Why two? To stop attackers who switch IPs to           │
│  bypass the first counter.                              │
│                                                         │
│  If exceeded: "Too many attempts. Try again in X sec."  │
└────────────────────────┬───────────────────────────────┘
                         │ (under limit)
                         ▼
┌── Step 2: Find User ───────────────────────────────────┐
│  Look up user by email in the database                  │
│  User found? If not → "These credentials don't match"  │
└────────────────────────┬───────────────────────────────┘
                         │ (found)
                         ▼
┌── Step 3: Check Password ──────────────────────────────┐
│  Hash::check(password from form, stored hashed password)│
│                                                         │
│  Wrong? → Increment BOTH rate limiters → Show error     │
└────────────────────────┬───────────────────────────────┘
                         │ (correct)
                         ▼
┌── Step 4: Blacklist Gate ──────────────────────────────┐
│  Is the account status 'blacklisted'?                   │
│  Yes → "Your account is suspended. Expires: [date]"     │
│         (NOT logged in)                                  │
└────────────────────────┬───────────────────────────────┘
                         │ (not blacklisted)
                         ▼
┌── Step 5: Warned Gate ─────────────────────────────────┐
│  Is the account status 'warned'?                        │
│  AND is there an unacknowledged warning?                │
│  Yes → They ARE logged in BUT are redirected            │
│         to the warning page first                       │
└────────────────────────┬───────────────────────────────┘
                         │ (no warning issue)
                         ▼
┌── Step 6: Successful Login ────────────────────────────┐
│  ① Auth::login($user) — creates a session              │
│  ② Regenerate session ID — prevents session fixation   │
│  ③ Update last_active_at to now()                      │
│  ④ Clear rate limiters                                 │
│  ⑤ Redirect based on role:                             │
│       System Admin → /admin/dashboard                  │
│       Everyone else → /dashboard                       │
└────────────────────────────────────────────────────────┘
```

### The Dual-Key Rate Limiter Explained Simply

Imagine an attacker trying to guess a password:

- They try `password1`, `password2`, `password3` from IP address `1.1.1.1`
- Counter 1 (`email + IP`) = 3 attempts
- They switch to IP `2.2.2.2` — Counter 1 resets (new IP)
- BUT Counter 2 (`email only`) is still at 3
- After 5 total tries across any IPs, Counter 2 locks them out

**This prevents the common attack of switching IPs to keep guessing forever.**

### Session Regeneration Explained

After login, the system creates a new session ID and destroys the old one. Why? Imagine an attacker gives you a link with a session ID they already know. If you log in with that session, they'd have access to your account. Regenerating the session ID after login prevents this (it's called "session fixation" protection).

---

## 8. How Login Works (API / Desktop Client)

**Controller:** [app/Http/Controllers/Api/AuthController.php](app/Http/Controllers/Api/AuthController.php)

The desktop app doesn't use cookies/sessions. Instead, it uses **tokens**. Think of a token like a hotel key card — you get it when you check in, and you show it every time you want to do something.

### The Difference Between Web and API Auth

| Aspect | Web Login (Browser) | API Login (Desktop App) |
|--------|---------------------|------------------------|
| **How it tracks you** | Session stored in database + cookie in browser | A token string stored on the desktop app |
| **What you send** | Email + password, then a cookie automatically | Email + password to login, then token in header |
| **Response** | Redirect to a new page | JSON: `{"token": "abc123...", "user": {...}}` |
| **Logout** | Destroys the session | Deletes the token |
| **Registration** | 3-step with rules acceptance | One step — register and get token immediately |
| **Account deletion** | Not available in web | Available in API |
| **Token management** | Not applicable | Can list, refresh, and revoke tokens |

### How API Login Works

```
Desktop app sends: POST /api/v1/login  { email, password }
        │
        ▼
Same rate limiter check (5 tries per 30 seconds)
        │
        ▼
Find user, check password
        │
        ▼
Blacklist check:
  If blacklisted → returns JSON: 403 "Account suspended"
        │
        ▼
Warned check:
  If warned + unacknowledged → returns JSON: 403 "Warning acknowledgement required"
        │
        ▼
SUCCESS → Create a Sanctum token:
  $token = $user->createToken('desktop-client')->plainTextToken;
        │
        ▼
Returns JSON: {
  "token": "1|abc123def456...",  ← This is the "key card"
  "user": { id, name, email, role, ... }
}
```

### How the Token Works

Every subsequent request from the desktop app includes:
```
Header: Authorization: Bearer 1|abc123def456...
```

Laravel checks this header, looks up the token in the `personal_access_tokens` table, finds the user, and sets `$request->user()` to that user.

### API Token Management

The API has extra endpoints for managing tokens (since sessions don't exist):

| Action | Endpoint | What Happens |
|--------|----------|-------------|
| **Refresh** | `POST /api/v1/token/refresh` | Old token deleted, new one issued |
| **List tokens** | `GET /api/v1/tokens` | Shows all active tokens with creation dates |
| **Revoke** | `DELETE /api/v1/tokens/{id}` | Deletes a specific token (log out one device) |
| **Logout** | `POST /api/v1/logout` | Deletes the current token |
| **Delete account** | `DELETE /api/v1/account` | Deletes user + all tokens + all data |

---

## 9. Authorization — Who Can Do What

Authorization answers: "Now that you're logged in, what are you allowed to do?"

### The Three Layers of Authorization

The system enforces permissions at three levels, like three security checkpoints:

```
Layer 1: Route Middleware
  ── Runs BEFORE the controller
  ── Checks basic role (Is this user an admin?)
  ── If blocked, the controller never even runs

Layer 2: Controller Checks
  ── Runs INSIDE the controller
  ── "Can this specific admin manage THIS specific user?"
  ── Uses canAdminUser() and canAdminGroup()

Layer 3: Policies
  ── Formal permission gates
  ── Used by some controllers and Blade views
  ── Same logic as Layer 2, just organized differently
```

### Layer 1: Middleware (Route-Level Guards)

#### `IsAdmin` Middleware

**File:** [app/Http/Middleware/IsAdmin.php](app/Http/Middleware/IsAdmin.php)

**What it does:** Checks if the user is ANY kind of admin (System Admin or Group Admin).

```php
if (!auth()->check()) {
    return redirect()->route('login');  // Not logged in? Go to login
}
if (!auth()->user()->isAdmin()) {
    abort(403);  // Logged in but not admin? Forbidden
}
```

**Applied to:** All routes starting with `/admin/...`

#### `IsSystemAdmin` Middleware

**File:** [app/Http/Middleware/IsSystemAdmin.php](app/Http/Middleware/IsSystemAdmin.php)

**What it does:** Checks if the user is specifically a System Administrator.

**Applied to:** Creating users, deleting users, resetting passwords, blacklisting, system config.

#### `CanAdminGroup` Middleware

**File:** [app/Http/Middleware/CanAdminGroup.php](app/Http/Middleware/CanAdminGroup.php)

**What it does:** Checks if the user can manage a SPECIFIC group (looks up the group from the URL).

### Layer 2: Controller Authorization

Even after passing middleware, the controller double-checks permissions using `canAdminUser()`:

```php
// In UserManagementController::show()
public function show($userId)
{
    $user = User::findOrFail($userId);
    
    // 🔒 Double check: Can this admin see THIS specific user?
    if (! $currentUser->canAdminUser($user)) {
        abort(403, 'You do not have permission to view this user');
    }
    
    // ... show the page
}
```

### Permission Summary Table

| Action | System Admin | Group Admin | Regular User |
|--------|:-----------:|:-----------:|:------------:|
| View user list | ✅ All users | ✅ Only their groups | ❌ |
| View user detail | ✅ Any user | ✅ Their groups only | ❌ |
| Create user | ✅ | ❌ | ❌ |
| Edit user (name, email, group) | ✅ Any user | ✅ Their groups only | ❌ |
| Edit user (role, status) | ✅ | ❌ (read-only) | ❌ |
| Delete user | ✅ (not self) | ❌ | ❌ |
| Reset password | ✅ | ❌ | ❌ |
| Blacklist user | ✅ | ❌ | ❌ |
| Lift blacklist | ✅ Any user | ✅ Their groups only | ❌ |
| Change role | ✅ (not last admin) | ❌ | ❌ |
| Resolve warning | ✅ | ❌ | ❌ |

---

## 10. Admin User Management — The Full CRUD

**Controller:** [app/Http/Controllers/Admin/UserManagementController.php](app/Http/Controllers/Admin/UserManagementController.php)

This controller has 14 methods that do everything an admin needs to manage users. Here's each one explained simply.

### 10.1 View User List (`index()`)

**Route:** `GET /admin/users`

**What the admin sees:** A table of users with columns for name, email, role, status, group, and last active. Plus search and filter controls.

**What happens behind the scenes:**

```
1. Start building a database query for users
2. IF the admin is a Group Admin:
     → Only get users whose group is one the admin manages
     → (System Admin: skip this filter, see everyone)
3. Apply any filters from the page:
     → Search text? Look in name OR email
     → Status filter? Only show active/warned/blacklisted
     → Role filter? Only show that role
4. ALSO load the role and group for each user
   (This prevents a performance bug called N+1 — see Section 15)
5. Show 15 users per page (pagination)
```

**Important detail — Group Admin scoping:**
```php
if ($currentUser->isGroupAdmin()) {
    $adminGroupIds = $currentUser->administeredGroups()->pluck('groups.id');
    $query->whereIn('group_id', $adminGroupIds);
}
```

A Group Admin only sees users in the groups they manage. A System Admin sees every user in the system.

### 10.2 View User Detail (`show()`)

**Route:** `GET /admin/users/{id}`

**What the admin sees:** A profile page with:
- User's name, email, status badge, role badge
- Account details (role, group, status, email verified, last active, join date)
- Action buttons (Edit, Lift Blacklist, Change Role)
- History tables for warnings, blacklists, and onboarding agreements

**What happens behind the scenes:**

```
1. Load the user with their role and group
2. 🔒 Check: canAdminUser(targetUser)?
     → System Admin: always yes
     → Group Admin: only if target user is in their groups
     → Anyone else: 403
3. Load the user's warnings, blacklist records, and agreements
4. Send all this data to the view
```

### 10.3 Create User (`create()` + `store()`)

**Routes:** `GET /admin/users/create` → form | `POST /admin/users` → save

**Only System Admin can do this.** The route has `system-admin` middleware.

**The form asks for:** Full name, email, password (with confirmation), role, group.

**Validation rules:**
- Name: required, max 100 characters
- Email: required, must be valid format, must not already exist
- Password: required, confirmed (type twice), min 8 chars, must have uppercase + lowercase + numbers
- Role: must be a real role from the database
- Group: must be a real group from the database

**After saving:**
1. User is created with `account_status = 'active'`
2. If this is the first member of a student group, they're auto-promoted to Group Admin
3. An audit log entry is created
4. Redirect to the new user's detail page

### 10.4 Edit User (`edit()` + `update()`)

**Routes:** `GET /admin/users/{id}/edit` → form | `PUT /admin/users/{id}` → save

**The form CHANGES based on who's editing:**

| Form Field | System Admin Editing | Group Admin Editing |
|-----------|---------------------|---------------------|
| Full Name | Editable text box | Editable text box |
| Email | Editable text box | Editable text box |
| Group | Editable dropdown | Editable dropdown |
| Role | Editable dropdown | **Read-only** (disabled, can't change) |
| Account Status | Editable dropdown | **Read-only** (disabled, can't change) |
| Delete button | Shown (unless editing self) | **Hidden** |

**Why?** Group Admins have limited permissions. They can change name, email, and group — but not role or status. Those require System Admin.

**Last-Admin Protection (important safety check):**

```php
// Before saving, check: is the target user THE LAST System Admin?
if ($user->role_id === $adminRole->id && $validated['role_id'] != $adminRole->id) {
    $adminCount = User::where('role_id', $adminRole->id)->count();
    if ($adminCount === 1) {
        // BLOCK IT — can't have zero admins!
        return redirect()->back()->with('error', 
            'Cannot downgrade the last Administrator account');
    }
}
```

**Why this exists:** If there's only one System Admin and you change their role to something else, nobody would have full admin access anymore. You'd lock yourself out of the system.

### 10.5 Delete User (`destroy()`)

**Route:** `DELETE /admin/users/{id}`

**Only System Admin. And you cannot delete yourself.**

```php
public function destroy($userId)
{
    if (! auth()->user()->isSystemAdmin()) {
        abort(403);
    }
    
    $user = User::findOrFail($userId);
    
    if ($user->id === auth()->id()) {
        return redirect()->back()->with('error', 'You cannot delete your own account');
    }
    
    // Log the deletion first
    $this->auditLogService->logUserDeleted($user);
    
    // Hard delete (completely removes from database)
    $user->forceDelete();
    
    // Database cascade deletes their warnings, blacklists, tokens, agreements too
    
    return redirect()->route('admin.users.index')
        ->with('success', 'User deleted successfully');
}
```

**Note:** This is a HARD delete. The user is gone permanently. Their warnings, blacklist records, email tokens, and onboarding agreements are all deleted too (database cascade).

### 10.6 Reset Password (`showResetPassword()` + `resetPassword()`)

**Routes:** `GET /admin/users/{id}/reset-password` → form | `POST /admin/users/{id}/reset-password` → save

**Only System Admin.** Same password rules as registration.

```php
$validated = $request->validate([
    'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
]);

// The 'hashed' cast auto-hashes the password
$user->update(['password' => $validated['password']]);
```

**The `hashed` cast** means `$user->update(['password' => 'newpass'])` automatically runs `Hash::make('newpass')` before saving to the database. The controller doesn't need to hash it manually.

### 10.7 Blacklist (`showBlacklist()` + `blacklist()`)

**Routes:** `GET /admin/users/{id}/blacklist` → form | `POST /admin/users/{id}/blacklist` → save

**Only System Admin.**

**The form asks for:**
- Reason (required) — why are they being blacklisted?
- Duration in days (optional) — how long? Empty = permanent

```php
// If duration given, calculate expiry
$expiresAt = null;
if (!empty($validated['duration_days'])) {
    $expiresAt = now()->addDays($validated['duration_days']);
}

// Create the blacklist record
BlacklistRecord::create([
    'user_id' => $user->id,
    'reason' => $validated['reason'],
    'expires_at' => $expiresAt,       // null = permanent
    'blacklisted_at' => now(),
]);

// Change the user's status
$user->update(['account_status' => 'blacklisted']);
```

### 10.8 Lift Blacklist (`liftBlacklist()`)

**Route:** `POST /admin/users/{id}/lift-blacklist`

**Available to:** Any admin who `canAdminUser()` the target user.

```php
// Find the active blacklist record (where lifted_at is null)
$blacklistRecord = BlacklistRecord::where('user_id', $userId)
    ->whereNull('lifted_at')
    ->first();

if ($blacklistRecord) {
    // Mark it as lifted
    $blacklistRecord->update([
        'lifted_at' => now(),
        'lifted_by' => Auth::id(),
    ]);
}

// Restore user to active
$user->update(['account_status' => 'active']);
```

### 10.9 Change Role (`changeRole()`)

**Route:** `POST /admin/users/{id}/change-role`

**Only System Admin.** And can't downgrade the last admin (same protection as edit).

### 10.10 Resolve Warning (`resolveWarning()`)

**Route:** `POST /admin/warnings/{warningId}/resolve`

**Only System Admin.**

```php
// Mark the warning as resolved
$warning->update([
    'is_resolved' => true,
    'resolved_at' => now(),
]);

// Check if user has any remaining unresolved warnings
$unresolvedCount = $user->warnings()->where('is_resolved', false)->count();

// If NO warnings remain AND status is 'warned', auto-reactivate
if ($unresolvedCount === 0 && $user->account_status === 'warned') {
    $user->update(['account_status' => 'active']);
}
```

**Auto-reactivation:** If an admin resolves the LAST warning, the user's status automatically goes back to 'active'. No separate step needed.

---

## 11. Account Lifecycle — Warnings and Blacklisting

**Command:** [app/Console/Commands/MonitorMemberActivity.php](app/Console/Commands/MonitorMemberActivity.php)  
**Run via:** `php artisan monitor:activity`

This is an automated system (like a robot guard) that checks user activity and escalates punishments.

### The 3-Step Escalation Ladder

```
User signed up and was active ──► Then stopped using the platform
                                        │
                               (inactivity_days > threshold)
                                        │
                                        ▼
              ┌─ WARNING 1 ──────────────────────────────────┐
              │ "You've been inactive. Please respond within │
              │  7 days, or you'll get a second warning."    │
              │ account_status → 'warned'                    │
              └─────────────────────┬────────────────────────┘
                                    │
                    ┌───────────────┴───────────────┐
                    │ Did user respond?              │
                    └───────────────┬───────────────┘
                  No                │            Yes
                  ▼                 │            ▼
   ┌─ WARNING 2 ────────────────────┘      Warning stays
   │ "You still haven't responded.         but acknowledged.
   │  Respond in 7 days or be banned."     No escalation.
   └─────────────────┬────────────────────┘
                      │
          ┌───────────┴───────────┐
          │ Did user respond?     │
          └───────────┬───────────┘
        No            │           Yes
        ▼             │           ▼
┌─ BLACKLISTED ───────┘     Warning resolved
│ Account suspended          by admin manually
│ for 90 days (default)
│ Cannot log in at all
└─────────────────────────────
```

### How the Monitor Command Works

Think of it as a night watchman that walks through the system periodically:

```
For EACH user whose status is 'active' or 'warned':

    Step 1: Calculate how many days since they last logged in
            daysSinceLastActivity = today - last_active_at

    Step 2: Is that more than the threshold?
            threshold = system_config['inactivity_warning_days'] (default: 30 days)

    Step 3: If yes, check their warnings:
    
            Case A: No warnings yet
              → Issue Warning 1 (deadline: 7 days to respond)
              → Change status to 'warned'
            
            Case B: Has Warning 1 AND deadline passed
              → Issue Warning 2 (deadline: 7 more days)
            
            Case C: Has Warning 2 AND deadline passed
              → Blacklist the user (ban for 90 days by default)
```

**The `--dry-run` flag:** Running `php artisan monitor:activity --dry-run` shows what WOULD happen without actually making changes. Like a rehearsal before the real thing.

### Where the Thresholds Come From

The thresholds are stored in the `system_configs` database table and cached so the system doesn't query the database every time:

| Config Key | Default | What It Controls |
|-----------|---------|-----------------|
| `inactivity_warning_days` | 30 | How many days of inactivity before Warning 1 |
| `warning_response_days` | 7 | How many days to respond to each warning |
| `blacklist_duration_days` | 90 | How long a blacklist lasts before auto-expiry |

Admins can change these values through the System Configuration page.

---

## 12. Email Verification

**Controller:** [app/Http/Controllers/Auth/EmailVerificationController.php](app/Http/Controllers/Auth/EmailVerificationController.php)

### The Flow

```
User registers ──► Welcome email sent with verification link
                         │
                         ▼
User clicks link: /verify-email?token=abc123&email=user@example.com
                         │
                         ▼
System checks:
  1. Does this token exist in the database?
  2. Does it match this email?
  3. Has it expired? (24 hour limit)
                         │
                  ┌──────┴──────┐
                  │              │
              All good        Invalid
                  │              │
                  ▼              ▼
      Set email_verified_at    Show error:
      = now()                  "Link expired or invalid"
      Delete token
      (one-time use)
```

### Resending the Verification Email

If the link expires, the user can click "Resend." But they can only do this once per 60 seconds (rate limited to prevent spam).

```php
$key = 'verify-email:'.$request->user()->email;
if (RateLimiter::tooManyAttempts($key, 1)) {
    // "Please wait X seconds before resending"
}

// Generate a brand new random token
$token = EmailVerificationToken::create([
    'user_id' => $user->id,
    'email' => $user->email,
    'token' => Str::random(64),
    'expires_at' => now()->addHours(24),
]);

// Queue the email (sent in background, doesn't slow down the response)
Mail::to($user->email)->queue(new VerifyEmailMailable(...));
```

---

## 13. Password Management

**Controller:** [app/Http/Controllers/Auth/PasswordController.php](app/Http/Controllers/Auth/PasswordController.php)

### Three Password Features

#### 1. Forgot Password

```
User clicks "Forgot Password"
  → Enters email
  → System sends an email with a reset link
  → User clicks link, enters new password
  → Password is changed
  → User is automatically logged in
```

This uses Laravel's built-in password broker. The system generates a token, stores it in the `password_reset_tokens` table, and emails the user. The token is one-time use.

#### 2. Change Password (while logged in)

```
User goes to "Change Password" page
  → Enters current password (verified against database)
  → Enters new password (must be different from current)
  → Confirms new password
  → Password is updated
```

**The current password check:**
```php
'current_password' => ['required', function ($attribute, $value, $fail) {
    if (!Hash::check($value, Auth::user()->password)) {
        $fail('Current password is incorrect.');
    }
}],
```

#### 3. Password Strength Calculator

The system scores passwords and labels them:

| Score | Label | Example |
|-------|-------|---------|
| 0-2 | Weak | `abc123` |
| 3-4 | Fair | `MyDog2023` |
| 5-6 | Good | `MyDog2023!` |
| 7+ | Strong | `MyD0g!sL0v3ly2023` |

Points are awarded for: length (8+, 12+, 16+), lowercase, uppercase, numbers, and special characters.

---

## 14. File Connection Map — How Everything Connects

### The Journey of One Request: "Admin Edits a User"

Let's trace what happens when a System Admin clicks "Edit" on a user:

```
Step 1: BROWSER
  Admin clicks "Edit" on User #5
  URL: /admin/users/5/edit

Step 2: ROUTE (routes/web.php)
  Matches: Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])
  → Named route: admin.users.edit
  → This route is inside the 'admin' prefix group
     which has ['auth', 'admin'] middleware

Step 3: MIDDLEWARE CHECKPOINT 1 — 'auth'
  "Is this user logged in?"
  Checks the session (cookie stored in browser)
  → No session? Redirect to login page

Step 4: MIDDLEWARE CHECKPOINT 2 — 'admin' (IsAdmin.php)
  "Is this user an admin of any kind?"
  Calls auth()->user()->isAdmin()
  → Checks if role is 'System Administrator' or 'Group Administrator'
  → No? 403 Forbidden

Step 5: CONTROLLER — edit($userId)
  Load User #5 from database
  "Can this admin actually edit THIS specific user?"
  Calls $currentUser->canAdminUser($user)
    → System Admin: yes, always
    → Group Admin: only if User #5 is in their managed groups
  → No? 403 Forbidden

Step 6: CONTROLLER loads form data
  Load all roles (for the dropdown)
  Load all non-deleted groups (for the dropdown)

Step 7: VIEW (resources/views/admin/users/edit.blade.php)
  Renders the HTML form
  Checks auth()->user()->isSystemAdmin() to decide:
    → Role field: editable SELECT or disabled TEXT
    → Status field: editable SELECT or disabled TEXT
    → Delete button: show or hide

Step 8: BROWSER
  Admin changes the name and clicks "Save"
  Form submits as PUT to /admin/users/5

Step 9: SAME middleware chain again (auth → admin)

Step 10: CONTROLLER — update()
  Same canAdminUser() check again
  Validate based on admin role:
    → System Admin: validates name, email, group, role, status
    → Group Admin: validates only name, email, group
  Last-Admin protection check
  Save to database
  Log the change (audit trail)
  Redirect to user detail page ✅
```

### All the Files Involved in User Management

```
ROUTES FILE
  routes/web.php ─────── Defines all the URLs and which controller handles them
        │
        ├── MIDDLEWARE (run before controllers)
        │     ├── app/Http/Middleware/IsAdmin.php
        │     │     Checks: Is this user any kind of admin?
        │     │     Uses: User::isAdmin()
        │     │
        │     ├── app/Http/Middleware/IsSystemAdmin.php
        │     │     Checks: Is this specifically a System Admin?
        │     │     Uses: User::isSystemAdmin()
        │     │
        │     └── app/Http/Middleware/CanAdminGroup.php
        │           Checks: Can this admin manage THIS group?
        │           Uses: User::canAdminGroup()
        │
        ├── CONTROLLERS (the logic)
        │     ├── Admin/UserManagementController.php
        │     │     → 14 methods: index, show, create, store, edit,
        │     │       update, destroy, resetPassword, blacklist,
        │     │       liftBlacklist, changeRole, resolveWarning
        │     │
        │     ├── Auth/LoginController.php
        │     │     → Handles web login (session-based)
        │     │     → Rate limiting, blacklist gate, warned gate
        │     │
        │     ├── Auth/RegisterController.php
        │     │     → 3-step registration flow
        │     │     → Session storage between steps
        │     │     → DB transaction for atomic creation
        │     │
        │     ├── Auth/EmailVerificationController.php
        │     │     → Token-based email verification
        │     │
        │     ├── Auth/PasswordController.php
        │     │     → Forgot/reset/change password
        │     │
        │     └── Api/AuthController.php
        │           → API login/register using tokens
        │           → Token management (refresh, list, revoke)
        │
        ├── MODELS (the data layer)
        │     ├── User.php ─── The central user blueprint
        │     │     → Relationships, permission checks, role helpers
        │     │
        │     ├── Role.php ─── Just has role_name and description
        │     ├── Group.php ─── Group with SoftDeletes, admin pivot
        │     ├── Warning.php ─── Warning records for escalation
        │     ├── BlacklistRecord.php ─── Blacklist records
        │     ├── OnboardingAgreement.php ─── Rules acceptance records
        │     ├── EmailVerificationToken.php ─── Email verification
        │     └── SystemConfig.php ─── Config values with caching
        │
        ├── POLICIES (formal permission rules)
        │     ├── UserPolicy.php ─── What can be done to users
        │     └── GroupPolicy.php ─── What can be done to groups
        │
        ├── SERVICES
        │     └── AuditLogService.php ─── Logs every admin action
        │
        └── VIEWS (the HTML/templates)
              resources/views/admin/users/
                ├── index.blade.php ─── User list table
                ├── show.blade.php ─── User detail page
                ├── create.blade.php ─── Create user form
                ├── edit.blade.php ─── Edit user form (role-aware)
                ├── reset-password.blade.php ─── Password reset form
                └── blacklist.blade.php ─── Blacklist form
```

---

## 15. Code Explanations for Your Presentation

Here are the key pieces of code you'll want to explain. Each one has the code, a plain-English explanation, and a "why it matters."

### 15.1 `canAdminUser()` — The Core Permission Check

**Where:** `app/Models/User.php`, line 235

```php
public function canAdminUser(User $targetUser): bool
{
    // System Admins can admin ALL users — immediate pass
    if ($this->isSystemAdmin()) {
        return true;
    }

    // Group Admins can only admin users in THEIR groups
    if ($this->isGroupAdmin()) {
        // Get IDs of all groups this admin manages
        // administeredGroups() queries the group_admins pivot table
        $adminGroupIds = $this->administeredGroups()->pluck('groups.id');
        
        // Check if the target user's group is in that list
        return $adminGroupIds->contains($targetUser->group_id);
    }

    // Everyone else: NOT an admin
    return false;
}
```

**Plain English:** "Can you manage this person?" If you're a System Admin, yes. If you're a Group Admin, only if they're in one of your groups. If you're neither, no.

**Why it matters for your presentation:** This single method is the security gate for almost every admin action. It's called in:
- Viewing a user detail → `show()`
- Editing a user → `edit()` and `update()`
- Changing a role → `changeRole()`
- Lifting a blacklist → `liftBlacklist()`
- All policy checks in `UserPolicy`

Without this, a Group Admin could manage users in other groups, which would break the security model.

---

### 15.2 The Dual-Key Rate Limiter

**Where:** `app/Http/Controllers/Auth/LoginController.php`, line 30

```php
// Key 1: email + IP together (stops brute force from one computer)
$key = 'login-attempts:'.$email.'|'.$request->ip();

// Key 2: email only (stops attackers switching IPs)
$emailKey = 'login-attempts-email:'.$email;

$maxAttempts = 5;
$lockoutSeconds = 30;

// Check BOTH limits
if (RateLimiter::tooManyAttempts($key, $maxAttempts) || 
    RateLimiter::tooManyAttempts($emailKey, $maxAttempts)) {
    // Locked out!
}

// On wrong password: increment BOTH counters
RateLimiter::hit($key, $lockoutSeconds);
RateLimiter::hit($emailKey, $lockoutSeconds);

// On correct password: clear BOTH counters
RateLimiter::clear($key);
RateLimiter::clear($emailKey);
```

**Plain English:** Two gates. The first gate counts tries from one computer. The second gate counts tries from any computer for that email. So switching computers doesn't reset the counter. You get 5 total tries across any device in 30 seconds.

**Why this matters:** Without the email-only key (Key 2), an attacker could try 5 passwords from IP 1, then 5 more from IP 2, then 5 from IP 3 — and never hit a limit. The email-only key prevents this.

---

### 15.3 The 3-Step Registration Transaction

**Where:** `app/Http/Controllers/Auth/RegisterController.php`, line 116

```php
$user = DB::transaction(function () use ($validated, $memberRole) {
    // Step A: Create the user account
    $user = User::create([
        'full_name' => $validated['full_name'],
        'email' => $validated['email'],
        'password' => $validated['password_hash'],  // Already hashed!
        'role_id' => $memberRole->id,
        'group_id' => $group->id,
        'account_status' => 'active',
    ]);
    
    // Step B: Create the onboarding agreement record
    OnboardingAgreement::create([
        'user_id' => $user->id,
        'agreed' => true,
        'agreement_version' => '1.0',
        'ip_address' => request()->ip(),
    ]);
    
    // Step C: If first member, auto-promote to Group Admin
    $group->autoPromoteFirstStudent($user);
    
    return $user;
});  // ← BOTH succeed OR both roll back
```

**Plain English:** Creating a user and creating their agreement are wrapped in a single atomic operation. If the server crashes between step A and step B, step A is rolled back too. No orphan users.

**Why 3 steps (not 1)?** Because the user must read AND ACCEPT the rules before creating an account. If we created the account on step 1 and the user declined on step 2, we'd have an orphan account. By waiting until step 3, we ensure the account is only created AFTER the user accepts.

**Why hash the password in the session?** The password is scrambled before being stored in the session data. If someone gains access to the server's session storage, they get a scrambled password, not the original.

---

### 15.4 The Last-Admin Protection

**Where:** `app/Http/Controllers/Admin/UserManagementController.php`, line 221

```php
// Are we trying to change this user's role away from System Admin?
if ($user->role_id === $adminRole->id && $validated['role_id'] != $adminRole->id) {
    // How many System Admins are left?
    $adminCount = User::where('role_id', $adminRole->id)->count();
    
    // If this is the LAST one...
    if ($adminCount === 1) {
        return redirect()->back()->with('error', 
            'Cannot downgrade the last Administrator account');
    }
}
```

**Plain English:** If there's only one System Administrator in the whole system, you can't change their role. Otherwise, nobody would have full admin access. No one would be able to manage users, change settings, or fix anything.

---

### 15.5 The N+1 Query Problem

**The problem (BAD code):**
```php
$users = User::all();               // 1 query: SELECT * FROM users
foreach ($users as $user) {
    echo $user->role->role_name;    // 1 NEW query per user: SELECT * FROM roles WHERE id = ?
}
// Total: 1 + 15 = 16 queries for just 15 users!
```

**The fix (eager loading):**
```php
$users = User::with(['role', 'group'])->paginate(15);  // 3 queries total
foreach ($users as $user) {
    echo $user->role->role_name;    // Already loaded — no new query!
}
// Total: 3 queries (users + roles + groups)
```

**Plain English:** Without `with(['role', 'group'])`, every time you access `$user->role`, Laravel runs a new database query. So for 15 users on a page, you run 1 query to get users + 15 queries for roles + 15 for groups = 31 queries. With eager loading, it's just 3 queries total. This is why `UserManagementController::index()` uses `with(['role', 'group'])`.

---

### 15.6 The Hashed Cast (Auto Password Hashing)

**Where:** `app/Models/User.php`, line 65

```php
protected function casts(): array
{
    return [
        'password' => 'hashed',  // ← THIS ONE LINE
    ];
}
```

**What it does:** Whenever you set `$user->password = 'something'`, Laravel automatically runs `Hash::make('something')` before saving to the database.

**Without it, every controller would need:**
```php
$user->update(['password' => Hash::make($validated['password'])]);
```

**With it, controllers just write:**
```php
$user->update(['password' => $validated['password']]);
// Laravel auto-hashes it!
```

This is used in admin password reset, user change password, API registration, and everywhere else. Consistent security across the whole system.

---

### 15.7 Session vs Token Authentication

**Session (Web browser):**
```
Browser logs in ──► Server creates a session ──► Session ID stored in cookie
                                                        │
Browser makes request ──► Cookie sent automatically ──► Server looks up session
                                                          │
                                                          ▼
                                                        Finds user data in sessions table
                                                        Sets Auth::user()
```

**Token (Desktop app via API):**
```
Desktop app logs in ──► Server creates a token ──► Returns: "1|abc123..."
                                                          │
Desktop app makes request ──► Sends header:               │
  Authorization: Bearer 1|abc123...            ──► Server looks up token
                                                          │
                                                          ▼
                                                        Finds token in personal_access_tokens
                                                        Finds associated user
                                                        Sets $request->user()
```

**Key differences to explain:**
1. **Sessions** are stored on the server and identified by a cookie. The server remembers you.
2. **Tokens** are stored on the client (desktop app). The server just verifies the token is valid.
3. **Sessions** are browser-only (cookies don't work well in desktop apps).
4. **Tokens** can be created, listed, and revoked independently (you can log out one device without affecting others).
5. The **same users table** works for both — it's just the authentication mechanism that differs.

---

> **End of User Management Module Guide**
>
> Cross-references: See [DOCUMENTATION.md](DOCUMENTATION.md) for full project docs,
> [UserManagementController.php](../app/Http/Controllers/Admin/UserManagementController.php) for the source code,
> [User.php](../app/Models/User.php) for the model with all authorization helpers.
