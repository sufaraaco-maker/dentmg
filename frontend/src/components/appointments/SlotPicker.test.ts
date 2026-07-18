import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import SlotPicker from './SlotPicker.vue'
import { appointmentsApi, workingHoursApi } from '@/services/appointments'
import { useWorkingHoursStore } from '@/stores/workingHours'
import { toCalendarUtcDate } from '@/lib/date'

vi.mock('@/services/appointments', () => ({
  appointmentsApi: { availableSlots: vi.fn() },
  workingHoursApi: { listForDentist: vi.fn() },
}))

const DATE = new Date(2026, 6, 20, 0, 0, 0, 0)

beforeEach(() => {
  setActivePinia(createPinia())
  // SlotPicker now fetches working hours for its own `dentistId` itself (a real bug: it used to
  // rely entirely on the Board's unrelated Dentist filter having already populated the store) —
  // give every test a resolved default so that fetch doesn't hang/reject unless a test overrides it.
  vi.mocked(workingHoursApi.listForDentist).mockResolvedValue([])
})

function nineAm(): Date {
  const slot = new Date(DATE)
  slot.setHours(9, 0, 0, 0)
  return slot
}

describe('SlotPicker', () => {
  it('renders candidate slots from working hours and marks the returned ones available', async () => {
    const workingHours = useWorkingHoursStore()
    workingHours.byDentist.set('d1', [
      {
        id: 'wh1',
        user_id: 'd1',
        day_of_week: DATE.getDay(),
        start_time: '09:00',
        end_time: '10:00',
        is_active: true,
      },
    ])
    // The real backend sends naive-digits-labeled timestamps (lib/date.ts), not a real UTC
    // conversion of `nineAm()` — `toCalendarUtcDate(...).toISOString()` produces exactly that
    // wire shape, so this test exercises the same `parseServerDateTime` path production hits
    // and stays correct regardless of the host machine's own timezone.
    vi.mocked(appointmentsApi.availableSlots).mockResolvedValue([toCalendarUtcDate(nineAm()).toISOString()])

    const wrapper = mount(SlotPicker, { props: { dentistId: 'd1', date: DATE, durationMinutes: 30 } })
    await flushPromises()

    const buttons = wrapper.findAll('button')
    expect(buttons.length).toBeGreaterThan(0)

    // A disabled native <button> renders `disabled=""` — an empty string, which is falsy in JS —
    // so "not disabled" must check for the attribute's absence (undefined), not just falsiness.
    const enabled = buttons.filter((b) => b.attributes('disabled') === undefined)
    expect(enabled).toHaveLength(1)
    expect(enabled[0].attributes('aria-pressed')).toBe('false')

    const disabled = buttons.find((b) => b.attributes('disabled') !== undefined)
    expect(disabled?.attributes('aria-disabled')).toBe('true')

    await enabled[0].trigger('click')
    expect(wrapper.emitted('slot-selected')?.[0][0]).toEqual(nineAm())
    expect(enabled[0].attributes('aria-pressed')).toBe('true')
  })

  it('does not emit when clicking a disabled (unavailable) slot', async () => {
    const workingHours = useWorkingHoursStore()
    workingHours.byDentist.set('d1', [
      {
        id: 'wh1',
        user_id: 'd1',
        day_of_week: DATE.getDay(),
        start_time: '09:00',
        end_time: '10:00',
        is_active: true,
      },
    ])
    vi.mocked(appointmentsApi.availableSlots).mockResolvedValue([])

    const wrapper = mount(SlotPicker, { props: { dentistId: 'd1', date: DATE, durationMinutes: 30 } })
    await flushPromises()

    const button = wrapper.find('button')
    await button.trigger('click')

    expect(wrapper.emitted('slot-selected')).toBeUndefined()
  })

  it('shows a "no slots" message when the dentist has no working hours for that day', async () => {
    vi.mocked(appointmentsApi.availableSlots).mockResolvedValue([])

    const wrapper = mount(SlotPicker, { props: { dentistId: 'd1', date: DATE, durationMinutes: 30 } })
    await flushPromises()

    expect(wrapper.find('button').exists()).toBe(false)
    expect(wrapper.text()).toContain('No available slots')
  })

  it('fetches working hours for its own dentistId instead of assuming some other component already did', async () => {
    // Regression test for a confirmed real bug: candidateSlots read `workingHours.byDentist`,
    // but only the Board's own (unrelated) Dentist filter ever populated it — opening the dialog
    // for a dentist not currently selected in that filter left the store empty for them, so
    // SlotPicker showed "No available slots" even when the dentist genuinely had working hours.
    vi.mocked(workingHoursApi.listForDentist).mockResolvedValue([
      {
        id: 'wh1',
        user_id: 'd1',
        day_of_week: DATE.getDay(),
        start_time: '09:00',
        end_time: '10:00',
        is_active: true,
      },
    ])
    vi.mocked(appointmentsApi.availableSlots).mockResolvedValue([])

    const workingHours = useWorkingHoursStore()
    expect(workingHours.byDentist.has('d1')).toBe(false)

    mount(SlotPicker, { props: { dentistId: 'd1', date: DATE, durationMinutes: 30 } })
    await flushPromises()

    expect(workingHoursApi.listForDentist).toHaveBeenCalledWith('d1')
    expect(workingHours.byDentist.has('d1')).toBe(true)
  })
})
