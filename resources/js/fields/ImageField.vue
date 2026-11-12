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
    <div class="flex flex-col gap-2.5">
        <div v-if="asset" class="flex items-center gap-4 rounded-lg border border-ring-control bg-surface p-2.5 max-md:flex-col max-md:items-stretch">
            <img
                class="h-28 w-[168px] flex-none rounded-sm bg-accent-soft object-cover max-md:aspect-[3/2] max-md:h-auto max-md:w-full"
                :src="asset.url"
                :alt="asset.name"
            />
            <div class="flex min-w-0 flex-col gap-0.5">
                <p class="overflow-hidden font-medium text-ellipsis whitespace-nowrap text-strong">{{ asset.name }}</p>
                <p class="text-xs text-faint">
                    <template v-if="asset.width && asset.height">{{ asset.width }} × {{ asset.height }} px · </template>{{ formatBytes(asset.size) }}
                </p>
                <div class="mt-2.5 flex flex-wrap gap-2">
                    <button type="button" class="btn" @click="isPickerOpen = true"><UiIcon name="image" :size="14" /> Remplacer</button>
                    <button type="button" class="btn danger" @click="emit('update:modelValue', null)"><UiIcon name="x" :size="14" /> Retirer</button>
                </div>
            </div>
        </div>

        <p v-else-if="assetId && isMissing" class="field-error">Cette image n’existe plus dans la médiathèque.</p>

        <div v-if="!asset" class="flex flex-col items-start gap-2.5 [&_.dropzone]:self-stretch">
            <UploadDropzone compact @uploaded="choose" />
            <button type="button" class="btn" @click="isPickerOpen = true"><UiIcon name="image" :size="14" /> Choisir dans la médiathèque</button>
        </div>

        <MediaPicker v-if="isPickerOpen" :selected-id="assetId" @pick="choose" @close="isPickerOpen = false" />
    </div>
</template>
