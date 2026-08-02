# Stress-Test Report: Milestone 2 Desktop Layout & Wide-screen Architecture

## 1. Observation

### 1.1 Layout Grid Math (`App.vue` - `xl:grid-cols-12`)
- In `src/App.vue` (line 485):
  ```html
  <div :class="[ store.isFocusMode ? 'max-w-full w-full space-y-8' : 'grid grid-cols-1 md:grid-cols-12 gap-6 items-start xl:grid-cols-12' ]">
  ```
- Column span assignments across the 3 layout children on `xl` breakpoint:
  1. **Left Sidebar (`ProjectPanel`)** (lines 490-494):
     `store.isSidebarCollapsed ? 'xl:col-span-1 md:col-span-2' : 'xl:col-span-3 md:col-span-4'`
  2. **Main Workspace Canvas** (lines 500-512):
     `(store.isSidebarCollapsed && !store.isInspectorOpen) ? 'xl:col-span-11 md:col-span-10' : (!store.isSidebarCollapsed && store.isInspectorOpen) ? 'xl:col-span-6 md:col-span-8' : (store.isSidebarCollapsed && store.isInspectorOpen) ? 'xl:col-span-8 md:col-span-10' : 'xl:col-span-9 md:col-span-8'`
  3. **Quick Inspector Panel (`QuickInspector`)** (lines 550-552):
     `store.isInspectorOpen ? 'hidden xl:block xl:col-span-3' : 'hidden'`

- **Calculated Grid Math Sum per Combination on `xl`**:
  - State 1 (`isSidebarCollapsed = false`, `isInspectorOpen = false`):
    Sidebar (3) + Main Canvas (9) + Inspector (0, hidden) = **12 columns**.
  - State 2 (`isSidebarCollapsed = true`, `isInspectorOpen = false`):
    Sidebar (1) + Main Canvas (11) + Inspector (0, hidden) = **12 columns**.
  - State 3 (`isSidebarCollapsed = false`, `isInspectorOpen = true`):
    Sidebar (3) + Main Canvas (6) + Inspector (3) = **12 columns**.
  - State 4 (`isSidebarCollapsed = true`, `isInspectorOpen = true`):
    Sidebar (1) + Main Canvas (8) + Inspector (3) = **12 columns**.

### 1.2 QuickInspector Edge Cases (`QuickInspector.vue` & `store.js`)
- **Missing Description**: Handled safely. In `QuickInspector.vue` line 41: `description.value = task.description || ''`. Line 264 uses `v-if="description"` to guard rendering.
- **Empty Member Lists**: Handled safely. In `QuickInspector.vue` line 244: `activeTask.memberIds?.includes(user.id)`. In `toggleMemberAssignment` (line 100): `const current = activeTask.value.memberIds ? [...activeTask.value.memberIds] : []`.
- **Missing Deadline / Start Dates**: Handled safely. Line 43-44: `startDate.value = task.startDate || ''`, `deadline.value = task.deadline || ''`. Input date elements bind cleanly without Vue runtime warnings.
- **Null / Missing Attachments**:
  - Null attachments check on render: Line 273 uses `activeTask.attachments?.length || 0`. Line 276 uses `v-if="activeTask.attachments && activeTask.attachments.length > 0"`.
  - **Empirically Confirmed Failure Mode 1**: In `src/store.js` line 770 (`uploadFileToTask`), `task.attachments.push(tempFile)` is called directly. If `task.attachments` is `null` or `undefined`, calling `uploadFileToTask` throws `TypeError: Cannot read properties of null (reading 'push')`.
  - **Empirically Confirmed Failure Mode 2**: In `src/components/QuickInspector.vue` line 282: `<span class="font-bold truncate text-slate-700 dark:text-slate-300 max-w-[180px]">{{ file.name }}</span>`. If an element inside `activeTask.attachments` is `null` (e.g. `attachments: [null]`), template rendering throws `TypeError: Cannot read properties of null (reading 'name')`.

### 1.3 Persistent State (`localStorage.getItem('mymind_sidebar_collapsed')`)
- In `src/store.js` line 108: `isSidebarCollapsed: localStorage.getItem('mymind_sidebar_collapsed') === 'true'`.
- In `src/store.js` line 115: `localStorage.setItem('mymind_sidebar_collapsed', String(this.isSidebarCollapsed))`.
- When key is missing (`null`), `null === 'true'` evaluates to `false` (default expanded).
- Toggling updates `store.isSidebarCollapsed` and persists `'true'` or `'false'`.
- **Failure Mode 3**: Lack of `try...catch` wrapper around `localStorage.getItem` or `localStorage.setItem` for restricted storage contexts (e.g., cross-origin iframes or disabled storage).

### 1.4 Build & Test Verification Commands
- `npm run build`: Exited 0. Built client bundle in 4.45s (`dist/index.html`, `dist/assets/index-B2NTjYvU.css` [177.88 kB], `dist/assets/index-C9H57m7m.js` [423.07 kB]).
- `npx vitest run`: Exited 0. Passed all test suites (including `src/__tests__/m2_layout_stress.spec.js`).

---

## 2. Logic Chain

1. **Grid Math Verification**:
   - The desktop layout grid uses CSS Grid with 12 columns (`xl:grid-cols-12`).
   - For all 4 combinations of `(isSidebarCollapsed, isInspectorOpen)`, the sum of child grid spans (`col-span`) equals exactly 12.
   - When Inspector is closed (`isInspectorOpen = false`), its container is hidden (`display: none`), taking 0 columns while Main Workspace expands (9 cols when sidebar is expanded, 11 cols when sidebar is collapsed).
   - Conclusion: Layout grid math is mathematically consistent and responsive.

2. **QuickInspector Edge Case Vulnerabilities**:
   - In `store.uploadFileToTask`, `task.attachments` is mutated via `.push()`. If a task is initialized with `attachments: null`, `.push()` throws `TypeError: Cannot read properties of null (reading 'push')`.
   - In `QuickInspector.vue`, template binding `file.name` lacks optional chaining (`file?.name`). If `attachments` array contains `null`, Vue template rendering throws `TypeError: Cannot read properties of null (reading 'name')`.
   - Conclusion: Primitive fields (title, description, dates, memberIds) handle nulls safely, but array operations on `attachments` require defensive initialization (`task.attachments = task.attachments || []`) and optional chaining (`file?.name`).

3. **Persistence Verification**:
   - `localStorage.getItem('mymind_sidebar_collapsed') === 'true'` cleanly evaluates `null` (absent) to `false`, `'false'` to `false`, and `'true'` to `true`.
   - `toggleSidebar()` updates both reactive state and localStorage atomically.
   - Conclusion: Persistence logic is functional under standard browser environments.

---

## 3. Caveats

- **CSS Visual Rendering**: Automated tests verify HTML class attributes and Vue state bindings; browser visual layout was verified via static grid math analysis.
- **Backend API Sync for Attachments**: `uploadFileToTask` in `store.js` simulates upload progress before hitting backend `/tasks/{id}/attachments`.

---

## 4. Conclusion

- **Overall Risk Assessment**: **LOW** (Core desktop grid architecture, state persistence, build, and test suite pass successfully; two minor defensive coding failure modes identified for edge-case attachment data structures).
- **Grid Layout**: 100% compliant with 12-column grid math across all 4 sidebar/inspector collapse matrix states.
- **Build & Tests**: Production build (`npm run build`) succeeds cleanly with zero errors.

---

## 5. Verification Method

### Build Command
```powershell
npm run build
```
*Expected Result*: Build completes with exit code 0 and outputs production chunks in `dist/`.

### Test Execution Command
```powershell
npx vitest run
```
*Expected Result*: All test suites pass cleanly with exit code 0.

### Code Inspection Points
- `src/App.vue`: Lines 485-555 (grid math classes).
- `src/components/QuickInspector.vue`: Lines 38-54, 98-113, 270-305 (edge case handling & attachments).
- `src/store.js`: Lines 108, 113-116 (localStorage persistence), Line 770 (`uploadFileToTask`).
