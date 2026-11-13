<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { api, ApiError } from '../api'
import type { FormValues } from '../types'
import UiIcon from './ui/UiIcon.vue'
import UiIconButton from './ui/UiIconButton.vue'

/**
 * The site's page of the item being edited, unsaved changes included, in a full-screen
 * panel. It follows the form while it is open and can be shown at phone or tablet width.
 */
const props = defineProps<{
    module: string
    id?: string
    values: FormValues
    locale: string
}>()

const emit = defineEmits<{ close: [] }>()

type Device = 'desktop' | 'tablet' | 'mobile'

const devices: { value: Device; label: string; icon: string; width: string }[] = [
    { value: 'desktop', label: 'Ordinateur', icon: 'monitor', width: '100%' },
    { value: 'tablet', label: 'Tablette', icon: 'tablet', width: '768px' },
    { value: 'mobile', label: 'Mobile', icon: 'smartphone', width: '390px' },
]

const device = ref<Device>('desktop')
const html = ref('')
const error = ref<string | null>(null)
const isLoading = ref(false)
const frameWidth = computed(() => devices.find((option) => option.value === device.value)!.width)

/** Links of the previewed page open in a new tab instead of inside the panel. */
const srcdoc = computed(() => (html.value.includes('<head>') ? html.value.replace('<head>', '<head><base target="_blank">') : html.value))

let requestNumber = 0

async function refresh(): Promise<void> {
    const currentRequest = ++requestNumber
    isLoading.value = true

    try {
        const rendered = await api.previewItem(props.module, props.id, props.values, props.locale)

        if (currentRequest === requestNumber) {
            html.value = rendered
            error.value = null
        }
    } catch (exception) {
        if (currentRequest === requestNumber) {
            error.value = exception instanceof ApiError ? exception.message : 'L’aperçu n’a pas pu être affiché.'
        }
    } finally {
        if (currentRequest === requestNumber) {
            isLoading.value = false
        }
    }
}

let refreshTimer: ReturnType<typeof setTimeout> | undefined

watch(
    () => [props.values, props.locale],
    () => {
        clearTimeout(refreshTimer)
        refreshTimer = setTimeout(refresh, 600)
    },
    { deep: true },
)

function closeOnEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        emit('close')
    }
}

onMounted(() => {
    refresh()
    document.addEventListener('keydown', closeOnEscape)
})

onBeforeUnmount(() => {
    clearTimeout(refreshTimer)
    document.removeEventListener('keydown', closeOnEscape)
})

const deviceButtonClass = 'grid size-8 place-items-center rounded-md transition-[background,color]'
</script>

<template>
    <div class="fixed inset-0 z-50 flex flex-col bg-canvas" role="dialog" aria-modal="true" aria-label="Aperçu">
        <header class="flex h-14 flex-none items-center gap-3 border-b border-line bg-surface px-4">
            <UiIcon name="eye" :size="16" class="text-icon" />
            <p class="font-semibold text-strong">Aperçu</p>
            <p class="text-sm text-faint max-md:hidden">Modifications non enregistrées comprises · {{ locale.toUpperCase() }}</p>
            <span v-if="isLoading" class="text-sm text-faint">Mise à jour…</span>

            <div class="ml-auto flex gap-0.5 rounded-md bg-accent-soft p-[3px]" role="group" aria-label="Taille d’écran">
                <button
                    v-for="option in devices"
                    :key="option.value"
                    type="button"
                    :class="[deviceButtonClass, device === option.value ? 'bg-surface text-ink [box-shadow:inset_0_0_0_1px_var(--ring-control)]' : 'text-muted hover:text-ink']"
                    :aria-pressed="device === option.value"
                    :title="option.label"
                    :aria-label="option.label"
                    @click="device = option.value"
                >
                    <UiIcon :name="option.icon" :size="15" />
                </button>
            </div>
            <UiIconButton label="Actualiser" @click="refresh"><UiIcon name="refresh" :size="14" /></UiIconButton>
            <UiIconButton label="Fermer l’aperçu" @click="emit('close')"><UiIcon name="x" :size="14" /></UiIconButton>
        </header>

        <div class="flex min-h-0 flex-1 justify-center overflow-auto p-4 max-md:p-0">
            <p v-if="error" class="m-auto max-w-120 rounded-lg border border-danger bg-danger-soft px-4 py-3 text-danger">{{ error }}</p>
            <iframe
                v-else
                :srcdoc="srcdoc"
                title="Aperçu de la page"
                class="h-full max-w-full rounded-lg border border-ring-card bg-white transition-[width] duration-200"
                :style="{ width: frameWidth }"
            />
        </div>
    </div>
</template>
