<script setup lang="ts">
import type { FieldSchema } from '../types'
import DateField from './DateField.vue'
import RichTextField from './RichTextField.vue'
import SelectField from './SelectField.vue'
import TextField from './TextField.vue'
import ToggleField from './ToggleField.vue'

defineProps<{
    field: FieldSchema
    modelValue: unknown
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
    <DateField
        v-else-if="field.type === 'date'"
        :field="field"
        :model-value="modelValue"
        @update:model-value="emit('update:modelValue', $event)"
    />
</template>
