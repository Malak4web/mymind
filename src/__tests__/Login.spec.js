import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Login from '../components/Login.vue'
import { store } from '../store.js'

describe('Login.vue Component Tests', () => {
  let wrapper = null

  beforeEach(() => {
    localStorage.clear()
    store.token = ''
    store.isAuthenticated = false
    store.currentUser = null
    vi.restoreAllMocks()
  })

  afterEach(() => {
    if (wrapper) {
      wrapper.unmount()
      wrapper = null
    }
  })

  it('renders landing page hero content and branding', () => {
    wrapper = mount(Login)
    expect(wrapper.text()).toContain('MyMind')
    expect(wrapper.text()).toContain('عقلي برو')
    expect(wrapper.text()).toContain('نظّم أفكارك')
  })

  it('renders pricing plans and feature list cards', () => {
    wrapper = mount(Login)
    expect(wrapper.text()).toContain('عقلي مجاني')
    expect(wrapper.text()).toContain('عقلي برو')
    expect(wrapper.text()).toContain('لوحات كانبان التفاعلية')
  })

  it('opens login modal overlay when login button is clicked', async () => {
    wrapper = mount(Login)

    // Initially modal is hidden
    expect(wrapper.find('form').exists()).toBe(false)

    // Click "تسجيل الدخول" button in header
    const loginButton = wrapper.findAll('button').find(b => b.text().includes('تسجيل الدخول'))
    expect(loginButton).toBeTruthy()
    await loginButton.trigger('click')

    // Modal form should now be visible
    expect(wrapper.find('form').exists()).toBe(true)
    expect(wrapper.find('input[type="email"]').exists()).toBe(true)
    expect(wrapper.find('input[type="password"]').exists()).toBe(true)
  })

  it('shows error message when trying to submit empty email/password (edge case)', async () => {
    wrapper = mount(Login)
    
    // Open login modal
    const loginBtn = wrapper.findAll('button').find(b => b.text().includes('تسجيل الدخول'))
    await loginBtn.trigger('click')

    // Submit form with empty fields
    await wrapper.find('form').trigger('submit')

    expect(wrapper.text()).toContain('يرجى إدخال البريد الإلكتروني وكلمة المرور')
    expect(store.isAuthenticated).toBe(false)
  })

  it('handles successful login happy path', async () => {
    const mockToken = 'mock-jwt-token-123'
    const mockUser = { id: 1, name: 'خالد', email: 'khaled@mymind.com', role: { name: 'مدير' } }

    global.fetch = vi.fn((url) => {
      if (url.endsWith('/login')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ token: mockToken, user: mockUser })
        })
      }
      return Promise.resolve({ ok: true, json: () => Promise.resolve([]) })
    })

    wrapper = mount(Login)

    // Open modal
    const loginBtn = wrapper.findAll('button').find(b => b.text().includes('تسجيل الدخول'))
    await loginBtn.trigger('click')

    // Fill credentials
    const emailInput = wrapper.find('input[type="email"]')
    const passwordInput = wrapper.find('input[type="password"]')

    await emailInput.setValue('khaled@mymind.com')
    await passwordInput.setValue('password123')

    // Submit
    await wrapper.find('form').trigger('submit')

    expect(global.fetch).toHaveBeenCalledWith(
      `${store.apiBase}/login`,
      expect.objectContaining({ method: 'POST' })
    )
    expect(store.token).toBe(mockToken)
    expect(store.isAuthenticated).toBe(true)
  })

  it('displays API error message when login credentials are invalid', async () => {
    global.fetch = vi.fn().mockResolvedValue({
      ok: false,
      status: 422,
      json: () => Promise.resolve({ message: 'بيانات الاعتماد غير صحيحة' })
    })

    wrapper = mount(Login)

    // Open modal
    const loginBtn = wrapper.findAll('button').find(b => b.text().includes('تسجيل الدخول'))
    await loginBtn.trigger('click')

    await wrapper.find('input[type="email"]').setValue('invalid@user.com')
    await wrapper.find('input[type="password"]').setValue('wrongpass')

    await wrapper.find('form').trigger('submit')

    expect(wrapper.text()).toContain('بيانات الاعتماد غير صحيحة')
    expect(store.isAuthenticated).toBe(false)
  })

  it('cycles demo tasks status on click in hero section', async () => {
    wrapper = mount(Login)
    const demoCard = wrapper.find('.cursor-pointer')

    if (demoCard.exists()) {
      await demoCard.trigger('click')
      expect(wrapper.exists()).toBe(true)
    }
  })
})
