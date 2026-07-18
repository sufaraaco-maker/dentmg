import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { describe, expect, it, vi } from 'vitest'
import { useCalendarKeyboardShortcuts } from './useCalendarKeyboardShortcuts'

function mountWithShortcuts(handlers: Parameters<typeof useCalendarKeyboardShortcuts>[0]) {
  return mount(
    defineComponent({
      setup() {
        useCalendarKeyboardShortcuts(handlers)
        return () => null
      },
    }),
  )
}

function fireKey(key: string, target: EventTarget = window) {
  const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true })
  target.dispatchEvent(event)
  return event
}

describe('useCalendarKeyboardShortcuts', () => {
  it('invokes the matching handler for each documented key', () => {
    const handlers = {
      onNewAppointment: vi.fn(),
      onPrevious: vi.fn(),
      onNext: vi.fn(),
      onToday: vi.fn(),
      onSwitchView: vi.fn(),
      onFocusSearch: vi.fn(),
      onShowHelp: vi.fn(),
    }
    mountWithShortcuts(handlers)

    fireKey('n')
    fireKey('ArrowLeft')
    fireKey('ArrowRight')
    fireKey('t')
    fireKey('3')
    fireKey('/')
    fireKey('?')

    expect(handlers.onNewAppointment).toHaveBeenCalledTimes(1)
    expect(handlers.onPrevious).toHaveBeenCalledTimes(1)
    expect(handlers.onNext).toHaveBeenCalledTimes(1)
    expect(handlers.onToday).toHaveBeenCalledTimes(1)
    expect(handlers.onSwitchView).toHaveBeenCalledWith(3)
    expect(handlers.onFocusSearch).toHaveBeenCalledTimes(1)
    expect(handlers.onShowHelp).toHaveBeenCalledTimes(1)
  })

  it('never fires while an input, textarea, or select has focus', () => {
    const onNewAppointment = vi.fn()
    mountWithShortcuts({
      onNewAppointment,
      onPrevious: vi.fn(),
      onNext: vi.fn(),
      onToday: vi.fn(),
      onSwitchView: vi.fn(),
      onFocusSearch: vi.fn(),
      onShowHelp: vi.fn(),
    })

    const input = document.createElement('input')
    document.body.appendChild(input)

    fireKey('n', input)

    expect(onNewAppointment).not.toHaveBeenCalled()
    input.remove()
  })

  it('never fires while a PrimeVue Dialog is open', () => {
    const onNewAppointment = vi.fn()
    mountWithShortcuts({
      onNewAppointment,
      onPrevious: vi.fn(),
      onNext: vi.fn(),
      onToday: vi.fn(),
      onSwitchView: vi.fn(),
      onFocusSearch: vi.fn(),
      onShowHelp: vi.fn(),
    })

    const dialog = document.createElement('div')
    dialog.className = 'p-dialog'
    document.body.appendChild(dialog)

    fireKey('n')

    expect(onNewAppointment).not.toHaveBeenCalled()
    dialog.remove()
  })

  it('ignores the shortcut when a modifier key is held (so browser/OS combos still work)', () => {
    const onNewAppointment = vi.fn()
    mountWithShortcuts({
      onNewAppointment,
      onPrevious: vi.fn(),
      onNext: vi.fn(),
      onToday: vi.fn(),
      onSwitchView: vi.fn(),
      onFocusSearch: vi.fn(),
      onShowHelp: vi.fn(),
    })

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'n', ctrlKey: true }))

    expect(onNewAppointment).not.toHaveBeenCalled()
  })

  it('removes its listener on unmount', () => {
    const onNewAppointment = vi.fn()
    const wrapper = mountWithShortcuts({
      onNewAppointment,
      onPrevious: vi.fn(),
      onNext: vi.fn(),
      onToday: vi.fn(),
      onSwitchView: vi.fn(),
      onFocusSearch: vi.fn(),
      onShowHelp: vi.fn(),
    })

    wrapper.unmount()
    fireKey('n')

    expect(onNewAppointment).not.toHaveBeenCalled()
  })
})
