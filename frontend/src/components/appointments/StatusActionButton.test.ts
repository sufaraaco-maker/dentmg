import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { useConfirm } from 'primevue/useconfirm'
import StatusActionButton from './StatusActionButton.vue'

vi.mock('primevue/useconfirm', () => ({ useConfirm: vi.fn() }))

describe('StatusActionButton', () => {
  it('emits confirmed (no reason) once the confirm popup is accepted, for a plain action', async () => {
    const requireMock = vi.fn()
    vi.mocked(useConfirm).mockReturnValue({ require: requireMock } as unknown as ReturnType<
      typeof useConfirm
    >)

    const wrapper = mount(StatusActionButton, { props: { action: 'confirm' } })
    await wrapper.find('button').trigger('click')

    expect(requireMock).toHaveBeenCalledTimes(1)
    const config = requireMock.mock.calls[0][0]
    config.accept()

    expect(wrapper.emitted('confirmed')?.[0]).toEqual([])
  })

  it('never calls confirm.require for a reason-requiring action — it opens its own dialog instead', async () => {
    const requireMock = vi.fn()
    vi.mocked(useConfirm).mockReturnValue({ require: requireMock } as unknown as ReturnType<
      typeof useConfirm
    >)

    const wrapper = mount(StatusActionButton, { props: { action: 'cancel', requiresReason: true } })
    await wrapper.find('button').trigger('click')

    expect(requireMock).not.toHaveBeenCalled()
    expect(wrapper.findComponent({ name: 'Dialog' }).props('visible')).toBe(true)
  })

  it('emits confirmed with the typed reason from the reason dialog', async () => {
    vi.mocked(useConfirm).mockReturnValue({ require: vi.fn() } as unknown as ReturnType<typeof useConfirm>)

    const wrapper = mount(StatusActionButton, { props: { action: 'noShow', requiresReason: true } })
    await wrapper.find('button').trigger('click')

    await wrapper.find('textarea').setValue('Patient did not answer calls')
    const buttons = wrapper.findAllComponents({ name: 'Button' })
    const confirmButton = buttons.find(
      (b) => b.text() === 'Mark as No Show' && b.props('severity') === 'danger',
    )
    await confirmButton!.trigger('click')

    expect(wrapper.emitted('confirmed')?.[0]).toEqual(['Patient did not answer calls'])
  })
})
