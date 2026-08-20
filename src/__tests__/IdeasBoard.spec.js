import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import IdeasBoard from '../components/IdeasBoard.vue'
import DailyRoutines from '../components/DailyRoutines.vue'
import { store } from '../store.js'

describe('IdeasBoard.vue and My Ideas Feature Tests', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()

    store.ideas = [
      {
        id: 1,
        title: 'فكرة تصميم جديدة',
        content: 'شرح وتفاصيل الفكرة الأولى مع وسوم وملاحظات هامة',
        images: [
          { id: 101, url: 'https://example.com/mock1.png', name: 'mock1.png' }
        ],
        color: 'amber',
        category: 'إبداع',
        is_pinned: true,
        idea_date: '2026-08-20',
        sort_order: 0,
        createdAt: new Date().toISOString()
      },
      {
        id: 2,
        title: 'فكرة مشروع ذكي',
        content: 'تطوير حلول برمجية مبتكرة لإدارة المشاريع',
        images: [],
        color: 'violet',
        category: 'مشاريع',
        is_pinned: false,
        idea_date: '2026-08-21',
        sort_order: 1,
        createdAt: new Date().toISOString()
      }
    ]

    store.ideaCategories = ['عام', 'إبداع', 'مشاريع', 'محتوى', 'شخصي']
  })

  it('renders ideas board with header and idea cards', () => {
    const wrapper = mount(IdeasBoard)
    expect(wrapper.text()).toContain('أفكاري وملاحظاتي الذكية')
    expect(wrapper.text()).toContain('فكرة تصميم جديدة')
    expect(wrapper.text()).toContain('فكرة مشروع ذكي')
    expect(wrapper.text()).toContain('إبداع')
    expect(wrapper.text()).toContain('مشاريع')
  })

  it('filters ideas by search query', async () => {
    const wrapper = mount(IdeasBoard)
    const searchInput = wrapper.find('input[placeholder*="ابحث في الأفكار"]')
    expect(searchInput.exists()).toBe(true)

    await searchInput.setValue('تصميم')
    expect(wrapper.text()).toContain('فكرة تصميم جديدة')
    expect(wrapper.text()).not.toContain('فكرة مشروع ذكي')
  })

  it('filters ideas by category and color', async () => {
    const wrapper = mount(IdeasBoard)

    // Category filter
    const select = wrapper.find('select')
    await select.setValue('مشاريع')
    expect(wrapper.text()).toContain('فكرة مشروع ذكي')
    expect(wrapper.text()).not.toContain('فكرة تصميم جديدة')

    await select.setValue('all')

    // Color filter
    const violetColorBtn = wrapper.findAll('button').find(b => b.text().includes('بنفسجي'))
    if (violetColorBtn) {
      await violetColorBtn.trigger('click')
      expect(wrapper.text()).toContain('فكرة مشروع ذكي')
      expect(wrapper.text()).not.toContain('فكرة تصميم جديدة')
    }
  })

  it('toggles pin state on an idea', async () => {
    const wrapper = mount(IdeasBoard)
    const pinBtn = wrapper.findAll('button[title*="تثبيت"]').find(b => b.exists())
    expect(pinBtn).toBeTruthy()

    const initialPin = store.ideas.find(i => i.id === 2).is_pinned
    expect(initialPin).toBe(false)

    await store.togglePinIdea(2)
    const updated = store.ideas.find(i => i.id === 2)
    expect(updated.is_pinned).toBe(true)
  })

  it('copies full content to clipboard with copyAllContent', async () => {
    const writeTextMock = vi.fn().mockResolvedValue()
    Object.assign(navigator, {
      clipboard: {
        writeText: writeTextMock
      }
    })

    const wrapper = mount(IdeasBoard)
    const copyBtn = wrapper.findAll('button').find(b => b.text().includes('نسخ المحتوى'))
    expect(copyBtn).toBeTruthy()

    await copyBtn.trigger('click')
    expect(writeTextMock).toHaveBeenCalled()
    expect(writeTextMock.mock.calls[0][0]).toContain('فكرة تصميم جديدة')
  })

  it('adds a new idea to store correctly', async () => {
    const newIdea = await store.addIdea({
      title: 'فكرة تجريبية مضافة',
      content: 'محتوى تجريبي',
      category: 'عام',
      color: 'emerald',
      is_pinned: false
    })

    expect(store.ideas.some(i => i.title === 'فكرة تجريبية مضافة')).toBe(true)
    expect(newIdea.title).toBe('فكرة تجريبية مضافة')
  })

  it('deletes an idea from store', async () => {
    expect(store.ideas.length).toBe(2)
    await store.deleteIdea(1)
    expect(store.ideas.length).toBe(1)
    expect(store.ideas.find(i => i.id === 1)).toBeUndefined()
  })

  it('manages idea categories in store', () => {
    const added = store.addIdeaCategory('استثمار')
    expect(added).toBe(true)
    expect(store.ideaCategories).toContain('استثمار')

    // Prevent duplicate
    expect(store.addIdeaCategory('استثمار')).toBe(false)

    // Delete custom category
    expect(store.deleteIdeaCategory('استثمار')).toBe(true)
    expect(store.ideaCategories).not.toContain('استثمار')

    // Protect 'عام'
    expect(store.deleteIdeaCategory('عام')).toBe(false)
  })

  it('renders ideas tab in DailyRoutines segmented control', async () => {
    const wrapper = mount(DailyRoutines)
    const ideasTabBtn = wrapper.findAll('button').find(b => b.text().includes('أفكاري'))
    expect(ideasTabBtn).toBeTruthy()

    await ideasTabBtn.trigger('click')
    expect(wrapper.text()).toContain('أفكاري وملاحظاتي الذكية')
    expect(wrapper.text()).toContain('فكرة جديدة')
  })
})
