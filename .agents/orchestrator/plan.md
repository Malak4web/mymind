# Execution Plan — Daily Routines ('يومياتي') Redesign

## Objective
Execute full redesign of Daily Routines ('يومياتي') screens and components in `c:\xampp\htdocs\mymind` adhering strictly to all requirements in `ORIGINAL_REQUEST.md`.

## Milestones & Strategy

### Phase 1: Exploration (M1)
- Dispatch 3 parallel Explorers (`teamwork_preview_explorer`):
  - **Explorer 1**: Inspect Routines main screen structure, components, styles, data model, and day picker logic.
  - **Explorer 2**: Inspect `HabitDetail.vue` (#routines/habit-[ID]), sub-tasks component, notes/journal logic, heatmap component, and streak calculations.
  - **Explorer 3**: Inspect modals, bottom sheets, drawer system, and mobile viewport styling / zero-scroll constraints.

### Phase 2: Implementation (M2 - M4)
- **M2 (Main Screen Redesign)**: Dispatch Implementer (`teamwork_preview_worker`) to redesign Main Screen header (glassmorphism), day picker bar, routine cards, and thumb-friendly check buttons.
- **M3 (Habit Detail View Redesign)**: Dispatch Implementer (`teamwork_preview_worker`) to redesign `HabitDetail.vue` with progress ring header + streak counter 🔥, sub-tasks checklist, habit journal notes interface, and monthly heatmap calendar grid.
- **M4 (Bottom Sheets Drawers)**: Dispatch Implementer (`teamwork_preview_worker`) to implement / refine bottom sheets for checking, stats, details, and creation.

### Phase 3: Verification & Integrity Audit (M5)
- Dispatch 2 Reviewers (`teamwork_preview_reviewer`) to verify code quality, UI elegance, zero horizontal overflow, and mobile thumb ergonomics.
- Dispatch 2 Challengers (`teamwork_preview_challenger`) to stress-test viewport responsiveness, component edge cases, sub-tasks, journal, heatmap, and verify `npm run build`.
- Dispatch Forensic Auditor (`teamwork_preview_auditor`) to verify zero cheating, genuine implementation, and clean build.

## Quality Standards
- Glassmorphism & modern mobile UI/UX styling.
- Zero horizontal scrolling on 360px-430px screens.
- Clean `npm run build` with zero errors.
