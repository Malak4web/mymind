<script setup>
import { ref, computed, onMounted } from 'vue'
import { store } from '../store'
import MobileBottomSheet from './MobileBottomSheet.vue'

// Search and Filters
const searchQuery = ref('')
const statusFilter = ref('all') // 'all' | 'active' | 'completed' | 'shared'
const categoryFilter = ref('all')

// Modals
const isCreateModalOpen = ref(false)
const isDetailModalOpen = ref(false)
const isCelebrationModalOpen = ref(false)
const editingChallengeId = ref(null)

// Active Selected Challenge for Details / Day Tracker
const selectedChallengeId = ref(null)
const selectedDayNumber = ref(1)
const dayFilter = ref('all') // 'all' | 'completed' | 'remaining'

// Confetti effect state
const showConfetti = ref(false)
const triggerConfetti = () => {
  showConfetti.value = true
  setTimeout(() => {
    showConfetti.value = false
  }, 2500)
}

// Haptic feedback
const triggerHaptic = () => {
  if (typeof navigator !== 'undefined' && navigator.vibrate) {
    try { navigator.vibrate(25) } catch (e) {}
  }
}

// Preset durations
const durationPresets = [
  { label: 'أسبوع (7 أيام)', days: 7 },
  { label: 'أسبوعين (14 يوم)', days: 14 },
  { label: '21 يوم (تكوين عادة)', days: 21 },
  { label: 'شهر (30 يوم)', days: 30 },
  { label: '3 شهور (90 يوم)', days: 90 },
  { label: '100 يوم إنجاز', days: 100 }
]

// Quick condition suggestions
const conditionSuggestions = [
  '📖 قراءة 20 صفحة',
  '🏃 تمرين 45 دقيقة',
  '💧 شرب 3 لتر ماء',
  '🥗 طعام صحي بدون سكر',
  '💻 ساعة برمجة / تعلم',
  '🧘 أذكار وتأمل 15 دقيقة',
  '😴 استيقاظ مبكر 6 صباحاً',
  '📵 تقليل وقت الشاشة ساعتين'
]

// Available color gradients
const colorOptions = [
  { id: 'bg-gradient-to-r from-violet-600 to-indigo-600', name: 'بنفسجي ملكي', class: 'bg-gradient-to-r from-violet-600 to-indigo-600' },
  { id: 'bg-gradient-to-r from-emerald-600 to-teal-600', name: 'أخضر إنجاز', class: 'bg-gradient-to-r from-emerald-600 to-teal-600' },
  { id: 'bg-gradient-to-r from-amber-500 to-orange-600', name: 'برتقالي حماسي', class: 'bg-gradient-to-r from-amber-500 to-orange-600' },
  { id: 'bg-gradient-to-r from-rose-500 to-pink-600', name: 'وردي طاقة', class: 'bg-gradient-to-r from-rose-500 to-pink-600' },
  { id: 'bg-gradient-to-r from-blue-600 to-cyan-600', name: 'أزرق تركيز', class: 'bg-gradient-to-r from-blue-600 to-cyan-600' },
  { id: 'bg-gradient-to-r from-purple-600 to-pink-600', name: 'أرجواني إبداع', class: 'bg-gradient-to-r from-purple-600 to-pink-600' }
]

// Safe gradient class resolver supporting both legacy 'from-...' and full 'bg-gradient-to-r ...'
const getGradientClass = (color) => {
  if (!color) return 'bg-gradient-to-r from-violet-600 to-indigo-600'
  const c = String(color).trim()
  if (c.startsWith('bg-')) return c
  return `bg-gradient-to-r ${c}`
}

// Emoji icons
const iconOptions = ['🎯', '🏃', '📚', '💪', '🚀', '💧', '🧠', '⚡', '⭐', '🔥', '🥗', '🧘', '💻', '🏆', '⏱️', '🌅']

// Prize emoji options
const prizeIconOptions = ['🏆', '🎁', '✈️', '⌚', '🎮', '🍕', '🏖️', '💻', '⭐', '👕', '🚲', '🍔']

// Form State for Create / Edit
const form = ref({
  title: '',
  description: '',
  category: 'عام',
  icon: '🎯',
  color: 'bg-gradient-to-r from-violet-600 to-indigo-600',
  start_date: new Date().toISOString().slice(0, 10),
  end_date: new Date(Date.now() + 6 * 86400000).toISOString().slice(0, 10),
  total_days: 7,
  reward_title: '',
  reward_icon: '🏆',
  reward_description: '',
  conditions: ['📖 قراءة 20 صفحة', '🏃 تمرين 45 دقيقة'],
  partner_id: null
})

const newConditionInput = ref('')

// Cheer form state
const cheerMessage = ref('')
const selectedReaction = ref('🔥')
const isSendingCheer = ref(false)

const quickCheers = [
  'عاش يا بطل! كمل! 💪',
  'فخور بيك، استمر! 🔥',
  'أنت قدها وقدود! 🚀',
  'قربت تخلص، لا تتراجع! 👏',
  'إنجاز أسطوري! 🏆',
  'معاك وفي ضهرك! 🤝'
]

const reactions = ['🔥', '💪', '👏', '🏆', '🚀', '❤️']

// Helper to compute date range
const updateEndDateFromDays = () => {
  if (!form.value.start_date || !form.value.total_days) return
  const start = new Date(form.value.start_date)
  start.setDate(start.getDate() + (parseInt(form.value.total_days, 10) - 1))
  form.value.end_date = start.toISOString().slice(0, 10)
}

const updateDaysFromEndDate = () => {
  if (!form.value.start_date || !form.value.end_date) return
  const start = new Date(form.value.start_date)
  const end = new Date(form.value.end_date)
  const diffTime = end.getTime() - start.getTime()
  const diffDays = Math.round(diffTime / (1000 * 3600 * 24)) + 1
  form.value.total_days = Math.max(1, diffDays)
}

const applyDurationPreset = (days) => {
  form.value.total_days = days
  updateEndDateFromDays()
}

// Condition manipulation
const addCondition = () => {
  const text = newConditionInput.value.trim()
  if (!text) return
  if (!form.value.conditions.includes(text)) {
    form.value.conditions.push(text)
  }
  newConditionInput.value = ''
}

const addSuggestedCondition = (suggestion) => {
  if (!form.value.conditions.includes(suggestion)) {
    form.value.conditions.push(suggestion)
  }
}

const removeCondition = (index) => {
  form.value.conditions.splice(index, 1)
}

// Open Create Modal
const openCreateModal = () => {
  editingChallengeId.value = null
  const today = new Date().toISOString().slice(0, 10)
  const endDate = new Date(Date.now() + 6 * 86400000).toISOString().slice(0, 10)
  form.value = {
    title: '',
    description: '',
    category: 'عام',
    icon: '🎯',
    color: 'bg-gradient-to-r from-violet-600 to-indigo-600',
    start_date: today,
    end_date: endDate,
    total_days: 7,
    reward_title: '',
    reward_icon: '🏆',
    reward_description: '',
    conditions: ['قراءة 20 صفحة', 'شرب 3 لتر ماء'],
    partner_id: null
  }
  newConditionInput.value = ''
  isCreateModalOpen.value = true
}

// Open Edit Modal
const openEditModal = (challenge, event) => {
  if (event) event.stopPropagation()
  editingChallengeId.value = challenge.id
  form.value = {
    title: challenge.title || '',
    description: challenge.description || '',
    category: challenge.category || 'عام',
    icon: challenge.icon || '🎯',
    color: getGradientClass(challenge.color),
    start_date: challenge.start_date || new Date().toISOString().slice(0, 10),
    end_date: challenge.end_date || new Date().toISOString().slice(0, 10),
    total_days: Number(challenge.total_days) || 7,
    reward_title: challenge.reward_title || '',
    reward_icon: challenge.reward_icon || '🏆',
    reward_description: challenge.reward_description || '',
    conditions: Array.isArray(challenge.conditions) ? [...challenge.conditions] : [],
    partner_id: challenge.partner_id || null
  }
  newConditionInput.value = ''
  isCreateModalOpen.value = true
}

// Submit Create/Edit
const submitChallengeForm = async () => {
  if (!form.value.title.trim()) {
    alert('يرجى كتابة اسم التحدي')
    return
  }
  if (!form.value.conditions || form.value.conditions.length === 0) {
    alert('يرجى إضافة شرط واحد على الأقل لليوميات')
    return
  }

  const payload = {
    title: form.value.title.trim(),
    description: form.value.description.trim(),
    category: form.value.category,
    icon: form.value.icon,
    color: form.value.color,
    start_date: form.value.start_date,
    end_date: form.value.end_date,
    total_days: parseInt(form.value.total_days, 10) || 7,
    reward_title: form.value.reward_title.trim(),
    reward_icon: form.value.reward_icon,
    reward_description: form.value.reward_description.trim(),
    conditions: form.value.conditions,
    partner_id: form.value.partner_id ? parseInt(form.value.partner_id, 10) : null
  }

  if (editingChallengeId.value) {
    await store.updateChallenge(editingChallengeId.value, payload)
  } else {
    const created = await store.addChallenge(payload)
    if (created) {
      triggerConfetti()
    }
  }

  isCreateModalOpen.value = false
}

// Delete Challenge
const handleDeleteChallenge = async (challenge, event) => {
  if (event) event.stopPropagation()
  if (confirm(`هل أنت متأكد من حذف تحدي "${challenge.title}"؟`)) {
    if (selectedChallengeId.value === challenge.id) {
      isDetailModalOpen.value = false
    }
    await store.deleteChallenge(challenge.id)
  }
}

// Open Challenge Details View
const openChallengeDetail = (challenge) => {
  selectedChallengeId.value = challenge.id
  // Determine default day: find current day in challenge, or first incomplete day, or 1
  const today = new Date().toISOString().slice(0, 10)
  const days = getChallengeDays(challenge)
  const todayItem = days.find(d => d.dateKey === today)
  if (todayItem) {
    selectedDayNumber.value = todayItem.dayNumber
  } else {
    const firstIncomplete = days.find(d => !d.completed)
    selectedDayNumber.value = firstIncomplete ? firstIncomplete.dayNumber : 1
  }
  isDetailModalOpen.value = true
}

// Active Challenge Object
const activeChallenge = computed(() => {
  if (!selectedChallengeId.value) return null
  return (store.challenges || []).find(c => c.id === selectedChallengeId.value) || null
})

// Calculate days breakdown for a challenge
const getChallengeDays = (challenge) => {
  if (!challenge) return []
  const total = Number(challenge.total_days) || 1
  const startDate = challenge.start_date ? new Date(challenge.start_date) : new Date()
  const todayKey = new Date().toISOString().slice(0, 10)
  const daysProgress = challenge.days_progress || {}
  const conditions = challenge.conditions || []

  const list = []
  for (let i = 1; i <= total; i++) {
    const d = new Date(startDate)
    d.setDate(d.getDate() + (i - 1))
    const dateKey = d.toISOString().slice(0, 10)

    const dayData = daysProgress[String(i)] || {}
    const items = dayData.items || {}
    const completed = Boolean(dayData.completed)
    const checkedCount = conditions.filter((_, idx) => Boolean(items[idx])).length

    list.push({
      dayNumber: i,
      dateKey,
      formattedDate: d.toLocaleDateString('ar-EG', { weekday: 'short', month: 'short', day: 'numeric' }),
      isToday: dateKey === todayKey,
      isPast: dateKey < todayKey,
      isFuture: dateKey > todayKey,
      completed,
      items,
      checkedCount,
      totalConditions: conditions.length,
      progressPercentage: conditions.length > 0 ? Math.round((checkedCount / conditions.length) * 100) : 0
    })
  }
  return list
}

// Active challenge days list
const activeChallengeDays = computed(() => {
  return getChallengeDays(activeChallenge.value)
})

// Filtered days list for detail view
const filteredActiveChallengeDays = computed(() => {
  const days = activeChallengeDays.value
  if (dayFilter.value === 'completed') {
    return days.filter(d => d.completed)
  }
  if (dayFilter.value === 'remaining') {
    return days.filter(d => !d.completed)
  }
  return days
})

// Selected day data
const currentDayData = computed(() => {
  if (!activeChallenge.value) return null
  return activeChallengeDays.value.find(d => d.dayNumber === selectedDayNumber.value) || activeChallengeDays.value[0] || null
})

// Toggle Condition
const toggleCondition = async (condIndex) => {
  if (!activeChallenge.value || !currentDayData.value) return

  triggerHaptic()
  const chId = activeChallenge.value.id
  const dayNum = currentDayData.value.dayNumber

  await store.toggleChallengeCondition(chId, dayNum, condIndex)

  // Check if this made the day newly completed
  const updatedChallenge = (store.challenges || []).find(c => c.id === chId)
  if (updatedChallenge) {
    const updatedDay = (updatedChallenge.days_progress || {})[String(dayNum)]
    if (updatedDay?.completed) {
      triggerConfetti()
    }
    // If entire challenge completed!
    if (updatedChallenge.status === 'completed') {
      isCelebrationModalOpen.value = true
    }
  }
}

// Calculate Progress Metrics for a Challenge
const getChallengeStats = (challenge) => {
  if (!challenge) return { completedDays: 0, totalDays: 0, percentage: 0, streak: 0 }
  const totalDays = Number(challenge.total_days) || 1
  const daysProgress = challenge.days_progress || {}

  let completedDays = 0
  for (let i = 1; i <= totalDays; i++) {
    if (daysProgress[String(i)]?.completed) {
      completedDays++
    }
  }

  const percentage = Math.min(100, Math.round((completedDays / totalDays) * 100))

  // Streak calculation
  let streak = 0
  const days = getChallengeDays(challenge)
  const todayKey = new Date().toISOString().slice(0, 10)
  const todayIdx = days.findIndex(d => d.dateKey === todayKey)
  const checkIdx = todayIdx !== -1 ? todayIdx : days.length - 1

  for (let i = checkIdx; i >= 0; i--) {
    if (days[i].completed) {
      streak++
    } else if (i === checkIdx && days[i].isToday) {
      // If today is not done yet, don't break streak if yesterday was done
      continue
    } else {
      break
    }
  }

  return { completedDays, totalDays, percentage, streak }
}

// Top Overall Summary Stats
const overallStats = computed(() => {
  const challenges = store.challenges || []
  const activeCount = challenges.filter(c => c.status === 'active').length
  const completedCount = challenges.filter(c => c.status === 'completed').length

  let totalCompletedDays = 0
  let maxStreak = 0

  challenges.forEach(c => {
    const stats = getChallengeStats(c)
    totalCompletedDays += stats.completedDays
    if (stats.streak > maxStreak) maxStreak = stats.streak
  })

  return {
    activeCount,
    completedCount,
    totalCompletedDays,
    maxStreak,
    totalChallenges: challenges.length
  }
})

// Filtered Challenges List
const filteredChallenges = computed(() => {
  let list = store.challenges || []

  // Status Filter
  if (statusFilter.value === 'active') {
    list = list.filter(c => c.status === 'active')
  } else if (statusFilter.value === 'completed') {
    list = list.filter(c => c.status === 'completed')
  } else if (statusFilter.value === 'shared') {
    const myId = store.currentUser?.id
    list = list.filter(c => c.partner_id && (c.partner_id === myId || c.user_id === myId))
  }

  // Category Filter
  if (categoryFilter.value !== 'all') {
    list = list.filter(c => c.category === categoryFilter.value)
  }

  // Search Query
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()
    list = list.filter(c => 
      (c.title || '').toLowerCase().includes(q) ||
      (c.description || '').toLowerCase().includes(q) ||
      (c.reward_title || '').toLowerCase().includes(q) ||
      (c.category || '').toLowerCase().includes(q)
    )
  }

  return list
})

// Submit Cheer
const handleSendCheer = async (presetText = null) => {
  if (!activeChallenge.value) return
  const msg = (presetText || cheerMessage.value || '').trim()
  const reaction = selectedReaction.value || '🔥'

  if (!msg && !reaction) return

  isSendingCheer.value = true
  try {
    await store.addChallengeCheer(activeChallenge.value.id, {
      message: msg,
      reaction
    })
    cheerMessage.value = ''
    triggerConfetti()
  } finally {
    isSendingCheer.value = false
  }
}

// User lookup helper
const getPartnerName = (challenge) => {
  if (!challenge || !challenge.partner_id) return null
  if (challenge.partner?.name) return challenge.partner.name
  const found = (store.users || []).find(u => u.id === challenge.partner_id)
  return found?.name || 'عضو في الفريق'
}

const getCreatorName = (challenge) => {
  if (!challenge) return ''
  if (challenge.user?.name) return challenge.user.name
  const found = (store.users || []).find(u => u.id === challenge.user_id)
  return found?.name || 'صاحب التحدي'
}

const isOwner = (challenge) => {
  if (!challenge || !store.currentUser) return true
  return challenge.user_id === store.currentUser.id
}
</script>

<template>
  <div class="space-y-4 sm:space-y-6">

    <!-- Confetti Particles Canvas -->
    <div v-if="showConfetti" class="fixed inset-0 pointer-events-none z-[120] overflow-hidden">
      <div v-for="n in 35" :key="n" 
           class="absolute animate-fall rounded-full opacity-80"
           :style="{
             left: `${Math.random() * 100}%`,
             top: `-10px`,
             width: `${Math.random() * 10 + 6}px`,
             height: `${Math.random() * 10 + 6}px`,
             backgroundColor: ['#8b5cf6', '#ec4899', '#3b82f6', '#10b981', '#f59e0b', '#ef4444'][n % 6],
             animationDuration: `${Math.random() * 2 + 1.5}s`,
             animationDelay: `${Math.random() * 0.5}s`
           }">
      </div>
    </div>

    <!-- Header Stats Dashboard Strip -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4">
      <div class="bg-white/80 dark:bg-slate-900/80 p-3 sm:p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm backdrop-blur-md flex items-center gap-3">
        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-violet-500/10 dark:bg-violet-950/40 text-violet-600 dark:text-violet-400 flex items-center justify-center text-xl sm:text-2xl shrink-0">
          🎯
        </div>
        <div>
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400 block">التحديات الجارية</span>
          <span class="text-lg sm:text-2xl font-black text-slate-800 dark:text-slate-100">{{ overallStats.activeCount }}</span>
        </div>
      </div>

      <div class="bg-white/80 dark:bg-slate-900/80 p-3 sm:p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm backdrop-blur-md flex items-center gap-3">
        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-emerald-500/10 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl sm:text-2xl shrink-0">
          ✅
        </div>
        <div>
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400 block">أيام تم إنجازها</span>
          <span class="text-lg sm:text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ overallStats.totalCompletedDays }}</span>
        </div>
      </div>

      <div class="bg-white/80 dark:bg-slate-900/80 p-3 sm:p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm backdrop-blur-md flex items-center gap-3">
        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-amber-500/10 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl sm:text-2xl shrink-0">
          🔥
        </div>
        <div>
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400 block">أطول سلسلة التزام</span>
          <span class="text-lg sm:text-2xl font-black text-amber-600 dark:text-amber-400">{{ overallStats.maxStreak }} يوم</span>
        </div>
      </div>

      <div class="bg-white/80 dark:bg-slate-900/80 p-3 sm:p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm backdrop-blur-md flex items-center gap-3">
        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-xl bg-rose-500/10 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xl sm:text-2xl shrink-0">
          🏆
        </div>
        <div>
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400 block">تحديات مكتملة</span>
          <span class="text-lg sm:text-2xl font-black text-rose-600 dark:text-rose-400">{{ overallStats.completedCount }}</span>
        </div>
      </div>
    </div>

    <!-- Actions & Filter Toolbar -->
    <div class="bg-white/80 dark:bg-slate-900/80 p-3 sm:p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm backdrop-blur-md space-y-3">
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <!-- Search Input -->
        <div class="relative flex-1">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="ابحث في التحديات والجوائز..."
            class="w-full pl-9 pr-4 py-2 sm:py-2.5 rounded-xl text-xs sm:text-sm bg-slate-100 dark:bg-slate-800/80 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-700/80 focus:outline-none focus:ring-2 focus:ring-violet-500/40 transition-all"
          />
          <span class="absolute left-3 top-2.5 sm:top-3 text-slate-400 text-sm">🔍</span>
        </div>

        <!-- Create Challenge Button -->
        <button
          @click="openCreateModal"
          class="px-4 py-2 sm:py-2.5 rounded-xl font-black text-xs sm:text-sm text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 shadow-md shadow-violet-600/30 active:scale-95 transition-all flex items-center justify-center gap-2 cursor-pointer shrink-0"
        >
          <span class="text-base leading-none">✨</span>
          <span>إنشاء تحدي جديد</span>
        </button>
      </div>

      <!-- Filter Pills -->
      <div class="flex items-center gap-1.5 overflow-x-auto pb-1 no-scrollbar text-xs font-bold">
        <button
          @click="statusFilter = 'all'"
          :class="[
            'px-3 py-1.5 rounded-xl transition-all cursor-pointer whitespace-nowrap',
            statusFilter === 'all'
              ? 'bg-violet-600 text-white shadow-sm'
              : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
          ]"
        >
          الكل ({{ store.challenges ? store.challenges.length : 0 }})
        </button>

        <button
          @click="statusFilter = 'active'"
          :class="[
            'px-3 py-1.5 rounded-xl transition-all cursor-pointer whitespace-nowrap',
            statusFilter === 'active'
              ? 'bg-violet-600 text-white shadow-sm'
              : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
          ]"
        >
          ⚡ الجارية ({{ overallStats.activeCount }})
        </button>

        <button
          @click="statusFilter = 'completed'"
          :class="[
            'px-3 py-1.5 rounded-xl transition-all cursor-pointer whitespace-nowrap',
            statusFilter === 'completed'
              ? 'bg-violet-600 text-white shadow-sm'
              : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
          ]"
        >
          🏆 المكتملة ({{ overallStats.completedCount }})
        </button>

        <button
          @click="statusFilter = 'shared'"
          :class="[
            'px-3 py-1.5 rounded-xl transition-all cursor-pointer whitespace-nowrap',
            statusFilter === 'shared'
              ? 'bg-violet-600 text-white shadow-sm'
              : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
          ]"
        >
          👥 المشتركة
        </button>
      </div>
    </div>

    <!-- Challenges Cards Grid -->
    <div v-if="filteredChallenges.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3.5 sm:gap-5">
      <div
        v-for="challenge in filteredChallenges"
        :key="challenge.id"
        @click="openChallengeDetail(challenge)"
        class="group relative bg-white/90 dark:bg-slate-900/90 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm hover:shadow-xl hover:border-violet-500/40 transition-all duration-300 p-4 sm:p-5 flex flex-col justify-between cursor-pointer overflow-hidden backdrop-blur-md"
      >
        <!-- Top decorative ambient glow -->
        <div 
          class="absolute -top-12 -right-12 w-28 h-28 rounded-full opacity-15 blur-2xl pointer-events-none transition-all group-hover:opacity-30"
          :class="challenge.color && challenge.color.includes('emerald') ? 'bg-emerald-500' : (challenge.color && challenge.color.includes('amber') ? 'bg-amber-500' : 'bg-violet-500')"
        ></div>

        <!-- Card Header -->
        <div>
          <div class="flex items-start justify-between gap-3 mb-3">
            <div class="flex items-center gap-3">
              <div 
                class="w-12 h-12 rounded-2xl text-2xl flex items-center justify-center shadow-md text-white shrink-0 group-hover:scale-105 transition-transform bg-violet-600"
                :class="getGradientClass(challenge.color)"
              >
                {{ challenge.icon || '🎯' }}
              </div>
              <div>
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                    {{ challenge.category || 'عام' }}
                  </span>
                  <span 
                    v-if="challenge.status === 'completed'"
                    class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20"
                  >
                    🏆 مكتمل بنجاح
                  </span>
                  <span 
                    v-else
                    class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-violet-500/15 text-violet-600 dark:text-violet-400 border border-violet-500/20 animate-pulse"
                  >
                    ⚡ جارٍ الآن
                  </span>
                </div>
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white mt-1 group-hover:text-violet-600 dark:group-hover:text-violet-400 transition-colors line-clamp-1">
                  {{ challenge.title }}
                </h3>
              </div>
            </div>

            <!-- Context Actions Menu -->
            <div class="flex items-center gap-1" @click.stop>
              <button
                v-if="isOwner(challenge)"
                @click="openEditModal(challenge, $event)"
                class="w-8 h-8 rounded-lg text-slate-400 hover:text-violet-600 hover:bg-violet-50 dark:hover:bg-slate-800 flex items-center justify-center transition-all cursor-pointer"
                title="تعديل التحدي"
              >
                ✏️
              </button>
              <button
                v-if="isOwner(challenge)"
                @click="handleDeleteChallenge(challenge, $event)"
                class="w-8 h-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-slate-800 flex items-center justify-center transition-all cursor-pointer"
                title="حذف التحدي"
              >
                🗑️
              </button>
            </div>
          </div>

          <!-- Description / Motive -->
          <p v-if="challenge.description" class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 mb-3.5 leading-relaxed">
            {{ challenge.description }}
          </p>

          <!-- Reward & Prize Highlight Box -->
          <div 
            class="mb-3.5 p-2.5 sm:p-3 rounded-xl border transition-all flex items-center gap-2.5"
            :class="challenge.status === 'completed'
              ? 'bg-amber-500/10 border-amber-500/30 text-amber-900 dark:text-amber-200 shadow-sm'
              : 'bg-slate-50 dark:bg-slate-800/60 border-slate-200/80 dark:border-slate-700/60 text-slate-700 dark:text-slate-300'"
          >
            <span class="text-2xl shrink-0">{{ challenge.reward_icon || '🏆' }}</span>
            <div class="min-w-0 flex-1">
              <div class="flex items-center justify-between gap-1">
                <span class="text-[10px] font-black uppercase tracking-wider text-amber-600 dark:text-amber-400">
                  {{ challenge.status === 'completed' ? '🎉 الجائزة المستحقة' : '🎁 الجائزة عند الإكمال' }}
                </span>
                <span v-if="challenge.status === 'completed'" class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
                  تم الفتح! ✅
                </span>
              </div>
              <p class="text-xs sm:text-sm font-black truncate text-slate-900 dark:text-white">
                {{ challenge.reward_title || 'جائزة الإنجاز العظيم' }}
              </p>
            </div>
          </div>

          <!-- Progress Bar & Metrics -->
          <div class="space-y-1.5 mb-3.5">
            <div class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-400">
              <span class="flex items-center gap-1">
                <span>🗓️</span>
                <span>{{ getChallengeStats(challenge).completedDays }} من {{ challenge.total_days }} يوم</span>
              </span>
              <span class="font-extrabold text-violet-600 dark:text-violet-400">
                {{ getChallengeStats(challenge).percentage }}%
              </span>
            </div>
            
            <div class="w-full h-2.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden p-0.5">
              <div
                class="h-full rounded-full transition-all duration-500 ease-out bg-violet-600"
                :class="getGradientClass(challenge.color)"
                :style="{ width: `${getChallengeStats(challenge).percentage}%` }"
              ></div>
            </div>
          </div>
        </div>

        <!-- Card Footer Info & Social Snippet -->
        <div class="pt-3 border-t border-slate-100 dark:border-slate-800/80 space-y-2.5">
          <div class="flex items-center justify-between text-xs">
            <!-- Streak Badge -->
            <div class="flex items-center gap-1 text-amber-600 dark:text-amber-400 font-bold">
              <span>🔥</span>
              <span>{{ getChallengeStats(challenge).streak }} أيام متتالية</span>
            </div>

            <!-- Partner Badge -->
            <div v-if="challenge.partner_id" class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
              <span class="w-5 h-5 rounded-full bg-violet-100 dark:bg-violet-900/60 text-violet-600 dark:text-violet-300 flex items-center justify-center text-[10px] font-bold">
                {{ getPartnerName(challenge)?.[0] || '👥' }}
              </span>
              <span class="text-[11px] font-semibold truncate max-w-[100px]">
                {{ isOwner(challenge) ? `مع: ${getPartnerName(challenge)}` : `من: ${getCreatorName(challenge)}` }}
              </span>
            </div>
            <div v-else class="text-[11px] text-slate-400 font-medium">
              تحدي فردي 👤
            </div>
          </div>

          <!-- Latest cheer pill if exists -->
          <div 
            v-if="challenge.cheers && challenge.cheers.length > 0"
            class="text-[11px] px-2.5 py-1.5 rounded-xl bg-violet-50/80 dark:bg-violet-950/30 border border-violet-100 dark:border-violet-900/40 text-violet-800 dark:text-violet-300 flex items-center gap-1.5 truncate"
          >
            <span>{{ challenge.cheers[challenge.cheers.length - 1].reaction || '💬' }}</span>
            <span class="font-bold shrink-0">{{ challenge.cheers[challenge.cheers.length - 1].user?.name || 'مشجع' }}:</span>
            <span class="truncate">{{ challenge.cheers[challenge.cheers.length - 1].message || 'استمر يا بطل!' }}</span>
          </div>

          <!-- Action Button -->
          <button
            @click="openChallengeDetail(challenge)"
            class="w-full py-2 rounded-xl text-xs font-black text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-violet-600 hover:text-white dark:hover:bg-violet-600 dark:hover:text-white transition-all flex items-center justify-center gap-1.5 cursor-pointer"
          >
            <span>عرض ومتابعة الأيام</span>
            <span>←</span>
          </button>
        </div>

      </div>
    </div>

    <!-- Empty State -->
    <div 
      v-else
      class="bg-white/70 dark:bg-slate-900/70 p-8 sm:p-12 rounded-3xl border border-dashed border-slate-300 dark:border-slate-800 text-center flex flex-col items-center justify-center backdrop-blur-md"
    >
      <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-3xl bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center text-3xl sm:text-4xl mb-4 animate-bounce">
        🎯
      </div>
      <h3 class="text-base sm:text-lg font-black text-slate-800 dark:text-slate-100">لا توجد تحديات تطابق بحثك حالياً</h3>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1.5 max-w-sm mx-auto leading-relaxed">
        ابدأ الآن بإنشاء تحدي جديد، حدد عدد الأيام وشروط الإنجاز اليومية والجائزة التي تنتظرك في خط النهاية!
      </p>
      <button
        @click="openCreateModal"
        class="mt-5 px-5 py-2.5 rounded-xl font-black text-xs sm:text-sm text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 shadow-md shadow-violet-600/30 active:scale-95 transition-all flex items-center gap-2 cursor-pointer"
      >
        <span>+ إنشاء تحدي جديد الآن</span>
      </button>
    </div>

    <!-- 1. Bottom Sheet / Modal: Create / Edit Challenge -->
    <MobileBottomSheet
      :isOpen="isCreateModalOpen"
      @close="isCreateModalOpen = false"
      :title="editingChallengeId ? 'تعديل التحدي' : 'إنشاء تحدي جديد'"
      icon="🎯"
      maxWidth="max-w-xl"
    >
      <form @submit.prevent="submitChallengeForm" class="space-y-4 sm:space-y-5 text-right">
        <!-- Challenge Title -->
        <div>
          <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-1.5">
            اسم التحدي <span class="text-rose-500">*</span>
          </label>
          <input
            v-model="form.title"
            type="text"
            required
            placeholder="مثال: تحدي 30 يوم انضباط ولياقة بدنية"
            class="w-full px-3.5 py-2.5 rounded-xl text-xs sm:text-sm bg-slate-100 dark:bg-slate-800/80 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
          />
        </div>

        <!-- Description / Motivation -->
        <div>
          <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-1.5">
            الدافع أو وصف التحدي
          </label>
          <textarea
            v-model="form.description"
            rows="2"
            placeholder="اكتب هدفك من هذا التحدي والدافع الذي يحركك للالتزام به..."
            class="w-full px-3.5 py-2.5 rounded-xl text-xs sm:text-sm bg-slate-100 dark:bg-slate-800/80 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
          ></textarea>
        </div>

        <!-- Icon, Color & Category -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <!-- Icon Picker -->
          <div>
            <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-1.5">
              أيقونة التحدي
            </label>
            <div class="flex items-center gap-1.5 flex-wrap p-2 rounded-xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
              <button
                v-for="ic in iconOptions"
                :key="ic"
                type="button"
                @click="form.icon = ic"
                :class="[
                  'w-8 h-8 rounded-lg text-lg flex items-center justify-center transition-all cursor-pointer',
                  form.icon === ic ? 'bg-violet-600 text-white scale-110 shadow-sm' : 'hover:bg-slate-200 dark:hover:bg-slate-700'
                ]"
              >
                {{ ic }}
              </button>
            </div>
          </div>

          <!-- Color Theme Picker -->
          <div>
            <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-1.5">
              لون وهوية التحدي
            </label>
            <div class="flex items-center gap-2 flex-wrap p-2 rounded-xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700">
              <button
                v-for="col in colorOptions"
                :key="col.id"
                type="button"
                @click="form.color = col.id"
                :class="[
                  'w-7 h-7 rounded-full transition-transform cursor-pointer',
                  col.class,
                  getGradientClass(form.color) === col.class ? 'ring-2 ring-offset-2 ring-violet-500 scale-115' : 'hover:scale-105'
                ]"
                :title="col.name"
              ></button>
            </div>
          </div>
        </div>

        <!-- Category -->
        <div>
          <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-1.5">التصنيف</label>
          <div class="flex items-center gap-1.5 flex-wrap text-xs font-bold">
            <button
              v-for="cat in ['عام', 'صحة ولياقة', 'تطوير ذات', 'دراسة وتعلم', 'روحانيات', 'عمل وإنتاجية']"
              :key="cat"
              type="button"
              @click="form.category = cat"
              :class="[
                'px-3 py-1.5 rounded-xl transition-all cursor-pointer',
                form.category === cat
                  ? 'bg-violet-600 text-white shadow-sm'
                  : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
              ]"
            >
              {{ cat }}
            </button>
          </div>
        </div>

        <!-- Challenge Duration (عدد الأيام والتواريخ) -->
        <div class="p-3.5 rounded-2xl bg-violet-500/5 dark:bg-violet-950/20 border border-violet-500/20 space-y-3">
          <label class="block text-xs font-black text-violet-700 dark:text-violet-300">
            ⏳ مدة التحدي وعدد الأيام
          </label>

          <!-- Duration Presets -->
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            <button
              v-for="preset in durationPresets"
              :key="preset.days"
              type="button"
              @click="applyDurationPreset(preset.days)"
              :class="[
                'px-2.5 py-1.5 rounded-xl text-xs font-bold transition-all border cursor-pointer text-center',
                form.total_days === preset.days
                  ? 'bg-violet-600 text-white border-violet-600 shadow-sm'
                  : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:border-violet-400'
              ]"
            >
              {{ preset.label }}
            </button>
          </div>

          <!-- Date Range Inputs -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-1">
            <div>
              <span class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">تاريخ البدء</span>
              <input
                v-model="form.start_date"
                type="date"
                required
                @change="updateEndDateFromDays"
                class="w-full px-2.5 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-700 focus:outline-none focus:ring-1 focus:ring-violet-500"
              />
            </div>
            <div>
              <span class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">تاريخ الانتهاء</span>
              <input
                v-model="form.end_date"
                type="date"
                required
                @change="updateDaysFromEndDate"
                class="w-full px-2.5 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-700 focus:outline-none focus:ring-1 focus:ring-violet-500"
              />
            </div>
            <div>
              <span class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">إجمالي الأيام</span>
              <input
                v-model.number="form.total_days"
                type="number"
                min="1"
                required
                @input="updateEndDateFromDays"
                class="w-full px-2.5 py-2 rounded-xl text-xs bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-700 focus:outline-none focus:ring-1 focus:ring-violet-500 text-center font-bold"
              />
            </div>
          </div>
        </div>

        <!-- Reward & Prize ("احط الجائزة") -->
        <div class="p-3.5 rounded-2xl bg-amber-500/5 dark:bg-amber-950/20 border border-amber-500/20 space-y-3">
          <div class="flex items-center justify-between">
            <label class="text-xs font-black text-amber-700 dark:text-amber-300 flex items-center gap-1.5">
              <span>🎁</span>
              <span>جائزة إكمال التحدي</span>
            </label>
            <span class="text-[10px] text-amber-600 dark:text-amber-400 font-semibold">تُفتح تلقائياً عند 100% إنجاز!</span>
          </div>

          <div class="flex items-center gap-2">
            <!-- Prize Icon Picker -->
            <select
              v-model="form.reward_icon"
              class="px-2 py-2 rounded-xl text-base bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 focus:outline-none shrink-0"
            >
              <option v-for="pi in prizeIconOptions" :key="pi" :value="pi">{{ pi }}</option>
            </select>

            <input
              v-model="form.reward_title"
              type="text"
              placeholder="اسم الجائزة (مثال: رحلة نهاية أسبوع، شراء ساعة ذكية...)"
              class="w-full px-3 py-2 rounded-xl text-xs sm:text-sm bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
            />
          </div>

          <input
            v-model="form.reward_description"
            type="text"
            placeholder="ملاحظة أو شرط للمكافأة (اختياري)"
            class="w-full px-3 py-1.5 rounded-xl text-xs bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-700 focus:outline-none"
          />
        </div>

        <!-- Daily Conditions Checklist Definition ("وفي كل يوم نحط ليسته") -->
        <div class="space-y-2.5">
          <div class="flex items-center justify-between">
            <label class="text-xs font-black text-slate-700 dark:text-slate-300">
              📋 شروط إنجاز اليوم (قائمة المهام اليومية للتحدي) <span class="text-rose-500">*</span>
            </label>
            <span class="text-[10px] text-slate-500">يجب إتمام الكل لاحتساب اليوم</span>
          </div>

          <!-- Add Condition Input -->
          <div class="flex items-center gap-2">
            <input
              v-model="newConditionInput"
              type="text"
              placeholder="اكتب شرطاً يومياً جديداً..."
              @keydown.enter.prevent="addCondition"
              class="flex-1 px-3 py-2 rounded-xl text-xs sm:text-sm bg-slate-100 dark:bg-slate-800/80 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
            />
            <button
              type="button"
              @click="addCondition"
              class="px-3 py-2 rounded-xl text-xs font-bold text-white bg-violet-600 hover:bg-violet-700 transition cursor-pointer shrink-0"
            >
              + إضافة
            </button>
          </div>

          <!-- Quick Suggestion Chips -->
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-[10px] text-slate-400 font-bold">اقتراحات سريعة:</span>
            <button
              v-for="sugg in conditionSuggestions"
              :key="sugg"
              type="button"
              @click="addSuggestedCondition(sugg)"
              class="text-[10px] px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-violet-100 dark:hover:bg-violet-900/40 hover:text-violet-700 transition cursor-pointer"
            >
              {{ sugg }}
            </button>
          </div>

          <!-- Current Conditions List -->
          <div class="space-y-1.5 mt-2">
            <div
              v-for="(cond, idx) in form.conditions"
              :key="idx"
              class="flex items-center justify-between p-2 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-700/80 text-xs text-slate-800 dark:text-slate-200"
            >
              <div class="flex items-center gap-2">
                <span class="w-5 h-5 rounded-md bg-violet-100 dark:bg-violet-950 text-violet-600 dark:text-violet-300 text-[10px] font-black flex items-center justify-center">
                  {{ idx + 1 }}
                </span>
                <span>{{ cond }}</span>
              </div>
              <button
                type="button"
                @click="removeCondition(idx)"
                class="text-rose-500 hover:text-rose-700 p-1 text-sm cursor-pointer"
                title="حذف الشرط"
              >
                ✕
              </button>
            </div>
          </div>
        </div>

        <!-- Social Partner Selection ("يقدر يضيف عضو اخر فالسيستم للتحدي بتاعه") -->
        <div class="p-3.5 rounded-2xl bg-slate-100/80 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 space-y-2">
          <label class="block text-xs font-black text-slate-800 dark:text-slate-200">
            👥 مشاركة التحدي مع شريك (اختياري)
          </label>
          <p class="text-[11px] text-slate-500 dark:text-slate-400">
            اختر عضواً من الفريق لمتابعة إنجازك وتبادل الرسائل التشجيعية والتفاعل الحماسي معك!
          </p>

          <select
            v-model="form.partner_id"
            class="w-full px-3 py-2 rounded-xl text-xs sm:text-sm bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
          >
            <option :value="null">بدون شريك (تحدي فردي شخصي)</option>
            <option
              v-for="u in (store.users || []).filter(u => u.id !== store.currentUser?.id)"
              :key="u.id"
              :value="u.id"
            >
              {{ u.name }} ({{ u.email }})
            </option>
          </select>
        </div>

        <!-- Submit Button -->
        <div class="pt-2 flex items-center justify-end gap-2.5">
          <button
            type="button"
            @click="isCreateModalOpen = false"
            class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
          >
            إلغاء
          </button>
          <button
            type="submit"
            class="px-5 py-2.5 rounded-xl text-xs sm:text-sm font-black text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 shadow-md shadow-violet-600/30 transition cursor-pointer"
          >
            {{ editingChallengeId ? 'حفظ التعديلات' : 'إطلاق التحدي 🚀' }}
          </button>
        </div>
      </form>
    </MobileBottomSheet>

    <!-- 2. Full Challenge Detail & Day Roadmap Tracker Modal -->
    <MobileBottomSheet
      :isOpen="isDetailModalOpen"
      @close="isDetailModalOpen = false"
      :title="activeChallenge ? activeChallenge.title : 'تفاصيل التحدي'"
      icon="🎯"
      maxWidth="max-w-4xl"
    >
      <div v-if="activeChallenge" class="space-y-5 text-right">
        
        <!-- Hero Header Card with Banner Gradient -->
        <div 
          class="relative p-5 sm:p-6 rounded-3xl text-white overflow-hidden shadow-xl bg-violet-600"
          :class="getGradientClass(activeChallenge.color)"
        >
          <!-- Decorative subtle ambient glow overlay -->
          <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
          <div class="absolute -left-10 -top-10 w-48 h-48 bg-black/10 rounded-full blur-2xl pointer-events-none"></div>

          <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
              <span class="text-4xl sm:text-5xl shrink-0 p-3 rounded-2xl bg-black/25 backdrop-blur-md shadow-inner border border-white/20">
                {{ activeChallenge.icon || '🎯' }}
              </span>
              <div>
                <div class="flex items-center gap-2 flex-wrap mb-1.5">
                  <span class="px-2.5 py-0.5 rounded-full bg-black/30 backdrop-blur-md text-[11px] font-extrabold border border-white/20 text-white shadow-sm">
                    {{ activeChallenge.category || 'عام' }}
                  </span>
                  <span 
                    v-if="activeChallenge.status === 'completed'"
                    class="px-2.5 py-0.5 rounded-full bg-emerald-400 text-emerald-950 text-[11px] font-black shadow-sm"
                  >
                    🏆 مكتمل 100%
                  </span>
                  <span v-else class="px-2.5 py-0.5 rounded-full bg-black/30 backdrop-blur-md text-[11px] font-bold border border-white/20 text-white shadow-sm">
                    اليوم {{ currentDayData?.dayNumber || 1 }} من {{ activeChallenge.total_days }}
                  </span>
                </div>
                <h2 class="text-xl sm:text-2xl font-black text-white drop-shadow-sm">{{ activeChallenge.title }}</h2>
                <p v-if="activeChallenge.description" class="text-xs sm:text-sm text-white/90 mt-1 max-w-xl line-clamp-2 drop-shadow-sm">
                  {{ activeChallenge.description }}
                </p>
              </div>
            </div>

            <!-- Header Quick Stats -->
            <div class="flex items-center gap-3 shrink-0">
              <div class="px-4 py-2.5 rounded-2xl bg-black/25 backdrop-blur-md text-center border border-white/20 shadow-sm">
                <span class="text-[10px] text-white/80 block font-bold">الالتزام</span>
                <span class="text-lg sm:text-xl font-black text-white">{{ getChallengeStats(activeChallenge).percentage }}%</span>
              </div>
              <div class="px-4 py-2.5 rounded-2xl bg-black/25 backdrop-blur-md text-center border border-white/20 shadow-sm">
                <span class="text-[10px] text-white/80 block font-bold">السلسلة 🔥</span>
                <span class="text-lg sm:text-xl font-black text-white">{{ getChallengeStats(activeChallenge).streak }} يوم</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Prize Banner in Detail View -->
        <div 
          class="p-4 rounded-2xl border transition-all flex items-center justify-between gap-3"
          :class="activeChallenge.status === 'completed'
            ? 'bg-amber-500/15 border-amber-500/40 text-amber-950 dark:text-amber-100 shadow-md'
            : 'bg-slate-50 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700'"
        >
          <div class="flex items-center gap-3">
            <span class="text-3xl shrink-0">{{ activeChallenge.reward_icon || '🏆' }}</span>
            <div>
              <span class="text-[11px] font-black text-amber-600 dark:text-amber-400 block">
                {{ activeChallenge.status === 'completed' ? '🎉 استحققت جائزتك بنجاح!' : '🎁 الجائزة المنتظرة عند إتمام كل الأيام' }}
              </span>
              <h4 class="text-sm sm:text-base font-black text-slate-900 dark:text-white">
                {{ activeChallenge.reward_title || 'جائزة الإنجاز العظيم' }}
              </h4>
              <p v-if="activeChallenge.reward_description" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ activeChallenge.reward_description }}
              </p>
            </div>
          </div>

          <div v-if="activeChallenge.status === 'completed'" class="shrink-0">
            <span class="px-3 py-1.5 rounded-xl bg-amber-500 text-slate-950 font-black text-xs shadow-md">
              مفتوحة 🌟
            </span>
          </div>
          <div v-else class="text-xs text-slate-400 font-bold shrink-0">
            متبقي {{ activeChallenge.total_days - getChallengeStats(activeChallenge).completedDays }} يوم
          </div>
        </div>

        <!-- Days Grid & Timeline Navigator ("فينشيء ايام بعدد التحدي") -->
        <div class="space-y-3">
          <div class="flex items-center justify-between flex-wrap gap-2">
            <h3 class="text-sm sm:text-base font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
              <span>🗓️</span>
              <span>خريطة أيام التحدي ({{ activeChallenge.total_days }} يوم)</span>
            </h3>

            <!-- Day Filter Pills -->
            <div class="flex items-center gap-1 text-[11px] font-bold">
              <button
                @click="dayFilter = 'all'"
                :class="[
                  'px-2.5 py-1 rounded-lg transition cursor-pointer',
                  dayFilter === 'all' ? 'bg-violet-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'
                ]"
              >
                الكل
              </button>
              <button
                @click="dayFilter = 'completed'"
                :class="[
                  'px-2.5 py-1 rounded-lg transition cursor-pointer',
                  dayFilter === 'completed' ? 'bg-violet-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'
                ]"
              >
                المنجزة ({{ getChallengeStats(activeChallenge).completedDays }})
              </button>
              <button
                @click="dayFilter = 'remaining'"
                :class="[
                  'px-2.5 py-1 rounded-lg transition cursor-pointer',
                  dayFilter === 'remaining' ? 'bg-violet-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'
                ]"
              >
                المتبقية
              </button>
            </div>
          </div>

          <!-- Days Grid Matrix -->
          <div class="grid grid-cols-4 sm:grid-cols-7 md:grid-cols-10 gap-2 max-h-56 overflow-y-auto p-1.5 rounded-2xl bg-slate-100/70 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-700/80">
            <button
              v-for="d in filteredActiveChallengeDays"
              :key="d.dayNumber"
              type="button"
              @click="selectedDayNumber = d.dayNumber"
              :class="[
                'relative p-2 rounded-xl text-center transition-all flex flex-col items-center justify-between cursor-pointer border',
                selectedDayNumber === d.dayNumber
                  ? 'ring-2 ring-violet-500 scale-105 z-10 shadow-md'
                  : 'hover:scale-102',
                d.completed
                  ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-800 dark:text-emerald-300'
                  : (d.isToday
                      ? 'bg-violet-500/15 border-violet-500/40 text-violet-800 dark:text-violet-300'
                      : 'bg-white dark:bg-slate-800/80 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300')
              ]"
            >
              <!-- Day Number -->
              <span class="text-xs font-black">يوم {{ d.dayNumber }}</span>

              <!-- Status Icon -->
              <span class="my-0.5 text-sm">
                <span v-if="d.completed">✅</span>
                <span v-else-if="d.isToday" class="animate-pulse">⚡</span>
                <span v-else class="text-slate-300 dark:text-slate-600 text-xs">⭕</span>
              </span>

              <!-- Checked conditions counter -->
              <span class="text-[9px] font-bold text-slate-400">
                {{ d.checkedCount }}/{{ d.totalConditions }}
              </span>
            </button>
          </div>
        </div>

        <!-- Selected Day Checklist Card ("وفي كل يوم نحط ليسته لو اتعلم على شروط انهاء التحدي لكل لست يبقى تم انجاز اليوم") -->
        <div v-if="currentDayData" class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
          <div class="flex items-center justify-between flex-wrap gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
              <div class="flex items-center gap-2">
                <span class="text-lg font-black text-slate-900 dark:text-white">
                  اليوم {{ currentDayData.dayNumber }} من التحدي
                </span>
                <span 
                  v-if="currentDayData.isToday"
                  class="px-2 py-0.5 rounded-full text-[10px] font-black bg-violet-100 dark:bg-violet-950 text-violet-600 dark:text-violet-400"
                >
                  اليوم الحالي ⚡
                </span>
                <span 
                  v-if="currentDayData.completed"
                  class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400"
                >
                  تم إنجاز اليوم 🎉
                </span>
              </div>
              <span class="text-xs text-slate-400 block mt-0.5">{{ currentDayData.formattedDate }}</span>
            </div>

            <!-- Completion state banner -->
            <div class="text-left text-xs font-bold text-slate-500">
              <span>{{ currentDayData.checkedCount }} من {{ currentDayData.totalConditions }} شروط منجزة</span>
            </div>
          </div>

          <!-- Day Completion Celebration Banner -->
          <div 
            v-if="currentDayData.completed"
            class="p-3 rounded-xl bg-gradient-to-r from-emerald-500/10 to-teal-500/10 border border-emerald-500/30 text-emerald-800 dark:text-emerald-300 flex items-center gap-2.5 text-xs font-bold"
          >
            <span class="text-2xl">🎉</span>
            <div>
              <span class="font-black block">عاش يا بطل! تم إنجاز شروط اليوم بالكامل!</span>
              <span class="text-[11px] opacity-80">تم احتساب هذا اليوم بنجاح ورفع نسبة التزامك بالتحدي.</span>
            </div>
          </div>

          <!-- Checklist Items for the Selected Day -->
          <div class="space-y-2">
            <div
              v-for="(condition, cIdx) in (activeChallenge.conditions || [])"
              :key="cIdx"
              @click="toggleCondition(cIdx)"
              :class="[
                'p-3 rounded-xl border transition-all flex items-center justify-between gap-3 cursor-pointer select-none',
                currentDayData.items[cIdx]
                  ? 'bg-emerald-50/80 dark:bg-emerald-950/20 border-emerald-500/40 text-emerald-900 dark:text-emerald-200'
                  : 'bg-slate-50 dark:bg-slate-800/60 border-slate-200/80 dark:border-slate-700/80 text-slate-800 dark:text-slate-200 hover:border-violet-400'
              ]"
            >
              <div class="flex items-center gap-3">
                <!-- Checkbox -->
                <div 
                  class="w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all shrink-0"
                  :class="currentDayData.items[cIdx]
                    ? 'bg-emerald-500 border-emerald-500 text-white shadow-sm'
                    : 'border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800'"
                >
                  <span v-if="currentDayData.items[cIdx]" class="text-xs font-black">✓</span>
                </div>

                <span 
                  class="text-xs sm:text-sm font-bold"
                  :class="{ 'line-through opacity-70': currentDayData.items[cIdx] }"
                >
                  {{ condition }}
                </span>
              </div>

              <span 
                class="text-[11px] font-bold px-2 py-0.5 rounded-md"
                :class="currentDayData.items[cIdx] ? 'bg-emerald-500/15 text-emerald-600' : 'bg-slate-200/70 dark:bg-slate-700 text-slate-500'"
              >
                {{ currentDayData.items[cIdx] ? 'تم' : 'متبقي' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Social Encouragement & Cheer Section ("رسائل تشجيعية وهكذا") -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-sm sm:text-base font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
              <span>💬</span>
              <span>لوحة التشجيع والتفاعل الحماسي</span>
            </h3>
            <span class="text-xs text-slate-400">
              {{ activeChallenge.partner_id ? `بينك وبين ${getPartnerName(activeChallenge)}` : 'سجل تشجيع التحدي' }}
            </span>
          </div>

          <!-- Quick Cheers Chips -->
          <div class="space-y-1.5">
            <span class="text-[11px] font-bold text-slate-500 block">إرسال تشجيع سريع بنقرة واحدة:</span>
            <div class="flex items-center gap-1.5 flex-wrap">
              <button
                v-for="qc in quickCheers"
                :key="qc"
                type="button"
                @click="handleSendCheer(qc)"
                class="px-2.5 py-1.5 rounded-xl text-xs font-bold bg-violet-50 dark:bg-violet-950/40 text-violet-700 dark:text-violet-300 border border-violet-200/60 dark:border-violet-800/40 hover:bg-violet-600 hover:text-white transition cursor-pointer"
              >
                {{ qc }}
              </button>
            </div>
          </div>

          <!-- Custom Message & Reaction Input -->
          <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-2.5">
            <!-- Reaction Selector -->
            <div class="flex items-center gap-2">
              <span class="text-xs font-bold text-slate-500">التفاعل:</span>
              <div class="flex items-center gap-1">
                <button
                  v-for="r in reactions"
                  :key="r"
                  type="button"
                  @click="selectedReaction = r"
                  :class="[
                    'w-7 h-7 rounded-lg text-sm flex items-center justify-center transition cursor-pointer',
                    selectedReaction === r ? 'bg-white dark:bg-slate-700 shadow-sm scale-110' : 'hover:bg-slate-200 dark:hover:bg-slate-700/60'
                  ]"
                >
                  {{ r }}
                </button>
              </div>
            </div>

            <!-- Text Input & Send Button -->
            <div class="flex items-center gap-2">
              <input
                v-model="cheerMessage"
                type="text"
                placeholder="اكتب رسالة تشجيعية مخصصة..."
                @keydown.enter.prevent="() => handleSendCheer()"
                class="flex-1 px-3 py-2 rounded-xl text-xs sm:text-sm bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-violet-500/40"
              />
              <button
                type="button"
                :disabled="isSendingCheer || (!cheerMessage.trim() && !selectedReaction)"
                @click="() => handleSendCheer()"
                class="px-4 py-2 rounded-xl text-xs font-black text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 disabled:opacity-50 transition cursor-pointer shrink-0"
              >
                {{ isSendingCheer ? 'جاري الإرسال...' : 'إرسال 🚀' }}
              </button>
            </div>
          </div>

          <!-- Cheers Feed Log -->
          <div v-if="activeChallenge.cheers && activeChallenge.cheers.length > 0" class="space-y-2 max-h-48 overflow-y-auto p-1">
            <div
              v-for="cheer in [...activeChallenge.cheers].reverse()"
              :key="cheer.id"
              class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-800 flex items-start gap-2.5 text-xs"
            >
              <span class="text-xl shrink-0">{{ cheer.reaction || '💬' }}</span>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-1 mb-0.5">
                  <span class="font-black text-slate-900 dark:text-slate-100">
                    {{ cheer.user?.name || 'عضو' }}
                  </span>
                  <span class="text-[10px] text-slate-400">
                    {{ cheer.created_at ? new Date(cheer.created_at).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' }) : '' }}
                  </span>
                </div>
                <p v-if="cheer.message" class="text-slate-700 dark:text-slate-300 leading-relaxed">
                  {{ cheer.message }}
                </p>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-4 text-xs text-slate-400">
            لا توجد رسائل تشجيعية بعد. كن أول من يشعل الحماس! 🔥
          </div>

        </div>

      </div>
    </MobileBottomSheet>

    <!-- 3. Celebration Victory Modal on 100% Completion -->
    <div 
      v-if="isCelebrationModalOpen"
      class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-md animate-fade-in"
    >
      <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 max-w-md w-full border border-amber-500/40 shadow-2xl text-center space-y-4">
        <div class="w-20 h-20 mx-auto rounded-3xl bg-amber-500/20 text-amber-500 flex items-center justify-center text-5xl animate-bounce shadow-lg">
          🏆
        </div>
        <h3 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white">
          ألف مبارك! أنجزت التحدي كاملاً! 🎉
        </h3>
        <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
          لقد أثبت انضباطاً أسطورياً وأتممت جميع أيام التحدي بنجاح 100%. حان وقت الاستمتاع بجائزتك المستحقة!
        </p>
        
        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-900 dark:text-amber-200">
          <span class="text-3xl block mb-1">{{ activeChallenge?.reward_icon || '🎁' }}</span>
          <span class="text-base font-black block">{{ activeChallenge?.reward_title || 'جائزتك المستحقة' }}</span>
        </div>

        <button
          @click="isCelebrationModalOpen = false"
          class="w-full py-3 rounded-xl font-black text-sm text-white bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 shadow-lg shadow-amber-500/30 transition cursor-pointer"
        >
          استلام الجائزة وإغلاق 🌟
        </button>
      </div>
    </div>

  </div>
</template>
