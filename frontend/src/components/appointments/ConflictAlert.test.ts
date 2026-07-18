import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ConflictAlert from './ConflictAlert.vue'

describe('ConflictAlert', () => {
  it('renders nothing when there is no error', () => {
    const wrapper = mount(ConflictAlert, { props: { error: null } })
    expect(wrapper.findComponent({ name: 'Message' }).exists()).toBe(false)
  })

  it('shows a hard-stop banner with no override button for a dentist conflict', () => {
    const wrapper = mount(ConflictAlert, {
      props: { error: { message: 'Dentist already booked', code: 'dentist_conflict' } },
    })

    expect(wrapper.text()).toContain('Dentist already booked')
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('announces a hard-stop conflict assertively (role="alert", design doc §14)', () => {
    const wrapper = mount(ConflictAlert, {
      props: { error: { message: 'Dentist already booked', code: 'dentist_conflict' } },
    })

    const root = wrapper.find('.p-message')
    expect(root.attributes('role')).toBe('alert')
    expect(root.attributes('aria-live')).toBe('assertive')
  })

  it('announces an overridable soft warning politely (role="status", design doc §14)', () => {
    const wrapper = mount(ConflictAlert, {
      props: {
        error: {
          message: 'Patient already booked',
          code: 'patient_conflict',
          overridable: true,
          override_field: 'override_patient_conflict',
        },
      },
    })

    const root = wrapper.find('.p-message')
    expect(root.attributes('role')).toBe('status')
    expect(root.attributes('aria-live')).toBe('polite')
  })

  it('shows a "Book Anyway" button for an overridable patient conflict', async () => {
    const wrapper = mount(ConflictAlert, {
      props: {
        error: {
          message: 'Patient already booked',
          code: 'patient_conflict',
          overridable: true,
          override_field: 'override_patient_conflict',
        },
      },
    })

    const button = wrapper.find('button')
    expect(button.exists()).toBe(true)

    await button.trigger('click')
    expect(wrapper.emitted('override')?.[0]).toEqual(['override_patient_conflict'])
  })
})
