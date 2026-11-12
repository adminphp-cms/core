import { inject, provide, ref, type InjectionKey, type Ref } from 'vue'
import type { FormValues } from '../types'

export interface FormContext {
    /** Every value of the form being edited, for fields that depend on another one. */
    values: Ref<FormValues>
    /** True until the item is saved for the first time. */
    isNew: Readonly<Ref<boolean>>
}

const formContextKey: InjectionKey<FormContext> = Symbol('formContext')

export function provideFormContext(context: FormContext): void {
    provide(formContextKey, context)
}

export function useFormContext(): FormContext {
    return inject(formContextKey, { values: ref({}), isNew: ref(false) })
}
