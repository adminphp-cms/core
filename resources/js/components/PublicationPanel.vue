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
</script>

<template>
    <aside class="inspector" aria-label="Publication">
        <UiCard title="Publication">
            <template #actions>
                <UiBadge :tone="visibilityBadges[publication.visibility].tone" dot>{{ visibilityBadges[publication.visibility].label }}</UiBadge>
            </template>

            <p v-if="publication.hasDraft" class="inspector-note">
                Des modifications enregistrées attendent d’être publiées<template v-if="publication.draftUpdatedAt">
                    ({{ formatDate(publication.draftUpdatedAt) }}<template v-if="publication.draftUpdatedBy">, {{ publication.draftUpdatedBy }}</template>)</template
                >.
            </p>
            <p v-else-if="publication.publishedAt" class="inspector-note">Publié pour la première fois le {{ formatDate(publication.publishedAt) }}.</p>

            <label class="inspector-field">
                <span>Visible à partir du</span>
                <input v-model="publishStartAt" type="datetime-local" :disabled="isBusy" />
            </label>

            <label class="inspector-field">
                <span>Jusqu’au</span>
                <input v-model="publishEndAt" type="datetime-local" :disabled="isBusy" />
            </label>

            <p class="inspector-hint">Les dates s’appliquent à la prochaine publication. Vides : en ligne dès la publication, sans fin.</p>

            <div class="inspector-actions">
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
                <UiIcon name="history" :size="14" class="inspector-icon" />
            </template>

            <p v-if="revisions.length === 0" class="inspector-note">Aucune version publiée pour l’instant.</p>

            <ol v-else class="revisions">
                <li v-for="(revision, index) in revisions" :key="revision.id">
                    <span>
                        {{ formatDate(revision.createdAt) }}
                        <small>
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
