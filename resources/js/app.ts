import '@fontsource-variable/figtree'
import '../css/app.css'

import { createPinia } from 'pinia'
import { createApp } from 'vue'
import { ApiError } from './api'
import App from './App.vue'
import './composables/useTheme'
import { useToast } from './composables/useToast'
import { router } from './router'

const app = createApp(App).use(createPinia()).use(router)

/**
 * Errors no screen handled itself still reach the user, instead of failing silently.
 * An expired session is handled by the API client (back to the login page).
 */
function reportUnexpectedError(error: unknown): void {
    if (error instanceof ApiError && error.status === 401) {
        return
    }

    console.error(error)

    const message =
        error instanceof ApiError && error.status === 422
            ? error.message
            : error instanceof ApiError && error.status === 403
              ? 'Vous n’avez pas les droits pour cette action.'
              : 'Une erreur est survenue. Réessayez dans un instant.'

    useToast().error(message)
}

app.config.errorHandler = reportUnexpectedError
window.addEventListener('unhandledrejection', (event) => reportUnexpectedError(event.reason))

app.mount('#adminphp')
