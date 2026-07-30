<script setup>
import { store } from '../store'
import { computed, ref } from 'vue'

const projectTasks = computed(() => store.tasks.filter(t => t.projectId === store.activeProjectId))



// Display month: يوليو 2026
const year = 2026
const monthIndex = 6 // July
const monthName = 'يوليو 2026'

// Weekdays in Arabic (starting Sunday to Saturday)
const weekdays = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت']

// Days in July 2026
const calendarCells = computed(() => {
  const cells = []
  const firstDayOfWeek = new Date(year, monthIndex, 1).getDay() // Wednesday (3)
  const daysInMonth = new Date(year, monthIndex + 1, 0).getDate() // 31

  // Pad previous month days
  for (let i = 0; i < firstDayOfWeek; i++) {
    cells.push({ dayNumber: '', dateString: '', isCurrentMonth: false })
  }

  // Current month days
  for (let d = 1; d <= daysInMonth; d++) {
    const dayStr = String(d).padStart(2, '0')
    const dateStr = `${year}-07-${dayStr}`
    cells.push({
      dayNumber: d,
      dateString: dateStr,
      isCurrentMonth: true
    })
  }

  // Pad next month
  const totalCells = Math.ceil(cells.length / 7) * 7
  const remaining = totalCells - cells.length
  for (let i = 1; i <= remaining; i++) {
    cells.push({ dayNumber: '', dateString: '', isCurrentMonth: false })
  }

  return cells
})

// Drag and drop states
const draggedTaskId = ref(null)
const activeDragOverCell = ref(null)

const handleDragStart = (taskId) => {
  draggedTaskId.value = taskId
}

const handleDragOver = (e, dateString) => {
  e.preventDefault()
  if (activeDragOverCell.value !== dateString) {
    activeDragOverCell.value = dateString
  }
}

const handleDragLeave = () => {
  activeDragOverCell.value = null
}

const handleDrop = (dateString) => {
  if (!draggedTaskId.value || !dateString) return
  const task = store.tasks.find(t => t.id === draggedTaskId.value)
  if (task) {
    store.updateTask(task.id, { deadline: dateString })
  }
  draggedTaskId.value = null
  activeDragOverCell.value = null
}

const getTasksForDate = (dateString) => {
  if (!dateString) return []
  return projectTasks.value.filter(t => t.deadline === dateString)
}

const openEditTask = (taskId) => {
  store.selectedTaskIdForModal = taskId
  store.isTaskModalOpen = true
}

// Custom status badge colors inside cell items
const getStatusPillStyle = (status) => {
  const s = status.toLowerCase()
  if (s.includes('todo') || s.includes('to do') || s.includes('بدء') || s.includes('بانتظار')) {
    return 'bg-blue-50/70 border-blue-200/50 text-blue-650 dark:bg-blue-955/20 dark:border-blue-900/30 dark:text-blue-400 border-r-2 border-r-blue-400 border-l-0'
  }
  if (s.includes('progress') || s.includes('عمل') || s.includes('approved') || s.includes('نشط')) {
    return 'bg-violet-50/70 border-violet-200/50 text-violet-650 dark:bg-violet-955/20 dark:border-violet-900/30 dark:text-violet-400 border-r-2 border-r-violet-500 border-l-0'
  }
  if (s.includes('review') || s.includes('مراجعة') || s.includes('schedule') || s.includes('مجدول')) {
    return 'bg-amber-50/70 border-amber-200/50 text-amber-655 dark:bg-amber-955/20 dark:border-amber-900/30 dark:text-amber-400 border-r-2 border-r-amber-400 border-l-0'
  }
  if (s.includes('done') || s.includes('مكتمل') || s.includes('منشور') || s.includes('publish') || s.includes('complete')) {
    return 'bg-emerald-50/70 border-emerald-200/50 text-emerald-650 dark:bg-emerald-955/20 dark:border-emerald-900/30 dark:text-emerald-400 border-r-2 border-r-emerald-450 border-l-0'
  }
  return 'bg-slate-50 border-slate-200 text-slate-700 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-350 border-r-2 border-r-slate-400 border-l-0'
}
</script>

<template>
  <div class="space-y-5 text-right">
    
    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-855 pb-4">
      <h2 class="text-base font-extrabold text-slate-855 dark:text-slate-100 uppercase tracking-widest flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        التقويم والجدولة الزمنية
      </h2>
      <span class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-200 bg-slate-100 dark:bg-slate-955/60 border border-slate-200/50 dark:border-slate-850 px-3.5 py-1 rounded-xl shadow-sm">
        {{ monthName }}
      </span>
    </div>

    <!-- Mobile Day List / Agenda View (< 640px screens) -->
    <div class="block sm:hidden space-y-3">
      <div class="text-xs font-bold text-slate-400 mb-2">جدول المواعيد والمهام (Agenda View)</div>
      
      <div v-if="projectTasks.length === 0" class="py-12 text-center text-slate-400 italic text-xs font-semibold bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4">
        لا توجد أي مهام مجدولة في هذا المشروع.
      </div>
      
      <div v-else class="space-y-3">
        <div 
          v-for="cell in calendarCells.filter(c => c.isCurrentMonth && getTasksForDate(c.dateString).length > 0)" 
          :key="'agenda-' + cell.dateString"
          class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 space-y-2.5 shadow-sm text-right"
        >
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
            <span class="text-xs font-black text-slate-900 dark:text-white flex items-center gap-1.5">
              <span>📅</span>
              <span>{{ cell.dateString }}</span>
              <span v-if="cell.dateString === '2026-07-16'" class="px-2 py-0.5 rounded-full bg-violet-600 text-white text-[9px] font-black">اليوم</span>
            </span>
            <span class="text-[10px] font-bold text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-lg">
              {{ getTasksForDate(cell.dateString).length }} مهام
            </span>
          </div>

          <div class="space-y-2">
            <div 
              v-for="task in getTasksForDate(cell.dateString)" 
              :key="'agenda-task-' + task.id"
              @click="openEditTask(task.id)"
              :class="[
                'p-3 rounded-xl border transition cursor-pointer flex items-center justify-between gap-2 min-h-[44px] min-w-[44px]',
                getStatusPillStyle(task.status)
              ]"
            >
              <div class="font-extrabold text-xs truncate flex-1">{{ task.title }}</div>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-white/50 dark:bg-slate-800/50 shrink-0">{{ task.status }}</span>
            </div>
          </div>
        </div>

        <div v-if="calendarCells.filter(c => c.isCurrentMonth && getTasksForDate(c.dateString).length > 0).length === 0" class="text-center py-8 text-xs text-slate-400 italic bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-4">
          لا توجد مهام مطابقة لتواريخ هذا الشهر.
        </div>
      </div>
    </div>

    <!-- Desktop Grid-based Calendar Box (hidden sm:block) -->
    <div class="hidden sm:block space-y-2.5">
      <!-- Weekdays header cards -->
      <div class="hidden sm:grid grid-cols-7 gap-2.5 text-center">
        <div 
          v-for="d in weekdays" 
          :key="d" 
          class="py-2.5 bg-slate-100/60 dark:bg-slate-900/60 border border-slate-200/40 dark:border-slate-850 rounded-xl text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider"
        >
          {{ d }}
        </div>
      </div>

      <!-- Days Grid (Modern SaaS Card layout) -->
      <div class="hidden sm:grid grid-cols-7 gap-2.5">
        <div 
          v-for="(cell, index) in calendarCells" 
          :key="index"
          @dragover="handleDragOver($event, cell.dateString)"
          @dragleave="handleDragLeave"
          @drop="handleDrop(cell.dateString)"
          :class="[
            'rounded-2xl p-3 flex flex-col justify-between min-h-[110px] transition-all duration-300 shadow-[0_2px_4px_rgba(0,0,0,0.005)] relative border',
            !cell.isCurrentMonth
              ? 'bg-slate-50/30 dark:bg-slate-955/5 border-slate-200/30 dark:border-slate-900/20 opacity-30 dark:opacity-20 cursor-not-allowed'
              : cell.dateString === '2026-07-16'
                ? 'bg-gradient-to-tr from-violet-500/[0.02] to-indigo-500/[0.02] border-violet-200 dark:border-violet-850 shadow-md ring-2 ring-violet-500/10'
                : 'bg-white dark:bg-slate-900/60 border-slate-200/60 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700 hover:shadow-sm hover:scale-[1.01]',
            activeDragOverCell === cell.dateString ? 'border-dashed border-violet-500 bg-violet-500/[0.04] dark:bg-violet-955/20 scale-[0.98] ring-2 ring-violet-500/20' : ''
          ]"
        >
          <!-- Day number & Today Tag -->
          <div class="flex justify-between items-start flex-row-reverse w-full">
            <span 
              :class="[
                'text-xs font-bold font-sans',
                cell.isCurrentMonth ? 'text-slate-855 dark:text-slate-200' : 'text-slate-400',
                cell.dateString === '2026-07-16' ? 'bg-violet-600 text-white rounded-full w-6 h-6 flex items-center justify-center font-extrabold shadow-sm shadow-violet-500/30' : ''
              ]"
            >
              {{ cell.dayNumber }}
            </span>
            <span v-if="cell.dateString === '2026-07-16'" class="text-[9px] font-bold text-violet-650 dark:text-violet-400 uppercase tracking-widest leading-none pt-1">
              اليوم
            </span>
          </div>

          <!-- Tasks lists in Cell (Draggable for rescheduling) -->
          <div class="space-y-1.5 flex-1 overflow-y-auto max-h-[75px] pl-0.5 mt-2.5 scrollbar-hide text-right">
            <div 
              v-for="task in getTasksForDate(cell.dateString)" 
              :key="task.id"
              draggable="true"
              @dragstart="handleDragStart(task.id)"
              @click.stop="openEditTask(task.id)"
              :class="[
                'text-[11px] font-bold p-1.5 rounded-lg border shadow-sm hover:shadow-md hover:-translate-y-0.5 cursor-grab active:cursor-grabbing transition duration-150 truncate text-right mb-1 block',
                getStatusPillStyle(task.status)
              ]"
              :title="task.title"
            >
              {{ task.title }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
