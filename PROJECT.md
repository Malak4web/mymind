# Project: mymind Audit, Testing & Bug Fixing Campaign

## Architecture
- **Backend**: Laravel 13 API (`api/`) — PHP 8.3, Sanctum auth, Eloquent ORM, MySQL (dev) / SQLite memory (test).
- **Frontend**: Vue 3 SPA (`src/`) — Vite, Tailwind CSS, Vuex/Pinia reactive store (`src/store.js`), Axios/fetch API client.
- **Test Infrastructure**: PHPUnit 12 (Laravel API), Vitest + Vue Test Utils (JS Unit/Component), E2E Test Suite.

## Synthesis of Exploration Findings

### Backend Vulnerabilities & Logic Flaws
1. **Unprotected API Routes**: Majority of endpoints in `api/routes/api.php` defined outside `auth:sanctum` middleware group.
2. **Broken Scope Logic & GET Side-Effects**: `ProjectController@index` bypasses user filtering when unauthenticated and mutates DB state on GET (`attach()`).
3. **Missing DB Transactions**: Template expansion (`ProjectController@store`) performs multi-table inserts without `DB::transaction()`.
4. **Logic & Validation Gaps**: Uncaught `DateTime` parse exceptions in `TaskController`; `FolderController` misses parent ownership validation.
5. **Coverage Gaps**: `api/tests/Unit/` is empty; 6 controllers lack feature test coverage.

### Frontend Bugs & Flaws
1. **Runtime TypeError**: `src/store.js:288` calls non-existent `this.loadMessages()`, breaking project data initialization.
2. **Auth Header Omission**: >70% of `fetch()` calls in `store.js` lack `Authorization: Bearer <token>` header.
3. **Memory Leaks**: Unclosed `AudioContext` in `Login.vue` and `TaskBoard.vue`; un-cleared `setInterval` in `uploadFileToTask` (store.js:716) and `Login.vue:125`.
4. **Data Corruption**: `TaskCalendar.vue` drag-and-drop sends `{ deadline }` without `title`/`description`/`status`, sending `title: undefined` to `PUT /api/tasks/{id}`.
5. **Missing JS Test Runner**: 0% JS test coverage; Vitest not configured.

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | Codebase Exploration | Exploration of backend, frontend, build/infra | None | DONE |
| 2 | Test Infra & Suite Creation | Setup Vitest, build PHPUnit & JS unit test suites, publish TEST_READY.md | M1 | DONE |
| 3 | Backend & Frontend Bug Fixing | Fix API route protection, store.js errors, memory leaks, data corruption | M2 | DONE |
| 4 | Test Hardening & Review | Reviewer & Challenger verification, 100% test pass rate | M3 | DONE |
| 5 | Forensic Audit & Final Build | Forensic Integrity Audit, `npm run build` verification, report to Sentinel | M4 | DONE |

## Interface Contracts
### Laravel API ↔ Vue Frontend
- All protected API routes MUST enforce `auth:sanctum` middleware and receive `Authorization: Bearer <token>`.
- `PUT /api/tasks/{id}` expects full payload (`title`, `description`, `status`, `deadline`) or optional fields handled safely without nullifying existing title.

## Code Layout
- Backend: `api/app/Http/Controllers/`, `api/app/Models/`, `api/routes/api.php`, `api/tests/`
- Frontend: `src/App.vue`, `src/store.js`, `src/components/`, `src/views/`
- JS Tests: `src/__tests__/` or `tests/js/`
