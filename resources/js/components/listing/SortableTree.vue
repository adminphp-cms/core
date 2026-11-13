<script setup lang="ts">
import Sortable from 'sortablejs'
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { formatDateTime, visibilityBadges } from '../../format'
import type { ItemPlacement, ItemSummary } from '../../types'
import UiBadge from '../ui/UiBadge.vue'
import UiIcon from '../ui/UiIcon.vue'

const props = defineProps<{
    module: string
    items: ItemSummary[]
    /** How many levels items can be nested (0: a flat list). Only one sub-level is drawn. */
    nestingDepth: number
    publishable: boolean
}>()

const emit = defineEmits<{ reorder: [placements: ItemPlacement[]] }>()

const tree = ref<HTMLElement | null>(null)
const isDragging = ref(false)

/** Bumped after each drop so Vue redraws the lists SortableJS has moved nodes in. */
const version = ref(0)

let sortables: Sortable[] = []

const roots = computed(() => {
    const ids = new Set(props.items.map((item) => item.id))

    return props.items.filter((item) => item.parentId === null || !ids.has(item.parentId))
})

function childrenOf(item: ItemSummary): ItemSummary[] {
    return props.items.filter((child) => child.parentId === item.id)
}

/** The new order, read from the lists as SortableJS left them: parents before their children. */
function placementsFromDom(): ItemPlacement[] {
    const placements: ItemPlacement[] = []

    tree.value?.querySelectorAll<HTMLElement>(':scope > ul > li').forEach((root) => {
        placements.push({ id: root.dataset.id!, parentId: null })

        root.querySelectorAll<HTMLElement>(':scope > ul > li').forEach((child) => placements.push({ id: child.dataset.id!, parentId: root.dataset.id! }))
    })

    return placements
}

/** The current tree as ids, in the shape placementsFromDom() reads: parents before their children. */
function placementsWith(move: (roots: { id: string; children: string[] }[]) => void): ItemPlacement[] {
    const structure = roots.value.map((root) => ({ id: root.id, children: childrenOf(root).map((child) => child.id) }))
    move(structure)

    return structure.flatMap((root) => [{ id: root.id, parentId: null }, ...root.children.map((id) => ({ id, parentId: root.id }))])
}

/** Takes a sub-item out of its parent: it becomes a top-level item, right after its former parent. */
function outdent(child: ItemSummary, parent: ItemSummary): void {
    apply(
        placementsWith((structure) => {
            const parentIndex = structure.findIndex((root) => root.id === parent.id)
            structure[parentIndex].children = structure[parentIndex].children.filter((id) => id !== child.id)
            structure.splice(parentIndex + 1, 0, { id: child.id, children: [] })
        }),
    )
}

/** Puts a top-level item without sub-items under the item above it, as its last sub-item. */
function indent(index: number): void {
    apply(
        placementsWith((structure) => {
            const [moved] = structure.splice(index, 1)
            structure[index - 1].children.push(moved.id)
        }),
    )
}

function canIndent(root: ItemSummary, index: number): boolean {
    return props.nestingDepth > 0 && index > 0 && childrenOf(root).length === 0
}

/** Redraws the lists from the new order (SortableJS may have moved their nodes) and saves it. */
async function apply(placements: ItemPlacement[]): Promise<void> {
    destroySortables()
    version.value++
    emit('reorder', placements)
    await nextTick()
    createSortables()
}

function destroySortables(): void {
    sortables.forEach((sortable) => sortable.destroy())
    sortables = []
}

function createSortables(): void {
    tree.value?.querySelectorAll<HTMLElement>('ul[data-list]').forEach((list) => {
        sortables.push(
            Sortable.create(list, {
                group: `tree-${props.module}`,
                handle: '[data-handle]',
                animation: 150,
                // Mouse-driven dragging: same look in every browser, and the dragged row follows the pointer.
                forceFallback: true,
                fallbackClass: 'sortable-fallback',
                fallbackOnBody: true,
                swapThreshold: 0.65,
                emptyInsertThreshold: 16,
                ghostClass: 'opacity-40',
                onStart: () => (isDragging.value = true),
                // An item with sub-items cannot go under another item: it would make a third level.
                onMove: (event) => !(event.to.dataset.list === 'children' && event.dragged.querySelector(':scope > ul > li') !== null),
                onEnd: () => {
                    isDragging.value = false
                    apply(placementsFromDom())
                },
            }),
        )
    })
}

onMounted(createSortables)
onBeforeUnmount(destroySortables)

const rowClass = 'group/row relative flex min-h-12 items-center gap-3 border-t border-line bg-surface pr-5 hover:bg-hover'
/** Shown while the row is hovered or focused; always on touch screens, which have no hover. */
const moveButtonClass =
    'relative z-10 inline-flex h-7 max-w-56 flex-none items-center gap-1 rounded-[6px] px-2 text-xs font-medium text-muted hover:bg-nav-hover hover:text-ink focus-visible:opacity-100 md:opacity-0 md:group-hover/row:opacity-100 md:group-focus-within/row:opacity-100'
const handleClass = 'relative z-10 grid size-7 flex-none cursor-grab place-items-center rounded-[6px] text-faint hover:bg-nav-hover hover:text-ink active:cursor-grabbing'
</script>

<template>
    <!-- Rounded like .table-scroll so rows stay inside the card's corners; rows are separated by a top border, the first one has none. -->
    <div ref="tree" :key="version" class="overflow-hidden rounded-b-[var(--round-card)] [&>ul>li:first-child>div]:border-t-0" :class="{ 'is-dragging': isDragging }">
        <ul data-list="roots">
            <!-- While dragging, a gap under each group: dropping there puts the item at the first level, right after the group. -->
            <li v-for="(root, index) in roots" :key="root.id" :data-id="root.id" class="transition-[padding] [.is-dragging_&:has(>ul>li)]:pb-5">
                <div :class="[rowClass, 'pl-2.5']">
                    <span data-handle :class="handleClass" title="Glisser pour déplacer" aria-hidden="true"><UiIcon name="grip-vertical" :size="15" /></span>
                    <RouterLink :to="{ name: 'module.edit', params: { module, id: root.id } }" class="min-w-0 flex-1 truncate font-medium text-strong after:absolute after:inset-0 after:content-['']">
                        {{ root.title }}
                    </RouterLink>
                    <span v-if="publishable" class="relative flex gap-1.5">
                        <UiBadge v-if="root.visibility" :tone="visibilityBadges[root.visibility].tone" dot>{{ visibilityBadges[root.visibility].label }}</UiBadge>
                        <UiBadge v-if="root.hasDraft" tone="warning">À publier</UiBadge>
                    </span>
                    <button
                        v-if="canIndent(root, index)"
                        type="button"
                        :class="moveButtonClass"
                        :aria-label="`Placer « ${root.title} » sous « ${roots[index - 1].title} »`"
                        @click="indent(index)"
                    >
                        <span class="truncate">Sous « {{ roots[index - 1].title }} »</span>
                        <UiIcon name="chevron-right" :size="14" />
                    </button>
                    <span class="w-36 text-right text-xs text-faint tabular-nums max-md:hidden">{{ root.updatedAt ? formatDateTime(root.updatedAt) : '' }}</span>
                </div>

                <ul
                    v-if="nestingDepth > 0"
                    data-list="children"
                    class="transition-[min-height] [.is-dragging_&:empty]:mx-5 [.is-dragging_&:empty]:my-1 [.is-dragging_&:empty]:ml-12 [.is-dragging_&:empty]:min-h-8 [.is-dragging_&:empty]:rounded-md [.is-dragging_&:empty]:border [.is-dragging_&:empty]:border-dashed [.is-dragging_&:empty]:border-ring-hover"
                >
                    <li v-for="child in childrenOf(root)" :key="child.id" :data-id="child.id">
                        <div :class="[rowClass, 'pl-10']">
                            <span data-handle :class="handleClass" title="Glisser pour déplacer" aria-hidden="true"><UiIcon name="grip-vertical" :size="15" /></span>
                            <RouterLink :to="{ name: 'module.edit', params: { module, id: child.id } }" class="min-w-0 flex-1 truncate text-ink after:absolute after:inset-0 after:content-['']">
                                {{ child.title }}
                            </RouterLink>
                            <span v-if="publishable" class="relative flex gap-1.5">
                                <UiBadge v-if="child.visibility" :tone="visibilityBadges[child.visibility].tone" dot>{{ visibilityBadges[child.visibility].label }}</UiBadge>
                                <UiBadge v-if="child.hasDraft" tone="warning">À publier</UiBadge>
                            </span>
                            <button
                                type="button"
                                :class="moveButtonClass"
                                :aria-label="`Sortir « ${child.title} » de « ${root.title} », au premier niveau`"
                                @click="outdent(child, root)"
                            >
                                <UiIcon name="chevron-left" :size="14" />
                                <span class="truncate">Sortir de « {{ root.title }} »</span>
                            </button>
                            <span class="w-36 text-right text-xs text-faint tabular-nums max-md:hidden">{{ child.updatedAt ? formatDateTime(child.updatedAt) : '' }}</span>
                        </div>
                    </li>
                </ul>
            </li>
        </ul>
    </div>
</template>
