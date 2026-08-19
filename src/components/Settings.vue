<script setup>
import { store } from '../store'
import { ref, onMounted, computed, watch, onUnmounted } from 'vue'

const users = ref([])
const roles = ref([])
const permissions = ref([])
const loadingUsers = ref(false)

// Active Tab State
const activeTab = ref('users') // 'users' | 'proj-templates' | 'task-templates'

// User Management states
const isEditing = ref(false)
const userIdToEdit = ref(null)
const userName = ref('')
const userEmail = ref('')
const userPassword = ref('')
const userRoleId = ref('')
const formError = ref('')
const formSuccess = ref('')

// Project Template CRUD states
const editingProjTemplateId = ref(null) 
const newProjTemplateName = ref('')
const newProjTemplateStatuses = ref('بانتظار البدء, قيد العمل, تحت المراجعة, مكتمل')
const newProjTemplateTaskIds = ref([]) 
const projTemplateError = ref('')
const projTemplateSuccess = ref('')

// Task Template CRUD states (Fully Dynamic Builder)
const editingTaskTemplateId = ref(null) 
const newTaskTemplateName = ref('')
const newTaskTemplateFields = ref([]) // array of { name, type, value, width }

const newTaskTemplateTitleVal = ref('')
const taskTemplateError = ref('')
const taskTemplateSuccess = ref('')

// Drag and Drop ordering for template fields list
const dragSrcIndex = ref(null)

const handleDragStart = (idx) => {
  dragSrcIndex.value = idx
}

const handleDrop = (targetIdx) => {
  if (dragSrcIndex.value === null || dragSrcIndex.value === targetIdx) return
  const items = [...newTaskTemplateFields.value]
  const [movedItem] = items.splice(dragSrcIndex.value, 1)
  items.splice(targetIdx, 0, movedItem)
  newTaskTemplateFields.value = items
}

const handleDragEnd = () => {
  dragSrcIndex.value = null
}

// Dynamic Field Form builder temporary states
const tempFieldType = ref('title')
const tempFieldName = ref('عنوان المهمة')
const tempFieldValue = ref('')
const tempFieldWidth = ref('full') // 'full' (100%) or 'half' (50%)

// Auto-fill field name based on selected type
watch(tempFieldType, (newType) => {
  if (newType === 'title') tempFieldName.value = 'عنوان المهمة'
  else if (newType === 'description') tempFieldName.value = 'وصف المهمة'
  else if (newType === 'status') tempFieldName.value = 'حالة المهمة'
  else if (newType === 'priority') {
    tempFieldName.value = 'الأولوية'
    tempFieldValue.value = 'متوسط'
  }
  else if (newType === 'date_start_offset') {
    tempFieldName.value = 'تاريخ البدء (إزاحة باليوم)'
    tempFieldValue.value = '0'
  }
  else if (newType === 'date_due_offset') {
    tempFieldName.value = 'تاريخ الاستحقاق (إزاحة باليوم)'
    tempFieldValue.value = '5'
  }
  else if (newType === 'attachment') tempFieldName.value = 'ملف مرفق تلقائي'
  else if (newType === 'text') tempFieldName.value = 'حقل نص مخصص'
  else if (newType === 'number') tempFieldName.value = 'حقل رقم مخصص'
  else if (newType === 'link') tempFieldName.value = 'حقل رابط مخصص'
})

const addFieldToTaskTemplate = () => {
  if (!tempFieldName.value.trim()) return
  newTaskTemplateFields.value.push({
    name: tempFieldName.value.trim(),
    type: tempFieldType.value,
    value: tempFieldValue.value !== null ? String(tempFieldValue.value).trim() : '',
    width: tempFieldWidth.value
  })
  // reset temporary field value
  tempFieldValue.value = ''
  tempFieldWidth.value = 'full'
}

const removeFieldFromTaskTemplate = (idx) => {
  newTaskTemplateFields.value.splice(idx, 1)
}

const fetchUsersAndRoles = async () => {
  if (!store.hasPermission('manage-users')) return

  loadingUsers.value = true
  try {
    const usersRes = await fetch(`${store.apiBase}/users`, {
      headers: { 'Authorization': `Bearer ${store.token}` }
    })
    if (usersRes.ok) {
      users.value = await usersRes.json()
    }

    const rolesRes = await fetch(`${store.apiBase}/roles`, {
      headers: { 'Authorization': `Bearer ${store.token}` }
    })
    if (rolesRes.ok) {
      roles.value = await rolesRes.json()
    }

    const permissionsRes = await fetch(`${store.apiBase}/permissions`, {
      headers: { 'Authorization': `Bearer ${store.token}` }
    })
    if (permissionsRes.ok) {
      permissions.value = await permissionsRes.json()
    }
  } catch (e) {
    console.error("فشل تحميل إعدادات المستخدمين والأدوار", e)
  } finally {
    loadingUsers.value = false
  }
}

// Permalink Router Hash Syncing
const updateHashFromTab = (tab) => {
  let hash = '#settings-users'
  if (tab === 'users') hash = '#settings-users'
  if (tab === 'proj-templates') hash = '#settings-proj-templates'
  if (tab === 'task-templates') hash = '#settings-task-templates'
  if (window.location.hash !== hash) {
    window.location.hash = hash
  }
}

watch(activeTab, (newTab) => {
  updateHashFromTab(newTab)
})

const handleHashChange = () => {
  const hash = window.location.hash
  if (hash.startsWith('#settings-')) {
    store.activeView = 'settings'
    const sub = hash.replace('#settings-', '')
    if (sub === 'users' || sub === 'db') activeTab.value = 'users'
    else if (sub === 'proj-templates') activeTab.value = 'proj-templates'
    else if (sub === 'task-templates') activeTab.value = 'task-templates'
  }
}

onMounted(() => {
  window.addEventListener('hashchange', handleHashChange)
  handleHashChange()
  fetchUsersAndRoles()
})

onUnmounted(() => {
  window.removeEventListener('hashchange', handleHashChange)
})

const handleSaveUser = async () => {
  formError.value = ''
  formSuccess.value = ''

  if (!userName.value.trim() || !userEmail.value.trim() || !userRoleId.value) {
    formError.value = 'يرجى ملء جميع الحقول المطلوبة.'
    return
  }

  const endpoint = isEditing.value 
    ? `${store.apiBase}/users/${userIdToEdit.value}` 
    : `${store.apiBase}/users`
  
  const method = isEditing.value ? 'PUT' : 'POST'

  const bodyData = {
    name: userName.value.trim(),
    email: userEmail.value.trim(),
    role_id: userRoleId.value
  }

  if (!isEditing.value) {
    if (!userPassword.value.trim()) {
      formError.value = 'كلمة المرور مطلوبة لإنشاء مستخدم جديد.'
      return
    }
    bodyData.password = userPassword.value.trim()
  } else if (userPassword.value.trim()) {
    bodyData.password = userPassword.value.trim()
  }

  try {
    const res = await fetch(endpoint, {
      method,
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${store.token}`
      },
      body: JSON.stringify(bodyData)
    })

    const data = await res.json()

    if (res.ok) {
      formSuccess.value = isEditing.value 
        ? 'تم تحديث بيانات المستخدم بنجاح.' 
        : 'تم إنشاء المستخدم الجديد بنجاح.'
      resetForm()
      fetchUsersAndRoles()
    } else {
      formError.value = data.message || 'فشل حفظ بيانات المستخدم.'
    }
  } catch (e) {
    formError.value = 'حدث خطأ في الاتصال بالخادم.'
  }
}

const editUser = (user) => {
  isEditing.value = true
  userIdToEdit.value = user.id
  userName.value = user.name
  userEmail.value = user.email
  userRoleId.value = user.role_id
  userPassword.value = ''
  formError.value = ''
  formSuccess.value = ''
}

const deleteUser = async (user) => {
  if (user.id === store.currentUser.id) {
    alert("لا يمكنك حذف حسابك الشخصي أثناء تسجيل الدخول.")
    return
  }

  if (!confirm(`هل أنت متأكد من حذف المستخدم "${user.name}" نهائياً؟`)) return

  try {
    const res = await fetch(`${store.apiBase}/users/${user.id}`, {
      method: 'DELETE',
      headers: { 'Authorization': `Bearer ${store.token}` }
    })

    if (res.ok) {
      fetchUsersAndRoles()
    } else {
      const data = await res.json()
      alert(data.error || 'فشل حذف المستخدم.')
    }
  } catch (e) {
    alert('حدث خطأ في الاتصال بالخادم.')
  }
}

const handleImpersonate = async (user) => {
  if (user.id === store.currentUser?.id) return
  if (confirm(`هل ترغب في تسجيل الدخول بصفة "${user.name}"؟`)) {
    const success = await store.impersonateUser(user.id)
    if (success) {
      window.location.hash = '#project-' + (store.activeProjectId || '')
    }
  }
}

const resetForm = () => {
  isEditing.value = false
  userIdToEdit.value = null
  userName.value = ''
  userEmail.value = ''
  userPassword.value = ''
  userRoleId.value = ''
}

// Project Template CRUD
const startEditProjTemplate = (tpl) => {
  editingProjTemplateId.value = tpl.id
  newProjTemplateName.value = tpl.name
  newProjTemplateStatuses.value = tpl.statuses?.join(', ') || ''
  newProjTemplateTaskIds.value = tpl.task_template_ids || []
  projTemplateError.value = ''
  projTemplateSuccess.value = ''
}

const resetProjTemplateForm = () => {
  editingProjTemplateId.value = null
  newProjTemplateName.value = ''
  newProjTemplateStatuses.value = 'بانتظار البدء, قيد العمل, تحت المراجعة, مكتمل'
  newProjTemplateTaskIds.value = []
  projTemplateError.value = ''
  projTemplateSuccess.value = ''
}

const handleSaveProjTemplate = async () => {
  projTemplateError.value = ''
  projTemplateSuccess.value = ''

  if (!newProjTemplateName.value.trim()) {
    projTemplateError.value = 'اسم قالب المشروع مطلوب.'
    return
  }

  const statusList = newProjTemplateStatuses.value
    .split(',')
    .map(s => s.trim())
    .filter(Boolean)

  if (statusList.length === 0) {
    projTemplateError.value = 'يرجى كتابة حالة واحدة على الأغل.'
    return
  }

  try {
    if (editingProjTemplateId.value) {
      await store.updateProjectTemplate(
        editingProjTemplateId.value,
        newProjTemplateName.value.trim(),
        false, 
        statusList,
        newProjTemplateTaskIds.value
      )
      projTemplateSuccess.value = 'تم تحديث قالب المشروع بنجاح.'
    } else {
      await store.createProjectTemplate(
        newProjTemplateName.value.trim(),
        false, 
        statusList,
        newProjTemplateTaskIds.value
      )
      projTemplateSuccess.value = 'تم إنشاء قالب المشروع الجديد بنجاح.'
    }
    resetProjTemplateForm()
  } catch (e) {
    projTemplateError.value = 'فشل حفظ قالب المشروع.'
  }
}

// Task Template CRUD (Dynamic Fields)
const startEditTaskTemplate = (tpl) => {
  editingTaskTemplateId.value = tpl.id
  newTaskTemplateName.value = tpl.name
  newTaskTemplateFields.value = Array.isArray(tpl.custom_fields_values) ? [...tpl.custom_fields_values] : []
  
  const titleField = newTaskTemplateFields.value.find(f => f.type === 'title')
  newTaskTemplateTitleVal.value = titleField ? titleField.value : ''

  taskTemplateError.value = ''
  taskTemplateSuccess.value = ''
}

const resetTaskTemplateForm = () => {
  editingTaskTemplateId.value = null
  newTaskTemplateName.value = ''
  newTaskTemplateFields.value = []
  newTaskTemplateTitleVal.value = ''
  tempFieldType.value = 'short_text'
  tempFieldName.value = 'حقل جديد'
  tempFieldValue.value = ''
  tempFieldWidth.value = 'full'
  taskTemplateError.value = ''
  taskTemplateSuccess.value = ''
}

const handleSaveTaskTemplate = async () => {
  taskTemplateError.value = ''
  taskTemplateSuccess.value = ''

  if (!newTaskTemplateName.value.trim()) {
    taskTemplateError.value = 'اسم قالب المهمة مطلوب.'
    return
  }

  // Ensure title field is synced in newTaskTemplateFields array
  const titleIdx = newTaskTemplateFields.value.findIndex(f => f.type === 'title')
  if (titleIdx > -1) {
    newTaskTemplateFields.value[titleIdx].value = newTaskTemplateTitleVal.value.trim()
  } else {
    newTaskTemplateFields.value.unshift({
      name: 'عنوان المهمة',
      type: 'title',
      value: newTaskTemplateTitleVal.value.trim(),
      width: 'full'
    })
  }

  try {
    if (editingTaskTemplateId.value) {
      await store.updateTaskTemplate(
        editingTaskTemplateId.value,
        newTaskTemplateName.value.trim(),
        false,
        '', // title empty (deprecated)
        '', // description empty (deprecated)
        '', // status empty (deprecated)
        newTaskTemplateFields.value, // Pass all fields inside custom_fields_values
        '', // priority empty (deprecated)
        null, // start_date_offset empty (deprecated)
        null, // due_date_offset empty (deprecated)
        [] // attachments empty (deprecated)
      )
      taskTemplateSuccess.value = 'تم تحديث قالب المهمة بنجاح.'
    } else {
      await store.createTaskTemplate(
        newTaskTemplateName.value.trim(),
        false,
        '',
        '',
        '',
        newTaskTemplateFields.value,
        '',
        null,
        null,
        []
      )
      taskTemplateSuccess.value = 'تم إنشاء قالب المهمة الجديد بنجاح.'
    }
    resetTaskTemplateForm()
  } catch (e) {
    taskTemplateError.value = 'فشل حفظ قالب المهمة.'
  }
}
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start text-right font-sans" dir="rtl">
    
    <!-- Right Sidebar Settings list (Horizontal scrollable on mobile) -->
    <div class="lg:col-span-3 bg-slate-50/50 dark:bg-slate-950/20 border border-slate-200/60 dark:border-slate-800 p-2.5 rounded-3xl text-right flex flex-row overflow-x-auto lg:flex-col gap-2 scrollbar-hide shrink-0">
      <span class="hidden lg:block text-[10px] font-bold text-slate-400 px-4 py-2 text-right">أقسام تهيئة النظام</span>

      
 
      <button 
        @click="activeTab = 'users'"
        :class="[
          'w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-xs font-bold transition duration-200 cursor-pointer text-right justify-start',
          activeTab === 'users' 
            ? 'bg-gradient-to-l from-violet-600 to-indigo-600 text-white shadow-md shadow-violet-500/20' 
            : 'text-slate-700 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-950/30'
        ]"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        <span>المستخدمين والصلاحيات</span>
      </button>
 
      <button 
        @click="activeTab = 'proj-templates'"
        :class="[
          'w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-xs font-bold transition duration-200 cursor-pointer text-right justify-start',
          activeTab === 'proj-templates' 
            ? 'bg-gradient-to-l from-violet-600 to-indigo-600 text-white shadow-md shadow-violet-500/20' 
            : 'text-slate-700 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-950/30'
        ]"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
        </svg>
        <span>قوالب المشاريع</span>
      </button>
 
      <button 
        @click="activeTab = 'task-templates'"
        :class="[
          'w-full flex items-center gap-3 px-4 py-3 rounded-2xl text-xs font-bold transition duration-200 cursor-pointer text-right justify-start',
          activeTab === 'task-templates' 
            ? 'bg-gradient-to-l from-violet-600 to-indigo-600 text-white shadow-md shadow-violet-500/20' 
            : 'text-slate-700 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-950/30'
        ]"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
        <span>قوالب المهام</span>
      </button>
    </div>

    <!-- Left Container Panel: Dynamic Tab View Stage (9 cols in RTL) -->
    <div class="lg:col-span-9 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-3xl p-6 shadow-sm min-h-[480px]">
      
      <!-- Tab 2: Users & Permissions -->
      <div v-if="activeTab === 'users'" class="space-y-6">
        <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
          <h3 class="text-sm font-extrabold text-slate-900 dark:text-slate-100">المستخدمين وإدارة الصلاحيات (RBAC)</h3>
          <p class="text-xs text-slate-400 font-semibold mt-1">إضافة أعضاء فريق عملك وتحديد أدوارهم وصلاحياتهم الوظيفية.</p>
        </div>

        <div v-if="store.hasPermission('manage-users')" class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
          <!-- Users Table -->
          <div class="xl:col-span-8 space-y-3">
            <span class="text-xs font-extrabold text-slate-500 block">المستخدمين المسجلين</span>
            <div class="border border-slate-200/60 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-950">
              <!-- Desktop Table View (hidden sm:block) -->
              <div class="hidden sm:block">
                <table class="w-full text-right border-collapse text-xs sm:text-sm">
                  <thead>
                    <tr class="bg-slate-50/50 dark:bg-slate-950/20 border-b border-slate-100 dark:border-slate-800 text-xs font-extrabold text-slate-500">
                      <th class="py-3 px-4">الاسم</th>
                      <th class="py-3 px-4">الدور</th>
                      <th class="py-3 px-4 text-left">التحكم</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 dark:divide-slate-900">
                    <tr v-for="u in users" :key="u.id" class="hover:bg-slate-50/30 dark:hover:bg-slate-950/10 transition">
                      <td class="py-3 px-4 font-bold">
                        {{ u.name }}
                        <span class="block text-[10px] text-slate-400 font-mono font-medium">{{ u.email }}</span>
                      </td>
                      <td class="py-3 px-4">
                        <span class="inline-flex px-2 py-0.5 rounded-lg text-[10px] font-bold bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-400 border border-rose-200/20">
                          {{ u.role ? u.role.name : 'بدون دور' }}
                        </span>
                      </td>
                      <td class="py-3 px-4 text-left space-x-2">
                        <button 
                          v-if="u.id !== store.currentUser?.id"
                          @click="handleImpersonate(u)"
                          title="تسجيل الدخول كـ هذا المستخدم" aria-label="تسجيل الدخول كـ هذا المستخدم"
                          class="px-2.5 py-1 text-xs bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 font-bold rounded-xl transition cursor-pointer min-h-[44px] inline-flex items-center gap-1 border border-indigo-200/50 dark:border-indigo-800/50"
                        >
                          <span>🔑</span>
                          <span>دخول كـ</span>
                        </button>
                        <button @click="editUser(u)" class="px-3 py-2 text-xs text-rose-600 font-bold transition cursor-pointer min-h-[44px] min-w-[44px] inline-flex items-center justify-center">تعديل</button>
                        <button @click="deleteUser(u)" :disabled="u.id === store.currentUser.id" class="px-3 py-2 text-xs text-rose-500 font-bold transition disabled:opacity-40 cursor-pointer min-h-[44px] min-w-[44px] inline-flex items-center justify-center">حذف</button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Mobile Cards View (block sm:hidden) -->
              <div class="block sm:hidden divide-y divide-slate-100 dark:divide-slate-900">
                <div v-for="u in users" :key="'mob-user-' + u.id" class="p-3.5 space-y-2.5 hover:bg-slate-50/30 dark:hover:bg-slate-950/10 transition text-right">
                  <div class="flex items-center justify-between gap-2">
                    <div class="font-bold text-xs">
                      {{ u.name }}
                      <span class="block text-[10px] text-slate-400 font-mono font-medium">{{ u.email }}</span>
                    </div>
                    <span class="inline-flex px-2 py-0.5 rounded-lg text-[10px] font-bold bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-400 border border-rose-200/20 shrink-0">
                      {{ u.role ? u.role.name : 'بدون دور' }}
                    </span>
                  </div>
                  <div class="flex items-center justify-end gap-2 pt-1 border-t border-slate-100/60 dark:border-slate-800/40">
                    <button 
                      v-if="u.id !== store.currentUser?.id"
                      @click="handleImpersonate(u)"
                      title="تسجيل الدخول كـ هذا المستخدم" aria-label="تسجيل الدخول كـ هذا المستخدم"
                      class="px-2.5 py-1 text-xs bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 font-bold rounded-xl transition cursor-pointer min-h-[44px] flex items-center gap-1 border border-indigo-200/50 dark:border-indigo-800/50"
                    >
                      <span>🔑</span>
                      <span>دخول كـ</span>
                    </button>
                    <button @click="editUser(u)" class="px-3 py-2 text-xs text-rose-600 font-bold transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center">تعديل</button>
                    <button @click="deleteUser(u)" :disabled="u.id === store.currentUser.id" class="px-3 py-2 text-xs text-rose-500 font-bold transition disabled:opacity-40 cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center">حذف</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Create User Form -->
          <div class="xl:col-span-4 bg-slate-50/50 dark:bg-slate-950/10 border border-slate-200/60 dark:border-slate-800 p-4 rounded-2xl space-y-3">
            <div class="flex items-center justify-between flex-row-reverse mb-2">
              <span class="text-xs font-extrabold text-slate-700 dark:text-slate-200">
                {{ isEditing ? 'تعديل بيانات العضو' : 'إضافة عضو جديد' }}
              </span>
              <button 
                v-if="isEditing" 
                @click.prevent="resetForm" 
                class="text-[10px] font-bold text-rose-600 bg-rose-50 dark:bg-rose-950 px-2 py-0.5 rounded border border-rose-100 transition cursor-pointer"
              >
                + جديد
              </button>
            </div>

            <form @submit.prevent="handleSaveUser" class="space-y-3">
              <div>
                <label class="block text-[10px] font-bold text-slate-500 mb-1">الاسم الكامل *</label>
                <input v-model="userName" type="text" placeholder="الاسم..." class="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-2.5 py-2 text-xs focus:outline-none" />
              </div>
              <div>
                <label class="block text-[10px] font-bold text-slate-500 mb-1">البريد الإلكتروني *</label>
                <input v-model="userEmail" type="email" placeholder="mail@..." class="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-2.5 py-2 text-xs focus:outline-none" />
              </div>
              <div>
                <label class="block text-[10px] font-bold text-slate-500 mb-1">{{ isEditing ? 'كلمة مرور جديدة (اختياري)' : 'كلمة المرور *' }}</label>
                <input v-model="userPassword" type="password" placeholder="******" class="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-2.5 py-2 text-xs focus:outline-none" />
              </div>
              <div>
                <label class="block text-[10px] font-bold text-slate-500 mb-1">الدور الوظيفي *</label>
                <select v-model="userRoleId" class="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-2.5 py-2 text-xs focus:outline-none cursor-pointer">
                  <option value="" disabled>اختر دور العضو...</option>
                  <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
              </div>

              <div v-if="formError" class="text-[10px] text-rose-500 font-bold">{{ formError }}</div>
              <div v-if="formSuccess" class="text-[10px] text-emerald-500 font-bold">{{ formSuccess }}</div>

              <div class="flex space-x-1.5 pt-1">
                <button type="submit" class="flex-1 bg-rose-600 hover:bg-rose-700 text-white font-bold py-2 rounded-xl text-xs transition cursor-pointer shadow-md shadow-rose-500/10">{{ isEditing ? 'تعديل' : 'إضافة' }}</button>
              </div>
            </form>
          </div>
        </div>

        <div v-else class="text-xs text-rose-600 italic bg-rose-500/5 p-4 rounded-xl border border-rose-200/20 text-center font-bold">
          غير مصرح لحسابك الجاري بإدارة أعضاء الفريق ومستخدمي النظام.
        </div>

        <!-- Role descriptions matrix -->
        <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
          <span class="text-xs font-extrabold text-slate-500 block mb-3">تفصيل مصفوفة الصلاحيات</span>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="border border-slate-100 dark:border-slate-800 rounded-xl p-3.5 space-y-1">
              <span class="text-xs font-extrabold text-rose-600 block">مدير (Admin)</span>
              <p class="text-[11px] text-slate-500 leading-normal">إشراف كلي على المشاريع والمهام وإضافة وحذف الأعضاء وإعداد القوالب العامة.</p>
            </div>
            <div class="border border-slate-100 dark:border-slate-800 rounded-xl p-3.5 space-y-1">
              <span class="text-xs font-extrabold text-rose-600 block">عضو (Member)</span>
              <p class="text-[11px] text-slate-500 leading-normal">تخطيط وإنشاء المهام، سحب وإفلات العناصر، المساهمة في محادثات المشروع ورفع الملفات.</p>
            </div>
            <div class="border border-slate-100 dark:border-slate-800 rounded-xl p-3.5 space-y-1">
              <span class="text-xs font-extrabold text-slate-500 block">مشاهد (Viewer)</span>
              <p class="text-[11px] text-slate-500 leading-normal">عرض ومراقبة لوحات العمل وسير العمل في وضع القراءة فقط دون تعديل أو مشاركة في الشات.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 3: Project Templates -->
      <div v-if="activeTab === 'proj-templates'" class="space-y-6 animate-fade-in">
        <div class="border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center justify-between flex-row-reverse">
          <div class="text-right">
            <h3 class="text-sm font-extrabold text-slate-900 dark:text-slate-100 font-extrabold">قوالب المشاريع</h3>
            <p class="text-xs text-slate-400 font-semibold mt-1">تحديد الحالات والربط التلقائي بقوالب المهام.</p>
          </div>
          <button 
            @click="resetProjTemplateForm"
            class="text-[10px] font-bold text-violet-600 hover:text-violet-700 bg-violet-50 dark:bg-violet-950/20 px-3 py-1.5 rounded-xl border border-violet-100 dark:border-violet-800/30 transition cursor-pointer"
          >
            + قالب جديد
          </button>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">
          
          <!-- Templates List -->
          <div class="xl:col-span-7 space-y-3">
            <span class="text-xs font-extrabold text-slate-500 block">مكتبة قوالب المشاريع</span>
            
            <div v-if="store.projectTemplates.length === 0" class="text-xs text-slate-400 italic py-4 text-center">لا توجد قوالب مشاريع مسجلة حالياً.</div>
            <div 
              v-for="tpl in store.projectTemplates" 
              :key="tpl.id"
              class="p-3.5 bg-slate-50/50 dark:bg-slate-950/20 border border-slate-200/60 dark:border-slate-800 rounded-2xl flex flex-col justify-between gap-3 text-right"
            >
              <div class="space-y-1.5">
                <div class="flex items-center gap-2 justify-start flex-row-reverse">
                  <span v-if="tpl.is_default" class="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-200/20 text-[10px] font-extrabold px-1.5 py-0.5 rounded-md">
                    القالب الافتراضي
                  </span>
                  <h4 class="text-xs font-extrabold text-slate-900 dark:text-slate-100">{{ tpl.name }}</h4>
                </div>
                <div class="text-[10px] text-slate-500 leading-relaxed">
                  <span><b>الحالات:</b> {{ tpl.statuses?.join(' | ') }}</span><br/>
                  <span v-if="tpl.task_template_ids && tpl.task_template_ids.length > 0">
                    <b>المهام المربوطة:</b>
                    {{ tpl.task_template_ids.map(id => store.taskTemplates.find(t => t.id === id)?.name || id).join('، ') }}
                  </span>
                  <span v-else>بدون مهام تلقائية</span>
                </div>
              </div>

              <div class="flex space-x-2 justify-start border-t border-slate-200 dark:border-slate-800 pt-2 flex-row-reverse">
                <button @click="startEditProjTemplate(tpl)" class="text-[10px] font-extrabold text-violet-600 bg-violet-50 dark:bg-violet-950/20 px-2.5 py-1 rounded-lg border border-violet-100 dark:border-violet-800/30 transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center">تعديل</button>
                <button v-if="!tpl.is_default" @click="store.setProjectTemplateDefault(tpl.id)" class="text-[10px] font-extrabold text-slate-600 hover:text-slate-800 bg-white dark:bg-slate-950 px-2 py-1 rounded-lg border border-slate-200 dark:border-slate-800 transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center">تعيين كافتراضي</button>
                <button @click="store.deleteProjectTemplate(tpl.id)" class="text-[10px] font-extrabold text-rose-500 hover:text-rose-700 bg-white dark:bg-slate-950 px-2 py-1 rounded-lg border border-slate-200 dark:border-slate-800 transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center">حذف</button>
              </div>
            </div>
          </div>

          <!-- Create/Edit Form -->
          <div class="xl:col-span-5 bg-slate-50/50 dark:bg-slate-950/10 border border-slate-200/60 dark:border-slate-800 p-4 rounded-2xl space-y-4">
            <span class="text-xs font-extrabold text-slate-700 dark:text-slate-200 block">
              {{ editingProjTemplateId ? 'تعديل قالب مشروع' : 'إنشاء قالب مشروع جديد' }}
            </span>
            
            <div class="space-y-3">
              <div>
                <label class="block text-[10px] font-bold text-slate-500 mb-1">اسم قالب المشروع *</label>
                <input v-model="newProjTemplateName" type="text" placeholder="مثال: قالب التطوير..." class="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-2.5 py-2 text-xs focus:outline-none" />
              </div>
              <div>
                <label class="block text-[10px] font-bold text-slate-500 mb-1">الحالات للمشروع (مفصولة بفاصلة) *</label>
                <input v-model="newProjTemplateStatuses" type="text" placeholder="بانتظار البدء, قيد العمل..." class="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-2.5 py-2 text-xs focus:outline-none" />
              </div>

              <!-- Linked task templates checklist selector -->
              <div class="space-y-2">
                <label class="block text-[10px] font-bold text-slate-500 mb-1.5">اربط قوالب المهام التلقائية بهذا المشروع</label>
                <div v-if="store.taskTemplates.length === 0" class="text-[10px] text-slate-400 italic">لا توجد قوالب مهام متاحة للربط. قم بإنشائها أولاً.</div>
                <div v-else class="space-y-2 max-h-36 overflow-y-auto border border-slate-200 dark:border-slate-800 rounded-xl p-3 bg-white dark:bg-slate-950">
                  <div v-for="t in store.taskTemplates" :key="t.id" class="flex items-center justify-between flex-row-reverse gap-2">
                    <label :for="'lnk-task-' + t.id" class="text-xs text-slate-700 dark:text-slate-300 cursor-pointer select-none">
                      {{ t.name }}
                    </label>
                    <input 
                      :id="'lnk-task-' + t.id"
                      type="checkbox" 
                      :value="t.id" 
                      v-model="newProjTemplateTaskIds"
                      class="text-rose-600 focus:ring-rose-500 rounded cursor-pointer min-h-[44px] min-w-[44px]"
                    />
                  </div>
                </div>
              </div>

              <div v-if="projTemplateError" class="text-[10px] text-rose-500 font-bold">{{ projTemplateError }}</div>
              <div v-if="projTemplateSuccess" class="text-[10px] text-emerald-500 font-bold">{{ projTemplateSuccess }}</div>

              <div class="pt-2">
                <button @click="handleSaveProjTemplate" class="w-full bg-violet-600 hover:bg-violet-700 text-white font-extrabold py-2.5 rounded-xl text-xs transition cursor-pointer shadow-lg shadow-violet-500/10 min-h-[44px]">
                  {{ editingProjTemplateId ? 'حفظ قالب المشروع المحدث' : 'حفظ وإنشاء قالب مشروع جديد' }}
                </button>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Tab 4: Task Templates (Fully Dynamic Field Builder & Live Form Preview) -->
      <div v-if="activeTab === 'task-templates'" class="space-y-8 animate-fade-in">
        
        <!-- Header Section -->
        <div class="border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center justify-between flex-row-reverse">
          <div class="text-right">
            <h3 class="text-sm font-extrabold text-slate-900 dark:text-slate-100">قوالب المهام الديناميكية</h3>
            <p class="text-xs text-slate-400 font-semibold mt-1">تجهيز قوالب فارغة وبناء شكل وتنسيق حقول نموذج المهام حقل حقل.</p>
          </div>
          <button 
            @click="resetTaskTemplateForm"
            class="text-[10px] font-bold text-violet-600 hover:text-violet-700 bg-violet-50 dark:bg-violet-950/20 px-3 py-1.5 rounded-xl border border-violet-100 dark:border-violet-800/30 transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center"
          >
            + قالب جديد
          </button>
        </div>

        <!-- Library of Task Templates list (Horizontal Cards grid for selection) -->
        <div class="space-y-2">
          <span class="text-xs font-extrabold text-slate-500 block">مكتبة قوالب المهام المتوفرة</span>
          <div v-if="store.taskTemplates.length === 0" class="text-xs text-slate-400 italic">لا توجد قوالب مهام مسجلة. قم بإنشاء أول قالب أدناه.</div>
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
            <div 
              v-for="tpl in store.taskTemplates" 
              :key="tpl.id"
              class="p-3 bg-slate-50/50 dark:bg-slate-950/20 border border-slate-200/60 dark:border-slate-800 rounded-2xl flex flex-col justify-between gap-3 text-right"
            >
              <div>
                <h4 class="text-xs font-extrabold text-slate-900 dark:text-slate-100">{{ tpl.name }}</h4>
                <span class="text-[10px] text-slate-400 font-medium block mt-1">
                  يحتوي على: {{ tpl.custom_fields_values?.length || 0 }} حقول
                </span>
              </div>
              <div class="flex space-x-1.5 border-t border-slate-200 dark:border-slate-800 pt-2 flex-row-reverse">
                <button @click="startEditTaskTemplate(tpl)" class="text-[10px] font-extrabold text-violet-600 bg-violet-50 dark:bg-violet-950/20 px-2 py-0.5 rounded-lg border border-violet-100 dark:border-violet-800/30 transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center">تعديل</button>
                <button @click="store.deleteTaskTemplate(tpl.id)" class="text-[10px] font-extrabold text-rose-500 hover:text-rose-700 bg-white dark:bg-slate-950 px-2 py-0.5 rounded-lg border border-slate-200 dark:border-slate-800 transition cursor-pointer min-h-[44px] min-w-[44px] flex items-center justify-center">حذف</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Top Section: Editor Pane -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start border-t border-slate-100 dark:border-slate-800 pt-6">
          
          <!-- RIGHT PANEL: Field Configurations & Spawner (7 cols) -->
          <div class="lg:col-span-7 bg-slate-50/[0.02] border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl space-y-4">
            <span class="text-xs font-extrabold text-slate-700 dark:text-slate-200 block">تخصيص القالب والاسم الرئيسي</span>
            
            <div class="space-y-4">
              <!-- Template Name -->
              <div>
                <label class="block text-[10px] font-bold text-slate-500 mb-1">اسم قالب المهمة *</label>
                <input v-model="newTaskTemplateName" type="text" placeholder="مثال: قالب التصاميم الهندسية..." class="w-full bg-white dark:bg-slate-950 border border-slate-200/80 rounded-xl px-2.5 py-2 text-xs focus:outline-none" />
              </div>

              <!-- Spawner: Add fields one-by-one -->
              <div class="bg-rose-500/[0.02] border border-rose-500/10 rounded-xl p-3.5 space-y-3">
                <span class="text-[10px] font-bold text-rose-600 dark:text-rose-400 block">إضافة حقل جديد للقالب</span>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-1">نوع الحقل</label>
                    <select v-model="tempFieldType" class="w-full bg-white dark:bg-slate-950 border border-slate-200/80 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none cursor-pointer">
                      <option value="short_text">نص قصير (Short Text / Input)</option>
                      <option value="long_text">نص طويل (Long Text / Textarea)</option>
                      <option value="number">رقم (Number)</option>
                      <option value="url">رابط (URL)</option>
                      <option value="dropdown">قائمة منسدلة (Dropdown / Select)</option>
                      <option value="multi_select">اختيار متعدد (Multi-Select)</option>
                      <option value="checkbox">مربع اختيار (Checkbox)</option>
                      <option value="radio">زر اختيار (Radio Button)</option>
                      <option value="date">تاريخ (Date Picker)</option>
                      <option value="date_time">تاريخ ووقت (Date & Time Picker)</option>
                      <option value="user_picker">اختيار مستخدم (User/Member Picker)</option>
                      <option value="file_upload">رفع ملفات (File Upload)</option>
                      <option value="divider">فاصل بصري (Divider / Section)</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-1">اسم الحقل *</label>
                    <input v-model="tempFieldName" type="text" placeholder="اسم الحقل..." class="w-full bg-white dark:bg-slate-950 border border-slate-200/80 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none" />
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-1">عرض الحقل في نموذج المهمة</label>
                    <select v-model="tempFieldWidth" class="w-full bg-white dark:bg-slate-950 border border-slate-200/80 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none cursor-pointer">
                      <option value="full">100% (عرض كامل)</option>
                      <option value="half">50% (نصف عرض)</option>
                      <option value="thirty">30% (عرض 30%)</option>
                      <option value="quarter">25% (ربع عرض)</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-1">قيمة الحقل الافتراضية</label>
                    
                    <select 
                      v-if="tempFieldType === 'checkbox'"
                      v-model="tempFieldValue" 
                      class="w-full bg-white dark:bg-slate-950 border border-slate-200/80 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none cursor-pointer"
                    >
                      <option value="">غير محدد</option>
                      <option value="true">محدد (نعم)</option>
                      <option value="false">غير محدد (لا)</option>
                    </select>

                    <textarea
                      v-else-if="tempFieldType === 'long_text'"
                      v-model="tempFieldValue"
                      rows="2"
                      placeholder="اكتب القيمة الافتراضية الطويلة..."
                      class="w-full bg-white dark:bg-slate-950 border border-slate-200/80 rounded-lg px-2.5 py-1 text-xs focus:outline-none resize-none"
                    ></textarea>

                    <input 
                      v-else-if="tempFieldType === 'dropdown' || tempFieldType === 'radio' || tempFieldType === 'multi_select'"
                      v-model="tempFieldValue"
                      type="text"
                      placeholder="اكتب الخيارات مفصولة بفاصلة (مثال: عاجل, متوسط, منخفض)..."
                      class="w-full bg-white dark:bg-slate-950 border border-slate-200/80 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none"
                    />

                    <input 
                      v-else-if="tempFieldType === 'number'"
                      v-model="tempFieldValue"
                      type="number"
                      placeholder="مثال: 15"
                      class="w-full bg-white dark:bg-slate-950 border border-slate-200/80 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none"
                    />

                    <input 
                      v-else-if="tempFieldType === 'date'"
                      v-model="tempFieldValue"
                      type="date"
                      class="w-full bg-white dark:bg-slate-950 border border-slate-200/80 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none cursor-pointer"
                    />

                    <input 
                      v-else-if="tempFieldType === 'date_time'"
                      v-model="tempFieldValue"
                      type="datetime-local"
                      class="w-full bg-white dark:bg-slate-950 border border-slate-200/80 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none cursor-pointer"
                    />

                    <input 
                      v-else-if="tempFieldType === 'divider' || tempFieldType === 'file_upload' || tempFieldType === 'user_picker'"
                      disabled
                      placeholder="لا توجد قيمة افتراضية لهذا النوع"
                      class="w-full bg-slate-100 dark:bg-slate-800 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-400 focus:outline-none cursor-not-allowed"
                    />

                    <input 
                      v-else 
                      v-model="tempFieldValue"
                      type="text"
                      placeholder="القيمة الافتراضية..."
                      class="w-full bg-white dark:bg-slate-950 border border-slate-200/80 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none"
                    />
                  </div>
                </div>

                <button @click="addFieldToTaskTemplate" class="bg-violet-50 hover:bg-violet-100 text-violet-600 dark:bg-violet-950/20 dark:text-violet-400 font-bold px-3 py-1.5 rounded-lg text-[10px] transition cursor-pointer">
                  + ربط وإضافة الحقل للقالب
                </button>
              </div>

              <!-- Save Actions -->
              <div v-if="taskTemplateError" class="text-[10px] text-rose-500 font-bold">{{ taskTemplateError }}</div>
              <div v-if="taskTemplateSuccess" class="text-[10px] text-emerald-500 font-bold">{{ taskTemplateSuccess }}</div>

              <div class="pt-2">
                <button @click="handleSaveTaskTemplate" class="w-full bg-violet-600 hover:bg-violet-700 text-white font-extrabold py-3 rounded-xl text-xs transition cursor-pointer shadow-lg shadow-violet-500/10">
                  {{ editingTaskTemplateId ? 'حفظ قالب المهمة المحدث' : 'حفظ وإنشاء قالب مهمة جديد' }}
                </button>
              </div>
            </div>
          </div>

          <!-- LEFT PANEL: Draggable List of Fields (5 cols) -->
          <div class="lg:col-span-5 bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl space-y-3">
            <div class="flex items-center justify-between flex-row-reverse pb-1.5 border-b border-slate-100 dark:border-slate-800">
              <span class="text-xs font-extrabold text-slate-700">ترتيب حقول القالب</span>
              <span class="text-[10px] font-bold text-slate-400">اسحب الحقل للترتيب (⋮⋮)</span>
            </div>

            <div v-if="newTaskTemplateFields.length === 0" class="text-xs text-slate-400 italic text-center py-12">
              لم تقم بإضافة حقول للقالب بعد.
            </div>

            <div class="space-y-1.5 max-h-[380px] overflow-y-auto">
              <div 
                v-for="(f, idx) in newTaskTemplateFields" 
                :key="idx" 
                draggable="true"
                @dragstart="handleDragStart(idx)"
                @dragover.prevent
                @drop="handleDrop(idx)"
                @dragend="handleDragEnd"
                :class="[
                  'flex items-center justify-between p-2.5 rounded-xl bg-slate-50/50 dark:bg-slate-950/10 border border-slate-200/60 dark:border-slate-800 text-xs flex-row-reverse transition duration-200 cursor-grab active:cursor-grabbing select-none',
                  dragSrcIndex === idx ? 'opacity-40 border-rose-500 border-dashed bg-rose-500/[0.02]' : 'hover:border-slate-300 dark:hover:border-slate-800'
                ]"
              >
                <div class="flex items-center gap-2 flex-row-reverse">
                  <!-- Drag Handle -->
                  <span class="text-slate-400 dark:text-slate-600 font-mono text-sm leading-none cursor-grab active:cursor-grabbing">⋮⋮</span>
                  <div class="text-right">
                    <span class="font-extrabold text-slate-800 dark:text-slate-200 block text-xs">{{ f.name }}</span>
                    <span class="text-[10px] text-slate-400 font-semibold">
                      النوع: {{ f.type }} | العرض: {{ f.width === 'quarter' ? '25%' : f.width === 'thirty' ? '30%' : f.width === 'half' ? '50%' : '100%' }}
                    </span>
                  </div>
                </div>
                
                <button 
                  @click.stop="removeFieldFromTaskTemplate(idx)" 
                  class="text-rose-500 hover:text-rose-700 font-extrabold px-2 py-0.5 rounded hover:bg-rose-50 dark:hover:bg-rose-950/20 transition cursor-pointer"
                >
                  حذف
                </button>
              </div>
            </div>
          </div>

        </div>

        <!-- Bottom Section: Live Form Preview (Full Size max-w-2xl Modal replication) -->
        <div class="border-t border-slate-100 dark:border-slate-800 pt-8 mt-8 space-y-4">
          <div class="text-center space-y-1">
            <h4 class="text-sm font-extrabold text-slate-800 dark:text-slate-100">المعاينة الحية لنموذج المهمة (بالحجم الكامل)</h4>
            <p class="text-xs text-slate-400 font-semibold">تعرض هذه اللوحة شكل نموذج المهمة الفعلي بالمقاسات والاصطفافات الحقيقية.</p>
          </div>

          <!-- Mock Modal Container -->
          <div class="max-w-2xl mx-auto bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-xl overflow-hidden text-right" dir="rtl">
            
            <!-- Mock Header -->
            <div class="bg-slate-50/50 dark:bg-slate-950/20 px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between flex-row-reverse">
              <span class="text-xs font-extrabold text-slate-800 dark:text-slate-100">إضافة مهمة جديدة</span>
              <div class="h-6 w-6 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-xs text-slate-500 font-bold select-none cursor-not-allowed">X</div>
            </div>

            <!-- Mock Body -->
            <div class="p-6 space-y-6">
              
              <!-- Mock Template Selection Info -->
              <div class="bg-violet-500/[0.02] border border-violet-500/10 rounded-xl p-3.5 flex items-center justify-between flex-row-reverse">
                <span class="text-xs font-bold text-violet-600 dark:text-violet-400">قالب المهام النشط: {{ newTaskTemplateName || 'قالب جديد' }}</span>
                <span class="text-[10px] bg-violet-500/10 text-violet-600 px-2 py-0.5 rounded font-extrabold">وضع المعاينة</span>
              </div>

              <!-- Mock Title field (Editable, binds to newTaskTemplateTitleVal) -->
              <div class="w-full space-y-1.5">
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400">عنوان المهمة *</label>
                <input 
                  type="text" 
                  v-model="newTaskTemplateTitleVal" 
                  placeholder="اكتب عنوان المهمة الافتراضي لهذا القالب..." 
                  class="w-full bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-800 font-bold focus:outline-none focus:border-violet-500"
                />
              </div>

              <!-- Additional Dynamic Fields -->
              <div class="space-y-4">
                <div v-if="newTaskTemplateFields.length === 0" class="text-xs text-slate-400 italic text-center py-10 border border-dashed border-slate-200/60 dark:border-slate-800 rounded-xl">
                  لا توجد حقول إضافية مخصصة في هذا القالب. أضف حقولاً من لوحة التخصيص أعلاه.
                </div>
                
                <div v-else class="flex flex-wrap gap-4 text-right">
                  <div 
                    v-for="(f, idx) in newTaskTemplateFields" 
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
                      <span class="text-xs font-extrabold text-slate-500 dark:text-slate-400">{{ f.name }}</span>
                    </div>

                    <div v-else class="w-full space-y-1.5">
                      <label class="block text-xs font-bold text-slate-500 dark:text-slate-400">{{ f.name }}</label>
                      
                      <!-- Long Text -->
                      <textarea 
                        v-if="f.type === 'long_text'" 
                        disabled 
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2 text-xs text-slate-500 resize-none h-16" 
                        :placeholder="f.value || 'اكتب التفاصيل...'"
                      ></textarea>

                      <!-- Dropdown -->
                      <select 
                        v-else-if="f.type === 'dropdown'" 
                        disabled 
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2 text-xs text-slate-500 focus:outline-none"
                      >
                        <option v-for="opt in (f.value || '').split(',').map(o => o.trim()).filter(Boolean)" :key="opt">{{ opt }}</option>
                      </select>

                      <!-- Multi-Select -->
                      <div v-else-if="f.type === 'multi_select'" class="flex flex-wrap gap-2 pt-1">
                        <label v-for="opt in (f.value || '').split(',').map(o => o.trim()).filter(Boolean)" :key="opt" class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-950 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-800 text-[10px] text-slate-500">
                          <input type="checkbox" disabled class="rounded text-violet-600" />
                          <span>{{ opt }}</span>
                        </label>
                      </div>

                      <!-- Checkbox -->
                      <div v-else-if="f.type === 'checkbox'" class="flex items-center gap-2 pt-2">
                        <input type="checkbox" disabled :checked="f.value === 'true'" class="rounded text-violet-600 h-4 w-4" />
                        <span class="text-xs text-slate-500">تفعيل هذا الخيار</span>
                      </div>

                      <!-- Radio -->
                      <div v-else-if="f.type === 'radio'" class="flex flex-wrap gap-3 pt-1">
                        <label v-for="opt in (f.value || '').split(',').map(o => o.trim()).filter(Boolean)" :key="opt" class="flex items-center gap-1.5 text-xs text-slate-500">
                          <input type="radio" disabled class="text-violet-600" />
                          <span>{{ opt }}</span>
                        </label>
                      </div>

                      <!-- User Picker -->
                      <select v-else-if="f.type === 'user_picker'" disabled class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-500">
                        <option>اختر مستخدماً من المشروع...</option>
                      </select>

                      <!-- File Upload -->
                      <div v-else-if="f.type === 'file_upload'" class="w-full border border-dashed border-slate-200 dark:border-slate-800 rounded-xl p-3 text-center text-xs text-slate-400 bg-slate-50/[0.03]">
                        📥 اسحب الملفات أو انقر هنا للرفع
                      </div>

                      <!-- Date Picker -->
                      <input v-else-if="f.type === 'date'" type="date" disabled :value="f.value" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2 text-xs text-slate-500" />

                      <!-- Date & Time Picker -->
                      <input v-else-if="f.type === 'date_time'" type="datetime-local" disabled :value="f.value" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2 text-xs text-slate-500" />

                      <!-- Short Text / Number / URL -->
                      <input 
                        v-else 
                        :type="f.type === 'number' ? 'number' : f.type === 'url' ? 'url' : 'text'" 
                        disabled 
                        :value="f.value" 
                        class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-xl px-3.5 py-2.5 text-xs text-slate-500" 
                      />
                    </div>
                  </div>
                </div>
              </div>

            </div>

            <!-- Mock Footer -->
            <div class="bg-slate-50/30 dark:bg-slate-950/10 px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex justify-start space-x-2 flex-row-reverse">
              <button disabled class="bg-violet-600/40 text-white font-bold py-2 px-4 rounded-xl text-xs cursor-not-allowed">إنشاء المهمة</button>
              <button disabled class="bg-slate-100 dark:bg-slate-800 text-slate-500 py-2 px-4 rounded-xl text-xs cursor-not-allowed">إلغاء</button>
            </div>

          </div>
        </div>

      </div>

    </div>

  </div>
</template>
