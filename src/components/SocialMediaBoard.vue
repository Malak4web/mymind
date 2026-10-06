<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { store } from '../store'

// View & Filter States
const currentTab = ref('all') // 'all' | 'scheduled' | 'published' | 'draft' | 'analytics'
const selectedPlatformFilter = ref('all') // 'all' | 'facebook' | 'instagram' | 'youtube' | 'linkedin'
const searchQuery = ref('')
const isSyncingPosts = ref(false)
const selectedAnalyticsPlatform = ref('all') // 'all' | 'facebook' | 'instagram'

// Modals
const isComposerOpen = ref(false)
const isConnectModalOpen = ref(false)
const isEditModalOpen = ref(false)
const editingPost = ref(null)

// Composer Form State
const composerForm = ref({
  content: '',
  media_urls: [],
  platforms: ['facebook'],
  account_ids: [],
  publishMode: 'now', // 'now' | 'schedule' | 'draft'
  scheduled_at: '',
})
const tempMediaUrl = ref('')
const activePreviewPlatform = ref('facebook')
const isSubmitting = ref(false)

// Connect Account Form State
const connectForm = ref({
  platform: 'facebook',
  account_id: '',
  account_name: '',
  account_username: '',
  avatar_url: '',
  followers_count: 0,
})

// Quick schedule helpers
const setScheduleOffset = (hours) => {
  const target = new Date(Date.now() + hours * 3600 * 1000)
  composerForm.value.scheduled_at = formatForDateTimeInput(target)
  composerForm.value.publishMode = 'schedule'
}

const setScheduleTomorrow = (hour) => {
  const target = new Date()
  target.setDate(target.getDate() + 1)
  target.setHours(hour, 0, 0, 0)
  composerForm.value.scheduled_at = formatForDateTimeInput(target)
  composerForm.value.publishMode = 'schedule'
}

const formatForDateTimeInput = (d) => {
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

// Media handlers & Device Upload
const isUploadingMedia = ref(false)
const uploadError = ref('')
const fileInputRef = ref(null)
const editFileInputRef = ref(null)
const isDraggingFile = ref(false)

const isVideoUrl = (url) => {
  if (!url) return false
  return /\.(mp4|mov|webm|mkv|avi)(\?.*)?$/i.test(url) || url.includes('/videos/') || url.includes('/reel/')
}

const triggerFileInput = () => {
  if (fileInputRef.value) {
    fileInputRef.value.click()
  }
}

const triggerEditFileInput = () => {
  if (editFileInputRef.value) {
    editFileInputRef.value.click()
  }
}

const handleFileUpload = async (event) => {
  const files = event.target?.files || event.dataTransfer?.files
  if (!files || !files.length) return

  isUploadingMedia.value = true
  uploadError.value = ''

  try {
    for (let i = 0; i < files.length; i++) {
      const file = files[i]
      if (file.size > 100 * 1024 * 1024) {
        uploadError.value = `الملف "${file.name}" أكبر من الحد الأقصى المسموح (100 ميجابايت)`
        continue
      }
      const res = await store.uploadSocialMedia(file)
      if (res && res.url) {
        if (!composerForm.value.media_urls.includes(res.url)) {
          composerForm.value.media_urls.push(res.url)
        }
      }
    }
  } catch (e) {
    console.error('فشل رفع الملف', e)
    uploadError.value = e.message || 'حدث خطأ أثناء رفع الملف، يرجى المحاولة مرة أخرى'
  } finally {
    isUploadingMedia.value = false
    if (fileInputRef.value) {
      fileInputRef.value.value = ''
    }
  }
}

const handleEditFileUpload = async (event) => {
  const files = event.target?.files || event.dataTransfer?.files
  if (!files || !files.length || !editingPost.value) return

  isUploadingMedia.value = true
  uploadError.value = ''

  try {
    if (!Array.isArray(editingPost.value.media_urls)) {
      editingPost.value.media_urls = []
    }
    for (let i = 0; i < files.length; i++) {
      const file = files[i]
      if (file.size > 100 * 1024 * 1024) {
        uploadError.value = `الملف "${file.name}" أكبر من 100 ميجابايت`
        continue
      }
      const res = await store.uploadSocialMedia(file)
      if (res && res.url) {
        editingPost.value.media_urls.push(res.url)
      }
    }
  } catch (e) {
    uploadError.value = e.message || 'حدث خطأ أثناء رفع الملف'
  } finally {
    isUploadingMedia.value = false
    if (editFileInputRef.value) {
      editFileInputRef.value.value = ''
    }
  }
}

const addMediaUrl = () => {
  const url = tempMediaUrl.value.trim()
  if (!url) return
  if (!composerForm.value.media_urls.includes(url)) {
    composerForm.value.media_urls.push(url)
  }
  tempMediaUrl.value = ''
}

const removeMediaUrl = (idx) => {
  composerForm.value.media_urls.splice(idx, 1)
}

const removeEditMediaUrl = (idx) => {
  if (editingPost.value && Array.isArray(editingPost.value.media_urls)) {
    editingPost.value.media_urls.splice(idx, 1)
  }
}

const togglePlatform = (p) => {
  const idx = composerForm.value.platforms.indexOf(p)
  if (idx === -1) {
    composerForm.value.platforms.push(p)
  } else {
    if (composerForm.value.platforms.length > 1) {
      composerForm.value.platforms.splice(idx, 1)
    }
  }
  if (!composerForm.value.platforms.includes(activePreviewPlatform.value)) {
    activePreviewPlatform.value = composerForm.value.platforms[0] || 'facebook'
  }
}

// Add emoji to post
const addEmoji = (emoji) => {
  composerForm.value.content += emoji
}

// Add hashtag to post
const addHashtag = (tag) => {
  if (composerForm.value.content && !composerForm.value.content.endsWith(' ')) {
    composerForm.value.content += ' '
  }
  composerForm.value.content += tag + ' '
}

// Open Composer
const openComposer = () => {
  composerForm.value = {
    content: '',
    media_urls: [],
    platforms: ['facebook'],
    account_ids: [],
    publishMode: 'now',
    scheduled_at: '',
  }
  tempMediaUrl.value = ''
  uploadError.value = ''
  isComposerOpen.value = true
}

// Interactive Discovery & OAuth Connect States
const selectedConnectPlatform = ref('facebook')
const availablePages = ref([])
const authUser = ref(null)
const isLoadingPages = ref(false)
const isPlatformAuthenticated = ref(false)
const isLoggingIn = ref(false)
const authCredentialsMissing = ref(false)
const authMissingMessage = ref('')
const connectingPageId = ref(null)
const isManualEntryOpen = ref(false)
const connectSuccessPageName = ref('')
const connectErrorMsg = ref('')

// Expand / collapse page posts in Analytics
const expandedPagePosts = ref({})
const togglePagePosts = (pageId) => {
  expandedPagePosts.value[pageId] = !expandedPagePosts.value[pageId]
}

// OAuth popup message listener (receives message when OAuth completes)
const handleOAuthWindowMessage = async (event) => {
  if (event.data && event.data.type === 'social_oauth_callback') {
    isLoggingIn.value = false
    if (event.data.success) {
      isPlatformAuthenticated.value = true
      authCredentialsMissing.value = false
      connectErrorMsg.value = ''
      await fetchAvailablePages(selectedConnectPlatform.value)
    } else {
      connectErrorMsg.value = event.data.error || 'فشل تسجيل الدخول على المنصة'
    }
  }
}

// Fetch available pages (Strictly requires authentication first)
const fetchAvailablePages = async (platform) => {
  selectedConnectPlatform.value = platform
  connectForm.value.platform = platform
  isLoadingPages.value = true
  connectErrorMsg.value = ''
  authCredentialsMissing.value = false

  try {
    // Check auth status first
    const status = await store.checkSocialAuthStatus(platform)
    isPlatformAuthenticated.value = status.is_authenticated

    if (!status.is_authenticated) {
      availablePages.value = []
      authUser.value = null
      isLoadingPages.value = false
      return
    }

    const res = await store.loadAvailablePages(platform)
    if (res && res.needs_login) {
      isPlatformAuthenticated.value = false
      availablePages.value = []
      authUser.value = null
    } else if (res && Array.isArray(res.pages)) {
      availablePages.value = res.pages
      isPlatformAuthenticated.value = true
      authUser.value = res.auth_user || null
    } else if (Array.isArray(res)) {
      availablePages.value = res
      isPlatformAuthenticated.value = true
      authUser.value = null
    } else {
      availablePages.value = []
      authUser.value = null
    }
  } catch (e) {
    console.error('فشل جلب الصفحات', e)
    availablePages.value = []
    authUser.value = null
    connectErrorMsg.value = 'تعذر الاتصال بالمنصة، يرجى المحاولة مجدداً'
  } finally {
    isLoadingPages.value = false
  }
}

// Trigger official OAuth Login popup
const handleLoginPlatform = async (platform) => {
  isLoggingIn.value = true
  connectErrorMsg.value = ''
  authCredentialsMissing.value = false
  authMissingMessage.value = ''

  try {
    const res = await store.getSocialOAuthRedirectUrl(platform)
    if (res && res.redirect_url) {
      const width = 600
      const height = 700
      const left = window.screen.width / 2 - width / 2
      const top = window.screen.height / 2 - height / 2
      window.open(
        res.redirect_url,
        `oauth_${platform}`,
        `width=${width},height=${height},top=${top},left=${left},scrollbars=yes,status=1`
      )
    } else if (res && res.error === 'missing_credentials') {
      authCredentialsMissing.value = true
      authMissingMessage.value = res.message || 'يلزم إدخال App ID و App Secret في إعدادات المنصة أولاً'
      isLoggingIn.value = false
    } else {
      connectErrorMsg.value = res?.message || 'تعذر بدء تسجيل الدخول'
      isLoggingIn.value = false
    }
  } catch (e) {
    connectErrorMsg.value = 'حدث خطأ أثناء محاولة بدء تسجيل الدخول'
    isLoggingIn.value = false
  }
}

// Fast demo login fallback
const handleDemoLogin = async (platform) => {
  isLoggingIn.value = true
  connectErrorMsg.value = ''
  authCredentialsMissing.value = false
  try {
    const ok = await store.socialDemoLogin(platform)
    if (ok) {
      isPlatformAuthenticated.value = true
      await fetchAvailablePages(platform)
    } else {
      connectErrorMsg.value = 'فشل تسجيل الدخول التجريبي'
    }
  } finally {
    isLoggingIn.value = false
  }
}

// Logout from platform
const handlePlatformLogout = async (platform) => {
  if (confirm(`هل أنت متأكد من تسجيل الخروج من ${getPlatformMeta(platform).name}؟`)) {
    await store.socialLogout(platform)
    isPlatformAuthenticated.value = false
    availablePages.value = []
  }
}

// Connect Discovered Page with 1-click
const handleConnectDiscoveredPage = async (page) => {
  connectingPageId.value = page.account_id
  connectErrorMsg.value = ''
  try {
    await store.connectSocialAccount({
      platform: selectedConnectPlatform.value,
      account_id: page.account_id,
      account_name: page.account_name,
      account_username: page.account_username || '',
      avatar_url: page.avatar_url || null,
      followers_count: Number(page.followers_count) || 0,
      access_token: page.page_access_token || null,
      metadata: { category: page.category || '' }
    })
    page.is_connected = true
    connectSuccessPageName.value = page.account_name
    setTimeout(() => {
      if (connectSuccessPageName.value === page.account_name) {
        connectSuccessPageName.value = ''
      }
    }, 4000)
    await store.loadSocialAnalytics()
    await store.loadSocialPosts(true)
  } catch (e) {
    alert('فشل ربط الصفحة، يرجى المحاولة مرة أخرى')
  } finally {
    connectingPageId.value = null
  }
}

// Disconnect Page from modal
const handleDisconnectFromModal = async (page) => {
  const existing = (store.socialAccounts || []).find(
    a => a.platform === selectedConnectPlatform.value && a.account_id === page.account_id
  )
  if (existing) {
    if (confirm(`هل أنت متأكد من إلغاء ربط "${page.account_name}"؟`)) {
      await store.disconnectSocialAccount(existing.id)
      page.is_connected = false
    }
  }
}

// Open Connect Modal
const openConnectModal = (preferredPlatform = 'facebook') => {
  selectedConnectPlatform.value = preferredPlatform
  connectForm.value = {
    platform: preferredPlatform,
    account_id: '',
    account_name: '',
    account_username: '',
    avatar_url: '',
    followers_count: 0,
  }
  isManualEntryOpen.value = false
  connectSuccessPageName.value = ''
  connectErrorMsg.value = ''
  authCredentialsMissing.value = false
  authMissingMessage.value = ''
  isPlatformAuthenticated.value = false
  availablePages.value = []
  isConnectModalOpen.value = true
  fetchAvailablePages(preferredPlatform)
}

// Submit Connect Account (Manual entry fallback)
const handleConnectAccount = async () => {
  if (!connectForm.value.account_name.trim() || !connectForm.value.account_id.trim()) {
    alert('يرجى كتابة اسم ومعرّف الحساب أو الصفحة')
    return
  }

  isSubmitting.value = true
  try {
    await store.connectSocialAccount({
      platform: connectForm.value.platform,
      account_id: connectForm.value.account_id.trim(),
      account_name: connectForm.value.account_name.trim(),
      account_username: connectForm.value.account_username.trim(),
      avatar_url: connectForm.value.avatar_url.trim() || null,
      followers_count: Number(connectForm.value.followers_count) || 0,
    })
    connectSuccessPageName.value = connectForm.value.account_name
    await fetchAvailablePages(connectForm.value.platform)
    isManualEntryOpen.value = false
  } finally {
    isSubmitting.value = false
  }
}

// Disconnect Account
const handleDisconnect = async (account) => {
  if (confirm(`هل أنت متأكد من فصل الحساب "${account.account_name}"؟`)) {
    await store.disconnectSocialAccount(account.id)
  }
}

// Submit Create Post
const handleSavePost = async () => {
  if (!composerForm.value.content.trim()) {
    alert('يرجى كتابة نص المنشور')
    return
  }
  if (!composerForm.value.platforms.length) {
    alert('يرجى اختيار منصة واحدة على الأقل للنشر')
    return
  }

  let status = 'draft'
  if (composerForm.value.publishMode === 'now') {
    status = 'published'
  } else if (composerForm.value.publishMode === 'schedule') {
    if (!composerForm.value.scheduled_at) {
      alert('يرجى تحديد وقت وتاريخ الجدولة')
      return
    }
    status = 'scheduled'
  }

  isSubmitting.value = true
  try {
    await store.createSocialPost({
      content: composerForm.value.content.trim(),
      media_urls: composerForm.value.media_urls,
      platforms: composerForm.value.platforms,
      account_ids: composerForm.value.account_ids,
      status: status,
      scheduled_at: status === 'scheduled' ? composerForm.value.scheduled_at : null,
    })
    isComposerOpen.value = false
  } finally {
    isSubmitting.value = false
  }
}

// Open Edit Post Modal
const openEditModal = (post) => {
  editingPost.value = {
    id: post.id,
    content: post.content,
    media_urls: Array.isArray(post.media_urls) ? [...post.media_urls] : [],
    platforms: Array.isArray(post.platforms) ? [...post.platforms] : ['facebook'],
    status: post.status,
    scheduled_at: post.scheduled_at ? formatForDateTimeInput(new Date(post.scheduled_at)) : '',
  }
  uploadError.value = ''
  isEditModalOpen.value = true
}

// Save Edited Post
const handleUpdatePost = async () => {
  if (!editingPost.value) return
  isSubmitting.value = true
  try {
    await store.updateSocialPost(editingPost.value.id, {
      content: editingPost.value.content,
      media_urls: editingPost.value.media_urls,
      platforms: editingPost.value.platforms,
      status: editingPost.value.status,
      scheduled_at: editingPost.value.scheduled_at || null,
    })
    isEditModalOpen.value = false
    editingPost.value = null
  } finally {
    isSubmitting.value = false
  }
}

// Publish Post Now
const handlePublishNow = async (post) => {
  if (confirm('هل ترغب في نشر هذا المنشور فوراً الآن؟')) {
    await store.publishSocialPostNow(post.id)
  }
}

// Delete Post
const handleDeletePost = async (post) => {
  if (confirm('هل أنت متأكد من حذف هذا المنشور؟')) {
    await store.deleteSocialPost(post.id)
  }
}

// Navigate to Settings
const goToSocialSettings = () => {
  window.location.hash = '#settings-social'
  store.activeView = 'settings'
}

// Analytics Data & Computed Helpers
const analyticsSummary = computed(() => store.socialAnalytics?.summary || {
  total_pages: 0,
  total_posts: 0,
  total_followers: 0,
  total_likes: 0,
  total_comments: 0,
  total_shares: 0,
  total_interactions: 0,
  average_engagement_rate: 0,
  total_reach: 0,
  total_impressions: 0,
})

const analyticsPages = computed(() => {
  const pages = store.socialAnalytics?.pages || []
  if (selectedAnalyticsPlatform.value === 'all') return pages
  return pages.filter(p => p.platform === selectedAnalyticsPlatform.value)
})

const handleSyncExternalPosts = async () => {
  isSyncingPosts.value = true
  try {
    const ok = await store.syncSocialPosts()
    if (ok) {
      await store.loadSocialAnalytics(selectedAnalyticsPlatform.value)
    }
  } catch (e) {
    console.error('فشل مزامنة المنشورات', e)
  } finally {
    isSyncingPosts.value = false
  }
}

const handleFilterAnalyticsPlatform = async (p) => {
  selectedAnalyticsPlatform.value = p
  await store.loadSocialAnalytics(p)
}

// Computed Posts List with Filters
const filteredPosts = computed(() => {
  if (currentTab.value === 'analytics') {
    return []
  }

  let list = store.socialPosts || []

  // Tab filter
  if (currentTab.value === 'scheduled') {
    list = list.filter(p => p.status === 'scheduled')
  } else if (currentTab.value === 'published') {
    list = list.filter(p => p.status === 'published')
  } else if (currentTab.value === 'draft') {
    list = list.filter(p => p.status === 'draft')
  }

  // Platform filter
  if (selectedPlatformFilter.value !== 'all') {
    list = list.filter(p => Array.isArray(p.platforms) && p.platforms.includes(selectedPlatformFilter.value))
  }

  // Search filter
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()
    list = list.filter(p => p.content && p.content.toLowerCase().includes(q))
  }

  return list
})

// Counts
const counts = computed(() => {
  const posts = store.socialPosts || []
  return {
    all: posts.length,
    scheduled: posts.filter(p => p.status === 'scheduled').length,
    published: posts.filter(p => p.status === 'published').length,
    draft: posts.filter(p => p.status === 'draft').length,
    analytics: (store.socialAnalytics?.pages || []).length,
    accounts: (store.socialAccounts || []).length,
  }
})

// Platform metadata helper
const getPlatformMeta = (platform) => {
  switch (platform) {
    case 'facebook':
      return {
        name: 'فيسبوك',
        icon: 'f',
        color: 'bg-blue-600',
        textColor: 'text-blue-600',
        borderColor: 'border-blue-500/30',
        bgLight: 'bg-blue-500/10',
      }
    case 'instagram':
      return {
        name: 'انستجرام',
        icon: '📸',
        color: 'bg-gradient-to-r from-pink-600 via-rose-500 to-amber-500',
        textColor: 'text-rose-600',
        borderColor: 'border-rose-500/30',
        bgLight: 'bg-rose-500/10',
      }
    case 'youtube':
      return {
        name: 'يوتيوب',
        icon: '▶',
        color: 'bg-red-600',
        textColor: 'text-red-600',
        borderColor: 'border-red-500/30',
        bgLight: 'bg-red-500/10',
      }
    case 'linkedin':
      return {
        name: 'لينكد إن',
        icon: 'in',
        color: 'bg-sky-700',
        textColor: 'text-sky-700',
        borderColor: 'border-sky-600/30',
        bgLight: 'bg-sky-600/10',
      }
    default:
      return {
        name: platform,
        icon: '🌐',
        color: 'bg-slate-600',
        textColor: 'text-slate-600',
        borderColor: 'border-slate-500/30',
        bgLight: 'bg-slate-500/10',
      }
  }
}

// Date formatter
const formatDate = (dateStr) => {
  if (!dateStr) return ''
  try {
    const d = new Date(dateStr)
    return new Intl.DateTimeFormat('ar-EG', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(d)
  } catch (e) {
    return dateStr
  }
}

onMounted(() => {
  window.addEventListener('message', handleOAuthWindowMessage)
  if (store.token) {
    if (!store.socialAccounts || !store.socialAccounts.length) {
      store.loadSocialAccounts(true)
    }
    if (!store.socialPosts || !store.socialPosts.length) {
      store.loadSocialPosts(true)
    }
    if (typeof store.loadSocialAnalytics === 'function') {
      store.loadSocialAnalytics('all')
    }
  }
})

onUnmounted(() => {
  window.removeEventListener('message', handleOAuthWindowMessage)
})
</script>

<template>
  <div class="space-y-6 text-right" dir="rtl">
    
    <!-- Top Bar: Title & Global Actions -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b border-slate-200/80 dark:border-slate-800">
      <div>
        <div class="flex items-center gap-2">
          <span class="text-2xl">🚀</span>
          <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-slate-100">
            إدارة السوشيال ميديا
          </h2>
          <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-violet-500/10 text-violet-600 dark:text-violet-400 border border-violet-500/20">
            خاص بك 🔒
          </span>
        </div>
        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
          ربط صفحات وحسابات التواصل الاجتماعي، ونشر وجدولة المحتوى بسهولة مع عزل تام للبيانات
        </p>
      </div>

      <div class="flex items-center gap-2.5 flex-wrap">
        <button
          @click="openComposer"
          class="px-4 py-2.5 rounded-2xl bg-gradient-to-l from-violet-600 to-indigo-600 text-white font-extrabold text-xs shadow-md shadow-violet-500/20 hover:opacity-95 active:scale-95 transition cursor-pointer flex items-center gap-1.5"
        >
          <span>✍️</span>
          <span>منشور جديد</span>
        </button>

        <button
          @click="handleSyncExternalPosts"
          :disabled="isSyncingPosts"
          class="px-3.5 py-2.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer flex items-center gap-1.5 shadow-sm disabled:opacity-50"
          title="مزامنة منشورات فيسبوك وإنستجرام وتحديث نسب الأداء"
        >
          <span :class="{'animate-spin inline-block': isSyncingPosts}">🔄</span>
          <span>{{ isSyncingPosts ? 'جارِ المزامنة...' : 'مزامنة المنشورات' }}</span>
        </button>

        <button
          @click="openConnectModal('facebook')"
          class="px-3.5 py-2.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer flex items-center gap-1.5 shadow-sm"
        >
          <span>🔗</span>
          <span>ربط حساب</span>
        </button>

        <button
          @click="goToSocialSettings"
          class="p-2.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition cursor-pointer shadow-sm"
          title="إعدادات الـ API"
        >
          ⚙️
        </button>
      </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm flex items-center gap-3">
        <div class="w-11 h-11 rounded-2xl bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center text-xl shrink-0">
          🔗
        </div>
        <div>
          <span class="block text-xl sm:text-2xl font-black text-slate-900 dark:text-slate-100">
            {{ counts.accounts }}
          </span>
          <span class="text-xs text-slate-400 font-bold">الحسابات المتصلة</span>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm flex items-center gap-3">
        <div class="w-11 h-11 rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl shrink-0">
          ⏰
        </div>
        <div>
          <span class="block text-xl sm:text-2xl font-black text-slate-900 dark:text-slate-100">
            {{ counts.scheduled }}
          </span>
          <span class="text-xs text-slate-400 font-bold">منشورات مجدولة</span>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm flex items-center gap-3">
        <div class="w-11 h-11 rounded-2xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl shrink-0">
          ✅
        </div>
        <div>
          <span class="block text-xl sm:text-2xl font-black text-slate-900 dark:text-slate-100">
            {{ counts.published }}
          </span>
          <span class="text-xs text-slate-400 font-bold">منشورات تم نشرها</span>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm flex items-center gap-3">
        <div class="w-11 h-11 rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xl shrink-0">
          📝
        </div>
        <div>
          <span class="block text-xl sm:text-2xl font-black text-slate-900 dark:text-slate-100">
            {{ counts.draft }}
          </span>
          <span class="text-xs text-slate-400 font-bold">مسودات المنشورات</span>
        </div>
      </div>
    </div>

    <!-- Connected Accounts Section -->
    <div class="space-y-3">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-black text-slate-800 dark:text-slate-200">
          الحسابات والصفحات المربوطة
        </h3>
        <span class="text-xs text-slate-400">
          {{ store.socialAccounts.length }} حسابات مسجلة
        </span>
      </div>

      <!-- Accounts Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        
        <!-- Connected Accounts Cards -->
        <div
          v-for="acc in store.socialAccounts"
          :key="acc.id"
          class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm relative group hover:border-violet-500/40 transition"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-2.5">
              <div
                v-if="acc.avatar_url"
                class="w-10 h-10 rounded-2xl bg-cover bg-center border border-slate-200 dark:border-slate-700 shrink-0"
                :style="{ backgroundImage: `url(${acc.avatar_url})` }"
              ></div>
              <div
                v-else
                :class="[
                  'w-10 h-10 rounded-2xl flex items-center justify-center font-bold text-white text-sm shrink-0 shadow-sm',
                  getPlatformMeta(acc.platform).color
                ]"
              >
                {{ getPlatformMeta(acc.platform).icon }}
              </div>

              <div class="truncate">
                <h4 class="text-xs font-black text-slate-900 dark:text-slate-100 truncate">
                  {{ acc.account_name }}
                </h4>
                <p class="text-[11px] text-slate-400 truncate">
                  {{ acc.account_username ? '@' + acc.account_username : getPlatformMeta(acc.platform).name }}
                </p>
              </div>
            </div>

            <button
              @click="handleDisconnect(acc)"
              class="opacity-0 group-hover:opacity-100 text-rose-500 hover:text-rose-700 p-1.5 rounded-xl hover:bg-rose-50 dark:hover:bg-rose-950/30 transition text-xs cursor-pointer"
              title="فصل الحساب"
            >
              ✕
            </button>
          </div>

          <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px]">
            <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-bold">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              متصل وجاهز
            </span>
            <span v-if="acc.followers_count" class="text-slate-400 font-medium">
              {{ Number(acc.followers_count).toLocaleString() }} متابع
            </span>
          </div>
        </div>

        <!-- Add Account Card -->
        <button
          @click="openConnectModal('facebook')"
          class="min-h-[105px] border-2 border-dashed border-slate-200 dark:border-slate-800 hover:border-violet-500 rounded-3xl p-4 flex flex-col items-center justify-center gap-2 text-slate-500 hover:text-violet-600 dark:hover:text-violet-400 transition cursor-pointer group bg-slate-50/50 dark:bg-slate-900/30"
        >
          <div class="w-8 h-8 rounded-full bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center font-bold text-sm group-hover:scale-110 transition">
            +
          </div>
          <span class="text-xs font-bold">ربط حساب / صفحة جديدة</span>
        </button>

      </div>
    </div>

    <!-- Posts Management & Feed Section -->
    <div class="space-y-4">
      
      <!-- Filter Bar -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        
        <!-- Status Tabs -->
        <div class="flex items-center gap-1 overflow-x-auto pb-1 md:pb-0">
          <button
            @click="currentTab = 'all'"
            :class="[
              'px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer whitespace-nowrap',
              currentTab === 'all'
                ? 'bg-violet-600 text-white shadow-sm'
                : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
            ]"
          >
            الكل ({{ counts.all }})
          </button>

          <button
            @click="currentTab = 'scheduled'"
            :class="[
              'px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer whitespace-nowrap',
              currentTab === 'scheduled'
                ? 'bg-amber-600 text-white shadow-sm'
                : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
            ]"
          >
            ⏰ المجدولة ({{ counts.scheduled }})
          </button>

          <button
            @click="currentTab = 'published'"
            :class="[
              'px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer whitespace-nowrap',
              currentTab === 'published'
                ? 'bg-emerald-600 text-white shadow-sm'
                : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
            ]"
          >
            ✅ المنشورة ({{ counts.published }})
          </button>

          <button
            @click="currentTab = 'draft'"
            :class="[
              'px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer whitespace-nowrap',
              currentTab === 'draft'
                ? 'bg-blue-600 text-white shadow-sm'
                : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
            ]"
          >
            📝 المسودات ({{ counts.draft }})
          </button>

          <button
            @click="currentTab = 'analytics'"
            :class="[
              'px-3.5 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer whitespace-nowrap flex items-center gap-1.5',
              currentTab === 'analytics'
                ? 'bg-gradient-to-l from-violet-600 to-indigo-600 text-white shadow-sm'
                : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
            ]"
          >
            <span>📊</span>
            <span>نسب التحليلات والصفحات ({{ counts.analytics }})</span>
          </button>
        </div>

        <!-- Platform & Search Filters -->
        <div class="flex items-center gap-2">
          <!-- Platform Filter Select -->
          <select
            v-if="currentTab !== 'analytics'"
            v-model="selectedPlatformFilter"
            class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-1.5 text-xs text-slate-700 dark:text-slate-300 focus:outline-none"
          >
            <option value="all">جميع المنصات</option>
            <option value="facebook">فيسبوك</option>
            <option value="instagram">انستجرام</option>
            <option value="youtube">يوتيوب</option>
            <option value="linkedin">لينكد إن</option>
          </select>

          <!-- Search Input -->
          <input
            v-if="currentTab !== 'analytics'"
            v-model="searchQuery"
            type="text"
            placeholder="بحث في المحتوى..."
            class="w-full sm:w-48 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-1.5 text-xs text-slate-700 dark:text-slate-300 focus:outline-none"
          />

          <!-- Sync Button in Filter Bar -->
          <button
            @click="handleSyncExternalPosts"
            :disabled="isSyncingPosts"
            class="px-3 py-1.5 rounded-xl bg-violet-50 dark:bg-violet-950/40 border border-violet-200 dark:border-violet-800/60 text-violet-700 dark:text-violet-300 font-bold text-xs hover:bg-violet-100 dark:hover:bg-violet-900/60 transition cursor-pointer flex items-center gap-1.5 disabled:opacity-50 shrink-0"
            title="مزامنة منشورات فيسبوك وإنستجرام وتحديث نسب الأداء"
          >
            <span :class="{'animate-spin inline-block': isSyncingPosts}">🔄</span>
            <span class="hidden sm:inline">{{ isSyncingPosts ? 'جارِ المزامنة...' : 'مزامنة الصفحات' }}</span>
          </button>
        </div>

      </div>

      <!-- Posts Feed View (when not viewing analytics) -->
      <div v-if="currentTab !== 'analytics'" class="space-y-4">
        <!-- Empty State -->
        <div
          v-if="!filteredPosts.length"
        class="text-center py-12 bg-white dark:bg-slate-900 border border-dashed border-slate-200 dark:border-slate-800 rounded-3xl p-6"
      >
        <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center text-2xl">
          📱
        </div>
        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">لا توجد منشورات في هذا القسم</h4>
        <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
          يمكنك البدء بكتابة محتوى جديد وجدولته أو نشره فوراً إلى جميع صفحاتك وحساباتك بضغطة واحدة.
        </p>
        <button
          @click="openComposer"
          class="mt-4 px-4 py-2 rounded-xl bg-violet-600 text-white font-bold text-xs shadow-sm hover:bg-violet-700 transition cursor-pointer"
        >
          إنشاء أول منشور الآن
        </button>
      </div>

      <!-- Posts Cards Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        
        <div
          v-for="post in filteredPosts"
          :key="post.id"
          class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm flex flex-col justify-between space-y-4 hover:border-violet-500/30 transition group"
        >
          <div>
            <!-- Post Header: Platforms & Status -->
            <div class="flex items-center justify-between gap-2 pb-3 border-b border-slate-100 dark:border-slate-800/80">
              <!-- Platforms Icons -->
              <div class="flex items-center gap-1.5">
                <span
                  v-for="p in post.platforms"
                  :key="p"
                  :class="[
                    'w-6 h-6 rounded-lg flex items-center justify-center text-[11px] font-bold text-white shadow-xs',
                    getPlatformMeta(p).color
                  ]"
                  :title="getPlatformMeta(p).name"
                >
                  {{ getPlatformMeta(p).icon }}
                </span>
              </div>

              <!-- Status Badge -->
              <div>
                <span
                  v-if="post.status === 'published'"
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20"
                >
                  ✓ تم النشر
                </span>
                <span
                  v-else-if="post.status === 'scheduled'"
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20"
                >
                  ⏰ مجدول
                </span>
                <span
                  v-else
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/20"
                >
                  📝 مسودة
                </span>
              </div>
            </div>

            <!-- Post Content -->
            <div class="mt-3">
              <p class="text-xs text-slate-800 dark:text-slate-200 leading-relaxed whitespace-pre-line line-clamp-4">
                {{ post.content }}
              </p>
            </div>

            <!-- Media Previews -->
            <div
              v-if="post.media_urls && post.media_urls.length"
              class="mt-3 grid grid-cols-2 gap-1.5 rounded-2xl overflow-hidden max-h-36"
            >
              <template v-for="(media, idx) in post.media_urls.slice(0, 2)" :key="idx">
                <div class="relative w-full h-24 rounded-xl overflow-hidden bg-slate-900">
                  <video
                    v-if="isVideoUrl(media)"
                    :src="media"
                    controls
                    class="w-full h-full object-cover"
                  ></video>
                  <img
                    v-else
                    :src="media"
                    alt="Media"
                    class="w-full h-full object-cover"
                    onerror="this.style.display='none'"
                  />
                  <div
                    v-if="isVideoUrl(media)"
                    class="absolute top-1 right-1 px-1.5 py-0.5 rounded bg-black/70 text-[9px] font-bold text-white pointer-events-none"
                  >
                    🎬 فيديو
                  </div>
                </div>
              </template>
            </div>

            <!-- Post Interaction & Analytics Metrics Bar -->
            <div
              v-if="post.metrics"
              class="mt-3 bg-slate-50 dark:bg-slate-800/60 rounded-2xl p-2.5 border border-slate-100 dark:border-slate-800 text-[11px] space-y-1.5"
            >
              <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
                <span class="font-bold flex items-center gap-1 text-[10px]">
                  <span>📊</span>
                  <span>تحليلات أداء المنشور</span>
                </span>
                <span
                  v-if="post.metrics.engagement_rate !== undefined"
                  class="px-2 py-0.5 rounded-full font-black text-[10px] bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20"
                >
                  معدل التفاعل: {{ post.metrics.engagement_rate }}%
                </span>
              </div>

              <div class="grid grid-cols-4 gap-1 text-center pt-0.5">
                <div class="bg-white dark:bg-slate-900 rounded-xl py-1 px-1 border border-slate-100 dark:border-slate-800">
                  <span class="block text-[9px] text-slate-400 font-bold">👍 إعجابات</span>
                  <span class="font-black text-slate-800 dark:text-slate-200 text-xs">
                    {{ (post.metrics.likes || 0).toLocaleString() }}
                  </span>
                </div>

                <div class="bg-white dark:bg-slate-900 rounded-xl py-1 px-1 border border-slate-100 dark:border-slate-800">
                  <span class="block text-[9px] text-slate-400 font-bold">💬 تعليقات</span>
                  <span class="font-black text-slate-800 dark:text-slate-200 text-xs">
                    {{ (post.metrics.comments || 0).toLocaleString() }}
                  </span>
                </div>

                <div class="bg-white dark:bg-slate-900 rounded-xl py-1 px-1 border border-slate-100 dark:border-slate-800">
                  <span class="block text-[9px] text-slate-400 font-bold">🔄 مشاركات</span>
                  <span class="font-black text-slate-800 dark:text-slate-200 text-xs">
                    {{ (post.metrics.shares || 0).toLocaleString() }}
                  </span>
                </div>

                <div class="bg-white dark:bg-slate-900 rounded-xl py-1 px-1 border border-slate-100 dark:border-slate-800">
                  <span class="block text-[9px] text-slate-400 font-bold">👁️ مشاهدات</span>
                  <span class="font-black text-slate-800 dark:text-slate-200 text-xs">
                    {{ ((post.metrics.views || post.metrics.impressions || (post.metrics.likes ? post.metrics.likes * 8 : 0))).toLocaleString() }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Post Footer & Timestamps -->
          <div class="pt-3 border-t border-slate-100 dark:border-slate-800/80 space-y-2.5">
            <div class="flex items-center justify-between text-[11px] text-slate-400 font-medium">
              <span v-if="post.status === 'scheduled' && post.scheduled_at">
                موعد النشر: {{ formatDate(post.scheduled_at) }}
              </span>
              <span v-else-if="post.status === 'published' && post.published_at">
                نُشر: {{ formatDate(post.published_at) }}
              </span>
              <span v-else>
                أُنشئ: {{ formatDate(post.created_at) }}
              </span>
            </div>

            <!-- Published Platform Links (if available) -->
            <div
              v-if="post.platform_post_ids && Object.keys(post.platform_post_ids).length"
              class="flex items-center gap-1.5 flex-wrap pt-1"
            >
              <span class="text-[10px] text-slate-400">مشاهدة على:</span>
              <a
                v-for="(info, p) in post.platform_post_ids"
                :key="p"
                :href="info.url"
                target="_blank"
                class="text-[10px] font-bold text-violet-600 dark:text-violet-400 hover:underline inline-flex items-center gap-0.5"
              >
                {{ getPlatformMeta(p).name }} ↗
              </a>
            </div>

            <!-- Actions Bar -->
            <div class="flex items-center justify-between pt-1">
              <div class="flex items-center gap-1">
                <button
                  v-if="post.status !== 'published'"
                  @click="handlePublishNow(post)"
                  class="px-2.5 py-1 rounded-xl bg-violet-600 text-white text-[11px] font-bold hover:bg-violet-700 transition cursor-pointer"
                  title="نشر الآن فوراً"
                >
                  🚀 نشر الآن
                </button>

                <button
                  @click="openEditModal(post)"
                  class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs cursor-pointer"
                  title="تعديل"
                >
                  ✏️
                </button>
              </div>

              <button
                @click="handleDeletePost(post)"
                class="text-rose-500 hover:text-rose-700 text-xs font-bold cursor-pointer p-1"
                title="حذف المنشور"
              >
                🗑️
              </button>
            </div>
          </div>

        </div>

      </div>

      </div>

      <!-- Tab View 2: Dedicated Page Analytics & Performance View (when currentTab === 'analytics') -->
      <div v-else class="space-y-6">
        
        <!-- Analytics Header & Sync Banner -->
        <div class="bg-gradient-to-r from-violet-600 via-indigo-600 to-purple-700 rounded-3xl p-6 text-white shadow-lg relative overflow-hidden">
          <div class="absolute -left-10 -bottom-10 w-48 h-48 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
          <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
              <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-white/20 text-white backdrop-blur-md mb-2">
                <span>📊</span>
                <span>تحليلات وإحصائيات دقيقة</span>
              </span>
              <h3 class="text-xl sm:text-2xl font-black">
                نسب أداء وتفاعل صفحات فيسبوك وإنستجرام
              </h3>
              <p class="text-xs sm:text-sm text-violet-100 mt-1 max-w-xl">
                رصد شامل لنسب التفاعل، توزيع الإعجابات والمشاركات والتعليقات، مع تحليل تفصيلي ومقارنة لأداء كل صفحة وحساب مربوط.
              </p>
            </div>

            <div class="flex items-center gap-2">
              <button
                @click="handleSyncExternalPosts"
                :disabled="isSyncingPosts"
                class="px-4 py-2.5 rounded-2xl bg-white text-violet-900 font-extrabold text-xs shadow-md hover:bg-violet-50 transition cursor-pointer flex items-center gap-2 disabled:opacity-50"
              >
                <span :class="{'animate-spin inline-block': isSyncingPosts}">🔄</span>
                <span>{{ isSyncingPosts ? 'جارِ المزامنة...' : 'مزامنة وتحديث البيانات الآن' }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Overall KPI Cards Grid -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
          
          <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm text-center">
            <span class="text-xs text-slate-400 font-bold block mb-1">الصفحات المربوطة</span>
            <span class="text-2xl font-black text-slate-900 dark:text-slate-100">
              {{ analyticsSummary.total_pages }}
            </span>
            <span class="text-[11px] text-violet-600 dark:text-violet-400 font-semibold block mt-1">صفحة نشطة</span>
          </div>

          <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm text-center">
            <span class="text-xs text-slate-400 font-bold block mb-1">إجمالي المتابعين</span>
            <span class="text-2xl font-black text-slate-900 dark:text-slate-100">
              {{ Number(analyticsSummary.total_followers || 0).toLocaleString() }}
            </span>
            <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold block mt-1">متابع للصفحات</span>
          </div>

          <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm text-center">
            <span class="text-xs text-slate-400 font-bold block mb-1">المنشورات المحللة</span>
            <span class="text-2xl font-black text-slate-900 dark:text-slate-100">
              {{ analyticsSummary.total_posts }}
            </span>
            <span class="text-[11px] text-blue-600 dark:text-blue-400 font-semibold block mt-1">منشور منشور</span>
          </div>

          <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm text-center">
            <span class="text-xs text-slate-400 font-bold block mb-1">إجمالي التفاعلات</span>
            <span class="text-2xl font-black text-slate-900 dark:text-slate-100">
              {{ Number(analyticsSummary.total_interactions || 0).toLocaleString() }}
            </span>
            <span class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold block mt-1">إعجاب ومشاركة وتعليق</span>
          </div>

          <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm text-center">
            <span class="text-xs text-slate-400 font-bold block mb-1">متوسط نسبة التفاعل</span>
            <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400">
              {{ analyticsSummary.average_engagement_rate }}%
            </span>
            <span class="text-[11px] text-slate-400 font-semibold block mt-1">معدل صحي وقوي</span>
          </div>

          <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-4 shadow-sm text-center">
            <span class="text-xs text-slate-400 font-bold block mb-1">إجمالي الوصول</span>
            <span class="text-2xl font-black text-slate-900 dark:text-slate-100">
              {{ Number(analyticsSummary.total_reach || 0).toLocaleString() }}
            </span>
            <span class="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold block mt-1">مشاهدة وظهور</span>
          </div>

        </div>

        <!-- Filter Sub-bar for Analytics Pages -->
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <span class="text-xs font-black text-slate-700 dark:text-slate-300">عرض تحليلات:</span>
            <div class="flex items-center gap-1">
              <button
                @click="handleFilterAnalyticsPlatform('all')"
                :class="[
                  'px-3 py-1 rounded-xl text-xs font-bold transition cursor-pointer',
                  selectedAnalyticsPlatform === 'all'
                    ? 'bg-violet-600 text-white'
                    : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'
                ]"
              >
                جميع المنصات
              </button>
              <button
                @click="handleFilterAnalyticsPlatform('facebook')"
                :class="[
                  'px-3 py-1 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-1',
                  selectedAnalyticsPlatform === 'facebook'
                    ? 'bg-blue-600 text-white'
                    : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'
                ]"
              >
                <span>فيسبوك</span>
              </button>
              <button
                @click="handleFilterAnalyticsPlatform('instagram')"
                :class="[
                  'px-3 py-1 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-1',
                  selectedAnalyticsPlatform === 'instagram'
                    ? 'bg-rose-600 text-white'
                    : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700'
                ]"
              >
                <span>إنستجرام</span>
              </button>
            </div>
          </div>

          <span class="text-xs text-slate-400">
            {{ analyticsPages.length }} صفحة وحساب
          </span>
        </div>

        <!-- Empty State for Pages Analytics -->
        <div
          v-if="!analyticsPages.length"
          class="text-center py-12 bg-white dark:bg-slate-900 border border-dashed border-slate-200 dark:border-slate-800 rounded-3xl p-6"
        >
          <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center text-2xl">
            📊
          </div>
          <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">لا توجد صفحات مربوطة أو بيانات تحليلات حالياً</h4>
          <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
            قم بربط صفحة فيسبوك أو حساب أعمال إنستجرام لمشاهدة جميع منشوراتها ونسب التفاعل ومعدلات الوصول بشكل فوري.
          </p>
          <div class="mt-4 flex items-center justify-center gap-2">
            <button
              @click="openConnectModal('facebook')"
              class="px-4 py-2 rounded-xl bg-blue-600 text-white font-bold text-xs shadow-sm hover:bg-blue-700 transition cursor-pointer"
            >
              ربط صفحة فيسبوك
            </button>
            <button
              @click="openConnectModal('instagram')"
              class="px-4 py-2 rounded-xl bg-gradient-to-r from-pink-600 to-rose-600 text-white font-bold text-xs shadow-sm hover:opacity-90 transition cursor-pointer"
            >
              ربط حساب إنستجرام
            </button>
          </div>
        </div>

        <!-- Detailed Cards Grid for Each Page -->
        <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <div
            v-for="page in analyticsPages"
            :key="page.id"
            class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 shadow-sm space-y-5 hover:border-violet-500/40 transition flex flex-col justify-between"
          >
            <!-- Page Header -->
            <div>
              <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                  <div
                    v-if="page.avatar_url"
                    class="w-12 h-12 rounded-2xl bg-cover bg-center border border-slate-200 dark:border-slate-700 shrink-0"
                    :style="{ backgroundImage: `url(${page.avatar_url})` }"
                  ></div>
                  <div
                    v-else
                    :class="[
                      'w-12 h-12 rounded-2xl flex items-center justify-center font-black text-white text-base shrink-0 shadow-sm',
                      getPlatformMeta(page.platform).color
                    ]"
                  >
                    {{ getPlatformMeta(page.platform).icon }}
                  </div>

                  <div>
                    <div class="flex items-center gap-2">
                      <h4 class="text-sm font-black text-slate-900 dark:text-slate-100">
                        {{ page.account_name }}
                      </h4>
                      <span
                        :class="[
                          'px-2 py-0.5 rounded-full text-[10px] font-bold text-white',
                          getPlatformMeta(page.platform).color
                        ]"
                      >
                        {{ getPlatformMeta(page.platform).name }}
                      </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">
                      {{ page.account_username ? '@' + page.account_username : page.account_id }}
                    </p>
                  </div>
                </div>

                <div class="text-left">
                  <span class="block text-sm font-black text-slate-900 dark:text-slate-100">
                    {{ Number(page.followers_count || 0).toLocaleString() }}
                  </span>
                  <div class="flex items-center gap-1 justify-end text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">
                    <span>{{ page.growth_rate || '+3.4%' }}</span>
                    <span>📈</span>
                  </div>
                </div>
              </div>

              <!-- 4 Performance Percentages Grid -->
              <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800/80 space-y-3">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
                    نسب ومعدلات أداء الصفحة
                  </span>
                  <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                    معدل التفاعل: {{ page.engagement_rate }}%
                  </span>
                </div>

                <!-- Three Interaction Distribution Progress Bars -->
                <div class="space-y-2.5 bg-slate-50 dark:bg-slate-800/40 p-3.5 rounded-2xl border border-slate-100 dark:border-slate-800/60">
                  
                  <!-- Likes Percentage -->
                  <div>
                    <div class="flex items-center justify-between text-xs mb-1">
                      <span class="font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1">
                        <span>👍</span>
                        <span>نسبة الإعجابات</span>
                      </span>
                      <div class="flex items-center gap-2">
                        <span class="text-slate-400 text-[11px]">({{ Number(page.total_likes || 0).toLocaleString() }})</span>
                        <span class="font-black text-blue-600 dark:text-blue-400">{{ page.likes_percentage }}%</span>
                      </div>
                    </div>
                    <div class="w-full h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                      <div
                        class="h-full bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full transition-all duration-500"
                        :style="{ width: `${Math.min(100, page.likes_percentage || 0)}%` }"
                      ></div>
                    </div>
                  </div>

                  <!-- Comments Percentage -->
                  <div>
                    <div class="flex items-center justify-between text-xs mb-1">
                      <span class="font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1">
                        <span>💬</span>
                        <span>نسبة التعليقات</span>
                      </span>
                      <div class="flex items-center gap-2">
                        <span class="text-slate-400 text-[11px]">({{ Number(page.total_comments || 0).toLocaleString() }})</span>
                        <span class="font-black text-emerald-600 dark:text-emerald-400">{{ page.comments_percentage }}%</span>
                      </div>
                    </div>
                    <div class="w-full h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                      <div
                        class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 rounded-full transition-all duration-500"
                        :style="{ width: `${Math.min(100, page.comments_percentage || 0)}%` }"
                      ></div>
                    </div>
                  </div>

                  <!-- Shares Percentage -->
                  <div>
                    <div class="flex items-center justify-between text-xs mb-1">
                      <span class="font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1">
                        <span>🔄</span>
                        <span>نسبة المشاركات</span>
                      </span>
                      <div class="flex items-center gap-2">
                        <span class="text-slate-400 text-[11px]">({{ Number(page.total_shares || 0).toLocaleString() }})</span>
                        <span class="font-black text-purple-600 dark:text-purple-400">{{ page.shares_percentage }}%</span>
                      </div>
                    </div>
                    <div class="w-full h-2 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
                      <div
                        class="h-full bg-gradient-to-r from-purple-500 to-pink-500 rounded-full transition-all duration-500"
                        :style="{ width: `${Math.min(100, page.shares_percentage || 0)}%` }"
                      ></div>
                    </div>
                  </div>

                </div>

                <!-- Page Metric Summary Strip -->
                <div class="grid grid-cols-3 gap-2 text-center pt-1">
                  <div class="bg-slate-50 dark:bg-slate-800/60 rounded-xl py-2 px-2 border border-slate-100 dark:border-slate-800">
                    <span class="block text-[10px] text-slate-400 font-bold">عدد المنشورات</span>
                    <span class="font-black text-slate-800 dark:text-slate-200 text-sm">
                      {{ page.posts_count }}
                    </span>
                  </div>

                  <div class="bg-slate-50 dark:bg-slate-800/60 rounded-xl py-2 px-2 border border-slate-100 dark:border-slate-800">
                    <span class="block text-[10px] text-slate-400 font-bold">تفاعل / منشور</span>
                    <span class="font-black text-slate-800 dark:text-slate-200 text-sm">
                      {{ page.interactions_per_post }}
                    </span>
                  </div>

                  <div class="bg-slate-50 dark:bg-slate-800/60 rounded-xl py-2 px-2 border border-slate-100 dark:border-slate-800">
                    <span class="block text-[10px] text-slate-400 font-bold">الوصول الإجمالي</span>
                    <span class="font-black text-slate-800 dark:text-slate-200 text-sm">
                      {{ Number(page.total_views || 0).toLocaleString() }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- No posts empty state for this page -->
            <div
              v-if="page.posts_count === 0"
              class="pt-3 border-t border-slate-100 dark:border-slate-800/80 bg-slate-50/70 dark:bg-slate-800/30 p-3.5 rounded-2xl border border-slate-200/60 dark:border-slate-700/50 text-center space-y-1.5"
            >
              <div class="text-xs font-bold text-slate-700 dark:text-slate-300">
                لا توجد منشورات منشورة على هذه الصفحة حالياً
              </div>
              <p class="text-[11px] text-slate-400 max-w-sm mx-auto">
                لم يتم العثور على منشورات في صفحة "{{ page.account_name }}" على فيسبوك/إنستجرام. عند نشر أي منشور جديد سيظهر هنا تلقائياً مع تحليلاته ونسبه الحقيقية.
              </p>
              <button
                @click="handleSyncExternalPosts"
                :disabled="isSyncingPosts"
                class="mt-1 px-3 py-1 rounded-xl bg-violet-50 dark:bg-violet-950/50 text-violet-600 dark:text-violet-400 font-bold text-[11px] hover:bg-violet-100 transition inline-flex items-center gap-1 cursor-pointer"
              >
                <span>🔄</span>
                <span>فحص ومزامنة المنشورات الآن</span>
              </button>
            </div>

            <!-- Top Post Spotlight for this Page -->
            <div
              v-else-if="page.top_post"
              class="pt-3 border-t border-slate-100 dark:border-slate-800/80 bg-violet-500/5 dark:bg-violet-950/20 p-3 rounded-2xl border border-violet-500/10 space-y-1.5"
            >
              <div class="flex items-center justify-between text-[11px]">
                <span class="font-extrabold text-violet-700 dark:text-violet-300 flex items-center gap-1">
                  <span>⭐</span>
                  <span>المنشور الأكثر تفاعلاً في الصفحة:</span>
                </span>
                <span
                  v-if="page.top_post.metrics?.engagement_rate"
                  class="font-black text-[10px] text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full"
                >
                  {{ page.top_post.metrics.engagement_rate }}% تفاعل
                </span>
              </div>

              <p class="text-xs text-slate-700 dark:text-slate-300 line-clamp-2 leading-relaxed">
                {{ page.top_post.content }}
              </p>

              <div class="flex items-center justify-between text-[10px] text-slate-400 pt-1">
                <div class="flex items-center gap-3">
                  <span>👍 {{ page.top_post.metrics?.likes || 0 }}</span>
                  <span>💬 {{ page.top_post.metrics?.comments || 0 }}</span>
                  <span>🔄 {{ page.top_post.metrics?.shares || 0 }}</span>
                </div>

                <a
                  v-if="page.top_post.platform_post_ids && page.top_post.platform_post_ids[page.platform]?.url"
                  :href="page.top_post.platform_post_ids[page.platform].url"
                  target="_blank"
                  class="text-violet-600 dark:text-violet-400 font-bold hover:underline"
                >
                  مشاهدة المنشور الأصلي ↗
                </a>
              </div>
            </div>

            <!-- View All Posts of This Page Accordion -->
            <div v-if="page.posts && page.posts.length > 0" class="pt-2 border-t border-slate-100 dark:border-slate-800/80">
              <button
                @click="togglePagePosts(page.id)"
                class="w-full flex items-center justify-between px-3 py-2 rounded-xl bg-slate-50 dark:bg-slate-800/50 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300 transition cursor-pointer"
              >
                <span class="flex items-center gap-1.5">
                  <span>📑</span>
                  <span>عرض جميع منشورات الصفحة ({{ page.posts.length }})</span>
                </span>
                <span class="text-slate-400 text-xs transition-transform duration-200" :class="{'rotate-180': expandedPagePosts[page.id]}">
                  ▼
                </span>
              </button>

              <!-- Collapsible Posts List -->
              <div v-if="expandedPagePosts[page.id]" class="mt-2 space-y-2 max-h-80 overflow-y-auto pr-1">
                <div
                  v-for="post in page.posts"
                  :key="post.id"
                  class="p-2.5 rounded-xl bg-white dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 space-y-2"
                >
                  <div class="flex items-start gap-2">
                    <img
                      v-if="post.media_urls && post.media_urls.length"
                      :src="post.media_urls[0]"
                      alt="post media"
                      class="w-12 h-12 rounded-lg object-cover shrink-0 border border-slate-200 dark:border-slate-700"
                    />
                    <div class="flex-1 min-w-0">
                      <p class="text-xs text-slate-800 dark:text-slate-200 line-clamp-2 leading-snug">
                        {{ post.content }}
                      </p>
                      <span class="text-[10px] text-slate-400 block mt-1">
                        {{ formatDate(post.published_at || post.created_at) }}
                      </span>
                    </div>
                  </div>

                  <div class="flex items-center justify-between text-[11px] pt-1 border-t border-slate-100 dark:border-slate-700/60">
                    <div class="flex items-center gap-2.5 text-slate-500 dark:text-slate-400 text-[10px]">
                      <span>👍 {{ post.metrics?.likes || 0 }}</span>
                      <span>💬 {{ post.metrics?.comments || 0 }}</span>
                      <span>🔄 {{ post.metrics?.shares || 0 }}</span>
                      <span v-if="post.metrics?.engagement_rate" class="text-emerald-600 dark:text-emerald-400 font-bold">
                        {{ post.metrics.engagement_rate }}% تفاعل
                      </span>
                    </div>

                    <a
                      v-if="post.platform_post_ids && post.platform_post_ids[page.platform]?.url"
                      :href="post.platform_post_ids[page.platform].url"
                      target="_blank"
                      class="text-[10px] text-violet-600 dark:text-violet-400 font-bold hover:underline"
                    >
                      عرض على {{ getPlatformMeta(page.platform).name }} ↗
                    </a>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>

      </div>

    </div>

    <!-- ========================================== -->
    <!-- MODAL 1: Create / Schedule Post Composer -->
    <!-- ========================================== -->
    <div
      v-if="isComposerOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in"
      @click.self="isComposerOpen = false"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-5 sm:p-6 space-y-5 shadow-2xl text-right">
        
        <!-- Composer Header -->
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2">
            <span class="text-xl">✍️</span>
            <h3 class="text-base font-black text-slate-900 dark:text-slate-100">
              إنشاء وجدولة منشور جديد
            </h3>
          </div>
          <button
            @click="isComposerOpen = false"
            class="text-slate-400 hover:text-slate-600 text-lg cursor-pointer"
          >
            ✕
          </button>
        </div>

        <!-- Target Platforms Checkboxes -->
        <div class="space-y-1.5">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
            النشر إلى المنصات المحددة:
          </label>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <button
              v-for="p in ['facebook', 'instagram', 'youtube', 'linkedin']"
              :key="p"
              type="button"
              @click="togglePlatform(p)"
              :class="[
                'p-2.5 rounded-2xl border transition flex items-center gap-2 cursor-pointer text-xs font-bold',
                composerForm.platforms.includes(p)
                  ? 'border-violet-600 bg-violet-50 dark:bg-violet-950/40 text-violet-700 dark:text-violet-300 shadow-xs'
                  : 'border-slate-200 dark:border-slate-800 text-slate-500 opacity-60'
              ]"
            >
              <span
                :class="[
                  'w-5 h-5 rounded-lg flex items-center justify-center text-[10px] font-bold text-white',
                  getPlatformMeta(p).color
                ]"
              >
                {{ getPlatformMeta(p).icon }}
              </span>
              <span>{{ getPlatformMeta(p).name }}</span>
            </button>
          </div>
        </div>

        <!-- Post Content Textarea -->
        <div class="space-y-1.5">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">نص المنشور:</label>
            <span class="text-[11px] text-slate-400 font-medium">
              {{ composerForm.content.length }} حرف
            </span>
          </div>

          <textarea
            v-model="composerForm.content"
            rows="5"
            placeholder="ما الذي يدور في ذهنك؟ شارك تحديثاً، خبراً، أو فكرة جديدة مع جمهورك..."
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 text-xs sm:text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-violet-500/20 focus:border-violet-500 transition resize-none"
          ></textarea>

          <!-- Quick Emoji & Hashtags Bar -->
          <div class="flex items-center justify-between flex-wrap gap-2 pt-1 text-xs">
            <!-- Emojis -->
            <div class="flex items-center gap-1 text-base">
              <button
                v-for="em in ['🚀', '💡', '🔥', '✨', '🎯', '📊', '🤝', '🎉']"
                :key="em"
                type="button"
                @click="addEmoji(em)"
                class="hover:scale-125 transition cursor-pointer p-0.5"
              >
                {{ em }}
              </button>
            </div>

            <!-- Hashtag Chips -->
            <div class="flex items-center gap-1 flex-wrap">
              <button
                v-for="tag in ['#أعمال', '#ريادة_أعمال', '#تسويق', '#تقنية']"
                :key="tag"
                type="button"
                @click="addHashtag(tag)"
                class="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10px] text-slate-600 dark:text-slate-400 hover:text-violet-600 font-bold transition cursor-pointer"
              >
                {{ tag }}
              </button>
            </div>
          </div>
        </div>

        <!-- Media Upload & Attachment Section -->
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
              الوسائط المرفقة (صور أو فيديو):
            </label>
            <span class="text-[11px] text-slate-400">
              يدعم JPG, PNG, WEBP, MP4, MOV (حتى 100 ميجابايت)
            </span>
          </div>

          <!-- Hidden File Input for Device Upload -->
          <input
            ref="fileInputRef"
            type="file"
            accept="image/*,video/mp4,video/quicktime,video/webm"
            multiple
            @change="handleFileUpload"
            class="hidden"
          />

          <!-- Drag & Drop / Click to Upload Box -->
          <div
            @dragover.prevent="isDraggingFile = true"
            @dragleave.prevent="isDraggingFile = false"
            @drop.prevent="onDrop"
            @click="triggerFileInput"
            :class="[
              'border-2 border-dashed rounded-2xl p-4 text-center cursor-pointer transition flex flex-col items-center justify-center gap-2 group',
              isDraggingFile
                ? 'border-violet-500 bg-violet-50 dark:bg-violet-950/40 scale-[1.01]'
                : 'border-slate-200 dark:border-slate-800 hover:border-violet-500/60 hover:bg-slate-50 dark:hover:bg-slate-800/60'
            ]"
          >
            <div class="w-11 h-11 rounded-2xl bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center text-xl group-hover:scale-110 transition shadow-sm">
              <span v-if="isUploadingMedia">⏳</span>
              <span v-else>📷 / 🎬</span>
            </div>

            <div v-if="isUploadingMedia" class="space-y-1">
              <p class="text-xs font-bold text-violet-600 dark:text-violet-400 animate-pulse">
                جاري رفع ومعالجة الملف من جهازك إلى السيرفر...
              </p>
              <span class="text-[10px] text-slate-400">يرجى الانتظار لحظات</span>
            </div>
            <div v-else class="space-y-1">
              <p class="text-xs font-bold text-slate-800 dark:text-slate-200">
                <span class="text-violet-600 dark:text-violet-400 underline font-black">اضغط لاختيار صورة أو فيديو من جهازك</span> أو اسحب الملف وأفلته هنا
              </p>
              <p class="text-[10px] text-slate-400">
                يمكنك رفع صور عالية الدقة أو مقاطع فيديو كاملة وريلز وسيتم حفظها ونشرها مباشرة
              </p>
            </div>
          </div>

          <!-- Error Alert if upload fails -->
          <div v-if="uploadError" class="p-2.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs font-bold flex items-center justify-between">
            <span>⚠️ {{ uploadError }}</span>
            <button type="button" @click="uploadError = ''" class="text-rose-400 hover:text-rose-600 cursor-pointer">✕</button>
          </div>

          <!-- Previews of Attached Media -->
          <div v-if="composerForm.media_urls.length" class="space-y-1.5">
            <span class="text-[11px] font-bold text-slate-600 dark:text-slate-400 block">
              الوسائط المرفقة للمنشور ({{ composerForm.media_urls.length }}):
            </span>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
              <div
                v-for="(url, idx) in composerForm.media_urls"
                :key="idx"
                class="relative rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden bg-slate-900 group aspect-video"
              >
                <!-- Video Preview -->
                <video
                  v-if="isVideoUrl(url)"
                  :src="url"
                  controls
                  class="w-full h-full object-cover"
                ></video>
                <!-- Image Preview -->
                <img
                  v-else
                  :src="url"
                  alt="Media preview"
                  class="w-full h-full object-cover"
                />

                <!-- Media Type Badge -->
                <div class="absolute top-1.5 right-1.5 px-2 py-0.5 rounded-md bg-black/70 backdrop-blur-sm text-[10px] font-bold text-white pointer-events-none">
                  {{ isVideoUrl(url) ? '🎬 فيديو' : '📷 صورة' }}
                </div>

                <!-- Delete Button -->
                <button
                  type="button"
                  @click="removeMediaUrl(idx)"
                  class="absolute top-1.5 left-1.5 w-6 h-6 rounded-full bg-rose-600/90 text-white flex items-center justify-center text-xs font-bold hover:bg-rose-700 transition cursor-pointer shadow-md"
                  title="حذف هذا الملف"
                >
                  ✕
                </button>
              </div>
            </div>
          </div>

          <!-- Optional URL link toggle -->
          <details class="text-xs text-slate-500">
            <summary class="cursor-pointer hover:text-violet-600 font-bold select-none py-1">
              🔗 أو إضافة رابط صورة/فيديو مباشر من الإنترنت
            </summary>
            <div class="flex items-center gap-2 mt-2">
              <input
                v-model="tempMediaUrl"
                type="url"
                placeholder="https://example.com/video.mp4 أو رابط صورة"
                class="flex-1 bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2 text-xs text-slate-800 dark:text-slate-200 focus:outline-none"
                dir="ltr"
                @keydown.enter.prevent="addMediaUrl"
              />
              <button
                type="button"
                @click="addMediaUrl"
                class="px-3.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 rounded-xl text-xs font-bold cursor-pointer text-slate-700 dark:text-slate-300"
              >
                + إضافة
              </button>
            </div>
          </details>
        </div>

        <!-- Publishing Mode Selector -->
        <div class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
            خيارات الإرسال والنشر:
          </label>
          <div class="grid grid-cols-3 gap-2">
            <button
              type="button"
              @click="composerForm.publishMode = 'now'"
              :class="[
                'p-2.5 rounded-2xl border text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer',
                composerForm.publishMode === 'now'
                  ? 'border-violet-600 bg-violet-50 dark:bg-violet-950/40 text-violet-700 dark:text-violet-300'
                  : 'border-slate-200 dark:border-slate-800 text-slate-500'
              ]"
            >
              <span>🚀</span>
              <span>نشر فوري الآن</span>
            </button>

            <button
              type="button"
              @click="composerForm.publishMode = 'schedule'"
              :class="[
                'p-2.5 rounded-2xl border text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer',
                composerForm.publishMode === 'schedule'
                  ? 'border-amber-600 bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300'
                  : 'border-slate-200 dark:border-slate-800 text-slate-500'
              ]"
            >
              <span>⏰</span>
              <span>جدولة للنشر لاحقاً</span>
            </button>

            <button
              type="button"
              @click="composerForm.publishMode = 'draft'"
              :class="[
                'p-2.5 rounded-2xl border text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer',
                composerForm.publishMode === 'draft'
                  ? 'border-blue-600 bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300'
                  : 'border-slate-200 dark:border-slate-800 text-slate-500'
              ]"
            >
              <span>💾</span>
              <span>حفظ كمسودة</span>
            </button>
          </div>

          <!-- Schedule DateTime Picker (if schedule mode) -->
          <div
            v-if="composerForm.publishMode === 'schedule'"
            class="bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200/80 dark:border-amber-800/60 rounded-2xl p-3.5 space-y-2 mt-2"
          >
            <div class="flex items-center justify-between flex-wrap gap-2">
              <label class="text-xs font-bold text-amber-900 dark:text-amber-200">
                تحديد تاريخ ووقت النشر:
              </label>
              <div class="flex items-center gap-1">
                <button
                  type="button"
                  @click="setScheduleOffset(1)"
                  class="px-2 py-0.5 rounded-lg bg-amber-100 dark:bg-amber-900/60 text-[10px] text-amber-800 dark:text-amber-200 font-bold hover:bg-amber-200 cursor-pointer"
                >
                  بعد ساعة
                </button>
                <button
                  type="button"
                  @click="setScheduleTomorrow(10)"
                  class="px-2 py-0.5 rounded-lg bg-amber-100 dark:bg-amber-900/60 text-[10px] text-amber-800 dark:text-amber-200 font-bold hover:bg-amber-200 cursor-pointer"
                >
                  غداً 10 ص
                </button>
                <button
                  type="button"
                  @click="setScheduleTomorrow(18)"
                  class="px-2 py-0.5 rounded-lg bg-amber-100 dark:bg-amber-900/60 text-[10px] text-amber-800 dark:text-amber-200 font-bold hover:bg-amber-200 cursor-pointer"
                >
                  غداً 6 م
                </button>
              </div>
            </div>

            <input
              v-model="composerForm.scheduled_at"
              type="datetime-local"
              class="w-full bg-white dark:bg-slate-900 border border-amber-300 dark:border-amber-700 rounded-xl px-3.5 py-2 text-xs text-slate-800 dark:text-slate-200 focus:outline-none"
            />
          </div>
        </div>

        <!-- Composer Modal Actions -->
        <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-800">
          <button
            type="button"
            @click="isComposerOpen = false"
            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
          >
            إلغاء
          </button>

          <button
            type="button"
            @click="handleSavePost"
            :disabled="isSubmitting"
            class="px-6 py-2.5 rounded-2xl bg-gradient-to-l from-violet-600 to-indigo-600 text-white font-black text-xs shadow-md shadow-violet-500/20 hover:opacity-95 active:scale-95 transition cursor-pointer flex items-center gap-2"
          >
            <span v-if="isSubmitting" class="inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            <span>
              {{ composerForm.publishMode === 'now' ? '🚀 نشر المنشور الآن' : (composerForm.publishMode === 'schedule' ? '⏰ جدولة المنشور' : '💾 حفظ كمسودة') }}
            </span>
          </button>
        </div>

      </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 2: Connect Social Account Modal (Interactive Discovery & 1-Click Connect) -->
    <!-- ========================================== -->
    <div
      v-if="isConnectModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in"
      @click.self="isConnectModalOpen = false"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl w-full max-w-xl p-5 sm:p-6 space-y-4 shadow-2xl text-right max-h-[90vh] overflow-y-auto">
        
        <!-- Modal Header -->
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-violet-600/10 text-violet-600 flex items-center justify-center text-base">
              🔗
            </div>
            <div>
              <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-slate-100">
                ربط صفحة أو حساب سوشيال ميديا
              </h3>
              <p class="text-[11px] text-slate-400 mt-0.5">
                اختر المنصة لاستعراض صفحاتك وقنواتك وربطها بنقرة واحدة
              </p>
            </div>
          </div>
          <button
            @click="isConnectModalOpen = false"
            class="text-slate-400 hover:text-slate-600 text-lg cursor-pointer px-2 py-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition"
          >
            ✕
          </button>
        </div>

        <!-- Success Toast inside modal -->
        <div
          v-if="connectSuccessPageName"
          class="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-2xl flex items-center gap-2 text-xs font-bold text-emerald-700 dark:text-emerald-300"
        >
          <span>🎉</span>
          <span>تم ربط "{{ connectSuccessPageName }}" بنجاح وجاهزة للنشر والجدولة!</span>
        </div>

        <!-- Error Toast inside modal -->
        <div
          v-if="connectErrorMsg"
          class="p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-2xl flex items-center gap-2 text-xs font-bold text-rose-700 dark:text-rose-300"
        >
          <span>⚠️</span>
          <span>{{ connectErrorMsg }}</span>
        </div>

        <!-- Platform Selection Cards -->
        <div class="space-y-1.5">
          <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300">
            1. اختر المنصة التي تريد ربط صفحاتها:
          </label>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <button
              v-for="p in ['facebook', 'instagram', 'youtube', 'linkedin']"
              :key="p"
              type="button"
              @click="fetchAvailablePages(p)"
              :class="[
                'p-3 rounded-2xl border text-xs font-bold flex flex-col items-center gap-2 cursor-pointer transition text-center',
                selectedConnectPlatform === p
                  ? 'border-violet-600 bg-violet-50 dark:bg-violet-950/50 text-violet-700 dark:text-violet-300 ring-2 ring-violet-500/20 shadow-sm'
                  : 'border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50'
              ]"
            >
              <span
                :class="[
                  'w-8 h-8 rounded-xl flex items-center justify-center text-xs font-black text-white shadow-sm',
                  getPlatformMeta(p).color
                ]"
              >
                {{ getPlatformMeta(p).icon }}
              </span>
              <span class="font-extrabold">{{ getPlatformMeta(p).name }}</span>
            </button>
          </div>
        </div>

        <!-- Step 2: Login to Platform (Required before discovering pages) -->
        <div v-if="!isPlatformAuthenticated" class="space-y-3 pt-2">
          <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300">
            2. تسجيل الدخول والتحقق من حسابك:
          </label>

          <div class="p-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/60 flex flex-col items-center justify-center text-center space-y-3">
            <div
              :class="[
                'w-12 h-12 rounded-2xl flex items-center justify-center text-xl font-black text-white shadow-md',
                getPlatformMeta(selectedConnectPlatform).color
              ]"
            >
              {{ getPlatformMeta(selectedConnectPlatform).icon }}
            </div>

            <div class="space-y-1">
              <h4 class="text-sm font-extrabold text-slate-800 dark:text-slate-200">
                تسجيل الدخول بحساب {{ getPlatformMeta(selectedConnectPlatform).name }}
              </h4>
              <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm">
                سجّل الدخول بحسابك لمنح الإذن وعرض الصفحات والقنوات التي تمتلك صلاحية إدارتها لربطها فوراً.
              </p>
            </div>

            <!-- Credentials Missing Warning -->
            <div
              v-if="authCredentialsMissing"
              class="w-full max-w-md p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-right space-y-2 text-xs text-amber-800 dark:text-amber-200"
            >
              <div class="flex items-center gap-1.5 font-bold">
                <span>⚠️</span>
                <span>{{ authMissingMessage }}</span>
              </div>
              <p class="text-[11px] text-amber-700 dark:text-amber-300 leading-relaxed">
                لاستخدام تسجيل الدخول الرسمي عبر نافذة المنصة، يلزم توفير مفاتيح تطبيق المطور (App ID & Secret). كما يمكنك تجربة تسجيل الدخول السريع (Demo Login) لمعاينة التجربة فوراً.
              </p>
              <div class="flex items-center gap-2 pt-1 flex-wrap">
                <button
                  type="button"
                  @click="isConnectModalOpen = false; goToSocialSettings()"
                  class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold text-[11px] transition cursor-pointer"
                >
                  ⚙️ إدخال المفاتيح في الإعدادات
                </button>
                <button
                  type="button"
                  @click="handleDemoLogin(selectedConnectPlatform)"
                  class="px-3 py-1.5 rounded-lg bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 text-slate-800 dark:text-slate-200 font-bold text-[11px] transition cursor-pointer"
                >
                  🚀 تجربة تسجيل الدخول السريع
                </button>
              </div>
            </div>

            <!-- Main Login Buttons -->
            <div class="flex flex-col sm:flex-row items-center gap-2 pt-2">
              <button
                type="button"
                @click="handleLoginPlatform(selectedConnectPlatform)"
                :disabled="isLoggingIn"
                :class="[
                  'px-6 py-2.5 rounded-2xl text-white font-extrabold text-xs shadow-md transition cursor-pointer flex items-center gap-2 disabled:opacity-50 hover:opacity-95 active:scale-95',
                  getPlatformMeta(selectedConnectPlatform).color
                ]"
              >
                <span v-if="isLoggingIn" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                <span v-else>🔑</span>
                <span>تسجيل الدخول عبر {{ getPlatformMeta(selectedConnectPlatform).name }}</span>
              </button>

              <button
                v-if="!authCredentialsMissing"
                type="button"
                @click="handleDemoLogin(selectedConnectPlatform)"
                :disabled="isLoggingIn"
                class="px-4 py-2.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs font-bold transition cursor-pointer"
              >
                تسجيل دخول تجريبي سريع
              </button>
            </div>
          </div>
        </div>

        <!-- Step 2 (Authenticated Status): Connected Info & Logout -->
        <div v-else class="space-y-3 pt-2">
          <div class="p-3.5 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-sm shrink-0">
                ✓
              </div>
              <div class="text-right">
                <span class="block text-xs font-black text-emerald-800 dark:text-emerald-200">
                  <span v-if="authUser && authUser.name">أنت مسجل الدخول بحساب: {{ authUser.name }}</span>
                  <span v-else>أنت مسجل الدخول حالياً بحسابك في {{ getPlatformMeta(selectedConnectPlatform).name }}</span>
                </span>
                <span class="text-[11px] text-emerald-600 dark:text-emerald-400">
                  تم التحقق من الحساب والاتصال بالـ API بنجاح
                </span>
              </div>
            </div>

            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="fetchAvailablePages(selectedConnectPlatform)"
                :disabled="isLoadingPages"
                class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 cursor-pointer flex items-center gap-1 transition"
              >
                <span :class="{'animate-spin': isLoadingPages}">🔄</span>
                <span>تحديث الصفحات</span>
              </button>
              <button
                type="button"
                @click="handlePlatformLogout(selectedConnectPlatform)"
                class="text-xs font-bold text-rose-500 hover:text-rose-700 hover:underline cursor-pointer px-2 py-1"
              >
                تسجيل الخروج
              </button>
            </div>
          </div>

          <!-- Step 3: Discovered Pages & Channels Section -->
          <div class="space-y-2.5 pt-2">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="text-xs font-extrabold text-slate-700 dark:text-slate-300">
                  3. الصفحات والقنوات التابعة لحسابك ({{ availablePages.length }}):
                </span>
                <span
                  v-if="!isLoadingPages && availablePages.length"
                  class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-100 dark:bg-violet-900/60 text-violet-700 dark:text-violet-300"
                >
                  {{ availablePages.length }} متاحة
                </span>
              </div>
            </div>

            <!-- Loading State -->
            <div
              v-if="isLoadingPages"
              class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-950/40 border border-slate-200 dark:border-slate-800 flex flex-col items-center justify-center gap-3 text-center"
            >
              <div class="w-7 h-7 border-2 border-violet-600 border-t-transparent rounded-full animate-spin"></div>
              <div class="text-xs font-bold text-slate-600 dark:text-slate-400">
                جاري استعراض الصفحات والقنوات المتاحة في {{ getPlatformMeta(selectedConnectPlatform).name }}...
              </div>
            </div>

            <!-- Discovered Pages List -->
            <div
              v-else-if="availablePages.length > 0"
              class="space-y-2 max-h-64 overflow-y-auto pr-0.5"
            >
              <div
                v-for="page in availablePages"
                :key="page.account_id"
                class="p-3.5 rounded-2xl border transition flex items-center justify-between gap-3 bg-white dark:bg-slate-950"
                :class="page.is_connected ? 'border-emerald-500/40 bg-emerald-50/20 dark:bg-emerald-950/10' : 'border-slate-200 dark:border-slate-800 hover:border-violet-300'"
              >
                <!-- Page Info & Avatar -->
                <div class="flex items-center gap-3 min-w-0">
                  <div class="relative shrink-0">
                    <img
                      v-if="page.avatar_url"
                      :src="page.avatar_url"
                      :alt="page.account_name"
                      class="w-10 h-10 rounded-xl object-cover border border-slate-200 dark:border-slate-800"
                    />
                    <div
                      v-else
                      :class="[
                        'w-10 h-10 rounded-xl flex items-center justify-center font-black text-white text-sm',
                        getPlatformMeta(selectedConnectPlatform).color
                      ]"
                    >
                      {{ getPlatformMeta(selectedConnectPlatform).icon }}
                    </div>
                    <span
                      :class="[
                        'absolute -bottom-1 -left-1 w-4 h-4 rounded-full text-[8px] flex items-center justify-center text-white font-bold ring-2 ring-white dark:ring-slate-900',
                        getPlatformMeta(selectedConnectPlatform).color
                      ]"
                    >
                      {{ getPlatformMeta(selectedConnectPlatform).icon }}
                    </span>
                  </div>

                  <div class="min-w-0 text-right">
                    <div class="flex items-center gap-2">
                      <span class="text-xs font-black text-slate-900 dark:text-slate-100 truncate block">
                        {{ page.account_name }}
                      </span>
                      <span
                        v-if="page.category"
                        class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 shrink-0"
                      >
                        {{ page.category }}
                      </span>
                    </div>
                    <div class="flex items-center gap-2 text-[10px] text-slate-400 mt-0.5">
                      <span v-if="page.account_username">@{{ page.account_username }}</span>
                      <span>•</span>
                      <span>{{ Number(page.followers_count).toLocaleString() }} متابع</span>
                    </div>
                  </div>
                </div>

                <!-- Action Button -->
                <div class="shrink-0">
                  <div v-if="page.is_connected" class="flex items-center gap-2">
                    <span class="px-2.5 py-1 rounded-xl bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 font-extrabold text-[11px] flex items-center gap-1">
                      <span>✓</span>
                      <span>مربوطة</span>
                    </span>
                    <button
                      type="button"
                      @click="handleDisconnectFromModal(page)"
                      class="text-[10px] text-rose-500 hover:text-rose-700 hover:underline cursor-pointer"
                    >
                      فصل
                    </button>
                  </div>

                  <button
                    v-else
                    type="button"
                    @click="handleConnectDiscoveredPage(page)"
                    :disabled="connectingPageId === page.account_id"
                    class="px-4 py-2 rounded-xl bg-gradient-to-l from-violet-600 to-indigo-600 text-white font-extrabold text-xs shadow-sm hover:opacity-95 active:scale-95 transition cursor-pointer flex items-center gap-1.5 disabled:opacity-50"
                  >
                    <span
                      v-if="connectingPageId === page.account_id"
                      class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"
                    ></span>
                    <span v-else>🔗</span>
                    <span>ربط هذه الصفحة</span>
                  </button>
                </div>
              </div>
            </div>

            <!-- Empty State with Helpful Troubleshooting & Actions -->
            <div
              v-else
              class="p-4 rounded-2xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 text-right text-xs space-y-3"
            >
              <div class="flex items-center gap-2 font-black text-amber-800 dark:text-amber-200 text-sm">
                <span>⚠️</span>
                <span>لم يتم العثور على صفحات أو قنوات مدارة بهذا الحساب</span>
              </div>
              <p class="text-amber-700 dark:text-amber-300 text-[11px] leading-relaxed">
                فيسبوك أعاد (0) صفحة. يحدث هذا غالباً لأحد الأسباب التالية:
              </p>
              <ul class="list-disc list-inside text-[11px] text-amber-800 dark:text-amber-200 space-y-1">
                <li>لم يتم وضع علامة صح (✓) على صفحاتك عندما طلبت شاشة فيسبوك تحديد الصفحات.</li>
                <li>التطبيق في وضع التطوير (Development Mode) وحسابك الحالي ليس مضافاً كـ Admin أو Tester للتطبيق في Meta.</li>
                <li>الصفحة تابعة لمحفظة أعمال (Meta Business Portfolio) أو حساب فيسبوك آخر.</li>
              </ul>
              <div class="pt-1 flex items-center gap-2 flex-wrap">
                <button
                  type="button"
                  @click="handleLoginPlatform(selectedConnectPlatform)"
                  class="px-3.5 py-1.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white font-extrabold text-xs cursor-pointer transition flex items-center gap-1.5"
                >
                  <span>🔄</span>
                  <span>إعادة تسجيل الدخول واختيار الصفحات</span>
                </button>
                <button
                  type="button"
                  @click="isManualEntryOpen = true"
                  class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs cursor-pointer hover:bg-slate-50 transition"
                >
                  ✏️ إدخال معرف الصفحة يدوياً (Page ID)
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Manual Entry Accordion -->
        <div class="border-t border-slate-100 dark:border-slate-800 pt-3">
          <button
            type="button"
            @click="isManualEntryOpen = !isManualEntryOpen"
            class="w-full flex items-center justify-between text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition py-1 cursor-pointer"
          >
            <span class="flex items-center gap-1.5">
              <span>✏️</span>
              <span>أو إدخال بيانات ومعرّف الصفحة يدوياً (Manual Connect)</span>
            </span>
            <span>{{ isManualEntryOpen ? '▲ إخفاء' : '▼ فتح' }}</span>
          </button>

          <!-- Collapsible Manual Form -->
          <div v-if="isManualEntryOpen" class="space-y-3 pt-3">
            <!-- Account Name Field -->
            <div class="space-y-1 text-right">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                اسم الصفحة أو القناة:
              </label>
              <input
                v-model="connectForm.account_name"
                type="text"
                placeholder="مثال: صفحتي الرسمية / قناتي في اليوتيوب"
                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2 text-xs text-slate-800 dark:text-slate-200 focus:outline-none"
              />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <!-- Account / Page ID Field -->
              <div class="space-y-1 text-right">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                  معرّف الصفحة أو القناة (ID):
                </label>
                <input
                  v-model="connectForm.account_id"
                  type="text"
                  placeholder="مثال: 10482910398 أو UC..."
                  class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2 text-xs text-slate-800 dark:text-slate-200 focus:outline-none"
                  dir="ltr"
                />
              </div>

              <!-- Username / Handle Field -->
              <div class="space-y-1 text-right">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                  اليوزر نيم / المعرّف (Handle):
                </label>
                <input
                  v-model="connectForm.account_username"
                  type="text"
                  placeholder="مثال: mypage (بدون @)"
                  class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2 text-xs text-slate-800 dark:text-slate-200 focus:outline-none"
                  dir="ltr"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <!-- Followers Count Field -->
              <div class="space-y-1 text-right">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                  عدد المتابعين / المشتركين:
                </label>
                <input
                  v-model.number="connectForm.followers_count"
                  type="number"
                  placeholder="0"
                  class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2 text-xs text-slate-800 dark:text-slate-200 focus:outline-none"
                />
              </div>

              <!-- Avatar URL Field -->
              <div class="space-y-1 text-right">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                  رابط صورة اللوجو (اختياري):
                </label>
                <input
                  v-model="connectForm.avatar_url"
                  type="url"
                  placeholder="https://..."
                  class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2 text-xs text-slate-800 dark:text-slate-200 focus:outline-none"
                  dir="ltr"
                />
              </div>
            </div>

            <div class="pt-2 flex justify-end">
              <button
                type="button"
                @click="handleConnectAccount"
                :disabled="isSubmitting"
                class="px-5 py-2 rounded-xl bg-slate-800 dark:bg-slate-700 text-white font-bold text-xs hover:bg-slate-900 transition cursor-pointer flex items-center gap-1.5"
              >
                <span v-if="isSubmitting" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                <span>حفظ وربط يدوياً</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Modal Footer Actions -->
        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between flex-row-reverse">
          <button
            type="button"
            @click="isConnectModalOpen = false"
            class="px-5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 font-extrabold text-xs transition cursor-pointer"
          >
            إغلاق
          </button>

          <button
            type="button"
            @click="isConnectModalOpen = false; goToSocialSettings()"
            class="text-[11px] font-bold text-violet-600 hover:text-violet-700 dark:text-violet-400 hover:underline cursor-pointer flex items-center gap-1"
          >
            <span>⚙️ إعداد مفاتيح التطبيق المخصصة (App ID / Secret)</span>
          </button>
        </div>

      </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL 3: Edit Post Modal -->
    <!-- ========================================== -->
    <div
      v-if="isEditModalOpen && editingPost"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in"
      @click.self="isEditModalOpen = false"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl w-full max-w-lg p-5 sm:p-6 space-y-4 shadow-2xl text-right">
        
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <h3 class="text-sm font-black text-slate-900 dark:text-slate-100">
            تعديل المنشور
          </h3>
          <button
            @click="isEditModalOpen = false"
            class="text-slate-400 hover:text-slate-600 text-lg cursor-pointer"
          >
            ✕
          </button>
        </div>

        <div class="space-y-1.5">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">محتوى المنشور:</label>
          <textarea
            v-model="editingPost.content"
            rows="4"
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl p-3 text-xs text-slate-800 dark:text-slate-200 focus:outline-none"
          ></textarea>
        </div>

        <div v-if="editingPost.status === 'scheduled'" class="space-y-1.5">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">موعد الجدولة:</label>
          <input
            v-model="editingPost.scheduled_at"
            type="datetime-local"
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-slate-200 focus:outline-none"
          />
        </div>

        <!-- Edit Post Media Section -->
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
              الوسائط المرفقة (صور أو فيديو):
            </label>
            <button
              type="button"
              @click="triggerEditFileInput"
              class="px-2.5 py-1 rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400 hover:bg-violet-500/20 text-xs font-bold transition cursor-pointer flex items-center gap-1"
            >
              <span>+ إضافة من الجهاز</span>
            </button>
          </div>

          <input
            ref="editFileInputRef"
            type="file"
            accept="image/*,video/mp4,video/quicktime,video/webm"
            multiple
            @change="handleEditFileUpload"
            class="hidden"
          />

          <!-- Attached Media Preview -->
          <div v-if="editingPost.media_urls && editingPost.media_urls.length" class="grid grid-cols-3 gap-2">
            <div
              v-for="(url, idx) in editingPost.media_urls"
              :key="idx"
              class="relative rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden bg-slate-900 aspect-video group"
            >
              <video
                v-if="isVideoUrl(url)"
                :src="url"
                controls
                class="w-full h-full object-cover"
              ></video>
              <img
                v-else
                :src="url"
                alt="Media"
                class="w-full h-full object-cover"
              />
              <button
                type="button"
                @click="removeEditMediaUrl(idx)"
                class="absolute top-1 left-1 w-5 h-5 rounded-full bg-rose-600 text-white flex items-center justify-center text-[10px] font-bold hover:bg-rose-700 cursor-pointer shadow"
                title="حذف"
              >
                ✕
              </button>
            </div>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <button
            type="button"
            @click="isEditModalOpen = false"
            class="px-4 py-2 text-xs font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition cursor-pointer"
          >
            إلغاء
          </button>

          <button
            type="button"
            @click="handleUpdatePost"
            :disabled="isSubmitting"
            class="px-6 py-2.5 rounded-2xl bg-violet-600 text-white font-bold text-xs shadow-md shadow-violet-500/20 hover:opacity-95 transition cursor-pointer"
          >
            حفظ التعديلات
          </button>
        </div>

      </div>
    </div>

  </div>
</template>
