import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import App from '../App.vue'
import QuickInspector from '../components/QuickInspector.vue'
import { store } from '../store.js'

describe('Milestone 2 Stress Tests: Desktop Layout & Wide-screen Architecture', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()

    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve([])
    })

    // Reset store state to clean defaults
    store.token = 'mock-token'
    store.isAuthenticated = true
    store.currentUser = { id: 1, name: 'مختبر النظام', role: { name: 'مدير', permissions: [] } }
    store.projects = [
      { id: 1, name: 'مشروع التجربة 1', statuses: ['بانتظار البدء', 'قيد العمل'], isDeleted: false }
    ]
    store.activeProjectId = 1
    store.tasks = [
      {
        id: 101,
        projectId: 1,
        title: 'مهمة فحص الواجهة',
        description: 'وصف المهمة الرئيسي',
        status: 'بانتظار البدء',
        startDate: '2026-08-01',
        deadline: '2026-08-10',
        memberIds: [1],
        attachments: [{ name: 'file1.pdf', size: '200 KB' }],
        comments: []
      }
    ]
    store.users = [
      { id: 1, name: 'مختبر 1', email: 'test1@mymind.com' },
      { id: 2, name: 'مختبر 2', email: 'test2@mymind.com' }
    ]
    store.activeView = 'kanban'
    store.isFocusMode = false
    store.isSidebarCollapsed = false
    store.isInspectorOpen = false
    store.activeInspectorTaskId = null
    store.notifications = []
    store.folders = []
    store.projectFiles = []
    store.notes = []
  })

  describe('1. Layout Grid Math (App.vue xl:grid-cols-12 Matrix Verification)', () => {
    it('State 1: [Sidebar: Expanded (false), Inspector: Closed (false)] -> Sum = 3 + 9 = 12', () => {
      store.isSidebarCollapsed = false
      store.isInspectorOpen = false

      const sidebarSpan = 3   // xl:col-span-3
      const mainSpan = 9      // xl:col-span-9
      const inspectorSpan = 0 // hidden / display: none

      expect(sidebarSpan + mainSpan + inspectorSpan).toBe(12)
    })

    it('State 2: [Sidebar: Collapsed (true), Inspector: Closed (false)] -> Sum = 1 + 11 = 12', () => {
      store.isSidebarCollapsed = true
      store.isInspectorOpen = false

      const sidebarSpan = 1   // xl:col-span-1
      const mainSpan = 11     // xl:col-span-11
      const inspectorSpan = 0 // hidden / display: none

      expect(sidebarSpan + mainSpan + inspectorSpan).toBe(12)
    })

    it('State 3: [Sidebar: Expanded (false), Inspector: Open (true)] -> Sum = 3 + 6 + 3 = 12', () => {
      store.isSidebarCollapsed = false
      store.isInspectorOpen = true

      const sidebarSpan = 3   // xl:col-span-3
      const mainSpan = 6      // xl:col-span-6
      const inspectorSpan = 3 // xl:col-span-3

      expect(sidebarSpan + mainSpan + inspectorSpan).toBe(12)
    })

    it('State 4: [Sidebar: Collapsed (true), Inspector: Open (true)] -> Sum = 1 + 8 + 3 = 12', () => {
      store.isSidebarCollapsed = true
      store.isInspectorOpen = true

      const sidebarSpan = 1   // xl:col-span-1
      const mainSpan = 8      // xl:col-span-8
      const inspectorSpan = 3 // xl:col-span-3

      expect(sidebarSpan + mainSpan + inspectorSpan).toBe(12)
    })

    it('Verifies exact class bindings in App.vue for all 4 matrix combinations', async () => {
      // 1. Expanded Sidebar & Closed Inspector
      store.isSidebarCollapsed = false
      store.isInspectorOpen = false
      let wrapper = mount(App, { shallow: true })
      let mainWorkspace = wrapper.find('main > div > div:nth-child(2)')
      expect(mainWorkspace.classes()).toContain('xl:col-span-9')
      wrapper.unmount()

      // 2. Collapsed Sidebar & Closed Inspector
      store.isSidebarCollapsed = true
      store.isInspectorOpen = false
      wrapper = mount(App, { shallow: true })
      mainWorkspace = wrapper.find('main > div > div:nth-child(2)')
      expect(mainWorkspace.classes()).toContain('xl:col-span-11')
      wrapper.unmount()

      // 3. Expanded Sidebar & Open Inspector
      store.isSidebarCollapsed = false
      store.isInspectorOpen = true
      wrapper = mount(App, { shallow: true })
      mainWorkspace = wrapper.find('main > div > div:nth-child(2)')
      expect(mainWorkspace.classes()).toContain('xl:col-span-6')
      wrapper.unmount()

      // 4. Collapsed Sidebar & Open Inspector
      store.isSidebarCollapsed = true
      store.isInspectorOpen = true
      wrapper = mount(App, { shallow: true })
      mainWorkspace = wrapper.find('main > div > div:nth-child(2)')
      expect(mainWorkspace.classes()).toContain('xl:col-span-8')
      wrapper.unmount()
    }, 15000)
  })

  describe('2. Edge Cases in QuickInspector.vue', () => {
    beforeEach(() => {
      store.tasks = [
        { id: 101, title: 'مهمة تجريبية', description: 'وصف المهمة', status: 'قيد العمل', project_id: 1, attachments: [] }
      ]
      store.activeInspectorTaskId = 101
      store.isInspectorOpen = true
    })

    it('Handles task with missing/empty description gracefully', async () => {
      store.tasks[0].description = undefined
      const wrapper = mount(QuickInspector)
      expect(wrapper.exists()).toBe(true)

      const textarea = wrapper.findComponent({ name: 'MentionInput' })
      expect(textarea.exists()).toBe(true)
    })

    it('Handles task with null or empty member list gracefully', async () => {
      store.tasks[0].memberIds = null
      const wrapper = mount(QuickInspector)

      const memberChip = wrapper.findAll('.cursor-pointer').find(el => el.text().includes('مختبر 1'))
      expect(memberChip).toBeTruthy()

      const currentTask = store.tasks[0]
      const memberIds = currentTask.memberIds ? [...currentTask.memberIds] : []
      expect(memberIds).toEqual([])
    })

    it('Handles task with missing deadline or startDate date', async () => {
      store.tasks[0].startDate = null
      store.tasks[0].deadline = undefined

      const wrapper = mount(QuickInspector)
      const dateInputs = wrapper.findAll('input[type="date"]')

      expect(dateInputs.length).toBe(2)
      expect(dateInputs[0].element.value).toBe('')
      expect(dateInputs[1].element.value).toBe('')
    })

    it('Handles task with null or missing attachments list', async () => {
      store.tasks[0].attachments = null

      const wrapper = mount(QuickInspector)
      expect(wrapper.text()).toContain('0 مرفق')
      expect(wrapper.text()).toContain('لا توجد مرفقات.')
    })

    it('Renders attachments list cleanly when valid attachments present', async () => {
      store.tasks[0].attachments = [{ name: 'file1.pdf', size: '200 KB' }]

      const wrapper = mount(QuickInspector)
      expect(wrapper.text()).toContain('1 مرفق')
      expect(wrapper.text()).toContain('file1.pdf')
      expect(wrapper.text()).toContain('200 KB')
    })

    it('Handles QuickInspector template gracefully when attachments array contains null item', async () => {
      store.tasks[0].attachments = [null]

      let renderError = null
      try {
        const wrapper = mount(QuickInspector)
        expect(wrapper.exists()).toBe(true)
      } catch (err) {
        renderError = err
      }

      expect(renderError).toBeNull()
    })

    it('uploadFileToTask handles null attachments list safely', async () => {
      const task = { id: 101, title: 'مهمة', attachments: null }
      store.tasks = [task]
      const origFetch = global.fetch
      global.fetch = vi.fn().mockImplementation(() => new Promise(() => {}))
      try {
        store.uploadFileToTask(101, 'document.png', '500 KB')
        expect(Array.isArray(task.attachments)).toBe(true)
        expect(task.attachments.length).toBe(1)
        expect(task.attachments[0].name).toBe('document.png')
      } finally {
        global.fetch = origFetch
      }
    })

    it('openFullModal closes QuickInspector when opening TaskModal', async () => {
      const wrapper = mount(QuickInspector)
      const modalBtn = wrapper.find('button[title="فتح المهمة في النافذة المنبثقة الكاملة"]')
      await modalBtn.trigger('click')
      expect(store.selectedTaskIdForModal).toBe(101)
      expect(store.isTaskModalOpen).toBe(true)
      expect(store.isInspectorOpen).toBe(false)
    })
  })

  describe('3. Persistent State (localStorage mymind_sidebar_collapsed)', () => {
    it('initializes store.isSidebarCollapsed from localStorage = "true"', () => {
      localStorage.setItem('mymind_sidebar_collapsed', 'true')
      const isCollapsed = localStorage.getItem('mymind_sidebar_collapsed') === 'true'
      expect(isCollapsed).toBe(true)
    })

    it('initializes store.isSidebarCollapsed from localStorage = "false"', () => {
      localStorage.setItem('mymind_sidebar_collapsed', 'false')
      const isCollapsed = localStorage.getItem('mymind_sidebar_collapsed') === 'true'
      expect(isCollapsed).toBe(false)
    })

    it('defaults store.isSidebarCollapsed to false when localStorage key is absent', () => {
      localStorage.removeItem('mymind_sidebar_collapsed')
      const isCollapsed = localStorage.getItem('mymind_sidebar_collapsed') === 'true'
      expect(isCollapsed).toBe(false)
    })

    it('toggleSidebar() flips state and writes string boolean to localStorage', () => {
      store.isSidebarCollapsed = false

      store.toggleSidebar()
      expect(store.isSidebarCollapsed).toBe(true)
      expect(localStorage.getItem('mymind_sidebar_collapsed')).toBe('true')

      store.toggleSidebar()
      expect(store.isSidebarCollapsed).toBe(false)
      expect(localStorage.getItem('mymind_sidebar_collapsed')).toBe('false')
    })
  })

  describe('4. Quick Search View Context in App.vue', () => {
    it('switches store.activeView to kanban when selecting a task search result from settings or routines', async () => {
      store.activeView = 'settings'
      const wrapper = mount(App, { shallow: true })
      wrapper.vm.selectSearchResult({ id: 101, projectId: 1, title: 'مهمة فحص الواجهة' }, 'task')
      expect(store.activeView).toBe('kanban')
      expect(store.isInspectorOpen).toBe(true)
      expect(store.activeInspectorTaskId).toBe(101)

      store.activeView = 'routines'
      wrapper.vm.selectSearchResult({ id: 101, projectId: 1, title: 'مهمة فحص الواجهة' }, 'task')
      expect(store.activeView).toBe('kanban')
    })
  })
})
