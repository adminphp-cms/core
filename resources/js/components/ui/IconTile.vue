<script setup lang="ts">
import UiIcon from './UiIcon.vue'

withDefaults(defineProps<{ name: string; size?: 'md' | 'lg' }>(), { size: 'md' })

const sizeClasses: Record<'md' | 'lg', string> = {
    md: 'size-8 rounded-md text-muted',
    lg: 'size-11 rounded-lg text-brand',
}
</script>

<template>
    <span class="grid flex-none place-items-center border border-ring-control bg-surface" :class="sizeClasses[size]">
        <UiIcon :name="name" :size="size === 'lg' ? 20 : 16" />
    </span>
</template>
