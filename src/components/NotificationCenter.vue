<script setup>
import { store } from '../store'
import { computed } from 'vue'

const unreadCount = computed(() => store.notifications.filter(n => !n.isRead).length)

const requestPush = () => {
  store.requestPushPermission()
}

const denyPush = () => {
  store.denyPushPermission()
}

// Icon helper based on notification title/type
const getNotificationIcon = (title) => {
  const t = title.toLowerCase()
  if (t.includes('إضافة') || t.includes('جديدة') || t.includes('task added') || t.includes('new task')) {
    return `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>`
  }
  if (t.includes('تحديث') || t.includes('تعديل') || t.includes('تغيير') || t.includes('تعارض') || t.includes('conflict')) {
    return `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H12v3" /></svg>`
  }
  if (t.includes('حذف') || t.includes('إزالة') || t.includes('deleted')) {
    return `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>`
  }
  return `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`
}
</script>

<template>
  <div class="w-full h-full flex flex-col space-y-6 text-right">
    <!-- Header -->
    <div class="flex items-center justify-between pb-3.5 border-b border-slate-100 dark:border-slate-850 flex-row-reverse">
      <div class="flex items-center space-x-2 space-x-reverse">
        <h3 class="text-xs font-bold text-slate-850 dark:text-slate-400 uppercase tracking-wider">مركز الإشعارات</h3>
        <span v-if="unreadCount > 0" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-violet-100 dark:bg-violet-955 text-violet-650 dark:text-violet-400 border border-violet-200/30">
          {{ unreadCount }} جديد
        </span>
      </div>
      
      <div class="flex items-center space-x-3 space-x-reverse">
        <button 
          @click="store.markAllNotificationsRead()" 
          class="text-xs font-bold text-slate-455 hover:text-slate-750 dark:hover:text-slate-250 transition cursor-pointer"
        >
          تحديد المقروء للكل
        </button>
        
        <!-- Drawer close button -->
        <button 
          @click="store.toggleNotificationDrawer()"
          class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-950 transition cursor-pointer animate-pulse min-h-[44px] min-w-[44px] flex items-center justify-center"
          title="إغلاق اللوحة"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Push Notification Banner Edge Case -->
    <div 
      v-if="store.pushPermission !== 'granted'" 
      class="bg-violet-500/[0.03] dark:bg-violet-900/[0.04] border border-violet-100/80 dark:border-violet-955 rounded-xl p-4 flex flex-col items-start gap-3 animate-fade-in"
    >
      <div class="space-y-1 text-right w-full">
        <h4 class="text-xs font-bold text-violet-655 dark:text-violet-455 uppercase tracking-wider flex items-center gap-1.5 justify-end">
          تفعيل تنبيهات سطح المكتب
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5 text-violet-500 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
        </h4>
        <p class="text-xs text-slate-500 dark:text-slate-450 leading-relaxed">
          ابق على اطلاع دائم بتحديثات المواعيد وتعديلات المهام الجارية عندما تكون خارج واجهة التطبيق.
        </p>
      </div>
      <div class="flex space-x-2 space-x-reverse w-full">
        <button 
          @click="requestPush" 
          class="flex-1 bg-violet-600 hover:bg-violet-750 text-white font-bold py-2 px-3 rounded-lg text-xs transition cursor-pointer min-h-[44px] flex items-center justify-center"
        >
          تفعيل الآن
        </button>
        <button 
          @click="denyPush" 
          class="flex-1 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 font-bold py-2 px-3 rounded-lg text-xs hover:bg-slate-50 dark:hover:bg-slate-950 transition cursor-pointer min-h-[44px] flex items-center justify-center"
        >
          لاحقاً
        </button>
      </div>
    </div>

    <!-- Notification List -->
    <div class="space-y-2.5 max-h-60 overflow-y-auto pr-0.5 scrollbar-hide">
      <div v-if="store.notifications.length === 0" class="text-center py-8 text-xs sm:text-sm text-slate-400 italic">
        علبة التنبيهات فارغة حالياً.
      </div>
      <div 
        v-for="notif in store.notifications" 
        :key="notif.id"
        @click="store.markNotificationRead(notif.id)"
        :class="[
          'p-3 rounded-xl border transition-all duration-300 relative flex items-start space-x-2.5 space-x-reverse cursor-pointer min-h-[44px]',
          notif.isRead 
            ? 'bg-slate-50/50 dark:bg-slate-950/20 border-slate-100 dark:border-slate-850/80 text-slate-500 dark:text-slate-450' 
            : 'bg-violet-500/[0.02] dark:bg-violet-950/20 border-violet-100 dark:border-violet-900/20 text-slate-800 dark:text-slate-205'
        ]"
      >
        <!-- Dynamic Type Icon -->
        <span class="shrink-0 mt-0.5" v-html="getNotificationIcon(notif.title)"></span>
        
        <div class="flex-1 min-w-0 space-y-1">
          <div class="flex items-center justify-between flex-row-reverse">
            <span class="text-xs font-bold truncate pr-2 text-right text-slate-800 dark:text-slate-200">{{ notif.title }}</span>
            <span class="text-[10.5px] text-slate-400 font-mono shrink-0">{{ notif.timestamp }}</span>
          </div>
          <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400 font-medium text-right">
            {{ notif.text }}
          </p>
        </div>
        
        <!-- Blue Unread Dot indicator -->
        <span v-if="!notif.isRead" class="w-1.5 h-1.5 rounded-full bg-violet-600 shrink-0 self-center"></span>
      </div>
    </div>

    <!-- Simulated Email Throttling Digest Panel -->
    <div class="pt-4 border-t border-slate-100 dark:border-slate-855 space-y-3.5">
      <div class="flex items-center justify-between flex-row-reverse">
        <div class="text-right">
          <h4 class="text-xs font-bold text-slate-850 dark:text-slate-400 uppercase tracking-widest">تجميع البريد المجدول</h4>
          <p class="text-[10.5px] text-slate-500 dark:text-slate-400 mt-1">تجميع تحديثات المشروع لتفادي إزعاج البريد المفرط.</p>
        </div>
        <button 
          v-if="store.emailQueue.length > 0"
          @click="store.sendBatchedEmail()" 
          class="bg-violet-50 dark:bg-violet-955 hover:bg-violet-100 dark:hover:bg-violet-900 text-violet-650 dark:text-violet-400 font-bold px-2.5 py-1.5 rounded-lg text-xs flex items-center space-x-1.5 space-x-reverse cursor-pointer transition min-h-[44px] min-w-[44px] justify-center"
        >
          <span>إرسال فوري</span>
          <span class="bg-violet-600 text-white rounded-full w-4.5 h-4.5 flex items-center justify-center text-[10px] font-extrabold">
            {{ store.emailQueue.length }}
          </span>
        </button>
      </div>

      <!-- Pending Queue State -->
      <div v-if="store.emailQueue.length > 0" class="bg-slate-50/50 dark:bg-slate-955/20 rounded-xl p-3 border border-slate-100 dark:border-slate-850/80 space-y-2 text-right">
        <div class="flex justify-between text-[10.5px] font-bold text-amber-600 dark:text-amber-550 flex-row-reverse">
          <span>{{ store.emailQueue.length }} تحديثات معلقة</span>
          <span class="animate-pulse">المؤقت المجمع نشط (15ث)</span>
        </div>
        <div class="space-y-1">
          <div v-for="(item, idx) in store.emailQueue" :key="idx" class="text-xs text-slate-500 dark:text-slate-400 truncate">
            • <b class="text-slate-755 dark:text-slate-350">{{ item.taskTitle }}</b>: {{ item.updateText }}
          </div>
        </div>
      </div>

      <!-- Dispatched digests logs -->
      <div class="space-y-2 text-right">
        <span class="text-xs font-bold text-slate-450 dark:text-slate-400 uppercase tracking-widest block">سجل الإرسال للبريد المجمع</span>
        <div v-if="store.batchedEmails.length === 0" class="text-center py-4 text-xs text-slate-400 italic border border-dashed border-slate-200 dark:border-slate-800 rounded-xl">
          سجل الإرسال فارغ حالياً.
        </div>
        <div 
          v-for="email in store.batchedEmails" 
          :key="email.id" 
          class="p-3 bg-slate-900 text-slate-200 rounded-xl text-xs font-mono space-y-1.5 relative border border-slate-800 shadow-sm text-right"
        >
          <div class="flex justify-between border-b border-slate-800 pb-1 text-slate-500 text-[10px] flex-row-reverse">
            <span class="truncate pr-2">العنوان: {{ email.subject }}</span>
            <span class="shrink-0">{{ email.sentAt }}</span>
          </div>
          <pre class="pt-1.5 whitespace-pre-wrap leading-relaxed text-slate-400 font-sans text-xs text-right">{{ email.body }}</pre>
          <span class="absolute left-2.5 bottom-2 text-[10px] text-emerald-500 bg-emerald-500/10 px-1.5 py-0.5 rounded-full font-bold">
            تم الإرسال
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
