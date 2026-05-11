<script setup>
import { ref, onMounted, computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
    title: { type: String, default: '' },
    theme: { type: String, default: 'dark' },
})

// ─── Theme ───────────────────────────────────────────────────────────────────
const isDark = ref(true)

onMounted(() => {
    const saved = localStorage.getItem('theme') ?? props.theme
    isDark.value = saved !== 'light'
    applyTheme()
})

function applyTheme() {
    if (isDark.value) {
        document.documentElement.classList.remove('light')
    } else {
        document.documentElement.classList.add('light')
    }
}

async function toggleTheme() {
    isDark.value = !isDark.value
    applyTheme()
    const value = isDark.value ? 'dark' : 'light'
    localStorage.setItem('theme', value)
    try {
        await axios.post('/preferencias/tema', { theme: value })
    } catch (_) {
        // fallo silencioso — la preferencia ya está en localStorage
    }
}

// ─── Sidebar mobile ──────────────────────────────────────────────────────────
const sidebarOpen = ref(false)

function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value
}

function closeSidebar() {
    sidebarOpen.value = false
}

// ─── User ─────────────────────────────────────────────────────────────────────
const page = usePage()
const user = computed(() => page.props.auth?.user ?? {})
const initials = computed(() => {
    const name = user.value?.name ?? '?'
    return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
})

// ─── Nav items ───────────────────────────────────────────────────────────────
const nav = [
    {
        section: 'PRINCIPAL',
        items: [
            { label: 'Dashboard', route: 'dashboard', icon: 'chart' },
        ],
    },
    {
        section: 'OPERACIONES',
        items: [
            { label: 'Nueva Venta', route: 'pos.index', icon: 'cart' },
            { label: 'Inventario', route: 'inventory.index', icon: 'box' },
        ],
    },
    {
        section: 'GESTIÓN',
        items: [
            { label: 'Catálogo', route: 'catalog.index', icon: 'tag' },
            { label: 'Ventas del Día', route: 'sales.index', icon: 'receipt' },
            { label: 'Cierre del Día', route: 'cashregister.index', icon: 'lock' },
        ],
    },
    {
        section: 'ADMINISTRACIÓN',
        items: [
            { label: 'Usuarios', route: 'users.index', icon: 'users' },
            { label: 'Configuración', route: 'settings.index', icon: 'cog' },
        ],
    },
]

function isActive(routeName) {
    try {
        return route().current(routeName)
    } catch (_) {
        return false
    }
}

function routeExists(routeName) {
    try {
        route(routeName)
        return true
    } catch (_) {
        return false
    }
}
</script>

<template>
    <div class="layout-root">

        <!-- ── Overlay móvil ─────────────────────────────────────────────── -->
        <div
            v-if="sidebarOpen"
            class="sidebar-overlay"
            @click="closeSidebar"
        />

        <!-- ── Sidebar ────────────────────────────────────────────────────── -->
        <aside :class="['sidebar', { 'sidebar--open': sidebarOpen }]">

            <!-- Logo -->
            <div class="sidebar-logo">
                <span class="sidebar-logo__text">SYNTImeat</span>
            </div>

            <!-- Navegación -->
            <nav class="sidebar-nav">
                <template v-for="group in nav" :key="group.section">
                    <p class="sidebar-nav__section">{{ group.section }}</p>

                    <template v-for="item in group.items" :key="item.route">
                        <Link
                            v-if="routeExists(item.route)"
                            :href="route(item.route)"
                            :class="['nav-item', { 'nav-item--active': isActive(item.route) }]"
                            @click="closeSidebar"
                        >
                            <span class="nav-item__icon" v-html="icons[item.icon]" />
                            <span>{{ item.label }}</span>
                        </Link>
                        <span v-else :class="['nav-item', 'nav-item--disabled']">
                            <span class="nav-item__icon" v-html="icons[item.icon]" />
                            <span>{{ item.label }}</span>
                        </span>
                    </template>
                </template>
            </nav>
        </aside>

        <!-- ── Contenido principal ───────────────────────────────────────── -->
        <div class="main-wrapper">

            <!-- Topbar -->
            <header class="topbar">

                <!-- Hamburguesa (solo móvil) -->
                <button class="topbar__hamburger" @click="toggleSidebar" aria-label="Abrir menú">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <!-- Título de página -->
                <h1 class="topbar__title">{{ title }}</h1>

                <div class="topbar__actions">

                    <!-- Badge caja -->
                    <span class="badge-caja">
                        <span class="badge-caja__dot" />
                        Caja abierta
                    </span>

                    <!-- Toggle dark/light -->
                    <button class="icon-btn" @click="toggleTheme" :aria-label="isDark ? 'Modo claro' : 'Modo oscuro'">
                        <!-- Sol -->
                        <svg v-if="!isDark" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z" />
                        </svg>
                        <!-- Luna -->
                        <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                        </svg>
                    </button>

                    <!-- Avatar usuario -->
                    <div class="avatar" :title="user.name">
                        {{ initials }}
                    </div>

                </div>
            </header>

            <!-- Slot de contenido -->
            <main class="page-content">
                <slot />
            </main>

        </div>
    </div>
</template>

<script>
// Iconos SVG heroicons (outline) — definidos fuera del setup para ser accesibles en template
const icons = {
    chart: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>`,
    cart: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>`,
    box: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>`,
    tag: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>`,
    receipt: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008h-.008V13.5Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>`,
    lock: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>`,
    users: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>`,
    cog: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>`,
}
</script>

<style scoped>
/* Layout principal */
.layout-root {
    display: flex;
    min-height: 100vh;
    background-color: var(--bg-base);
}

/* ── Sidebar ──────────────────────────────────────────────────────────── */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: var(--sidebar-w);
    height: 100vh;
    background-color: var(--bg-surface);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    z-index: 40;
    transition: transform 0.25s ease;
}

.sidebar-logo {
    padding: 1.25rem 1rem;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}

.sidebar-logo__text {
    font-size: 1.125rem;
    font-weight: 800;
    color: var(--brand);
    letter-spacing: -0.02em;
}

.sidebar-nav {
    padding: 0.75rem 0.5rem;
    flex: 1;
}

.sidebar-nav__section {
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    padding: 0.75rem 0.5rem 0.25rem;
    text-transform: uppercase;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--text-secondary);
    text-decoration: none;
    margin-bottom: 2px;
    transition: background-color 0.15s, color 0.15s;
    cursor: pointer;
}

.nav-item:hover {
    background-color: var(--bg-elevated);
    color: var(--text-primary);
}

.nav-item--active {
    background-color: var(--brand);
    color: #ffffff;
}

.nav-item--active:hover {
    background-color: var(--brand-hover);
    color: #ffffff;
}

.nav-item--disabled {
    opacity: 0.4;
    cursor: default;
    pointer-events: none;
}

.nav-item__icon {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    color: inherit;
}

/* ── Overlay móvil ────────────────────────────────────────────────────── */
.sidebar-overlay {
    position: fixed;
    inset: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 39;
}

/* ── Main wrapper ──────────────────────────────────────────────────────── */
.main-wrapper {
    flex: 1;
    margin-left: var(--sidebar-w);
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* ── Topbar ────────────────────────────────────────────────────────────── */
.topbar {
    position: sticky;
    top: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0 1.25rem;
    height: 56px;
    background-color: var(--bg-surface);
    border-bottom: 1px solid var(--border);
    z-index: 30;
}

.topbar__hamburger {
    display: none;
    padding: 0.25rem;
    color: var(--text-secondary);
    background: transparent;
    border: none;
    cursor: pointer;
    border-radius: 0.375rem;
}

.topbar__hamburger:hover {
    color: var(--text-primary);
    background-color: var(--bg-elevated);
}

.topbar__title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    flex: 1;
    margin: 0;
}

.topbar__actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Badge caja */
.badge-caja {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--green);
    background-color: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.25);
    padding: 0.25rem 0.625rem;
    border-radius: 9999px;
}

.badge-caja__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: var(--green);
}

/* Botón ícono */
.icon-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 0.5rem;
    background: transparent;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    transition: background-color 0.15s, color 0.15s;
}

.icon-btn:hover {
    background-color: var(--bg-elevated);
    color: var(--text-primary);
}

/* Avatar */
.avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: var(--brand);
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    cursor: default;
}

/* ── Contenido ──────────────────────────────────────────────────────────── */
.page-content {
    flex: 1;
    padding: 1.5rem;
    background-color: var(--bg-base);
}

/* ── Responsive ─────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }

    .sidebar--open {
        transform: translateX(0);
    }

    .main-wrapper {
        margin-left: 0;
    }

    .topbar__hamburger {
        display: flex;
    }

    .badge-caja {
        display: none;
    }
}
</style>
