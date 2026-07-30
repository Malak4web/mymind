# Backend Explorer Handoff Report — Gen 2

## 1. Observation

- **Unprotected Routes**: In `api/routes/api.php` (lines 27–38), only `/profile`, `/logout`, `/users`, `/roles`, `/permissions` are wrapped in `Route::middleware('auth:sanctum')`. Routes for Projects (lines 41–48), Tasks (lines 57–60), Categories (lines 51–54), Templates (lines 111–122), Attachments (lines 68–69), Folders (lines 72–74), Files (lines 77–80), Notes (lines 83–86), Notifications (lines 89–93), and Digest (lines 96–107) have zero auth middleware.
- **Side Effects in GET Request**: In `api/app/Http/Controllers/ProjectController.php` (lines 25–30):
  ```php
  if ($p->users->isEmpty()) {
      $firstUser = \App\Models\User::first();
      if ($firstUser) {
          $p->users()->attach($firstUser->id);
          $p->load('users');
      }
  }
  ```
  Calling `GET /api/projects` auto-attaches `User::first()` to unassigned projects in DB.
- **Unprotected User Filtering Bug**: In `api/app/Http/Controllers/ProjectController.php` (lines 16–22):
  ```php
  if ($user && $user->role && $user->role->name !== 'مدير') {
      ...
  }
  ```
  If `$user` is `null` (unauthenticated request), the condition evaluates to `false` and returns all non-deleted projects without filtering.
- **Missing Database Transactions**: In `api/app/Http/Controllers/ProjectController.php` (lines 80–178): `Project::create()`, `CustomFieldDefinition::create()`, `Task::create()`, `Attachment::create()`, `CustomFieldValue::create()`, and `$project->users()->sync()` execute sequentially without `DB::transaction()`.
- **Date Exception Bug**: In `api/app/Http/Controllers/TaskController.php` (lines 30, 64): `new \DateTime($deadline)` can throw an unhandled `Exception` if invalid date strings are passed.
- **Folder Parent Scope Flaw**: In `api/app/Http/Controllers/FolderController.php` (line 20): `'parent_id' => 'nullable|exists:folders,id'` does not check if `parent_id` belongs to the same `project_id`.
- **Test Coverage Gaps**: `api/tests/Unit/` has zero files. `ProjectTemplateController`, `TaskTemplateController`, `FolderController`, `ProjectFileController`, `ProjectCategoryController`, and `NoteController` have no corresponding test files in `api/tests/Feature/`.

---

## 2. Logic Chain

1. **Observation 1 & 3** -> Because routes in `routes/api.php` lack Sanctum middleware, any unauthenticated client can invoke `GET /api/projects`, `POST /api/projects`, `POST /api/tasks`, etc. When `ProjectController::index()` runs without authentication, `$user` is `null`, bypassing the user permission check and exposing all internal project data to unauthenticated requests.
2. **Observation 2** -> `ProjectController::index()` executes `$p->users()->attach(...)` during GET requests. Read operations should be idempotent; mutating DB state on a GET endpoint introduces side-effects and race conditions under concurrent reads.
3. **Observation 4** -> Project creation from templates involves 6 dependent database operations. If an error occurs on task attachment creation (e.g. invalid string/type cast), the database is left with orphaned `Project` and `CustomFieldDefinition` records because no transaction rollback is wrapped around `store()`.
4. **Observation 7** -> The test suite in `api/tests/` lacks tests for 6 controllers and has 0 unit tests. Crucially, the tests do not check unauthenticated requests, allowing critical security gaps in `routes/api.php` to pass unnoticed.

---

## 3. Caveats

- **Runtime Execution**: The environment did not execute `php artisan test` via CLI tool in this pass as codebase exploration was conducted via direct file inspection.
- **Database Driver Specifics**: The SQLite database setup in `api/database/database.sqlite` may behave differently regarding foreign key constraints compared to MySQL/PostgreSQL in production.

---

## 4. Conclusion

The Laravel backend codebase in `api/` is functionally rich but has **high-severity authentication gaps** (the majority of API endpoints are publicly accessible), **missing database transaction boundaries** in template instantiation, **logic bugs in date handling and folder scoping**, and **missing test coverage for 6 controllers**.

---

## 5. Verification Method

1. **Verify Unprotected Routes**:
   Inspect `api/routes/api.php` lines 40–123 to verify missing `auth:sanctum` middleware block.
2. **Verify Missing Transactions**:
   Inspect `api/app/Http/Controllers/ProjectController.php` lines 42–184. Note absence of `DB::beginTransaction()` or `DB::transaction()`.
3. **Verify Test Coverage Gaps**:
   Inspect `api/tests/` directory structure. Observe empty `api/tests/Unit/` directory and missing test files for Project Templates, Task Templates, Folders, Project Files, Categories, and Notes.
