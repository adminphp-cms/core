import { ref, watch } from 'vue'

export type ThemePreference = 'auto' | 'light' | 'dark'

const storageKey = 'adminphp.theme'

function storedPreference(): ThemePreference {
    try {
        const value = localStorage.getItem(storageKey)

        return value === 'light' || value === 'dark' ? value : 'auto'
    } catch {
        return 'auto'
    }
}

const preference = ref<ThemePreference>(storedPreference())

function apply(value: ThemePreference): void {
    if (value === 'auto') {
        delete document.documentElement.dataset.theme
    } else {
        document.documentElement.dataset.theme = value
    }
}

apply(preference.value)

watch(preference, (value) => {
    apply(value)

    try {
        if (value === 'auto') {
            localStorage.removeItem(storageKey)
        } else {
            localStorage.setItem(storageKey, value)
        }
    } catch {
        // Private browsing can refuse storage: the choice then lasts for this visit only.
    }
})

/**
 * Light, dark, or following the system. Remembered per browser.
 */
export function useTheme() {
    return { preference }
}
