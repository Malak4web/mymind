# Project Context & Environment

## Workspace Details
- Root Directory: `c:\xampp\htdocs\mymind`
- Orchestrator Directory: `c:\xampp\htdocs\mymind\.agents\orchestrator`
- Framework: Vue 3 (SFC), Vite, Tailwind CSS v4
- Dependencies: `@tailwindcss/vite`, `@vitejs/plugin-vue`, `vitest`, `@vue/test-utils`
- Build Command: `npm run build`
- Test Command: `npm run test` / `npx vitest run`

## Key Requirements Reference
- **R1 Desktop Layout**: Wide-screen desktop layout (1440px+), multi-column structure (Sidebar, Main Workspace, Inspector/Quick Details panel, Topbar).
- **R2 Modern Glassmorphism & Ergonomics**: Translucent glass surfaces, high-contrast typography, micro-animations, single-click shortcuts.
- **R3 Build Integrity**: Clean build (`npm run build`), passing test suite, zero regressions.

## Parent Communication
- Parent: Sentinel (`ebb6ab9b-0b26-40d8-8cdd-4f109e888baa`)
- Progress updates sent via `send_message` to `ebb6ab9b-0b26-40d8-8cdd-4f109e888baa`.
