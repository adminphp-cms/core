<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import BrandLogo from './components/BrandLogo.vue'
import UiIcon from './components/ui/UiIcon.vue'
import { initials as initialsOf } from './format'
import { useModulesStore } from './stores/modules'
import { useSessionStore } from './stores/session'

const modulesStore = useModulesStore()
const session = useSessionStore()
const router = useRouter()

watch(
    () => session.user,
    (user) => {
        if (user !== null) {
            modulesStore.loadModules()
        }
    },
    { immediate: true },
)

const initials = computed(() => initialsOf(session.user?.name ?? ''))

async function logout(): Promise<void> {
    await session.logout()
    await router.replace({ name: 'login' })
}
</script>

<template>
    <div v-if="session.user" class="app">
        <aside class="nav">
            <div class="nav-brand">
                <RouterLink :to="{ name: 'home' }" class="brand" aria-label="AdminPHP, tableau de bord">
                    <BrandLogo />
                </RouterLink>
            </div>

            <nav class="nav-scroll" aria-label="Navigation principale">
                <div class="nav-group">
                    <RouterLink
                        :to="{ name: 'home' }"
                        class="nav-link"
                        :class="{ 'is-active': $route.name === 'home' }"
                        :aria-current="$route.name === 'home' ? 'page' : undefined"
                    >
                        <span class="nav-link-label"><UiIcon name="layout-dashboard" /> Tableau de bord</span>
                    </RouterLink>
                </div>

                <div class="nav-group">
                    <h3>Contenus</h3>
                    <RouterLink
                        v-for="module in modulesStore.modules"
                        :key="module.name"
                        :to="{ name: 'module.index', params: { module: module.name } }"
                        class="nav-link"
                        :class="{ 'is-active': $route.params.module === module.name }"
                        :aria-current="$route.params.module === module.name ? 'page' : undefined"
                    >
                        <span class="nav-link-label"><UiIcon :name="module.icon" /> {{ module.title }}</span>
                        <small>{{ module.count }}</small>
                    </RouterLink>
                </div>

                <div class="nav-group">
                    <h3>Ressources</h3>
                    <RouterLink
                        :to="{ name: 'media' }"
                        class="nav-link"
                        :class="{ 'is-active': $route.name === 'media' }"
                        :aria-current="$route.name === 'media' ? 'page' : undefined"
                    >
                        <span class="nav-link-label"><UiIcon name="image" /> Médiathèque</span>
                    </RouterLink>
                </div>

                <div v-if="session.user.canManageUsers" class="nav-group">
                    <h3>Administration</h3>
                    <RouterLink
                        :to="{ name: 'users' }"
                        class="nav-link"
                        :class="{ 'is-active': $route.name === 'users' }"
                        :aria-current="$route.name === 'users' ? 'page' : undefined"
                    >
                        <span class="nav-link-label"><UiIcon name="users" /> Utilisateurs</span>
                    </RouterLink>
                </div>
            </nav>

            <div class="nav-profile" :class="{ 'is-active': $route.name === 'account' }">
                <RouterLink :to="{ name: 'account' }" class="nav-profile-link" title="Mon compte" :aria-current="$route.name === 'account' ? 'page' : undefined">
                    <span class="avatar" aria-hidden="true">{{ initials }}</span>
                    <span class="nav-profile-text">
                        <b>{{ session.user.name }}</b>
                        <small>{{ session.user.email }}</small>
                    </span>
                </RouterLink>
                <button type="button" class="icon-button" title="Se déconnecter" aria-label="Se déconnecter" @click="logout">
                    <UiIcon name="log-out" :size="14" />
                </button>
            </div>
        </aside>

        <RouterView :key="$route.fullPath" />
    </div>

    <!-- Only public pages render without a user, so a page never loads data while logged out. -->
    <RouterView v-else-if="$route.meta.isPublic" />
</template>
