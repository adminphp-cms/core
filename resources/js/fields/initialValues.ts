import type { FieldSchema } from '../types'

/**
 * Value of a field in a new content: its default when it has one, else its empty value.
 */
export function initialValue(field: FieldSchema): unknown {
    if (field.default !== null && field.default !== undefined) {
        return structuredClone(field.default)
    }

    if (field.type === 'blocks' || (field.type === 'select' && field.multiple)) {
        return []
    }

    if (field.type === 'toggle') {
        return false
    }

    return field.translatable ? {} : null
}

/**
 * Values of a new content (item, block, repeater row): only the fields that have a default,
 * the others stay absent as before.
 */
export function defaultValues(fields: FieldSchema[]): Record<string, unknown> {
    return Object.fromEntries(
        fields.filter((field) => field.default !== null && field.default !== undefined).map((field) => [field.name, structuredClone(field.default)]),
    )
}
