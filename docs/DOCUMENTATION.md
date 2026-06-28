# Smart Discussion Forum — Comprehensive Documentation

> **Project:** Smart-Discussion-Forum-G23  
> **Framework:** Laravel 13.16.1 | PHP 8.4.22 | PHPUnit 12  
> **Last Updated:** June 2026  
> **Test Suite:** 144 tests, 397 assertions, 0 failures

---

## Table of Contents

- [System Overview](#system-overview)
- [Chapter 1: Laravel Concepts Involved](#chapter-1-laravel-concepts-involved)
  - [1.1 MVC Architecture](#11-mvc-architecture)
  - [1.2 Eloquent Relationships](#12-eloquent-relationships)
  - [1.3 Database Migrations](#13-database-migrations)
  - [1.4 Middleware Pipeline](#14-middleware-pipeline)
  - [1.5 Laravel Sanctum (API Authentication)](#15-laravel-sanctum-api-authentication)
  - [1.6 Rate Limiting](#16-rate-limiting)
  - [1.7 Caching (SystemConfig Pattern)](#17-caching-systemconfig-pattern)
  - [1.8 Blade Layout Inheritance](#18-blade-layout-inheritance)
  - [1.9 Artisan Commands](#19-artisan-commands)
  - [1.10 Database Seeding](#110-database-seeding)
- [Chapter 2: Authentication Features](#chapter-2-authentication-features)
  - [2.1 Registration — The 3-Step Onboarding Flow](#21-registration--the-3-step-onboarding-flow)
  - [2.2 Login (Web)](#22-login-web)
  - [2.3 Password Hashing](#23-password-hashing)
  - [2.4 Session Management](#24-session-management)
  - [2.5 Password Reset (Forgot Password)](#25-password-reset-forgot-password)
  - [2.6 Change Password (Authenticated Users)](#26-change-password-authenticated-users)
  - [2.7 Email Verification](#27-email-verification)
  - [2.8 Warning Acknowledgement](#28-warning-acknowledgement)
  - [2.9 Three-Step Warning Escalation (Automated)](#29-three-step-warning-escalation-automated)
- [Chapter 3: Authorization Features](#chapter-3-authorization-features)
  - [3.1 Role Hierarchy](#31-role-hierarchy)
  - [3.2 Middleware-Based Authorization (Three Layers)](#32-middleware-based-authorization-three-layers)
  - [3.3 Policy-Based Authorization (GroupPolicy)](#33-policy-based-authorization-grouppolicy)
  - [3.4 Complete Access Matrix](#34-complete-access-matrix)
  - [3.5 API Authorization](#35-api-authorization)
- [Chapter 4: Testing Files and Test Cases](#chapter-4-testing-files-and-test-cases)
  - [4.1 Test Infrastructure](#41-test-infrastructure)
  - [4.2 Web Login Tests (14 tests)](#42-web-login-tests-14-tests)
  - [4.3 Registration & Onboarding Tests (15 tests)](#43-registration--onboarding-tests-15-tests)
  - [4.4 Admin Access Control Tests (17 tests)](#44-admin-access-control-tests-17-tests)
  - [4.5 User Management Tests (25 tests)](#45-user-management-tests-25-tests)
  - [4.6 Group Management Tests (12 tests)](#46-group-management-tests-12-tests)
  - [4.7 Monitor Member Activity Tests (8 tests)](#47-monitor-member-activity-tests-8-tests)
  - [4.8 API Login Tests (12 tests)](#48-api-login-tests-12-tests)
  - [4.9 API Registration Tests (11 tests)](#49-api-registration-tests-11-tests)
  - [4.10 API Token Management Tests (13 tests)](#410-api-token-management-tests-13-tests)
  - [4.11 API Email Verification Tests (12 tests)](#411-api-email-verification-tests-12-tests)
  - [4.12 API Password Controller Tests (18 tests)](#412-api-password-controller-tests-18-tests)
- [Chapter 5: Admin Features](#chapter-5-admin-features)
  - [5.1 User Management](#51-user-management)
  - [5.2 Group Management](#52-group-management)
  - [5.3 System Configuration](#53-system-configuration)
  - [5.4 Admin Dashboard Views](#54-admin-dashboard-views)
- [Chapter 6: Underlying Issues](#chapter-6-underlying-issues)

---

## System Overview

### What Is the Smart Discussion Forum?

The Smart Discussion Forum is a web-based academic platform built with Laravel. It serves two interfaces: a browser-based web application for students and administrators, and a RESTful API (versioned under `/api/v1/`) for a companion desktop client. Both interfaces share the same user database but use different authentication mechanisms — sessions for the web, Laravel Sanctum tokens for the API.

### Core Purpose

The platform provides a structured environment for academic discussions organised into groups. Administrators manage users and groups, monitor activity, and enforce engagement through an automated inactivity warning system. The system enforces a registration onboarding flow, email verification, and a 3-step escalation pipeline (Warning 1 → Warning 2 → Blacklist) for inactive members.

### User Roles

The system defines four roles arranged in a hierarchy:

- **System Administrator** — Full system-wide access: manage all users, all groups, system configuration, and IP whitelists.
- **Group Administrator** — Can view and manage only the groups assigned to them via a pivot table (`group_admins`). Can edit groups, manage members, and view users within their scope.
- **Student** — Default role assigned during registration. Regular user with access to discussions and profile features.
- **Member** — Restricted access to discussion features only.

### Account Lifecycle

Every user account progresses through a lifecycle governed by three status states stored in the `account_status` column of the `users` table:

1. **active** — The default state after registration. The user can log in and use all features.
2. **warned** — Assigned by the automated `MonitorMemberActivity` command when inactivity exceeds the configured threshold. The user can still log in but must acknowledge a warning before proceeding.
3. **blacklisted** — Assigned after the 3-step escalation completes (Warning 1 → Warning 2 → Blacklist). The user is blocked from logging in until an admin lifts the blacklist or the ban expires.

### Registration Flow

Registration uses a deliberate 3-step process:

1. The user fills out a registration form (name, email, password).
2. Validated data is stored in the session (no database write yet).
3. The user must read and accept the platform rules on an onboarding page. Only then is the user record created, assigned the `Student` role and `Default Group`, and auto-logged in.

Declining the rules discards the session data — no account is created.

### Authentication — Two Parallel Systems

**Web (session-based).** Users log in via a form at `/login`. Laravel creates a session stored in the `sessions` database table. A "Remember Me" checkbox enables persistent login via an encrypted cookie. Session IDs are regenerated on login to prevent session fixation.

**API (token-based).** The desktop client authenticates via `POST /api/v1/login` and receives a Sanctum bearer token. Tokens are stored in the `personal_access_tokens` table and sent in the `Authorization` header on subsequent requests. Logout deletes the token.

Both systems enforce the same blacklist and warned gates — blacklisted users are blocked entirely, and warned users with unacknowledged warnings are redirected (web) or receive a 403 response (API).

### Authorization — Layered Access Control

Access control is enforced at three levels:

1. **Route middleware** — `auth`, `admin`, `system-admin`, and `can-admin-group` middleware in `bootstrap/app.php` and `routes/web.php` block unauthorised requests before they reach the controller.
2. **Policy checks** — The `GroupPolicy` at `app/Policies/GroupPolicy.php` governs who can create, edit, delete, and manage members of each group. Controllers invoke these via `Gate::allows()`.
3. **Model helpers** — Methods on the `User` model (`isSystemAdmin()`, `isGroupAdmin()`, `isAdmin()`, `canAdminGroup()`, `canAdminUser()`) provide reusable authorization logic that both middleware and policies delegate to.

A Group Admin editing a group they don't manage, for example, passes through `auth` (logged in) → `admin` (is admin) → `can-admin-group` (can't admin this specific group → 403).

### Inactivity Monitoring

The `php artisan monitor:activity` command (`app/Console/Commands/MonitorMemberActivity.php`) scans all active and warned users, calculates days since last activity, and applies a 3-step escalation:

- **No warnings** → Issue Warning 1 (deadline: configurable days)
- **Warning 1 expired** → Issue Warning 2 (deadline: configurable days)
- **Warning 2 expired** → Blacklist the user (duration: configurable days)

Thresholds are stored in the `system_configs` table and cached via the `SystemConfig::getValue()` pattern. The command supports a `--dry-run` flag for previewing changes without writing to the database.

### Key Database Tables

| Table | Purpose |
|-------|---------|
| `roles` | Defines the four user roles |
| `groups` | Discussion groups (soft-deletable) |
| `users` | User accounts with role, group, and status |
| `group_admins` | Pivot table linking Group Admins to their groups |
| `warnings` | Tracks the 3-step warning escalation per user |
| `blacklist_records` | Records user bans with expiry and lift tracking |
| `onboarding_agreements` | Records platform rules acceptance during registration |
| `email_verification_tokens` | One-time tokens for email verification |
| `password_reset_tokens` | Tokens for the forgot-password flow |
| `sessions` | Server-side session storage for web authentication |
| `personal_access_tokens` | Sanctum tokens for API authentication |
| `system_configs` | Key-value store for runtime configuration |

### Project Structure at a Glance

| Directory | Contents |
|-----------|----------|
| `app/Models/` | 8 Eloquent models: User, Role, Group, Warning, BlacklistRecord, OnboardingAgreement, EmailVerificationToken, SystemConfig |
| `app/Http/Controllers/Auth/` | 6 controllers: Login, Register, Password, EmailVerification, WarningAcknowledgement, Profile |
| `app/Http/Controllers/Admin/` | 3 controllers: UserManagement, GroupController, SystemConfigController |
| `app/Http/Controllers/Api/` | API controllers for the desktop client (AuthController, UserController, ProfileController, PasswordController, EmailVerificationController) |
| `app/Http/Middleware/` | 5 custom middleware: IsAdmin, IsSystemAdmin, IsGroupAdmin, CanAdminGroup, IpWhitelist |
| `app/Policies/` | GroupPolicy for group-level authorization |
| `app/Console/Commands/` | MonitorMemberActivity artisan command |
| `routes/` | `web.php` (296 lines, all web routes), `api.php` (201 lines, all API routes) |
| `database/migrations/` | 10 migration files defining the schema |
| `database/seeders/` | RoleSeeder (4 roles) |
| `tests/Feature/` | 12 test files covering web, admin, API, and console features |
| `resources/views/admin/users/` | 6 Blade templates: index, show, create, edit, reset-password, blacklist |
| `bootstrap/app.php` | Middleware registration, route configuration, exception handling |

### Test Suite

The project has **144 tests** with **397 assertions** and **0 failures**. Tests use PHPUnit 12 with the `RefreshDatabase` trait (SQLite `:memory:`) for complete database isolation. A shared `CreatesTestUsers` trait at `tests/CreatesTestUsers.php` provides helper methods to seed roles, groups, and users for testing. Test files are organised into `Web/` (login, registration), `Admin/` (user management, group management, access control), `Api/` (auth, tokens, email verification, passwords), and `Console/` (activity monitoring).

---

# Chapter 1: Laravel Concepts Involved

## 1.1 MVC Architecture

Laravel organises every feature around three layers — Model, View, and Controller — and this project is no exception.

**Model layer.** The `User` model lives in `app/Models/User.php` (lines 16–48). It extends Laravel's built-in `Authenticatable` class, which is what plugs it into the authentication system. Three traits are composed into the class: `HasFactory` (for test/database seeding factories), `Notifiable` (for sending notifications like emails), and `HasApiTokens` (for Laravel Sanctum token-based API authentication). The `$fillable` array on lines 21–30 acts as a whitelist that controls which columns can be mass-assigned via `User::create()` or `$user->update()` — anything not listed is silently ignored, which is a security measure against mass-assignment vulnerability. The `$hidden` array on lines 32–35 ensures `password` and `remember_token` never appear when the model is serialised to JSON (e.g., in API responses). The `casts()` method on lines 41–48 tells Laravel to automatically convert `email_verified_at` and `last_active_at` into Carbon date objects whenever they are read, and to automatically run `Hash::make()` on any value assigned to the `password` attribute (the `'hashed'` cast on line 45).

**Controller layer.** The `LoginController` at `app/Http/Controllers/Auth/LoginController.php` (lines 28–112) demonstrates the controller's role. Its `authenticate()` method receives the HTTP `Request` object, performs rate-limit checking, validates input, queries the database for the user, verifies the password, evaluates business-logic gates (blacklist and warned status), and finally creates the session. The controller orchestrates the flow but delegates data operations to models and presentation to views.

**View layer.** Blade templates such as `resources/views/auth/login.blade.php` handle presentation. They use `@extends('layouts.guest')` for layout inheritance and `@csrf` for CSRF token injection. Data flows from controllers to views via the `view()` helper's second parameter.

**How they connect.** A browser submits a login form (View) via POST to a route that maps to `LoginController::authenticate()` (Controller). The controller queries `User::where(...)` (Model), verifies the password, creates a session, and returns a redirect to the dashboard view.

---

## 1.2 Eloquent Relationships

The application uses four types of Eloquent relationships to connect database tables.

**belongsTo (inverse one-to-many).** The `User` model defines `role()` and `group()` methods at `app/Models/User.php` lines 50–58. Because the `users` table stores `role_id` and `group_id` as foreign key columns, each user "belongs to" one role and one group. Laravel infers the foreign key name from the method name (e.g., `role()` → `role_id`). Accessing `$user->role` transparently queries the `roles` table.

**hasMany (one-to-many).** The `User` model defines `warnings()`, `blacklistRecords()`, `emailVerificationTokens()`, and `onboardingAgreements()` at lines 60–78. In each case the foreign key (`user_id`) lives on the child table. So `$user->warnings` returns all warning records whose `user_id` matches.

**belongsToMany (many-to-many with pivot table).** The `administeredGroups()` method on `User` (lines 83–88) and the `admins()` method on `Group` at `app/Models/Group.php` (lines 29–34) both reference the `group_admins` pivot table. This table stores `user_id`, `group_id`, `assigned_by`, `assigned_at`, `created_at`, and `updated_at`. The `withPivot()` call exposes the extra `assigned_by` and `assigned_at` columns, while `withTimestamps()` tells Laravel to auto-manage `created_at` and `updated_at` on the pivot row. This is how the system tracks which Group Administrators are assigned to which groups.

**SoftDeletes.** The `Group` model at `app/Models/Group.php` line 12 uses the `SoftDeletes` trait. This means calling `$group->delete()` does not remove the row from the database — it sets the `deleted_at` timestamp instead. Soft-deleted groups are excluded from normal queries but can be retrieved with `Group::withTrashed()`.

**How they connect.** When a Group Admin logs in, the system calls `$user->administeredGroups()` to find which groups they manage via the pivot table. When displaying those groups, `Group::withCount('users')` counts members. When the admin views users, `User::whereIn('group_id', $adminGroupIds)` filters to only users in those groups. The entire role-based data scoping chain flows through these relationships.

---

## 1.3 Database Migrations

Migrations define the database schema as PHP code. Each file in `database/migrations/` creates or modifies one or more tables.

**roles table** — `database/migrations/2026_06_23_203352_create_roles_table.php` (lines 14–19): Contains `id`, a unique `role_name` (max 50 chars), a nullable `description`, and standard timestamps. The unique constraint on `role_name` prevents duplicate roles.

**groups table** — `database/migrations/2026_06_23_203519_create_groups_table.php` (lines 14–20): Contains `id`, `group_name` (max 100 chars), nullable `description`, nullable `created_by` (unsigned big integer for a future foreign key), and timestamps.

**users table** — `database/migrations/2026_06_23_203600_create_users_table.php` (lines 14–25): The central table. `full_name` (max 100), `email` (max 100, unique), `password`, `role_id` (foreign key to `roles`), `group_id` (foreign key to `groups`), `account_status` (enum restricted to `'active'`, `'warned'`, `'blacklisted'` with default `'active'`), nullable `last_active_at` timestamp, nullable `profile_picture` path, and timestamps. The `foreignId()->constrained()` calls create actual database-level foreign key constraints, so inserting a user with a non-existent `role_id` or `group_id` will fail at the database level. This migration also creates the `password_reset_tokens` table (lines 27–31) and the `sessions` table (lines 33–40) for Laravel's session driver.

**warnings table** — `database/migrations/2026_06_23_214437_create_warnings_table.php` (lines 14–25): Tracks the 3-step warning escalation. `user_id` has `onDelete('cascade')` — if a user is deleted, all their warnings disappear automatically. `warning_number` is a tiny integer (1 or 2). `response_deadline` is the date by which the user must respond. `is_acknowledged` and `is_resolved` are boolean flags. `created_by` has `onDelete('set null')` — if the admin who created the warning is deleted, the record is preserved with a null creator.

**blacklist_records table** — `database/migrations/2026_06_23_214539_create_blacklist_records_table.php` (lines 14–22): Records user bans. Notable: this table has no `$table->timestamps()` call, so the model sets `$timestamps = false`. It uses `blacklisted_at` with `useCurrent()` (defaults to the current timestamp) instead of the standard `created_at`. `lifted_at` and `lifted_by` track who removed the ban and when.

**onboarding_agreements table** — `database/migrations/2026_06_23_214437_create_onboarding_agreements_table.php` (lines 14–21): Records whether a user accepted or declined the platform rules during registration. Also has no standard timestamps; uses `agreed_at` with `useCurrent()` instead.

**email_verification_tokens table** — `database/migrations/2026_06_25_191439_create_email_verification_tokens_table.php` (lines 14–21): Stores one-time tokens for email verification. `token` is unique. `user_id` cascades on delete. Only has `created_at` (no `updated_at`), since tokens are created and then deleted — never updated.

**How they connect.** The foreign key chain is: `users.role_id → roles.id`, `users.group_id → groups.id`, `warnings.user_id → users.id`, `blacklist_records.user_id → users.id`, `onboarding_agreements.user_id → users.id`, `email_verification_tokens.user_id → users.id`. The `group_admins` pivot table (created by a separate migration) links `users.id` to `groups.id` for the many-to-many Group Admin assignment.

---

## 1.4 Middleware Pipeline

Middleware are filters that run before a request reaches the controller. They are registered as aliases in `bootstrap/app.php` (lines 15–27). Six custom aliases are defined: `api.security`, `admin`, `system-admin`, `group-admin`, and `can-admin-group`, plus `ip-whitelist`. The file also configures global API rate limiting at 60 requests per minute via `throttleApi(60, 0)` on line 17.

**IsAdmin middleware** — `app/Http/Middleware/IsAdmin.php` (lines 16–29): First checks if the user is authenticated at all (`auth()->check()`); if not, redirects to login. Then calls `auth()->user()->isAdmin()` which returns true for both System Administrators and Group Administrators. If neither, it aborts with a 403 status. This middleware is applied to the entire admin route group in `routes/web.php` line 189.

**IsSystemAdmin middleware** — `app/Http/Middleware/IsSystemAdmin.php` (lines 14–25): Same pattern but stricter — calls `isSystemAdmin()` which only returns true for the "System Administrator" role. Group Administrators are blocked. Applied to System Config routes (line 199), IP Whitelist routes (line 220), and Group creation routes (line 244) in `routes/web.php`.

**CanAdminGroup middleware** — `app/Http/Middleware/CanAdminGroup.php` (lines 15–38): This is the most nuanced middleware. It extracts the `{group}` parameter from the route URL, resolves it to a `Group` model instance (handling both route-model-bound objects and raw IDs), then calls `$user->canAdminGroup($group)`. That method on the `User` model (lines 117–130 of `app/Models/User.php`) returns true immediately for System Admins, checks the `group_admins` pivot table for Group Admins, and returns false for everyone else. This ensures a Group Admin can only edit/manage groups they are specifically assigned to.

**How they connect.** The route in `routes/web.php` line 189 stacks `['auth', 'admin']` as the outer middleware. Inside that group, nested middleware groups add further restrictions. For example, line 254 wraps group-specific actions with `can-admin-group`, so a request to edit a group passes through: `auth` (are you logged in?) → `admin` (are you any kind of admin?) → `can-admin-group` (can you admin this specific group?). Each layer narrows the scope.

---

## 1.5 Laravel Sanctum (API Authentication)

The `HasApiTokens` trait on the `User` model (`app/Models/User.php` line 19) enables token-based authentication for the API (used by the desktop client). When a user logs in or registers via the API, the `AuthController` at `app/Http/Controllers/Api/AuthController.php` calls `$user->createToken('desktop-client')` (lines 60 and 147) which creates a record in the `personal_access_tokens` table and returns a plaintext token string. The client sends this token in the `Authorization: Bearer <token>` header on subsequent requests. The `auth:sanctum` middleware (configured in `routes/api.php` line 65) looks up the token, finds the associated user, and sets `$request->user()`. Logout simply deletes the current token (line 167). Token refresh deletes the old token and creates a new one (lines 224–238). The `listTokens()` method (lines 249–267) returns all active tokens for the user, and `revokeToken()` (lines 279–294) deletes a specific token by ID.

**How it connects.** The web interface uses session-based authentication (cookies + `sessions` table), while the desktop client uses Sanctum tokens (Bearer header + `personal_access_tokens` table). Both authenticate against the same `users` table but through different mechanisms. The `auth` middleware guards web routes; the `auth:sanctum` middleware guards API routes.

---

## 1.6 Rate Limiting

Laravel's `RateLimiter` facade provides token-bucket rate limiting to prevent abuse.

**Web login** — `app/Http/Controllers/Auth/LoginController.php` lines 31–41: The rate limit key combines the email address and IP address (`login-attempts:{email}|{ip}`). The limit is 5 attempts per 30 seconds. On failure, the attempt counter increments (line 56). On success, the counter is cleared (line 109). If the limit is exceeded, a `ValidationException` is thrown with a message showing how many seconds remain.

**API login** — `app/Http/Controllers/Api/AuthController.php` lines 80–90: Same logic but with a separate key prefix (`api-login-attempts:`) so web and API rate limits are independent. Returns a JSON 429 response instead of a validation exception.

**Registration** — `routes/web.php` line 100: Uses route-level middleware `throttle:3,60` which limits to 3 requests per 60 seconds per IP. No custom code needed — Laravel's built-in throttle middleware handles it.

**Email verification resend** — `app/Http/Controllers/Auth/EmailVerificationController.php` lines 66–73: Rate limited to 1 request per 60 seconds per email address. Uses the key `verify-email:{email}`.

**Global API** — `bootstrap/app.php` line 17: `throttleApi(60, 0)` applies a global limit of 60 requests per minute to all API routes.

**How they connect.** Each rate limiter uses a different key strategy tailored to its threat model. Login limiters include both email and IP to prevent distributed attacks on a single account. Registration uses IP-only limiting to prevent one IP from creating many accounts. Email verification uses email-only limiting to prevent spamming one inbox.

---

## 1.7 Caching (SystemConfig Pattern)

The `SystemConfig` model at `app/Models/SystemConfig.php` (lines 19–27) implements a cache-backed configuration system. The static `getValue()` method uses `Cache::remember()` with a 3600-second (1 hour) TTL. On first call for a given key, it queries the database and stores the result in cache. On subsequent calls within the hour, it returns the cached value without hitting the database. This is used extensively by the `MonitorMemberActivity` command (e.g., `app/Console/Commands/MonitorMemberActivity.php` lines 55 and 110) to fetch `inactivity_warning_days`, `warning_response_days`, and `blacklist_duration_days` without repeated database queries.

Cache invalidation happens via `clearCache()` (line 35) which calls `Cache::forget()` for a specific key, or `clearAllCaches()` (line 45) which iterates all config records and clears each one. The `SystemConfigController::update()` method at `app/Http/Controllers/Admin/SystemConfigController.php` uses `updateOrCreate()` to save new values, though it does not explicitly clear the cache after updating — the old cached value will persist until the TTL expires.

---

## 1.8 Blade Layout Inheritance

The application has two base layouts. `resources/views/layouts/app.blade.php` (68 lines) is the authenticated layout — it includes a navigation bar with links to dashboard, profile, admin sections (conditionally shown for admin users), and a logout button. It yields content via `@yield('content')` and conditionally loads admin CSS. `resources/views/layouts/guest.blade.php` (54 lines) is the unauthenticated layout — it has a simpler header, a site title, and a footer.

Individual pages declare which layout they want with `@extends('layouts.guest')` or `@extends('layouts.app')` and inject their content with `@section('content')`. For example, the login page at `resources/views/auth/login.blade.php` extends `layouts.guest`, while the dashboard at `resources/views/auth/dashboard.blade.php` extends `layouts.app`.

---

## 1.9 Artisan Commands

The `MonitorMemberActivity` command at `app/Console/Commands/MonitorMemberActivity.php` (lines 19, 31–94) is invoked via `php artisan monitor:activity`. It accepts an optional `--dry-run` flag that lets operators preview what would happen without making any database changes. The command queries all users with `account_status` of `'active'` or `'warned'`, reads the `inactivity_warning_days` threshold from `SystemConfig::getValue()` (cached), then loops through each user calculating days since last activity using `now()->diffInDays($lastActive, absolute: true)` — the `absolute: true` parameter ensures the result is always positive even when `$lastActive` is in the past. Users exceeding the threshold enter the warning escalation pipeline (see Section 2.9).

---

## 1.10 Database Seeding

The `RoleSeeder` at `database/seeders/RoleSeeder.php` (lines 14–22) inserts four roles: `Administrator`, `Lecturer`, `Student`, and `Member`. It uses `Role::insert()` which is a bulk insert (no model events fired, no timestamps set individually).

**Important discrepancy:** The seeder uses `'Administrator'` (line 17) while the `User::isSystemAdmin()` method at `app/Models/User.php` line 95 checks for `'System Administrator'`. The test trait `CreatesTestUsers` at `tests/CreatesTestUsers.php` line 22 also uses `'System Administrator'`. This means if you run the seeder and try to use admin features, no user will be recognised as a System Administrator. Tests pass because they seed their own roles independently.

---

# Chapter 2: Authentication Features

## 2.1 Registration — The 3-Step Onboarding Flow

Registration is deliberately split into three steps to enforce a platform-rules agreement before account creation.

**Step 1 — Show the registration form.** A GET request to `/register` hits `RegisterController::showRegister()` at `app/Http/Controllers/Auth/RegisterController.php` lines 28–31, which simply returns the `auth.register` view. The form collects `full_name`, `email`, `password`, and `password_confirmation`.

**Step 2 — Validate and store in session.** A POST to `/register` hits `storeRegister()` at lines 47–77. The controller validates: `full_name` is required (max 255 chars), `email` must be a valid unique email, and `password` must be confirmed, at least 8 characters, with mixed case and at least one number (enforced by Laravel's `Password::min(8)->mixedCase()->numbers()` rule object). Instead of creating the user immediately, the validated data (including the plaintext password) is stored in the session under the key `registration_data`, and the user is redirected to the onboarding page. This two-phase approach exists because the user must first see and agree to the platform rules before an account is created.

The route at `routes/web.php` line 100 has `throttle:3,60` middleware, limiting registration to 3 attempts per minute to prevent spam.

**Step 3 — Accept or decline onboarding.** The onboarding page at `resources/views/auth/onboarding.blade.php` (124 lines) displays the platform rules with a checkbox and Agree/Decline buttons. JavaScript disables the Agree button until the checkbox is checked.

If the user agrees, POST to `/onboarding/agree` hits `agreeOnboarding()` at lines 106–158. This method: (1) retrieves the session data — if it's missing (expired or tampered), redirects back to register with an error; (2) looks up the `Student` role and `Default Group` by name from the database (not by hardcoded ID, which would be fragile); (3) creates the `User` record with `Hash::make()` for the password; (4) creates an `OnboardingAgreement` record capturing the user's acceptance, IP address, and agreement version; (5) clears the session data; (6) auto-logs in the user via `Auth::login()`; (7) fires the `Registered` event (which triggers Laravel's email verification notification system); (8) sends a welcome email via `WelcomeMailable`; and (9) redirects to the dashboard with a success message.

If the user declines, POST to `/onboarding/decline` hits `declineOnboarding()` at lines 174–180. The session data is cleared and the user is redirected back to the registration page. No user record or agreement record is created.

**Database tables involved.** The `users` table stores the account. The `onboarding_agreements` table records the acceptance (with IP and version for legal audit). The `roles` and `groups` tables are pre-seeded reference data that provide the default `Student` role and `Default Group`.

---

## 2.2 Login (Web)

The login flow is at `app/Http/Controllers/Auth/LoginController.php` lines 28–112. It proceeds through a series of gates, each of which can block or redirect the user:

1. **Rate limit check** (lines 31–41): Before any database query, the controller checks whether this email+IP combination has exceeded 5 attempts in 30 seconds. If so, it throws a validation exception immediately.

2. **Input validation** (lines 45–48): Email must be present and valid; password must be present and at least 8 characters.

3. **User lookup** (line 51): Queries for the user by email. If no user is found, the method falls through to the credential check.

4. **Credential verification** (lines 54–61): Uses `Hash::check()` to compare the submitted password against the stored bcrypt hash. If they don't match (or the user wasn't found), the rate limiter counter increments and a validation exception is thrown.

5. **Blacklist gate** (lines 63–77): If the user's `account_status` is `'blacklisted'`, the controller looks for an active `BlacklistRecord` (one where `lifted_at` is null). If found, it extracts the expiry date and throws a validation exception telling the user when their ban expires. The rate limiter also increments.

6. **Warned gate** (lines 79–97): If `account_status` is `'warned'`, the controller checks for unacknowledged warnings. If one exists, the user IS logged in (`Auth::login()`) and the session is regenerated, but instead of going to the dashboard, they are redirected to `/warning-acknowledgement` where they must acknowledge the warning before proceeding. This design lets warned users still access the system but forces them to see the warning first.

7. **Successful login** (lines 99–111): `Auth::login()` creates the authentication session. `session()->regenerate()` creates a new session ID to prevent session fixation attacks. `last_active_at` is updated to the current time. The rate limiter is cleared. The user is redirected to the dashboard.

**Logout** is at lines 117–124: `Auth::logout()` clears the auth data, `invalidate()` destroys all session data, `regenerateToken()` creates a new CSRF token, and the user is redirected to the login page.

---

## 2.3 Password Hashing

The application uses bcrypt hashing through Laravel's `Hash` facade, applied in three different ways:

**Explicit hashing during registration.** `RegisterController::agreeOnboarding()` at `app/Http/Controllers/Auth/RegisterController.php` line 130 calls `Hash::make($registrationData['password'])` to hash the password before storing it.

**Explicit verification during login.** `LoginController::authenticate()` at `app/Http/Controllers/Auth/LoginController.php` line 54 calls `Hash::check($input, $user->password)` to compare the plaintext input against the stored hash.

**Automatic hashing via the `hashed` cast.** The `User` model at `app/Models/User.php` line 45 declares `'password' => 'hashed'` in the `casts()` method. This means any time a value is assigned to the `password` attribute — whether via `User::create()`, `$user->update()`, or `$user->password = '...'` — Laravel automatically runs `Hash::make()` on it. This provides a safety net: even if a developer forgets to call `Hash::make()` explicitly, the password will still be hashed.

**Password strength rules.** Registration enforces `Password::min(8)->mixedCase()->numbers()` at `app/Http/Controllers/Auth/RegisterController.php` lines 55–61. This requires at least 8 characters, at least one uppercase letter, at least one lowercase letter, and at least one digit. The same rule is applied during password reset (`PasswordController::resetPassword()` at `app/Http/Controllers/Auth/PasswordController.php` lines 95–101) and password change (lines 175–182).

---

## 2.4 Session Management

**Session regeneration on login.** At `app/Http/Controllers/Auth/LoginController.php` line 103, `session()->regenerate()` creates a brand-new session ID. This prevents **session fixation** — an attack where a malicious user sets a known session ID on the victim's browser, then later uses that same ID to hijack the session after the victim logs in. By regenerating the ID, the old (potentially compromised) ID becomes useless.

**Remember me.** The `$request->input('remember')` boolean at line 100 is passed to `Auth::login()`. When true, Laravel sets a long-lived encrypted cookie (the "remember token") that persists across browser restarts. The token is stored in the `remember_token` column of the `users` table (which is in the `$hidden` array so it never appears in JSON).

**Logout.** Lines 117–124: `Auth::logout()` removes authentication from the current session. `invalidate()` destroys all session data (flash messages, registration data, etc.). `regenerateToken()` generates a fresh CSRF token. The user is redirected to login.

**Session storage.** Sessions are stored in the database via the `sessions` table created at `database/migrations/2026_06_23_203600_create_users_table.php` lines 33–40. Each row has a session ID, optional user ID, IP address, user agent, encrypted payload, and last activity timestamp.

---

## 2.5 Password Reset (Forgot Password)

The password reset flow uses Laravel's built-in `Password` facade, which manages tokens in the `password_reset_tokens` table.

**Requesting a reset link.** `PasswordController::sendResetLink()` at `app/Http/Controllers/Auth/PasswordController.php` lines 40–57 validates that the email exists in the `users` table, then calls `Password::sendResetLink()` which generates a token, stores it in `password_reset_tokens`, and sends it to the user's email. The response is either a success flash message or an error, based on the status returned.

**Resetting the password.** `PasswordController::resetPassword()` at lines 90–132 validates the token, email, and new password (with strength rules). It calls `Password::reset()` which verifies the token is valid and not expired, then runs the provided closure: the closure uses `forceFill()` (which bypasses mass-assignment protection) to set the new hashed password and saves the user, then fires a `PasswordReset` event. If the reset succeeds, the user is automatically logged in and redirected to the dashboard — no need to re-enter credentials after a reset.

---

## 2.6 Change Password (Authenticated Users)

`PasswordController::updatePassword()` at `app/Http/Controllers/Auth/PasswordController.php` lines 164–197 handles password changes for logged-in users. It uses a custom closure validation rule (lines 168–173) to verify the current password is correct by running `Hash::check()` against the authenticated user's stored hash. The new password must be `different:current_password` (can't be the same as the old one), `confirmed` (must match the confirmation field), and meet the strength requirements. After validation, the password is updated via `Auth::user()->update()` which triggers the `hashed` cast for automatic hashing.

---

## 2.7 Email Verification

The application implements a custom email verification system using the `email_verification_tokens` table rather than Laravel's built-in `MustVerifyEmail` interface.

**Token generation.** `EmailVerificationController::resend()` at `app/Http/Controllers/Auth/EmailVerificationController.php` lines 57–89 first checks a rate limit of 1 request per 60 seconds per email address. It then generates a 64-character random token via `Str::random(64)`, stores it in the `email_verification_tokens` table with a 24-hour expiry, and sends it via `VerifyEmailMailable` through the mail queue.

**Token verification.** `EmailVerificationController::verify()` at lines 31–52 looks up the token in the database, matching both the token string and the email address. It calls `$verification->isValid()` which at `app/Models/EmailVerificationToken.php` lines 29–32 checks that `now()` is before `expires_at`. If valid, it sets `email_verified_at` to the current time on the user record and deletes the token (one-time use). If invalid or expired, it redirects with an error message.

**How it connects.** The `Registered` event fired during registration (at `RegisterController.php` line 150) can trigger Laravel's email verification notification. The profile update flow at `ProfileController.php` lines 37–54 also generates a new verification token when the user changes their email address, and sets `email_verified_at` back to null until the new email is verified.

---

## 2.8 Warning Acknowledgement

`WarningAcknowledgementController` at `app/Http/Controllers/Auth/WarningAcknowledgementController.php` (37 lines) is a simple controller. The `show()` method returns the warning acknowledgement view. The `acknowledge()` method finds the first unacknowledged warning for the current user (lines 24–26) and sets `is_acknowledged` to true (lines 29–31). It then redirects to the dashboard. Only the first unacknowledged warning is acknowledged per request — if a user has multiple, they would need to acknowledge them one at a time on subsequent logins.

---

## 2.9 Three-Step Warning Escalation (Automated)

The `MonitorMemberActivity` Artisan command at `app/Console/Commands/MonitorMemberActivity.php` implements an automated inactivity monitoring pipeline.

**Entry point.** The `handle()` method (lines 31–95) queries all users with `account_status` of `'active'` or `'warned'`, reads the `inactivity_warning_days` threshold from `SystemConfig::getValue()` (default 30), then loops through each user. For each user, it calculates `daysInactive` using `now()->diffInDays($lastActive, absolute: true)` where `$lastActive` falls back to `created_at` if `last_active_at` is null. If the user exceeds the threshold, `issueWarning()` is called.

**The `issueWarning()` method** (lines 107–163) implements the 3-step escalation:

- **No existing unresolved warnings → Warning 1** (lines 118–136): Creates a warning with `warning_number = 1`, a `response_deadline` of `warning_response_days` (default 7) from now, and sets the user's `account_status` to `'warned'`.

- **Warning 1 with expired deadline → Warning 2** (lines 140–153): If the latest unresolved warning is number 1 and its `response_deadline` is in the past, creates warning number 2 with a fresh deadline.

- **Warning 2 with expired deadline → Blacklist** (lines 156–163): If the latest unresolved warning is number 2 and its deadline has passed, calls `blacklistUser()`.

**The `blacklistUser()` method** (lines 172–192) creates a `BlacklistRecord` with an `expires_at` date based on `blacklist_duration_days` (default 90), and sets the user's `account_status` to `'blacklisted'`. The user is then blocked from logging in (the blacklist gate in `LoginController` will reject them).

**Dry-run mode.** The `--dry-run` flag (line 19) causes the command to report what would happen without making any database changes — useful for operators to preview the impact before committing.

**How it connects.** This command is the backend mechanism that feeds the login gates described in Section 2.2. When the command sets `account_status` to `'warned'` and creates a warning record, the next time that user tries to log in, the warned gate in `LoginController` detects the unacknowledged warning and redirects them to the acknowledgement page. When the command sets `account_status` to `'blacklisted'`, the blacklist gate blocks the login entirely.

---

# Chapter 3: Authorization Features

## 3.1 Role Hierarchy

The application defines four roles in a hierarchy:

- **System Administrator** — highest privilege; full system-wide access to all features including user management, group management, system configuration, and IP whitelist management.
- **Group Administrator** — medium privilege; can view and manage only the specific groups assigned to them via the `group_admins` pivot table.
- **Student** — low privilege; regular user with full access to discussion features.
- **Member** — low privilege; access to discussion features only.

The role-checking logic lives on the `User` model at `app/Models/User.php`:

- `isSystemAdmin()` (lines 93–96): Returns true if the user's role name is exactly `'System Administrator'`. Checks `$this->role` first to handle the case where the role relationship is not loaded.
- `isGroupAdmin()` (lines 101–104): Returns true if the role name is `'Group Administrator'`.
- `isAdmin()` (lines 109–112): Returns true if the user is either a System Admin or a Group Admin. This is the method the `IsAdmin` middleware calls.
- `canAdminGroup(Group $group)` (lines 117–130): System admins return true immediately (they can admin all groups). Group admins check the `group_admins` pivot table for a matching record. Everyone else returns false.
- `canAdminUser(User $targetUser)` (lines 135–149): Same pattern — System admins can admin all users; Group admins can only admin users whose `group_id` is in their administered groups.

---

## 3.2 Middleware-Based Authorization (Three Layers)

**Layer 1 — `admin` middleware (IsAdmin).** File: `app/Http/Middleware/IsAdmin.php` lines 16–29. Checks `auth()->check()` first (redirects to login if not authenticated), then calls `auth()->user()->isAdmin()`. If the user is neither a System Admin nor a Group Admin, it aborts with HTTP 403. This is applied to the entire admin route group at `routes/web.php` line 189.

**Layer 2 — `system-admin` middleware (IsSystemAdmin).** File: `app/Http/Middleware/IsSystemAdmin.php` lines 14–25. Same pattern but calls `isSystemAdmin()` instead of `isAdmin()`, so Group Admins are also blocked. Applied to System Config (line 199), IP Whitelist (line 220), and Group creation (line 244) in `routes/web.php`.

**Layer 3 — `can-admin-group` middleware (CanAdminGroup).** File: `app/Http/Middleware/CanAdminGroup.php` lines 15–38. Extracts the `{group}` route parameter, resolves it to a `Group` model, then calls `$user->canAdminGroup($group)`. Applied at `routes/web.php` line 254 to group-specific actions: edit, update, delete, view members, and update members. This ensures a Group Admin can only manage groups they are specifically assigned to.

**How the layers stack.** A Group Admin trying to edit a group they don't manage would pass through `auth` (logged in — yes) → `admin` (is admin — yes) → `can-admin-group` (can admin this group — no → 403). A Student trying the same route would fail at `admin` (not admin → 403). A guest would fail at `auth` (not logged in → redirect to login).

---

## 3.3 Policy-Based Authorization (GroupPolicy)

The `GroupPolicy` at `app/Policies/GroupPolicy.php` (79 lines) provides fine-grained authorization for group operations. Laravel automatically resolves policy methods based on the model class name.

- `viewAny()` (lines 13–16): Any admin can view the group list. Calls `$user->isAdmin()`.
- `view()` (lines 21–24): Can the user view this specific group? Calls `$user->canAdminGroup($group)`.
- `create()` (lines 30–33): Only System Admins can create new groups. Calls `$user->isSystemAdmin()`.
- `update()` (lines 38–51): System Admins can update any group. Group Admins can only update their assigned groups (checked via `canAdminGroup()`). Others are denied.
- `delete()` (lines 57–60): Only System Admins can delete groups.
- `manageMembers()` (lines 65–68): Anyone who can admin the group can manage its members.
- `assignAdmin()` (lines 74–77): Only System Admins can assign group admins.

The `GroupController` at `app/Http/Controllers/Admin/GroupController.php` uses `Gate::allows()` to invoke these policy methods: line 66 for create, line 109 for edit, line 124 for update, line 152 for delete, line 176 for manage members. If the gate check fails, the controller aborts with 403.

---

## 3.4 Complete Access Matrix

| Resource | System Admin | Group Admin | Student | Guest |
|----------|:-----------:|:-----------:|:-------:|:-----:|
| Admin Dashboard | Yes | Yes | 403 | → login |
| User List | All users | Own groups' users | 403 | → login |
| User Detail | Any user | Own groups' users | 403 | → login |
| Create User | Yes | 403 | 403 | → login |
| Edit User | Any user | Own groups' users | 403 | → login |
| Delete User | Yes | 403 | 403 | → login |
| Reset Password | Yes | 403 | 403 | → login |
| Blacklist User | Yes | 403 | 403 | → login |
| Lift Blacklist | Yes | Yes | 403 | → login |
| Change Role | Yes | Yes | 403 | → login |
| Resolve Warning | Yes | 403 | 403 | → login |
| System Config | Yes | 403 | 403 | → login |
| IP Whitelist | Yes | 403 | 403 | → login |
| Group List | All groups | Assigned groups | 403 | → login |
| Group Create | Yes | 403 | 403 | → login |
| Group Edit | Any group | Assigned groups | 403 | → login |
| Group Delete | Yes | 403 | 403 | → login |
| Group Members | Any group | Assigned groups | 403 | → login |
| Bulk Assign | Yes | 403 | 403 | → login |

---

## 3.5 API Authorization

API routes at `routes/api.php` use a two-layer approach. The outer `auth:sanctum` middleware (line 65) validates the Bearer token. Inside that, the `admin` middleware (line 130) applies the same `IsAdmin` check as web routes. Some API admin controllers also perform explicit `isSystemAdmin()` checks internally (e.g., `SystemConfigController`).

---

# Chapter 4: Testing Files and Test Cases

## 4.1 Test Infrastructure

**Test trait: `CreatesTestUsers`** — `tests/CreatesTestUsers.php` (lines 10–99): This trait is used by most test classes to set up the required reference data and create test users. The `seedRolesAndGroups()` method uses `Role::firstOrCreate()` and `Group::firstOrCreate()` to create four roles (`System Administrator`, `Group Administrator`, `Student`, `Member`) and two groups (`Default Group`, `Second Group`). Using `firstOrCreate` instead of `create` prevents unique constraint violations if the data already exists. Four helper methods — `createSystemAdmin()`, `createGroupAdmin()`, `createStudent()`, `createMember()` — each create a user with the appropriate role and group assignment, defaulting to password `Password123`. All accept an `$attrs` array to override defaults.

**Database strategy.** All test classes use the `RefreshDatabase` trait, which re-runs migrations for each test (using SQLite `:memory:` for speed). This ensures complete database isolation between tests.

**Running tests.** Execute `php artisan test` from the project root. Filter with `--filter=ClassName` or by directory.

---

## 4.2 Web Login Tests (14 tests)

**File:** `tests/Feature/Web/WebLoginTest.php`

| Test | What It Verifies |
|------|-----------------|
| `test_login_form_is_accessible` | GET /login returns 200 and renders the correct view |
| `test_user_can_login_with_valid_credentials` | Correct credentials redirect to dashboard and authenticate the user |
| `test_login_fails_with_wrong_email` | Non-existent email produces a session error; user remains a guest |
| `test_login_fails_with_wrong_password` | Wrong password produces a session error; user remains a guest |
| `test_login_requires_email` | Missing email field triggers a validation error |
| `test_login_requires_password` | Missing password field triggers a validation error |
| `test_blacklisted_user_cannot_login` | A blacklisted user is blocked and sees an error |
| `test_blacklisted_user_sees_expiry_date` | The blacklist error message includes the expiry date |
| `test_warned_user_with_unacknowledged_warning_is_redirected` | Warned user with unack'd warning is logged in but redirected to warning-acknowledgement |
| `test_warned_user_with_acknowledged_warning_can_login` | Warned user with ack'd warning proceeds normally to dashboard |
| `test_user_can_logout` | POST /logout redirects to login and the user becomes a guest |
| `test_login_updates_last_active_at` | After login, the user's `last_active_at` is no longer null |
| `test_login_is_rate_limited` | After 5 failed attempts, the 6th attempt is rejected |
| `test_root_redirects_to_login` | GET / redirects to the login page |

---

## 4.3 Registration & Onboarding Tests (15 tests)

**File:** `tests/Feature/Web/RegistrationOnboardingTest.php`

| Test | What It Verifies |
|------|-----------------|
| `test_registration_form_is_accessible` | GET /register returns 200 |
| `test_authenticated_user_is_redirected_from_register` | Already-logged-in users are redirected to dashboard |
| `test_registration_requires_full_name` | Missing name → validation error |
| `test_registration_requires_valid_email` | Invalid email format → validation error |
| `test_registration_requires_unique_email` | Duplicate email → validation error |
| `test_registration_requires_strong_password` | Weak password → validation error |
| `test_registration_requires_password_confirmation` | Mismatched passwords → validation error |
| `test_valid_registration_stores_session_and_redirects_to_onboarding` | Valid data → session has `registration_data`, redirects to onboarding |
| `test_onboarding_page_shows_rules` | GET /onboarding returns 200 with correct view |
| `test_accepting_onboarding_creates_user_and_logs_in` | Agree → user in DB, agreement recorded, user authenticated |
| `test_accepting_onboarding_assigns_default_role` | New user gets the Student role |
| `test_accepting_onboarding_assigns_default_group` | New user gets Default Group |
| `test_declining_onboarding_does_not_create_user` | Decline → no user in DB, redirects to register |
| `test_onboarding_agree_fails_without_session_data` | No session data → redirects back to register |
| `test_registration_is_rate_limited` | 3 registrations → 4th returns 429 |

---

## 4.4 Admin Access Control Tests (17 tests)

**File:** `tests/Feature/Admin/AdminAccessControlTest.php`

| Test | What It Verifies |
|------|-----------------|
| `test_system_admin_can_access_dashboard` | System admin → 200 |
| `test_group_admin_can_access_dashboard` | Group admin → 200 |
| `test_regular_user_cannot_access_admin_dashboard` | Student → 403 |
| `test_guest_cannot_access_admin_dashboard` | Guest → redirect to login |
| `test_system_admin_can_access_user_management` | System admin → 200 |
| `test_group_admin_can_access_user_management` | Group admin → 200 |
| `test_regular_user_cannot_access_user_management` | Student → 403 |
| `test_system_admin_can_access_system_config` | System admin → 200 |
| `test_group_admin_cannot_access_system_config` | Group admin → 403 |
| `test_system_admin_can_access_ip_whitelist` | System admin → 200 |
| `test_group_admin_cannot_access_ip_whitelist` | Group admin → 403 |
| `test_system_admin_can_access_audit_logs` | System admin → 200 |
| `test_group_admin_can_access_audit_logs` | Group admin → 200 |
| `test_system_admin_can_access_groups` | System admin → 200 |
| `test_group_admin_can_access_groups` | Group admin → 200 |
| `test_system_admin_can_create_groups` | System admin → 200 |
| `test_group_admin_cannot_create_groups` | Group admin → 403 |

---

## 4.5 User Management Tests (25 tests)

**File:** `tests/Feature/Admin/UserManagementTest.php`

### User List

| Test | What It Verifies |
|------|----------------|
| `test_admin_can_view_users_list` | Returns 200 with `users` view data |
| `test_admin_can_search_users` | Search by name returns 200 |
| `test_admin_can_filter_users_by_status` | Filter by `account_status` returns 200 |

### Lift Blacklist & Change Role

| Test | What It Verifies |
|------|----------------|
| `test_admin_can_lift_blacklist` | Blacklist record gets `lifted_at` set; user status becomes active |
| `test_system_admin_can_change_user_role` | User's `role_id` is updated to the new role |
| `test_cannot_downgrade_last_admin` | Error message in session; role remains unchanged |
| `test_can_downgrade_admin_when_others_exist` | When 2+ admins exist, downgrade succeeds |

### Show User Detail

| Test | What It Verifies |
|------|----------------|
| `test_admin_can_view_user_detail_page` | Returns 200 with correct view and `user` data |
| `test_group_admin_cannot_view_user_outside_scope` | Group Admin → 403 for users outside their groups |

### Create User

| Test | What It Verifies |
|------|----------------|
| `test_system_admin_can_create_user` | User appears in database with `active` status |
| `test_create_user_requires_valid_password` | Weak password → validation error |
| `test_group_admin_cannot_create_user` | Group Admin → 403 |

### Edit User

| Test | What It Verifies |
|------|----------------|
| `test_system_admin_can_edit_user` | User's `full_name` is updated in database |
| `test_edit_user_validates_email_uniqueness` | Duplicate email → validation error |
| `test_edit_user_cannot_downgrade_last_admin` | Error in session; role remains unchanged |

### Delete User

| Test | What It Verifies |
|------|----------------|
| `test_system_admin_can_delete_user` | User removed from database; redirects to index |
| `test_admin_cannot_delete_self` | Error in session; user still in database |
| `test_group_admin_cannot_delete_user` | Group Admin → 403; user still in database |

### Reset Password

| Test | What It Verifies |
|------|----------------|
| `test_system_admin_can_reset_password` | Password actually changed in database |
| `test_reset_password_requires_strong_password` | Weak password → validation error |
| `test_group_admin_cannot_reset_password` | Group Admin → 403 |

### Blacklist User

| Test | What It Verifies |
|------|----------------|
| `test_system_admin_can_blacklist_user` | User status becomes `blacklisted`; BlacklistRecord created with expiry |
| `test_system_admin_can_permanently_blacklist_user` | BlacklistRecord created with null `expires_at` |
| `test_blacklist_requires_reason` | Missing reason → validation error |
| `test_group_admin_cannot_blacklist_user` | Group Admin → 403 |

### Resolve Warning

| Test | What It Verifies |
|------|----------------|
| `test_system_admin_can_resolve_warning` | Warning `is_resolved` set to true; `resolved_at` populated |
| `test_resolving_last_warning_activates_user` | User `account_status` changes from `warned` to `active` |
| `test_cannot_resolve_already_resolved_warning` | Error in session; warning remains resolved |
| `test_group_admin_cannot_resolve_warning` | Group Admin → 403 |

---

## 4.6 Group Management Tests (12 tests)

**File:** `tests/Feature/Admin/GroupManagementTest.php`

| Test | What It Verifies |
|------|-----------------|
| `test_admin_can_view_groups` | Returns 200 |
| `test_admin_can_search_groups` | Search by name returns 200 |
| `test_system_admin_can_create_group` | Group appears in database |
| `test_group_name_must_be_unique` | Duplicate name → validation error |
| `test_group_admin_cannot_create_group` | Group admin → 403 |
| `test_system_admin_can_edit_group` | Group name is updated in database |
| `test_system_admin_can_delete_group` | Redirects after delete |
| `test_cannot_delete_general_group` | Error in session; group still in database |
| `test_system_admin_can_view_group_members` | Returns 200 |
| `test_system_admin_can_update_group_members` | Redirects after update |
| `test_system_admin_can_bulk_assign_users` | User's `group_id` is updated to the target group |
| `test_group_admin_cannot_bulk_assign` | Group admin → 403 |

---

## 4.7 Monitor Member Activity Tests (8 tests)

**File:** `tests/Feature/Console/MonitorMemberActivityTest.php`

| Test | What It Verifies |
|------|-----------------|
| `test_inactive_user_receives_warning_1` | 31 days inactive → Warning 1 created; status becomes `warned` |
| `test_active_user_does_not_receive_warning` | 5 days inactive → no warning created |
| `test_expired_warning_1_leads_to_warning_2` | Warning 1 deadline passed → Warning 2 created |
| `test_active_warning_1_does_not_lead_to_warning_2` | Warning 1 deadline in future → no Warning 2 |
| `test_expired_warning_2_leads_to_blacklist` | Warning 2 deadline passed → user blacklisted; BlacklistRecord created |
| `test_active_warning_2_does_not_lead_to_blacklist` | Warning 2 deadline in future → not blacklisted |
| `test_dry_run_does_not_make_changes` | `--dry-run` → no warning or blacklist records created |
| `test_no_duplicate_warnings_for_same_user` | Running command twice → only 1 warning (no duplicates) |

---

## 4.8 API Login Tests (12 tests)

**File:** `tests/Feature/Api/AuthControllerLoginTest.php`

| Test | What It Verifies |
|------|-----------------|
| `test_user_can_login_with_valid_credentials` | Returns 200 with token and correct JSON structure |
| `test_login_fails_with_invalid_email` | Returns 401 with "Invalid credentials" |
| `test_login_fails_with_wrong_password` | Returns 401 with "Invalid credentials" |
| `test_login_fails_with_blacklisted_account` | Returns 403 with blacklist expiry message |
| `test_login_fails_with_unacknowledged_warning` | Returns 403 with `requires_warning_acknowledgement: true` |
| `test_login_succeeds_with_acknowledged_warning` | Returns 200 with "Login successful" |
| `test_login_rate_limiting` | 5 failures → 6th returns 429 |
| `test_login_updates_last_active_at` | `last_active_at` is populated after login |
| `test_login_requires_email` | Returns 422 with validation error |
| `test_login_requires_password` | Returns 422 with validation error |
| `test_logout_revokes_token` | Token is deleted; returns 200 |
| `test_logout_fails_without_authentication` | Returns 401 |

---

## 4.9 API Registration Tests (11 tests)

**File:** `tests/Feature/Api/AuthControllerRegisterTest.php`

| Test | What It Verifies |
|------|-----------------|
| `test_user_can_register_via_api` | Returns 201 with token; role is Student; group is Default Group |
| `test_registration_requires_full_name` | Returns 422 |
| `test_registration_requires_valid_email` | Returns 422 |
| `test_registration_requires_unique_email` | Returns 422 |
| `test_registration_requires_password_confirmation` | Returns 422 |
| `test_registration_requires_strong_password` | Returns 422 |
| `test_registration_assigns_student_role` | `role_id` matches Student |
| `test_registration_assigns_default_group` | `group_id` matches Default Group |
| `test_registration_creates_api_token` | 1 token exists in database |
| `test_registration_fails_without_required_role` | Returns 500 with error message |
| `test_registration_fails_without_required_group` | Returns 500 with error message |

---

## 4.10 API Token Management Tests (13 tests)

**File:** `tests/Feature/Api/AuthControllerTokenManagementTest.php`

| Test | What It Verifies |
|------|-----------------|
| `test_user_can_refresh_token` | Old token deleted; new token is different |
| `test_refresh_token_fails_without_authentication` | Returns 401 |
| `test_user_can_list_tokens` | Returns array with correct structure |
| `test_list_tokens_fails_without_authentication` | Returns 401 |
| `test_user_can_revoke_specific_token` | Token deleted; returns 200 |
| `test_revoke_token_fails_with_invalid_token_id` | Returns 404 |
| `test_revoke_token_fails_without_authentication` | Returns 401 |
| `test_user_can_delete_account` | User removed from database |
| `test_delete_account_fails_with_wrong_password` | Returns 403; user still exists |
| `test_delete_account_fails_without_password` | Returns 422 |
| `test_delete_account_fails_without_authentication` | Returns 401 |
| `test_delete_account_revokes_all_tokens` | Zero tokens remain after deletion |
| `test_token_expiration_is_set` | Tokens contain id, name, created_at |

---

## 4.11 API Email Verification Tests (12 tests)

**File:** `tests/Feature/Api/EmailVerificationControllerTest.php`

| Test | What It Verifies |
|------|-----------------|
| `test_user_can_verify_email_with_valid_token` | Returns 200; token deleted from database |
| `test_verify_email_fails_with_invalid_token` | Returns 400; `email_verified_at` still null |
| `test_verify_email_fails_with_expired_token` | Returns 400 |
| `test_verify_email_fails_with_wrong_email` | Returns 400 |
| `test_verify_email_requires_token` | Returns 422 |
| `test_verify_email_requires_email` | Returns 422 |
| `test_verify_email_fails_without_authentication` | Returns 401 |
| `test_user_can_resend_verification_email` | Returns 200; token created in database |
| `test_resend_verification_fails_if_already_verified` | Returns 200 (idempotent) |
| `test_resend_verification_is_rate_limited` | Second request within 1 minute → 429 |
| `test_resend_verification_fails_without_authentication` | Returns 401 |
| `test_resend_verification_creates_valid_token` | Created token passes `isValid()` check |

---

## 4.12 API Password Controller Tests (18 tests)

**File:** `tests/Feature/Api/PasswordControllerTest.php`

| Test | What It Verifies |
|------|-----------------|
| `test_user_can_request_password_reset_link` | Returns 200; `Password::sendResetLink` called |
| `test_forgot_password_fails_with_nonexistent_email` | Returns 422 |
| `test_forgot_password_requires_email` | Returns 422 |
| `test_forgot_password_requires_valid_email_format` | Returns 422 |
| `test_user_can_reset_password_with_valid_token` | Returns 200; `Password::reset` called |
| `test_reset_password_fails_with_invalid_token` | Returns 400 |
| `test_reset_password_requires_token` | Returns 422 |
| `test_reset_password_requires_email` | Returns 422 |
| `test_reset_password_requires_password_confirmation` | Returns 422 |
| `test_reset_password_requires_strong_password` | Returns 422 |
| `test_authenticated_user_can_change_password` | Returns 200; password actually changed in database |
| `test_change_password_fails_with_wrong_current_password` | Returns 403; password unchanged |
| `test_change_password_requires_current_password` | Returns 422 |
| `test_change_password_requires_new_password` | Returns 422 |
| `test_change_password_requires_password_confirmation` | Returns 422 |
| `test_change_password_requires_strong_new_password` | Returns 422 |
| `test_change_password_requires_different_password` | Returns 422 (same as current) |
| `test_change_password_fails_without_authentication` | Returns 401 |

---

# Chapter 5: Admin Features

## 5.1 User Management

The `UserManagementController` at `app/Http/Controllers/Admin/UserManagementController.php` provides full CRUD and moderation capabilities for user accounts. All actions are logged via the `AuditLogService`.

**User list with role-based filtering.** The `index()` method starts with a base query on the `User` model. If the current user is a Group Admin, it scopes the query to only include users whose `group_id` matches one of the admin's assigned groups (retrieved via the `administeredGroups()` pivot relationship). System Admins see all users without filtering. Three optional filters are applied if present in the request: text search (matches against `full_name` or `email` using LIKE), status filter (exact match on `account_status`), and role filter (exact match on `role_id`). The query eager-loads `role` and `group` relationships to prevent N+1 query problems, then paginates at 15 results per page.

**User detail page.** The `show()` method loads a user with their role and group, then verifies authorization via `canAdminUser()`. It eager-loads the user's warnings (with `createdBy`), blacklist records (with `liftedBy`), and onboarding agreements. The view at `resources/views/admin/users/show.blade.php` renders a profile header with status/role badges, account details grid, and history tables for warnings, blacklists, and agreements.

**Create user (System Admin only).** The `create()` and `store()` methods allow System Admins to create new user accounts. Validation requires `full_name`, unique `email`, `password` (confirmed, min 8 chars, mixed case, numbers), `role_id`, and `group_id`. The new user is created with `account_status = 'active'`. An audit log entry records the creation.

**Edit user.** The `edit()` and `update()` methods support both System Admin and Group Admin access. System Admins can modify `full_name`, `email`, `role_id`, `group_id`, and `account_status`. Group Admins can only modify `full_name`, `email`, and `group_id` — role and status fields appear as disabled read-only inputs. A safety check prevents downgrading the last System Administrator. Audit logs record role changes, group changes, and reactivations.

**Delete user (System Admin only).** The `destroy()` method performs a hard delete (`forceDelete()`) — DB cascade removes related warnings, blacklist records, tokens, and agreements. Cannot delete your own account. An audit log entry is recorded before deletion.

**Reset password (System Admin only).** The `showResetPassword()` and `resetPassword()` methods allow System Admins to set a new password for any user. The same password strength rules apply as registration (`Password::min(8)->mixedCase()->numbers()`). The `hashed` cast on the User model handles hashing automatically.

**Manual blacklist (System Admin only).** The `showBlacklist()` and `blacklist()` methods allow System Admins to blacklist a user with a required reason and optional duration (1–365 days). If no duration is provided, the blacklist is permanent. A `BlacklistRecord` is created and the user's `account_status` is set to `'blacklisted'`.

**Lift blacklist.** The `liftBlacklist()` method finds the active blacklist record (where `lifted_at` is null) for the target user, sets `lifted_at` to now and `lifted_by` to the current admin's ID, then changes the user's `account_status` back to `'active'`. This immediately unblocks the user — they can log in on their next attempt.

**Change role.** The `changeRole()` method includes a safety check: it counts how many System Administrators exist, and if the target user is the last one, it refuses to change their role (preventing the system from being locked out of admin access). Otherwise, it updates the user's `role_id` to the new value.

**Resolve warning (System Admin only).** The `resolveWarning()` method marks a warning as resolved by setting `is_resolved = true` and `resolved_at = now()`. If the resolved warning was the user's last unresolved warning and their status is `'warned'`, the status is automatically changed to `'active'`. Already-resolved warnings are rejected with an error.

---

## 5.2 Group Management

**Group list with filtering and sorting.** The `GroupController::index()` method at `app/Http/Controllers/Admin/GroupController.php` lines 25–58 uses `Group::withCount('users')` to include a member count for each group. Group Admins see only their assigned groups (same pivot-table scoping as user management). Search filters by `group_name` using LIKE. Sorting supports two options: by creation date (newest first, the default) or by member count (most members first). Results are paginated at 15 per page.

**Create group.** The `store()` method at lines 76–101 first checks `Gate::allows('create', Group::class)` which invokes `GroupPolicy::create()` — only System Admins pass. It validates `group_name` as required, unique, max 100 characters, and `description` as optional, max 500 characters. The group is created with `created_by` set to the current admin's ID. An audit log entry is recorded.

**Edit group.** The `update()` method at lines 121–144 checks `Gate::allows('update', $group)` which invokes `GroupPolicy::update()` — System Admins can update any group; Group Admins can only update their assigned groups. Validation is the same as create but the unique rule excludes the current group's ID. Old values are captured before the update for audit logging.

**Delete group.** The `destroy()` method at lines 149–168 checks `Gate::allows('delete', $group)` — only System Admins pass. It also prevents deletion of the `'General'` group as a safety measure. The group is soft-deleted (because the `Group` model uses `SoftDeletes`).

**Update group members.** The `updateMembers()` method at lines 204–235 handles adding and removing users from a group. Users who are removed are not left without a group (which would violate the NOT NULL constraint on `users.group_id`) — they are reassigned to the `Default Group`. Users who are selected but not yet in the group are moved in. The `'General'` group cannot have all its members removed.

**Bulk assign.** The `bulkAssign()` method at lines 240–257 is restricted to System Admins via an explicit `isSystemAdmin()` check. It takes an array of user IDs and a target group ID, and updates all selected users' `group_id` in a single query.

---

## 5.3 System Configuration

The `SystemConfigController` at `app/Http/Controllers/Admin/SystemConfigController.php` (63 lines) manages runtime-configurable system parameters. Both the `index()` and `update()` methods check `isSystemAdmin()` explicitly (lines 23 and 38) — this is in addition to the route-level `system-admin` middleware, providing defense in depth.

The `update()` method (lines 35–61) validates five configuration keys, all required integers with minimum value 1:

| Key | Purpose | Default Used By |
|-----|---------|----------------|
| `max_login_attempts` | Maximum failed login attempts before lockout | LoginController |
| `lockout_minutes` | Duration of login lockout | LoginController |
| `inactivity_warning_days` | Days of inactivity before the monitor command triggers a warning | MonitorMemberActivity |
| `warning_response_days` | Days a user has to respond to a warning before escalation | MonitorMemberActivity |
| `blacklist_duration_days` | How long a blacklist lasts | MonitorMemberActivity |

Each config value is saved via `SystemConfig::updateOrCreate()` which either inserts a new row or updates the existing one. An audit log entry records the change.

---

## 5.4 Admin Dashboard Views

**User management views** — The `resources/views/admin/users/` directory contains six Blade templates:

- `index.blade.php`: Searchable, filterable table of users with a "+ Create User" button (System Admin only). Each row has View, Edit, Password, Blacklist, Lift Blacklist, and Change Role action buttons with role-based visibility.
- `show.blade.php`: User detail page showing profile header with status/role badges, account details grid (role, group, status, email verified, last active, joined date), and history tables for warnings, blacklist records, and onboarding agreements.
- `create.blade.php`: Form to create a new user (System Admin only) with fields for full name, email, password, role, and group.
- `edit.blade.php`: Role-aware edit form. System Admins see all fields (name, email, role, group, status). Group Admins see name, email, group as editable with role/status as disabled read-only. Includes a Danger Zone with delete button (System Admin only, hidden for self).
- `reset-password.blade.php`: Password reset form (System Admin only) with new password and confirmation fields.
- `blacklist.blade.php`: Blacklist form (System Admin only) with reason textarea and optional duration in days.

**Group management view** — `resources/views/admin/groups/index.blade.php` (124 lines): Renders a searchable, sortable table of groups. Includes a search bar, sort toggle (date vs. member count), and action buttons for Create (System Admin only), Edit, Delete, and Manage Members.

---

# Chapter 6: Underlying Issues

## 6.1 Role Name Discrepancy

The `RoleSeeder` at `database/seeders/RoleSeeder.php` line 17 seeds the top role as `'Administrator'`, but `User::isSystemAdmin()` at `app/Models/User.php` line 95 checks for `'System Administrator'`. The test trait `CreatesTestUsers` at `tests/CreatesTestUsers.php` line 22 also uses `'System Administrator'`. This means if you run the production seeder, no user will ever be recognised as a System Administrator, and all admin features will return 403. Tests pass only because they seed their own roles independently.

---

## 6.2 Partial Per-User Authorization in Legacy Methods

The newer methods (`show`, `edit`, `update`, `destroy`) all call `canAdminUser()` for proper scoping. However, the legacy `changeRole()` and `liftBlacklist()` methods still rely solely on the route-level `admin` middleware without calling `canAdminUser()`. A Group Admin could potentially change the role of or lift the blacklist for users outside their assigned groups through these two endpoints.

---

## 6.3 No Database Transaction on Multi-Step Operations

`RegisterController::agreeOnboarding()` at `app/Http/Controllers/Auth/RegisterController.php` lines 127–141 creates a `User` record and then an `OnboardingAgreement` record in two separate operations without wrapping them in a `DB::transaction()`. If the second operation fails (e.g., database error), the user exists without a corresponding agreement record, leaving the data in an inconsistent state.

---

## 6.4 Plaintext Password Stored in Session

During registration, `RegisterController::storeRegister()` at `app/Http/Controllers/Auth/RegisterController.php` lines 69–73 stores the plaintext password in the session under `registration_data`. If the session store (the `sessions` database table) is compromised, plaintext passwords are exposed. This is a security concern, albeit mitigated by the short window before onboarding completes and the session data is cleared.

---

## 6.5 BlacklistRecord Lacks Updated-At Tracking

The `blacklist_records` table at `database/migrations/2026_06_23_214539_create_blacklist_records_table.php` lines 14–22 has no `timestamps()` call, and the model at `app/Models/BlacklistRecord.php` line 12 sets `$timestamps = false`. When a blacklist is lifted (updating `lifted_at` and `lifted_by`), there is no `updated_at` column to record when the modification occurred.

---

## 6.6 Potential Memory Issues with Unscoped User Loads

`GroupController::showMembers()` at `app/Http/Controllers/Admin/GroupController.php` line 191 calls `User::all()` when the current user is a System Admin, loading every user into memory at once. For large user bases, this could cause memory exhaustion. Similarly, line 59 of `UserManagementController` calls `Role::all()` on every page load.

---

## 6.7 Inconsistent Delete Strategy Between Web and API

The web `ProfileController::destroy()` at `app/Http/Controllers/Auth/ProfileController.php` line 105 calls `$user->forceDelete()` which permanently removes the user from the database. The API `AuthController::deleteAccount()` at `app/Http/Controllers/Api/AuthController.php` line 208 calls `$user->delete()` which, because the `User` model does not use `SoftDeletes`, also permanently removes the row — but the inconsistency in approach (force delete vs. regular delete) could become a problem if soft deletes are ever added to the User model.

---

## 6.8 Rate Limiter Can Be Bypassed by IP Rotation

The login rate limiter at `app/Http/Controllers/Auth/LoginController.php` line 31 includes both the email and IP in the key. An attacker who controls multiple IP addresses (e.g., via a botnet or proxy rotation) can bypass the 5-attempt limit because each IP gets its own counter. A more robust approach would also include an email-only counter.

---

## 6.9 No Email Verification Enforcement

The application generates verification tokens and provides verification endpoints, but no middleware or gate actually prevents unverified users from accessing features. The dashboard at `resources/views/auth/dashboard.blade.php` shows a warning banner when `email_verified_at` is null, but this is purely cosmetic — unverified users can still use all features.

---

## 6.10 SystemConfig Cache Not Invalidated on Update

`SystemConfigController::update()` at `app/Http/Controllers/Admin/SystemConfigController.php` lines 50–55 saves new values via `updateOrCreate()` but does not call `SystemConfig::clearCache()` or `clearAllCaches()` afterwards. The `SystemConfig::getValue()` method at `app/Models/SystemConfig.php` lines 19–27 caches values for 3600 seconds. This means configuration changes may not take effect for up to an hour.

---

## 6.11 Soft-Deleted Groups Leave Dangling Foreign Keys

`GroupController::destroy()` at `app/Http/Controllers/Admin/GroupController.php` line 164 calls `$group->delete()` which soft-deletes the group (sets `deleted_at`). However, users still reference the group via their `group_id` foreign key. Those users now point to a soft-deleted group, which may cause unexpected behaviour in queries that join groups or scope by group.

---

## 6.12 Test Coverage Gaps

Several areas of the application lack automated test coverage:

1. **Web profile management** — `ProfileController` (edit, update, picture upload, web account deletion) has no tests.
2. **API profile/me endpoints** — `UserController::me()` and API `ProfileController::update()` are untested.
3. **Web password reset flow** — `PasswordController` web routes (forgot, reset, change) are only tested through the API test file.
4. **Web email verification** — `EmailVerificationController` web routes are only tested through the API test file.
5. **Warning acknowledgement** — `WarningAcknowledgementController` is only indirectly tested via login redirect tests.
6. **Audit logging verification** — Tests verify that actions succeed but do not assert that specific audit log entries are created.
7. **Legacy method authorization** — `changeRole()` and `liftBlacklist()` lack tests verifying Group Admin scoping via `canAdminUser()`.
8. **Concurrent access** — No tests for race conditions in role changes, blacklist operations, or group membership updates.
