import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { Images, Inbox } from 'lucide-vue-next'
import EmptyState from './EmptyState.vue'

describe('EmptyState', () => {
  it('renders the title', () => {
    const wrapper = mount(EmptyState, { props: { title: 'No records yet' } })

    expect(wrapper.text()).toContain('No records yet')
  })

  it('renders the description only when provided', () => {
    const withDescription = mount(EmptyState, {
      props: { title: 'No records yet', description: 'Try adjusting your filters.' },
    })
    const withoutDescription = mount(EmptyState, { props: { title: 'No records yet' } })

    expect(withDescription.text()).toContain('Try adjusting your filters.')
    expect(withoutDescription.text()).not.toContain('Try adjusting your filters.')
  })

  it('falls back to the Inbox icon when none is provided', () => {
    const wrapper = mount(EmptyState, { props: { title: 'No records yet' } })

    expect(wrapper.findComponent(Inbox).exists()).toBe(true)
  })

  it('renders a custom icon when provided', () => {
    const wrapper = mount(EmptyState, { props: { title: 'No records yet', icon: Images } })

    expect(wrapper.findComponent(Inbox).exists()).toBe(false)
    expect(wrapper.findComponent(Images).exists()).toBe(true)
  })

  it('shows an action button only when actionLabel is provided, and emits on click', async () => {
    const withAction = mount(EmptyState, { props: { title: 'No records yet', actionLabel: 'Add one' } })
    const withoutAction = mount(EmptyState, { props: { title: 'No records yet' } })

    expect(withoutAction.find('button').exists()).toBe(false)
    expect(withAction.text()).toContain('Add one')

    await withAction.find('button').trigger('click')

    expect(withAction.emitted('action')).toHaveLength(1)
  })
})
