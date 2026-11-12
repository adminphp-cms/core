<script setup lang="ts">
import { computed } from 'vue'
import type { RouteLocationRaw } from 'vue-router'

export type ButtonVariant = 'default' | 'primary' | 'danger'

/**
 * A button, or a link styled as one: `to` renders a RouterLink, `href` a plain link.
 * `lg` is the taller button of the login screens.
 */
const props = withDefaults(
    defineProps<{
        variant?: ButtonVariant
        size?: 'md' | 'lg'
        to?: RouteLocationRaw
        href?: string
        type?: 'button' | 'submit' | 'reset'
    }>(),
    { variant: 'default', size: 'md', type: 'button' },
)

/*
 * Pressing paints the pressed background, unless the pointer hovers: the hover rules
 * use an arbitrary variant, which Tailwind emits after `active:`, so they win as before.
 */
const variantClasses: Record<ButtonVariant, string> = {
    default: 'border-ring-control bg-surface text-ink active:not-disabled:bg-pressed hover:[&:not(:disabled)]:bg-hover',
    primary:
        'border-accent bg-accent text-accent-ink active:not-disabled:opacity-86 hover:[&:not(:disabled)]:bg-[color-mix(in_srgb,var(--accent)_86%,var(--canvas))]',
    danger: 'border-ring-control bg-surface text-danger active:not-disabled:bg-pressed hover:[&:not(:disabled)]:bg-hover',
}

const sizeClasses: Record<'md' | 'lg', string> = {
    md: 'h-8',
    lg: 'mt-2 h-10.5 text-md',
}

const classes = computed(() => [
    'inline-flex items-center justify-center gap-1.5 rounded-md border px-3 leading-none font-medium whitespace-nowrap no-underline [transition:background_var(--press),color_var(--press),border-color_var(--press)] active:not-disabled:border-ring-control',
    variantClasses[props.variant],
    sizeClasses[props.size],
])
</script>

<template>
    <RouterLink v-if="to" :to="to" :class="classes"><slot /></RouterLink>
    <a v-else-if="href" :href="href" :class="classes"><slot /></a>
    <button v-else :type="type" :class="classes"><slot /></button>
</template>
