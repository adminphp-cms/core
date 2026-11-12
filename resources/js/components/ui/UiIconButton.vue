<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'

/**
 * A 32px square button holding an icon. `label` names it for assistive technology and
 * as its tooltip, unless `title` gives a shorter tooltip.
 */
withDefaults(
    defineProps<{
        label: string
        title?: string
        to?: RouteLocationRaw
        href?: string
        type?: 'button' | 'submit' | 'reset'
    }>(),
    { type: 'button' },
)

/* The hover rules use an arbitrary variant, emitted after `active:`, so hovering wins over pressing as before. */
const classes =
    'relative z-1 inline-flex size-8 flex-none items-center justify-center gap-1.5 rounded-md border border-ring-control bg-surface leading-none font-medium whitespace-nowrap text-muted no-underline [transition:background_var(--press),color_var(--press),border-color_var(--press)] active:not-disabled:border-ring-control active:not-disabled:bg-pressed hover:[&:not(:disabled)]:bg-hover hover:[&:not(:disabled)]:text-ink'
</script>

<template>
    <RouterLink v-if="to" :to="to" :class="classes" :aria-label="label" :title="title ?? label"><slot /></RouterLink>
    <a v-else-if="href" :href="href" :class="classes" :aria-label="label" :title="title ?? label"><slot /></a>
    <button v-else :type="type" :class="classes" :aria-label="label" :title="title ?? label"><slot /></button>
</template>
