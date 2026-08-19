import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import TaskModal from '../components/TaskModal.vue'
import QuickInspector from '../components/QuickInspector.vue'
import MentionInput from '../components/MentionInput.vue'
import ProjectDocuments from '../components/ProjectDocuments.vue'
import App from '../App.vue'
import { store } from '../store.js'

/**
 * Regression guards for defects that silently destroyed user work or made
 * core interactions unreachable. Each of these failed before its fix.
 */
describe('regressions', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()
    global.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve([]) })

    store.startRealtimeSync = vi.fn()
    store.stopRealtimeSync = vi.fn()
    // Neutralise the global watch(activeProjectId) so it cannot race the assertions.
    store.loadTasks = vi.fn()
    store.loadFolders = vi.fn()
    store.loadProjectFiles = vi.fn()
    store.loadNotes = vi.fn()
    store.loadMessages = vi.fn()
    store.loadTrashedProjects = vi.fn()

    store.isAuthenticated = true
    store.currentUser = { id: 1, name: 'مختبِر', role: { name: 'مدير' } }
    store.activeProjectId = 1
    store.projects = [{ id: 1, name: 'مشروع', statuses: ['بانتظار البدء', 'مكتمل'], customFields: [], memberIds: [1] }]
    store.taskTemplates = []
    store.tasks = [{
      id: 7, projectId: 1, title: 'عنوان أصلي', description: 'وصف أصلي',
      status: 'بانتظار البدء', startDate: '', deadline: '',
      attachments: [], comments: [], customFieldValues: {}
    }]
  })

  // Simulates what store.loadTasks() does: rebuild the array with new objects.
  const backgroundRefresh = () => { store.tasks = store.tasks.map(t => ({ ...t })) }

  describe('DL-01 — a background refresh must not overwrite what the user is typing', () => {
    it('TaskModal keeps the in-progress description', async () => {
      store.selectedTaskIdForModal = 7
      store.isTaskModalOpen = true

      const wrapper = mount(TaskModal)
      await nextTick()

      const inputs = wrapper.findAllComponents(MentionInput)
      const desc = inputs[inputs.length - 1]
      desc.vm.$emit('update:modelValue', 'نص طويل كتبه المستخدم ولم يحفظه بعد')
      await nextTick()

      backgroundRefresh()
      await nextTick(); await nextTick()

      expect(wrapper.findAllComponents(MentionInput)[inputs.length - 1].props('modelValue'))
        .toBe('نص طويل كتبه المستخدم ولم يحفظه بعد')
    })

    it('QuickInspector keeps the in-progress description', async () => {
      store.activeInspectorTaskId = 7
      store.isInspectorOpen = true

      const wrapper = mount(QuickInspector)
      await nextTick()

      const ta = wrapper.findAll('textarea')[0]
      await ta.setValue('ملاحظات مراجعة لم تُحفظ بعد')

      backgroundRefresh()
      await nextTick(); await nextTick()

      expect(wrapper.findAll('textarea')[0].element.value).toBe('ملاحظات مراجعة لم تُحفظ بعد')
    })
  })

  describe('DL-02 — the sheet dismiss gesture lives on the handle, not the scrollable panel', () => {
    it('TaskModal binds touch handlers to the grab handle only', () => {
      store.selectedTaskIdForModal = 7
      store.isTaskModalOpen = true
      const wrapper = mount(TaskModal)

      const touchEls = wrapper.findAll('[class*="touch-none"]')
      expect(touchEls.length).toBeGreaterThan(0)

      // No element may be both the scroll container and the gesture target:
      // scrolling past the threshold would close the sheet and drop the edits.
      const scrollers = wrapper.findAll('[class*="overflow-y-auto"]')
      for (const el of scrollers) {
        const cls = el.attributes('class') || ''
        expect(cls).not.toContain('touch-none')
      }
    })
  })

  describe('MB-01 — a single tap on a task must do something below xl', () => {
    it('the inspector is rendered as a sheet, not hidden, when open', async () => {
      store.activeInspectorTaskId = 7
      store.isInspectorOpen = true
      store.activeView = 'kanban'
      store.isFocusMode = false

      const wrapper = mount(App, {
        global: {
          stubs: {
            TaskBoard: true, TaskList: true, TaskCalendar: true, ProjectDocuments: true,
            Login: true, Settings: true, DailyRoutines: true, HabitDetail: true,
            MobileBottomNav: true, ProjectPanel: true, NotificationCenter: true, TaskModal: true
          }
        }
      })
      await nextTick()

      const host = wrapper.find('[role="dialog"][aria-label="تفاصيل المهمة"]')
      expect(host.exists()).toBe(true)

      const cls = host.attributes('class') || ''
      // Visible on small screens (fixed sheet) and docked at xl.
      expect(cls).not.toMatch(/(^|\s)hidden(\s|$)/)
      expect(cls).toContain('fixed')
      expect(cls).toContain('xl:static')

      wrapper.unmount()
    })
  })

  describe('BUG-02 — a cyclic folder chain must not hang the breadcrumbs', () => {
    it('terminates on a self-referencing folder', () => {
      store.folders = [{ id: 1, name: 'مجلد', parent_id: 1 }]
      store.projectFiles = []
      store.notes = []
      store.activeDocumentFolderId = 1

      // Before the cycle guard this spun forever and froze the tab.
      const wrapper = mount(ProjectDocuments)
      expect(wrapper.exists()).toBe(true)
      wrapper.unmount()
    })

    it('terminates on a two-folder cycle', () => {
      store.folders = [
        { id: 1, name: 'أ', parent_id: 2 },
        { id: 2, name: 'ب', parent_id: 1 }
      ]
      store.projectFiles = []
      store.notes = []
      store.activeDocumentFolderId = 1

      const wrapper = mount(ProjectDocuments)
      expect(wrapper.exists()).toBe(true)
      wrapper.unmount()
    })
  })

  describe('DL-03 — comments go to the server', () => {
    it('posts the comment instead of keeping it in local state', async () => {
      const spy = vi.spyOn(store, 'addComment').mockResolvedValue({
        id: 1, task_id: 7, user_id: 1, author_name: 'مختبِر',
        body: 'تعليق حقيقي', created_at: new Date().toISOString()
      })

      store.activeInspectorTaskId = 7
      store.isInspectorOpen = true
      const wrapper = mount(QuickInspector)
      await nextTick()

      const inputs = wrapper.findAllComponents(MentionInput)
      const commentBox = inputs[inputs.length - 1]
      commentBox.vm.$emit('update:modelValue', 'تعليق حقيقي')
      await nextTick()

      const sendBtn = wrapper.findAll('button').find(b => b.text().includes('إرسال'))
      expect(sendBtn).toBeTruthy()
      await sendBtn.trigger('click')
      await nextTick()

      expect(spy).toHaveBeenCalledWith(7, 'تعليق حقيقي')
    })
  })
})

describe('feedback loop', () => {
  beforeEach(() => {
    store.toasts = []
  })

  it('shows a toast and removes it on dismiss', () => {
    const id = store.toast('تم الحفظ', 'success', { timeout: 0 })
    expect(store.toasts.map(t => t.text)).toEqual(['تم الحفظ'])

    store.dismissToast(id)
    expect(store.toasts).toEqual([])
  })

  it('runs a toast action once and then clears it', () => {
    const run = vi.fn()
    const id = store.toast('تم الحذف', 'info', { timeout: 0, action: { label: 'تراجع', run } })

    store.runToastAction(id)

    expect(run).toHaveBeenCalledTimes(1)
    expect(store.toasts).toEqual([])
  })

  it('copying to the clipboard no longer writes a durable notification', () => {
    const spy = vi.spyOn(store, 'addNotification')
    store.toastSuccess('تم نسخ عنوان المهمة إلى الحافظة')

    expect(spy).not.toHaveBeenCalled()
    expect(store.toasts).toHaveLength(1)
  })

  it('honours the celebration preference and reduced motion', () => {
    store.celebrationsEnabled = true
    vi.spyOn(store, 'prefersReducedMotion').mockReturnValue(false)
    expect(store.shouldCelebrate()).toBe(true)

    store.celebrationsEnabled = false
    expect(store.shouldCelebrate()).toBe(false)

    store.celebrationsEnabled = true
    store.prefersReducedMotion.mockReturnValue(true)
    expect(store.shouldCelebrate()).toBe(false)
  })
})
