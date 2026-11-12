<script setup lang="ts">
export type BadgeTone = 'neutral' | 'success' | 'warning' | 'danger' | 'brand'

withDefaults(defineProps<{ tone?: BadgeTone; dot?: boolean }>(), { tone: 'neutral', dot: false })

const toneClasses: Record<BadgeTone, string> = {
    neutral: 'bg-accent-soft text-muted',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
    brand: 'bg-brand-soft text-brand',
}
</script>

<template>
    <!-- `badge` stays as a hook for `.listing-status .badge + .badge`. -->
    <span class="badge inline-flex h-5.5 items-center gap-1.5 rounded-[999px] px-2 text-xs font-medium whitespace-nowrap" :class="toneClasses[tone]">
        <i v-if="dot" class="size-1.5 rounded-[50%] bg-current" :class="{ 'text-icon': tone === 'neutral' }" aria-hidden="true" />
        <slot />
    </span>
</template>
