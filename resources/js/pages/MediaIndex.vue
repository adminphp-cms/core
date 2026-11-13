<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { api } from '../api'
import MediaGrid from '../components/media/MediaGrid.vue'
import UploadDropzone from '../components/media/UploadDropzone.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import PageIntro from '../components/ui/PageIntro.vue'
import UiButton from '../components/ui/UiButton.vue'
import UiCard from '../components/ui/UiCard.vue'
import UiIcon from '../components/ui/UiIcon.vue'
import UiIconButton from '../components/ui/UiIconButton.vue'
import { useToast } from '../composables/useToast'
import { formatBytes, formatDateTime } from '../format'
import { useMediasStore } from '../stores/medias'
import type { RouteLocationRaw } from 'vue-router'
import type { Media, MediaUsage } from '../types'

const mediasStore = useMediasStore()
const medias = ref<Media[]>([])
const total = ref(0)
const search = ref('')
const isLoaded = ref(false)
const selected = ref<Media | null>(null)
const copied = ref(false)
const isDeleting = ref(false)
const toast = useToast()

/**
 * Next to the details card the grid shrinks to a column; on narrow screens the details move above it.
 */
const detailLayoutClasses = 'grid grid-cols-[minmax(0,1fr)_300px] items-start gap-4 max-lg:flex max-lg:flex-col-reverse'

async function load(): Promise<void> {
    const response = await api.medias(search.value.trim())
    response.data.forEach(mediasStore.remember)
    medias.value = response.data
    total.value = response.meta.total
    isLoaded.value = true
}

/**
 * Loads the contents using the selected image; the listing only knows how many there are.
 */
watch(
    () => selected.value?.id,
    async (id) => {
        if (!id) {
            return
        }

        const media = await api.media(id)

        if (selected.value?.id === id) {
            selected.value = media
        }
    },
)

/**
 * A trashed content cannot be opened: its link leads to the trash of its module.
 */
function usageLocation(usage: MediaUsage): RouteLocationRaw {
    if (!usage.id) {
        return { name: 'settings' }
    }

    return usage.isTrashed
        ? { name: 'module.index', params: { module: usage.type }, query: { view: 'trash' } }
        : { name: 'module.edit', params: { module: usage.type, id: usage.id } }
}

async function deleteSelected(): Promise<void> {
    const media = selected.value

    if (!media || !window.confirm(`Supprimer définitivement « ${media.name} » ? Cette action est irréversible.`)) {
        return
    }

    isDeleting.value = true

    try {
        await api.deleteMedia(media.id)
        mediasStore.forget(media.id)
        medias.value = medias.value.filter((candidate) => candidate.id !== media.id)
        total.value--
        selected.value = null
        toast.success('Image supprimée')
    } catch (error) {
        toast.error(error instanceof Error ? error.message : 'La suppression a échoué.')
    } finally {
        isDeleting.value = false
    }
}

let searchTimer: ReturnType<typeof setTimeout> | undefined

watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(load, 250)
})

function onUploaded(media: Media): void {
    medias.value = [media, ...medias.value]
    total.value++
    selected.value = media
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

                    <MediaGrid v-if="medias.length" :medias="medias" :selected-id="selected?.id" @select="selected = $event" />
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

                    <section v-if="selected.usages" class="flex flex-col gap-2 border-t border-line pt-3.5">
                        <h3 class="m-0 text-sm font-semibold text-ink">
                            {{ selected.usages.length ? `Utilisée dans ${selected.usages.length} ${selected.usages.length > 1 ? 'contenus' : 'contenu'}` : 'Utilisée nulle part' }}
                        </h3>
                        <ul v-if="selected.usages.length" class="m-0 flex list-none flex-col gap-1 p-0">
                            <li v-for="usage in selected.usages" :key="`${usage.type}:${usage.id}`">
                                <RouterLink
                                    :to="usageLocation(usage)"
                                    class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-ink no-underline hover:bg-hover"
                                >
                                    <UiIcon :name="usage.icon" :size="14" class="shrink-0 text-faint" />
                                    <span class="min-w-0 flex-1 truncate">{{ usage.title }}</span>
                                    <span class="shrink-0 text-xs text-faint">{{ usage.isTrashed ? 'À la corbeille' : usage.moduleTitle }}</span>
                                </RouterLink>
                            </li>
                        </ul>
                        <UiButton variant="danger" :disabled="isDeleting || selected.usages.length > 0" @click="deleteSelected">
                            <UiIcon name="trash" :size="14" /> Supprimer l’image
                        </UiButton>
                        <p v-if="selected.usages.length" class="m-0 text-xs text-faint">Retirez-la de ces contenus pour pouvoir la supprimer.</p>
                    </section>
                </UiCard>
            </div>
        </div>
    </main>
</template>
