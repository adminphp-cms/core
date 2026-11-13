import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '../api'
import type { Media } from '../types'

/**
 * Medias already loaded, so a form shows the preview of its images without asking twice.
 */
export const useMediasStore = defineStore('medias', () => {
    const byId = ref<Record<string, Media>>({})
    const pending = new Map<string, Promise<Media | null>>()

    function remember(media: Media): void {
        byId.value[media.id] = media
    }

    async function find(id: string): Promise<Media | null> {
        if (byId.value[id]) {
            return byId.value[id]
        }

        if (!pending.has(id)) {
            pending.set(
                id,
                api
                    .media(id)
                    .then((media) => {
                        remember(media)

                        return media
                    })
                    .catch(() => null)
                    .finally(() => pending.delete(id)),
            )
        }

        return pending.get(id)!
    }

    function forget(id: string): void {
        delete byId.value[id]
    }

    return { byId, remember, find, forget }
})
