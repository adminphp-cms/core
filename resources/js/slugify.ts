/**
 * Mirrors Laravel's Str::slug so the address shown while typing is the one saved:
 * accents and ligatures transliterated, "@" spelled "at", other punctuation
 * dropped, spaces and underscores turned into single dashes.
 */
export function slugify(text: string, maxLength = 255): string {
    return text
        .replace(/œ/g, 'oe')
        .replace(/Œ/g, 'OE')
        .replace(/æ/g, 'ae')
        .replace(/Æ/g, 'AE')
        .replace(/ß/g, 'ss')
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/_/g, '-')
        .replace(/@/g, '-at-')
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/[\s-]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, maxLength)
}
