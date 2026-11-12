import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '../api'
import type { ContentReference, ReferencedContent } from '../types'

export function referenceKey(reference: ContentReference): string {
    return `${reference.type}:${reference.id}`
}

/**
 * Contents chosen in Browser fields, already named, so a form asks for each one only once.
 * A key set to null is a content that no longer exists.
 */
export const useReferencesStore = defineStore('references', () => {
    const byKey = ref<Record<string, ReferencedContent | null>>({})
    const pending = new Set<string>()

    function remember(content: ReferencedContent): void {
        byKey.value[referenceKey(content)] = content
    }

    async function load(references: ContentReference[]): Promise<void> {
        const missing = references.filter((reference) => {
            const key = referenceKey(reference)

            return !(key in byKey.value) && !pending.has(key)
        })

        if (missing.length === 0) {
            return
        }

        const keys = missing.map(referenceKey)
        keys.forEach((key) => pending.add(key))

        try {
            ;(await api.references(missing)).forEach(remember)
            keys.filter((key) => !(key in byKey.value)).forEach((key) => (byKey.value[key] = null))
        } finally {
            keys.forEach((key) => pending.delete(key))
        }
    }

    return { byKey, remember, load }
})
