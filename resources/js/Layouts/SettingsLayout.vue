<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()

const menu = [
    {
        section: 'MI NEGOCIO',
        items: [
            { label: 'General',    route: 'settings.general',  icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
        ],
    },
    {
        section: 'DÓNDE OPERA',
        items: [
            { label: 'Sucursales', route: 'settings.branches', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
            { label: 'Cajas',      route: 'settings.cash-registers', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z' },
        ],
    },
    {
        section: 'QUIÉN OPERA',
        items: [
            { label: 'Equipo',    route: 'settings.team',  icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' },
            { label: 'Usuarios',  route: 'settings.users', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
        ],
    },
    {
        section: 'CÓMO COBRA',
        items: [
            { label: 'Métodos de Pago', route: 'payment-methods.index', icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z' },
            { label: 'Terminales POS',  route: 'settings.terminals',    icon: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z' },
            { label: 'Ticket',          route: 'settings.ticket',       icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
            { label: 'Hardware',        route: 'settings.hardware',     icon: 'M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18' },
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
    min-height: 44px;
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

/* ── Mobile: sidebar → horizontal scrollable top strip ────────────── */
@media (max-width: 767px) {
    .settings-root {
        flex-direction: column;
        gap: 1rem;
    }
    .settings-sidebar {
        width: 100%;
        position: static;
        padding: 0.25rem 0.5rem;
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        gap: 0;
    }
    .settings-sidebar__section { display: none; }
    .settings-nav-item {
        flex-shrink: 0;
        white-space: nowrap;
        border-radius: 8px;
        padding: 0.5rem 0.875rem;
    }
}

@media (max-width: 640px) {
    .settings-sidebar { padding: 0.25rem 0.25rem; }
    .settings-nav-item { padding: 0.5rem 0.625rem; font-size: 0.75rem; }
}

@media (max-width: 1023px) {
    .settings-root { gap: 0.75rem; }
    .settings-content { min-width: 0; }
}
</style>
