<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '../api'
import BrandLogo from '../components/BrandLogo.vue'
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
            <div class="brand" role="img" aria-label="AdminPHP">
                <BrandLogo />
            </div>

            <h1>Bon retour parmi nous</h1>
            <p class="login-lead">Connectez-vous pour retrouver vos contenus.</p>

            <label class="login-field">
                <span>Email</span>
                <input v-model="email" type="email" autocomplete="username" required autofocus />
            </label>

            <label class="login-field">
                <span>Mot de passe</span>
                <input v-model="password" type="password" autocomplete="current-password" required />
            </label>

            <div class="flex items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-muted">
                    <input v-model="remember" type="checkbox" class="accent-accent" />
                    Rester connecté
                </label>
                <RouterLink :to="{ name: 'password.forgot', query: email ? { email } : {} }" class="font-medium text-ink underline decoration-ring-hover underline-offset-3">Mot de passe oublié ?</RouterLink>
            </div>

            <p v-if="error" class="field-error" role="alert">{{ error }}</p>

            <button type="submit" class="btn primary" :disabled="isSubmitting">
                {{ isSubmitting ? 'Connexion…' : 'Se connecter' }}
            </button>
        </form>
    </main>
</template>
