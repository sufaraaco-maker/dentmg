# Dental Chart Rendering — `ToothSvg.vue` / `ToothSurface.vue` Design (Step 7)

**Status: Design for implementation-plan Step 7 only** (`docs/modules/dental-chart-implementation-plan.md`
§4, step 7). Scope is strictly the two rendering components — no `ToothChart.vue` assembly, no
`PatientDetailView.vue` integration, no `ChartEntryDialog.vue`. Those are Step 8/9/10.

---

## 1. Tooth Numbering Convention (recap, no change)

FDI (ISO 3950), as decided and implemented in `frontend/src/lib/teeth.ts` / `App\Support\ToothChart`
(design draft §6, implementation plan §0 item 1). This doc adds two purely-rendering concerns to
`teeth.ts` that were not needed until now:

- **`toothDisplayName(code)`** — frontend mirror of the backend's `ToothChart::displayName()` (e.g.
  `"Upper Right First Molar"`), needed for the accessibility `aria-label` requirement (design draft §18)
  that `teeth.ts` didn't need until a component actually renders one.
- **Arch/side orientation** — needed to lay out the 5-surface diagram correctly (§3 below), derived
  purely from the FDI code, never a prop the parent has to compute or pass in:
  - `isUpperArch(code)`: quadrants `{1,2,5,6}` → `true` (upper), `{3,4,7,8}` → `false` (lower).
  - `mesialSide(code)`: which horizontal side of the tooth square faces the dental midline, given the
    standard "patient's right shown on the viewer's left" chart layout every competitor in §0 uses.
    Quadrants `{1,4,5,8}` → mesial is drawn on the tooth's **right**; quadrants `{2,3,6,7}` → **left**.
    (Reasoning: in that layout the upper row reads `18…11 | 21…28` left-to-right — for a quadrant-1
    tooth, position increases moving *away* from the midline as you go left, so the midline — and thus
    the mesial surface — is to that tooth's right. Quadrant 4/5/8 mirror the same logic for their row.)

Both are pure, hand-mirrored-style functions (matching the existing duplication convention between
`teeth.ts` and `Support\ToothChart.php` — no backend equivalent needed since only the frontend renders
SVG).

## 2. Surface Naming Convention (recap, no change)

Six FDI surface codes, already typed in `types/dentalChart.ts`: `M` (Mesial), `D` (Distal), `F`
(Facial/Buccal/Labial), `L` (Lingual/Palatal), `O` (Occlusal, posterior only), `I` (Incisal, anterior
only). `ToothSvg` derives which of `O`/`I` is valid for a given tooth via the existing
`isAnteriorTooth()` — never a prop.

A condition that does **not** apply to a surface (`surfaces: null`/`[]` on the entry) is rendered as a
**whole-tooth** entry — the entire tooth square, not one of the 6 regions.

## 3. SVG Coordinate Strategy

Classic "envelope"/tic-tac-toe 5-region odontogram diagram, `viewBox="0 0 100 100"`, used identically
by every competitor product surveyed in the design draft §0 (schematic, not photorealistic — matches
§16/§21's explicit scope call).

```
(0,0)────────────────(100,0)
  │  \              /  │
  │   (30,30)──(70,30)  │
  │     │  O/I   │      │
  │   (30,70)──(70,70)  │
  │  /              \  │
(0,100)──────────────(100,100)
```

Outer square `(0,0)-(100,0)-(100,100)-(0,100)`, inner square `(30,30)-(70,30)-(70,70)-(30,70)`. Five
regions, each a closed path:

| Region | Path |
|---|---|
| Center | `M30,30 L70,30 L70,70 L30,70 Z` |
| Top trapezoid | `M0,0 L100,0 L70,30 L30,30 Z` |
| Right trapezoid | `M100,0 L100,100 L70,70 L70,30 Z` |
| Bottom trapezoid | `M100,100 L0,100 L30,70 L70,70 Z` |
| Left trapezoid | `M0,100 L0,0 L30,30 L30,70 Z` |

Mapping trapezoid → surface code, resolved once per tooth from `isUpperArch`/`mesialSide` (§1):

- Center → `O` if posterior, `I` if anterior.
- Top → `F` if upper arch, `L` if lower arch. Bottom → the opposite.
- Whichever of Left/Right equals `mesialSide(tooth)` → `M`. The other → `D`.

This keeps `ToothSvg` fully self-orienting from just the `tooth` prop — a future `ToothChart.vue`
assembling all 32/52 teeth into quadrant rows never needs to pass orientation.

**Whole-tooth region**: the outer square path itself (`M0,0 L100,0 L100,100 L0,100 Z`), used as one
clickable/colorable region when an entry has no surfaces.

**Sizing**: `viewBox` fixed at `0 0 100 100`; rendered pixel size controlled by a `size` prop (default
`64`) applied to `width`/`height` — scales cleanly for a future compact legend thumbnail vs. a full
chart tooth without any path recalculation.

## 4. Color / Status / Icon — Three Independent Signals

Per design draft §16/§18 ("status is never conveyed by color alone"):

1. **Base color** — `entry.dental_condition.default_color`, identifies *what* condition.
2. **Status tone** — a fill-opacity + stroke-style modifier, identifies *when/urgency*, resolved
   deterministically (unit-tested, not a vibe):

   | Status | Fill opacity | Stroke |
   |---|---|---|
   | `existing` / `completed` | `1` | solid |
   | `active` / `planned` | `0.55` | dashed (`stroke-dasharray`) — visually "not yet finalized" |
   | `cancelled` | `0.15` | dotted, muted gray stroke regardless of condition color |

3. **Icon glyph** — a small **bounded** set (new `frontend/src/lib/dentalIcons.ts`, mirrors the
   `teeth.ts`/`color.ts` placement convention), so a new catalog condition never needs new frontend
   code (design draft §16, plan §2.4): `'filled' | 'outline' | 'x' | 'lines' | 'dot'`. `icon_key` values
   map into this set via `resolveIconGlyph(iconKey)`, defaulting to `'filled'` for `null`/unrecognized
   keys (forward-compatible with any future catalog entry). Seed-data mapping documented in the function
   itself: `missing → x`, `extraction → lines`, `fracture|impacted → outline`, `root_canal|sealant →
   dot`, everything else (`caries`, `filling`, `crown`, `implant`, `bridge`, `veneer`) → `filled`.

**Multiple entries on one surface** (e.g. an old `cancelled` finding and a new `active` one on the same
tooth's mesial surface): only one is rendered per region. Precedence, most-clinically-relevant first
(unit-tested): `active` > `planned` > `existing` > `completed` > `cancelled`. This is a rendering-only
precedence — it does not hide or alter the other entries' actual data (still fully visible in the future
list view / dialog history), it only picks what one region's color shows at a glance.

## 5. Component Props/Events API

### `ToothSurface.vue` — one clickable region, purely presentational

```ts
withDefaults(defineProps<{
  path: string                          // precomputed 'd' attribute, from ToothSvg
  fill: string                          // resolved hex, or 'none' when empty
  fillOpacity?: number                  // default 1
  strokeDasharray?: string              // default undefined (solid)
  strokeColor?: string                  // default 'currentColor'-ish neutral
  glyph?: 'filled' | 'outline' | 'x' | 'lines' | 'dot' | null
  label: string                         // full aria-label for this region's current state
  interactive?: boolean                 // default true
  selected?: boolean                    // external highlight ring (future surface-picker reuse)
}>(), { fillOpacity: 1, interactive: true, selected: false, glyph: null })

const emit = defineEmits<{ activate: [] }>()
```

Emits one event, `activate`, on both click and Enter/Space keydown (mirrors the existing
`AppointmentEventContent.vue`/`AppointmentCard.vue` `onKeydown` convention exactly — no new
interaction pattern invented). `ToothSurface` does not know what surface code or tooth it represents;
`ToothSvg` supplies the finished `path`/`label` and re-emits with identity attached. This is what keeps
a future alternate rendering mode (design draft §22 deferred item) a `ToothSurface`-only swap.

### `ToothSvg.vue` — one tooth, orchestrates 5 (or 6) `ToothSurface` children

```ts
withDefaults(defineProps<{
  tooth: string                         // FDI code, required
  entries?: DentalChartEntry[]          // this tooth's entries only — ToothSvg does not filter by tooth_number
  size?: number                         // px, default 64
  interactive?: boolean                 // default true — false for read-only/legend use
  selectedSurface?: ToothSurface | 'whole' | null  // external highlight, default null
}>(), { entries: () => [], size: 64, interactive: true, selectedSurface: null })

const emit = defineEmits<{
  'surface-click': [payload: { tooth: string; surface: ToothSurface }]
  'tooth-click': [payload: { tooth: string }]
}>()
```

Usage matches the sketch already agreed:

```vue
<ToothSvg tooth="18" :entries="entriesForTooth18" @surface-click="handleSurfaceClick" @tooth-click="handleToothClick" />
```

`ToothSvg` root carries `dir="ltr"` unconditionally (design draft §17/§2.5) — the Mesial/Distal
left-right placement inside one tooth is anatomically fixed and must never mirror under Arabic RTL,
independent of whatever the surrounding `ToothChart.vue` does later. Root also carries a full
descriptive `aria-label` built from `toothDisplayName(tooth)` + entry summary (design draft §18's
worked example), and `role="img"`-like grouping (`role="group"`) around the interactive children.

## 6. Out of Scope (explicit, per Step 7 instructions)

`ToothChart.vue` assembly (quadrant/arch layout, keyboard arrow-navigation between teeth),
`ChartEntryDialog.vue`, `ToothLegend.vue`, `PatientDetailView.vue` integration, tooth history, any
Treatment Plan / AI feature. These remain Step 8+ per the implementation plan.

---

**No code written before this document; implementation follows immediately after, per the two-phase
workflow, with Vitest + real-browser (Arabic RTL + dark mode) verification before the Step 7 report.**
