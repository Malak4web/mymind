<script setup>
import { store } from '../store'
import { computed, ref, onUnmounted, onMounted } from 'vue'
import MentionInput from './MentionInput.vue'
import MentionText from './MentionText.vue'

// Local directive for autofocusing Trello quick add textarea
const vFocus = {
  mounted: (el) => el.focus()
}

const activeProject = computed(() => store.projects.find(p => p.id === store.activeProjectId))
const projectTasks = computed(() => store.tasks.filter(t => t.projectId === store.activeProjectId))

// Mobile Column Filter State
const selectedMobileStatus = ref('all')
const setMobileStatusFilter = (status) => {
  selectedMobileStatus.value = status
}

const toggleTaskStatus = async (task, e) => {
  if (e) e.stopPropagation()
  const statuses = activeProjectStatuses.value
  if (!statuses || statuses.length === 0) return
  const currentIndex = statuses.indexOf(task.status)
  const nextStatus = statuses[(currentIndex + 1) % statuses.length]
  await store.updateTask(task.id, {
    title: task.title,
    description: task.description,
    status: nextStatus,
    startDate: task.startDate,
    deadline: task.deadline
  })
}

// Drag and drop state
const draggedTaskId = ref(null)
const activeDragOverColumn = ref(null)

// Quick Add States
const activeQuickAddColumn = ref(null)
const quickAddTitle = ref('')

// Paste Multiline Modal States
const showPasteModal = ref(false)
const pastedLines = ref([])
const pasteTargetStatus = ref('')

const triggerQuickAdd = (statusName) => {
  activeQuickAddColumn.value = statusName
  quickAddTitle.value = ''
}

const cancelQuickAdd = () => {
  activeQuickAddColumn.value = null
  quickAddTitle.value = ''
}

const openFullModalFromQuickAdd = (statusName) => {
  store.selectedTaskIdForModal = null
  store.prefilledTaskTitle = quickAddTitle.value.trim()
  store.prefilledTaskStatus = statusName
  store.isTaskModalOpen = true
  cancelQuickAdd()
}

const submitQuickAdd = async (statusName) => {
  const title = quickAddTitle.value.trim()
  if (!title) return
  
  await store.createTask(
    title,
    '', // description
    statusName, // status
    null, // start_date
    null, // deadline
    {} // custom fields
  )
  
  quickAddTitle.value = ''
}

const handlePaste = (event, statusName) => {
  const text = event.clipboardData.getData('text')
  const lines = text.split(/\r?\n/).map(l => l.trim()).filter(Boolean)
  if (lines.length > 1) {
    event.preventDefault()
    pastedLines.value = lines
    pasteTargetStatus.value = statusName
    showPasteModal.value = true
  }
}

const createPastedAsSeparate = async () => {
  showPasteModal.value = false
  const lines = pastedLines.value
  pastedLines.value = []
  
  for (const line of lines) {
    if (line) {
      await store.createTask(
        line,
        '',
        pasteTargetStatus.value,
        null,
        null,
        {}
      )
    }
  }
  cancelQuickAdd()
}

const createPastedAsSingle = () => {
  showPasteModal.value = false
  quickAddTitle.value = pastedLines.value.join(' ')
  pastedLines.value = []
}

const cancelPasteModal = () => {
  showPasteModal.value = false
  pastedLines.value = []
}

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

const triggerCelebration = () => {
  const colors = ['#8B5CF6', '#EC4899', '#3B82F6', '#10B981', '#F59E0B', '#EF4444']
  const shapes = ['circle', 'square', 'triangle', 'star']
  
  const container = document.createElement('div')
  container.className = 'fixed inset-0 pointer-events-none z-50 overflow-hidden'
  document.body.appendChild(container)
  
  for (let i = 0; i < 80; i++) {
    const particle = document.createElement('div')
    const color = colors[Math.floor(Math.random() * colors.length)]
    const shape = shapes[Math.floor(Math.random() * shapes.length)]
    
    particle.style.position = 'absolute'
    particle.style.width = `${Math.random() * 8 + 6}px`
    particle.style.height = `${Math.random() * 8 + 6}px`
    particle.style.backgroundColor = color
    particle.style.left = `${Math.random() * 100}vw`
    particle.style.bottom = '-10px'
    
    if (shape === 'circle') {
      particle.style.borderRadius = '50%'
    } else if (shape === 'triangle') {
      particle.style.width = '0'
      particle.style.height = '0'
      particle.style.backgroundColor = 'transparent'
      particle.style.borderLeft = '6px solid transparent'
      particle.style.borderRight = '6px solid transparent'
      particle.style.borderBottom = `12px solid ${color}`
    } else if (shape === 'star') {
      particle.style.clipPath = 'polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%)'
    }
    
    container.appendChild(particle)
    
    const duration = Math.random() * 2 + 1.5
    const delay = Math.random() * 0.4
    const horizontalShift = (Math.random() - 0.5) * 250
    
    particle.animate([
      { transform: 'translate(0, 0) rotate(0deg)', bottom: '-10px', opacity: 1 },
      { transform: `translate(${horizontalShift}px, -110vh) rotate(${Math.random() * 360}deg)`, opacity: 0 }
    ], {
      duration: duration * 1000,
      delay: delay * 1000,
      easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
      fill: 'forwards'
    })
  }
  
  setTimeout(() => {
    container.remove()
  }, 4000)
}

let activeAudioContexts = []

const playSuccessSound = () => {
  const AudioContextClass = window.AudioContext || window.webkitAudioContext
  if (!AudioContextClass) return
  try {
    const ctx = new AudioContextClass()
    activeAudioContexts.push(ctx)
    const now = ctx.currentTime
    
    // 1. Massive Stadium Cheer & Applause Noise
    const bufferSize = ctx.sampleRate * 2.0 // 2 seconds of massive stadium cheer
    const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate)
    const data = buffer.getChannelData(0)
    for (let i = 0; i < bufferSize; i++) {
      data[i] = Math.random() * 2 - 1
    }
    
    const noise = ctx.createBufferSource()
    noise.buffer = buffer
    
    const noiseFilter = ctx.createBiquadFilter()
    noiseFilter.type = 'bandpass'
    noiseFilter.frequency.setValueAtTime(1000, now)
    noiseFilter.frequency.exponentialRampToValueAtTime(1600, now + 0.4)
    noiseFilter.frequency.exponentialRampToValueAtTime(600, now + 1.8)
    noiseFilter.Q.setValueAtTime(1.5, now)
    
    const noiseGain = ctx.createGain()
    noiseGain.gain.setValueAtTime(0, now)
    noiseGain.gain.linearRampToValueAtTime(0.5, now + 0.1) // Loud burst of crowd
    noiseGain.gain.exponentialRampToValueAtTime(0.001, now + 1.9)
    
    noise.connect(noiseFilter)
    noiseFilter.connect(noiseGain)
    noiseGain.connect(ctx.destination)
    noise.start(now)
    
    // 2. Triumphant detuned brass trumpet synthesizer
    const playTrumpet = (freq, startTime, duration, vol) => {
      const osc1 = ctx.createOscillator()
      const osc2 = ctx.createOscillator()
      const gainNode = ctx.createGain()
      const filter = ctx.createBiquadFilter()
      
      osc1.type = 'sawtooth'
      osc2.type = 'sawtooth'
      
      // Detune oscillators for a rich, fat, ensemble sound
      osc1.frequency.setValueAtTime(freq - 1.5, startTime)
      osc2.frequency.setValueAtTime(freq + 1.5, startTime)
      
      filter.type = 'lowpass'
      filter.frequency.setValueAtTime(200, startTime)
      filter.frequency.exponentialRampToValueAtTime(3200, startTime + 0.08) // Brass swipe
      filter.frequency.exponentialRampToValueAtTime(1100, startTime + duration)
      
      gainNode.gain.setValueAtTime(0, startTime)
      gainNode.gain.linearRampToValueAtTime(vol, startTime + 0.05)
      gainNode.gain.exponentialRampToValueAtTime(0.001, startTime + duration)
      
      osc1.connect(filter)
      osc2.connect(filter)
      filter.connect(gainNode)
      gainNode.connect(ctx.destination)
      
      osc1.start(startTime)
      osc2.start(startTime)
      osc1.stop(startTime + duration)
      osc2.stop(startTime + duration)
    }
    
    // Epic C-major arpeggiated brass intro
    const tempo = 0.08
    playTrumpet(261.63, now, 0.4, 0.15)             // C4
    playTrumpet(329.63, now + tempo, 0.4, 0.15)     // E4
    playTrumpet(392.00, now + tempo * 2, 0.4, 0.15) // G4
    playTrumpet(523.25, now + tempo * 3, 0.5, 0.15) // C5
    playTrumpet(659.25, now + tempo * 4, 0.5, 0.15) // E5
    playTrumpet(783.99, now + tempo * 5, 0.8, 0.20) // G5
    
    // Massive final C-major orchestral chord hit!
    const chordTime = now + tempo * 6
    playTrumpet(261.63, chordTime, 1.2, 0.15) // C4
    playTrumpet(392.00, chordTime, 1.2, 0.15) // G4
    playTrumpet(523.25, chordTime, 1.2, 0.15) // C5
    playTrumpet(659.25, chordTime, 1.2, 0.15) // E5
    playTrumpet(783.99, chordTime, 1.2, 0.15) // G5
    playTrumpet(1046.50, chordTime, 1.2, 0.10) // C6
    
    // Glittering high pitch bells on top of the chord hit
    const playBell = (freq, startTime, duration) => {
      const osc = ctx.createOscillator()
      const gainNode = ctx.createGain()
      osc.type = 'sine'
      osc.frequency.setValueAtTime(freq, startTime)
      gainNode.gain.setValueAtTime(0, startTime)
      gainNode.gain.linearRampToValueAtTime(0.2, startTime + 0.02)
      gainNode.gain.exponentialRampToValueAtTime(0.001, startTime + duration)
      osc.connect(gainNode)
      gainNode.connect(ctx.destination)
      osc.start(startTime)
      osc.stop(startTime + duration)
    }
    
    playBell(1567.98, chordTime, 0.8) // G6 bell
    playBell(2093.00, chordTime + 0.1, 0.8) // C7 bell

    setTimeout(() => {
      if (ctx.state !== 'closed') {
        ctx.close().catch(() => {})
      }
      activeAudioContexts = activeAudioContexts.filter(c => c !== ctx)
    }, 3000)
    
  } catch (e) {
    console.error("Audio Context initialization failed or user interaction required:", e)
  }
}

onUnmounted(() => {
  activeAudioContexts.forEach(ctx => {
    if (ctx && ctx.state !== 'closed') {
      ctx.close().catch(() => {})
    }
  })
  activeAudioContexts = []
})

const toggleTaskCompletion = async (task, event) => {
  const isChecked = event.target.checked
  const targetStatus = isChecked ? 'مكتمل' : (activeProject.value?.statuses[0] || 'بانتظار البدء')
  
  await store.updateTask(task.id, {
    title: task.title,
    description: task.description,
    status: targetStatus,
    startDate: task.startDate,
    deadline: task.deadline
  })

  if (isChecked) {
    triggerCelebration()
    playSuccessSound()
  }
}

const handleDragStart = (taskId) => {
  draggedTaskId.value = taskId
}

const handleDragOver = (e, statusName) => {
  e.preventDefault()
  if (activeDragOverColumn.value !== statusName) {
    activeDragOverColumn.value = statusName
  }
}

const handleDragLeave = () => {
  activeDragOverColumn.value = null
}

const handleDrop = async (statusName) => {
  if (!draggedTaskId.value) return
  
  // If the dragged task is part of the selected tasks group, move all of them together
  if (selectedTaskIds.value.includes(draggedTaskId.value)) {
    for (const id of selectedTaskIds.value) {
      const task = store.tasks.find(t => String(t.id) === String(id))
      if (task && task.status !== statusName) {
        await store.updateTask(task.id, {
          title: task.title,
          description: task.description,
          status: statusName,
          startDate: task.startDate,
          deadline: task.deadline
        })
      }
    }
    selectedTaskIds.value = []
  } else {
    // Single card drag
    const task = store.tasks.find(t => String(t.id) === String(draggedTaskId.value))
    if (task && task.status !== statusName) {
      await store.updateTask(task.id, {
        title: task.title,
        description: task.description,
        status: statusName,
        startDate: task.startDate,
        deadline: task.deadline
      })
    }
  }
  
  draggedTaskId.value = null
  activeDragOverColumn.value = null
}

const openEditTask = (taskId) => {
  store.selectedTaskIdForModal = taskId
  store.isTaskModalOpen = true
}

const triggerNewTask = (status) => {
  store.selectedTaskIdForModal = null
  store.isTaskModalOpen = true
}

const getTasksByStatus = (statusName) => {
  return projectTasks.value.filter(t => t.status === statusName)
}

// Map color indicators for Kanban columns
const getColumnColorClass = (status) => {
  const s = status.toLowerCase()
  if (s.includes('todo') || s.includes('to do') || s.includes('بدء') || s.includes('بانتظار')) {
    return 'border-t-2 border-t-sky-400'
  }
  if (s.includes('progress') || s.includes('عمل') || s.includes('approved') || s.includes('نشط')) {
    return 'border-t-2 border-t-violet-500'
  }
  if (s.includes('review') || s.includes('مراجعة') || s.includes('schedule') || s.includes('مجدول')) {
    return 'border-t-2 border-t-amber-400'
  }
  if (s.includes('done') || s.includes('مكتمل') || s.includes('منشور') || s.includes('publish') || s.includes('complete')) {
    return 'border-t-2 border-t-emerald-400'
  }
  return 'border-t-2 border-t-slate-350 dark:border-t-slate-700'
}

// Icon getter based on custom field name key
const getCustomFieldIcon = (fieldName) => {
  const name = fieldName.toLowerCase()
  if (name.includes('url') || name.includes('link') || name.includes('رابط') || name.includes('figma')) {
    return `<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>`
  }
  if (name.includes('hour') || name.includes('time') || name.includes('ساعات') || name.includes('مدة')) {
    return `<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`
  }
  if (name.includes('budget') || name.includes('spend') || name.includes('ميزانية') || name.includes('تكلفة') || name.includes('$')) {
    return `<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M12 16v1" /></svg>`
  }
  return `<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>`
}

// Bulk Actions States
const selectedTaskIds = ref([])

const isAllSelected = computed(() => {
  return projectTasks.value.length > 0 && selectedTaskIds.value.length === projectTasks.value.length
})

const toggleSelectAllKanban = () => {
  if (isAllSelected.value) {
    selectedTaskIds.value = []
  } else {
    selectedTaskIds.value = projectTasks.value.map(t => t.id)
  }
}

const isColumnAllSelected = (statusName) => {
  const colTasks = getTasksByStatus(statusName)
  if (colTasks.length === 0) return false
  return colTasks.every(t => selectedTaskIds.value.includes(t.id))
}

const toggleSelectColumn = (statusName, event) => {
  const colTasks = getTasksByStatus(statusName)
  const isChecked = event.target.checked
  colTasks.forEach(t => {
    const idx = selectedTaskIds.value.indexOf(t.id)
    if (isChecked) {
      if (idx === -1) selectedTaskIds.value.push(t.id)
    } else {
      if (idx > -1) selectedTaskIds.value.splice(idx, 1)
    }
  })
}

const activeProjectStatuses = computed(() => activeProject.value?.statuses || store.globalStatuses)
const otherProjects = computed(() => store.projects.filter(p => p.id !== store.activeProjectId && !p.isDeleted))

const bulkChangeStatus = async (newStatus) => {
  if (!newStatus) return
  try {
    for (const id of selectedTaskIds.value) {
      const task = projectTasks.value.find(t => String(t.id) === String(id))
      if (task) {
        await store.updateTask(id, {
          title: task.title,
          description: task.description,
          status: newStatus,
          startDate: task.startDate,
          deadline: task.deadline
        })
      }
    }
    selectedTaskIds.value = []
    store.addNotification('تعديل جماعي', 'تم تحديث حالة المهام المحددة بنجاح.')
  } catch (e) {
    console.error(e)
  }
}

const bulkMoveToProject = async (targetProjId) => {
  if (!targetProjId) return
  const proj = store.projects.find(p => p.id === parseInt(targetProjId))
  if (!proj) return
  if (!confirm(`هل أنت متأكد من نقل المهام المحددة إلى المشروع "${proj.name}"؟`)) return

  try {
    for (const id of selectedTaskIds.value) {
      const task = projectTasks.value.find(t => String(t.id) === String(id))
      if (task) {
        await store.updateTask(id, {
          title: task.title,
          description: task.description,
          status: proj.statuses[0] || 'بانتظار البدء',
          startDate: task.startDate,
          deadline: task.deadline,
          projectId: proj.id
        })
      }
    }
    selectedTaskIds.value = []
    store.addNotification('نقل جماعي', 'تم نقل المهام المحددة إلى المشروع الآخر بنجاح.')
  } catch (e) {
    console.error(e)
  }
}

const bulkDelete = async () => {
  if (selectedTaskIds.value.length === 0) return
  if (!confirm(`هل أنت متأكد من حذف المهام المحددة البالغ عددها (${selectedTaskIds.value.length}) نهائياً؟`)) return

  try {
    for (const id of selectedTaskIds.value) {
      await store.deleteTask(id)
    }
    selectedTaskIds.value = []
    store.addNotification('حذف جماعي', 'تم حذف المهام المحددة بنجاح.')
  } catch (e) {
    console.error(e)
  }
}
const quickDeleteTask = async (task) => {
  if (!task) return
  if (confirm(`هل أنت متأكد من حذف المهمة "${task.title}"؟`)) {
    await store.deleteTask(task.id)
  }
}

// 3-Dots Task Card Actions Menu State
const activeTaskMenuId = ref(null)

const toggleTaskMenu = (taskId, e) => {
  if (e) e.stopPropagation()
  activeTaskMenuId.value = activeTaskMenuId.value === taskId ? null : taskId
}

const closeTaskMenu = () => {
  activeTaskMenuId.value = null
}

onMounted(() => {
  window.addEventListener('click', closeTaskMenu)
})

onUnmounted(() => {
  window.removeEventListener('click', closeTaskMenu)
})
</script>

<template>
  <div class="space-y-6 text-right" v-if="activeProject">
    <!-- Kanban Header Actions -->
    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-850 pb-4">
      <div>
        <h2 class="text-base font-extrabold text-slate-850 dark:text-slate-100 uppercase tracking-widest flex items-center gap-2">
          لوحة المهام (Kanban)
          <span class="text-xs font-bold text-slate-400 font-sans">({{ projectTasks.length }} مهام)</span>
        </h2>
      </div>

      <div class="flex items-center space-x-2 space-x-reverse">
        <button 
          @click="toggleSelectAllKanban"
          class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-850 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold px-3 py-1.5 rounded-xl text-xs transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center"
        >
          {{ isAllSelected ? 'إلغاء تحديد الكل' : 'تحديد جميع المهام' }}
        </button>
      </div>
    </div>

    <!-- Mobile Column Filter Tabs (lg:hidden) -->
    <div class="lg:hidden flex gap-2 overflow-x-auto no-scrollbar py-2 shrink-0 border-b border-slate-100 dark:border-slate-800 -mt-2 mb-2">
      <!-- All Columns Pill -->
      <button 
        @click="setMobileStatusFilter('all')"
        :class="[
          selectedMobileStatus === 'all'
            ? 'bg-violet-600 text-white shadow-md rounded-full px-3 py-1.5 text-xs font-medium flex items-center gap-1.5 shrink-0 border border-violet-600 cursor-pointer min-h-[44px] min-w-[44px] justify-center'
            : 'bg-slate-100/80 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-full px-3 py-1.5 text-xs font-medium flex items-center gap-1.5 shrink-0 hover:bg-slate-200 transition cursor-pointer min-h-[44px] min-w-[44px] justify-center'
        ]"
      >
        <span>الكل</span>
        <span 
          :class="[
            'px-1.5 py-0.2 rounded-full text-[10px] font-black',
            selectedMobileStatus === 'all' ? 'bg-white/20 text-white' : 'bg-white dark:bg-slate-900 text-violet-600 dark:text-violet-400'
          ]"
        >
          {{ projectTasks.length }}
        </span>
      </button>

      <!-- Specific Column Pills -->
      <button 
        v-for="status in activeProject.statuses" 
        :key="'tab-' + status"
        @click="setMobileStatusFilter(status)"
        :class="[
          selectedMobileStatus === status
            ? 'bg-violet-600 text-white shadow-md rounded-full px-3 py-1.5 text-xs font-medium flex items-center gap-1.5 shrink-0 border border-violet-600 cursor-pointer min-h-[44px] min-w-[44px] justify-center'
            : 'bg-slate-100/80 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-full px-3 py-1.5 text-xs font-medium flex items-center gap-1.5 shrink-0 hover:bg-slate-200 transition cursor-pointer min-h-[44px] min-w-[44px] justify-center'
        ]"
      >
        <span>{{ status }}</span>
        <span 
          :class="[
            'px-1.5 py-0.2 rounded-full text-[10px] font-black',
            selectedMobileStatus === status ? 'bg-white/20 text-white' : 'bg-white dark:bg-slate-900 text-violet-600 dark:text-violet-400'
          ]"
        >
          {{ getTasksByStatus(status).length }}
        </span>
      </button>
    </div>

    <!-- Columns Container -->
    <div 
      class="flex overflow-x-auto snap-x snap-mandatory gap-4 pb-4 scrollbar-hide lg:grid lg:grid-cols-4 lg:overflow-visible items-start"
    >
      <div 
        v-for="status in activeProject.statuses" 
        :key="status"
        @dragover="handleDragOver($event, status)"
        @dragleave="handleDragLeave"
        @drop="handleDrop(status)"
        :class="[
          'bg-slate-100/40 dark:bg-slate-900/40 backdrop-blur-md border border-slate-200/50 dark:border-slate-800/60 rounded-2xl p-4 flex flex-col space-y-4 min-h-[480px] transition-all duration-300 w-[85vw] sm:w-80 shrink-0 lg:w-full snap-center shadow-sm',
          activeDragOverColumn === status ? 'border-violet-500/80 bg-violet-500/[0.03] dark:bg-violet-900/[0.04]' : '',
          getColumnColorClass(status),
          selectedMobileStatus !== 'all' && selectedMobileStatus !== status ? 'hidden lg:flex' : 'flex'
        ]"
      >

        <!-- Column Header -->
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-850 pb-2">
          <div class="flex items-center space-x-2 space-x-reverse">
            <span class="text-xs font-extrabold text-slate-855 dark:text-slate-205 uppercase tracking-wider">{{ status }}</span>
            <span class="bg-slate-150 dark:bg-slate-855 text-slate-700 dark:text-slate-350 font-sans text-xs font-extrabold px-2 py-0.5 rounded-full">
              {{ getTasksByStatus(status).length }}
            </span>
          </div>
          <div class="flex items-center space-x-1 space-x-reverse">
            <div class="min-h-[44px] min-w-[44px] flex items-center justify-center">
              <input 
                type="checkbox"
                :checked="isColumnAllSelected(status)"
                @change="toggleSelectColumn(status, $event)"
                class="rounded border-slate-300 dark:border-slate-805 text-violet-650 focus:ring-violet-500 cursor-pointer h-4 w-4"
                title="تحديد كل مهام هذا العمود"
              />
            </div>
            <button 
              @click="triggerQuickAdd(status)"
              class="min-h-[44px] min-w-[44px] flex items-center justify-center text-slate-455 hover:text-violet-600 dark:hover:text-violet-400 p-2.5 rounded-xl hover:bg-white dark:hover:bg-slate-900 transition cursor-pointer"
              title="إضافة مهمة سريعة كـ Trello"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Cards List -->
        <div class="space-y-3 flex-1 overflow-y-auto max-h-[560px] pr-0.5 scrollbar-hide">
          <div 
            v-for="task in getTasksByStatus(status)" 
            :key="task.id"
            draggable="true"
            @dragstart="handleDragStart(task.id)"
            @click="store.openTaskInspector(task.id)"
            @dblclick="openEditTask(task.id)"
            class="glass-card-hover rounded-2xl p-3.5 shadow-sm hover:-translate-y-1 hover:shadow-glass-glow transition-all duration-300 btn-touch-active cursor-grab active:cursor-grabbing select-none relative group space-y-2 bg-white/90 dark:bg-slate-900/90 border border-slate-200/80 dark:border-slate-800/80"
            :title="task.title"
          >
            <!-- Card Header Row: Completion Checkbox + Title + Bulk Select + 3-Dots Menu -->
            <div class="flex items-center justify-between gap-2 w-full min-w-0">
              <!-- Left: Checkbox + Full Title -->
              <div class="flex items-center gap-2 flex-1 min-w-0">
                <div class="shrink-0 flex items-center justify-center min-h-[32px] min-w-[32px]" @click.stop>
                  <input 
                    type="checkbox"
                    :checked="task.status === 'مكتمل'"
                    @change="toggleTaskCompletion(task, $event)"
                    class="rounded-full border-slate-350 dark:border-slate-800 text-emerald-500 focus:ring-emerald-500 cursor-pointer h-4.5 w-4.5 transition-all duration-200"
                    title="تحديد المهمة كمكتملة"
                  />
                </div>
                <div class="relative group/title flex-1 min-w-0">
                  <h4 
                    class="text-xs sm:text-sm font-extrabold text-slate-855 dark:text-slate-100 group-hover:text-violet-650 dark:group-hover:text-violet-400 transition duration-150 line-clamp-1 overflow-hidden text-ellipsis block leading-snug w-full"
                    :class="[task.status === 'مكتمل' ? 'line-through text-slate-400 dark:text-slate-500' : '']"
                    :title="task.title"
                  >
                    <MentionText :content="task.title" />
                  </h4>
                  <!-- Custom Floating Tooltip on Hover -->
                  <div class="absolute bottom-full right-0 mb-1.5 hidden group-hover/title:block z-50 pointer-events-none max-w-xs sm:max-w-sm">
                    <div class="bg-slate-900/95 dark:bg-slate-800/95 text-white text-xs font-medium px-3 py-1.5 rounded-xl shadow-xl border border-slate-700/50 backdrop-blur-md whitespace-normal break-words text-right dir-rtl">
                      {{ task.title }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Right: Bulk Select Checkbox & 3-Dots Action Menu -->
              <div class="flex items-center gap-1 shrink-0" @click.stop>
                <!-- Bulk selection checkbox -->
                <input 
                  type="checkbox" 
                  v-model="selectedTaskIds" 
                  :value="task.id" 
                  class="rounded border-slate-300 dark:border-slate-855 text-violet-650 focus:ring-violet-500 cursor-pointer h-3.5 w-3.5 transition-opacity"
                  :class="[selectedTaskIds.includes(task.id) ? 'opacity-100' : 'opacity-0 group-hover:opacity-100 focus:opacity-100']"
                  title="تحديد المهمة للعمليات الجماعية"
                />

                <!-- 3-Dots Action Menu Trigger -->
                <div class="relative">
                  <button 
                    @click="toggleTaskMenu(task.id, $event)"
                    class="p-1 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer flex items-center justify-center min-h-[32px] min-w-[32px]"
                    title="خيارات المهمة"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                    </svg>
                  </button>

                  <!-- Floating Dropdown Popover Menu -->
                  <Transition name="fade">
                    <div 
                      v-if="activeTaskMenuId === task.id" 
                      class="absolute left-0 top-full mt-1 z-30 w-36 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border border-slate-200 dark:border-slate-800 rounded-xl shadow-xl py-1 text-right animate-fade-in"
                      @click.stop
                    >
                      <button 
                        @click="closeTaskMenu(); openEditTask(task.id)"
                        class="w-full text-right px-3 py-1.5 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer flex items-center gap-2"
                      >
                        <span>✏️</span>
                        <span>تعديل المهمة</span>
                      </button>
                      <button 
                        @click="closeTaskMenu(); quickDeleteTask(task)"
                        class="w-full text-right px-3 py-1.5 text-xs font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-955/30 transition cursor-pointer flex items-center gap-2"
                      >
                        <span>🗑️</span>
                        <span>حذف المهمة</span>
                      </button>
                    </div>
                  </Transition>
                </div>
              </div>
            </div>

            <!-- Task Description Mention & Links Preview -->
            <div v-if="task.description" class="pt-0.5 text-[11px] text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed pr-6" @click.stop>
              <MentionText :content="task.description" />
            </div>

            <!-- Touch Interactive Card Footer: Status Switcher & Date Tag -->
            <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800/80 text-[11px] font-mono text-slate-400 gap-2 flex-wrap" @click.stop>
              <button
                @click="toggleTaskStatus(task, $event)"
                class="px-2.5 py-1 rounded-lg text-[10px] font-bold border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-violet-50 dark:hover:bg-violet-900/30 hover:text-violet-600 transition min-h-[44px] min-w-[44px] flex items-center justify-center cursor-pointer"
                title="انقر للتنقل السريع بين الحالات"
              >
                {{ task.status }}
              </button>

              <span v-if="task.deadline" class="text-[10px] font-sans font-bold text-slate-500 dark:text-slate-400">
                📅 {{ task.deadline }}
              </span>
            </div>

            <!-- Soft hover overlay -->
            <div class="absolute inset-0 bg-violet-500/[0.01] dark:bg-violet-400/[0.01] opacity-0 group-hover:opacity-100 rounded-xl pointer-events-none transition duration-200"></div>
          </div>

          <div v-if="getTasksByStatus(status).length === 0 && activeQuickAddColumn !== status" class="text-center py-8 text-xs text-slate-400 dark:text-slate-555 border border-dashed border-slate-200 dark:border-slate-855 rounded-xl">
            أفلت المهام هنا
          </div>

          <!-- Quick Add Trigger Button (min 44px height) -->
          <button 
            v-if="activeQuickAddColumn !== status"
            @click="triggerQuickAdd(status)" 
            class="w-full py-3 px-4 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 text-xs font-extrabold text-slate-600 dark:text-slate-300 hover:text-violet-600 dark:hover:text-violet-400 hover:border-violet-400 transition cursor-pointer flex items-center justify-center gap-2 min-h-[44px] min-w-[44px] mt-2 bg-white/50 dark:bg-slate-900/50"
          >
            <span class="text-sm font-black">+</span>
            <span>إضافة مهمة سريعة</span>
          </button>

          <!-- Trello-like Inline Quick Add Input -->
          <div v-if="activeQuickAddColumn === status" class="bg-white dark:bg-slate-900 border border-slate-250 dark:border-slate-805 rounded-xl p-3 shadow-sm space-y-2.5 text-right mt-2" @click.stop>
            <MentionInput
              v-model="quickAddTitle"
              :is-textarea="true"
              :rows="2"
              :autofocus="true"
              placeholder="اكتب عنوان المهمة... (اضغط Enter للإضافة، و Shift+Enter للسطر الجديد)"
              inputClass="font-extrabold"
              @paste="e => handlePaste(e, status)"
              @keydown.esc="cancelQuickAdd"
              @submit="submitQuickAdd(status)"
            />
            <div class="flex items-center gap-1.5 justify-start flex-row-reverse flex-wrap">
              <button 
                @click="submitQuickAdd(status)"
                class="bg-violet-650 hover:bg-violet-755 text-white font-bold px-3 py-1 rounded-lg text-[10px] transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center"
              >
                إضافة
              </button>
              <button 
                @click="cancelQuickAdd"
                class="text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold px-2 py-1 rounded-lg text-[10px] transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center"
              >
                إلغاء
              </button>
              <button 
                @click="openFullModalFromQuickAdd(status)"
                class="text-violet-600 dark:text-violet-405 hover:bg-violet-50 dark:hover:bg-violet-955/20 font-extrabold px-2 py-1 rounded-lg text-[10px] transition cursor-pointer mr-auto min-h-[44px] min-w-[44px] flex items-center justify-center"
              >
                فتح التفاصيل
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Floating Bulk Actions Bar (Responsive Mobile Stack / Sheet) -->
    <div 
      v-if="selectedTaskIds.length > 0"
      class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-white/85 dark:bg-slate-900/85 backdrop-blur-2xl border border-violet-500/30 shadow-glass-glow rounded-2xl w-[calc(100%-2rem)] max-w-lg px-4 sm:px-6 py-3 sm:py-4 flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-6 z-40 animate-fade-in flex-row-reverse" 
      dir="rtl"
    >
      <span class="text-xs font-extrabold text-slate-800 dark:text-slate-200 shrink-0">
        تم تحديد {{ selectedTaskIds.length }} مهام:
      </span>

      <div class="flex flex-wrap sm:flex-nowrap items-center justify-center gap-2 sm:gap-3 w-full sm:w-auto">
        <!-- Change Status -->
        <div class="relative flex-1 sm:flex-none">
          <select 
            @change="bulkChangeStatus($event.target.value); $event.target.value = ''"
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 focus:outline-none cursor-pointer min-h-[44px]"
          >
            <option value="">-- نقل للحالة --</option>
            <option v-for="s in activeProjectStatuses" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>

        <!-- Move to Project -->
        <div class="relative flex-1 sm:flex-none">
          <select 
            @change="bulkMoveToProject($event.target.value); $event.target.value = ''"
            class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-805 rounded-xl px-3 py-2 text-xs font-bold text-slate-700 dark:text-slate-300 focus:outline-none cursor-pointer min-h-[44px]"
          >
            <option value="">-- نقل للمشروع --</option>
            <option v-for="p in otherProjects" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>

        <!-- Bulk Delete Button -->
        <button 
          @click="bulkDelete"
          class="bg-rose-50 hover:bg-rose-100 text-rose-650 dark:bg-rose-955/20 dark:text-rose-400 font-extrabold px-4 py-2 rounded-xl text-xs transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center shrink-0"
        >
          حذف جماعي
        </button>
      </div>
    </div>

    <!-- Multiline Paste Confirmation Modal (Trello Pop-up) -->
    <Transition name="sheet">
      <div v-if="showPasteModal" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4" dir="rtl">
        <!-- Backdrop -->
        <div @click="cancelPasteModal" class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>

        <!-- Modal Content -->
        <div 
          @touchstart="handleTouchStart"
          @touchmove="handleTouchMove"
          @touchend="handleTouchEnd(cancelPasteModal)"
          class="relative z-10 bg-white dark:bg-slate-900 border-t sm:border border-slate-200 dark:border-slate-800 rounded-t-3xl sm:rounded-3xl p-6 max-w-md max-h-[85vh] overflow-y-auto w-full shadow-2xl space-y-4 text-right transform transition-all duration-300"
        >
          <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto my-2.5 shrink-0 sm:hidden cursor-grab"></div>
          <h3 class="text-sm font-extrabold text-slate-855 dark:text-slate-100">لقد قمت بلصق نص متعدد السطور</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed font-semibold">
            النص الملصق يحتوي على {{ pastedLines.length }} أسطر. هل ترغب في إنشاء مهمة منفصلة لكل سطر أم دمجها كلها في مهمة واحدة؟
          </p>

          <div class="flex items-center justify-start space-x-2 space-x-reverse flex-row-reverse pt-2 border-t border-slate-100 dark:border-slate-800">
            <button 
              @click="createPastedAsSeparate" 
              class="bg-violet-600 hover:bg-violet-755 text-white font-bold py-2 px-4 rounded-xl text-xs transition cursor-pointer"
            >
              إنشاء {{ pastedLines.length }} مهام منفصلة
            </button>
            <button 
              @click="createPastedAsSingle" 
              class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-350 font-bold py-2 px-3 rounded-xl text-xs transition cursor-pointer"
            >
              دمج في عنوان واحد
            </button>
            <button 
              @click="cancelPasteModal" 
              class="text-slate-400 hover:text-slate-600 font-bold py-2 px-3 rounded-xl text-xs transition cursor-pointer mr-auto"
            >
              إلغاء
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>
