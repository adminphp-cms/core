<script setup lang="ts">
import { formatBytes } from '../../format'
import type { Media } from '../../types'

defineProps<{ medias: Media[]; selectedId?: string | null }>()

const emit = defineEmits<{ select: [media: Media] }>()
</script>

<template>
    <ul class="m-0 grid list-none grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-3.5 p-0 max-md:grid-cols-[repeat(auto-fill,minmax(120px,1fr))]">
        <li v-for="media in medias" :key="media.id">
            <button
                type="button"
                class="flex w-full flex-col gap-1 rounded-lg border-0 bg-transparent bg-none px-1.5 pt-1.5 pb-2.5 text-left transition-[background,border-color] hover:not-aria-pressed:bg-hover aria-pressed:bg-brand-soft aria-pressed:outline-[1.5px] aria-pressed:outline-solid aria-pressed:outline-brand"
                :aria-pressed="selectedId === media.id"
                :title="media.name"
                @click="emit('select', media)"
            >
                <span class="mb-1 block aspect-[4/3] overflow-hidden rounded-sm border border-line [background:repeating-conic-gradient(var(--accent-soft)_0%_25%,var(--surface)_0%_50%)_50%/16px_16px]">
                    <img :src="media.thumbnailUrl" :alt="media.name" loading="lazy" class="block h-full w-full object-cover" />
                </span>
                <span class="truncate text-sm font-medium text-ink">{{ media.name }}</span>
                <span class="text-xs text-faint tabular-nums">
                    <template v-if="media.width && media.height">{{ media.width }} × {{ media.height }} · </template>{{ formatBytes(media.size) }}
                </span>
            </button>
        </li>
    </ul>
</template>
