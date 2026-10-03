import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import DocumentViewer from '../DocumentViewer.vue'
import api from '@/services/api'

const files = [
  {
    id: 1,
    original_name: 'first.pdf',
    mime_type: 'application/pdf',
    file_path: 'private/first'
  },
  {
    id: 2,
    original_name: 'second.pdf',
    mime_type: 'application/pdf',
    file_path: 'private/second'
  }
]
beforeEach(() => {
  vi.stubGlobal('URL', {
    createObjectURL: vi.fn().mockReturnValue('blob:protected-file'),
    revokeObjectURL: vi.fn()
  })
})
afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})
describe('Protected attachment lifecycle', () => {
  it('keeps a failed file selected, reports the error and retries the authenticated endpoint', async () => {
    const get = vi
      .spyOn(api, 'get')
      .mockRejectedValueOnce(new Error('Offline'))
      .mockResolvedValueOnce({ data: new Blob(['pdf']) })
    const wrapper = mount(DocumentViewer, { props: { attachments: files } })
    await wrapper.findAll('button')[0]!.trigger('click')
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain(
      'could not be loaded'
    )
    expect(wrapper.find('a[download]').exists()).toBe(false)
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Retry document')!
      .trigger('click')
    await flushPromises()
    expect(get).toHaveBeenLastCalledWith('/attachments/1', {
      responseType: 'blob'
    })
    expect(wrapper.get('iframe').attributes('src')).toBe('blob:protected-file')
    wrapper.unmount()
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:protected-file')
  })
  it('ignores late responses when a different document has been selected', async () => {
    let finishFirst!: (data: { data: Blob }) => void
    vi.spyOn(api, 'get')
      .mockImplementationOnce(
        () =>
          new Promise((resolve) => {
            finishFirst = resolve
          })
      )
      .mockResolvedValueOnce({ data: new Blob(['second']) })
    const wrapper = mount(DocumentViewer, { props: { attachments: files } })
    await wrapper.findAll('button')[0]!.trigger('click')
    await wrapper.findAll('button')[1]!.trigger('click')
    await flushPromises()
    finishFirst({ data: new Blob(['first']) })
    await flushPromises()
    expect(URL.createObjectURL).toHaveBeenCalledTimes(1)
    expect(wrapper.get('iframe').attributes('title')).toBe('second.pdf')
    wrapper.unmount()
  })
  it('does not create a preview after closing while a download is pending', async () => {
    let finish!: (data: { data: Blob }) => void
    vi.spyOn(api, 'get').mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          finish = resolve
        })
    )
    const wrapper = mount(DocumentViewer, { props: { attachments: files } })
    await wrapper.findAll('button')[0]!.trigger('click')
    await wrapper.get('[aria-label="Close document preview"]').trigger('click')
    finish({ data: new Blob(['pdf']) })
    await flushPromises()
    expect(URL.createObjectURL).not.toHaveBeenCalled()
    expect(wrapper.find('iframe').exists()).toBe(false)
    wrapper.unmount()
  })
})
