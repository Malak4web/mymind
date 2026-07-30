# Adversarial Challenge Report — Vue 3 Frontend

## Challenge Summary

**Overall risk assessment**: MEDIUM

## Challenges

### [High] Challenge 1: Strict Equality (`===`) ID Mismatch in Store Actions & Drag-and-Drop Payloads
- **Assumption challenged**: Assumed `taskId` or `id` passed to `store.updateTask()` / `deleteTask()` is always of type `Number`.
- **Attack scenario**: When HTML5 drag-and-drop (`event.dataTransfer.getData('text')`), URL query parameters, or string inputs pass string ID `"101"`, `this.tasks.find(t => t.id === taskId)` fails strict equality (`101 === "101"` is false). `existingTask` evaluates to `undefined`.
- **Blast radius**: `updates.title !== undefined ? updates.title : existingTask?.title` yields `undefined`. `JSON.stringify` strips `title` from the payload, sending `{ status: "مكتمل" }` to the API. Backend returns HTTP 422 validation error or loses task title.
- **Mitigation**: Use loose equality `t.id == taskId` or normalize `Number(taskId)` across store methods (`updateTask`, `deleteTask`, etc.).

### [High] Challenge 2: Unhandled Authorization Header Injection with Missing Token (`token = ''`)
- **Assumption challenged**: Assumed all API calls use `getAuthHeaders()` to conditionally attach Authorization header.
- **Attack scenario**: Methods `loadProjects`, `loadProjectCategories`, `createProjectCategory`, `updateProjectCategory`, `deleteProjectCategory`, `loadUsers`, `createProject`, `updateProject`, `updateTaskTemplate`, `deleteTaskTemplate`, and `setTaskTemplateDefault` hardcode `headers: { 'Authorization': 'Bearer ' + this.token }`. Additionally, `sendBatchedEmail` and `addNotification` omit headers entirely.
- **Blast radius**: When unauthenticated or token is cleared (`this.token = ''`), requests send `Authorization: Bearer ` with a trailing space rather than omitting the header. Backend auth guards reject requests as malformed bearer headers instead of missing authorization, causing unexpected error handling or crashes.
- **Mitigation**: Refactor all fetch calls in `store.js` to strictly use `this.getAuthHeaders()`.

### [Medium] Challenge 3: AudioContext Memory Leaks and Missing Unmount Cleanup in `TaskBoard.vue`
- **Assumption challenged**: Assumed AudioContext instances terminate cleanly without component lifecycle tracking.
- **Attack scenario**: In `TaskBoard.vue` (`playSuccessSound()`), a 2-second synthesized trumpet and stadium crowd noise is instantiated on `AudioContextClass`. There is no `onUnmounted` hook in `TaskBoard.vue`. Rapid task check toggling or unmounting `TaskBoard` while audio is playing leaves active AudioContext instances running in background.
- **Blast radius**: Leaked AudioContext objects, unhandled browser autoplay restrictions, background audio continuing after component destruction.
- **Mitigation**: Store AudioContext handle in component state, check `state === 'suspended'` before playing, and close active contexts inside `onUnmounted()`.

### [Medium] Challenge 4: Vue 3 Reactivity Bypass on Nested Habit State Updates
- **Assumption challenged**: Assumed mutating nested properties inside `store.habits` automatically triggers reactive UI re-renders across components.
- **Attack scenario**: Methods `updateHabitNote`, `addHabitNote`, `deleteHabitNote`, `addHabitChecklistItem`, `toggleHabitChecklistItem`, `deleteHabitChecklistItem` mutate nested arrays/objects in place and call `this.saveHabits()`, but do NOT trigger top-level array re-assignment `this.habits = [...this.habits]`.
- **Blast radius**: UI views watching `store.habits` omit re-renders when subtask checklists or habit notes are added/updated until a full page refresh.
- **Mitigation**: Reassign `this.habits = [...this.habits]` after mutating nested habit arrays.

## Stress Test Results

- `updateTask("101", { status: "مكتمل" })` with string ID → Expected: preserves title → Actual: `existingTask` undefined, title stripped from payload → FAIL (Bug confirmed)
- `loadProjects()` with `token=""` → Expected: omits Authorization header → Actual: sends `Authorization: Bearer ` → FAIL (Bug confirmed)
- Unmount `TaskBoard.vue` during sound synthesis → Expected: AudioContext closed on unmount → Actual: AudioContext continues in background → FAIL (Bug confirmed)
- `addHabitChecklistItem()` call → Expected: triggers top-level array reference update → Actual: mutates array in place without reference update → FAIL (Bug confirmed)
- `npm run test` (unit test suite) → Expected: 100% pass → Actual: 8 test files, 54 tests passed → PASS
- `npm run build` (vite build) → Expected: clean build → Actual: 31 modules transformed, 0 errors → PASS

## Unchallenged Areas

- Backend REST API endpoints (out of scope for frontend challenger, verified frontend state & components only).
