import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

/**
 * Builds the admin front into public/, published by the host app to public/vendor/adminphp.
 */
export default defineConfig({
    plugins: [vue(), tailwindcss()],
    base: '/vendor/adminphp/',
    publicDir: false,
    build: {
        outDir: 'public',
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: 'resources/js/app.ts',
        },
    },
})
