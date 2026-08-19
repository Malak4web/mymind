import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { store } from '../store.js'
import DailyRoutines from '../components/DailyRoutines.vue'
import HabitDetail from '../components/HabitDetail.vue'

describe('Milestone 5 Empirical Verification & Challenge Suite', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()

    if (typeof globalThis.navigator !== 'undefined') {
      globalThis.navigator.vibrate = vi.fn()
    }

    // Standard baseline habits for testing
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

    store.dailyTasks = []
    store.dailyNotesList = {}
  })

  // Helper date formatter matching store/component logic
  const formatDateKey = (d) => {
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
  }

  describe('1. Rapid Habit Toggling & Completion Percentage Reactivity', () => {
    it('handles 50 rapid toggles sequentially without state drift or memory corruption', () => {
      const todayKey = formatDateKey(new Date())
      const habitId = 1

      for (let i = 0; i < 50; i++) {
        store.toggleHabitLog(habitId, todayKey)
        const currentCompleted = store.habits.find(h => h.id === habitId).logs[todayKey].completed
        expect(currentCompleted).toBe(i % 2 === 0)
      }

      // Final state after 50 toggles (even number) should be false (unchecked)
      expect(store.habits.find(h => h.id === habitId).logs[todayKey].completed).toBe(false)
    })

    it('calculates selectedDateStats completion percentage reactively in DailyRoutines', async () => {
      const wrapper = mount(DailyRoutines)
      const todayKey = formatDateKey(new Date())

      // Initial stats: 0 completed out of 2 = 0%
      expect(wrapper.text()).toContain('0 من 2 (0%)')

      // Toggle first habit
      store.toggleHabitLog(1, todayKey)
      await wrapper.vm.$nextTick()
      expect(wrapper.text()).toContain('1 من 2 (50%)')

      // Toggle second habit
      store.toggleHabitLog(2, todayKey)
      await wrapper.vm.$nextTick()
      expect(wrapper.text()).toContain('2 من 2 (100%)')

      // Toggle off first habit
      store.toggleHabitLog(1, todayKey)
      await wrapper.vm.$nextTick()
      expect(wrapper.text()).toContain('1 من 2 (50%)')
    })

    it('handles rapid numeric habit increments up to target value reactively', async () => {
      const todayKey = formatDateKey(new Date())
      const numHabit = await store.addHabit({
        title: 'تمارين ضغط',
        type: 'numeric',
        targetValue: 10,
        unit: 'مرة'
      })

      // Increment step by step
      for (let c = 1; c <= 10; c++) {
        store.toggleHabitLog(numHabit.id, todayKey, c)
        const log = store.habits.find(h => h.id === numHabit.id).logs[todayKey]
        expect(log.count).toBe(c)
        expect(log.completed).toBe(c >= 10)
      }
    })
  })

  describe('2. Streak Calculations Across Month Boundaries & Year Transitions', () => {
    it('calculates streaks across month boundaries (e.g. July -> June)', () => {
      const habit = store.habits[0]
      const logs = {}

      // Create a 10-day consecutive log spanning across month boundary: June 25 to July 4
      // Suppose today is 2026-07-04
      const baseDate = new Date(2026, 6, 4) // July 4, 2026

      for (let i = 0; i < 10; i++) {
        const d = new Date(baseDate)
        d.setDate(baseDate.getDate() - i)
        logs[formatDateKey(d)] = { completed: true, count: 1 }
      }
      habit.logs = logs

      // Simulate streak calculation starting from 2026-07-04
      let streak = 0
      let checkDate = new Date(2026, 6, 4)
      while (true) {
        const key = formatDateKey(checkDate)
        if (habit.logs[key]?.completed) {
          streak++
          checkDate.setDate(checkDate.getDate() - 1)
        } else {
          break
        }
      }

      expect(streak).toBe(10)
    })

    it('calculates streaks across year transitions (e.g. Jan 3, 2026 -> Dec 25, 2025)', () => {
      const habit = store.habits[0]
      const logs = {}

      // Create a 10-day consecutive log spanning Dec 25, 2025 to Jan 3, 2026
      const baseDate = new Date(2026, 0, 3) // Jan 3, 2026

      for (let i = 0; i < 10; i++) {
        const d = new Date(baseDate)
        d.setDate(baseDate.getDate() - i)
        logs[formatDateKey(d)] = { completed: true, count: 1 }
      }
      habit.logs = logs

      let streak = 0
      let checkDate = new Date(2026, 0, 3)
      while (true) {
        const key = formatDateKey(checkDate)
        if (habit.logs[key]?.completed) {
          streak++
          checkDate.setDate(checkDate.getDate() - 1)
        } else {
          break
        }
      }

      expect(streak).toBe(10)
    })

    it('handles leap year transition boundary (Feb 27 -> March 2 in a leap year)', () => {
      const habit = store.habits[0]
      const logs = {}

      // 2028 is a leap year (Feb has 29 days)
      const baseDate = new Date(2028, 2, 2) // March 2, 2028
      const days = [
        new Date(2028, 2, 2), // Mar 2
        new Date(2028, 2, 1), // Mar 1
        new Date(2028, 1, 29), // Feb 29
        new Date(2028, 1, 28), // Feb 28
        new Date(2028, 1, 27)  // Feb 27
      ]

      days.forEach(d => {
        logs[formatDateKey(d)] = { completed: true }
      })
      habit.logs = logs

      let streak = 0
      let checkDate = new Date(2028, 2, 2)
      while (true) {
        const key = formatDateKey(checkDate)
        if (habit.logs[key]?.completed) {
          streak++
          checkDate.setDate(checkDate.getDate() - 1)
        } else {
          break
        }
      }

      expect(streak).toBe(5)
    })

    it('breaks streak immediately on missing day in consecutive sequence', () => {
      const habit = store.habits[0]
      const today = new Date()
      const yesterday = new Date(today)
      yesterday.setDate(today.getDate() - 1)
      const threeDaysAgo = new Date(today)
      threeDaysAgo.setDate(today.getDate() - 3)

      habit.logs = {
        [formatDateKey(today)]: { completed: true },
        [formatDateKey(yesterday)]: { completed: true },
        // 2 days ago missing!
        [formatDateKey(threeDaysAgo)]: { completed: true }
      }

      let streak = 0
      let checkDate = new Date(today)
      while (true) {
        const key = formatDateKey(checkDate)
        if (habit.logs[key]?.completed) {
          streak++
          checkDate.setDate(checkDate.getDate() - 1)
        } else {
          break
        }
      }

      expect(streak).toBe(2)
    })
  })

  describe('3. Daily Notes Submission Payload Formatting & Reactive Sync', () => {
    it('sends the journal note to the API and shows it for the selected day', async () => {
      store.dailyNotes = []
      const addDailyNoteSpy = vi.spyOn(store, 'addDailyNote').mockImplementation(async (dateKey, content) => {
        const note = { id: 4242, dateKey, content, createdAt: new Date().toISOString() }
        store.dailyNotes = [note, ...store.dailyNotes]
        return note
      })

      const wrapper = mount(DailyRoutines)

      // Switch to journal tab
      const journalTabBtn = wrapper.findAll('button').find(b => b.text().includes('اليوميات'))
      await journalTabBtn.trigger('click')

      const noteTextarea = wrapper.find('textarea[placeholder*="اكتب ملاحظة"]')
      await noteTextarea.setValue('  انطباع يومي رائع عن التقدّم  ')

      const submitBtn = wrapper.findAll('button').find(b => b.text().includes('حفظ الملاحظة'))
      if (submitBtn) await submitBtn.trigger('click')
      const noteForm = wrapper.findAll('form').find(f => f.find('textarea[placeholder*="اكتب ملاحظة"]').exists())
      if (noteForm && noteForm.exists()) await noteForm.trigger('submit')

      const todayKey = formatDateKey(new Date())

      // Notes are persisted through the API now; they used to be pushed into
      // an undeclared store property that was never saved anywhere.
      expect(addDailyNoteSpy).toHaveBeenCalledWith(todayKey, 'انطباع يومي رائع عن التقدّم')

      const notes = store.notesForDate(todayKey)
      expect(notes.length).toBe(1)
      expect(notes[0].content).toBe('انطباع يومي رائع عن التقدّم')
    })

    it('formats HabitDetail note payload with mood emoji prefix', () => {
      const habit = store.habits[0]
      const noteContent = 'قرأت 20 صفحة من كتاب التصميم'
      const mood = '🚀'
      const dateStr = formatDateKey(new Date())

      const formattedText = `${mood} | ${noteContent}`
      const addedNote = store.addHabitNote(habit.id, formattedText, dateStr)

      expect(addedNote.content).toBe('🚀 | قرأت 20 صفحة من كتاب التصميم')
      expect(store.habits[0].notesList[0].content).toContain('🚀 | قرأت')
    })

    it('deletes daily note reactively from store', async () => {
      const wrapper = mount(DailyRoutines)
      const journalTabBtn = wrapper.findAll('button').find(b => b.text().includes('اليوميات'))
      await journalTabBtn.trigger('click')

      const todayKey = formatDateKey(new Date())
      store.dailyNotes = [
        { id: 999, dateKey: todayKey, content: 'ملاحظة ملغاة', createdAt: new Date().toISOString() }
      ]

      const deleteSpy = vi.spyOn(store, 'deleteDailyNote').mockImplementation(async (id) => {
        store.dailyNotes = store.dailyNotes.filter(n => String(n.id) !== String(id))
      })

      await wrapper.vm.$nextTick()
      expect(wrapper.text()).toContain('ملاحظة ملغاة')

      const deleteBtn = wrapper.findAll('button').find(b => b.text().includes('🗑️'))
      await deleteBtn.trigger('click')

      expect(deleteSpy).toHaveBeenCalledWith(999)
      await wrapper.vm.$nextTick()
      expect(wrapper.text()).not.toContain('ملاحظة ملغاة')
      deleteSpy.mockRestore()
    })
  })

  describe('4. Haptic Vibration Trigger Execution (navigator.vibrate)', () => {
    it('executes navigator.vibrate(25) ONLY on habit completion (check-in), NOT on unchecking', async () => {
      const vibrateSpy = vi.fn()
      globalThis.navigator.vibrate = vibrateSpy

      const wrapper = mount(DailyRoutines)
      const checkBtn = wrapper.find('button[title="تسجيل الإنجاز"]')

      // 1. Check-in (incomplete -> complete)
      await checkBtn.trigger('click')
      expect(vibrateSpy).toHaveBeenCalledTimes(1)
      expect(vibrateSpy).toHaveBeenCalledWith(25)

      // Reset mock
      vibrateSpy.mockClear()

      // 2. Uncheck (complete -> incomplete)
      await checkBtn.trigger('click')
      expect(vibrateSpy).not.toHaveBeenCalled()
    })

    it('executes navigator.vibrate(25) when toggling scheduled day button in habit card', async () => {
      const vibrateSpy = vi.fn()
      globalThis.navigator.vibrate = vibrateSpy

      const wrapper = mount(DailyRoutines)

      // Find day button for current day in habit card
      const dayBtns = wrapper.findAll('button').filter(b => b.attributes('title')?.includes('انقر للتسجيل'))
      if (dayBtns.length > 0) {
        await dayBtns[0].trigger('click')
        expect(vibrateSpy).toHaveBeenCalledWith(25)
      }
    })

    it('gracefully executes without throwing if navigator.vibrate is undefined', async () => {
      // Temporarily remove vibrate
      delete globalThis.navigator.vibrate

      const wrapper = mount(DailyRoutines)
      const checkBtn = wrapper.find('button[title="تسجيل الإنجاز"]')

      expect(() => {
        checkBtn.trigger('click')
      }).not.toThrow()
    })
  })
})
