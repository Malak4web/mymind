# Task Request: Backend Route Protection & Controller Remediation

## Working Directory
`c:\xampp\htdocs\mymind\.agents\teamwork_preview_worker_fixes_v2`

## Objective
Apply required security and logic fixes to Laravel 13 backend in `api/`:

1. **Route Protection in `api/routes/api.php`**:
   - Wrap lines 40–122 (routes for `/projects`, `/tasks`, `/folders`, `/notes`, `/project-files`, `/attachments`, `/custom-fields`, `/notifications`, `/project-categories`, `/project-templates`, `/task-templates`) inside `Route::middleware('auth:sanctum')->group(function () { ... })`.

2. **Role Scope Safeguard in `api/app/Http/Controllers/ProjectController.php`**:
   - Line 21 in `ProjectController@index`: Change condition `if ($user->role && $user->role->name !== 'مدير')` to `if (!$user->role || $user->role->name !== 'مدير')` so users without an assigned role default to scoped project access.

3. **Unauthenticated Route Protection Test**:
   - Add explicit test assertions in `api/tests/Feature/ProjectTest.php` or `api/tests/Feature/AdversarialStressTest.php` verifying that unauthenticated requests to `/api/projects`, `/api/tasks`, `/api/folders` return HTTP 401 Unauthorized.

4. **Verification**:
   - Run `php artisan test` in `api/` to verify 100% test pass rate with 0 failures.

5. **Reporting**:
   - Write `changes.md` and `handoff.md` in your working directory.
   - Send summary message to parent agent.
