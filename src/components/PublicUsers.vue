<script setup>
import { ref, onMounted, computed } from 'vue'
import { store } from '../store'

const users = ref([])
const loading = ref(true)
const error = ref('')
const searchQuery = ref('')

// Password reset modal/form state
const resettingUser = ref(null)
const newPassword = ref('password123')
const resetLoading = ref(false)
const resetMessage = ref('')
const resetError = ref('')

const fetchUsers = async () => {
  loading.value = true
  error.value = ''
  try {
    const res = await fetch(`${store.apiBase}/public-users`, {
      headers: { 'Accept': 'application/json' }
    })
    
    if (!res.ok) {
      throw new Error(`خطأ من الخادم (${res.status})`)
    }
    
    const data = await res.json()
    users.value = Array.isArray(data) ? data : []
  } catch (e) {
    error.value = 'تعذر جلب بيانات المستخدمين. تأكد من عمل السيرفر والمسار /api/public-users'
  } finally {
    loading.value = false
  }
}

const filteredUsers = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return users.value
  return users.value.filter(u => 
    (u.name && u.name.toLowerCase().includes(q)) ||
    (u.email && u.email.toLowerCase().includes(q)) ||
    (u.role?.name && u.role.name.toLowerCase().includes(q))
  )
})

const openResetModal = (user) => {
  resettingUser.value = user
  newPassword.value = 'password123'
  resetMessage.value = ''
  resetError.value = ''
}

const closeResetModal = () => {
  resettingUser.value = null
  resetMessage.value = ''
  resetError.value = ''
}

const handleResetPassword = async () => {
  if (!resettingUser.value || !newPassword.value.trim()) return
  
  resetLoading.value = true
  resetMessage.value = ''
  resetError.value = ''
  
  try {
    const res = await fetch(`${store.apiBase}/public-reset-password`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        email: resettingUser.value.email,
        password: newPassword.value.trim()
      })
    })
    
    const data = await res.json()
    if (res.ok) {
      resetMessage.value = `تم تعيين كلمة المرور بنجاح إلى: ${newPassword.value}`
    } else {
      resetError.value = data.message || 'فشل تغيير كلمة المرور.'
    }
  } catch (e) {
    resetError.value = 'حدث خطأ في الاتصال بالخادم.'
  } finally {
    resetLoading.value = false
  }
}

const copyToClipboard = (text) => {
  navigator.clipboard?.writeText(text)
  alert('تم النسخ: ' + text)
}

const goToLogin = () => {
  window.location.hash = ''
}

onMounted(() => {
  fetchUsers()
})
</script>

<template>
  <div dir="rtl" class="min-h-screen bg-slate-900 text-slate-100 p-4 sm:p-8 font-sans selection:bg-violet-500 selection:text-white">
    <div class="max-w-5xl mx-auto">
      
      <!-- Header -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
        <div>
          <div class="flex items-center gap-3">
            <span class="text-3xl">👥</span>
            <h1 class="text-2xl sm:text-3xl font-black bg-gradient-to-r from-violet-400 via-fuchsia-300 to-indigo-400 bg-clip-text text-transparent">
              قائمة المستخدمين (Public)
            </h1>
          </div>
          <p class="text-slate-400 text-sm mt-1">
            عرض مباشر لجميع حسابات الأعضاء والمديرين على قاعدة البيانات مع إمكانية تعيين كلمة المرور
          </p>
        </div>

        <div class="flex items-center gap-3">
          <button 
            @click="fetchUsers" 
            :disabled="loading"
            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-sm font-bold border border-slate-700 transition flex items-center gap-2"
          >
            <span :class="{ 'animate-spin': loading }">🔄</span>
            تحديث
          </button>
          
          <button 
            @click="goToLogin" 
            class="px-5 py-2 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 text-white rounded-xl text-sm font-bold shadow-lg shadow-violet-900/30 transition flex items-center gap-2"
          >
            <span>🔐</span>
            الذهاب لتسجيل الدخول
          </button>
        </div>
      </div>

      <!-- Search & Filters -->
      <div class="mt-6 flex items-center gap-4 bg-slate-800/50 p-4 rounded-2xl border border-slate-800">
        <span class="text-xl text-slate-400">🔍</span>
        <input 
          v-model="searchQuery"
          type="text"
          placeholder="ابحث بالاسم أو البريد الإلكتروني أو الدور..."
          class="w-full bg-transparent border-none text-slate-100 placeholder-slate-500 text-sm focus:outline-none"
        />
        <span v-if="searchQuery" @click="searchQuery = ''" class="cursor-pointer text-xs text-slate-400 hover:text-white px-2 py-1 bg-slate-700 rounded-lg">مسح</span>
      </div>

      <!-- Error message -->
      <div v-if="error" class="mt-6 p-4 rounded-2xl bg-rose-950/50 border border-rose-800 text-rose-300 flex items-start gap-3">
        <span class="text-xl">⚠️</span>
        <div class="flex-1">
          <div class="font-bold">تنبيه</div>
          <div class="text-sm mt-0.5">{{ error }}</div>
        </div>
        <button @click="fetchUsers" class="px-3 py-1 bg-rose-900/60 hover:bg-rose-800 rounded-lg text-xs font-bold transition">إعادة المحاولة</button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="mt-12 flex flex-col items-center justify-center gap-4 py-16">
        <div class="w-12 h-12 border-4 border-violet-500/30 border-t-violet-500 rounded-full animate-spin"></div>
        <p class="text-slate-400 text-sm font-medium">جاري استرجاع بيانات الحسابات من الخادم...</p>
      </div>

      <!-- Users Grid/Table -->
      <div v-else-if="filteredUsers.length > 0" class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div 
          v-for="u in filteredUsers" 
          :key="u.id"
          class="bg-slate-800/70 border border-slate-700/60 hover:border-violet-500/50 rounded-2xl p-5 transition-all duration-300 shadow-xl flex flex-col justify-between"
        >
          <div>
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-violet-600 to-fuchsia-600 flex items-center justify-center text-white font-black text-lg shadow-md">
                  {{ (u.name || 'U').charAt(0) }}
                </div>
                <div>
                  <h3 class="font-bold text-slate-100 text-base flex items-center gap-2">
                    {{ u.name }}
                  </h3>
                  <span class="text-xs px-2.5 py-0.5 rounded-full font-bold inline-block mt-1"
                    :class="u.role?.name === 'مدير' || u.role_id === 1 ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-slate-700 text-slate-300'"
                  >
                    👑 {{ u.role?.name || 'عضو' }}
                  </span>
                </div>
              </div>
              <span class="text-xs font-mono text-slate-500">#{{ u.id }}</span>
            </div>

            <!-- Email Box -->
            <div class="mt-4 bg-slate-900/60 p-3 rounded-xl border border-slate-800 flex items-center justify-between gap-2">
              <div class="truncate text-xs font-mono text-slate-300">
                {{ u.email }}
              </div>
              <button 
                @click="copyToClipboard(u.email)"
                title="نسخ البريد الإلكتروني"
                class="p-1.5 hover:bg-slate-800 text-slate-400 hover:text-white rounded-lg transition text-xs"
              >
                📋
              </button>
            </div>
          </div>

          <!-- Actions -->
          <div class="mt-5 pt-4 border-t border-slate-700/50 flex items-center gap-2">
            <button 
              @click="openResetModal(u)"
              class="flex-1 py-2 px-3 bg-violet-600/20 hover:bg-violet-600/40 text-violet-300 hover:text-violet-200 border border-violet-500/30 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5"
            >
              <span>🔑</span>
              تعيين كلمة المرور
            </button>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else class="mt-12 text-center py-16 bg-slate-800/30 rounded-3xl border border-slate-800">
        <div class="text-4xl mb-3">🔍</div>
        <h3 class="text-lg font-bold text-slate-300">لم يتم العثور على مستخدمين</h3>
        <p class="text-slate-500 text-sm mt-1">تأكد من وجود مستخدمين مسجلين في قاعدة البيانات أو جرب البحث بكلمة أخرى.</p>
      </div>

    </div>

    <!-- Password Reset Modal -->
    <div 
      v-if="resettingUser"
      class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
          <h3 class="text-lg font-bold text-slate-100 flex items-center gap-2">
            <span>🔑</span>
            تعيين كلمة مرور جديدة
          </h3>
          <button @click="closeResetModal" class="text-slate-400 hover:text-white text-xl">✕</button>
        </div>

        <div class="mt-4 space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-400 mb-1">المستخدم:</label>
            <div class="text-sm font-bold text-slate-200 bg-slate-800/70 p-3 rounded-xl border border-slate-800">
              {{ resettingUser.name }} ({{ resettingUser.email }})
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-400 mb-1">كلمة المرور الجديدة:</label>
            <input 
              v-model="newPassword"
              type="text"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-100 font-mono focus:border-violet-500 focus:outline-none"
              placeholder="أدخل كلمة المرور الجديدة"
            />
          </div>

          <div v-if="resetMessage" class="p-3 bg-emerald-950/50 border border-emerald-800 text-emerald-300 rounded-xl text-xs font-bold">
            ✓ {{ resetMessage }}
          </div>

          <div v-if="resetError" class="p-3 bg-rose-950/50 border border-rose-800 text-rose-300 rounded-xl text-xs font-bold">
            ⚠️ {{ resetError }}
          </div>

          <div class="flex items-center gap-3 pt-2">
            <button 
              @click="handleResetPassword"
              :disabled="resetLoading"
              class="flex-1 py-2.5 bg-violet-600 hover:bg-violet-500 disabled:opacity-50 text-white rounded-xl text-sm font-bold transition flex items-center justify-center gap-2"
            >
              <span v-if="resetLoading" class="animate-spin">🔄</span>
              <span>حفظ كلمة المرور</span>
            </button>
            <button 
              @click="closeResetModal"
              class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-sm font-bold transition"
            >
              إلغاء
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>
