<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { store } from '../store'
import MobileBottomSheet from './MobileBottomSheet.vue'

// View mode: 'grid' or 'list'
const viewMode = ref('grid')

// Filters and Search
const searchQuery = ref('')
const selectedCategory = ref('all')
const selectedColor = ref('all')
const filterPinnedOnly = ref(false)
const filterHasImagesOnly = ref(false)

// Modals
const isCreateModalOpen = ref(false)
const isEditModalOpen = ref(false)
const isCategoryModalOpen = ref(false)
const editingIdea = ref(null)

// Lightbox for image preview
const lightboxImage = ref(null)

// Form state for New / Edit Idea
const form = ref({
  id: null,
  title: '',
  content: '',
  images: [],
  color: 'amber',
  category: 'عام',
  is_pinned: false,
  idea_date: new Date().toISOString().slice(0, 10)
})

const isUploadingImage = ref(false)
const newCategoryName = ref('')
const copySuccessId = ref(null)

// Color definitions matching the design system
const colorOptions = [
  { id: 'amber', name: 'أصفر كهرماني', bg: 'bg-amber-500/15 dark:bg-amber-950/30', border: 'border-amber-400/50 dark:border-amber-700/60', text: 'text-amber-900 dark:text-amber-200', dot: 'bg-amber-400' },
  { id: 'violet', name: 'بنفسجي إبداعي', bg: 'bg-violet-500/15 dark:bg-violet-950/30', border: 'border-violet-400/50 dark:border-violet-700/60', text: 'text-violet-900 dark:text-violet-200', dot: 'bg-violet-500' },
  { id: 'sky', name: 'أزرق سماوي', bg: 'bg-sky-500/15 dark:bg-sky-950/30', border: 'border-sky-400/50 dark:border-sky-700/60', text: 'text-sky-900 dark:text-sky-200', dot: 'bg-sky-400' },
  { id: 'emerald', name: 'أخضر زمردي', bg: 'bg-emerald-500/15 dark:bg-emerald-950/30', border: 'border-emerald-400/50 dark:border-emerald-700/60', text: 'text-emerald-900 dark:text-emerald-200', dot: 'bg-emerald-500' },
  { id: 'rose', name: 'وردي ملهم', bg: 'bg-rose-500/15 dark:bg-rose-950/30', border: 'border-rose-400/50 dark:border-rose-700/60', text: 'text-rose-900 dark:text-rose-200', dot: 'bg-rose-400' },
  { id: 'slate', name: 'رمادي احترافي', bg: 'bg-slate-500/15 dark:bg-slate-800/40', border: 'border-slate-400/50 dark:border-slate-700/60', text: 'text-slate-900 dark:text-slate-200', dot: 'bg-slate-400' },
]

const getColorMeta = (colorId) => {
  return colorOptions.find(c => c.id === colorId) || colorOptions[0]
}

// Filtered and Searched Ideas
const filteredIdeas = computed(() => {
  let list = store.ideas || []

  if (selectedCategory.value !== 'all') {
    list = list.filter(i => i.category === selectedCategory.value)
  }

  if (selectedColor.value !== 'all') {
    list = list.filter(i => i.color === selectedColor.value)
  }

  if (filterPinnedOnly.value) {
    list = list.filter(i => i.is_pinned)
  }

  if (filterHasImagesOnly.value) {
    list = list.filter(i => Array.isArray(i.images) && i.images.length > 0)
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()
    list = list.filter(i => {
      const matchTitle = (i.title || '').toLowerCase().includes(q)
      const matchContent = (i.content || '').toLowerCase().includes(q)
      const matchCategory = (i.category || '').toLowerCase().includes(q)
      return matchTitle || matchContent || matchCategory
    })
  }

  // Pinned items stay at top, then sort_order, then createdAt
  return [...list].sort((a, b) => {
    if (a.is_pinned !== b.is_pinned) return a.is_pinned ? -1 : 1
    return (a.sort_order || 0) - (b.sort_order || 0)
  })
})

// Stats
const totalIdeasCount = computed(() => (store.ideas || []).length)
const pinnedIdeasCount = computed(() => (store.ideas || []).filter(i => i.is_pinned).length)
const withImagesCount = computed(() => (store.ideas || []).filter(i => Array.isArray(i.images) && i.images.length > 0).length)

// Drag and Drop
const draggedIdeaIndex = ref(null)
const dragOverIdeaIndex = ref(null)

const handleDragStart = (e, idea, idx) => {
  draggedIdeaIndex.value = idx
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
    e.dataTransfer.setData('text/plain', String(idea.id))
  }
}

const handleDragOver = (e, idx) => {
  e.preventDefault()
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'move'
  dragOverIdeaIndex.value = idx
}

const handleDrop = async (e, targetIdx) => {
  e.preventDefault()
  const sourceIdx = draggedIdeaIndex.value
  if (sourceIdx === null || sourceIdx === targetIdx) {
    handleDragEnd()
    return
  }

  const list = [...filteredIdeas.value]
  const [movedItem] = list.splice(sourceIdx, 1)
  list.splice(targetIdx, 0, movedItem)

  const newOrderedIds = list.map(item => item.id)
  await store.reorderIdeas(newOrderedIds)
  handleDragEnd()
}

const handleDragEnd = () => {
  draggedIdeaIndex.value = null
  dragOverIdeaIndex.value = null
}

// Reset Form
const resetForm = () => {
  form.value = {
    id: null,
    title: '',
    content: '',
    images: [],
    color: 'amber',
    category: 'عام',
    is_pinned: false,
    idea_date: new Date().toISOString().slice(0, 10)
  }
}

// Open Creation Modal
const openCreateModal = () => {
  resetForm()
  isCreateModalOpen.value = true
}

// Open Edit Modal
const openEditModal = (idea) => {
  editingIdea.value = idea
  form.value = {
    id: idea.id,
    title: idea.title || '',
    content: idea.content || '',
    images: Array.isArray(idea.images) ? [...idea.images] : [],
    color: idea.color || 'amber',
    category: idea.category || 'عام',
    is_pinned: Boolean(idea.is_pinned),
    idea_date: idea.idea_date || new Date().toISOString().slice(0, 10)
  }
  isEditModalOpen.value = true
}

// Save Idea (Create or Edit)
const handleSaveIdea = async () => {
  if (!form.value.title.trim()) return

  if (isEditModalOpen.value && form.value.id) {
    await store.updateIdea(form.value.id, {
      title: form.value.title.trim(),
      content: form.value.content,
      images: form.value.images,
      color: form.value.color,
      category: form.value.category,
      is_pinned: form.value.is_pinned,
      idea_date: form.value.idea_date
    })
    store.toastSuccess('تم تحديث الفكرة بنجاح ✨')
    isEditModalOpen.value = false
  } else {
    await store.addIdea({
      title: form.value.title.trim(),
      content: form.value.content,
      images: form.value.images,
      color: form.value.color,
      category: form.value.category,
      is_pinned: form.value.is_pinned,
      idea_date: form.value.idea_date
    })
    store.toastSuccess('تمت إضافة الفكرة الجديدة بنجاح 💡')
    isCreateModalOpen.value = false
  }
  resetForm()
}

// Delete Idea
const handleDeleteIdea = async (id) => {
  if (confirm('هل أنت متأكد من رغبتك في حذف هذه الفكرة؟')) {
    await store.deleteIdea(id)
    store.toastSuccess('تم حذف الفكرة 🗑️')
  }
}

// Toggle Pin
const handleTogglePin = async (id) => {
  await store.togglePinIdea(id)
}

// Quick Change Color
const handleQuickColorChange = async (idea, colorId) => {
  if (idea.color === colorId) return
  await store.updateIdea(idea.id, { color: colorId })
}

// Clipboard Actions: Copy All Content
const copyAllContent = async (idea) => {
  const parts = []
  if (idea.title) parts.push(`📌 ${idea.title}`)
  if (idea.idea_date) parts.push(`📅 التاريخ: ${idea.idea_date}`)
  if (idea.category) parts.push(`🏷️ التصنيف: ${idea.category}`)
  if (idea.content) parts.push(`\n${idea.content}`)

  const textToCopy = parts.join('\n')
  try {
    await navigator.clipboard.writeText(textToCopy)
    copySuccessId.value = idea.id
    store.toastSuccess('تم نسخ كامل محتوى الفكرة إلى الحافظة 📋')
    setTimeout(() => {
      if (copySuccessId.value === idea.id) copySuccessId.value = null
    }, 2500)
  } catch (e) {
    store.toastError('تعذر النسخ إلى الحافظة')
  }
}

// Copy Specific Image to Clipboard or Link
const copyImageToClipboard = async (imgObj) => {
  const url = typeof imgObj === 'string' ? imgObj : imgObj?.url
  if (!url) return

  try {
    // Attempt blob copy if same-origin / fetchable
    const res = await fetch(url)
    const blob = await res.blob()
    if (navigator.clipboard && navigator.clipboard.write) {
      await navigator.clipboard.write([
        new ClipboardItem({ [blob.type]: blob })
      ])
      store.toastSuccess('تم نسخ الصورة إلى الحافظة 🖼️')
      return
    }
  } catch (e) {
    // fallback to URL copy
  }

  try {
    await navigator.clipboard.writeText(url)
    store.toastSuccess('تم نسخ رابط الصورة إلى الحافظة 🔗')
  } catch (e) {
    store.toastError('تعذر نسخ الصورة')
  }
}

// Image File Upload Handler
const handleFileUpload = async (e) => {
  const files = e.target.files
  if (!files || files.length === 0) return
  await processFiles(Array.from(files))
  e.target.value = ''
}

// Process Files (from file picker, dropzone, or paste)
const processFiles = async (fileList) => {
  isUploadingImage.value = true
  for (const file of fileList) {
    if (!file.type.startsWith('image/')) continue

    try {
      if (store.token) {
        // Upload to server
        const uploaded = await store.uploadIdeaImage(file)
        form.value.images.push(uploaded)
      } else {
        // Offline / Guest: convert to local Base64 URL
        const base64Url = await fileToBase64(file)
        form.value.images.push({
          id: Date.now() + Math.random(),
          name: file.name,
          url: base64Url,
          size: `${Math.round(file.size / 1024)} KB`
        })
      }
    } catch (err) {
      store.toastError('فشل رفع الصورة: ' + (err.message || 'خطأ غير معروف'))
    }
  }
  isUploadingImage.value = false
}

const fileToBase64 = (file) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(reader.result)
    reader.onerror = reject
    reader.readAsDataURL(file)
  })
}

// Remove Attached Image from Form
const removeFormImage = (index) => {
  form.value.images.splice(index, 1)
}

// Global & Container Paste Listener (Ctrl+V)
const handlePasteEvent = async (e) => {
  const items = e.clipboardData?.items
  if (!items) return

  const imageFiles = []
  for (const item of items) {
    if (item.type.indexOf('image') !== -1) {
      const blob = item.getAsFile()
      if (blob) imageFiles.push(blob)
    }
  }

  if (imageFiles.length > 0) {
    e.preventDefault()
    if (isCreateModalOpen.value || isEditModalOpen.value) {
      // Paste inside active modal
      await processFiles(imageFiles)
      store.toastSuccess(`تم لصق ${imageFiles.length} صورة بنجاح 🖼️`)
    } else {
      // Paste on main board: open Create modal with pre-loaded pasted image!
      resetForm()
      form.value.title = 'فكرة ملصقة: ' + new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' })
      isCreateModalOpen.value = true
      await processFiles(imageFiles)
      store.toastSuccess(`تم إنشاء فكرة جديدة بالصور الملصقة 💡`)
    }
  }
}

onMounted(() => {
  window.addEventListener('paste', handlePasteEvent)
})

onUnmounted(() => {
  window.removeEventListener('paste', handlePasteEvent)
})

// Category Management
const handleAddCategory = () => {
  const name = newCategoryName.value.trim()
  if (!name) return
  if (store.addIdeaCategory(name)) {
    form.value.category = name
    newCategoryName.value = ''
    store.toastSuccess('تمت إضافة التصنيف بنجاح 🏷️')
  } else {
    store.toastError('التصنيف موجود بالفعل')
  }
}

const handleDeleteCategory = (cat) => {
  if (confirm(`هل أنت متأكد من حذف التصنيف "${cat}"؟`)) {
    store.deleteIdeaCategory(cat)
    if (form.value.category === cat) form.value.category = 'عام'
    if (selectedCategory.value === cat) selectedCategory.value = 'all'
    store.toastSuccess('تم حذف التصنيف')
  }
}
</script>

<template>
  <div class="space-y-4 sm:space-y-6">
    
    <!-- Top Header Glass Card -->
    <div class="relative bg-white/70 dark:bg-slate-900/70 backdrop-blur-2xl border border-white/40 dark:border-slate-800/60 shadow-xl rounded-2xl sm:rounded-3xl p-3 sm:p-5 md:p-6 overflow-hidden">
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        
        <div>
          <div class="flex items-center gap-2 mb-1">
            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black bg-amber-500/15 text-amber-600 dark:text-amber-300 border border-amber-500/30 flex items-center gap-1">
              💡 لوحة الأفكار والإلهام
            </span>
            <span v-if="pinnedIdeasCount > 0" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-500/15 text-violet-600 dark:text-violet-300">
              📌 {{ pinnedIdeasCount }} مثبتة
            </span>
          </div>
          <h2 class="text-lg sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
            أفكاري وملاحظاتي الذكية
          </h2>
          <p class="text-[11px] sm:text-sm text-slate-600 dark:text-slate-400 mt-0.5 leading-tight">
            دوّن أفكارك، الصق الصور من الحافظة (Ctrl+V) مباشرة، ورتّبها بالسحب والإفلات بكل حرية ✨
          </p>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto">
          <!-- View Mode Toggle Button -->
          <div class="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-xl border border-slate-200/80 dark:border-slate-700">
            <button
              @click="viewMode = 'grid'"
              :class="[
                'p-2 rounded-lg text-xs font-bold transition cursor-pointer min-h-[38px] min-w-[38px] flex items-center justify-center',
                viewMode === 'grid' ? 'bg-white dark:bg-slate-700 text-violet-600 dark:text-violet-300 shadow-sm' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'
              ]"
              title="عرض كشبكة بطاقات" aria-label="عرض كشبكة بطاقات"
            >
              🔲
            </button>
            <button
              @click="viewMode = 'list'"
              :class="[
                'p-2 rounded-lg text-xs font-bold transition cursor-pointer min-h-[38px] min-w-[38px] flex items-center justify-center',
                viewMode === 'list' ? 'bg-white dark:bg-slate-700 text-violet-600 dark:text-violet-300 shadow-sm' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'
              ]"
              title="عرض كقائمة" aria-label="عرض كقائمة"
            >
              📋
            </button>
          </div>

          <!-- Add New Idea Button -->
          <button
            @click="openCreateModal"
            class="flex-1 sm:flex-initial px-4 py-2.5 sm:px-6 sm:py-3 rounded-xl sm:rounded-2xl bg-gradient-to-r from-amber-500 via-amber-600 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-white font-black text-xs sm:text-sm transition-all cursor-pointer flex items-center justify-center gap-2 shadow-lg shadow-amber-500/25 active:scale-95 min-h-[44px]"
          >
            <span class="text-base font-bold">+</span>
            <span>فكرة جديدة</span>
          </button>
        </div>

      </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="bg-white/50 dark:bg-slate-900/50 backdrop-blur-md p-3 sm:p-4 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 space-y-3">
      
      <!-- Search & Category Selector Row -->
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5 sm:gap-4">
        
        <!-- Search Input -->
        <div class="relative flex-1">
          <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm">🔍</span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="ابحث في الأفكار، النصوص، التصنيفات..."
            class="w-full pl-3 pr-9 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 text-xs font-semibold border border-slate-200 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500 transition min-h-[42px]"
          />
        </div>

        <!-- Category Dropdown & Manage Button -->
        <div class="flex items-center gap-2">
          <select
            v-model="selectedCategory"
            class="px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-xs font-bold border border-slate-200 dark:border-slate-700 focus:outline-none cursor-pointer min-h-[42px]"
          >
            <option value="all">كل التصنيفات ({{ totalIdeasCount }})</option>
            <option v-for="cat in store.ideaCategories" :key="cat" :value="cat">🏷️ {{ cat }}</option>
          </select>

          <button
            @click="isCategoryModalOpen = true"
            type="button"
            class="px-2.5 py-2 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-700 dark:text-amber-300 text-xs font-bold border border-amber-500/20 transition cursor-pointer flex items-center gap-1 shrink-0 min-h-[42px]"
            title="إدارة التصنيفات" aria-label="إدارة التصنيفات"
          >
            <span>⚙️</span>
            <span class="hidden md:inline">التصنيفات</span>
          </button>
        </div>

      </div>

      <!-- Color Filters & Quick Toggle Pills Row -->
      <div class="flex items-center justify-between gap-2 flex-wrap pt-1 border-t border-slate-200/60 dark:border-slate-800/60">
        
        <!-- Color filter pills -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
          <button
            @click="selectedColor = 'all'"
            :class="[
              'px-2.5 py-1 rounded-lg text-[11px] font-extrabold border transition cursor-pointer flex items-center gap-1',
              selectedColor === 'all' ? 'bg-slate-800 text-white dark:bg-white dark:text-slate-900 border-transparent shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-transparent hover:bg-slate-200 dark:hover:bg-slate-700'
            ]"
          >
            🌈 كل الألوان
          </button>
          
          <button
            v-for="c in colorOptions"
            :key="c.id"
            @click="selectedColor = selectedColor === c.id ? 'all' : c.id"
            :class="[
              'px-2.5 py-1 rounded-lg text-[11px] font-extrabold border transition cursor-pointer flex items-center gap-1.5',
              selectedColor === c.id ? `${c.bg} ${c.border} ring-2 ring-amber-500` : 'bg-slate-100/80 dark:bg-slate-800/80 border-transparent text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'
            ]"
          >
            <span :class="['w-2.5 h-2.5 rounded-full', c.dot]"></span>
            <span>{{ c.name.split(' ')[0] }}</span>
          </button>
        </div>

        <!-- Quick Toggles (Pinned / Images) -->
        <div class="flex items-center gap-2 text-xs font-bold">
          <button
            @click="filterPinnedOnly = !filterPinnedOnly"
            :class="[
              'px-2.5 py-1 rounded-lg transition cursor-pointer flex items-center gap-1 text-[11px]',
              filterPinnedOnly ? 'bg-amber-500 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200'
            ]"
          >
            <span>📌 المثبتة</span>
            <span v-if="pinnedIdeasCount > 0">({{ pinnedIdeasCount }})</span>
          </button>

          <button
            @click="filterHasImagesOnly = !filterHasImagesOnly"
            :class="[
              'px-2.5 py-1 rounded-lg transition cursor-pointer flex items-center gap-1 text-[11px]',
              filterHasImagesOnly ? 'bg-sky-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200'
            ]"
          >
            <span>🖼️ صور</span>
            <span v-if="withImagesCount > 0">({{ withImagesCount }})</span>
          </button>
        </div>

      </div>

    </div>

    <!-- Ideas Board Container (Grid or List) -->
    <div
      v-if="filteredIdeas.length > 0"
      :class="[
        viewMode === 'grid'
          ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5 sm:gap-5'
          : 'space-y-3'
      ]"
    >
      <div
        v-for="(idea, idx) in filteredIdeas"
        :key="idea.id"
        draggable="true"
        @dragstart="handleDragStart($event, idea, idx)"
        @dragover="handleDragOver($event, idx)"
        @drop="handleDrop($event, idx)"
        @dragend="handleDragEnd"
        :class="[
          'group relative p-4 sm:p-5 rounded-2xl sm:rounded-3xl border transition-all duration-300 flex flex-col justify-between overflow-hidden shadow-sm hover:shadow-md glass-card-hover',
          getColorMeta(idea.color).bg,
          getColorMeta(idea.color).border,
          draggedIdeaIndex === idx ? 'opacity-30 border-dashed border-amber-500 scale-95' : '',
          dragOverIdeaIndex === idx && draggedIdeaIndex !== idx ? 'ring-2 ring-amber-500 border-amber-500' : ''
        ]"
      >
        
        <!-- Pinned Accent Indicator (if pinned) -->
        <div
          v-if="idea.is_pinned"
          class="absolute top-0 right-0 left-0 h-1 bg-gradient-to-r from-amber-400 via-orange-500 to-amber-500"
        ></div>

        <!-- Top Row: Drag Handle, Badges, Pin Button -->
        <div>
          <div class="flex items-center justify-between gap-2 mb-2.5">
            
            <div class="flex items-center gap-1.5 flex-wrap min-w-0">
              <!-- Drag Grip -->
              <span
                class="text-slate-400 hover:text-amber-600 dark:hover:text-amber-300 cursor-grab active:cursor-grabbing p-1 transition shrink-0"
                title="اسحب لترتيب الفكرة"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16" />
                </svg>
              </span>

              <!-- Category Badge -->
              <span class="px-2 py-0.5 rounded-md bg-white/60 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 text-[10px] sm:text-[11px] font-extrabold border border-slate-200/60 dark:border-slate-700/60">
                🏷️ {{ idea.category || 'عام' }}
              </span>

              <!-- Date Badge -->
              <span v-if="idea.idea_date" class="px-2 py-0.5 rounded-md bg-white/40 dark:bg-slate-900/40 text-slate-600 dark:text-slate-400 text-[10px] font-bold">
                📅 {{ idea.idea_date }}
              </span>
            </div>

            <!-- Pin Toggle Button -->
            <button
              @click="handleTogglePin(idea.id)"
              :class="[
                'p-1.5 rounded-lg transition cursor-pointer min-h-[36px] min-w-[36px] flex items-center justify-center shrink-0',
                idea.is_pinned
                  ? 'bg-amber-500 text-white shadow-sm'
                  : 'text-slate-400 hover:text-amber-500 hover:bg-white/60 dark:hover:bg-slate-800'
              ]"
              :title="idea.is_pinned ? 'إلغاء التثبيت' : 'تثبيت في الأعلى'"
              aria-label="تثبيت الفكرة"
            >
              📌
            </button>

          </div>

          <!-- Title -->
          <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-white tracking-tight mb-2 leading-snug">
            {{ idea.title }}
          </h3>

          <!-- Content Body -->
          <p
            v-if="idea.content"
            class="text-xs sm:text-sm text-slate-700 dark:text-slate-300 font-medium whitespace-pre-wrap leading-relaxed mb-3 break-words"
          >
            {{ idea.content }}
          </p>

          <!-- Attached Images Grid -->
          <div
            v-if="Array.isArray(idea.images) && idea.images.length > 0"
            class="grid grid-cols-2 gap-2 mb-3.5"
          >
            <div
              v-for="(img, imgIdx) in idea.images"
              :key="imgIdx"
              class="relative group/img rounded-xl overflow-hidden border border-black/10 dark:border-white/10 aspect-video bg-slate-900/10 dark:bg-slate-950/40"
            >
              <img
                :src="typeof img === 'string' ? img : img.url"
                :alt="idea.title"
                class="w-full h-full object-cover cursor-pointer group-hover/img:scale-105 transition-transform duration-300"
                @click="lightboxImage = typeof img === 'string' ? img : img.url"
                loading="lazy"
              />
              
              <!-- Quick image actions overlay -->
              <div class="absolute inset-0 bg-black/40 opacity-0 group-hover/img:opacity-100 transition-opacity flex items-center justify-center gap-1.5 p-1">
                <button
                  @click.stop="copyImageToClipboard(img)"
                  class="p-1.5 rounded-lg bg-white/90 text-slate-800 hover:bg-white text-xs font-bold shadow transition cursor-pointer"
                  title="نسخ الصورة"
                >
                  📋
                </button>
                <button
                  @click.stop="lightboxImage = typeof img === 'string' ? img : img.url"
                  class="p-1.5 rounded-lg bg-white/90 text-slate-800 hover:bg-white text-xs font-bold shadow transition cursor-pointer"
                  title="تكبير الصورة"
                >
                  🔍
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Bottom Action Bar -->
        <div class="mt-3 pt-3 border-t border-black/10 dark:border-white/10 flex items-center justify-between gap-2">
          
          <!-- 1-Click Copy All Button -->
          <button
            @click="copyAllContent(idea)"
            :class="[
              'px-2.5 py-1.5 rounded-xl text-xs font-extrabold transition cursor-pointer flex items-center gap-1.5 shrink-0 min-h-[36px]',
              copySuccessId === idea.id
                ? 'bg-emerald-600 text-white shadow-sm'
                : 'bg-white/70 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700 border border-slate-200/60 dark:border-slate-700'
            ]"
            title="نسخ كامل محتوى الفكرة إلى الحافظة"
          >
            <span>{{ copySuccessId === idea.id ? '✓' : '📋' }}</span>
            <span>{{ copySuccessId === idea.id ? 'تم النسخ!' : 'نسخ المحتوى' }}</span>
          </button>

          <!-- Color palette picker dots -->
          <div class="flex items-center gap-1 shrink-0">
            <button
              v-for="c in colorOptions"
              :key="c.id"
              @click="handleQuickColorChange(idea, c.id)"
              :class="[
                'w-4 h-4 rounded-full transition-transform cursor-pointer',
                c.dot,
                idea.color === c.id ? 'ring-2 ring-black dark:ring-white scale-125' : 'opacity-60 hover:opacity-100 hover:scale-110'
              ]"
              :title="`تغيير اللون إلى ${c.name}`"
            ></button>
          </div>

          <!-- Edit & Delete Buttons -->
          <div class="flex items-center gap-1 shrink-0">
            <button
              @click="openEditModal(idea)"
              class="p-1.5 rounded-lg text-slate-600 dark:text-slate-300 hover:text-violet-600 hover:bg-white/80 dark:hover:bg-slate-800 transition cursor-pointer min-h-[36px] min-w-[36px] flex items-center justify-center"
              title="تعديل الفكرة" aria-label="تعديل الفكرة"
            >
              ✏️
            </button>
            <button
              @click="handleDeleteIdea(idea.id)"
              class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer min-h-[36px] min-w-[36px] flex items-center justify-center"
              title="حذف الفكرة" aria-label="حذف الفكرة"
            >
              🗑️
            </button>
          </div>

        </div>

      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else
      class="text-center py-12 sm:py-16 bg-white/40 dark:bg-slate-900/40 backdrop-blur-md rounded-2xl sm:rounded-3xl border border-dashed border-slate-300 dark:border-slate-800 p-6"
    >
      <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto mb-3 rounded-3xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-3xl sm:text-4xl shadow-inner">
        💡
      </div>
      <h3 class="text-base sm:text-lg font-black text-slate-800 dark:text-slate-200">
        {{ searchQuery || selectedCategory !== 'all' || selectedColor !== 'all' ? 'لا توجد نتائج مطابقة لبحثك' : 'لا توجد أفكار مسجلة بعد' }}
      </h3>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 max-w-md mx-auto leading-relaxed">
        اضغط على زر <b class="text-amber-600 dark:text-amber-400">+ فكرة جديدة</b> أو الصق صورة مباشرة من الحافظة (Ctrl+V) لتبدأ بتدوين إلهاماتك اليوم!
      </p>
      <div class="mt-4 flex items-center justify-center gap-2">
        <button
          @click="openCreateModal"
          class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-extrabold text-xs shadow-md transition cursor-pointer"
        >
          + إضافة فكرة الآن
        </button>
      </div>
    </div>

    <!-- Create / Edit Modal (MobileBottomSheet) -->
    <MobileBottomSheet
      :isOpen="isCreateModalOpen || isEditModalOpen"
      @close="isCreateModalOpen = false; isEditModalOpen = false; resetForm()"
      :title="isEditModalOpen ? 'تعديل الفكرة' : '💡 إضافة فكرة جديدة'"
      icon="✨"
      maxWidth="max-w-xl"
    >
      <form @submit.prevent="handleSaveIdea" class="space-y-4 sm:space-y-5 text-right">
        
        <!-- Title Input -->
        <div>
          <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-1.5">عنوان الفكرة *</label>
          <input
            v-model="form.title"
            type="text"
            placeholder="مثلاً: خطة محتوى رمضان، تصميم أيقونات 3D، فكرة تطبيق ذكي..."
            required
            class="w-full px-4 py-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-bold focus:ring-2 focus:ring-amber-500 outline-none"
          />
        </div>

        <!-- Content Area with direct paste support -->
        <div>
          <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-1.5">التفاصيل والشروحات (اختياري)</label>
          <textarea
            v-model="form.content"
            placeholder="اكتب أفكارك وملاحظاتك بالتفصيل هنا... يمكنك لصق صور (Ctrl+V) في أي وقت!"
            rows="4"
            class="w-full px-4 py-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-xs sm:text-sm font-semibold focus:ring-2 focus:ring-amber-500 outline-none resize-y"
          ></textarea>
        </div>

        <!-- Images Dropzone / Paste Zone -->
        <div>
          <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-1.5">الصور والمرفقات البصرية</label>
          
          <div class="border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-amber-500 rounded-2xl p-4 text-center bg-slate-50/50 dark:bg-slate-800/50 transition">
            <input
              type="file"
              id="idea-image-input"
              multiple
              accept="image/*"
              @change="handleFileUpload"
              class="hidden"
            />
            <label
              for="idea-image-input"
              class="cursor-pointer flex flex-col items-center justify-center gap-1.5"
            >
              <span class="text-2xl">🖼️</span>
              <span class="text-xs font-extrabold text-slate-800 dark:text-slate-200">
                اضغط لاختيار صور أو الصق من الحافظة (Ctrl+V)
              </span>
              <span class="text-[11px] text-slate-400 font-semibold">
                يدعم صيغ PNG, JPG, WebP, GIF حتى 20 ميجابايت
              </span>
            </label>
            <div v-if="isUploadingImage" class="mt-2 text-xs font-bold text-amber-600 animate-pulse">
              ⏳ جاري رفع ومعالجة الصور...
            </div>
          </div>

          <!-- Previews of Attached Images in Form -->
          <div
            v-if="form.images && form.images.length > 0"
            class="grid grid-cols-3 gap-2.5 mt-3"
          >
            <div
              v-for="(img, idx) in form.images"
              :key="idx"
              class="relative rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 aspect-video bg-slate-100 dark:bg-slate-800 group"
            >
              <img
                :src="typeof img === 'string' ? img : img.url"
                class="w-full h-full object-cover"
              />
              <button
                type="button"
                @click="removeFormImage(idx)"
                class="absolute top-1 left-1 p-1 rounded-full bg-rose-600 text-white text-[10px] font-bold shadow hover:bg-rose-700 transition"
                title="حذف الصورة"
              >
                ✕
              </button>
            </div>
          </div>
        </div>

        <!-- Meta options: Color, Category, Date, Pin -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-2 border-t border-slate-200/80 dark:border-slate-800">
          
          <!-- Color Picker -->
          <div>
            <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-1.5">لون البطاقة</label>
            <div class="flex items-center gap-1.5 flex-wrap">
              <button
                v-for="c in colorOptions"
                :key="c.id"
                type="button"
                @click="form.color = c.id"
                :class="[
                  'w-8 h-8 rounded-xl flex items-center justify-center transition-all cursor-pointer',
                  c.dot,
                  form.color === c.id ? 'ring-2 ring-offset-2 ring-amber-500 scale-110 shadow-md' : 'opacity-70 hover:opacity-100'
                ]"
                :title="c.name"
              >
                <span v-if="form.color === c.id" class="text-white text-xs font-bold">✓</span>
              </button>
            </div>
          </div>

          <!-- Category Selector -->
          <div>
            <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-1.5">التصنيف</label>
            <select
              v-model="form.category"
              class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-bold focus:ring-2 focus:ring-amber-500 outline-none cursor-pointer"
            >
              <option v-for="cat in store.ideaCategories" :key="cat" :value="cat">🏷️ {{ cat }}</option>
            </select>
          </div>

          <!-- Date Picker -->
          <div>
            <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-1.5">التاريخ</label>
            <input
              v-model="form.idea_date"
              type="date"
              class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-bold focus:ring-2 focus:ring-amber-500 outline-none"
            />
          </div>

          <!-- Pin to Top Checkbox -->
          <div class="flex items-center gap-2 self-end pb-2">
            <label class="flex items-center gap-2 cursor-pointer text-xs font-extrabold text-slate-800 dark:text-slate-200">
              <input
                v-model="form.is_pinned"
                type="checkbox"
                class="w-4 h-4 rounded text-amber-600 focus:ring-amber-500 cursor-pointer"
              />
              <span>📌 تثبيت هذه الفكرة في الأعلى</span>
            </label>
          </div>

        </div>

        <!-- Submit Buttons -->
        <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-200/80 dark:border-slate-800">
          <button
            type="button"
            @click="isCreateModalOpen = false; isEditModalOpen = false; resetForm()"
            class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer min-h-[44px]"
          >
            إلغاء
          </button>
          <button
            type="submit"
            class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-white font-black text-xs shadow-md transition cursor-pointer min-h-[44px]"
          >
            {{ isEditModalOpen ? 'حفظ التعديلات' : '+ إنشاء الفكرة' }}
          </button>
        </div>

      </form>
    </MobileBottomSheet>

    <!-- Category Management Modal -->
    <MobileBottomSheet
      :isOpen="isCategoryModalOpen"
      @close="isCategoryModalOpen = false"
      title="إدارة تصنيفات الأفكار"
      icon="🏷️"
      maxWidth="max-w-md"
    >
      <div class="space-y-5 text-right">
        <form @submit.prevent="handleAddCategory" class="space-y-2">
          <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300">إضافة تصنيف جديد</label>
          <div class="flex items-center gap-2">
            <input
              v-model="newCategoryName"
              type="text"
              placeholder="مثلاً: ذكاء اصطناعي، استثمار..."
              class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white text-xs font-bold focus:ring-2 focus:ring-amber-500 outline-none"
              required
            />
            <button
              type="submit"
              class="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-extrabold text-xs shadow transition cursor-pointer min-h-[40px]"
            >
              + إضافة
            </button>
          </div>
        </form>

        <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
          <div
            v-for="cat in store.ideaCategories"
            :key="cat"
            class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700 flex items-center justify-between"
          >
            <span class="text-xs font-bold text-slate-800 dark:text-slate-200">🏷️ {{ cat }}</span>
            <button
              v-if="cat !== 'عام'"
              @click="handleDeleteCategory(cat)"
              class="p-1 text-slate-400 hover:text-rose-600 transition"
              title="حذف"
            >
              🗑️
            </button>
          </div>
        </div>
      </div>
    </MobileBottomSheet>

    <!-- Image Fullscreen Lightbox -->
    <div
      v-if="lightboxImage"
      @click="lightboxImage = null"
      class="fixed inset-0 z-50 bg-black/85 backdrop-blur-sm flex items-center justify-center p-4 cursor-zoom-out"
    >
      <div class="relative max-w-4xl max-h-[90vh] flex flex-col items-center" @click.stop>
        <img
          :src="lightboxImage"
          class="max-w-full max-h-[80vh] rounded-2xl shadow-2xl object-contain"
        />
        <div class="mt-3 flex items-center gap-3">
          <button
            @click="copyImageToClipboard(lightboxImage)"
            class="px-4 py-2 rounded-xl bg-white text-slate-900 font-bold text-xs shadow hover:bg-slate-100 transition cursor-pointer flex items-center gap-1.5"
          >
            📋 نسخ الصورة
          </button>
          <button
            @click="lightboxImage = null"
            class="px-4 py-2 rounded-xl bg-slate-800 text-white font-bold text-xs shadow hover:bg-slate-700 transition cursor-pointer"
          >
            ✕ إغلاق
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
