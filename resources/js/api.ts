import { config } from './config'
import type { ActivityEntry, AdminUser, Asset, FormValues, Item, ItemSummary, ManagedUser, ModuleSchema, ModuleSummary, PublishOptions, RevisionSummary, UserValues, ValidationErrors } from './types'

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

async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
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

    items: (module: string) =>
        request<{ data: ItemSummary[] }>('GET', `modules/${module}/items`).then((response) => response.data),

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
