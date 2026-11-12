<script setup lang="ts">
import { computed, ref } from 'vue'
import { api, ApiError } from '../../api'
import { useAssetsStore } from '../../stores/assets'
import type { Asset } from '../../types'
import UiIcon from '../ui/UiIcon.vue'

const props = withDefaults(defineProps<{ multiple?: boolean; compact?: boolean }>(), { multiple: false, compact: false })

const emit = defineEmits<{ uploaded: [asset: Asset] }>()

const assetsStore = useAssetsStore()
const input = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)
const uploadingCount = ref(0)
const errors = ref<string[]>([])

/**
 * Uploads one file after the other, so a batch keeps the order it was dropped in.
 */
async function upload(files: FileList | File[]): Promise<void> {
    errors.value = []

    for (const file of Array.from(files)) {
        uploadingCount.value++

        try {
            const asset = await api.uploadAsset(file)
            assetsStore.remember(asset)
            emit('uploaded', asset)
        } catch (error) {
            const message = error instanceof ApiError ? (error.errors.file?.[0] ?? error.message) : 'L’envoi a échoué.'
            errors.value.push(`${file.name} : ${message}`)
        } finally {
            uploadingCount.value--
        }
    }
}

function onDrop(event: DragEvent): void {
    isDragging.value = false

    if (event.dataTransfer?.files.length) {
        upload(event.dataTransfer.files)
    }
}

function onPick(event: Event): void {
    const target = event.target as HTMLInputElement

    if (target.files?.length) {
        upload(target.files)
    }

    target.value = ''
}

/**
 * The compact variant sits in a row inside the media picker, the regular one is a centred block.
 */
const layoutClasses = computed(() => (props.compact ? 'flex-row justify-start gap-3 rounded-lg px-4 py-3.5 text-left' : 'flex-col gap-1.5 rounded-xl px-5 py-7 text-center'))

const stateClasses = computed(() => (isDragging.value ? 'border-brand bg-brand-soft' : 'border-ring-control bg-surface hover:border-icon'))

defineExpose({ open: () => input.value?.click() })
</script>

<template>
    <div
        class="dropzone flex items-center border-[1.5px] border-dashed transition-[background,border-color]"
        :class="[layoutClasses, stateClasses]"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="onDrop"
    >
        <input ref="input" type="file" accept="image/*" :multiple="multiple" hidden @change="onPick" />

        <span class="icon-tile" :class="uploadingCount > 0 ? 'text-brand' : 'text-muted'" aria-hidden="true"><UiIcon :name="uploadingCount > 0 ? 'upload' : 'image-plus'" :size="18" /></span>

        <p class="text-muted">
            <template v-if="uploadingCount > 0">Envoi en cours…</template>
            <template v-else>
                Glissez {{ multiple ? 'des images' : 'une image' }} ici ou
                <button type="button" class="link-button text-[length:inherit] text-brand" @click="input?.click()">parcourez vos fichiers</button>
            </template>
        </p>
        <p v-if="!compact" class="text-xs text-faint">JPG, PNG, WebP, GIF ou AVIF.</p>

        <ul v-if="errors.length" class="mx-0 mt-1 mb-0 list-none p-0 text-sm text-danger" role="alert">
            <li v-for="error in errors" :key="error">{{ error }}</li>
        </ul>
    </div>
</template>
