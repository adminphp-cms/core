<script setup lang="ts">
import IconTile from './IconTile.vue'

defineProps<{ title: string; description?: string; icon?: string }>()
</script>

<template>
    <div class="page-intro">
        <IconTile v-if="icon" :name="icon" size="lg" />
        <div class="page-intro-text">
            <h1>{{ title }}</h1>
            <p v-if="description">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="page-intro-actions"><slot name="actions" /></div>
    </div>
</template>
