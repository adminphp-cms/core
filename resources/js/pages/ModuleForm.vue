<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api, ApiError } from '../api'
import { isFilled } from '../composables/useLocalizedValue'
import { config } from '../config'
import PreviewPanel from '../components/PreviewPanel.vue'
import PublicationPanel from '../components/PublicationPanel.vue'
import { provideFormContext } from '../composables/useFormContext'
import { useToast } from '../composables/useToast'
import { useEditLock } from '../composables/useEditLock'
import { useSaveShortcut, useUnsavedChanges } from '../composables/useUnsavedChanges'
import UiButton from '../components/ui/UiButton.vue'
import UiIconButton from '../components/ui/UiIconButton.vue'
import UiIcon from '../components/ui/UiIcon.vue'
import FieldRenderer from '../fields/FieldRenderer.vue'
import { initialValue } from '../fields/initialValues'
import { formatRelative } from '../format'
import { useEditorStore } from '../stores/editor'
import { useModulesStore } from '../stores/modules'
import type { BlockValue, FieldSchema, FormValues, Item, ModuleSchema, Publication, RevisionSummary } from '../types'

const props = defineProps<{ module: string; id?: string }>()

const router = useRouter()
const modulesStore = useModulesStore()
const editor = useEditorStore()

const schema = ref<ModuleSchema | null>(null)
const values = ref<FormValues>({})
const itemTitle = ref('')
const isDirty = ref(false)
const isSaving = ref(false)
/** The editor only shows once the schema and the item are there, so nothing empty can be saved. */
const isLoaded = ref(false)

provideFormContext({ values, isNew: computed(() => !props.id) })
const toast = useToast()
const publication = ref<Publication | null>(null)
const revisions = ref<RevisionSummary[]>([])
const isPreviewOpen = ref(false)

/** Reloads the saved item, e.g. once the lock frees up after watching someone else edit it. */
async function reloadItem(): Promise<void> {
    if (props.id) {
        await applyItem(await api.item(props.module, props.id))
        await loadRevisions(props.id)
    }
}

const editLock = useEditLock(props.module, props.id, reloadItem)
const isReadOnly = editLock.isReadOnly

async function takeOverLock(): Promise<void> {
    const name = editLock.lock.value?.userName ?? 'L’autre personne'

    if (window.confirm(`${name} ne pourra plus enregistrer ses modifications. Prendre la main ?`)) {
        await editLock.takeOver()
    }
}

function reloadPage(): void {
    isDirty.value = false
    window.location.reload()
}
const publishStartAt = ref('')
const publishEndAt = ref('')

const isPublishable = computed(() => schema.value?.publishable === true)

const isScheduleChanged = computed(
    () =>
        publication.value !== null &&
        (publishStartAt.value !== toLocalInput(publication.value.publishStartAt) ||
            publishEndAt.value !== toLocalInput(publication.value.publishEndAt)),
)

/**
 * Whether publishing would change what the site shows.
 */
const hasSomethingToPublish = computed(
    () =>
        publication.value === null ||
        publication.value.status !== 'published' ||
        publication.value.hasDraft ||
        isDirty.value ||
        isScheduleChanged.value,
)

const saveStatus = computed(() => {
    if (!props.id) {
        return { label: 'Pas encore enregistré', isSaved: false }
    }

    if (isDirty.value) {
        return { label: 'Modifications non enregistrées', isSaved: false }
    }

    if (publication.value?.hasDraft) {
        return { label: 'Enregistré, non publié', isSaved: false }
    }

    return { label: 'Enregistré', isSaved: true }
})

const publishLabel = computed(() => {
    if (publication.value?.status === 'published') {
        return hasSomethingToPublish.value ? 'Publier les modifications' : 'Publié'
    }

    return 'Publier'
})

/**
 * ISO date from the API to the value of a datetime-local input, in the browser time zone.
 */
function toLocalInput(iso: string | null): string {
    if (iso === null) {
        return ''
    }

    const date = new Date(iso)
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset())

    return date.toISOString().slice(0, 16)
}

function fromLocalInput(value: string): string | null {
    return value === '' ? null : new Date(value).toISOString()
}

/**
 * Whether a translatable value is filled in the default locale but not in the given one,
 * looking inside blocks too.
 */
function hasMissingTranslation(fields: FieldSchema[], fieldValues: Record<string, unknown>, locale: string): boolean {
    return fields.some((field) => {
        const value = fieldValues[field.name]

        if (field.type === 'blocks') {
            return ((value as BlockValue[] | undefined) ?? []).some((block) => {
                const blockSchema = field.blocks.find((candidate) => candidate.type === block.type)

                return blockSchema !== undefined && hasMissingTranslation(blockSchema.fields, block.content, locale)
            })
        }

        if (!field.translatable) {
            return false
        }

        const translations = (value ?? {}) as Record<string, unknown>

        return isFilled(translations[config.defaultLocale]) && !isFilled(translations[locale])
    })
}

const localesWithGaps = computed(() =>
    schema.value === null
        ? []
        : config.locales.filter((locale) => locale !== config.defaultLocale && hasMissingTranslation(schema.value!.fields, values.value, locale)),
)

async function applyItem(item: Item): Promise<void> {
    values.value = item.values
    itemTitle.value = item.title
    publication.value = item.publication

    if (item.publication !== null) {
        publishStartAt.value = toLocalInput(item.publication.publishStartAt)
        publishEndAt.value = toLocalInput(item.publication.publishEndAt)
    }

    await nextTick()
    isDirty.value = false
}

async function loadRevisions(id: string): Promise<void> {
    revisions.value = isPublishable.value ? await api.revisions(props.module, id) : []
}

/**
 * Runs an API call that returns the item, with the shared busy state and error handling.
 */
async function run(action: () => Promise<Item>, successMessage: string): Promise<Item | null> {
    isSaving.value = true
    editor.errors = {}

    try {
        const item = await action()
        await applyItem(item)
        toast.success(successMessage)
        modulesStore.loadModules()

        return item
    } catch (error) {
        if (error instanceof ApiError && error.status === 423) {
            toast.error(error.message)
            await editLock.markLost()

            return null
        }

        if (error instanceof ApiError && error.status === 422) {
            editor.errors = error.errors
            toast.error(error.errors.publishEndAt?.[0] ?? 'Certains champs sont à corriger')

            return null
        }

        console.error(error)
        toast.error('L’opération a échoué. Réessayez dans un instant.')

        return null
    } finally {
        isSaving.value = false
    }
}

async function goToItem(id: string): Promise<void> {
    if (!props.id) {
        await router.replace({ name: 'module.edit', params: { module: props.module, id } })
    }
}

async function save(): Promise<void> {
    const item = await run(
        () => (props.id ? api.updateItem(props.module, props.id, values.value) : api.createItem(props.module, values.value)),
        publication.value?.status === 'published' ? 'Enregistré, pas encore publié' : 'Enregistré',
    )

    if (item !== null) {
        await goToItem(item.id)
    }
}

useUnsavedChanges(isDirty)
useSaveShortcut(() => {
    if (isLoaded.value && !isSaving.value && !isReadOnly.value) {
        save()
    }
})

async function publish(): Promise<void> {
    const options = { publishStartAt: fromLocalInput(publishStartAt.value), publishEndAt: fromLocalInput(publishEndAt.value) }

    const item = await run(async () => {
        if (!props.id) {
            const created = await api.createItem(props.module, values.value)

            return api.publishItem(props.module, created.id, options)
        }

        return api.publishItem(props.module, props.id, { ...options, values: isDirty.value ? values.value : undefined })
    }, 'Publié')

    if (item !== null) {
        await loadRevisions(item.id)
        await goToItem(item.id)
    }
}

async function unpublish(): Promise<void> {
    if (props.id && window.confirm('Retirer ce contenu du site ?')) {
        await run(() => api.unpublishItem(props.module, props.id!), 'Retiré du site')
    }
}

async function discardDraft(): Promise<void> {
    if (props.id && window.confirm('Abandonner les modifications non publiées et revenir à la version en ligne ?')) {
        await run(() => api.discardDraft(props.module, props.id!), 'Modifications abandonnées')
    }
}

async function duplicate(): Promise<void> {
    if (!props.id || (isDirty.value && !window.confirm('Dupliquer la dernière version enregistrée ? Les modifications non enregistrées ne seront pas copiées.'))) {
        return
    }

    isSaving.value = true

    try {
        const copy = await api.duplicateItem(props.module, props.id)
        isDirty.value = false
        modulesStore.loadModules()
        await router.push({ name: 'module.edit', params: { module: props.module, id: copy.id } })
        toast.success('Copie créée, hors ligne')
    } catch (error) {
        console.error(error)
        toast.error('La copie a échoué. Réessayez dans un instant.')
    } finally {
        isSaving.value = false
    }
}

async function trash(): Promise<void> {
    if (!props.id || !window.confirm('Mettre ce contenu à la corbeille ? Il disparaîtra du site et pourra être restauré depuis la corbeille.')) {
        return
    }

    isSaving.value = true

    try {
        await api.trashItem(props.module, props.id)
        isDirty.value = false
        modulesStore.loadModules()
        await router.push({ name: 'module.index', params: { module: props.module } })
        toast.success('Mis à la corbeille')
    } catch (error) {
        console.error(error)
        toast.error('La suppression a échoué. Réessayez dans un instant.')
    } finally {
        isSaving.value = false
    }
}

async function restoreRevision(revisionId: string): Promise<void> {
    const message = isDirty.value
        ? 'Restaurer cette version ? Les modifications non enregistrées seront perdues.'
        : 'Restaurer cette version comme brouillon ?'

    if (props.id && window.confirm(message)) {
        await run(() => api.restoreRevision(props.module, props.id!, revisionId), 'Version restaurée, à publier')
    }
}

/** Utility sets of the editor layout, shared by the loading skeleton and the loaded editor. */
const canvasClass = 'px-7 pt-8 pb-30 max-md:px-3 max-md:pt-4 max-md:pb-15'
const docClass = 'mx-auto max-w-[900px] rounded-xl border border-ring-card bg-surface pt-11 pr-11 pb-9 max-md:px-4 max-md:py-6'
const skeletonClass =
    'block rounded-[6px] bg-[linear-gradient(90deg,var(--accent-soft)_0%,var(--hover)_50%,var(--accent-soft)_100%)] bg-[length:200%_100%] animate-[shimmer_1.4s_linear_infinite]'
const inspectorLayoutClass = 'grid grid-cols-[minmax(0,1fr)_320px] items-start max-xl:block'
const localeButtonClass = [
    'flex items-center gap-1.5 rounded-[7px] border border-transparent px-2.5',
    'text-xs font-semibold tracking-[0.03em] text-muted transition-[background,color]',
    'aria-pressed:border-ring-control aria-pressed:bg-surface aria-pressed:text-strong',
]

const saveStatusClass = computed(() => (saveStatus.value.isSaved ? 'text-muted before:bg-success' : 'text-warning before:bg-current'))

onMounted(async () => {
    editor.locale = config.defaultLocale
    editor.errors = {}

    schema.value = await modulesStore.schema(props.module)

    if (props.id) {
        await applyItem(await api.item(props.module, props.id))
        await loadRevisions(props.id)
    } else {
        values.value = Object.fromEntries(schema.value.fields.map((field) => [field.name, initialValue(field)]))
        itemTitle.value = 'Nouveau'
    }

    isLoaded.value = true
    watch(values, () => (isDirty.value = true), { deep: true })
})
</script>

<template>
    <main class="page">
        <header class="top">
            <div class="crumbs">
                <RouterLink :to="{ name: 'module.index', params: { module } }">{{ schema?.title }}</RouterLink>
                /
                <strong>{{ itemTitle }}</strong>
            </div>

            <div v-if="config.locales.length > 1" class="flex h-8 gap-0.5 rounded-md bg-accent-soft p-[3px]" role="group" aria-label="Langue de saisie">
                <button
                    v-for="locale in config.locales"
                    :key="locale"
                    type="button"
                    :class="localeButtonClass"
                    :aria-pressed="editor.locale === locale"
                    @click="editor.locale = locale"
                >
                    {{ locale.toUpperCase() }}
                    <i v-if="localesWithGaps.includes(locale)" class="size-1.5 rounded-[50%] bg-warning" title="Traduction incomplète" />
                </button>
            </div>

            <template v-if="isLoaded">
                <span class="flex items-center gap-1.5 px-2 text-sm before:size-1.5 before:rounded-[50%]" :class="saveStatusClass">{{ saveStatus.label }}</span>

                <UiButton v-if="schema?.previewable" @click="isPreviewOpen = true"><UiIcon name="eye" :size="14" /> Aperçu</UiButton>

                <template v-if="id">
                    <UiIconButton label="Dupliquer" :disabled="isSaving" @click="duplicate"><UiIcon name="copy" :size="14" /></UiIconButton>
                    <UiIconButton v-if="schema?.trashable" label="Mettre à la corbeille" :disabled="isSaving || isReadOnly" @click="trash"><UiIcon name="trash" :size="14" /></UiIconButton>
                </template>

                <template v-if="isPublishable">
                    <UiButton :disabled="isSaving || isReadOnly" @click="save">Enregistrer</UiButton>
                    <UiButton variant="primary" :disabled="isSaving || isReadOnly || !hasSomethingToPublish" @click="publish">
                        <UiIcon name="send" :size="14" /> {{ publishLabel }}
                    </UiButton>
                </template>
                <UiButton v-else variant="primary" :disabled="isSaving || isReadOnly" @click="save">
                    {{ isSaving ? 'Enregistrement…' : 'Enregistrer' }}
                </UiButton>
            </template>
        </header>

        <div v-if="isLoaded && isReadOnly" class="flex flex-wrap items-center gap-3 border-b border-line bg-warning-soft px-7 py-2.5 text-sm text-ink max-md:px-3" role="status">
            <UiIcon name="lock" :size="14" class="text-warning" />
            <p v-if="editLock.hasLost.value" class="flex-1">
                <strong>{{ editLock.lock.value?.userName ?? 'Quelqu’un' }}</strong> a pris la main sur ce contenu. Vos modifications non enregistrées ne peuvent plus l’être.
            </p>
            <p v-else class="flex-1">
                <strong>{{ editLock.lock.value?.userName ?? 'Quelqu’un' }}</strong> modifie ce contenu
                <template v-if="editLock.lock.value">depuis {{ formatRelative(editLock.lock.value.lockedAt).replace('il y a ', '') }}</template>. Vous le voyez en lecture seule.
            </p>
            <UiButton v-if="editLock.hasLost.value" @click="reloadPage">Recharger</UiButton>
            <UiButton @click="takeOverLock">{{ editLock.hasLost.value ? 'Reprendre la main' : 'Prendre la main' }}</UiButton>
        </div>

        <div v-if="!isLoaded" :class="canvasClass" aria-busy="true">
            <div :class="[docClass, 'flex flex-col gap-3.5 pl-49']" aria-label="Chargement du contenu">
                <span :class="[skeletonClass, 'mb-1.5 h-8.5 w-[60%]']" />
                <span :class="[skeletonClass, 'mb-3.5 h-4.5 w-[78%]']" />
                <span :class="[skeletonClass, 'h-3.5 w-[92%]']" />
                <span :class="[skeletonClass, 'h-3.5 w-[55%]']" />
            </div>
        </div>

        <div v-else-if="schema" :class="{ [inspectorLayoutClass]: publication !== null }">
            <div :class="canvasClass">
                <div :class="[docClass, 'pl-5', { 'opacity-70': isReadOnly }]" :inert="isReadOnly || undefined">
                    <FieldRenderer
                        v-for="(field, index) in schema.fields"
                        :key="field.name"
                        v-model="values[field.name]"
                        :field="field"
                        :path="field.name"
                        :variant="index === 0 && field.type === 'text' ? 'title' : index === 1 && field.type === 'text' ? 'lead' : 'default'"
                    />
                </div>
            </div>

            <PublicationPanel
                v-if="publication !== null"
                v-model:publish-start-at="publishStartAt"
                v-model:publish-end-at="publishEndAt"
                :publication="publication"
                :revisions="revisions"
                :is-busy="isSaving || isReadOnly"
                @unpublish="unpublish"
                @discard-draft="discardDraft"
                @restore="restoreRevision"
            />
        </div>

        <PreviewPanel v-if="isPreviewOpen" :module="module" :id="id" :values="values" :locale="editor.locale" @close="isPreviewOpen = false" />
    </main>
</template>
