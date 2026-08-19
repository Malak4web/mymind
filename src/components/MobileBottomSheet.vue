<script setup>
import { ref, watch, onUnmounted } from 'vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true
  },
  title: {
    type: String,
    default: ''
  },
  icon: {
    type: String,
    default: ''
  },
  showClose: {
    type: Boolean,
    default: true
  },
  maxWidth: {
    type: String,
    default: 'max-w-md'
  },
  drawerMode: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close'])

// Dynamic touch drag tracking state
const dragY = ref(0)
const isDragging = ref(false)
let touchStartY = 0
let touchStartTime = 0

const handleTouchStart = (e) => {
  if (e.touches && e.touches.length === 1) {
    touchStartY = e.touches[0].clientY
    touchStartTime = Date.now()
    isDragging.value = true
    dragY.value = 0
  }
}

const handleTouchMove = (e) => {
  if (!isDragging.value || !e.touches || e.touches.length === 0) return
  const currentY = e.touches[0].clientY
  const deltaY = currentY - touchStartY
  
  // Only track downward drag
  if (deltaY > 0) {
    dragY.value = deltaY
  } else {
    dragY.value = 0
  }
}

const handleTouchEnd = () => {
  if (!isDragging.value) return
  isDragging.value = false
  
  const duration = Date.now() - touchStartTime
  const velocity = duration > 0 ? dragY.value / duration : 0

  // Dismiss if dragged down more than 80px or drag velocity > 0.4 px/ms
  if (dragY.value > 80 || velocity > 0.4) {
    emit('close')
  }

  // Reset drag position (with transition enabled)
  dragY.value = 0
}

const handleTouchCancel = () => {
  isDragging.value = false
  dragY.value = 0
}

// Reset drag state when modal opens/closes
watch(() => props.isOpen, (newVal) => {
  dragY.value = 0
  isDragging.value = false
  if (newVal) {
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = ''
  }
})

onUnmounted(() => {
  document.body.style.overflow = ''
})
</script>

<template>
  <Transition name="sheet">
    <div 
      v-if="isOpen" 
      :class="[
        'fixed inset-0 z-50 flex mobile-bottom-sheet',
        drawerMode 
          ? 'items-end sm:items-stretch justify-center sm:justify-end' 
          : 'items-end sm:items-center justify-center p-0 sm:p-4'
      ]"
      role="dialog"
      dir="rtl"
    >
      <!-- Backdrop Overlay -->
      <div 
        @click="emit('close')" 
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity duration-300 cursor-pointer z-40"
      ></div>

      <!-- Sheet Container Box -->
      <div 
        :class="[
          'relative z-50 bg-white/95 dark:bg-slate-900/95 backdrop-blur-2xl border-t border-slate-200/80 dark:border-slate-800 shadow-2xl flex flex-col overflow-hidden text-right transform transition-all duration-300 w-full',
          drawerMode 
            ? 'sm:border-t-0 sm:border-l sm:rounded-none max-h-[88vh] sm:max-h-none sm:h-full rounded-t-3xl' 
            : 'sm:border sm:rounded-3xl max-h-[85vh] sm:max-h-[90vh] rounded-t-3xl',
          maxWidth || 'max-w-md'
        ]"
        :style="dragY > 0 ? { transform: `translateY(${dragY}px)`, transition: isDragging ? 'none' : 'transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275)' } : {}"
      >
        <!-- Top Drag Handle Header (Touch gesture listeners isolated HERE) -->
        <div 
          @touchstart="handleTouchStart"
          @touchmove="handleTouchMove"
          @touchend="handleTouchEnd"
          @touchcancel="handleTouchCancel"
          class="shrink-0 cursor-grab active:cursor-grabbing select-none pt-3 px-6 pb-2 border-b border-slate-100 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-800/50"
          style="touch-action: none;"
        >
          <!-- Visual Drag Bar Indicator -->
          <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto mb-3 shrink-0 sm:hidden"></div>

          <!-- Header Content / Title & Close button -->
          <slot name="header">
            <div v-if="title || showClose" class="flex items-center justify-between min-h-[44px]">
              <h3 v-if="title" class="text-lg sm:text-xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span v-if="icon">{{ icon }}</span>
                <span>{{ title }}</span>
              </h3>

              <!-- Min 44px x 44px Touch Target Close Button -->
              <button 
                v-if="showClose"
                @click.stop="emit('close')" 
                class="min-h-[44px] min-w-[44px] flex items-center justify-center p-2.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-200/60 dark:hover:bg-slate-800 transition cursor-pointer"
                title="إغلاق" aria-label="إغلاق"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </slot>
        </div>

        <!-- Sheet Scrollable Body (Isolated from drag touch listeners so inner scrolling is smooth) -->
        <div class="flex-1 overflow-y-auto p-5 sm:p-6 text-right scrollbar-hide">
          <slot></slot>
        </div>

        <!-- Optional Sheet Footer -->
        <div v-if="$slots.footer" class="shrink-0 p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50">
          <slot name="footer"></slot>
        </div>

      </div>
    </div>
  </Transition>
</template>
