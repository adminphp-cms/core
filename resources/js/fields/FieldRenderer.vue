<script setup lang="ts">
import { computed } from 'vue'
import { useEditorStore } from '../stores/editor'
import type { FieldSchema } from '../types'
import BlocksField from './BlocksField.vue'
import FieldInput from './FieldInput.vue'

const props = withDefaults(
    defineProps<{
        field: FieldSchema
        modelValue: unknown
        path: string
        layout?: 'row' | 'stacked'
        variant?: 'default' | 'title' | 'lead'
        autofocus?: boolean
    }>(),
    { layout: 'row', variant: 'default', autofocus: false },
)

const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>()

const editor = useEditorStore()

const error = computed(() => {
    const ownError = editor.errorFor(props.path)

    return ownError ?? (props.field.translatable ? editor.errorFor(`${props.path}.${editor.locale}`) : null)
})

/** The label column of a row: a vertical rule that lights up while editing, red on error, above the field on narrow screens. */
const gutterClass = [
    'border-r-2 pt-2 pr-4 text-right text-xs font-medium select-none transition-[color,border-color]',
    'max-md:flex max-md:items-center max-md:gap-2.5 max-md:p-0 max-md:pb-1 max-md:text-left max-md:[border-right:0]!',
]
const gutterStateClass = computed(() =>
    error.value ? 'border-r-danger text-danger' : 'border-r-transparent text-faint group-focus-within/row:border-r-brand group-focus-within/row:text-ink',
)
</script>

<template>
    <BlocksField
        v-if="field.type === 'blocks'"
        :field="field"
        :model-value="modelValue"
        :path="path"
        @update:model-value="emit('update:modelValue', $event)"
    />

    <section v-else-if="layout === 'row'" class="group/row relative grid grid-cols-[128px_minmax(0,1fr)] gap-x-6 gap-y-0 max-md:grid-cols-[minmax(0,1fr)]">
        <div :class="[gutterClass, gutterStateClass]">
            <span class="block">{{ field.label }}<i v-if="field.required" class="ml-0.5 text-brand not-italic" title="Obligatoire">*</i></span>
            <span v-if="field.translatable" class="mt-0.5 inline-block text-3xs font-semibold tracking-[0.06em] uppercase">{{ editor.locale }}</span>
        </div>
        <div class="min-w-0 pt-1 pb-5">
            <FieldInput :field="field" :model-value="modelValue" :path="path" :variant="variant" :autofocus="autofocus" @update:model-value="emit('update:modelValue', $event)" />
            <p v-if="field.note" class="mt-1 text-xs text-muted">{{ field.note }}</p>
            <p v-if="error" class="field-error">{{ error }}</p>
        </div>
    </section>

    <div v-else>
        <span class="mb-1.5 block text-xs font-medium text-muted">{{ field.label }}<i v-if="field.required" class="ml-0.5 text-brand not-italic">*</i></span>
        <FieldInput :field="field" :model-value="modelValue" :path="path" :variant="variant" :autofocus="autofocus" @update:model-value="emit('update:modelValue', $event)" />
        <p v-if="field.note" class="mt-1 text-xs text-muted">{{ field.note }}</p>
        <p v-if="error" class="field-error">{{ error }}</p>
    </div>
</template>
