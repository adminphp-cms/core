<script setup lang="ts">
import { computed } from 'vue'
import { controlClasses } from '../components/ui/controlClasses'
import type { DateFieldSchema } from '../types'

const props = defineProps<{
    field: DateFieldSchema
    modelValue: unknown
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>()

/**
 * Dates travel as `Y-m-d`; moments as ISO 8601, shown in the browser time zone.
 */
const inputValue = computed(() => {
    if (typeof props.modelValue !== 'string' || props.modelValue === '') {
        return ''
    }

    if (!props.field.withTime) {
        return props.modelValue.slice(0, 10)
    }

    const date = new Date(props.modelValue)
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset())

    return date.toISOString().slice(0, 16)
})

function update(event: Event): void {
    const value = (event.target as HTMLInputElement).value

    if (value === '') {
        emit('update:modelValue', null)
    } else {
        emit('update:modelValue', props.field.withTime ? new Date(value).toISOString() : value)
    }
}
</script>

<template>
    <input
        :class="controlClasses"
        :type="field.withTime ? 'datetime-local' : 'date'"
        :value="inputValue"
        :aria-label="field.label"
        @change="update"
    />
</template>
