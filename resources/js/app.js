import '../css/app.css'
import './bootstrap'

import { createInertiaApp, usePage } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createApp, h } from 'vue'
import { ZiggyVue } from '../../vendor/tightenco/ziggy'
import { InertiaProgress } from '@inertiajs/progress'

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const vueApp = createApp({
            render: () => h(App, props),
        })

        vueApp.use(plugin)
        vueApp.use(ZiggyVue)

        // ✅ REACIVE GLOBAL content() HELPER
        vueApp.config.globalProperties.content = (key, fallback = '') => {
            // Access the site_content from the page props
            const content = usePage().props.site_content;
            
            // Check if the key exists and has a value (not null/undefined)
            if (content && content[key] !== undefined && content[key] !== null) {
                return content[key];
            }
            
            return fallback;
        };

        vueApp.mount(el)
    },
    progress: {
        color: '#4B5563',
    },
})

InertiaProgress.init({ showSpinner: true })
