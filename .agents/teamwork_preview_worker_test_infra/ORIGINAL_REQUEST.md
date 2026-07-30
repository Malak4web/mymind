## 2026-07-30T15:06:35Z
You are the Test Suite Creation Worker for the mymind campaign.
Your working directory is: c:\xampp\htdocs\mymind\.agents\teamwork_preview_worker_test_infra
Your identity is: teamwork_preview_worker

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A Forensic Auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Tasks:
1. Configure JS unit testing with Vitest:
   - Update `package.json` at root to include `vitest`, `@vue/test-utils`, `@vitejs/plugin-vue`, and `jsdom` (or install via npm).
   - Add `"test": "vitest run"` and `"test:watch": "vitest"` scripts to `package.json`.
   - Create `vite.config.js` / `vitest.config.js` configuration if needed.
2. Create comprehensive JS unit/component tests in `src/__tests__/`:
   - Unit test `store.js` actions, state initialization, mutations, and error handling.
   - Unit/Component tests for Vue components (`Login.vue`, `TaskBoard.vue`, `TaskCalendar.vue`, `Navbar.vue`, `ProjectList.vue`, etc.).
   - Verify tests cover both happy path and edge cases (unauthenticated state, invalid input, drag-and-drop deadline payload).
3. Expand Backend PHPUnit test suite in `api/tests/`:
   - Add missing feature tests in `api/tests/Feature/`: `ProjectCategoryTest.php`, `ProjectTemplateTest.php`, `TaskTemplateTest.php`, `FolderTest.php`, `ProjectFileTest.php`, `NoteTest.php`.
   - Add unit tests in `api/tests/Unit/` for Models, Services, and Validation logic.
   - Ensure PHPUnit tests check route authentication, authorization, missing parameters, and database transaction rollback scenarios.
4. Execute test runners:
   - Run `cd api && php artisan test` and document exact pass/fail outputs.
   - Run `npx vitest run` (or `npm run test`) and document exact pass/fail outputs.
5. Publish `TEST_READY.md` at project root with full coverage breakdown.
6. Write a detailed `handoff.md` in your working directory and send a completion message to parent.
