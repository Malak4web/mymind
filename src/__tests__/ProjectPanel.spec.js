import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ProjectPanel from '../components/ProjectPanel.vue'
import { store } from '../store.js'

describe('ProjectPanel.vue Component Tests', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()

    store.activeProjectId = 1
    store.activeCategoryId = null
    store.projectCategories = [
      { id: 1, name: 'تطوير', color: '#8b5cf6', icon: '🚀', projects_count: 2 },
      { id: 2, name: 'تسويق', color: '#f59e0b', icon: '🎯', projects_count: 1 }
    ]
    store.projects = [
      { id: 1, name: 'مشروع الويب', categoryId: 1, categoryName: 'تطوير', statuses: ['بانتظار البدء'], isDeleted: false },
      { id: 2, name: 'مشروع الموبايل', categoryId: 1, categoryName: 'تطوير', statuses: ['قيد العمل'], isDeleted: false },
      { id: 3, name: 'مشروع الإعلانات', categoryId: 2, categoryName: 'تسويق', statuses: ['مكتمل'], isDeleted: false }
    ]
    store.users = [
      { id: 1, name: 'خالد', email: 'khaled@mymind.com', roleName: 'مدير' },
      { id: 2, name: 'سارة', email: 'sara@mymind.com', roleName: 'عضو' }
    ]
  })

  it('renders categories pills and projects list', () => {
    const wrapper = mount(ProjectPanel)
    expect(wrapper.text()).toContain('التصنيفات')
    expect(wrapper.text()).toContain('تطوير')
    expect(wrapper.text()).toContain('مشروع الويب')
    expect(wrapper.text()).toContain('مشروع الإعلانات')
  })

  it('displays correct task count for each project status badge when expanded', async () => {
    store.projects[0].statuses = ['بانتظار البدء', 'قيد العمل']
    store.tasks = [
      { id: 1, projectId: 1, title: 'تاسك 1', status: 'بانتظار البدء' },
      { id: 2, projectId: 1, title: 'تاسك 2', status: 'بانتظار البدء' },
      { id: 3, projectId: 1, title: 'تاسك 3', status: 'قيد العمل' }
    ]
    const wrapper = mount(ProjectPanel)
    const webProjectCard = wrapper.findAll('.glass-card-hover').find(c => c.text().includes('مشروع الويب'))
    expect(webProjectCard).toBeTruthy()

    // Default is collapsed - status counts should not be visible yet
    expect(webProjectCard.text()).not.toContain('بانتظار البدء')

    // Click arrow to expand details
    const expandBtn = webProjectCard.find('button[aria-label="عرض تفاصيل المشروع"]')
    expect(expandBtn.exists()).toBe(true)
    await expandBtn.trigger('click')

    expect(webProjectCard.text()).toContain('بانتظار البدء')
    expect(webProjectCard.text()).toContain('2')
    expect(webProjectCard.text()).toContain('قيد العمل')
    expect(webProjectCard.text()).toContain('1')
  })

  it('filters projects by category dropdown and provides compact "+" button', async () => {
    const wrapper = mount(ProjectPanel)

    // Select category "تسويق" (id: 2) from dropdown
    const select = wrapper.find('select[aria-label="فلترة المشاريع حسب التصنيف"]')
    expect(select.exists()).toBe(true)
    
    // Check "+" button exists with icon only
    const addCatBtn = wrapper.find('button[aria-label="إضافة تصنيف جديد"]')
    expect(addCatBtn.exists()).toBe(true)
    expect(addCatBtn.text()).toBe('＋')

    await select.setValue('2')
    await select.trigger('change')

    expect(store.activeCategoryId).toBe(2)
    // Only "مشروع الإعلانات" should be shown
    expect(wrapper.text()).toContain('مشروع الإعلانات')
    expect(wrapper.text()).not.toContain('مشروع الويب')
  })

  it('filters projects in real-time when typing in project search input', async () => {
    const wrapper = mount(ProjectPanel)
    const searchInput = wrapper.find('input[placeholder="ابحث عن مشروع..."]')
    expect(searchInput.exists()).toBe(true)

    await searchInput.setValue('موبايل')
    expect(wrapper.text()).toContain('مشروع الموبايل')
    expect(wrapper.text()).not.toContain('مشروع الإعلانات')
  })

  it('submits new project form when name is provided', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    global.fetch = vi.fn((url, opts) => {
      if (opts && opts.method === 'POST') {
        return Promise.resolve({ ok: true, json: () => Promise.resolve({ id: 99, name: 'مشروع الذكاء الاصطناعي' }) })
      }
      return Promise.resolve({ ok: true, json: () => Promise.resolve([{ id: 99, name: 'مشروع الذكاء الاصطناعي', statuses: ['بانتظار البدء'] }]) })
    })

    const wrapper = mount(ProjectPanel)
    const nameInput = wrapper.find('input[placeholder="مثال: تطبيق الويب..."]')
    await nameInput.setValue('مشروع الذكاء الاصطناعي')

    const createBtn = wrapper.findAll('button').find(b => b.text() === 'إنشاء المشروع الجديد')
    await createBtn.trigger('click')

    expect(global.fetch).toHaveBeenCalledWith(
      `${store.apiBase}/projects`,
      expect.objectContaining({ method: 'POST' })
    )
  })

  it('prevents project creation if project name is empty', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    const wrapper = mount(ProjectPanel)
    const createBtn = wrapper.findAll('button').find(b => b.text() === 'إنشاء المشروع الجديد')
    
    expect(createBtn.attributes('disabled')).toBeDefined()
  })

  it('allows editing project name inline and persists changes', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    vi.spyOn(store, 'renameProject').mockResolvedValue(true)

    const wrapper = mount(ProjectPanel)
    const webProjectCard = wrapper.findAll('.glass-card-hover').find(c => c.text().includes('مشروع الويب'))
    expect(webProjectCard).toBeTruthy()

    // Click edit project name button ✏️
    const editBtn = webProjectCard.find('button[aria-label="تعديل اسم المشروع"]')
    expect(editBtn.exists()).toBe(true)
    await editBtn.trigger('click')

    // Find input and submit new name
    const editInput = webProjectCard.find('input[placeholder="اسم المشروع الجديد..."]')
    expect(editInput.exists()).toBe(true)
    await editInput.setValue('مشروع الويب المطور')
    await editInput.trigger('keyup.enter')

    expect(store.renameProject).toHaveBeenCalledWith(1, 'مشروع الويب المطور')
  })

  it('asks before deleting a project, and does nothing if the user backs out', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    global.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve([]) })
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(false)

    const wrapper = mount(ProjectPanel)
    const webProjectCard = wrapper.findAll('.glass-card-hover').find(c => c.text().includes('مشروع الويب'))
    
    // First expand card to access delete button
    const expandBtn = webProjectCard.find('button[aria-label="عرض تفاصيل المشروع"]')
    await expandBtn.trigger('click')

    const deleteBtn = webProjectCard.find('button[aria-label="نقل المشروع لسلة المهملات"]')
    expect(deleteBtn.exists()).toBe(true)

    await deleteBtn.trigger('click')

    expect(confirmSpy).toHaveBeenCalled()
    expect(global.fetch).not.toHaveBeenCalledWith(
      expect.stringContaining('/projects/'),
      expect.objectContaining({ method: 'DELETE' })
    )
    confirmSpy.mockRestore()
  })

  it('handles soft deleting a project once confirmed', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    global.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve([]) })
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true)

    const wrapper = mount(ProjectPanel)
    const webProjectCard = wrapper.findAll('.glass-card-hover').find(c => c.text().includes('مشروع الويب'))

    // First expand card to access delete button
    const expandBtn = webProjectCard.find('button[aria-label="عرض تفاصيل المشروع"]')
    await expandBtn.trigger('click')

    const deleteBtn = webProjectCard.find('button[aria-label="نقل المشروع لسلة المهملات"]')
    expect(deleteBtn.exists()).toBe(true)

    await deleteBtn.trigger('click')

    expect(global.fetch).toHaveBeenCalledWith(
      expect.stringContaining('/projects/'),
      expect.objectContaining({ method: 'DELETE' })
    )
    confirmSpy.mockRestore()
  })
})
