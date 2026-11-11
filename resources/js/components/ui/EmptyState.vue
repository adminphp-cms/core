<script setup lang="ts">
import IconTile from './IconTile.vue'

defineProps<{ icon: string; title: string; description?: string }>()
</script>

<template>
    <div class="empty-state">
        <IconTile :name="icon" size="lg" />
        <p class="empty-state-title">{{ title }}</p>
        <p v-if="description" class="empty-state-text">{{ description }}</p>
        <div v-if="$slots.default" class="empty-state-actions"><slot /></div>
    </div>
</template>
