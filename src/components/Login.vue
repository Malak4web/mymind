<script setup>
import { store } from '../store'
import { ref, onMounted, onUnmounted, computed } from 'vue'

const email = ref('')
const password = ref('')
const errorMessage = ref('')
const loading = ref(false)
const isLoginModalOpen = ref(false)

// Dynamic landing demo states
const demoTasks = ref([
  { id: 1, title: 'تصميم واجهة المستخدم الجديدة', status: 'بانتظار البدء', color: 'border-r-amber-500' },
  { id: 2, title: 'ربط قاعدة بيانات MySQL المحلية', status: 'قيد العمل', color: 'border-r-violet-500' },
  { id: 3, title: 'بناء محرك المستندات والملاحظات', status: 'مكتمل', color: 'border-r-emerald-500' }
])

const activeFeature = ref(0)
const featuresList = [
  {
    title: 'لوحات كانبان التفاعلية',
    desc: 'تنظيم مهام فريقك وسحبها وإفلاتها بسلاسة بين الحالات مع إحصائيات فورية ودقة عالية.',
    icon: '📊'
  },
  {
    title: 'نظام إدارة ملفات متكامل',
    desc: 'تنسيق مجلدات ومستندات ذكية ونوتس لكتابة ومشاركة المعرفة والتعليمات والملاحظات الفنية.',
    icon: '📁'
  },
  {
    title: 'وضع التركيز (Zen Mode)',
    desc: 'بيئة عمل هادئة وخالية من المشتتات تساعدك على إنجاز مهامك الحرجة بصفاء ذهني.',
    icon: '🧘'
  },
  {
    title: 'أنظمة تحفيزية واحتفالات',
    desc: 'انفجارات احتفالية تفاعلية مليئة بالحماس والموسيقى الملهمة بمجرد إنجاز المهام لشحن طاقتك.',
    icon: '🎉'
  }
]

// Interactive drag-like simulator for landing demo
const moveDemoTask = (task) => {
  if (task.status === 'بانتظار البدء') {
    task.status = 'قيد العمل'
    task.color = 'border-r-violet-500'
  } else if (task.status === 'قيد العمل') {
    task.status = 'مكتمل'
    task.color = 'border-r-emerald-500'
    // Trigger localized fanfare celebration sound
    playMiniSound()
  } else {
    task.status = 'بانتظار البدء'
    task.color = 'border-r-amber-500'
  }
}

// Mini sound synthesiser for interactive landing demo
const playMiniSound = () => {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)()
    
    // Play a happy rising arpeggio
    const playTone = (freq, time, dur) => {
      const osc = ctx.createOscillator()
      const gain = ctx.createGain()
      osc.type = 'triangle'
      osc.frequency.setValueAtTime(freq, time)
      gain.gain.setValueAtTime(0.1, time)
      gain.gain.exponentialRampToValueAtTime(0.001, time + dur)
      osc.connect(gain)
      gain.connect(ctx.destination)
      osc.start(time)
      osc.stop(time + dur)
    }

    const now = ctx.currentTime
    playTone(523.25, now, 0.15) // C5
    playTone(659.25, now + 0.1, 0.15) // E5
    playTone(783.99, now + 0.2, 0.3) // G5

    setTimeout(() => {
      ctx.close().catch(() => {})
    }, 600)
  } catch (e) {
    console.log(e)
  }
}

const handleLogin = async () => {
  if (!email.value.trim() || !password.value.trim()) {
    errorMessage.value = 'يرجى إدخال البريد الإلكتروني وكلمة المرور.'
    return
  }

  errorMessage.value = ''
  loading.value = true

  try {
    const res = await fetch(`${store.apiBase}/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: email.value.trim(),
        password: password.value.trim()
      })
    })

    const data = await res.json()

    if (res.ok) {
      localStorage.setItem('mymind_token', data.token)
      store.token = data.token
      store.currentUser = data.user
      store.isAuthenticated = true
      await store.init()
    } else {
      errorMessage.value = data.errors?.email?.[0] || data.message || 'فشل تسجيل الدخول. يرجى التحقق من بياناتك.'
    }
  } catch (e) {
    errorMessage.value = 'فشل الاتصال بالخادم. يرجى التأكد من تشغيل السيرفر.'
  } finally {
    loading.value = false
  }
}

// Auto-rotate feature showcase
let featureInterval = null
onMounted(() => {
  if (typeof process === 'undefined' || process.env?.NODE_ENV !== 'test') {
    featureInterval = setInterval(() => {
      activeFeature.value = (activeFeature.value + 1) % featuresList.length
    }, 4000)
  }
})

onUnmounted(() => {
  if (featureInterval) {
    clearInterval(featureInterval)
    featureInterval = null
  }
})

const scrollToSection = (id) => {
  const el = document.getElementById(id)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 font-sans selection:bg-violet-500/10 selection:text-violet-600 transition-colors duration-500 relative overflow-hidden" dir="rtl">
    
    <!-- Giant Glowing Ambient Beams -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
      <div class="absolute -top-40 right-[15%] w-[600px] h-[600px] rounded-full bg-violet-600/10 dark:bg-violet-500/[0.05] blur-[150px] animate-pulse-slow"></div>
      <div class="absolute top-[60%] left-[-100px] w-[500px] h-[500px] rounded-full bg-indigo-600/10 dark:bg-indigo-500/[0.05] blur-[120px]"></div>
    </div>

    <!-- Sticky Glassmorphic Header -->
    <header class="sticky top-0 z-30 bg-white/70 dark:bg-slate-950/70 backdrop-blur-md border-b border-slate-200/50 dark:border-slate-800/80 transition-all duration-300">
      <div class="max-w-7xl mx-auto px-6 md:px-12 py-4 flex items-center justify-between">
        
        <!-- Logo -->
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-violet-600 to-indigo-600 flex items-center justify-center shadow-md shadow-violet-500/10">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
          </div>
          <span class="text-lg font-extrabold text-slate-800 dark:text-slate-50 flex items-center gap-1">
            MyMind
            <span class="px-1.5 py-0.5 rounded bg-gradient-to-r from-violet-600 to-indigo-600 text-[10px] font-extrabold text-white">برو</span>
          </span>
        </div>

        <!-- Desktop Nav Links -->
        <nav class="hidden md:flex items-center gap-8">
          <button @click="scrollToSection('hero')" class="text-xs font-bold text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 transition cursor-pointer">الرئيسية</button>
          <button @click="scrollToSection('features')" class="text-xs font-bold text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 transition cursor-pointer">المميزات</button>
          <button @click="scrollToSection('tech')" class="text-xs font-bold text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 transition cursor-pointer">التكنولوجيا</button>
          <button @click="scrollToSection('pricing')" class="text-xs font-bold text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 transition cursor-pointer">الأسعار</button>
        </nav>

        <!-- Right Side CTA & Theme -->
        <div class="flex items-center gap-4">
          <button 
            @click="store.toggleTheme()" 
            class="p-2 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400 rounded-xl transition cursor-pointer hover:shadow-sm"
            title="تبديل مظهر النظام" aria-label="تبديل مظهر النظام"
          >
            <svg v-if="store.theme === 'light'" xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.828 9.9l-.707-.707M6.343 6.343l-.707-.707M14.25 12a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
            </svg>
          </button>
          
          <button 
            @click="isLoginModalOpen = true"
            class="bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 text-white font-extrabold text-xs px-4 py-2 rounded-xl transition cursor-pointer shadow-md shadow-violet-500/10 btn-touch-active hover:-translate-y-0.5 hover:shadow-glass-glow"
          >
            تسجيل الدخول
          </button>
        </div>

      </div>
    </header>

    <!-- Main Content Area -->
    <main class="relative z-10 max-w-7xl mx-auto px-6 md:px-12 py-12 md:py-24 space-y-32">
      
      <!-- Hero Section -->
      <section id="hero" class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
        
        <!-- Left Column: Copywriting -->
        <div class="lg:col-span-6 space-y-6 text-right">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-500/10 text-violet-600 dark:text-violet-400 text-xs font-bold border border-violet-500/10 animate-pulse">
            <span>✨ نظام التعاون وإدارة المشاريع الأكثر بساطة ومرونة</span>
          </div>
          
          <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold text-slate-800 dark:text-slate-50 leading-tight">
            نظّم أفكارك، أدر مهامك، وحقّق أهدافك مع <span class="bg-gradient-to-r from-violet-600 to-indigo-600 bg-clip-text text-transparent">عقلي برو</span>
          </h1>
          
          <p class="text-base text-slate-500 dark:text-slate-400 leading-relaxed font-medium max-w-xl">
            نظام متكامل يدمج لوحات العمل المرنة، مدير الملفات والوثائق المشترك، ووضع التركيز الهادئ، بجماليات تفاعلية غامرة تُشحن بالأنيميشن المحفز.
          </p>

          <!-- CTAs -->
          <div class="flex items-center gap-4 flex-wrap pt-2">
            <button 
              @click="isLoginModalOpen = true"
              class="bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 text-white font-extrabold text-sm px-7 py-3.5 rounded-2xl transition cursor-pointer shadow-lg shadow-violet-500/20 transform hover:-translate-y-0.5 hover:shadow-glass-glow duration-200 btn-touch-active"
            >
              ابدأ الاستخدام مجاناً
            </button>
            <button 
              @click="scrollToSection('features')"
              class="border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-900 text-slate-700 dark:text-slate-300 font-bold text-sm px-7 py-3.5 rounded-2xl transition cursor-pointer"
            >
              شاهد الميزات
            </button>
          </div>

          <!-- Quick statistics -->
          <div class="grid grid-cols-3 gap-6 border-t border-slate-200/60 dark:border-slate-800 pt-8 mt-4">
            <div>
              <span class="block text-2xl font-extrabold text-slate-800 dark:text-slate-50">99.9%</span>
              <span class="text-[10px] text-slate-400 font-bold block mt-1">زمن العمل النشط</span>
            </div>
            <div>
              <span class="block text-2xl font-extrabold text-slate-800 dark:text-slate-50">MySQL</span>
              <span class="text-[10px] text-slate-400 font-bold block mt-1">قاعدة بيانات محلية</span>
            </div>
            <div>
              <span class="block text-2xl font-extrabold text-slate-800 dark:text-slate-50">Zero</span>
              <span class="text-[10px] text-slate-400 font-bold block mt-1">تكلفة الإعداد الأولي</span>
            </div>
          </div>
        </div>

        <!-- Right Column: Premium Interactive Mockup Device -->
        <div class="lg:col-span-6 flex justify-center relative">
          <!-- Ambient glowing sphere behind device -->
          <div class="absolute -inset-4 rounded-[40px] bg-gradient-to-tr from-violet-600 to-indigo-600 opacity-20 blur-2xl z-0"></div>
          
          <!-- Mock Screen Container -->
          <div class="relative w-full max-w-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl p-6 space-y-6 z-10 transform hover:scale-[1.02] transition-transform duration-300 select-none">
            
            <!-- Mock Header Bar -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800 flex-row-reverse">
              <span class="text-xs font-extrabold text-slate-800 dark:text-slate-200">اللوحة التجريبية للكانبان</span>
              <div class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
              </div>
            </div>

            <p class="text-[10px] text-slate-400 font-bold text-center mt-1">انقر على البطاقات لتشاهد سير العمل والاحتفال!</p>

            <!-- Mock Kanban Grid -->
            <div class="grid grid-cols-3 gap-2.5">
              <!-- Columns -->
              <div v-for="col in ['بانتظار البدء', 'قيد العمل', 'مكتمل']" :key="col" class="space-y-2.5 bg-slate-50/50 dark:bg-slate-950/30 border border-slate-200/50 dark:border-slate-800 p-2.5 rounded-xl min-h-[220px]">
                <span class="block text-[10px] font-extrabold text-slate-400 text-center tracking-wide">{{ col }}</span>
                
                <!-- Cards -->
                <div 
                  v-for="task in demoTasks.filter(t => t.status === col)" 
                  :key="task.id"
                  @click="moveDemoTask(task)"
                  :class="[
                    'p-2.5 bg-white dark:bg-slate-900 border-r-4 border-y border-l border-slate-200/70 dark:border-slate-800 rounded-lg text-right text-[10px] font-extrabold shadow-sm transition-all duration-300 cursor-pointer hover:shadow hover:scale-[1.03] active:scale-[0.98]',
                    task.color
                  ]"
                >
                  <p class="text-slate-800 dark:text-slate-200 leading-snug">{{ task.title }}</p>
                  <span class="text-[10px] text-violet-500 font-bold block mt-1.5">انقر للنقل ➔</span>
                </div>
              </div>
            </div>
          </div>
        </div>

      </section>

      <!-- Features Section -->
      <section id="features" class="space-y-12">
        <div class="text-center space-y-2 max-w-2xl mx-auto">
          <h2 class="text-3xl font-extrabold text-slate-800 dark:text-slate-100">صُمم لرفع كفاءة تفكيرك وعملك</h2>
          <p class="text-sm text-slate-500 dark:text-slate-400 font-semibold leading-relaxed">
            مجموعة متناغمة من الميزات المينيماليست المخصصة لحفظ ملفاتك وتخطيط مهامك اليومية دون فوضى.
          </p>
        </div>

        <!-- Bento Grid Feature Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div 
            v-for="(f, idx) in featuresList" 
            :key="idx"
            :class="[
              activeFeature === idx ? 'border-violet-500 dark:border-violet-700 shadow-lg shadow-violet-500/[0.03] scale-[1.02]' : 'border-slate-200 dark:border-slate-800',
              'p-6 bg-white dark:bg-slate-900 border rounded-3xl transition-all duration-300 text-right space-y-4 hover:border-slate-300 dark:hover:border-slate-700'
            ]"
          >
            <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-lg">
              {{ f.icon }}
            </div>
            <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-100">{{ f.title }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed font-semibold">{{ f.desc }}</p>
          </div>
        </div>
      </section>

      <!-- Technology & Database Section -->
      <section id="tech" class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
        
        <!-- Left: Image / Schema Representation -->
        <div class="lg:col-span-6 space-y-4 text-right">
          <!-- Glassmorphic Tech card -->
          <div class="bg-gradient-to-tr from-slate-50/60 to-white/60 dark:from-slate-950/20 dark:to-slate-900/20 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 md:p-8 space-y-4">
            <h3 class="text-base font-extrabold text-slate-900 dark:text-slate-100">هندسة النظام المحلية المستقرة</h3>
            
            <div class="space-y-3 text-xs text-slate-500 dark:text-slate-400 font-semibold leading-relaxed">
              <div class="flex items-center gap-2 flex-row-reverse justify-end">
                <span class="w-1.5 h-1.5 rounded-full bg-violet-600"></span>
                <span><b>الواجهة الأمامية:</b> تطبيق صفحة واحدة (SPA) فائق السرعة مبني باستخدام Vue 3 و Tailwind CSS.</span>
              </div>
              <div class="flex items-center gap-2 flex-row-reverse justify-end">
                <span class="w-1.5 h-1.5 rounded-full bg-violet-600"></span>
                <span><b>الخادم الخلفي:</b> محرك RESTful API قوي يعمل بنظام Laravel 11.</span>
              </div>
              <div class="flex items-center gap-2 flex-row-reverse justify-end">
                <span class="w-1.5 h-1.5 rounded-full bg-violet-600"></span>
                <span><b>تخزين البيانات:</b> نظام MySQL لإدارة البيانات بشكل علائقي آمن ومستمر.</span>
              </div>
            </div>

            <!-- Visual Flowchart Mock -->
            <div class="flex items-center justify-between border-t border-slate-200/60 dark:border-slate-800 pt-4 mt-2 font-mono text-[10px] text-slate-400">
              <div class="bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700">Vue 3 Frontend</div>
              <div class="text-violet-500">➔</div>
              <div class="bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700">Laravel API</div>
              <div class="text-violet-500">➔</div>
              <div class="bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700">MySQL Local DB</div>
            </div>
          </div>
        </div>

        <!-- Right: Text Description -->
        <div class="lg:col-span-6 space-y-6 text-right">
          <h2 class="text-3xl font-extrabold text-slate-800 dark:text-slate-100">أداء فوري واستجابة فورية</h2>
          <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed font-semibold">
            لا داعي للقلق بشأن اتصال الإنترنت البطيء. نظراً لأن النظام يعمل محلياً بالكامل على خادم XAMPP الشخصي الخاص بك، فإن العمليات تتم في أجزاء من الملي ثانية.
          </p>
          <div class="p-4 bg-emerald-500/[0.03] border border-emerald-500/10 rounded-2xl flex items-start gap-3 flex-row-reverse text-right">
            <span class="text-emerald-500 text-lg">✓</span>
            <div class="space-y-1">
              <h5 class="text-xs font-extrabold text-slate-900 dark:text-slate-100">دعم كامل للغة العربية</h5>
              <p class="text-[11px] text-slate-500">الواجهات مصممة من الصفر باتجاه RTL ومتوافقة مع الخط العربي الجميل Tajawal.</p>
            </div>
          </div>
        </div>

      </section>

      <!-- Pricing Plans Section -->
      <section id="pricing" class="space-y-12">
        <div class="text-center space-y-2 max-w-2xl mx-auto">
          <h2 class="text-3xl font-extrabold text-slate-900 dark:text-slate-100">خطط تناسب تطلعاتك</h2>
          <p class="text-sm text-slate-500 dark:text-slate-400 font-semibold">ابدأ خطتك الأولى فوراً ودع عقلي برو يتكفل بالباقي.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-3xl mx-auto">
          
          <!-- Plan 1: Free -->
          <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 space-y-6 text-right relative flex flex-col justify-between">
            <div class="space-y-4">
              <span class="text-xs font-bold text-slate-400 block">نسخة البداية</span>
              <h3 class="text-xl font-extrabold text-slate-800 dark:text-slate-100">عقلي مجاني</h3>
              <p class="text-xs text-slate-500 leading-relaxed font-semibold">تخطيط مبدئي للحد الأدنى من المهام والمشاريع دون مميزات برو المتقدمة.</p>
              
              <div class="text-3xl font-extrabold text-slate-800 dark:text-slate-50">
                0$ <span class="text-xs font-semibold text-slate-400">/ للأبد</span>
              </div>

              <div class="border-t border-slate-100 dark:border-slate-800 pt-4 space-y-2.5 text-xs text-slate-500 dark:text-slate-400">
                <div>✓ إدارة مشروع واحد فقط</div>
                <div>✓ لوحة كانبان أساسية</div>
                <div>✗ قوالب المشاريع التلقائية</div>
                <div>✗ وضع التركيز والأنيميشن التفاعلي</div>
              </div>
            </div>
            <button @click="isLoginModalOpen = true" class="w-full mt-6 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold py-2.5 rounded-xl text-xs transition cursor-pointer">ابدأ الآن</button>
          </div>

          <!-- Plan 2: Pro -->
          <div class="bg-white dark:bg-slate-900 border-2 border-violet-500 dark:border-violet-600 rounded-3xl p-8 space-y-6 text-right relative flex flex-col justify-between shadow-xl">
            <!-- Badge -->
            <div class="absolute -top-3.5 right-6 bg-violet-600 text-white text-[10px] font-extrabold px-3 py-1 rounded-full">الخيار الأكثر تميزاً</div>
            
            <div class="space-y-4">
              <span class="text-xs font-bold text-violet-600 dark:text-violet-400 block">النسخة الاحترافية</span>
              <h3 class="text-xl font-extrabold text-slate-800 dark:text-slate-100">عقلي برو</h3>
              <p class="text-xs text-slate-500 leading-relaxed font-semibold">كافة الخصائص المتقدمة والمميزات التفاعلية لبناء القوالب والملفات بلا حدود.</p>
              
              <div class="text-3xl font-extrabold text-slate-800 dark:text-slate-50">
                19$ <span class="text-xs font-semibold text-slate-400">/ دفع لمرة واحدة</span>
              </div>

              <div class="border-t border-slate-100 dark:border-slate-800 pt-4 space-y-2.5 text-xs text-slate-500 dark:text-slate-400">
                <div>✓ مشاريع ولوحات مهام غير محدودة</div>
                <div>✓ قوالب مهام ومشاريع ديناميكية مخصصة</div>
                <div>✓ نظام إدارة الملفات والمجلدات والنوتس مدمج</div>
                <div>✓ وضع التركيز (Zen Mode) بكامل عرض الشاشة</div>
                <div>✓ أنيميشن احتفالي محفز وأصوات تشجيعية</div>
              </div>
            </div>
            <button @click="isLoginModalOpen = true" class="w-full mt-6 bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 text-white font-extrabold py-2.5 rounded-xl text-xs transition cursor-pointer shadow-md shadow-violet-500/10 btn-touch-active hover:-translate-y-0.5 hover:shadow-glass-glow">اشترك الآن</button>
          </div>

        </div>
      </section>

    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-200/60 dark:border-slate-800/80 bg-white/30 dark:bg-slate-950/10 py-12 relative z-10">
      <div class="max-w-7xl mx-auto px-6 md:px-12 flex flex-col md:flex-row items-center justify-between gap-6 text-center">
        <!-- Logo -->
        <span class="text-sm font-extrabold text-slate-500 flex items-center gap-1.5">
          MyMind
          <span class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-800 text-[10px] font-extrabold text-slate-500">برو</span>
        </span>
        
        <!-- Copyright -->
        <p class="text-xs text-slate-400 font-semibold">© 2026 عقلي برو. جميع الحقوق محفوظة.</p>
        
        <!-- Links -->
        <div class="flex items-center gap-6">
          <button @click="isLoginModalOpen = true" class="text-xs font-bold text-slate-400 hover:text-slate-600 transition">دخول النظام</button>
        </div>
      </div>
    </footer>

    <!-- Glassmorphic Login Modal Overlay -->
    <Transition name="fade">
      <div v-if="isLoginModalOpen" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4" dir="rtl">
        <!-- Backdrop with Blur -->
        <div @click="isLoginModalOpen = false" class="absolute inset-0 bg-slate-900/60 dark:bg-slate-950/70 backdrop-blur-sm transition-opacity duration-300"></div>
        
        <!-- Modal Card -->
        <div class="relative w-full max-w-md bg-white dark:bg-slate-900 border-t sm:border border-slate-200 dark:border-slate-800 rounded-t-3xl sm:rounded-3xl p-6 sm:p-8 max-h-[90vh] overflow-y-auto shadow-2xl space-y-6 z-10 transition-all duration-300 transform scale-100">
          <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto my-2.5 shrink-0 sm:hidden cursor-grab"></div>
          
          <!-- Close Button -->
          <button aria-label="إغلاق نافذة تسجيل الدخول" 
            @click="isLoginModalOpen = false" 
            class="absolute top-4 left-4 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 p-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <!-- Logo & Header -->
          <div class="flex flex-col items-center text-center space-y-2.5">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-violet-600 to-indigo-600 flex items-center justify-center shadow-lg shadow-violet-500/15">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            </div>
            <div>
              <h4 class="text-xl font-extrabold text-slate-800 dark:text-slate-50 flex items-center justify-center gap-1.5">
                MyMind
                <span class="px-1.5 py-0.5 rounded-lg bg-gradient-to-r from-violet-600 to-indigo-600 text-[10px] font-extrabold text-white">برو</span>
              </h4>
              <p class="text-xs text-slate-400 font-semibold mt-1">سجل دخولك لتتمكن من فتح لوحات عملك وأوراقك</p>
            </div>
          </div>

          <!-- Login Form -->
          <form @submit.prevent="handleLogin" class="space-y-4">
            <div>
              <label class="block text-xs font-semibold text-slate-500 mb-1.5">البريد الإلكتروني</label>
              <input 
                v-model="email" 
                type="email" 
                required
                placeholder="name@domain.com"
                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:border-violet-500 transition"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-500 mb-1.5">كلمة المرور</label>
              <input 
                v-model="password" 
                type="password" 
                required
                placeholder="******"
                class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:border-violet-500 transition"
              />
            </div>

            <div v-if="errorMessage" class="text-xs text-rose-500 font-semibold leading-relaxed">
              {{ errorMessage }}
            </div>

            <button 
              type="submit" 
              :disabled="loading"
              class="w-full bg-gradient-to-tr from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 disabled:opacity-40 text-white font-extrabold py-3 rounded-xl text-sm transition cursor-pointer shadow-md shadow-violet-500/10 flex items-center justify-center gap-1.5 btn-touch-active hover:-translate-y-0.5 hover:shadow-glass-glow"
            >
              <span v-if="loading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <span>تسجيل الدخول للنظام</span>
            </button>
          </form>

          <!-- Public Users Quick Access -->
          <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-800 text-center">
            <a 
              href="#public-users" 
              class="inline-flex items-center gap-2 text-xs font-bold text-violet-600 dark:text-violet-400 hover:underline"
            >
              <span>👥</span>
              <span>عرض قائمة الحسابات والمديرين (Public Users)</span>
            </a>
          </div>

        </div>
      </div>
    </Transition>

  </div>
</template>

<style scoped>
@keyframes pulse-slow {
  0%, 100% { opacity: 0.15; transform: scale(1); }
  50% { opacity: 0.3; transform: scale(1.08); }
}
.animate-pulse-slow {
  animation: pulse-slow 8s infinite ease-in-out;
}

/* Modal transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
