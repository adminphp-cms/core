<script setup lang="ts">
import IconTile from './IconTile.vue'

defineProps<{ title: string; description?: string; icon?: string }>()
</script>

<template>
    <div class="mb-2 flex items-center gap-4 max-md:flex-wrap">
        <IconTile v-if="icon" :name="icon" size="lg" />
        <div class="flex min-w-0 flex-1 flex-col gap-1">
            <h1 class="text-[26px] leading-[1.15] font-semibold tracking-[-0.025em] text-strong max-md:text-2xl">{{ title }}</h1>
            <p v-if="description" class="text-md text-muted">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex gap-2"><slot name="actions" /></div>
    </div>
</template>
