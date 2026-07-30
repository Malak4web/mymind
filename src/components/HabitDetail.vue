<script setup>
import { ref, computed } from 'vue'
import { store } from '../store'

const props = defineProps({
  habitId: {
    type: [Number, String],
    required: true
  }
})

const habit = computed(() => {
  return store.habits.find(h => h.id === Number(props.habitId))
})

const goBackToHabits = () => {
  window.location.hash = '#routines'
  store.activeView = 'routines'
}

// Date Key Helpers
const formatDateKey = (dateObj) => {
  const y = dateObj.getFullYear()
  const m = String(dateObj.getMonth() + 1).padStart(2, '0')
  const d = String(dateObj.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

const todayKey = computed(() => formatDateKey(new Date()))

const isCompletedToday = computed(() => {
  return !!habit.value?.logs?.[todayKey.value]?.completed
})

// Celebratory Micro-Animation State
const isCelebrating = ref(false)

const triggerCelebration = () => {
  isCelebrating.value = true
  setTimeout(() => {
    isCelebrating.value = false
  }, 900)
}

const toggleTodayCheckin = () => {
  if (!habit.value) return
  const willBeCompleted = !isCompletedToday.value
  store.toggleHabitLog(habit.value.id, todayKey.value)
  if (willBeCompleted) {
    triggerCelebration()
  }
}

// Quick 7-Day Horizontal Touch Strip
const weekDayShorts = ['أح', 'إث', 'ثل', 'أر', 'خم', 'جم', 'سب']

const last7Days = computed(() => {
  const days = []
  const today = new Date()

  for (let i = 6; i >= 0; i--) {
    const d = new Date(today)
    d.setDate(today.getDate() - i)
    const key = formatDateKey(d)
    const dayOfWeek = d.getDay()
    const isCompleted = !!habit.value?.logs?.[key]?.completed
    const isToday = (i === 0)

    days.push({
      dateObj: d,
      dateKey: key,
      dayName: weekDayShorts[dayOfWeek],
      dayNumber: d.getDate(),
      isCompleted,
      isToday
    })
  }
  return days
})

const toggleDayLog = (dateKey) => {
  if (!habit.value) return
  const isTargetToday = dateKey === todayKey.value
  const willBeCompleted = !habit.value.logs?.[dateKey]?.completed
  store.toggleHabitLog(habit.value.id, dateKey)
  if (isTargetToday && willBeCompleted) {
    triggerCelebration()
  }
}

// Calculate Streak
const currentStreak = computed(() => {
  if (!habit.value || !habit.value.logs) return 0
  let streak = 0
  let checkDate = new Date()
  
  const today = formatDateKey(checkDate)
  if (!habit.value.logs[today]?.completed) {
    checkDate.setDate(checkDate.getDate() - 1)
  }

  while (true) {
    const key = formatDateKey(checkDate)
    if (habit.value.logs[key]?.completed) {
      streak++
      checkDate.setDate(checkDate.getDate() - 1)
    } else {
      break
    }
  }
  return streak
})

const getStreakBadgeInfo = (streak) => {
  if (streak >= 30) return { title: '👑 أسطورة الاستمرار الذهبي', color: 'from-amber-400 to-yellow-600 text-white' }
  if (streak >= 14) return { title: '🥇 بطل العادات الفضي', color: 'from-violet-500 to-indigo-600 text-white' }
  if (streak >= 7) return { title: '🥈 ملتزم متألق', color: 'from-blue-400 to-cyan-500 text-white' }
  if (streak >= 3) return { title: '🥉 بداية قوية', color: 'from-emerald-400 to-teal-500 text-white' }
  return { title: '🌱 خطوة أولى نحو النجاح', color: 'from-slate-400 to-slate-600 text-white' }
}

// Month Heatmap & Navigation
const selectedYear = ref(new Date().getFullYear())
const selectedMonth = ref(new Date().getMonth()) // 0-11

const monthNamesAr = [
  'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
  'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'
]

const currentMonthLabel = computed(() => {
  return `${monthNamesAr[selectedMonth.value]} ${selectedYear.value}`
})

const isCurrentMonthSelected = computed(() => {
  const now = new Date()
  return selectedYear.value === now.getFullYear() && selectedMonth.value === now.getMonth()
})

const prevMonth = () => {
  if (selectedMonth.value === 0) {
    selectedMonth.value = 11
    selectedYear.value--
  } else {
    selectedMonth.value--
  }
}

const nextMonth = () => {
  if (selectedMonth.value === 11) {
    selectedMonth.value = 0
    selectedYear.value++
  } else {
    selectedMonth.value++
  }
}

const resetToCurrentMonth = () => {
  const now = new Date()
  selectedYear.value = now.getFullYear()
  selectedMonth.value = now.getMonth()
}

const weekHeaders = [
  { short: 'أح', full: 'الأحد' },
  { short: 'إث', full: 'الإثنين' },
  { short: 'ثل', full: 'الثلاثاء' },
  { short: 'أر', full: 'الأربعاء' },
  { short: 'خم', full: 'الخميس' },
  { short: 'جم', full: 'الجمعة' },
  { short: 'سب', full: 'السبت' }
]

const monthHeatmapData = computed(() => {
  if (!habit.value) return { offset: 0, days: [] }
  
  const year = selectedYear.value
  const month = selectedMonth.value
  
  const firstDay = new Date(year, month, 1)
  const offset = firstDay.getDay() // 0 = Sunday
  const daysInMonth = new Date(year, month + 1, 0).getDate()

  const days = []
  const todayStr = formatDateKey(new Date())

  for (let i = 1; i <= daysInMonth; i++) {
    const d = new Date(year, month, i)
    const key = formatDateKey(d)
    const log = habit.value.logs?.[key]
    const completed = !!log?.completed
    const count = log?.count || 0

    let level = 0
    if (completed) {
      level = 3
    } else if (habit.value.type === 'numeric' && habit.value.targetValue > 0) {
      const ratio = count / habit.value.targetValue
      if (ratio >= 1) level = 3
      else if (ratio >= 0.5) level = 2
      else if (ratio > 0) level = 1
      else level = 0
    } else if (count > 0) {
      level = 1
    }

    days.push({
      dayNum: i,
      dateKey: key,
      completed,
      count,
      level,
      isToday: key === todayStr
    })
  }

  return { offset, days }
})

// Current month completion percentage for circular progress ring gauge
const currentMonthCompletionPercentage = computed(() => {
  if (!habit.value) return 0
  const now = new Date()
  const year = now.getFullYear()
  const month = now.getMonth()
  const daysInMonth = new Date(year, month + 1, 0).getDate()

  let completedCount = 0
  for (let i = 1; i <= daysInMonth; i++) {
    const d = new Date(year, month, i)
    const key = formatDateKey(d)
    if (habit.value.logs?.[key]?.completed) {
      completedCount++
    }
  }

  return Math.round((completedCount / daysInMonth) * 100)
})

// SVG Gauge Calculations (radius = 38)
const circumference = 2 * Math.PI * 38 // ~238.76
const progressDashOffset = computed(() => {
  const pct = Math.min(100, Math.max(0, currentMonthCompletionPercentage.value))
  return circumference - (pct / 100) * circumference
})

// Sub-tasks Checklist
const newChecklistTitle = ref('')
const editingSubtaskId = ref(null)
const editingSubtaskTitle = ref('')

const checklistProgress = computed(() => {
  if (!habit.value?.checklist?.length) return 0
  const total = habit.value.checklist.length
  const done = habit.value.checklist.filter(c => c.completed).length
  return Math.round((done / total) * 100)
})

const handleAddChecklistItem = () => {
  if (!newChecklistTitle.value.trim() || !habit.value) return
  store.addHabitChecklistItem(habit.value.id, newChecklistTitle.value)
  newChecklistTitle.value = ''
}

const handleToggleChecklist = (itemId) => {
  if (!habit.value) return
  store.toggleHabitChecklistItem(habit.value.id, itemId)
}

const handleDeleteChecklist = (itemId) => {
  if (!habit.value) return
  store.deleteHabitChecklistItem(habit.value.id, itemId)
}

const startEditingSubtask = (item) => {
  editingSubtaskId.value = item.id
  editingSubtaskTitle.value = item.title
}

const cancelEditingSubtask = () => {
  editingSubtaskId.value = null
  editingSubtaskTitle.value = ''
}

const saveEditingSubtask = (itemId) => {
  if (!habit.value || !editingSubtaskTitle.value.trim()) return
  const item = habit.value.checklist.find(i => i.id === itemId)
  if (item) {
    item.title = editingSubtaskTitle.value.trim()
    store.saveHabits()
  }
  editingSubtaskId.value = null
  editingSubtaskTitle.value = ''
}

// Habit Journal Notes Interface
const newNoteContent = ref('')
const noteDate = ref(formatDateKey(new Date()))
const selectedMood = ref('🤩')
const moodOptions = [
  { emoji: '🤩', label: 'ممتاز' },
  { emoji: '😃', label: 'سعيد' },
  { emoji: '😐', label: 'عادي' },
  { emoji: '😫', label: 'متعب' },
  { emoji: '🚀', label: 'منجز' },
  { emoji: '⚡', label: 'طاقة' }
]

const parseNoteMoodAndContent = (content) => {
  if (!content) return { mood: null, text: '' }
  const moodRegex = /^([\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}])\s*\|\s*(.*)/u
  const match = content.match(moodRegex)
  if (match) {
    return { mood: match[1], text: match[2] }
  }
  return { mood: null, text: content }
}

const handleAddNote = () => {
  if (!newNoteContent.value.trim() || !habit.value) return
  const noteText = `${selectedMood.value} | ${newNoteContent.value.trim()}`
  store.addHabitNote(habit.value.id, noteText, noteDate.value || formatDateKey(new Date()))
  newNoteContent.value = ''
}

const handleDeleteNote = (noteId) => {
  if (!habit.value) return
  store.deleteHabitNote(habit.value.id, noteId)
}
</script>

<template>
  <div class="max-w-6xl mx-auto px-3 sm:px-6 lg:px-8 py-6 sm:py-8 animate-fade-in space-y-6 sm:space-y-8 overflow-x-hidden">
    
    <!-- If Habit Not Found -->
    <div v-if="!habit" class="text-center py-20 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-8 shadow-sm">
      <div class="text-4xl mb-4">🔍</div>
      <h3 class="text-lg font-bold text-slate-900 dark:text-white">لم يتم العثور على هذه العادة</h3>
      <button @click="goBackToHabits" class="mt-4 px-6 py-2.5 rounded-2xl bg-violet-600 hover:bg-violet-500 text-white font-bold text-sm min-h-[44px]">
        العودة إلى يومياتي والعادات
      </button>
    </div>

    <!-- Main Habit View Screen -->
    <div v-else class="space-y-6 sm:space-y-8">
      
      <!-- Top Action Bar -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <button 
          @click="goBackToHabits"
          class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs sm:text-sm hover:border-violet-500 transition cursor-pointer shadow-sm min-h-[44px]"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transform rotate-180 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
          </svg>
          <span>العودة لجميع العادات</span>
        </button>

        <div class="flex items-center gap-2 text-xs font-bold text-slate-400 dark:text-slate-500">
          <span>معرف العادة:</span>
          <code class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-violet-600 dark:text-violet-400 text-[11px] font-mono">#routines/habit-{{ habit.id }}</code>
        </div>
      </div>

      <!-- 1. Glassmorphism Hero Header Banner -->
      <div class="bg-white/70 dark:bg-slate-900/70 backdrop-blur-2xl border border-white/40 dark:border-slate-800/60 shadow-xl rounded-3xl p-6 md:p-8 relative overflow-hidden transition-all">
        <!-- Background Ambient Glow -->
        <div class="absolute -top-24 -right-24 w-80 h-80 bg-violet-500/15 dark:bg-violet-600/25 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-24 w-80 h-80 bg-emerald-500/15 dark:bg-emerald-600/25 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col lg:flex-row items-center justify-between gap-6">
          
          <!-- Left Main Info (Icon, Title, Badges) -->
          <div class="flex items-start sm:items-center gap-4 sm:gap-5 w-full lg:w-auto">
            <div 
              :class="[
                'w-16 h-16 sm:w-20 sm:h-20 rounded-3xl flex items-center justify-center text-3xl sm:text-4xl shadow-xl bg-gradient-to-tr text-white shrink-0',
                habit.color || 'from-violet-500 to-indigo-500'
              ]"
            >
              {{ habit.icon || '⭐' }}
            </div>

            <div class="space-y-2 flex-1">
              <!-- Badges Row -->
              <div class="flex flex-wrap items-center gap-2">
                <span :class="['px-3 py-1 rounded-full text-xs font-black bg-gradient-to-r shadow-sm', getStreakBadgeInfo(currentStreak).color]">
                  {{ getStreakBadgeInfo(currentStreak).title }}
                </span>

                <span class="px-3 py-1 rounded-full text-xs font-black bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30">
                  🔥 {{ currentStreak }} يوماً متتالياً
                </span>
              </div>

              <!-- Habit Title -->
              <h1 class="text-xl sm:text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                {{ habit.title }}
              </h1>
            </div>
          </div>

          <!-- Right Side: SVG Circular Progress Ring & Primary Check-in Button -->
          <div class="flex flex-col sm:flex-row items-center gap-6 w-full lg:w-auto justify-end">
            
            <!-- Animated SVG Circular Progress Ring Gauge -->
            <div class="relative w-24 h-24 sm:w-28 sm:h-28 flex items-center justify-center shrink-0">
              <svg class="w-full h-full transform -rotate-90" viewBox="0 0 96 96">
                <!-- Background Circle -->
                <circle
                  cx="48"
                  cy="48"
                  r="38"
                  stroke="currentColor"
                  stroke-width="8"
                  class="text-slate-200 dark:text-slate-800/80 fill-none"
                />
                <!-- Animated Progress Circle -->
                <circle
                  cx="48"
                  cy="48"
                  r="38"
                  stroke="url(#ring-gradient)"
                  stroke-width="8"
                  stroke-linecap="round"
                  class="fill-none transition-all duration-1000 ease-out"
                  :stroke-dasharray="circumference"
                  :stroke-dashoffset="progressDashOffset"
                />
                <defs>
                  <linearGradient id="ring-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#10b981" />
                    <stop offset="100%" stop-color="#14b8a6" />
                  </linearGradient>
                </defs>
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none">
                <span class="text-lg sm:text-xl font-black text-slate-900 dark:text-white tracking-tight">
                  {{ currentMonthCompletionPercentage }}%
                </span>
                <span class="text-[9px] font-bold text-slate-500 dark:text-slate-400">إنجاز الشهر</span>
              </div>
            </div>

            <!-- Primary Check-in Button (min 52px height) -->
            <button 
              @click="toggleTodayCheckin"
              :class="[
                'w-full sm:w-auto px-8 py-3.5 rounded-2xl font-black text-sm transition-all duration-300 shadow-xl cursor-pointer flex items-center justify-center gap-3 transform min-h-[52px]',
                isCelebrating ? 'scale-110 ring-4 ring-emerald-400/50 bg-gradient-to-r from-emerald-500 to-teal-500 text-white' : '',
                isCompletedToday && !isCelebrating
                  ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-white shadow-emerald-500/30'
                  : !isCompletedToday && !isCelebrating
                  ? 'bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white shadow-violet-600/30 active:scale-95'
                  : ''
              ]"
            >
              <span class="text-xl sm:text-2xl">{{ isCelebrating ? '🎉' : isCompletedToday ? '✓' : '+' }}</span>
              <span>{{ isCelebrating ? 'رائع! تم التسجيل 🎉' : isCompletedToday ? 'مكتملة لليوم 🎉' : 'تسجيل إنجاز اليوم' }}</span>
            </button>

          </div>

        </div>

        <!-- Quick 7-Day Horizontal Touch Strip -->
        <div class="mt-6 pt-6 border-t border-slate-200/60 dark:border-slate-800/80">
          <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
              <span>⚡</span> شريط الإنجاز السريع (آخر 7 أيام)
            </span>
            <span class="text-[11px] font-semibold text-slate-400">اضغط للتسجيل السريع</span>
          </div>

          <div class="flex items-center gap-2 sm:gap-3 overflow-x-auto no-scrollbar py-1">
            <button 
              v-for="day in last7Days"
              :key="day.dateKey"
              @click="toggleDayLog(day.dateKey)"
              :class="[
                'flex-1 min-w-[50px] p-2.5 rounded-2xl flex flex-col items-center justify-center gap-1 transition-all cursor-pointer border min-h-[56px]',
                day.isCompleted
                  ? 'bg-emerald-500 text-white border-emerald-400 shadow-md shadow-emerald-500/20 font-black'
                  : day.isToday
                  ? 'bg-violet-500/10 dark:bg-violet-500/20 border-violet-500/50 text-violet-700 dark:text-violet-300 font-bold'
                  : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:border-violet-400'
              ]"
            >
              <span class="text-[10px] font-bold opacity-80">{{ day.dayName }}</span>
              <span class="text-xs font-black">{{ day.dayNumber }}</span>
              <div :class="['w-2 h-2 rounded-full mt-0.5', day.isCompleted ? 'bg-white' : 'bg-slate-300 dark:bg-slate-700']"></div>
            </button>
          </div>
        </div>

      </div>

      <!-- Main Content Grid (Checklist, Monthly Heatmap & Notes) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-start">
        
        <!-- Left 7 Cols: Sub-Tasks Checklist & Monthly Heatmap -->
        <div class="lg:col-span-7 space-y-6 sm:space-y-8">
          
          <!-- 2. Mobile-Optimized Sub-Tasks Checklist Card -->
          <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 md:p-8 border border-slate-200 dark:border-slate-800 shadow-sm">
            
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                  <span>📋</span> قائمة المهام الفرعية (Sub-tasks)
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-semibold mt-0.5">خطوات تنفيذ العادة والتفاصيل الفرعية</p>
              </div>
              
              <span class="px-3 py-1 rounded-xl bg-violet-50 dark:bg-violet-950/50 text-violet-600 dark:text-violet-400 text-xs font-black border border-violet-200 dark:border-violet-800/50">
                {{ habit.checklist?.filter(c => c.completed).length || 0 }} / {{ habit.checklist?.length || 0 }}
              </span>
            </div>

            <!-- Visual Percentage Progress Bar -->
            <div class="space-y-1.5 mb-6">
              <div class="flex items-center justify-between text-xs font-bold text-slate-500 dark:text-slate-400">
                <span>نسبة الإنجاز الفرعية</span>
                <span class="font-mono text-emerald-600 dark:text-emerald-400 font-black">{{ checklistProgress }}%</span>
              </div>
              <div class="h-3 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden p-0.5 border border-slate-200/60 dark:border-slate-800">
                <div 
                  class="h-full bg-gradient-to-r from-emerald-500 to-teal-400 rounded-full transition-all duration-500 ease-out"
                  :style="{ width: `${checklistProgress}%` }"
                ></div>
              </div>
            </div>

            <!-- Add Checklist Item Form -->
            <form @submit.prevent="handleAddChecklistItem" class="flex gap-2 mb-6">
              <input 
                v-model="newChecklistTitle"
                type="text"
                placeholder="إضافة خطوة جديدة..."
                class="flex-1 px-4 py-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-850 text-slate-900 dark:text-white text-xs font-bold outline-none focus:ring-2 focus:ring-violet-500 min-h-[44px]"
              />
              <button 
                type="submit"
                class="px-5 py-3 rounded-2xl bg-violet-600 hover:bg-violet-500 text-white font-bold text-xs shadow-md transition cursor-pointer shrink-0 min-h-[44px] flex items-center justify-center"
              >
                + إضافة
              </button>
            </form>

            <!-- Checklist List -->
            <div v-if="!habit.checklist || habit.checklist.length === 0" class="text-center py-8 text-xs text-slate-400 font-semibold border-2 border-dashed border-slate-100 dark:border-slate-800/80 rounded-2xl">
              لا توجد عناصر مضافة للقائمة بعد. أضف أول خطوة أعلاه!
            </div>

            <div v-else class="space-y-2.5">
              <div 
                v-for="item in habit.checklist" 
                :key="item.id"
                :class="[
                  'p-3 rounded-2xl border transition-all flex items-center justify-between gap-3 min-h-[44px]',
                  item.completed
                    ? 'bg-slate-50 dark:bg-slate-850/60 border-slate-200/60 dark:border-slate-800/80 text-slate-400 dark:text-slate-500'
                    : 'bg-white dark:bg-slate-850 border-slate-200 dark:border-slate-750 text-slate-800 dark:text-slate-100 hover:border-violet-400'
                ]"
              >
                <!-- Inline Editing Mode -->
                <template v-if="editingSubtaskId === item.id">
                  <div class="flex items-center gap-2 flex-1">
                    <input 
                      v-model="editingSubtaskTitle"
                      type="text"
                      @keyup.enter="saveEditingSubtask(item.id)"
                      @keyup.esc="cancelEditingSubtask"
                      class="flex-1 px-3 py-1.5 rounded-xl border border-violet-500 bg-white dark:bg-slate-900 text-xs font-bold outline-none dark:text-white"
                    />
                    <button 
                      @click="saveEditingSubtask(item.id)"
                      class="min-h-[44px] min-w-[44px] flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-bold hover:bg-emerald-50 dark:hover:bg-emerald-950/50 rounded-xl"
                      title="حفظ"
                    >
                      ✓
                    </button>
                    <button 
                      @click="cancelEditingSubtask"
                      class="min-h-[44px] min-w-[44px] flex items-center justify-center text-slate-400 hover:text-slate-600 rounded-xl"
                      title="إلغاء"
                    >
                      ✕
                    </button>
                  </div>
                </template>

                <!-- Normal View Mode -->
                <template v-else>
                  <div class="flex items-center gap-3 flex-1 cursor-pointer" @click="handleToggleChecklist(item.id)">
                    <!-- Touch-Friendly Check Circle (min 44px hit target wrapper) -->
                    <div class="min-w-[44px] min-h-[44px] flex items-center justify-center">
                      <div 
                        :class="[
                          'w-6 h-6 rounded-lg flex items-center justify-center text-xs font-black border transition-all duration-300',
                          item.completed ? 'bg-emerald-500 text-white border-emerald-500 scale-105' : 'border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800'
                        ]"
                      >
                        <span v-if="item.completed">✓</span>
                      </div>
                    </div>

                    <span :class="['text-xs font-bold transition-all duration-300', item.completed ? 'line-through opacity-70' : '']">
                      {{ item.title }}
                    </span>
                  </div>

                  <div class="flex items-center gap-1">
                    <!-- Edit Button -->
                    <button 
                      @click="startEditingSubtask(item)"
                      class="min-h-[44px] min-w-[44px] flex items-center justify-center text-slate-400 hover:text-violet-500 transition cursor-pointer"
                      title="تعديل العنصر"
                    >
                      ✏️
                    </button>

                    <!-- Delete Button -->
                    <button 
                      @click="handleDeleteChecklist(item.id)"
                      class="min-h-[44px] min-w-[44px] flex items-center justify-center text-slate-300 hover:text-red-500 transition cursor-pointer"
                      title="حذف العنصر"
                    >
                      ✕
                    </button>
                  </div>
                </template>
              </div>
            </div>

          </div>

          <!-- 4. Monthly Heatmap Calendar Grid Card -->
          <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 md:p-8 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
            
            <!-- Month Navigator Header -->
            <div class="flex flex-wrap items-center justify-between gap-3">
              <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>📅</span> تقويم الالتزام الشهري
              </h3>

              <div class="flex items-center gap-2">
                <button 
                  @click="prevMonth"
                  class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-violet-100 dark:hover:bg-violet-900/40 text-xs font-bold transition cursor-pointer min-h-[44px] flex items-center"
                >
                  ◄ الشهر السابق
                </button>

                <button 
                  v-if="!isCurrentMonthSelected"
                  @click="resetToCurrentMonth"
                  class="px-3 py-1.5 rounded-xl bg-violet-600 text-white text-xs font-bold shadow-sm transition cursor-pointer min-h-[44px] flex items-center"
                >
                  الشهر الحالي
                </button>

                <button 
                  @click="nextMonth"
                  class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-violet-100 dark:hover:bg-violet-900/40 text-xs font-bold transition cursor-pointer min-h-[44px] flex items-center"
                >
                  الشهر التالي ►
                </button>
              </div>
            </div>

            <!-- Current Month Label & Intensity Legend -->
            <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
              <span class="text-sm font-black text-violet-600 dark:text-violet-400">
                {{ currentMonthLabel }}
              </span>

              <!-- 4-Level Color Intensity Legend -->
              <div class="flex items-center gap-1.5 text-[10px] font-bold text-slate-500 dark:text-slate-400">
                <span>أقل</span>
                <span class="w-3 h-3 rounded bg-slate-100 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-800"></span>
                <span class="w-3 h-3 rounded bg-emerald-500/30 border border-emerald-500/40"></span>
                <span class="w-3 h-3 rounded bg-emerald-500/60 border border-emerald-500/70"></span>
                <span class="w-3 h-3 rounded bg-emerald-500 border border-emerald-400 shadow-sm"></span>
                <span>أكثر</span>
              </div>
            </div>

            <!-- Calendar Grid Container -->
            <div class="space-y-2">
              <!-- RTL Day-of-Week Headers -->
              <div class="grid grid-cols-7 gap-1.5 text-center">
                <div 
                  v-for="wh in weekHeaders" 
                  :key="wh.short"
                  class="text-xs font-black text-slate-400 dark:text-slate-500 py-1"
                  :title="wh.full"
                >
                  {{ wh.short }}
                </div>
              </div>

              <!-- Day Cells -->
              <div class="grid grid-cols-7 gap-1.5 text-center">
                <!-- Empty Offset Padding Cells -->
                <div 
                  v-for="o in monthHeatmapData.offset" 
                  :key="'offset-' + o" 
                  class="h-10 sm:h-11 rounded-xl bg-transparent"
                ></div>

                <!-- Calendar Days -->
                <button 
                  v-for="item in monthHeatmapData.days" 
                  :key="item.dateKey"
                  @click="toggleDayLog(item.dateKey)"
                  :class="[
                    'h-10 sm:h-11 rounded-xl flex flex-col items-center justify-center text-xs transition-all border cursor-pointer min-h-[44px]',
                    item.isToday ? 'ring-2 ring-violet-500 ring-offset-1 dark:ring-offset-slate-900' : '',
                    item.level === 3
                      ? 'bg-emerald-500 text-white border-emerald-400 shadow-md shadow-emerald-500/30 font-black scale-105'
                      : item.level === 2
                      ? 'bg-emerald-500/60 text-white border-emerald-500/70 font-semibold'
                      : item.level === 1
                      ? 'bg-emerald-500/30 text-emerald-800 dark:text-emerald-300 border-emerald-500/40'
                      : 'bg-slate-100 dark:bg-slate-800/60 text-slate-600 dark:text-slate-400 border-slate-200/50 dark:border-slate-800/80 hover:border-violet-400'
                  ]"
                  :title="item.dateKey + (item.completed ? ' (مكتمل)' : '')"
                >
                  <span class="font-bold">{{ item.dayNum }}</span>
                  <span v-if="item.completed" class="text-[9px] -mt-1 font-black">✓</span>
                </button>
              </div>
            </div>

          </div>

        </div>

        <!-- Right 5 Cols: 3. Habit Journal Notes Interface -->
        <div class="lg:col-span-5 space-y-6 sm:space-y-8">
          
          <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 md:p-8 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6">
            
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
              <div>
                <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                  <span>📝</span> يوميات ونوتس العادة
                </h3>
                <p class="text-xs text-slate-400 font-semibold mt-0.5">تدوين الأفكار والانطباعات</p>
              </div>
              <span class="px-3 py-1 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-xs font-bold">
                {{ habit.notesList?.length || 0 }} ملاحظة
              </span>
            </div>

            <!-- Inline Note Creation Form -->
            <form @submit.prevent="handleAddNote" class="space-y-4">
              
              <!-- Mood Selector Pills -->
              <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-2">
                  اختر الشعور / الحالة النفسية:
                </label>
                <div class="flex flex-wrap gap-2">
                  <button 
                    v-for="m in moodOptions" 
                    :key="m.emoji"
                    type="button"
                    @click="selectedMood = m.emoji"
                    :class="[
                      'px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 border min-h-[44px] cursor-pointer',
                      selectedMood === m.emoji
                        ? 'bg-violet-600 text-white border-violet-600 shadow-md ring-2 ring-violet-400/40'
                        : 'bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-750 hover:border-violet-400'
                    ]"
                  >
                    <span class="text-base">{{ m.emoji }}</span>
                    <span>{{ m.label }}</span>
                  </button>
                </div>
              </div>

              <!-- Date Picker Input -->
              <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">
                  تاريخ الملاحظة:
                </label>
                <input 
                  v-model="noteDate"
                  type="date"
                  class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-850 text-slate-900 dark:text-white text-xs font-bold outline-none focus:ring-2 focus:ring-violet-500 min-h-[44px]"
                />
              </div>

              <!-- Note Content Textarea -->
              <textarea 
                v-model="newNoteContent"
                rows="3"
                placeholder="اكتب ملاحظة أو انطباع جديد بخصوص هذه العادة..."
                class="w-full p-3.5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-850 text-slate-900 dark:text-white text-xs font-semibold outline-none focus:ring-2 focus:ring-violet-500"
              ></textarea>

              <button 
                type="submit"
                class="w-full py-3 rounded-2xl bg-violet-600 hover:bg-violet-500 text-white font-bold text-xs shadow-md transition cursor-pointer min-h-[44px] flex items-center justify-center gap-2"
              >
                <span>+ حفظ الملاحظة</span>
              </button>
            </form>

            <!-- Card-Based Journal Notes Feed -->
            <div v-if="!habit.notesList || habit.notesList.length === 0" class="text-center py-8 text-xs text-slate-400 font-semibold border-2 border-dashed border-slate-100 dark:border-slate-800/80 rounded-2xl">
              لا توجد ملاحظات مدونة بعد. شارك أفكارك وانطباعاتك اليومية!
            </div>

            <div v-else class="space-y-3 max-h-96 overflow-y-auto pr-1">
              <div 
                v-for="n in habit.notesList" 
                :key="n.id"
                class="bg-slate-50 dark:bg-slate-850 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 relative group transition hover:border-violet-300 dark:hover:border-violet-800/60"
              >
                <!-- Note Header: Date & Mood Badge -->
                <div class="flex items-center justify-between text-xs font-bold text-violet-600 dark:text-violet-400 mb-2">
                  <div class="flex items-center gap-2">
                    <!-- Mood Badge -->
                    <span v-if="parseNoteMoodAndContent(n.content).mood" class="px-2 py-0.5 rounded-lg bg-violet-100 dark:bg-violet-950 text-base">
                      {{ parseNoteMoodAndContent(n.content).mood }}
                    </span>
                    <span>🗓️ {{ n.dateStr }}</span>
                  </div>

                  <button 
                    @click="handleDeleteNote(n.id)"
                    class="min-h-[44px] min-w-[44px] flex items-center justify-center text-slate-300 hover:text-red-500 transition cursor-pointer"
                    title="حذف الملاحظة"
                  >
                    ✕
                  </button>
                </div>

                <!-- Note Content Text -->
                <p class="text-xs font-medium text-slate-800 dark:text-slate-200 whitespace-pre-wrap leading-relaxed">
                  {{ parseNoteMoodAndContent(n.content).text }}
                </p>
              </div>
            </div>

          </div>

        </div>

      </div>

    </div>

  </div>
</template>
