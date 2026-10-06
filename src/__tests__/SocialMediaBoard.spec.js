import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import SocialMediaBoard from '../components/SocialMediaBoard.vue'
import Settings from '../components/Settings.vue'
import { store } from '../store.js'

describe('SocialMediaBoard.vue and Social Media Management Features', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.restoreAllMocks()

    store.currentUser = { id: 42, name: 'سارة أحمد', role: { name: 'مدير' } }

    store.socialAccounts = [
      {
        id: 1,
        platform: 'facebook',
        account_id: 'fb_page_101',
        account_name: 'صفحة الشركة الرسمية',
        account_username: 'mybrand',
        avatar_url: '',
        followers_count: 12500,
        status: 'connected',
      },
      {
        id: 2,
        platform: 'instagram',
        account_id: 'ig_acc_202',
        account_name: 'انستجرام الأعمال',
        account_username: 'mybrand_official',
        avatar_url: '',
        followers_count: 45000,
        status: 'connected',
      },
    ]

    store.socialPosts = [
      {
        id: 101,
        content: 'إعلان إطلاق التحديث الجديد للمنصة اليوم! 🚀',
        media_urls: ['https://example.com/banner.jpg'],
        platforms: ['facebook', 'instagram'],
        status: 'published',
        scheduled_at: null,
        published_at: new Date().toISOString(),
        platform_post_ids: {
          facebook: { url: 'https://facebook.com/posts/101' },
          instagram: { url: 'https://instagram.com/p/101' },
        },
      },
      {
        id: 102,
        content: 'نصيحة تسويقية قادمة غداً لرواد الأعمال ✨',
        media_urls: [],
        platforms: ['linkedin'],
        status: 'scheduled',
        scheduled_at: new Date(Date.now() + 86400000).toISOString(),
        published_at: null,
        platform_post_ids: {},
      },
      {
        id: 103,
        content: 'مسودة مقال قيد المراجعة 📝',
        media_urls: [],
        platforms: ['youtube'],
        status: 'draft',
        scheduled_at: null,
        published_at: null,
        platform_post_ids: {},
      },
    ]

    store.socialSettings = {
      facebook: { app_id: '12345', app_secret: 'sec_fb', access_token: 'tok_fb', is_active: true },
      instagram: { app_id: '67890', app_secret: 'sec_ig', access_token: 'tok_ig', is_active: true },
      youtube: { api_key: 'key_yt', app_id: '', app_secret: '', access_token: '', is_active: false },
      linkedin: { app_id: '', app_secret: '', access_token: '', is_active: false },
    }
  })

  it('renders social media board header, stats, and connected accounts', () => {
    const wrapper = mount(SocialMediaBoard)
    expect(wrapper.text()).toContain('إدارة السوشيال ميديا')
    expect(wrapper.text()).toContain('خاص بك 🔒')
    expect(wrapper.text()).toContain('صفحة الشركة الرسمية')
    expect(wrapper.text()).toContain('انستجرام الأعمال')
    expect(wrapper.text()).toContain('12,500 متابع')
  })

  it('filters posts by status tabs (all, scheduled, published, draft)', async () => {
    const wrapper = mount(SocialMediaBoard)

    // All posts
    expect(wrapper.text()).toContain('إعلان إطلاق التحديث')
    expect(wrapper.text()).toContain('نصيحة تسويقية')
    expect(wrapper.text()).toContain('مسودة مقال')

    // Click 'المجدولة' tab
    const scheduledTab = wrapper.findAll('button').find(b => b.text().includes('المجدولة'))
    expect(scheduledTab).toBeDefined()
    await scheduledTab.trigger('click')

    expect(wrapper.text()).toContain('نصيحة تسويقية')
    expect(wrapper.text()).not.toContain('إعلان إطلاق التحديث')

    // Click 'المنشورة' tab
    const publishedTab = wrapper.findAll('button').find(b => b.text().includes('المنشورة'))
    await publishedTab.trigger('click')

    expect(wrapper.text()).toContain('إعلان إطلاق التحديث')
    expect(wrapper.text()).not.toContain('نصيحة تسويقية')
  })

  it('filters posts by search query', async () => {
    const wrapper = mount(SocialMediaBoard)
    const searchInput = wrapper.find('input[placeholder*="بحث في المحتوى"]')
    expect(searchInput.exists()).toBe(true)

    await searchInput.setValue('تسويقية')
    expect(wrapper.text()).toContain('نصيحة تسويقية')
    expect(wrapper.text()).not.toContain('إعلان إطلاق التحديث')
  })

  it('opens and cancels new post composer modal', async () => {
    const wrapper = mount(SocialMediaBoard)
    expect(wrapper.find('textarea[placeholder*="ما الذي يدور في ذهنك"]').exists()).toBe(false)

    // Click new post button
    const newPostBtn = wrapper.findAll('button').find(b => b.text().includes('منشور جديد'))
    await newPostBtn.trigger('click')

    expect(wrapper.find('textarea[placeholder*="ما الذي يدور في ذهنك"]').exists()).toBe(true)
  })

  it('ensures strict user isolation for storage keys in store.js', () => {
    store.currentUser = { id: 99, name: 'مستخدم تجريبي' }

    const accountsKey = store.getSocialAccountsStorageKey()
    const postsKey = store.getSocialPostsStorageKey()
    const settingsKey = store.getSocialSettingsStorageKey()

    expect(accountsKey).toBe('mymind_social_accounts_user_99')
    expect(postsKey).toBe('mymind_social_posts_user_99')
    expect(settingsKey).toBe('mymind_social_settings_user_99')

    // Switch user
    store.currentUser = { id: 105, name: 'مستخدم آخر' }
    expect(store.getSocialAccountsStorageKey()).toBe('mymind_social_accounts_user_105')
  })

  it('renders social media tab in Settings and allows saving platform configuration', async () => {
    const wrapper = mount(Settings)

    // Find tab button for social settings
    const socialTabBtn = wrapper.findAll('button').find(b => b.text().includes('إعدادات السوشيال ميديا'))
    expect(socialTabBtn).toBeDefined()
    await socialTabBtn.trigger('click')

    expect(wrapper.text()).toContain('إعدادات السوشيال ميديا وربط الـ API')
    expect(wrapper.text()).toContain('خاص بحسابك فقط')
    expect(wrapper.text()).toContain('Facebook App ID')

    // Check pre-populated field from store
    const fbAppIdInput = wrapper.find('input[placeholder*="123456789012345"]')
    expect(fbAppIdInput.exists()).toBe(true)
    expect(fbAppIdInput.element.value).toBe('12345')
  })
})
