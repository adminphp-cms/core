import { createRouter, createWebHistory } from 'vue-router'
import { handleUnauthorized } from './api'
import { config } from './config'
import Home from './pages/Home.vue'
import Login from './pages/Login.vue'
import ModuleForm from './pages/ModuleForm.vue'
import ModuleIndex from './pages/ModuleIndex.vue'
import { useSessionStore } from './stores/session'

export const router = createRouter({
    history: createWebHistory(config.basePath),
    routes: [
        { path: '/', name: 'home', component: Home },
        { path: '/login', name: 'login', component: Login, meta: { isPublic: true } },
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

    if (to.meta.isPublic) {
        return session.user !== null ? { name: 'home' } : true
    }

    return session.user !== null ? true : { name: 'login', query: { redirect: to.fullPath } }
})

handleUnauthorized(() => {
    useSessionStore().forget()

    const current = router.currentRoute.value

    if (!current.meta.isPublic) {
        router.replace({ name: 'login', query: { redirect: current.fullPath } })
    }
})
