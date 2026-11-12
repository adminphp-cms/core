export interface AdminConfig {
    basePath: string
    apiUrl: string
    locales: string[]
    defaultLocale: string
    /** The public site, linked from the sidebar. */
    siteUrl: string
    /** Whether the application declares site settings ("Réglages"). */
    hasSettings: boolean
}

export type UserRole = 'admin' | 'editor'

export interface AdminUser {
    id: string
    name: string
    email: string
    role: UserRole
    canManageUsers: boolean
    canManageSettings: boolean
}

/** A row of the users page. */
export interface ManagedUser {
    id: string
    name: string
    email: string
    role: UserRole
    roleLabel: string
    status: 'active' | 'invited' | 'deactivated'
    lastLoginAt: string | null
    createdAt: string | null
}

export interface UserValues {
    name: string
    email: string
    role: UserRole
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

export interface SlugFieldSchema extends BaseFieldSchema {
    type: 'slug'
    maxLength: number
    /** Field the slug is made from. */
    from: string
    /** Shown before the slug, e.g. "/blog/". */
    prefix: string
}

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

export interface ImageFieldSchema extends BaseFieldSchema {
    type: 'image'
}

export interface Asset {
    id: string
    url: string
    /** The smallest copy, the original until it is made. */
    thumbnailUrl: string
    name: string
    mimeType: string
    size: number
    width: number | null
    height: number | null
    createdAt: string
}

export interface RepeaterFieldSchema extends BaseFieldSchema {
    type: 'repeater'
    fields: FieldSchema[]
    min: number | null
    max: number | null
    /** Field whose value names an item, null to number them. */
    itemLabel: string | null
    addLabel: string
}

/** An item of a repeater: its id plus the values of its fields. */
export interface RepeaterItem {
    id: string
    [field: string]: unknown
}

export interface BrowserModule {
    name: string
    title: string
    icon: string
}

export interface BrowserFieldSchema extends BaseFieldSchema {
    type: 'browser'
    modules: BrowserModule[]
    max: number | null
}

/** A content chosen in a Browser field: its module name and its id. */
export interface ContentReference {
    type: string
    id: string
}

/** A chosen content, named for the admin. */
export interface ReferencedContent extends ContentReference {
    title: string
    moduleTitle: string
    icon: string
    visibility: Visibility | null
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
    | SlugFieldSchema
    | RichTextFieldSchema
    | SelectFieldSchema
    | ToggleFieldSchema
    | DateFieldSchema
    | ImageFieldSchema
    | RepeaterFieldSchema
    | BrowserFieldSchema
    | BlocksFieldSchema

export interface ModuleSchema {
    name: string
    title: string
    description: string
    icon: string
    publishable: boolean
    /** Items are ordered by hand, by drag and drop in the listing. */
    sortable: boolean
    /** How many levels items can be nested under others (0: a flat list). */
    nestingDepth: number
    fields: FieldSchema[]
}

export interface SettingsSchema {
    title: string
    description: string
    fields: FieldSchema[]
}

export interface SettingsData {
    schema: SettingsSchema
    values: FormValues
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
    /** Parent item in a nested module, null at the top or in a flat module. */
    parentId: string | null
}

/** An item at its new place, as saved after a drag and drop. */
export interface ItemPlacement {
    id: string
    parentId: string | null
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
