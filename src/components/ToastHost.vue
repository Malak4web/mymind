<script setup>
import { store } from '../store'

/**
 * The app had no transient feedback at all. Every "تم الحفظ" / "تم الحذف"
 * was written to the notification drawer -- which is closed by default --
 * so confirmations were invisible at the moment they happened, and every
 * failure went to console.error and nowhere else.
 *
 * Toasts sit above the mobile navigation and inside the safe area.
 */

const toneClass = (tone) => {
  switch (tone) {
    case 'success': return 'border-emerald-500/30 text-emerald-700 dark:text-emerald-300'
    case 'error':   return 'border-rose-500/40 text-rose-700 dark:text-rose-300'
    case 'warning': return 'border-amber-500/35 text-amber-700 dark:text-amber-300'
    default:        return 'border-violet-500/30 text-slate-700 dark:text-slate-200'
  }
}

const toneIcon = (tone) => {
  switch (tone) {
    case 'success': return '✓'
    case 'error':   return '!'
    case 'warning': return '!'
    default:        return 'i'
  }
}

const toneDot = (tone) => {
  switch (tone) {
    case 'success': return 'bg-emerald-500'
    case 'error':   return 'bg-rose-500'
    case 'warning': return 'bg-amber-500'
    default:        return 'bg-violet-500'
  }
}
</script>

<template>
  <div
    class="fixed inset-x-0 z-toast flex flex-col items-center gap-2 px-4 pointer-events-none above-nav sm:bottom-6"
    dir="rtl"
  >
    <TransitionGroup name="sheet">
      <div
        v-for="t in store.toasts"
        :key="t.id"
        class="pointer-events-auto w-full max-w-md bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl border shadow-2xl rounded-2xl px-4 py-3 flex items-center gap-3"
        :class="toneClass(t.tone)"
        role="status"
        aria-live="polite"
      >
        <span
          class="shrink-0 w-7 h-7 rounded-full text-white grid place-items-center text-sm font-bold"
          :class="toneDot(t.tone)"
          aria-hidden="true"
        >{{ toneIcon(t.tone) }}</span>

        <p class="flex-1 min-w-0 text-sm font-semibold leading-snug">{{ t.text }}</p>

        <button
          v-if="t.action"
          @click="store.runToastAction(t.id)"
          class="shrink-0 text-sm font-bold text-violet-600 dark:text-violet-400 hover:underline px-2 min-h-[44px] cursor-pointer"
        >
          {{ t.action.label }}
        </button>

        <button
          @click="store.dismissToast(t.id)"
          class="shrink-0 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 min-h-[44px] min-w-[44px] grid place-items-center cursor-pointer"
          aria-label="إغلاق التنبيه"
        >✕</button>
      </div>
    </TransitionGroup>
  </div>
</template>
