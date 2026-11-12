<script lang="ts">
import type { InjectionKey } from 'vue'

/** Tells nested components (such as EmptyState) that they render inside a card. */
export const cardContextKey: InjectionKey<boolean> = Symbol('cardContext')
</script>

<script setup lang="ts">
import { provide } from 'vue'

withDefaults(defineProps<{ title?: string; description?: string; padded?: boolean; bodyClass?: string }>(), { padded: true })

provide(cardContextKey, true)
</script>

<template>
    <section class="relative rounded-xl border border-ring-card bg-surface">
        <header
            v-if="title || $slots.actions"
            class="flex min-h-14 items-center justify-between gap-3 border-b border-b-line px-4.5 py-3.5 max-md:flex-wrap"
        >
            <div class="flex min-w-0 flex-col gap-0.5">
                <h2 v-if="title" class="text-md font-semibold tracking-[-0.01em] text-strong">{{ title }}</h2>
                <p v-if="description" class="text-sm text-muted">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="flex flex-none items-center gap-2"><slot name="actions" /></div>
        </header>

        <!-- `ui-card-body` stays as a hook for page rules such as `.inspector .ui-card-body`. -->
        <div :class="[padded && 'ui-card-body p-4.5', bodyClass]"><slot /></div>

        <footer v-if="$slots.footer" class="border-t border-t-line px-4.5 py-3"><slot name="footer" /></footer>
    </section>
</template>
