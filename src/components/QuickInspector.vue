<script setup>
import { store } from '../store'
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
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

// Quick Comment stream state
const newCommentText = ref('')
const localComments = ref([])
const isSendingComment = ref(false)
const commentError = ref('')

const inspectorFileInputRef = ref(null)

const handleInspectorFileSelect = (e) => {
  const files = e.target.files
  if (!files || files.length === 0) return
  processInspectorFiles(files)
}

const handleImagePaste = (e) => {
  const items = (e.clipboardData || e.originalEvent?.clipboardData)?.items
  if (!items) return

  const imageFiles = []
  for (const item of items) {
    if (item.type && item.type.startsWith('image/')) {
      const file = item.getAsFile()
      if (file) imageFiles.push(file)
    }
  }

  if (imageFiles.length > 0) {
    e.preventDefault()
    processInspectorFiles(imageFiles)
  }
}

let isPastingInspector = false

const handleGlobalPaste = async (e) => {
  if (!store.activeInspectorTaskId) return
  if (isPastingInspector) return
  isPastingInspector = true
  try {
    await handleImagePaste(e)
  } finally {
    setTimeout(() => { isPastingInspector = false }, 400)
  }
}

onMounted(() => {
  window.addEventListener('paste', handleGlobalPaste)
})

onUnmounted(() => {
  window.removeEventListener('paste', handleGlobalPaste)
})

const processInspectorFiles = async (files) => {
  if (!activeTask.value) return
  for (const file of files) {
    const timestamp = new Date().getTime()
    const fileName = file.name || `pasted_image_${timestamp}.png`
    const sizeFormatted = (file.size / 1024).toFixed(1) + ' KB'
    await store.uploadFileToTask(activeTask.value.id, fileName, sizeFormatted, file)
  }
}

// Attachments & Lightbox helpers for Quick Inspector
const lightboxUrl = ref('')
const lightboxTitle = ref('')

const openLightbox = (url, title) => {
  if (!url) return
  lightboxUrl.value = url
  lightboxTitle.value = title || 'معاينة الصورة'
}

const closeLightbox = () => {
  lightboxUrl.value = ''
  lightboxTitle.value = ''
}

// The API returns a signed, expiring `url` for every stored attachment.
// We never build the URL ourselves: an unsigned one is rejected (SEC-05),
// and reaching into /storage directly would bypass authorization entirely.
const getAttachmentUrl = (file) => {
  if (!file) return ''
  if (file.url) return file.url
  if (file.fileObj) {
    // Still uploading - show the local blob until the server responds.
    try {
      return URL.createObjectURL(file.fileObj)
    } catch (e) {}
  }
  return ''
}

const isImageFile = (file) => {
  if (!file) return false
  if (file.type && file.type.startsWith('image/')) return true
  const name = file.name || file.path || ''
  return /\.(png|jpe?g|gif|webp|svg|bmp)$/i.test(name)
}

const handleDeleteInspectorAttachment = async (file, idx) => {
  if (!confirm(`هل أنت متاكد من حذف المرفق "${file?.name || 'هذا الملف'}"؟`)) return
  if (file?.id) {
    await store.deleteAttachment(file.id)
  } else if (activeTask.value && activeTask.value.attachments) {
    activeTask.value.attachments.splice(idx, 1)
  }
  store.addNotification('حذف مرفق', 'تم حذف المرفق بنجاح.')
}

// Sync task data into local refs when activeTask changes
// Watches the task *id*, not the object: store.loadTasks() replaces every
// task object on each refresh, which used to refire this and wipe the user's
// in-progress edits.
watch(() => activeTask.value?.id ?? null, () => {
  const task = activeTask.value
  if (task) {
    title.value = task.title || ''
    description.value = task.description || ''
    status.value = task.status || ''
    startDate.value = task.startDate || ''
    deadline.value = task.deadline || ''
    localComments.value = Array.isArray(task.comments) ? [...task.comments] : []
    commentError.value = ''
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

const addQuickComment = async () => {
  const text = newCommentText.value.trim()
  if (!text || !activeTask.value || isSendingComment.value) return

  isSendingComment.value = true
  commentError.value = ''
  const taskId = activeTask.value.id

  const created = await store.addComment(taskId, text)

  if (created) {
    localComments.value = [...localComments.value, created]
    newCommentText.value = ''
  } else {
    // Previously this path announced success regardless. Now it says what happened.
    commentError.value = 'تعذّر حفظ التعليق. تحقق من الاتصال وحاول مرة أخرى.'
  }
  isSendingComment.value = false
}

const removeComment = async (commentId) => {
  if (!activeTask.value) return
  const ok = await store.deleteComment(activeTask.value.id, commentId)
  if (ok) {
    localComments.value = localComments.value.filter(c => String(c.id) !== String(commentId))
  }
}

const commentAuthor = (c) => c.author_name || c.author || 'مستخدم'
const commentBody = (c) => c.body ?? c.text ?? ''
const commentTime = (c) => {
  if (!c.created_at) return ''
  try {
    return new Intl.DateTimeFormat('ar-EG', {
      day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'
    }).format(new Date(c.created_at))
  } catch (e) {
    return ''
  }
}
const isMyComment = (c) => String(c.user_id ?? '') === String(store.currentUser?.id ?? '-')
</script>

<template>
  <aside 
    class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-2xl border-s border-white/30 dark:border-slate-800/60 rounded-t-3xl xl:rounded-3xl p-5 pb-safe xl:pb-5 shadow-2xl space-y-4 text-right transition-all duration-300 flex flex-col max-h-[88vh] xl:max-h-[calc(100vh-6rem)] overflow-y-auto scrollbar-hide xl:sticky xl:top-20"
    dir="rtl"
  >
    <!-- Mobile grab affordance -->
    <div class="xl:hidden shrink-0 flex justify-center pb-2 -mt-1" aria-hidden="true">
      <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full"></div>
    </div>
    <!-- Inspector Header Bar -->
    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800 shrink-0">
      <div class="flex items-center gap-2">
        <button 
          @click="store.closeTaskInspector()"
          class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center"
          title="إغلاق المعاين السريع" aria-label="إغلاق المعاين السريع"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
        <span class="text-xs font-bold text-slate-400 dark:text-slate-500">المعاين السريع</span>
      </div>

      <button 
        v-if="activeTask"
        @click="openFullModal"
        class="text-xs font-bold text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 bg-violet-50 dark:bg-violet-950/40 border border-violet-200/50 dark:border-violet-800/40 px-2.5 py-1.5 rounded-xl transition cursor-pointer flex items-center gap-1"
        title="فتح المهمة في النافذة المنبثقة الكاملة" aria-label="فتح المهمة في النافذة المنبثقة الكاملة"
      >
        <span>فتح النموذج الكامل</span>
        <span>↗</span>
      </button>
    </div>

    <!-- Empty State when no task is active -->
    <div v-if="!activeTask" class="py-16 text-center space-y-3">
      <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mx-auto text-slate-400">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        </svg>
      </div>
      <p class="text-xs text-slate-400 dark:text-slate-400 font-semibold">اضغط على أي مهمة لمعاينتها وتعديل تفاصيلها فوراً بدون التغطية على اللوحة.</p>
    </div>

    <!-- Task Details Form Content -->
    <div v-else class="space-y-4 text-right flex-1">

      <!-- Title Input -->
      <div>
        <label class="block text-[11px] font-extrabold text-slate-400 mb-1">عنوان المهمة</label>
        <input 
          v-model="title"
          @blur="saveFieldUpdates"
          @keyup.enter="saveFieldUpdates"
          type="text"
          class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-sm font-bold text-slate-900 dark:text-slate-100 focus:outline-none focus:border-violet-500 transition"
          placeholder="عنوان المهمة..."
        />
      </div>

      <!-- Status & Project Info -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        <div>
          <label class="block text-[11px] font-extrabold text-slate-400 mb-1">الحالة</label>
          <select 
            v-model="status" 
            @change="handleStatusChange"
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-2.5 py-1.5 text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none cursor-pointer"
          >
            <option v-for="s in (activeProject?.statuses || store.globalStatuses)" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-extrabold text-slate-400 mb-1">المشروع</label>
          <div class="bg-slate-50 dark:bg-slate-950 border border-slate-200/60 dark:border-slate-800 rounded-xl px-2.5 py-1.5 text-xs text-slate-600 dark:text-slate-300 font-bold truncate">
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
      <!-- Description -->
      <div>
        <label class="block text-[11px] font-extrabold text-slate-400 mb-1">الوصف والتفاصيل</label>
        <MentionInput 
          v-model="description"
          :is-textarea="true"
          :rows="3"
          placeholder="إضافة وصف تفصيلي..."
          @blur="saveFieldUpdates"
        />
        <div v-if="description" class="mt-1.5 p-2 bg-slate-50 dark:bg-slate-950 rounded-xl border border-slate-200/60 dark:border-slate-800 text-xs">
          <MentionText :content="description" />
        </div>
      </div>

      <!-- Attachments Stream -->
      <div class="bg-slate-50/70 dark:bg-slate-950/50 border border-slate-200/60 dark:border-slate-800/60 rounded-2xl p-3 space-y-2">
        <div class="flex items-center justify-between">
          <span class="text-xs font-extrabold text-slate-700 dark:text-slate-300">المرفقات والملفات</span>
          <span class="text-[10px] text-slate-400 font-bold">{{ activeTask.attachments?.length || 0 }} مرفق</span>
        </div>

        <!-- Image Paste Drop Zone -->
        <div 
          @click="$refs.inspectorFileInputRef?.click()"
          class="border border-dashed border-violet-300/80 dark:border-violet-800/80 hover:border-violet-500 rounded-xl p-2.5 text-center cursor-pointer transition bg-violet-50/40 dark:bg-violet-950/20 group/inspectpaste"
        >
          <input 
            ref="inspectorFileInputRef"
            type="file"
            accept="image/*"
            multiple
            class="hidden"
            @change="handleInspectorFileSelect"
          />
          <div class="text-[11px] font-extrabold text-violet-700 dark:text-violet-300 flex items-center justify-center gap-1 group-hover/inspectpaste:scale-105 transition">
            <span>📋</span>
            <span>لصق صورة (Ctrl+V) أو اختيار صورة</span>
          </div>
        </div>

        <div v-if="activeTask.attachments && activeTask.attachments.filter(Boolean).length > 0" class="space-y-1.5 max-h-48 overflow-y-auto">
          <div 
            v-for="(file, idx) in activeTask.attachments.filter(Boolean)" 
            :key="idx"
            class="flex items-center justify-between p-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 text-xs gap-2"
          >
            <!-- Thumbnail if image -->
            <div 
              v-if="isImageFile(file) && getAttachmentUrl(file)" 
              @click="openLightbox(getAttachmentUrl(file), file.name)"
              class="relative shrink-0 group/inspectthumb cursor-pointer overflow-hidden rounded-md border border-slate-200 dark:border-slate-800"
            >
              <img 
                :src="getAttachmentUrl(file)" 
                :alt="file.name"
                class="w-9 h-9 object-cover group-hover/inspectthumb:scale-110 transition duration-200" 
              />
              <div class="absolute inset-0 bg-black/40 opacity-0 group-hover/inspectthumb:opacity-100 flex items-center justify-center transition text-white text-[10px]">
                🔍
              </div>
            </div>
            <div v-else class="w-8 h-8 rounded-md bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-xs shrink-0">
              📄
            </div>

            <div class="min-w-0 flex-1 text-right">
              <div 
                @click="isImageFile(file) && getAttachmentUrl(file) ? openLightbox(getAttachmentUrl(file), file.name) : null"
                :class="[
                  'font-bold truncate text-slate-700 dark:text-slate-300 text-xs',
                  isImageFile(file) && getAttachmentUrl(file) ? 'hover:text-violet-600 dark:hover:text-violet-400 cursor-pointer' : ''
                ]"
              >
                {{ file?.name || 'ملف بدون اسم' }}
              </div>
              <span class="text-[10px] font-mono text-slate-400 block">{{ file?.size || '' }}</span>
            </div>

            <!-- Actions (Download & Delete) -->
            <div class="flex items-center gap-1 shrink-0">
              <a 
                v-if="getAttachmentUrl(file)" 
                :href="getAttachmentUrl(file)" 
                target="_blank"
                download
                class="p-1 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition cursor-pointer text-xs"
                title="تحميل"
              >
                ⬇️
              </a>
              <button 
                @click.stop="handleDeleteInspectorAttachment(file, idx)"
                class="p-1 text-rose-500 hover:text-rose-700 transition cursor-pointer text-xs"
                title="حذف هذا المرفق" aria-label="حذف هذا المرفق"
              >
                🗑️
              </button>
            </div>
          </div>
        </div>
        <div v-else class="text-[11px] text-slate-400 italic">لا توجد مرفقات.</div>

      </div>

      <!-- Quick Comments Stream -->
      <div class="bg-slate-50/70 dark:bg-slate-950/50 border border-slate-200/60 dark:border-slate-800/60 rounded-2xl p-3 space-y-2">
        <span class="text-xs font-extrabold text-slate-700 dark:text-slate-300 block">شريط التعليقات السريعة</span>

        <div v-if="localComments.length > 0" class="space-y-2 max-h-40 overflow-y-auto">
          <div 
            v-for="c in localComments" 
            :key="c.id"
            class="p-2 bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 rounded-xl text-xs space-y-1"
          >
            <div class="flex items-center justify-between text-[10px] text-slate-400 gap-2">
              <span class="font-bold text-violet-600 dark:text-violet-400">{{ commentAuthor(c) }}</span>
              <span class="flex items-center gap-1.5">
                <span>{{ commentTime(c) }}</span>
                <button
                  v-if="isMyComment(c)"
                  @click="removeComment(c.id)"
                  class="text-slate-400 hover:text-rose-600 transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center"
                  aria-label="حذف التعليق"
                >✕</button>
              </span>
            </div>
            <p class="text-slate-700 dark:text-slate-300 leading-normal">{{ commentBody(c) }}</p>
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
            :disabled="!newCommentText.trim() || isSendingComment"
            class="bg-slate-900 dark:bg-slate-100 hover:bg-slate-800 dark:hover:bg-slate-200 disabled:opacity-40 text-white dark:text-slate-900 font-bold px-3 py-1 rounded-xl text-xs transition cursor-pointer min-h-[44px]"
          >
            {{ isSendingComment ? '...' : 'إرسال' }}
          </button>
        </div>
        <p v-if="commentError" class="text-[11px] text-rose-600 dark:text-rose-400 font-bold">{{ commentError }}</p>
      </div>

    </div>
  </aside>

  <!-- Image Lightbox Modal -->
  <Teleport to="body">
    <Transition name="fade">
      <div 
        v-if="lightboxUrl" 
        class="fixed inset-0 z-[99999] bg-black/85 backdrop-blur-md flex items-center justify-center p-4"
        dir="rtl"
        @click="closeLightbox"
      >
        <div class="relative max-w-4xl max-h-[90vh] bg-slate-900 rounded-3xl overflow-hidden shadow-2xl border border-slate-800 flex flex-col" @click.stop>
          <!-- Header -->
          <div class="p-4 bg-slate-900/90 border-b border-slate-800 flex items-center justify-between">
            <span class="text-sm font-bold text-slate-200 truncate pr-2">{{ lightboxTitle }}</span>
            <div class="flex items-center gap-2">
              <a 
                :href="lightboxUrl" 
                download 
                target="_blank"
                class="px-3 py-1 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-xs font-bold transition flex items-center gap-1"
              >
                <span>⬇️</span>
                <span>تحميل</span>
              </a>
              <button aria-label="إغلاق معاينة الصورة" 
                @click="closeLightbox"
                class="p-1.5 text-slate-400 hover:text-white rounded-xl hover:bg-slate-800 transition cursor-pointer"
              >
                ✕
              </button>
            </div>
          </div>
          <!-- Image Body -->
          <div class="p-2 flex items-center justify-center overflow-auto max-h-[80vh]">
            <img :src="lightboxUrl" :alt="lightboxTitle" class="max-w-full max-h-[75vh] object-contain rounded-xl shadow-lg" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
