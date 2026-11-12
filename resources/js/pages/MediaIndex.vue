<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { api } from '../api'
import AssetGrid from '../components/media/AssetGrid.vue'
import UploadDropzone from '../components/media/UploadDropzone.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import PageIntro from '../components/ui/PageIntro.vue'
import UiCard from '../components/ui/UiCard.vue'
import UiIcon from '../components/ui/UiIcon.vue'
import { formatBytes, formatDateTime } from '../format'
import { useAssetsStore } from '../stores/assets'
import type { Asset } from '../types'

const assetsStore = useAssetsStore()
const assets = ref<Asset[]>([])
const total = ref(0)
const search = ref('')
const isLoaded = ref(false)
const selected = ref<Asset | null>(null)
const copied = ref(false)

async function load(): Promise<void> {
    const response = await api.assets(search.value.trim())
    response.data.forEach(assetsStore.remember)
    assets.value = response.data
    total.value = response.meta.total
    isLoaded.value = true
}

let searchTimer: ReturnType<typeof setTimeout> | undefined

watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(load, 250)
})

function onUploaded(asset: Asset): void {
    assets.value = [asset, ...assets.value]
    total.value++
    selected.value = asset
}

async function copyUrl(): Promise<void> {
    if (selected.value) {
        await navigator.clipboard.writeText(new URL(selected.value.url, window.location.origin).href)
        copied.value = true
        setTimeout(() => (copied.value = false), 1500)
    }
}

onMounted(load)
</script>

<template>
    <main class="page">
        <header class="top">
            <div class="crumbs"><strong>Médiathèque</strong></div>
        </header>

        <div class="page-body">
            <PageIntro title="Médiathèque" description="Les images de vos contenus, réutilisables partout." icon="image" />

            <UploadDropzone multiple @uploaded="onUploaded" />

            <div class="media-layout" :class="{ 'has-detail': selected }">
                <UiCard :title="`${total} ${total > 1 ? 'images' : 'image'}`">
                    <template #actions>
                        <label class="search-field">
                            <UiIcon name="search" :size="14" />
                            <input v-model="search" type="search" placeholder="Rechercher" aria-label="Rechercher une image" />
                        </label>
                    </template>

                    <AssetGrid v-if="assets.length" :assets="assets" :selected-id="selected?.id" @select="selected = $event" />
                    <EmptyState
                        v-else-if="isLoaded"
                        icon="image"
                        :title="search ? `Aucune image pour « ${search} »` : 'Pas encore d’image'"
                        description="Glissez vos premières images dans la zone ci-dessus."
                    />
                </UiCard>

                <UiCard v-if="selected" title="Détails" class="media-detail">
                    <template #actions>
                        <button type="button" class="icon-button" aria-label="Fermer les détails" title="Fermer" @click="selected = null">
                            <UiIcon name="x" :size="14" />
                        </button>
                    </template>

                    <img :src="selected.url" :alt="selected.name" class="media-detail-image" />
                    <dl class="media-detail-list">
                        <dt>Nom</dt>
                        <dd>{{ selected.name }}</dd>
                        <template v-if="selected.width && selected.height">
                            <dt>Dimensions</dt>
                            <dd>{{ selected.width }} × {{ selected.height }} px</dd>
                        </template>
                        <dt>Poids</dt>
                        <dd>{{ formatBytes(selected.size) }}</dd>
                        <dt>Ajoutée</dt>
                        <dd>{{ formatDateTime(selected.createdAt) }}</dd>
                    </dl>
                    <button type="button" class="btn" @click="copyUrl"><UiIcon name="link" :size="14" /> {{ copied ? 'Adresse copiée' : 'Copier l’adresse' }}</button>
                </UiCard>
            </div>
        </div>
    </main>
</template>
