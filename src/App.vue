<script setup>
import { store } from './store'
import { computed, onMounted, onUnmounted, watch, ref } from 'vue'


// Import components
import ProjectPanel from './components/ProjectPanel.vue'
import NotificationCenter from './components/NotificationCenter.vue'
import TaskModal from './components/TaskModal.vue'
import TaskBoard from './components/TaskBoard.vue'
import TaskList from './components/TaskList.vue'
import TaskCalendar from './components/TaskCalendar.vue'
import ProjectDocuments from './components/ProjectDocuments.vue'
import Login from './components/Login.vue'
import Settings from './components/Settings.vue'
import DailyRoutines from './components/DailyRoutines.vue'
import HabitDetail from './components/HabitDetail.vue'
import MobileBottomNav from './components/MobileBottomNav.vue'
const activeHabitId = ref(null)
const showMobileProjectsSheet = ref(false)
const showMobileMoreSheet = ref(false)

// Touch swipe gesture handlers for bottom sheets
const touchStartY = ref(0)
const touchCurrentY = ref(0)

const handleTouchStart = (e) => {
  touchStartY.value = e.touches[0].clientY
  touchCurrentY.value = e.touches[0].clientY
}

const handleTouchMove = (e) => {
  touchCurrentY.value = e.touches[0].clientY
}

const handleTouchEnd = (closeFn) => {
  const deltaY = touchCurrentY.value - touchStartY.value
  if (touchStartY.value > 0 && deltaY > 50) {
    closeFn()
  }
  touchStartY.value = 0
  touchCurrentY.value = 0
}

const activeProject = computed(() => store.projects.find(p => p.id === store.activeProjectId))
const unreadNotificationsCount = computed(() => store.notifications.filter(n => !n.isRead).length)


const handleLogout = () => {
  store.logout()
}

const goToSettings = () => {
  window.location.hash = '#settings-db'
  store.activeView = 'settings'
}

const setView = (view) => {
  store.activeView = view
  if (view === 'routines') {
    activeHabitId.value = null
    window.location.hash = '#routines'
  } else if (view === 'settings') {
    window.location.hash = '#settings-db'
  } else if (store.activeProjectId) {
    window.location.hash = `#project-${store.activeProjectId}`
  }
}

// Sync changes of activeProjectId to hash
watch(() => store.activeProjectId, (newId) => {
  if (newId && store.activeView !== 'settings' && store.activeView !== 'routines') {
    window.location.hash = `#project-${newId}`
  }
})

// Apply dark class to document root for reliable Tailwind dark mode styling
watch(() => store.theme, (newTheme) => {
  if (newTheme === 'dark') {
    document.documentElement.classList.add('dark')
  } else {
    document.documentElement.classList.remove('dark')
  }
}, { immediate: true })

// Handle window hash change
const handleHashChange = () => {
  const hash = window.location.hash

  if (hash.startsWith('#routines/habit-')) {
    const hId = parseInt(hash.replace('#routines/habit-', ''))
    if (!isNaN(hId)) {
      activeHabitId.value = hId
      store.activeView = 'routines'
      return
    }
  } else if (hash.startsWith('#habit-')) {
    const hId = parseInt(hash.replace('#habit-', ''))
    if (!isNaN(hId)) {
      activeHabitId.value = hId
      store.activeView = 'routines'
      return
    }
  }

  activeHabitId.value = null

  if (hash === '#routines' || hash === '#habits') {
    store.activeView = 'routines'
  } else if (hash.startsWith('#project-')) {
    const projId = parseInt(hash.replace('#project-', ''))
    if (!isNaN(projId)) {
      if (store.activeProjectId !== projId) {
        store.activeProjectId = projId
      }
      if (store.activeView === 'settings' || store.activeView === 'routines') {
        store.activeView = 'kanban'
      }
    }
  } else if (hash.startsWith('#settings-')) {
    store.activeView = 'settings'
  }
}

onMounted(() => {
  window.addEventListener('hashchange', handleHashChange)
  handleHashChange()
})

onUnmounted(() => {
  window.removeEventListener('hashchange', handleHashChange)
})

// Watch when projects list finishes loading, to select the project based on hash
watch(() => store.projects, (newProjects) => {
  if (newProjects.length > 0) {
    const hash = window.location.hash
    if (hash.startsWith('#project-')) {
      const projId = parseInt(hash.replace('#project-', ''))
      if (!isNaN(projId) && newProjects.some(p => p.id === projId)) {
        store.activeProjectId = projId
      }
    } else if (hash.startsWith('#routines') || hash.startsWith('#habit-')) {
      handleHashChange()
    }
  }
}, { deep: true })

</script>

<template>
  <!-- Authentication Gate -->
  <div v-if="!store.isAuthenticated">
    <Login />
  </div>

  <div 
    v-else 
    dir="rtl" 
    :class="[
      store.theme, 
      'min-h-screen overflow-x-hidden transition-colors duration-500 text-slate-800 dark:text-slate-100 font-sans antialiased selection:bg-violet-500/10 selection:text-violet-600 bg-slate-50/50 dark:bg-slate-955'
    ]"
  >
    
    <!-- Ambient Radial Gradient Background in Focus Mode -->
    <div v-if="store.isFocusMode" class="absolute inset-0 pointer-events-none overflow-hidden z-0">
      <div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[600px] h-[600px] rounded-full bg-violet-600/[0.03] dark:bg-violet-500/[0.02] blur-3xl"></div>
    </div>

    <!-- Top Glassmorphic Navigation Header (Non-sticky, Hidden in Focus Mode) -->
    <header 
      v-if="!store.isFocusMode"
      class="bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border-b border-slate-200/60 dark:border-slate-800/80 z-30 shadow-sm sticky top-0 md:relative"
    >
      <!-- Desktop Navigation Header (hidden on mobile) -->
      <div class="hidden md:flex max-w-full w-full px-6 md:px-12 py-3.5 items-center justify-between gap-4">
        
        <!-- Left Brand info (in RTL, it will render on the right) -->
        <div class="flex items-center gap-5">
          <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-650 flex items-center justify-center shadow-lg shadow-violet-500/15 hover:scale-[1.03] transition-transform duration-300">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
          </div>
          <div class="text-right">
            <h1 class="text-lg font-extrabold tracking-tight text-slate-900 dark:text-slate-50 flex items-center gap-2">
              عقلي
              <span class="px-1.5 py-0.5 rounded-md bg-gradient-to-r from-violet-600 to-indigo-650 text-[10px] font-extrabold text-white tracking-wider">برو</span>
            </h1>
            <p class="text-xs text-slate-400 font-semibold tracking-wide mt-1">بيئة تخطيط المشاريع والتعاون الجماعي البسيطة</p>
          </div>
        </div>

        <!-- Middle View Navigator: Arabic View Tabs -->
        <div class="flex items-center">
          <div class="bg-slate-100/80 dark:bg-slate-955/80 border border-slate-200/50 dark:border-slate-855 p-1 rounded-2xl flex gap-1">
            <button 
              @click="setView('kanban')" 
              :class="[
                'px-4 py-1.5 rounded-xl text-sm font-semibold transition-all duration-300 cursor-pointer flex items-center space-x-1.5 space-x-reverse min-h-[44px] min-w-[44px]',
                store.activeView === 'kanban' 
                  ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm ring-1 ring-slate-200/40 dark:ring-slate-800' 
                  : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-350'
              ]"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
              </svg>
              <span>لوحة المهام</span>
            </button>
            <button 
              @click="setView('list')" 
              :class="[
                'px-4 py-1.5 rounded-xl text-sm font-semibold transition-all duration-300 cursor-pointer flex items-center space-x-1.5 space-x-reverse min-h-[44px] min-w-[44px]',
                store.activeView === 'list' 
                  ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm ring-1 ring-slate-200/40 dark:ring-slate-800' 
                  : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-350'
              ]"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
              </svg>
              <span>جدول المهام</span>
            </button>
            <button 
              @click="setView('calendar')" 
              :class="[
                'px-4 py-1.5 rounded-xl text-sm font-semibold transition-all duration-300 cursor-pointer flex items-center space-x-1.5 space-x-reverse min-h-[44px] min-w-[44px]',
                store.activeView === 'calendar' 
                  ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm ring-1 ring-slate-200/40 dark:ring-slate-800' 
                  : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-350'
              ]"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <span>التقويم</span>
            </button>
            <button 
              @click="setView('routines')" 
              :class="[
                'px-4 py-1.5 rounded-xl text-sm font-semibold transition-all duration-300 cursor-pointer flex items-center space-x-1.5 space-x-reverse min-h-[44px] min-w-[44px]',
                store.activeView === 'routines' 
                  ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm ring-1 ring-slate-200/40 dark:ring-slate-800 text-violet-600 dark:text-violet-400 font-extrabold' 
                  : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-350'
              ]"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>يومياتي</span>
            </button>
            <button 
              @click="goToSettings" 
              :class="[
                'px-4 py-1.5 rounded-xl text-sm font-semibold transition-all duration-300 cursor-pointer flex items-center space-x-1.5 space-x-reverse min-h-[44px] min-w-[44px]',
                store.activeView === 'settings' 
                  ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm ring-1 ring-slate-200/40 dark:ring-slate-800' 
                  : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-350'
              ]"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <span>الإعدادات</span>
            </button>
          </div>
        </div>

        <!-- Right Side actions (User Profile, Theme, Notifications) -->
        <div class="flex items-center gap-3.5">
          <!-- Active User Badge -->
          <div class="hidden sm:flex flex-col text-right justify-center">
            <span class="text-xs font-extrabold text-slate-850 dark:text-slate-205">{{ store.currentUser?.name }}</span>
            <span class="text-[9.5px] font-bold text-slate-400 block -mt-0.5">({{ store.currentUser?.role?.name || 'زائر' }})</span>
          </div>

          <!-- Zen Focus Mode Trigger -->
          <button 
            @click="store.isFocusMode = true"
            class="p-2 border border-slate-200 dark:border-slate-805 hover:border-slate-350 dark:hover:border-slate-700 bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400 rounded-xl transition cursor-pointer hover:shadow-sm min-h-[44px] min-w-[44px] flex items-center justify-center"
            title="تفعيل وضع التركيز (Zen Mode)"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
            </svg>
          </button>

          <!-- Logout Button -->
          <button 
            @click="handleLogout"
            class="p-2 border border-slate-200 dark:border-slate-805 hover:border-slate-350 dark:hover:border-slate-700 bg-white dark:bg-slate-900 text-rose-500 rounded-xl transition cursor-pointer hover:shadow-sm min-h-[44px] min-w-[44px] flex items-center justify-center"
            title="تسجيل الخروج"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
          </button>

          <!-- Notification Bell Toggle -->
          <button 
            @click="store.toggleNotificationDrawer()"
            class="p-2 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400 rounded-xl transition cursor-pointer hover:shadow-sm relative min-h-[44px] min-w-[44px] flex items-center justify-center"
            title="مركز الإشعارات"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span v-if="unreadNotificationsCount > 0" class="absolute -top-1 -left-1 bg-violet-650 text-white rounded-full text-[9px] font-extrabold w-4 h-4 flex items-center justify-center border border-white dark:border-slate-900 shadow-sm">
              {{ unreadNotificationsCount }}
            </span>
          </button>

          <!-- Theming Toggle -->
          <button 
            @click="store.toggleTheme()" 
            class="p-2 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400 rounded-xl transition cursor-pointer hover:shadow-sm min-h-[44px] min-w-[44px] flex items-center justify-center"
            title="تبديل مظهر النظام"
          >
            <svg v-if="store.theme === 'light'" xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.828 9.9l-.707-.707M6.343 6.343l-.707-.707M14.25 12a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Mobile Native App Top Bar (md:hidden) -->
      <div class="md:hidden px-4 py-2.5 flex items-center justify-between gap-2 min-h-[56px] border-b border-slate-200/50 dark:border-slate-800/50 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl">
        <!-- Right: Logo & System Branding -->
        <div class="flex items-center gap-2 shrink-0">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-violet-600 to-indigo-650 flex items-center justify-center shadow-md shadow-violet-500/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 012 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
          </div>
          <div class="flex flex-col">
            <span class="text-base font-black tracking-tight text-slate-900 dark:text-slate-50 leading-tight">عقلي</span>
            <span class="text-[9px] font-semibold text-slate-400 dark:text-slate-500 -mt-0.5">mymind</span>
          </div>
        </div>

        <!-- Center: Active Project Picker Button -->
        <button 
          @click="showMobileProjectsSheet = true"
          class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-violet-50/80 dark:bg-slate-800/80 hover:bg-violet-100 dark:hover:bg-slate-750 text-violet-700 dark:text-violet-300 text-xs font-bold border border-violet-200/70 dark:border-slate-700/80 max-w-[180px] sm:max-w-[220px] transition-all min-h-[44px] min-w-[44px] shadow-sm cursor-pointer"
          title="تبديل المشروع النشط"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-violet-500 dark:text-violet-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
          </svg>
          <span class="truncate">{{ activeProject?.name || store.activeProject?.name || 'جميع المشاريع' }}</span>
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-violet-400 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <!-- Left: Quick Actions (Notifications & Settings) -->
        <div class="flex items-center gap-1 shrink-0">
          <button 
            @click="store.toggleNotificationDrawer()"
            class="w-11 h-11 min-h-[44px] min-w-[44px] flex items-center justify-center text-slate-600 dark:text-slate-300 hover:text-violet-600 dark:hover:text-violet-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition cursor-pointer relative"
            title="مركز الإشعارات"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span v-if="unreadNotificationsCount > 0" class="absolute top-1.5 left-1.5 bg-violet-600 text-white rounded-full text-[8px] font-black w-4 h-4 flex items-center justify-center border border-white dark:border-slate-900 shadow-sm">
              {{ unreadNotificationsCount }}
            </span>
          </button>

          <button 
            @click="showMobileMoreSheet = true"
            class="w-11 h-11 min-h-[44px] min-w-[44px] flex items-center justify-center text-slate-600 dark:text-slate-300 hover:text-violet-600 dark:hover:text-violet-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition cursor-pointer"
            title="الإعدادات السريعة والمزيد"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
        </div>
      </div>
    </header>

    <!-- Main Workspace Area -->
    <main class="max-w-full w-full px-4 md:px-12 py-6 pb-28 md:pb-8 transition-all duration-500 relative z-10">
      <!-- Floating Exit Focus Mode Panel at the top -->
      <div v-if="store.isFocusMode" class="flex justify-center mb-8 animate-fade-in">
        <button 
          @click="store.isFocusMode = false"
          class="bg-gradient-to-r from-violet-600 to-indigo-650 hover:from-violet-700 hover:to-indigo-700 text-white font-extrabold px-6 py-3 rounded-full text-xs shadow-lg shadow-violet-500/25 cursor-pointer flex items-center space-x-2 space-x-reverse transition hover:scale-[1.03]"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <span>الخروج من وضع التركيز (Zen Mode)</span>
        </button>
      </div>

      <!-- Workspace Layout Grid -->
      <div :class="[
        store.isFocusMode ? 'max-w-full w-full space-y-8' : 'grid grid-cols-1 md:grid-cols-12 gap-8 items-start'
      ]">
        
        <!-- Left Sidebar: Project Settings (3 cols) - Hidden in Focus Mode / Settings / Routines, Hidden on Mobile (< md) -->
        <div v-if="!store.isFocusMode && store.activeView !== 'settings' && store.activeView !== 'routines'" class="hidden md:block md:col-span-4 lg:col-span-3 space-y-6">
          <ProjectPanel />
        </div>

        <!-- Center Stage (Takes full 12 cols when Focus/Settings/Routines or Mobile, else 9 cols on lg / 8 cols on md) -->
        <div :class="[
          store.isFocusMode ? 'w-full space-y-8' : (store.activeView === 'settings' || store.activeView === 'routines') ? 'md:col-span-12' : 'md:col-span-8 lg:col-span-9', 
          'space-y-8 transition-all duration-500'
        ]">
          
          <!-- Selected Tasks / Routines Component container -->
          <div :class="[
            'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-4 sm:p-6 transition-all duration-500',
            store.isFocusMode 
              ? 'shadow-2xl shadow-violet-500/[0.02] border-violet-500/20 dark:border-violet-900/35 ring-1 ring-violet-500/10' 
              : 'shadow-[0_4px_12px_rgba(0,0,0,0.015)]'
          ]">
            <div v-if="store.activeView === 'settings'">
              <Settings />
            </div>
            <div v-else-if="store.activeView === 'routines'">
              <HabitDetail v-if="activeHabitId" :habitId="activeHabitId" />
              <DailyRoutines v-else />
            </div>

            <div v-else-if="!activeProject" class="text-center py-24 text-sm text-slate-455 italic font-medium">
              يرجى إنشاء مشروع جديد أو استعادته من سلة المهملات لبدء تخطيط المهام.
            </div>
            <div v-else>
              <KeepAlive>
                <component :is="store.activeView === 'kanban' ? TaskBoard : store.activeView === 'list' ? TaskList : TaskCalendar" />
              </KeepAlive>
            </div>
          </div>

          <!-- Project Documents and Notes Block - Hidden in settings / routines view -->
          <div class="space-y-3.5" v-if="activeProject && store.activeView !== 'settings' && store.activeView !== 'routines'">
            <ProjectDocuments :key="store.activeProjectId" />
          </div>

        </div>

      </div>
    </main>

    <!-- Active Task Dialog -->
    <TaskModal />

    <!-- Slide-out / Bottom Sheet Notifications Drawer -->
    <Transition name="sheet">
      <div v-if="store.isNotificationDrawerOpen" class="fixed inset-0 z-50 flex items-end sm:items-stretch justify-center sm:justify-start" dir="rtl">
        <div 
          @click="store.isNotificationDrawerOpen = false" 
          class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 transition-opacity duration-300 cursor-pointer"
        ></div>
        
        <div 
          @touchstart="handleTouchStart"
          @touchmove="handleTouchMove"
          @touchend="handleTouchEnd(() => store.isNotificationDrawerOpen = false)"
          class="relative z-50 bg-white dark:bg-slate-900 border-t sm:border-t-0 sm:border-r border-slate-200 dark:border-slate-800 rounded-t-3xl sm:rounded-none w-full sm:max-w-sm max-h-[85vh] sm:max-h-none sm:h-full shadow-2xl p-5 sm:p-6 overflow-y-auto scrollbar-hide text-right transform transition-all duration-300"
        >
          <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto my-2.5 shrink-0 sm:hidden cursor-grab"></div>
          <NotificationCenter />
        </div>
      </div>
    </Transition>

    <!-- ══════════════════════════════════════════════════════════ -->
    <!--  MOBILE APP FLOATING STICKY BOTTOM NAVIGATION BAR (md:hidden) -->
    <!-- ══════════════════════════════════════════════════════════ -->
    <MobileBottomNav 
      v-if="!store.isFocusMode" 
      class="md:hidden"
      v-model:showProjectsSheet="showMobileProjectsSheet" 
      v-model:showMoreSheet="showMobileMoreSheet" 
      @set-view="setView" 
    />

    <!-- Mobile Projects Bottom Sheet Drawer -->
    <Transition name="sheet">
      <div v-if="showMobileProjectsSheet" class="md:hidden fixed inset-0 z-50 flex items-end justify-center" dir="rtl">
        <div 
          @click="showMobileProjectsSheet = false" 
          class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 transition-opacity"
        ></div>
        <div 
          @touchstart="handleTouchStart"
          @touchmove="handleTouchMove"
          @touchend="handleTouchEnd(() => showMobileProjectsSheet = false)"
          class="relative z-50 w-full bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 rounded-t-3xl p-5 shadow-2xl max-h-[85vh] overflow-y-auto"
        >
          <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto my-2.5 shrink-0 cursor-grab"></div>
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-black text-slate-900 dark:text-white">إدارة المشاريع والتصنيفات</h3>
            <button @click="showMobileProjectsSheet = false" class="text-xs font-bold text-slate-400 p-1 cursor-pointer">إغلاق ✕</button>
          </div>
          <ProjectPanel />
        </div>
      </div>
    </Transition>

    <!-- Mobile More / Quick Settings Bottom Sheet Drawer -->
    <Transition name="sheet">
      <div v-if="showMobileMoreSheet" class="md:hidden fixed inset-0 z-50 flex items-end justify-center" dir="rtl">
        <div 
          @click="showMobileMoreSheet = false" 
          class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 transition-opacity"
        ></div>
        <div 
          @touchstart="handleTouchStart"
          @touchmove="handleTouchMove"
          @touchend="handleTouchEnd(() => showMobileMoreSheet = false)"
          class="relative z-50 w-full bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 rounded-t-3xl p-5 shadow-2xl max-h-[85vh] overflow-y-auto"
        >
          <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto my-2.5 shrink-0 cursor-grab"></div>
          
          <!-- User Profile Header -->
          <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 mb-4">
            <div class="text-right">
              <span class="text-sm font-black text-slate-900 dark:text-white block">{{ store.currentUser?.name }}</span>
              <span class="text-xs text-slate-400 font-bold block">{{ store.currentUser?.role?.name || 'زائر' }}</span>
            </div>
            <button @click="handleLogout" class="px-3.5 py-1.5 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 text-xs font-extrabold cursor-pointer">
              تسجيل الخروج
            </button>
          </div>

          <!-- Quick Actions Grid -->
          <div class="grid grid-cols-2 gap-3 mb-2">
            <button 
              @click="store.toggleNotificationDrawer(); showMobileMoreSheet = false" 
              class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-850 border border-slate-200/60 dark:border-slate-800 text-right flex items-center justify-between cursor-pointer"
            >
              <span class="text-xs font-bold text-slate-800 dark:text-slate-200">الإشعارات</span>
              <span class="px-2 py-0.5 rounded-md bg-violet-600 text-white text-[10px] font-black">{{ unreadNotificationsCount }}</span>
            </button>

            <button 
              @click="store.toggleTheme()" 
              class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-850 border border-slate-200/60 dark:border-slate-800 text-right flex items-center justify-between cursor-pointer"
            >
              <span class="text-xs font-bold text-slate-800 dark:text-slate-200">المظهر</span>
              <span class="text-xs font-bold">{{ store.theme === 'light' ? '🌙 داكن' : '☀️ فاتح' }}</span>
            </button>

            <button 
              @click="goToSettings(); showMobileMoreSheet = false" 
              class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-850 border border-slate-200/60 dark:border-slate-800 text-right flex items-center justify-between col-span-2 cursor-pointer"
            >
              <span class="text-xs font-bold text-slate-800 dark:text-slate-200">الإعدادات وإدارة النظام</span>
              <span class="text-xs">⚙️</span>
            </button>
          </div>
        </div>
      </div>
    </Transition>

  </div>
</template>

