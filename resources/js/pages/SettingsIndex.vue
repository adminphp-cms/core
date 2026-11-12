<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue'
import { api, ApiError } from '../api'
import PageIntro from '../components/ui/PageIntro.vue'
import UiButton from '../components/ui/UiButton.vue'
import { provideFormContext } from '../composables/useFormContext'
import { useToast } from '../composables/useToast'
import { useSaveShortcut, useUnsavedChanges } from '../composables/useUnsavedChanges'
import { config } from '../config'
import FieldRenderer from '../fields/FieldRenderer.vue'
import { useEditorStore } from '../stores/editor'
import type { FormValues, SettingsSchema } from '../types'

/**
 * Site-wide settings declared by the application (homepage, tagline…), saved in one go.
 */
const editor = useEditorStore()
const toast = useToast()

const schema = ref<SettingsSchema | null>(null)
const values = ref<FormValues>({})
const isDirty = ref(false)
const isSaving = ref(false)

provideFormContext({ values, isNew: ref(false) })
useUnsavedChanges(isDirty)
useSaveShortcut(() => {
    if (schema.value !== null && !isSaving.value) {
        save()
    }
})

const hasTranslatableField = () => schema.value?.fields.some((field) => field.translatable) ?? false

const localeButtonClass = [
    'flex items-center gap-1.5 rounded-[7px] border border-transparent px-2.5',
    'text-xs font-semibold tracking-[0.03em] text-muted transition-[background,color]',
    'aria-pressed:border-ring-control aria-pressed:bg-surface aria-pressed:text-strong',
]

async function apply(data: { schema: SettingsSchema; values: FormValues }): Promise<void> {
    schema.value = data.schema
    values.value = data.values
    await nextTick()
    isDirty.value = false
}

async function save(): Promise<void> {
    isSaving.value = true
    editor.errors = {}

    try {
        await apply(await api.updateSettings(values.value))
        toast.success('Réglages enregistrés')
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            editor.errors = error.errors
            toast.error('Certains champs sont à corriger')

            return
        }

        console.error(error)
        toast.error('L’enregistrement a échoué. Réessayez dans un instant.')
    } finally {
        isSaving.value = false
    }
}

onMounted(async () => {
    editor.errors = {}
    editor.locale = config.defaultLocale
    await apply(await api.settings())
    watch(values, () => (isDirty.value = true), { deep: true })
})
</script>

<template>
    <main class="page">
        <header class="top">
            <div class="crumbs"><strong>{{ schema?.title ?? 'Réglages' }}</strong></div>

            <div v-if="config.locales.length > 1 && hasTranslatableField()" class="flex h-8 gap-0.5 rounded-md bg-accent-soft p-[3px]" role="group" aria-label="Langue de saisie">
                <button
                    v-for="locale in config.locales"
                    :key="locale"
                    type="button"
                    :class="localeButtonClass"
                    :aria-pressed="editor.locale === locale"
                    @click="editor.locale = locale"
                >
                    {{ locale.toUpperCase() }}
                </button>
            </div>

            <span v-if="isDirty" class="flex items-center gap-1.5 px-2 text-sm text-warning before:size-1.5 before:rounded-[50%] before:bg-current">
                Modifications non enregistrées
            </span>

            <UiButton variant="primary" :disabled="isSaving || schema === null" @click="save">
                {{ isSaving ? 'Enregistrement…' : 'Enregistrer' }}
            </UiButton>
        </header>

        <div class="page-body">
            <PageIntro :title="schema?.title ?? 'Réglages'" :description="schema?.description || undefined" icon="settings" />

            <div v-if="schema" class="max-w-[900px] rounded-xl border border-ring-card bg-surface pt-9 pr-11 pb-7 pl-5 max-md:px-4 max-md:py-6">
                <FieldRenderer v-for="field in schema.fields" :key="field.name" v-model="values[field.name]" :field="field" :path="field.name" />
            </div>
        </div>
    </main>
</template>
