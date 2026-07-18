import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

// Deliberately separate from vite.config.ts (not merged via `vite`'s defineConfig + mergeConfig):
// this project's `vite` package resolves to a rolldown-based build whose Plugin types conflict
// with the (non-rolldown) vite that `vitest/config` bundles internally, which vue-tsc reports as
// a type error if the two configs are merged into one file. Small, deliberate duplication of the
// plugin/alias setup below avoids that dual-package type conflict — see also the frontend build
// verification notes in this step's report.
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  test: {
    environment: 'jsdom',
    globals: false,
    // Explicit, not the default (which would also match `e2e/*.spec.ts` — the Playwright suite,
    // a different test runner entirely with an incompatible `test()`/`expect()` API).
    include: ['src/**/*.test.ts'],
    setupFiles: ['./src/test/setup.ts'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html'],
      include: ['src/**/*.{ts,vue}'],
      exclude: ['src/main.ts', 'src/**/*.d.ts', 'src/test/**'],
    },
  },
})
