import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import SocialMediaBoard from '../components/SocialMediaBoard.vue'
import Settings from '../components/Settings.vue'
import { store } from '../store.js'

describe('SocialMediaBoard.vue and Social Media Management Features', () => {
  beforeEach(() => {
    localStorage.clear()
    window.location.hash = ''
    vi.restoreAllMocks()

    store.token = ''
    store.isAuthenticated = false
    store.loadSocialAccounts = vi.fn().mockResolvedValue()
    store.loadSocialPosts = vi.fn().mockResolvedValue()
    store.loadSocialSettings = vi.fn().mockResolvedValue()

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

  afterEach(() => {
    window.location.hash = ''
    store.token = ''
    store.isAuthenticated = false
    store.socialAccounts = []
    store.socialPosts = []
    vi.restoreAllMocks()
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

  it('opens connect modal, requires login, discovers available pages, and connects with 1-click', async () => {
    store.checkSocialAuthStatus = vi.fn()
      .mockResolvedValueOnce({ is_authenticated: false, has_app_credentials: false })
      .mockResolvedValue({ is_authenticated: true, has_app_credentials: true })

    store.socialDemoLogin = vi.fn().mockResolvedValue(true)

    store.loadAvailablePages = vi.fn().mockResolvedValue({
      needs_login: false,
      pages: [
        {
          account_id: 'fb_page_discovered_99',
          account_name: 'صفحة متجري الذكي',
          account_username: 'smart_store',
          avatar_url: '',
          category: 'تسوق وتجارة',
          followers_count: 8900,
          is_connected: false,
        }
      ]
    })
    store.connectSocialAccount = vi.fn().mockResolvedValue({
      id: 99,
      platform: 'facebook',
      account_name: 'صفحة متجري الذكي',
    })

    const wrapper = mount(SocialMediaBoard)

    // Click "ربط حساب"
    const connectBtn = wrapper.findAll('button').find(b => b.text().includes('ربط حساب'))
    expect(connectBtn).toBeDefined()
    await connectBtn.trigger('click')

    // Wait for auth check
    await new Promise(r => setTimeout(r, 20))
    await wrapper.vm.$nextTick()

    // Must show login requirement and NOT show pages yet
    expect(wrapper.text()).toContain('تسجيل الدخول والتحقق من حسابك')
    expect(wrapper.text()).toContain('تسجيل الدخول عبر فيسبوك')
    expect(wrapper.text()).not.toContain('صفحة متجري الذكي')

    // Click Demo Login button
    const demoLoginBtn = wrapper.findAll('button').find(b => b.text().includes('تسجيل دخول تجريبي'))
    expect(demoLoginBtn).toBeDefined()
    await demoLoginBtn.trigger('click')

    // Wait for login and discovery
    await new Promise(r => setTimeout(r, 20))
    await wrapper.vm.$nextTick()

    // Now pages must be visible
    expect(wrapper.text()).toContain('صفحة متجري الذكي')
    expect(wrapper.text()).toContain('8,900 متابع')
    expect(wrapper.text()).toContain('تسوق وتجارة')

    // Find and click "ربط هذه الصفحة"
    const linkBtn = wrapper.findAll('button').find(b => b.text().includes('ربط هذه الصفحة'))
    expect(linkBtn).toBeDefined()
    await linkBtn.trigger('click')

    expect(store.connectSocialAccount).toHaveBeenCalledWith(expect.objectContaining({
      platform: 'facebook',
      account_id: 'fb_page_discovered_99',
      account_name: 'صفحة متجري الذكي',
    }))
  })

  it('renders post metrics bar on published posts having metrics data', async () => {
    store.socialPosts[0].metrics = {
      likes: 245,
      comments: 38,
      shares: 19,
      views: 3200,
      engagement_rate: 4.8,
    }

    const wrapper = mount(SocialMediaBoard)
    expect(wrapper.text()).toContain('تحليلات أداء المنشور')
    expect(wrapper.text()).toContain('معدل التفاعل: 4.8%')
    expect(wrapper.text()).toContain('245')
    expect(wrapper.text()).toContain('38')
    expect(wrapper.text()).toContain('19')
  })

  it('switches to analytics tab, displays summary KPIs and per-page metrics breakdown', async () => {
    store.socialAnalytics = {
      summary: {
        total_pages: 2,
        total_posts: 14,
        total_followers: 57500,
        total_likes: 3420,
        total_comments: 890,
        total_shares: 410,
        total_interactions: 4720,
        average_engagement_rate: 6.2,
        total_reach: 48900,
        total_impressions: 66000,
      },
      pages: [
        {
          id: 1,
          platform: 'facebook',
          account_id: 'fb_page_101',
          account_name: 'صفحة الشركة الرسمية',
          account_username: 'mybrand',
          avatar_url: '',
          followers_count: 12500,
          growth_rate: '+4.2%',
          posts_count: 8,
          total_likes: 1200,
          total_comments: 300,
          total_shares: 150,
          total_views: 18000,
          total_interactions: 1650,
          engagement_rate: 5.4,
          likes_percentage: 72.7,
          comments_percentage: 18.2,
          shares_percentage: 9.1,
          interactions_per_post: 206.3,
          top_post: {
            content: 'المنشور المميز لصفحة فيسبوك 🚀',
            metrics: { likes: 520, comments: 110, shares: 60, engagement_rate: 7.8 },
            platform_post_ids: { facebook: { url: 'https://facebook.com/posts/top_fb' } }
          }
        },
        {
          id: 2,
          platform: 'instagram',
          account_id: 'ig_acc_202',
          account_name: 'انستجرام الأعمال',
          account_username: 'mybrand_official',
          avatar_url: '',
          followers_count: 45000,
          growth_rate: '+5.1%',
          posts_count: 6,
          total_likes: 2220,
          total_comments: 590,
          total_shares: 260,
          total_views: 30900,
          total_interactions: 3070,
          engagement_rate: 6.8,
          likes_percentage: 72.3,
          comments_percentage: 19.2,
          shares_percentage: 8.5,
          interactions_per_post: 511.7,
          top_post: null
        }
      ],
      posts: store.socialPosts,
    }

    const wrapper = mount(SocialMediaBoard)

    // Click Analytics Tab
    const analyticsTab = wrapper.findAll('button').find(b => b.text().includes('نسب التحليلات والصفحات'))
    expect(analyticsTab).toBeDefined()
    await analyticsTab.trigger('click')

    // Expect Summary KPIs
    expect(wrapper.text()).toContain('نسب أداء وتفاعل صفحات فيسبوك وإنستجرام')
    expect(wrapper.text()).toContain('57,500')
    expect(wrapper.text()).toContain('4,720')
    expect(wrapper.text()).toContain('6.2%')

    // Expect Per-page analytics
    expect(wrapper.text()).toContain('معدل التفاعل: 5.4%')
    expect(wrapper.text()).toContain('72.7%')
    expect(wrapper.text()).toContain('18.2%')
    expect(wrapper.text()).toContain('9.1%')
    expect(wrapper.text()).toContain('معدل التفاعل: 6.8%')
    expect(wrapper.text()).toContain('المنشور المميز لصفحة فيسبوك')
  })

  it('triggers syncSocialPosts and reloads analytics when sync button is clicked', async () => {
    store.syncSocialPosts = vi.fn().mockResolvedValue(true)
    store.loadSocialAnalytics = vi.fn().mockResolvedValue({})

    const wrapper = mount(SocialMediaBoard)

    const syncBtn = wrapper.findAll('button').find(b => b.text().includes('مزامنة المنشورات'))
    expect(syncBtn).toBeDefined()
    await syncBtn.trigger('click')

    expect(store.syncSocialPosts).toHaveBeenCalled()
    expect(store.loadSocialAnalytics).toHaveBeenCalledWith('all')
  })
})

