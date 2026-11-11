<script setup lang="ts">
import type { ToggleFieldSchema } from '../types'

const props = defineProps<{
    field: ToggleFieldSchema
    modelValue: unknown
}>()

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
</script>

<template>
    <button
        type="button"
        class="switch"
        role="switch"
        :aria-checked="modelValue === true"
        :aria-label="field.label"
        @click="emit('update:modelValue', props.modelValue !== true)"
    >
        <span class="switch-track"><span class="switch-thumb" /></span>
        {{ modelValue === true ? 'Oui' : 'Non' }}
    </button>
</template>
