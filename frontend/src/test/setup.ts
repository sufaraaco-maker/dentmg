import { config } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import PrimeVue from 'primevue/config'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import Tooltip from 'primevue/tooltip'
import Aura from '@primeuix/themes/aura'
import en from '@/locales/en.json'

// Exported so a component test can flip `locale.value` to 'ar' to assert RTL-conditional
// rendering (e.g. mirrored icons) — every other test file only ever runs against the 'en'
// default, so this was previously untestable outside a real-browser pass.
export const i18n = createI18n({
  legacy: false,
  locale: 'en',
  fallbackLocale: 'en',
  messages: { en },
})

config.global.plugins = [
  ...config.global.plugins,
  i18n,
  [PrimeVue, { theme: { preset: Aura, options: { darkModeSelector: '.dark' } } }],
  ToastService,
  ConfirmationService,
]
config.global.directives = { ...config.global.directives, tooltip: Tooltip }
// PrimeVue's Dialog (and other overlay components) teleport to <body> by default, which moves
// their content outside `wrapper`'s own DOM subtree — stubbing `teleport` renders it in place so
// `wrapper.text()`/`wrapper.find()` can still see it, per Vue Test Utils' documented approach.
config.global.stubs = { ...config.global.stubs, teleport: true }

// jsdom has no layout engine, so it doesn't implement matchMedia; several PrimeVue components
// (e.g. Select) use it for responsive behavior and throw during mount without this polyfill.
if (!window.matchMedia) {
  window.matchMedia = (query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => false,
  })
}

// jsdom has no layout engine, so it doesn't implement ResizeObserver either; PrimeVue's Tabs
// (TabList's ink-bar positioning) uses it and throws during mount without this polyfill.
if (!window.ResizeObserver) {
  window.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  }
}
