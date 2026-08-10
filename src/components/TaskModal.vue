<script setup>
import { store } from '../store'
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import MentionInput from './MentionInput.vue'
import MentionText from './MentionText.vue'

const activeProject = computed(() => store.projects.find(p => p.id === store.activeProjectId))

// Form fields state
const taskTitle = ref('')
const taskDesc = ref('')
const taskStatus = ref('')
const startDate = ref('')
const deadline = ref('')
const customFieldValues = ref({})
const templateFieldValues = ref({})

// Selected Task Template
const selectedTaskTemplateId = ref('')

// Errors
const dateError = ref('')
const validationError = ref('')

// File Upload simulation state
const fileToUploadName = ref('')
const fileToUploadSize = ref('1.4 MB')
const simulateFailure = ref(false)

const taskToEdit = computed(() => {
  if (store.selectedTaskIdForModal) {
    return store.tasks.find(t => t.id === store.selectedTaskIdForModal)
  }
  return null
})

// Compute active custom fields from active project
const activeFields = computed(() => {
  return activeProject.value?.customFields?.filter(f => f.active) || []
})

// Populate fields when editing
watch(taskToEdit, (task) => {
  selectedTaskTemplateId.value = ''
  templateFieldValues.value = {}
  if (task) {
    taskTitle.value = task.title
    taskDesc.value = task.description || ''
    taskStatus.value = task.status
    startDate.value = task.startDate || ''
    deadline.value = task.deadline || ''
    customFieldValues.value = { ...task.customFieldValues }
  } else {
    // Reset to defaults
    taskTitle.value = store.prefilledTaskTitle || ''
    taskDesc.value = ''
    taskStatus.value = store.prefilledTaskStatus || (activeProject.value?.statuses[0] || 'بانتظار البدء')
    startDate.value = ''
    deadline.value = ''
    customFieldValues.value = {}

    // Check if there is a default task template to auto-populate (only if not prefilled)
    if (!store.prefilledTaskTitle) {
      const defaultTemplate = store.taskTemplates.find(t => t.is_default)
      if (defaultTemplate) {
        selectedTaskTemplateId.value = String(defaultTemplate.id)
        applyTaskTemplate(defaultTemplate.id)
      }
    }

    // Clear prefilled values after reading
    store.prefilledTaskTitle = ''
    store.prefilledTaskStatus = ''
  }
  dateError.value = ''
  validationError.value = ''
}, { immediate: true })

// Real-time Date validation check
watch([startDate, deadline], ([start, end]) => {
  if (start && end && new Date(end) < new Date(start)) {
    dateError.value = 'خطأ في التحقق: تاريخ التسليم النهائي لا يمكن أن يكون قبل تاريخ البدء.'
  } else {
    dateError.value = ''
  }
})

const applyTaskTemplate = (tplId) => {
  if (!tplId) return
  const tpl = store.taskTemplates.find(t => t.id === parseInt(tplId))
  if (!tpl) return

  templateFieldValues.value = {}

  if (tpl.custom_fields_values && Array.isArray(tpl.custom_fields_values)) {
    tpl.custom_fields_values.forEach(f => {
      // Initialize model value
      if (f.type === 'multi_select') {
        templateFieldValues.value[f.name] = f.value ? f.value.split(',').map(o => o.trim()).filter(Boolean) : []
      } else if (f.type === 'checkbox') {
        templateFieldValues.value[f.name] = f.value === 'true'
      } else {
        templateFieldValues.value[f.name] = f.value || ''
      }

      // Legacy fallback mapping
      if (f.type === 'title') {
        taskTitle.value = f.value
      } else if (f.type === 'description' || f.type === 'textarea' || f.type === 'long_text') {
        taskDesc.value = f.value
      } else if (f.type === 'status') {
        taskStatus.value = f.value
      }
    })
  }
}

watch(selectedTaskTemplateId, (newId) => {
  if (newId) {
    applyTaskTemplate(newId)
  }
})

const handleSaveTask = () => {
  if (!taskTitle.value.trim()) {
    validationError.value = 'اسم المهمة مطلوب بشكل إجباري.'
    return
  }
  if (dateError.value) return

  let finalDesc = taskDesc.value.trim()
  const entries = Object.entries(templateFieldValues.value)
  if (entries.length > 0) {
    let customFieldsSection = ''
    entries.forEach(([name, val]) => {
      if (name && val !== undefined && val !== '' && val !== null) {
        // Render array representation for multi-select
        const displayVal = Array.isArray(val) ? val.join(', ') : val
        if (displayVal !== 'false' && displayVal !== '') {
          customFieldsSection += `- **${name}**: ${displayVal}\n`
        }
      }
    })
    if (customFieldsSection) {
      finalDesc += (finalDesc ? '\n\n' : '') + `### تفاصيل إضافية من قالب المهمة:\n` + customFieldsSection
    }
  }

  try {
    if (taskToEdit.value) {
      store.updateTask(taskToEdit.value.id, {
        title: taskTitle.value.trim(),
        description: finalDesc,
        status: taskStatus.value,
        startDate: startDate.value,
        deadline: deadline.value,
        customFieldValues: customFieldValues.value
      })
    } else {
      store.createTask(
        taskTitle.value.trim(),
        finalDesc,
        taskStatus.value,
        startDate.value,
        deadline.value,
        customFieldValues.value
      )
    }
    closeModal()
  } catch (e) {
    validationError.value = e.message
  }
}

const handleDeleteTask = async () => {
  if (!taskToEdit.value) return
  if (!confirm(`هل أنت متأكد من حذف المهمة "${taskToEdit.value.title}" نهائياً؟`)) return
  try {
    await store.deleteTask(taskToEdit.value.id)
    closeModal()
  } catch (e) {
    validationError.value = e.message || 'فشل حذف المهمة.'
  }
}

const closeModal = () => {
  store.isTaskModalOpen = false
  store.selectedTaskIdForModal = null
  selectedTaskTemplateId.value = ''
}

const triggerFileUpload = () => {
  if (!fileToUploadName.value.trim()) return
  if (!taskToEdit.value) {
    alert("يرجى حفظ المهمة أولاً لتمكين إرفاق الملفات.")
    return
  }
  
  store.uploadFileToTask(
    taskToEdit.value.id,
    fileToUploadName.value.trim(),
    fileToUploadSize.value,
    simulateFailure.value
  )
  
  fileToUploadName.value = ''
  simulateFailure.value = false
}
const taskAttachments = ref([])

const fileInputRef = ref(null)

const handleFileSelect = (e) => {
  const files = e.target.files
  if (!files || files.length === 0) return
  processSelectedFiles(files)
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
    processSelectedFiles(imageFiles)
  }
}

const handleGlobalPaste = (e) => {
  if (!store.isTaskModalOpen) return
  handleImagePaste(e)
}

const processSelectedFiles = async (files) => {
  for (const file of files) {
    const timestamp = new Date().getTime()
    const fileName = file.name || `pasted_image_${timestamp}.png`
    const sizeFormatted = (file.size / 1024).toFixed(1) + ' KB'

    if (taskToEdit.value && taskToEdit.value.id) {
      await store.uploadFileToTask(taskToEdit.value.id, fileName, sizeFormatted, file)
    } else {
      taskAttachments.value.push({
        name: fileName,
        size: sizeFormatted,
        progress: 100,
        status: 'done',
        fileObj: file
      })
      store.addNotification('مرفق جديد', `تم إرفاق الملف "${fileName}" بنجاح.`)
    }
  }
}

// Compute legacy custom fields
const legacyFields = computed(() => {
  if (!taskToEdit.value) return []
  return activeProject.value?.customFields?.filter(f => {
    return !f.active && taskToEdit.value.customFieldValues[f.id] !== undefined
  }) || []
})

const activeTaskTemplate = computed(() => {
  if (selectedTaskTemplateId.value) {
    return store.taskTemplates.find(t => t.id === parseInt(selectedTaskTemplateId.value))
  }
  return null
})

// Touch swipe gesture handlers for bottom sheet dismiss
const touchStartY = ref(0)
const touchCurrentY = ref(0)

const handleTouchStart = (e) => {
  touchStartY.value = e.touches[0].clientY
  touchCurrentY.value = e.touches[0].clientY
}

const handleTouchMove = (e) => {
  touchCurrentY.value = e.touches[0].clientY
}

const handleTouchEnd = () => {
  const deltaY = touchCurrentY.value - touchStartY.value
  if (touchStartY.value > 0 && deltaY > 50) {
    closeModal()
  }
  touchStartY.value = 0
  touchCurrentY.value = 0
}

onMounted(() => {
  window.addEventListener('paste', handleGlobalPaste)
})

onUnmounted(() => {
  window.removeEventListener('paste', handleGlobalPaste)
})
</script>

<template>
  <Transition name="sheet">
    <div v-if="store.isTaskModalOpen" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4" dir="rtl">
      <!-- Backdrop -->
      <div @click="closeModal" class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"></div>

      <!-- Modal Content (Bottom Sheet on Mobile, Centered Modal on Desktop) -->
      <div 
        @touchstart="handleTouchStart"
        @touchmove="handleTouchMove"
        @touchend="handleTouchEnd"
        @paste="handleImagePaste"
        class="relative z-10 bg-white/90 dark:bg-slate-900/90 backdrop-blur-2xl border border-white/30 dark:border-slate-700/60 shadow-2xl rounded-3xl w-full max-w-2xl max-h-[88vh] sm:max-h-[90vh] overflow-y-auto p-5 sm:p-8 space-y-5 text-right transform transition-all duration-300"
      >
        <!-- Mobile Drag Handle Bar -->
        <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto my-2.5 shrink-0 sm:hidden cursor-grab"></div>

      
      <!-- Modal Header -->
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 flex-row-reverse">
        <div class="text-right">
          <h3 class="text-xl font-extrabold text-slate-850 dark:text-slate-100">
            {{ taskToEdit ? 'تعديل تفاصيل المهمة' : 'إنشاء مهمة جديدة' }}
          </h3>
          <p class="text-xs sm:text-sm text-slate-500 mt-1">المشروع الفعال: {{ activeProject?.name }}</p>
        </div>
        <button 
          @click="closeModal" 
          class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 p-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-850 transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5.5 w-5.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Task Template Selection Dropdown (Only when creating) -->
      <div v-if="!taskToEdit" class="bg-violet-500/5 border border-violet-500/10 rounded-2xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-right">
        <div class="space-y-1">
          <span class="text-xs font-bold text-violet-650 dark:text-violet-400 block">هل ترغب بتعبئة البيانات من قالب مهام؟</span>
          <span class="text-[10px] text-slate-400 font-semibold block">يتم استيراد العنوان، الوصف، وقيم الحقول المخصصة فوراً.</span>
        </div>
        <select 
          v-model="selectedTaskTemplateId"
          class="bg-white dark:bg-slate-955 border border-slate-250 dark:border-slate-850 rounded-xl px-3 py-2 text-xs focus:outline-none cursor-pointer"
        >
          <option value="">-- بدون قالب (فارغ) --</option>
          <option 
            v-for="tpl in store.taskTemplates" 
            :key="tpl.id" 
            :value="String(tpl.id)"
          >
            {{ tpl.name }} {{ tpl.is_default ? '(تلقائي)' : '' }}
          </option>
        </select>
      </div>

      <!-- Main Form Grid (If a task template is selected and we are creating) -->
      <div v-if="activeTaskTemplate && !taskToEdit" class="space-y-4 w-full">
        <!-- Fixed Task Title Field at the top -->
        <div class="w-full space-y-1.5">
          <label class="block text-xs font-semibold text-slate-655 dark:text-slate-400 mb-1">عنوان المهمة *</label>
          <input 
            v-model="taskTitle" 
            type="text" 
            placeholder="اكتب عنوان المهمة..."
            class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-805 rounded-xl px-3.5 py-2.5 text-sm text-slate-855 dark:text-slate-200 focus:outline-none focus:border-violet-500"
          />
        </div>

        <!-- Dynamic Fields list (filtering out title field) -->
        <div class="flex flex-wrap gap-4 text-right">
          <div 
            v-for="(f, idx) in activeTaskTemplate.custom_fields_values" 
            :key="idx"
            :class="[
              f.type === 'divider' ? 'w-full' :
              f.width === 'quarter' ? 'w-[calc(25%-12px)]' :
              f.width === 'thirty' ? 'w-[calc(30%-12px)]' :
              f.width === 'half' ? 'w-[calc(50%-12px)]' : 'w-full'
            ]"
            class="space-y-1.5"
          >
            <!-- Divider type renders section header -->
            <div v-if="f.type === 'divider'" class="w-full border-t border-slate-200 dark:border-slate-800 my-4 pt-2 flex items-center justify-between">
              <span class="text-xs font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">{{ f.name }}</span>
            </div>

            <div v-else class="w-full space-y-1.5">
              <label class="block text-xs font-semibold text-slate-550 dark:text-slate-400 mb-1">{{ f.name }}</label>
              
              <!-- Long Text -->
              <textarea 
                v-if="f.type === 'long_text'" 
                v-model="templateFieldValues[f.name]" 
                rows="2.5" 
                placeholder="اكتب التفاصيل..."
                class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-805 rounded-xl px-3.5 py-2 text-sm text-slate-850 dark:text-slate-200 focus:outline-none resize-none focus:border-violet-500"
              ></textarea>

              <!-- Dropdown -->
              <select 
                v-else-if="f.type === 'dropdown'" 
                v-model="templateFieldValues[f.name]" 
                class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-805 rounded-xl px-3.5 py-2.5 text-sm text-slate-855 dark:text-slate-200 focus:outline-none cursor-pointer focus:border-violet-500"
              >
                <option value="">اختر...</option>
                <option v-for="opt in (f.value || '').split(',').map(o => o.trim()).filter(Boolean)" :key="opt" :value="opt">{{ opt }}</option>
              </select>

              <!-- Multi-Select -->
              <div v-else-if="f.type === 'multi_select'" class="flex flex-wrap gap-2 pt-1">
                <label v-for="opt in (f.value || '').split(',').map(o => o.trim()).filter(Boolean)" :key="opt" class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-955 px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-800 text-xs text-slate-700 dark:text-slate-350 cursor-pointer">
                  <input type="checkbox" :value="opt" v-model="templateFieldValues[f.name]" class="rounded text-violet-650 focus:ring-violet-500 h-4 w-4" />
                  <span>{{ opt }}</span>
                </label>
              </div>

              <!-- Checkbox -->
              <div v-else-if="f.type === 'checkbox'" class="flex items-center gap-2.5 pt-2">
                <input type="checkbox" v-model="templateFieldValues[f.name]" class="rounded text-violet-650 focus:ring-violet-500 h-5 w-5 cursor-pointer" />
                <span class="text-xs text-slate-655 dark:text-slate-400">تفعيل هذا الخيار</span>
              </div>

              <!-- Radio -->
              <div v-else-if="f.type === 'radio'" class="flex flex-wrap gap-4 pt-1.5">
                <label v-for="opt in (f.value || '').split(',').map(o => o.trim()).filter(Boolean)" :key="opt" class="flex items-center gap-1.5 text-xs text-slate-700 dark:text-slate-350 cursor-pointer">
                  <input type="radio" :value="opt" v-model="templateFieldValues[f.name]" :name="'task-radio-' + idx" class="text-violet-650 focus:ring-violet-500 h-4 w-4" />
                  <span>{{ opt }}</span>
                </label>
              </div>

              <!-- User Picker -->
              <select v-else-if="f.type === 'user_picker'" v-model="templateFieldValues[f.name]" class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-805 rounded-xl px-3.5 py-2.5 text-sm text-slate-855 dark:text-slate-200 focus:outline-none cursor-pointer focus:border-violet-500">
                <option value="">اختر عضواً...</option>
                <option v-for="u in store.users" :key="u.id" :value="u.name">{{ u.name }}</option>
              </select>

              <!-- File Upload -->
              <div v-else-if="f.type === 'file_upload'" class="w-full space-y-2">
                <div class="border border-dashed border-slate-300 dark:border-slate-800 rounded-xl p-4 text-center text-xs text-slate-455 bg-slate-50/[0.02] hover:bg-slate-50/[0.05] transition cursor-pointer relative">
                  <span>📥 انقر هنا لاختيار الملفات أو اسحبها</span>
                  <input type="file" @change="(e) => { templateFieldValues[f.name] = e.target.files[0]?.name || '' }" class="absolute inset-0 opacity-0 cursor-pointer" />
                </div>
                <div v-if="templateFieldValues[f.name]" class="text-xs text-emerald-500 font-bold">
                  تم تحديد: {{ templateFieldValues[f.name] }}
                </div>
              </div>

              <!-- Date Picker -->
              <input v-else-if="f.type === 'date'" type="date" v-model="templateFieldValues[f.name]" class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-805 rounded-xl px-3.5 py-2.5 text-sm text-slate-855 dark:text-slate-200 focus:outline-none focus:border-violet-500 cursor-pointer" />

              <!-- Date & Time Picker -->
              <input v-else-if="f.type === 'date_time'" type="datetime-local" v-model="templateFieldValues[f.name]" class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-805 rounded-xl px-3.5 py-2.5 text-sm text-slate-855 dark:text-slate-200 focus:outline-none focus:border-violet-500 cursor-pointer" />

              <!-- Short Text / Number / URL -->
              <input 
                v-else 
                v-model="templateFieldValues[f.name]"
                :type="f.type === 'number' ? 'number' : f.type === 'url' ? 'url' : 'text'" 
                :placeholder="f.type === 'url' ? 'https://example.com' : 'اكتب...'"
                class="w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-805 rounded-xl px-3.5 py-2.5 text-sm text-slate-855 dark:text-slate-200 focus:outline-none focus:border-violet-500" 
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Standard Form Grid (If no template is active or editing) -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- Right Side: Basic Fields -->
        <div class="space-y-4 text-right">
          <div>
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">عنوان المهمة *</label>
            <MentionInput 
              v-model="taskTitle" 
              placeholder="اكتب عنوان المهمة... (استخدم / للملفات، و @ للمنشن)"
            />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">الوصف والتفاصيل</label>
            <MentionInput 
              v-model="taskDesc" 
              :is-textarea="true"
              :rows="3" 
              placeholder="وصف المهمة... (استخدم / للملفات والمجلدات، و @ للمنشن)"
            />
            <!-- Formatted Mention Preview & Interactive Links -->
            <div v-if="taskDesc" class="mt-2 p-3 bg-slate-50/80 dark:bg-slate-955/60 border border-slate-200/60 dark:border-slate-805 rounded-xl text-xs leading-relaxed text-slate-700 dark:text-slate-300 text-right">
              <div class="text-[10px] font-extrabold text-violet-600 dark:text-violet-400 mb-1 flex items-center gap-1">
                <span>🔗 المعاينة التفاعلية والروابط:</span>
              </div>
              <MentionText :content="taskDesc" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">تاريخ البدء</label>
              <input 
                v-model="startDate" 
                type="date" 
                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-855 dark:text-slate-200 focus:outline-none"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">تاريخ الاستحقاق</label>
              <input 
                v-model="deadline" 
                type="date" 
                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-855 dark:text-slate-200 focus:outline-none"
              />
            </div>
          </div>

          <!-- Date Validation Warning -->
          <div v-if="dateError" class="p-3 bg-rose-50/50 dark:bg-rose-955/20 rounded-xl border border-rose-200/50 dark:border-rose-900/40 text-rose-600 dark:text-rose-400 text-sm font-semibold text-right leading-relaxed">
            {{ dateError }}
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">حالة المهمة</label>
            <select 
              v-model="taskStatus"
              class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-sm text-slate-855 dark:text-slate-200 focus:outline-none cursor-pointer"
            >
              <option v-for="s in activeProject?.statuses" :key="s" :value="s">{{ s }}</option>
            </select>
          </div>
        </div>

        <!-- Left Side: Custom Fields Values & Attachments -->
        <div class="space-y-6 text-right">
          
          <!-- Custom Fields values inputs -->
          <div class="bg-slate-50/50 dark:bg-slate-955/20 border border-slate-100 dark:border-slate-855 rounded-2xl p-4 space-y-4">
            <h4 class="text-sm font-semibold text-slate-850 dark:text-slate-100 uppercase tracking-wider text-right">الحقول المخصصة للمهمة</h4>
            
            <div class="space-y-3">
              <div v-if="activeFields.length === 0" class="text-xs text-slate-400 italic py-3 border border-dashed border-slate-200/60 dark:border-slate-800 rounded-xl text-center">
                لا توجد حقول مخصصة للمشروع الحالي. يمكنك إعداد القوالب من صفحة الإعدادات.
              </div>
              
              <div v-for="field in activeFields" :key="field.id" class="space-y-1">
                <label class="block text-xs font-semibold text-slate-650 dark:text-slate-400 uppercase tracking-wide">
                  {{ field.name }} ({{ field.type === 'number' ? 'رقم' : field.type === 'link' ? 'رابط' : 'نص' }})
                </label>
                <input 
                  v-model="customFieldValues[field.id]" 
                  :type="field.type === 'number' ? 'number' : 'text'"
                  :placeholder="'أدخل قيمة ' + field.name + '...'"
                  class="w-full bg-white dark:bg-slate-950 border border-slate-250 dark:border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-850 dark:text-slate-200 focus:outline-none"
                />
              </div>

              <!-- LEGACY FIELDS -->
              <div v-if="legacyFields.length > 0" class="pt-2 border-t border-slate-200 dark:border-slate-800 space-y-2">
                <span class="text-xs font-bold text-amber-600 dark:text-amber-550 uppercase block tracking-wider text-right">
                  بيانات الحقول المورثة القديمة
                </span>
                <div v-for="field in legacyFields" :key="field.id" class="p-2.5 bg-amber-500/[0.03] border border-amber-500/10 rounded-lg text-xs space-y-1 text-right">
                  <span class="font-semibold text-amber-800 dark:text-amber-450 block text-xs">{{ field.name }}</span>
                  <p class="text-slate-655 dark:text-slate-350 font-mono text-xs truncate">{{ customFieldValues[field.id] }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Attachments Panel -->
          <div class="bg-slate-50/50 dark:bg-slate-955/20 border border-slate-100 dark:border-slate-855 rounded-2xl p-4 space-y-4">
            <div class="flex items-center justify-between">
              <h4 class="text-sm font-semibold text-slate-850 dark:text-slate-100 uppercase tracking-wider text-right">المرفقات والصور</h4>
              <span class="text-[11px] text-violet-600 dark:text-violet-400 font-extrabold">📋 يمكنك لصق الصورة (Ctrl+V)</span>
            </div>

            <!-- Image Paste & File Picker Drop Zone -->
            <div 
              @click="$refs.fileInputRef?.click()"
              class="border-2 border-dashed border-violet-200 dark:border-violet-900/50 hover:border-violet-500 rounded-xl p-3.5 text-center cursor-pointer transition bg-violet-50/30 dark:bg-violet-950/20 space-y-1 group/pastezone"
            >
              <input 
                ref="fileInputRef"
                type="file"
                accept="image/*"
                multiple
                class="hidden"
                @change="handleFileSelect"
              />
              <div class="text-xs font-extrabold text-violet-700 dark:text-violet-300 flex items-center justify-center gap-1.5 group-hover/pastezone:scale-105 transition">
                <span>📋</span>
                <span>اضغط لاختيار صورة أو استخدم (Ctrl+V) للصق صورة مباشرة</span>
              </div>
              <p class="text-[10.5px] text-slate-400">يدعم الصور والملفات المختلفة</p>
            </div>
            
            <div v-if="taskToEdit" class="space-y-2">
              <div v-if="taskToEdit.attachments.length === 0" class="text-sm text-slate-400 italic">
                لم يتم إرفاق ملفات لهذه المهمة بعد.
              </div>
              <div 
                v-for="(file, idx) in taskToEdit.attachments" 
                :key="idx" 
                class="flex items-center justify-between p-2.5 rounded-lg bg-white dark:bg-slate-950 border border-slate-100 dark:border-slate-850 flex-row-reverse"
              >
                <div class="min-w-0 flex-1 pr-2 text-right">
                  <div class="flex items-center justify-between pr-2 text-xs flex-row-reverse">
                    <span class="font-semibold text-slate-750 dark:text-slate-350 truncate">{{ file.name }}</span>
                    <span class="text-xs text-slate-455 font-mono">{{ file.size }}</span>
                  </div>
                  
                  <div class="w-full bg-slate-100 dark:bg-slate-800 h-1.5 rounded-full mt-1.5 overflow-hidden">
                    <div 
                      :class="[
                        'h-full transition-all duration-300',
                        file.status === 'failed' ? 'bg-rose-500' : 'bg-violet-650'
                      ]" 
                      :style="{ width: file.progress + '%' }"
                    ></div>
                  </div>
                </div>

                <div class="pl-2">
                  <span v-if="file.status === 'done'" class="text-[10.5px] font-bold text-emerald-500 bg-emerald-500/10 px-1.5 py-0.5 rounded">جاهز</span>
                  <span v-else-if="file.status === 'failed'" class="text-[10.5px] font-bold text-rose-500 bg-rose-500/10 px-1.5 py-0.5 rounded">فشل الرفع</span>
                  <span v-else class="text-[10.5px] font-bold text-violet-550 animate-pulse">{{ file.progress }}%</span>
                </div>
              </div>
            </div>

            <!-- Upload simulation triggers -->
            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2 text-right">
              <span class="text-xs font-bold text-slate-450 dark:text-slate-400 uppercase tracking-wide block">محاكاة رفع ملف في الخلفية</span>
              <div class="flex items-center space-x-2 space-x-reverse">
                <input 
                  v-model="fileToUploadName" 
                  type="text" 
                  placeholder="مثال: تصاميم-الواجهة.png" 
                  class="flex-1 bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-850 rounded-lg px-2.5 py-1.5 text-sm text-slate-855 dark:text-slate-200 focus:outline-none"
                />
                <button 
                  @click="triggerFileUpload"
                  :disabled="!fileToUploadName.trim()"
                  class="bg-violet-650 hover:bg-violet-755 disabled:opacity-40 text-white font-bold px-3 py-1.5 rounded-lg text-sm transition cursor-pointer"
                >
                  رفع
                </button>
              </div>
              <div class="flex items-center space-x-2 space-x-reverse justify-end">
                <label for="fail-check" class="text-sm text-slate-500 cursor-pointer select-none">محاكاة فشل الرفع تلقائياً عند 50%</label>
                <input 
                  id="fail-check" 
                  type="checkbox" 
                  v-model="simulateFailure" 
                  class="text-violet-650 focus:ring-violet-500 rounded cursor-pointer"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
      <div v-if="validationError" class="text-sm text-rose-500 text-right font-bold">
        {{ validationError }}
      </div>

      <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800 flex-row-reverse flex-wrap gap-2">
        <div class="flex items-center space-x-3 space-x-reverse flex-wrap gap-2">
          <button 
            @click="closeModal" 
            class="border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-350 hover:bg-slate-50 dark:hover:bg-slate-955 font-semibold py-2 px-4 rounded-xl text-sm transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center"
          >
            إلغاء
          </button>
          <button 
            @click="handleSaveTask"
            :disabled="!!dateError"
            class="bg-violet-600 hover:bg-violet-755 disabled:opacity-40 text-white font-semibold py-2 px-5 rounded-xl text-sm transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center"
          >
            {{ taskToEdit ? 'حفظ التغييرات' : 'إنشاء مهمة' }}
          </button>
        </div>
        <button 
          v-if="taskToEdit"
          @click="handleDeleteTask"
          class="bg-rose-50 hover:bg-rose-100 text-rose-650 dark:bg-rose-955/20 dark:text-rose-400 font-semibold py-2 px-4 rounded-xl text-sm transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center"
        >
          حذف المهمة
        </button>
      </div>

    </div>
  </div>
</Transition>
</template>
