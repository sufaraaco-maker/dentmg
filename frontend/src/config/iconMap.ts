/**
 * Reference map for the PrimeIcons → Lucide migration (frontend-visual-redesign-design.md §4).
 *
 * Not imported at runtime — each migrated file imports its Lucide icon component directly
 * (`import { Users } from 'lucide-vue-next'`), the standard Vue/Lucide pattern and best for
 * tree-shaking. This file is the single reviewed decision record so every occurrence of a given
 * `pi-*` class maps to the same Lucide icon, rather than being re-decided ad hoc per file during
 * the module-by-module rollout (§8 steps 2–5).
 *
 * Key = PrimeIcons class suffix (without the `pi-` prefix). Value = Lucide component export name
 * (`lucide-vue-next`). All confirmed to exist in the installed version before this file was written.
 */
export const ICON_MIGRATION_MAP: Record<string, string> = {
  'angle-right': 'ChevronRight',
  'arrow-left': 'ArrowLeft',
  'arrow-right': 'ArrowRight',
  ban: 'Ban',
  bars: 'Menu',
  bell: 'Bell',
  bolt: 'Zap',
  book: 'Book',
  box: 'Package',
  building: 'Building2',
  calendar: 'Calendar',
  camera: 'Camera',
  'chart-bar': 'BarChart3',
  'chart-line': 'LineChart',
  check: 'Check',
  'check-circle': 'CheckCircle2',
  'chevron-down': 'ChevronDown',
  'chevron-left': 'ChevronLeft',
  'chevron-right': 'ChevronRight',
  // No filled-dot equivalent in Lucide's outline set. Used today as a small inline status dot —
  // migrate to a plain `<span class="rounded-full bg-current" />` sized to match, not an icon
  // component, at the specific call site(s) found during the icon-usage audit.
  'circle-fill': '(status dot — replace with a styled <span>, not an icon)',
  clipboard: 'ClipboardList',
  clock: 'Clock',
  cog: 'Settings',
  download: 'Download',
  'exclamation-circle': 'AlertCircle',
  'exclamation-triangle': 'AlertTriangle',
  file: 'File',
  'file-edit': 'FileEdit',
  filter: 'Filter',
  history: 'History',
  home: 'Home',
  images: 'Images',
  inbox: 'Inbox',
  link: 'Link',
  lock: 'Lock',
  moon: 'Moon',
  palette: 'Palette',
  paperclip: 'Paperclip',
  pencil: 'Pencil',
  play: 'Play',
  plus: 'Plus',
  print: 'Printer',
  'question-circle': 'HelpCircle',
  receipt: 'Receipt',
  refresh: 'RefreshCw',
  replay: 'RotateCcw',
  search: 'Search',
  'search-plus': 'ZoomIn',
  send: 'Send',
  'sign-in': 'LogIn',
  'sign-out': 'LogOut',
  // Dental Chart's nav icon — "sitemap" (hierarchical tree) has no direct Lucide equivalent;
  // Network is the closest semantic match (branching/structural), chosen over a literal
  // tree-diagram icon Lucide doesn't have.
  sitemap: 'Network',
  sparkles: 'Sparkles',
  // Paired today with the separate `pi-spin` animation modifier class
  // (`class="pi pi-spin pi-spinner"`). Lucide equivalent pairs `LoaderCircle` with Tailwind's
  // built-in `animate-spin` utility instead — same visual result, no custom keyframes needed.
  spinner: 'LoaderCircle',
  star: 'Star',
  // No separate "filled star" icon needed — Lucide's `Star` accepts a `fill="currentColor"` prop
  // for the filled state instead of a second icon component.
  'star-fill': 'Star (fill="currentColor")',
  sun: 'Sun',
  tags: 'Tags',
  times: 'X',
  'times-circle': 'XCircle',
  trash: 'Trash2',
  truck: 'Truck',
  user: 'User',
  'user-minus': 'UserMinus',
  'user-plus': 'UserPlus',
  users: 'Users',
  verified: 'BadgeCheck',
  wallet: 'Wallet',
}
