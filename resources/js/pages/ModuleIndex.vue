<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api } from '../api'
import EmptyState from '../components/ui/EmptyState.vue'
import PageIntro from '../components/ui/PageIntro.vue'
import UiBadge from '../components/ui/UiBadge.vue'
import UiButton from '../components/ui/UiButton.vue'
import UiCard from '../components/ui/UiCard.vue'
import UiIcon from '../components/ui/UiIcon.vue'
import { formatDateTime, visibilityBadges } from '../format'
import { useModulesStore } from '../stores/modules'
import type { ItemSummary, ModuleSchema } from '../types'

const props = defineProps<{ module: string }>()

const modulesStore = useModulesStore()
const schema = ref<ModuleSchema | null>(null)
const items = ref<ItemSummary[]>([])
const isLoading = ref(true)
const search = ref('')

const filteredItems = computed(() => {
    const query = search.value.trim().toLocaleLowerCase()

    return query === '' ? items.value : items.value.filter((item) => item.title.toLocaleLowerCase().includes(query))
})

onMounted(async () => {
    ;[schema.value, items.value] = await Promise.all([modulesStore.schema(props.module), api.items(props.module)])
    isLoading.value = false
})
</script>

<template>
    <main class="page">
        <header class="top">
            <div class="crumbs"><strong>{{ schema?.title }}</strong></div>
        </header>

        <div v-if="schema" class="page-body">
            <PageIntro :title="schema.title" :description="schema.description || undefined" :icon="schema.icon">
                <template #actions>
                    <UiButton variant="primary" :to="{ name: 'module.create', params: { module } }">
                        <UiIcon name="plus" :size="14" /> Créer
                    </UiButton>
                </template>
            </PageIntro>

            <EmptyState
                v-if="!isLoading && items.length === 0"
                :icon="schema.icon"
                :title="`Aucun contenu dans ${schema.title} pour l’instant`"
                description="Créez le premier : vous pourrez le relire, le traduire et le publier quand il sera prêt."
            >
                <UiButton variant="primary" :to="{ name: 'module.create', params: { module } }">
                    <UiIcon name="plus" :size="14" /> Créer le premier
                </UiButton>
            </EmptyState>

            <UiCard v-else-if="!isLoading" :title="`${items.length} ${items.length > 1 ? 'éléments' : 'élément'}`" :padded="false">
                <template #actions>
                    <label class="search-field">
                        <UiIcon name="search" :size="14" />
                        <input v-model="search" type="search" placeholder="Rechercher" :aria-label="`Rechercher dans ${schema.title}`" />
                    </label>
                </template>

                <div class="table-scroll">
                    <table class="listing">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th v-if="schema.publishable">Statut</th>
                                <th class="listing-date">Modifié</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in filteredItems" :key="item.id">
                                <td>
                                    <RouterLink :to="{ name: 'module.edit', params: { module, id: item.id } }" class="stretched-link">{{ item.title }}</RouterLink>
                                </td>
                                <td v-if="schema.publishable" class="listing-status">
                                    <UiBadge v-if="item.visibility" :tone="visibilityBadges[item.visibility].tone" dot>
                                        {{ visibilityBadges[item.visibility].label }}
                                    </UiBadge>
                                    <UiBadge v-if="item.hasDraft" tone="warning">À publier</UiBadge>
                                </td>
                                <td class="listing-date">{{ item.updatedAt ? formatDateTime(item.updatedAt) : '' }}</td>
                            </tr>
                            <tr v-if="filteredItems.length === 0">
                                <td :colspan="schema.publishable ? 3 : 2" class="listing-empty">Aucun résultat pour « {{ search }} ».</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </UiCard>
        </div>
    </main>
</template>
