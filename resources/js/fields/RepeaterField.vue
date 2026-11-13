<script setup lang="ts">
import { computed, ref } from 'vue'
import UiButton from '../components/ui/UiButton.vue'
import UiIcon from '../components/ui/UiIcon.vue'
import { useEditorStore } from '../stores/editor'
import type { RepeaterFieldSchema, RepeaterItem } from '../types'
import { ulid } from '../ulid'
import FieldRenderer from './FieldRenderer.vue'
import { defaultValues } from './initialValues'

const props = defineProps<{
    field: RepeaterFieldSchema
    modelValue: unknown
    path: string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: RepeaterItem[]] }>()

const editor = useEditorStore()

const items = computed(() => (Array.isArray(props.modelValue) ? (props.modelValue as RepeaterItem[]) : []))
const isFull = computed(() => props.field.max !== null && items.value.length >= props.field.max)

/** Ids of the folded items: all items start open. */
const foldedIds = ref(new Set<string>())

/** Item just added: its first field takes the focus when it mounts. */
const addedItemId = ref<string | null>(null)

function update(newItems: RepeaterItem[]): void {
    emit('update:modelValue', newItems)
}

function add(): void {
    const id = ulid()
    addedItemId.value = id
    update([...items.value, { ...defaultValues(props.field.fields), id }])
}

function move(index: number, offset: -1 | 1): void {
    const target = index + offset

    if (target < 0 || target >= items.value.length) {
        return
    }

    const newItems = [...items.value]
    ;[newItems[index], newItems[target]] = [newItems[target], newItems[index]]
    update(newItems)
}

function remove(index: number): void {
    update(items.value.filter((_, itemIndex) => itemIndex !== index))
}

function updateField(index: number, fieldName: string, value: unknown): void {
    update(items.value.map((item, itemIndex) => (itemIndex === index ? { ...item, [fieldName]: value } : item)))
}

function toggleFold(id: string): void {
    const folded = new Set(foldedIds.value)
    folded.has(id) ? folded.delete(id) : folded.add(id)
    foldedIds.value = folded
}

/** The value of the label field in the editing locale (else any filled one), else the item number. */
function itemTitle(item: RepeaterItem, index: number): string {
    let value = props.field.itemLabel ? item[props.field.itemLabel] : null

    if (value && typeof value === 'object' && !Array.isArray(value)) {
        const translations = value as Record<string, unknown>
        value = translations[editor.locale] || Object.values(translations).find(Boolean)
    }

    return typeof value === 'string' && value.trim() !== '' ? value : `Élément ${index + 1}`
}

function hasErrors(index: number): boolean {
    const prefix = `${props.path}.${index}.`

    return Object.keys(editor.errors).some((key) => key.startsWith(prefix))
}

const toolButtonClass = 'grid size-6 place-items-center rounded-[6px] border-none text-icon hover:not-disabled:bg-nav-hover hover:not-disabled:text-ink disabled:opacity-40'
</script>

<template>
    <div class="flex flex-col gap-2.5">
        <section
            v-for="(item, index) in items"
            :key="item.id"
            class="group/item rounded-lg border bg-surface transition-[border-color]"
            :class="hasErrors(index) ? 'border-danger' : 'border-ring-control'"
        >
            <header class="flex h-10 items-center gap-1 pr-2 pl-1.5" :class="{ 'border-b border-line': !foldedIds.has(item.id) }">
                <button
                    type="button"
                    class="flex h-8 min-w-0 flex-1 items-center gap-2 rounded-sm px-1.5 text-left"
                    :aria-expanded="!foldedIds.has(item.id)"
                    @click="toggleFold(item.id)"
                >
                    <UiIcon :name="foldedIds.has(item.id) ? 'chevron-right' : 'chevron-down'" :size="14" class="flex-none text-icon" />
                    <span class="flex-none text-xs text-faint tabular-nums">{{ index + 1 }}</span>
                    <span class="truncate font-medium text-strong">{{ itemTitle(item, index) }}</span>
                </button>
                <span class="flex gap-0.5 opacity-0 transition-opacity duration-150 group-focus-within/item:opacity-100 group-hover/item:opacity-100 max-md:opacity-100">
                    <button type="button" :class="toolButtonClass" title="Monter" aria-label="Monter" :disabled="index === 0" @click="move(index, -1)"><UiIcon name="arrow-up" :size="13" /></button>
                    <button type="button" :class="toolButtonClass" title="Descendre" aria-label="Descendre" :disabled="index === items.length - 1" @click="move(index, 1)"><UiIcon name="arrow-down" :size="13" /></button>
                    <button type="button" :class="toolButtonClass" title="Supprimer" aria-label="Supprimer" @click="remove(index)"><UiIcon name="x" :size="13" /></button>
                </span>
            </header>

            <div v-if="!foldedIds.has(item.id)" class="flex flex-col gap-4 px-3.5 pt-3 pb-4">
                <FieldRenderer
                    v-for="(itemField, fieldIndex) in field.fields"
                    :key="itemField.name"
                    :field="itemField"
                    :model-value="item[itemField.name]"
                    :path="`${path}.${index}.${itemField.name}`"
                    :autofocus="fieldIndex === 0 && item.id === addedItemId"
                    layout="stacked"
                    @update:model-value="updateField(index, itemField.name, $event)"
                />
            </div>
        </section>

        <div class="flex items-center gap-2.5">
            <UiButton :disabled="isFull" @click="add"><UiIcon name="plus" :size="14" /> {{ field.addLabel }}</UiButton>
            <span v-if="field.max !== null" class="text-xs text-faint tabular-nums">{{ items.length }} / {{ field.max }}</span>
        </div>
    </div>
</template>
