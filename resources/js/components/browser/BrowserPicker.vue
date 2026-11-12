<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { api } from '../../api'
import { formatRelative, visibilityBadges } from '../../format'
import { referenceKey } from '../../stores/references'
import type { BrowserModule, ItemSummary, ReferencedContent } from '../../types'
import EmptyState from '../ui/EmptyState.vue'
import UiBadge from '../ui/UiBadge.vue'
import UiButton from '../ui/UiButton.vue'
import UiIcon from '../ui/UiIcon.vue'
import UiIconButton from '../ui/UiIconButton.vue'

const props = defineProps<{
    title: string
    modules: BrowserModule[]
    selectedKeys: string[]
    /** No more contents can be added: only chosen ones can be taken out. */
    isFull: boolean
}>()

const emit = defineEmits<{ toggle: [content: ReferencedContent]; close: [] }>()

const dialog = ref<HTMLDialogElement | null>(null)
const activeModule = ref<BrowserModule>(props.modules[0])
const items = ref<ItemSummary[]>([])
const search = ref('')
const isLoaded = ref(false)

let requestNumber = 0

async function load(): Promise<void> {
    const currentRequest = ++requestNumber
    const loadedItems = await api.items(activeModule.value.name, search.value.trim())

    // An older search answering late must not replace the newer one.
    if (currentRequest === requestNumber) {
        items.value = loadedItems
        isLoaded.value = true
    }
}

let searchTimer: ReturnType<typeof setTimeout> | undefined

watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(load, 250)
})

watch(activeModule, () => {
    isLoaded.value = false
    load()
})

function isSelected(item: ItemSummary): boolean {
    return props.selectedKeys.includes(referenceKey({ type: activeModule.value.name, id: item.id }))
}

function toggle(item: ItemSummary): void {
    emit('toggle', {
        type: activeModule.value.name,
        id: item.id,
        title: item.title,
        moduleTitle: activeModule.value.title,
        icon: activeModule.value.icon,
        visibility: item.visibility,
    })
}

onMounted(() => {
    dialog.value?.showModal()
    load()
})

const tabClass = 'flex h-8 items-center gap-1.5 rounded-md px-2.5 text-sm font-medium transition-[background,color]'
</script>

<template>
    <dialog
        ref="dialog"
        class="m-auto h-[min(560px,calc(100vh-48px))] w-[min(640px,calc(100vw-32px))] overflow-hidden rounded-2xl border border-ring-card bg-surface p-0 text-ink shadow-popover backdrop:bg-[rgba(9,9,11,0.32)] backdrop:[backdrop-filter:blur(2px)] open:flex open:animate-[pop-in_180ms_var(--ease-snappy)] open:flex-col"
        aria-labelledby="browser-picker-title"
        @close="emit('close')"
    >
        <header class="flex items-center gap-2.5 border-b border-line py-3.5 pr-4 pl-5">
            <h2 id="browser-picker-title" class="min-w-0 flex-1 truncate text-lg font-semibold text-strong">{{ title }}</h2>
            <label class="search-field max-md:w-36!">
                <UiIcon name="search" :size="14" />
                <input v-model="search" type="search" placeholder="Rechercher" :aria-label="`Rechercher dans ${activeModule.title}`" />
            </label>
            <UiIconButton label="Fermer" @click="dialog?.close()">
                <UiIcon name="x" :size="14" />
            </UiIconButton>
        </header>

        <nav v-if="modules.length > 1" class="flex gap-1 border-b border-line px-4 py-2" aria-label="Modules">
            <button
                v-for="module in modules"
                :key="module.name"
                type="button"
                :class="[tabClass, module.name === activeModule.name ? 'bg-brand-soft text-brand' : 'text-muted hover:bg-nav-hover hover:text-ink']"
                :aria-pressed="module.name === activeModule.name"
                @click="activeModule = module"
            >
                <UiIcon :name="module.icon" :size="14" /> {{ module.title }}
            </button>
        </nav>

        <div class="min-h-0 flex-1 overflow-y-auto p-2">
            <ul v-if="items.length">
                <li v-for="item in items" :key="item.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition-[background] hover:not-disabled:bg-nav-hover disabled:opacity-50"
                        :aria-pressed="isSelected(item)"
                        :disabled="isFull && !isSelected(item)"
                        @click="toggle(item)"
                    >
                        <span
                            class="grid size-4.5 flex-none place-items-center rounded-[5px] border transition-[background,border-color]"
                            :class="isSelected(item) ? 'border-brand bg-brand text-white' : 'border-ring-control bg-surface'"
                        >
                            <UiIcon v-if="isSelected(item)" name="check" :size="12" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-strong">{{ item.title }}</span>
                            <span v-if="item.updatedAt" class="block text-xs text-faint">Modifié {{ formatRelative(item.updatedAt) }}</span>
                        </span>
                        <UiBadge v-if="item.visibility" :tone="visibilityBadges[item.visibility].tone" dot>{{ visibilityBadges[item.visibility].label }}</UiBadge>
                    </button>
                </li>
            </ul>
            <EmptyState
                v-else-if="isLoaded"
                :icon="activeModule.icon"
                :title="search ? `Aucun résultat pour « ${search} »` : `Aucun contenu dans ${activeModule.title}`"
            />
        </div>

        <footer class="flex items-center justify-between gap-3 border-t border-line px-5 py-3">
            <span class="text-xs text-faint">{{ selectedKeys.length }} {{ selectedKeys.length > 1 ? 'choisis' : 'choisi' }}</span>
            <UiButton variant="primary" @click="dialog?.close()">Terminé</UiButton>
        </footer>
    </dialog>
</template>
