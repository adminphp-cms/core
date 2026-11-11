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

onMounted(() => document.addEventListener('click', closeMenu))
onBeforeUnmount(() => document.removeEventListener('click', closeMenu))
</script>

<template>
    <div class="blocks-zone">
        <p v-if="editor.errorFor(path)" class="field-error zone-error">{{ editor.errorFor(path) }}</p>

        <template v-for="(block, index) in blocks" :key="block.id">
            <div v-if="index === 0" class="insert">
                <button type="button" aria-haspopup="menu" @click="toggleMenu(-1)"><UiIcon name="plus" :size="13" /> Ajouter un bloc</button>
                <div v-if="openMenuAt === -1" class="menu" role="menu">
                    <button v-for="schema in field.blocks" :key="schema.type" type="button" role="menuitem" @click="insert(schema, -1)">
                        <b>{{ schema.title }}</b>
                    </button>
                </div>
            </div>

            <section class="block" :data-block-id="block.id" :class="{ 'has-error': editor.errorFor(`${path}.${index}.type`) }">
                <div class="gutter">
                    <span class="type">{{ schemasByType[block.type]?.title ?? block.type }}</span>
                    <span class="tools">
                        <button type="button" title="Monter" aria-label="Monter" :disabled="index === 0" @click="move(index, -1)"><UiIcon name="arrow-up" :size="13" /></button>
                        <button type="button" title="Descendre" aria-label="Descendre" :disabled="index === blocks.length - 1" @click="move(index, 1)"><UiIcon name="arrow-down" :size="13" /></button>
                        <button type="button" title="Supprimer" aria-label="Supprimer" @click="remove(index)"><UiIcon name="x" :size="13" /></button>
                    </span>
                </div>
                <div class="body block-body">
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

            <div class="insert">
                <button type="button" aria-haspopup="menu" @click="toggleMenu(index)"><UiIcon name="plus" :size="13" /> Ajouter un bloc</button>
                <div v-if="openMenuAt === index" class="menu" role="menu">
                    <button v-for="schema in field.blocks" :key="schema.type" type="button" role="menuitem" @click="insert(schema, index)">
                        <b>{{ schema.title }}</b>
                    </button>
                </div>
            </div>
        </template>

        <div v-if="blocks.length === 0" class="insert is-empty">
            <button type="button" aria-haspopup="menu" @click="toggleMenu(-1)"><UiIcon name="plus" :size="13" /> Ajouter un premier bloc</button>
            <div v-if="openMenuAt === -1" class="menu" role="menu">
                <button v-for="schema in field.blocks" :key="schema.type" type="button" role="menuitem" @click="insert(schema, -1)">
                    <b>{{ schema.title }}</b>
                </button>
            </div>
        </div>
    </div>
</template>
