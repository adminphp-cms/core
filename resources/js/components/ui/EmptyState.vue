<script setup lang="ts">
import { inject } from 'vue'
import IconTile from './IconTile.vue'
import { cardContextKey } from './UiCard.vue'

defineProps<{ icon: string; title: string; description?: string }>()

const isInCard = inject(cardContextKey, false)
</script>

<template>
    <div
        class="flex flex-col items-center gap-2 px-6 text-center [.page-body>&]:rounded-xl [.page-body>&]:border [.page-body>&]:border-ring-card [.page-body>&]:bg-surface"
        :class="isInCard ? 'py-10' : 'py-12'"
    >
        <IconTile :name="icon" size="lg" class="mb-2" />
        <p class="text-md font-semibold text-strong">{{ title }}</p>
        <p v-if="description" class="max-w-[380px] text-muted">{{ description }}</p>
        <div v-if="$slots.default" class="mt-3"><slot /></div>
    </div>
</template>
