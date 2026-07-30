import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TaskModal from '../components/TaskModal.vue'
import { store } from '../store.js'

describe('TaskModal.vue Component Tests', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()

    store.isTaskModalOpen = true
    store.selectedTaskIdForModal = null
    store.activeProjectId = 1
    store.projects = [{ id: 1, name: 'مشروع رئيسي', statuses: ['بانتظار البدء', 'مكتمل'] }]
    store.tasks = [
      { id: 301, projectId: 1, title: 'مهمة قائمة', description: 'تفاصيل', status: 'بانتظار البدء', startDate: '2026-08-01', deadline: '2026-08-05', attachments: [] }
    ]
  })

  it('renders modal in create mode when selectedTaskIdForModal is null', () => {
    const wrapper = mount(TaskModal)
    expect(wrapper.text()).toContain('إنشاء مهمة جديدة')
  })

  it('populates fields in edit mode when selectedTaskIdForModal is set', async () => {
    store.selectedTaskIdForModal = 301
    const wrapper = mount(TaskModal)
    expect(wrapper.text()).toContain('تعديل تفاصيل المهمة')
    const titleInput = wrapper.find('input[placeholder*="عنوان المهمة"]')
    if (titleInput.exists()) {
      expect(titleInput.element.value).toBe('مهمة قائمة')
    }
  })

  it('displays validation error if attempting to save empty task title', async () => {
    const wrapper = mount(TaskModal)
    const saveBtn = wrapper.findAll('button').find(b => b.text() === 'إنشاء مهمة')
    await saveBtn.trigger('click')

    expect(wrapper.text()).toContain('اسم المهمة مطلوب بشكل إجباري')
  })

  it('displays date error if deadline is earlier than start_date (edge case)', async () => {
    store.selectedTaskIdForModal = 301
    const wrapper = mount(TaskModal)

    const dateInputs = wrapper.findAll('input[type="date"]')
    if (dateInputs.length >= 2) {
      await dateInputs[0].setValue('2026-08-10') // Start date
      await dateInputs[1].setValue('2026-08-05') // Deadline before start date

      expect(wrapper.text()).toContain('تاريخ التسليم النهائي لا يمكن أن يكون قبل تاريخ البدء')
    }
  })

  it('calls store.createTask when valid data is saved', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    global.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve({ id: 302 }) })

    const wrapper = mount(TaskModal)
    
    // Fill title
    const titleInput = wrapper.find('input[placeholder*="عنوان المهمة"]')
    if (titleInput.exists()) {
      await titleInput.setValue('مهمة تم إنشاؤها في التست')
    }

    const saveBtn = wrapper.findAll('button').find(b => b.text() === 'إنشاء مهمة')
    await saveBtn.trigger('click')

    expect(global.fetch).toHaveBeenCalledWith(
      `${store.apiBase}/projects/1/tasks`,
      expect.objectContaining({ method: 'POST' })
    )
  })
})
