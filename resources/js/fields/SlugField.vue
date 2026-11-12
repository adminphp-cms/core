<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import UiIcon from '../components/ui/UiIcon.vue'
import { useFormContext } from '../composables/useFormContext'
import { useLocalizedValue } from '../composables/useLocalizedValue'
import { config } from '../config'
import { slugify } from '../slugify'
import { useEditorStore } from '../stores/editor'
import type { SlugFieldSchema } from '../types'

const props = defineProps<{
    field: SlugFieldSchema
    modelValue: unknown
}>()

const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>()

const { value } = useLocalizedValue<string>(props, emit)
const { values, isNew } = useFormContext()
const editor = useEditorStore()

/**
 * Text of the source field in the locale being edited (the default locale for a single slug).
 */
const sourceText = computed(() => {
    const source = values.value[props.field.from]

    if (source !== null && typeof source === 'object') {
        const translations = source as Record<string, unknown>
        const text = translations[props.field.translatable ? editor.locale : config.defaultLocale]

        return typeof text === 'string' ? text : ''
    }

    return typeof source === 'string' ? source : ''
})

const suggestion = computed(() => slugify(sourceText.value, props.field.maxLength))

/**
 * A new item's slug follows its title as long as nobody edits the slug by hand:
 * it stays empty or equal to the slug of the previous title.
 */
watch(sourceText, (newText, oldText) => {
    const current = value.value ?? ''

    if (isNew.value && (current === '' || current === slugify(oldText ?? '', props.field.maxLength))) {
        value.value = slugify(newText, props.field.maxLength) || null
    }
})

const input = ref<HTMLInputElement | null>(null)

function update(event: Event): void {
    const text = (event.target as HTMLInputElement).value

    value.value = text === '' ? null : text
}

/** Tidies what was typed as soon as the field is left, like the server will. */
function normalize(): void {
    if (value.value) {
        value.value = slugify(value.value, props.field.maxLength) || null
    }
}

function regenerate(): void {
    value.value = suggestion.value || null
    input.value?.focus()
}

const canRegenerate = computed(() => suggestion.value !== '' && suggestion.value !== (value.value ?? ''))
</script>

<template>
    <div class="slug-field">
        <label class="slug-input">
            <UiIcon name="link" :size="14" />
            <span class="slug-prefix">{{ field.prefix }}</span>
            <input
                ref="input"
                type="text"
                :value="value ?? ''"
                :maxlength="field.maxLength"
                :placeholder="suggestion || 'genere-depuis-le-titre'"
                :aria-label="field.label"
                spellcheck="false"
                autocomplete="off"
                @input="update"
                @blur="normalize"
            />
        </label>

        <button v-if="canRegenerate" type="button" class="btn" title="Remplacer par l’adresse tirée du titre" @click="regenerate">
            <UiIcon name="refresh" :size="14" /> Depuis le titre
        </button>

        <p v-if="!value" class="slug-hint">Laissée vide, l’adresse sera tirée du titre à l’enregistrement.</p>
    </div>
</template>
