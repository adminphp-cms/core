<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { api } from '../../api'
import { useAssetsStore } from '../../stores/assets'
import type { Asset } from '../../types'
import EmptyState from '../ui/EmptyState.vue'
import UiIcon from '../ui/UiIcon.vue'
import AssetGrid from './AssetGrid.vue'
import UploadDropzone from './UploadDropzone.vue'

defineProps<{ selectedId?: string | null }>()

const emit = defineEmits<{ pick: [asset: Asset]; close: [] }>()

const assetsStore = useAssetsStore()
const dialog = ref<HTMLDialogElement | null>(null)
const assets = ref<Asset[]>([])
const search = ref('')
const isLoaded = ref(false)

async function load(): Promise<void> {
    const response = await api.assets(search.value.trim())
    response.data.forEach(assetsStore.remember)
    assets.value = response.data
    isLoaded.value = true
}

let searchTimer: ReturnType<typeof setTimeout> | undefined

watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(load, 250)
})

function pick(asset: Asset): void {
    emit('pick', asset)
    dialog.value?.close()
}

onMounted(() => {
    dialog.value?.showModal()
    load()
})
</script>

<template>
    <dialog ref="dialog" class="media-picker" aria-labelledby="media-picker-title" @close="emit('close')">
        <header class="media-picker-header">
            <h2 id="media-picker-title">Médiathèque</h2>
            <label class="search-field">
                <UiIcon name="search" :size="14" />
                <input v-model="search" type="search" placeholder="Rechercher une image" aria-label="Rechercher une image" />
            </label>
            <button type="button" class="icon-button" aria-label="Fermer" title="Fermer" @click="dialog?.close()">
                <UiIcon name="x" :size="14" />
            </button>
        </header>

        <div class="media-picker-body">
            <UploadDropzone compact @uploaded="pick" />

            <AssetGrid v-if="assets.length" :assets="assets" :selected-id="selectedId" @select="pick" />
            <EmptyState
                v-else-if="isLoaded"
                icon="image"
                :title="search ? `Aucune image pour « ${search} »` : 'La médiathèque est vide'"
                description="Envoyez une première image ci-dessus."
            />
        </div>
    </dialog>
</template>
