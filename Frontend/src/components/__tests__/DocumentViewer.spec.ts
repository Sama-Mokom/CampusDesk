/**
 * Unit tests for DocumentViewer.vue
 *
 * Validates: Requirements 1.2, 3.4, 3.5, 3.6
 */
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import DocumentViewer from '../DocumentViewer.vue'
import type { Attachment } from '../../types'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeAttachment(overrides: Partial<Attachment> = {}): Attachment {
  return {
    id: 1,
    file_path: 'http://localhost/storage/test-file',
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
      file_path: 'http://localhost/storage/photo.png',
    })

    const wrapper = mount(DocumentViewer, {
      props: { attachments: [imageAttachment] },
    })

    // Click to select the file
    await wrapper.find('button[type="button"]').trigger('click')

    expect(wrapper.find('img').exists()).toBe(true)
    expect(wrapper.find('img').attributes('src')).toBe(imageAttachment.file_path)
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
      file_path: 'http://localhost/storage/document.pdf',
    })

    const wrapper = mount(DocumentViewer, {
      props: { attachments: [pdfAttachment] },
    })

    await wrapper.find('button[type="button"]').trigger('click')

    expect(wrapper.find('iframe').exists()).toBe(true)
    expect(wrapper.find('iframe').attributes('src')).toBe(pdfAttachment.file_path)
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
      file_path: 'http://localhost/storage/notes.txt',
    })

    const wrapper = mount(DocumentViewer, {
      props: { attachments: [textAttachment] },
    })

    await wrapper.find('button[type="button"]').trigger('click')

    expect(wrapper.text()).toContain('Preview not available for this file type.')
    // Should not render image or iframe
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('iframe').exists()).toBe(false)
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

    const fileButton = wrapper.find('button[type="button"]')

    // First click — opens viewer
    await fileButton.trigger('click')
    expect(wrapper.find('img').exists()).toBe(true)

    // Second click — closes viewer
    await fileButton.trigger('click')
    expect(wrapper.find('img').exists()).toBe(false)
  })
})
