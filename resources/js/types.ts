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
    /** How many contents use the image (listing and details only). */
    usageCount?: number
    /** The contents using the image (details only). */
    usages?: AssetUsage[]
}

/**
 * A content showing an image: an item of a module, or the settings (no id).
 */
export interface AssetUsage {
    type: string
    id: string | null
    title: string
    moduleTitle: string
    icon: string
    isTrashed: boolean
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
    /** Deleted items go to a trash, from where they can be restored. */
    trashable: boolean
    /** The site can show an item with unsaved changes. */
    previewable: boolean
    listing: ListingSchema
    fields: FieldSchema[]
}

export type ListingColumnType = 'title' | 'status' | 'text' | 'boolean' | 'date' | 'datetime' | 'image'

export interface ListingColumn {
    name: string
    label: string
    type: ListingColumnType
    sortable: boolean
}

export interface ListingFilter {
    name: string
    label: string
    options: { value: string; label: string }[]
}

export interface ListingSchema {
    columns: ListingColumn[]
    filters: ListingFilter[]
    /** A column name, with a leading "-" for descending order. */
    defaultSort: string
}

/** Publication views of a publishable module, shown as tabs. */
export type ListingView = 'all' | 'published' | 'unpublished' | 'pending' | 'trash'

export interface ListingQuery {
    search: string
    filters: Record<string, string>
    view: ListingView
    /** Null for the module's default order (or the hand-made order of a sortable module). */
    sort: string | null
    page: number
}

export interface ListingMeta {
    currentPage: number
    lastPage: number
    total: number
    sort: string | null
    view: ListingView | null
    filters: Record<string, string>
    /** Items per view the module has (publication views, trash), null when it has none. */
    counts: Partial<Record<ListingView, number>> | null
    /** The whole module in its hand-made order, to drag around. */
    isTree: boolean
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
    /** Values of the listing columns, by column name. */
    cells: Record<string, unknown>
    /** Name of the other admin user editing the item right now, if any. */
    lockedBy: string | null
}

/** Who edits an item: the current user (isMine) or someone else. */
export interface EditLock {
    isMine: boolean
    userName: string | null
    lockedAt: string
}

export type BulkAction = 'publish' | 'unpublish' | 'trash' | 'restore' | 'delete'

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
