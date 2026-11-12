<script setup lang="ts">
import type { FieldSchema } from '../types'
import BrowserField from './BrowserField.vue'
import DateField from './DateField.vue'
import ImageField from './ImageField.vue'
import RepeaterField from './RepeaterField.vue'
import RichTextField from './RichTextField.vue'
import SelectField from './SelectField.vue'
import SlugField from './SlugField.vue'
import TextField from './TextField.vue'
import ToggleField from './ToggleField.vue'

defineProps<{
    field: FieldSchema
    modelValue: unknown
    /** Input path of the value, for the fields that show the errors of their parts. */
    path: string
    variant?: 'default' | 'title' | 'lead'
    autofocus?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>()
</script>

<template>
    <TextField
        v-if="field.type === 'text'"
        :field="field"
        :model-value="modelValue"
        :variant="variant"
        :autofocus="autofocus"
        @update:model-value="emit('update:modelValue', $event)"
    />
    <RichTextField
        v-else-if="field.type === 'rich-text'"
        :field="field"
        :model-value="modelValue"
        :autofocus="autofocus"
        @update:model-value="emit('update:modelValue', $event)"
    />
    <SlugField
        v-else-if="field.type === 'slug'"
        :field="field"
        :model-value="modelValue"
        @update:model-value="emit('update:modelValue', $event)"
    />
    <SelectField
        v-else-if="field.type === 'select'"
        :field="field"
        :model-value="modelValue"
        @update:model-value="emit('update:modelValue', $event)"
    />
    <ToggleField
        v-else-if="field.type === 'toggle'"
        :field="field"
        :model-value="modelValue"
        @update:model-value="emit('update:modelValue', $event)"
    />
    <ImageField
        v-else-if="field.type === 'image'"
        :field="field"
        :model-value="modelValue"
        @update:model-value="emit('update:modelValue', $event)"
    />
    <DateField
        v-else-if="field.type === 'date'"
        :field="field"
        :model-value="modelValue"
        @update:model-value="emit('update:modelValue', $event)"
    />
    <RepeaterField
        v-else-if="field.type === 'repeater'"
        :field="field"
        :model-value="modelValue"
        :path="path"
        @update:model-value="emit('update:modelValue', $event)"
    />
    <BrowserField
        v-else-if="field.type === 'browser'"
        :field="field"
        :model-value="modelValue"
        :path="path"
        @update:model-value="emit('update:modelValue', $event)"
    />
</template>
