<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useLocalizedValue } from '../composables/useLocalizedValue'
import type { TextFieldSchema } from '../types'

const props = defineProps<{
    field: TextFieldSchema
    modelValue: unknown
    variant?: 'default' | 'title' | 'lead'
    autofocus?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>()

const { value, isMissingTranslation } = useLocalizedValue<string>(props, emit)

const textarea = ref<HTMLTextAreaElement | null>(null)
const input = ref<HTMLInputElement | null>(null)

function resize(): void {
    if (textarea.value) {
        textarea.value.style.height = 'auto'
        textarea.value.style.height = `${textarea.value.scrollHeight}px`
    }
}

function update(event: Event): void {
    const text = (event.target as HTMLInputElement | HTMLTextAreaElement).value

    value.value = text === '' ? null : text
}

onMounted(() => {
    resize()

    if (props.autofocus) {
        ;(textarea.value ?? input.value)?.focus()
    }
})
watch(value, () => nextTick(resize))

/**
 * Inputs drawn as the text they hold: no frame, a soft background on hover and focus.
 * Below 760px the default size is 16px so iOS does not zoom on focus.
 */
const inputClass = [
    '-mx-2 block w-full resize-none overflow-hidden rounded-sm border-none px-2 py-1 font-sans transition-[background]',
    'hover:not-focus:bg-hover focus:bg-accent-soft focus:[outline:none]',
]
const variantClasses: Record<'default' | 'title' | 'lead', string> = {
    default: 'text-md leading-[1.6] font-normal text-ink max-md:text-lg',
    title: 'text-4xl leading-[1.15] font-semibold tracking-[-0.03em] text-strong max-md:text-3xl',
    lead: 'text-xl leading-[1.5] font-normal text-muted',
}
const textClass = computed(() => [
    inputClass,
    variantClasses[props.variant ?? 'default'],
    isMissingTranslation.value ? 'placeholder:text-warning' : 'placeholder:text-faint',
])
</script>

<template>
    <textarea
        v-if="field.multiline || variant === 'title'"
        ref="textarea"
        :class="textClass"
        rows="1"
        :value="value ?? ''"
        :maxlength="field.maxLength"
        :placeholder="isMissingTranslation ? 'Pas encore traduit' : field.label"
        @input="update"
    />
    <input
        v-else
        ref="input"
        type="text"
        :class="textClass"
        :value="value ?? ''"
        :maxlength="field.maxLength"
        :placeholder="isMissingTranslation ? 'Pas encore traduit' : field.label"
        @input="update"
    />
</template>
