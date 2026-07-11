# User Management — Group Restore Functionality

## 1. Overview

Groups in this project use **soft delete** — when an admin "deletes" a group, the row is **not removed** from the database. Instead, a `deleted_at` timestamp is set on the row. Normal queries automatically filter out deleted groups (`WHERE deleted_at IS NULL`), so they disappear from the admin UI and API responses.

Before this change, there was **no way** for an admin to see which groups had been deleted or bring them back. Recovery required raw SQL (`UPDATE groups SET deleted_at = NULL WHERE id = ?`) or Laravel Tinker.

This feature adds:
- A **trash view** showing all soft-deleted groups
- A **Restore button** that sets `deleted_at = NULL`, bringing the group back
- **API endpoints** for the same operations (desktop client)
- **Audit logging** for every restore
- **Policy enforcement** — only System Administrators can restore

---

## 2. How it connects to existing code

This feature builds on infrastructure that already existed but had no UI or API surface:

| Existing piece | Where | What it provides |
|---|---|---|
| `SoftDeletes` trait | `app/Models/Group.php` (line 12) | Gives the model `restore()`, `onlyTrashed()`, `withTrashed()` methods for free |
| `deleted_at` column | `database/migrations/2026_06_26_203507_add_soft_deletes_to_groups_table.php` | The actual timestamp column on the `groups` table |
| `GroupController@destroy` (web) | `app/Http/Controllers/Admin/GroupController.php` (line 154) | Existing soft-delete logic — calls `$group->delete()` |
| `GroupController@destroy` (API) | `app/Http/Controllers/Api/Admin/GroupController.php` (line 172) | Same, but was **missing** user reassignment (fixed in this feature) |
| `GroupPolicy` | `app/Policies/GroupPolicy.php` | Authorization pattern — `restore()` follows the same gate as `delete()` |
| `AuditLogService` | `app/Services/AuditLogService.php` | Existing group audit methods (`logGroupCreated`, `logGroupDeleted` etc.) — `logGroupRestored()` follows the same pattern |
| `system-admin` middleware | `bootstrap/app.php` (line 31) | Route protection — `restore` and `trashed` routes use the same middleware as `create` and `delete` |
| Admin group views | `resources/views/admin/groups/*.blade.php` | Existing view structure (index, create, edit, members) — the new `trashed` view mirrors `index.blade.php` |

### Relationship diagram

```
User (Web Browser)
    │
    ├── GET  /admin/groups              → GroupController@index    → groups.index.blade.php
    ├── GET  /admin/groups/trashed      → GroupController@trashed  → groups.trashed.blade.php   ★ NEW
    ├── POST /admin/groups/{group}/restore → GroupController@restore (withTrashed binding)      ★ NEW
    └── DELETE /admin/groups/{group}    → GroupController@destroy

Desktop Client (API)
    │
    ├── GET  /api/v1/admin/groups              → AdminGroupController@index
    ├── GET  /api/v1/admin/groups/trashed      → AdminGroupController@trashed           ★ NEW
    ├── POST /api/v1/admin/groups/{id}/restore → AdminGroupController@restore           ★ NEW
    └── DELETE /api/v1/admin/groups/{id}       → AdminGroupController@destroy (+ fix)

Authorization chain:
    admin middleware (isAdmin)  →  system-admin middleware (isSystemAdmin)  →  GroupPolicy::restore()
```

---

## 3. All changes — file by file

### 3.1 `app/Policies/GroupPolicy.php` — New `restore()` method

```php
public function restore(User $user, Group $group): bool
{
    return $user->isSystemAdmin();
}
```

**What changed:** Added one method after the existing `delete()` method.

**Why:** Every model operation that modifies data should have an explicit policy gate. The `restore` gate is identical to `delete` — only System Administrators can restore groups. Policy gates are called automatically by `Gate::allows('restore', $group)` in the controller.

---

### 3.2 `app/Services/AuditLogService.php` — New `logGroupRestored()` method

```php
public function logGroupRestored($group): AuditLog
{
    return $this->log(
        action: 'group.restored',
        target: $group,
        description: Auth::user()?->full_name . ' restored group ' . $group->group_name
    );
}
```

Also added to the `$descriptions` array:
```php
'group.restored' => "{$userName} restored group {$targetName}",
```

**What changed:** One new method + one new entry in the descriptions lookup array.

**Why:** Every group lifecycle event (created, updated, deleted) is already logged. Restore is a significant action — it reverses a deletion — so it must be auditable too. The method follows the exact same pattern as `logGroupDeleted()`.

---

### 3.3 `routes/web.php` — Two new routes

```php
// Inside the existing system-admin middleware group (around line 597):
Route::get('/groups/trashed', [GroupController::class, 'trashed'])
    ->name('admin.groups.trashed');

Route::post('/groups/{group}/restore', [GroupController::class, 'restore'])
    ->withTrashed()
    ->name('admin.groups.restore');
```

**What changed:** Two routes added inside the existing `Route::middleware(['system-admin'])` block (which already contains group create, store, bulk-assign).

**Why placed here:** This middleware block appears **before** the `can-admin-group` middleware block that contains `GET /groups/{group}`. If the trashed route were placed after that block, the string "trashed" in the URL would be caught by `{group}` as a group ID. Placing it here avoids the collision entirely.

**Why `->withTrashed()` on the restore route:** Route-model binding normally excludes soft-deleted records. Without `->withTrashed()`, `POST /admin/groups/5/restore` would return 404 if group 5 has been soft-deleted. The `withTrashed()` modifier tells Laravel to include soft-deleted records when resolving the model from the route parameter.

---

### 3.4 `app/Http/Controllers/Admin/GroupController.php` — Two new methods

**`trashed(Request $request)`:**

```php
public function trashed(Request $request)
{
    $query = Group::onlyTrashed()->withCount('users')->with('createdBy');

    if ($request->filled('search')) {
        $query->where('group_name', 'like', "%{$request->input('search')}%");
    }

    $query->orderBy('deleted_at', 'desc');
    $groups = $query->paginate(15);

    return view('admin.groups.trashed', compact('groups', 'search'));
}
```

**What it does:**
1. `Group::onlyTrashed()` — fetches **only** soft-deleted groups (inverse of the default `WHERE deleted_at IS NULL`)
2. `withCount('users')` — eager-loads the member count (same as `index()`)
3. `with('createdBy')` — eager-loads the creator relationship (same as `index()`)
4. Optional search by group name
5. Ordered by `deleted_at` descending (most recently deleted first)
6. Paginated 15 per page (same as `index()`)

**`restore(Group $group)`:**

```php
public function restore(Group $group)
{
    if (! Gate::allows('restore', $group)) {
        abort(403);
    }

    $group->restore();
    $this->auditLogService->logGroupRestored($group);

    return redirect()->route('admin.groups.trashed')
        ->with('success', "Group '{$group->group_name}' restored successfully");
}
```

**What it does:**
1. Policy check via `Gate::allows('restore', $group)` — only System Administrators pass
2. `$group->restore()` — Eloquent sets `deleted_at = NULL`. The group immediately becomes visible to all normal queries
3. Audit log entry
4. Redirects back to the trashed page (keeping the admin in their "trash management" context)

**Why the group parameter resolves despite being soft-deleted:** The route binding has `->withTrashed()` (see section 3.3), so even soft-deleted records are found.

---

### 3.5 `resources/views/admin/groups/trashed.blade.php` — New view

A Blade view that lists only soft-deleted groups. It mirrors `index.blade.php` with these differences:

| Aspect | `index.blade.php` | `trashed.blade.php` |
|---|---|---|
| Title | "Group management" | "Deleted Groups" |
| Description | "Manage groups, member counts..." | "Groups that have been deleted can be restored here." |
| "Create group" button | Yes (System Admin only) | No |
| "Deleted groups" link | Yes (newly added) | No |
| Date column | "Created date" | "Deleted date" (`$group->deleted_at`) |
| Action button | Members / Edit / Delete | **Restore** (POST form with confirm dialog) |
| Empty state | "No groups found." | "No deleted groups found." |

The Restore button uses a POST form:
```blade
<form method="POST" action="{{ route('admin.groups.restore', $group) }}">
    @csrf
    <button type="submit" class="btn btn-success btn-sm"
            onclick="return confirm('Restore this group? All members and data will reappear.')">
        Restore
    </button>
</form>
```

---

### 3.6 `resources/views/admin/groups/index.blade.php` — "Deleted groups" link

A single link added next to the "Create group" button inside the `@if (auth()->user()->isSystemAdmin())` block:

```blade
<a href="{{ route('admin.groups.trashed') }}" class="btn btn-secondary">Deleted groups</a>
```

**Why:** Without this link, the only way to reach the trash view would be to type the URL manually.

---

### 3.7 `routes/api.php` — Two new API routes

```php
Route::get('/groups/trashed', [AdminGroupController::class, 'trashed']);
Route::post('/groups/{groupId}/restore', [AdminGroupController::class, 'restore']);
```

**Why placed before `/groups/{groupId}`:** Same route collision concern as the web routes — "trashed" must not be interpreted as a group ID.

---

### 3.8 `app/Http/Controllers/Api/Admin/GroupController.php` — Two new methods + one fix

**`trashed(Request $request)`:**

Same logic as the web version but returns JSON. Authorization is checked inline (not via policy) — mirrors the existing pattern in this controller.

```php
public function trashed(Request $request)
{
    if (! auth()->user()->isSystemAdmin()) {
        return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
    }

    $query = Group::onlyTrashed()->withCount('users');

    if ($request->filled('search')) {
        $query->where('group_name', 'like', '%' . $request->input('search') . '%');
    }

    $query->orderBy('deleted_at', 'desc');
    $groups = $query->paginate($request->input('per_page', 15));

    return response()->json([
        'success' => true,
        'data' => $groups->items(),
        'pagination' => [
            'total' => $groups->total(),
            'per_page' => $groups->perPage(),
            'current_page' => $groups->currentPage(),
            'last_page' => $groups->lastPage(),
        ],
    ]);
}
```

**`restore($groupId)`:**

```php
public function restore($groupId)
{
    if (! auth()->user()->isSystemAdmin()) {
        return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
    }

    // CRITICAL: Must use withTrashed() — findOrFail() alone returns 404 on soft-deleted records
    $group = Group::withTrashed()->findOrFail($groupId);
    $group->restore();

    $this->auditLogService->logGroupRestored($group);

    return response()->json([
        'success' => true,
        'message' => "Group '{$group->group_name}' restored successfully",
        'data' => $group->loadCount('users'),
    ]);
}
```

**`destroy()` fix — user reassignment:**

The API `destroy()` was missing user reassignment logic that the web version has. Before this fix:

```php
// API destroy did ONLY this:
$group->delete();
```

After the fix:

```php
// Reassign users to the default "General" group before soft-deleting
$defaultGroupId = Group::where('group_name', 'General')->value('id')
    ?? Group::min('id');

if ($defaultGroupId && $defaultGroupId != $group->id) {
    User::where('group_id', $group->id)
        ->update(['group_id' => $defaultGroupId]);
}

$group->delete();
```

**Why this matters:** Without reassignment, users remain with a `group_id` pointing to a soft-deleted group. While the foreign key constraint is satisfied (the row still exists), Laravel's `BelongsTo` relationship **excludes soft-deleted models by default**. So `$user->group` returns `null`, which can cause issues in views and API responses that expect a valid group object.

---

### 3.9 `tests/Feature/Admin/GroupManagementTest.php` — Three new tests

**`test_system_admin_can_view_trashed_groups()`:**

Creates a group, soft-deletes it, then verifies the System Admin can access the trashed view and sees the deleted group's name.

**`test_system_admin_can_restore_group()`:**

Creates a group, soft-deletes it, then restores it via POST. Asserts the redirect goes to the trashed page and the database row now has `deleted_at = null`.

**`test_group_admin_cannot_restore_group()`:**

Creates a group, soft-deletes it, then attempts to restore as a Group Admin. Asserts a 403 response and that the group remains soft-deleted.

---

## 4. Complete UI walkthrough

### Step 1: System Admin logs in

Admin navigates to `/admin/groups`.

The page shows the normal group list with a "Create group" button (System Admin only) and now a **"Deleted groups"** button:

![Group index header layout]
```
[Group management]                     [Create group]  [Deleted groups]
```

### Step 2: Delete a group

Admin clicks "Delete" on a group (e.g. "Old Course 2024"). A confirmation dialog appears: "Delete this group?"

Admin confirms. Behind the scenes:
1. Users in the group are reassigned to the "General" group
2. `Group::delete()` sets `deleted_at = now()`
3. Audit log: `group.deleted`
4. The group disappears from the table immediately

### Step 3: View trashed groups

Admin clicks the **"Deleted groups"** button.

The trashed view shows:
```
Deleted Groups
Groups that have been deleted can be restored here.

| Group name       | Type    | Description | Members | Deleted date        | Actions |
|------------------|---------|-------------|---------|---------------------|---------|
| Old Course 2024  | student | —           | 0       | Apr 08, 2026       | [Restore] |

[Back to Groups]
```

### Step 4: Restore the group

Admin clicks **[Restore]**. A confirmation dialog: "Restore this group? All members and data will reappear."

Admin confirms. Behind the scenes:
1. `Group::restore()` sets `deleted_at = NULL`
2. Audit log: `group.restored`
3. The page redirects back to the trashed view with a success message
4. The group now appears in the main group list again

### Step 5: Group Admin cannot access trash

If a Group Administrator tries to navigate to `/admin/groups/trashed` directly, they get a **403 Forbidden** page. The "Deleted groups" button is not shown to them in the index view.

---

## 5. Complete API flow

### List trashed groups

```
GET /api/v1/admin/groups/trashed
Authorization: Bearer <system-admin-token>
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 5,
            "group_name": "Old Course 2024",
            "description": null,
            "group_type": "student",
            "created_by": 1,
            "deleted_at": "2026-04-08T12:00:00.000000Z",
            "created_at": "2025-09-01T10:00:00.000000Z",
            "updated_at": "2026-04-08T12:00:00.000000Z",
            "users_count": 0
        }
    ],
    "pagination": {
        "total": 1,
        "per_page": 15,
        "current_page": 1,
        "last_page": 1
    }
}
```

**Error (non-System-Admin):**
```json
{
    "success": false,
    "message": "Unauthorized."
}
```
Status: 403

### Restore a group

```
POST /api/v1/admin/groups/5/restore
Authorization: Bearer <system-admin-token>
```

**Response:**
```json
{
    "success": true,
    "message": "Group 'Old Course 2024' restored successfully",
    "data": {
        "id": 5,
        "group_name": "Old Course 2024",
        ...
        "users_count": 0
    }
}
```

**Error (not found — wrong ID or permanently deleted):**
```json
{
    "message": "No query results for model [App\\Models\\Group] 999"
}
```
Status: 404

---

## 6. Edge cases and notes

### Restored groups and reassigned users

When a group is deleted via the **web interface**, all its users are reassigned to the "General" group **before** the soft-delete happens. If the group is later restored, those users are **not** automatically moved back. They remain in the "General" group. An admin must manually reassign them if needed.

When a group is deleted via the **API** (before this fix), users were **not** reassigned — they kept their `group_id` pointing to the soft-deleted group. The API `destroy()` now reassigns users too, matching the web behaviour.

### "General" group protection

The system prevents deleting the "General" group entirely (checked in both web and API controllers). This is unchanged — the "General" group will never appear in the trashed view.

### Route ordering

The `GET /admin/groups/trashed` route is defined **before** `GET /admin/groups/{group}` in both `routes/web.php` and `routes/api.php`. This is required because Laravel matches routes top-to-bottom. Without this ordering, "trashed" would be captured as a group ID parameter.

### `withTrashed()` on route binding

The `POST /admin/groups/{group}/restore` route uses `->withTrashed()` because route-model binding in Laravel automatically excludes soft-deleted models. Without this modifier, the restore route would return a 404 for any group that has been soft-deleted — which is the exact group the admin is trying to restore.

### Audit trail

Every restore creates an audit log entry with:
- **Action:** `group.restored`
- **Target:** The restored Group model
- **User:** The System Administrator who performed the restore
- **Timestamp:** When the restore happened

This appears in the admin audit log viewer alongside created, updated, and deleted events.

---

## 7. Verification

After deployment, confirm:

1. `php artisan route:list | grep -i trash` shows two routes (web + API)
2. `php artisan test tests/Feature/Admin/GroupManagementTest.php` passes all tests
3. System Admin sees "Deleted groups" button on the groups index page
4. Deleting a group removes it from the main list, and it appears in the trashed view
5. Restoring a group makes it reappear in the main list
6. Group Admin gets 403 on trashed/restore endpoints
7. API returns proper JSON responses with correct HTTP status codes
