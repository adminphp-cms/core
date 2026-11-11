<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '../api'
import { useModulesStore } from '../stores/modules'
import type { ItemSummary, ModuleSchema, Visibility } from '../types'

const props = defineProps<{ module: string }>()

const modulesStore = useModulesStore()
const schema = ref<ModuleSchema | null>(null)
const items = ref<ItemSummary[]>([])
const isLoading = ref(true)

const visibilityLabels: Record<Visibility, string> = {
    draft: 'Hors ligne',
    scheduled: 'Programmé',
    live: 'En ligne',
    expired: 'Expiré',
}

const dateFormatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' })

onMounted(async () => {
    ;[schema.value, items.value] = await Promise.all([modulesStore.schema(props.module), api.items(props.module)])
    isLoading.value = false
})
</script>

<template>
    <main class="page">
        <header class="top">
            <div class="crumbs"><strong>{{ schema?.title }}</strong></div>
            <RouterLink :to="{ name: 'module.create', params: { module } }" class="btn primary">Créer</RouterLink>
        </header>

        <div class="page-body">
            <p v-if="isLoading" class="empty">Chargement…</p>
            <p v-else-if="items.length === 0" class="empty">Aucun contenu pour l’instant.</p>

            <table v-else class="listing">
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th v-if="schema?.publishable">Statut</th>
                        <th class="listing-date">Modifié</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items" :key="item.id">
                        <td>
                            <RouterLink :to="{ name: 'module.edit', params: { module, id: item.id } }">{{ item.title }}</RouterLink>
                        </td>
                        <td v-if="schema?.publishable" class="listing-status">
                            <span v-if="item.visibility" class="visibility" :class="`is-${item.visibility}`">{{ visibilityLabels[item.visibility] }}</span>
                            <small v-if="item.hasDraft">modifications en attente</small>
                        </td>
                        <td class="listing-date">{{ item.updatedAt ? dateFormatter.format(new Date(item.updatedAt)) : '' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>
</template>
