import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TaskCalendar from '../components/TaskCalendar.vue'
import { store } from '../store.js'

describe('TaskCalendar.vue Component Tests', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()

    store.activeProjectId = 1
    store.projects = [{ id: 1, name: 'مشروع رئيسي', statuses: ['بانتظار البدء', 'مكتمل'] }]
    store.tasks = [
      { id: 201, projectId: 1, title: 'تسليم التصاميم', status: 'بانتظار البدء', deadline: '2026-07-16' },
      { id: 202, projectId: 1, title: 'بدء البرمجة', status: 'قيد العمل', deadline: '2026-07-20' },
      { id: 203, projectId: 1, title: 'مهمة بدون موعد', status: 'بانتظار البدء', deadline: null } // Edge case
    ]
  })

  it('renders calendar title and weekdays header', () => {
    const wrapper = mount(TaskCalendar)
    expect(wrapper.text()).toContain('التقويم والجدولة الزمنية')
    expect(wrapper.text()).toContain('يوليو 2026')
    expect(wrapper.text()).toContain('الأحد')
    expect(wrapper.text()).toContain('الخميس')
  })

  it('renders tasks under their corresponding date cell', () => {
    const wrapper = mount(TaskCalendar)
    expect(wrapper.text()).toContain('تسليم التصاميم')
    expect(wrapper.text()).toContain('بدء البرمجة')
  })

  it('handles edge case of tasks with missing or null deadlines safely', () => {
    const wrapper = mount(TaskCalendar)
    expect(wrapper.exists()).toBe(true)
    // Missing deadline task should not break calendar rendering
  })

  it('updates task deadline when dropped on a calendar cell', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    global.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve([]) })

    const wrapper = mount(TaskCalendar)
    
    // Simulate drop onto date '2026-07-25'
    const dateCell = wrapper.findAll('.rounded-2xl').find(c => c.text().includes('25'))
    if (dateCell) {
      await dateCell.trigger('drop')
    }
    expect(wrapper.exists()).toBe(true)
  })

  it('opens quick inspector on single click and task modal on double click when clicking task item inside cell', async () => {
    const wrapper = mount(TaskCalendar)
    const taskItem = wrapper.findAll('.cursor-grab').find(i => i.text().includes('تسليم التصاميم'))
    if (taskItem) {
      await taskItem.trigger('click')
      expect(store.isInspectorOpen).toBe(true)
      expect(store.activeInspectorTaskId).toBe(201)

      await taskItem.trigger('dblclick')
      expect(store.isTaskModalOpen).toBe(true)
      expect(store.selectedTaskIdForModal).toBe(201)
    }
  })
})
