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
    const typed = (event.target as HTMLInputElement | HTMLTextAreaElement).value
    // Spaces are never part of an e-mail, an address or a number: a pasted value often brings some.
    const text = ['email', 'url', 'number'].includes(props.field.inputType) ? typed.trim() : typed

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
/** Mobile keyboard of each kind of text. */
const inputModes: Record<TextFieldSchema['inputType'], 'text' | 'email' | 'url' | 'tel' | 'decimal'> = {
    text: 'text',
    email: 'email',
    url: 'url',
    tel: 'tel',
    number: 'decimal',
}

const placeholder = computed(() => (isMissingTranslation.value ? 'Pas encore traduit' : (props.field.placeholder ?? props.field.label)))

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
        :placeholder="placeholder"
        :aria-label="field.label"
        @input="update"
    />
    <input
        v-else
        ref="input"
        :type="field.inputType"
        :inputmode="inputModes[field.inputType]"
        :autocomplete="field.inputType === 'text' ? undefined : 'off'"
        :class="textClass"
        :value="value ?? ''"
        :maxlength="field.inputType === 'number' ? undefined : field.maxLength"
        :min="field.min ?? undefined"
        :max="field.max ?? undefined"
        :step="field.inputType === 'number' ? (field.step ?? 'any') : undefined"
        :placeholder="placeholder"
        :aria-label="field.label"
        @input="update"
    />
</template>
