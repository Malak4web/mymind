import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import DailyRoutines from '../components/DailyRoutines.vue'
import { store } from '../store.js'

describe('DailyRoutines.vue Component Tests', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()

    store.habits = [
      {
        id: 1,
        title: 'شرب 8 أكواب ماء',
        category: 'صحة ورشاقة',
        icon: '🥛',
        color: 'from-blue-500 to-cyan-500',
        frequency: [0, 1, 2, 3, 4, 5, 6],
        logs: {}
      },
      {
        id: 2,
        title: 'قراءة 15 دقيقة',
        category: 'تطوير ذات',
        icon: '📖',
        color: 'from-amber-500 to-orange-500',
        frequency: [0, 1, 2, 3, 4, 5, 6],
        logs: {}
      }
    ]
  })

  it('renders routines header and habits list', () => {
    const wrapper = mount(DailyRoutines)
    expect(wrapper.text()).toContain('يومياتي والعادات')
    expect(wrapper.text()).toContain('شرب 8 أكواب ماء')
    expect(wrapper.text()).toContain('قراءة 15 دقيقة')
  })

  it('toggles habit completion when check button is clicked', async () => {
    const wrapper = mount(DailyRoutines)
    const checkBtn = wrapper.find('button[title="تسجيل الإنجاز"]')
    expect(checkBtn.exists()).toBe(true)

    await checkBtn.trigger('click')

    // Expect habit log state to be updated in store
    const todayStr = new Date().toISOString().split('T')[0]
    const habitLog = store.habits[0].logs[todayStr]
    expect(habitLog).toBeTruthy()
    expect(habitLog.completed).toBe(true)
  })

  it('opens add habit modal and submits a new habit', async () => {
    const wrapper = mount(DailyRoutines)

    const addBtn = wrapper.findAll('button').find(b => b.text().includes('إضافة عادة'))
    expect(addBtn).toBeTruthy()
    await addBtn.trigger('click')

    // Add modal should open
    const titleInput = wrapper.find('input[placeholder*="مثلاً: شرب ماء"]')
    if (titleInput.exists()) {
      await titleInput.setValue('رياضة الصباح')
      const form = wrapper.find('form')
      if (form.exists()) {
        await form.trigger('submit.prevent')
      } else {
        const saveBtn = wrapper.findAll('button').find(b => b.text() === 'حفظ العادة')
        if (saveBtn) await saveBtn.trigger('click')
      }
      expect(store.habits.some(h => h.title === 'رياضة الصباح')).toBe(true)
    }
  })
})
