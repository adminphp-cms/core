import type { BadgeTone } from './components/ui/UiBadge.vue'
import type { Visibility } from './types'

/** The admin interface is written in French, so its dates are too. */
const interfaceLocale = 'fr'

const dateTimeFormatter = new Intl.DateTimeFormat(interfaceLocale, { dateStyle: 'medium', timeStyle: 'short' })
const longDateFormatter = new Intl.DateTimeFormat(interfaceLocale, { weekday: 'long', day: 'numeric', month: 'long' })
const relativeFormatter = new Intl.RelativeTimeFormat(interfaceLocale, { numeric: 'auto' })

export function formatDateTime(iso: string): string {
    return dateTimeFormatter.format(new Date(iso))
}

export function formatLongDate(date: Date): string {
    const text = longDateFormatter.format(date)

    return text.charAt(0).toUpperCase() + text.slice(1)
}

/**
 * "à l’instant", "il y a 5 minutes", "hier"… then the full date after a week.
 */
export function formatRelative(iso: string, now: Date = new Date()): string {
    const seconds = Math.round((new Date(iso).getTime() - now.getTime()) / 1000)
    const absolute = Math.abs(seconds)

    if (absolute < 45) {
        return 'à l’instant'
    }

    const steps: [number, Intl.RelativeTimeFormatUnit][] = [
        [60, 'second'],
        [3600, 'minute'],
        [86400, 'hour'],
        [604800, 'day'],
    ]

    for (const [limit, unit] of steps) {
        if (absolute < limit) {
            const size = { second: 1, minute: 60, hour: 3600, day: 86400 }[unit as 'second' | 'minute' | 'hour' | 'day']

            return relativeFormatter.format(Math.round(seconds / size), unit)
        }
    }

    return formatDateTime(iso)
}

export const visibilityBadges: Record<Visibility, { label: string; tone: BadgeTone }> = {
    draft: { label: 'Hors ligne', tone: 'neutral' },
    scheduled: { label: 'Programmé', tone: 'warning' },
    live: { label: 'En ligne', tone: 'success' },
    expired: { label: 'Expiré', tone: 'danger' },
}

export function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0].toUpperCase())
        .join('')
}
