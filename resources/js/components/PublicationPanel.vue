<script setup lang="ts">
import { formatDateTime, visibilityBadges } from '../format'
import type { Publication, RevisionSummary } from '../types'
import UiBadge from './ui/UiBadge.vue'
import UiCard from './ui/UiCard.vue'
import UiIcon from './ui/UiIcon.vue'

defineProps<{
    publication: Publication
    revisions: RevisionSummary[]
    isBusy: boolean
}>()

const publishStartAt = defineModel<string>('publishStartAt', { required: true })
const publishEndAt = defineModel<string>('publishEndAt', { required: true })

const emit = defineEmits<{
    unpublish: []
    discardDraft: []
    restore: [revisionId: string]
}>()

const formatDate = formatDateTime

/** Sticky column beside the canvas, back in the flow under it on narrow screens. Cards stack their content. */
const inspectorClass = [
    'sticky top-(--header-height) flex h-[calc(100vh-var(--header-height))] flex-col gap-4 overflow-y-auto py-8 pr-7 text-sm',
    '[&>section>div]:flex [&>section>div]:flex-col [&>section>div]:gap-3',
    'max-xl:static max-xl:h-auto max-xl:px-7 max-xl:pt-0 max-xl:pb-12 max-md:px-3',
]
const dateInputClass = [
    'h-8.5 rounded-md border border-ring-control bg-surface px-2.5 font-sans text-sm leading-none font-normal text-ink',
    'focus:border-brand focus:outline-3 focus:outline-offset-0 focus:outline-brand-soft',
]
</script>

<template>
    <aside :class="inspectorClass" aria-label="Publication">
        <UiCard title="Publication">
            <template #actions>
                <UiBadge :tone="visibilityBadges[publication.visibility].tone" dot>{{ visibilityBadges[publication.visibility].label }}</UiBadge>
            </template>

            <p v-if="publication.hasDraft" class="text-muted">
                Des modifications enregistrées attendent d’être publiées<template v-if="publication.draftUpdatedAt">
                    ({{ formatDate(publication.draftUpdatedAt) }}<template v-if="publication.draftUpdatedBy">, {{ publication.draftUpdatedBy }}</template>)</template
                >.
            </p>
            <p v-else-if="publication.publishedAt" class="text-muted">Publié pour la première fois le {{ formatDate(publication.publishedAt) }}.</p>

            <label class="flex flex-col gap-1.5 font-medium text-muted">
                <span>Visible à partir du</span>
                <input v-model="publishStartAt" type="datetime-local" :class="dateInputClass" :disabled="isBusy" />
            </label>

            <label class="flex flex-col gap-1.5 font-medium text-muted">
                <span>Jusqu’au</span>
                <input v-model="publishEndAt" type="datetime-local" :class="dateInputClass" :disabled="isBusy" />
            </label>

            <p class="text-xs text-faint">Les dates s’appliquent à la prochaine publication. Vides : en ligne dès la publication, sans fin.</p>

            <div class="flex flex-col gap-2">
                <button v-if="publication.hasDraft" type="button" class="btn" :disabled="isBusy" @click="emit('discardDraft')">
                    Abandonner les modifications
                </button>
                <button v-if="publication.status === 'published'" type="button" class="btn danger" :disabled="isBusy" @click="emit('unpublish')">
                    Dépublier
                </button>
            </div>
        </UiCard>

        <UiCard title="Versions publiées">
            <template #actions>
                <UiIcon name="history" :size="14" class="text-icon" />
            </template>

            <p v-if="revisions.length === 0" class="text-muted">Aucune version publiée pour l’instant.</p>

            <ol v-else class="m-0 list-none p-0">
                <li
                    v-for="(revision, index) in revisions"
                    :key="revision.id"
                    class="flex items-baseline justify-between gap-2 border-b border-line py-2.5 tabular-nums first:pt-0 last:pb-0 last:[border-bottom:0]"
                >
                    <span>
                        {{ formatDate(revision.createdAt) }}
                        <small class="block text-xs text-faint">
                            <template v-if="revision.userName">{{ revision.userName }}</template>
                            <template v-if="revision.userName && index === 0"> · </template>
                            <template v-if="index === 0">dernière publiée</template>
                        </small>
                    </span>
                    <button type="button" class="link-button" :disabled="isBusy" @click="emit('restore', revision.id)">Restaurer</button>
                </li>
            </ol>
        </UiCard>
    </aside>
</template>
