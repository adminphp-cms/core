import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '../api'
import type { Asset } from '../types'

/**
 * Assets already loaded, so a form shows the preview of its images without asking twice.
 */
export const useAssetsStore = defineStore('assets', () => {
    const byId = ref<Record<string, Asset>>({})
    const pending = new Map<string, Promise<Asset | null>>()

    function remember(asset: Asset): void {
        byId.value[asset.id] = asset
    }

    async function find(id: string): Promise<Asset | null> {
        if (byId.value[id]) {
            return byId.value[id]
        }

        if (!pending.has(id)) {
            pending.set(
                id,
                api
                    .asset(id)
                    .then((asset) => {
                        remember(asset)

                        return asset
                    })
                    .catch(() => null)
                    .finally(() => pending.delete(id)),
            )
        }

        return pending.get(id)!
    }

    return { byId, remember, find }
})
