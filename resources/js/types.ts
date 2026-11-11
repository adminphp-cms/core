export interface AdminConfig {
    basePath: string
    apiUrl: string
    locales: string[]
    defaultLocale: string
}

export interface BaseFieldSchema {
    name: string
    label: string
    translatable: boolean
    required: boolean
}

export interface TextFieldSchema extends BaseFieldSchema {
    type: 'text'
    multiline: boolean
    maxLength: number
}

export interface RichTextFieldSchema extends BaseFieldSchema {
    type: 'rich-text'
}

export interface BlockSchema {
    type: string
    title: string
    version: number
    fields: FieldSchema[]
}

export interface BlocksFieldSchema extends BaseFieldSchema {
    type: 'blocks'
    blocks: BlockSchema[]
}

export type FieldSchema = TextFieldSchema | RichTextFieldSchema | BlocksFieldSchema

export interface ModuleSchema {
    name: string
    title: string
    publishable: boolean
    fields: FieldSchema[]
}

export interface ModuleSummary {
    name: string
    title: string
    count: number
}

export type Visibility = 'draft' | 'scheduled' | 'live' | 'expired'

export interface ItemSummary {
    id: string
    title: string
    updatedAt: string | null
    visibility: Visibility | null
    hasDraft: boolean
}

export interface BlockValue {
    id: string
    type: string
    content: Record<string, unknown>
}

export type FormValues = Record<string, unknown>

export interface Publication {
    status: 'draft' | 'published'
    visibility: Visibility
    publishedAt: string | null
    publishStartAt: string | null
    publishEndAt: string | null
    hasDraft: boolean
    draftUpdatedAt: string | null
}

export interface Item {
    id: string
    title: string
    values: FormValues
    /** Null when the module is not publishable. */
    publication: Publication | null
}

export interface PublishOptions {
    values?: FormValues
    publishStartAt: string | null
    publishEndAt: string | null
}

export interface RevisionSummary {
    id: string
    createdAt: string
}

export type ValidationErrors = Record<string, string[]>
