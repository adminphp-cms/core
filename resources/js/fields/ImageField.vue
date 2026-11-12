<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import MediaPicker from '../components/media/MediaPicker.vue'
import UploadDropzone from '../components/media/UploadDropzone.vue'
import UiIcon from '../components/ui/UiIcon.vue'
import { formatBytes } from '../format'
import { useAssetsStore } from '../stores/assets'
import type { Asset, ImageFieldSchema } from '../types'

const props = defineProps<{
    field: ImageFieldSchema
    modelValue: unknown
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>()

const assetsStore = useAssetsStore()
const isPickerOpen = ref(false)
const isMissing = ref(false)

const assetId = computed(() => (typeof props.modelValue === 'string' && props.modelValue !== '' ? props.modelValue : null))
const asset = computed<Asset | null>(() => (assetId.value ? (assetsStore.byId[assetId.value] ?? null) : null))

watch(
    assetId,
    async (id) => {
        isMissing.value = false

        if (id && !assetsStore.byId[id]) {
            isMissing.value = (await assetsStore.find(id)) === null
        }
    },
    { immediate: true },
)

function choose(chosen: Asset): void {
    emit('update:modelValue', chosen.id)
    isPickerOpen.value = false
}
</script>

<template>
    <div class="image-field">
        <div v-if="asset" class="image-preview">
            <img :src="asset.url" :alt="asset.name" />
            <div class="image-preview-info">
                <p class="image-preview-name">{{ asset.name }}</p>
                <p class="image-preview-meta">
                    <template v-if="asset.width && asset.height">{{ asset.width }} × {{ asset.height }} px · </template>{{ formatBytes(asset.size) }}
                </p>
                <div class="image-preview-actions">
                    <button type="button" class="btn" @click="isPickerOpen = true"><UiIcon name="image" :size="14" /> Remplacer</button>
                    <button type="button" class="btn danger" @click="emit('update:modelValue', null)"><UiIcon name="x" :size="14" /> Retirer</button>
                </div>
            </div>
        </div>

        <p v-else-if="assetId && isMissing" class="field-error">Cette image n’existe plus dans la médiathèque.</p>

        <div v-if="!asset" class="image-empty">
            <UploadDropzone compact @uploaded="choose" />
            <button type="button" class="btn" @click="isPickerOpen = true"><UiIcon name="image" :size="14" /> Choisir dans la médiathèque</button>
        </div>

        <MediaPicker v-if="isPickerOpen" :selected-id="assetId" @pick="choose" @close="isPickerOpen = false" />
    </div>
</template>
