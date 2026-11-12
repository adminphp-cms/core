<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import BrowserPicker from '../components/browser/BrowserPicker.vue'
import UiBadge from '../components/ui/UiBadge.vue'
import UiButton from '../components/ui/UiButton.vue'
import UiIcon from '../components/ui/UiIcon.vue'
import { visibilityBadges } from '../format'
import { useEditorStore } from '../stores/editor'
import { referenceKey, useReferencesStore } from '../stores/references'
import type { BrowserFieldSchema, ContentReference, ReferencedContent } from '../types'

const props = defineProps<{
    field: BrowserFieldSchema
    modelValue: unknown
    path: string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: ContentReference[]] }>()

const editor = useEditorStore()
const referencesStore = useReferencesStore()
const isPickerOpen = ref(false)

const references = computed<ContentReference[]>(() =>
    Array.isArray(props.modelValue)
        ? (props.modelValue as unknown[])
              .filter((value): value is ContentReference => typeof value === 'object' && value !== null && 'type' in value && 'id' in value)
              .map(({ type, id }) => ({ type, id }))
        : [],
)
const selectedKeys = computed(() => references.value.map(referenceKey))
const isFull = computed(() => props.field.max !== null && references.value.length >= props.field.max)

watch(references, (newReferences) => referencesStore.load(newReferences), { immediate: true })

function update(newReferences: ContentReference[]): void {
    emit('update:modelValue', newReferences)
}

function move(index: number, offset: -1 | 1): void {
    const target = index + offset

    if (target < 0 || target >= references.value.length) {
        return
    }

    const newReferences = [...references.value]
    ;[newReferences[index], newReferences[target]] = [newReferences[target], newReferences[index]]
    update(newReferences)
}

function remove(index: number): void {
    update(references.value.filter((_, referenceIndex) => referenceIndex !== index))
}

/** Adds a content from the picker, or takes it out when it was already chosen. A single choice replaces the previous one. */
function toggle(content: ReferencedContent): void {
    referencesStore.remember(content)

    const reference = { type: content.type, id: content.id }
    const key = referenceKey(reference)

    if (selectedKeys.value.includes(key)) {
        update(references.value.filter((existing) => referenceKey(existing) !== key))
    } else if (props.field.max === 1) {
        update([reference])
        isPickerOpen.value = false
    } else if (!isFull.value) {
        update([...references.value, reference])
    }
}

function errorAt(index: number): string | null {
    return editor.errorFor(`${props.path}.${index}.id`) ?? editor.errorFor(`${props.path}.${index}.type`)
}

const toolButtonClass = 'grid size-6 place-items-center rounded-[6px] border-none text-icon hover:not-disabled:bg-nav-hover hover:not-disabled:text-ink disabled:opacity-40'
</script>

<template>
    <div class="flex flex-col gap-2.5">
        <ul v-if="references.length" class="overflow-hidden rounded-lg border border-ring-control bg-surface">
            <li
                v-for="(reference, index) in references"
                :key="referenceKey(reference)"
                class="group/item flex items-center gap-3 border-line py-2 pr-2 pl-2.5 not-first:border-t"
            >
                <span class="grid size-8 flex-none place-items-center rounded-md bg-accent-soft text-icon">
                    <UiIcon :name="referencesStore.byKey[referenceKey(reference)]?.icon ?? 'file-text'" :size="15" />
                </span>
                <div class="min-w-0 flex-1">
                    <template v-if="referencesStore.byKey[referenceKey(reference)]">
                        <p class="truncate font-medium text-strong">{{ referencesStore.byKey[referenceKey(reference)]!.title }}</p>
                        <p class="text-xs text-faint">{{ referencesStore.byKey[referenceKey(reference)]!.moduleTitle }}</p>
                    </template>
                    <p v-else-if="referencesStore.byKey[referenceKey(reference)] === null" class="text-danger">Ce contenu n’existe plus.</p>
                    <p v-else class="text-faint">Chargement…</p>
                    <p v-if="errorAt(index)" class="field-error">{{ errorAt(index) }}</p>
                </div>
                <UiBadge
                    v-if="referencesStore.byKey[referenceKey(reference)]?.visibility"
                    :tone="visibilityBadges[referencesStore.byKey[referenceKey(reference)]!.visibility!].tone"
                    dot
                >
                    {{ visibilityBadges[referencesStore.byKey[referenceKey(reference)]!.visibility!].label }}
                </UiBadge>
                <span class="flex gap-0.5 opacity-0 transition-opacity duration-150 group-focus-within/item:opacity-100 group-hover/item:opacity-100 max-md:opacity-100">
                    <button type="button" :class="toolButtonClass" title="Monter" aria-label="Monter" :disabled="index === 0" @click="move(index, -1)"><UiIcon name="arrow-up" :size="13" /></button>
                    <button type="button" :class="toolButtonClass" title="Descendre" aria-label="Descendre" :disabled="index === references.length - 1" @click="move(index, 1)"><UiIcon name="arrow-down" :size="13" /></button>
                    <button type="button" :class="toolButtonClass" title="Retirer" aria-label="Retirer" @click="remove(index)"><UiIcon name="x" :size="13" /></button>
                </span>
            </li>
        </ul>

        <div class="flex items-center gap-2.5">
            <UiButton :disabled="isFull && field.max !== 1" @click="isPickerOpen = true">
                <UiIcon :name="references.length && field.max === 1 ? 'refresh' : 'plus'" :size="14" />
                {{ references.length && field.max === 1 ? 'Remplacer' : references.length ? 'Ajouter' : 'Choisir' }}
            </UiButton>
            <span v-if="field.max !== null && field.max > 1" class="text-xs text-faint tabular-nums">{{ references.length }} / {{ field.max }}</span>
        </div>

        <BrowserPicker
            v-if="isPickerOpen"
            :title="field.label"
            :modules="field.modules"
            :selected-keys="selectedKeys"
            :is-full="isFull && field.max !== 1"
            @toggle="toggle"
            @close="isPickerOpen = false"
        />
    </div>
</template>
