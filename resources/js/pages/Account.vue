<script setup lang="ts">
import { computed, ref } from 'vue'
import { api, ApiError } from '../api'
import PageIntro from '../components/ui/PageIntro.vue'
import UiButton from '../components/ui/UiButton.vue'
import UiCard from '../components/ui/UiCard.vue'
import UiField from '../components/ui/UiField.vue'
import UiInput from '../components/ui/UiInput.vue'
import UiIcon from '../components/ui/UiIcon.vue'
import { useTheme, type ThemePreference } from '../composables/useTheme'
import { useToast } from '../composables/useToast'
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

const toast = useToast()

const { preference: themePreference } = useTheme()

const themeOptions: { value: ThemePreference; label: string; icon: string }[] = [
    { value: 'auto', label: 'Système', icon: 'monitor' },
    { value: 'light', label: 'Clair', icon: 'sun' },
    { value: 'dark', label: 'Sombre', icon: 'moon' },
]

const themeButtonClass = [
    'flex h-full items-center gap-1.5 rounded-[7px] border border-transparent px-3',
    'text-sm font-medium text-muted transition-[background,color]',
    'hover:not-aria-pressed:text-ink aria-pressed:border-ring-control aria-pressed:bg-surface aria-pressed:text-strong',
]

const roleLabel = computed(() => (session.user?.role === 'admin' ? 'Administrateur' : 'Éditeur'))
const isEmailChanged = computed(() => email.value.trim().toLocaleLowerCase() !== session.user?.email)

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
        toast.success('Profil enregistré')
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
        toast.success('Mot de passe changé')
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
                    <UiField label="Nom" :error="profileErrors.name?.[0]">
                        <UiInput v-model="name" type="text" autocomplete="name" required maxlength="255" />
                    </UiField>

                    <UiField label="Email" :error="profileErrors.email?.[0]">
                        <UiInput v-model="email" type="email" autocomplete="email" required maxlength="255" />
                    </UiField>

                    <UiField v-if="isEmailChanged" label="Mot de passe actuel" hint="Demandé pour changer l’email de connexion." :error="profileErrors.current_password?.[0]">
                        <UiInput v-model="profilePassword" type="password" autocomplete="current-password" required />
                    </UiField>

                    <p v-if="profileErrors.form" class="field-error" role="alert">{{ profileErrors.form[0] }}</p>

                    <div class="flex gap-2">
                        <UiButton type="submit" variant="primary" :disabled="isSavingProfile">{{ isSavingProfile ? 'Enregistrement…' : 'Enregistrer' }}</UiButton>
                    </div>
                </form>
            </UiCard>

            <UiCard title="Apparence" description="Choix propre à ce navigateur. « Système » suit le réglage clair ou sombre de votre ordinateur.">
                <div class="flex h-9 w-fit gap-0.5 rounded-md bg-accent-soft p-[3px]" role="group" aria-label="Thème de l’administration">
                    <button
                        v-for="option in themeOptions"
                        :key="option.value"
                        type="button"
                        :class="themeButtonClass"
                        :aria-pressed="themePreference === option.value"
                        @click="themePreference = option.value"
                    >
                        <UiIcon :name="option.icon" :size="14" /> {{ option.label }}
                    </button>
                </div>
            </UiCard>

            <UiCard title="Mot de passe" description="Vos autres navigateurs restés connectés seront déconnectés.">
                <form class="flex max-w-[420px] flex-col gap-4" @submit.prevent="savePassword">
                    <!-- Lets password managers tie the new password to this account. -->
                    <input :value="session.user?.email" type="email" autocomplete="username" hidden />

                    <UiField label="Mot de passe actuel" :error="passwordErrors.current_password?.[0]">
                        <UiInput v-model="currentPassword" type="password" autocomplete="current-password" required />
                    </UiField>

                    <UiField label="Nouveau mot de passe" hint="8 caractères minimum.">
                        <UiInput v-model="newPassword" type="password" autocomplete="new-password" minlength="8" required />
                    </UiField>

                    <UiField label="Confirmer le nouveau mot de passe" :error="passwordErrors.password?.[0]">
                        <UiInput v-model="newPasswordConfirmation" type="password" autocomplete="new-password" minlength="8" required />
                    </UiField>

                    <p v-if="passwordErrors.form" class="field-error" role="alert">{{ passwordErrors.form[0] }}</p>

                    <div class="flex gap-2">
                        <UiButton type="submit" variant="primary" :disabled="isSavingPassword">{{ isSavingPassword ? 'Enregistrement…' : 'Changer le mot de passe' }}</UiButton>
                    </div>
                </form>
            </UiCard>
        </div>

    </main>
</template>
