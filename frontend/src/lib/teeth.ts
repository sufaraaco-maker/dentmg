/**
 * Static FDI (ISO 3950) tooth-numbering reference — hand-written mirror of the backend's
 * `App\Support\ToothChart` (backend/app/Support/ToothChart.php). Kept as plain, framework-free
 * functions (no i18n) so it stays usable from validation/logic code, not just components; a
 * localized display-name helper belongs with whatever component first renders one, not here.
 *
 * FDI code = quadrant digit + position digit:
 *   Quadrants 1-4: permanent dentition (1 upper right, 2 upper left, 3 lower left, 4 lower right),
 *     positions 1-8 (1-3 anterior: central/lateral incisor, canine; 4-8 posterior: premolars, molars).
 *   Quadrants 5-8: primary/deciduous dentition (5 upper right, 6 upper left, 7 lower left, 8 lower
 *     right), positions 1-5 (1-3 anterior; 4-5 posterior — primary dentition has no premolars).
 */

function permanentCodes(): string[] {
  const codes: string[] = []
  for (let quadrant = 1; quadrant <= 4; quadrant += 1) {
    for (let position = 1; position <= 8; position += 1) {
      codes.push(`${quadrant}${position}`)
    }
  }
  return codes
}

function primaryCodes(): string[] {
  const codes: string[] = []
  for (let quadrant = 5; quadrant <= 8; quadrant += 1) {
    for (let position = 1; position <= 5; position += 1) {
      codes.push(`${quadrant}${position}`)
    }
  }
  return codes
}

/** All 52 valid FDI codes: 32 permanent (11-18, 21-28, 31-38, 41-48) then 20 primary (51-55, 61-65, 71-75, 81-85). */
export const TOOTH_CODES: string[] = [...permanentCodes(), ...primaryCodes()]

export function isValidToothCode(code: string): boolean {
  return TOOTH_CODES.includes(code)
}

function quadrant(code: string): number {
  return Number(code[0])
}

function position(code: string): number {
  return Number(code[1])
}

/** True for permanent dentition (quadrants 1-4), false for primary/deciduous (quadrants 5-8). */
export function isPermanentTooth(code: string): boolean {
  return quadrant(code) <= 4
}

/**
 * True for anterior teeth (incisors/canines — positions 1-3 in either dentition), false for
 * posterior (premolars/molars). Drives the Occlusal-vs-Incisal surface rule: `O` only valid on a
 * posterior tooth, `I` only on an anterior one.
 */
export function isAnteriorTooth(code: string): boolean {
  return position(code) <= 3
}

const QUADRANT_NAMES: Record<number, string> = {
  1: 'Upper Right',
  2: 'Upper Left',
  3: 'Lower Left',
  4: 'Lower Right',
  5: 'Upper Right',
  6: 'Upper Left',
  7: 'Lower Left',
  8: 'Lower Right',
}

const PERMANENT_POSITION_NAMES: Record<number, string> = {
  1: 'Central Incisor',
  2: 'Lateral Incisor',
  3: 'Canine',
  4: 'First Premolar',
  5: 'Second Premolar',
  6: 'First Molar',
  7: 'Second Molar',
  8: 'Third Molar',
}

const PRIMARY_POSITION_NAMES: Record<number, string> = {
  1: 'Central Incisor',
  2: 'Lateral Incisor',
  3: 'Canine',
  4: 'First Molar',
  5: 'Second Molar',
}

/**
 * Human-readable tooth name, e.g. "Upper Right First Molar" (permanent) or "Upper Right Second
 * Molar (Primary)" — frontend mirror of `App\Support\ToothChart::displayName()`, needed for the
 * accessibility `aria-label` requirement (design draft §18), not just server-side rendering.
 */
export function toothDisplayName(code: string): string {
  if (!isValidToothCode(code)) {
    throw new Error(`Invalid FDI tooth code: ${code}`)
  }

  const permanent = isPermanentTooth(code)
  const positionName = permanent
    ? PERMANENT_POSITION_NAMES[position(code)]
    : PRIMARY_POSITION_NAMES[position(code)]
  const name = `${QUADRANT_NAMES[quadrant(code)]} ${positionName}`

  return permanent ? name : `${name} (Primary)`
}

/**
 * True when this tooth belongs to the upper arch (quadrants 1,2,5,6), false for the lower arch
 * (3,4,7,8) — drives which trapezoid of the 5-surface diagram is Facial vs. Lingual (rendering
 * design doc §1/§3), never stored.
 */
export function isUpperArch(code: string): boolean {
  const q = quadrant(code)
  return q === 1 || q === 2 || q === 5 || q === 6
}

/**
 * Which horizontal side of the tooth's SVG square the Mesial surface is drawn on, given the
 * standard "patient's right shown on the viewer's left" chart layout (rendering design doc §1):
 * quadrants 1/4/5/8 → mesial on the right, quadrants 2/3/6/7 → mesial on the left.
 */
export function mesialSide(code: string): 'left' | 'right' {
  const q = quadrant(code)
  return q === 1 || q === 4 || q === 5 || q === 8 ? 'right' : 'left'
}

/** All codes in one quadrant, position ascending (e.g. quadrant 1 → ['11','12',...,'18']). */
export function quadrantCodes(quadrantNumber: number): string[] {
  return TOOTH_CODES.filter((code) => quadrant(code) === quadrantNumber)
}
