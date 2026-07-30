# Changes Summary

## 1. Route Protection Enclosure (`api/routes/api.php`)
- **Modification**: Wrapped all business API routes (lines 40–122) within the `Route::middleware('auth:sanctum')->group(function () { ... });` block.
- **Affected Route Groups**:
  - `/projects` (GET, POST, PUT, DELETE, restore, status management)
  - `/project-categories` (GET, POST, PUT, DELETE)
  - `/tasks` (GET, POST, PUT, DELETE)
  - `/custom-fields` (POST, DELETE)
  - `/attachments` (POST, DELETE)
  - `/folders` (GET, POST, DELETE)
  - `/project-files` (GET, POST, download, DELETE)
  - `/notes` (GET, POST, PUT, DELETE)
  - `/notifications` (GET, POST, read, read-all, testing helper)
  - `/digest` (send, queue, emails)
  - `/project-templates` (GET, POST, PUT, DELETE, set-default)
  - `/task-templates` (GET, POST, PUT, DELETE, set-default)
- **Impact**: All API endpoints now strictly require Sanctum authentication, preventing unauthenticated access.

## 2. Null Role Access Safeguard (`api/app/Http/Controllers/ProjectController.php`)
- **Modification**: Changed line 21 in `ProjectController@index` from:
  `if ($user->role && $user->role->name !== 'مدير')`
  to:
  `if (!$user->role || $user->role->name !== 'مدير')`
- **Impact**: Users without an assigned role (`$user->role` is null) are now safely scoped to only view projects to which they are explicitly assigned (`whereHas('users', ...)`), preventing unintentional admin-level visibility across all projects.

## 3. Feature & Stress Test Additions
- **`api/tests/Feature/ProjectTest.php`**:
  - Added `test_unauthenticated_requests_to_projects_return_401()` to verify unauthenticated requests return HTTP 401 Unauthorized.
  - Added `test_user_without_role_only_sees_assigned_projects()` to test and verify project query scoping for users with `role_id = null`.
- **`api/tests/Feature/AdversarialStressTest.php`**:
  - Expanded `test_unauthenticated_requests_to_strictly_protected_routes()` to include endpoints for projects, tasks, folders, notes, categories, notifications, and templates.

## 4. Verification Results
- Ran `php artisan test` in `api/`:
  - Total tests: **88**
  - Passed: **88**
  - Failures: **0**
  - Pass rate: **100%**
