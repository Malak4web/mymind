import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import DailyRoutines from '../components/DailyRoutines.vue'
import { store } from '../store.js'

describe('DailyRoutines.vue Component Tests', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()

    // Mock navigator.vibrate if not present in test jsdom env
    if (typeof globalThis.navigator !== 'undefined') {
      globalThis.navigator.vibrate = vi.fn()
    }

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

    store.dailyTasks = [
      {
        id: 101,
        title: 'مهمة مسبقة للاختبار',
        category: 'عام',
        priority: 'متوسطة',
        dueTime: '10:00',
        completed: false,
        createdAt: new Date().toISOString()
      }
    ]

    store.dailyNotesList = {}
  })

  it('renders routines header and habits list', () => {
    const wrapper = mount(DailyRoutines)
    expect(wrapper.text()).toContain('يومياتي والعادات')
    expect(wrapper.text()).toContain('شرب 8 أكواب ماء')
    expect(wrapper.text()).toContain('قراءة 15 دقيقة')
  })

  it('toggles habit completion when check button is clicked and triggers haptic vibration', async () => {
    const wrapper = mount(DailyRoutines)
    const checkBtn = wrapper.find('button[title="تسجيل الإنجاز"]')
    expect(checkBtn.exists()).toBe(true)

    await checkBtn.trigger('click')

    // Expect habit log state to be updated in store with local date formatting key
    const now = new Date()
    const y = now.getFullYear()
    const m = String(now.getMonth() + 1).padStart(2, '0')
    const d = String(now.getDate()).padStart(2, '0')
    const todayStr = `${y}-${m}-${d}`

    const habitLog = store.habits[0].logs[todayStr]
    expect(habitLog).toBeTruthy()
    expect(habitLog.completed).toBe(true)

    // Verify haptic vibration trigger
    if (globalThis.navigator && globalThis.navigator.vibrate) {
      expect(globalThis.navigator.vibrate).toHaveBeenCalledWith(25)
    }
  })

  it('opens add habit modal and submits a new habit', async () => {
    const wrapper = mount(DailyRoutines)

    const addBtn = wrapper.findAll('button').find(b => b.text().includes('إضافة عادة'))
    expect(addBtn).toBeTruthy()
    await addBtn.trigger('click')

    // Add modal should open
    const titleInput = wrapper.find('input[placeholder*="مثلاً: شرب ماء"]')
    expect(titleInput.exists()).toBe(true)
    await titleInput.setValue('رياضة الصباح')

    const form = wrapper.find('form')
    expect(form.exists()).toBe(true)
    await form.trigger('submit')

    expect(store.habits.some(h => h.title === 'رياضة الصباح')).toBe(true)
  })

  it('switches between habits and daily tasks tabs using segmented control', async () => {
    const wrapper = mount(DailyRoutines)

    // Find tab buttons matching required Arabic texts
    const habitsTabBtn = wrapper.findAll('button').find(b => b.text().includes('العادات'))
    const journalTabBtn = wrapper.findAll('button').find(b => b.text().includes('اليوميات'))

    expect(habitsTabBtn).toBeTruthy()
    expect(journalTabBtn).toBeTruthy()

    // Switch to journal tab
    await journalTabBtn.trigger('click')
    expect(wrapper.text()).toContain('سجل اليوميات والتاسكات السريعة')

    // Switch back to habits tab
    await habitsTabBtn.trigger('click')
    expect(wrapper.text()).toContain('يومياتي والعادات')
  })

  it('handles horizontal touch/swipe gesture navigation with RTL rules and exclusion guards', async () => {
    const wrapper = mount(DailyRoutines)

    // 1. Switch to journal tab first
    const journalTabBtn = wrapper.findAll('button').find(b => b.text().includes('اليوميات'))
    await journalTabBtn.trigger('click')
    expect(wrapper.text()).toContain('سجل اليوميات والتاسكات السريعة')

    // 2. Simulate Left Swipe (deltaX < -50) on main view container: switches 'journal' -> 'habits'
    await wrapper.trigger('touchstart', {
      touches: [{ clientX: 200, clientY: 100 }]
    })
    await wrapper.trigger('touchmove', {
      touches: [{ clientX: 100, clientY: 100 }]
    })
    await wrapper.trigger('touchend', {
      changedTouches: [{ clientX: 100, clientY: 100 }]
    })

    expect(wrapper.text()).toContain('يومياتي والعادات')

    // 3. Simulate Right Swipe (deltaX > 50) on main view container: switches 'habits' -> 'journal'
    await wrapper.trigger('touchstart', {
      touches: [{ clientX: 100, clientY: 100 }]
    })
    await wrapper.trigger('touchmove', {
      touches: [{ clientX: 200, clientY: 100 }]
    })
    await wrapper.trigger('touchend', {
      changedTouches: [{ clientX: 200, clientY: 100 }]
    })

    expect(wrapper.text()).toContain('سجل اليوميات والتاسكات السريعة')

    // 4. Test Exclusion Guard: Swipe inside element with .overflow-x-auto should be ignored
    const scrollContainer = wrapper.find('.overflow-x-auto')
    expect(scrollContainer.exists()).toBe(true)
    await scrollContainer.trigger('touchstart', {
      touches: [{ clientX: 200, clientY: 100 }]
    })
    await scrollContainer.trigger('touchmove', {
      touches: [{ clientX: 100, clientY: 100 }]
    })
    await scrollContainer.trigger('touchend', {
      changedTouches: [{ clientX: 100, clientY: 100 }]
    })

    // Active tab remains 'journal' because touch was inside .overflow-x-auto
    expect(wrapper.text()).toContain('سجل اليوميات والتاسكات السريعة')
  })

  it('enforces touch target dimensions, 32-36px micro toggles, compact card classes, micro-FAB, and slim tab bar selectors', () => {
    const wrapper = mount(DailyRoutines)
    const buttons = wrapper.findAll('button')

    // Filter interactive buttons with compact touch target classes
    const interactiveButtons = buttons.filter(b => {
      const cls = b.classes().join(' ')
      return cls.includes('min-h-[32px]') || cls.includes('min-h-[36px]') || cls.includes('min-h-[44px]') || cls.includes('min-h-[48px]')
    })

    expect(interactiveButtons.length).toBeGreaterThan(0)

    // Assert container compact padding class px-1 sm:px-3
    const rootContainer = wrapper.find('div.max-w-6xl')
    expect(rootContainer.exists()).toBe(true)
    expect(rootContainer.classes()).toContain('px-1')
    expect(rootContainer.classes()).toContain('sm:px-3')

    // Assert habit card list gap-2 and card p-2.5
    const cardList = wrapper.find('div.grid.grid-cols-1')
    expect(cardList.exists()).toBe(true)
    expect(cardList.classes()).toContain('gap-2')

    const firstCard = wrapper.find('div.group.relative.rounded-2xl')
    expect(firstCard.exists()).toBe(true)
    expect(firstCard.classes()).toContain('p-2.5')

    // Check sleek 32-36px check-in toggle button
    const checkBtn = wrapper.find('button[title="تسجيل الإنجاز"]')
    expect(checkBtn.exists()).toBe(true)
    expect(checkBtn.classes()).toContain('w-9')
    expect(checkBtn.classes()).toContain('h-9')
    expect(checkBtn.classes()).toContain('min-h-[36px]')
    expect(checkBtn.classes()).toContain('min-w-[36px]')

    // Check micro floating action button (Micro-FAB)
    const microFab = wrapper.find('button.micro-fab')
    expect(microFab.exists()).toBe(true)
    expect(microFab.classes()).toContain('fixed')
    expect(microFab.classes()).toContain('bottom-4')
    expect(microFab.classes()).toContain('left-4')

    // Assert slim tab bar segmented switcher py-1 px-1.5
    const segmentedSwitcher = wrapper.find('div.relative.flex.items-center.justify-between')
    expect(segmentedSwitcher.exists()).toBe(true)
    expect(segmentedSwitcher.classes()).toContain('py-1')
    expect(segmentedSwitcher.classes()).toContain('px-1.5')

    // Check stepper button micro dimensions
    const prevDateBtn = wrapper.find('button[title="اليوم السابق"]')
    expect(prevDateBtn.classes()).toContain('min-h-[36px]')
    expect(prevDateBtn.classes()).toContain('min-w-[36px]')
  })

  it('updates progress bar and streak counter upon habit check-in', async () => {
    const wrapper = mount(DailyRoutines)
    const initialText = wrapper.text()
    expect(initialText).toContain('0 من 2')

    // Toggle check-in on first habit
    const checkBtn = wrapper.find('button[title="تسجيل الإنجاز"]')
    await checkBtn.trigger('click')

    expect(wrapper.text()).toContain('1 من 2 (50%)')
  })

  it('handles mobile daily notes entry and quick submit trigger', async () => {
    const wrapper = mount(DailyRoutines)

    // Switch to journal tab
    const journalTabBtn = wrapper.findAll('button').find(b => b.text().includes('اليوميات'))
    await journalTabBtn.trigger('click')

    // Find daily note textarea
    const noteTextarea = wrapper.find('textarea[placeholder*="اكتب ملاحظة أو خاطر سريع"]')
    expect(noteTextarea.exists()).toBe(true)

    await noteTextarea.setValue('ملاحظة يومية سريعة لاختبار الواجهة')

    // Find daily note form and submit it
    const noteForm = wrapper.findAll('form').find(f => f.find('textarea[placeholder*="اكتب ملاحظة أو خاطر سريع"]').exists())
    expect(noteForm.exists()).toBe(true)
    await noteForm.trigger('submit')

    // Verify note is saved and displayed in list
    expect(wrapper.text()).toContain('ملاحظة يومية سريعة لاختبار الواجهة')
  })

  it('adds and toggles quick daily tasks in journal tab', async () => {
    store.dailyTasks = []
    const wrapper = mount(DailyRoutines)

    // Switch to journal tab
    const journalTabBtn = wrapper.findAll('button').find(b => b.text().includes('اليوميات'))
    await journalTabBtn.trigger('click')

    // Find quick add input
    const taskInput = wrapper.find('input[placeholder*="أضف مهمة جديدة لسجل يومياتك"]')
    expect(taskInput.exists()).toBe(true)

    await taskInput.setValue('مهمة سريعة جديدة')
    const form = wrapper.findAll('form').find(f => f.find('input[placeholder*="أضف مهمة جديدة"]').exists())
    expect(form.exists()).toBe(true)
    await form.trigger('submit')

    // Verify task is added to store
    expect(store.dailyTasks.length).toBe(1)
    expect(store.dailyTasks[0].title).toBe('مهمة سريعة جديدة')

    // Toggle task completion
    const toggleBtn = wrapper.find('button[title="تغيير حالة الإنجاز"]')
    expect(toggleBtn.exists()).toBe(true)
    await toggleBtn.trigger('click')

    expect(store.dailyTasks[0].completed).toBe(true)
  })

  it('manages daily task categories in store', () => {
    store.dailyTaskCategories = ['عام', 'عمل', 'شخصي']

    // Add custom category
    const added = store.addDailyTaskCategory('مشاريع سفر')
    expect(added).toBe(true)
    expect(store.dailyTaskCategories).toContain('مشاريع سفر')

    // Prevent duplicate category
    const duplicate = store.addDailyTaskCategory('مشاريع سفر')
    expect(duplicate).toBe(false)

    // Delete category
    const deleted = store.deleteDailyTaskCategory('مشاريع سفر')
    expect(deleted).toBe(true)
    expect(store.dailyTaskCategories).not.toContain('مشاريع سفر')

    // Protect default 'عام' category
    const deleteDefault = store.deleteDailyTaskCategory('عام')
    expect(deleteDefault).toBe(false)
    expect(store.dailyTaskCategories).toContain('عام')
  })
})
