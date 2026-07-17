import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import WorkingHoursDayRow from './WorkingHoursDayRow.vue'
import type { DentistWorkingHour } from '@/types/appointment'

function shift(overrides: Partial<DentistWorkingHour> = {}): DentistWorkingHour {
  return {
    id: 's1',
    user_id: 'd1',
    day_of_week: 1,
    start_time: '09:00',
    end_time: '17:00',
    is_active: true,
    ...overrides,
  }
}

describe('WorkingHoursDayRow', () => {
  it('shows "Not set" when a day has no shifts', () => {
    const wrapper = mount(WorkingHoursDayRow, {
      props: { dayOfWeek: 1, shifts: [], editable: true },
    })

    expect(wrapper.text()).toContain('Not set')
  })

  it('renders read-only rows when not editable, with no edit controls', () => {
    const wrapper = mount(WorkingHoursDayRow, {
      props: { dayOfWeek: 1, shifts: [shift()], editable: false },
    })

    expect(wrapper.text()).toContain('09:00')
    expect(wrapper.text()).toContain('17:00')
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it("strips the backend's seconds suffix in the read-only time display", () => {
    const wrapper = mount(WorkingHoursDayRow, {
      props: {
        dayOfWeek: 1,
        shifts: [shift({ start_time: '08:00:00', end_time: '18:00:00' })],
        editable: false,
      },
    })

    expect(wrapper.text()).toContain('08:00 – 18:00')
    expect(wrapper.text()).not.toContain('08:00:00')
  })

  it('emits update:shifts with the removal when deleting a persisted shift', async () => {
    const wrapper = mount(WorkingHoursDayRow, {
      props: { dayOfWeek: 1, shifts: [shift()], editable: true },
    })

    const deleteButton = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Delete')
    await deleteButton?.trigger('click')

    const emitted = wrapper.emitted('update:shifts')
    expect(emitted).toBeTruthy()
    expect(emitted?.[0][0]).toEqual([])
  })

  it('discards an unsaved new shift locally without emitting update:shifts', async () => {
    const wrapper = mount(WorkingHoursDayRow, {
      props: { dayOfWeek: 1, shifts: [], editable: true },
    })

    await wrapper.find('button').trigger('click') // "+ Add shift"
    expect(wrapper.findAll('button').length).toBeGreaterThan(1)

    const deleteButton = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Delete')
    await deleteButton?.trigger('click')

    expect(wrapper.emitted('update:shifts')).toBeFalsy()
    expect(wrapper.text()).toContain('Not set')
  })

  it('replaces a committed draft with its real persisted id once the parent confirms it, rather than stranding it as unsaved', async () => {
    const wrapper = mount(WorkingHoursDayRow, {
      props: { dayOfWeek: 1, shifts: [], editable: true },
    })

    await wrapper.find('button').trigger('click') // "+ Add shift"
    const saveButton = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Save')
    await saveButton?.trigger('click')
    expect(wrapper.emitted('update:shifts')).toBeTruthy()

    // Simulates the store round-tripping the create (e.g. via an unrelated day's edit forcing
    // the parent's whole shiftsByDay map to recompute) and handing back the real, persisted row.
    await wrapper.setProps({ shifts: [shift({ id: 'real-id' })] })

    const deleteButton = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Delete')
    await deleteButton?.trigger('click')

    // A real DELETE must be requested — if the draft were still stuck at `id: null`, this would
    // instead be silently dropped locally with no emission at all.
    const emitted = wrapper.emitted('update:shifts')
    expect(emitted?.[emitted.length - 1][0]).toEqual([])
  })

  it('keeps an unsaved new shift when the parent refreshes shifts from an unrelated fetch', async () => {
    const wrapper = mount(WorkingHoursDayRow, {
      props: { dayOfWeek: 1, shifts: [], editable: true },
    })

    await wrapper.find('button').trigger('click') // "+ Add shift"
    expect(wrapper.text()).not.toContain('Not set')

    // Simulates the initial background fetch resolving (with data for a *different* day)
    // after the user already started adding a draft here — the resync must not wipe it.
    await wrapper.setProps({ shifts: [] })

    expect(wrapper.text()).not.toContain('Not set')
    expect(wrapper.findAll('button').length).toBeGreaterThan(1)
  })

  it('emits copy-to with the selected target days', async () => {
    const wrapper = mount(WorkingHoursDayRow, {
      props: { dayOfWeek: 1, shifts: [shift()], editable: true },
    })

    const copyButton = wrapper.findAll('button').find((b) => b.text().includes('Copy to'))
    await copyButton?.trigger('click')
    await wrapper.vm.$nextTick()

    await wrapper.findComponent({ name: 'MultiSelect' }).vm.$emit('update:modelValue', [2, 3])

    const confirmButton = wrapper.findAll('button').find((b) => b.text() === 'Copy')
    await confirmButton?.trigger('click')

    expect(wrapper.emitted('copy-to')?.[0][0]).toEqual([2, 3])
  })
})
