<script setup lang="ts">
import { computed, ref } from 'vue'
import { api, ApiError } from '../../api'
import type { ManagedUser, UserRole, ValidationErrors } from '../../types'
import UiButton from '../ui/UiButton.vue'
import UiDialog from '../ui/UiDialog.vue'
import UiField from '../ui/UiField.vue'
import UiIcon from '../ui/UiIcon.vue'
import UiInput from '../ui/UiInput.vue'

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

const dialog = ref<InstanceType<typeof UiDialog> | null>(null)
const name = ref(props.user?.name ?? '')
const email = ref(props.user?.email ?? '')
const role = ref<UserRole>(props.user?.role ?? 'editor')
const errors = ref<ValidationErrors>({})
const busyAction = ref<string | null>(null)

const isInvite = computed(() => props.user === null)

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
    <UiDialog
        ref="dialog"
        :title="isInvite ? 'Inviter un utilisateur' : 'Modifier l’utilisateur'"
        title-id="user-dialog-title"
        :lead="isInvite ? 'La personne reçoit un email avec un lien pour choisir son mot de passe. Le lien est valable 7 jours.' : undefined"
        form
        @submit="submit"
        @close="emit('close')"
    >
        <UiField label="Nom" :error="errors.name?.[0]">
            <UiInput v-model="name" type="text" autocomplete="off" required maxlength="255" />
        </UiField>

        <UiField label="Email" :error="errors.email?.[0]">
            <UiInput v-model="email" type="email" autocomplete="off" required maxlength="255" />
        </UiField>

        <UiField
            as="fieldset"
            label="Rôle"
            :hint="isCurrentUser ? 'Vous ne pouvez pas changer votre propre rôle.' : undefined"
            :error="errors.role?.[0]"
            :disabled="isCurrentUser"
        >
            <div class="grid grid-cols-2 gap-2 max-sm:grid-cols-1">
                <label
                    v-for="option in roles"
                    :key="option.value"
                    class="relative flex cursor-pointer flex-col gap-0.5 rounded-lg border px-3 py-2.5 transition-[background,border-color] has-[input:focus-visible]:border-brand has-[input:focus-visible]:outline-3 has-[input:focus-visible]:outline-brand-soft in-[fieldset:disabled]:cursor-not-allowed in-[fieldset:disabled]:opacity-60"
                    :class="role === option.value ? 'border-brand-ring bg-brand-soft' : 'border-ring-control hover:bg-hover'"
                >
                    <input v-model="role" type="radio" name="role" :value="option.value" class="pointer-events-none absolute opacity-0" />
                    <b class="font-semibold" :class="role === option.value ? 'text-brand' : 'text-strong'">{{ option.label }}</b>
                    <small class="text-xs text-muted">{{ option.description }}</small>
                </label>
            </div>
        </UiField>

        <p v-if="errors.user" class="field-error" role="alert">{{ errors.user[0] }}</p>

        <template v-if="user && !isCurrentUser" #footer-start>
            <UiButton v-if="user.status === 'invited'" :disabled="busyAction !== null" @click="resendInvitation">
                <UiIcon name="send" :size="14" /> {{ busyAction === 'invite' ? 'Envoi…' : 'Renvoyer l’invitation' }}
            </UiButton>
            <UiButton :variant="user.status !== 'deactivated' ? 'danger' : 'default'" :disabled="busyAction !== null" @click="toggleActivation">
                {{ user.status === 'deactivated' ? 'Réactiver' : 'Désactiver' }}
            </UiButton>
        </template>

        <template #footer>
            <UiButton @click="dialog?.close()">Annuler</UiButton>
            <UiButton type="submit" variant="primary" :disabled="busyAction !== null">
                <template v-if="isInvite"><UiIcon name="send" :size="14" /> {{ busyAction === 'save' ? 'Envoi…' : 'Envoyer l’invitation' }}</template>
                <template v-else>{{ busyAction === 'save' ? 'Enregistrement…' : 'Enregistrer' }}</template>
            </UiButton>
        </template>
    </UiDialog>
</template>
