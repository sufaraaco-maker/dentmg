import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import ConfirmationService from 'primevue/confirmationservice'
import ToastService from 'primevue/toastservice'
import Tooltip from 'primevue/tooltip'
import Aura from '@primeuix/themes/aura'
import { definePreset } from '@primeuix/themes'

import './style.css'
import App from './App.vue'
import { router } from './router'
import { i18n, setLocale } from './locales'

const app = createApp(App)

// Slightly softer, more premium corner rounding than Aura's stock scale (md 6px->8px, xl 12px->16px),
// applied via preset tokens so Card/Dialog/Button/inputs pick it up consistently everywhere.
const DentalSuitePreset = definePreset(Aura, {
  primitive: {
    borderRadius: {
      md: '8px',
      xl: '16px',
    },
  },
})

app.use(createPinia())
app.use(router)
app.use(i18n)
app.use(PrimeVue, {
  theme: {
    preset: DentalSuitePreset,
    options: {
      darkModeSelector: '.dark',
      cssLayer: {
        name: 'primevue',
        order: 'tailwind-base, primevue, tailwind-utilities',
      },
    },
  },
})
app.use(ConfirmationService)
app.use(ToastService)
app.directive('tooltip', Tooltip)

setLocale(i18n.global.locale.value as 'ar' | 'en' | 'tr')

app.mount('#app')
