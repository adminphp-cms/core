<script lang="ts">
import type { InjectionKey } from 'vue'

/** Tells nested controls (such as UiInput) that they render inside a field. */
export const fieldContextKey: InjectionKey<boolean> = Symbol('fieldContext')
</script>

<script setup lang="ts">
import { provide } from 'vue'

/**
 * A labelled form control with an optional hint and error message. `fieldset` groups
 * several controls under a legend (a set of radios, say).
 */
withDefaults(defineProps<{ label: string; hint?: string; error?: string; as?: 'label' | 'fieldset' }>(), { as: 'label' })

provide(fieldContextKey, true)
</script>

<template>
    <component :is="as" class="flex min-w-0 flex-col gap-1.5 border-none">
        <legend v-if="as === 'fieldset'" class="mb-1.5 text-sm font-medium text-ink">{{ label }}</legend>
        <span v-else class="text-sm font-medium text-ink">{{ label }}</span>
        <slot />
        <small v-if="hint" class="text-xs text-faint">{{ hint }}</small>
        <small v-if="error" class="field-error">{{ error }}</small>
    </component>
</template>
