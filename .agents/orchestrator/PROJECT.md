# Project Scope: Daily Routines & Habits Mobile UI Overhaul

## Overview
Overhaul the Vue.js mobile UI (`DailyRoutines.vue` and related components/tests) in `mymind` for mobile viewports (< 768px). Transform it into a compact, high-density experience with minimal padding/margins, slim glassmorphic header, slim progress gauge, ergonomic 32-36px micro controls, micro-FAB, and compact tab switcher.

## Architecture & Code Layout
- Target View: `src/views/DailyRoutines.vue` (or `src/components/DailyRoutines.vue`, to be determined by Explorer)
- Target Test: `src/__tests__/DailyRoutines.spec.js` (and related test specs)
- Design tokens / CSS: Tailwind CSS classes (e.g. `p-2.5`, `gap-2`, `p-2 sm:p-3`)

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| 1 | Exploration & Analysis | Codebase scan of `DailyRoutines.vue` and tests | none | DONE |
| 2 | High-Density Compact Cards Layout & Micro Controls | Compact padding (`p-2.5`, `gap-2`, `p-2 sm:p-3`), 4-5 cards above fold, slim header/gauge, 36px toggles, micro-FAB, slim switcher | M1 | DONE |
| 3 | Vitest Suite Update & Build | Align unit tests with UI updates, achieve 100% test pass & clean build | M2 | DONE |
| 4 | Review, Challenge & Audit | Verification, review, challenge, and forensic integrity audit | M3 | DONE |
