import { config } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import PrimeVue from 'primevue/config'
import ToastService from 'primevue/toastservice'
import Tooltip from 'primevue/tooltip'
import Aura from '@primeuix/themes/aura'
import en from '@/locales/en.json'

const i18n = createI18n({
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
]
config.global.directives = { ...config.global.directives, tooltip: Tooltip }

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
