<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { store } from '../store'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  isTextarea: {
    type: Boolean,
    default: false
  },
  rows: {
    type: [Number, String],
    default: 3
  },
  placeholder: {
    type: String,
    default: ''
  },
  inputClass: {
    type: String,
    default: ''
  },
  disabled: {
    type: Boolean,
    default: false
  },
  autofocus: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'change', 'keydown', 'keyup', 'blur', 'focus', 'submit', 'paste'])

const inputRef = ref(null)
const isMenuOpen = ref(false)
const triggerType = ref('') // '@' or '/'
const searchQuery = ref('')
const selectedIndex = ref(0)
const triggerIndex = ref(-1)

// Close menu on click outside
const containerRef = ref(null)
const handleClickOutside = (e) => {
  if (containerRef.value && !containerRef.value.contains(e.target)) {
    isMenuOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  if (props.autofocus) {
    nextTick(() => {
      inputRef.value?.focus()
    })
  }
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

const activeProject = computed(() => store.projects.find(p => p.id === store.activeProjectId))

// Compute Member items for '@' (strictly filtered to active project members)
const memberItems = computed(() => {
  const allUsers = store.users && store.users.length > 0 ? store.users : [
    { id: 1, name: 'خالد', roleName: 'مدير' },
    { id: 2, name: 'سارة', roleName: 'عضو' },
    { id: 3, name: 'أحمد', roleName: 'مشاهد' }
  ]

  const projectMemberIds = activeProject.value?.memberIds || []
  
  // Filter strictly to users assigned to the current active project
  const list = allUsers.filter(u => projectMemberIds.includes(u.id))

  const q = searchQuery.value.trim().toLowerCase()
  return list
    .filter(u => !q || u.name.toLowerCase().includes(q) || (u.roleName && u.roleName.toLowerCase().includes(q)))
    .map(u => ({
      id: 'user-' + u.id,
      name: u.name,
      badge: u.roleName || 'عضو',
      icon: '👤',
      typeLabel: 'عضو مشروع',
      insertText: `@${u.name} `
    }))
})

// Compute File/Folder/Note/Task items for '/'
const fileFolderItems = computed(() => {
  const items = []

  // Folders
  if (store.folders) {
    store.folders.forEach(f => {
      const folderUrl = `${window.location.origin}#folder-${f.id}`
      items.push({
        id: 'folder-' + f.id,
        name: f.name,
        badge: 'مجلد',
        icon: '📁',
        typeLabel: 'مجلد مشروع',
        url: folderUrl,
        insertText: `📁 ${f.name} `
      })
    })
  }

  // Project Files
  if (store.projectFiles) {
    store.projectFiles.forEach(f => {
      const fileUrl = `${store.apiBase}/project-files/${f.id}/download`
      items.push({
        id: 'file-' + f.id,
        name: f.name,
        badge: f.size || f.file_size || 'ملف',
        icon: '📄',
        typeLabel: 'ملف مرفق',
        url: fileUrl,
        insertText: `[📄 ${f.name}](${fileUrl}) `
      })
    })
  }

  // Notes
  if (store.notes) {
    store.notes.forEach(n => {
      const noteUrl = `${window.location.origin}#note-${n.id}`
      items.push({
        id: 'note-' + n.id,
        name: n.title,
        badge: 'ملاحظة',
        icon: '📝',
        typeLabel: 'وثيقة / ملاحظة',
        url: noteUrl,
        insertText: `📝 ${n.title} `
      })
    })
  }

  // Tasks
  if (store.tasks) {
    store.tasks.forEach(t => {
      const taskUrl = `${window.location.origin}#task-${t.id}`
      items.push({
        id: 'task-' + t.id,
        name: t.title,
        badge: t.status || 'مهمة',
        icon: '📋',
        typeLabel: 'مهمة عمل',
        url: taskUrl,
        insertText: `📋 ${t.title} `
      })
    })
  }

  const q = searchQuery.value.trim().toLowerCase()
  return items.filter(item => !q || item.name.toLowerCase().includes(q) || item.typeLabel.toLowerCase().includes(q))
})

// Filtered suggestion items depending on current trigger type ('@' or '/')
const filteredSuggestions = computed(() => {
  if (triggerType.value === '@') {
    return memberItems.value
  } else if (triggerType.value === '/') {
    return fileFolderItems.value
  }
  return []
})

// Handle input text changes & detect trigger symbols ('@' or '/')
const handleInput = (e) => {
  const val = e.target.value
  emit('update:modelValue', val)

  const cursor = e.target.selectionStart
  const textBeforeCursor = val.slice(0, cursor)

  // Match `@name` or `/filename` at end of text before cursor
  // Regex looks for '@' or '/' preceded by space or start of line
  const match = textBeforeCursor.match(/(?:^|\s)([/@])([^\s/@]*)$/)

  if (match) {
    triggerType.value = match[1]
    searchQuery.value = match[2]
    triggerIndex.value = textBeforeCursor.lastIndexOf(match[1])
    isMenuOpen.value = true
    selectedIndex.value = 0
  } else {
    isMenuOpen.value = false
    triggerType.value = ''
    searchQuery.value = ''
  }
}

// Handle keyboard navigation inside mention popup
const handleKeydown = (e) => {
  emit('keydown', e)

  if (!isMenuOpen.value || filteredSuggestions.value.length === 0) {
    if (e.key === 'Enter' && (!props.isTextarea || !e.shiftKey)) {
      e.preventDefault()
      emit('submit')
    }
    return
  }

  if (e.key === 'ArrowDown') {
    e.preventDefault()
    selectedIndex.value = (selectedIndex.value + 1) % filteredSuggestions.value.length
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    selectedIndex.value = (selectedIndex.value - 1 + filteredSuggestions.value.length) % filteredSuggestions.value.length
  } else if (e.key === 'Enter' || e.key === 'Tab') {
    e.preventDefault()
    selectItem(filteredSuggestions.value[selectedIndex.value])
  } else if (e.key === 'Escape') {
    e.preventDefault()
    isMenuOpen.value = false
  }
}

// Insert selected mention item into text
const selectItem = (item) => {
  if (!item || triggerIndex.value < 0) return

  const inputEl = inputRef.value
  if (!inputEl) return

  const val = props.modelValue || ''
  const cursor = inputEl.selectionStart

  const beforeTrigger = val.slice(0, triggerIndex.value)
  const afterCursor = val.slice(cursor)

  const newVal = beforeTrigger + item.insertText + afterCursor
  emit('update:modelValue', newVal)
  emit('change', newVal)

  isMenuOpen.value = false

  nextTick(() => {
    inputEl.focus()
    const newCursor = triggerIndex.value + item.insertText.length
    inputEl.setSelectionRange(newCursor, newCursor)
  })
}
</script>

<template>
  <div ref="containerRef" class="relative w-full text-right" dir="rtl">
    
    <!-- Textarea Input -->
    <textarea
      v-if="isTextarea"
      ref="inputRef"
      :value="modelValue"
      :rows="rows"
      :placeholder="placeholder || 'اكتب التفاصيل... (استخدم / للملفات والمجلدات، و @ للمنشَن)'"
      :disabled="disabled"
      :class="[
        'w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-805 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-855 dark:text-slate-200 focus:outline-none focus:border-violet-500 transition-all font-sans leading-relaxed resize-none',
        inputClass
      ]"
      @input="handleInput"
      @keydown="handleKeydown"
      @keyup="e => emit('keyup', e)"
      @blur="e => emit('blur', e)"
      @focus="e => emit('focus', e)"
      @paste="e => emit('paste', e)"
    ></textarea>

    <!-- Single-line Text Input -->
    <input
      v-else
      ref="inputRef"
      type="text"
      :value="modelValue"
      :placeholder="placeholder || 'اكتب... (استخدم / للملفات أو @ للمنشن)'"
      :disabled="disabled"
      :class="[
        'w-full bg-slate-50 dark:bg-slate-955 border border-slate-200 dark:border-slate-805 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-855 dark:text-slate-200 focus:outline-none focus:border-violet-500 transition-all font-sans',
        inputClass
      ]"
      @input="handleInput"
      @keydown="handleKeydown"
      @keyup="e => emit('keyup', e)"
      @blur="e => emit('blur', e)"
      @focus="e => emit('focus', e)"
      @paste="e => emit('paste', e)"
    />

    <!-- Floating Mention Autocomplete Popup -->
    <Transition name="fade">
      <div 
        v-if="isMenuOpen && filteredSuggestions.length > 0" 
        class="absolute z-50 bottom-full mb-2 right-0 w-full sm:w-80 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl overflow-hidden text-right select-none animate-fade-in"
      >
        <!-- Header badge -->
        <div class="bg-slate-100/80 dark:bg-slate-955/80 px-3.5 py-2 border-b border-slate-200/60 dark:border-slate-800 flex items-center justify-between flex-row-reverse text-[10px] font-bold text-slate-500 dark:text-slate-400">
          <span class="flex items-center gap-1.5">
            <span>{{ triggerType === '@' ? '👥 منشن عضو في المشروع' : '📁 منشن ملف أو مجلد أو مهمة' }}</span>
          </span>
          <span class="font-mono text-[9px] bg-slate-200 dark:bg-slate-800 px-1.5 py-0.5 rounded text-slate-600 dark:text-slate-300">
            {{ filteredSuggestions.length }} نتائج
          </span>
        </div>

        <!-- Suggestion items list -->
        <div class="max-h-56 overflow-y-auto p-1.5 space-y-1">
          <div
            v-for="(item, idx) in filteredSuggestions"
            :key="item.id"
            @click="selectItem(item)"
            @mouseenter="selectedIndex = idx"
            :class="[
              'flex items-center justify-between px-3 py-2 rounded-xl text-xs font-bold transition duration-150 cursor-pointer flex-row-reverse gap-3',
              selectedIndex === idx
                ? 'bg-violet-600 text-white shadow-sm'
                : 'text-slate-800 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800/60'
            ]"
          >
            <!-- Right Icon & Name -->
            <div class="flex items-center gap-2 flex-row-reverse truncate">
              <span class="text-sm shrink-0">{{ item.icon }}</span>
              <span class="truncate">{{ item.name }}</span>
            </div>

            <!-- Left Type Badge -->
            <span 
              :class="[
                'text-[9px] font-extrabold px-2 py-0.5 rounded-md shrink-0',
                selectedIndex === idx 
                  ? 'bg-white/20 text-white' 
                  : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'
              ]"
            >
              {{ item.badge }}
            </span>
          </div>
        </div>

        <!-- Footer helper hint -->
        <div class="bg-slate-50/50 dark:bg-slate-955/30 px-3 py-1.5 border-t border-slate-100 dark:border-slate-850 text-[9.5px] text-slate-400 font-semibold text-center">
          💡 استخدم أسهم الفأرة/الكيبورد وسجل باختيار Enter
        </div>
      </div>
    </Transition>

  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(4px);
}
</style>
