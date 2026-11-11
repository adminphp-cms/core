<script setup lang="ts">
import { computed } from 'vue'
import type { SelectFieldSchema, SelectOption } from '../types'

const props = defineProps<{
    field: SelectFieldSchema
    modelValue: unknown
}>()

const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>()

const selectedValues = computed(() => (Array.isArray(props.modelValue) ? (props.modelValue as SelectOption['value'][]) : []))

/** Index of the selected option in a single select, -1 for none (native selects only handle strings). */
const selectedIndex = computed(() => props.field.options.findIndex((option) => option.value === props.modelValue))

function selectIndex(event: Event): void {
    const index = Number((event.target as HTMLSelectElement).value)

    emit('update:modelValue', index < 0 ? null : props.field.options[index].value)
}

function toggleOption(option: SelectOption): void {
    const isSelected = selectedValues.value.includes(option.value)

    emit(
        'update:modelValue',
        isSelected ? selectedValues.value.filter((value) => value !== option.value) : [...selectedValues.value, option.value],
    )
}
</script>

<template>
    <div v-if="field.multiple" class="choice-list" role="group" :aria-label="field.label">
        <button
            v-for="option in field.options"
            :key="option.value"
            type="button"
            class="choice"
            :aria-pressed="selectedValues.includes(option.value)"
            @click="toggleOption(option)"
        >
            {{ option.label }}
        </button>
    </div>

    <select v-else class="control-input" :value="selectedIndex" :aria-label="field.label" @change="selectIndex">
        <option :value="-1" :disabled="field.required && selectedIndex >= 0">—</option>
        <option v-for="(option, index) in field.options" :key="option.value" :value="index">{{ option.label }}</option>
    </select>
</template>
