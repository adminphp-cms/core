<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '../api'
import EmptyState from '../components/ui/EmptyState.vue'
import IconTile from '../components/ui/IconTile.vue'
import PageIntro from '../components/ui/PageIntro.vue'
import UiBadge from '../components/ui/UiBadge.vue'
import UiCard from '../components/ui/UiCard.vue'
import UiIcon from '../components/ui/UiIcon.vue'
import UiIconButton from '../components/ui/UiIconButton.vue'
import { formatLongDate, formatRelative, initials } from '../format'
import { useModulesStore } from '../stores/modules'
import { useSessionStore } from '../stores/session'
import type { ActivityEntry } from '../types'

const modulesStore = useModulesStore()
const session = useSessionStore()

const activity = ref<ActivityEntry[]>([])
const isActivityLoaded = ref(false)

const greeting = computed(() => {
    const hour = new Date().getHours()
    const firstName = (session.user?.name ?? '').split(/\s+/)[0]

    return `${hour >= 18 || hour < 5 ? 'Bonsoir' : 'Bonjour'}${firstName ? `, ${firstName}` : ''}`
})

const today = formatLongDate(new Date())

onMounted(async () => {
    activity.value = await api.activity()
    isActivityLoaded.value = true
})
</script>

<template>
    <main class="page">
        <header class="top">
            <div class="crumbs"><strong>Tableau de bord</strong></div>
        </header>

        <div class="page-body">
            <PageIntro :title="greeting" :description="`${today} · Voici où en sont vos contenus.`" />

            <EmptyState
                v-if="modulesStore.modules.length === 0"
                icon="layout-grid"
                title="Aucun module pour l’instant"
                description="Déclarez vos modules dans l’application avec AdminPhp::registerModules()."
            />

            <ul v-else class="grid grid-cols-[repeat(auto-fill,minmax(260px,1fr))] gap-4">
                <li v-for="module in modulesStore.modules" :key="module.name">
                    <UiCard
                        class="group h-full transition-[border-color] duration-[160ms] ease-out hover:border-ring-hover"
                        body-class="flex h-full flex-col gap-3.5"
                    >
                        <div class="flex items-center gap-2.5">
                            <IconTile :name="module.icon" />
                            <RouterLink :to="{ name: 'module.index', params: { module: module.name } }" class="stretched-link min-w-0 flex-1 font-semibold text-strong no-underline transition-[color] group-hover:text-brand">
                                {{ module.title }}
                            </RouterLink>
                            <UiIconButton :label="`Créer dans ${module.title}`" :to="{ name: 'module.create', params: { module: module.name } }">
                                <UiIcon name="plus" :size="14" />
                            </UiIconButton>
                        </div>

                        <p class="text-4xl leading-none font-semibold tracking-[-0.03em] text-strong tabular-nums">{{ module.count }}</p>

                        <div class="mt-auto flex flex-wrap gap-1.5">
                            <template v-if="module.counts">
                                <UiBadge v-if="module.counts.published > 0" tone="success" dot>{{ module.counts.published }} en ligne</UiBadge>
                                <UiBadge v-if="module.counts.unpublished > 0" dot>{{ module.counts.unpublished }} hors ligne</UiBadge>
                                <UiBadge v-if="module.counts.pendingChanges > 0" tone="warning" dot>{{ module.counts.pendingChanges }} à publier</UiBadge>
                                <span v-if="module.count === 0" class="text-sm text-muted">Rien encore, lancez-vous</span>
                            </template>
                            <span v-else class="text-sm text-muted">{{ module.description || 'Contenus' }}</span>
                        </div>
                    </UiCard>
                </li>
            </ul>

            <UiCard title="Activité récente" description="Les derniers enregistrements et publications." :padded="false">
                <ol v-if="activity.length > 0" class="py-1.5">
                    <li
                        v-for="entry in activity"
                        :key="entry.id"
                        class="flex items-center gap-3 px-4.5 py-2.5 not-first:border-t not-first:border-t-line max-md:flex-wrap max-md:px-4 max-md:py-3"
                    >
                        <span class="avatar is-small" aria-hidden="true">{{ initials(entry.userName ?? '?') }}</span>
                        <p class="min-w-0 flex-1 text-muted max-md:basis-[calc(100%-40px)]">
                            <b class="font-semibold text-strong">{{ entry.userName ?? 'Quelqu’un' }}</b>
                            {{ entry.action === 'published' ? 'a publié' : 'a enregistré des modifications sur' }}
                            <RouterLink
                                :to="{ name: 'module.edit', params: { module: entry.module, id: entry.itemId } }"
                                class="font-medium text-strong no-underline hover:underline hover:underline-offset-3"
                            >{{ entry.itemTitle }}</RouterLink>
                        </p>
                        <UiBadge><UiIcon :name="entry.icon" :size="12" /> {{ entry.moduleTitle }}</UiBadge>
                        <time class="min-w-24 text-right text-sm whitespace-nowrap text-faint max-md:ml-auto max-md:min-w-0" :datetime="entry.at">{{ formatRelative(entry.at) }}</time>
                    </li>
                </ol>

                <EmptyState
                    v-else-if="isActivityLoaded"
                    icon="sparkles"
                    title="Pas encore d’activité"
                    description="Les enregistrements et publications de l’équipe apparaîtront ici."
                />
            </UiCard>
        </div>
    </main>
</template>
