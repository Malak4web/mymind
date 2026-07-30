# Project: Daily Routines ('يومياتي') Redesign

## Architecture & Overview
Mobile-first UI/UX overhaul of the Daily Routines system in mymind Vue 3 codebase:
- Glassmorphic Header & Weekly Day Picker Bar (`DailyRoutines.vue`).
- Modern Routine Cards & Thumb-friendly 56px Check Buttons (`DailyRoutines.vue`).
- Dedicated Habit Detail View (`HabitDetail.vue` / `#routines/habit-[ID]`):
  - Progress Ring Header with SVG gauge & Streak Counter 🔥
  - Interactive Sub-tasks Checklist with % progress bar & inline editing
  - Habit Journal & Mood Notes Interface (🤩 😃 😐 😫 🚀 ⚡)
  - Monthly Heatmap Grid with month navigation, RTL headers & 4-level color intensity
- Mobile Bottom Sheets / Drawers (`MobileBottomSheet.vue`) for checking, stats, details, and habit creation.

## Milestones

| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| M1 | Exploration | Codebase investigation for routines & habit details | none | DONE |
| M2 | Main Screen Redesign | Header glassmorphism, day picker, routine cards, check buttons | M1 | DONE |
| M3 | Habit Detail View (`HabitDetail.vue`) | Progress ring, streak 🔥, sub-tasks, journal notes, heatmap | M1, M2 | DONE |
| M4 | Bottom Sheets & Drawers | Bottom sheet drawers for checking, stats, details, creation | M2, M3 | DONE |
| M5 | Verification & Build Audit | Reviewers, Challengers, Forensic Integrity Audit, npm run build | M2, M3, M4 | DONE |

## Interface Contracts & Layout
- Framework: Vue 3 (Composition API / Options API), Tailwind CSS / Custom CSS.
- Route: `#routines` / `#routines/habit-[ID]`.
- Components: Routines main view (`DailyRoutines.vue`), `HabitDetail.vue`, `MobileBottomSheet.vue`.
- Zero horizontal overflow on 360px-430px screens.
- Build output: 100% clean `npm run build`.
