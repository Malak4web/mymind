# Task Request: Frontend Edge-Case & Reactivity Remediation

## Working Directory
`c:\xampp\htdocs\mymind\.agents\teamwork_preview_worker_frontend_fixes_v2`

## Objective
Apply frontend hardening fixes in `src/` to resolve empirical stress test findings:

1. **Unified Auth Headers in `src/store.js`**:
   - Standardize all authenticated `fetch()` API calls to use `getAuthHeaders()` (or check `if (this.token)`), avoiding sending `Authorization: Bearer ` when unauthenticated or token is empty.

2. **Loose/Strict ID Type Comparison Safety in `src/store.js` & `TaskCalendar.vue`**:
   - Ensure task lookups (`this.tasks.find(...)`) compare IDs safely using `String(t.id) === String(taskId)` or `Number(t.id) === Number(taskId)`.
   - Guarantee that `updateTask` payload preserves existing task fields (`title`, `description`, `status`, `deadline`) during drag-and-drop or partial updates.

3. **AudioContext Lifecycle Cleanup in `TaskBoard.vue`**:
   - Track `AudioContext` created in `playSuccessSound()` and ensure `AudioContext.close()` is called and cleaned up upon component unmount (`onUnmounted`).

4. **Reactivity Triggers on Nested Habit Objects**:
   - Ensure nested mutations on `habit.checklist` or `habit.notesList` trigger reactivity by reassigning `this.habits = [...this.habits]`.

5. **Verification**:
   - Run `npm run test` (Vitest) and `npm run build` (Vite) to verify 100% test pass rate and clean build.

6. **Reporting**:
   - Write `changes.md` and `handoff.md` in your working directory.
   - Send summary message to parent.
