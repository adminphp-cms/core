import { config } from './config'
import type { FormValues, Item, ItemSummary, ModuleSchema, ModuleSummary, PublishOptions, RevisionSummary, ValidationErrors } from './types'

export class ApiError extends Error {
    constructor(
        public readonly status: number,
        message: string,
        public readonly errors: ValidationErrors = {},
    ) {
        super(message)
    }
}

async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

    const response = await fetch(`${config.apiUrl}/${path}`, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
        body: body === undefined ? undefined : JSON.stringify(body),
    })

    const payload = await response.json().catch(() => ({}))

    if (!response.ok) {
        throw new ApiError(response.status, payload.message ?? response.statusText, payload.errors ?? {})
    }

    return payload as T
}

export const api = {
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
