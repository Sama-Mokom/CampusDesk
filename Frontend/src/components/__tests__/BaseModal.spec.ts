import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import BaseModal from '../ui/BaseModal.vue'

afterEach(() => {
  document.body.innerHTML = ''
  document.body.style.overflow = ''
})
describe('Shared modal accessibility', () => {
  it('names the dialog, locks body scroll, contains keyboard focus and restores the trigger', async () => {
    const trigger = document.createElement('button')
    document.body.append(trigger)
    trigger.focus()
    const wrapper = mount(BaseModal, {
      attachTo: document.body,
      props: { open: false, title: 'Review request' },
      slots: {
        default: '<input aria-label="Note">',
        footer: '<button id="last-action">Save</button>'
      }
    })
    await wrapper.setProps({ open: true })
    await nextTick()
    const dialog = document.querySelector('dialog')!
    expect(dialog.open).toBe(true)
    expect(
      document.getElementById(dialog.getAttribute('aria-labelledby')!)
        ?.textContent
    ).toBe('Review request')
    expect(document.body.style.overflow).toBe('hidden')
    expect(document.activeElement).toBe(dialog)
    const last = document.getElementById('last-action')!
    last.focus()
    last.dispatchEvent(
      new KeyboardEvent('keydown', {
        key: 'Tab',
        bubbles: true,
        cancelable: true
      })
    )
    expect(document.activeElement?.getAttribute('aria-label')).toBe(
      'Close dialog'
    )
    document.activeElement?.dispatchEvent(
      new KeyboardEvent('keydown', {
        key: 'Tab',
        shiftKey: true,
        bubbles: true,
        cancelable: true
      })
    )
    expect(document.activeElement).toBe(last)
    await wrapper.setProps({ open: false })
    expect(document.body.style.overflow).toBe('')
    expect(document.activeElement).toBe(trigger)
    wrapper.unmount()
  })
  it('closes on native Escape cancellation and backdrop, but keeps pending actions open', async () => {
    const wrapper = mount(BaseModal, {
      props: { open: true, title: 'Resolve', busy: true },
      global: { stubs: { teleport: true } }
    })
    await nextTick()
    await wrapper.get('dialog').trigger('cancel')
    await wrapper.get('dialog').trigger('click')
    expect(wrapper.emitted('close')).toBeUndefined()
    await wrapper.setProps({ busy: false })
    await wrapper.get('dialog').trigger('cancel')
    expect(wrapper.emitted('close')).toHaveLength(1)
    await wrapper.get('dialog').trigger('click')
    expect(wrapper.emitted('close')).toHaveLength(2)
    wrapper.unmount()
  })
})
