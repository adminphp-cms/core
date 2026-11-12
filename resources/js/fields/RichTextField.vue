<script setup lang="ts">
import { Placeholder } from '@tiptap/extensions'
import StarterKit from '@tiptap/starter-kit'
import { EditorContent, useEditor, type JSONContent } from '@tiptap/vue-3'
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import UiButton from '../components/ui/UiButton.vue'
import UiIcon from '../components/ui/UiIcon.vue'
import { useLocalizedValue } from '../composables/useLocalizedValue'
import type { RichTextFieldSchema, RichTextTool } from '../types'

const props = defineProps<{
    field: RichTextFieldSchema
    modelValue: unknown
    autofocus?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: unknown] }>()

const { value, isMissingTranslation } = useLocalizedValue<JSONContent>(props, emit)

function has(tool: RichTextTool): boolean {
    return props.field.tools.includes(tool)
}

/**
 * Extensions of tools left out of the toolbar are disabled too,
 * so shortcuts and pasted content cannot bring their formatting in.
 */
const editor = useEditor({
    extensions: [
        StarterKit.configure({
            heading: has('heading') ? { levels: [2, 3] } : false,
            bold: has('bold') ? {} : false,
            italic: has('italic') ? {} : false,
            underline: has('underline') ? {} : false,
            strike: has('strike') ? {} : false,
            code: has('code') ? {} : false,
            codeBlock: false,
            bulletList: has('bulletList') ? {} : false,
            orderedList: has('orderedList') ? {} : false,
            listItem: has('bulletList') || has('orderedList') ? {} : false,
            listKeymap: has('bulletList') || has('orderedList') ? {} : false,
            blockquote: has('blockquote') ? {} : false,
            horizontalRule: has('horizontalRule') ? {} : false,
            link: has('link') ? { openOnClick: false, autolink: true, defaultProtocol: 'https' } : false,
            undoRedo: has('history') ? {} : false,
        }),
        Placeholder.configure({ placeholder: () => (isMissingTranslation.value ? 'Pas encore traduit' : 'Écrivez ici…') }),
    ],
    content: value.value ?? '',
    onUpdate: ({ editor }) => {
        value.value = editor.isEmpty ? null : editor.getJSON()
    },
})

/**
 * Reloads the document when the value changes from outside (locale switch, reload).
 */
watch(value, (newValue) => {
    if (!editor.value) {
        return
    }

    const isSameDocument = JSON.stringify(newValue ?? null) === JSON.stringify(editor.value.isEmpty ? null : editor.value.getJSON())

    if (!isSameDocument) {
        editor.value.commands.setContent(newValue ?? '', { emitUpdate: false })
    }
})

/** Link being edited in the small form under the toolbar, or null when it is closed. */
const linkUrl = ref<string | null>(null)
const linkInput = ref<HTMLInputElement | null>(null)

async function openLinkForm(): Promise<void> {
    linkUrl.value = (editor.value?.getAttributes('link').href as string | undefined) ?? ''
    await nextTick()
    linkInput.value?.focus()
}

function applyLink(): void {
    const url = (linkUrl.value ?? '').trim()

    if (url === '') {
        removeLink()

        return
    }

    const href = /^(https?:\/\/|mailto:|tel:|\/|#)/i.test(url) ? url : `https://${url}`

    editor.value?.chain().focus().extendMarkRange('link').setLink({ href }).run()
    linkUrl.value = null
}

function removeLink(): void {
    editor.value?.chain().focus().extendMarkRange('link').unsetLink().run()
    linkUrl.value = null
}

/**
 * The editor is created on mount and its DOM attached by EditorContent afterwards,
 * so autofocus waits for the next task before focusing.
 */
onMounted(() => {
    if (props.autofocus) {
        setTimeout(() => editor.value?.view.focus())
    }
})

onBeforeUnmount(() => editor.value?.destroy())

const toolButtonClass = [
    'inline-grid size-7 place-items-center rounded-[7px] border-none bg-transparent p-0 text-muted',
    'transition-[background,color] aria-pressed:bg-brand-soft aria-pressed:text-brand',
    'hover:not-disabled:not-aria-pressed:bg-nav-hover hover:not-disabled:not-aria-pressed:text-ink',
]
</script>

<template>
    <div
        class="rounded-lg border border-ring-control bg-surface transition-[border-color] focus-within:border-brand focus-within:outline-3 focus-within:outline-offset-0 focus-within:outline-brand-soft"
        :class="{ '[&_.ProseMirror_p.is-editor-empty:first-child::before]:text-warning': isMissingTranslation }"
    >
        <div v-if="editor && field.tools.length > 0" class="flex flex-wrap items-center gap-0.5 rounded-t-lg border-b border-line bg-sunken px-1.5 py-1.25" role="toolbar" :aria-label="`Mise en forme : ${field.label}`">
            <template v-if="has('history')">
                <button type="button" :class="toolButtonClass" title="Annuler" aria-label="Annuler" :disabled="!editor.can().undo()" @click="editor.chain().focus().undo().run()">
                    <UiIcon name="undo" :size="15" />
                </button>
                <button type="button" :class="toolButtonClass" title="Rétablir" aria-label="Rétablir" :disabled="!editor.can().redo()" @click="editor.chain().focus().redo().run()">
                    <UiIcon name="redo" :size="15" />
                </button>
                <span class="mx-1 h-3.5 w-px bg-line" aria-hidden="true" />
            </template>

            <template v-if="has('heading')">
                <button type="button" :class="toolButtonClass" title="Titre" aria-label="Titre" :aria-pressed="editor.isActive('heading', { level: 2 })" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">
                    <UiIcon name="heading-2" :size="15" />
                </button>
                <button type="button" :class="toolButtonClass" title="Sous-titre" aria-label="Sous-titre" :aria-pressed="editor.isActive('heading', { level: 3 })" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">
                    <UiIcon name="heading-3" :size="15" />
                </button>
                <span class="mx-1 h-3.5 w-px bg-line" aria-hidden="true" />
            </template>

            <button v-if="has('bold')" type="button" :class="toolButtonClass" title="Gras" aria-label="Gras" :aria-pressed="editor.isActive('bold')" @click="editor.chain().focus().toggleBold().run()">
                <UiIcon name="bold" :size="15" />
            </button>
            <button v-if="has('italic')" type="button" :class="toolButtonClass" title="Italique" aria-label="Italique" :aria-pressed="editor.isActive('italic')" @click="editor.chain().focus().toggleItalic().run()">
                <UiIcon name="italic" :size="15" />
            </button>
            <button v-if="has('underline')" type="button" :class="toolButtonClass" title="Souligné" aria-label="Souligné" :aria-pressed="editor.isActive('underline')" @click="editor.chain().focus().toggleUnderline().run()">
                <UiIcon name="underline" :size="15" />
            </button>
            <button v-if="has('strike')" type="button" :class="toolButtonClass" title="Barré" aria-label="Barré" :aria-pressed="editor.isActive('strike')" @click="editor.chain().focus().toggleStrike().run()">
                <UiIcon name="strikethrough" :size="15" />
            </button>
            <button v-if="has('code')" type="button" :class="toolButtonClass" title="Code" aria-label="Code" :aria-pressed="editor.isActive('code')" @click="editor.chain().focus().toggleCode().run()">
                <UiIcon name="code" :size="15" />
            </button>

            <span v-if="has('bulletList') || has('orderedList') || has('blockquote')" class="mx-1 h-3.5 w-px bg-line" aria-hidden="true" />

            <button v-if="has('bulletList')" type="button" :class="toolButtonClass" title="Liste à puces" aria-label="Liste à puces" :aria-pressed="editor.isActive('bulletList')" @click="editor.chain().focus().toggleBulletList().run()">
                <UiIcon name="list" :size="15" />
            </button>
            <button v-if="has('orderedList')" type="button" :class="toolButtonClass" title="Liste numérotée" aria-label="Liste numérotée" :aria-pressed="editor.isActive('orderedList')" @click="editor.chain().focus().toggleOrderedList().run()">
                <UiIcon name="list-ordered" :size="15" />
            </button>
            <button v-if="has('blockquote')" type="button" :class="toolButtonClass" title="Citation" aria-label="Citation" :aria-pressed="editor.isActive('blockquote')" @click="editor.chain().focus().toggleBlockquote().run()">
                <UiIcon name="quote" :size="15" />
            </button>

            <span v-if="has('link') || has('horizontalRule')" class="mx-1 h-3.5 w-px bg-line" aria-hidden="true" />

            <button v-if="has('link')" type="button" :class="toolButtonClass" title="Lien" aria-label="Lien" :aria-pressed="editor.isActive('link')" :aria-expanded="linkUrl !== null" @click="linkUrl === null ? openLinkForm() : (linkUrl = null)">
                <UiIcon name="link" :size="15" />
            </button>
            <button v-if="has('horizontalRule')" type="button" :class="toolButtonClass" title="Séparateur" aria-label="Séparateur" @click="editor.chain().focus().setHorizontalRule().run()">
                <UiIcon name="minus" :size="15" />
            </button>
        </div>

        <form v-if="linkUrl !== null" class="flex gap-1.5 border-b border-line p-1.5" @submit.prevent="applyLink">
            <input
                ref="linkInput"
                class="h-8 min-w-0 flex-1 rounded-md border border-ring-control bg-surface px-2.5 text-ink focus:border-brand focus:outline-3 focus:outline-offset-0 focus:outline-brand-soft max-md:text-lg"
                v-model="linkUrl"
                type="text"
                inputmode="url"
                placeholder="https://…"
                aria-label="Adresse du lien"
                @keydown.esc.prevent="linkUrl = null"
            />
            <UiButton type="submit" variant="primary">Appliquer</UiButton>
            <UiButton v-if="editor?.isActive('link')" @click="removeLink">Retirer</UiButton>
        </form>

        <EditorContent :editor="editor" class="prose" />
    </div>
</template>
