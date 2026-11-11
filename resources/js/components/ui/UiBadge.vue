<script setup lang="ts">
export type BadgeTone = 'neutral' | 'success' | 'warning' | 'danger' | 'brand'

withDefaults(defineProps<{ tone?: BadgeTone; dot?: boolean }>(), { tone: 'neutral', dot: false })
</script>

<template>
    <span class="badge" :class="`is-${tone}`">
        <i v-if="dot" class="badge-dot" aria-hidden="true" />
        <slot />
    </span>
</template>
