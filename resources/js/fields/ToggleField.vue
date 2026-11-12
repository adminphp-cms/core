<script setup lang="ts">
import type { ToggleFieldSchema } from '../types'

const props = defineProps<{
    field: ToggleFieldSchema
    modelValue: unknown
}>()

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const trackClass = [
    'relative h-5 w-[34px] rounded-[100px] bg-ring-control transition-[background] duration-200 ease-out',
    'group-focus-visible:outline-2 group-focus-visible:outline-offset-2 group-focus-visible:outline-focus group-aria-checked:bg-success',
]

const thumbClass = [
    'absolute top-0.5 left-0.5 size-4 rounded-[50%] bg-white [box-shadow:0_1px_2px_rgba(9,9,11,0.25)]',
    'transition-[transform] duration-200 ease-out group-aria-checked:[transform:translateX(14px)]',
]
</script>

<template>
    <button
        type="button"
        class="group inline-flex h-8 items-center gap-2.5 border-none bg-transparent p-0 font-medium text-muted focus-visible:outline-none aria-checked:text-ink"
        role="switch"
        :aria-checked="modelValue === true"
        :aria-label="field.label"
        @click="emit('update:modelValue', props.modelValue !== true)"
    >
        <span :class="trackClass"><span :class="thumbClass" /></span>
        {{ modelValue === true ? 'Oui' : 'Non' }}
    </button>
</template>
