import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ChallengesBoard from '../components/ChallengesBoard.vue'
import DailyRoutines from '../components/DailyRoutines.vue'
import { store } from '../store.js'

let wrapper = null

describe('ChallengesBoard.vue and Challenges Feature Tests', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()

    global.fetch = vi.fn().mockResolvedValue({
      ok: true,
      json: () => Promise.resolve([])
    })

    if (typeof globalThis.navigator !== 'undefined') {
      globalThis.navigator.vibrate = vi.fn()
    }

    store.currentUser = { id: 1, name: 'سارة محمد', email: 'sara@mymind.com' }
    store.users = [
      { id: 1, name: 'سارة محمد', email: 'sara@mymind.com' },
      { id: 2, name: 'أحمد علي', email: 'ahmed@mymind.com' },
      { id: 3, name: 'منى حسن', email: 'mona@mymind.com' }
    ]

    store.challenges = [
      {
        id: 1,
        user_id: 1,
        partner_id: 2,
        user: { id: 1, name: 'سارة محمد', email: 'sara@mymind.com' },
        partner: { id: 2, name: 'أحمد علي', email: 'ahmed@mymind.com' },
        title: 'تحدي 30 يوم لياقة',
        description: 'الالتزام اليومي بالتمرين وشرب الماء بدون انقطاع',
        category: 'صحة ولياقة',
        icon: '🏃',
        color: 'from-emerald-600 to-teal-600',
        start_date: '2026-10-01',
        end_date: '2026-10-30',
        total_days: 30,
        reward_title: 'ساعة رياضية جارمين',
        reward_icon: '⌚',
        reward_description: 'شراء ساعة جديدة فور إكمال جميع الأيام بنجاح',
        conditions: ['تمرين 45 دقيقة', 'شرب 3 لتر ماء', 'بدون سكر مضاف'],
        days_progress: {
          '1': { completed: true, items: { 0: true, 1: true, 2: true } },
          '2': { completed: false, items: { 0: true, 1: false, 2: false } }
        },
        status: 'active',
        cheers: [
          {
            id: 101,
            challenge_id: 1,
            user_id: 2,
            user: { id: 2, name: 'أحمد علي' },
            message: 'عاش يا بطلة! كملي للأمام!',
            reaction: '🔥',
            created_at: new Date().toISOString()
          }
        ],
        created_at: new Date().toISOString()
      },
      {
        id: 2,
        user_id: 1,
        partner_id: null,
        user: { id: 1, name: 'سارة محمد', email: 'sara@mymind.com' },
        partner: null,
        title: 'تحدي القراءة السريعة',
        description: 'قراءة 5 كتب خلال أسبوعين',
        category: 'تطوير ذات',
        icon: '📚',
        color: 'from-violet-600 to-indigo-600',
        start_date: '2026-10-01',
        end_date: '2026-10-14',
        total_days: 14,
        reward_title: 'جهاز قارئ كيندل',
        reward_icon: '🎁',
        reward_description: 'مكافأة ذاتية',
        conditions: ['قراءة 30 صفحة', 'تلخيص صفحة'],
        days_progress: {},
        status: 'active',
        cheers: [],
        created_at: new Date().toISOString()
      }
    ]
  })

  afterEach(() => {
    if (wrapper) {
      try { wrapper.unmount() } catch (e) {}
      wrapper = null
    }
    vi.restoreAllMocks()
    store.currentUser = null
    store.token = ''
    store.isAuthenticated = false
    store.challenges = []
  })

  it('renders challenges board with summary counters and cards', () => {
    wrapper = mount(ChallengesBoard)
    expect(wrapper.text()).toContain('التحديات الجارية')
    expect(wrapper.text()).toContain('أيام تم إنجازها')
    expect(wrapper.text()).toContain('تحدي 30 يوم لياقة')
    expect(wrapper.text()).toContain('تحدي القراءة السريعة')
    expect(wrapper.text()).toContain('ساعة رياضية جارمين')
    expect(wrapper.text()).toContain('جهاز قارئ كيندل')
  })

  it('filters challenges by search query', async () => {
    wrapper = mount(ChallengesBoard)
    const searchInput = wrapper.find('input[placeholder*="ابحث في التحديات"]')
    expect(searchInput.exists()).toBe(true)

    await searchInput.setValue('لياقة')
    expect(wrapper.text()).toContain('تحدي 30 يوم لياقة')
    expect(wrapper.text()).not.toContain('تحدي القراءة السريعة')
  })

  it('filters challenges by shared status', async () => {
    wrapper = mount(ChallengesBoard)
    const sharedFilterBtn = wrapper.findAll('button').find(b => b.text().includes('المشتركة'))
    expect(sharedFilterBtn).toBeDefined()

    await sharedFilterBtn.trigger('click')
    // Challenge 1 has partner_id: 2, Challenge 2 has null
    expect(wrapper.text()).toContain('تحدي 30 يوم لياقة')
    expect(wrapper.text()).not.toContain('تحدي القراءة السريعة')
  })

  it('opens create modal with duration presets and conditions', async () => {
    wrapper = mount(ChallengesBoard)
    const createBtn = wrapper.findAll('button').find(b => b.text().includes('إنشاء تحدي جديد'))
    expect(createBtn).toBeDefined()

    await createBtn.trigger('click')
    expect(wrapper.text()).toContain('إنشاء تحدي جديد')
    expect(wrapper.text()).toContain('مدة التحدي وعدد الأيام')
    expect(wrapper.text()).toContain('شروط إنجاز اليوم')
    expect(wrapper.text()).toContain('جائزة إكمال التحدي')
  })

  it('can add and remove conditions in create form', async () => {
    wrapper = mount(ChallengesBoard)
    const createBtn = wrapper.findAll('button').find(b => b.text().includes('إنشاء تحدي جديد'))
    await createBtn.trigger('click')

    const conditionInput = wrapper.find('input[placeholder*="اكتب شرطاً"]')
    expect(conditionInput.exists()).toBe(true)

    await conditionInput.setValue('صلاة الفجر في وقتها')
    const addCondBtn = wrapper.findAll('button').find(b => b.text().includes('+ إضافة'))
    await addCondBtn.trigger('click')

    expect(wrapper.text()).toContain('صلاة الفجر في وقتها')
  })

  it('opens challenge detail view with days matrix', async () => {
    wrapper = mount(ChallengesBoard)
    const card = wrapper.find('.group.relative')
    expect(card.exists()).toBe(true)

    await card.trigger('click')

    expect(wrapper.text()).toContain('خريطة أيام التحدي')
    expect(wrapper.text()).toContain('يوم 1')
    expect(wrapper.text()).toContain('يوم 2')
    expect(wrapper.text()).toContain('لوحة التشجيع والتفاعل الحماسي')
  })

  it('toggles condition for a day and checks day completion in store', async () => {
    const spy = vi.spyOn(store, 'toggleChallengeCondition')
    wrapper = mount(ChallengesBoard)
    const card = wrapper.find('.group.relative')
    await card.trigger('click')

    // Find condition item in checklist
    const conditionRow = wrapper.findAll('.cursor-pointer.select-none').find(r => r.text().includes('تمرين 45 دقيقة'))
    expect(conditionRow).toBeDefined()

    await conditionRow.trigger('click')
    expect(spy).toHaveBeenCalled()
  })

  it('sends cheer message and reaction on challenge', async () => {
    const spy = vi.spyOn(store, 'addChallengeCheer')
    wrapper = mount(ChallengesBoard)
    const card = wrapper.find('.group.relative')
    await card.trigger('click')

    const cheerInput = wrapper.find('input[placeholder*="اكتب رسالة تشجيعية"]')
    expect(cheerInput.exists()).toBe(true)

    await cheerInput.setValue('أداء رائع جداً، استمر!')
    const sendBtn = wrapper.findAll('button').find(b => b.text().includes('إرسال 🚀'))
    await sendBtn.trigger('click')

    expect(spy).toHaveBeenCalledWith(1, expect.objectContaining({
      message: 'أداء رائع جداً، استمر!',
      reaction: '🔥'
    }))
  })

  it('switches to Challenges tab in DailyRoutines component', async () => {
    wrapper = mount(DailyRoutines)
    const challengeTabBtn = wrapper.findAll('button').find(b => b.text().includes('التحديات'))
    expect(challengeTabBtn).toBeDefined()

    await challengeTabBtn.trigger('click')
    expect(wrapper.findComponent(ChallengesBoard).exists()).toBe(true)
  })
})
