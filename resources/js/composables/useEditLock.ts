import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { api } from '../api'
import type { EditLock } from '../types'

const RENEW_EVERY_MS = 30_000

/**
 * Keeps the edit lock of an item while its editor is open: one admin user edits at a time,
 * the others see the item read-only until it is free or they take over.
 *
 * Someone who loses the lock (taken over) stays read-only even once it is free again: their
 * form may be older than what was saved since, so they reload instead of overwriting it.
 * Someone who was waiting gets the lock as soon as it is free, and `onGained` reloads the item.
 */
export function useEditLock(module: string, id: string | undefined, onGained: () => Promise<void>) {
    const lock = ref<EditLock | null>(null)
    const hasLost = ref(false)
    let timer: ReturnType<typeof setInterval> | undefined
    let isClosed = false

    const isReadOnly = computed(() => hasLost.value || (lock.value !== null && !lock.value.isMine))

    async function renew(takeOver = false): Promise<void> {
        if (!id || isClosed || (hasLost.value && !takeOver)) {
            return
        }

        const wasMine = lock.value?.isMine === true
        const wasWaiting = lock.value !== null && !lock.value.isMine
        const hadLost = hasLost.value

        try {
            lock.value = await api.lockItem(module, id, takeOver)
        } catch {
            return
        }

        if (wasMine && !lock.value.isMine) {
            hasLost.value = true
            stopRenewing()
        } else if (lock.value.isMine) {
            hasLost.value = false

            // Someone who only watched gets the latest saved version; someone taking back the
            // lock they lost keeps their own form, by choice.
            if (wasWaiting && !hadLost) {
                await onGained()
            }
        }
    }

    function stopRenewing(): void {
        clearInterval(timer)
    }

    function release(): void {
        if (id && lock.value?.isMine && !isClosed) {
            isClosed = true
            api.unlockItem(module, id).catch(() => {})
        }
    }

    /** Takes the item from whoever edits it; their next save will be refused. */
    async function takeOver(): Promise<void> {
        await renew(true)
        stopRenewing()
        timer = setInterval(() => renew(), RENEW_EVERY_MS)
    }

    /** Called when a save is refused because someone else holds the lock. */
    async function markLost(): Promise<void> {
        hasLost.value = true
        stopRenewing()

        if (id) {
            lock.value = await api.lockItem(module, id).catch(() => lock.value)
        }
    }

    onMounted(() => {
        if (!id) {
            return
        }

        renew()
        timer = setInterval(() => renew(), RENEW_EVERY_MS)
        window.addEventListener('pagehide', release)
    })

    onBeforeUnmount(() => {
        stopRenewing()
        window.removeEventListener('pagehide', release)
        release()
    })

    return { lock, isReadOnly, hasLost, takeOver, markLost }
}
