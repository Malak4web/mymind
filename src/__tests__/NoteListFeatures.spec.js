import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { store } from '../store'
import NoteContentRenderer from '../components/NoteContentRenderer.vue'
import MentionInput from '../components/MentionInput.vue'
import ProjectDocuments from '../components/ProjectDocuments.vue'

describe('Note List, Hyperlink, and Copy Features', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    store.activeProjectId = 1
    store.activeDocumentFolderId = null
    store.folders = []
    store.projectFiles = []
    store.notes = []

    // Mock clipboard
    Object.assign(navigator, {
      clipboard: {
        writeText: vi.fn().mockResolvedValue()
      }
    })
  })

  describe('1. NoteContentRenderer Component', () => {
    it('parses bullet and numbered list items and renders copy button for each item', () => {
      const content = `ملاحظة هامة:
- عنصر أول
- عنصر ثاني
1. خطوة رقم واحد
2. خطوة رقم اثنين`

      const wrapper = mount(NoteContentRenderer, {
        props: { content }
      })

      const listItems = wrapper.findAll('.group\\/item')
      expect(listItems.length).toBe(4) // 2 bullet + 2 numbered

      const copyBtns = wrapper.findAll('.copy-item-btn')
      expect(copyBtns.length).toBe(4) // Each list item has its own copy button
    })

    it('copies the specific item content when copy button is clicked', async () => {
      const content = `- كود التفعيل: ABC-123\n- رابط الخادم: [جوجل](https://google.com)`

      const wrapper = mount(NoteContentRenderer, {
        props: { content }
      })

      const copyBtns = wrapper.findAll('.copy-item-btn')
      expect(copyBtns.length).toBe(2)

      // Click copy on first item
      await copyBtns[0].trigger('click')
      expect(navigator.clipboard.writeText).toHaveBeenCalledWith('كود التفعيل: ABC-123')

      // Visual checkmark feedback
      await nextTick()
      expect(copyBtns[0].classes()).toContain('bg-emerald-500/15')

      // Click copy on second item
      await copyBtns[1].trigger('click')
      expect(navigator.clipboard.writeText).toHaveBeenCalledWith('رابط الخادم: جوجل (https://google.com)')
    })

    it('copies URL directly when list item is purely a markdown link', async () => {
      const content = `- [المستودع](https://github.com/project)`

      const wrapper = mount(NoteContentRenderer, {
        props: { content }
      })

      const copyBtn = wrapper.find('.copy-item-btn')
      await copyBtn.trigger('click')
      expect(navigator.clipboard.writeText).toHaveBeenCalledWith('https://github.com/project')
    })

    it('respects maxItems and displays remaining items badge', () => {
      const content = `- بند 1\n- بند 2\n- بند 3\n- بند 4\n- بند 5`

      const wrapper = mount(NoteContentRenderer, {
        props: { content, maxItems: 3 }
      })

      const listItems = wrapper.findAll('.group\\/item')
      expect(listItems.length).toBe(3)
      expect(wrapper.text()).toContain('+ 2 عنصر إضافي...')
    })
  })

  describe('2. MentionInput with multilineEnter and Selection API', () => {
    it('auto-continues bullet lists upon pressing Enter', async () => {
      const wrapper = mount(MentionInput, {
        props: {
          modelValue: '- العنصر الأول',
          isTextarea: true,
          multilineEnter: true
        }
      })

      const textarea = wrapper.find('textarea')
      const el = textarea.element
      el.selectionStart = el.value.length
      el.selectionEnd = el.value.length

      await textarea.trigger('keydown', { key: 'Enter' })

      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      const emittedVal = wrapper.emitted('update:modelValue')[0][0]
      expect(emittedVal).toBe('- العنصر الأول\n- ')
    })

    it('auto-continues numbered lists with incremented number upon pressing Enter', async () => {
      const wrapper = mount(MentionInput, {
        props: {
          modelValue: '1. الخطوة الأولى',
          isTextarea: true,
          multilineEnter: true
        }
      })

      const textarea = wrapper.find('textarea')
      const el = textarea.element
      el.selectionStart = el.value.length
      el.selectionEnd = el.value.length

      await textarea.trigger('keydown', { key: 'Enter' })

      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      const emittedVal = wrapper.emitted('update:modelValue')[0][0]
      expect(emittedVal).toBe('1. الخطوة الأولى\n2. ')
    })

    it('exits list mode when pressing Enter on an empty bullet line', async () => {
      const wrapper = mount(MentionInput, {
        props: {
          modelValue: '- بند سابق\n- ',
          isTextarea: true,
          multilineEnter: true
        }
      })

      const textarea = wrapper.find('textarea')
      const el = textarea.element
      el.selectionStart = el.value.length
      el.selectionEnd = el.value.length

      await textarea.trigger('keydown', { key: 'Enter' })

      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      const emittedVal = wrapper.emitted('update:modelValue')[0][0]
      expect(emittedVal).toBe('- بند سابق\n')
    })

    it('exposes getSelection and replaceSelection methods for toolbar integration', async () => {
      const wrapper = mount(MentionInput, {
        props: {
          modelValue: 'نص تجريبي للرابط',
          isTextarea: true
        }
      })

      const el = wrapper.find('textarea').element
      el.selectionStart = 0
      el.selectionEnd = 2

      const sel = wrapper.vm.getSelection()
      expect(sel.text).toBe('نص')

      wrapper.vm.replaceSelection('[نص](https://example.com)')
      await nextTick()

      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      const lastEmitted = wrapper.emitted('update:modelValue').slice(-1)[0][0]
      expect(lastEmitted).toContain('[نص](https://example.com)')
    })
  })

  describe('3. ProjectDocuments Notes Integration', () => {
    it('renders note cards with copy full note button and per-item copy buttons', async () => {
      store.notes = [
        {
          id: 1,
          title: 'ملاحظة الروابط والمهام',
          content: '- مهمة 1\n- مهمة 2\n- رابط [التطبيق](https://app.com)',
          folder_id: null
        }
      ]

      const wrapper = mount(ProjectDocuments)
      await nextTick()

      // Full note copy button on the card
      const fullCopyBtn = wrapper.find('button[title="نسخ محتوى الملاحظة بالكامل"]')
      expect(fullCopyBtn.exists()).toBe(true)

      await fullCopyBtn.trigger('click')
      expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
        expect.stringContaining('مهمة 1')
      )

      // Per-item copy buttons inside the note card
      const itemCopyBtns = wrapper.findAll('.copy-item-btn')
      expect(itemCopyBtns.length).toBeGreaterThanOrEqual(3)

      await itemCopyBtns[0].trigger('click')
      expect(navigator.clipboard.writeText).toHaveBeenCalledWith('مهمة 1')
    })

    it('supports inserting bullet lists and hyperlinks inside note editor modal', async () => {
      const wrapper = mount(ProjectDocuments)
      await nextTick()

      // Open new note modal
      const newNoteBtn = wrapper.findAll('button').find(b => b.text().includes('ملاحظة جديدة'))
      expect(newNoteBtn.exists()).toBe(true)
      await newNoteBtn.trigger('click')
      await nextTick()

      // Check toolbar buttons
      const bulletBtn = wrapper.find('button[title="إدراج قائمة نقطية"]')
      expect(bulletBtn.exists()).toBe(true)
      await bulletBtn.trigger('click')
      await nextTick()

      // Link button
      const linkBtn = wrapper.find('button[title*="رابط تشعبي"]')
      expect(linkBtn.exists()).toBe(true)
      await linkBtn.trigger('click')
      await nextTick()

      // Link popover dialog should be visible
      expect(wrapper.text()).toContain('إضافة رابط تشعبي')

      // Fill in link and apply
      const urlInput = wrapper.find('input[placeholder="https://example.com"]')
      expect(urlInput.exists()).toBe(true)
      await urlInput.setValue('https://mysite.com')

      const applyBtn = wrapper.findAll('button').find(b => b.text().includes('تطبيق الرابط'))
      expect(applyBtn.exists()).toBe(true)
      await applyBtn.trigger('click')
      await nextTick()

      // The popover should close
      expect(wrapper.find('input[placeholder="https://example.com"]').exists()).toBe(false)
    })
  })
})
