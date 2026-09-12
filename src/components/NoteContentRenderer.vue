<script setup>
import { computed, ref } from 'vue'
import { store } from '../store'
import MentionText from './MentionText.vue'

const props = defineProps({
  content: {
    type: String,
    default: ''
  },
  maxItems: {
    type: Number,
    default: 0 // 0 means unlimited
  },
  compact: {
    type: Boolean,
    default: false
  }
})

// Track copied state for visual feedback per item
const copiedIndex = ref(null)
let copyTimeout = null

// Parse lines into structured tokens (paragraphs vs list items)
const parsedLines = computed(() => {
  const text = props.content || ''
  if (!text.trim()) return []

  const rawLines = text.split('\n')
  const items = []

  rawLines.forEach((line, idx) => {
    const trimmed = line.trim()
    if (!trimmed) {
      items.push({ type: 'empty', id: idx })
      return
    }

    // Check for bullet list: - , * , • , +
    const bulletMatch = line.match(/^(\s*)([-*•+])\s+(.*)$/)
    // Check for numbered list: 1. , 2. , etc.
    const numMatch = line.match(/^(\s*)(\d+[.)])\s+(.*)$/)

    if (bulletMatch) {
      items.push({
        type: 'list-item',
        listType: 'bullet',
        id: idx,
        bullet: '•',
        text: bulletMatch[3],
        rawText: getCleanItemText(bulletMatch[3])
      })
    } else if (numMatch) {
      items.push({
        type: 'list-item',
        listType: 'numbered',
        id: idx,
        bullet: numMatch[2],
        text: numMatch[3],
        rawText: getCleanItemText(numMatch[3])
      })
    } else {
      items.push({
        type: 'paragraph',
        id: idx,
        text: line,
        rawText: line
      })
    }
  })

  return items
})

// Clean text for copying (extracting URL or title from [title](url) when applicable)
function getCleanItemText(text) {
  if (!text) return ''
  const trimmed = text.trim()
  // If the item is purely a markdown link `[Label](url)`
  const pureLinkMatch = trimmed.match(/^\[([^\]]+)\]\(([^)]+)\)$/)
  if (pureLinkMatch) {
    return pureLinkMatch[2].trim() // copy URL directly
  }
  // If text contains markdown links `[Label](url)`, convert to Label (url)
  return trimmed.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '$1 ($2)')
}

// Displayed lines respecting maxItems
const visibleLines = computed(() => {
  if (props.maxItems > 0 && parsedLines.value.length > props.maxItems) {
    return parsedLines.value.slice(0, props.maxItems)
  }
  return parsedLines.value
})

const hasMoreItems = computed(() => {
  return props.maxItems > 0 && parsedLines.value.length > props.maxItems
})

const remainingCount = computed(() => {
  return parsedLines.value.length - props.maxItems
})

// Copy a single list item
const handleCopyItem = async (item, event) => {
  if (event) {
    event.stopPropagation()
    event.preventDefault()
  }

  const textToCopy = item.rawText || item.text || ''
  if (!textToCopy) return

  try {
    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(textToCopy)
    }
  } catch (err) {
    console.warn('Clipboard writeText failed, fallback executed:', err)
  }

  copiedIndex.value = item.id
  if (copyTimeout) clearTimeout(copyTimeout)
  copyTimeout = setTimeout(() => {
    copiedIndex.value = null
  }, 1800)

  if (store.toastSuccess) {
    store.toastSuccess(`تم نسخ العنصر: "${textToCopy.length > 30 ? textToCopy.slice(0, 30) + '...' : textToCopy}"`)
  }
}
</script>

<template>
  <div class="note-content-renderer space-y-1 text-right" dir="rtl">
    <template v-for="item in visibleLines" :key="item.id">
      <!-- Empty line spacer -->
      <div v-if="item.type === 'empty'" class="h-1.5"></div>

      <!-- List Item Row -->
      <div
        v-else-if="item.type === 'list-item'"
        class="flex items-start gap-1.5 py-0.5 px-1 rounded-lg group/item transition hover:bg-slate-50 dark:hover:bg-slate-800/40 text-xs"
      >
        <!-- Copy Icon in front of the item (rightmost in RTL) -->
        <button
          type="button"
          @click="handleCopyItem(item, $event)"
          :class="[
            'copy-item-btn shrink-0 p-1 rounded-md transition-all cursor-pointer flex items-center justify-center min-w-[24px] min-h-[24px]',
            copiedIndex === item.id
              ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 font-bold scale-105'
              : 'text-slate-400 hover:text-violet-600 dark:hover:text-violet-400 hover:bg-violet-50 dark:hover:bg-violet-950/40 opacity-80 group-hover/item:opacity-100'
          ]"
          :title="copiedIndex === item.id ? 'تم النسخ!' : 'نسخ هذا العنصر'"
          :aria-label="'نسخ العنصر: ' + item.text"
        >
          <!-- Copied Feedback Checkmark -->
          <svg v-if="copiedIndex === item.id" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
          </svg>

          <!-- Default Clipboard Copy Icon -->
          <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
        </button>

        <!-- Bullet / Number symbol -->
        <span class="font-bold text-violet-500 dark:text-violet-400 shrink-0 select-none text-xs leading-relaxed min-w-[12px]">
          {{ item.bullet }}
        </span>

        <!-- List Item Content with Clickable Hyperlinks -->
        <div class="flex-1 text-slate-800 dark:text-slate-200 leading-relaxed break-words min-w-0">
          <MentionText :content="item.text" />
        </div>
      </div>

      <!-- Regular Paragraph -->
      <div
        v-else
        class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed px-1 py-0.5 break-words"
      >
        <MentionText :content="item.text" />
      </div>
    </template>

    <!-- More items indicator when truncated -->
    <div v-if="hasMoreItems" class="text-[10px] text-violet-600 dark:text-violet-400 font-bold px-1 pt-0.5">
      + {{ remainingCount }} عنصر إضافي...
    </div>
  </div>
</template>

<style scoped>
.copy-item-btn:hover svg {
  transform: scale(1.1);
  transition: transform 0.1s ease-in-out;
}
</style>
