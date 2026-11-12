<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { api } from '../api'
import AssetGrid from '../components/media/AssetGrid.vue'
import UploadDropzone from '../components/media/UploadDropzone.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import PageIntro from '../components/ui/PageIntro.vue'
import UiButton from '../components/ui/UiButton.vue'
import UiCard from '../components/ui/UiCard.vue'
import UiIcon from '../components/ui/UiIcon.vue'
import UiIconButton from '../components/ui/UiIconButton.vue'
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

/**
 * Next to the details card the grid shrinks to a column; on narrow screens the details move above it.
 */
const detailLayoutClasses = 'grid grid-cols-[minmax(0,1fr)_300px] items-start gap-4 max-lg:flex max-lg:flex-col-reverse'

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

            <div :class="selected ? detailLayoutClasses : undefined">
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

                <UiCard
                    v-if="selected"
                    title="Détails"
                    class="sticky top-[calc(var(--header-height)+16px)] max-lg:static [&_.ui-card-body]:flex [&_.ui-card-body]:flex-col [&_.ui-card-body]:gap-3.5"
                >
                    <template #actions>
                        <UiIconButton label="Fermer les détails" title="Fermer" @click="selected = null">
                            <UiIcon name="x" :size="14" />
                        </UiIconButton>
                    </template>

                    <img :src="selected.thumbnailUrl" :alt="selected.name" class="max-h-60 w-full rounded-md object-contain [background:repeating-conic-gradient(var(--accent-soft)_0%_25%,var(--surface)_0%_50%)_50%/16px_16px]" />
                    <dl class="m-0 grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1.5 text-sm">
                        <dt class="text-faint">Nom</dt>
                        <dd class="m-0 text-ink wrap-anywhere">{{ selected.name }}</dd>
                        <template v-if="selected.width && selected.height">
                            <dt class="text-faint">Dimensions</dt>
                            <dd class="m-0 text-ink wrap-anywhere">{{ selected.width }} × {{ selected.height }} px</dd>
                        </template>
                        <dt class="text-faint">Poids</dt>
                        <dd class="m-0 text-ink wrap-anywhere">{{ formatBytes(selected.size) }}</dd>
                        <dt class="text-faint">Ajoutée</dt>
                        <dd class="m-0 text-ink wrap-anywhere">{{ formatDateTime(selected.createdAt) }}</dd>
                    </dl>
                    <UiButton @click="copyUrl"><UiIcon name="link" :size="14" /> {{ copied ? 'Adresse copiée' : 'Copier l’adresse' }}</UiButton>
                </UiCard>
            </div>
        </div>
    </main>
</template>
