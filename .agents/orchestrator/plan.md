# Mobile UI Overhaul Plan — Daily Routines & Habits

## Project Goal
Transform the Daily Routines & Habits (`DailyRoutines.vue`) mobile experience (< 768px viewports) into a high-density, compact, sleek interface with ergonomic micro-controls (32-36px), slim glassmorphic header and progress gauge, micro-FAB, and slim segmented tab switcher, while maintaining 100% test pass rates and zero build errors.

## Milestones & Execution Strategy

### Phase 1: Exploration & Codebase Analysis
- Spawn `teamwork_preview_explorer` to inspect `DailyRoutines.vue`, `src/__tests__/DailyRoutines.spec.js`, associated Tailwind classes, existing components, and test setups.
- Identify current layout paddings, button sizes, header height, progress bar dimensions, and test assertions.

### Phase 2: Compact Mobile Layout & Sleek Components Overhaul
- Milestone 1: High-Density Layout & Slim Header/Gauge
  - Update `DailyRoutines.vue` layout paddings (`p-2 sm:p-3`, `gap-2`, `p-2.5`).
  - Fit 4-5 habit cards above the fold on mobile screens.
  - Reduce header padding/height and thin out progress gauge.
- Milestone 2: Sleek Micro Controls, Micro-FAB & Segmented Switcher
  - Replace bulky check-in buttons with 32-36px check-in toggles & status badges.
  - Add/refine compact Micro Floating Action Button (Micro-FAB).
  - Streamline tab switcher ("اليوميات" | "العادات") into a slim segmented design.

### Phase 3: Vitest Test Suite Updates & Build Verification
- Update `src/__tests__/DailyRoutines.spec.js` and any related component tests to match new classes, structure, and micro controls.
- Verify `npm test` passes 100% (0 failures).
- Verify `npm run build` compiles cleanly (0 errors).

### Phase 4: Review & Forensic Integrity Audit
- Dispatch `teamwork_preview_reviewer` and `teamwork_preview_challenger` to verify mobile density, component sizing, and functionality.
- Dispatch `teamwork_preview_auditor` for integrity verification.
