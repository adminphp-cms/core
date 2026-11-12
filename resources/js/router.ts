import { createRouter, createWebHistory } from 'vue-router'
import { handleUnauthorized } from './api'
import { config } from './config'
import Home from './pages/Home.vue'
import Account from './pages/Account.vue'
import ChoosePassword from './pages/ChoosePassword.vue'
import ForgotPassword from './pages/ForgotPassword.vue'
import Login from './pages/Login.vue'
import MediaIndex from './pages/MediaIndex.vue'
import ModuleForm from './pages/ModuleForm.vue'
import ModuleIndex from './pages/ModuleIndex.vue'
import SettingsIndex from './pages/SettingsIndex.vue'
import UsersIndex from './pages/UsersIndex.vue'
import { useSessionStore } from './stores/session'

export const router = createRouter({
    history: createWebHistory(config.basePath),
    routes: [
        { path: '/', name: 'home', component: Home },
        { path: '/login', name: 'login', component: Login, meta: { isPublic: true } },
        { path: '/forgot-password', name: 'password.forgot', component: ForgotPassword, meta: { isPublic: true } },
        { path: '/reset-password/:token', name: 'password.reset', component: ChoosePassword, props: { isInvitation: false }, meta: { isPublic: true, logsOut: true } },
        { path: '/invitation/:token', name: 'invitation', component: ChoosePassword, props: { isInvitation: true }, meta: { isPublic: true, logsOut: true } },
        { path: '/media', name: 'media', component: MediaIndex },
        // Before /:module, so they are never read as module names.
        { path: '/account', name: 'account', component: Account },
        { path: '/users', name: 'users', component: UsersIndex, meta: { requiresUserManagement: true } },
        { path: '/settings', name: 'settings', component: SettingsIndex, meta: { requiresSettingsManagement: true } },
        { path: '/:module', name: 'module.index', component: ModuleIndex, props: true },
        { path: '/:module/create', name: 'module.create', component: ModuleForm, props: true },
        { path: '/:module/:id', name: 'module.edit', component: ModuleForm, props: true },
    ],
})

router.beforeEach(async (to) => {
    const session = useSessionStore()

    if (!session.isLoaded) {
        await session.load()
    }

    if (to.meta.logsOut && session.user !== null) {
        // A password or invitation link is for whoever received it, not for the user logged in here.
        await session.logout()
    }

    if (to.meta.isPublic) {
        return session.user !== null ? { name: 'home' } : true
    }

    if (session.user === null) {
        return { name: 'login', query: { redirect: to.fullPath } }
    }

    if (to.meta.requiresUserManagement && !session.user.canManageUsers) {
        return { name: 'home' }
    }

    return to.meta.requiresSettingsManagement && !(session.user.canManageSettings && config.hasSettings) ? { name: 'home' } : true
})

handleUnauthorized(() => {
    useSessionStore().forget()

    const current = router.currentRoute.value

    if (!current.meta.isPublic) {
        router.replace({ name: 'login', query: { redirect: current.fullPath } })
    }
})
