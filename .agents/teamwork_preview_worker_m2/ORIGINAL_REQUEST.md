## 2026-07-31T00:23:15Z
You are a Worker agent (`teamwork_preview_worker`) for project "mymind".
Your working directory is: c:\xampp\htdocs\mymind\.agents\teamwork_preview_worker_m2
Your task is to implement Milestone 2: Desktop Layout & Wide-screen Architecture Restructuring.

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A Forensic Auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Refer to the exploration reports in:
- `c:\xampp\htdocs\mymind\.agents\teamwork_preview_explorer_m1_1\handoff.md`
- `c:\xampp\htdocs\mymind\.agents\teamwork_preview_explorer_m1_3\handoff.md`

Tasks:
1. Extend `src/store.js`:
   - Add reactive layout state: `isSidebarCollapsed` (persisted to `localStorage.setItem('mymind_sidebar_collapsed', ...)`), `isInspectorOpen` (boolean), `activeInspectorTaskId` (null or taskId).
   - Add actions: `toggleSidebar()`, `openTaskInspector(taskId)`, `closeTaskInspector()`.

2. Create `src/components/QuickInspector.vue`:
   - Dedicated docked right side inspector panel for wide screens (`xl:col-span-3` / ~340px glass panel).
   - Show task details (title, status picker, deadline date, assigned members, description, attachments, quick comment input stream).
   - Provide a button "فتح النموذج الكامل ↗" to open full `TaskModal.vue`.
   - Provide close button `x` calling `store.closeTaskInspector()`.

3. Update `src/components/ProjectPanel.vue`:
   - Support `store.isSidebarCollapsed` mode:
     - Expanded state: render full project list & categories.
     - Collapsed state: render sleek icon rail displaying category icons, project initial badges with tooltips, compact create trigger, and expand button.

4. Update `src/App.vue`:
   - Header Bar: Add sidebar toggle button (`<<` / `>>`), breadcrumb trail (`المشروع > [اسم مشروع]` or view name), quick search trigger input button (`Ctrl+K`), quick create button (`+ إضافة جديدة`), view navigation tabs.
   - Dynamic 3-Column Responsive Desktop Grid (`xl:grid-cols-12`):
     - Left Sidebar: `store.isSidebarCollapsed ? 'xl:col-span-1' : 'xl:col-span-3'`
     - Main Workspace Canvas: `(store.isSidebarCollapsed && !store.isInspectorOpen) ? 'xl:col-span-11' : (!store.isSidebarCollapsed && store.isInspectorOpen) ? 'xl:col-span-6' : (store.isSidebarCollapsed && store.isInspectorOpen) ? 'xl:col-span-8' : 'xl:col-span-9'`
     - Quick Inspector Panel (`<QuickInspector />`): `store.isInspectorOpen ? 'xl:col-span-3' : 'hidden'`.

5. Update task click handlers in `TaskBoard.vue`, `TaskList.vue`, `TaskCalendar.vue`:
   - Single click on a task card opens `QuickInspector` (`store.openTaskInspector(task.id)`).
   - Double click or edit icon opens `TaskModal`.

6. Run build & tests:
   - Run `npm run build` using terminal command.
   - Run `npx vitest run` using terminal command.

Write your implementation report to `c:\xampp\htdocs\mymind\.agents\teamwork_preview_worker_m2\handoff.md` and send a message to parent when completed.
