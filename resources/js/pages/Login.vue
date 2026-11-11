<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '../api'
import { useSessionStore } from '../stores/session'

const session = useSessionStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const remember = ref(false)
const error = ref<string | null>(null)
const isSubmitting = ref(false)

async function submit(): Promise<void> {
    isSubmitting.value = true
    error.value = null

    try {
        await session.login(email.value, password.value, remember.value)

        const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') ? route.query.redirect : '/'
        await router.replace(redirect)
    } catch (exception) {
        if (exception instanceof ApiError && (exception.status === 422 || exception.status === 429)) {
            error.value = exception.errors.email?.[0] ?? exception.message
            password.value = ''
        } else {
            error.value = 'La connexion a échoué. Réessayez.'
            throw exception
        }
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <main class="login">
        <form class="login-card" @submit.prevent="submit">
            <div class="brand">
                <svg width="22" height="22" viewBox="0 0 22 22" aria-hidden="true">
                    <rect x="1" y="1" width="20" height="20" rx="5" fill="var(--accent)" />
                    <path d="M6 15l5-9 5 9M8.2 11.5h5.6" stroke="var(--accent-ink)" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                AdminPhp
            </div>

            <h1>Connexion</h1>

            <label class="login-field">
                <span>Email</span>
                <input v-model="email" type="email" autocomplete="username" required autofocus />
            </label>

            <label class="login-field">
                <span>Mot de passe</span>
                <input v-model="password" type="password" autocomplete="current-password" required />
            </label>

            <label class="login-remember">
                <input v-model="remember" type="checkbox" />
                Rester connecté
            </label>

            <p v-if="error" class="field-error" role="alert">{{ error }}</p>

            <button type="submit" class="btn primary" :disabled="isSubmitting">
                {{ isSubmitting ? 'Connexion…' : 'Se connecter' }}
            </button>
        </form>
    </main>
</template>
