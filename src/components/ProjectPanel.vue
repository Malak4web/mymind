<script setup>
import { store } from '../store'
import { ref, computed, nextTick } from 'vue'

const activeProject = computed(() => store.projects.find(p => p.id === store.activeProjectId))
const deletedProjects = computed(() => store.projects.filter(p => p.isDeleted))
const activeProjectsList = computed(() => store.projects.filter(p => !p.isDeleted))

// Category-filtered projects
const filteredProjectsByCategory = computed(() => {
  if (store.activeCategoryId === null) return activeProjectsList.value
  if (store.activeCategoryId === 'none') return activeProjectsList.value.filter(p => !p.categoryId)
  return activeProjectsList.value.filter(p => p.categoryId === store.activeCategoryId)
})

// Drag and Drop state for projects reordering
const draggedProjectIndex = ref(null)
const dragOverProjectIndex = ref(null)

const handleDragStart = (e, project, index) => {
  draggedProjectIndex.value = index
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', String(index))
}

const handleDragOver = (e, index) => {
  e.preventDefault()
  e.dataTransfer.dropEffect = 'move'
  dragOverProjectIndex.value = index
}

const handleDrop = (e, targetIndex) => {
  e.preventDefault()
  const sourceIndex = draggedProjectIndex.value
  if (sourceIndex !== null && sourceIndex !== targetIndex) {
    const sourceProject = filteredProjectsByCategory.value[sourceIndex]
    const targetProject = filteredProjectsByCategory.value[targetIndex]

    if (sourceProject && targetProject) {
      const realSourceIdx = store.projects.findIndex(p => p.id === sourceProject.id)
      const realTargetIdx = store.projects.findIndex(p => p.id === targetProject.id)

      if (realSourceIdx !== -1 && realTargetIdx !== -1) {
        store.reorderProjects(realSourceIdx, realTargetIdx)
      }
    }
  }
  draggedProjectIndex.value = null
  dragOverProjectIndex.value = null
}

const handleDragEnd = () => {
  draggedProjectIndex.value = null
  dragOverProjectIndex.value = null
}


// Form state
const newProjName = ref('')
const newProjDesc = ref('')
const selectedTemplateId = ref('')
const selectedCategoryId = ref('')

// Category form state
const showCategoryForm = ref(false)
const newCatName = ref('')
const newCatColor = ref('#8b5cf6')
const newCatIcon = ref('📂')
const editingCategoryId = ref(null)
const editCatName = ref('')
const editCatColor = ref('')
const editCatIcon = ref('')
const showCategoryMenu = ref(null)

const categoryColors = [
  '#8b5cf6', '#6366f1', '#3b82f6', '#06b6d4', '#14b8a6',
  '#10b981', '#22c55e', '#f59e0b', '#f97316', '#ef4444',
  '#ec4899', '#a855f7', '#64748b', '#0ea5e9', '#84cc16'
]

const categoryIcons = [
  '📂', '🚀', '💼', '🎯', '🔧', '📊', '🎨', '📱', '🌐', '💡',
  '📈', '🛒', '🏗️', '📝', '🔬', '🎮', '📡', '🏢', '⚡', '🧩'
]

// Helper for dynamic coloring of status badges
const getStatusColor = (status) => {
  const s = status.toLowerCase()
  if (s.includes('todo') || s.includes('to do') || s.includes('بدء') || s.includes('بانتظار')) {
    return 'bg-blue-50/80 text-blue-600 dark:bg-blue-950/35 dark:text-blue-400 border-blue-100/60 dark:border-blue-900/20'
  }
  if (s.includes('progress') || s.includes('عمل') || s.includes('approved') || s.includes('نشط')) {
    return 'bg-violet-50/80 text-violet-650 dark:bg-violet-955/35 dark:text-violet-400 border-violet-100/60 dark:border-violet-900/20'
  }
  if (s.includes('review') || s.includes('مراجعة') || s.includes('schedule') || s.includes('مجدول')) {
    return 'bg-amber-50/80 text-amber-605 dark:bg-amber-955/35 dark:text-amber-400 border-amber-100/60 dark:border-amber-900/20'
  }
  if (s.includes('done') || s.includes('مكتمل') || s.includes('منشور') || s.includes('publish') || s.includes('complete')) {
    return 'bg-emerald-50/80 text-emerald-600 dark:bg-emerald-950/35 dark:text-emerald-400 border-emerald-100/60 dark:border-emerald-900/20'
  }
  return 'bg-slate-50 text-slate-550 dark:bg-slate-900 dark:text-slate-400 border-slate-100 dark:border-slate-800'
}

const selectedMemberIds = ref([])
const showMemberModal = ref(false)
const selectedProjectForMembers = ref(null)
const memberSearchQuery = ref('')

const openMemberModal = (project) => {
  selectedProjectForMembers.value = project
  memberSearchQuery.value = ''
  showMemberModal.value = true
}

const filteredUsersForModal = computed(() => {
  const q = memberSearchQuery.value.trim().toLowerCase()
  if (!q) return store.users
  return store.users.filter(u => 
    u.name.toLowerCase().includes(q) || 
    (u.email && u.email.toLowerCase().includes(q)) ||
    (u.roleName && u.roleName.toLowerCase().includes(q))
  )
})

const handleCreateProject = () => {
  if (!newProjName.value.trim()) return
  
  // Use active category if no category explicitly selected
  const catId = selectedCategoryId.value || store.activeCategoryId || null

  store.createProject(
    newProjName.value.trim(),
    newProjDesc.value.trim(),
    selectedTemplateId.value || null,
    selectedMemberIds.value,
    catId
  )

  newProjName.value = ''
  newProjDesc.value = ''
  selectedTemplateId.value = ''
  selectedMemberIds.value = []
  selectedCategoryId.value = ''
}

const handleCreateCategory = async () => {
  if (!newCatName.value.trim()) return
  await store.createProjectCategory(
    newCatName.value.trim(),
    '',
    newCatColor.value,
    newCatIcon.value
  )
  newCatName.value = ''
  newCatColor.value = '#8b5cf6'
  newCatIcon.value = '📂'
  showCategoryForm.value = false
}

const startEditCategory = (cat) => {
  editingCategoryId.value = cat.id
  editCatName.value = cat.name
  editCatColor.value = cat.color || '#8b5cf6'
  editCatIcon.value = cat.icon || '📂'
  showCategoryMenu.value = null
}

const saveEditCategory = async () => {
  if (!editCatName.value.trim() || !editingCategoryId.value) return
  await store.updateProjectCategory(editingCategoryId.value, {
    name: editCatName.value.trim(),
    color: editCatColor.value,
    icon: editCatIcon.value
  })
  editingCategoryId.value = null
}

const handleDeleteCategory = async (catId) => {
  if (!confirm('هل أنت متأكد من حذف هذا التصنيف؟ المشاريع لن تُحذف بل ستصبح بدون تصنيف.')) return
  await store.deleteProjectCategory(catId)
  showCategoryMenu.value = null
}

const getCategoryProjectCount = (catId) => {
  return activeProjectsList.value.filter(p => p.categoryId === catId).length
}

const getUncategorizedCount = () => {
  return activeProjectsList.value.filter(p => !p.categoryId).length
}

const changeProjectCategory = (project, newCatId) => {
  if (!project) return
  const catId = newCatId ? parseInt(newCatId) : null
  project.categoryId = catId
  const cat = store.projectCategories.find(c => c.id === catId)
  project.categoryName = cat ? cat.name : null
  
  const storeProj = store.projects.find(p => p.id === project.id)
  if (storeProj) {
    storeProj.categoryId = catId
    storeProj.categoryName = project.categoryName
  }

  store.updateProject(project.id, project.name, project.description, project.memberIds || [], catId)
}

const toggleProjectMember = (project, userId) => {
  if (!project) return
  if (!project.memberIds) project.memberIds = []

  const currentMembers = [...project.memberIds]
  const idx = currentMembers.indexOf(userId)
  if (idx > -1) {
    currentMembers.splice(idx, 1)
  } else {
    currentMembers.push(userId)
  }

  // 1. Mutate local reactive array immediately
  project.memberIds = currentMembers

  // 2. Mutate store project item
  const storeProj = store.projects.find(p => p.id === project.id)
  if (storeProj) {
    storeProj.memberIds = currentMembers
  }

  // 3. Trigger reactivity on open modal ref
  if (selectedProjectForMembers.value && selectedProjectForMembers.value.id === project.id) {
    selectedProjectForMembers.value = { ...project, memberIds: currentMembers }
  }

  // 4. Sync with API
  store.updateProject(project.id, project.name, project.description, currentMembers)
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
  <div class="space-y-4 text-right animate-fade-in">
    
    <!-- ═══════════════════════════════════════════ -->
    <!--  CATEGORY PILLS BAR                        -->
    <!-- ═══════════════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-2xl p-4 shadow-[0_4px_12px_rgba(0,0,0,0.01)]">
      <div class="flex items-center justify-between mb-3">
        <button 
          @click="showCategoryForm = !showCategoryForm"
          class="text-[10px] font-bold text-violet-600 dark:text-violet-400 hover:bg-violet-50 dark:hover:bg-violet-955/30 px-2 py-1 rounded-lg transition cursor-pointer flex items-center gap-1"
          title="إضافة تصنيف جديد"
        >
          <span>＋</span>
          <span>تصنيف</span>
        </button>
        <h3 class="text-xs font-bold text-slate-450 dark:text-slate-400 uppercase tracking-wider">التصنيفات</h3>
      </div>

      <!-- Category Pills (horizontal scrollable) -->
      <div class="flex flex-wrap gap-2">
        <!-- "All" pill -->
        <button
          @click="store.activeCategoryId = null"
          :class="[
            'flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[11px] font-bold border transition-all duration-200 cursor-pointer shrink-0',
            store.activeCategoryId === null
              ? 'bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 border-slate-900 dark:border-slate-100 shadow-md shadow-slate-900/20'
              : 'bg-slate-50 dark:bg-slate-955 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-800 hover:border-slate-400 dark:hover:border-slate-600'
          ]"
        >
          <span>📋</span>
          <span>الكل</span>
          <span class="text-[9px] opacity-70 font-extrabold">{{ activeProjectsList.length }}</span>
        </button>

        <!-- Category pills -->
        <div 
          v-for="cat in store.projectCategories" 
          :key="cat.id" 
          class="relative group/cat"
        >
          <!-- Inline editing mode -->
          <div v-if="editingCategoryId === cat.id" class="flex items-center gap-1.5 bg-white dark:bg-slate-900 border-2 border-violet-500 rounded-xl p-1.5 shadow-lg">
            <input
              v-model="editCatName"
              @keyup.enter="saveEditCategory"
              @keyup.escape="editingCategoryId = null"
              class="w-24 bg-transparent text-[11px] font-bold text-slate-800 dark:text-slate-200 outline-none px-1"
              autofocus
            />
            <div class="flex gap-0.5">
              <button 
                v-for="c in categoryColors.slice(0, 6)" :key="c"
                @click="editCatColor = c"
                :class="['w-4 h-4 rounded-full border-2 transition cursor-pointer', editCatColor === c ? 'border-slate-900 dark:border-white scale-110' : 'border-transparent']"
                :style="{ backgroundColor: c }"
              ></button>
            </div>
            <button @click="saveEditCategory" class="text-emerald-500 hover:text-emerald-600 text-sm font-bold cursor-pointer">✓</button>
            <button @click="editingCategoryId = null" class="text-slate-400 hover:text-slate-600 text-sm font-bold cursor-pointer">✕</button>
          </div>
          
          <!-- Normal pill -->
          <button
            v-else
            @click="store.activeCategoryId = cat.id"
            :class="[
              'flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[11px] font-bold border transition-all duration-200 cursor-pointer shrink-0',
              store.activeCategoryId === cat.id
                ? 'text-white shadow-md'
                : 'bg-white dark:bg-slate-955 border-slate-200 dark:border-slate-800 hover:border-slate-400 dark:hover:border-slate-600'
            ]"
            :style="store.activeCategoryId === cat.id 
              ? { backgroundColor: cat.color || '#8b5cf6', borderColor: cat.color || '#8b5cf6', boxShadow: `0 4px 12px ${(cat.color || '#8b5cf6')}40` } 
              : { color: cat.color || '#8b5cf6' }
            "
          >
            <span>{{ cat.icon || '📂' }}</span>
            <span>{{ cat.name }}</span>
            <span class="text-[9px] opacity-70 font-extrabold">{{ cat.projects_count ?? getCategoryProjectCount(cat.id) }}</span>
          </button>

          <!-- Context menu trigger (appears on hover) -->
          <div 
            v-if="editingCategoryId !== cat.id"
            class="absolute -top-1 -left-1 opacity-0 group-hover/cat:opacity-100 transition"
          >
            <button 
              @click.stop="showCategoryMenu = showCategoryMenu === cat.id ? null : cat.id"
              class="w-4 h-4 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400 text-[8px] flex items-center justify-center cursor-pointer hover:bg-slate-300 dark:hover:bg-slate-600 transition"
            >⋯</button>
          </div>
          
          <!-- Context dropdown -->
          <div 
            v-if="showCategoryMenu === cat.id" 
            class="absolute top-full left-0 mt-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-xl py-1.5 z-30 min-w-[120px] text-right"
          >
            <button 
              @click="startEditCategory(cat)" 
              class="w-full text-right px-3 py-1.5 text-[11px] font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer flex items-center gap-2"
            >
              <span>✏️</span><span>تعديل</span>
            </button>
            <button 
              @click="handleDeleteCategory(cat.id)" 
              class="w-full text-right px-3 py-1.5 text-[11px] font-bold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-955/20 transition cursor-pointer flex items-center gap-2"
            >
              <span>🗑️</span><span>حذف</span>
            </button>
          </div>
        </div>

        <!-- Uncategorized pill (if any projects have no category) -->
        <button
          v-if="getUncategorizedCount() > 0"
          @click="store.activeCategoryId = 'none'"
          :class="[
            'flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[11px] font-bold border transition-all duration-200 cursor-pointer shrink-0',
            store.activeCategoryId === 'none'
              ? 'bg-slate-600 text-white border-slate-600 shadow-md shadow-slate-600/20'
              : 'bg-slate-50 dark:bg-slate-955 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-800 hover:border-slate-400 dark:hover:border-slate-600 border-dashed'
          ]"
        >
          <span>📌</span>
          <span>بدون تصنيف</span>
          <span class="text-[9px] opacity-70 font-extrabold">{{ getUncategorizedCount() }}</span>
        </button>
      </div>

      <!-- New Category Inline Form -->
      <Transition name="slide">
        <div v-if="showCategoryForm" class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-800/60 space-y-3">
          <div class="flex items-center gap-2">
            <input 
              v-model="newCatName"
              type="text"
              placeholder="اسم التصنيف الجديد..."
              @keyup.enter="handleCreateCategory"
              class="flex-1 bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-violet-500 transition"
              autofocus
            />
          </div>
          
          <!-- Color picker -->
          <div class="space-y-1.5">
            <span class="text-[10px] font-bold text-slate-400">اللون:</span>
            <div class="flex flex-wrap gap-1.5">
              <button 
                v-for="c in categoryColors" :key="c"
                @click="newCatColor = c"
                :class="['w-5 h-5 rounded-full border-2 transition cursor-pointer hover:scale-110', newCatColor === c ? 'border-slate-900 dark:border-white scale-110 ring-2 ring-offset-1' : 'border-transparent']"
                :style="{ backgroundColor: c, '--tw-ring-color': c }"
              ></button>
            </div>
          </div>

          <!-- Icon picker -->
          <div class="space-y-1.5">
            <span class="text-[10px] font-bold text-slate-400">الأيقونة:</span>
            <div class="flex flex-wrap gap-1">
              <button 
                v-for="ic in categoryIcons" :key="ic"
                @click="newCatIcon = ic"
                :class="['w-7 h-7 rounded-lg flex items-center justify-center text-sm transition cursor-pointer', newCatIcon === ic ? 'bg-violet-100 dark:bg-violet-900/40 ring-2 ring-violet-500 scale-110' : 'hover:bg-slate-100 dark:hover:bg-slate-800']"
              >{{ ic }}</button>
            </div>
          </div>

          <div class="flex gap-2">
            <button 
              @click="handleCreateCategory"
              :disabled="!newCatName.trim()"
              class="flex-1 bg-violet-600 hover:bg-violet-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold py-2 rounded-xl text-xs transition cursor-pointer"
            >
              إنشاء التصنيف
            </button>
            <button 
              @click="showCategoryForm = false"
              class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-bold rounded-xl text-xs transition cursor-pointer hover:bg-slate-200 dark:hover:bg-slate-700"
            >
              إلغاء
            </button>
          </div>
        </div>
      </Transition>
    </div>

    <!-- ═══════════════════════════════════════════ -->
    <!--  PROJECTS LIST (filtered by category)      -->
    <!-- ═══════════════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-2xl p-5 shadow-[0_4px_12px_rgba(0,0,0,0.01)]">
      <div class="flex items-center justify-between mb-4">
        <span class="text-[10px] font-bold text-slate-400">
          {{ filteredProjectsByCategory.length }} مشروع
        </span>
        <h3 class="text-xs font-bold text-slate-450 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
          <span>مشاريع</span>
          <span v-if="store.activeCategoryId && store.activeCategoryId !== 'none'" class="text-violet-600 dark:text-violet-400 normal-case">
            {{ store.projectCategories.find(c => c.id === store.activeCategoryId)?.name }}
          </span>
          <span v-else-if="store.activeCategoryId === 'none'" class="text-slate-500 normal-case">بدون تصنيف</span>
          <span v-else class="text-slate-500 normal-case">الكل</span>
        </h3>
      </div>

      <div class="space-y-3">
        <div v-if="filteredProjectsByCategory.length === 0" class="text-xs text-slate-400 italic py-6 text-center flex flex-col items-center gap-2">
          <span class="text-2xl opacity-40">📭</span>
          <span>لا توجد مشاريع في هذا التصنيف حالياً.</span>
        </div>
        <div 
          v-for="(p, idx) in filteredProjectsByCategory" 
          :key="p.id"
          draggable="true"
          @dragstart="handleDragStart($event, p, idx)"
          @dragover="handleDragOver($event, idx)"
          @drop="handleDrop($event, idx)"
          @dragend="handleDragEnd"
          @click="store.activeProjectId = p.id"
          :class="[
            'p-4 rounded-xl border text-right cursor-pointer transition-all duration-200 relative group flex items-start justify-between gap-3 overflow-hidden',
            store.activeProjectId === p.id 
              ? 'bg-slate-50/60 dark:bg-slate-955/20 border-slate-350 dark:border-slate-700 shadow-sm' 
              : 'border-slate-200/60 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-slate-300 dark:hover:border-slate-700',
            draggedProjectIndex === idx ? 'opacity-30 border-dashed border-violet-500 scale-95' : '',
            dragOverProjectIndex === idx && draggedProjectIndex !== idx ? 'ring-2 ring-violet-500 border-violet-500 bg-violet-50/40 dark:bg-violet-955/30' : ''
          ]"
        >
          <!-- Selected accent right bar -->
          <div 
            v-if="store.activeProjectId === p.id"
            class="absolute top-0 right-0 w-1 h-full shadow-[0_0_8px_rgba(139,92,246,0.5)]"
            :style="{ background: `linear-gradient(to bottom, ${store.projectCategories.find(c => c.id === p.categoryId)?.color || '#8b5cf6'}, #4f46e5)` }"
          ></div>

          <!-- Drag Handle Grip Icon -->
          <div 
            class="text-slate-300 dark:text-slate-600 hover:text-violet-500 dark:hover:text-violet-400 cursor-grab active:cursor-grabbing p-0.5 -mr-1 transition shrink-0 self-center"
            title="اسحب لترتيب المشروع"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 8h16M4 16h16" />
            </svg>
          </div>

          
          <div class="space-y-1.5 pr-1.5 flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <h4 class="text-sm font-bold text-slate-855 dark:text-slate-100 truncate">{{ p.name }}</h4>
              <!-- Interactive Category Selector Badge on Project Card -->
              <select
                :value="p.categoryId || ''"
                @click.stop
                @change="changeProjectCategory(p, $event.target.value)"
                class="text-[9px] font-extrabold px-1.5 py-0.5 rounded-md border shrink-0 bg-transparent focus:outline-none cursor-pointer transition hover:opacity-80"
                :style="{ 
                  backgroundColor: (store.projectCategories.find(c => c.id === p.categoryId)?.color || '#64748b') + '20',
                  color: store.projectCategories.find(c => c.id === p.categoryId)?.color || '#64748b',
                  borderColor: (store.projectCategories.find(c => c.id === p.categoryId)?.color || '#64748b') + '40'
                }"
                title="انقر لتغيير تصنيف هذا المشروع"
              >
                <option value="" class="bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300">📌 بدون تصنيف</option>
                <option 
                  v-for="cat in store.projectCategories" 
                  :key="cat.id" 
                  :value="cat.id"
                  class="bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300"
                >
                  {{ cat.icon }} {{ cat.name }}
                </option>
              </select>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed line-clamp-2">{{ p.description }}</p>
            <div class="flex flex-wrap items-center gap-1.5 pt-2">
              <span 
                v-for="s in p.statuses" 
                :key="s" 
                :class="['text-[9px] font-bold px-1.5 py-0.5 rounded border', getStatusColor(s)]"
              >
                {{ s }}
              </span>
            </div>

            <!-- Project Assigned Members List & Manage Button -->
            <div class="pt-2 border-t border-slate-100 dark:border-slate-800/60 flex items-center justify-between gap-2 flex-wrap" @click.stop>
              <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-[9.5px] font-bold text-slate-400">الأعضاء:</span>
                <span 
                  v-for="u in store.users.filter(u => (p.memberIds || []).includes(u.id))" 
                  :key="u.id" 
                  @click.stop="toggleProjectMember(p, u.id)"
                  class="text-[9.5px] font-bold px-2 py-0.5 rounded-md bg-violet-500/10 text-violet-600 dark:text-violet-400 border border-violet-500/30 flex items-center gap-1 group/u cursor-pointer hover:bg-rose-500/10 hover:text-rose-600 hover:border-rose-500/30 transition"
                  :title="'انقر لإزالة ' + u.name + ' من المشروع'"
                >
                  <span>👤 {{ u.name }}</span>
                  <span class="text-[9px] opacity-60 group-hover/u:opacity-100">✕</span>
                </span>
                <span v-if="(p.memberIds || []).length === 0" class="text-[9.5px] text-slate-400 italic">لم يحدد أعضاء</span>
              </div>

              <!-- Button to open Member Search Popup Modal -->
              <button 
                @click="openMemberModal(p)"
                class="bg-slate-100 hover:bg-violet-50 dark:bg-slate-800 dark:hover:bg-violet-955/30 text-slate-700 dark:text-slate-300 hover:text-violet-600 dark:hover:text-violet-400 font-bold px-2.5 py-1.5 min-h-[38px] rounded-xl text-[10px] border border-slate-200 dark:border-slate-700 transition cursor-pointer flex items-center gap-1 shrink-0 active-scale"
              >
                <span>👥 إدارة الأعضاء</span>
                <span class="bg-violet-600 text-white font-extrabold text-[9px] px-1.5 py-0.2 rounded-md">
                  {{ (p.memberIds || []).length }}
                </span>
              </button>
            </div>
          </div>
          
          <button 
            @click.stop="store.deleteProject(p.id)"
            class="opacity-100 sm:opacity-0 group-hover:opacity-100 p-2 min-h-[40px] min-w-[40px] flex items-center justify-center text-slate-455 hover:text-rose-550 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition duration-200 cursor-pointer self-start active-scale"
            title="نقل المشروع لسلة المهملات"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════ -->
    <!--  CREATE NEW PROJECT FORM                   -->
    <!-- ═══════════════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-2xl p-5 shadow-[0_4px_12px_rgba(0,0,0,0.01)] space-y-4">
      <h3 class="text-xs font-bold text-slate-455 dark:text-slate-400 uppercase tracking-wider block">إنشاء مشروع جديد</h3>
      
      <div class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">اسم المشروع *</label>
          <input 
            v-model="newProjName" 
            type="text" 
            placeholder="مثال: تطبيق الويب..."
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-850 rounded-xl px-3 py-2 text-sm text-slate-850 dark:text-slate-200 focus:outline-none"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">وصف مختصر</label>
          <input 
            v-model="newProjDesc" 
            type="text" 
            placeholder="وصف المشروع..."
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-855 rounded-xl px-3 py-2 text-sm text-slate-850 dark:text-slate-200 focus:outline-none"
          />
        </div>

        <!-- Category Selection for New Project -->
        <div>
          <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">التصنيف</label>
          <select 
            v-model="selectedCategoryId" 
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-850 rounded-xl px-3 py-2.5 text-sm text-slate-855 dark:text-slate-200 focus:outline-none cursor-pointer"
          >
            <option value="">
              {{ store.activeCategoryId && store.activeCategoryId !== 'none' 
                ? store.projectCategories.find(c => c.id === store.activeCategoryId)?.name + ' (التصنيف الحالي)' 
                : 'بدون تصنيف' 
              }}
            </option>
            <option 
              v-for="cat in store.projectCategories" 
              :key="cat.id" 
              :value="cat.id"
            >
              {{ cat.icon }} {{ cat.name }}
            </option>
          </select>
        </div>

        <!-- Project Template Selection Dropdown -->
        <div>
          <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">اختر قالب المشروع</label>
          <select 
            v-model="selectedTemplateId" 
            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-850 rounded-xl px-3 py-2.5 text-sm text-slate-855 dark:text-slate-200 focus:outline-none cursor-pointer"
          >
            <option value="">مشروع فارغ (افتراضي بدون حقول)</option>
            <option 
              v-for="tpl in store.projectTemplates" 
              :key="tpl.id" 
              :value="tpl.id"
            >
              {{ tpl.name }} {{ tpl.is_default ? '(تلقائي)' : '' }}
            </option>
          </select>
        </div>

        <!-- Select Members for new project -->
        <div>
          <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">تحديد أعضاء المشروع</label>
          <div class="flex flex-wrap gap-2 pt-1">
            <label 
              v-for="u in store.users" 
              :key="u.id" 
              class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-955 px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 text-xs text-slate-700 dark:text-slate-300 cursor-pointer hover:border-violet-400"
            >
              <input type="checkbox" :value="u.id" v-model="selectedMemberIds" class="rounded text-violet-650 focus:ring-violet-500 h-4 w-4" />
              <span>👤 {{ u.name }} ({{ u.roleName }})</span>
            </label>
          </div>
        </div>

        <button 
          @click="handleCreateProject"
          :disabled="!newProjName.trim()"
          class="w-full bg-slate-900 hover:bg-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-bold py-2.5 rounded-xl text-xs transition shadow-sm cursor-pointer"
        >
          إنشاء المشروع الجديد
        </button>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════ -->
    <!--  BIN / DELETED PROJECTS                    -->
    <!-- ═══════════════════════════════════════════ -->
    <div v-if="deletedProjects.length > 0" class="bg-slate-50/50 dark:bg-slate-955/20 border border-slate-200/60 dark:border-slate-855 rounded-2xl p-5 space-y-4">
      <h3 class="text-xs font-bold text-slate-455 dark:text-slate-400 uppercase tracking-wider block">سلة المحذوفات (المشاريع المهملة)</h3>
      <div class="space-y-3">
        <div 
          v-for="p in deletedProjects" 
          :key="p.id" 
          class="flex items-center justify-between p-3.5 bg-white dark:bg-slate-900 border border-slate-200/40 dark:border-slate-805 rounded-xl text-right flex-row-reverse"
        >
          <div class="min-w-0 flex-1 pr-2 text-right">
            <h4 class="text-xs font-bold text-slate-700 dark:text-slate-350 truncate">{{ p.name }}</h4>
          </div>
          <button 
            @click="store.restoreProject(p.id)" 
            class="text-[10px] font-bold text-violet-650 hover:text-violet-750 bg-violet-50 dark:bg-violet-955/35 dark:text-violet-400 py-1.5 px-2.5 rounded-lg cursor-pointer transition"
          >
            استعادة
          </button>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════ -->
    <!--  MEMBER MANAGEMENT MODAL                   -->
    <!-- ═══════════════════════════════════════════ -->
    <Transition name="sheet">
      <div v-if="showMemberModal" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4" dir="rtl">
        <!-- Backdrop -->
        <div @click="showMemberModal = false" class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>

        <!-- Modal Content -->
        <div 
          @touchstart="handleTouchStart"
          @touchmove="handleTouchMove"
          @touchend="handleTouchEnd(() => showMemberModal = false)"
          class="relative z-10 bg-white dark:bg-slate-900 border-t sm:border border-slate-200 dark:border-slate-800 rounded-t-3xl sm:rounded-3xl p-5 sm:p-6 max-w-md max-h-[85vh] overflow-y-auto w-full shadow-2xl space-y-5 text-right transform transition-all duration-300"
        >
          <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto my-2.5 shrink-0 sm:hidden cursor-grab"></div>

          <!-- Close button -->
          <button 
            @click="showMemberModal = false" 
            class="absolute top-4 left-4 p-1.5 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer font-extrabold text-sm"
          >
            ✕
          </button>

          <!-- Header info -->
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class="p-2 bg-violet-500/10 text-violet-600 dark:text-violet-400 rounded-xl text-lg">👥</span>
              <div>
                <h3 class="text-base font-extrabold text-slate-855 dark:text-slate-100">
                  إدارة أعضاء المشروع
                </h3>
                <span class="text-xs font-bold text-violet-600 dark:text-violet-400">{{ selectedProjectForMembers?.name }}</span>
              </div>
            </div>
            <p class="text-xs text-slate-400 font-semibold pt-1">
              ابحث عن الأعضاء المسموح لهم بالوصول لهذا المشروع والذين يظهرون عند المنشَن (@).
            </p>
          </div>

          <!-- Search Input Box -->
          <div class="relative">
            <input 
              type="text"
              v-model="memberSearchQuery"
              placeholder="ابحث باسم العضو أو البريد الإلكتروني..."
              class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-805 rounded-xl pr-9 pl-4 py-2.5 text-xs sm:text-sm text-slate-850 dark:text-slate-200 focus:outline-none focus:border-violet-500 transition font-sans"
            />
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">🔍</span>
          </div>

          <!-- Filtered Users List -->
          <div class="max-h-64 overflow-y-auto space-y-2 pr-0.5">
            <div v-if="filteredUsersForModal.length === 0" class="text-center py-8 text-xs text-slate-400 font-semibold">
              لا يوجد أعضاء يطابقون كلمة البحث "{{ memberSearchQuery }}".
            </div>

            <div 
              v-for="u in filteredUsersForModal" 
              :key="u.id"
              class="p-3 bg-slate-50/60 dark:bg-slate-955/40 border border-slate-200/60 dark:border-slate-855 rounded-2xl flex items-center justify-between gap-3 transition hover:border-slate-350 dark:hover:border-slate-700"
            >
              <!-- Right Avatar & User details -->
              <div class="flex items-center gap-3 min-w-0 flex-1">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-violet-500/20 to-indigo-500/20 text-violet-600 dark:text-violet-400 font-extrabold text-xs flex items-center justify-center border border-violet-500/20 shrink-0">
                  {{ u.name.slice(0, 2) }}
                </div>
                <div class="min-w-0 text-right">
                  <div class="flex items-center gap-1.5">
                    <span class="text-xs font-bold text-slate-855 dark:text-slate-100 truncate">{{ u.name }}</span>
                    <span class="text-[9px] font-extrabold px-1.5 py-0.2 bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded">{{ u.roleName }}</span>
                  </div>
                  <span class="text-[10px] text-slate-400 font-mono block truncate text-right">{{ u.email }}</span>
                </div>
              </div>

              <!-- Action button: Toggle Member -->
              <button 
                @click="toggleProjectMember(selectedProjectForMembers, u.id)"
                :class="[
                  'px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer shrink-0 flex items-center gap-1',
                  (selectedProjectForMembers?.memberIds || []).includes(u.id)
                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 hover:bg-rose-500/10 hover:text-rose-600 hover:border-rose-500/30'
                    : 'bg-violet-600 hover:bg-violet-700 text-white shadow-sm shadow-violet-500/20'
                ]"
              >
                <span v-if="(selectedProjectForMembers?.memberIds || []).includes(u.id)">✓ مضاف</span>
                <span v-else>+ إضافة للمشروع</span>
              </button>
            </div>
          </div>

          <!-- Footer summary & action -->
          <div class="pt-3 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs">
            <span class="text-slate-400 font-semibold">
              إجمالي المضافين: <b class="text-violet-600 dark:text-violet-400">{{ (selectedProjectForMembers?.memberIds || []).length }}</b> أعضاء
            </span>
            <button 
              @click="showMemberModal = false"
              class="bg-slate-900 dark:bg-slate-800 hover:bg-slate-800 dark:hover:bg-slate-700 text-white font-bold px-4 py-2 rounded-xl transition cursor-pointer text-xs"
            >
              إغلاق
            </button>
          </div>

        </div>
      </div>
    </Transition>

  </div>
</template>
