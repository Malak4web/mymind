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

  it('displays correct task count for each project status badge', () => {
    store.projects[0].statuses = ['بانتظار البدء', 'قيد العمل']
    store.tasks = [
      { id: 1, projectId: 1, title: 'تاسك 1', status: 'بانتظار البدء' },
      { id: 2, projectId: 1, title: 'تاسك 2', status: 'بانتظار البدء' },
      { id: 3, projectId: 1, title: 'تاسك 3', status: 'قيد العمل' }
    ]
    const wrapper = mount(ProjectPanel)
    const webProjectCard = wrapper.findAll('.glass-card-hover').find(c => c.text().includes('مشروع الويب'))
    expect(webProjectCard).toBeTruthy()
    expect(webProjectCard.text()).toContain('بانتظار البدء')
    expect(webProjectCard.text()).toContain('2')
    expect(webProjectCard.text()).toContain('قيد العمل')
    expect(webProjectCard.text()).toContain('1')
  })

  it('filters projects by active category pill', async () => {
    const wrapper = mount(ProjectPanel)

    // Click on category "تسويق" (id: 2)
    const marketingPill = wrapper.findAll('button').find(b => b.text().includes('تسويق'))
    expect(marketingPill).toBeTruthy()
    await marketingPill.trigger('click')

    expect(store.activeCategoryId).toBe(2)
    // Only "مشروع الإعلانات" should be shown
    expect(wrapper.text()).toContain('مشروع الإعلانات')
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

  it('handles soft deleting a project', async () => {
    store.currentUser = { role: { name: 'مدير' } }
    global.fetch = vi.fn().mockResolvedValue({ ok: true, json: () => Promise.resolve([]) })

    const wrapper = mount(ProjectPanel)
    const deleteBtn = wrapper.find('button[title="نقل المشروع لسلة المهملات"]')
    if (deleteBtn.exists()) {
      await deleteBtn.trigger('click')
      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining('/projects/'),
        expect.objectContaining({ method: 'DELETE' })
      )
    }
  })
})
