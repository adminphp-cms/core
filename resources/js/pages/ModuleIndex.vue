<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter, type LocationQuery } from 'vue-router'
import { api } from '../api'
import SortableTree from '../components/listing/SortableTree.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import PageIntro from '../components/ui/PageIntro.vue'
import UiBadge from '../components/ui/UiBadge.vue'
import UiButton from '../components/ui/UiButton.vue'
import UiCard from '../components/ui/UiCard.vue'
import UiIcon from '../components/ui/UiIcon.vue'
import { controlClasses } from '../components/ui/controlClasses'
import { useToast } from '../composables/useToast'
import { formatDate, formatDateTime, visibilityBadges } from '../format'
import { useModulesStore } from '../stores/modules'
import type { BulkAction, ItemPlacement, ItemSummary, ListingColumn, ListingMeta, ListingQuery, ListingView, ModuleSchema } from '../types'

const props = defineProps<{ module: string }>()

const route = useRoute()
const router = useRouter()
const modulesStore = useModulesStore()
const toast = useToast()

const schema = ref<ModuleSchema | null>(null)
const items = ref<ItemSummary[]>([])
const meta = ref<ListingMeta | null>(null)
const isLoading = ref(true)

const views: { value: ListingView; label: string }[] = [
    { value: 'all', label: 'Tous' },
    { value: 'published', label: 'Publiés' },
    { value: 'unpublished', label: 'Hors ligne' },
    { value: 'pending', label: 'À publier' },
    { value: 'trash', label: 'Corbeille' },
]

/** The views the module has, as the server counted them. */
const availableViews = computed(() => views.filter((view) => meta.value?.counts?.[view.value] !== undefined))

/** The listing state lives in the address, so a reload or the back button keeps it. */
function queryFromRoute(routeQuery: LocationQuery): ListingQuery {
    const filters: Record<string, string> = {}

    Object.entries(routeQuery).forEach(([key, value]) => {
        const name = key.match(/^filters\[(.+)\]$/)?.[1]

        if (name && typeof value === 'string') {
            filters[name] = value
        }
    })

    const view = routeQuery.view
    const page = Number(routeQuery.page)

    return {
        search: typeof routeQuery.search === 'string' ? routeQuery.search : '',
        filters,
        view: views.some((option) => option.value === view) ? (view as ListingView) : 'all',
        sort: typeof routeQuery.sort === 'string' ? routeQuery.sort : null,
        page: Number.isInteger(page) && page > 1 ? page : 1,
    }
}

const query = computed(() => queryFromRoute(route.query))
const search = ref(query.value.search)

function update(changes: Partial<ListingQuery>): void {
    const next = { ...query.value, page: 1, ...changes }
    const routeQuery: Record<string, string> = {}

    if (next.search.trim()) {
        routeQuery.search = next.search.trim()
    }

    if (next.view !== 'all') {
        routeQuery.view = next.view
    }

    if (next.sort) {
        routeQuery.sort = next.sort
    }

    if (next.page > 1) {
        routeQuery.page = String(next.page)
    }

    Object.entries(next.filters).forEach(([name, value]) => {
        if (value) {
            routeQuery[`filters[${name}]`] = value
        }
    })

    router.replace({ query: routeQuery })
}

let searchTimer: ReturnType<typeof setTimeout> | undefined

watch(search, (value) => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => update({ search: value }), 250)
})

let requestNumber = 0

async function load(): Promise<void> {
    const currentRequest = ++requestNumber
    const [moduleSchema, response] = await Promise.all([modulesStore.schema(props.module), api.listItems(props.module, query.value)])

    // An older request answering late must not replace the newer one.
    if (currentRequest !== requestNumber) {
        return
    }

    schema.value = moduleSchema
    items.value = response.data
    meta.value = response.meta
    isLoading.value = false
    selectedIds.value = selectedIds.value.filter((id) => response.data.some((item) => item.id === id))
}

const selectedIds = ref<string[]>([])
const isRunningBulk = ref(false)
const isTrashView = computed(() => query.value.view === 'trash')

/** Rows can be selected when the module offers at least one action on several items. */
const isSelectable = computed(() => schema.value !== null && (schema.value.publishable || schema.value.trashable))
const isAllSelected = computed(() => items.value.length > 0 && items.value.every((item) => selectedIds.value.includes(item.id)))

function toggleAll(): void {
    selectedIds.value = isAllSelected.value ? [] : items.value.map((item) => item.id)
}

function toggleSelected(id: string): void {
    selectedIds.value = selectedIds.value.includes(id) ? selectedIds.value.filter((selected) => selected !== id) : [...selectedIds.value, id]
}

const bulkActions = computed<{ action: BulkAction; label: string; icon: string; isDanger?: boolean }[]>(() => {
    if (!schema.value) {
        return []
    }

    if (isTrashView.value) {
        return [
            { action: 'restore', label: 'Restaurer', icon: 'rotate-ccw' },
            { action: 'delete', label: 'Supprimer définitivement', icon: 'trash', isDanger: true },
        ]
    }

    return [
        ...(schema.value.publishable
            ? [
                  { action: 'publish' as const, label: 'Publier', icon: 'send' },
                  { action: 'unpublish' as const, label: 'Dépublier', icon: 'minus' },
              ]
            : []),
        ...(schema.value.trashable ? [{ action: 'trash' as const, label: 'Mettre à la corbeille', icon: 'trash', isDanger: true }] : []),
    ]
})

const bulkMessages: Record<BulkAction, { confirm?: string; done: string }> = {
    publish: { done: 'publié(s)' },
    unpublish: { confirm: 'Retirer du site les éléments sélectionnés ?', done: 'retiré(s) du site' },
    trash: { done: 'mis à la corbeille' },
    restore: { done: 'restauré(s)' },
    delete: { confirm: 'Supprimer définitivement les éléments sélectionnés ? Cette action est irréversible.', done: 'supprimé(s) définitivement' },
}

async function runBulk(action: BulkAction): Promise<void> {
    const message = bulkMessages[action]

    if (message.confirm && !window.confirm(message.confirm)) {
        return
    }

    isRunningBulk.value = true

    try {
        const result = await api.bulkItems(props.module, action, selectedIds.value)

        if (result.failed.length > 0) {
            toast.error(`${result.done} ${message.done}, ${result.failed.length} à compléter avant publication`)
            selectedIds.value = result.failed
        } else {
            toast.success(`${result.done} ${message.done}`)
            selectedIds.value = []
        }

        await load()
        modulesStore.loadModules()
    } catch (error) {
        toast.error(error instanceof Error ? error.message : 'L’opération a échoué.')
    } finally {
        isRunningBulk.value = false
    }
}

watch([() => props.module, query], load, { immediate: true })

watch(
    () => props.module,
    () => {
        isLoading.value = true
        search.value = ''
    },
)

const hasCriteria = computed(() => query.value.search !== '' || query.value.view !== 'all' || Object.keys(query.value.filters).length > 0)
const isModuleEmpty = computed(() => !hasCriteria.value && meta.value?.total === 0)
const columns = computed(() => schema.value?.listing.columns ?? [])

/** Current sort: the requested one, else the one the server applied. */
const activeSort = computed(() => query.value.sort ?? meta.value?.sort ?? null)

function sortBy(column: ListingColumn): void {
    const isAscending = activeSort.value === column.name

    update({ sort: isAscending ? `-${column.name}` : column.name })
}

function sortIcon(column: ListingColumn): string | null {
    if (activeSort.value === column.name) {
        return 'arrow-up'
    }

    return activeSort.value === `-${column.name}` ? 'arrow-down' : null
}

function setFilter(name: string, value: string): void {
    update({ filters: { ...query.value.filters, [name]: value } })
}

function clearCriteria(): void {
    search.value = ''
    router.replace({ query: {} })
}

async function reorder(placements: ItemPlacement[]): Promise<void> {
    const previousItems = items.value
    const itemsById = Object.fromEntries(items.value.map((item) => [item.id, item]))

    items.value = placements.map((placement) => ({ ...itemsById[placement.id], parentId: placement.parentId }))

    try {
        await api.reorderItems(props.module, placements)
        toast.success('Ordre enregistré')
    } catch (error) {
        items.value = previousItems
        toast.error(error instanceof Error ? error.message : 'L’ordre n’a pas pu être enregistré.')
    }
}

const cardTitle = computed(() => {
    const total = meta.value?.total ?? 0

    return `${total} ${total > 1 ? 'éléments' : 'élément'}`
})

const tabClass = 'flex h-8 items-center gap-1.5 rounded-md px-2.5 text-sm font-medium transition-[background,color]'
const headerButtonClass = 'inline-flex items-center gap-1 rounded-xs hover:text-ink focus-visible:text-ink'
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
                v-if="!isLoading && isModuleEmpty"
                :icon="schema.icon"
                :title="`Aucun contenu dans ${schema.title} pour l’instant`"
                description="Créez le premier : vous pourrez le relire, le traduire et le publier quand il sera prêt."
            >
                <UiButton variant="primary" :to="{ name: 'module.create', params: { module } }">
                    <UiIcon name="plus" :size="14" /> Créer le premier
                </UiButton>
            </EmptyState>

            <template v-else-if="!isLoading && meta">
                <nav v-if="meta.counts" class="mb-3 flex flex-wrap gap-1" aria-label="Vues">
                    <button
                        v-for="view in availableViews"
                        :key="view.value"
                        type="button"
                        :class="[tabClass, query.view === view.value ? 'bg-surface text-strong [box-shadow:inset_0_0_0_1px_var(--ring-control)]' : 'text-muted hover:bg-nav-hover hover:text-ink']"
                        :aria-pressed="query.view === view.value"
                        @click="update({ view: view.value })"
                    >
                        {{ view.label }}
                        <span class="text-xs text-faint tabular-nums">{{ meta.counts[view.value] }}</span>
                    </button>
                </nav>

                <UiCard :title="cardTitle" :padded="false">
                    <template #actions>
                        <div v-if="selectedIds.length" class="flex flex-wrap items-center justify-end gap-2">
                            <span class="text-sm text-muted tabular-nums">{{ selectedIds.length }} sélectionné{{ selectedIds.length > 1 ? 's' : '' }}</span>
                            <UiButton
                                v-for="bulk in bulkActions"
                                :key="bulk.action"
                                :variant="bulk.isDanger ? 'danger' : 'default'"
                                :disabled="isRunningBulk"
                                @click="runBulk(bulk.action)"
                            >
                                <UiIcon :name="bulk.icon" :size="14" /> {{ bulk.label }}
                            </UiButton>
                            <UiButton :disabled="isRunningBulk" @click="selectedIds = []">Annuler</UiButton>
                        </div>
                        <div v-else class="flex flex-wrap items-center justify-end gap-2">
                            <select
                                v-for="filter in schema.listing.filters"
                                :key="filter.name"
                                :class="[controlClasses, 'h-8 w-auto max-w-44 pr-7', query.filters[filter.name] ? 'border-brand-ring text-ink' : 'text-muted']"
                                :value="query.filters[filter.name] ?? ''"
                                :aria-label="filter.label"
                                @change="setFilter(filter.name, ($event.target as HTMLSelectElement).value)"
                            >
                                <option value="">{{ filter.label }} : tous</option>
                                <option v-for="option in filter.options" :key="option.value" :value="option.value">{{ filter.label }} : {{ option.label }}</option>
                            </select>
                            <label class="search-field">
                                <UiIcon name="search" :size="14" />
                                <input v-model="search" type="search" placeholder="Rechercher" :aria-label="`Rechercher dans ${schema.title}`" />
                            </label>
                        </div>
                    </template>

                    <SortableTree
                        v-if="meta.isTree"
                        :module="module"
                        :items="items"
                        :nesting-depth="schema.nestingDepth"
                        :publishable="schema.publishable"
                        @reorder="reorder"
                    />

                    <div v-else class="table-scroll">
                        <table class="listing">
                            <thead>
                                <tr>
                                    <th v-if="isSelectable" class="w-10">
                                        <input type="checkbox" class="size-4 accent-[var(--brand)]" :checked="isAllSelected" aria-label="Tout sélectionner" @change="toggleAll" />
                                    </th>
                                    <th
                                        v-for="column in columns"
                                        :key="column.name"
                                        class="whitespace-nowrap"
                                        :class="{ 'listing-date': column.type === 'date' || column.type === 'datetime', 'w-14': column.type === 'image' }"
                                        :aria-sort="sortIcon(column) === 'arrow-up' ? 'ascending' : sortIcon(column) === 'arrow-down' ? 'descending' : undefined"
                                    >
                                        <button v-if="column.sortable" type="button" :class="headerButtonClass" @click="sortBy(column)">
                                            {{ column.label }}
                                            <UiIcon v-if="sortIcon(column)" :name="sortIcon(column)!" :size="12" />
                                        </button>
                                        <span v-else-if="column.type !== 'image'">{{ column.label }}</span>
                                        <span v-else class="sr-only">{{ column.label }}</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in items" :key="item.id" :class="{ 'bg-brand-soft/40': selectedIds.includes(item.id) }">
                                    <td v-if="isSelectable" class="relative z-10 w-10">
                                        <input
                                            type="checkbox"
                                            class="size-4 accent-[var(--brand)]"
                                            :checked="selectedIds.includes(item.id)"
                                            :aria-label="`Sélectionner ${item.title}`"
                                            @change="toggleSelected(item.id)"
                                        />
                                    </td>
                                    <td
                                        v-for="column in columns"
                                        :key="column.name"
                                        :class="{ 'listing-status': column.type === 'status', 'listing-date': column.type === 'date' || column.type === 'datetime' }"
                                    >
                                        <span v-if="column.type === 'title' && isTrashView" class="text-muted">{{ item.title }}</span>
                                        <template v-else-if="column.type === 'title'">
                                            <RouterLink :to="{ name: 'module.edit', params: { module, id: item.id } }" class="stretched-link">{{ item.title }}</RouterLink>
                                            <span v-if="column.type === 'title' && item.lockedBy" class="relative ml-2 inline-flex items-center gap-1 text-xs whitespace-nowrap text-warning" :title="`${item.lockedBy} modifie ce contenu`">
                                                <UiIcon name="lock" :size="12" /> {{ item.lockedBy }}
                                            </span>
                                        </template>

                                        <UiBadge v-else-if="column.type === 'status' && isTrashView">À la corbeille</UiBadge>
                                        <template v-else-if="column.type === 'status'">
                                            <UiBadge v-if="item.visibility" :tone="visibilityBadges[item.visibility].tone" dot>{{ visibilityBadges[item.visibility].label }}</UiBadge>
                                            <UiBadge v-if="item.hasDraft" tone="warning">À publier</UiBadge>
                                        </template>

                                        <template v-else-if="column.type === 'image'">
                                            <img v-if="item.cells[column.name]" :src="String(item.cells[column.name])" alt="" class="h-8 w-11 rounded-xs bg-accent-soft object-cover" />
                                            <span v-else class="block h-8 w-11 rounded-xs bg-accent-soft" aria-hidden="true" />
                                        </template>

                                        <UiIcon v-else-if="column.type === 'boolean'" :name="item.cells[column.name] ? 'check' : 'minus'" :size="14" :class="item.cells[column.name] ? 'text-ink' : 'text-faint'" />

                                        <template v-else-if="item.cells[column.name] === null || item.cells[column.name] === ''"><span class="text-faint">—</span></template>
                                        <template v-else-if="column.type === 'date'">{{ formatDate(String(item.cells[column.name])) }}</template>
                                        <template v-else-if="column.type === 'datetime'">{{ formatDateTime(String(item.cells[column.name])) }}</template>
                                        <template v-else>{{ item.cells[column.name] }}</template>
                                    </td>
                                </tr>
                                <tr v-if="items.length === 0">
                                    <td :colspan="columns.length + (isSelectable ? 1 : 0)" class="listing-empty">
                                        <template v-if="isTrashView && !query.search && Object.keys(query.filters).length === 0">La corbeille est vide.</template>
                                        <template v-else>
                                            Aucun résultat<template v-if="query.search"> pour « {{ query.search }} »</template>.
                                            <button type="button" class="ml-1 font-medium text-ink underline underline-offset-3" @click="clearCriteria">Tout afficher</button>
                                        </template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <footer v-if="!meta.isTree && meta.lastPage > 1" class="flex items-center justify-between gap-3 border-t border-line px-4.5 py-2.5 text-sm text-muted">
                        <span class="tabular-nums">Page {{ meta.currentPage }} sur {{ meta.lastPage }}</span>
                        <span class="flex gap-2">
                            <UiButton :disabled="meta.currentPage <= 1" @click="update({ ...query, page: meta.currentPage - 1 })"><UiIcon name="chevron-left" :size="14" /> Précédente</UiButton>
                            <UiButton :disabled="meta.currentPage >= meta.lastPage" @click="update({ ...query, page: meta.currentPage + 1 })">Suivante <UiIcon name="chevron-right" :size="14" /></UiButton>
                        </span>
                    </footer>
                </UiCard>
            </template>
        </div>
    </main>
</template>
