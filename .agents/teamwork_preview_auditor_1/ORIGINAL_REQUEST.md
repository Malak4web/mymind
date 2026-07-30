## 2026-07-30T15:29:09Z
<USER_REQUEST>
You are Forensic Integrity Auditor for the mymind campaign.
Your working directory is: c:\xampp\htdocs\mymind\.agents\teamwork_preview_auditor_1
Your identity is: teamwork_preview_auditor

Task:
1. Perform systematic forensic integrity verification across all codebase changes in `api/` and `src/`, as well as test files in `api/tests/` and `src/__tests__/`.
2. Check for:
   - Hardcoded test results or mock data returned by production API endpoints/controllers.
   - Facade / dummy implementations that bypass genuine logic.
   - Circumvention of test execution or suppressed test failures.
   - Fabricated attestation artifacts.
3. Confirm that all implementations in `api/` and `src/` are 100% genuine and fully functional.
4. Write a formal forensic audit verdict report `audit_verdict.md` and `handoff.md` in your working directory. Clearly state either 'VERDICT: CLEAN' or 'VERDICT: INTEGRITY VIOLATION'. Send a summary message to parent.
</USER_REQUEST>
