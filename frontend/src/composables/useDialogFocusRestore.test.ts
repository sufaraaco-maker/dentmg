import { mount } from '@vue/test-utils'
import { defineComponent, nextTick, ref, type Ref } from 'vue'
import { describe, expect, it } from 'vitest'
import { useDialogFocusRestore } from './useDialogFocusRestore'

function mountWithFocusRestore(visible: Ref<boolean>, options?: Parameters<typeof mount>[1]) {
  return mount(
    defineComponent({
      setup() {
        useDialogFocusRestore(() => visible.value)
        return () => null
      },
    }),
    options,
  )
}

describe('useDialogFocusRestore', () => {
  it('captures the triggering element on open and restores focus to it on close', async () => {
    const visible = ref(false)

    mountWithFocusRestore(visible, { attachTo: document.body })

    const trigger = document.createElement('button')
    document.body.appendChild(trigger)
    trigger.focus()
    expect(document.activeElement).toBe(trigger)

    visible.value = true
    await nextTick()

    // Simulate the dialog moving focus elsewhere while open (PrimeVue's own focus trap /
    // `Dialog.focus()` — see the composable's own doc comment on why this hook stays out of the
    // "move focus onto the dialog's first field" business entirely).
    const fieldInsideDialog = document.createElement('input')
    document.body.appendChild(fieldInsideDialog)
    fieldInsideDialog.focus()

    visible.value = false
    await nextTick()

    expect(document.activeElement).toBe(trigger)

    trigger.remove()
    fieldInsideDialog.remove()
  })

  it('does nothing on close when nothing was captured (dialog mounted already-visible)', async () => {
    const visible = ref(true)

    expect(() => mountWithFocusRestore(visible)).not.toThrow()

    visible.value = false
    await nextTick()
    // No assertion needed beyond "doesn't throw" — there was no `visible` transition to capture
    // a trigger from (the composable only watches, it doesn't fire on the initial value).
  })
})
