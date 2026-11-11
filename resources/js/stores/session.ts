import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '../api'
import type { AdminUser } from '../types'

/**
 * The logged in admin user. `isLoaded` tells whether the session was checked yet.
 */
export const useSessionStore = defineStore('session', () => {
    const user = ref<AdminUser | null>(null)
    const isLoaded = ref(false)

    async function load(): Promise<void> {
        user.value = await api.currentUser()
        isLoaded.value = true
    }

    async function login(email: string, password: string, remember: boolean): Promise<void> {
        user.value = await api.login({ email, password, remember })
    }

    async function logout(): Promise<void> {
        await api.logout()
        forget()
    }

    /**
     * Drops the user locally, e.g. when the server says the session has expired.
     */
    function forget(): void {
        user.value = null
    }

    return { user, isLoaded, load, login, logout, forget }
})
