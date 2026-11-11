export interface AdminConfig {
    basePath: string
    apiUrl: string
    locales: string[]
    defaultLocale: string
}

export interface AdminUser {
    id: string
    name: string
    email: string
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

export type RichTextTool =
    | 'history'
    | 'heading'
    | 'bold'
    | 'italic'
    | 'underline'
    | 'strike'
    | 'code'
    | 'bulletList'
    | 'orderedList'
    | 'blockquote'
    | 'link'
    | 'horizontalRule'

export interface RichTextFieldSchema extends BaseFieldSchema {
    type: 'rich-text'
    tools: RichTextTool[]
}

export interface SelectOption {
    value: string | number
    label: string
}

export interface SelectFieldSchema extends BaseFieldSchema {
    type: 'select'
    multiple: boolean
    options: SelectOption[]
}

export interface ToggleFieldSchema extends BaseFieldSchema {
    type: 'toggle'
}

export interface DateFieldSchema extends BaseFieldSchema {
    type: 'date'
    withTime: boolean
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

export type FieldSchema =
    | TextFieldSchema
    | RichTextFieldSchema
    | SelectFieldSchema
    | ToggleFieldSchema
    | DateFieldSchema
    | BlocksFieldSchema

export interface ModuleSchema {
    name: string
    title: string
    description: string
    icon: string
    publishable: boolean
    fields: FieldSchema[]
}

export interface ModuleSummary {
    name: string
    title: string
    description: string
    icon: string
    count: number
    /** Null when the module is not publishable. */
    counts: { published: number; unpublished: number; pendingChanges: number } | null
}

export interface ActivityEntry {
    id: string
    action: 'saved' | 'published'
    module: string
    moduleTitle: string
    icon: string
    itemId: string
    itemTitle: string
    userName: string | null
    at: string
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
    draftUpdatedBy: string | null
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
    userName: string | null
}

export type ValidationErrors = Record<string, string[]>
