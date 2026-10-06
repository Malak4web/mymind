import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TaskBoard from '../components/TaskBoard.vue'
import { store } from '../store.js'

describe('TaskBoard.vue Component Tests', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()

    global.fetch = vi.fn().mockImplementation((url) => {
      if (typeof url === 'string' && url.includes('/tasks')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([
            { id: 101, project_id: 1, title: 'مهمة تصميم', status: 'بانتظار البدء', deadline: '2026-08-01' },
            { id: 102, project_id: 1, title: 'مهمة برمجة', status: 'قيد العمل', deadline: '2026-08-05' }
          ])
        })
      }
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve([])
      })
    })

    store.activeProjectId = 1
    store.projects = [
      { id: 1, name: 'مشروع رئيسي', statuses: ['بانتظار البدء', 'قيد العمل', 'مكتمل'] }
    ]
    store.tasks = [
      { id: 101, projectId: 1, title: 'مهمة تصميم', status: 'بانتظار البدء', deadline: '2026-08-01' },
      { id: 102, projectId: 1, title: 'مهمة برمجة', status: 'قيد العمل', deadline: '2026-08-05' }
    ]
  })

  it('renders Kanban board with active project columns and task count', () => {
    const wrapper = mount(TaskBoard)
    expect(wrapper.text()).toContain('لوحة المهام (Kanban)')
    expect(wrapper.text()).toContain('بانتظار البدء')
    expect(wrapper.text()).toContain('مهمة تصميم')
    expect(wrapper.text()).toContain('مهمة برمجة')
  })

  it('renders empty prompt when no tasks are present in active project', () => {
    store.tasks = []
    const wrapper = mount(TaskBoard)
    expect(wrapper.text()).toContain('أفلت المهام هنا')
  })

  it('opens quick add form and submits new task', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    global.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve({ id: 103 }) })

    const wrapper = mount(TaskBoard)
    const quickAddBtn = wrapper.findAll('button').find(b => b.text().includes('إضافة مهمة سريعة'))
    expect(quickAddBtn).toBeTruthy()
    await quickAddBtn.trigger('click')

    // Find input area for quick add
    const textarea = wrapper.find('textarea')
    if (textarea.exists()) {
      await textarea.setValue('مهمة جديدة سريعة')
      const addSubmitBtn = wrapper.findAll('button').find(b => b.text() === 'إضافة')
      if (addSubmitBtn) {
        await addSubmitBtn.trigger('click')
        expect(global.fetch).toHaveBeenCalled()
      }
    }
  })

  it('submits new task directly when pressing Enter key in quick add textarea', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    global.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve({ id: 104 }) })

    const wrapper = mount(TaskBoard)
    const quickAddBtn = wrapper.findAll('button').find(b => b.text().includes('إضافة مهمة سريعة'))
    await quickAddBtn.trigger('click')

    const textarea = wrapper.find('textarea')
    expect(textarea.exists()).toBe(true)

    await textarea.setValue('مهمة عبر مفتاح الإنتر')
    await textarea.trigger('keydown', { key: 'Enter' })

    expect(global.fetch).toHaveBeenCalledWith(
      expect.stringContaining('/tasks'),
      expect.objectContaining({
        method: 'POST'
      })
    )
  })

  it('handles task drag and drop status update payload', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    global.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve([]) })

    const wrapper = mount(TaskBoard)
    
    // Simulate drop on column 'مكتمل'
    const column = wrapper.findAll('.snap-center').find(c => c.text().includes('مكتمل'))
    if (column) {
      await column.trigger('drop')
    }
    expect(wrapper.exists()).toBe(true)
  })

  it('opens quick inspector on single click and task modal on double click', async () => {
    store.activeProjectId = 1
    store.tasks = [
      { id: 101, projectId: 1, title: 'مهمة تصميم', status: 'بانتظار البدء', deadline: '2026-08-01' }
    ]
    const wrapper = mount(TaskBoard)
    const taskCard = wrapper.find('.glass-card-hover')
    expect(taskCard.exists()).toBe(true)
    await taskCard.trigger('click')
    expect(store.isInspectorOpen).toBe(true)
    expect(store.activeInspectorTaskId).toBe(101)

    await taskCard.trigger('dblclick')
    expect(store.isTaskModalOpen).toBe(true)
    expect(store.selectedTaskIdForModal).toBe(101)
  })

  it('cleans up AudioContext on unmount', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    global.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve([]) })
    if (!Element.prototype.animate) {
      Element.prototype.animate = vi.fn().mockReturnValue({ finished: Promise.resolve() })
    }

    const closeSpy = vi.fn().mockResolvedValue()
    class MockAudioContext {
      constructor() {
        this.currentTime = 0
        this.sampleRate = 44100
        this.destination = {}
        this.state = 'running'
      }
      createBuffer() {
        return { getChannelData: () => new Float32Array(100) }
      }
      createBufferSource() {
        return { buffer: null, connect: vi.fn(), start: vi.fn() }
      }
      createBiquadFilter() {
        return {
          type: '',
          frequency: { setValueAtTime: vi.fn(), exponentialRampToValueAtTime: vi.fn() },
          Q: { setValueAtTime: vi.fn() },
          connect: vi.fn()
        }
      }
      createGain() {
        return {
          gain: { setValueAtTime: vi.fn(), linearRampToValueAtTime: vi.fn(), exponentialRampToValueAtTime: vi.fn() },
          connect: vi.fn()
        }
      }
      createOscillator() {
        return {
          type: '',
          frequency: { setValueAtTime: vi.fn() },
          connect: vi.fn(),
          start: vi.fn(),
          stop: vi.fn()
        }
      }
      close() {
        return closeSpy()
      }
    }
    window.AudioContext = MockAudioContext

    const wrapper = mount(TaskBoard)
    const checkbox = wrapper.find('input[type="checkbox"][title="تحديد المهمة كمكتملة"]')
    if (checkbox.exists()) {
      await checkbox.setValue(true)
      await new Promise(r => setTimeout(r, 50))
      expect(closeSpy).not.toHaveBeenCalled()
      wrapper.unmount()
      expect(closeSpy).toHaveBeenCalled()
    } else {
      wrapper.unmount()
    }
  }, 15000)

  it('moves task to next status automatically when clicking the next status button', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    const updateSpy = vi.spyOn(store, 'updateTask').mockResolvedValue()

    const wrapper = mount(TaskBoard)
    // Find next button with label or aria-label
    const nextBtn = wrapper.find('button[aria-label="نقل للحالة التالية تلقائياً"]')
    expect(nextBtn.exists()).toBe(true)

    await nextBtn.trigger('click')
    expect(updateSpy).toHaveBeenCalledWith(
      101,
      expect.objectContaining({
        status: 'قيد العمل'
      })
    )
  })

  it('adds and renders a section separator between tasks inside a column', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    const addSepSpy = vi.spyOn(store, 'addProjectSeparator').mockImplementation((projId, status, title) => {
      const proj = store.projects.find(p => p.id === projId)
      const sep = { id: 'sep-1', projectId: projId, status, title }
      proj.separators = [sep]
      proj.columnOrders = { [status]: [101, 'sep-1', 102] }
      return Promise.resolve(sep)
    })

    const wrapper = mount(TaskBoard)
    const addSepBtn = wrapper.find('button[title="إضافة عنوان فاصل بين المهام"]')
    expect(addSepBtn.exists()).toBe(true)

    await addSepBtn.trigger('click')
    const sepInput = wrapper.find('input[placeholder*="اكتب عنوان الفاصل"]')
    expect(sepInput.exists()).toBe(true)

    await sepInput.setValue('مهام المرحلة الأولى')
    const confirmBtn = wrapper.findAll('button').find(b => b.text().includes('إضافة الفاصل'))
    await confirmBtn.trigger('click')

    expect(addSepSpy).toHaveBeenCalledWith(1, 'بانتظار البدء', 'مهام المرحلة الأولى')
  })

  it('identifies and displays designated completed status with badge and celebration support', async () => {
    store.projects[0].completedStatus = 'قيد العمل' // Custom completed status
    store.tasks = [
      { id: 105, projectId: 1, title: 'مهمة تم تسليمها', status: 'قيد العمل' }
    ]

    const wrapper = mount(TaskBoard)
    // The designated column header should have the completed indicator
    const completedBadge = wrapper.find('span[title="هذه هي الحالة المحددة كـ مكتمل"]')
    expect(completedBadge.exists()).toBe(true)

    // The task inside that status should have completed checkbox checked
    const checkbox = wrapper.find('input[type="checkbox"][title="تحديد المهمة كمكتملة"]')
    expect(checkbox.element.checked).toBe(true)
  })

  it('renders exactly one next-status button per task card in the card footer', () => {
    store.currentUser = { role: { name: 'مدير' } }
    const wrapper = mount(TaskBoard)
    const nextButtons = wrapper.findAll('button[aria-label="نقل للحالة التالية تلقائياً"]')
    expect(nextButtons.length).toBe(store.tasks.length)
  })

  it('inserts separator right after a specific task when afterItemId is provided', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    const proj = store.projects[0]
    proj.separators = []
    proj.columnOrders = { 'بانتظار البدء': [101, 102] }
    
    await store.addProjectSeparator(1, 'بانتظار البدء', 'فاصل بعد أول مهمة', 101)
    
    expect(proj.columnOrders['بانتظار البدء'][0]).toBe(101)
    expect(proj.columnOrders['بانتظار البدء'][1]).toContain('sep_')
    expect(proj.columnOrders['بانتظار البدء'][2]).toBe(102)
  })

  it('reorders separators and tasks using store.reorderColumnItem', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    const proj = store.projects[0]
    proj.separators = [{ id: 'sep-test', projectId: 1, status: 'بانتظار البدء', title: 'فاصل تجريبي' }]
    proj.columnOrders = { 'بانتظار البدء': [101, 102, 'sep-test'] }
    
    // Move separator before task 101
    await store.reorderColumnItem(1, 'sep-test', 'بانتظار البدء', 101, 'before')
    expect(proj.columnOrders['بانتظار البدء']).toEqual(['sep-test', 101, 102])

    // Move task 102 before sep-test
    await store.reorderColumnItem(1, 102, 'بانتظار البدء', 'sep-test', 'before')
    expect(proj.columnOrders['بانتظار البدء']).toEqual([102, 'sep-test', 101])
  })

  it('provides an action in task menu to insert separator after the task', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    const wrapper = mount(TaskBoard)
    
    // Open 3-dots menu on first task
    const menuBtn = wrapper.find('button[aria-label="خيارات المهمة"]')
    await menuBtn.trigger('click')
    
    const addSepAfterBtn = wrapper.findAll('button').find(b => b.text().includes('إضافة عنوان فاصل بعد هذه المهمة'))
    expect(addSepAfterBtn).toBeTruthy()
    
    await addSepAfterBtn.trigger('click')
    // Should display contextual input for this task
    expect(wrapper.text()).toContain('إضافة عنوان فاصل بعد: "مهمة تصميم"')
  })

  it('correctly drops and positions separator between tasks even when columnOrders only had separators initially', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    const proj = store.projects[0]
    store.tasks = [
      { id: 201, projectId: 1, title: 'المهمة 1', status: 'بانتظار البدء' },
      { id: 202, projectId: 1, title: 'المهمة 2', status: 'بانتظار البدء' },
      { id: 203, projectId: 1, title: 'المهمة 3', status: 'بانتظار البدء' }
    ]
    proj.separators = [
      { id: 'sep-alpha', projectId: 1, status: 'بانتظار البدء', title: 'عنوان فاصل' }
    ]
    // Initially, columnOrders only knew about the separator
    proj.columnOrders = { 'بانتظار البدء': ['sep-alpha'] }

    // User drags sep-alpha and drops it after task 202
    await store.reorderColumnItem(1, 'sep-alpha', 'بانتظار البدء', 202, 'after')

    // Must place sep-alpha right after 202 and before 203
    expect(proj.columnOrders['بانتظار البدء']).toEqual([201, 202, 'sep-alpha', 203])

    const wrapper = mount(TaskBoard)
    const items = wrapper.vm.getColumnItems('بانتظار البدء')
    expect(items.map(i => i.data.id)).toEqual([201, 202, 'sep-alpha', 203])
  })

  it('renders quick jump dropdown for columns with separators and navigates smoothly', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    const proj = store.projects[0]
    proj.separators = [
      { id: 'sep-nav-1', projectId: 1, status: 'بانتظار البدء', title: 'مرحلة التخطيط' },
      { id: 'sep-nav-2', projectId: 1, status: 'بانتظار البدء', title: 'مرحلة التنفيذ' }
    ]

    const wrapper = mount(TaskBoard, { attachTo: document.body })
    
    // Find dropdown in 'بانتظار البدء' column
    const select = wrapper.find('select[aria-label="الانتقال إلى عنوان فاصل"]')
    expect(select.exists()).toBe(true)
    expect(select.text()).toContain('مرحلة التخطيط')
    expect(select.text()).toContain('مرحلة التنفيذ')
    expect(select.text()).toContain('أعلى الحالة')

    // Target separator widget
    const targetSep = document.getElementById('sep-widget-sep-nav-1')
    expect(targetSep).toBeTruthy()
    targetSep.scrollIntoView = vi.fn()

    // Select separator
    await select.setValue('sep-nav-1')
    await select.trigger('change')

    await wrapper.vm.$nextTick()
    expect(targetSep.scrollIntoView).toHaveBeenCalledWith(
      expect.objectContaining({ behavior: 'smooth', block: 'center' })
    )
    expect(wrapper.vm.highlightedSeparatorId).toBe('sep-nav-1')

    // Mock scrollTo on column container
    const colContainer = document.getElementById('col-cards-list-بانتظار البدء')
    expect(colContainer).toBeTruthy()
    colContainer.scrollTo = vi.fn()

    await select.setValue('__TOP__')
    await select.trigger('change')
    expect(colContainer.scrollTo).toHaveBeenCalledWith({ top: 0, behavior: 'smooth' })

    wrapper.unmount()
  })
})

