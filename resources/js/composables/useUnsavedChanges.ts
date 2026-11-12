import { onBeforeUnmount, onMounted, type Ref } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'

const leaveMessage = 'Vos modifications ne sont pas enregistrées. Quitter quand même ?'

/**
 * Asks before leaving a page that has unsaved changes, whether by closing the tab,
 * reloading or following a link in the admin.
 */
export function useUnsavedChanges(isDirty: Ref<boolean>): void {
    const warnBeforeUnload = (event: BeforeUnloadEvent): void => {
        if (isDirty.value) {
            event.preventDefault()
        }
    }

    onMounted(() => window.addEventListener('beforeunload', warnBeforeUnload))
    onBeforeUnmount(() => window.removeEventListener('beforeunload', warnBeforeUnload))

    onBeforeRouteLeave(() => !isDirty.value || window.confirm(leaveMessage))
}

/**
 * Runs the callback on Cmd+S (Mac) or Ctrl+S, instead of the browser's "save page".
 */
export function useSaveShortcut(save: () => unknown): void {
    const onKeydown = (event: KeyboardEvent): void => {
        if ((event.metaKey || event.ctrlKey) && !event.altKey && event.key.toLowerCase() === 's') {
            event.preventDefault()
            save()
        }
    }

    onMounted(() => window.addEventListener('keydown', onKeydown))
    onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
}
