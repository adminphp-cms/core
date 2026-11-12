<script setup lang="ts">
import { formatBytes } from '../../format'
import type { Asset } from '../../types'

defineProps<{ assets: Asset[]; selectedId?: string | null }>()

const emit = defineEmits<{ select: [asset: Asset] }>()
</script>

<template>
    <ul class="asset-grid">
        <li v-for="asset in assets" :key="asset.id">
            <button type="button" class="asset-tile" :aria-pressed="selectedId === asset.id" :title="asset.name" @click="emit('select', asset)">
                <span class="asset-thumb"><img :src="asset.url" :alt="asset.name" loading="lazy" /></span>
                <span class="asset-name">{{ asset.name }}</span>
                <span class="asset-meta">
                    <template v-if="asset.width && asset.height">{{ asset.width }} × {{ asset.height }} · </template>{{ formatBytes(asset.size) }}
                </span>
            </button>
        </li>
    </ul>
</template>
