<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '../api'
import UiBadge, { type BadgeTone } from '../components/ui/UiBadge.vue'
import PageIntro from '../components/ui/PageIntro.vue'
import UiCard from '../components/ui/UiCard.vue'
import UiIcon from '../components/ui/UiIcon.vue'
import UserDialog from '../components/users/UserDialog.vue'
import { formatRelative, initials } from '../format'
import { useSessionStore } from '../stores/session'
import type { ManagedUser } from '../types'

const session = useSessionStore()

const users = ref<ManagedUser[]>([])
const isLoading = ref(true)
const search = ref('')
/** `null` invites a new user; `undefined` means the dialog is closed. */
const editedUser = ref<ManagedUser | null | undefined>(undefined)
const toast = ref<string | null>(null)

const statusBadges: Record<ManagedUser['status'], { label: string; tone: BadgeTone }> = {
    active: { label: 'Actif', tone: 'success' },
    invited: { label: 'Invitation envoyée', tone: 'warning' },
    deactivated: { label: 'Désactivé', tone: 'neutral' },
}

const filteredUsers = computed(() => {
    const query = search.value.trim().toLocaleLowerCase()

    return query === '' ? users.value : users.value.filter((user) => `${user.name} ${user.email}`.toLocaleLowerCase().includes(query))
})

onMounted(async () => {
    users.value = await api.users()
    isLoading.value = false
})

function saved(user: ManagedUser, message: string): void {
    const index = users.value.findIndex((existing) => existing.id === user.id)

    if (index === -1) {
        users.value = [...users.value, user].sort((a, b) => a.name.localeCompare(b.name))
    } else {
        users.value.splice(index, 1, user)
    }

    if (user.id === session.user?.id) {
        session.set({ ...session.user, name: user.name, email: user.email })
    }

    toast.value = message
    setTimeout(() => (toast.value = null), 2500)
}
</script>

<template>
    <main class="page">
        <header class="top">
            <div class="crumbs"><strong>Utilisateurs</strong></div>
        </header>

        <div class="page-body">
            <PageIntro title="Utilisateurs" description="Les personnes qui ont accès à l’administration, et ce qu’elles peuvent y faire." icon="users">
                <template #actions>
                    <button type="button" class="btn primary" @click="editedUser = null"><UiIcon name="user-plus" :size="14" /> Inviter</button>
                </template>
            </PageIntro>

            <UiCard v-if="!isLoading" :title="`${users.length} ${users.length > 1 ? 'utilisateurs' : 'utilisateur'}`" :padded="false">
                <template #actions>
                    <label class="search-field">
                        <UiIcon name="search" :size="14" />
                        <input v-model="search" type="search" placeholder="Rechercher" aria-label="Rechercher un utilisateur" />
                    </label>
                </template>

                <div class="table-scroll">
                    <table class="listing">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Rôle</th>
                                <th>Statut</th>
                                <th class="listing-date">Dernière connexion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="user in filteredUsers" :key="user.id">
                                <td>
                                    <span class="flex items-center gap-2.5" :class="{ 'opacity-55': user.status === 'deactivated' }">
                                        <span class="avatar is-small" aria-hidden="true">{{ initials(user.name) }}</span>
                                        <span class="flex min-w-0 flex-col">
                                            <button type="button" class="stretched-link link-button text-left text-base text-strong after:rounded-none" @click="editedUser = user">{{ user.name }}</button>
                                            <small class="text-xs text-faint">{{ user.email }}</small>
                                        </span>
                                        <UiBadge v-if="user.id === session.user?.id" tone="brand">Vous</UiBadge>
                                    </span>
                                </td>
                                <td>{{ user.roleLabel }}</td>
                                <td class="listing-status">
                                    <UiBadge :tone="statusBadges[user.status].tone" dot>{{ statusBadges[user.status].label }}</UiBadge>
                                </td>
                                <td class="listing-date">{{ user.lastLoginAt ? formatRelative(user.lastLoginAt) : 'Jamais' }}</td>
                            </tr>
                            <tr v-if="filteredUsers.length === 0">
                                <td colspan="4" class="listing-empty">Aucun résultat pour « {{ search }} ».</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </UiCard>
        </div>

        <UserDialog v-if="editedUser !== undefined" :user="editedUser" :is-current-user="editedUser?.id === session.user?.id" @saved="saved" @close="editedUser = undefined" />

        <div class="toast" :class="{ on: toast }" role="status">{{ toast }}</div>
    </main>
</template>
