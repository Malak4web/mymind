# Task Request: Forensic Integrity Audit

## Working Directory
`c:\xampp\htdocs\mymind\.agents\teamwork_preview_auditor_1_v2`

## Objective
Perform a forensic code integrity audit across the entire codebase (`api/`, `src/`, `api/tests/`, `src/__tests__/`).
1. Run backend tests (`php artisan test` in `api/`) and frontend tests/build (`npm run test`, `npm run build`).
2. Perform comprehensive static analysis and execution checks:
   - Check for hardcoded test results, facade implementations, dummy return values, or fake assertions.
   - Check for bypassed tests, disabled assertions, or self-certifying mocks.
   - Search for pre-populated attestation artifacts or cheating tricks.
3. Verify that 100% PHPUnit pass rate and 100% JS unit test pass rate are genuinely achieved.
4. Verify that `npm run build` succeeds cleanly with 0 errors.
5. Issue a definitive binary verdict: CLEAN or INTEGRITY VIOLATION.
6. Write `audit_verdict.md` and `handoff.md` in your working directory.
