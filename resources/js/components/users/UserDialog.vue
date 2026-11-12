<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api, ApiError } from '../../api'
import type { ManagedUser, UserRole, ValidationErrors } from '../../types'
import UiIcon from '../ui/UiIcon.vue'

/**
 * Invites a new user (no `user` given) or edits one: name, email, role,
 * plus resending the invitation and deactivating or reactivating.
 */
const props = defineProps<{ user: ManagedUser | null; isCurrentUser: boolean }>()

const emit = defineEmits<{
    close: []
    saved: [user: ManagedUser, message: string]
}>()

const roles: { value: UserRole; label: string; description: string }[] = [
    { value: 'editor', label: 'Éditeur', description: 'Crée, modifie et publie les contenus.' },
    { value: 'admin', label: 'Administrateur', description: 'Comme un éditeur, et gère les utilisateurs.' },
]

const dialog = ref<HTMLDialogElement | null>(null)
const name = ref(props.user?.name ?? '')
const email = ref(props.user?.email ?? '')
const role = ref<UserRole>(props.user?.role ?? 'editor')
const errors = ref<ValidationErrors>({})
const busyAction = ref<string | null>(null)

const isInvite = computed(() => props.user === null)

onMounted(() => dialog.value?.showModal())

async function run(action: string, call: () => Promise<ManagedUser>, message: string): Promise<void> {
    busyAction.value = action
    errors.value = {}

    try {
        const user = await call()
        emit('saved', user, message)
        dialog.value?.close()
    } catch (exception) {
        if (exception instanceof ApiError && exception.status === 422) {
            errors.value = exception.errors
        } else {
            errors.value = { user: ['L’enregistrement a échoué. Réessayez.'] }
            throw exception
        }
    } finally {
        busyAction.value = null
    }
}

function submit(): Promise<void> {
    const values = { name: name.value, email: email.value, role: role.value }

    return props.user === null
        ? run('save', () => api.inviteUser(values), `Invitation envoyée à ${values.email}`)
        : run('save', () => api.updateUser(props.user!.id, values), 'Modifications enregistrées')
}

function resendInvitation(): Promise<void> {
    return run('invite', () => api.resendInvitation(props.user!.id), `Invitation renvoyée à ${props.user!.email}`)
}

function toggleActivation(): Promise<void> {
    const user = props.user!

    return user.status === 'deactivated'
        ? run('activation', () => api.activateUser(user.id), `${user.name} peut de nouveau se connecter`)
        : run('activation', () => api.deactivateUser(user.id), `${user.name} ne peut plus se connecter`)
}
</script>

<template>
    <dialog ref="dialog" class="dialog" aria-labelledby="user-dialog-title" @close="emit('close')">
        <form class="dialog-form" @submit.prevent="submit">
            <header class="dialog-header">
                <h2 id="user-dialog-title">{{ isInvite ? 'Inviter un utilisateur' : 'Modifier l’utilisateur' }}</h2>
                <button type="button" class="icon-button" aria-label="Fermer" title="Fermer" @click="dialog?.close()">
                    <UiIcon name="x" :size="14" />
                </button>
            </header>

            <div class="dialog-body">
                <p v-if="isInvite" class="dialog-lead">La personne reçoit un email avec un lien pour choisir son mot de passe. Le lien est valable 7 jours.</p>

                <label class="form-field">
                    <span>Nom</span>
                    <input v-model="name" class="control-input" type="text" autocomplete="off" required maxlength="255" />
                    <small v-if="errors.name" class="field-error">{{ errors.name[0] }}</small>
                </label>

                <label class="form-field">
                    <span>Email</span>
                    <input v-model="email" class="control-input" type="email" autocomplete="off" required maxlength="255" />
                    <small v-if="errors.email" class="field-error">{{ errors.email[0] }}</small>
                </label>

                <fieldset class="form-field" :disabled="isCurrentUser">
                    <legend>Rôle</legend>
                    <div class="role-options">
                        <label v-for="option in roles" :key="option.value" class="role-option" :class="{ 'is-selected': role === option.value }">
                            <input v-model="role" type="radio" name="role" :value="option.value" />
                            <b>{{ option.label }}</b>
                            <small>{{ option.description }}</small>
                        </label>
                    </div>
                    <small v-if="isCurrentUser" class="form-hint">Vous ne pouvez pas changer votre propre rôle.</small>
                    <small v-if="errors.role" class="field-error">{{ errors.role[0] }}</small>
                </fieldset>

                <p v-if="errors.user" class="field-error" role="alert">{{ errors.user[0] }}</p>
            </div>

            <footer class="dialog-footer">
                <div v-if="user && !isCurrentUser" class="dialog-footer-start">
                    <button v-if="user.status === 'invited'" type="button" class="btn" :disabled="busyAction !== null" @click="resendInvitation">
                        <UiIcon name="send" :size="14" /> {{ busyAction === 'invite' ? 'Envoi…' : 'Renvoyer l’invitation' }}
                    </button>
                    <button type="button" class="btn" :class="{ danger: user.status !== 'deactivated' }" :disabled="busyAction !== null" @click="toggleActivation">
                        {{ user.status === 'deactivated' ? 'Réactiver' : 'Désactiver' }}
                    </button>
                </div>

                <button type="button" class="btn" @click="dialog?.close()">Annuler</button>
                <button type="submit" class="btn primary" :disabled="busyAction !== null">
                    <template v-if="isInvite"><UiIcon name="send" :size="14" /> {{ busyAction === 'save' ? 'Envoi…' : 'Envoyer l’invitation' }}</template>
                    <template v-else>{{ busyAction === 'save' ? 'Enregistrement…' : 'Enregistrer' }}</template>
                </button>
            </footer>
        </form>
    </dialog>
</template>
