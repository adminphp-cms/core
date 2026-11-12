<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { api, ApiError } from '../api'
import BrandLogo from '../components/BrandLogo.vue'
import UiButton from '../components/ui/UiButton.vue'

const route = useRoute()

const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const sentMessage = ref<string | null>(null)
const error = ref<string | null>(null)
const isSubmitting = ref(false)

async function submit(): Promise<void> {
    isSubmitting.value = true
    error.value = null

    try {
        sentMessage.value = await api.forgotPassword(email.value)
    } catch (exception) {
        if (exception instanceof ApiError && (exception.status === 422 || exception.status === 429)) {
            error.value = exception.status === 429 ? 'Trop de demandes. Réessayez dans une minute.' : (exception.errors.email?.[0] ?? exception.message)
        } else {
            error.value = 'L’envoi a échoué. Réessayez.'
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

            <h1>Mot de passe oublié</h1>

            <template v-if="sentMessage">
                <p class="login-lead" role="status">{{ sentMessage }} Pensez à regarder dans vos indésirables.</p>
            </template>

            <template v-else>
                <p class="login-lead">Indiquez votre email : nous vous envoyons un lien pour choisir un nouveau mot de passe.</p>

                <label class="login-field">
                    <span>Email</span>
                    <input v-model="email" type="email" autocomplete="username" required autofocus />
                </label>

                <p v-if="error" class="field-error" role="alert">{{ error }}</p>

                <UiButton type="submit" variant="primary" size="lg" :disabled="isSubmitting">
                    {{ isSubmitting ? 'Envoi…' : 'Envoyer le lien' }}
                </UiButton>
            </template>

            <RouterLink :to="{ name: 'login' }" class="self-center text-sm font-medium text-muted underline decoration-ring-hover underline-offset-3">Retour à la connexion</RouterLink>
        </form>
    </main>
</template>
