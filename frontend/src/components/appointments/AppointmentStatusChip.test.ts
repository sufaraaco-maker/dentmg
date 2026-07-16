import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import AppointmentStatusChip from './AppointmentStatusChip.vue'

describe('AppointmentStatusChip', () => {
  it('renders the localized label for a status', () => {
    const wrapper = mount(AppointmentStatusChip, { props: { status: 'checked_in' } })
    expect(wrapper.text()).toContain('Checked In')
  })

  it('sets an aria-label so status is conveyed to screen readers, not just color', () => {
    const wrapper = mount(AppointmentStatusChip, { props: { status: 'no_show' } })
    expect(wrapper.find('[aria-label]').attributes('aria-label')).toBe('No Show')
  })
})
