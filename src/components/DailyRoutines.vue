<script setup>
import { ref, computed } from 'vue'
import { store } from '../store'
import MobileBottomSheet from './MobileBottomSheet.vue'

// Date helpers
const formatDateKey = (dateObj) => {
  const y = dateObj.getFullYear()
  const m = String(dateObj.getMonth() + 1).padStart(2, '0')
  const d = String(dateObj.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

const selectedDate = ref(new Date())
const selectedDateKey = computed(() => formatDateKey(selectedDate.value))

const isToday = computed(() => {
  return selectedDateKey.value === formatDateKey(new Date())
})

const changeDate = (offsetDays) => {
  const d = new Date(selectedDate.value)
  d.setDate(d.getDate() + offsetDays)
  selectedDate.value = d
}

const resetToToday = () => {
  selectedDate.value = new Date()
}

// Arabic Date Formatting
const formattedDateDisplay = computed(() => {
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }
  return selectedDate.value.toLocaleDateString('ar-EG', options)
})

// Current Week Days (Sun - Sat)
const weekDayLabels = [
  { id: 0, label: 'الأحد', short: 'أح' },
  { id: 1, label: 'الإثنين', short: 'إث' },
  { id: 2, label: 'الثلاثاء', short: 'ثل' },
  { id: 3, label: 'الأربعاء', short: 'أر' },
  { id: 4, label: 'الخميس', short: 'خم' },
  { id: 5, label: 'الجمعة', short: 'جم' },
  { id: 6, label: 'السبت', short: 'سب' }
]

const currentWeekDays = computed(() => {
  const current = new Date(selectedDate.value)
  const dayOfWeek = current.getDay() // 0 = Sunday
  const startOfWeek = new Date(current)
  startOfWeek.setDate(current.getDate() - dayOfWeek)

  const days = []
  for (let i = 0; i < 7; i++) {
    const d = new Date(startOfWeek)
    d.setDate(startOfWeek.getDate() + i)
    const key = formatDateKey(d)

    const scheduledForDay = store.habits.filter(h => {
      if (!h.frequency || !Array.isArray(h.frequency) || h.frequency.length === 0) return true
      return h.frequency.map(Number).includes(i)
    })
    const totalScheduled = scheduledForDay.length
    const completedCount = scheduledForDay.filter(h => h.logs?.[key]?.completed).length

    let completionState = 'none'
    if (totalScheduled === 0) {
      completionState = 'empty'
    } else if (completedCount === totalScheduled) {
      completionState = 'all'
    } else if (completedCount > 0) {
      completionState = 'partial'
    }

    days.push({
      dateObj: d,
      dateKey: key,
      dayIndex: i,
      dayName: weekDayLabels[i].short,
      fullDayName: weekDayLabels[i].label,
      dayNumber: d.getDate(),
      isToday: key === formatDateKey(new Date()),
      isSelected: key === selectedDateKey.value,
      totalScheduled,
      completedCount,
      completionState
    })
  }
  return days
})

// Active day index (0 to 6)
const activeDayOfWeek = computed(() => selectedDate.value.getDay())

// Get scheduled days for a specific habit
const getHabitScheduledDays = (habit) => {
  const current = new Date(selectedDate.value)
  const dayOfWeek = current.getDay()
  const startOfWeek = new Date(current)
  startOfWeek.setDate(current.getDate() - dayOfWeek)

  const arDayNames = ['أح', 'إث', 'ثل', 'أر', 'خم', 'جم', 'سب']
  
  const freq = Array.isArray(habit.frequency) && habit.frequency.length > 0
    ? habit.frequency.map(Number)
    : [0, 1, 2, 3, 4, 5, 6]

  return freq.map(dayIdx => {
    const d = new Date(startOfWeek)
    d.setDate(startOfWeek.getDate() + dayIdx)
    const key = formatDateKey(d)
    return {
      dayIndex: dayIdx,
      dayName: arDayNames[dayIdx],
      dayNumber: d.getDate(),
      dateKey: key,
      dateObj: d,
      isToday: key === formatDateKey(new Date()),
      isSelected: key === selectedDateKey.value,
      isCompleted: !!habit.logs?.[key]?.completed
    }
  })
}

// Filter habits scheduled for selected date's day of week
const activeHabits = computed(() => {
  const dayIndex = Number(activeDayOfWeek.value)
  return store.habits.filter(h => {
    if (!h.frequency || !Array.isArray(h.frequency) || h.frequency.length === 0) {
      return true
    }
    return h.frequency.map(Number).includes(dayIndex)
  })
})

// Calculate Streak
const getHabitStreak = (habit) => {
  if (!habit || !habit.logs) return 0
  let streak = 0
  let checkDate = new Date()
  
  const todayKey = formatDateKey(checkDate)
  if (!habit.logs[todayKey]?.completed) {
    checkDate.setDate(checkDate.getDate() - 1)
  }

  while (true) {
    const key = formatDateKey(checkDate)
    if (habit.logs[key]?.completed) {
      streak++
      checkDate.setDate(checkDate.getDate() - 1)
    } else {
      break
    }
  }
  return streak
}

// Stats summary for active habits
const selectedDateStats = computed(() => {
  const total = activeHabits.value.length
  if (total === 0) return { total: 0, completed: 0, percentage: 0 }
  
  const completed = activeHabits.value.filter(h => {
    return h.logs?.[selectedDateKey.value]?.completed
  }).length

  return {
    total,
    completed,
    percentage: Math.round((completed / total) * 100)
  }
})

const totalActiveStreaks = computed(() => {
  return store.habits.reduce((acc, h) => acc + getHabitStreak(h), 0)
})

// Level / Badge title helper
const getStreakBadgeInfo = (streak) => {
  if (streak >= 30) return { title: '👑 أسطورة الاستمرار الذهبي', color: 'from-amber-400 to-yellow-600 text-white' }
  if (streak >= 14) return { title: '🥇 بطل العادات الفضي', color: 'from-violet-500 to-indigo-600 text-white' }
  if (streak >= 7) return { title: '🥈 ملتزم متألق', color: 'from-blue-400 to-cyan-500 text-white' }
  if (streak >= 3) return { title: '🥉 بداية قوية', color: 'from-emerald-400 to-teal-500 text-white' }
  return { title: '🌱 خطوة أولى نحو النجاح', color: 'from-slate-400 to-slate-600 text-white' }
}

// Confetti Particle animation trigger
const showConfetti = ref(false)
const triggerConfetti = () => {
  if (!store.shouldCelebrate()) return
  showConfetti.value = true
  setTimeout(() => {
    showConfetti.value = false
  }, 2000)
}

// Check-in action with animation & haptic feedback
const handleToggleHabit = (habit, dateKey, e) => {
  if (e) e.stopPropagation()
  const currentStatus = habit.logs?.[dateKey]?.completed
  store.toggleHabitLog(habit.id, dateKey)
  if (!currentStatus) {
    if (typeof navigator !== 'undefined' && navigator.vibrate) {
      navigator.vibrate(25)
    }
    triggerConfetti()
  }
}

const handleToggleHabitDay = (habit, day, e) => {
  if (e) e.stopPropagation()
  selectedDate.value = day.dateObj
  const currentStatus = habit.logs?.[day.dateKey]?.completed
  store.toggleHabitLog(habit.id, day.dateKey)
  if (!currentStatus) {
    if (typeof navigator !== 'undefined' && navigator.vibrate) {
      navigator.vibrate(25)
    }
    triggerConfetti()
  }
}

// Modals State
const isAddModalOpen = ref(false)
const isStatsDrawerOpen = ref(false)
const activeHabitForStats = ref(null)

// Quick Detail Bottom Sheet State
const isQuickDetailOpen = ref(false)
const quickDetailHabit = ref(null)

const openQuickDetail = (habit) => {
  quickDetailHabit.value = habit
  isQuickDetailOpen.value = true
}

const closeQuickDetail = () => {
  isQuickDetailOpen.value = false
}

// Form for Adding New Habit
const customForm = ref({
  title: '',
  category: 'صحة ورشاقة',
  icon: '⭐',
  color: 'from-violet-500 to-indigo-500',
  frequency: [0, 1, 2, 3, 4, 5, 6] // default all 7 days
})

const presetCategories = ['صحة ورشاقة', 'تطوير ذات', 'هدوء وروحانيات', 'عمل وإنتاجية', 'عادات شخصية']
const presetEmojis = ['💧', '📖', '🤲', '🏃‍♂️', '🧘‍♂️', '💊', '💻', '🏋️‍♂️', '🍎', '🎨', '✍️', '😴', '⭐', '🔥']
const presetColors = [
  { label: 'بنفسجي', value: 'from-violet-500 to-indigo-500' },
  { label: 'أزرق', value: 'from-blue-500 to-cyan-500' },
  { label: 'زمردي', value: 'from-emerald-500 to-teal-500' },
  { label: 'برتقالي', value: 'from-amber-500 to-orange-500' },
  { label: 'وردي', value: 'from-pink-500 to-rose-500' },
  { label: 'أحمر', value: 'from-red-500 to-amber-600' }
]

const toggleFormDay = (dayIdx) => {
  const arr = [...customForm.value.frequency]
  const idx = arr.indexOf(dayIdx)
  if (idx > -1) {
    if (arr.length > 1) {
      arr.splice(idx, 1)
    }
  } else {
    arr.push(dayIdx)
  }
  customForm.value.frequency = arr
}

const submitNewHabit = () => {
  const titleStr = customForm.value.title ? customForm.value.title.trim() : ''
  if (!titleStr) return

  store.addHabit({
    title: titleStr,
    category: customForm.value.category || 'عادات شخصية',
    icon: customForm.value.icon || '⭐',
    color: customForm.value.color || 'from-violet-500 to-indigo-500',
    frequency: [...customForm.value.frequency]
  })

  // Reset form
  customForm.value = {
    title: '',
    category: 'صحة ورشاقة',
    icon: '⭐',
    color: 'from-violet-500 to-indigo-500',
    frequency: [0, 1, 2, 3, 4, 5, 6]
  }

  isAddModalOpen.value = false
  triggerConfetti()
}

const openHabitDetail = (habit, e) => {
  if (e) e.stopPropagation()
  window.location.hash = `#routines/habit-${habit.id}`
}

const openStatsDrawer = (habit, e) => {
  if (e) e.stopPropagation()
  activeHabitForStats.value = habit
  isStatsDrawerOpen.value = true
}

const closeStatsDrawer = () => {
  isStatsDrawerOpen.value = false
}

const confirmDeleteHabit = (habit, e) => {
  if (e) e.stopPropagation()
  if (confirm(`هل أنت تأكد من حذف عادة "${habit.title}"؟`)) {
    store.deleteHabit(habit.id)
    if (activeHabitForStats.value?.id === habit.id) {
      isStatsDrawerOpen.value = false
    }
    if (quickDetailHabit.value?.id === habit.id) {
      isQuickDetailOpen.value = false
    }
  }
}

// Month stats for selected habit modal
const currentMonthDays = computed(() => {
  if (!activeHabitForStats.value) return []
  const now = new Date()
  const year = now.getFullYear()
  const month = now.getMonth()
  const daysInMonth = new Date(year, month + 1, 0).getDate()

  const list = []
  for (let i = 1; i <= daysInMonth; i++) {
    const d = new Date(year, month, i)
    const key = formatDateKey(d)
    const log = activeHabitForStats.value.logs?.[key]
    list.push({
      dayNum: i,
      dateKey: key,
      completed: !!log?.completed
    })
  }
  return list
})

const activeHabitCompletionPercentage = computed(() => {
  const days = currentMonthDays.value
  if (!days.length) return 0
  const completedCount = days.filter(d => d.completed).length
  return Math.round((completedCount / days.length) * 100)
})

// Navigation Tab State (العادات vs اليوميات)
const activeTab = ref('habits') // 'habits' | 'journal'

// Daily Quick Tasks State & Helpers
const newDailyTaskTitle = ref('')
const newDailyTaskCategory = ref('عام')
const newDailyTaskPriority = ref('متوسطة')
const newDailyTaskTime = ref('')

const dailyTaskStatusFilter = ref('all') // 'all' | 'pending' | 'completed'
const dailyTaskCategoryFilter = ref('all')
const dailyTaskSearchQuery = ref('')

const dailyTaskCategories = computed(() => store.dailyTaskCategories || ['عام', 'عمل', 'شخصي', 'صحة', 'دراسة', 'عاجل'])

// Category Management Modal State
const isCategoryManageModalOpen = ref(false)
const newCategoryInput = ref('')

const handleAddCategory = () => {
  const name = newCategoryInput.value.trim()
  if (!name) return
  const added = store.addDailyTaskCategory(name)
  if (added) {
    newDailyTaskCategory.value = name
    newCategoryInput.value = ''
    triggerConfetti()
  } else {
    alert('التصنيف موجود بالفعل!')
  }
}

const handleDeleteCategory = (cat) => {
  if (cat === 'عام') {
    alert('لا يمكن حذف التصنيف الافتراضي (عام)!')
    return
  }
  if (confirm(`هل أنت تأكد من حذف التصنيف "${cat}"؟`)) {
    store.deleteDailyTaskCategory(cat)
  }
}


const filteredDailyTasks = computed(() => {
  // The journal sits under a day navigator, so it must be scoped to the
  // selected day. Without this every date showed the same list forever.
  let list = (store.dailyTasks || []).filter(t => {
    const d = t.dueDate || t.due_date
    return d ? String(d).slice(0, 10) === selectedDateKey.value : true
  })
  
  if (dailyTaskStatusFilter.value === 'pending') {
    list = list.filter(t => !t.completed)
  } else if (dailyTaskStatusFilter.value === 'completed') {
    list = list.filter(t => t.completed)
  }

  if (dailyTaskCategoryFilter.value !== 'all') {
    list = list.filter(t => t.category === dailyTaskCategoryFilter.value)
  }

  if (dailyTaskSearchQuery.value.trim()) {
    const q = dailyTaskSearchQuery.value.trim().toLowerCase()
    list = list.filter(t => t.title.toLowerCase().includes(q))
  }

  return list
})

const dailyTasksStats = computed(() => {
  const list = (store.dailyTasks || []).filter(t => {
    const d = t.dueDate || t.due_date
    return d ? String(d).slice(0, 10) === selectedDateKey.value : true
  })
  const total = list.length
  if (total === 0) return { total: 0, completed: 0, percentage: 0 }
  const completed = list.filter(t => t.completed).length
  return {
    total,
    completed,
    percentage: Math.round((completed / total) * 100)
  }
})

const handleCreateDailyTask = () => {
  const title = newDailyTaskTitle.value.trim()
  if (!title) return

  store.addDailyTask({
    title,
    category: newDailyTaskCategory.value || 'عام',
    priority: newDailyTaskPriority.value || 'متوسطة',
    dueDate: selectedDateKey.value,
    dueTime: newDailyTaskTime.value || ''
  })

  newDailyTaskTitle.value = ''
  newDailyTaskTime.value = ''
  triggerConfetti()
}

const handleToggleDailyTask = (id) => {
  const task = (store.dailyTasks || []).find(t => t.id === id)
  const isCompleting = task && !task.completed
  store.toggleDailyTask(id)
  if (isCompleting) {
    triggerConfetti()
  }
}

const handleDeleteDailyTask = (id) => {
  store.deleteDailyTask(id)
}

// Touch/Swipe Gesture Handlers with 50px threshold & RTL direction support
const touchStartX = ref(null)
const touchStartY = ref(null)
const touchEndX = ref(null)
const touchEndY = ref(null)
const isTouchOriginExcluded = ref(false)

const isExcludedTouchTarget = (target) => {
  if (isAddModalOpen.value || isStatsDrawerOpen.value || isQuickDetailOpen.value) {
    return true
  }
  if (!target) return false
  if (typeof target.closest === 'function') {
    return !!target.closest('.overflow-x-auto, [role="dialog"], .mobile-bottom-sheet')
  }
  return false
}

const onTouchStart = (e) => {
  if (isExcludedTouchTarget(e.target)) {
    isTouchOriginExcluded.value = true
    touchStartX.value = null
    return
  }
  isTouchOriginExcluded.value = false
  const touch = e.touches?.[0] || e.changedTouches?.[0]
  if (touch) {
    touchStartX.value = touch.clientX
    touchStartY.value = touch.clientY
    touchEndX.value = touch.clientX
    touchEndY.value = touch.clientY
  }
}

const onTouchMove = (e) => {
  if (isTouchOriginExcluded.value || touchStartX.value === null) return
  if (isExcludedTouchTarget(e.target)) return
  const touch = e.touches?.[0] || e.changedTouches?.[0]
  if (touch) {
    touchEndX.value = touch.clientX
    touchEndY.value = touch.clientY
  }
}

const onTouchEnd = (e) => {
  if (isTouchOriginExcluded.value || touchStartX.value === null) {
    touchStartX.value = null
    touchStartY.value = null
    touchEndX.value = null
    touchEndY.value = null
    isTouchOriginExcluded.value = false
    return
  }
  if (isExcludedTouchTarget(e.target)) {
    touchStartX.value = null
    touchStartY.value = null
    touchEndX.value = null
    touchEndY.value = null
    isTouchOriginExcluded.value = false
    return
  }

  const touch = e.changedTouches?.[0] || e.touches?.[0]
  if (touch) {
    touchEndX.value = touch.clientX
    touchEndY.value = touch.clientY
  }

  const deltaX = touchEndX.value - (touchStartX.value || 0)
  const deltaY = touchEndY.value - (touchStartY.value || 0)

  if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 50) {
    if (deltaX < -50 && activeTab.value === 'journal') {
      activeTab.value = 'habits'
    } else if (deltaX > 50 && activeTab.value === 'habits') {
      activeTab.value = 'journal'
    }
  }

  touchStartX.value = null
  touchStartY.value = null
  touchEndX.value = null
  touchEndY.value = null
  isTouchOriginExcluded.value = false
}

// Mobile Daily Notes Inline Journal Editing State & Actions
const dailyNoteInput = ref('')
const isSavingNote = ref(false)
const noteError = ref('')

// Notes are persisted server-side now. They used to be written into an
// undeclared store property that was never saved anywhere, so every note
// disappeared on refresh.
const savedDailyNotes = computed(() => store.notesForDate(selectedDateKey.value))

const noteTime = (createdAt) => {
  if (!createdAt) return ''
  try {
    return new Intl.DateTimeFormat('ar-EG', { hour: '2-digit', minute: '2-digit' })
      .format(new Date(createdAt))
  } catch (e) {
    return ''
  }
}

const handleSaveDailyNote = async () => {
  const text = dailyNoteInput.value.trim()
  if (!text || isSavingNote.value) return

  isSavingNote.value = true
  noteError.value = ''

  const created = await store.addDailyNote(selectedDateKey.value, text)
  if (created) {
    dailyNoteInput.value = ''
    triggerConfetti()
  } else {
    noteError.value = 'تعذّر حفظ الملاحظة. تحقق من الاتصال وحاول مرة أخرى.'
  }
  isSavingNote.value = false
}

const handleDeleteDailyNote = (noteId) => {
  store.deleteDailyNote(noteId)
}
</script>

<template>
  <div class="w-full max-w-6xl mx-auto px-1 sm:px-3 py-1 relative overflow-x-hidden" @touchstart="onTouchStart" @touchmove="onTouchMove" @touchend="onTouchEnd">
    
    <!-- Animated Confetti Overlay -->
    <div v-if="showConfetti" class="fixed inset-0 pointer-events-none z-50 overflow-hidden">
      <div v-for="n in 30" :key="n" 
           class="absolute animate-fall rounded-full opacity-80"
           :style="{
             left: `${Math.random() * 100}%`,
             top: `-10px`,
             width: `${Math.random() * 10 + 6}px`,
             height: `${Math.random() * 10 + 6}px`,
             backgroundColor: ['#8b5cf6', '#ec4899', '#3b82f6', '#10b981', '#f59e0b'][n % 5],
             animationDuration: `${Math.random() * 2 + 1.5}s`,
             animationDelay: `${Math.random() * 0.5}s`
           }">
      </div>
    </div>

    <!-- Main Top Sticky Segmented Control (العادات vs اليوميات) with Glassmorphism & Active Sliding Pill Indicator -->
    <div class="sticky top-0 z-30 mb-2 sm:mb-4 bg-white/80 dark:bg-slate-900/80 p-1 sm:p-2 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-md backdrop-blur-xl glass-header">
      <div class="relative flex items-center justify-between gap-1.5 py-1 px-1.5 bg-slate-100/90 dark:bg-slate-800/90 rounded-xl">
        <!-- Active Sliding Pill Indicator -->
        <div
          class="absolute top-1 bottom-1 w-[calc(50%-4px)] rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 shadow-md shadow-violet-600/30 transition-all duration-300 ease-out"
          :class="activeTab === 'habits' ? 'right-1' : 'right-[calc(50%+2px)]'"
        ></div>

        <button
          @click="activeTab = 'habits'"
          :class="[
            'relative z-10 flex-1 px-3 py-1.5 rounded-xl font-extrabold text-xs sm:text-sm transition-all duration-200 flex items-center justify-center gap-1.5 min-h-[44px] sm:min-h-[44px] min-w-[44px] sm:min-w-[44px] cursor-pointer',
            activeTab === 'habits'
              ? 'text-white'
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
          ]"
        >
          <span>⚡ العادات اليومية</span>
          <span :class="[
            'px-2 py-0.5 rounded-full text-[10px] sm:text-[11px] font-bold transition-colors',
            activeTab === 'habits' ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300'
          ]">
            {{ activeHabits.length }}
          </span>
        </button>

        <button
          @click="activeTab = 'journal'"
          :class="[
            'relative z-10 flex-1 px-3 py-1.5 rounded-xl font-extrabold text-xs sm:text-sm transition-all duration-200 flex items-center justify-center gap-1.5 min-h-[44px] sm:min-h-[44px] min-w-[44px] sm:min-w-[44px] cursor-pointer',
            activeTab === 'journal'
              ? 'text-white'
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
          ]"
        >
          <span>📝 اليوميات (تاسكات سريعة)</span>
          <span :class="[
            'px-2 py-0.5 rounded-full text-[10px] sm:text-[11px] font-bold transition-colors',
            activeTab === 'journal' ? 'bg-white/20 text-white' : 'bg-violet-500/15 text-violet-600 dark:text-violet-300'
          ]">
            {{ store.dailyTasks ? store.dailyTasks.length : 0 }}
          </span>
        </button>
      </div>
    </div>

    <!-- Mobile Sticky Compact Progress Gauge & Streak Bar (<768px) -->
    <div class="block md:hidden sticky top-[52px] z-20 mb-2 sm:mb-4 p-2.5 sm:p-4 rounded-xl sm:rounded-2xl bg-white/90 dark:bg-slate-900/90 backdrop-blur-md border border-slate-200/80 dark:border-slate-800 shadow-md">
      <div class="flex items-center justify-between gap-2 text-xs font-bold">
        <div class="flex items-center gap-1 px-2 py-0.5 rounded-lg bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/20 shrink-0 text-[11px]">
          <span class="animate-pulse">🔥</span>
          <span>{{ totalActiveStreaks }} يوم streak</span>
        </div>
        <div class="flex-1 min-w-0 flex items-center gap-2">
          <div class="w-full h-1.5 sm:h-2 rounded-full bg-slate-200 dark:bg-slate-700/80 overflow-hidden">
            <div
              class="h-full bg-gradient-to-r from-violet-500 via-indigo-500 to-emerald-400 rounded-full transition-all duration-500"
              :style="{ width: `${activeTab === 'habits' ? selectedDateStats.percentage : dailyTasksStats.percentage}%` }"
            ></div>
          </div>
          <span class="text-[10px] font-black text-violet-600 dark:text-violet-400 shrink-0">
            {{ activeTab === 'habits' ? `${selectedDateStats.completed}/${selectedDateStats.total}` : `${dailyTasksStats.completed}/${dailyTasksStats.total}` }} ({{ activeTab === 'habits' ? selectedDateStats.percentage : dailyTasksStats.percentage }}%)
          </span>
        </div>
      </div>
    </div>

    <!-- Habits Tab View -->
    <div v-if="activeTab === 'habits'">
      <!-- Top Glass Header Summary -->
      <div class="relative bg-white/70 dark:bg-slate-900/70 backdrop-blur-2xl border border-white/40 dark:border-slate-800/60 shadow-xl rounded-2xl sm:rounded-3xl p-2.5 sm:p-4 overflow-hidden mb-2 sm:mb-4">

      <!-- Ambient Radial Glow Mesh -->
      <div class="absolute -top-24 -right-24 w-96 h-96 bg-gradient-to-tr from-violet-500/10 via-indigo-500/10 to-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-gradient-to-bl from-indigo-500/10 via-teal-500/10 to-violet-500/10 rounded-full blur-3xl pointer-events-none"></div>

      <div class="relative z-10 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-6">
        
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-amber-500/15 text-amber-600 dark:text-amber-300 border border-amber-500/30 flex items-center gap-1 shadow-sm animate-pulse">
              🔥 {{ totalActiveStreaks }} يوم مجموع السلاسل
            </span>
          </div>
          <h2 class="text-lg sm:text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
            يومياتي والعادات
          </h2>
          <p class="text-[11px] sm:text-sm text-slate-600 dark:text-slate-300 mt-0.5 font-semibold leading-tight">
            سجل إنجازك اليومي، أو اضغط على أي عادة لملاحظة المعاينة والإحصائيات ✨
          </p>
        </div>

        <button 
          @click="isAddModalOpen = true"
          class="w-full sm:w-auto px-4 py-2 sm:px-6 sm:py-3 rounded-xl sm:rounded-2xl bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-black text-xs sm:text-sm transition-all cursor-pointer flex items-center justify-center gap-1.5 shadow-lg shadow-violet-600/25 hover:scale-105 active:scale-95 min-h-[44px] sm:min-h-[44px]"
        >
          <span class="text-sm sm:text-base font-black">+</span>
          <span>إضافة عادة</span>
        </button>

      </div>

      <!-- Date Navigator & Integrated Progress Gauge -->
      <div class="relative z-10 mt-3 sm:mt-8 pt-3 sm:pt-6 border-t border-slate-200/80 dark:border-slate-800/80 flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-4">
        
        <!-- Date Selector Stepper -->
        <div class="flex items-center gap-1 sm:gap-2 bg-slate-100/80 dark:bg-slate-800/60 p-1 sm:p-1.5 rounded-xl sm:rounded-2xl border border-slate-200/80 dark:border-slate-700/50 w-full sm:w-auto justify-between shadow-inner">
          <button 
            @click="changeDate(-1)" 
            class="p-1.5 sm:p-2 rounded-lg sm:rounded-xl text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-200/60 dark:hover:bg-slate-700/50 transition cursor-pointer min-h-[44px] min-w-[44px] sm:min-h-[44px] sm:min-w-[44px] flex items-center justify-center shrink-0" 
            title="اليوم السابق" aria-label="اليوم السابق"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transform rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
          </button>

          <div class="text-center px-2 min-w-0 flex-1">
            <span class="text-xs sm:text-sm font-extrabold text-slate-900 dark:text-white block truncate">{{ formattedDateDisplay }}</span>
            <span v-if="isToday" class="text-[10px] font-bold text-violet-600 dark:text-violet-400 block -mt-0.5">اليوم الحاضر 🎯</span>
          </div>

          <button 
            @click="changeDate(1)" 
            class="p-1.5 sm:p-2 rounded-lg sm:rounded-xl text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-200/60 dark:hover:bg-slate-700/50 transition cursor-pointer min-h-[44px] min-w-[44px] sm:min-h-[44px] sm:min-w-[44px] flex items-center justify-center shrink-0" 
            title="اليوم التالي" aria-label="اليوم التالي"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
          </button>

          <button 
            v-if="!isToday" 
            @click="resetToToday" 
            class="mr-1 px-2 py-1 rounded-lg sm:rounded-xl text-[11px] sm:text-xs font-bold bg-violet-600 text-white hover:bg-violet-500 transition cursor-pointer min-h-[44px] sm:min-h-[44px] flex items-center shrink-0 shadow-sm"
          >
            العودة
          </button>
        </div>

        <!-- Integrated Daily Progress Gauge Bar (Desktop only, mobile uses top sticky gauge) -->
        <div class="hidden md:block w-full sm:w-72 bg-slate-100/80 dark:bg-slate-800/50 p-2.5 sm:p-3.5 rounded-xl sm:rounded-2xl border border-slate-200/80 dark:border-slate-700/50 shadow-inner">
          <div class="flex justify-between items-center text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            <span>إنجاز اليوم</span>
            <span class="text-violet-600 dark:text-violet-400 font-black">{{ selectedDateStats.completed }} من {{ selectedDateStats.total }} ({{ selectedDateStats.percentage }}%)</span>
          </div>
          <div class="w-full h-1.5 sm:h-2 rounded-full bg-slate-200 dark:bg-slate-700/80 overflow-hidden p-0.5">
            <div 
              class="h-full bg-gradient-to-r from-violet-500 via-indigo-500 to-emerald-400 transition-all duration-700 rounded-full shadow-sm"
              :style="{ width: `${selectedDateStats.percentage}%` }"
            ></div>
          </div>
        </div>

      </div>
    </div>

    <!-- Weekly Day Picker Bar (Sunday to Saturday) -->
    <div class="mb-2 sm:mb-4 p-1.5 sm:p-3 bg-white/70 dark:bg-slate-900/70 backdrop-blur-2xl rounded-2xl sm:rounded-3xl border border-white/40 dark:border-slate-800/60 shadow-xl">
      <div class="grid grid-cols-7 gap-1 sm:gap-2">
        <button 
          v-for="day in currentWeekDays" 
          :key="day.dateKey"
          @click="selectedDate = day.dateObj"
          :class="[
            'relative flex flex-col items-center justify-center py-1 sm:py-3 px-0.5 rounded-xl sm:rounded-2xl transition-all duration-300 cursor-pointer min-h-[44px] sm:min-h-[64px] w-full min-w-0',
            day.isSelected
              ? 'bg-gradient-to-r from-violet-600 to-indigo-600 text-white shadow-lg shadow-violet-500/25 scale-105 ring-2 ring-violet-400/30'
              : 'bg-white/70 dark:bg-slate-800/50 hover:bg-white dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700/50'
          ]"
        >
          <!-- Day Name -->
          <span :class="['text-[10px] sm:text-xs font-extrabold truncate', day.isSelected ? 'text-violet-100' : 'text-slate-500 dark:text-slate-400']">
            {{ day.dayName }}
          </span>

          <!-- Date Number -->
          <span :class="['text-xs sm:text-base font-black font-mono my-0.5', day.isSelected ? 'text-white' : 'text-slate-900 dark:text-white']">
            {{ day.dayNumber }}
          </span>

          <!-- Micro Completion Status Dot per Day -->
          <div class="flex items-center gap-1 mt-0.5">
            <span 
              v-if="day.completionState === 'all'" 
              :class="[
                'w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full transition-transform',
                day.isSelected ? 'bg-emerald-300 ring-2 ring-white/40' : 'bg-emerald-500 shadow-sm shadow-emerald-500/50'
              ]"
              title="جميع العادات مكتملة"
            ></span>
            <span 
              v-else-if="day.completionState === 'partial'" 
              :class="[
                'w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full transition-transform',
                day.isSelected ? 'bg-amber-300 ring-2 ring-white/40' : 'bg-amber-400 shadow-sm shadow-amber-400/50'
              ]"
              title="مكتمل جزئياً"
            ></span>
            <span 
              v-else 
              :class="[
                'w-1 h-1 sm:w-1.5 sm:h-1.5 rounded-full opacity-40',
                day.isSelected ? 'bg-white/60' : 'bg-slate-300 dark:bg-slate-600'
              ]"
            ></span>

            <span v-if="day.isToday && !day.isSelected" class="w-1 h-1 sm:w-1.5 sm:h-1.5 rounded-full bg-violet-500 animate-ping"></span>
          </div>
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-if="activeHabits.length === 0" class="text-center py-12 sm:py-16 bg-white/80 dark:bg-slate-900/80 rounded-2xl sm:rounded-3xl border border-dashed border-slate-300 dark:border-slate-800 p-6 sm:p-8 shadow-sm">
      <div class="w-12 h-12 sm:w-16 sm:h-16 rounded-full bg-violet-100 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400 flex items-center justify-center mx-auto mb-3 sm:mb-4 text-2xl sm:text-3xl">
        🌱
      </div>
      <h3 class="text-base sm:text-lg font-bold text-slate-900 dark:text-slate-100">لا توجد عادات مخصصة لهذا اليوم</h3>
      <p class="text-xs sm:text-sm text-slate-400 mt-1 max-w-sm mx-auto">قم بإضافة عادتك الأولى واختيار أيام تكرارها البسيطة</p>
      <button 
        @click="isAddModalOpen = true"
        class="mt-4 sm:mt-5 px-5 py-2 sm:px-6 sm:py-2.5 rounded-xl bg-violet-600 text-white font-bold text-xs sm:text-sm shadow-md hover:bg-violet-500 transition cursor-pointer min-h-[44px] sm:min-h-[44px]"
      >
        + إضافة عادة جديدة
      </button>
    </div>

    <!-- Streamlined Modern Routine Cards List (High-Density Compact Layout) -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-2">
      
      <div 
        v-for="habit in activeHabits" 
        :key="habit.id"
        @click="openQuickDetail(habit)"
        :class="[
          'group relative rounded-2xl sm:rounded-3xl p-2.5 border transition-all duration-300 shadow-sm hover:shadow-xl cursor-pointer overflow-hidden flex flex-col justify-between gap-2',
          habit.logs?.[selectedDateKey]?.completed 
            ? 'border-emerald-500/50 bg-gradient-to-r from-emerald-50/40 to-teal-50/20 dark:from-emerald-950/20 dark:to-teal-950/10 shadow-emerald-500/5' 
            : 'border-slate-200/80 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 hover:border-violet-400 dark:hover:border-violet-600'
        ]"
      >
        <!-- Row 1: Primary Habit Details & Sleek 32-36px Check-in Toggle -->
        <div class="flex items-center justify-between gap-2">
          
          <div class="flex items-center gap-2 min-w-0 flex-1">
            <!-- Icon Badge -->
            <div 
              :class="[
                'w-9 h-9 rounded-xl flex items-center justify-center text-lg shadow-md bg-gradient-to-tr text-white transition-transform duration-300 group-hover:scale-110 shrink-0',
                habit.color || 'from-violet-500 to-indigo-500'
              ]"
            >
              {{ habit.icon || '⭐' }}
            </div>

            <!-- Title, Category & Streak Badges -->
            <div class="min-w-0 flex-1">
              <h4 class="text-xs sm:text-sm font-extrabold text-slate-900 dark:text-slate-50 truncate flex items-center gap-1.5 leading-tight">
                {{ habit.title }}
              </h4>

              <div class="flex items-center gap-1 flex-wrap mt-0.5">
                <!-- Category Badge -->
                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200/80 dark:border-slate-700/60 shrink-0 leading-tight">
                  {{ habit.category || 'عادات يومية' }}
                </span>

                <!-- Streak Counter Badge -->
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 flex items-center gap-0.5 shrink-0 leading-tight">
                  🔥 {{ getHabitStreak(habit) }} يوم
                </span>
              </div>
            </div>
          </div>

          <!-- Sleek 32-36px Check-in Toggle Button -->
          <button 
            @click="(e) => handleToggleHabit(habit, selectedDateKey, e)"
            :class="[
              'w-9 h-9 min-h-[44px] min-w-[44px] rounded-xl flex items-center justify-center text-base font-black transition-all duration-300 shadow-md cursor-pointer shrink-0 transform active:scale-90 hover:scale-105',
              habit.logs?.[selectedDateKey]?.completed
                ? 'bg-gradient-to-tr from-emerald-500 to-teal-500 text-white shadow-lg shadow-emerald-500/30 scale-105 ring-2 ring-emerald-400/40'
                : 'bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 hover:bg-violet-600 hover:text-white hover:shadow-lg hover:shadow-violet-600/30 border border-slate-200 dark:border-slate-700'
            ]"
            title="تسجيل الإنجاز"
          >
            <span v-if="habit.logs?.[selectedDateKey]?.completed" class="animate-pulse">✓</span>
            <span v-else class="text-base font-black text-slate-400 group-hover:text-white">+</span>
          </button>

        </div>

        <!-- Row 2: Scheduled Days Overview & Secondary Action Controls -->
        <div class="pt-1.5 sm:pt-2.5 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between gap-1.5">
          
          <!-- Scheduled Days Micro Dots / Pills Strip -->
          <div class="flex items-center gap-1 overflow-x-auto scrollbar-hide py-0.5">
            <span class="text-[10px] sm:text-[11px] font-bold text-slate-400 ml-0.5 shrink-0">الأيام:</span>
            <button 
              v-for="day in getHabitScheduledDays(habit)" 
              :key="day.dateKey"
              @click="(e) => handleToggleHabitDay(habit, day, e)"
              :class="[
                'px-1.5 py-1 sm:px-2.5 sm:py-1.5 rounded-lg sm:rounded-xl flex items-center justify-center gap-0.5 text-[10px] sm:text-[11px] font-extrabold transition-all cursor-pointer border min-h-[44px] min-w-[44px] sm:min-h-[44px] sm:min-w-[44px] shrink-0',
                day.isCompleted
                  ? 'bg-emerald-500 text-white border-emerald-400 shadow-sm'
                  : day.isSelected
                    ? 'bg-violet-100 dark:bg-violet-900/40 text-violet-600 dark:text-violet-300 border-violet-400'
                    : 'bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700/80 hover:border-slate-400'
              ]"
              :title="`${day.dayName} (${day.dayNumber}) - ${day.isCompleted ? 'مكتمل' : 'انقر للتسجيل'}`"
            >
              <span>{{ day.dayName }}</span>
              <span v-if="day.isCompleted" class="text-[10px] sm:text-[10px]">✓</span>
            </button>
          </div>

          <!-- Secondary Actions (Full Detail, Stats Drawer & Delete Button) -->
          <div class="flex items-center gap-0.5 shrink-0">
            <!-- Expand into full #routines/habit-[ID] detail view -->
            <button 
              @click="(e) => openHabitDetail(habit, e)"
              class="min-h-[44px] min-w-[44px] sm:min-h-[44px] sm:min-w-[44px] px-1.5 py-1 sm:px-2.5 sm:py-1.5 rounded-lg sm:rounded-xl text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-violet-600 dark:hover:text-violet-300 hover:bg-violet-50 dark:hover:bg-slate-800 transition cursor-pointer flex items-center justify-center"
              title="فتح التفاصيل الكاملة"
            >
              ↗
            </button>

            <!-- Stats Drawer Trigger -->
            <button 
              @click="(e) => openStatsDrawer(habit, e)"
              class="min-h-[44px] min-w-[44px] sm:min-h-[44px] sm:min-w-[44px] px-1.5 py-1 sm:px-2.5 sm:py-1.5 rounded-lg sm:rounded-xl text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-violet-600 dark:hover:text-violet-300 hover:bg-violet-50 dark:hover:bg-slate-800 transition cursor-pointer flex items-center justify-center gap-0.5"
              title="عرض تقويم وإحصائيات العادة"
            >
              <span>📊</span>
            </button>

            <!-- Delete Button -->
            <button 
              @click="(e) => confirmDeleteHabit(habit, e)"
              class="min-h-[44px] min-w-[44px] sm:min-h-[44px] sm:min-w-[44px] p-1.5 sm:p-2.5 rounded-lg sm:rounded-xl text-slate-300 hover:text-red-500 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-slate-800 transition cursor-pointer flex items-center justify-center"
              title="حذف العادة"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>

        </div>

      </div>
    </div>
    </div>
    <!-- End Habits Tab View -->

    <!-- Daily Tasks / Journal Tab View -->
    <div v-if="activeTab === 'journal'" class="space-y-4 sm:space-y-6">
      
      <!-- Top Glass Card for Quick Entry & Progress -->
      <div class="relative bg-white/70 dark:bg-slate-900/70 backdrop-blur-2xl border border-white/40 dark:border-slate-800/60 shadow-xl rounded-2xl sm:rounded-3xl p-3 sm:p-5 md:p-8 overflow-hidden">
        
        <!-- Header Info & Progress Bar -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-6 mb-4 sm:mb-6 pb-4 sm:pb-6 border-b border-slate-200/80 dark:border-slate-800">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-violet-500/15 text-violet-600 dark:text-violet-300 border border-violet-500/30">
                📌 مفكرة المهام اليومية السريعة
              </span>
            </div>
            <h2 class="text-lg sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
              سجل اليوميات والتاسكات السريعة
            </h2>
            <p class="text-[11px] sm:text-sm text-slate-600 dark:text-slate-400 mt-0.5 leading-tight">
              أضف مهامك اليومية الخفيفة، صنفها حسب المجال، وتابع نسبة إنجازك اليومي 🚀
            </p>
          </div>

          <!-- Progress Gauge -->
          <div class="w-full sm:w-72 bg-slate-100/80 dark:bg-slate-800/50 p-2.5 sm:p-4 rounded-xl sm:rounded-2xl border border-slate-200/80 dark:border-slate-700/50 shadow-inner">
            <div class="flex justify-between items-center text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              <span>تقدم اليوميات</span>
              <span class="text-violet-600 dark:text-violet-400 font-black">
                {{ dailyTasksStats.completed }} / {{ dailyTasksStats.total }} ({{ dailyTasksStats.percentage }}%)
              </span>
            </div>
            <div class="w-full h-1.5 sm:h-3.5 rounded-full bg-slate-200 dark:bg-slate-700/80 overflow-hidden p-0.5">
              <div
                class="h-full bg-gradient-to-r from-violet-500 via-indigo-500 to-emerald-400 transition-all duration-700 rounded-full shadow-sm"
                :style="{ width: `${dailyTasksStats.percentage}%` }"
              ></div>
            </div>
          </div>
        </div>

        <!-- Quick Task Entry Form -->
        <form @submit.prevent="handleCreateDailyTask" class="space-y-3 sm:space-y-4">
          <div class="flex flex-col md:flex-row gap-2.5 sm:gap-3">
            <!-- Task Title Input -->
            <div class="flex-1 relative">
              <input
                v-model="newDailyTaskTitle"
                type="text"
                placeholder="أضف مهمة جديدة لسجل يومياتك اليوم... (مثلاً: الاتصال بالعميل، مراجعة ملف الميزانية)"
                class="w-full px-3.5 py-2.5 sm:px-4 sm:py-3.5 rounded-xl sm:rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 text-xs sm:text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-violet-500 transition min-h-[44px] sm:min-h-[48px]"
                required
              />
            </div>

            <!-- Options Row: Category, Priority, Due Time, Submit -->
            <div class="flex flex-wrap sm:flex-nowrap items-center gap-1.5 sm:gap-2">
              <!-- Category Selector -->
              <select
                v-model="newDailyTaskCategory"
                class="px-2.5 py-2 sm:px-3 sm:py-3 rounded-lg sm:rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-violet-500 cursor-pointer min-h-[44px] sm:min-h-[48px]"
              >
                <option v-for="cat in dailyTaskCategories" :key="cat" :value="cat">🏷️ {{ cat }}</option>
              </select>

              <!-- Priority Selector -->
              <select
                v-model="newDailyTaskPriority"
                class="px-2.5 py-2 sm:px-3 sm:py-3 rounded-lg sm:rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-violet-500 cursor-pointer min-h-[44px] sm:min-h-[48px]"
              >
                <option value="منخفضة">🟢 منخفضة</option>
                <option value="متوسطة">🟡 متوسطة</option>
                <option value="عالية">🔴 عالية</option>
              </select>

              <!-- Optional Time Input -->
              <input
                v-model="newDailyTaskTime"
                type="time"
                class="px-2.5 py-2 sm:px-3 sm:py-3 rounded-lg sm:rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-violet-500 cursor-pointer min-h-[44px] sm:min-h-[48px]"
                title="تحديد موعد اختياري"
              />

              <!-- Submit Button -->
              <button
                type="submit"
                class="px-4 py-2 sm:px-6 sm:py-3 rounded-lg sm:rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-black text-xs transition-all cursor-pointer flex items-center justify-center gap-1 shadow-md shadow-violet-600/20 active:scale-95 min-h-[44px] sm:min-h-[48px] shrink-0"
              >
                <span class="text-sm sm:text-base font-bold">+</span>
                <span>إضافة</span>
              </button>
            </div>
          </div>
        </form>
      </div>

      <!-- Daily Notes Inline Journal Editor (R2 Mobile Ergonomics & Daily Notes) -->
      <div class="relative bg-white/70 dark:bg-slate-900/70 backdrop-blur-2xl border border-white/40 dark:border-slate-800/60 shadow-xl rounded-2xl sm:rounded-3xl p-3 sm:p-5 md:p-6 overflow-hidden">
        <div class="flex items-center justify-between mb-2.5 sm:mb-3">
          <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-white flex items-center gap-1.5 sm:gap-2">
            <span>📖</span>
            <span>ملاحظات اليوميات الخفيفة</span>
          </h3>
          <span class="text-xs font-bold text-violet-600 dark:text-violet-400">
            {{ savedDailyNotes.length }} ملاحظات
          </span>
        </div>

        <form @submit.prevent="handleSaveDailyNote" class="space-y-2.5 sm:space-y-3">
          <div class="relative">
            <textarea
              v-model="dailyNoteInput"
              placeholder="اكتب ملاحظة أو خاطر سريع لسجل يومك اليوم..."
              class="w-full px-3.5 py-2.5 sm:px-4 sm:py-3 rounded-xl sm:rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 text-xs sm:text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-violet-500 transition min-h-[70px] sm:min-h-[80px] resize-none"
              required
            ></textarea>
          </div>
          
          <div class="flex items-center justify-end">
            <button
              type="submit"
              class="glass-fab-mobile px-4 py-2 sm:px-6 sm:py-2.5 rounded-xl text-white font-black text-xs cursor-pointer flex items-center justify-center gap-1.5 min-h-[44px] sm:min-h-[44px] min-w-[44px] sm:min-w-[44px]"
            >
              <span>💬 حفظ الملاحظة</span>
            </button>
          </div>
        </form>

        <!-- Saved Notes List -->
        <div v-if="savedDailyNotes.length > 0" class="mt-3 space-y-2 pt-3 border-t border-slate-200/60 dark:border-slate-800">
          <div
            v-for="note in savedDailyNotes"
            :key="note.id"
            class="p-2.5 sm:p-3 rounded-xl bg-slate-100/80 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700 flex items-start justify-between gap-2 text-xs"
          >
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-slate-800 dark:text-slate-200 whitespace-pre-wrap leading-tight">{{ note.content }}</p>
              <span class="text-[10px] text-slate-400 font-bold block mt-1">⏰ {{ noteTime(note.createdAt) }}</span>
            </div>
            <button
              @click="handleDeleteDailyNote(note.id)"
              class="p-1 rounded-lg text-slate-400 hover:text-rose-500 min-h-[44px] min-w-[44px] sm:min-h-[44px] sm:min-w-[44px] flex items-center justify-center shrink-0 cursor-pointer"
              title="حذف الملاحظة" aria-label="حذف الملاحظة"
            >
              🗑️
            </button>
          </div>
        </div>
      </div>

      <!-- Filters & Tasks Controls -->
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 sm:gap-4 bg-white/50 dark:bg-slate-900/50 backdrop-blur-md p-3 sm:p-4 rounded-2xl border border-slate-200/60 dark:border-slate-800/60">
        <!-- Status Filter Pills -->
        <div class="flex items-center gap-1 sm:gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
          <button
            @click="dailyTaskStatusFilter = 'all'"
            :class="[
              'px-3 py-1.5 sm:px-3.5 sm:py-2 rounded-xl text-xs font-bold transition cursor-pointer shrink-0 min-h-[44px] sm:min-h-[44px] min-w-[44px] sm:min-w-[44px] flex items-center justify-center',
              dailyTaskStatusFilter === 'all'
                ? 'bg-violet-600 text-white shadow-sm'
                : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'
            ]"
          >
            الكل ({{ store.dailyTasks ? store.dailyTasks.length : 0 }})
          </button>

          <button
            @click="dailyTaskStatusFilter = 'pending'"
            :class="[
              'px-3 py-1.5 sm:px-3.5 sm:py-2 rounded-xl text-xs font-bold transition cursor-pointer shrink-0 min-h-[44px] sm:min-h-[44px] min-w-[44px] sm:min-w-[44px] flex items-center justify-center',
              dailyTaskStatusFilter === 'pending'
                ? 'bg-amber-600 text-white shadow-sm'
                : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'
            ]"
          >
            المعلقة ({{ store.dailyTasks ? store.dailyTasks.filter(t => !t.completed).length : 0 }})
          </button>

          <button
            @click="dailyTaskStatusFilter = 'completed'"
            :class="[
              'px-3 py-1.5 sm:px-3.5 sm:py-2 rounded-xl text-xs font-bold transition cursor-pointer shrink-0 min-h-[44px] sm:min-h-[44px] min-w-[44px] sm:min-w-[44px] flex items-center justify-center',
              dailyTaskStatusFilter === 'completed'
                ? 'bg-emerald-600 text-white shadow-sm'
                : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'
            ]"
          >
            المكتملة ({{ store.dailyTasks ? store.dailyTasks.filter(t => t.completed).length : 0 }})
          </button>
        </div>

        <!-- Search & Category Filter -->
        <div class="flex items-center gap-2">
          <select
            v-model="dailyTaskCategoryFilter"
            class="px-2.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold border border-slate-200 dark:border-slate-700 focus:outline-none cursor-pointer min-h-[44px] sm:min-h-[44px]"
          >
            <option value="all">كل التصنيفات</option>
            <option v-for="cat in dailyTaskCategories" :key="cat" :value="cat">{{ cat }}</option>
          </select>

          <button
            @click="isCategoryManageModalOpen = true"
            type="button"
            class="px-2 py-2 rounded-xl bg-violet-500/10 hover:bg-violet-500/20 text-violet-600 dark:text-violet-300 text-xs font-bold border border-violet-500/20 transition cursor-pointer flex items-center gap-1 shrink-0 min-h-[44px] sm:min-h-[44px]"
            title="إدارة التصنيفات" aria-label="إدارة التصنيفات"
          >
            <span>⚙️</span>
            <span class="hidden sm:inline">إدارة التصنيفات</span>
          </button>

          <input
            v-model="dailyTaskSearchQuery"
            type="text"
            placeholder="بحث..."
            class="px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 text-xs font-semibold border border-slate-200 dark:border-slate-700 focus:outline-none w-28 sm:w-44 min-h-[44px] sm:min-h-[44px]"
          />
        </div>
      </div>

      <!-- Daily Tasks List -->
      <div v-if="filteredDailyTasks.length > 0" class="space-y-2.5 sm:space-y-3">
        <div
          v-for="task in filteredDailyTasks"
          :key="task.id"
          :class="[
            'group relative p-3 sm:p-4 rounded-xl sm:rounded-2xl border transition-all duration-300 flex items-center justify-between gap-3 sm:gap-4 glass-card-hover',
            task.completed
              ? 'bg-emerald-50/40 dark:bg-emerald-950/20 border-emerald-200/60 dark:border-emerald-900/40 opacity-80'
              : 'bg-white/80 dark:bg-slate-900/80 border-slate-200/70 dark:border-slate-800 shadow-sm hover:shadow-md'
          ]"
        >
          <!-- Left side: Checkbox & Title & Badges -->
          <div class="flex items-center gap-2.5 sm:gap-3.5 min-w-0 flex-1">
            <!-- Custom Checkbox Button -->
            <button
              @click="handleToggleDailyTask(task.id)"
              :class="[
                'w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all duration-300 shrink-0 cursor-pointer min-h-[44px] min-w-[44px] sm:min-h-[44px] sm:min-w-[44px] -m-1.5 p-1.5',
                task.completed
                  ? 'bg-emerald-500 border-emerald-500 text-white shadow-sm shadow-emerald-500/30'
                  : 'border-slate-300 dark:border-slate-600 hover:border-violet-500 text-transparent'
              ]"
              title="تغيير حالة الإنجاز" aria-label="تغيير حالة الإنجاز"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 stroke-[3]" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
            </button>

            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 mb-0.5 sm:mb-1">
                <!-- Task Title -->
                <span
                  :class="[
                    'text-xs sm:text-base font-extrabold tracking-tight transition-all leading-tight',
                    task.completed
                      ? 'line-through text-slate-400 dark:text-slate-500'
                      : 'text-slate-900 dark:text-white'
                  ]"
                >
                  {{ task.title }}
                </span>
              </div>

              <!-- Metadata Pills (Category, Priority, Time) -->
              <div class="flex flex-wrap items-center gap-1 sm:gap-2 text-[10px] sm:text-[11px] font-bold">
                <!-- Category Pill -->
                <span class="px-2 py-0.5 rounded-md bg-violet-500/10 text-violet-700 dark:text-violet-300 border border-violet-500/20">
                  🏷️ {{ task.category || 'عام' }}
                </span>

                <!-- Priority Pill -->
                <span
                  :class="[
                    'px-1.5 py-0.5 rounded-md border',
                    task.priority === 'عالية' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20' :
                    task.priority === 'منخفضة' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' :
                    'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20'
                  ]"
                >
                  {{ task.priority === 'عالية' ? '🔴 عالية' : task.priority === 'منخفضة' ? '🟢 منخفضة' : '🟡 متوسطة' }}
                </span>

                <!-- Optional Due Time -->
                <span v-if="task.dueTime" class="px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 flex items-center gap-1">
                  ⏰ {{ task.dueTime }}
                </span>
              </div>
            </div>
          </div>

          <!-- Right side: Actions -->
          <div class="flex items-center gap-1 shrink-0">
            <!-- Delete Button -->
            <button
              @click="handleDeleteDailyTask(task.id)"
              class="p-1.5 sm:p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition cursor-pointer min-h-[44px] min-w-[44px] sm:min-h-[44px] sm:min-w-[44px] flex items-center justify-center"
              title="حذف المهمة" aria-label="حذف المهمة"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-10 sm:py-12 bg-white/40 dark:bg-slate-900/40 backdrop-blur-md rounded-2xl sm:rounded-3xl border border-dashed border-slate-300 dark:border-slate-800 p-4">
        <div class="w-12 h-12 sm:w-16 sm:h-16 mx-auto mb-2 sm:mb-3 rounded-2xl bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center text-xl sm:text-2xl">
          📝
        </div>
        <h3 class="text-sm sm:text-base font-black text-slate-800 dark:text-slate-200">لا توجد تاسكات يومية هنا حالياً</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">أضف مهمة جديدة من الشريط العلوي لسجل اليوميات</p>
      </div>

    </div>
    <!-- End Daily Tasks / Journal Tab View -->

    <!-- Micro Floating Action Button (Micro-FAB) -->
    <button
      @click="isAddModalOpen = true"
      class="fixed above-nav left-4 z-float md:hidden w-11 h-11 min-h-[44px] min-w-[44px] rounded-full bg-gradient-to-r from-violet-600 to-indigo-600 text-white shadow-lg flex items-center justify-center text-xl font-bold active:scale-95 transition-all micro-fab cursor-pointer"
      title="إضافة جديدة" aria-label="إضافة جديدة"
    >
      +
    </button>

    <!-- 1. Bottom Sheet: Add New Habit -->
    <MobileBottomSheet 
      :isOpen="isAddModalOpen" 
      @close="isAddModalOpen = false"
      title="إضافة عادة جديدة"
      icon="✨"
      maxWidth="max-w-md"
    >
      <form @submit.prevent="submitNewHabit" class="space-y-4 sm:space-y-5">
        <!-- Title -->
        <div>
          <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-1.5">اسم العادة</label>
          <input 
            v-model="customForm.title"
            type="text"
            placeholder="مثلاً: شرب ماء، قراءة، ممارسة الرياضة..."
            required
            class="w-full px-4 py-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-bold focus:ring-2 focus:ring-violet-500 outline-none"
          />
        </div>

        <!-- Category Picker -->
        <div>
          <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-2">التصنيف</label>
          <div class="flex items-center gap-2 flex-wrap">
            <button 
              v-for="cat in presetCategories" 
              :key="cat"
              type="button"
              @click="customForm.category = cat"
              :class="[
                'px-3 py-1.5 rounded-xl text-xs font-extrabold border transition cursor-pointer min-h-[44px] sm:min-h-[44px] flex items-center justify-center',
                customForm.category === cat 
                  ? 'bg-violet-600 text-white border-violet-600 shadow-sm' 
                  : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'
              ]"
            >
              {{ cat }}
            </button>
          </div>
        </div>

        <!-- Emoji Picker -->
        <div>
          <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-2">الأيقونة</label>
          <div class="flex items-center gap-2 flex-wrap">
            <button 
              v-for="e in presetEmojis" 
              :key="e"
              type="button"
              @click="customForm.icon = e"
              :class="[
                'w-10 h-10 sm:w-11 sm:h-11 min-h-[44px] min-w-[44px] sm:min-h-[44px] sm:min-w-[44px] rounded-xl text-lg flex items-center justify-center border transition cursor-pointer',
                customForm.icon === e ? 'bg-violet-600 text-white border-violet-600 shadow-md scale-110' : 'bg-slate-100 dark:bg-slate-800 border-transparent'
              ]"
            >
              {{ e }}
            </button>
          </div>
        </div>

        <!-- Color Picker -->
        <div>
          <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-2">اللون المميز</label>
          <div class="flex items-center gap-2.5 flex-wrap">
            <button 
              v-for="c in presetColors" 
              :key="c.value"
              type="button"
              @click="customForm.color = c.value"
              :class="[
                'w-10 h-10 sm:w-11 sm:h-11 min-h-[44px] min-w-[44px] sm:min-h-[44px] sm:min-w-[44px] rounded-xl bg-gradient-to-tr border-2 transition cursor-pointer',
                c.value,
                customForm.color === c.value ? 'border-white dark:border-slate-900 ring-2 ring-violet-500 scale-110' : 'border-transparent opacity-80'
              ]"
              :title="c.label" aria-label="c.label"
            ></button>
          </div>
        </div>

        <!-- Days Frequency Picker Grid -->
        <div>
          <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-2">أيام التكرار الأسبوعية</label>
          <div class="grid grid-cols-7 gap-1 text-center">
            <button 
              v-for="day in weekDayLabels"
              :key="day.id"
              type="button"
              @click="toggleFormDay(day.id)"
              :class="[
                'py-2 rounded-xl text-xs font-extrabold border transition cursor-pointer min-h-[44px] sm:min-h-[44px] w-full min-w-0 h-10 sm:h-11 flex items-center justify-center',
                customForm.frequency.includes(day.id)
                  ? 'bg-violet-600 text-white border-violet-600 shadow-sm'
                  : 'bg-slate-100 dark:bg-slate-800 text-slate-400 border-transparent'
              ]"
            >
              {{ day.short }}
            </button>
          </div>
          <span class="text-[10.5px] text-slate-400 mt-1.5 block">اختر الأيام التي ترغب بالتكرار فيها (محدد {{ customForm.frequency.length }} أيام)</span>
        </div>

        <div class="pt-3">
          <button 
            type="submit" 
            class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-extrabold text-sm shadow-lg shadow-violet-600/30 transition cursor-pointer hover:scale-[1.02] min-h-[44px] flex items-center justify-center"
          >
            حفظ العادة
          </button>
        </div>
      </form>
    </MobileBottomSheet>

    <!-- 2. Bottom Sheet: Habit Stats & Overview Drawer -->
    <MobileBottomSheet 
      :isOpen="isStatsDrawerOpen" 
      @close="closeStatsDrawer"
      drawerMode
      title="إحصائيات وتقويم العادة"
      icon="📊"
      maxWidth="max-w-md"
    >
      <div v-if="activeHabitForStats" class="text-center relative">
        <!-- Top Glow -->
        <div class="w-32 h-32 bg-violet-500/15 rounded-full blur-2xl absolute top-0 right-1/2 translate-x-1/2 pointer-events-none"></div>

        <!-- Habit Icon Badge Header -->
        <div 
          :class="[
            'w-20 h-20 rounded-3xl mx-auto mt-2 mb-4 flex items-center justify-center text-4xl shadow-xl bg-gradient-to-tr text-white animate-bounce-slow',
            activeHabitForStats.color || 'from-violet-500 to-indigo-500'
          ]"
        >
          {{ activeHabitForStats.icon || '⭐' }}
        </div>

        <h3 class="text-xl font-black text-slate-900 dark:text-white mb-1">
          {{ activeHabitForStats.title }}
        </h3>

        <!-- Level Badge -->
        <div class="inline-block px-4 py-1.5 rounded-full bg-gradient-to-r text-white text-xs font-black shadow-md mb-6 animate-pulse"
             :class="getStreakBadgeInfo(getHabitStreak(activeHabitForStats)).color">
          {{ getStreakBadgeInfo(getHabitStreak(activeHabitForStats)).title }}
        </div>

        <!-- Animated Flame Streak Counter -->
        <div class="bg-gradient-to-b from-slate-50 to-amber-50/40 dark:from-slate-800 dark:to-amber-950/20 rounded-3xl p-5 border border-amber-200/50 dark:border-amber-900/30 mb-6 shadow-inner relative overflow-hidden">
          <div class="text-5xl mb-2 animate-pulse">🔥</div>
          
          <div class="text-4xl font-black text-amber-500 tracking-tight mb-1">
            {{ getHabitStreak(activeHabitForStats) }} <span class="text-lg font-bold text-slate-600 dark:text-slate-300">أيام</span>
          </div>

          <p class="text-xs font-extrabold text-slate-500 dark:text-slate-400">
            سلسلة إنجاز متواصلة بدون انقطاع 🎉
          </p>
        </div>

        <!-- Circular Completion Gauge -->
        <div class="flex items-center justify-around bg-slate-50 dark:bg-slate-800 p-4 rounded-2xl border border-slate-200/60 dark:border-slate-800 mb-6">
          <div class="relative w-20 h-20 flex items-center justify-center">
            <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
              <path class="text-slate-200 dark:text-slate-700" stroke-width="3.5" stroke="currentColor" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
              <path 
                class="text-violet-500 transition-all duration-1000 ease-out" 
                :stroke-dasharray="`${activeHabitCompletionPercentage}, 100`"
                stroke-width="3.5" 
                stroke-linecap="round" 
                stroke="currentColor" 
                fill="none" 
                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" 
              />
            </svg>
            <span class="absolute text-xs font-black text-slate-900 dark:text-white">%{{ activeHabitCompletionPercentage }}</span>
          </div>

          <div class="text-right">
            <span class="text-xs font-bold text-slate-400 block">نسبة الالتزام بالشهر</span>
            <span class="text-sm font-black text-slate-900 dark:text-white mt-0.5 block">
              {{ currentMonthDays.filter(d => d.completed).length }} / {{ currentMonthDays.length }} يوم مكتمل
            </span>
            <span class="text-[10px] text-emerald-500 font-extrabold block mt-1">حافظ على الإنجاز 🚀</span>
          </div>
        </div>

        <!-- Monthly Calendar Heatmap Grid -->
        <div class="text-right mb-6">
          <h5 class="text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-2">تقويم إنجاز هذا الشهر:</h5>
          <div class="grid grid-cols-7 gap-1.5 text-center">
            <div 
              v-for="item in currentMonthDays" 
              :key="item.dateKey"
              :class="[
                'h-7 rounded-lg flex items-center justify-center text-[10px] font-black transition-all min-w-0',
                item.completed
                  ? 'bg-emerald-500 text-white shadow-sm scale-105'
                  : 'bg-slate-100 dark:bg-slate-800 text-slate-400'
              ]"
            >
              {{ item.dayNum }}
            </div>
          </div>
        </div>

        <!-- Action Row inside Stats Drawer: Full Details link & Close -->
        <div class="space-y-2.5">
          <button 
            @click="openHabitDetail(activeHabitForStats); closeStatsDrawer()"
            class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-extrabold text-xs shadow-md transition cursor-pointer flex items-center justify-center gap-2 min-h-[44px]"
          >
            <span>الانتقال للتفاصيل الكاملة والمهام الفرعية ↗</span>
          </button>

          <button 
            @click="closeStatsDrawer"
            class="w-full py-3 rounded-2xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs transition cursor-pointer min-h-[44px]"
          >
            متابعة التحدي 💪
          </button>
        </div>
      </div>
    </MobileBottomSheet>

    <!-- 3. Bottom Sheet: Quick Habit Detail Preview -->
    <MobileBottomSheet 
      :isOpen="isQuickDetailOpen" 
      @close="closeQuickDetail"
      title="معاينة العادة"
      icon="🔍"
      maxWidth="max-w-md"
    >
      <div v-if="quickDetailHabit" class="space-y-5 text-right">
        
        <!-- Hero Row (Icon, Title, Category, Streak & Level) -->
        <div class="flex items-center gap-3.5 bg-slate-50 dark:bg-slate-800 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800">
          <div 
            :class="[
              'w-14 h-14 rounded-2xl flex items-center justify-center text-3xl shadow-md bg-gradient-to-tr text-white shrink-0',
              quickDetailHabit.color || 'from-violet-500 to-indigo-500'
            ]"
          >
            {{ quickDetailHabit.icon || '⭐' }}
          </div>

          <div class="min-w-0 flex-1">
            <h4 class="text-base font-black text-slate-900 dark:text-white truncate">
              {{ quickDetailHabit.title }}
            </h4>

            <div class="flex items-center gap-1.5 flex-wrap mt-1">
              <span class="px-2 py-0.5 rounded-lg text-[11px] font-bold bg-slate-200/60 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                {{ quickDetailHabit.category || 'عادات يومية' }}
              </span>

              <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                🔥 {{ getHabitStreak(quickDetailHabit) }} يوم
              </span>
            </div>
          </div>
        </div>

        <!-- Today's Status & Quick Action Button -->
        <div class="flex items-center justify-between p-4 rounded-2xl bg-gradient-to-r from-violet-500/10 to-indigo-500/10 border border-violet-500/20">
          <div>
            <span class="text-xs font-extrabold text-slate-700 dark:text-slate-300 block">حالة اليوم</span>
            <span :class="['text-xs font-black mt-0.5 block', quickDetailHabit.logs?.[selectedDateKey]?.completed ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400']">
              {{ quickDetailHabit.logs?.[selectedDateKey]?.completed ? 'مكتملة بنجاح 🎉' : 'غير مكتملة بعد ⏳' }}
            </span>
          </div>

          <button 
            @click="handleToggleHabit(quickDetailHabit, selectedDateKey)"
            :class="[
              'px-5 py-2.5 rounded-xl text-xs font-black transition-all cursor-pointer shadow-md min-h-[44px] flex items-center justify-center gap-1.5',
              quickDetailHabit.logs?.[selectedDateKey]?.completed
                ? 'bg-emerald-500 text-white shadow-emerald-500/30'
                : 'bg-violet-600 hover:bg-violet-500 text-white shadow-violet-600/30'
            ]"
          >
            <span>{{ quickDetailHabit.logs?.[selectedDateKey]?.completed ? '✓ مكتملة' : '+ تسجيل الإنجاز' }}</span>
          </button>
        </div>

        <!-- Subtasks Checklist Preview -->
        <div class="bg-slate-50 dark:bg-slate-800 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 space-y-3">
          <div class="flex items-center justify-between">
            <h5 class="text-xs font-extrabold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
              <span>📋</span> قائمة المهام الفرعية
            </h5>
            <span class="text-[11px] font-black text-violet-600 dark:text-violet-400">
              {{ quickDetailHabit.checklist?.filter(c => c.completed).length || 0 }} / {{ quickDetailHabit.checklist?.length || 0 }}
            </span>
          </div>

          <!-- Preview checklist items (up to 3) -->
          <div v-if="quickDetailHabit.checklist && quickDetailHabit.checklist.length > 0" class="space-y-1.5">
            <div 
              v-for="item in quickDetailHabit.checklist.slice(0, 3)" 
              :key="item.id"
              @click="store.toggleHabitChecklistItem(quickDetailHabit.id, item.id)"
              class="flex items-center gap-2.5 p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 cursor-pointer min-h-[44px]"
            >
              <div :class="['w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-black border', item.completed ? 'bg-emerald-500 text-white border-emerald-500' : 'border-slate-300 dark:border-slate-600']">
                <span v-if="item.completed">✓</span>
              </div>
              <span :class="['text-xs font-bold truncate flex-1', item.completed ? 'line-through opacity-60' : 'text-slate-800 dark:text-slate-200']">
                {{ item.title }}
              </span>
            </div>
            <div v-if="quickDetailHabit.checklist.length > 3" class="text-[11px] font-bold text-slate-400 text-center pt-1">
              + {{ quickDetailHabit.checklist.length - 3 }} عناصر أخرى في التفاصيل الكاملة
            </div>
          </div>
          <div v-else class="text-[11px] text-slate-400 font-semibold text-center py-2">
            لا توجد مهام فرعية مضافة بعد.
          </div>
        </div>

        <!-- Notes Preview -->
        <div class="bg-slate-50 dark:bg-slate-800 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 space-y-2">
          <div class="flex items-center justify-between">
            <h5 class="text-xs font-extrabold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
              <span>📝</span> الملاحظات واليوميات
            </h5>
            <span class="text-[11px] font-bold text-slate-400">
              {{ quickDetailHabit.notesList?.length || 0 }} ملاحظة
            </span>
          </div>

          <div v-if="quickDetailHabit.notesList && quickDetailHabit.notesList.length > 0" class="text-xs text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-900 p-2.5 rounded-xl border border-slate-200/60 dark:border-slate-800 truncate">
            "{{ quickDetailHabit.notesList[0].content }}"
          </div>
          <div v-else class="text-[11px] text-slate-400 font-semibold text-center py-1">
            لا توجد ملاحظات مدونة بعد.
          </div>
        </div>

        <!-- Action Buttons Row -->
        <div class="pt-2 space-y-2">
          <button 
            @click="openHabitDetail(quickDetailHabit); closeQuickDetail()"
            class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white font-extrabold text-xs shadow-lg shadow-violet-600/30 transition cursor-pointer flex items-center justify-center gap-2 min-h-[44px]"
          >
            <span>عرض التفاصيل الكاملة والتعديل ↗</span>
          </button>

          <div class="flex items-center gap-2">
            <button 
              @click="openStatsDrawer(quickDetailHabit); closeQuickDetail()"
              class="flex-1 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs transition cursor-pointer min-h-[44px] flex items-center justify-center gap-1.5"
            >
              <span>📊</span>
              <span>الإحصائيات</span>
            </button>

            <button 
              @click="confirmDeleteHabit(quickDetailHabit); closeQuickDetail()"
              class="px-4 py-2.5 rounded-xl bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 hover:bg-red-100 font-bold text-xs transition cursor-pointer min-h-[44px] flex items-center justify-center gap-1"
            >
              <span>🗑️</span>
              <span>حذف</span>
            </button>
          </div>
        </div>

      </div>
    </MobileBottomSheet>

    <!-- 3. Bottom Sheet: Category Management Modal -->
    <MobileBottomSheet 
      :isOpen="isCategoryManageModalOpen" 
      @close="isCategoryManageModalOpen = false"
      title="إدارة وتخصيص التصنيفات"
      icon="🏷️"
      maxWidth="max-w-md"
    >
      <div class="space-y-6">
        <!-- Quick Category Creation Form -->
        <form @submit.prevent="handleAddCategory" class="space-y-3">
          <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300">إضافة تصنيف جديد</label>
          <div class="flex items-center gap-2">
            <input
              v-model="newCategoryInput"
              type="text"
              placeholder="مثلاً: سفر، ميزانية، تسوق..."
              class="flex-1 px-4 py-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-bold focus:ring-2 focus:ring-violet-500 outline-none"
              required
            />
            <button
              type="submit"
              class="px-5 py-3 rounded-2xl bg-violet-600 hover:bg-violet-500 text-white font-extrabold text-xs shadow-md transition cursor-pointer min-h-[44px] shrink-0"
            >
              + إضافة
            </button>
          </div>
        </form>

        <!-- Existing Categories List -->
        <div>
          <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-3">التصنيفات المتاحة حالياً</label>
          <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
            <div
              v-for="cat in store.dailyTaskCategories"
              :key="cat"
              class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/60 flex items-center justify-between gap-3"
            >
              <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-xl bg-violet-500/15 text-violet-600 dark:text-violet-300 flex items-center justify-center text-xs font-black">
                  🏷️
                </span>
                <span class="text-sm font-extrabold text-slate-900 dark:text-white">{{ cat }}</span>
                <span v-if="cat === 'عام'" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300">افتراضي</span>
              </div>

              <button
                v-if="cat !== 'عام'"
                @click="handleDeleteCategory(cat)"
                class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center"
                title="حذف التصنيف" aria-label="حذف التصنيف"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </MobileBottomSheet>

  </div>
</template>

<style scoped>
@keyframes fall {
  0% { transform: translateY(0) rotate(0deg); opacity: 1; }
  100% { transform: translateY(100vh) rotate(360deg); opacity: 0; }
}
.animate-fall {
  animation: fall linear forwards;
}

@keyframes bounceSlow {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-6px); }
}
.animate-bounce-slow {
  animation: bounceSlow 3s ease-in-out infinite;
}
</style>
