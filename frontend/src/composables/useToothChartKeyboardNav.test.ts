import { mount } from '@vue/test-utils'
import { defineComponent, ref } from 'vue'
import { afterEach, describe, expect, it } from 'vitest'
import { useToothChartKeyboardNav } from './useToothChartKeyboardNav'

/**
 * A minimal stand-in for ToothChart's rendered grid: one focusable `[data-tooth]` element per
 * code, arranged in the same `rows` shape ToothChart.vue derives from its quadrant arch layout.
 */
const TestHost = defineComponent({
  props: { rows: { type: Array as () => string[][], required: true } },
  setup(props) {
    const container = ref<HTMLElement | null>(null)
    const rowsRef = ref(props.rows)
    useToothChartKeyboardNav(container, rowsRef)
    return { container }
  },
  template: `
    <div ref="container">
      <div v-for="row in rows" :key="row.join(',')">
        <div v-for="code in row" :key="code" :data-tooth="code">
          <div tabindex="0" role="button">{{ code }}</div>
        </div>
      </div>
    </div>
  `,
})

function focusTooth(wrapper: ReturnType<typeof mount>, code: string) {
  const el = wrapper.get(`[data-tooth="${code}"] [tabindex]`).element as HTMLElement
  el.focus()
  return el
}

function pressKey(key: string) {
  window.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }))
}

afterEach(() => {
  document.body.innerHTML = ''
})

describe('useToothChartKeyboardNav', () => {
  const rows = [
    ['18', '17', '16', '21', '22', '23'],
    ['48', '47', '46', '31', '32', '33'],
  ]

  it('moves focus right/left within the same row', () => {
    const wrapper = mount(TestHost, { props: { rows }, attachTo: document.body })
    focusTooth(wrapper, '17')

    pressKey('ArrowRight')
    expect(document.activeElement).toBe(wrapper.get('[data-tooth="16"] [tabindex]').element)

    pressKey('ArrowLeft')
    pressKey('ArrowLeft')
    expect(document.activeElement).toBe(wrapper.get('[data-tooth="18"] [tabindex]').element)

    wrapper.unmount()
  })

  it('clamps at the row edge instead of wrapping', () => {
    const wrapper = mount(TestHost, { props: { rows }, attachTo: document.body })
    focusTooth(wrapper, '18')

    pressKey('ArrowLeft')
    expect(document.activeElement).toBe(wrapper.get('[data-tooth="18"] [tabindex]').element)

    wrapper.unmount()
  })

  it('moves focus down/up between arch rows at the same column, clamping the column if needed', () => {
    const wrapper = mount(TestHost, { props: { rows }, attachTo: document.body })
    focusTooth(wrapper, '21')

    pressKey('ArrowDown')
    expect(document.activeElement).toBe(wrapper.get('[data-tooth="31"] [tabindex]').element)

    pressKey('ArrowUp')
    expect(document.activeElement).toBe(wrapper.get('[data-tooth="21"] [tabindex]').element)

    wrapper.unmount()
  })

  it('does nothing when focus is outside any tooth element', () => {
    const wrapper = mount(TestHost, { props: { rows }, attachTo: document.body })
    ;(document.activeElement as HTMLElement | null)?.blur()
    document.body.focus()

    pressKey('ArrowRight')
    expect(document.activeElement).not.toBe(wrapper.get('[data-tooth="18"] [tabindex]').element)

    wrapper.unmount()
  })

  it('ignores non-arrow keys', () => {
    const wrapper = mount(TestHost, { props: { rows }, attachTo: document.body })
    const el = focusTooth(wrapper, '17')

    pressKey('Enter')
    expect(document.activeElement).toBe(el)

    wrapper.unmount()
  })

  it('removes its listener on unmount', () => {
    const wrapper = mount(TestHost, { props: { rows }, attachTo: document.body })
    focusTooth(wrapper, '17')
    wrapper.unmount()

    expect(() => pressKey('ArrowRight')).not.toThrow()
  })
})
