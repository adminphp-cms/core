<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { ulid } from '../ulid'
import { useEditorStore } from '../stores/editor'
import type { BlockSchema, BlocksFieldSchema, BlockValue } from '../types'
import UiIcon from '../components/ui/UiIcon.vue'
import FieldRenderer from './FieldRenderer.vue'

const props = defineProps<{
    field: BlocksFieldSchema
    modelValue: unknown
    path: string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: BlockValue[]] }>()

const editor = useEditorStore()

const blocks = computed(() => (Array.isArray(props.modelValue) ? (props.modelValue as BlockValue[]) : []))
const schemasByType = computed(() => Object.fromEntries(props.field.blocks.map((block) => [block.type, block])))

/** Index after which the insert menu is open (-1 = at the top), or null when closed. */
const openMenuAt = ref<number | null>(null)

function update(newBlocks: BlockValue[]): void {
    emit('update:modelValue', newBlocks)
}

/** Block just inserted: its first field takes the focus when it mounts. */
const insertedBlockId = ref<string | null>(null)

function insert(schema: BlockSchema, afterIndex: number): void {
    const id = ulid()
    const newBlocks = [...blocks.value]
    newBlocks.splice(afterIndex + 1, 0, { id, type: schema.type, content: {} })
    openMenuAt.value = null
    insertedBlockId.value = id
    update(newBlocks)
}

function move(index: number, offset: -1 | 1): void {
    const target = index + offset

    if (target < 0 || target >= blocks.value.length) {
        return
    }

    const newBlocks = [...blocks.value]
    ;[newBlocks[index], newBlocks[target]] = [newBlocks[target], newBlocks[index]]
    update(newBlocks)
}

function remove(index: number): void {
    update(blocks.value.filter((_, blockIndex) => blockIndex !== index))
}

function updateContent(index: number, fieldName: string, value: unknown): void {
    update(blocks.value.map((block, blockIndex) => (blockIndex === index ? { ...block, content: { ...block.content, [fieldName]: value } } : block)))
}

function toggleMenu(afterIndex: number): void {
    openMenuAt.value = openMenuAt.value === afterIndex ? null : afterIndex
}

function closeMenu(event: Event): void {
    if (!(event.target as HTMLElement).closest('.insert')) {
        openMenuAt.value = null
    }
}

/** Same grid as a field row, so the insert button lines up with the fields. `.insert` stays: closeMenu() looks for it. */
const insertClass = 'insert relative grid grid-cols-[128px_minmax(0,1fr)] items-center gap-x-6 gap-y-0 py-1.5 max-md:grid-cols-[minmax(0,1fr)]'
const insertButtonClass = [
    'col-2 row-1 flex h-11 w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-ring-hover',
    'text-sm font-medium text-muted transition-[color,background,border-color]',
    '[.insert:hover>&]:border-brand-ring [.insert:hover>&]:bg-brand-soft [.insert:hover>&]:text-brand',
    'focus-visible:border-brand-ring focus-visible:bg-brand-soft focus-visible:text-brand',
    'aria-expanded:border-brand-ring aria-expanded:bg-brand-soft aria-expanded:text-brand',
    'max-md:col-1',
]
const menuClass = [
    'absolute top-[calc(100%_-_2px)] left-[calc(152px_+_(100%_-_152px)_/_2)] z-10 ml-[-110px] w-[220px] origin-top p-1',
    'rounded-lg border border-ring-card bg-surface shadow-popover animate-[pop-in_150ms_var(--ease-snappy)]',
    'max-md:left-1/2',
]
const menuItemClass = 'flex h-8.5 w-full items-center rounded-sm border-none px-2.5 text-left text-ink transition-[background,color] hover:bg-nav-hover hover:text-ink focus-visible:bg-nav-hover'

const gutterClass = [
    'border-r-2 pt-2 pr-4 text-right text-xs font-medium select-none transition-[color,border-color]',
    'max-md:flex max-md:items-center max-md:gap-2.5 max-md:p-0 max-md:pb-1 max-md:text-left max-md:[border-right:0]!',
]
const gutterStateClass = (hasError: boolean): string =>
    hasError ? 'border-r-danger text-danger' : 'border-r-transparent text-faint group-focus-within/row:border-r-brand group-focus-within/row:text-ink'
const toolsClass =
    'mt-1.5 flex justify-end gap-0.5 opacity-0 transition-opacity duration-150 ease-out group-focus-within/row:opacity-100 group-[:hover]/row:opacity-100 max-md:m-0 max-md:opacity-100'
const toolButtonClass = 'grid size-6 place-items-center rounded-[6px] border-none text-icon hover:not-disabled:bg-nav-hover hover:not-disabled:text-ink'

onMounted(() => document.addEventListener('click', closeMenu))
onBeforeUnmount(() => document.removeEventListener('click', closeMenu))
</script>

<template>
    <div>
        <p v-if="editor.errorFor(path)" class="field-error pl-38 max-md:pl-0">{{ editor.errorFor(path) }}</p>

        <template v-for="(block, index) in blocks" :key="block.id">
            <div v-if="index === 0" :class="insertClass">
                <button type="button" aria-haspopup="menu" :class="insertButtonClass" :aria-expanded="openMenuAt === -1" @click="toggleMenu(-1)"><UiIcon name="plus" :size="15" /> Ajouter un bloc</button>
                <div v-if="openMenuAt === -1" :class="menuClass" role="menu">
                    <button v-for="schema in field.blocks" :key="schema.type" type="button" role="menuitem" :class="menuItemClass" @click="insert(schema, -1)">
                        <b class="font-medium">{{ schema.title }}</b>
                    </button>
                </div>
            </div>

            <section class="group/row relative grid grid-cols-[128px_minmax(0,1fr)] gap-x-6 gap-y-0 max-md:grid-cols-[minmax(0,1fr)]" :data-block-id="block.id">
                <div :class="[gutterClass, gutterStateClass(Boolean(editor.errorFor(`${path}.${index}.type`)))]">
                    <span class="block">{{ schemasByType[block.type]?.title ?? block.type }}</span>
                    <span :class="toolsClass">
                        <button type="button" :class="toolButtonClass" title="Monter" aria-label="Monter" :disabled="index === 0" @click="move(index, -1)"><UiIcon name="arrow-up" :size="13" /></button>
                        <button type="button" :class="toolButtonClass" title="Descendre" aria-label="Descendre" :disabled="index === blocks.length - 1" @click="move(index, 1)"><UiIcon name="arrow-down" :size="13" /></button>
                        <button type="button" :class="toolButtonClass" title="Supprimer" aria-label="Supprimer" @click="remove(index)"><UiIcon name="x" :size="13" /></button>
                    </span>
                </div>
                <div class="flex min-w-0 flex-col gap-4 pt-1 pb-5">
                    <template v-if="schemasByType[block.type]">
                        <FieldRenderer
                            v-for="(blockField, fieldIndex) in schemasByType[block.type].fields"
                            :key="blockField.name"
                            :field="blockField"
                            :model-value="block.content[blockField.name]"
                            :path="`${path}.${index}.content.${blockField.name}`"
                            :autofocus="fieldIndex === 0 && block.id === insertedBlockId"
                            layout="stacked"
                            @update:model-value="updateContent(index, blockField.name, $event)"
                        />
                    </template>
                    <p v-else class="field-error">Type de bloc inconnu : {{ block.type }}</p>
                </div>
            </section>

            <div :class="insertClass">
                <button type="button" aria-haspopup="menu" :class="insertButtonClass" :aria-expanded="openMenuAt === index" @click="toggleMenu(index)"><UiIcon name="plus" :size="15" /> Ajouter un bloc</button>
                <div v-if="openMenuAt === index" :class="menuClass" role="menu">
                    <button v-for="schema in field.blocks" :key="schema.type" type="button" role="menuitem" :class="menuItemClass" @click="insert(schema, index)">
                        <b class="font-medium">{{ schema.title }}</b>
                    </button>
                </div>
            </div>
        </template>

        <p v-if="field.blocks.length === 0" class="m-0 rounded-md border border-dashed border-line px-3 py-2.5 text-sm text-faint">
            Aucun type de bloc n’est autorisé : ajoutez-en dans <code>allow()</code> du champ Blocks.
        </p>
        <div v-else-if="blocks.length === 0" :class="insertClass">
            <button type="button" aria-haspopup="menu" :class="insertButtonClass" :aria-expanded="openMenuAt === -1" @click="toggleMenu(-1)"><UiIcon name="plus" :size="15" /> Ajouter un premier bloc</button>
            <div v-if="openMenuAt === -1" :class="menuClass" role="menu">
                <button v-for="schema in field.blocks" :key="schema.type" type="button" role="menuitem" :class="menuItemClass" @click="insert(schema, -1)">
                    <b class="font-medium">{{ schema.title }}</b>
                </button>
            </div>
        </div>
    </div>
</template>
