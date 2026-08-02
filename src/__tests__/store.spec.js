import { describe, it, expect, beforeEach, vi } from 'vitest'
import { store } from '../store.js'

describe('store.js State & Actions Unit Tests', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()

    // Reset store state to clean defaults
    store.token = ''
    store.isAuthenticated = false
    store.currentUser = null
    store.projects = []
    store.projectCategories = []
    store.tasks = []
    store.folders = []
    store.projectFiles = []
    store.notes = []
    store.notifications = []
    store.emailQueue = []
    store.batchedEmails = []
    store.activeProjectId = null
    store.activeCategoryId = null
    store.activeView = 'kanban'
    store.isFocusMode = false
    store.theme = 'light'
    store.pushPermission = 'default'
    store.isSidebarCollapsed = false
    store.isInspectorOpen = false
    store.activeInspectorTaskId = null
  })

  describe('1. State Initialization & Auth Logic', () => {
    it('should initialize with correct default state', () => {
      expect(store.isAuthenticated).toBe(false)
      expect(store.currentUser).toBeNull()
      expect(store.projects).toEqual([])
      expect(store.globalStatuses).toContain('بانتظار البدء')
    })

    it('init() should handle unauthenticated state when token is empty', async () => {
      store.token = ''
      await store.init()
      expect(store.isAuthenticated).toBe(false)
    })

    it('init() should load profile and data on valid token', async () => {
      store.token = 'valid-token'
      const mockUser = { id: 1, name: 'أحمد', email: 'ahmed@mymind.com', role: { name: 'مدير', permissions: [] } }

      global.fetch = vi.fn((url) => {
        if (url.endsWith('/profile')) {
          return Promise.resolve({ ok: true, json: () => Promise.resolve(mockUser) })
        }
        return Promise.resolve({ ok: true, json: () => Promise.resolve([]) })
      })

      await store.init()

      expect(store.isAuthenticated).toBe(true)
      expect(store.currentUser).toEqual(mockUser)
    })

    it('init() should logout if profile request returns 401/error', async () => {
      store.token = 'invalid-token'
      global.fetch = vi.fn().mockResolvedValue({ ok: false, status: 401 })

      await store.init()

      expect(store.isAuthenticated).toBe(false)
      expect(store.token).toBe('')
      expect(store.currentUser).toBeNull()
    })

    it('logout() should clear authentication and state variables', () => {
      store.token = 'some-token'
      store.isAuthenticated = true
      store.currentUser = { id: 1 }
      store.projects = [{ id: 10 }]

      store.logout()

      expect(store.token).toBe('')
      expect(store.isAuthenticated).toBe(false)
      expect(store.currentUser).toBeNull()
      expect(store.projects).toEqual([])
    })
  })

  describe('2. Permission Checks (hasPermission)', () => {
    it('should return false if currentUser is null or has no role', () => {
      store.currentUser = null
      expect(store.hasPermission('manage-projects')).toBe(false)

      store.currentUser = { role: null }
      expect(store.hasPermission('manage-projects')).toBe(false)
    })

    it('should return true for Admin role regardless of permission slug', () => {
      store.currentUser = { role: { name: 'مدير', permissions: [] } }
      expect(store.hasPermission('manage-projects')).toBe(true)
      expect(store.hasPermission('manage-tasks')).toBe(true)
    })

    it('should check permissions slug array for non-admin roles', () => {
      store.currentUser = {
        role: {
          name: 'عضو',
          permissions: [{ slug: 'manage-tasks' }]
        }
      }

      expect(store.hasPermission('manage-tasks')).toBe(true)
      expect(store.hasPermission('manage-projects')).toBe(false)
    })
  })

  describe('3. Project Management Actions', () => {
    beforeEach(() => {
      store.currentUser = { role: { name: 'مدير' } }
    })

    it('createProject() should send POST request and refresh projects', async () => {
      global.fetch = vi.fn((url, opts) => {
        if (opts && opts.method === 'POST') {
          return Promise.resolve({ ok: true, json: () => Promise.resolve({ id: 101, name: 'مشروع جديد' }) })
        }
        return Promise.resolve({ ok: true, json: () => Promise.resolve([{ id: 101, name: 'مشروع جديد' }]) })
      })

      await store.createProject('مشروع جديد', 'وصف المشروع', null, [], null)

      expect(global.fetch).toHaveBeenCalledWith(
        `${store.apiBase}/projects`,
        expect.objectContaining({ method: 'POST' })
      )
      expect(store.activeProjectId).toBe(101)
    })

    it('updateProject() should send PUT request with updated data', async () => {
      global.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve([]) })

      await store.updateProject(101, 'اسم معدل', 'وصف جديد', [1, 2], null)

      expect(global.fetch).toHaveBeenCalledWith(
        `${store.apiBase}/projects/101`,
        expect.objectContaining({ method: 'PUT' })
      )
    })

    it('deleteProject() and restoreProject() should invoke delete/restore endpoints', async () => {
      store.projects = [{ id: 50, name: 'مشروع للتجربة' }]
      global.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve([]) })

      await store.deleteProject(50)
      expect(global.fetch).toHaveBeenCalledWith(`${store.apiBase}/projects/50`, expect.objectContaining({ method: 'DELETE' }))

      await store.restoreProject(50)
      expect(global.fetch).toHaveBeenCalledWith(`${store.apiBase}/projects/50/restore`, expect.objectContaining({ method: 'POST' }))
    })

    it('reorderProjects() should reorder projects array and save order to localStorage', () => {
      store.projects = [{ id: 1 }, { id: 2 }, { id: 3 }]
      store.reorderProjects(0, 2)

      expect(store.projects.map(p => p.id)).toEqual([2, 3, 1])
      expect(localStorage.getItem('mymind_projects_order')).toBe(JSON.stringify([2, 3, 1]))
    })
  })

  describe('4. Task Management Actions & Drag-and-Drop Deadline Payload', () => {
    beforeEach(() => {
      store.currentUser = { role: { name: 'مدير' } }
      store.activeProjectId = 1
    })

    it('createTask() should send task payload including custom fields', async () => {
      global.fetch = vi.fn((url) => {
        if (url.includes('/tasks') && !url.includes('/custom-fields')) {
          return Promise.resolve({ ok: true, json: () => Promise.resolve({ id: 88, title: 'مهمة تستر' }) })
        }
        return Promise.resolve({ ok: true, json: () => Promise.resolve({}) })
      })

      await store.createTask('مهمة تستر', 'وصف المهمة', 'بانتظار البدء', '2026-08-01', '2026-08-10', { '1': 'قيمة' })

      expect(global.fetch).toHaveBeenCalledWith(
        `${store.apiBase}/projects/1/tasks`,
        expect.objectContaining({ method: 'POST' })
      )
    })

    it('updateTask() should handle status & deadline updates (drag-and-drop payload)', async () => {
      global.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve([]) })

      const updates = {
        title: 'مهمة منقولة',
        status: 'مكتمل',
        deadline: '2026-08-15',
        projectId: 1
      }

      await store.updateTask(88, updates)

      expect(global.fetch).toHaveBeenCalledWith(
        `${store.apiBase}/tasks/88`,
        expect.objectContaining({
          method: 'PUT',
          body: JSON.stringify({
            title: 'مهمة منقولة',
            description: undefined,
            status: 'مكتمل',
            start_date: undefined,
            deadline: '2026-08-15',
            project_id: 1
          })
        })
      )
    })

    it('deleteTask() should send DELETE request for given task ID', async () => {
      store.tasks = [{ id: 88, title: 'حذف هذه' }]
      global.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve([]) })

      await store.deleteTask(88)

      expect(global.fetch).toHaveBeenCalledWith(`${store.apiBase}/tasks/88`, expect.objectContaining({ method: 'DELETE' }))
    })
  })

  describe('5. Habits & Routines Actions (Local Storage State)', () => {
    beforeEach(() => {
      store.habits = []
    })

    it('addHabit() should append new habit and save to localStorage', () => {
      const initialCount = store.habits.length
      const newHabit = store.addHabit({ title: 'قراءة قرآن', category: 'روحانيات' })

      expect(store.habits.length).toBe(initialCount + 1)
      expect(newHabit.title).toBe('قراءة قرآن')
      expect(localStorage.getItem('mymind_habits')).toBeTruthy()
    })

    it('toggleHabitLog() should update numeric and boolean habits', () => {
      const habit = store.addHabit({ title: 'خطوات', type: 'numeric', targetValue: 1000 })
      const dateStr = '2026-07-30'

      store.toggleHabitLog(habit.id, dateStr, 500)
      expect(store.habits.find(h => h.id === habit.id).logs[dateStr].count).toBe(500)

      store.toggleHabitLog(habit.id, dateStr, 1200)
      expect(store.habits.find(h => h.id === habit.id).logs[dateStr].completed).toBe(true)
    })

    it('deleteHabit() should remove habit by ID', () => {
      const habit = store.addHabit({ title: 'مؤقتة' })
      store.deleteHabit(habit.id)

      expect(store.habits.some(h => h.id === habit.id)).toBe(false)
    })
  })

  describe('6. Edge Cases & Error Handling', () => {
    it('actions should prevent execution if user lacks permission', async () => {
      const alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => {})
      store.currentUser = { role: { name: 'مشاهد', permissions: [] } }

      await store.createProject('غير مسموح', '')
      expect(alertSpy).toHaveBeenCalledWith('غير مصرح لك بإنشاء مشاريع.')

      await store.createTask('غير مسموح', '', 'مكتمل', null, null)
      expect(alertSpy).toHaveBeenCalledWith('غير مصرح لك بإنشاء مهام.')
    })

    it('addNotification() should handle network failure and fall back to local notification', async () => {
      global.fetch = vi.fn().mockRejectedValue(new Error('Network offline'))

      await store.addNotification('تنبيه أوفلاين', 'نص التنبيه')

      expect(store.notifications.length).toBeGreaterThan(0)
      expect(store.notifications[0].title).toBe('تنبيه أوفلاين')
    })

    it('toggleTheme() should alternate theme between light and dark', () => {
      store.theme = 'light'
      store.toggleTheme()
      expect(store.theme).toBe('dark')
      expect(localStorage.getItem('mymind_theme')).toBe('dark')

      store.toggleTheme()
      expect(store.theme).toBe('light')
    })
  })

  describe('7. Standardized Auth Headers & Coercion Edge Cases', () => {
    it('getAuthHeaders() returns Authorization Bearer when token is present and omits it when empty', () => {
      store.token = ''
      expect(store.getAuthHeaders({ 'Content-Type': 'application/json' })).toEqual({ 'Content-Type': 'application/json' })

      store.token = 'abc-123'
      expect(store.getAuthHeaders({ 'Content-Type': 'application/json' })).toEqual({
        'Content-Type': 'application/json',
        'Authorization': 'Bearer abc-123'
      })
    })

    it('updateTask() matches string taskId against numeric task.id and preserves existing fields', async () => {
      store.currentUser = { role: { name: 'مدير' } }
      store.tasks = [
        { id: 88, projectId: 1, title: 'عنوان قديم', description: 'وصف قديم', status: 'بانتظار البدء', deadline: '2026-08-01' }
      ]
      global.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve([]) })

      await store.updateTask('88', { status: 'مكتمل' })

      expect(global.fetch).toHaveBeenCalledWith(
        `${store.apiBase}/tasks/88`,
        expect.objectContaining({
          method: 'PUT',
          body: JSON.stringify({
            title: 'عنوان قديم',
            description: 'وصف قديم',
            status: 'مكتمل',
            start_date: undefined,
            deadline: '2026-08-01',
            project_id: 1
          })
        })
      )
    })

    it('nested habit mutations reassign store.habits array to trigger Vue reactivity', () => {
      const habit = store.addHabit({ title: 'عادة باختبار التفرع' })
      const originalHabitsRef = store.habits

      const note = store.addHabitNote(String(habit.id), 'ملاحظة جديدة')
      expect(store.habits).not.toBe(originalHabitsRef) // Reference changed for reactivity
      expect(habit.notesList.length).toBe(1)

      const habitsRef2 = store.habits
      const item = store.addHabitChecklistItem(String(habit.id), 'عنصر في القائمة')
      expect(store.habits).not.toBe(habitsRef2)
      expect(habit.checklist.length).toBe(1)

      const habitsRef3 = store.habits
      store.toggleHabitChecklistItem(String(habit.id), String(item.id))
      expect(store.habits).not.toBe(habitsRef3)
      expect(item.completed).toBe(true)

      const habitsRef4 = store.habits
      store.deleteHabitChecklistItem(String(habit.id), String(item.id))
      expect(store.habits).not.toBe(habitsRef4)
      expect(habit.checklist.length).toBe(0)

      const habitsRef5 = store.habits
      store.deleteHabitNote(String(habit.id), String(note.id))
      expect(store.habits).not.toBe(habitsRef5)
      expect(habit.notesList.length).toBe(0)
    })
  })

  describe('8. Layout State & Inspector Actions', () => {
    it('toggleSidebar() should toggle isSidebarCollapsed and persist to localStorage', () => {
      store.isSidebarCollapsed = false
      store.toggleSidebar()
      expect(store.isSidebarCollapsed).toBe(true)
      expect(localStorage.getItem('mymind_sidebar_collapsed')).toBe('true')

      store.toggleSidebar()
      expect(store.isSidebarCollapsed).toBe(false)
      expect(localStorage.getItem('mymind_sidebar_collapsed')).toBe('false')
    })

    it('openTaskInspector() and closeTaskInspector() should toggle active task inspector state', () => {
      store.openTaskInspector(101)
      expect(store.isInspectorOpen).toBe(true)
      expect(store.activeInspectorTaskId).toBe(101)

      store.closeTaskInspector()
      expect(store.isInspectorOpen).toBe(false)
      expect(store.activeInspectorTaskId).toBeNull()
    })
  })
})
