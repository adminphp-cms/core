<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '../api'
import BrandLogo from '../components/BrandLogo.vue'
import { useSessionStore } from '../stores/session'
import UiButton from '../components/ui/UiButton.vue'

/**
 * Where a user chooses their password, from an invitation or a "forgotten password" link.
 */
const props = defineProps<{ isInvitation: boolean }>()

const route = useRoute()
const router = useRouter()
const session = useSessionStore()

const email = computed(() => (typeof route.query.email === 'string' ? route.query.email : ''))
const password = ref('')
const passwordConfirmation = ref('')
const errors = ref<{ token?: string; password?: string }>({})
const isSubmitting = ref(false)

async function submit(): Promise<void> {
    errors.value = {}

    if (password.value !== passwordConfirmation.value) {
        errors.value = { password: 'Les deux mots de passe ne sont pas identiques.' }

        return
    }

    isSubmitting.value = true

    try {
        const user = await api.resetPassword({
            token: String(route.params.token),
            email: email.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
            invitation: props.isInvitation,
        })

        session.set(user)
        await router.replace({ name: 'home' })
    } catch (exception) {
        if (exception instanceof ApiError && exception.status === 422) {
            errors.value = { token: exception.errors.token?.[0], password: exception.errors.password?.[0] ?? exception.errors.email?.[0] }
        } else if (exception instanceof ApiError && exception.status === 429) {
            errors.value = { token: 'Trop d’essais. Réessayez dans une minute.' }
        } else {
            errors.value = { token: 'L’enregistrement a échoué. Réessayez.' }
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

            <h1>{{ isInvitation ? 'Bienvenue' : 'Nouveau mot de passe' }}</h1>
            <p class="login-lead">
                {{ isInvitation ? 'Choisissez votre mot de passe pour accéder à l’administration.' : 'Choisissez un nouveau mot de passe pour votre compte.' }}
            </p>

            <label class="login-field">
                <span>Email</span>
                <input :value="email" type="email" autocomplete="username" readonly />
            </label>

            <label class="login-field">
                <span>Mot de passe</span>
                <input v-model="password" type="password" autocomplete="new-password" minlength="8" required autofocus />
                <small class="text-xs font-normal text-faint">8 caractères minimum.</small>
            </label>

            <label class="login-field">
                <span>Confirmer le mot de passe</span>
                <input v-model="passwordConfirmation" type="password" autocomplete="new-password" minlength="8" required />
            </label>

            <p v-if="errors.password" class="field-error" role="alert">{{ errors.password }}</p>

            <p v-if="errors.token" class="field-error" role="alert">
                {{ errors.token }}
                <RouterLink v-if="!isInvitation" :to="{ name: 'password.forgot', query: { email } }" class="font-medium text-ink underline decoration-ring-hover underline-offset-3">Recevoir un nouveau lien</RouterLink>
                <template v-else>Demandez à un administrateur de vous renvoyer l’invitation.</template>
            </p>

            <UiButton type="submit" variant="primary" size="lg" :disabled="isSubmitting">
                {{ isSubmitting ? 'Enregistrement…' : isInvitation ? 'Accéder à l’administration' : 'Enregistrer et se connecter' }}
            </UiButton>
        </form>
    </main>
</template>
