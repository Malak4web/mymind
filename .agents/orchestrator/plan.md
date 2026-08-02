# Master Plan: Mobile UX/UI Redesign for Daily Routines & Habits (اليوميات والعادات)

## Strategic Objectives
1. **Segmented Control & Touch Navigation (R1)**:
   - Mobile segmented control ("اليوميات" | "العادات") visible and intuitive on mobile viewports (< 768px).
   - Horizontal touch/swipe gesture support for smooth switching between Daily Reflection Notes and Habit Tracking.

2. **Mobile Ergonomics & Habit Cards Redesign (R2)**:
   - Thumb-friendly habit cards with minimum 44x44px touch target sizes and visual completion feedback.
   - Compact progress gauge & streak counter top bar on mobile without screen clutter.
   - Enhanced mobile daily notes with smooth inline journal editing and quick floating/fixed submit action.

3. **Automated Test Coverage & Verification (R3)**:
   - Update and expand Vitest test suite (`src/__tests__/DailyRoutines.spec.js`) to cover mobile segmented control, swipe gesture handling, habit check-in state logic, and mobile daily notes.
   - Ensure 100% pass rate on `npm test` / `npx vitest run` and zero build errors on `npm run build`.

## Milestones & Decomposition

### Milestone 1: Exploration & Codebase Analysis
- **Goal**: Analyze current `DailyRoutines.vue`, `src/__tests__/DailyRoutines.spec.js`, `store.js`, responsive styles, and touch event handling.
- **Workers**: 3 Explorers (`teamwork_preview_explorer`) in parallel.
- **Deliverables**: Comprehensive analysis report detailing existing implementation, structure, gesture integration points, and test expansion strategy.

### Milestone 2: Segmented Control & Mobile Touch Navigation
- **Goal**: Implement top segmented control ("اليوميات" | "العادات") and touch swipe gesture logic on mobile screens (< 768px).
- **Workers**: Implementer (`teamwork_preview_worker`), Reviewers (`teamwork_preview_reviewer`), Challenger (`teamwork_preview_challenger`).
- **Deliverables**: Updated `DailyRoutines.vue` with mobile segmented tabs and swipe gesture handlers.

### Milestone 3: Mobile Ergonomics & Habit Cards Redesign
- **Goal**: Redesign habit cards for mobile (min 44x44px touch targets), compact progress gauge & streak bar, and enhanced mobile daily notes UI.
- **Workers**: Implementer (`teamwork_preview_worker`), Reviewers (`teamwork_preview_reviewer`), Challenger (`teamwork_preview_challenger`).
- **Deliverables**: Thumb-friendly mobile UI components in `DailyRoutines.vue`.

### Milestone 4: Automated Test Suite Expansion (`DailyRoutines.spec.js`)
- **Goal**: Expand Vitest unit/component tests to cover segmented control, touch swipe triggers, habit check-in, notes submit, and state updates.
- **Workers**: Implementer (`teamwork_preview_worker`), Reviewer (`teamwork_preview_reviewer`).
- **Deliverables**: Passing test suite with 100% pass rate (`npx vitest run`) and clean build (`npm run build`).

### Milestone 5: Review, Stress Testing & Forensic Integrity Audit
- **Goal**: Independent review, empirical stress testing, and forensic integrity audit.
- **Workers**: Reviewers (`teamwork_preview_reviewer`), Challenger (`teamwork_preview_challenger`), Forensic Auditor (`teamwork_preview_auditor`).
- **Deliverables**: CLEAN audit verdict, 0 test failures, verified build.

### Milestone 6: Final Verification & Victory Claim
- **Goal**: Final verification and victory claim to Sentinel.
- **Deliverables**: Comprehensive handoff report and victory notification.
