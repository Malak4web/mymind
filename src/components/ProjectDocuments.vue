<script setup>
import { store } from '../store'
import { computed, ref } from 'vue'
import MentionInput from './MentionInput.vue'
import MentionText from './MentionText.vue'

// UI Modals and Forms states
const showNewFolderModal = ref(false)
const newFolderName = ref('')

const showNoteModal = ref(false)
const noteIdToEdit = ref(null)
const noteTitle = ref('')
const noteContent = ref('')

// File Upload ref
const fileInputRef = ref(null)

// Data is loaded by the watch(activeProjectId) in store.js
// No need to reload here on mount

// Recursive Breadcrumbs Builder
const breadcrumbs = computed(() => {
  const list = []
  let currentId = store.activeDocumentFolderId
  const folders = Array.isArray(store.folders) ? store.folders : []
  // `seen` guards against a cyclic parent chain. Without it a folder that
  // referenced itself (or an ancestor) spun this loop forever and froze the
  // tab -- the same hang that crashed the test worker.
  const seen = new Set()
  while (currentId && !seen.has(currentId)) {
    seen.add(currentId)
    const folder = folders.find(f => f.id === currentId)
    if (!folder) break
    list.unshift(folder)
    currentId = folder.parent_id
  }
  return list
})

// Filter items belonging to the current directory
const currentFolders = computed(() => {
  const folders = Array.isArray(store.folders) ? store.folders : []
  return folders.filter(f => f.parent_id === store.activeDocumentFolderId)
})

const currentFiles = computed(() => {
  const files = Array.isArray(store.projectFiles) ? store.projectFiles : []
  return files.filter(f => f.folder_id === store.activeDocumentFolderId)
})

const currentNotes = computed(() => {
  const notes = Array.isArray(store.notes) ? store.notes : []
  return notes.filter(n => n.folder_id === store.activeDocumentFolderId)
})

// Folder actions
const handleCreateFolder = async () => {
  if (!newFolderName.value.trim()) return
  await store.createFolder(newFolderName.value.trim(), store.activeDocumentFolderId)
  newFolderName.value = ''
  showNewFolderModal.value = false
}

const handleDeleteFolder = async (id) => {
  if (confirm('هل أنت متأكد من حذف هذا المجلد؟ سيتم حذف جميع الملفات والملاحظات الموجودة داخله.')) {
    await store.deleteFolder(id)
  }
}

// File actions
const triggerFileUpload = () => {
  fileInputRef.value.click()
}

const handleFileSelect = async (event) => {
  const files = event.target.files
  if (!files || files.length === 0) return
  await store.uploadProjectFile(files[0], store.activeDocumentFolderId)
  event.target.value = '' // Clear input
}

const handleDeleteFile = async (id) => {
  if (confirm('هل أنت متأكد من حذف هذا الملف؟')) {
    await store.deleteProjectFile(id)
  }
}

const copyItemLink = (type, item) => {
  let link = ''
  if (type === 'file') {
    link = item.path ? (item.path.startsWith('http') ? item.path : `${store.apiBase.replace('/api', '')}${item.path}`) : `${window.location.origin}#file-${item.id}`
  } else if (type === 'folder') {
    link = `${window.location.origin}#folder-${item.id}`
  } else if (type === 'note') {
    link = `${window.location.origin}#note-${item.id}`
  }
  navigator.clipboard.writeText(link)
  store.toastSuccess(`تم نسخ رابط الـ ${type === 'file' ? 'ملف' : type === 'folder' ? 'مجلد' : 'ملاحظة'} إلى الحافظة بنجاح.`)
}

const openFileInNewTab = (file) => {
  const url = file.path ? (file.path.startsWith('http') ? file.path : `${store.apiBase.replace('/api', '')}${file.path}`) : `${store.apiBase}/project-files/${file.id}/download`
  window.open(url, '_blank')
}

const getFileIcon = (type) => {
  const t = type.toLowerCase()
  if (['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'].includes(t)) {
    return '🖼️' // Image
  }
  if (['pdf'].includes(t)) {
    return '📕' // PDF
  }
  if (['zip', 'rar', 'tar', 'gz'].includes(t)) {
    return '📦' // Archive
  }
  if (['doc', 'docx', 'txt', 'rtf'].includes(t)) {
    return '📄' // Document
  }
  if (['xls', 'xlsx', 'csv'].includes(t)) {
    return '📊' // Spreadsheet
  }
  return '📎' // Default attachment
}

// Note actions
const openNewNoteModal = () => {
  noteIdToEdit.value = null
  noteTitle.value = ''
  noteContent.value = ''
  showNoteModal.value = true
}

const openEditNoteModal = (note) => {
  noteIdToEdit.value = note.id
  noteTitle.value = note.title
  noteContent.value = note.content || ''
  showNoteModal.value = true
}

const handleSaveNote = async () => {
  if (!noteTitle.value.trim()) return
  if (noteIdToEdit.value) {
    // Edit existing note
    await store.updateNote(noteIdToEdit.value, noteTitle.value.trim(), noteContent.value, store.activeDocumentFolderId)
  } else {
    // Create new note
    await store.createNote(noteTitle.value.trim(), noteContent.value, store.activeDocumentFolderId)
  }
  showNoteModal.value = false
}

const handleDeleteNote = async (id) => {
  if (confirm('هل أنت متأكد من حذف هذه الملاحظة؟')) {
    await store.deleteNote(id)
  }
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
</script>

<template>
  <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-6 shadow-[0_4px_12px_rgba(0,0,0,0.015)] space-y-5 text-right" dir="rtl">
    
    <!-- Header Documents Actions -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4 gap-4">
      <div class="space-y-1">
        <h3 class="text-sm font-extrabold text-slate-900 dark:text-slate-100 flex items-center gap-1.5 justify-start">
          <span>المستندات والملفات المشتركة</span>
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
          </svg>
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 block font-semibold leading-relaxed">تنظيم ملفات المشروع، المرفقات والملاحظات الغنية في هيكل مجلدات منسق.</p>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2 flex-row-reverse w-full sm:w-auto flex-wrap">
        <button 
          @click="showNewFolderModal = true"
          class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-extrabold px-3 py-1.5 rounded-xl text-xs transition cursor-pointer flex items-center gap-1 min-h-[44px] min-w-[44px] justify-center"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
          </svg>
          مجلد جديد
        </button>

        <button 
          @click="openNewNoteModal"
          class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-extrabold px-3 py-1.5 rounded-xl text-xs transition cursor-pointer flex items-center gap-1 min-h-[44px] min-w-[44px] justify-center"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
          ملاحظة جديدة
        </button>

        <button 
          @click="triggerFileUpload"
          class="bg-violet-600 hover:bg-violet-700 text-white font-extrabold px-4 py-1.5 rounded-xl text-xs shadow-md shadow-violet-500/10 transition cursor-pointer flex items-center gap-1 min-h-[44px] min-w-[44px] justify-center"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
          </svg>
          رفع ملف
        </button>

        <input 
          type="file" 
          ref="fileInputRef" 
          @change="handleFileSelect" 
          class="hidden" 
        />
      </div>
    </div>

    <!-- Breadcrumb Path Navigation -->
    <div class="flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950/60 border border-slate-200/50 dark:border-slate-800 px-4 py-2.5 rounded-xl">
      <button 
        @click="store.activeDocumentFolderId = null"
        class="hover:text-violet-600 dark:hover:text-violet-400 font-extrabold flex items-center gap-1 transition cursor-pointer"
      >
        <span>📁 الرئيسية</span>
      </button>

      <template v-for="crumb in breadcrumbs" :key="crumb.id">
        <span class="text-slate-300">/</span>
        <button 
          @click="store.activeDocumentFolderId = crumb.id"
          class="hover:text-violet-600 dark:hover:text-violet-400 font-extrabold transition cursor-pointer"
        >
          {{ crumb.name }}
        </button>
      </template>
    </div>

    <!-- Directory Content List -->
    <div class="space-y-3">
      <div v-if="currentFolders.length === 0 && currentFiles.length === 0 && currentNotes.length === 0" class="text-center py-16 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl">
        <div class="text-2xl mb-2">📁</div>
        <p class="text-xs text-slate-400 italic font-semibold">هذا المجلد فارغ تماماً. قم بإنشاء مجلدات، رفع ملفات أو إضافة ملاحظات جديدة لبدء تنظيم عملك.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" v-else>
        
        <div 
          v-for="folder in currentFolders" 
          :key="'folder-' + folder.id"
          class="glass-card-hover rounded-2xl p-4 flex items-center justify-between group cursor-pointer btn-touch-active"
          @click="store.activeDocumentFolderId = folder.id"
        >
          <div class="flex items-center gap-3">
            <span class="text-2xl">📁</span>
            <div class="text-right">
              <span class="text-xs font-bold text-slate-900 dark:text-slate-100 block">{{ folder.name }}</span>
              <span class="text-[10px] font-semibold text-slate-400 block mt-0.5">مجلد فرعي</span>
            </div>
          </div>
          <div class="flex items-center gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 sm:focus-within:opacity-100 transition">
            <button 
              @click.stop="copyItemLink('folder', folder)"
              class="p-1.5 text-slate-400 hover:text-violet-600 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
              title="نسخ رابط المجلد" aria-label="نسخ رابط المجلد"
            >
              🔗
            </button>
            <button 
              @click.stop="handleDeleteFolder(folder.id)"
              class="p-1.5 text-slate-400 hover:text-rose-500 rounded-lg hover:bg-rose-50/50 dark:hover:bg-rose-950/20 transition cursor-pointer"
              title="حذف المجلد" aria-label="حذف المجلد"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Render Files -->
        <div 
          v-for="file in currentFiles" 
          :key="'file-' + file.id"
          class="glass-card-hover rounded-2xl p-4 flex items-center justify-between group cursor-pointer btn-touch-active"
          @click="openFileInNewTab(file)"
        >
          <div class="flex items-center gap-3 min-w-0">
            <span class="text-2xl">{{ getFileIcon(file.type) }}</span>
            <div class="text-right min-w-0">
              <span 
                class="text-xs font-bold text-slate-900 dark:text-slate-100 block hover:text-violet-600 truncate max-w-[150px]"
                title="فتح الملف في نافذة جديدة"
              >
                {{ file.name }}
              </span>
              <span class="text-[10px] font-semibold text-slate-400 block mt-0.5">{{ file.size }} • {{ file.type.toUpperCase() }}</span>
            </div>
          </div>
          <div class="flex items-center gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 sm:focus-within:opacity-100 transition">
            <button 
              @click.stop="openFileInNewTab(file)"
              class="p-1.5 text-slate-400 hover:text-violet-600 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
              title="فتح الملف في نافذة جديدة" aria-label="فتح الملف في نافذة جديدة"
            >
              ↗
            </button>
            <button 
              @click.stop="copyItemLink('file', file)"
              class="p-1.5 text-slate-400 hover:text-violet-600 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
              title="نسخ رابط الملف" aria-label="نسخ رابط الملف"
            >
              🔗
            </button>
            <button 
              @click.stop="handleDeleteFile(file.id)"
              class="p-1.5 text-slate-400 hover:text-rose-500 rounded-lg hover:bg-rose-50/50 dark:hover:bg-rose-950/20 transition cursor-pointer"
              title="حذف الملف" aria-label="حذف الملف"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Render Notes -->
        <div 
          v-for="note in currentNotes" 
          :key="'note-' + note.id"
          class="glass-card-hover rounded-2xl p-4 flex items-center justify-between group cursor-pointer btn-touch-active"
          @click="openEditNoteModal(note)"
        >
          <div class="flex items-center gap-3">
            <span class="text-2xl">📝</span>
            <div class="text-right">
              <span class="text-xs font-bold text-slate-900 dark:text-slate-100 block"><MentionText :content="note.title" /></span>
              <span class="text-[10px] font-semibold text-slate-400 block mt-0.5 max-w-xs truncate"><MentionText :content="note.content || 'ملاحظة غنية'" /></span>
            </div>
          </div>
          <div class="flex items-center gap-1 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 sm:focus-within:opacity-100 transition">
            <button 
              @click.stop="copyItemLink('note', note)"
              class="p-1.5 text-slate-400 hover:text-violet-600 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
              title="نسخ رابط الملاحظة" aria-label="نسخ رابط الملاحظة"
            >
              🔗
            </button>
            <button 
              @click.stop="handleDeleteNote(note.id)"
              class="p-1.5 text-slate-400 hover:text-rose-500 rounded-lg hover:bg-rose-50/50 dark:hover:bg-rose-950/20 transition cursor-pointer"
              title="حذف الملاحظة" aria-label="حذف الملاحظة"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>

      </div>
    </div>

    <!-- Create Folder Modal Popup (Bottom Sheet on Mobile) -->
    <Transition name="sheet">
      <div v-if="showNewFolderModal" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4" dir="rtl">
        <!-- Backdrop -->
        <div @click="showNewFolderModal = false; newFolderName = ''" class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>

        <!-- Modal Content -->
        <div 
          @touchstart="handleTouchStart"
          @touchmove="handleTouchMove"
          @touchend="handleTouchEnd(() => { showNewFolderModal = false; newFolderName = '' })"
          class="relative z-10 bg-white dark:bg-slate-900 border-t sm:border border-slate-200 dark:border-slate-800 rounded-t-3xl sm:rounded-3xl p-5 sm:p-6 max-w-sm max-h-[85vh] overflow-y-auto w-full shadow-2xl space-y-4 text-right transform transition-all duration-300"
        >
          <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto my-2.5 shrink-0 sm:hidden cursor-grab"></div>
          <h3 class="text-sm font-extrabold text-slate-900 dark:text-slate-100 flex items-center gap-1 justify-start">
            <span>إنشاء مجلد جديد</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
            </svg>
          </h3>
          
          <div class="space-y-1.5">
            <label class="text-[11px] font-bold text-slate-400 dark:text-slate-400">اسم المجلد</label>
            <input 
              type="text" 
              v-model="newFolderName" 
              @keydown.enter.prevent="handleCreateFolder"
              placeholder="مثال: التصاميم والواجهات"
              class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-violet-500 font-extrabold"
            />
          </div>

          <div class="flex items-center justify-start space-x-2 flex-row-reverse pt-2 border-t border-slate-100 dark:border-slate-800">
            <button 
              @click="handleCreateFolder" 
              class="bg-violet-600 hover:bg-violet-700 text-white font-extrabold py-2 px-4 rounded-xl text-xs transition cursor-pointer"
            >
              إنشاء مجلد
            </button>
            <button 
              @click="showNewFolderModal = false; newFolderName = ''" 
              class="text-slate-400 hover:text-slate-600 font-bold py-2 px-3 rounded-xl text-xs transition cursor-pointer mr-auto"
            >
              إلغاء
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Note Editor Modal Popup (Bottom Sheet on Mobile) -->
    <Transition name="sheet">
      <div v-if="showNoteModal" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4" dir="rtl">
        <!-- Backdrop -->
        <div @click="showNoteModal = false" class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>

        <!-- Modal Content -->
        <div 
          @touchstart="handleTouchStart"
          @touchmove="handleTouchMove"
          @touchend="handleTouchEnd(() => showNoteModal = false)"
          class="relative z-10 bg-white dark:bg-slate-900 border-t sm:border border-slate-200 dark:border-slate-800 rounded-t-3xl sm:rounded-3xl p-5 sm:p-6 max-w-lg max-h-[90vh] overflow-y-auto w-full shadow-2xl space-y-4 text-right transform transition-all duration-300"
        >
          <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto my-2.5 shrink-0 sm:hidden cursor-grab"></div>
          <h3 class="text-sm font-extrabold text-slate-900 dark:text-slate-100 flex items-center gap-1 justify-start">
            <span>{{ noteIdToEdit ? 'تعديل الملاحظة' : 'ملاحظة جديدة' }}</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
          </h3>

          <div class="space-y-4">
            <div class="space-y-1.5">
              <label class="text-[11px] font-bold text-slate-400 dark:text-slate-400">العنوان</label>
              <MentionInput 
                v-model="noteTitle" 
                placeholder="اكتب عنوان الملاحظة... (استخدم / للملفات، و @ للمنشن)"
              />
            </div>

            <div class="space-y-1.5">
              <label class="text-[11px] font-bold text-slate-400 dark:text-slate-400">محتوى الملاحظة</label>
              <MentionInput 
                v-model="noteContent" 
                :is-textarea="true"
                :rows="5"
                placeholder="اكتب تفاصيل الملاحظة... (استخدم / للملفات والمجلدات، و @ للمنشن)"
              />
            </div>
          </div>

          <div class="flex items-center justify-start space-x-2 flex-row-reverse pt-2 border-t border-slate-100 dark:border-slate-800">
            <button 
              @click="handleSaveNote" 
              class="bg-violet-600 hover:bg-violet-700 text-white font-extrabold py-2 px-4 rounded-xl text-xs transition cursor-pointer"
            >
              {{ noteIdToEdit ? 'حفظ التعديلات' : 'حفظ الملاحظة' }}
            </button>
            <button 
              @click="showNoteModal = false" 
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
