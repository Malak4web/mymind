<script setup>
import { ref, computed, onMounted } from 'vue'
import { store } from '../store'

// View & Filter States
const currentTab = ref('all') // 'all' | 'scheduled' | 'published' | 'draft'
const selectedPlatformFilter = ref('all') // 'all' | 'facebook' | 'instagram' | 'youtube' | 'linkedin'
const searchQuery = ref('')

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

// Media handlers
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
  isComposerOpen.value = true
}

// Open Connect Modal
const openConnectModal = (preferredPlatform = 'facebook') => {
  connectForm.value = {
    platform: preferredPlatform,
    account_id: '',
    account_name: '',
    account_username: '',
    avatar_url: '',
    followers_count: 0,
  }
  isConnectModalOpen.value = true
}

// Submit Connect Account
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
    isConnectModalOpen.value = false
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

// Computed Posts List with Filters
const filteredPosts = computed(() => {
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
  if (store.token) {
    store.loadSocialAccounts(true)
    store.loadSocialPosts(true)
    store.loadSocialSettings(true)
  }
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
        </div>

        <!-- Platform & Search Filters -->
        <div class="flex items-center gap-2">
          <!-- Platform Filter Select -->
          <select
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
            v-model="searchQuery"
            type="text"
            placeholder="بحث في المحتوى..."
            class="w-full sm:w-48 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-1.5 text-xs text-slate-700 dark:text-slate-300 focus:outline-none"
          />
        </div>

      </div>

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
              <img
                v-for="(img, idx) in post.media_urls.slice(0, 2)"
                :key="idx"
                :src="img"
                alt="Media"
                class="w-full h-24 object-cover rounded-xl"
                onerror="this.style.display='none'"
              />
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

        <!-- Media URLs Section -->
        <div class="space-y-2">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
            روابط الصور / الفيديو (اختياري):
          </label>
          <div class="flex items-center gap-2">
            <input
              v-model="tempMediaUrl"
              type="url"
              placeholder="https://example.com/image.jpg"
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

          <!-- Thumbnails Preview Grid -->
          <div v-if="composerForm.media_urls.length" class="flex items-center gap-2 flex-wrap pt-1">
            <div
              v-for="(url, idx) in composerForm.media_urls"
              :key="idx"
              class="relative w-16 h-16 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden group"
            >
              <img :src="url" alt="Media preview" class="w-full h-full object-cover" />
              <button
                type="button"
                @click="removeMediaUrl(idx)"
                class="absolute inset-0 bg-slate-900/70 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition text-xs font-bold cursor-pointer"
              >
                ✕
              </button>
            </div>
          </div>
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
    <!-- MODAL 2: Connect Social Account Modal -->
    <!-- ========================================== -->
    <div
      v-if="isConnectModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in"
      @click.self="isConnectModalOpen = false"
    >
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl w-full max-w-md p-5 sm:p-6 space-y-4 shadow-2xl text-right">
        
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2">
            <span class="text-xl">🔗</span>
            <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-slate-100">
              ربط صفحة أو حساب سوشيال ميديا
            </h3>
          </div>
          <button
            @click="isConnectModalOpen = false"
            class="text-slate-400 hover:text-slate-600 text-lg cursor-pointer"
          >
            ✕
          </button>
        </div>

        <!-- Platform Selection -->
        <div class="space-y-1.5">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">اختر المنصة:</label>
          <div class="grid grid-cols-2 gap-2">
            <button
              v-for="p in ['facebook', 'instagram', 'youtube', 'linkedin']"
              :key="p"
              type="button"
              @click="connectForm.platform = p"
              :class="[
                'p-2.5 rounded-2xl border text-xs font-bold flex items-center gap-2 cursor-pointer transition',
                connectForm.platform === p
                  ? 'border-violet-600 bg-violet-50 dark:bg-violet-950/40 text-violet-700 dark:text-violet-300'
                  : 'border-slate-200 dark:border-slate-800 text-slate-500'
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

        <!-- Account Name Field -->
        <div class="space-y-1.5">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
            اسم الصفحة أو القناة:
          </label>
          <input
            v-model="connectForm.account_name"
            type="text"
            placeholder="مثال: صفحتي الرسمية / قناتي في اليوتيوب"
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-200 focus:outline-none"
          />
        </div>

        <!-- Account / Page ID Field -->
        <div class="space-y-1.5">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
            معرّف الصفحة أو القناة (ID):
          </label>
          <input
            v-model="connectForm.account_id"
            type="text"
            placeholder="مثال: 10482910398 أو UC..."
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-200 focus:outline-none"
            dir="ltr"
          />
        </div>

        <!-- Username / Handle Field -->
        <div class="space-y-1.5">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
            اليوزر نيم / المعرّف (Handle):
          </label>
          <input
            v-model="connectForm.account_username"
            type="text"
            placeholder="مثال: mypage (بدون @)"
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-200 focus:outline-none"
            dir="ltr"
          />
        </div>

        <!-- Followers Count Field -->
        <div class="space-y-1.5">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
            عدد المتابعين / المشتركين:
          </label>
          <input
            v-model.number="connectForm.followers_count"
            type="number"
            placeholder="0"
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-200 focus:outline-none"
          />
        </div>

        <!-- Avatar URL Field -->
        <div class="space-y-1.5">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
            رابط صورة اللوجو / البروفايل (اختياري):
          </label>
          <input
            v-model="connectForm.avatar_url"
            type="url"
            placeholder="https://..."
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 dark:text-slate-200 focus:outline-none"
            dir="ltr"
          />
        </div>

        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <button
            type="button"
            @click="isConnectModalOpen = false"
            class="px-4 py-2 text-xs font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition cursor-pointer"
          >
            إلغاء
          </button>

          <button
            type="button"
            @click="handleConnectAccount"
            :disabled="isSubmitting"
            class="px-6 py-2.5 rounded-2xl bg-gradient-to-l from-violet-600 to-indigo-600 text-white font-black text-xs shadow-md shadow-violet-500/20 hover:opacity-95 active:scale-95 transition cursor-pointer flex items-center gap-2"
          >
            <span v-if="isSubmitting" class="inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            <span>ربط وحفظ الحساب</span>
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
