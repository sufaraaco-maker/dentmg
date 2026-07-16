import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import AppointmentEventContent from './AppointmentEventContent.vue'
import type { AppointmentEventData } from './AppointmentEventContent.vue'

function makeEvent(overrides: Partial<AppointmentEventData> = {}): AppointmentEventData {
  return {
    id: 'a1',
    patientName: 'Jane Doe',
    status: 'scheduled',
    timeText: '9:00 - 9:30 AM',
    hasNotes: false,
    ...overrides,
  }
}

describe('AppointmentEventContent', () => {
  it('renders the patient name, time, and status chip', () => {
    const wrapper = mount(AppointmentEventContent, { props: { event: makeEvent() } })
    expect(wrapper.text()).toContain('Jane Doe')
    expect(wrapper.text()).toContain('9:00 - 9:30 AM')
    expect(wrapper.text()).toContain('Scheduled')
  })

  it('shows the dentist name only when provided', () => {
    const withoutDentist = mount(AppointmentEventContent, { props: { event: makeEvent() } })
    expect(withoutDentist.text()).not.toContain('Dr.')

    const withDentist = mount(AppointmentEventContent, {
      props: { event: makeEvent({ dentistName: 'Dr. Smith' }) },
    })
    expect(withDentist.text()).toContain('Dr. Smith')
  })

  it('emits activate on click', async () => {
    const wrapper = mount(AppointmentEventContent, { props: { event: makeEvent() } })
    await wrapper.trigger('click')
    expect(wrapper.emitted('activate')).toHaveLength(1)
  })

  it('emits activate on Enter/Space so events are keyboard-operable, not mouse-only', async () => {
    const wrapper = mount(AppointmentEventContent, { props: { event: makeEvent() } })
    await wrapper.trigger('keydown', { key: 'Enter' })
    await wrapper.trigger('keydown', { key: ' ' })
    expect(wrapper.emitted('activate')).toHaveLength(2)
  })

  it('is a real tabbable element, not a mouse-only div', () => {
    const wrapper = mount(AppointmentEventContent, { props: { event: makeEvent() } })
    expect(wrapper.attributes('tabindex')).toBe('0')
  })
})
