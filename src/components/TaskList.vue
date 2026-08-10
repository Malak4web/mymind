<script setup>
import { store } from '../store'
import { computed, ref } from 'vue'
import MentionText from './MentionText.vue'

const projectTasks = computed(() => store.tasks.filter(t => t.projectId === store.activeProjectId))

const searchQuery = ref('')
const sortBy = ref('deadline')
const sortOrder = ref('asc')

const filteredAndSortedTasks = computed(() => {
  let list = projectTasks.value.filter(t => {
    return t.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
           (t.description && t.description.toLowerCase().includes(searchQuery.value.toLowerCase()))
  })

  list.sort((a, b) => {
    let valA = a[sortBy.value] || ''
    let valB = b[sortBy.value] || ''

    if (sortBy.value === 'title') {
      valA = valA.toLowerCase()
      valB = valB.toLowerCase()
    }

    if (valA < valB) return sortOrder.value === 'asc' ? -1 : 1
    if (valA > valB) return sortOrder.value === 'asc' ? 1 : -1
    return 0
  })

  return list
})

const toggleSort = (field) => {
  if (sortBy.value === field) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = field
    sortOrder.value = 'asc'
  }
}

const openEditTask = (taskId) => {
  store.selectedTaskIdForModal = taskId
  store.isTaskModalOpen = true
}

const triggerNewTask = () => {
  store.selectedTaskIdForModal = null
  store.isTaskModalOpen = true
}

const deleteSingleTask = async (taskId) => {
  if (!confirm('هل أنت متأكد من حذف هذه المهمة؟')) return
  try {
    await store.deleteTask(taskId)
    const idx = selectedTaskIds.value.indexOf(taskId)
    if (idx > -1) selectedTaskIds.value.splice(idx, 1)
    store.addNotification('حذف مهمة', 'تم حذف المهمة بنجاح.')
  } catch (e) {
    console.error(e)
  }
}

const toggleTaskStatus = async (task) => {
  const statuses = activeProjectStatuses.value
  if (!statuses || statuses.length === 0) return
  const currentIndex = statuses.indexOf(task.status)
  const nextStatus = statuses[(currentIndex + 1) % statuses.length]
  try {
    await store.updateTask(task.id, {
      title: task.title,
      description: task.description,
      status: nextStatus,
      startDate: task.startDate,
      deadline: task.deadline
    })
  } catch (e) {
    console.error(e)
  }
}

const getPriorityInfo = (task) => {
  const p = (task.priority || task.customFieldValues?.priority || '').toLowerCase()
  if (p.includes('high') || p.includes('عالي') || p.includes('عالية') || p.includes('حرج')) {
    return { label: 'أولوية عالية', class: 'bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border-rose-200/60 dark:border-rose-800' }
  }
  if (p.includes('low') || p.includes('منخفض') || p.includes('منخفضة')) {
    return { label: 'أولوية منخفضة', class: 'bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 border-sky-200/60 dark:border-sky-800' }
  }
  return { label: task.priority || 'أولوية متوسطة', class: 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 border-amber-200/60 dark:border-amber-800' }
}


// Color badges for table statuses
const getStatusColor = (status) => {
  const s = status.toLowerCase()
  if (s.includes('todo') || s.includes('to do') || s.includes('بدء') || s.includes('بانتظار')) {
    return 'bg-blue-50 text-blue-600 dark:bg-blue-950/30 dark:text-blue-450 border-blue-100/50 dark:border-blue-900/25'
  }
  if (s.includes('progress') || s.includes('عمل') || s.includes('approved') || s.includes('نشط')) {
    return 'bg-violet-50 text-violet-650 dark:bg-violet-950/30 dark:text-violet-400 border-violet-100/50 dark:border-violet-900/25'
  }
  if (s.includes('review') || s.includes('مراجعة') || s.includes('schedule') || s.includes('مجدول')) {
    return 'bg-amber-50 text-amber-655 dark:bg-amber-955/30 dark:text-amber-400 border-amber-100/50 dark:border-amber-900/25'
  }
  if (s.includes('done') || s.includes('مكتمل') || s.includes('منشور') || s.includes('publish') || s.includes('complete')) {
    return 'bg-emerald-50 text-emerald-650 dark:bg-emerald-955/30 dark:text-emerald-400 border-emerald-100/50 dark:border-emerald-900/25'
  }
  return 'bg-slate-50 text-slate-500 dark:bg-slate-900/60 dark:text-slate-400 border-slate-200/50 dark:border-slate-800'
}

// Bulk Actions States
const selectedTaskIds = ref([])

const activeProject = computed(() => store.projects.find(p => p.id === store.activeProjectId))
const activeProjectStatuses = computed(() => activeProject.value?.statuses || store.globalStatuses)
const otherProjects = computed(() => store.projects.filter(p => p.id !== store.activeProjectId && !p.isDeleted))

const isAllSelected = computed(() => {
  return filteredAndSortedTasks.value.length > 0 && selectedTaskIds.value.length === filteredAndSortedTasks.value.length
})

const toggleSelectAll = (e) => {
  if (e.target.checked) {
    selectedTaskIds.value = filteredAndSortedTasks.value.map(t => t.id)
  } else {
    selectedTaskIds.value = []
  }
}

const bulkChangeStatus = async (newStatus) => {
  if (!newStatus) return
  try {
    for (const id of selectedTaskIds.value) {
      const task = projectTasks.value.find(t => t.id === id)
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
      const task = projectTasks.value.find(t => t.id === id)
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
const fallbackCopyText = (text, message = 'تم نسخ عنوان المهمة إلى الحافظة') => {
  const textarea = document.createElement('textarea')
  textarea.value = text
  textarea.style.position = 'fixed'
  textarea.style.opacity = '0'
  document.body.appendChild(textarea)
  textarea.select()
  try {
    document.execCommand('copy')
    store.addNotification('تم النسخ', message)
  } catch (e) {
    console.error('فشل النسخ إلى الحافظة', e)
  }
  document.body.removeChild(textarea)
}

const copyTaskTitle = (title) => {
  if (!title) return
  const textToCopy = String(title).trim()
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(textToCopy).then(() => {
      store.addNotification('تم النسخ', 'تم نسخ عنوان المهمة إلى الحافظة')
    }).catch(() => {
      fallbackCopyText(textToCopy)
    })
  } else {
    fallbackCopyText(textToCopy)
  }
}

const bulkCopyTitles = () => {
  if (selectedTaskIds.value.length === 0) return
  const selectedTasks = store.tasks.filter(t => selectedTaskIds.value.includes(t.id))
  const titles = selectedTasks.map(t => t.title).filter(Boolean)
  if (titles.length === 0) return

  const textToCopy = titles.join('\n')
  const msg = `تم نسخ عناوين (${titles.length}) مهام كـ أسطر منفصلة إلى الحافظة`
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(textToCopy).then(() => {
      store.addNotification('تم النسخ الجماعي', msg)
    }).catch(() => {
      fallbackCopyText(textToCopy, msg)
    })
  } else {
    fallbackCopyText(textToCopy, msg)
  }
}
</script>

<template>
  <div class="space-y-6 text-right">
    <!-- List Header Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-855 pb-4">
      <h2 class="text-base font-extrabold text-slate-850 dark:text-slate-100 uppercase tracking-widest gap-2 flex items-center">
        جدول مهام المشروع
        <span class="text-xs font-bold text-slate-400 font-sans">({{ projectTasks.length }} مهام إجمالاً)</span>
      </h2>

      <div class="flex items-center gap-3 w-full sm:w-auto">
        <!-- Search input -->
        <div class="relative flex-1 sm:flex-none">
          <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="ابحث عن المهام بالاسم..."
            class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-850 rounded-xl pr-9 pl-4 py-2.5 text-sm text-slate-850 dark:text-slate-200 focus:outline-none focus:border-violet-500 transition-all"
          />
        </div>

        <button 
          @click="triggerNewTask"
          class="bg-violet-600 hover:bg-violet-700 text-white font-bold py-2.5 px-4 rounded-xl text-sm transition duration-200 cursor-pointer flex items-center gap-1.5 shadow-sm"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          <span>إضافة مهمة</span>
        </button>
      </div>
    </div>

    <!-- Mobile Cards View (Visible on sm:hidden) -->
    <div class="block sm:hidden space-y-3 max-w-full overflow-hidden">
      <div v-if="filteredAndSortedTasks.length === 0" class="py-12 text-center text-slate-400 italic font-semibold">
        لا توجد أي مهام مطابقة لفلترة البحث الحالية.
      </div>
      <div 
        v-for="task in filteredAndSortedTasks" 
        :key="'mobile-' + task.id"
        @click="store.openTaskInspector(task.id)"
        @dblclick="openEditTask(task.id)"
        class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 space-y-3 shadow-sm text-right cursor-pointer relative overflow-hidden max-w-full"
      >
        <!-- Card Header: Status Indicator Button, Priority Pill & Selection Checkbox -->
        <div class="flex items-center justify-between gap-2 flex-wrap" @click.stop>
          <div class="flex items-center gap-2 flex-wrap">
            <!-- Status Indicator Button (Cycles Status) -->
            <button
              @click="toggleTaskStatus(task)"
              :class="['inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-bold border min-h-[44px] min-w-[44px] transition cursor-pointer active:scale-95 shadow-sm', getStatusColor(task.status)]"
              title="انقر لتغيير حالة المهمة"
            >
              <span class="w-2 h-2 rounded-full bg-current ml-1.5 shrink-0"></span>
              <span>{{ task.status }}</span>
            </button>

            <!-- Priority Pill -->
            <span :class="['inline-flex items-center px-2.5 py-1 rounded-xl text-[11px] font-extrabold border shrink-0', getPriorityInfo(task).class]">
              {{ getPriorityInfo(task).label }}
            </span>
          </div>

          <!-- Selection Checkbox (Touch hit target min 44px) -->
          <div class="flex items-center justify-center min-h-[44px] min-w-[44px] -mr-2">
            <input 
              type="checkbox" 
              v-model="selectedTaskIds" 
              :value="task.id" 
              class="rounded border-slate-300 dark:border-slate-800 text-violet-600 focus:ring-violet-500 cursor-pointer h-5 w-5" 
            />
          </div>
        </div>

        <!-- Task Title with Mention Preview & Floating Tooltip -->
        <div class="relative group/title min-w-0 flex-1">
          <h3 
            class="text-sm font-extrabold text-slate-900 dark:text-slate-100 leading-snug whitespace-nowrap overflow-hidden text-ellipsis block max-w-full"
            :title="task.title"
          >
            <MentionText :content="task.title" singleLine />
          </h3>
          <div class="absolute bottom-full right-0 mb-1.5 hidden group-hover/title:block z-50 pointer-events-none max-w-xs sm:max-w-sm">
            <div class="bg-slate-900/95 dark:bg-slate-800/95 text-white text-xs font-medium px-3 py-1.5 rounded-xl shadow-xl border border-slate-700/50 backdrop-blur-md whitespace-normal break-words text-right dir-rtl">
              {{ task.title }}
            </div>
          </div>
        </div>
        
        <!-- Description Preview -->
        <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed break-words" v-if="task.description">
          <MentionText :content="task.description" />
        </p>

        <!-- Footer Row: Date Tag, Attachments & Action Triggers -->
        <div class="flex items-center justify-between text-xs font-mono text-slate-500 dark:text-slate-400 pt-3 border-t border-slate-100 dark:border-slate-800 gap-2 flex-wrap" @click.stop>
          <!-- Date Tag -->
          <span 
            :class="[
              'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-[11px] font-bold font-sans border',
              task.deadline && new Date(task.deadline) < new Date() && task.status !== 'مكتمل'
                ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border-rose-200/60 dark:border-rose-800'
                : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'
            ]"
          >
            <span>📅</span>
            <span>{{ task.deadline || 'بدون تاريخ' }}</span>
          </span>

          <div class="flex items-center gap-1 mr-auto">
            <span v-if="task.attachments?.length > 0" class="text-[11px] text-slate-400 ml-1 font-bold">📎 {{ task.attachments.length }}</span>
            
            <!-- Copy Title Button -->
            <button
              @click="copyTaskTitle(task.title)"
              class="min-h-[44px] min-w-[44px] p-2.5 rounded-xl text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-slate-800 transition cursor-pointer flex items-center justify-center text-xs"
              title="نسخ عنوان المهمة"
            >
              📋
            </button>

            <!-- Quick Edit Button (min 44px hit target) -->
            <button
              @click="openEditTask(task.id)"
              class="min-h-[44px] min-w-[44px] p-2.5 rounded-xl text-slate-500 hover:text-violet-600 hover:bg-violet-50 dark:hover:bg-slate-800 transition cursor-pointer flex items-center justify-center"
              title="تعديل المهمة"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
            </button>

            <!-- Delete Trigger (min 44px hit target) -->
            <button
              @click="deleteSingleTask(task.id)"
              class="min-h-[44px] min-w-[44px] p-2.5 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-slate-800 transition cursor-pointer flex items-center justify-center"
              title="حذف المهمة"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Desktop Table View (Hidden on Mobile) -->
    <div class="hidden sm:block glass-card rounded-2xl p-4 shadow-glass-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-right border-collapse">
          <thead>
            <tr class="bg-slate-50/50 dark:bg-slate-955/30 border-b border-slate-100 dark:border-slate-850 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider select-none">
              <th class="py-4 px-4 w-12 text-center">
                <input 
                  type="checkbox" 
                  :checked="isAllSelected" 
                  @change="toggleSelectAll" 
                  class="rounded border-slate-300 dark:border-slate-800 text-violet-650 focus:ring-violet-500 cursor-pointer h-4 w-4" 
                />
              </th>
              <th class="py-4 px-5 cursor-pointer hover:bg-slate-100/50 dark:hover:bg-slate-900/60 transition" @click="toggleSort('title')">
                <div class="flex items-center space-x-1 space-x-reverse">
                  <span>اسم وتفاصيل المهمة</span>
                  <span v-if="sortBy === 'title'" class="text-[9px] text-violet-500">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                </div>
              </th>
              <th class="py-4 px-5 cursor-pointer hover:bg-slate-100/50 dark:hover:bg-slate-900/60 transition" @click="toggleSort('status')">
                <div class="flex items-center space-x-1 space-x-reverse">
                  <span>الحالة</span>
                  <span v-if="sortBy === 'status'" class="text-[9px] text-violet-500">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                </div>
              </th>
              <th class="py-4 px-5 cursor-pointer hover:bg-slate-100/50 dark:hover:bg-slate-900/60 transition" @click="toggleSort('startDate')">
                <div class="flex items-center space-x-1 space-x-reverse">
                  <span>تاريخ البدء</span>
                  <span v-if="sortBy === 'startDate'" class="text-[9px] text-violet-500">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                </div>
              </th>
              <th class="py-4 px-5 cursor-pointer hover:bg-slate-100/50 dark:hover:bg-slate-900/60 transition" @click="toggleSort('deadline')">
                <div class="flex items-center space-x-1 space-x-reverse">
                  <span>تاريخ الاستحقاق</span>
                  <span v-if="sortBy === 'deadline'" class="text-[9px] text-violet-500">{{ sortOrder === 'asc' ? '▲' : '▼' }}</span>
                </div>
              </th>
              <th class="py-4 px-5">المرفقات</th>
              <th class="py-4 px-4 text-center">إجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-855 text-sm text-slate-700 dark:text-slate-350">
            <tr v-if="filteredAndSortedTasks.length === 0">
              <td colspan="7" class="py-12 text-center text-slate-400 italic font-semibold">
                لا توجد أي مهام مطابقة لفلترة البحث الحالية.
              </td>
            </tr>
            <tr 
              v-for="task in filteredAndSortedTasks" 
              :key="task.id"
              @click="store.openTaskInspector(task.id)"
              @dblclick="openEditTask(task.id)"
              class="hover:bg-slate-50/40 dark:hover:bg-slate-950/20 cursor-pointer transition duration-150"
            >
              <td class="py-4 px-4 text-center" @click.stop>
                <input 
                  type="checkbox" 
                  v-model="selectedTaskIds" 
                  :value="task.id" 
                  class="rounded border-slate-300 dark:border-slate-850 text-violet-650 focus:ring-violet-500 cursor-pointer h-4 w-4" 
                />
              </td>
              <td class="py-4 px-5 max-w-xs">
                <div class="relative group/title min-w-0">
                  <div 
                    class="font-extrabold text-slate-855 dark:text-slate-205 truncate whitespace-nowrap overflow-hidden leading-relaxed text-sm block cursor-pointer"
                    :title="task.title"
                  >
                    <MentionText :content="task.title" />
                  </div>
                  <div class="absolute bottom-full right-0 mb-1.5 hidden group-hover/title:block z-50 pointer-events-none max-w-xs sm:max-w-sm">
                    <div class="bg-slate-900/95 dark:bg-slate-800/95 text-white text-xs font-medium px-3 py-1.5 rounded-xl shadow-xl border border-slate-700/50 backdrop-blur-md whitespace-normal break-words text-right dir-rtl">
                      {{ task.title }}
                    </div>
                  </div>
                </div>
                <div class="text-xs text-slate-455 line-clamp-1 mt-1" @click.stop>
                  <MentionText v-if="task.description" :content="task.description" />
                  <span v-else class="text-slate-400 italic">لا توجد ملاحظات تفصيلية.</span>
                </div>
              </td>
              <td class="py-4 px-5">
                <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-bold tracking-wider uppercase border', getStatusColor(task.status)]">
                  {{ task.status }}
                </span>
              </td>
              <td class="py-4 px-5 font-mono text-xs text-slate-500 dark:text-slate-450">{{ task.startDate || '—' }}</td>
              <td class="py-4 px-5">
                <span 
                  :class="[
                    'font-mono text-xs font-bold py-0.5 px-1.5 rounded',
                    task.deadline && new Date(task.deadline) < new Date() && task.status !== 'مكتمل'
                      ? 'text-rose-650 dark:text-rose-400 bg-rose-500/10'
                      : 'text-slate-550'
                  ]"
                >
                  {{ task.deadline || '—' }}
                </span>
              </td>
              <td class="py-4 px-5">
                <div class="flex items-center space-x-1 space-x-reverse" v-if="task.attachments?.length > 0">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                  </svg>
                  <span class="text-xs text-slate-500 font-extrabold">{{ task.attachments.length }}</span>
                </div>
                <span v-else class="text-xs text-slate-400">—</span>
              </td>
              <td class="py-4 px-4 text-center space-x-1 space-x-reverse" @click.stop>
                <button 
                  @click="copyTaskTitle(task.title)"
                  class="p-1.5 rounded-lg text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-slate-800 transition cursor-pointer"
                  title="نسخ عنوان المهمة"
                >
                  📋
                </button>
                <button 
                  @click="openEditTask(task.id)"
                  class="p-1.5 rounded-lg text-slate-500 hover:text-violet-600 hover:bg-violet-50 dark:hover:bg-slate-800 transition cursor-pointer"
                  title="تعديل المهمة (فتح النموذج الكامل)"
                >
                  ✏️
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Floating Bulk Actions Bar (Responsive Mobile Stack / Sheet) -->
    <div 
      v-if="selectedTaskIds.length > 0"
      class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border border-slate-200 dark:border-slate-800 shadow-2xl rounded-2xl w-[calc(100%-2rem)] max-w-lg px-4 sm:px-6 py-3 sm:py-4 flex flex-col sm:flex-row items-center justify-between gap-3 sm:gap-6 z-40 animate-fade-in flex-row-reverse" 
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

        <!-- Bulk Copy Titles Button -->
        <button 
          @click="bulkCopyTitles"
          class="bg-indigo-50 hover:bg-indigo-100 text-indigo-650 dark:bg-indigo-950/40 dark:text-indigo-400 font-extrabold px-3 py-2 rounded-xl text-xs transition cursor-pointer min-h-[44px] flex items-center justify-center gap-1.5 shrink-0 border border-indigo-200/50 dark:border-indigo-800/50"
          title="نسخ عناوين المهام المحددة كـ أسطر منفصلة"
        >
          <span>📋</span>
          <span>نسخ العناوين</span>
        </button>

        <!-- Bulk Delete Button -->
        <button 
          @click="bulkDelete"
          class="bg-rose-50 hover:bg-rose-100 text-rose-650 dark:bg-rose-955/20 dark:text-rose-400 font-extrabold px-4 py-2 rounded-xl text-xs transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center shrink-0"
        >
          حذف جماعي
        </button>
      </div>
    </div>
  </div>
</template>
