import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'SYNTImeat';

// Directiva global: cierra dropdowns al hacer clic fuera
const vClickOutside = {
    mounted(el, binding) {
        el._clickOutsideHandler = (e) => {
            if (!el.contains(e.target)) binding.value(e)
        }
        document.addEventListener('mousedown', el._clickOutsideHandler)
    },
    unmounted(el) {
        document.removeEventListener('mousedown', el._clickOutsideHandler)
    },
}

createInertiaApp({
    title: (title) => `${title} · SYNTImeat`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .directive('click-outside', vClickOutside)
            .mount(el);
    },
    progress: {
        color: '#B91C1C',
    },
});
