<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api, ApiError } from '../api'
import { isFilled } from '../composables/useLocalizedValue'
import { config } from '../config'
import PublicationPanel from '../components/PublicationPanel.vue'
import UiIcon from '../components/ui/UiIcon.vue'
import FieldRenderer from '../fields/FieldRenderer.vue'
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
const toast = ref<string | null>(null)
const publication = ref<Publication | null>(null)
const revisions = ref<RevisionSummary[]>([])
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

function emptyValue(field: FieldSchema): unknown {
    if (field.type === 'blocks' || (field.type === 'select' && field.multiple)) {
        return []
    }

    if (field.type === 'toggle') {
        return false
    }

    return field.translatable ? {} : null
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

function showToast(message: string): void {
    toast.value = message
    setTimeout(() => (toast.value = null), 2000)
}

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
        showToast(successMessage)
        modulesStore.loadModules()

        return item
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            editor.errors = error.errors
            showToast(error.errors.publishEndAt?.[0] ?? 'Certains champs sont à corriger')

            return null
        }

        showToast('L’opération a échoué')
        throw error
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

async function restoreRevision(revisionId: string): Promise<void> {
    const message = isDirty.value
        ? 'Restaurer cette version ? Les modifications non enregistrées seront perdues.'
        : 'Restaurer cette version comme brouillon ?'

    if (props.id && window.confirm(message)) {
        await run(() => api.restoreRevision(props.module, props.id!, revisionId), 'Version restaurée, à publier')
    }
}

onMounted(async () => {
    editor.locale = config.defaultLocale
    editor.errors = {}

    schema.value = await modulesStore.schema(props.module)

    if (props.id) {
        await applyItem(await api.item(props.module, props.id))
        await loadRevisions(props.id)
    } else {
        values.value = Object.fromEntries(schema.value.fields.map((field) => [field.name, emptyValue(field)]))
        itemTitle.value = 'Nouveau'
    }

    isLoaded.value = true
    watch(values, () => (isDirty.value = true), { deep: true })
})
</script>

<template>
    <main class="page editor">
        <header class="top">
            <div class="crumbs">
                <RouterLink :to="{ name: 'module.index', params: { module } }">{{ schema?.title }}</RouterLink>
                /
                <strong>{{ itemTitle }}</strong>
            </div>

            <div v-if="config.locales.length > 1" class="langs" role="group" aria-label="Langue de saisie">
                <button
                    v-for="locale in config.locales"
                    :key="locale"
                    type="button"
                    :aria-pressed="editor.locale === locale"
                    @click="editor.locale = locale"
                >
                    {{ locale.toUpperCase() }}
                    <i v-if="localesWithGaps.includes(locale)" class="gap" title="Traduction incomplète" />
                </button>
            </div>

            <template v-if="isLoaded">
                <span class="status" :class="{ saved: saveStatus.isSaved }">{{ saveStatus.label }}</span>

                <template v-if="isPublishable">
                    <button type="button" class="btn" :disabled="isSaving" @click="save">Enregistrer</button>
                    <button type="button" class="btn primary" :disabled="isSaving || !hasSomethingToPublish" @click="publish">
                        <UiIcon name="send" :size="14" /> {{ publishLabel }}
                    </button>
                </template>
                <button v-else type="button" class="btn primary" :disabled="isSaving" @click="save">
                    {{ isSaving ? 'Enregistrement…' : 'Enregistrer' }}
                </button>
            </template>
        </header>

        <div v-if="!isLoaded" class="canvas" aria-busy="true">
            <div class="doc doc-skeleton" aria-label="Chargement du contenu">
                <span class="skeleton is-title" />
                <span class="skeleton is-lead" />
                <span class="skeleton" />
                <span class="skeleton is-short" />
            </div>
        </div>

        <div v-else-if="schema" class="editor-body" :class="{ 'has-inspector': publication !== null }">
            <div class="canvas">
                <div class="doc">
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
                :is-busy="isSaving"
                @unpublish="unpublish"
                @discard-draft="discardDraft"
                @restore="restoreRevision"
            />
        </div>

        <div class="toast" :class="{ on: toast }" role="status">{{ toast }}</div>
    </main>
</template>
