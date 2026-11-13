import { config } from './config'
import type { ActivityEntry, AdminUser, Asset, FormValues, BulkAction, EditLock, Item, ItemPlacement, ItemSummary, ListingMeta, ListingQuery, ContentReference, ReferencedContent, ManagedUser, ModuleSchema, ModuleSummary, PublishOptions, RevisionSummary, SettingsData, UserValues, ValidationErrors } from './types'

export class ApiError extends Error {
    constructor(
        public readonly status: number,
        message: string,
        public readonly errors: ValidationErrors = {},
    ) {
        super(message)
    }
}

let csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

let onUnauthorized: () => void = () => {}

/**
 * Called when the session has expired, so the admin can send the user back to the login page.
 */
export function handleUnauthorized(callback: () => void): void {
    onUnauthorized = callback
}

/**
 * `keepalive` lets a request finish while the page is closing (releasing an edit lock).
 */
async function request<T>(method: string, path: string, body?: unknown, options: { keepalive?: boolean } = {}): Promise<T> {
    const isForm = body instanceof FormData

    const response = await fetch(`${config.apiUrl}/${path}`, {
        method,
        headers: {
            Accept: 'application/json',
            // A FormData body sets its own multipart boundary.
            ...(isForm ? {} : { 'Content-Type': 'application/json' }),
            'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
        keepalive: options.keepalive,
        body: body === undefined ? undefined : isForm ? body : JSON.stringify(body),
    })

    const payload = await response.json().catch(() => ({}))

    if (typeof payload.csrfToken === 'string') {
        // The session is regenerated on login and logout, which renews the token.
        csrfToken = payload.csrfToken
    }

    if (response.status === 401 && path !== 'session') {
        onUnauthorized()
    }

    if (!response.ok) {
        throw new ApiError(response.status, payload.message ?? response.statusText, payload.errors ?? {})
    }

    return payload as T
}

export const api = {
    currentUser: () =>
        request<{ data: AdminUser }>('GET', 'session')
            .then((response) => response.data)
            .catch((error: unknown) => {
                if (error instanceof ApiError && error.status === 401) {
                    return null
                }

                throw error
            }),

    login: (credentials: { email: string; password: string; remember: boolean }) =>
        request<{ data: AdminUser }>('POST', 'session', credentials).then((response) => response.data),

    logout: () => request<unknown>('DELETE', 'session'),

    forgotPassword: (email: string) => request<{ message: string }>('POST', 'password/forgot', { email }).then((response) => response.message),

    /** Sets the password from a reset or invitation link, which also logs the user in. */
    resetPassword: (values: { token: string; email: string; password: string; password_confirmation: string; invitation: boolean }) =>
        request<{ data: AdminUser }>('POST', 'password/reset', values).then((response) => response.data),

    updateAccount: (values: { name: string; email: string; current_password?: string }) =>
        request<{ data: AdminUser }>('PUT', 'account', values).then((response) => response.data),

    updatePassword: (values: { current_password: string; password: string; password_confirmation: string }) =>
        request<{ data: AdminUser }>('PUT', 'account/password', values).then((response) => response.data),

    settings: () => request<{ data: SettingsData }>('GET', 'settings').then((response) => response.data),

    updateSettings: (values: FormValues) => request<{ data: SettingsData }>('PUT', 'settings', values).then((response) => response.data),

    users: () => request<{ data: ManagedUser[] }>('GET', 'users').then((response) => response.data),

    inviteUser: (values: UserValues) => request<{ data: ManagedUser }>('POST', 'users', values).then((response) => response.data),

    updateUser: (id: string, values: UserValues) => request<{ data: ManagedUser }>('PUT', `users/${id}`, values).then((response) => response.data),

    resendInvitation: (id: string) => request<{ data: ManagedUser }>('POST', `users/${id}/invitation`).then((response) => response.data),

    deactivateUser: (id: string) => request<{ data: ManagedUser }>('POST', `users/${id}/deactivate`).then((response) => response.data),

    activateUser: (id: string) => request<{ data: ManagedUser }>('POST', `users/${id}/activate`).then((response) => response.data),

    assets: (search = '') =>
        request<{ data: Asset[]; meta: { total: number } }>('GET', `assets${search ? `?search=${encodeURIComponent(search)}` : ''}`),

    asset: (id: string) => request<{ data: Asset }>('GET', `assets/${id}`).then((response) => response.data),

    uploadAsset: (file: File) => {
        const body = new FormData()
        body.append('file', file)

        return request<{ data: Asset }>('POST', 'assets', body).then((response) => response.data)
    },

    activity: () => request<{ data: ActivityEntry[] }>('GET', 'activity').then((response) => response.data),

    modules: () => request<{ data: ModuleSummary[] }>('GET', 'modules').then((response) => response.data),

    module: (name: string) => request<{ data: ModuleSchema }>('GET', `modules/${name}`).then((response) => response.data),

    items: (module: string, search = '') =>
        request<{ data: ItemSummary[] }>('GET', `modules/${module}/items${search ? `?search=${encodeURIComponent(search)}` : ''}`).then((response) => response.data),

    /** Names the contents chosen in Browser fields, leaving out the ones that no longer exist. */
    references: (references: ContentReference[]) =>
        request<{ data: ReferencedContent[] }>('GET', `references?${references.map((reference) => `items[]=${encodeURIComponent(`${reference.type}:${reference.id}`)}`).join('&')}`).then(
            (response) => response.data,
        ),

    /** One page of the admin listing of a module. */
    listItems: (module: string, query: ListingQuery) => {
        const params = new URLSearchParams()

        const entries: [string, string | null][] = [
            ['search', query.search.trim() || null],
            ['view', query.view === 'all' ? null : query.view],
            ['sort', query.sort],
            ['page', query.page > 1 ? String(query.page) : null],
            ...Object.entries(query.filters).map(([name, value]): [string, string | null] => [`filters[${name}]`, value || null]),
        ]

        entries.forEach(([name, value]) => {
            if (value !== null) {
                params.set(name, value)
            }
        })

        return request<{ data: ItemSummary[]; meta: ListingMeta }>('GET', `modules/${module}/items${params.size ? `?${params}` : ''}`)
    },

    /** Renders the site's page of an item with the form values, saved or not (no id: a new item). */
    previewItem: (module: string, id: string | undefined, values: FormValues, locale: string) =>
        request<{ data: { html: string } }>('POST', id ? `modules/${module}/items/${id}/preview` : `modules/${module}/preview`, { values, locale }).then((response) => response.data.html),

    /** Takes or renews the edit lock of an item; with `takeOver`, takes it from whoever holds it. */
    lockItem: (module: string, id: string, takeOver = false) =>
        request<{ data: EditLock }>('POST', `modules/${module}/items/${id}/lock`, { takeOver }).then((response) => response.data),

    /** Releases the edit lock, even while the page is closing. */
    unlockItem: (module: string, id: string) => request<unknown>('DELETE', `modules/${module}/items/${id}/lock`, undefined, { keepalive: true }),

    /** Moves an item to the trash. */
    trashItem: (module: string, id: string) => request<unknown>('DELETE', `modules/${module}/items/${id}`),

    duplicateItem: (module: string, id: string) => request<{ data: Item }>('POST', `modules/${module}/items/${id}/duplicate`).then((response) => response.data),

    /** Applies a listing action to several items; the ones that could not take it are listed in `failed`. */
    bulkItems: (module: string, action: BulkAction, ids: string[]) =>
        request<{ data: { done: number; failed: string[] } }>('POST', `modules/${module}/items/bulk`, { action, ids }).then((response) => response.data),

    /** Saves the order of a sortable module: every item in its new order, parents before their children. */
    reorderItems: (module: string, items: ItemPlacement[]) => request<unknown>('PUT', `modules/${module}/items/order`, { items }),

    item: (module: string, id: string) =>
        request<{ data: Item }>('GET', `modules/${module}/items/${id}`).then((response) => response.data),

    createItem: (module: string, values: FormValues) =>
        request<{ data: Item }>('POST', `modules/${module}/items`, values).then((response) => response.data),

    updateItem: (module: string, id: string, values: FormValues) =>
        request<{ data: Item }>('PUT', `modules/${module}/items/${id}`, values).then((response) => response.data),

    publishItem: (module: string, id: string, options: PublishOptions) =>
        request<{ data: Item }>('POST', `modules/${module}/items/${id}/publish`, options).then((response) => response.data),

    unpublishItem: (module: string, id: string) =>
        request<{ data: Item }>('POST', `modules/${module}/items/${id}/unpublish`).then((response) => response.data),

    discardDraft: (module: string, id: string) =>
        request<{ data: Item }>('DELETE', `modules/${module}/items/${id}/draft`).then((response) => response.data),

    revisions: (module: string, id: string) =>
        request<{ data: RevisionSummary[] }>('GET', `modules/${module}/items/${id}/revisions`).then((response) => response.data),

    restoreRevision: (module: string, id: string, revisionId: string) =>
        request<{ data: Item }>('POST', `modules/${module}/items/${id}/revisions/${revisionId}/restore`).then((response) => response.data),
}
