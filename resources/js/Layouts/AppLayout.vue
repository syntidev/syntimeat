<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { usePage, Link, useForm, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLogo from '@/Components/AppLogo.vue'

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
const page     = usePage()
const user     = computed(() => page.props.auth?.user ?? {})
const initials = computed(() => {
    const name = user.value?.name ?? '?'
    return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
})
const businessLogoUrl = computed(() => page.props.business_logo_url ?? null)
const businessName    = computed(() => page.props.business_name ?? '')

const roleLabels = { admin: 'Administrador', supervisor: 'Supervisor', cashier: 'Cajero', owner: 'Propietario', branch_admin: 'Admin Sucursal', analyst: 'Analista' }
const roleLabel  = computed(() => roleLabels[user.value?.role] ?? (user.value?.role ? user.value.role : 'Usuario'))

// ─── Dropdown usuario ─────────────────────────────────────────────────────────
const userMenuOpen = ref(false)
function toggleUserMenu() { userMenuOpen.value = !userMenuOpen.value }
function closeUserMenu()  { userMenuOpen.value = false }
function logout() {
    userMenuOpen.value = false
    router.post(route('logout'))
}

// ─── Visibilidad del menú por rol ─────────────────────────────────────────────
const rolePermissions = {
    admin:        ['dashboard','pos','inventory','boveda','fabrica','orders','sales','dayclose','catalog','clients','contingency','users','settings','cash'],
    owner:        ['dashboard','pos','inventory','boveda','fabrica','orders','sales','dayclose','catalog','clients','contingency','users','settings','cash'],
    branch_admin: ['dashboard','pos','inventory','boveda','fabrica','orders','sales','dayclose','catalog','clients','contingency','users','settings','cash'],
    supervisor:   ['dashboard','pos','cash','sales','dayclose','inventory','catalog','boveda','fabrica','orders','clients','reports','contingency'],
    analyst:      ['dashboard','pos','inventory','boveda','fabrica','orders','sales','dayclose','catalog','clients','contingency','settings','cash','reports'],
    cashier:      ['dashboard','pos','cash','sales','dayclose','orders','clients','inventory','contingency'],
}
function canAccess(permission) {
    const role = user.value?.role ?? 'admin'
    // Si el rol no está mapeado aún (migración pendiente) → acceso total
    return (rolePermissions[role] ?? rolePermissions['admin']).includes(permission)
}

// ─── Stock crítico ────────────────────────────────────────────────────────────
const stockCriticoCount = computed(() => page.props.stock_critico_count ?? 0)

// ─── Cobros pendientes (créditos + deliveries sin cobrar) ─────────────────────
const cobrosPendientesRaw   = computed(() => page.props.cobros_pendientes_count ?? 0)
const hasCobros             = computed(() => cobrosPendientesRaw.value > 0)
const cobrosPendientesLabel = computed(() => cobrosPendientesRaw.value > 99 ? '99+' : cobrosPendientesRaw.value)

// ─── Despiece pendiente (boveda_entries con kg en vitrina sin completar) ───────
const despiecePendienteRaw   = computed(() => page.props.despiece_pendiente_count ?? 0)
const hasDespiece             = computed(() => despiecePendienteRaw.value > 0)
const despiecePendienteLabel = computed(() => despiecePendienteRaw.value > 99 ? '99+' : despiecePendienteRaw.value)

// ─── Paleta de colores ───────────────────────────────────────────────────────
onMounted(() => { applyPalette(page.props.theme_color ?? 'blue') })
watch(() => page.props.theme_color, (color) => { applyPalette(color ?? 'blue') })

const palettes = {
    blue:   { '--brand': '#2563EB', '--brand-hover': '#1D4ED8' },
    green:  { '--brand': '#059669', '--brand-hover': '#047857' },
    red:    { '--brand': '#DC2626', '--brand-hover': '#B91C1C' },
    orange: { '--brand': '#EA580C', '--brand-hover': '#C2410C' },
    purple: { '--brand': '#7C3AED', '--brand-hover': '#6D28D9' },
    teal:   { '--brand': '#0891B2', '--brand-hover': '#0E7490' },
}

function applyPalette(color) {
    const vars = palettes[color] ?? palettes.blue
    Object.entries(vars).forEach(([k, v]) => {
        document.documentElement.style.setProperty(k, v)
    })
}

// ─── Sucursales ───────────────────────────────────────────────────────────────
const branches      = computed(() => page.props.branches ?? [])
const currentBranch = computed(() => page.props.currentBranch ?? null)

function changeBranch(branchId) {
    router.post('/set-branch', { branch_id: branchId }, { preserveState: false })
}

// ─── Tasa ─────────────────────────────────────────────────────────────────────
const tasa = computed(() => page.props.tasa ?? { rate: 40, source: 'fallback', hora: null })
const isAdmin = computed(() => user.value?.role === 'admin')
const showRateModal = ref(false)
const rateForm = useForm({ rate: '' })

const sourceLabels = {
    bcv:        'BCV',
    parallel:   'Paralelo',
    negotiated: 'Acordada',
    manual:     'Manual',
    fallback:   'Estimada',
}
function sourceLabel(s) { return sourceLabels[s] ?? s }

function submitRate() {
    rateForm.post(route('rate.manual'), {
        preserveScroll: true,
        onSuccess: () => {
            showRateModal.value = false
            rateForm.reset()
        },
    })
}

// ─── Nav items ───────────────────────────────────────────────────────────────
const nav = [
    {
        section: 'OPERACIÓN DIARIA',
        items: [
            { label: 'Bóveda',        route: 'boveda.index',     icon: 'warehouse', perm: 'boveda' },
            { label: 'Punto de Venta',route: 'pos.index',        icon: 'cart',      perm: 'pos' },
            { label: 'Inventario',    route: 'inventory.index',  icon: 'box',       perm: 'inventory' },
            { label: 'Fábrica',       route: 'fabrica.index',    icon: 'beaker',    perm: 'fabrica' },
            { label: 'Pedidos',       route: 'orders.index',     icon: 'clipboard', perm: 'orders' },
        ],
    },
    {
        section: 'CAJA',
        items: [
            { label: 'Caja',           route: 'cash.index',     icon: 'landmark',  perm: 'cash' },
            { label: 'Ventas del Día', route: 'sales.index',    icon: 'receipt',   perm: 'sales' },
            { label: 'Cierre del Día', route: 'cash.day-close', icon: 'calendar',  perm: 'dayclose' },
        ],
    },
    {
        section: 'GESTIÓN',
        items: [
            { label: 'Catálogo',     route: 'catalog.index',    icon: 'tag',       perm: 'catalog' },
            { label: 'Clientes',     route: 'clients.index',    icon: 'person',    perm: 'clients' },
            { label: 'Contingencia', route: 'contingency.index',icon: 'lightning', perm: 'contingency' },
        ],
    },
    {
        section: 'ANÁLISIS',
        items: [
            { label: 'Resumen',           route: 'dashboard',            icon: 'chart',  perm: 'dashboard' },
            { label: 'Panel Empresarial', route: 'reports.consolidated', icon: 'chart',  perm: 'dashboard', roles: ['super_admin', 'owner'] },
        ],
    },
    {
        section: 'ADMINISTRACIÓN',
        items: [
            { label: 'Configuración', route: 'settings.index', icon: 'cog', perm: 'settings' },
        ],
    },
]

// Nav alternativo para owner / branch_admin — prioriza análisis arriba
const navOwner = [
    {
        section: 'ANÁLISIS',
        items: [
            { label: 'Panel Empresarial', route: 'reports.consolidated', icon: 'chart',    perm: 'dashboard', roles: ['super_admin', 'owner'] },
            { label: 'Resumen',           route: 'dashboard',            icon: 'chart',    perm: 'dashboard' },
            { label: 'Ventas del Día',    route: 'sales.index',          icon: 'receipt',  perm: 'sales' },
            { label: 'Cierre del Día',    route: 'cash.day-close',       icon: 'calendar', perm: 'dayclose' },
        ],
    },
    {
        section: 'OPERACIÓN',
        items: [
            { label: 'Bóveda',        route: 'boveda.index',      icon: 'warehouse', perm: 'boveda' },
            { label: 'Punto de Venta',route: 'pos.index',         icon: 'cart',      perm: 'pos' },
            { label: 'Inventario',    route: 'inventory.index',   icon: 'box',       perm: 'inventory' },
            { label: 'Fábrica',       route: 'fabrica.index',     icon: 'beaker',    perm: 'fabrica' },
            { label: 'Pedidos',       route: 'orders.index',      icon: 'clipboard', perm: 'orders' },
            { label: 'Caja',          route: 'cash.index',        icon: 'landmark',  perm: 'cash' },
            { label: 'Catálogo',      route: 'catalog.index',     icon: 'tag',       perm: 'catalog' },
            { label: 'Clientes',      route: 'clients.index',     icon: 'person',    perm: 'clients' },
            { label: 'Contingencia',  route: 'contingency.index', icon: 'lightning', perm: 'contingency' },
            { label: 'Configuración', route: 'settings.index',    icon: 'cog',       perm: 'settings' },
        ],
    },
]

const visibleNav = computed(() => {
    const role      = user.value?.role
    const activeNav = (role === 'owner' || role === 'branch_admin') ? navOwner : nav
    return activeNav
        .map(group => ({
            ...group,
            items: group.items.filter(item =>
                canAccess(item.perm)
                && (!item.roles || item.roles.includes(role)),
            ),
        }))
        .filter(group => group.items.length > 0)
})

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

            <!-- Logo SYNTImeat -->
            <div class="sidebar-logo">
                <AppLogo :dark="isDark" :size="28" />
                <span class="sidebar-logo__text">
                    <span class="sidebar-logo__synti">SYNTI</span><span class="sidebar-logo__meat">meat</span>
                </span>
            </div>

            <!-- Navegación -->
            <nav class="sidebar-nav">
                <template v-for="group in visibleNav" :key="group.section">
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
                            <span
                                v-if="item.route === 'inventory.index' && stockCriticoCount > 0"
                                class="nav-badge"
                            >{{ stockCriticoCount }}</span>
                            <span
                                v-if="item.route === 'orders.index' && hasCobros"
                                class="nav-badge"
                            >{{ cobrosPendientesLabel }}</span>
                            <span
                                v-if="item.route === 'fabrica.index' && hasDespiece"
                                class="nav-badge"
                            >{{ despiecePendienteLabel }}</span>
                        </Link>
                        <span v-else :class="['nav-item', 'nav-item--disabled']">
                            <span class="nav-item__icon" v-html="icons[item.icon]" />
                            <span>{{ item.label }}</span>
                        </span>
                    </template>
                </template>
            </nav>

            <!-- Footer del sidebar — solo visible en mobile -->
            <div class="sidebar-mobile-footer">
                <span class="sidebar-caja-badge">
                    <span class="badge-caja__dot" />
                    Caja abierta
                </span>
                <div v-if="branches.length > 0" class="sidebar-branch-picker">
                    <span class="branch-picker__label">Sucursal</span>
                    <select class="branch-select" @change="changeBranch($event.target.value)">
                        <option value="">Todas</option>
                        <option
                            v-for="b in branches"
                            :key="b.id"
                            :value="b.id"
                            :selected="b.id == currentBranch"
                        >{{ b.name }}</option>
                    </select>
                </div>
            </div>
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

                <!-- Selector de sucursal -->
                <div v-if="branches.length > 0" class="branch-picker">
                    <span class="branch-picker__label">Sucursal</span>
                    <select class="branch-select" @change="changeBranch($event.target.value)">
                        <option value="">Todas</option>
                        <option
                            v-for="b in branches"
                            :key="b.id"
                            :value="b.id"
                            :selected="b.id == currentBranch"
                        >{{ b.name }}</option>
                    </select>
                </div>

                <div class="topbar__actions">

                    <!-- Badge caja -->
                    <span class="badge-caja">
                        <span class="badge-caja__dot" />
                        Caja abierta
                    </span>

                    <!-- Badge tasa -->
                    <button
                        :class="['rate-badge', tasa.source === 'manual' ? 'rate-badge--manual' : '']"
                        @click="isAdmin ? showRateModal = true : null"
                        :style="isAdmin ? '' : 'cursor:default'"
                        :title="isAdmin ? 'Cambiar tasa manualmente' : 'Tasa del día'"
                    >
                        <span class="rate-badge__value">Bs. {{ tasa.rate.toFixed(2) }}</span>
                        <span class="rate-badge__src">{{ sourceLabel(tasa.source) }}</span>
                        <svg v-if="isAdmin" xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="opacity:0.6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                        </svg>
                    </button>

                    <!-- Negocio (logo + nombre) -->
                    <div class="topbar-biz" :title="businessName">
                        <img v-if="businessLogoUrl" :src="businessLogoUrl" :alt="businessName" class="topbar-biz__logo" />
                        <span v-if="businessName" class="topbar-biz__name">{{ businessName }}</span>
                    </div>

                    <!-- Toggle dark/light -->
                    <button class="icon-btn" @click="toggleTheme" :aria-label="isDark ? 'Modo claro' : 'Modo oscuro'">
                        <svg v-if="!isDark" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z" />
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                        </svg>
                    </button>

                    <!-- Avatar + dropdown usuario -->
                    <div class="user-menu" v-click-outside="closeUserMenu">
                        <button class="avatar" @click="toggleUserMenu" :aria-label="'Menú de ' + user.name">
                            {{ initials }}
                        </button>

                        <div v-if="userMenuOpen" class="user-dropdown">
                            <!-- Identidad -->
                            <div class="user-dropdown__header">
                                <div class="user-dropdown__avatar">{{ initials }}</div>
                                <div class="user-dropdown__info">
                                    <span class="user-dropdown__name">{{ user.name }}</span>
                                    <span class="user-dropdown__role">{{ roleLabel }}</span>
                                </div>
                            </div>

                            <div class="user-dropdown__sep" />

                            <!-- Links -->
                            <Link
                                v-if="canAccess('settings')"
                                :href="route('settings.general')"
                                class="user-dropdown__item"
                                @click="closeUserMenu"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                                Configuración
                            </Link>

                            <div class="user-dropdown__sep" />

                            <button class="user-dropdown__item user-dropdown__item--danger" @click="logout">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/></svg>
                                Cerrar sesión
                            </button>
                        </div>
                    </div>

                </div>
            </header>

            <!-- Slot de contenido -->
            <main class="page-content">
                <slot />
            </main>

        </div>
    </div>

    <!-- ── Modal tasa manual ──────────────────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="showRateModal" class="rate-overlay" @click.self="showRateModal = false">
            <div class="rate-modal">
                <h3 class="rate-modal__title">Ajustar tasa del día</h3>
                <p class="rate-modal__sub">
                    Tasa actual: <strong>Bs. {{ tasa.rate.toFixed(2) }}</strong>
                    ({{ sourceLabel(tasa.source) }}
                    <span v-if="tasa.hora">· {{ tasa.hora }}</span>)
                </p>
                <form @submit.prevent="submitRate">
                    <input
                        v-model="rateForm.rate"
                        type="number"
                        step="0.01"
                        min="1"
                        placeholder="Ej. 52.30"
                        class="rate-modal__input"
                        autofocus
                    />
                    <p class="rate-modal__hint">Se registrará como tasa manual hasta la próxima actualización automática.</p>
                    <div class="rate-modal__actions">
                        <button type="button" class="rate-modal__cancel" @click="showRateModal = false">Cancelar</button>
                        <button type="submit" class="rate-modal__submit" :disabled="rateForm.processing">Aplicar tasa</button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
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
    lightning: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"/></svg>`,
    warehouse: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205 3 1m1.5.5-1.5-.5M6.75 7.364V3h-3v18m3-13.636 10.5-3.819"/></svg>`,
    branch:    `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>`,
    clipboard: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>`,
    calendar:  `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z"/></svg>`,
    person:    `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>`,
    beaker:    `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15M14.25 3.104c.251.023.501.05.75.082M19.8 15a2.25 2.25 0 0 1 .45 1.32v.102a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 16.422V16.32a2.25 2.25 0 0 1 .45-1.32L5 14.5m14.8.5-6.75-6.75M5 14.5l6.75-6.75"/></svg>`,
    landmark:  `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z"/></svg>`,
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
    padding: 1rem 1rem 1rem 0.875rem;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 0.625rem;
}

.sidebar-logo__text {
    font-size: 1.05rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1;
}
.sidebar-logo__synti { color: var(--text-primary); }
.sidebar-logo__meat  { color: #B91C1C; }

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

.nav-badge {
    margin-left: auto;
    background: #ef4444;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
    padding: 2px 6px;
    border-radius: 999px;
    min-width: 18px;
    text-align: center;
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

/* Negocio en topbar */
.topbar-biz {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    max-width: 200px;
    cursor: default;
}
.topbar-biz__logo {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    object-fit: contain;
    flex-shrink: 0;
    background: var(--bg-elevated);
}
.topbar-biz__name {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.25;
    max-height: 2.5em;
}
@media (max-width: 640px) {
    .topbar-biz__name { display: none; }
}

/* Avatar + user menu */
.user-menu { position: relative; }

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
    cursor: pointer;
    border: none;
    transition: opacity 0.15s;
}
.avatar:hover { opacity: 0.85; }

/* Dropdown */
.user-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 220px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    box-shadow: 0 12px 40px rgba(0,0,0,0.35);
    z-index: 9999;
    overflow: hidden;
}
.user-dropdown__header {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.875rem 1rem;
}
.user-dropdown__avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: var(--brand);
    color: #fff;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.user-dropdown__info {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}
.user-dropdown__name {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.user-dropdown__role {
    font-size: 0.72rem;
    color: var(--text-muted);
}
.user-dropdown__sep {
    height: 1px;
    background: var(--border);
    margin: 0;
}
.user-dropdown__item {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.65rem 1rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
    text-decoration: none;
    background: transparent;
    border: none;
    width: 100%;
    text-align: left;
    cursor: pointer;
    transition: background 0.12s, color 0.12s;
    font-family: inherit;
}
.user-dropdown__item:hover {
    background: var(--bg-elevated);
    color: var(--text-primary);
}
.user-dropdown__item--danger { color: #f87171; }
.user-dropdown__item--danger:hover { background: rgba(239,68,68,0.08); color: #ef4444; }

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

/* ── Rate badge ───────────────────────────────────────────────────────────── */
.rate-badge {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-secondary);
    background-color: var(--bg-elevated);
    border: 1px solid var(--border);
    padding: 0.25rem 0.6rem;
    border-radius: 9999px;
    cursor: pointer;
    transition: background-color 0.15s;
    line-height: 1;
}
.rate-badge:hover {
    background-color: var(--hover);
}
.rate-badge--manual {
    color: #D97706;
    background-color: rgba(245, 158, 11, 0.12);
    border-color: rgba(245, 158, 11, 0.3);
}
.rate-badge__src {
    opacity: 0.65;
    font-weight: 500;
}

/* ── Modal tasa ───────────────────────────────────────────────────────────── */
.rate-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}
.rate-modal {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 0.875rem;
    padding: 1.5rem;
    width: min(360px, 92vw);
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
}
.rate-modal__title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.375rem;
}
.rate-modal__sub {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin: 0 0 1rem;
}
.rate-modal__input {
    width: 100%;
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    color: var(--text-primary);
    font-size: 1.125rem;
    font-weight: 600;
    padding: 0.625rem 0.875rem;
    outline: none;
    box-sizing: border-box;
}
.rate-modal__input:focus {
    border-color: var(--brand);
}
.rate-modal__hint {
    font-size: 0.72rem;
    color: var(--text-muted);
    margin: 0.5rem 0 1rem;
}
.rate-modal__actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}
.rate-modal__cancel {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-secondary);
    font-size: 0.875rem;
    cursor: pointer;
}
.rate-modal__submit {
    padding: 0.5rem 1.25rem;
    border-radius: 0.5rem;
    background: var(--brand);
    border: none;
    color: #fff;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
}
.rate-modal__submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* ── Selector de sucursal ─────────────────────────────────────────────────── */
.branch-picker {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: color-mix(in srgb, var(--brand) 10%, transparent);
    border: 1.5px solid var(--brand);
    border-radius: 0.5rem;
    padding: 0.375rem 0.875rem;
    flex-shrink: 0;
    transition: background 0.15s;
}
.branch-picker:focus-within {
    background: color-mix(in srgb, var(--brand) 16%, transparent);
}
.branch-picker__label {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--brand);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    white-space: nowrap;
    flex-shrink: 0;
}
.branch-select {
    background: transparent;
    border: none;
    color: var(--text-primary);
    font-size: 0.875rem;
    font-weight: 600;
    font-family: inherit;
    outline: none;
    cursor: pointer;
    min-width: 110px;
    padding: 0;
}
.branch-select option {
    background: var(--bg-card);
    color: var(--text-primary);
}
@media (max-width: 640px) {
    .branch-picker { display: none; }
}

/* ── Sidebar mobile footer ────────────────────────────────────────────────── */
.sidebar-mobile-footer {
    display: none; /* oculto en desktop */
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.875rem 0.75rem;
    border-top: 1px solid var(--border);
    flex-shrink: 0;
}
.sidebar-caja-badge {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.78rem;
    font-weight: 500;
    color: var(--green);
    background-color: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.25);
    padding: 0.35rem 0.75rem;
    border-radius: 9999px;
    width: fit-content;
}
.sidebar-branch-picker {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: color-mix(in srgb, var(--brand) 10%, transparent);
    border: 1px solid color-mix(in srgb, var(--brand) 25%, transparent);
    border-radius: 0.5rem;
    padding: 0.375rem 0.75rem;
}
@media (max-width: 768px) {
    .sidebar-mobile-footer { display: flex; }
}
</style>
