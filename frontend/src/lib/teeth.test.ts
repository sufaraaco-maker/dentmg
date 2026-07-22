import { describe, expect, it } from 'vitest'
import {
  isAnteriorTooth,
  isPermanentTooth,
  isUpperArch,
  isValidToothCode,
  mesialSide,
  TOOTH_CODES,
  toothDisplayName,
} from './teeth'

describe('TOOTH_CODES', () => {
  it('contains exactly 52 codes: 32 permanent + 20 primary', () => {
    expect(TOOTH_CODES).toHaveLength(52)
  })

  it('starts with the permanent quadrants then the primary quadrants, in FDI order', () => {
    expect(TOOTH_CODES.slice(0, 8)).toEqual(['11', '12', '13', '14', '15', '16', '17', '18'])
    expect(TOOTH_CODES.slice(24, 32)).toEqual(['41', '42', '43', '44', '45', '46', '47', '48'])
    expect(TOOTH_CODES.slice(32, 37)).toEqual(['51', '52', '53', '54', '55'])
    expect(TOOTH_CODES.slice(47, 52)).toEqual(['81', '82', '83', '84', '85'])
  })
})

describe('isValidToothCode', () => {
  it('accepts every code in TOOTH_CODES', () => {
    TOOTH_CODES.forEach((code) => expect(isValidToothCode(code)).toBe(true))
  })

  it('rejects out-of-range quadrant/position codes', () => {
    expect(isValidToothCode('19')).toBe(false) // permanent quadrant has no position 9
    expect(isValidToothCode('91')).toBe(false) // no quadrant 9
    expect(isValidToothCode('56')).toBe(false) // primary quadrant has no position 6
  })
})

describe('isPermanentTooth', () => {
  it('is true for quadrants 1-4, false for quadrants 5-8', () => {
    expect(isPermanentTooth('16')).toBe(true)
    expect(isPermanentTooth('48')).toBe(true)
    expect(isPermanentTooth('55')).toBe(false)
    expect(isPermanentTooth('84')).toBe(false)
  })
})

describe('isAnteriorTooth', () => {
  it('is true for positions 1-3 (incisors/canines), false for positions 4-8 (premolars/molars)', () => {
    expect(isAnteriorTooth('11')).toBe(true)
    expect(isAnteriorTooth('13')).toBe(true)
    expect(isAnteriorTooth('14')).toBe(false)
    expect(isAnteriorTooth('16')).toBe(false)
  })

  it('holds the same position boundary in the primary dentition', () => {
    expect(isAnteriorTooth('53')).toBe(true)
    expect(isAnteriorTooth('54')).toBe(false)
  })
})

describe('toothDisplayName', () => {
  it('renders permanent tooth names without a suffix', () => {
    expect(toothDisplayName('16')).toBe('Upper Right First Molar')
    expect(toothDisplayName('21')).toBe('Upper Left Central Incisor')
    expect(toothDisplayName('48')).toBe('Lower Right Third Molar')
  })

  it('appends "(Primary)" for primary dentition', () => {
    expect(toothDisplayName('55')).toBe('Upper Right Second Molar (Primary)')
    expect(toothDisplayName('84')).toBe('Lower Right First Molar (Primary)')
  })

  it('throws on an invalid code', () => {
    expect(() => toothDisplayName('19')).toThrow(/Invalid FDI tooth code/)
  })
})

describe('isUpperArch', () => {
  it('is true for quadrants 1,2,5,6 and false for 3,4,7,8', () => {
    expect(isUpperArch('11')).toBe(true)
    expect(isUpperArch('26')).toBe(true)
    expect(isUpperArch('51')).toBe(true)
    expect(isUpperArch('63')).toBe(true)
    expect(isUpperArch('31')).toBe(false)
    expect(isUpperArch('48')).toBe(false)
    expect(isUpperArch('71')).toBe(false)
    expect(isUpperArch('84')).toBe(false)
  })
})

describe('mesialSide', () => {
  it('is "right" for quadrants 1,4,5,8 and "left" for 2,3,6,7', () => {
    expect(mesialSide('11')).toBe('right')
    expect(mesialSide('44')).toBe('right')
    expect(mesialSide('55')).toBe('right')
    expect(mesialSide('81')).toBe('right')
    expect(mesialSide('21')).toBe('left')
    expect(mesialSide('34')).toBe('left')
    expect(mesialSide('65')).toBe('left')
    expect(mesialSide('71')).toBe('left')
  })
})
