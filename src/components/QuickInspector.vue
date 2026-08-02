<script setup>
import { store } from '../store'
import { ref, computed, watch } from 'vue'
import MentionInput from './MentionInput.vue'
import MentionText from './MentionText.vue'

const activeTask = computed(() => {
  if (store.activeInspectorTaskId) {
    return store.tasks.find(t => t.id === store.activeInspectorTaskId)
  }
  return null
})

const activeProject = computed(() => {
  if (activeTask.value) {
    return store.projects.find(p => p.id === activeTask.value.projectId) || store.projects.find(p => p.id === store.activeProjectId)
  }
  return store.projects.find(p => p.id === store.activeProjectId)
})

// Editable fields state
const title = ref('')
const description = ref('')
const status = ref('')
const startDate = ref('')
const deadline = ref('')

// File Upload simulation state
const fileToUploadName = ref('')
const fileToUploadSize = ref('1.2 MB')
const simulateFailure = ref(false)

// Quick Comment stream state
const newCommentText = ref('')
const localComments = ref([])

// Sync task data into local refs when activeTask changes
watch(activeTask, (task) => {
  if (task) {
    title.value = task.title || ''
    description.value = task.description || ''
    status.value = task.status || ''
    startDate.value = task.startDate || ''
    deadline.value = task.deadline || ''
    localComments.value = Array.isArray(task.comments) ? [...task.comments] : []
  } else {
    title.value = ''
    description.value = ''
    status.value = ''
    startDate.value = ''
    deadline.value = ''
    localComments.value = []
  }
}, { immediate: true })

// Auto-save changes to store
const saveFieldUpdates = async () => {
  if (!activeTask.value) return
  if (!title.value.trim()) return

  await store.updateTask(activeTask.value.id, {
    title: title.value.trim(),
    description: description.value.trim(),
    status: status.value,
    startDate: startDate.value,
    deadline: deadline.value
  })
}

const handleStatusChange = async () => {
  await saveFieldUpdates()
}

const handleDateChange = async () => {
  await saveFieldUpdates()
}

const openFullModal = () => {
  if (!activeTask.value) return
  store.selectedTaskIdForModal = activeTask.value.id
  store.isTaskModalOpen = true
  store.closeTaskInspector()
}

const triggerFileUpload = async () => {
  if (!fileToUploadName.value.trim() || !activeTask.value) return

  await store.uploadFileToTask(
    activeTask.value.id,
    fileToUploadName.value.trim(),
    fileToUploadSize.value,
    simulateFailure.value
  )

  fileToUploadName.value = ''
  simulateFailure.value = false
}

const toggleMemberAssignment = async (userId) => {
  if (!activeTask.value) return
  const current = activeTask.value.memberIds ? [...activeTask.value.memberIds] : []
  const idx = current.indexOf(userId)
  if (idx > -1) {
    current.splice(idx, 1)
  } else {
    current.push(userId)
  }
  activeTask.value.memberIds = current

  await store.updateTask(activeTask.value.id, {
    title: activeTask.value.title,
    memberIds: current
  })
}

const addQuickComment = () => {
  if (!newCommentText.value.trim() || !activeTask.value) return
  const authorName = store.currentUser?.name || 'أنت'
  const newComment = {
    id: Date.now(),
    author: authorName,
    text: newCommentText.value.trim(),
    createdAt: new Date().toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' })
  }
  localComments.value.push(newComment)
  if (!activeTask.value.comments) activeTask.value.comments = []
  activeTask.value.comments.push(newComment)
  store.addNotification('تعليق جديد', `تم إضافة تعليق على المهمة "${activeTask.value.title}".`)
  newCommentText.value = ''
}
</script>

<template>
  <aside 
    class="bg-white/85 dark:bg-slate-900/85 backdrop-blur-2xl border-l border-white/30 dark:border-slate-800/60 rounded-3xl p-5 shadow-2xl space-y-4 text-right transition-all duration-300 flex flex-col max-h-[calc(100vh-6rem)] overflow-y-auto scrollbar-hide sticky top-20"
    dir="rtl"
  >
    <!-- Inspector Header Bar -->
    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800 shrink-0">
      <div class="flex items-center gap-2">
        <button 
          @click="store.closeTaskInspector()"
          class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer min-h-[36px] min-w-[36px] flex items-center justify-center"
          title="إغلاق المعاين السريع"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
        <span class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">المعاين السريع</span>
      </div>

      <button 
        v-if="activeTask"
        @click="openFullModal"
        class="text-xs font-bold text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 bg-violet-50 dark:bg-violet-955/40 border border-violet-200/50 dark:border-violet-800/40 px-2.5 py-1.5 rounded-xl transition cursor-pointer flex items-center gap-1"
        title="فتح المهمة في النافذة المنبثقة الكاملة"
      >
        <span>فتح النموذج الكامل</span>
        <span>↗</span>
      </button>
    </div>

    <!-- Empty State when no task is active -->
    <div v-if="!activeTask" class="py-16 text-center space-y-3">
      <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto text-slate-400">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        </svg>
      </div>
      <p class="text-xs text-slate-450 dark:text-slate-400 font-semibold">اضغط على أي مهمة لمعاينتها وتعديل تفاصيلها فوراً بدون التغطية على اللوحة.</p>
    </div>

    <!-- Task Details Form Content -->
    <div v-else class="space-y-4 text-right flex-1">

      <!-- Title Input -->
      <div>
        <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wider mb-1">عنوان المهمة</label>
        <input 
          v-model="title"
          @blur="saveFieldUpdates"
          @keyup.enter="saveFieldUpdates"
          type="text"
          class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-sm font-bold text-slate-855 dark:text-slate-100 focus:outline-none focus:border-violet-500 transition"
          placeholder="عنوان المهمة..."
        />
      </div>

      <!-- Status & Project Info -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        <div>
          <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wider mb-1">الحالة</label>
          <select 
            v-model="status" 
            @change="handleStatusChange"
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-2.5 py-1.5 text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none cursor-pointer"
          >
            <option v-for="s in (activeProject?.statuses || store.globalStatuses)" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wider mb-1">المشروع</label>
          <div class="bg-slate-50 dark:bg-slate-955 border border-slate-200/60 dark:border-slate-800 rounded-xl px-2.5 py-1.5 text-xs text-slate-600 dark:text-slate-350 font-bold truncate">
            {{ activeProject?.name || 'عام' }}
          </div>
        </div>
      </div>

      <!-- Dates Section -->
      <div class="grid grid-cols-2 gap-2">
        <div>
          <label class="block text-[10px] font-bold text-slate-400 mb-1">تاريخ البدء</label>
          <input 
            type="date" 
            v-model="startDate"
            @change="handleDateChange"
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg px-2 py-1 text-xs text-slate-700 dark:text-slate-300 focus:outline-none"
          />
        </div>
        <div>
          <label class="block text-[10px] font-bold text-slate-400 mb-1">تاريخ الاستحقاق</label>
          <input 
            type="date" 
            v-model="deadline"
            @change="handleDateChange"
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-lg px-2 py-1 text-xs text-slate-700 dark:text-slate-300 focus:outline-none"
          />
        </div>
      </div>

      <!-- Assigned Members -->
      <div>
        <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wider mb-1.5">الأعضاء المكلفون</label>
        <div class="flex flex-wrap gap-1.5 items-center">
          <div 
            v-for="user in store.users" 
            :key="user.id"
            @click="toggleMemberAssignment(user.id)"
            :class="[
              'px-2.5 py-1 rounded-xl text-xs font-semibold border transition cursor-pointer flex items-center gap-1 select-none',
              activeTask.memberIds?.includes(user.id)
                ? 'bg-violet-600 text-white border-violet-600 shadow-sm'
                : 'bg-slate-50 dark:bg-slate-955 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-800 hover:border-violet-400'
            ]"
            :title="user.name"
          >
            <span>👤</span>
            <span>{{ user.name }}</span>
          </div>
        </div>
      </div>

      <!-- Description -->
      <div>
        <label class="block text-[11px] font-extrabold text-slate-400 uppercase tracking-wider mb-1">الوصف والتفاصيل</label>
        <MentionInput 
          v-model="description"
          :is-textarea="true"
          :rows="3"
          placeholder="إضافة وصف تفصيلي..."
        />
        <div v-if="description" class="mt-1.5 p-2 bg-slate-50 dark:bg-slate-955 rounded-xl border border-slate-200/60 dark:border-slate-800 text-xs">
          <MentionText :content="description" />
        </div>
      </div>

      <!-- Attachments Stream -->
      <div class="bg-slate-50/70 dark:bg-slate-955/50 border border-slate-200/60 dark:border-slate-800/60 rounded-2xl p-3 space-y-2">
        <div class="flex items-center justify-between">
          <span class="text-xs font-extrabold text-slate-700 dark:text-slate-300">المرفقات والملفات</span>
          <span class="text-[10px] text-slate-400 font-bold">{{ activeTask.attachments?.length || 0 }} مرفق</span>
        </div>

        <div v-if="activeTask.attachments && activeTask.attachments.filter(Boolean).length > 0" class="space-y-1.5 max-h-36 overflow-y-auto">
          <div 
            v-for="(file, idx) in activeTask.attachments.filter(Boolean)" 
            :key="idx"
            class="flex items-center justify-between p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 text-xs"
          >
            <span class="font-bold truncate text-slate-700 dark:text-slate-300 max-w-[180px]">{{ file?.name || 'ملف بدون اسم' }}</span>
            <span class="text-[10px] font-mono text-slate-400">{{ file?.size || '' }}</span>
          </div>
        </div>
        <div v-else class="text-[11px] text-slate-400 italic">لا توجد مرفقات.</div>

        <!-- Quick Upload Stream -->
        <div class="flex gap-1.5 pt-1">
          <input 
            v-model="fileToUploadName"
            type="text"
            placeholder="اسم الملف..."
            class="flex-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg px-2 py-1 text-xs focus:outline-none"
          />
          <button 
            @click="triggerFileUpload"
            :disabled="!fileToUploadName.trim()"
            class="bg-violet-600 hover:bg-violet-700 disabled:opacity-40 text-white font-bold px-2.5 py-1 rounded-lg text-xs transition cursor-pointer"
          >
            رفع
          </button>
        </div>
      </div>

      <!-- Quick Comments Stream -->
      <div class="bg-slate-50/70 dark:bg-slate-955/50 border border-slate-200/60 dark:border-slate-800/60 rounded-2xl p-3 space-y-2">
        <span class="text-xs font-extrabold text-slate-700 dark:text-slate-300 block">شريط التعليقات السريعة</span>

        <div v-if="localComments.length > 0" class="space-y-2 max-h-40 overflow-y-auto">
          <div 
            v-for="c in localComments" 
            :key="c.id"
            class="p-2 bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 rounded-xl text-xs space-y-1"
          >
            <div class="flex items-center justify-between text-[10px] text-slate-400">
              <span class="font-bold text-violet-600 dark:text-violet-400">{{ c.author }}</span>
              <span>{{ c.createdAt }}</span>
            </div>
            <p class="text-slate-700 dark:text-slate-300 leading-normal">{{ c.text }}</p>
          </div>
        </div>
        <div v-else class="text-[11px] text-slate-400 italic">لا توجد تعليقات حتى الآن.</div>

        <div class="flex gap-1.5 pt-1">
          <MentionInput 
            v-model="newCommentText"
            placeholder="اكتب تعليقاً سريعاً..."
            class="flex-1"
          />
          <button 
            @click="addQuickComment"
            :disabled="!newCommentText.trim()"
            class="bg-slate-900 dark:bg-slate-100 hover:bg-slate-800 dark:hover:bg-slate-200 disabled:opacity-40 text-white dark:text-slate-900 font-bold px-3 py-1 rounded-xl text-xs transition cursor-pointer"
          >
            إرسال
          </button>
        </div>
      </div>

    </div>
  </aside>
</template>
