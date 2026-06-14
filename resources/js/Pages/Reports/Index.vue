<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HelpModal from '@/Components/HelpModal.vue'
import { ref, computed, reactive, watch, onMounted } from 'vue'
import { ChevronDown, ChevronRight } from '@lucide/vue'
import axios from 'axios'

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    categories: { type: Array, default: () => [] },
    products:   { type: Array, default: () => [] },
})

// ─── Estado global ────────────────────────────────────────────────────────────
const activeTab  = ref('ventas')
const loading    = ref(false)
const errorMsg   = ref('')
const PAGE_SIZE  = 50

// ─── Filtros por tab ──────────────────────────────────────────────────────────
const filters = reactive({
    ventas:      { fecha_desde: '', fecha_hasta: '', category_id: '', status: '' },
    inventario:  { fecha_desde: '', fecha_hasta: '', product_id: '', category_id: '' },
    cierres:     { fecha_desde: '', fecha_hasta: '' },
    pedidos:     { fecha_desde: '', fecha_hasta: '', client_type: '', status: '' },
})

// ─── Datos y paginación ───────────────────────────────────────────────────────
const rows  = reactive({ ventas: [], inventario: [], cierres: [], pedidos: [], reporte_dia: [] })
const pages = reactive({ ventas: 1, inventario: 1, cierres: 1, pedidos: 1 })

const totalPages = computed(() => Math.max(1, Math.ceil(rows[activeTab.value].length / PAGE_SIZE)))
const currentPage = computed({
    get: () => pages[activeTab.value],
    set: (v) => { pages[activeTab.value] = v },
})
const pageRows = computed(() => {
    const all = rows[activeTab.value]
    const start = (currentPage.value - 1) * PAGE_SIZE
    return all.slice(start, start + PAGE_SIZE)
})

// Resetear página al cambiar tab
watch(activeTab, (val) => { currentPage.value = 1; errorMsg.value = ''; if (val === 'reporte_dia') loadDayReport() })
onMounted(() => { if (activeTab.value === 'reporte_dia') loadDayReport() })

// ─── Cargar datos ─────────────────────────────────────────────────────────────
const routeMap = {
    ventas:     'reports.sales',
    inventario: 'reports.inventory',
    cierres:    'reports.closings',
    pedidos:    'reports.orders',
}

async function loadData() {
    loading.value = true
    errorMsg.value = ''
    try {
        const res = await axios.get(route(routeMap[activeTab.value]), {
            params: clean(filters[activeTab.value]),
        })
        rows[activeTab.value] = res.data.data
        currentPage.value = 1
    } catch {
        errorMsg.value = 'Error al cargar los datos.'
    } finally {
        loading.value = false
    }
}

function clean(obj) {
    return Object.fromEntries(Object.entries(obj).filter(([, v]) => v !== ''))
}

// ─── Exportar ─────────────────────────────────────────────────────────────────
function exportExcel() {
    const params = new URLSearchParams({
        tipo: activeTab.value,
        ...clean(filters[activeTab.value]),
    })
    window.location.href = route('reports.export') + '?' + params.toString()
}

// ─── Reporte del Día ──────────────────────────────────────────────────────────
const dayFilters = reactive({
    fecha:        new Date().toISOString().split('T')[0],
    category_ids: [],
})
const dayData    = ref({ categories: [], totals: {} })
const dayLoading = ref(false)
const dayError   = ref('')
const expandedCats = ref(new Set())
function toggleCat(cat) { expandedCats.value.has(cat) ? expandedCats.value.delete(cat) : expandedCats.value.add(cat) }

const maxUtilidad = computed(() =>
    Math.max(...(dayData.value.categories ?? []).map(r => Math.abs(r.utilidad_usd)), 1)
)

function barWidth(utilidad) {
    return Math.round((Math.abs(utilidad) / maxUtilidad.value) * 100)
}

async function loadDayReport() {
    dayLoading.value = true
    dayError.value   = ''
    try {
        const params = { fecha: dayFilters.fecha }
        if (dayFilters.category_ids.length) params.category_ids = dayFilters.category_ids
        const res = await axios.get(route('reports.day'), { params })
        dayData.value = res.data
    } catch {
        dayError.value = 'Error al cargar el reporte.'
    } finally {
        dayLoading.value = false
    }
}

function exportDayPdf() {
    const params = new URLSearchParams({ fecha: dayFilters.fecha })
    dayFilters.category_ids.forEach(id => params.append('category_ids[]', id))
    window.location.href = route('reports.day-pdf') + '?' + params.toString()
}

function exportDayExcel() {
    const params = new URLSearchParams({
        tipo: 'ventas',
        fecha_desde: dayFilters.fecha,
        fecha_hasta: dayFilters.fecha,
    })
    window.location.href = route('reports.export') + '?' + params.toString()
}

// ─── Formato ──────────────────────────────────────────────────────────────────
function fmtBs(n)  { return 'Bs. ' + Number(n ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
function fmtUsd(n) { return '$' + Number(n ?? 0).toFixed(2) }
function fmtKg(n)  { return Number(n ?? 0).toFixed(3) + ' kg' }
function fmtDate(d) { return d ? new Date(d).toLocaleString('es-VE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—' }

const statusLabel = { open: 'Abierto', pending: 'Pendiente', paid: 'Pagado', cancelled: 'Cancelado' }
const statusClass = { open: 'pill-open', pending: 'pill-pending', paid: 'pill-paid', cancelled: 'pill-cancelled' }
const typeLabel   = { internal: 'Interno', external: 'Externo' }
const typeClass   = { internal: 'badge-amber', external: 'badge-blue' }

// ─── Ayuda ────────────────────────────────────────────────────────────────────
const showHelp = ref(false)

const helpSteps = [
    {
        title: 'Seleccionar rango de fechas',
        body: 'Elige el período que quieres analizar: hoy, esta semana, este mes o un rango personalizado.',
        tip: 'Los reportes incluyen solo ventas con status "cobrado".',
    },
    {
        title: 'Reporte de ventas',
        body: 'Muestra el total vendido, tickets emitidos y promedio por venta en el período seleccionado.',
        tip: 'Filtra por categoría para ver qué corte vende más.',
    },
    {
        title: 'Reporte por categoría',
        body: 'Desglosa las ventas por Res, Pollo, Cerdo, Charcutería, etc. Con kg vendidos, monto en USD y participación porcentual.',
        tip: 'Compara semanas para detectar tendencias de consumo.',
    },
    {
        title: 'Exportar',
        body: 'Descarga el reporte en PDF para imprimir o en Excel para analizar en detalle.',
        tip: 'El Excel incluye el detalle por producto y por día.',
    },
]

const helpFaqs = [
    {
        q: '¿Los reportes incluyen ventas canceladas?',
        a: 'No. Solo ventas con status "cobrado" aparecen en reportes.',
    },
    {
        q: '¿Puedo ver reportes de otro mes?',
        a: 'Sí, usa el selector de fechas personalizado.',
    },
    {
        q: '¿El reporte incluye el costo?',
        a: 'Sí, si la bóveda tiene el costo registrado muestra la utilidad bruta.',
    },
    {
        q: '¿Puedo filtrar por cajero?',
        a: 'Sí, hay un filtro por usuario en la pestaña de detalle.',
    },
    {
        q: '¿El export PDF es el mismo que el ticket?',
        a: 'No, es un reporte gerencial con resumen del período.',
    },
]
</script>

<template>
    <AppLayout title="Reportes e Historial">
        <div class="rep-wrap">

            <!-- ─── Tabs ─────────────────────────────────────────────────── -->
            <div class="tab-bar">
                <button v-for="t in ['ventas','inventario','cierres','pedidos','reporte_dia']" :key="t"
                    class="tab" :class="{ 'tab-active': activeTab === t }"
                    @click="activeTab = t">
                    {{ { ventas: 'Ventas', inventario: 'Inventario', cierres: 'Cierres de Caja', pedidos: 'Pedidos', reporte_dia: 'Reporte del Día' }[t] }}
                </button>
                <button class="tab tab-help" @click="showHelp = true" title="Ayuda">?</button>
            </div>

            <!-- ─── Filtros + acciones ────────────────────────────────────── -->
            <div class="filter-bar">

                <!-- Fechas (común) — solo tabs normales -->
                <template v-if="activeTab !== 'reporte_dia'">
                <div class="filter-group">
                    <label class="filter-label">Desde</label>
                    <input v-model="filters[activeTab].fecha_desde" type="date" class="filter-input" />
                </div>
                <div class="filter-group">
                    <label class="filter-label">Hasta</label>
                    <input v-model="filters[activeTab].fecha_hasta" type="date" class="filter-input" />
                </div>
                </template>

                <!-- Reporte del Día: fecha única + categorías -->
                <template v-if="activeTab === 'reporte_dia'">
                <div class="filter-group">
                    <label class="filter-label">Fecha</label>
                    <input v-model="dayFilters.fecha" type="date" class="filter-input" />
                </div>
                <div class="filter-group">
                    <label class="filter-label">Categorías</label>
                    <div class="cat-checks">
                        <label v-for="c in categories" :key="c.id" class="cat-check-label">
                            <input type="checkbox" :value="c.id" v-model="dayFilters.category_ids" class="cat-check-input" />
                            {{ c.name }}
                        </label>
                    </div>
                </div>
                </template>

                <!-- Ventas: categoría + estado -->
                <template v-if="activeTab === 'ventas'">
                    <div class="filter-group">
                        <label class="filter-label">Categoría</label>
                        <select v-model="filters.ventas.category_id" class="filter-input">
                            <option value="">Todas</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Estado</label>
                        <select v-model="filters.ventas.status" class="filter-input">
                            <option value="">Todos</option>
                            <option value="paid">Pagado</option>
                            <option value="open">Abierto</option>
                            <option value="cancelled">Cancelado</option>
                        </select>
                    </div>
                </template>

                <!-- Inventario: producto + categoría -->
                <template v-if="activeTab === 'inventario'">
                    <div class="filter-group">
                        <label class="filter-label">Categoría</label>
                        <select v-model="filters.inventario.category_id" class="filter-input">
                            <option value="">Todas</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Producto</label>
                        <select v-model="filters.inventario.product_id" class="filter-input">
                            <option value="">Todos</option>
                            <option
                                v-for="p in (filters.inventario.category_id
                                    ? products.filter(x => x.category_id == filters.inventario.category_id)
                                    : products)"
                                :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                </template>

                <!-- Pedidos: tipo + estado -->
                <template v-if="activeTab === 'pedidos'">
                    <div class="filter-group">
                        <label class="filter-label">Tipo</label>
                        <select v-model="filters.pedidos.client_type" class="filter-input">
                            <option value="">Todos</option>
                            <option value="external">Externo</option>
                            <option value="internal">Interno</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Estado</label>
                        <select v-model="filters.pedidos.status" class="filter-input">
                            <option value="">Todos</option>
                            <option value="pending">Pendiente</option>
                            <option value="paid">Cobrado</option>
                            <option value="cancelled">Cancelado</option>
                        </select>
                    </div>
                </template>

                <div class="filter-actions">
                    <template v-if="activeTab !== 'reporte_dia'">
                        <button class="btn-brand" :disabled="loading" @click="loadData">
                            {{ loading ? 'Cargando…' : 'Consultar' }}
                        </button>
                        <button class="btn-outline" @click="exportExcel">↓ Excel</button>
                    </template>
                    <template v-else>
                        <button class="btn-brand" :disabled="dayLoading" @click="loadDayReport">
                            {{ dayLoading ? 'Cargando…' : 'Consultar' }}
                        </button>
                        <button class="btn-outline" @click="exportDayPdf">↓ PDF del día</button>
                        <button class="btn-outline" @click="exportDayExcel">↓ Excel</button>
                    </template>
                </div>
            </div>

            <!-- ─── Error ──────────────────────────────────────────────────── -->
            <p v-if="errorMsg" class="error-msg">{{ errorMsg }}</p>

            <!-- ─── Resultados ────────────────────────────────────────────── -->
            <div class="card">
                <!-- Contador -->
                <div class="results-header" v-if="activeTab !== 'reporte_dia'">
                    <span class="results-count">
                        {{ rows[activeTab].length }} registros
                        <span v-if="rows[activeTab].length > PAGE_SIZE">
                            — pág. {{ currentPage }}/{{ totalPages }}
                        </span>
                    </span>
                </div>

                <!-- ── TAB VENTAS ────────────────────────────────────────── -->
                <div v-if="activeTab === 'ventas'" class="table-wrap">
                    <table class="rep-table mobile-cards">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th class="center">Items</th>
                                <th>Métodos Pago</th>
                                <th class="right">Total USD</th>
                                <th class="right">Total Bs.</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in pageRows" :key="r.id">
                                <td class="mono" data-label="Ticket">{{ r.ticket_number }}</td>
                                <td class="muted" data-label="Fecha">{{ fmtDate(r.fecha) }}</td>
                                <td data-label="Cliente">{{ r.client_name }}</td>
                                <td class="center muted" data-label="Items">{{ r.items_count }}</td>
                                <td class="muted small" data-label="Métodos">{{ r.metodos_pago }}</td>
                                <td class="right amount" data-label="Total USD">{{ fmtUsd(r.total_usd) }}</td>
                                <td class="right muted" data-label="Total Bs.">{{ fmtBs(r.total_bs) }}</td>
                                <td data-label="Estado"><span class="pill" :class="statusClass[r.status]">{{ statusLabel[r.status] ?? r.status }}</span></td>
                            </tr>
                            <tr v-if="!rows.ventas.length && !loading">
                                <td colspan="8" class="empty-row">Aplica filtros y presiona Consultar.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ── TAB INVENTARIO ────────────────────────────────────── -->
                <div v-else-if="activeTab === 'inventario'" class="table-wrap">
                    <table class="rep-table mobile-cards">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th class="right">Recibido</th>
                                <th class="right">Merma</th>
                                <th class="right">Neto</th>
                                <th class="right">Costo USD/kg</th>
                                <th>Proveedor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in pageRows" :key="r.id">
                                <td class="muted" data-label="Fecha">{{ fmtDate(r.fecha) }}</td>
                                <td data-label="Producto">{{ r.producto }}</td>
                                <td class="muted small" data-label="Categoría">{{ r.categoria }}</td>
                                <td class="right" data-label="Recibido">{{ r.sale_mode === 'unit' ? parseInt(r.recibido) + ' und' : fmtKg(r.recibido) }}</td>
                                <td class="right muted" data-label="Merma">{{ fmtKg(r.merma) }}</td>
                                <td class="right amount" data-label="Neto">{{ fmtKg(r.neto) }}</td>
                                <td class="right muted" data-label="Costo USD/kg">{{ fmtUsd(r.costo_usd) }}</td>
                                <td class="muted small" data-label="Proveedor">{{ r.proveedor }}</td>
                            </tr>
                            <tr v-if="!rows.inventario.length && !loading">
                                <td colspan="8" class="empty-row">Aplica filtros y presiona Consultar.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ── TAB CIERRES ────────────────────────────────────────── -->
                <div v-else-if="activeTab === 'cierres'" class="table-wrap">
                    <table class="rep-table mobile-cards">
                        <thead>
                            <tr>
                                <th>Caja</th>
                                <th>Apertura</th>
                                <th>Cierre</th>
                                <th class="right">Apertura Bs.</th>
                                <th class="right">Esperado USD</th>
                                <th class="right">Contado USD</th>
                                <th class="right">Diferencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in pageRows" :key="r.id">
                                <td data-label="Caja">{{ r.nombre }}</td>
                                <td class="muted" data-label="Apertura">{{ fmtDate(r.abierto_at) }}</td>
                                <td class="muted" data-label="Cierre">{{ fmtDate(r.cerrado_at) }}</td>
                                <td class="right" data-label="Apertura Bs.">{{ fmtBs(r.apertura_usd * r.tasa) }}</td>
                                <td class="right muted" data-label="Esperado USD">{{ fmtUsd(r.esperado_usd) }}</td>
                                <td class="right" data-label="Contado USD">{{ fmtUsd(r.contado_usd) }}</td>
                                <td class="right" data-label="Diferencia" :class="r.diferencia_usd < 0 ? 'text-red' : r.diferencia_usd > 0 ? 'text-green' : 'muted'">
                                    {{ fmtUsd(r.diferencia_usd) }}
                                </td>
                            </tr>
                            <tr v-if="!rows.cierres.length && !loading">
                                <td colspan="7" class="empty-row">Aplica filtros y presiona Consultar.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ── TAB PEDIDOS ────────────────────────────────────────── -->
                <div v-else-if="activeTab === 'pedidos'" class="table-wrap">
                    <table class="rep-table mobile-cards">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Tipo</th>
                                <th class="center">Items</th>
                                <th class="right">Total USD</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in pageRows" :key="r.id">
                                <td class="muted" data-label="Fecha">{{ fmtDate(r.fecha) }}</td>
                                <td data-label="Cliente">{{ r.cliente }}</td>
                                <td data-label="Tipo">
                                    <span class="type-badge" :class="typeClass[r.tipo]">
                                        {{ typeLabel[r.tipo] ?? r.tipo }}
                                    </span>
                                </td>
                                <td class="center muted" data-label="Items">{{ r.items_count }}</td>
                                <td class="right amount" data-label="Total USD">{{ fmtUsd(r.total_usd) }}</td>
                                <td data-label="Estado"><span class="pill" :class="statusClass[r.status]">{{ statusLabel[r.status] ?? r.status }}</span></td>
                            </tr>
                            <tr v-if="!rows.pedidos.length && !loading">
                                <td colspan="6" class="empty-row">Aplica filtros y presiona Consultar.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ── TAB REPORTE DEL DÍA ──────────────────────────────── -->
                <div v-else-if="activeTab === 'reporte_dia'" class="dia-wrap">
                    <p v-if="dayError" class="error-msg">{{ dayError }}</p>

                    <div v-if="dayData.categories && dayData.categories.length > 0">
                        <!-- Tabla por categoría -->
                        <table class="rep-table dia-table mobile-cards">
                            <thead>
                                <tr>
                                    <th>Categoría</th>
                                    <th class="right">Vendido USD</th>
                                    <th class="right">Vendido Bs.</th>
                                    <th class="right">Costo USD</th>
                                    <th class="right">Utilidad USD</th>
                                    <th class="right">Margen %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="row in dayData.categories" :key="row.categoria">
                                <tr class="cat-row" style="cursor:pointer" @click="toggleCat(row.categoria)">
                                    <td data-label="Categoría">
                                        <span class="cat-toggle-icon">
                                            <ChevronDown v-if="expandedCats.has(row.categoria)" :size="14" />
                                            <ChevronRight v-else :size="14" />
                                        </span>
                                        {{ row.categoria }}
                                    </td>
                                    <td class="right amount" data-label="Vendido USD">{{ fmtUsd(row.vendido_usd) }}</td>
                                    <td class="right muted" data-label="Vendido Bs.">{{ fmtBs(row.vendido_bs) }}</td>
                                    <td class="right muted" data-label="Costo USD">{{ fmtUsd(row.costo_usd) }}</td>
                                    <td class="right" data-label="Utilidad USD" :class="row.utilidad_usd >= 0 ? 'text-green' : 'text-red'">{{ fmtUsd(row.utilidad_usd) }}</td>
                                    <td class="right" data-label="Margen %">{{ row.margen_pct }}%</td>
                                </tr>
                                <template v-if="expandedCats.has(row.categoria)">
                                    <tr v-for="p in row.productos" :key="p.producto" class="prod-row">
                                        <td class="prod-name" data-label="Producto">↳ {{ p.producto }}<span class="prod-kg">{{ p.sale_mode === 'unit' ? Math.round(p.kg) + ' und' : fmtKg(p.kg) }}</span></td>
                                        <td class="right amount" data-label="Vendido USD">{{ fmtUsd(p.vendido_usd) }}</td>
                                        <td class="right muted" data-label="Vendido Bs.">{{ fmtBs(p.vendido_bs) }}</td>
                                        <td class="right muted" data-label="Costo USD">{{ fmtUsd(p.costo_usd) }}</td>
                                        <td class="right" data-label="Utilidad USD" :class="(p.vendido_usd - p.costo_usd) >= 0 ? 'green' : 'red'">
                                            {{ fmtUsd(p.vendido_usd - p.costo_usd) }}
                                        </td>
                                        <td data-label=""></td>
                                    </tr>
                                </template>
                                </template>
                                <tr class="dia-totals-row">
                                    <td><strong>TOTAL DEVENGADO</strong></td>
                                    <td class="right"><strong>{{ fmtUsd(dayData.totals.vendido_usd) }}</strong></td>
                                    <td class="right muted"><strong>{{ fmtBs(dayData.totals.vendido_bs) }}</strong></td>
                                    <td class="right muted"><strong>{{ fmtUsd(dayData.totals.costo_usd) }}</strong></td>
                                    <td class="right" :class="dayData.totals.utilidad_usd >= 0 ? 'text-green' : 'text-red'"><strong>{{ fmtUsd(dayData.totals.utilidad_usd) }}</strong></td>
                                    <td class="right"><strong>{{ dayData.totals.margen_pct }}%</strong></td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Resumen contable: cobrado vs crédito vs devengado -->
                        <div v-if="dayData.totals.cobrado_bs !== undefined" class="dia-summary">
                            <div class="dia-summary-row dia-summary-cobrado">
                                <span class="dia-summary-label">Ventas cobradas ({{ dayData.totals.tickets_paid }} tickets)</span>
                                <span class="dia-summary-val">{{ fmtBs(dayData.totals.cobrado_bs) }}</span>
                                <span class="dia-summary-usd muted">{{ fmtUsd(dayData.totals.cobrado_usd) }}</span>
                            </div>
                            <div v-if="(dayData.totals.credito_bs ?? 0) > 0" class="dia-summary-row dia-summary-credito">
                                <span class="dia-summary-label">Créditos/Delivery despachados ({{ dayData.totals.tickets_pending }} sin cobrar)</span>
                                <span class="dia-summary-val">{{ fmtBs(dayData.totals.credito_bs) }}</span>
                                <span class="dia-summary-usd muted">{{ fmtUsd(dayData.totals.credito_usd) }}</span>
                            </div>
                            <div class="dia-summary-row dia-summary-total">
                                <span class="dia-summary-label">Total Devengado del día</span>
                                <span class="dia-summary-val">{{ fmtBs(dayData.totals.vendido_bs) }}</span>
                                <span class="dia-summary-usd">{{ fmtUsd(dayData.totals.vendido_usd) }}</span>
                            </div>
                            <div v-if="dayData.totals.tickets_cancelled > 0" class="dia-summary-anulados">
                                Anuladas: {{ dayData.totals.tickets_cancelled }} tickets
                            </div>
                            <p class="dia-devengo-note">Las ventas a crédito se registran en la fecha de despacho, independientemente de cuándo se cobren.</p>
                        </div>

                        <!-- Gráfica de barras HTML/CSS -->
                        <div class="bar-chart">
                            <p class="chart-title">Utilidad bruta por categoría (USD)</p>
                            <div v-for="row in dayData.categories" :key="row.categoria" class="bar-row">
                                <span class="bar-label">{{ row.categoria }}</span>
                                <div class="bar-track">
                                    <div
                                        class="bar-fill"
                                        :class="row.utilidad_usd >= 0 ? 'bar-pos' : 'bar-neg'"
                                        :style="{ width: barWidth(row.utilidad_usd) + '%' }"
                                    ></div>
                                </div>
                                <span class="bar-value" :class="row.utilidad_usd >= 0 ? 'text-green' : 'text-red'">
                                    {{ fmtUsd(row.utilidad_usd) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <p v-else-if="!dayLoading" class="empty-row">Selecciona una fecha y presiona Consultar.</p>
                    <div v-if="dayLoading" class="loading-overlay">Cargando…</div>
                </div>

                <!-- Cargando overlay (tabs normales) -->
                <div v-if="loading && activeTab !== 'reporte_dia'" class="loading-overlay">Cargando…</div>
            </div>

            <!-- ─── Paginación ────────────────────────────────────────────── -->
            <div v-if="totalPages > 1" class="pagination">
                <button class="page-btn" :disabled="currentPage <= 1" @click="currentPage--">‹</button>
                <span class="page-info">{{ currentPage }} / {{ totalPages }}</span>
                <button class="page-btn" :disabled="currentPage >= totalPages" @click="currentPage++">›</button>
            </div>

        </div>

        <!-- ── Panel de ayuda ────────────────────────────────────────────── -->
        <HelpModal
            :show="showHelp"
            title="Reportes — Cómo funciona"
            :steps="helpSteps"
            :faqs="helpFaqs"
            @close="showHelp = false"
        />

    </AppLayout>
</template>

<style scoped>
.rep-wrap {
    padding: 1.5rem;
    max-width: 1440px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* ─── Tabs ─────────────────────────────────────────────────────────────────── */
.tab-bar { display: flex; gap: 0.35rem; background: var(--bg-base); border-radius: 12px; padding: 0.3rem; width: fit-content; }
.tab {
    padding: 0.5rem 1.1rem;
    border-radius: 8px;
    font-size: 0.86rem;
    font-weight: 600;
    color: var(--text-muted);
    background: transparent;
    border: none;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
    white-space: nowrap;
}
.tab.tab-active { background: var(--bg-card); color: var(--text-primary); }
.tab-help {
    margin-left: auto;
    border-radius: 50%;
    width: 2rem; height: 2rem;
    padding: 0;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text-muted);
    font-size: 14px;
    cursor: pointer;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.tab-help:hover { background: var(--brand); color: #fff; border-color: var(--brand); }

/* ─── Filtros ──────────────────────────────────────────────────────────────── */
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0.75rem;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1rem;
}
.filter-group { display: flex; flex-direction: column; gap: 0.25rem; }
.filter-label { font-size: 0.72rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; }
.filter-input {
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.42rem 0.7rem;
    color: var(--text-primary);
    font-size: 0.88rem;
    outline: none;
    min-width: 130px;
}
.filter-input:focus { border-color: var(--brand); }
.filter-actions { display: flex; align-items: flex-end; gap: 0.5rem; margin-left: auto; }

/* ─── Botones ──────────────────────────────────────────────────────────────── */
.btn-brand {
    background: var(--brand);
    color: #fff;
    border: none;
    padding: 0.5rem 1.25rem;
    border-radius: 9px;
    font-weight: 700;
    font-size: 0.88rem;
    cursor: pointer;
    transition: opacity 0.15s;
}
.btn-brand:hover { opacity: 0.88; }
.btn-brand:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-outline {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-primary);
    padding: 0.5rem 1.1rem;
    border-radius: 9px;
    font-weight: 600;
    font-size: 0.88rem;
    cursor: pointer;
    transition: border-color 0.15s;
}
.btn-outline:hover { border-color: var(--brand); color: var(--brand); }

/* ─── Card ─────────────────────────────────────────────────────────────────── */
.card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    position: relative;
}
.results-header { margin-bottom: 0.75rem; }
.results-count { font-size: 0.78rem; color: var(--text-muted); font-weight: 600; }

/* ─── Tabla ────────────────────────────────────────────────────────────────── */
.table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; min-width: 0; }
.rep-table { width: 100%; min-width: 560px; border-collapse: collapse; font-size: 0.84rem; }
.rep-table th {
    padding: 0.45rem 0.75rem;
    text-align: left;
    font-size: 0.71rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
.rep-table td {
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid var(--border);
    color: var(--text-primary);
    vertical-align: middle;
}
.rep-table tr:last-child td { border-bottom: none; }
.rep-table tr:hover td { background: var(--bg-base); }
.center { text-align: center !important; }
.right  { text-align: right !important; }
.muted  { color: var(--text-muted) !important; }
.small  { font-size: 0.78rem !important; }
.mono   { font-family: monospace; color: var(--brand); font-size: 0.82rem; }
.amount { font-weight: 700; }
.text-red   { color: #ef4444 !important; font-weight: 700; }
.text-green { color: #16a34a !important; font-weight: 700; }
.empty-row { text-align: center; color: var(--text-muted); padding: 2rem !important; font-size: 0.9rem; }

/* ─── Pills / badges ────────────────────────────────────────────────────────── */
.pill { font-size: 0.71rem; padding: 0.15rem 0.55rem; border-radius: 99px; font-weight: 600; white-space: nowrap; }
.pill-open      { background: rgba(59,130,246,0.12);  color: #3b82f6; }
.pill-pending   { background: rgba(245,158,11,0.14);  color: #d97706; }
.pill-paid      { background: rgba(22,163,74,0.13);   color: #16a34a; }
.pill-cancelled { background: rgba(239,68,68,0.11);   color: #ef4444; }
.type-badge { font-size: 0.7rem; font-weight: 700; padding: 0.12rem 0.5rem; border-radius: 99px; }
.badge-amber { background: rgba(245,158,11,0.14); color: #d97706; }
.badge-blue  { background: rgba(59,130,246,0.11); color: #3b82f6; }

/* ─── Loading overlay ───────────────────────────────────────────────────────── */
.loading-overlay {
    position: absolute;
    inset: 0;
    background: rgba(var(--bg-card-rgb, 17,17,22), 0.75);
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    font-size: 0.9rem;
    color: var(--text-muted);
    font-weight: 600;
}

/* ─── Paginación ────────────────────────────────────────────────────────────── */
.pagination { display: flex; align-items: center; justify-content: center; gap: 0.75rem; }
.page-btn {
    background: var(--bg-card);
    border: 1px solid var(--border);
    color: var(--text-primary);
    width: 2rem;
    height: 2rem;
    border-radius: 8px;
    font-size: 1.1rem;
    cursor: pointer;
    line-height: 1;
}
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { font-size: 0.84rem; color: var(--text-muted); font-weight: 600; }

/* ─── Error ─────────────────────────────────────────────────────────────────── */
.error-msg { font-size: 0.85rem; color: #ef4444; background: rgba(239,68,68,0.1); padding: 0.5rem 0.85rem; border-radius: 8px; }

/* ─── Responsive ────────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .filter-bar { gap: 0.5rem; }
    .filter-actions { margin-left: 0; width: 100%; }
    .tab-bar { width: 100%; overflow-x: auto; }
}

@media (max-width: 640px) {
    .rep-wrap { padding: 1rem 0.75rem; }

    /* Tab bar scrollable */
    .tab-bar { padding: 0.25rem; }
    .tab { padding: 0.5rem 0.85rem; font-size: 0.82rem; }

    /* Filter bar stacks */
    .filter-bar { flex-direction: column; align-items: stretch; }
    .filter-group { width: 100%; }
    .filter-input { width: 100%; min-width: unset; font-size: 1rem; /* prevent iOS zoom */ }
    .filter-actions { flex-direction: column; gap: 0.5rem; }
    .btn-brand, .btn-outline { width: 100%; justify-content: center; min-height: 44px; }

    /* Card padding */
    .card { padding: 0.85rem; }

    /* Pagination touch targets */
    .page-btn { width: 2.75rem; height: 2.75rem; }

    /* Bar chart: label narrower on small screens */
    .bar-label { width: 70px; min-width: 70px; font-size: 0.76rem; }
    .bar-value { width: 65px; min-width: 65px; font-size: 0.76rem; }
}

@media (max-width: 480px) {
    /* Day report table also needs min-width */
    .dia-table { font-size: 0.8rem; }
}

@media (max-width: 1023px) {
    .rep-wrap { padding: 1rem 0.75rem; }
    /* Tab bar scrollable en tablet */
    .tab-bar { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; }
    /* Filter bar agrupa mejor */
    .filter-bar { gap: 0.5rem; }
    .filter-actions { margin-left: 0; }
    /* Touch targets */
    .tab { min-height: 44px; }
    .btn-brand, .btn-outline { min-height: 44px; }
}

/* ─── Reporte del Día ────────────────────────────────────────────────────────── */
.cat-checks { display: flex; flex-wrap: wrap; gap: 0.4rem 0.75rem; max-width: 400px; }
.cat-check-label {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.84rem;
    color: var(--text-primary);
    cursor: pointer;
}
.cat-check-input { accent-color: var(--brand); cursor: pointer; }

.dia-wrap { padding: 0.25rem 0; }
.dia-table { margin-bottom: 1.25rem; }
.dia-totals-row td {
    border-top: 2px solid var(--border);
    background: var(--bg-base);
    padding-top: 0.6rem !important;
    padding-bottom: 0.6rem !important;
}
.cat-row:hover td { background: var(--bg-base); }
.cat-toggle-icon { display: inline-flex; align-items: center; width: 1rem; color: var(--text-muted); vertical-align: middle; margin-right: 0.25rem; }
.prod-kg { display: inline-block; margin-left: 0.5rem; font-size: 0.72rem; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.prod-row td {
    background: color-mix(in srgb, var(--bg-card) 60%, var(--bg-base));
    font-size: 0.8rem;
    padding-top: 0.3rem !important;
    padding-bottom: 0.3rem !important;
    border-bottom: 1px solid color-mix(in srgb, var(--border) 50%, transparent);
    color: var(--text-secondary);
}
.prod-row:last-of-type td { border-bottom: 1px solid var(--border); }
.prod-name { padding-left: 2rem !important; font-style: italic; }
.green { color: var(--green, #10b981); }
.red   { color: #ef4444; }

/* ─── Resumen contable ──────────────────────────────────────────────────────── */
.dia-summary {
    margin: 1rem 0;
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    font-size: 0.88rem;
}
.dia-summary-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.55rem 1rem;
    border-bottom: 1px solid var(--border);
}
.dia-summary-label { flex: 1; color: var(--text-primary); }
.dia-summary-val { font-weight: 700; font-size: 0.95rem; min-width: 140px; text-align: right; color: var(--text-primary); }
.dia-summary-usd { min-width: 90px; text-align: right; font-size: 0.82rem; }
.dia-summary-cobrado { background: color-mix(in srgb, var(--bg-card) 80%, #16a34a 20%); }
.dia-summary-credito { background: color-mix(in srgb, var(--bg-card) 80%, #b45309 20%); }
.dia-summary-total {
    background: var(--bg-base);
    font-weight: 700;
    border-bottom: none;
}
.dia-summary-total .dia-summary-label { font-weight: 700; color: var(--text-primary); }
.dia-summary-total .dia-summary-val { color: var(--brand); }
.dia-summary-anulados {
    padding: 0.3rem 1rem;
    font-size: 0.78rem;
    color: var(--text-muted);
    background: var(--bg-base);
    border-top: 1px solid var(--border);
}
.dia-devengo-note {
    margin: 0.4rem 0 0;
    font-size: 0.74rem;
    color: var(--text-muted);
    font-style: italic;
    padding: 0 1rem 0.5rem;
}

/* ─── Bar chart ─────────────────────────────────────────────────────────────── */
.bar-chart {
    margin-top: 0.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}
.chart-title {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    margin: 0 0 0.85rem;
}
.bar-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.55rem;
}
.bar-label {
    width: 100px;
    min-width: 100px;
    font-size: 0.82rem;
    color: var(--text-primary);
    text-align: right;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.bar-track {
    flex: 1;
    height: 20px;
    background: var(--bg-base);
    border-radius: 4px;
    overflow: hidden;
}
.bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.4s ease;
    min-width: 2px;
}
.bar-pos { background: rgba(22,163,74,0.55); }
.bar-neg { background: rgba(239,68,68,0.45); }
.bar-value {
    width: 80px;
    min-width: 80px;
    font-size: 0.82rem;
    font-variant-numeric: tabular-nums;
    text-align: right;
}
</style>
