import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import MobileBottomNav from '../components/MobileBottomNav.vue'
import { store } from '../store.js'

describe('Navbar and Navigation Component Tests', () => {
  beforeEach(() => {
    store.activeView = 'kanban'
    store.notifications = []
  })

  it('renders navigation tabs for MobileBottomNav', () => {
    const wrapper = mount(MobileBottomNav, {
      props: { showProjectsSheet: false, showMoreSheet: false }
    })
    expect(wrapper.text()).toContain('اللوحة')
    expect(wrapper.text()).toContain('الجدول')
    expect(wrapper.text()).toContain('يومياتي')
    expect(wrapper.text()).toContain('المشاريع')
    expect(wrapper.text()).toContain('المزيد')
  })

  it('emits set-view event when clicking navigation tabs', async () => {
    const wrapper = mount(MobileBottomNav, {
      props: { showProjectsSheet: false, showMoreSheet: false }
    })

    const listTab = wrapper.findAll('button').find(b => b.text().includes('الجدول'))
    expect(listTab).toBeTruthy()
    await listTab.trigger('click')

    expect(wrapper.emitted('set-view')).toBeTruthy()
    expect(wrapper.emitted('set-view')[0]).toEqual(['list'])
  })

  it('emits update:showProjectsSheet when clicking projects trigger button', async () => {
    const wrapper = mount(MobileBottomNav, {
      props: { showProjectsSheet: false, showMoreSheet: false }
    })

    const projectsBtn = wrapper.findAll('button').find(b => b.text().includes('المشاريع'))
    await projectsBtn.trigger('click')

    expect(wrapper.emitted('update:showProjectsSheet')).toBeTruthy()
    expect(wrapper.emitted('update:showProjectsSheet')[0]).toEqual([true])
  })
})
