import { reactive, watch } from 'vue'

export const store = reactive({
  // Connection and Authentication
  apiBase: import.meta.env.VITE_API_BASE_URL || 'https://mind.zadians.com/api',
  token: localStorage.getItem('mymind_token') || '',
  isAuthenticated: false,
  currentUser: null,

  // Application Data States
  projects: [],
  projectCategories: [],
  tasks: [],
  folders: [],
  projectFiles: [],
  notes: [],
  activeDocumentFolderId: null,
  notifications: [],
  messages: [],
  emailQueue: [],
  batchedEmails: [],
  globalStatuses: ['بانتظار البدء', 'قيد العمل', 'تحت المراجعة', 'مكتمل'],
  projectTemplates: [],
  taskTemplates: [],
  users: [],

  // Habit & Routines State (يومياتي)
  habits: (() => {
    try {
      const saved = localStorage.getItem('mymind_habits')
      if (saved) return JSON.parse(saved)
    } catch (e) {
      console.error('فشل تحميل العادات من التخزين المحلي', e)
    }
    return [
      {
        id: 1,
        title: 'شرب 8 أكواب ماء',
        category: 'صحة ورشاقة',
        icon: '🥛',
        color: 'from-blue-500 to-cyan-500',
        timeOfDay: 'anytime',
        type: 'numeric',
        targetValue: 8,
        unit: 'كوب',
        frequency: [0, 1, 2, 3, 4, 5, 6],
        logs: {}
      },
      {
        id: 2,
        title: 'قراءة 15 دقيقة في كتاب',
        category: 'تطوير ذات',
        icon: '📖',
        color: 'from-amber-500 to-orange-500',
        timeOfDay: 'evening',
        type: 'boolean',
        targetValue: 1,
        unit: 'مرة',
        frequency: [0, 1, 2, 3, 4, 5, 6],
        logs: {}
      },
      {
        id: 3,
        title: 'أذكار الصباح والمساء',
        category: 'هدوء وروحانيات',
        icon: '🤲',
        color: 'from-emerald-500 to-teal-500',
        timeOfDay: 'morning',
        type: 'boolean',
        targetValue: 1,
        unit: 'مرة',
        frequency: [0, 1, 2, 3, 4, 5, 6],
        logs: {}
      },
      {
        id: 4,
        title: 'مشي 5000 خطوة',
        category: 'صحة ورشاقة',
        icon: '🏃‍♂️',
        color: 'from-violet-500 to-purple-500',
        timeOfDay: 'afternoon',
        type: 'numeric',
        targetValue: 5000,
        unit: 'خطوة',
        frequency: [0, 1, 2, 3, 4, 5, 6],
        logs: {}
      }
    ]
  })(),

  // Daily Quick Tasks State (اليوميات)
  dailyTasks: (() => {
    try {
      const saved = localStorage.getItem('mymind_daily_tasks')
      if (saved) return JSON.parse(saved)
    } catch (e) {
      console.error('فشل تحميل اليوميات من التخزين المحلي', e)
    }
    return [
      {
        id: 101,
        title: 'مراجعة أهداف اليوم والمهام الأكثر أهمية',
        category: 'شخصي',
        priority: 'عالية',
        dueTime: '09:00',
        completed: false,
        createdAt: new Date().toISOString()
      },
      {
        id: 102,
        title: 'متابعة رسائل البريد الإلكتروني والرد السريع',
        category: 'عمل',
        priority: 'متوسطة',
        dueTime: '10:30',
        completed: true,
        createdAt: new Date().toISOString()
      },
      {
        id: 103,
        title: 'تمارين الاستطالة والاستراحة الصباحية',
        category: 'صحة',
        priority: 'منخفضة',
        dueTime: '12:00',
        completed: false,
        createdAt: new Date().toISOString()
      }
    ]
  })(),

  // Dynamic Categories for Daily Tasks & Routines
  dailyTaskCategories: (() => {
    try {
      const saved = localStorage.getItem('mymind_daily_task_categories')
      if (saved) return JSON.parse(saved)
    } catch (e) {
      console.error('فشل تحميل تصنيفات اليوميات من التخزين المحلي', e)
    }
    return ['عام', 'عمل', 'شخصي', 'صحة', 'دراسة', 'عاجل']
  })(),




  // Navigation and UI States
  activeProjectId: null,
  activeCategoryId: null,
  activeView: 'kanban',
  isFocusMode: false,
  theme: localStorage.getItem('mymind_theme') || 'light',
  isOnline: true,
  isNotificationDrawerOpen: false,
  selectedTaskIdForModal: null,
  isTaskModalOpen: false,
  prefilledTaskTitle: '',
  prefilledTaskStatus: '',
  pushPermission: 'default',
  typingCollaborators: {},

  // Layout and Inspector States
  isSidebarCollapsed: localStorage.getItem('mymind_sidebar_collapsed') === 'true',
  isInspectorOpen: false,
  activeInspectorTaskId: null,

  // Layout & Inspector Actions
  toggleSidebar() {
    this.isSidebarCollapsed = !this.isSidebarCollapsed
    localStorage.setItem('mymind_sidebar_collapsed', String(this.isSidebarCollapsed))
  },

  openTaskInspector(taskId) {
    this.activeInspectorTaskId = taskId
    this.isInspectorOpen = true
  },

  closeTaskInspector() {
    this.isInspectorOpen = false
    this.activeInspectorTaskId = null
  },

  // Check role permissions helper
  hasPermission(permissionSlug) {
    if (!this.currentUser || !this.currentUser.role) return false
    // Admin has absolute control
    if (this.currentUser.role.name === 'مدير') return true
    // Otherwise check permissions slug array
    return this.currentUser.role.permissions?.some(p => p.slug === permissionSlug) || false
  },

  getAuthHeaders(customHeaders = {}) {
    const headers = { ...customHeaders }
    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`
    }
    return headers
  },

  // Initialize and load profile/data
  async init() {
    if (!this.token) {
      this.isAuthenticated = false
      return
    }

    try {
      const res = await fetch(`${this.apiBase}/profile`, {
        headers: this.getAuthHeaders()
      })

      if (res.ok) {
        this.currentUser = await res.json()
        this.isAuthenticated = true
        await this.loadProjectCategories()
        await this.loadProjects()
        await this.loadNotifications()
        await this.loadDigestInfo()
        await this.loadProjectTemplates()
        await this.loadTaskTemplates()
        await this.loadUsers()
      } else {
        // Token expired/invalid
        this.logout();
      }
    } catch (e) {
      console.error("فشل الاتصال بالخادم الرئيسي في مرحلة التحقق من الهوية", e)
    }
  },

  logout() {
    localStorage.removeItem('mymind_token')
    this.token = ''
    this.isAuthenticated = false
    this.currentUser = null
    this.projects = []
    this.tasks = []
    this.folders = []
    this.projectFiles = []
    this.notes = []
    this.activeDocumentFolderId = null
    this.notifications = []
  },

  // Load Project Categories
  async loadProjectCategories() {
    try {
      const res = await fetch(`${this.apiBase}/project-categories`, {
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        this.projectCategories = await res.json()
        // Auto-select first category if none active
        if (this.projectCategories.length > 0 && !this.activeCategoryId) {
          this.activeCategoryId = null // null = show all
        }
      }
    } catch (e) {
      console.error("فشل تحميل تصنيفات المشاريع", e)
    }
  },

  // Create Project Category
  async createProjectCategory(name, description = '', color = '#8b5cf6', icon = '📂') {
    try {
      const res = await fetch(`${this.apiBase}/project-categories`, {
        method: 'POST',
        headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ name, description, color, icon })
      })
      if (res.ok) {
        await this.loadProjectCategories()
        this.addNotification('تصنيف جديد', `تم إنشاء التصنيف "${name}" بنجاح.`)
      }
    } catch (e) {
      console.error("خطأ في إنشاء التصنيف", e)
    }
  },

  // Update Project Category
  async updateProjectCategory(id, data) {
    try {
      const res = await fetch(`${this.apiBase}/project-categories/${id}`, {
        method: 'PUT',
        headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(data)
      })
      if (res.ok) {
        await this.loadProjectCategories()
      }
    } catch (e) {
      console.error("خطأ في تحديث التصنيف", e)
    }
  },

  // Delete Project Category
  async deleteProjectCategory(id) {
    try {
      const res = await fetch(`${this.apiBase}/project-categories/${id}`, {
        method: 'DELETE',
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        if (this.activeCategoryId === id) this.activeCategoryId = null
        await this.loadProjectCategories()
        await this.loadProjects()
        this.addNotification('حذف تصنيف', 'تم حذف التصنيف بنجاح.')
      }
    } catch (e) {
      console.error("خطأ في حذف التصنيف", e)
    }
  },

  // Load Projects
  async loadProjects() {
    try {
      const res = await fetch(`${this.apiBase}/projects`, {
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        const rawProjects = await res.json()
        // Map backend custom fields, member relations, and category
        this.projects = rawProjects.map(p => ({
          id: p.id,
          name: p.name,
          description: p.description,
          statuses: p.statuses,
          customFields: p.custom_fields || [],
          memberIds: p.member_ids || [],
          categoryId: p.category_id || null,
          categoryName: p.category_name || null,
          task_counts: p.task_counts || {},
          total_tasks_count: p.total_tasks_count || 0,
          isDeleted: p.is_deleted
        }))

        // Sort projects by saved order if exists
        try {
          const savedOrder = JSON.parse(localStorage.getItem('mymind_projects_order') || '[]')
          if (savedOrder && savedOrder.length > 0) {
            this.projects.sort((a, b) => {
              const idxA = savedOrder.indexOf(a.id)
              const idxB = savedOrder.indexOf(b.id)
              if (idxA === -1 && idxB === -1) return 0
              if (idxA === -1) return 1
              if (idxB === -1) return -1
              return idxA - idxB
            })
          }
        } catch (e) {
          console.error("خطأ في ترتيب المشاريع المحفوظة", e)
        }

        // Auto-select active project if not set or deleted
        if (this.projects.length > 0) {

          if (!this.activeProjectId || !this.projects.some(p => p.id === this.activeProjectId)) {
            this.activeProjectId = this.projects[0].id
          }
          await this.loadTasks()
          await this.loadFolders()
          await this.loadProjectFiles()
          await this.loadNotes()
          await this.loadMessages()
          await this.loadDailyTasks()
        } else {
          this.activeProjectId = null
          this.tasks = []
          this.messages = []
        }
      }
    } catch (e) {
      console.error("فشل تحميل المشاريع", e)
    }
  },

  // Load Tasks
  async loadTasks() {
    if (!this.activeProjectId) return
    try {
      const res = await fetch(`${this.apiBase}/projects/${this.activeProjectId}/tasks`, {
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        const rawTasks = await res.json()
        if (Array.isArray(rawTasks)) {
          this.tasks = rawTasks.map(t => {
            const values = {}
            if (t.custom_field_values) {
              t.custom_field_values.forEach(v => {
                values[v.custom_field_definition_id] = v.value
              })
            }
            return {
              id: t.id,
              projectId: t.project_id,
              title: t.title,
              description: t.description,
              status: t.status,
              startDate: t.start_date,
              deadline: t.deadline,
              attachments: t.attachments || [],
              customFieldValues: values
            }
          })
          this.updateProjectTaskCounts()
        }
      }
    } catch (e) {
      console.error("فشل تحميل المهام", e)
    }
  },

  updateProjectTaskCounts() {
    if (!this.activeProjectId) return
    const activeProj = this.projects.find(p => p.id === this.activeProjectId)
    if (activeProj) {
      const counts = {};
      (activeProj.statuses || []).forEach(s => { counts[s] = 0 });
      (this.tasks || []).forEach(t => {
        counts[t.status] = (counts[t.status] || 0) + 1
      });
      activeProj.task_counts = counts
      activeProj.total_tasks_count = (this.tasks || []).length
    }
  },

  // Load Folders
  async loadFolders() {
    if (!this.activeProjectId) return
    try {
      const res = await fetch(`${this.apiBase}/projects/${this.activeProjectId}/folders`, {
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        this.folders = await res.json()
      }
    } catch (e) {
      console.error("خطأ في تحميل المجلدات", e)
    }
  },

  // Load Project Files
  async loadProjectFiles() {
    if (!this.activeProjectId) return
    try {
      const res = await fetch(`${this.apiBase}/projects/${this.activeProjectId}/project-files`, {
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        this.projectFiles = await res.json()
      }
    } catch (e) {
      console.error("خطأ في تحميل ملفات المشروع", e)
    }
  },

  // Reorder Projects
  reorderProjects(fromIndex, toIndex) {
    if (fromIndex < 0 || toIndex < 0 || fromIndex === toIndex) return
    const item = this.projects.splice(fromIndex, 1)[0]
    this.projects.splice(toIndex, 0, item)
    this.projects = [...this.projects]

    try {
      const orderIds = this.projects.map(p => p.id)
      localStorage.setItem('mymind_projects_order', JSON.stringify(orderIds))
    } catch (e) {
      console.error("فشل حفظ ترتيب المشاريع", e)
    }
  },

  // Load Notes

  async loadNotes() {
    if (!this.activeProjectId) return
    try {
      const res = await fetch(`${this.apiBase}/projects/${this.activeProjectId}/notes`, {
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        this.notes = await res.json()
      }
    } catch (e) {
      console.error("خطأ في تحميل الملاحظات", e)
    }
  },

  // Load Messages
  async loadMessages() {
    if (!this.activeProjectId) return
    try {
      const res = await fetch(`${this.apiBase}/projects/${this.activeProjectId}/messages`, {
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        const rawMsgs = await res.json()
        this.messages = Array.isArray(rawMsgs) ? rawMsgs : []
      }
    } catch (e) {
      console.error("خطأ في تحميل الرسائل", e)
      this.messages = []
    }
  },

  // Load Notifications
  async loadNotifications() {
    try {
      const res = await fetch(`${this.apiBase}/notifications`, {
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        const rawNotifs = await res.json()
        if (Array.isArray(rawNotifs)) {
          this.notifications = rawNotifs.map(n => ({
            id: n.id,
            title: n.title,
            text: n.text,
            isRead: n.is_read,
            timestamp: new Date(n.created_at).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' })
          }))
        }
      }
    } catch (e) {
      console.error("فشل تحميل الإشعارات", e)
    }
  },

  // Load digest details
  async loadDigestInfo() {
    try {
      const resQueue = await fetch(`${this.apiBase}/digest/queue`, {
        headers: this.getAuthHeaders()
      })
      if (resQueue.ok) {
        this.emailQueue = await resQueue.json()
      }
      const resEmails = await fetch(`${this.apiBase}/digest/emails`, {
        headers: this.getAuthHeaders()
      })
      if (resEmails.ok) {
        this.batchedEmails = await resEmails.json()
      }
    } catch (e) {
      console.error("فشل تحميل ملخص البريد", e)
    }
  },

  // Load Users/Members for mentions and assignment
  async loadUsers() {
    try {
      const res = await fetch(`${this.apiBase}/users`, {
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        const rawUsers = await res.json()
        this.users = rawUsers.map(u => ({
          id: u.id,
          name: u.name,
          email: u.email,
          roleName: u.role?.name || 'عضو'
        }))
      } else {
        this.setFallbackUsers()
      }
    } catch (e) {
      this.setFallbackUsers()
    }
  },

  setFallbackUsers() {
    this.users = [
      { id: 1, name: 'خالد', email: 'khaled@mymind.com', roleName: 'مدير' },
      { id: 2, name: 'سارة', email: 'sara@mymind.com', roleName: 'عضو' },
      { id: 3, name: 'أحمد', email: 'ahmed@mymind.com', roleName: 'مشاهد' }
    ]
  },

  // Create Project
  async createProject(name, description, projectTemplateId = null, memberIds = [], categoryId = null) {
    if (!this.hasPermission('manage-projects')) {
      alert("غير مصرح لك بإنشاء مشاريع.")
      return
    }

    try {
      const res = await fetch(`${this.apiBase}/projects`, {
        method: 'POST',
        headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({
          name,
          description,
          project_template_id: projectTemplateId ? parseInt(projectTemplateId) : null,
          member_ids: memberIds,
          category_id: categoryId ? parseInt(categoryId) : null
        })
      })

      if (res.ok) {
        const data = await res.json()
        await this.loadProjects()
        await this.loadProjectCategories()
        this.activeProjectId = data.id
        this.addNotification('إنشاء مشروع جديد', `تم إنشاء المشروع "${name}" بنجاح.`)
      }
    } catch (e) {
      console.error("خطأ في إنشاء المشروع", e)
    }
  },

  // Update Project
  async updateProject(id, name, description, memberIds = [], categoryId = undefined) {
    if (!this.hasPermission('manage-projects')) {
      alert("غير مصرح لك بتعديل المشاريع.")
      return
    }

    try {
      const body = { name, description, member_ids: memberIds }
      if (categoryId !== undefined) {
        body.category_id = categoryId ? parseInt(categoryId) : null
      }
      const res = await fetch(`${this.apiBase}/projects/${id}`, {
        method: 'PUT',
        headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(body)
      })

      if (res.ok) {
        await this.loadProjects()
        this.addNotification('تعديل مشروع', `تم تحديث تفاصيل المشروع "${name}" بنجاح.`)
      }
    } catch (e) {
      console.error("خطأ في تحديث المشروع", e)
    }
  },

  // Soft Delete Project
  async deleteProject(id) {
    if (!this.hasPermission('manage-projects')) {
      alert("غير مصرح لك بحذف المشاريع.")
      return
    }

    try {
      const res = await fetch(`${this.apiBase}/projects/${id}`, {
        method: 'DELETE',
        headers: this.getAuthHeaders()
      })

      if (res.ok) {
        const project = this.projects.find(p => p.id === id)
        await this.loadProjects()
        this.addNotification('نقل للمهملات', `تم نقل المشروع "${project?.name}" إلى سلة المهملات.`)
      }
    } catch (e) {
      console.error("خطأ في حذف المشروع", e)
    }
  },

  // Restore Project
  async restoreProject(id) {
    if (!this.hasPermission('manage-projects')) {
      alert("غير مصرح لك باستعادة المشاريع.")
      return
    }

    try {
      const res = await fetch(`${this.apiBase}/projects/${id}/restore`, {
        method: 'POST',
        headers: this.getAuthHeaders()
      })

      if (res.ok) {
        await this.loadProjects()
        const project = this.projects.find(p => p.id === id)
        this.addNotification('استعادة مشروع', `تمت استعادة المشروع "${project?.name}" بنجاح.`)
      }
    } catch (e) {
      console.error("خطأ في استعادة المشروع", e)
    }
  },

  // Create Task
  async createTask(title, description, status, startDate, deadline, customFieldsObj = {}) {
    if (!this.hasPermission('manage-tasks')) {
      alert("غير مصرح لك بإنشاء مهام.")
      return
    }

    try {
      const res = await fetch(`${this.apiBase}/projects/${this.activeProjectId}/tasks`, {
        method: 'POST',
        headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ title, description, status, start_date: startDate, deadline })
      })

      if (res.ok) {
        const task = await res.json()

        // Save custom fields value if present
        for (const [fieldId, val] of Object.entries(customFieldsObj)) {
          if (val) {
            await fetch(`${this.apiBase}/tasks/${task.id}/custom-fields`, {
              method: 'POST',
              headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
              body: JSON.stringify({ custom_field_definition_id: fieldId, value: String(val) })
            })
          }
        }

        await this.loadTasks()
        await this.loadDigestInfo()
        this.addNotification('مهمة جديدة', `تمت إضافة المهمة "${title}" إلى قائمة المهام.`)
      }
    } catch (e) {
      console.error("خطأ في إنشاء المهمة", e)
    }
  },

  // Update Task
  async updateTask(taskId, updates) {
    if (!this.hasPermission('manage-tasks')) {
      alert("غير مصرح لك بتعديل المهام.")
      return
    }

    try {
      const existingTask = this.tasks.find(t => String(t.id) === String(taskId))
      const body = {
        title: updates.title !== undefined ? updates.title : existingTask?.title,
        description: updates.description !== undefined ? updates.description : existingTask?.description,
        status: updates.status !== undefined ? updates.status : existingTask?.status,
        start_date: updates.startDate !== undefined ? updates.startDate : (updates.start_date !== undefined ? updates.start_date : existingTask?.startDate),
        deadline: updates.deadline !== undefined ? updates.deadline : existingTask?.deadline,
        project_id: updates.projectId !== undefined ? updates.projectId : existingTask?.projectId
      }

      const res = await fetch(`${this.apiBase}/tasks/${taskId}`, {
        method: 'PUT',
        headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify(body)
      })

      if (res.ok) {
        // Save custom field values if specified in updates
        if (updates.customFieldValues) {
          for (const [fieldId, val] of Object.entries(updates.customFieldValues)) {
            await fetch(`${this.apiBase}/tasks/${taskId}/custom-fields`, {
              method: 'POST',
              headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
              body: JSON.stringify({ custom_field_definition_id: fieldId, value: String(val) })
            })
          }
        }

        await this.loadTasks()
        await this.loadDigestInfo()
      }
    } catch (e) {
      console.error("خطأ في تعديل المهمة", e)
    }
  },

  // Delete Task
  async deleteTask(taskId) {
    if (!this.hasPermission('manage-tasks')) {
      alert("غير مصرح لك بحذف المهام.")
      return
    }

    try {
      const task = this.tasks.find(t => String(t.id) === String(taskId))
      const res = await fetch(`${this.apiBase}/tasks/${taskId}`, {
        method: 'DELETE',
        headers: this.getAuthHeaders()
      })

      if (res.ok) {
        await this.loadTasks()
        this.addNotification('حذف مهمة', `تمت إزالة المهمة "${task?.title}" نهائياً.`)
      }
    } catch (e) {
      console.error("خطأ في حذف المهمة", e)
    }
  },

  // Custom Fields Configurations
  async addCustomFieldToProject(projectId, name, type) {
    if (!this.hasPermission('manage-projects')) {
      alert("غير مصرح لك بإعداد الحقول المخصصة للمشروع.")
      return
    }

    try {
      const res = await fetch(`${this.apiBase}/projects/${projectId}/custom-fields`, {
        method: 'POST',
        headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ name, type })
      })

      if (res.ok) {
        await this.loadProjects()
      }
    } catch (e) {
      console.error("خطأ في إضافة الحقل المخصص", e)
    }
  },

  async removeCustomFieldFromProject(projectId, fieldId) {
    if (!this.hasPermission('manage-projects')) {
      alert("غير مصرح لك بحذف الحقول المخصصة للمشروع.")
      return
    }

    try {
      const res = await fetch(`${this.apiBase}/projects/${projectId}/custom-fields/${fieldId}`, {
        method: 'DELETE',
        headers: this.getAuthHeaders()
      })

      if (res.ok) {
        await this.loadProjects()
      }
    } catch (e) {
      console.error("خطأ في حذف الحقل المخصص", e)
    }
  },

  // Upload File Attachment
  async uploadFileToTask(taskId, name, size, simulateFailure) {
    // Generate dummy file blob to perform a real multipart upload to backend database validation
    const fileBlob = new Blob(['mymind file content dummy'], { type: 'image/png' })
    const formData = new FormData()
    formData.append('file', fileBlob, name)
    if (simulateFailure) {
      formData.append('simulate_failure', '1')
    }

    let interval = null
    // Set temp local preview for responsive progress feedback
    const task = this.tasks.find(t => String(t.id) === String(taskId))
    if (task) {
      if (!task.attachments) task.attachments = []
      const tempFile = { name, size, progress: 0, status: 'uploading' }
      task.attachments.push(tempFile)

      // Simulate step progress increments before API response return
      interval = setInterval(() => {
        if (tempFile.progress < 80) {
          tempFile.progress += 20
        } else if (interval) {
          clearInterval(interval)
          interval = null
        }
      }, 100)
    }

    try {
      const res = await fetch(`${this.apiBase}/tasks/${taskId}/attachments`, {
        method: 'POST',
        headers: this.getAuthHeaders(),
        body: formData
      })

      if (res.ok) {
        await this.loadTasks()
        this.addNotification('مرفق جديد', `تم إرفاق الملف "${name}" بنجاح في المهمة.`)
      } else {
        // Failed simulation response mapping
        await this.loadTasks()
      }
    } catch (e) {
      console.error("خطأ في رفع المرفق", e)
    } finally {
      if (interval) {
        clearInterval(interval)
        interval = null
      }
    }
  },

  // Delete Attachment
  async deleteAttachment(attachmentId) {
    try {
      const res = await fetch(`${this.apiBase}/attachments/${attachmentId}`, {
        method: 'DELETE',
        headers: this.getAuthHeaders()
      })

      if (res.ok) {
        await this.loadTasks()
      }
    } catch (e) {
      console.error("خطأ في حذف المرفق", e)
    }
  },

  // Create Folder
  async createFolder(name, parentId = null) {
    if (!this.activeProjectId) return
    try {
      const res = await fetch(`${this.apiBase}/projects/${this.activeProjectId}/folders`, {
        method: 'POST',
        headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ name, parent_id: parentId })
      })
      if (res.ok) {
        await this.loadFolders()
        this.addNotification('إنشاء مجلد', `تم إنشاء المجلد "${name}" بنجاح.`)
      }
    } catch (e) {
      console.error("خطأ في إنشاء المجلد", e)
    }
  },

  // Delete Folder
  async deleteFolder(id) {
    try {
      const res = await fetch(`${this.apiBase}/folders/${id}`, {
        method: 'DELETE',
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        await this.loadFolders()
        await this.loadProjectFiles()
        await this.loadNotes()
        this.addNotification('حذف مجلد', 'تم حذف المجلد وكافة محتوياته بنجاح.')
      }
    } catch (e) {
      console.error("خطأ في حذف المجلد", e)
    }
  },

  // Upload Project File
  async uploadProjectFile(file, folderId = null) {
    if (!this.activeProjectId) return
    const formData = new FormData()
    formData.append('file', file)
    if (folderId) {
      formData.append('folder_id', folderId)
    }

    try {
      const res = await fetch(`${this.apiBase}/projects/${this.activeProjectId}/project-files`, {
        method: 'POST',
        headers: this.getAuthHeaders(),
        body: formData
      })
      if (res.ok) {
        await this.loadProjectFiles()
        this.addNotification('ملف جديد', `تم رفع الملف "${file.name}" بنجاح.`)
      }
    } catch (e) {
      console.error("خطأ في رفع ملف المشروع", e)
    }
  },

  // Delete Project File
  async deleteProjectFile(id) {
    try {
      const res = await fetch(`${this.apiBase}/project-files/${id}`, {
        method: 'DELETE',
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        await this.loadProjectFiles()
        this.addNotification('حذف ملف', 'تم حذف الملف بنجاح.')
      }
    } catch (e) {
      console.error("خطأ في حذف ملف المشروع", e)
    }
  },

  // Create Note
  async createNote(title, content, folderId = null) {
    if (!this.activeProjectId) return
    try {
      const res = await fetch(`${this.apiBase}/projects/${this.activeProjectId}/notes`, {
        method: 'POST',
        headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ title, content, folder_id: folderId })
      })
      if (res.ok) {
        await this.loadNotes()
        this.addNotification('ملاحظة جديدة', `تم حفظ الملاحظة "${title}" بنجاح.`)
      }
    } catch (e) {
      console.error("خطأ في إنشاء الملاحظة", e)
    }
  },

  // Update Note
  async updateNote(id, title, content, folderId = null) {
    try {
      const res = await fetch(`${this.apiBase}/notes/${id}`, {
        method: 'PUT',
        headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ title, content, folder_id: folderId })
      })
      if (res.ok) {
        await this.loadNotes()
        this.addNotification('تعديل ملاحظة', `تم تحديث الملاحظة "${title}" بنجاح.`)
      }
    } catch (e) {
      console.error("خطأ في تحديث الملاحظة", e)
    }
  },

  // Delete Note
  async deleteNote(id) {
    try {
      const res = await fetch(`${this.apiBase}/notes/${id}`, {
        method: 'DELETE',
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        await this.loadNotes()
        this.addNotification('حذف ملاحظة', 'تم حذف الملاحظة بنجاح.')
      }
    } catch (e) {
      console.error("خطأ في حذف الملاحظة", e)
    }
  },

  // Mark single Notification read
  async markNotificationRead(notificationId) {
    try {
      const res = await fetch(`${this.apiBase}/notifications/${notificationId}/read`, {
        method: 'POST',
        headers: this.getAuthHeaders()
      })

      if (res.ok) {
        await this.loadNotifications()
      }
    } catch (e) {
      console.error("خطأ في تحديث الإشعار", e)
    }
  },

  // Mark all notifications read
  async markAllNotificationsRead() {
    try {
      const res = await fetch(`${this.apiBase}/notifications/read-all`, {
        method: 'POST',
        headers: this.getAuthHeaders()
      })

      if (res.ok) {
        await this.loadNotifications()
      }
    } catch (e) {
      console.error("خطأ في تحديث الإشعارات", e)
    }
  },

  // Trigger Send Batched Email Digest artisan command on backend
  async sendBatchedEmail() {
    try {
      const res = await fetch(`${this.apiBase}/digest/send`, {
        method: 'POST',
        headers: this.getAuthHeaders()
      })

      if (res.ok) {
        await this.loadDigestInfo()
      }
    } catch (e) {
      console.error("خطأ في إرسال الملخص المجمع", e)
    }
  },

  // Client-side quick notification generator (triggers push simulation)
  async addNotification(title, text) {
    try {
      const res = await fetch(`${this.apiBase}/notifications`, {
        method: 'POST',
        headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ title, text })
      })

      if (res.ok) {
        await this.loadNotifications()
        // Trigger browser push simulation
        if (this.pushPermission === 'granted') {
          console.log(`[إشعار سطح المكتب]: ${title} - ${text}`)
        }
      }
    } catch (e) {
      // Offline fallback
      const newNotif = {
        id: Date.now(),
        title,
        text,
        isRead: false,
        timestamp: new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' })
      }
      this.notifications.unshift(newNotif)
    }
  },

  // Push Permission Simulator
  requestPushPermission() {
    this.pushPermission = 'granted'
    this.addNotification('تفعيل التنبيهات', 'تم تفعيل إشعارات سطح المكتب بنجاح!')
  },

  denyPushPermission() {
    this.pushPermission = 'denied'
  },

  // Theme Toggler
  toggleTheme() {
    this.theme = this.theme === 'light' ? 'dark' : 'light'
    localStorage.setItem('mymind_theme', this.theme)
  },

  // Drawer Toggle
  toggleNotificationDrawer() {
    this.isNotificationDrawerOpen = !this.isNotificationDrawerOpen
    if (this.isNotificationDrawerOpen) {
      this.loadNotifications()
    }
  },

  // Offline / Online toggle simulation
  toggleOnline() {
    this.isOnline = !this.isOnline
  },

  // Delete status custom trigger helper
  async deleteProjectStatus(projectId, status, fallbackStatus) {
    if (!this.hasPermission('manage-projects')) {
      alert("غير مصرح لك بتعديل إعدادات المشروع.")
      return
    }

    try {
      const res = await fetch(`${this.apiBase}/projects/${projectId}/statuses`, {
        method: 'DELETE',
        headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ status, fallback_status: fallbackStatus })
      })

      if (res.ok) {
        await this.loadProjects()
        this.addNotification('حذف حالة', `تم إزالة الحالة "${status}" وتحديث مهام المشروع.`)
      } else {
        const data = await res.json()
        alert(data.error || 'فشل حذف الحالة.')
      }
    } catch (e) {
      console.error("خطأ في حذف الحالة", e)
    }
  },

  // Add status custom trigger helper
  async addProjectStatus(projectId, status) {
    if (!this.hasPermission('manage-projects')) {
      alert("غير مصرح لك بتعديل إعدادات المشروع.")
      return
    }

    try {
      const res = await fetch(`${this.apiBase}/projects/${projectId}/statuses`, {
        method: 'POST',
        headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ status })
      })

      if (res.ok) {
        await this.loadProjects()
        this.addNotification('إضافة حالة', `تم إضافة الحالة الجديدة "${status}" للمشروع.`)
      }
    } catch (e) {
      console.error("خطأ في إضافة الحالة", e)
    }
  },

  updateGlobalStatusName(oldName, newName) {
    const idx = this.globalStatuses.indexOf(oldName)
    if (idx !== -1) {
      this.globalStatuses[idx] = newName
    }
  },

  // Load Project Templates
  async loadProjectTemplates() {
    try {
      const res = await fetch(`${this.apiBase}/project-templates`, {
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        this.projectTemplates = await res.json()
      }
    } catch (e) {
      console.error("خطأ في تحميل قوالب المشاريع", e)
    }
  },

  // Create Project Template
  async createProjectTemplate(name, isDefault, statuses, taskTemplateIds) {
    try {
      const res = await fetch(`${this.apiBase}/project-templates`, {
        method: 'POST',
        headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ name, is_default: isDefault, statuses, task_template_ids: taskTemplateIds })
      })
      if (res.ok) {
        await this.loadProjectTemplates()
        this.addNotification('قالب مشروع جديد', `تم إنشاء قالب المشروع "${name}" بنجاح.`)
      }
    } catch (e) {
      console.error("خطأ في إنشاء قالب المشروع", e)
    }
  },

  // Update Project Template
  async updateProjectTemplate(id, name, isDefault, statuses, taskTemplateIds) {
    try {
      const res = await fetch(`${this.apiBase}/project-templates/${id}`, {
        method: 'PUT',
        headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ name, is_default: isDefault, statuses, task_template_ids: taskTemplateIds })
      })
      if (res.ok) {
        await this.loadProjectTemplates()
        this.addNotification('تعديل قالب المشروع', `تم تحديث قالب المشروع "${name}" بنجاح.`)
      }
    } catch (e) {
      console.error("خطأ في تحديث قالب المشروع", e)
    }
  },

  // Delete Project Template
  async deleteProjectTemplate(id) {
    try {
      const res = await fetch(`${this.apiBase}/project-templates/${id}`, {
        method: 'DELETE',
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        await this.loadProjectTemplates()
      }
    } catch (e) {
      console.error("خطأ في حذف قالب المشروع", e)
    }
  },

  // Set Project Template as Default
  async setProjectTemplateDefault(id) {
    try {
      const res = await fetch(`${this.apiBase}/project-templates/${id}/set-default`, {
        method: 'POST',
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        await this.loadProjectTemplates()
      }
    } catch (e) {
      console.error("خطأ في تحديد القالب الافتراضي", e)
    }
  },

  // Load Task Templates
  async loadTaskTemplates() {
    try {
      const res = await fetch(`${this.apiBase}/task-templates`, {
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        this.taskTemplates = await res.json()
      }
    } catch (e) {
      console.error("خطأ في تحميل قوالب المهام", e)
    }
  },

  // Create Task Template
  async createTaskTemplate(name, isDefault, title, description, status, customFieldsValues, priority, startDateOffset, dueDateOffset, attachments) {
    try {
      const res = await fetch(`${this.apiBase}/task-templates`, {
        method: 'POST',
        headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({
          name,
          is_default: isDefault,
          title,
          description,
          status,
          custom_fields_values: customFieldsValues,
          priority,
          start_date_offset: startDateOffset !== '' ? parseInt(startDateOffset) : null,
          due_date_offset: dueDateOffset !== '' ? parseInt(dueDateOffset) : null,
          attachments
        })
      })
      if (res.ok) {
        await this.loadTaskTemplates()
        this.addNotification('قالب مهمة جديد', `تم إنشاء قالب المهمة "${name}" بنجاح.`)
      }
    } catch (e) {
      console.error("خطأ في إنشاء قالب المهمة", e)
    }
  },

  // Update Task Template
  async updateTaskTemplate(id, name, isDefault, title, description, status, customFieldsValues, priority, startDateOffset, dueDateOffset, attachments) {
    try {
      const res = await fetch(`${this.apiBase}/task-templates/${id}`, {
        method: 'PUT',
        headers: this.getAuthHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({
          name,
          is_default: isDefault,
          title,
          description,
          status,
          custom_fields_values: customFieldsValues,
          priority,
          start_date_offset: startDateOffset !== '' && startDateOffset !== null ? parseInt(startDateOffset) : null,
          due_date_offset: dueDateOffset !== '' && dueDateOffset !== null ? parseInt(dueDateOffset) : null,
          attachments
        })
      })
      if (res.ok) {
        await this.loadTaskTemplates()
        this.addNotification('تعديل قالب المهمة', `تم تحديث قالب المهمة "${name}" بنجاح.`)
      }
    } catch (e) {
      console.error("خطأ في تحديث قالب المهمة", e)
    }
  },

  // Delete Task Template
  async deleteTaskTemplate(id) {
    try {
      const res = await fetch(`${this.apiBase}/task-templates/${id}`, {
        method: 'DELETE',
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        await this.loadTaskTemplates()
      }
    } catch (e) {
      console.error("خطأ في حذف قالب المهمة", e)
    }
  },

  // Set Task Template as Default
  async setTaskTemplateDefault(id) {
    try {
      const res = await fetch(`${this.apiBase}/task-templates/${id}/set-default`, {
        method: 'POST',
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        await this.loadTaskTemplates()
      }
    } catch (e) {
      console.error("خطأ في تحديد قالب المهمة الافتراضي", e)
    }
  },

  // Daily Tasks Management Methods (اليوميات)
  async loadDailyTasks() {
    try {
      const res = await fetch(`${this.apiBase}/daily-tasks`, {
        headers: this.getAuthHeaders()
      })
      if (res.ok) {
        const rawTasks = await res.json()
        if (Array.isArray(rawTasks)) {
          this.dailyTasks = rawTasks.map(t => ({
            id: t.id,
            title: t.title,
            category: t.category || 'عام',
            priority: t.priority || 'متوسطة',
            dueTime: t.due_time || '',
            completed: Boolean(t.completed),
            createdAt: t.created_at || new Date().toISOString()
          }))
          this.saveDailyTasks()
        }
      }
    } catch (e) {
      console.error('فشل تحميل اليوميات من السيرفر', e)
    }
  },

  saveDailyTasks() {
    try {
      localStorage.setItem('mymind_daily_tasks', JSON.stringify(this.dailyTasks))
    } catch (e) {
      console.error('فشل حفظ اليوميات في التخزين المحلي', e)
    }
  },

  async addDailyTask(taskData) {
    const tempId = Date.now()
    const newTask = {
      id: tempId,
      category: 'عام',
      priority: 'متوسطة',
      dueTime: '',
      completed: false,
      createdAt: new Date().toISOString(),
      ...taskData
    }
    this.dailyTasks = [newTask, ...this.dailyTasks]
    this.saveDailyTasks()

    try {
      const res = await fetch(`${this.apiBase}/daily-tasks`, {
        method: 'POST',
        headers: this.getAuthHeaders(),
        body: JSON.stringify({
          title: newTask.title,
          category: newTask.category,
          priority: newTask.priority,
          due_time: newTask.dueTime,
          completed: newTask.completed
        })
      })
      if (res.ok) {
        const created = await res.json()
        const idx = this.dailyTasks.findIndex(t => t.id === tempId)
        if (idx !== -1 && created.id) {
          this.dailyTasks[idx].id = created.id
          this.dailyTasks = [...this.dailyTasks]
          this.saveDailyTasks()
        }
      }
    } catch (e) {
      console.error('فشل إضافة المهمة اليومية إلى السيرفر', e)
    }
    return newTask
  },

  async toggleDailyTask(id) {
    const task = this.dailyTasks.find(t => String(t.id) === String(id))
    if (task) {
      task.completed = !task.completed
      this.dailyTasks = [...this.dailyTasks]
      this.saveDailyTasks()

      try {
        await fetch(`${this.apiBase}/daily-tasks/${id}`, {
          method: 'PUT',
          headers: this.getAuthHeaders(),
          body: JSON.stringify({ completed: task.completed })
        })
      } catch (e) {
        console.error('فشل تحديث المهمة اليومية على السيرفر', e)
      }
    }
  },

  async deleteDailyTask(id) {
    this.dailyTasks = this.dailyTasks.filter(t => String(t.id) !== String(id))
    this.saveDailyTasks()

    try {
      await fetch(`${this.apiBase}/daily-tasks/${id}`, {
        method: 'DELETE',
        headers: this.getAuthHeaders()
      })
    } catch (e) {
      console.error('فشل حذف المهمة اليومية من السيرفر', e)
    }
  },

  async updateDailyTask(id, data) {
    const taskIndex = this.dailyTasks.findIndex(t => String(t.id) === String(id))
    if (taskIndex !== -1) {
      this.dailyTasks[taskIndex] = { ...this.dailyTasks[taskIndex], ...data }
      this.dailyTasks = [...this.dailyTasks]
      this.saveDailyTasks()

      try {
        await fetch(`${this.apiBase}/daily-tasks/${id}`, {
          method: 'PUT',
          headers: this.getAuthHeaders(),
          body: JSON.stringify({
            title: data.title,
            category: data.category,
            priority: data.priority,
            due_time: data.dueTime,
            completed: data.completed
          })
        })
      } catch (e) {
        console.error('فشل تعديل المهمة اليومية على السيرفر', e)
      }
    }
  },

  // Daily Task Categories Management
  saveDailyTaskCategories() {
    try {
      localStorage.setItem('mymind_daily_task_categories', JSON.stringify(this.dailyTaskCategories))
    } catch (e) {
      console.error('فشل حفظ تصنيفات اليوميات في التخزين المحلي', e)
    }
  },

  addDailyTaskCategory(categoryName) {
    const name = categoryName ? categoryName.trim() : ''
    if (!name || this.dailyTaskCategories.includes(name)) return false
    this.dailyTaskCategories = [...this.dailyTaskCategories, name]
    this.saveDailyTaskCategories()
    return true
  },

  deleteDailyTaskCategory(categoryName) {
    if (categoryName === 'عام') return false
    this.dailyTaskCategories = this.dailyTaskCategories.filter(c => c !== categoryName)
    this.saveDailyTaskCategories()
    return true
  },

  // Habits Management Methods (يومياتي)
  saveHabits() {
    try {
      localStorage.setItem('mymind_habits', JSON.stringify(this.habits))
    } catch (e) {
      console.error('فشل حفظ العادات في التخزين المحلي', e)
    }
  },

  addHabit(habitData) {
    const newHabit = {
      id: habitData.id || (Date.now() + Math.random()),
      logs: {},
      frequency: [0, 1, 2, 3, 4, 5, 6],
      timeOfDay: 'anytime',
      type: 'boolean',
      targetValue: 1,
      unit: 'مرة',
      ...habitData
    }
    this.habits = [...this.habits, newHabit]
    this.saveHabits()
    return newHabit
  },


  deleteHabit(id) {
    this.habits = this.habits.filter(h => String(h.id) !== String(id))
    this.saveHabits()
  },

  toggleHabitLog(habitId, dateStr, value = null, note = null) {
    const habitIndex = this.habits.findIndex(h => String(h.id) === String(habitId))
    if (habitIndex === -1) return

    const habit = this.habits[habitIndex]
    const logs = habit.logs ? { ...habit.logs } : {}

    const currentLog = logs[dateStr] || { completed: false, count: 0, note: '' }
    
    let updatedLog
    if (habit.type === 'numeric') {
      let newCount = typeof value === 'number' ? value : (currentLog.count + 1)
      if (newCount < 0) newCount = 0
      const isDone = newCount >= (habit.targetValue || 1)
      updatedLog = {
        completed: isDone,
        count: newCount,
        note: note !== null ? note : (currentLog.note || ''),
        updatedAt: new Date().toISOString()
      }
    } else {
      const isCompleted = typeof value === 'boolean' ? value : !currentLog.completed
      updatedLog = {
        completed: isCompleted,
        count: isCompleted ? 1 : 0,
        note: note !== null ? note : (currentLog.note || ''),
        updatedAt: new Date().toISOString()
      }
    }

    logs[dateStr] = updatedLog
    habit.logs = logs
    this.habits = [...this.habits]
    this.saveHabits()
  },


  updateHabitNote(habitId, dateStr, note) {
    const habit = this.habits.find(h => String(h.id) === String(habitId))
    if (!habit) return
    if (!habit.logs) habit.logs = {}
    if (!habit.logs[dateStr]) {
      habit.logs[dateStr] = { completed: false, count: 0, note: note }
    } else {
      habit.logs[dateStr].note = note
    }
    this.habits = [...this.habits]
    this.saveHabits()
  },

  addHabitNote(habitId, content, dateStr = null) {
    const habit = this.habits.find(h => String(h.id) === String(habitId))
    if (!habit) return
    if (!habit.notesList) habit.notesList = []
    
    let formattedDate = dateStr
    if (!formattedDate) {
      const now = new Date()
      const y = now.getFullYear()
      const m = String(now.getMonth() + 1).padStart(2, '0')
      const d = String(now.getDate()).padStart(2, '0')
      formattedDate = `${y}-${m}-${d}`
    }

    const newNote = {
      id: Date.now(),
      content: content.trim(),
      dateStr: formattedDate,
      createdAt: new Date().toISOString()
    }
    habit.notesList.unshift(newNote)
    this.habits = [...this.habits]
    this.saveHabits()
    return newNote
  },

  deleteHabitNote(habitId, noteId) {
    const habit = this.habits.find(h => String(h.id) === String(habitId))
    if (!habit || !habit.notesList) return
    habit.notesList = habit.notesList.filter(n => String(n.id) !== String(noteId))
    this.habits = [...this.habits]
    this.saveHabits()
  },

  addHabitChecklistItem(habitId, title) {
    const habit = this.habits.find(h => String(h.id) === String(habitId))
    if (!habit) return
    if (!habit.checklist) habit.checklist = []
    
    const newItem = {
      id: Date.now(),
      title: title.trim(),
      completed: false
    }
    habit.checklist.push(newItem)
    this.habits = [...this.habits]
    this.saveHabits()
    return newItem
  },

  toggleHabitChecklistItem(habitId, itemId) {
    const habit = this.habits.find(h => String(h.id) === String(habitId))
    if (!habit || !habit.checklist) return
    const item = habit.checklist.find(i => String(i.id) === String(itemId))
    if (item) {
      item.completed = !item.completed
      this.habits = [...this.habits]
      this.saveHabits()
    }
  },

  deleteHabitChecklistItem(habitId, itemId) {
    const habit = this.habits.find(h => String(h.id) === String(habitId))
    if (!habit || !habit.checklist) return
    habit.checklist = habit.checklist.filter(i => String(i.id) !== String(itemId))
    this.habits = [...this.habits]
    this.saveHabits()
  }
})



// Auto init if token present
store.init()

watch(() => store.activeProjectId, (newVal) => {
  if (newVal) {
    store.loadTasks()
    store.loadFolders()
    store.loadProjectFiles()
    store.loadNotes()
    store.loadMessages()
    store.activeDocumentFolderId = null
  }
})
