# Adversarial Backend Challenge Report

## Challenge Summary

**Overall risk assessment**: LOW

All 86 backend API tests pass cleanly with 270 assertions. The system exhibits robust defensive design, including JSON exception rendering via `bootstrap/app.php`, strong date validation and comparison handling in `TaskController`, strict multi-tenant project boundary checks on subfolders in `FolderController`, and database transaction integrity during project template expansion in `ProjectController`.

---

## Challenges & Attack Vector Analysis

### [Low] Challenge 1: Unauthenticated Endpoint Exposure Risk
- **Assumption challenged**: All sensitive project management operations require authenticated sessions.
- **Attack scenario**: An unauthenticated attacker attempts direct HTTP access to project, task, folder, and note endpoints (e.g., `GET /api/projects`, `POST /api/projects/{id}/tasks`).
- **Blast radius**: `GET /api/projects` strictly rejects unauthenticated access (returns HTTP 401). Other routes inside `routes/api.php` rely on Sanctum token validation when grouped or API token middleware.
- **Mitigation**: Ensure all non-public API endpoints in `routes/api.php` remain grouped explicitly under `Route::middleware('auth:sanctum')`.

### [Low] Challenge 2: SQL Injection & Boundary Input Resilience
- **Assumption challenged**: Controller parameters could be vulnerable to SQL injection payload injection or memory exhaustion via boundary strings.
- **Attack scenario**: An attacker submits SQL injection payloads (`' OR '1'='1`, `1; DROP TABLE users;--`) or 10,000+ character strings into input parameters.
- **Blast radius**: Eloquent ORM parameterized queries prevent SQL injection completely. Input validation (`max:255` on strings) rejects oversized titles with 422 HTTP responses. Invalid IDs return 404 without database error leaks.
- **Mitigation**: Maintain standard Eloquent binding and input validation across all present and future controller endpoints.

---

## Stress Test Results

| Test Scenario | Target Endpoint | Expected Behavior | Actual Behavior | Result |
|---|---|---|---|---|
| Unauthenticated Access | `GET /api/profile`, `GET /api/projects`, `GET /api/users` | 401 Unauthorized | 401 Unauthorized | PASS |
| SQL Injection in Route Parameter | `GET /api/projects/' OR '1'='1` | 404 Not Found (no 500/SQL error) | 404 Not Found | PASS |
| Out-of-Bounds & Negative IDs | `GET /api/projects/-1`, `GET /api/projects/999999999999` | 404 Not Found | 404 Not Found | PASS |
| Malformed Date Formats | `POST /api/projects/1/tasks` with `"invalid-date"`, `"2026-13-45"`, `"2026-02-30"` | 422 Unprocessable Content | 422 Unprocessable Content | PASS |
| Inverted Task Dates | `POST /api/projects/1/tasks` with `start_date` > `deadline` | 422 Unprocessable Content | 422 Unprocessable Content (`تاريخ التسليم لا يمكن أن يكون قبل تاريخ البدء`) | PASS |
| Multi-tenant Subfolder Leak | `POST /api/projects/2/folders` with `parent_id` belonging to Project 1 | 422 Unprocessable Content | 422 Unprocessable Content (`المجلد الأب لا ينتمي إلى هذا المشروع`) | PASS |
| Cross-tenant Folder Listing | `GET /api/projects/1/folders` | Isolates folders to Project 1 | Isolated count: 1 folder | PASS |
| Transaction Rollback on Failure | `POST /api/projects` with faulty task template triggering internal exception | DB Rollback, 500 response, 0 persisted project records | DB Rollback executed, 500 returned, 0 project records persisted | PASS |

---

## Unchallenged Areas

- **OAuth / Third-Party SS0 Integrations**: Out of scope for this backend API instance (local Sanctum token auth used).
- **Physical File Storage Disk Quotas**: Out of scope for current test database configuration.
