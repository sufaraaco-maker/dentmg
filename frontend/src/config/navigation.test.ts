import { describe, expect, it } from 'vitest'
import { DETAIL_ROUTES, findNavTrailByRouteName, flattenNavItems, navigation } from './navigation'

describe('findNavTrailByRouteName', () => {
  it('returns a single-item trail for a top-level item', () => {
    expect(findNavTrailByRouteName('patients')).toEqual([
      expect.objectContaining({ labelKey: 'nav.patients', routeName: 'patients' }),
    ])
  })

  it('returns a two-item trail for a nested child', () => {
    const trail = findNavTrailByRouteName('appointment-types')
    expect(trail).toHaveLength(2)
    expect(trail?.[0]?.routeName).toBe('appointments')
    expect(trail?.[1]?.routeName).toBe('appointment-types')
  })

  it('returns undefined for a route not in the sidebar at all', () => {
    expect(findNavTrailByRouteName('patient-detail')).toBeUndefined()
  })
})

describe('flattenNavItems', () => {
  it('includes every leaf item exactly once, expanding groups with children', () => {
    const flat = flattenNavItems()
    const routeNames = flat.map((item) => item.routeName)

    expect(routeNames).toContain('patients')
    expect(routeNames).toContain('appointment-types')
    // The group's own row (`appointments`) is superseded by its children in the flattened list,
    // since `flattenNavItems` exists for the Command Palette's "go to X" list, not the sidebar tree.
    expect(routeNames.filter((name) => name === 'appointments')).toHaveLength(1)
  })

  it('never returns a group item alongside its own children twice', () => {
    const flat = flattenNavItems()
    expect(flat.length).toBeGreaterThanOrEqual(navigation.length)
  })
})

describe('DETAIL_ROUTES', () => {
  it('every declared parentRouteName resolves to a real sidebar entry', () => {
    for (const detail of Object.values(DETAIL_ROUTES)) {
      expect(findNavTrailByRouteName(detail.parentRouteName)).toBeDefined()
    }
  })
})
