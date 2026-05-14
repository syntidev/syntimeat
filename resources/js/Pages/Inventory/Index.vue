<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    products:     Array,
    categories:   Array,
    todayEntries: Array,
    stockMap:     Object,
    lastEntryMap: Object,
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

const selectedCategory = ref('')

const filteredProducts = computed(() => {
    if (! selectedCategory.value) return props.products
    return props.products.filter(p => p.category_id === Number(selectedCategory.value))
})

const netKg = computed(() => {
    const qty   = parseFloat(form.quantity_kg) || 0
    const waste = parseFloat(form.waste_kg)    || 0
    return Math.max(0, qty - waste).toFixed(3)
})

function openModal(product = null) {
    form.reset()
    form.waste_kg   = 0
    form.entered_at = new Date().toISOString().slice(0, 16)
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

function fmtKg(val) {
    return Number(val).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 3 })
}

function fmtTime(dt) {
    if (! dt) return '—'
    return new Date(dt).toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit' })
}

function fmtDate(dt) {
    if (! dt) return '—'
    return new Date(dt).toLocaleDateString('es-VE', { day: '2-digit', month: 'short' })
}

function fmtUsd(val) {
    if (! val) return '—'
    return '$' + Number(val).toFixed(2)
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
                    <span class="kpi-label">Stock Disponible</span>
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

                <button class="btn btn-primary filter-cta" @click="openModal()">+ Nueva Entrada</button>
            </div>

            <!-- ── Tabla stock actual ─────────────────────────────────────────── -->
            <div class="table-card">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="th-sort" @click="toggleSort('name')">Producto <span class="sort-icon">{{ sortIcon('name') }}</span></th>
                                <th>Categoría</th>
                                <th class="txt-right th-sort" @click="toggleSort('stock')">Stock <span class="sort-icon">{{ sortIcon('stock') }}</span></th>
                                <th class="th-sort" @click="toggleSort('status')">Estado <span class="sort-icon">{{ sortIcon('status') }}</span></th>
                                <th class="th-sort" @click="toggleSort('last')">Última entrada <span class="sort-icon">{{ sortIcon('last') }}</span></th>
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
                                <td class="txt-right mono">
                                    {{ fmtKg(stockValue(p.id)) }}
                                    <small>{{ p.sale_mode === 'weight' ? 'kg' : 'und' }}</small>
                                </td>
                                <td>
                                    <span class="status-badge" :class="'status-' + stockStatus(p)">
                                        {{ stockLabel(p) }}
                                    </span>
                                </td>
                                <td class="txt-muted">{{ fmtDate(lastEntryMap[p.id]) }}</td>
                                <td class="txt-muted row-arrow">›</td>
                            </tr>
                            <tr v-if="pagedProducts.length === 0">
                                <td colspan="6" class="empty-cell">Sin productos que coincidan.</td>
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
                                {{ fmtKg(stockValue(p.id)) }}
                                <small>{{ p.sale_mode === 'weight' ? 'kg' : 'und' }}</small>
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
                <div v-if="drawerProduct" class="drawer-overlay" @click.self="closeDrawer">
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
                                    {{ fmtKg(stockValue(drawerProduct.id)) }}
                                    <small>{{ drawerProduct.sale_mode === 'weight' ? 'kg' : 'und' }}</small>
                                </span>
                            </div>
                            <div class="dstat">
                                <span class="dstat-label">Mínimo</span>
                                <span class="dstat-value">
                                    {{ parseFloat(drawerProduct.min_stock) > 0 ? fmtKg(drawerProduct.min_stock) + ' kg' : '—' }}
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
                                        <span class="h-type badge-entry">Entrada</span>
                                    </div>
                                    <div class="history-nums">
                                        <span class="h-qty">{{ fmtKg(e.quantity_kg) }} kg</span>
                                        <span v-if="parseFloat(e.waste_kg) > 0" class="h-waste">
                                            − {{ fmtKg(e.waste_kg) }} kg merma
                                        </span>
                                        <span class="h-net">Neto: {{ fmtKg(e.net_kg) }} kg</span>
                                    </div>
                                    <div v-if="e.supplier" class="h-supplier">{{ e.supplier }}</div>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Modal Nueva Entrada ────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
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

                        <!-- Fila 2: Kilos + Merma + Neto -->
                        <div class="form-row">
                            <div class="field-group">
                                <label class="field-label">Kilos recibidos *</label>
                                <input v-model="form.quantity_kg" type="number" step="0.001" min="0.001"
                                    class="field-input" :class="{ 'field-error': form.errors.quantity_kg }"
                                    placeholder="0.000" />
                                <span v-if="form.errors.quantity_kg" class="error-msg">{{ form.errors.quantity_kg }}</span>
                            </div>
                            <div class="field-group">
                                <label class="field-label">Merma / hueso / pérdida (kg)</label>
                                <input v-model="form.waste_kg" type="number" step="0.001" min="0"
                                    class="field-input" :class="{ 'field-error': form.errors.waste_kg }"
                                    placeholder="0.000" />
                                <span v-if="form.errors.waste_kg" class="error-msg">{{ form.errors.waste_kg }}</span>
                            </div>
                            <div class="field-group">
                                <label class="field-label">Neto calculado</label>
                                <div class="field-readonly">{{ netKg }} kg</div>
                            </div>
                        </div>

                        <!-- Fila 3: Costo + Proveedor + Fecha -->
                        <div class="form-row">
                            <div class="field-group">
                                <label class="field-label">Costo por kg ($)</label>
                                <input v-model="form.cost_per_kg_usd" type="number" step="0.01" min="0"
                                    class="field-input" placeholder="0.00 (opcional)" />
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

    </AppLayout>
</template>

<style scoped>
/* ─── Wrap ───────────────────────────────────────────────────────────────── */
.inv-wrap {
    max-width: 1100px;
    margin: 0 auto;
    padding: 2rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* ─── KPIs ───────────────────────────────────────────────────────────────── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
@media (max-width: 768px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
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
    padding: 0.55rem 2rem 0.55rem 2.1rem;
    color: var(--text-primary);
    font-size: 0.9rem;
    font-family: inherit;
    outline: none;
    transition: border-color 0.15s;
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
.badge-entry { background: color-mix(in srgb, #3b82f6 18%, transparent); color: #2563eb; font-size: 0.68rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 999px; text-transform: uppercase; }
.history-nums { display: flex; flex-wrap: wrap; gap: 0.5rem; font-size: 0.82rem; font-variant-numeric: tabular-nums; }
.h-qty { color: var(--text-primary); font-weight: 600; }
.h-waste { color: #f59e0b; }
.h-net { color: #16a34a; font-weight: 600; }
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
    max-height: 90vh;
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
.field-group { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; min-width: 150px; }
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
    padding: 0.5rem 1.1rem;
    border-radius: 0.5rem;
    border: 1px solid transparent;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.15s;
    white-space: nowrap;
    font-family: inherit;
}
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary { background: var(--brand); color: #fff; border-color: var(--brand); }
.btn-ghost { background: transparent; color: var(--text-primary); border-color: var(--border); }
.btn-full { width: 100%; justify-content: center; }

/* ─── Empty msg ──────────────────────────────────────────────────────────── */
.empty-msg { text-align: center; color: var(--text-muted); padding: 1rem 0; font-size: 0.85rem; }

/* ─── Mobile: card view ──────────────────────────────────────────────────── */
@media (max-width: 640px) {
    .inv-wrap { padding: 1rem 0.75rem; gap: 1rem; }
    .filter-bar { gap: 0.5rem; }
    .filter-count { display: none; }
    .filter-select { font-size: 0.8rem; padding: 0.45rem 0.5rem; }

    /* Hide table, show cards */
    .table-wrap { display: none; }
    .mobile-cards { display: flex; flex-direction: column; gap: 0.5rem; padding: 0.75rem; }

    .mc-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: var(--bg-base);
        border: 1px solid var(--border);
        border-radius: 0.6rem;
        padding: 0.75rem;
        cursor: pointer;
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
}
@media (min-width: 641px) {
    .mobile-cards { display: none; }
}
</style>