<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { api } from '../../api'
import { useMediasStore } from '../../stores/medias'
import type { Media } from '../../types'
import EmptyState from '../ui/EmptyState.vue'
import UiIcon from '../ui/UiIcon.vue'
import UiIconButton from '../ui/UiIconButton.vue'
import MediaGrid from './MediaGrid.vue'
import UploadDropzone from './UploadDropzone.vue'

defineProps<{ selectedId?: string | null }>()

const emit = defineEmits<{ pick: [media: Media]; close: [] }>()

const mediasStore = useMediasStore()
const dialog = ref<HTMLDialogElement | null>(null)
const medias = ref<Media[]>([])
const search = ref('')
const isLoaded = ref(false)

async function load(): Promise<void> {
    const response = await api.medias(search.value.trim())
    response.data.forEach(mediasStore.remember)
    medias.value = response.data
    isLoaded.value = true
}

let searchTimer: ReturnType<typeof setTimeout> | undefined

watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(load, 250)
})

function pick(media: Media): void {
    emit('pick', media)
    dialog.value?.close()
}

onMounted(() => {
    dialog.value?.showModal()
    load()
})
</script>

<template>
    <dialog
        ref="dialog"
        class="m-auto max-h-[min(720px,calc(100vh-48px))] w-[min(880px,calc(100vw-32px))] overflow-hidden rounded-2xl border border-ring-card bg-surface p-0 text-ink shadow-popover backdrop:bg-[rgba(9,9,11,0.32)] backdrop:[backdrop-filter:blur(2px)] open:flex open:animate-[pop-in_180ms_var(--ease-snappy)] open:flex-col"
        aria-labelledby="media-picker-title"
        @close="emit('close')"
    >
        <header class="flex items-center gap-2.5 border-b border-line py-3.5 pr-4 pl-5">
            <h2 id="media-picker-title" class="flex-1 text-lg font-semibold text-strong">Médiathèque</h2>
            <label class="search-field">
                <UiIcon name="search" :size="14" />
                <input v-model="search" type="search" placeholder="Rechercher une image" aria-label="Rechercher une image" />
            </label>
            <UiIconButton label="Fermer" @click="dialog?.close()">
                <UiIcon name="x" :size="14" />
            </UiIconButton>
        </header>

        <div class="flex flex-col gap-4 overflow-y-auto px-5 pt-4 pb-5">
            <UploadDropzone compact @uploaded="pick" />

            <MediaGrid v-if="medias.length" :medias="medias" :selected-id="selectedId" @select="pick" />
            <EmptyState
                v-else-if="isLoaded"
                icon="image"
                :title="search ? `Aucune image pour « ${search} »` : 'La médiathèque est vide'"
                description="Envoyez une première image ci-dessus."
            />
        </div>
    </dialog>
</template>
