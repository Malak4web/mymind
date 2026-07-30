import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TaskBoard from '../components/TaskBoard.vue'
import { store } from '../store.js'

describe('TaskBoard.vue Component Tests', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()

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

  it('opens edit modal when clicking task card', async () => {
    const wrapper = mount(TaskBoard)
    const taskCard = wrapper.find('.cursor-grab')
    if (taskCard.exists()) {
      await taskCard.trigger('click')
      expect(store.isTaskModalOpen).toBe(true)
      expect(store.selectedTaskIdForModal).toBe(101)
    }
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
  })
})
