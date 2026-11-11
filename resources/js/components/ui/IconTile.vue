<script setup lang="ts">
import UiIcon from './UiIcon.vue'

withDefaults(defineProps<{ name: string; size?: 'md' | 'lg' }>(), { size: 'md' })
</script>

<template>
    <span class="icon-tile" :class="`is-${size}`">
        <UiIcon :name="name" :size="size === 'lg' ? 20 : 16" />
    </span>
</template>
