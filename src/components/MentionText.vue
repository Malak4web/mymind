<script setup>
import { computed } from 'vue'
import { store } from '../store'

const props = defineProps({
  content: {
    type: String,
    default: ''
  },
  tagClass: {
    type: String,
    default: ''
  }
})

// Parse text tokens into plain text and styled mention pills / hyperlinks
const tokens = computed(() => {
  const text = props.content || ''
  if (!text) return []

  // IMPORTANT: Markdown links MUST come FIRST in alternation to take priority over emoji mentions
  const regex = /(\[[^\]]+\]\([^\)]+\)|https?:\/\/[^\s)]+|@[\w\u0600-\u06FF]+|📁\s*[\w\u0600-\u06FF\s.#_-]+|📄\s*[\w\u0600-\u06FF\s.#_-]+|📝\s*[\w\u0600-\u06FF\s.#_-]+|📋\s*[\w\u0600-\u06FF\s.#_-]+)/g

  const result = []
  let lastIndex = 0
  let match

  while ((match = regex.exec(text)) !== null) {
    if (match.index > lastIndex) {
      result.push({ isMention: false, text: text.slice(lastIndex, match.index) })
    }

    const matchedText = match[0].trim()
    let type = 'user'
    let styleClass = 'bg-violet-500/10 text-violet-600 dark:text-violet-400 border-violet-500/20'
    let label = matchedText
    let targetUrl = ''

    // Parse Markdown link `[Label](URL)` - this is the KEY check
    const mdMatch = matchedText.match(/^\[([^\]]+)\]\(([^)]+)\)$/)
    if (mdMatch) {
      label = mdMatch[1].trim()
      targetUrl = mdMatch[2].trim()
      
      // Determine type from label content
      if (label.includes('📄')) {
        type = 'file'
        styleClass = 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20 hover:bg-indigo-500/20'
      } else if (label.includes('📁')) {
        type = 'folder'
        styleClass = 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20 hover:bg-amber-500/20'
      } else if (label.includes('📝')) {
        type = 'note'
        styleClass = 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 hover:bg-emerald-500/20'
      } else if (label.includes('📋')) {
        type = 'task'
        styleClass = 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20 hover:bg-purple-500/20'
      } else {
        type = 'url'
        styleClass = 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20 hover:bg-blue-500/20'
      }
    } else if (matchedText.startsWith('http://') || matchedText.startsWith('https://')) {
      type = 'url'
      label = matchedText
      targetUrl = matchedText
      styleClass = 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20 hover:bg-blue-500/20'
    } else if (matchedText.startsWith('📁')) {
      type = 'folder'
      styleClass = 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20 hover:bg-amber-500/20'
    } else if (matchedText.startsWith('📄')) {
      type = 'file'
      styleClass = 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20 hover:bg-indigo-500/20'
    } else if (matchedText.startsWith('📝')) {
      type = 'note'
      styleClass = 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 hover:bg-emerald-500/20'
    } else if (matchedText.startsWith('📋')) {
      type = 'task'
      styleClass = 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20 hover:bg-purple-500/20'
    }

    result.push({
      isMention: true,
      type,
      text: label,
      url: targetUrl,
      styleClass
    })

    lastIndex = regex.lastIndex
  }

  if (lastIndex < text.length) {
    result.push({ isMention: false, text: text.slice(lastIndex) })
  }

  return result
})

// Resolve file URL for a file token
const getFileUrl = (token) => {
  // If token already has a direct URL from markdown parsing, use it
  if (token.url && token.url.startsWith('http')) {
    return token.url
  }
  // Fallback: find the file in store by name
  const rawName = token.text.replace(/^[📁📄📝📋@]\s*/, '').trim()
  const file = store.projectFiles.find(f =>
    f.name.trim().toLowerCase() === rawName.toLowerCase() ||
    rawName.toLowerCase().includes(f.name.trim().toLowerCase()) ||
    f.name.trim().toLowerCase().includes(rawName.toLowerCase())
  )
  if (file) {
    return `${store.apiBase}/project-files/${file.id}/download`
  }
  return `${store.apiBase}/projects/${store.activeProjectId || 1}/project-files`
}

// Handle clicking on NON-file mentions (folders, notes, tasks, users)
const handleNonFileClick = async (token, e) => {
  if (e) {
    e.preventDefault()
    e.stopPropagation()
  }
  const rawName = token.text.replace(/^[📁📄📝📋@]\s*/, '').trim()

  if (token.type === 'folder') {
    if (!store.folders || store.folders.length === 0) await store.loadFolders()
    const folder = store.folders.find(f =>
      f.name.trim().toLowerCase() === rawName.toLowerCase() ||
      rawName.toLowerCase().includes(f.name.trim().toLowerCase())
    )
    store.activeView = 'documents'
    if (folder) store.activeDocumentFolderId = folder.id
  } else if (token.type === 'note') {
    store.activeView = 'documents'
  } else if (token.type === 'task') {
    const task = store.tasks.find(t => t.title.trim().toLowerCase() === rawName.toLowerCase())
    if (task) {
      store.selectedTaskIdForModal = task.id
      store.isTaskModalOpen = true
    }
  }
}

// Explicit click handler for file tokens using window.open as ultimate fallback
const handleFileClick = (token, e) => {
  if (e) {
    e.preventDefault()
    e.stopPropagation()
    e.stopImmediatePropagation()
  }
  const url = getFileUrl(token)
  window.open(url, '_blank', 'noopener,noreferrer')
}
</script>

<template>
  <span class="inline leading-relaxed">
    <template v-for="(token, idx) in tokens" :key="idx">
      <!-- File mentions: use explicit window.open click handler -->
      <span
        v-if="token.isMention && token.type === 'file'"
        @click.stop.prevent="handleFileClick(token, $event)"
        @mousedown.stop
        :class="[
          'inline-flex items-center gap-1 px-1.5 py-0.5 mx-0.5 rounded-md text-[11px] font-bold border transition select-none align-baseline cursor-pointer group/pill',
          token.styleClass,
          tagClass
        ]"
        :title="'فتح الملف في نافذة جديدة: ' + token.text"
        role="link"
      >
        <span>{{ token.text }}</span>
        <span class="text-[9px] opacity-60 group-hover/pill:opacity-100 transition">↗</span>
      </span>

      <!-- URL mentions: also explicit window.open -->
      <span
        v-else-if="token.isMention && token.type === 'url'"
        @click.stop.prevent="handleFileClick(token, $event)"
        @mousedown.stop
        :class="[
          'inline-flex items-center gap-1 px-1.5 py-0.5 mx-0.5 rounded-md text-[11px] font-bold border transition select-none align-baseline cursor-pointer group/pill',
          token.styleClass,
          tagClass
        ]"
        :title="'فتح الرابط: ' + token.url"
        role="link"
      >
        <span>{{ token.text }}</span>
        <span class="text-[9px] opacity-60 group-hover/pill:opacity-100 transition">↗</span>
      </span>

      <!-- Non-file mentions (folder, note, task, user) -->
      <span
        v-else-if="token.isMention"
        @click.stop.prevent="handleNonFileClick(token, $event)"
        @mousedown.stop
        :class="[
          'inline-flex items-center gap-1 px-1.5 py-0.5 mx-0.5 rounded-md text-[11px] font-bold border transition select-none align-baseline cursor-pointer group/pill',
          token.styleClass,
          tagClass
        ]"
        :title="token.type === 'user' ? 'عضو منشن' : 'انقر للانتقال إلى ' + token.text"
      >
        <span>{{ token.text }}</span>
        <span v-if="token.type !== 'user'" class="text-[9px] opacity-60 group-hover/pill:opacity-100 transition">🔗</span>
      </span>

      <span v-else>{{ token.text }}</span>
    </template>
  </span>
</template>
