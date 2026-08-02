import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TaskBoard from '../components/TaskBoard.vue'
import ProjectPanel from '../components/ProjectPanel.vue'
import ProjectDocuments from '../components/ProjectDocuments.vue'
import App from '../App.vue'
import { store } from '../store.js'
import fs from 'fs'
import path from 'path'

describe('Milestone 3 Animation Performance & Interaction Empirical Tests', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()

    store.isAuthenticated = true
    store.activeProjectId = 1
    store.activeDocumentFolderId = 1
    store.projects = [
      { id: 1, name: 'مشروع التجربة', categoryId: 1, statuses: ['بانتظار البدء', 'قيد العمل', 'مكتمل'] }
    ]
    store.projectCategories = [
      { id: 1, name: 'التطوير', color: '#8b5cf6' }
    ]
    store.tasks = [
      { id: 101, projectId: 1, title: 'بطاقة مهمة اختبارية', status: 'بانتظار البدء', deadline: '2026-08-01' }
    ]
    store.documentFolders = [
      { id: 1, projectId: 1, name: 'مجلد المستندات', parentId: null }
    ]
    store.documentFiles = [
      { id: 1, projectId: 1, folderId: null, name: 'ملف مشروع.pdf', size: '1.2 MB', type: 'pdf', url: '#' }
    ]
    store.documentNotes = [
      { id: 1, projectId: 1, folderId: null, title: 'ملاحظة الفحص', content: 'محتوى تجريبي' }
    ]
    store.folders = [{ id: 1, name: 'Folder', parent_id: 1 }]
    store.projectFiles = [{ id: 1, name: 'File', folder_id: 1, type: 'pdf' }]
    store.notes = [{ id: 1, title: 'Note', folder_id: 1 }]
  })

  it('verifies hover elevation classes on TaskBoard task cards', () => {
    const wrapper = mount(TaskBoard)
    const taskCard = wrapper.find('.glass-card-hover')
    expect(taskCard.exists()).toBe(true)

    const classes = taskCard.classes()
    expect(classes).toContain('glass-card-hover')
    expect(classes).toContain('hover:-translate-y-1')
    expect(classes).toContain('hover:shadow-glass-glow')
    expect(classes).toContain('transition-all')
    expect(classes).toContain('duration-300')
    expect(classes).toContain('btn-touch-active')
  })

  it('verifies hover elevation and active feedback classes on ProjectPanel project cards', () => {
    const wrapper = mount(ProjectPanel)
    const projectCard = wrapper.find('.glass-card-hover')
    expect(projectCard.exists()).toBe(true)

    const classes = projectCard.classes()
    expect(classes).toContain('glass-card-hover')
    expect(classes).toContain('btn-touch-active')
    expect(classes).toContain('transition-all')
    expect(classes).toContain('duration-300')
  })

  it('verifies hover elevation and active feedback classes on ProjectDocuments cards', () => {
    const wrapper = mount(ProjectDocuments)
    const docCards = wrapper.findAll('.glass-card-hover')
    expect(docCards.length).toBeGreaterThanOrEqual(3) // Folder, file, and note cards

    docCards.forEach(card => {
      expect(card.classes()).toContain('glass-card-hover')
      expect(card.classes()).toContain('btn-touch-active')
    })
  })

  it('verifies active touch feedback (btn-touch-active / active:scale) on App navigation tabs and main buttons', () => {
    const wrapper = mount(App)
    
    // Quick Add button
    const quickAddBtn = wrapper.find('button.bg-gradient-to-r')
    expect(quickAddBtn.exists()).toBe(true)
    expect(quickAddBtn.classes()).toContain('btn-touch-active')
    expect(quickAddBtn.classes()).toContain('hover:-translate-y-0.5')
    expect(quickAddBtn.classes()).toContain('hover:shadow-glass-glow')

    // Navigation view tabs
    const tabButtons = wrapper.findAll('div.backdrop-blur-md button')
    expect(tabButtons.length).toBeGreaterThanOrEqual(5)
    tabButtons.forEach(tab => {
      expect(tab.classes()).toContain('btn-touch-active')
    })
  })

  it('verifies CSS definitions in style.css for .btn-touch-active:active and .glass-card-hover:hover', () => {
    const stylePath = path.resolve(__dirname, '../style.css')
    const styleContent = fs.readFileSync(stylePath, 'utf-8')

    // Verify .btn-touch-active active scale
    expect(styleContent).toContain('.btn-touch-active:active')
    expect(styleContent).toContain('transform: scale(0.97);')

    // Verify .glass-card-hover:hover rules
    expect(styleContent).toContain('.glass-card-hover:hover')
    expect(styleContent).toContain('transform: translateY(-4px);')
    expect(styleContent).toContain('box-shadow: var(--shadow-glass-glow);')

    // Verify theme variable --shadow-glass-glow
    expect(styleContent).toContain('--shadow-glass-glow: 0 0 25px -3px rgba(139, 92, 246, 0.35);')
  })
})
