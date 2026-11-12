<script setup lang="ts">
import { computed, onMounted, ref, useId, type FunctionalComponent } from 'vue'
import UiIcon from './UiIcon.vue'
import UiIconButton from './UiIconButton.vue'

/**
 * A modal dialog, open as soon as it mounts: a header with the title and a close button,
 * the body, and an optional footer for the actions. With `form`, header, body and footer
 * sit in a form whose submission emits `submit`.
 */
const props = withDefaults(
    defineProps<{
        title: string
        titleId?: string
        lead?: string
        width?: string
        form?: boolean
    }>(),
    { form: false },
)

const emit = defineEmits<{ close: []; submit: [event: Event] }>()

const dialog = ref<HTMLDialogElement | null>(null)
const generatedTitleId = useId()

/** Renders its content without an element, for the dialog without a form. */
const Passthrough: FunctionalComponent = (_, { slots }) => slots.default?.()

const wrapper = computed(() => (props.form ? 'form' : Passthrough))

/** A custom width replaces the default one, still leaving a 16px gutter on narrow screens. */
const widthStyle = computed(() => (props.width ? { width: `min(${props.width}, calc(100vw - 32px))` } : undefined))

function submit(event: Event): void {
    event.preventDefault()
    emit('submit', event)
}

function close(): void {
    dialog.value?.close()
}

onMounted(() => dialog.value?.showModal())

defineExpose({ close })
</script>

<template>
    <dialog
        ref="dialog"
        class="m-auto max-h-[calc(100vh-48px)] rounded-2xl border border-ring-card bg-surface p-0 text-ink [box-shadow:var(--popover-shadow)] backdrop:bg-[rgba(9,9,11,0.32)] backdrop:[backdrop-filter:blur(2px)] open:animate-[pop-in_180ms_var(--ease-snappy)]"
        :class="{ 'w-[min(520px,calc(100vw-32px))]': !width }"
        :style="widthStyle"
        :aria-labelledby="titleId ?? generatedTitleId"
        @close="emit('close')"
    >
        <component :is="wrapper" v-bind="form ? { onSubmit: submit } : {}">
            <header class="flex items-center gap-2.5 border-b border-b-line py-3.5 pr-4 pl-5">
                <h2 :id="titleId ?? generatedTitleId" class="flex-1 text-lg font-semibold text-strong">{{ title }}</h2>
                <UiIconButton label="Fermer" @click="close">
                    <UiIcon name="x" :size="14" />
                </UiIconButton>
            </header>

            <div class="flex flex-col gap-4 p-5">
                <p v-if="lead || $slots.lead" class="text-muted"><slot name="lead">{{ lead }}</slot></p>
                <slot />
            </div>

            <footer v-if="$slots.footer || $slots['footer-start']" class="flex flex-wrap justify-end gap-2 border-t border-t-line bg-sunken px-5 py-3.5">
                <div v-if="$slots['footer-start']" class="mr-auto flex gap-2 max-sm:w-full">
                    <slot name="footer-start" />
                </div>
                <slot name="footer" />
            </footer>
        </component>
    </dialog>
</template>
