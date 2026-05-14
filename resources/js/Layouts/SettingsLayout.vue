<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()

const menu = [
    {
        section: 'NEGOCIO',
        items: [
            { label: 'Mi Negocio',  route: 'settings.general',   icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
            { label: 'Sucursales',  route: 'settings.branches',  icon: 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21' },
            { label: 'Usuarios',    route: 'settings.users',     icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
        ],
    },
    {
        section: 'OPERACIONES',
        items: [
            { label: 'Cajas',           route: 'settings.cash-registers', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z' },
            { label: 'Métodos de Pago', route: 'payment-methods.index',   icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z' },
            { label: 'Terminales POS',  route: 'settings.terminals',      icon: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z' },
            { label: 'Ticket',          route: 'settings.ticket',         icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
        ],
    },
]

function isActive(routeName) {
    try { return route().current(routeName) } catch (_) { return false }
}
function routeExists(routeName) {
    try { route(routeName); return true } catch (_) { return false }
}
</script>

<template>
    <AppLayout :title="$slots.title ? '' : 'Configuración'">
        <template #default>
            <div class="settings-root">

                <!-- ── Submenu lateral ───────────────────────────────────── -->
                <aside class="settings-sidebar">
                    <template v-for="group in menu" :key="group.section">
                        <p class="settings-sidebar__section">{{ group.section }}</p>
                        <template v-for="item in group.items" :key="item.route">
                            <Link
                                v-if="routeExists(item.route)"
                                :href="route(item.route)"
                                :class="['settings-nav-item', { 'settings-nav-item--active': isActive(item.route) }]"
                            >
                                <svg class="settings-nav-item__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                                </svg>
                                {{ item.label }}
                            </Link>
                        </template>
                    </template>
                </aside>

                <!-- ── Contenido ─────────────────────────────────────────── -->
                <div class="settings-content">
                    <slot />
                </div>

            </div>
        </template>
    </AppLayout>
</template>

<style scoped>
.settings-root {
    display: flex;
    gap: 1.5rem;
    min-height: calc(100vh - 56px);
    align-items: flex-start;
}

/* ── Sidebar ─────────────────────────────────────────────────────── */
.settings-sidebar {
    width: 200px;
    flex-shrink: 0;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.75rem 0.5rem;
    position: sticky;
    top: 1.5rem;
}

.settings-sidebar__section {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    padding: 0.5rem 0.75rem 0.25rem;
    margin-top: 0.5rem;
}
.settings-sidebar__section:first-child { margin-top: 0; }

.settings-nav-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--text-secondary);
    text-decoration: none;
    transition: background 0.15s, color 0.15s;
}
.settings-nav-item:hover {
    background: var(--hover);
    color: var(--text-primary);
}
.settings-nav-item--active {
    background: color-mix(in srgb, var(--brand) 12%, transparent);
    color: var(--brand);
    font-weight: 600;
}
.settings-nav-item__icon {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

/* ── Content ─────────────────────────────────────────────────────── */
.settings-content {
    flex: 1;
    min-width: 0;
}
</style>
