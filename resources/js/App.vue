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

/** Utilities shared by every sidebar link; the current page is the one with `aria-current`. */
const navLinkClasses = [
    'group flex h-8.5 items-center justify-between gap-2 rounded-sm px-2.5 font-medium text-muted no-underline',
    'transition-[background,color] hover:not-aria-[current=page]:bg-nav-hover hover:not-aria-[current=page]:text-ink',
    'aria-[current=page]:bg-brand-soft aria-[current=page]:text-brand max-md:whitespace-nowrap',
]
const navLabelClasses = 'inline-flex min-w-0 items-center gap-2.5'
const navIconClasses = 'text-icon transition-[color] group-hover:group-not-aria-[current=page]:text-muted group-aria-[current=page]:text-brand'
const navCountClasses = [
    'min-w-5.5 rounded-[999px] bg-accent-soft px-1.5 py-px text-center text-xs font-medium text-muted tabular-nums',
    'group-aria-[current=page]:bg-surface group-aria-[current=page]:text-brand max-md:hidden',
]
const navGroupClasses = 'flex flex-col gap-0.5 max-md:flex-row'
const navHeadingClasses = 'mb-1.5 px-2.5 text-xs font-medium text-faint max-md:hidden'

async function logout(): Promise<void> {
    await session.logout()
    await router.replace({ name: 'login' })
}
</script>

<template>
    <div v-if="session.user" class="grid min-h-full grid-cols-[var(--sidebar-width)_minmax(0,1fr)] max-md:block">
        <aside
            class="sticky top-0 flex h-screen flex-col border-r border-r-line bg-surface max-md:static max-md:h-auto max-md:flex-row max-md:items-center max-md:overflow-x-auto max-md:border-r-0 max-md:border-b max-md:border-b-line"
        >
            <div class="flex h-(--header-height) flex-none items-center px-5 max-md:h-13 max-md:px-4">
                <RouterLink
                    :to="{ name: 'home' }"
                    class="inline-flex items-baseline text-[19px] leading-none font-bold tracking-[-0.03em] no-underline"
                    aria-label="AdminPHP, tableau de bord"
                >
                    <BrandLogo />
                </RouterLink>
            </div>

            <nav class="flex flex-1 flex-col gap-5 overflow-y-auto px-3 pt-2 pb-4 max-md:flex-row max-md:overflow-visible max-md:p-0" aria-label="Navigation principale">
                <div :class="navGroupClasses">
                    <RouterLink :to="{ name: 'home' }" :class="navLinkClasses" :aria-current="$route.name === 'home' ? 'page' : undefined">
                        <span :class="navLabelClasses"><UiIcon name="layout-dashboard" :class="navIconClasses" /> Tableau de bord</span>
                    </RouterLink>
                </div>

                <div :class="navGroupClasses">
                    <h3 :class="navHeadingClasses">Contenus</h3>
                    <RouterLink
                        v-for="module in modulesStore.modules"
                        :key="module.name"
                        :to="{ name: 'module.index', params: { module: module.name } }"
                        :class="navLinkClasses"
                        :aria-current="$route.params.module === module.name ? 'page' : undefined"
                    >
                        <span :class="navLabelClasses"><UiIcon :name="module.icon" :class="navIconClasses" /> {{ module.title }}</span>
                        <small :class="navCountClasses">{{ module.count }}</small>
                    </RouterLink>
                </div>

                <div :class="navGroupClasses">
                    <h3 :class="navHeadingClasses">Ressources</h3>
                    <RouterLink :to="{ name: 'media' }" :class="navLinkClasses" :aria-current="$route.name === 'media' ? 'page' : undefined">
                        <span :class="navLabelClasses"><UiIcon name="image" :class="navIconClasses" /> Médiathèque</span>
                    </RouterLink>
                </div>

                <div v-if="session.user.canManageUsers" :class="navGroupClasses">
                    <h3 :class="navHeadingClasses">Administration</h3>
                    <RouterLink :to="{ name: 'users' }" :class="navLinkClasses" :aria-current="$route.name === 'users' ? 'page' : undefined">
                        <span :class="navLabelClasses"><UiIcon name="users" :class="navIconClasses" /> Utilisateurs</span>
                    </RouterLink>
                </div>
            </nav>

            <div
                class="m-3 flex flex-none items-center gap-2.5 rounded-lg border border-line bg-sunken p-2.5 max-md:my-0 max-md:mr-2 max-md:ml-auto max-md:border-0 max-md:bg-transparent max-md:p-0"
            >
                <RouterLink
                    :to="{ name: 'account' }"
                    class="group -m-1 flex min-w-0 flex-1 items-center gap-2.5 rounded-md p-1 text-inherit no-underline transition-[background] aria-[current=page]:bg-nav-hover [&:hover]:bg-nav-hover"
                    title="Mon compte"
                    :aria-current="$route.name === 'account' ? 'page' : undefined"
                >
                    <span class="avatar max-md:size-7 max-md:text-2xs" aria-hidden="true">{{ initials }}</span>
                    <span class="flex min-w-0 flex-1 flex-col leading-[1.3] max-md:hidden">
                        <b class="truncate font-semibold text-strong group-aria-[current=page]:text-brand">{{ session.user.name }}</b>
                        <small class="truncate text-xs text-muted">{{ session.user.email }}</small>
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
