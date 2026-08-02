import { mount } from '@vue/test-utils'
import { describe, it, expect, beforeEach } from 'vitest'
import DailyRoutines from '../components/DailyRoutines.vue'
import { store } from '../store'

describe('Milestone 5 Mobile Swipe & Touch Ergonomics Challenge', () => {
  beforeEach(() => {
    store.init()
  })

  // Helper to simulate touch events
  const createTouchEvent = (type, clientX, clientY, target) => {
    const touch = { clientX, clientY }
    const event = new Event(type, { bubbles: true, cancelable: true })
    Object.defineProperty(event, 'touches', { value: [touch] })
    Object.defineProperty(event, 'changedTouches', { value: [touch] })
    if (target) {
      Object.defineProperty(event, 'target', { value: target })
    }
    return event
  }

  describe('1. Swipe Delta Threshold (49px vs 50px vs 51px)', () => {
    it('does NOT change tab at 49px horizontal swipe delta', async () => {
      const wrapper = mount(DailyRoutines)
      const container = wrapper.element

      // Start habits tab
      expect(wrapper.vm.activeTab).toBe('habits')

      // Right swipe of 49px (habits -> journal)
      container.dispatchEvent(createTouchEvent('touchstart', 100, 200, container))
      container.dispatchEvent(createTouchEvent('touchmove', 149, 200, container))
      container.dispatchEvent(createTouchEvent('touchend', 149, 200, container))
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.activeTab).toBe('habits')
    })

    it('evaluates threshold at exactly 50px horizontal swipe delta (> 50 check)', async () => {
      const wrapper = mount(DailyRoutines)
      const container = wrapper.element

      // Start habits tab
      expect(wrapper.vm.activeTab).toBe('habits')

      // Right swipe of exactly 50px (100 -> 150)
      container.dispatchEvent(createTouchEvent('touchstart', 100, 200, container))
      container.dispatchEvent(createTouchEvent('touchmove', 150, 200, container))
      container.dispatchEvent(createTouchEvent('touchend', 150, 200, container))
      await wrapper.vm.$nextTick()

      // Since condition is deltaX > 50, 50 > 50 is FALSE -> tab remains habits!
      expect(wrapper.vm.activeTab).toBe('habits')
    })

    it('DOES change tab at 51px horizontal swipe delta', async () => {
      const wrapper = mount(DailyRoutines)
      const container = wrapper.element

      // Start habits tab
      expect(wrapper.vm.activeTab).toBe('habits')

      // Right swipe of 51px (100 -> 151)
      container.dispatchEvent(createTouchEvent('touchstart', 100, 200, container))
      container.dispatchEvent(createTouchEvent('touchmove', 151, 200, container))
      container.dispatchEvent(createTouchEvent('touchend', 151, 200, container))
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.activeTab).toBe('journal')

      // Left swipe of 51px (150 -> 99)
      container.dispatchEvent(createTouchEvent('touchstart', 150, 200, container))
      container.dispatchEvent(createTouchEvent('touchmove', 99, 200, container))
      container.dispatchEvent(createTouchEvent('touchend', 99, 200, container))
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.activeTab).toBe('habits')
    })
  })

  describe('2. Exclusion Guard: .overflow-x-auto', () => {
    it('does NOT change tabs when touch gesture is inside .overflow-x-auto strip', async () => {
      const wrapper = mount(DailyRoutines)
      
      // Find element inside .overflow-x-auto (e.g. scheduled days micro button)
      const overflowElem = wrapper.find('.overflow-x-auto button')
      expect(overflowElem.exists()).toBe(true)

      expect(wrapper.vm.activeTab).toBe('habits')

      // Swipe 100px inside overflow-x-auto
      overflowElem.element.dispatchEvent(createTouchEvent('touchstart', 100, 200, overflowElem.element))
      overflowElem.element.dispatchEvent(createTouchEvent('touchmove', 200, 200, overflowElem.element))
      overflowElem.element.dispatchEvent(createTouchEvent('touchend', 200, 200, overflowElem.element))
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.activeTab).toBe('habits')
    })

    it('prevents tab change when touchstart is inside .overflow-x-auto and touchend outside', async () => {
      const wrapper = mount(DailyRoutines)
      const overflowElem = wrapper.find('.overflow-x-auto button')
      const outerElem = wrapper.element

      expect(wrapper.vm.activeTab).toBe('habits')

      // Touchstart inside overflow container at clientX = 100
      overflowElem.element.dispatchEvent(createTouchEvent('touchstart', 100, 200, overflowElem.element))

      // Touchend outside overflow container at clientX = 300
      outerElem.dispatchEvent(createTouchEvent('touchend', 300, 200, outerElem))
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.activeTab).toBe('habits')
    })
  })

  describe('3. Exclusion Guard: [role="dialog"]', () => {
    it('checks if MobileBottomSheet modal element has role="dialog"', async () => {
      const wrapper = mount(DailyRoutines)
      
      // Open add habit modal via UI button
      const addBtn = wrapper.findAll('button').find(b => b.text().includes('إضافة عادة'))
      expect(addBtn).toBeTruthy()
      await addBtn.trigger('click')

      const dialogElem = wrapper.find('[role="dialog"]')
      expect(dialogElem.exists()).toBe(true)
    })

    it('prevents background tab change when swiping inside active MobileBottomSheet', async () => {
      const wrapper = mount(DailyRoutines)
      
      // Open add habit modal via UI button
      const addBtn = wrapper.findAll('button').find(b => b.text().includes('إضافة عادة'))
      expect(addBtn).toBeTruthy()
      await addBtn.trigger('click')

      expect(wrapper.vm.activeTab).toBe('habits')

      // Find an element inside the opened modal sheet
      const modalInput = wrapper.find('input[placeholder*="شرب ماء"]')
      expect(modalInput.exists()).toBe(true)

      // Touch gesture inside open modal input/content
      modalInput.element.dispatchEvent(createTouchEvent('touchstart', 100, 200, modalInput.element))
      modalInput.element.dispatchEvent(createTouchEvent('touchmove', 200, 200, modalInput.element))
      modalInput.element.dispatchEvent(createTouchEvent('touchend', 200, 200, modalInput.element))
      await wrapper.vm.$nextTick()

      expect(wrapper.vm.activeTab).toBe('habits')
    })
  })

  describe('4. RTL Swipe Direction Mapping', () => {
    it('correctly maps RTL right swipe (habits -> journal) and left swipe (journal -> habits)', async () => {
      const wrapper = mount(DailyRoutines)
      const container = wrapper.element

      // 1. On Habits Tab: Right swipe (deltaX > 50) -> switches to Journal
      expect(wrapper.vm.activeTab).toBe('habits')
      container.dispatchEvent(createTouchEvent('touchstart', 100, 200, container))
      container.dispatchEvent(createTouchEvent('touchend', 200, 200, container)) // deltaX = 100
      await wrapper.vm.$nextTick()
      expect(wrapper.vm.activeTab).toBe('journal')

      // 2. On Journal Tab: Right swipe (deltaX > 50) -> does NOT change tab (stays journal)
      container.dispatchEvent(createTouchEvent('touchstart', 100, 200, container))
      container.dispatchEvent(createTouchEvent('touchend', 200, 200, container)) // deltaX = 100
      await wrapper.vm.$nextTick()
      expect(wrapper.vm.activeTab).toBe('journal')

      // 3. On Journal Tab: Left swipe (deltaX < -50) -> switches to Habits
      container.dispatchEvent(createTouchEvent('touchstart', 200, 200, container))
      container.dispatchEvent(createTouchEvent('touchend', 100, 200, container)) // deltaX = -100
      await wrapper.vm.$nextTick()
      expect(wrapper.vm.activeTab).toBe('habits')

      // 4. On Habits Tab: Left swipe (deltaX < -50) -> does NOT change tab (stays habits)
      container.dispatchEvent(createTouchEvent('touchstart', 200, 200, container))
      container.dispatchEvent(createTouchEvent('touchend', 100, 200, container)) // deltaX = -100
      await wrapper.vm.$nextTick()
      expect(wrapper.vm.activeTab).toBe('habits')
    })
  })

  describe('5. Touch Target Size Compliance', () => {
    it('audits interactive controls for min 44x44px target sizes', () => {
      const wrapper = mount(DailyRoutines)

      // Category Manage Button check
      const catManageBtn = wrapper.find('button[title="إدارة التصنيفات"]')
      if (catManageBtn.exists()) {
        const classes = catManageBtn.attributes('class')
        expect(classes).toContain('min-h-[44px]')
      }

      // Check category filter select
      const catFilterSelect = wrapper.find('select')
      if (catFilterSelect.exists()) {
        const selectClasses = catFilterSelect.attributes('class')
        expect(selectClasses).toContain('min-h-[44px]')
      }

      // Check search input
      const searchInput = wrapper.find('input[placeholder="بحث..."]')
      if (searchInput.exists()) {
        const inputClasses = searchInput.attributes('class')
        expect(inputClasses).toContain('min-h-[44px]')
      }
    })
  })
})
