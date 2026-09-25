/**
 * Unit tests for DocumentViewer.vue
 *
 * Validates: Requirements 1.2, 3.4, 3.5, 3.6
 */
import { describe, it, expect, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import DocumentViewer from '../DocumentViewer.vue'
import type { Attachment } from '../../types'
import api from '../../services/api'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeAttachment(overrides: Partial<Attachment> = {}): Attachment {
  return {
    id: 1,
    file_path: 'protected/attachments/test-file',
    original_name: 'test-file.png',
    mime_type: 'image/png',
    ...overrides,
  }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('DocumentViewer', () => {
  /**
   * Test 1 — empty-state message
   * Validates: Requirement 1.2 / 3.6
   */
  it('renders the empty-state message when attachments is empty', () => {
    const wrapper = mount(DocumentViewer, {
      props: { attachments: [] },
    })

    expect(wrapper.text()).toContain('No attachments uploaded for this request.')
  })

  /**
   * Test 2 — image attachment shows <img>
   * Validates: Requirement 3.4
   */
  it('renders an <img> element when an image attachment is selected', async () => {
    const imageAttachment = makeAttachment({
      id: 2,
      original_name: 'photo.png',
      mime_type: 'image/png',
      file_path: 'protected/attachments/photo.png',
    })

    const wrapper = mount(DocumentViewer, {
      props: { attachments: [imageAttachment] },
    })
    const get = vi.spyOn(api, 'get').mockResolvedValue({
      data: new Blob(['image data'], { type: 'image/png' }),
    } as never)
    const createObjectURL = vi.fn().mockReturnValue('blob:photo-preview')
    const previousCreateObjectURL = URL.createObjectURL
    URL.createObjectURL = createObjectURL

    try {
      // The preview is available only after the protected attachment fetch completes.
      await wrapper.find('button[type="button"]').trigger('click')
      await flushPromises()

      expect(get).toHaveBeenCalledWith('/attachments/2', { responseType: 'blob' })
      expect(createObjectURL).toHaveBeenCalled()
      expect(wrapper.find('img').attributes('src')).toBe('blob:photo-preview')
    } finally {
      URL.createObjectURL = previousCreateObjectURL
      get.mockRestore()
    }
  })

  /**
   * Test 3 — PDF attachment shows <iframe>
   * Validates: Requirement 3.4
   */
  it('renders an <iframe> element when a PDF attachment is selected', async () => {
    const pdfAttachment = makeAttachment({
      id: 3,
      original_name: 'document.pdf',
      mime_type: 'application/pdf',
      file_path: 'protected/attachments/document.pdf',
    })

    const wrapper = mount(DocumentViewer, {
      props: { attachments: [pdfAttachment] },
    })
    const get = vi.spyOn(api, 'get').mockResolvedValue({
      data: new Blob(['pdf data'], { type: 'application/pdf' }),
    } as never)
    const previousCreateObjectURL = URL.createObjectURL
    URL.createObjectURL = vi.fn().mockReturnValue('blob:document-preview')

    try {
      await wrapper.find('button[type="button"]').trigger('click')
      await flushPromises()

      expect(wrapper.find('iframe').attributes('src')).toBe('blob:document-preview')
    } finally {
      URL.createObjectURL = previousCreateObjectURL
      get.mockRestore()
    }
  })

  /**
   * Test 4 — unsupported file type shows download fallback
   * Validates: Requirement 3.5
   */
  it('renders the download fallback UI for unsupported file types', async () => {
    const textAttachment = makeAttachment({
      id: 4,
      original_name: 'notes.txt',
      mime_type: 'text/plain',
      file_path: 'protected/attachments/notes.txt',
    })

    const wrapper = mount(DocumentViewer, {
      props: { attachments: [textAttachment] },
    })
    const get = vi.spyOn(api, 'get').mockResolvedValue({
      data: new Blob(['text data'], { type: 'text/plain' }),
    } as never)
    const previousCreateObjectURL = URL.createObjectURL
    URL.createObjectURL = vi.fn().mockReturnValue('blob:text-preview')

    try {
      await wrapper.find('button[type="button"]').trigger('click')
      await flushPromises()

      expect(wrapper.text()).toContain('Preview not available for this file type.')
      expect(wrapper.find('img').exists()).toBe(false)
      expect(wrapper.find('iframe').exists()).toBe(false)
    } finally {
      URL.createObjectURL = previousCreateObjectURL
      get.mockRestore()
    }
  })

  /**
   * Test 5 — clicking an already-active file collapses the viewer (toggle)
   * Validates: Requirement 3.7
   */
  it('collapses the viewer when the active file is clicked again', async () => {
    const imageAttachment = makeAttachment({
      id: 5,
      original_name: 'toggle-test.png',
      mime_type: 'image/png',
    })

    const wrapper = mount(DocumentViewer, {
      props: { attachments: [imageAttachment] },
    })

    const get = vi.spyOn(api, 'get').mockResolvedValue({
      data: new Blob(['image data'], { type: 'image/png' }),
    } as never)
    const previousCreateObjectURL = URL.createObjectURL
    const previousRevokeObjectURL = URL.revokeObjectURL
    URL.createObjectURL = vi.fn().mockReturnValue('blob:toggle-preview')
    URL.revokeObjectURL = vi.fn()

    try {
      const fileButton = wrapper.find('button[type="button"]')

    // First click — opens viewer
    await fileButton.trigger('click')
    await flushPromises()
    expect(wrapper.find('img').exists()).toBe(true)

    // Second click — closes viewer
      await fileButton.trigger('click')
      expect(wrapper.find('img').exists()).toBe(false)
    } finally {
      URL.createObjectURL = previousCreateObjectURL
      URL.revokeObjectURL = previousRevokeObjectURL
      get.mockRestore()
    }
  })
})
