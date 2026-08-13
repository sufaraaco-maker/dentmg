<script setup lang="ts">
/**
 * The app's brand mark — reuses the existing `public/favicon.svg` (already the browser-tab icon)
 * as the one actual logo asset in the codebase, rather than inventing a second one. `<img>` over
 * inlining the SVG: the file carries several blur filters that are cheaper left as a cached static
 * asset than duplicated inline on every mount (login screen, sidebar, mobile header).
 */
withDefaults(defineProps<{ size?: number }>(), { size: 32 })
</script>

<template>
  <img
    src="/favicon.svg"
    alt=""
    :width="size"
    :height="(size * 46) / 48"
    class="shrink-0"
    draggable="false"
  />
</template>
