<script setup>
import { computed } from 'vue'
import { store } from '../store'

const props = defineProps({
  showProjectsSheet: {
    type: Boolean,
    default: false
  },
  showMoreSheet: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits([
  'set-view',
  'update:showProjectsSheet',
  'update:showMoreSheet',
  'quick-create'
])

const unreadNotificationsCount = computed(() => 
  store.notifications?.filter(n => !n.isRead).length || 0
)

const handleSetView = (view) => {
  emit('set-view', view)
  emit('update:showProjectsSheet', false)
  emit('update:showMoreSheet', false)
}

const toggleProjects = () => {
  const nextState = !props.showProjectsSheet
  emit('update:showProjectsSheet', nextState)
  if (nextState) {
    emit('update:showMoreSheet', false)
  }
}

const toggleMore = () => {
  const nextState = !props.showMoreSheet
  emit('update:showMoreSheet', nextState)
  if (nextState) {
    emit('update:showProjectsSheet', false)
  }
}
</script>

<template>
  <nav 
    class="fixed bottom-safe left-3 right-3 z-nav bg-white/80 dark:bg-slate-900/80 backdrop-blur-2xl border border-white/30 dark:border-slate-800/60 shadow-2xl rounded-2xl p-1.5 flex items-center justify-around max-w-lg mx-auto transition-all duration-300"
    aria-label="التنقل الرئيسي للهاتف"
  >
    <!-- 1. اللوحة (Kanban) -->
    <button 
      @click="handleSetView('kanban')"
      :class="[
        'flex-1 min-h-[44px] flex flex-col items-center justify-center gap-1 py-1 px-2 rounded-xl transition-all duration-200 cursor-pointer active-scale relative select-none',
        store.activeView === 'kanban' && !showProjectsSheet && !showMoreSheet
          ? 'bg-gradient-to-r from-violet-600 to-indigo-600 text-white font-extrabold shadow-md shadow-violet-500/25 tab-active-scale' 
          : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100/50 dark:hover:bg-slate-800/50'
      ]"
      title="عرض اللوحة" aria-label="عرض اللوحة"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
      </svg>
      <span class="text-[10px] tracking-tight">اللوحة</span>
    </button>

    <!-- 2. الجدول (List) -->
    <button 
      @click="handleSetView('list')"
      :class="[
        'flex-1 min-h-[44px] flex flex-col items-center justify-center gap-1 py-1 px-2 rounded-xl transition-all duration-200 cursor-pointer active-scale relative select-none',
        store.activeView === 'list' && !showProjectsSheet && !showMoreSheet
          ? 'bg-gradient-to-r from-violet-600 to-indigo-600 text-white font-extrabold shadow-md shadow-violet-500/25 tab-active-scale' 
          : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100/50 dark:hover:bg-slate-800/50'
      ]"
      title="عرض الجدول" aria-label="عرض الجدول"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
      <span class="text-[10px] tracking-tight">الجدول</span>
    </button>

    <!-- 3. يومياتي (Routines) -->
    <button 
      @click="handleSetView('routines')"
      :class="[
        'flex-1 min-h-[44px] flex flex-col items-center justify-center gap-1 py-1 px-2 rounded-xl transition-all duration-200 cursor-pointer active-scale relative select-none',
        store.activeView === 'routines' && !showProjectsSheet && !showMoreSheet
          ? 'bg-gradient-to-r from-violet-600 to-indigo-600 text-white font-extrabold shadow-md shadow-violet-500/25 tab-active-scale' 
          : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100/50 dark:hover:bg-slate-800/50'
      ]"
      title="يومياتي والعادات"
      aria-label="يومياتي والعادات"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span class="text-[10px] tracking-tight">يومياتي</span>
    </button>

    <!-- 4. التقويم (Calendar) -->
    <button 
      @click="handleSetView('calendar')"
      :class="[
        'flex-1 min-h-[44px] flex flex-col items-center justify-center gap-1 py-1 px-2 rounded-xl transition-all duration-200 cursor-pointer active-scale relative select-none',
        store.activeView === 'calendar' && !showProjectsSheet && !showMoreSheet
          ? 'bg-gradient-to-r from-violet-600 to-indigo-600 text-white font-extrabold shadow-md shadow-violet-500/25 tab-active-scale' 
          : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100/50 dark:hover:bg-slate-800/50'
      ]"
      title="التقويم"
      aria-label="التقويم"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
      <span class="text-[10px] tracking-tight">التقويم</span>
    </button>

    <!-- 5. المشاريع (Projects Sheet Trigger) -->
    <button 
      @click="toggleProjects"
      :class="[
        'flex-1 min-h-[44px] flex flex-col items-center justify-center gap-1 py-1 px-2 rounded-xl transition-all duration-200 cursor-pointer active-scale relative select-none',
        showProjectsSheet
          ? 'bg-gradient-to-r from-violet-600 to-indigo-600 text-white font-extrabold shadow-md shadow-violet-500/25 tab-active-scale' 
          : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100/50 dark:hover:bg-slate-800/50'
      ]"
      title="عرض المشاريع والتصنيفات"
      aria-label="عرض المشاريع والتصنيفات"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
      </svg>
      <span class="text-[10px] tracking-tight">المشاريع</span>
    </button>

    <!-- 6. المزيد (More Sheet Trigger) -->
    <button 
      @click="toggleMore"
      :class="[
        'flex-1 min-h-[44px] flex flex-col items-center justify-center gap-1 py-1 px-2 rounded-xl transition-all duration-200 cursor-pointer active-scale relative select-none',
        showMoreSheet || (store.activeView === 'settings' && !showProjectsSheet)
          ? 'bg-gradient-to-r from-violet-600 to-indigo-600 text-white font-extrabold shadow-md shadow-violet-500/25 tab-active-scale' 
          : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-100/50 dark:hover:bg-slate-800/50'
      ]"
      title="المزيد والإعدادات"
      aria-label="المزيد والإعدادات"
    >
      <div class="relative">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
        </svg>
        <span 
          v-if="unreadNotificationsCount > 0" 
          :class="[
            'absolute -top-1 -right-1.5 w-2 h-2 rounded-full animate-pulse',
            showMoreSheet || store.activeView === 'settings' ? 'bg-amber-300' : 'bg-violet-600'
          ]"
        ></span>
      </div>
      <span class="text-[10px] tracking-tight">المزيد</span>
    </button>
  </nav>
</template>
