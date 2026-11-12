import { readonly, ref } from 'vue'

export type ToastTone = 'success' | 'error'

const state = ref<{ message: string; tone: ToastTone; isVisible: boolean }>({ message: '', tone: 'success', isVisible: false })
let hideTimer: ReturnType<typeof setTimeout> | undefined

function show(message: string, tone: ToastTone): void {
    clearTimeout(hideTimer)
    state.value = { message, tone, isVisible: true }
    // Errors stay longer: they usually ask the user to do something.
    hideTimer = setTimeout(() => (state.value = { ...state.value, isVisible: false }), tone === 'error' ? 5000 : 2500)
}

/**
 * The admin's single notification line, shown at the bottom of the screen by UiToaster.
 * The message stays in place while it fades out.
 */
export function useToast() {
    return {
        state: readonly(state),
        success: (message: string) => show(message, 'success'),
        error: (message: string) => show(message, 'error'),
    }
}
