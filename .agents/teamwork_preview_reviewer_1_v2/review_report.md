# Backend Code Review Report

## Review Summary

**Verdict**: REQUEST_CHANGES

The backend implementation in `api/` is well-structured and authentic, with 81 passing PHPUnit tests (216 assertions), clean Eloquent model relationships, proper `DB::transaction()` usage in `ProjectController@store` and `FolderController@destroy`, thorough date validation in `TaskController`, and parent folder project ownership validation in `FolderController`.

However, **route protection is incomplete**: the majority of API endpoints in `api/routes/api.php` (including `/projects`, `/tasks`, `/folders`, `/notes`, `/project-files`, `/attachments`, `/custom-fields`, `/notifications`, `/project-categories`, `/project-templates`, and `/task-templates`) are declared outside the `auth:sanctum` middleware group. In addition, `ProjectController@index` contains a minor scope check vulnerability when a user has no assigned role (`$user->role` is `null`).

---

## Findings

### [Major] Finding 1: Unprotected API Routes in `api/routes/api.php`
- **What**: Only `/profile`, `/logout`, `/users*`, `/roles`, and `/permissions` are enclosed inside `Route::middleware('auth:sanctum')->group(...)` in `api/routes/api.php` (lines 27-38).
- **Where**: `api/routes/api.php`, lines 40–122.
- **Why**: Routes for projects, tasks, folders, notes, files, attachments, custom fields, categories, and templates are exposed outside the Sanctum authentication middleware. This leaves API endpoints unprotected or dependent on manual `$request->user()` checks, while preventing Laravel's HTTP middleware pipeline from authenticating Bearer tokens.
- **Suggestion**: Wrap lines 40–122 inside the `Route::middleware('auth:sanctum')->group(function () { ... })` block in `api/routes/api.php`.

### [Minor] Finding 2: Weak Role Scope Check in `ProjectController@index`
- **What**: The condition `if ($user->role && $user->role->name !== 'مدير')` in `ProjectController@index` skips scope filtering if `$user->role` is `null`.
- **Where**: `api/app/Http/Controllers/ProjectController.php`, lines 21–25.
- **Why**: If a user is registered or assigned without a `Role` relation (`$user->role` is `null`), `$user->role` evaluates to `false`, causing the condition to be skipped. As a result, non-admin users without an assigned role are granted full access to all system projects instead of being restricted to their assigned projects.
- **Suggestion**: Change condition to `if (!$user->role || $user->role->name !== 'مدير')` so that users default to scoped project access unless explicitly assigned the 'مدير' role.

### [Minor] Finding 3: Lack of Unauthenticated Route Rejection Tests
- **What**: Feature tests (`ProjectTest`, `TaskTest`, `FolderTest`, etc.) authenticate globally in `setUp()` using `Sanctum::actingAs($user)`, but do not verify that requests without authentication headers are rejected with 401 Unauthorized.
- **Where**: `api/tests/Feature/ProjectTest.php`, `api/tests/Feature/TaskTest.php`, `api/tests/Feature/FolderTest.php`.
- **Why**: Because tests always run authenticated, the missing `auth:sanctum` middleware on routes was not detected by PHPUnit.
- **Suggestion**: Add explicit test cases in feature test suites checking `$this->getJson('/api/projects')->assertStatus(401)` without calling `Sanctum::actingAs(...)`.

---

## Verified Claims

- **100% PHPUnit Test Pass Rate** → Verified via `php artisan test` → PASS (81/81 tests passed, 216 assertions).
- **Authentic Implementation Integrity** → Verified via source code inspection of `api/app/Http/Controllers/`, `api/app/Models/`, and `api/tests/` → PASS (No fake facades, hardcoded outputs, or mocked shortcuts found).
- **Removal of GET Side-effects in `ProjectController@index`** → Verified in `ProjectController.php:12-36` → PASS (Query is strictly read-only with no state mutations).
- **`DB::transaction()` Wrapping in `ProjectController@store`** → Verified in `ProjectController.php:76-181` → PASS (Project creation, custom field definition, template task spawning, attachment creation, and member sync are atomically wrapped in `DB::transaction`).
- **Date Validation in `TaskController`** → Verified in `TaskController.php:29-52` & lines 82-105 → PASS (Validates date format and asserts `deadline >= start_date`, returning HTTP 422 on failure).
- **Parent Folder Ownership Validation in `FolderController`** → Verified in `FolderController.php:25-30` → PASS (Asserts `$parentFolder->project_id === $projectId`, returning HTTP 422 on mismatch).

---

## Coverage Gaps

- **Unauthenticated HTTP Middleware Execution** — Risk level: Medium — Recommendation: Wrap all business logic API routes in `auth:sanctum` middleware in `api/routes/api.php` and add unauthenticated tests in PHPUnit.

---

## Unverified Items

- None. All backend routes, controllers, models, and tests were fully inspected and verified.
