<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HelpModal  from '@/Components/HelpModal.vue'
import { ref, computed, watch } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { Scale, RefreshCw } from '@lucide/vue'
import axios from 'axios'

const props = defineProps({
    products:     Array,
    categories:   Array,
    todayEntries: Array,
    stockMap:     Object,
    lastEntryMap: Object,
    lastCostMap:  { type: Object, default: () => ({}) },
    kpis:         Object,
})

// ─── Búsqueda + filtros + ordenamiento ───────────────────────────────────────
const PAGE_SIZE    = 20
const search       = ref('')
const filterCat    = ref('')          // '' = todas
const filterStatus = ref('')          // '' | 'ok' | 'low' | 'empty'
const sortKey      = ref('name')      // 'name' | 'stock' | 'status' | 'last'
const sortDir      = ref('asc')       // 'asc' | 'desc'
const currentPage  = ref(1)

function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortKey.value = key
        sortDir.value = 'asc'
    }
    currentPage.value = 1
}

function sortIcon(key) {
    if (sortKey.value !== key) return '↕'
    return sortDir.value === 'asc' ? '↑' : '↓'
}

const filteredAndSorted = computed(() => {
    const q = search.value.trim().toLowerCase()

    let list = props.products.filter(p => {
        if (q && !p.name.toLowerCase().includes(q) && !(p.category?.name ?? '').toLowerCase().includes(q)) return false
        if (filterCat.value && p.category_id !== Number(filterCat.value)) return false
        if (filterStatus.value && stockStatus(p) !== filterStatus.value) return false
        return true
    })

    list = [...list].sort((a, b) => {
        let va, vb
        if (sortKey.value === 'stock') {
            va = stockValue(a.id); vb = stockValue(b.id)
        } else if (sortKey.value === 'status') {
            const order = { empty: 0, low: 1, ok: 2 }
            va = order[stockStatus(a)]; vb = order[stockStatus(b)]
        } else if (sortKey.value === 'last') {
            va = props.lastEntryMap[a.id] ?? ''; vb = props.lastEntryMap[b.id] ?? ''
        } else {
            va = a.name.toLowerCase(); vb = b.name.toLowerCase()
        }
        if (va < vb) return sortDir.value === 'asc' ? -1 : 1
        if (va > vb) return sortDir.value === 'asc' ? 1  : -1
        return 0
    })

    return list
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredAndSorted.value.length / PAGE_SIZE)))

const pagedProducts = computed(() => {
    const start = (currentPage.value - 1) * PAGE_SIZE
    return filteredAndSorted.value.slice(start, start + PAGE_SIZE)
})

function onSearch() { currentPage.value = 1 }
function clearFilters() {
    search.value = ''; filterCat.value = ''; filterStatus.value = ''
    sortKey.value = 'name'; sortDir.value = 'asc'; currentPage.value = 1
}

const hasActiveFilters = computed(() =>
    search.value || filterCat.value || filterStatus.value
)

// ─── Drawer ───────────────────────────────────────────────────────────────────
const drawerProduct = ref(null)

const drawerEntries = computed(() => {
    if (! drawerProduct.value) return []
    return props.todayEntries
        .filter(e => e.product_id === drawerProduct.value.id)
        .slice(0, 10)
})

function openDrawer(product) { drawerProduct.value = product }
function closeDrawer()       { drawerProduct.value = null }

// ─── Modal "Nueva Entrada" ────────────────────────────────────────────────────
const showModal = ref(false)

const form = useForm({
    product_id:      '',
    quantity_kg:     '',
    waste_kg:        0,
    cost_per_kg_usd: '',
    supplier:        '',
    entered_at:      new Date().toISOString().slice(0, 16),
})

const page        = usePage()
const dollarRate  = computed(() => page.props.tasa?.rate ?? 1)
const bsCostPrice = ref('')

function syncBsCost()  { bsCostPrice.value    = (parseFloat(form.cost_per_kg_usd) * dollarRate.value).toFixed(2) }
function syncUsdCost() { form.cost_per_kg_usd = (parseFloat(bsCostPrice.value)    / dollarRate.value).toFixed(2) }

const selectedCategory = ref('')

const filteredProducts = computed(() => {
    if (! selectedCategory.value) return props.products
    return props.products.filter(p => p.category_id === Number(selectedCategory.value))
})

const selectedProduct = computed(() => {
    if (!form.product_id) return null
    return props.products.find(p => p.id === Number(form.product_id)) ?? null
})

const isUnitMode = computed(() => selectedProduct.value?.sale_mode === 'unit')

watch(() => form.product_id, (newId) => {
    if (isUnitMode.value) form.waste_kg = 0
    const numId = Number(newId)
    if (!numId) return
    const product = props.products.find(p => p.id === numId)
    const lookupId = product?.stock_product_id ?? numId
    const lastCost = props.lastCostMap[lookupId] ?? props.lastCostMap[numId] ?? null
    form.cost_per_kg_usd = lastCost ? parseFloat(lastCost) : ''
    bsCostPrice.value = lastCost && dollarRate.value > 0
        ? parseFloat((lastCost * dollarRate.value).toFixed(2))
        : ''
})

const netKg = computed(() => {
    if (isUnitMode.value) {
        return Math.max(0, parseFloat(form.quantity_kg) || 0).toFixed(0)
    }
    const qty   = parseFloat(form.quantity_kg) || 0
    const waste = parseFloat(form.waste_kg)    || 0
    return Math.max(0, qty - waste).toFixed(3)
})

function openModal(product = null) {
    form.reset()
    form.waste_kg     = 0
    form.entered_at   = new Date().toISOString().slice(0, 16)
    bsCostPrice.value = ''
    selectedCategory.value = ''
    if (product) {
        form.product_id    = product.id
        selectedCategory.value = product.category_id ?? ''
    }
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    form.clearErrors()
}

function submitEntry() {
    form.post(route('inventory.store'), {
        preserveScroll: true,
        onSuccess: () => { closeModal() },
    })
}

// ─── Stock helpers ────────────────────────────────────────────────────────────
function stockValue(productId) {
    return props.stockMap[productId] ?? 0
}

function stockStatus(product) {
    const stock    = stockValue(product.id)
    const minStock = parseFloat(product.min_stock) || 0
    if (stock <= 0)                       return 'empty'
    if (minStock > 0 && stock < minStock) return 'low'
    return 'ok'
}

function stockLabel(product) {
    const s = stockStatus(product)
    if (s === 'empty') return 'Agotado'
    if (s === 'low')   return 'Bajo'
    return 'OK'
}

function getLastCost(product) {
    return product.cost_per_kg_usd
        ? parseFloat(product.cost_per_kg_usd).toFixed(2)
        : null
}

function sharedPoolLabel(product) {
    return product.shared_pool_name ? `(compartido: ${product.shared_pool_name})` : ''
}

function fmtKg(val) {
    return Number(val).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 3 })
}

function fmtTime(dt) {
    if (! dt) return '—'
    return new Date(dt).toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit' })
}

function entryLabel(e) {
    const n = (e.notes ?? '').toLowerCase()
    if (n.includes('producción fábrica') || n.includes('produccion fabrica')) return { text: 'Fábrica', cls: 'badge-fabrica' }
    if (n.includes('insumo fábrica')     || n.includes('insumo fabrica'))     return { text: 'Consumo', cls: 'badge-consumo' }
    if (n.includes('despiece'))                                                return { text: 'Despiece', cls: 'badge-despiece' }
    if (parseFloat(e.quantity_kg) < 0)                                        return { text: 'Salida', cls: 'badge-consumo' }
    return { text: 'Entrada', cls: 'badge-entry' }
}

function fmtDate(dt) {
    if (! dt) return '—'
    return new Date(dt).toLocaleDateString('es-VE', { day: '2-digit', month: 'short' })
}

function fmtUsd(val) {
    if (! val) return '—'
    return '$' + Number(val).toFixed(2)
}

// ─── Ayuda ────────────────────────────────────────────────────────────────────
const showHelp = ref(false)

const helpSteps = [
    {
        title: 'Ver el stock disponible',
        body: 'Aquí ves todos los productos en vitrina y despensa con sus kg disponibles. La bóveda no aparece aquí.',
        tip: 'El número en rojo significa que el producto está bajo el mínimo.',
    },
    {
        title: 'Registrar entrada manual',
        body: 'Si recibiste producto directo (sin pasar por bóveda), regístralo aquí con el peso y el costo.',
        tip: 'Productos de despensa como abarrotes entran por aquí directamente.',
    },
    {
        title: 'Stock crítico',
        body: 'El badge rojo en el menú indica cuántos productos están por debajo del mínimo configurado.',
        tip: 'El mínimo se configura en el Catálogo por producto.',
    },
    {
        title: 'Historial de movimientos',
        body: 'Cada entrada y salida queda registrada con fecha, usuario y cantidad.',
        tip: 'Usa el historial para rastrear cualquier diferencia de inventario.',
    },
]

const helpFaqs = [
    {
        q: '¿Por qué no veo los productos de bóveda aquí?',
        a: 'La bóveda tiene su propio módulo. El inventario solo muestra vitrina y despensa.',
    },
    {
        q: '¿Cómo bajo el stock?',
        a: 'El stock baja automáticamente cuando se confirma una venta en el POS.',
    },
    {
        q: '¿Qué es stock crítico?',
        a: 'Cuando el stock disponible cae por debajo del mínimo configurado para ese producto.',
    },
    {
        q: '¿Puedo corregir un stock mal ingresado?',
        a: 'Registra una entrada negativa para corregir. Consulta al administrador.',
    },
    {
        q: '¿Cada cuánto se actualiza?',
        a: 'En tiempo real. Cada venta confirmada descuenta al instante.',
    },
]

// ─── Ajuste de stock ─────────────────────────────────────────────────────────
const showAdjust = ref(false)
const adjustForm = useForm({ product_id: '', stock_real: '' })

function openAdjust() {
    adjustForm.product_id = drawerProduct.value.id
    adjustForm.stock_real  = stockValue(drawerProduct.value.id)
    showAdjust.value = true
}

function closeAdjust() {
    showAdjust.value = false
    adjustForm.clearErrors()
}

function submitAdjust() {
    adjustForm.post(route('inventory.adjust'), {
        preserveScroll: true,
        onSuccess: () => { closeAdjust(); closeDrawer() },
    })
}

watch(drawerProduct, (val) => { if (! val) closeAdjust() })

// ─── Reciclar remanente ─────────────────────────────────────────────────────
const showReciclar    = ref(false)
const reciclarStep    = ref(1)
const reciclarProd    = ref(null)
const reciclarCosto   = ref('')
const reciclarNotas   = ref('')
const reciclarLoading = ref(false)
const reciclarError   = ref('')

const reciclarKg    = computed(() => reciclarProd.value ? stockValue(reciclarProd.value.id) : 0)
const reciclarTotal = computed(() => (reciclarKg.value * (parseFloat(reciclarCosto.value) || 0)).toFixed(2))

function canReciclar(p) {
    if (!p) return false
    const role = page.props.auth.user.role
    return p.sale_mode === 'weight'
        && stockValue(p.id) > 0
        && ['owner', 'branch_admin', 'super_admin'].includes(role)
}

function openReciclar(product) {
    reciclarProd.value    = product
    reciclarCosto.value   = ''
    reciclarNotas.value   = ''
    reciclarStep.value    = 1
    reciclarError.value   = ''
    reciclarLoading.value = false
    showReciclar.value    = true
}

function closeReciclar() {
    showReciclar.value    = false
    reciclarLoading.value = false
    reciclarError.value   = ''
}

async function submitReciclar() {
    reciclarLoading.value = true
    reciclarError.value   = ''
    try {
        await axios.post(route('inventory.reciclar'), {
            product_id:      reciclarProd.value.id,
            new_cost_per_kg: parseFloat(reciclarCosto.value),
            notes:           reciclarNotas.value || null,
        }, { withCredentials: true })
        closeReciclar()
        window.location.reload()
    } catch (e) {
        reciclarError.value   = e.response?.data?.error ?? 'Error al procesar.'
        reciclarLoading.value = false
    }
}
</script>

<template>
    <AppLayout title="Inventario">
        <div class="inv-wrap">

            <!-- ── KPIs ─────────────────────────────────────────────────────── -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <span class="kpi-label">Entrado Hoy</span>
                    <span class="kpi-value">{{ fmtKg(kpis.kg_today) }} <small>kg</small></span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-label">Stock Disponible (kg)</span>
                    <span class="kpi-value">{{ fmtKg(kpis.total_stock) }} <small>kg</small></span>
                </div>
                <div class="kpi-card" :class="{ 'kpi-card--alert': kpis.below_min > 0 }">
                    <span class="kpi-label">Bajo Mínimo</span>
                    <span class="kpi-value">
                        {{ kpis.below_min }}
                        <span v-if="kpis.below_min > 0" class="badge-alert">alerta</span>
                    </span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-label">Costo Hoy</span>
                    <span class="kpi-value">{{ fmtUsd(kpis.cost_today) }}</span>
                </div>
            </div>

            <!-- ── Barra de filtros ───────────────────────────────────────────── -->
            <div class="filter-bar">
                <div class="filter-search-wrap">
                    <svg class="filter-search-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3a6 6 0 1 0 0 12A6 6 0 0 0 9 3Zm-8 6a8 8 0 1 1 14.32 4.906l4.387 4.387a1 1 0 0 1-1.414 1.414l-4.387-4.387A8 8 0 0 1 1 9Z" clip-rule="evenodd"/></svg>
                    <input
                        v-model="search"
                        class="filter-search"
                        type="search"
                        placeholder="Buscar por nombre o categoría…"
                        @input="onSearch"
                        autocomplete="off"
                    />
                    <button v-if="search" class="filter-clear-x" @click="search = ''; onSearch()">×</button>
                </div>

                <select v-model="filterCat" class="filter-select" @change="currentPage = 1">
                    <option value="">Todas las categorías</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                        {{ cat.name }}
                    </option>
                </select>

                <select v-model="filterStatus" class="filter-select" @change="currentPage = 1">
                    <option value="">Todos los estados</option>
                    <option value="ok">OK</option>
                    <option value="low">Bajo mínimo</option>
                    <option value="empty">Agotado</option>
                </select>

                <button v-if="hasActiveFilters" class="filter-reset" @click="clearFilters">
                    ✕ Limpiar
                </button>

                <span class="filter-count">{{ filteredAndSorted.length }} producto{{ filteredAndSorted.length !== 1 ? 's' : '' }}</span>

                <a :href="route('export.catalogo')" class="btn-export" target="_blank">Exportar Excel</a>
                <button class="btn btn-primary filter-cta" @click="openModal()">+ Nueva Entrada</button>
                <button class="btn-help" @click="showHelp = true" title="Ayuda">?</button>
            </div>

            <!-- ── Tabla stock actual ─────────────────────────────────────────── -->
            <div class="table-card">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="th-sort" @click="toggleSort('name')">Producto <span class="sort-icon">{{ sortIcon('name') }}</span></th>
                                <th>Categoría</th>
                                <th>Sucursal</th>
                                <th class="txt-right th-sort" @click="toggleSort('stock')">Stock <span class="sort-icon">{{ sortIcon('stock') }}</span></th>
                                <th class="th-sort" @click="toggleSort('status')">Estado <span class="sort-icon">{{ sortIcon('status') }}</span></th>
                                <th class="th-sort" @click="toggleSort('last')">Última entrada <span class="sort-icon">{{ sortIcon('last') }}</span></th>
                                <th class="txt-right">Ultimo Costo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="p in pagedProducts"
                                :key="p.id"
                                class="row-clickable"
                                @click="openDrawer(p)"
                            >
                                <td class="prod-name">{{ p.name }}</td>
                                <td>
                                    <span class="cat-dot" :style="{ background: p.category?.color ?? 'var(--brand)' }"></span>
                                    {{ p.category?.name ?? '—' }}
                                </td>
                                <td class="txt-muted">{{ p.branch?.name ?? '—' }}</td>
                                <td class="txt-right mono">
                                    <div>
                                        {{ p.sale_mode === 'unit'
                                            ? parseInt(stockValue(p.id))
                                            : fmtKg(stockValue(p.id)) }}
                                        <small>{{ p.sale_mode === 'weight' ? 'kg' : (p.base_unit_label ?? 'und') }}</small>
                                    </div>
                                    <small v-if="p.shared_pool_name" class="txt-muted" style="font-size: 0.7rem; font-weight: 400;">{{ sharedPoolLabel(p) }}</small>
                                </td>
                                <td>
                                    <span class="status-badge" :class="'status-' + stockStatus(p)">
                                        {{ stockLabel(p) }}
                                    </span>
                                </td>
                                <td class="txt-muted">{{ fmtDate(lastEntryMap[p.id]) }}</td>
                                <td class="txt-right mono">
                                    <span v-if="getLastCost(p)" class="cost-tag">{{ fmtUsd(getLastCost(p)) }}/{{ p.sale_mode === 'weight' ? 'kg' : (p.base_unit_label ?? 'und') }}</span>
                                    <span v-else class="txt-muted">—</span>
                                </td>
                                <td class="txt-muted row-arrow">›</td>
                            </tr>
                            <tr v-if="pagedProducts.length === 0">
                                <td colspan="7" class="empty-cell">Sin productos que coincidan.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile cards (< 640 px) -->
                <div class="mobile-cards">
                    <div
                        v-for="p in pagedProducts"
                        :key="'mc-' + p.id"
                        class="mc-row"
                        @click="openDrawer(p)"
                    >
                        <span class="cat-dot" :style="{ background: p.category?.color ?? 'var(--brand)' }"></span>
                        <div class="mc-body">
                            <div class="mc-name">{{ p.name }}</div>
                            <div class="mc-cat">{{ p.category?.name ?? '—' }}</div>
                        </div>
                        <div class="mc-right">
                            <div class="mc-stock">
                                <div>
                                    {{ p.sale_mode === 'unit'
                                        ? parseInt(stockValue(p.id))
                                        : fmtKg(stockValue(p.id)) }}
                                    <small>{{ p.sale_mode === 'weight' ? 'kg' : (p.base_unit_label ?? 'und') }}</small>
                                </div>
                                <small v-if="p.shared_pool_name" class="txt-muted" style="font-size: 0.7rem; font-weight: 400; display: block; margin-top: 0.2rem;">{{ sharedPoolLabel(p) }}</small>
                            </div>
                            <span class="status-badge" :class="'status-' + stockStatus(p)">{{ stockLabel(p) }}</span>
                        </div>
                        <span class="mc-arrow">›</span>
                    </div>
                    <p v-if="pagedProducts.length === 0" class="empty-cell">Sin productos que coincidan.</p>
                </div>

                <!-- Paginación -->
                <div v-if="totalPages > 1" class="pagination">
                    <button
                        class="page-btn"
                        :disabled="currentPage === 1"
                        @click="currentPage--"
                    >‹</button>
                    <span class="page-info">{{ currentPage }} / {{ totalPages }}</span>
                    <button
                        class="page-btn"
                        :disabled="currentPage === totalPages"
                        @click="currentPage++"
                    >›</button>
                </div>
            </div>

        </div>

        <!-- ── Drawer producto ────────────────────────────────────────────────── -->
        <Teleport to="body">
            <Transition name="drawer">
                <div v-if="drawerProduct" class="drawer-overlay">
                    <aside class="drawer">
                        <!-- Header -->
                        <div class="drawer-header">
                            <div>
                                <p class="drawer-cat">
                                    <span class="cat-dot" :style="{ background: drawerProduct.category?.color ?? 'var(--brand)' }"></span>
                                    {{ drawerProduct.category?.name ?? '—' }}
                                </p>
                                <h2 class="drawer-title">{{ drawerProduct.name }}</h2>
                            </div>
                            <button class="drawer-close" @click="closeDrawer">✕</button>
                        </div>

                        <!-- Stats -->
                        <div class="drawer-stats">
                            <div class="dstat">
                                <span class="dstat-label">Stock actual</span>
                                <span class="dstat-value" :class="'dstat-' + stockStatus(drawerProduct)">
                                    <div>
                                        {{ drawerProduct.sale_mode === 'unit'
                                            ? parseInt(stockValue(drawerProduct.id))
                                            : fmtKg(stockValue(drawerProduct.id)) }}
                                        <small>{{ drawerProduct.sale_mode === 'weight' ? 'kg' : 'und' }}</small>
                                    </div>
                                    <small v-if="drawerProduct.shared_pool_name" class="txt-muted" style="font-size: 0.7rem; font-weight: 400; display: block; margin-top: 0.25rem;">{{ sharedPoolLabel(drawerProduct) }}</small>
                                </span>
                            </div>
                            <div class="dstat">
                                <span class="dstat-label">Mínimo</span>
                                <span class="dstat-value">
                                    {{ parseFloat(drawerProduct.min_stock) > 0
                                        ? (drawerProduct.sale_mode === 'unit'
                                            ? parseInt(drawerProduct.min_stock) + ' und'
                                            : fmtKg(drawerProduct.min_stock) + ' kg')
                                        : '—' }}
                                </span>
                            </div>
                            <div class="dstat">
                                <span class="dstat-label">Precio ref.</span>
                                <span class="dstat-value">
                                    {{ drawerProduct.sale_mode === 'weight'
                                        ? fmtUsd(drawerProduct.price_per_kg_usd) + '/kg'
                                        : fmtUsd(drawerProduct.price_per_unit_usd) + '/und' }}
                                </span>
                            </div>
                            <div class="dstat">
                                <span class="dstat-label">Estado</span>
                                <span class="status-badge" :class="'status-' + stockStatus(drawerProduct)">
                                    {{ stockLabel(drawerProduct) }}
                                </span>
                            </div>
                        </div>

                        <!-- CTA -->
                        <button class="btn btn-primary btn-full" @click="openModal(drawerProduct); closeDrawer()">
                            + Registrar Entrada
                        </button>

                        <!-- Ajuste de stock -->
                        <template v-if="$page.props.auth.user.role !== 'cashier'">
                            <button class="btn btn-ghost btn-full adjust-btn" @click="openAdjust()">
                                <Scale :size="16" />
                                Ajustar stock
                            </button>
                            <div v-if="showAdjust" class="adjust-panel">
                                <label class="field-label">Stock real actual (lo que hay físicamente)</label>
                                <input
                                    v-model="adjustForm.stock_real"
                                    type="number"
                                    :step="drawerProduct?.sale_mode === 'unit' ? '1' : '0.001'"
                                    min="0"
                                    max="99999"
                                    class="field-input"
                                    :class="{ 'field-error': adjustForm.errors.stock_real }"
                                />
                                <span v-if="adjustForm.errors.stock_real" class="error-msg">{{ adjustForm.errors.stock_real }}</span>
                                <div class="adjust-actions">
                                    <button type="button" class="btn btn-ghost" @click="closeAdjust">Cancelar</button>
                                    <button type="button" class="btn btn-primary" :disabled="adjustForm.processing" @click="submitAdjust">
                                        Confirmar ajuste
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- Reciclar remanente -->
                        <template v-if="canReciclar(drawerProduct)">
                            <button
                                class="btn btn-ghost btn-full reciclar-btn"
                                @click="openReciclar(drawerProduct)"
                            >
                                <RefreshCw :size="16" />
                                Reciclar remanente
                            </button>
                        </template>

                        <!-- Historial del día -->
                        <div class="drawer-history">
                            <h3 class="drawer-section">Movimientos de hoy</h3>

                            <p v-if="drawerEntries.length === 0" class="empty-msg">
                                Sin movimientos hoy para este producto.
                            </p>

                            <div v-else class="history-list">
                                <div v-for="e in drawerEntries" :key="e.id" class="history-row">
                                    <div class="history-meta">
                                        <span class="h-time">{{ fmtTime(e.entered_at) }}</span>
                                        <span class="h-type" :class="entryLabel(e).cls">{{ entryLabel(e).text }}</span>
                                    </div>
                                    <div class="history-nums">
                                        <span class="h-qty" :class="parseFloat(e.quantity_kg) < 0 ? 'h-qty--neg' : ''">
                                            {{ drawerProduct.sale_mode === 'unit'
                                                ? parseInt(Math.abs(parseFloat(e.quantity_kg)))
                                                : fmtKg(Math.abs(parseFloat(e.quantity_kg))) }}
                                            {{ drawerProduct.sale_mode === 'unit' ? 'und' : 'kg' }}
                                        </span>
                                        <span v-if="drawerProduct.sale_mode !== 'unit' && parseFloat(e.waste_kg) > 0" class="h-waste">
                                            − {{ fmtKg(e.waste_kg) }} kg merma
                                        </span>
                                        <span v-if="drawerProduct.sale_mode !== 'unit' && parseFloat(e.quantity_kg) > 0" class="h-net">
                                            Neto: {{ fmtKg(e.net_kg) }} kg
                                        </span>
                                    </div>
                                    <div v-if="e.notes" class="h-notes">{{ e.notes }}</div>
                                    <div v-else-if="e.supplier" class="h-supplier">{{ e.supplier }}</div>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Modal Nueva Entrada ────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showModal" class="modal-overlay">
                <div class="modal-box">
                    <h2 class="modal-title">Registrar Entrada</h2>

                    <form @submit.prevent="submitEntry" class="entry-form">

                        <!-- Fila 1: Categoría + Producto -->
                        <div class="form-row">
                            <div class="field-group">
                                <label class="field-label">Categoría</label>
                                <select v-model="selectedCategory" class="field-input"
                                    @change="form.product_id = ''">
                                    <option value="">Todas</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                        {{ cat.icon }} {{ cat.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="field-group field-grow">
                                <label class="field-label">Producto *</label>
                                <select v-model="form.product_id" class="field-input"
                                    :class="{ 'field-error': form.errors.product_id }">
                                    <option value="">— Seleccionar —</option>
                                    <option v-for="p in filteredProducts" :key="p.id" :value="p.id">
                                        {{ p.name }}
                                    </option>
                                </select>
                                <span v-if="form.errors.product_id" class="error-msg">{{ form.errors.product_id }}</span>
                            </div>
                        </div>

                        <!-- Fila 2: Cantidad + Merma + Neto -->
                        <div class="form-row">
                            <div class="field-group">
                                <label class="field-label">{{ isUnitMode ? 'Unidades recibidas *' : 'Kilos recibidos *' }}</label>
                                <input v-model="form.quantity_kg" type="number"
                                    :step="isUnitMode ? '1' : '0.001'"
                                    :min="isUnitMode ? '1' : '0.001'"
                                    class="field-input" :class="{ 'field-error': form.errors.quantity_kg }"
                                    :placeholder="isUnitMode ? '0' : '0.000'" />
                                <span v-if="form.errors.quantity_kg" class="error-msg">{{ form.errors.quantity_kg }}</span>
                            </div>
                            <div v-if="!isUnitMode" class="field-group">
                                <label class="field-label">Merma / hueso / pérdida (kg)</label>
                                <input v-model="form.waste_kg" type="number" step="0.001" min="0"
                                    class="field-input" :class="{ 'field-error': form.errors.waste_kg }"
                                    placeholder="0.000" />
                                <span v-if="form.errors.waste_kg" class="error-msg">{{ form.errors.waste_kg }}</span>
                            </div>
                            <div class="field-group">
                                <label class="field-label">{{ isUnitMode ? 'Total' : 'Neto calculado' }}</label>
                                <div class="field-readonly">{{ netKg }} {{ isUnitMode ? 'und' : 'kg' }}</div>
                            </div>
                        </div>

                        <!-- Fila 3: Costo + Proveedor + Fecha -->
                        <div class="form-row">
                            <div class="field-group">
                                <label class="field-label">{{ isUnitMode ? 'Costo por unidad ($)' : 'Costo por kg ($)' }}</label>
                                <input v-model="form.cost_per_kg_usd" type="number" step="0.01" min="0"
                                    class="field-input" placeholder="0.00 (opcional)" @input="syncBsCost" />
                                <div class="price-pivot">
                                    <input
                                        v-model="bsCostPrice"
                                        type="number"
                                        step="0.01"
                                        class="field-input field-input--bs"
                                        placeholder="Equivalente automático"
                                        @input="syncUsdCost"
                                    />
                                    <span class="rate-hint">Bs. (referencia) — Tasa: {{ dollarRate.toFixed(2) }}</span>
                                </div>
                            </div>
                            <div class="field-group field-grow">
                                <label class="field-label">Proveedor</label>
                                <input v-model="form.supplier" type="text" maxlength="150"
                                    class="field-input" placeholder="Nombre del proveedor (opcional)" />
                            </div>
                            <div class="field-group">
                                <label class="field-label">Fecha y hora *</label>
                                <input v-model="form.entered_at" type="datetime-local"
                                    class="field-input" :class="{ 'field-error': form.errors.entered_at }" />
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" @click="closeModal">Cancelar</button>
                            <button type="submit" class="btn btn-primary" :disabled="form.processing">
                                Registrar Entrada
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ── Modal Reciclar Remanente ──────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showReciclar" class="modal-overlay" @click.self="closeReciclar">
                <div class="modal-box">

                    <!-- PASO 1 — Formulario -->
                    <template v-if="reciclarStep === 1">
                        <h2 class="modal-title">Reciclar remanente</h2>
                        <div class="reciclar-info">
                            <div class="field-group">
                                <label class="field-label">Producto</label>
                                <div class="field-readonly">{{ reciclarProd?.name }}</div>
                            </div>
                            <div class="form-row">
                                <div class="field-group">
                                    <label class="field-label">Stock disponible</label>
                                    <div class="field-readonly mono">{{ fmtKg(reciclarKg) }} kg</div>
                                </div>
                                <div class="field-group">
                                    <label class="field-label">Costo actual (ref.)</label>
                                    <div class="field-readonly mono">
                                        {{ getLastCost(reciclarProd) ? fmtUsd(getLastCost(reciclarProd)) + '/kg' : '—' }}
                                    </div>
                                </div>
                            </div>
                            <div class="field-group">
                                <label class="field-label">Nuevo costo por kg (USD) *</label>
                                <input
                                    v-model="reciclarCosto"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    class="field-input"
                                    placeholder="0.00"
                                />
                            </div>
                            <div class="field-group">
                                <label class="field-label">Motivo (opcional)</label>
                                <input
                                    v-model="reciclarNotas"
                                    type="text"
                                    maxlength="200"
                                    class="field-input"
                                    placeholder="Ej: carne de ayer"
                                />
                            </div>
                            <p v-if="reciclarError" class="error-msg">{{ reciclarError }}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" @click="closeReciclar">Cancelar</button>
                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="!reciclarCosto || parseFloat(reciclarCosto) <= 0"
                                @click="reciclarStep = 2"
                            >
                                Continuar →
                            </button>
                        </div>
                    </template>

                    <!-- PASO 2 — Confirmación -->
                    <template v-if="reciclarStep === 2">
                        <h2 class="modal-title">Confirmar reciclaje</h2>
                        <div class="reciclar-confirm">
                            <p class="confirm-line">
                                <strong>{{ fmtKg(reciclarKg) }} kg</strong> de
                                <strong>{{ reciclarProd?.name }}</strong>
                                a <strong>{{ fmtUsd(parseFloat(reciclarCosto)) }}/kg</strong>
                            </p>
                            <p class="confirm-line">
                                Costo total nuevo lote:
                                <strong>{{ fmtUsd(parseFloat(reciclarTotal)) }}</strong>
                            </p>
                            <p class="confirm-warn">
                                Esta acción no se puede deshacer. Se cerrará el lote actual y se creará uno nuevo con el costo indicado.
                            </p>
                            <p v-if="reciclarError" class="error-msg">{{ reciclarError }}</p>
                        </div>
                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn btn-ghost"
                                :disabled="reciclarLoading"
                                @click="reciclarStep = 1"
                            >
                                ← Atrás
                            </button>
                            <button
                                type="button"
                                class="btn btn-danger"
                                :disabled="reciclarLoading"
                                @click="submitReciclar"
                            >
                                {{ reciclarLoading ? 'Procesando...' : 'Confirmar' }}
                            </button>
                        </div>
                    </template>

                </div>
            </div>
        </Teleport>

        <!-- ── Panel de ayuda ────────────────────────────────────────────── -->
        <HelpModal
            :show="showHelp"
            title="Inventario — Cómo funciona"
            :steps="helpSteps"
            :faqs="helpFaqs"
            @close="showHelp = false"
        />

    </AppLayout>
</template>

<style scoped>
/* ─── Wrap ───────────────────────────────────────────────────────────────── */
.inv-wrap {
    max-width: 1100px;
    margin: 0 auto;
    padding: 1rem 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
@media (min-width: 640px) {
    .inv-wrap { padding: 2rem 1rem; gap: 1.5rem; }
}

/* ─── KPIs ───────────────────────────────────────────────────────────────── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}
@media (min-width: 768px) { .kpi-grid { grid-template-columns: repeat(4, 1fr); gap: 1rem; } }
.kpi-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    padding: 1.25rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.kpi-card--alert { border-color: #ef4444; }
.kpi-label { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.kpi-value { font-size: 1.6rem; font-weight: 800; color: var(--text-primary); line-height: 1; display: flex; align-items: baseline; gap: 0.3rem; }
.kpi-value small { font-size: 0.8rem; font-weight: 400; color: var(--text-muted); }
.badge-alert { background: #ef4444; color: #fff; font-size: 0.65rem; font-weight: 700; padding: 0.15rem 0.4rem; border-radius: 999px; text-transform: uppercase; }

/* ─── Filter bar ─────────────────────────────────────────────────────────── */
.filter-bar {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}
.filter-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 200px;
}
.filter-search-icon {
    position: absolute;
    left: 0.65rem;
    width: 1rem;
    height: 1rem;
    color: var(--text-muted);
    pointer-events: none;
    flex-shrink: 0;
}
.filter-search {
    width: 100%;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.65rem 2rem 0.65rem 2.1rem;
    color: var(--text-primary);
    font-size: 0.9rem;
    font-family: inherit;
    outline: none;
    transition: border-color 0.15s;
    min-height: 44px;
}
.filter-search:focus { border-color: var(--brand); }
.filter-search::-webkit-search-cancel-button { display: none; }
.filter-clear-x {
    position: absolute;
    right: 0.5rem;
    background: transparent;
    border: none;
    color: var(--text-muted);
    font-size: 1.1rem;
    cursor: pointer;
    padding: 0 0.2rem;
    line-height: 1;
}
.filter-clear-x:hover { color: var(--text-primary); }
.filter-select {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.55rem 0.75rem;
    color: var(--text-primary);
    font-size: 0.875rem;
    font-family: inherit;
    outline: none;
    cursor: pointer;
    transition: border-color 0.15s;
}
.filter-select:focus { border-color: var(--brand); }
.filter-reset {
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    color: var(--text-muted);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: color 0.15s, border-color 0.15s;
}
.filter-reset:hover { color: var(--text-primary); border-color: var(--text-muted); }
.filter-count {
    font-size: 0.8rem;
    color: var(--text-muted);
    white-space: nowrap;
    margin-left: auto;
}
.filter-cta { flex-shrink: 0; }

/* sortable column headers */
.th-sort { cursor: pointer; user-select: none; }
.th-sort:hover { color: var(--text-primary); }
.sort-icon { font-style: normal; margin-left: 0.2rem; opacity: 0.6; font-size: 0.8em; }

/* ─── Table card ─────────────────────────────────────────────────────────── */
.table-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    overflow: hidden;
}
.table-wrap { overflow-x: auto; }
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.data-table th {
    text-align: left;
    padding: 0.7rem 0.875rem;
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
    background: color-mix(in srgb, var(--bg-base) 60%, transparent);
}
.data-table td {
    padding: 0.75rem 0.875rem;
    color: var(--text-primary);
    border-bottom: 1px solid color-mix(in srgb, var(--border) 50%, transparent);
    vertical-align: middle;
}
.data-table tbody tr:last-child td { border-bottom: none; }
.row-clickable { cursor: pointer; transition: background 0.1s; }
.row-clickable:hover { background: color-mix(in srgb, var(--brand) 6%, transparent); }
.row-arrow { font-size: 1.1rem; color: var(--text-muted); text-align: right; }
.txt-right { text-align: right; }
.txt-muted { color: var(--text-muted); }
.mono { font-variant-numeric: tabular-nums; }
.prod-name { font-weight: 600; }
.empty-cell { text-align: center; color: var(--text-muted); padding: 2rem; font-size: 0.875rem; }

/* ─── Status badges ──────────────────────────────────────────────────────── */
.status-badge {
    display: inline-block;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.status-ok    { background: color-mix(in srgb, #22c55e 18%, transparent); color: #16a34a; }
.status-low   { background: color-mix(in srgb, #f59e0b 18%, transparent); color: #d97706; }
.status-empty { background: color-mix(in srgb, #ef4444 18%, transparent); color: #dc2626; }

/* ─── Category dot ───────────────────────────────────────────────────────── */
.cat-dot {
    display: inline-block;
    width: 0.6rem;
    height: 0.6rem;
    border-radius: 50%;
    margin-right: 0.35rem;
    vertical-align: middle;
    flex-shrink: 0;
}

/* ─── Pagination ─────────────────────────────────────────────────────────── */
.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 0.875rem;
    border-top: 1px solid var(--border);
}
.page-btn {
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 0.375rem;
    padding: 0.3rem 0.75rem;
    color: var(--text-primary);
    font-size: 1rem;
    cursor: pointer;
    transition: background 0.15s;
}
.page-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.page-btn:not(:disabled):hover { background: color-mix(in srgb, var(--brand) 12%, transparent); }
.page-info { font-size: 0.8rem; color: var(--text-muted); }

/* ─── Drawer ─────────────────────────────────────────────────────────────── */
.drawer-overlay {
    position: fixed;
    inset: 0;
    background: color-mix(in srgb, #000 45%, transparent);
    z-index: 40;
    display: flex;
    justify-content: flex-end;
}
.drawer {
    width: min(420px, 92vw);
    height: 100%;
    background: var(--bg-card);
    border-left: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    padding: 1.5rem;
    gap: 1.25rem;
}

/* drawer transition */
.drawer-enter-active, .drawer-leave-active { transition: transform 0.25s ease; }
.drawer-enter-from .drawer, .drawer-leave-to .drawer { transform: translateX(100%); }

.drawer-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}
.drawer-cat {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin: 0 0 0.3rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.drawer-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}
.drawer-close {
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 0.375rem;
    color: var(--text-muted);
    font-size: 0.9rem;
    padding: 0.3rem 0.6rem;
    cursor: pointer;
    flex-shrink: 0;
}

.drawer-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}
.dstat {
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.dstat-label { font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
.dstat-value { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); }
.dstat-value small { font-size: 0.75rem; font-weight: 400; color: var(--text-muted); }
.dstat-ok    { color: #16a34a; }
.dstat-low   { color: #d97706; }
.dstat-empty { color: #dc2626; }

.drawer-section {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 0 0 0.75rem;
}
.drawer-history { display: flex; flex-direction: column; }

.history-list { display: flex; flex-direction: column; gap: 0.5rem; }
.history-row {
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.history-meta { display: flex; align-items: center; gap: 0.5rem; }
.h-time { font-size: 0.75rem; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.badge-entry    { background: color-mix(in srgb, #3b82f6 18%, transparent); color: #2563eb; font-size: 0.68rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 999px; text-transform: uppercase; }
.badge-fabrica  { background: color-mix(in srgb, #8b5cf6 18%, transparent); color: #7c3aed; font-size: 0.68rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 999px; text-transform: uppercase; }
.badge-consumo  { background: color-mix(in srgb, #ef4444 15%, transparent); color: #dc2626; font-size: 0.68rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 999px; text-transform: uppercase; }
.badge-despiece { background: color-mix(in srgb, #f59e0b 18%, transparent); color: #d97706; font-size: 0.68rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 999px; text-transform: uppercase; }
.history-nums { display: flex; flex-wrap: wrap; gap: 0.5rem; font-size: 0.82rem; font-variant-numeric: tabular-nums; }
.h-qty { color: var(--text-primary); font-weight: 600; }
.h-qty--neg { color: #dc2626; }
.h-waste { color: #f59e0b; }
.h-net { color: #16a34a; font-weight: 600; }
.h-notes { font-size: 0.72rem; color: var(--text-muted); font-style: italic; }
.h-supplier { font-size: 0.75rem; color: var(--text-muted); }

/* ─── Modal ──────────────────────────────────────────────────────────────── */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: color-mix(in srgb, #000 60%, transparent);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
    padding: 1rem;
}
.modal-box {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 1rem;
    padding: 1.5rem;
    width: 100%;
    max-width: 720px;
    box-shadow: 0 8px 32px color-mix(in srgb, #000 40%, transparent);
    max-height: 90dvh;
    overflow-y: auto;
}
.modal-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 1.25rem;
}
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border);
}

/* ─── Entry form ─────────────────────────────────────────────────────────── */
.entry-form { display: flex; flex-direction: column; gap: 0.75rem; }
.form-row { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.field-group { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; min-width: min(150px, 100%); }
.field-grow { flex: 2; }
.field-label { font-size: 0.78rem; font-weight: 600; color: var(--text-muted); }
.field-input {
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    color: var(--text-primary);
    font-size: 0.9rem;
    font-family: inherit;
    outline: none;
    transition: border-color 0.15s;
    width: 100%;
    box-sizing: border-box;
}
.field-input:focus { border-color: var(--brand); }
.field-error { border-color: #ef4444; }
.field-readonly {
    background: color-mix(in srgb, var(--bg-base) 60%, transparent);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    color: var(--brand);
    font-size: 0.9rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}
.error-msg { font-size: 0.72rem; color: #ef4444; }

/* ─── Buttons ─────────────────────────────────────────────────────────────── */
.btn {
    padding: 0.6rem 1.1rem;
    border-radius: 0.5rem;
    border: 1px solid transparent;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.15s;
    white-space: nowrap;
    font-family: inherit;
    min-height: 44px;
}
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary { background: var(--brand); color: #fff; border-color: var(--brand); }
.btn-ghost { background: transparent; color: var(--text-primary); border-color: var(--border); }
.btn-full { width: 100%; justify-content: center; }

/* ─── Empty msg ──────────────────────────────────────────────────────────── */
.empty-msg { text-align: center; color: var(--text-muted); padding: 1rem 0; font-size: 0.85rem; }

/* ─── Botón ayuda ────────────────────────────────────────────────────────── */
.btn-help {
    border-radius: 50%;
    width: 2rem; height: 2rem;
    padding: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text-muted);
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.btn-help:hover { background: var(--brand); color: #fff; border-color: var(--brand); }

/* ─── Mobile: card view ──────────────────────────────────────────────────── */
/* Base (mobile): show cards, hide table */
.table-wrap { display: none; }
.mobile-cards {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.75rem;
}
.mc-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 0.6rem;
    padding: 0.75rem;
    cursor: pointer;
    min-height: 44px;
    transition: background 0.1s;
}
.mc-row:hover { background: color-mix(in srgb, var(--brand) 6%, transparent); }
.mc-body { flex: 1; min-width: 0; }
.mc-name { font-size: 0.9rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mc-cat  { font-size: 0.72rem; color: var(--text-muted); margin-top: 0.1rem; display: flex; align-items: center; gap: 0.25rem; }
.mc-right { text-align: right; flex-shrink: 0; }
.mc-stock { font-size: 1rem; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; }
.mc-stock small { font-size: 0.7rem; font-weight: 400; color: var(--text-muted); }
.mc-arrow { font-size: 1rem; color: var(--text-muted); margin-left: 0.25rem; }

/* Tablet+: show table, hide cards */
@media (min-width: 640px) {
    .filter-count { display: inline; }
    .table-wrap { display: block; }
    .mobile-cards { display: none; }
}

/* Mobile: iOS auto-zoom fix (inputs < 16px) + touch targets */
@media (max-width: 640px) {
    .field-input  { font-size: 1rem; }
    .filter-select { font-size: 1rem; min-height: 44px; }
}

/* Tablet: mostrar tabla, pero con padding reducido */
@media (max-width: 1023px) {
    .inv-wrap { padding: 1rem 0.75rem; }
    /* modal: 1 col si el max-width es 720px — reducir */
    .modal-box { max-width: 95vw; }
    /* drawer: ancho completo en tablet chico */
    .drawer { width: min(380px, 94vw); }
    /* filter bar: alinear en columna */
    .filter-bar { flex-wrap: wrap; }
    .filter-count { margin-left: 0; }
}

/* ─── Ajuste de stock ─────────────────────────────────────────────────────── */
.reciclar-btn { display: flex; align-items: center; justify-content: center; gap: 0.4rem; color: var(--amber, #f59e0b); border-color: var(--amber, #f59e0b); }
.reciclar-btn:hover { background: rgba(245, 158, 11, 0.1); }
.btn-danger { background: var(--red); color: #fff; border-color: var(--red); }
.btn-danger:hover { opacity: 0.9; }
.btn-danger:disabled { opacity: 0.55; cursor: not-allowed; }
.reciclar-info, .reciclar-confirm { display: flex; flex-direction: column; gap: 0.85rem; margin-bottom: 1rem; }
.confirm-line { font-size: 1rem; color: var(--text-primary); }
.confirm-warn { font-size: 0.85rem; color: var(--text-muted); background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); border-radius: 0.4rem; padding: 0.6rem 0.75rem; }
.adjust-btn { display: flex; align-items: center; justify-content: center; gap: 0.4rem; }
.adjust-panel {
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.adjust-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    margin-top: 0.25rem;
}

/* ─── Pivot USD ↔ Bs ─────────────────────────────────────────────────────── */
.price-pivot {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-top: 0.25rem;
}
.field-input--bs {
    border-color: color-mix(in srgb, var(--brand) 35%, var(--border));
    background: color-mix(in srgb, var(--brand) 5%, var(--bg-base));
}
.field-input--bs:focus { border-color: var(--brand); }
.rate-hint {
    font-size: 0.7rem;
    color: var(--text-muted);
    font-variant-numeric: tabular-nums;
}

.btn-export {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.9rem;
    background: rgba(34, 197, 94, 0.12);
    color: #16a34a;
    border: 1px solid rgba(34, 197, 94, 0.3);
    border-radius: 0.5rem;
    font-size: 0.82rem;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-export:hover { background: rgba(34, 197, 94, 0.22); }
</style>