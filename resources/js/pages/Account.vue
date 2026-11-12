<script setup lang="ts">
import { computed, ref } from 'vue'
import { api, ApiError } from '../api'
import PageIntro from '../components/ui/PageIntro.vue'
import UiCard from '../components/ui/UiCard.vue'
import { useSessionStore } from '../stores/session'
import type { ValidationErrors } from '../types'

const session = useSessionStore()

const name = ref(session.user?.name ?? '')
const email = ref(session.user?.email ?? '')
const profilePassword = ref('')
const profileErrors = ref<ValidationErrors>({})
const isSavingProfile = ref(false)

const currentPassword = ref('')
const newPassword = ref('')
const newPasswordConfirmation = ref('')
const passwordErrors = ref<ValidationErrors>({})
const isSavingPassword = ref(false)

const toast = ref<string | null>(null)

const roleLabel = computed(() => (session.user?.role === 'admin' ? 'Administrateur' : 'Éditeur'))
const isEmailChanged = computed(() => email.value.trim().toLocaleLowerCase() !== session.user?.email)

function notify(message: string): void {
    toast.value = message
    setTimeout(() => (toast.value = null), 2500)
}

function errorsFrom(exception: unknown): ValidationErrors {
    if (exception instanceof ApiError && exception.status === 422) {
        return exception.errors
    }

    if (exception instanceof ApiError && exception.status === 429) {
        return { form: ['Trop d’essais. Réessayez dans une minute.'] }
    }

    throw exception
}

async function saveProfile(): Promise<void> {
    isSavingProfile.value = true
    profileErrors.value = {}

    try {
        const user = await api.updateAccount({
            name: name.value,
            email: email.value,
            ...(isEmailChanged.value ? { current_password: profilePassword.value } : {}),
        })

        session.set(user)
        email.value = user.email
        profilePassword.value = ''
        notify('Profil enregistré')
    } catch (exception) {
        profileErrors.value = errorsFrom(exception)
    } finally {
        isSavingProfile.value = false
    }
}

async function savePassword(): Promise<void> {
    passwordErrors.value = {}

    if (newPassword.value !== newPasswordConfirmation.value) {
        passwordErrors.value = { password: ['Les deux mots de passe ne sont pas identiques.'] }

        return
    }

    isSavingPassword.value = true

    try {
        session.set(
            await api.updatePassword({
                current_password: currentPassword.value,
                password: newPassword.value,
                password_confirmation: newPasswordConfirmation.value,
            }),
        )

        currentPassword.value = ''
        newPassword.value = ''
        newPasswordConfirmation.value = ''
        notify('Mot de passe changé')
    } catch (exception) {
        passwordErrors.value = errorsFrom(exception)
    } finally {
        isSavingPassword.value = false
    }
}
</script>

<template>
    <main class="page">
        <header class="top">
            <div class="crumbs"><strong>Mon compte</strong></div>
        </header>

        <div class="page-body max-w-[760px]">
            <PageIntro title="Mon compte" :description="`Connecté en tant que ${roleLabel.toLocaleLowerCase()}.`" icon="user" />

            <UiCard title="Profil" description="Votre nom apparaît dans l’historique des versions.">
                <form class="flex max-w-[420px] flex-col gap-4" @submit.prevent="saveProfile">
                    <label class="form-field">
                        <span>Nom</span>
                        <input v-model="name" class="control-input" type="text" autocomplete="name" required maxlength="255" />
                        <small v-if="profileErrors.name" class="field-error">{{ profileErrors.name[0] }}</small>
                    </label>

                    <label class="form-field">
                        <span>Email</span>
                        <input v-model="email" class="control-input" type="email" autocomplete="email" required maxlength="255" />
                        <small v-if="profileErrors.email" class="field-error">{{ profileErrors.email[0] }}</small>
                    </label>

                    <label v-if="isEmailChanged" class="form-field">
                        <span>Mot de passe actuel</span>
                        <input v-model="profilePassword" class="control-input" type="password" autocomplete="current-password" required />
                        <small class="form-hint">Demandé pour changer l’email de connexion.</small>
                        <small v-if="profileErrors.current_password" class="field-error">{{ profileErrors.current_password[0] }}</small>
                    </label>

                    <p v-if="profileErrors.form" class="field-error" role="alert">{{ profileErrors.form[0] }}</p>

                    <div class="flex gap-2">
                        <button type="submit" class="btn primary" :disabled="isSavingProfile">{{ isSavingProfile ? 'Enregistrement…' : 'Enregistrer' }}</button>
                    </div>
                </form>
            </UiCard>

            <UiCard title="Mot de passe" description="Vos autres navigateurs restés connectés seront déconnectés.">
                <form class="flex max-w-[420px] flex-col gap-4" @submit.prevent="savePassword">
                    <!-- Lets password managers tie the new password to this account. -->
                    <input :value="session.user?.email" type="email" autocomplete="username" hidden />

                    <label class="form-field">
                        <span>Mot de passe actuel</span>
                        <input v-model="currentPassword" class="control-input" type="password" autocomplete="current-password" required />
                        <small v-if="passwordErrors.current_password" class="field-error">{{ passwordErrors.current_password[0] }}</small>
                    </label>

                    <label class="form-field">
                        <span>Nouveau mot de passe</span>
                        <input v-model="newPassword" class="control-input" type="password" autocomplete="new-password" minlength="8" required />
                        <small class="form-hint">8 caractères minimum.</small>
                    </label>

                    <label class="form-field">
                        <span>Confirmer le nouveau mot de passe</span>
                        <input v-model="newPasswordConfirmation" class="control-input" type="password" autocomplete="new-password" minlength="8" required />
                        <small v-if="passwordErrors.password" class="field-error">{{ passwordErrors.password[0] }}</small>
                    </label>

                    <p v-if="passwordErrors.form" class="field-error" role="alert">{{ passwordErrors.form[0] }}</p>

                    <div class="flex gap-2">
                        <button type="submit" class="btn primary" :disabled="isSavingPassword">{{ isSavingPassword ? 'Enregistrement…' : 'Changer le mot de passe' }}</button>
                    </div>
                </form>
            </UiCard>
        </div>

        <div class="toast" :class="{ on: toast }" role="status">{{ toast }}</div>
    </main>
</template>
