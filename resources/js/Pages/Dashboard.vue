<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, onUnmounted, computed } from 'vue'
import axios from 'axios'

// ─── Props iniciales desde Inertia ────────────────────────────────────────────
const props = defineProps({
    ventas_hoy:         { type: Object, default: () => ({ count: 0, total_bs: 0, total_usd: 0 }) },
    top_productos:      { type: Array,  default: () => [] },
    stock_critico:      { type: Array,  default: () => [] },
    ultimas_ventas:     { type: Array,  default: () => [] },
    caja_activa:        { type: Object, default: null },
    tasa_hoy:           { type: Number, default: 1 },
    pedidos_pendientes: { type: Number, default: 0 },
    categorias_hoy:     { type: Array,  default: () => [] },
    utilidad_boveda:    { type: Array,  default: () => [] },
})

// ─── Estado reactivo (sincronizado por polling) ───────────────────────────────
const d = ref({
    ventas_hoy:         props.ventas_hoy,
    top_productos:      props.top_productos,
    stock_critico:      props.stock_critico,
    ultimas_ventas:     props.ultimas_ventas,
    caja_activa:        props.caja_activa,
    tasa_hoy:           props.tasa_hoy,
    pedidos_pendientes: props.pedidos_pendientes,
    categorias_hoy:     props.categorias_hoy,
    utilidad_boveda:    props.utilidad_boveda,
    kilos_por_categoria: [],
})

// ─── Polling 30 s ─────────────────────────────────────────────────────────────
let pollTimer = null

async function fetchData() {
    try {
        const res = await axios.get(route('dashboard.data'))
        d.value = res.data
    } catch (_) { /* fallo silencioso */ }
}

onMounted(() => { pollTimer = setInterval(fetchData, 30_000) })
onUnmounted(() => clearInterval(pollTimer))

// ─── Formato ──────────────────────────────────────────────────────────────────
function fmtBs(n)  { return 'Bs. ' + Number(n ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
function fmtUsd(n) { return '$' + Number(n ?? 0).toFixed(2) }
function fmtKg(n)  { return Number(n ?? 0).toFixed(1) + ' kg' }
function fmtTime(d) { return d ? new Date(d).toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit' }) : '—' }

// ─── Categorías ordenadas por monto Bs. descendente ─────────────────────────
const categoriasOrdenadas = computed(() =>
    [...d.value.kilos_por_categoria].sort((a, b) => b.monto_bs - a.monto_bs)
)
const topCategoria = computed(() => categoriasOrdenadas.value[0] || null)
const totalMontoCat = computed(() =>
    d.value.kilos_por_categoria.reduce((sum, c) => sum + c.monto_bs, 0)
)

// ─── Barra de progreso por categoría ─────────────────────────────────────────
const maxMonto = computed(() => Math.max(1, ...d.value.kilos_por_categoria.map(c => c.monto_bs)))

function barWidth(monto) {
    return Math.round((monto / maxMonto.value) * 100) + '%'
}

function pctMonto(monto) {
    const total = totalMontoCat.value
    if (!total) return '0%'
    return Math.round((monto / total) * 100) + '%'
}

// ─── Stock crítico filtrado (stock < min_stock o negativo) ────────────────────
const stockCriticoFiltrado = computed(() =>
    d.value.stock_critico.filter(p => p.stock < p.min_stock || p.stock < 0)
)

// ─── Stock badge ──────────────────────────────────────────────────────────────
function stockBadge(item) {
    return item.stock <= 0 ? 'badge-empty' : 'badge-low'
}
function stockLabel(item) {
    return item.stock <= 0 ? 'Sin stock' : 'Stock bajo'
}

// ─── Ticket promedio ──────────────────────────────────────────────────────────
const ticketPromedio = computed(() => {
    const count = d.value.ventas_hoy.count
    return count > 0 ? d.value.ventas_hoy.total_bs / count : 0
})

// ─── Centro de Control — selector de categorías ───────────────────────────────
const ALL_CATS = ['Res', 'Cerdo', 'Pollo', 'Charcutería', 'Trastes', 'Abarrotes']
const LS_KEY   = 'syntimeat_dashboard_cats'

const selectedCats = ref((() => {
    try {
        const v = localStorage.getItem(LS_KEY)
        return v ? JSON.parse(v) : [...ALL_CATS]
    } catch { return [...ALL_CATS] }
})())

function toggleCat(cat) {
    const idx = selectedCats.value.indexOf(cat)
    if (idx === -1) selectedCats.value.push(cat)
    else selectedCats.value.splice(idx, 1)
    localStorage.setItem(LS_KEY, JSON.stringify(selectedCats.value))
}

const catCardsData = computed(() =>
    selectedCats.value.map(cat => {
        const stats = d.value.categorias_hoy?.find(c => c.category_name === cat) ?? null
        const bov   = d.value.utilidad_boveda?.find(b => b.category_name === cat) ?? null
        return {
            name:        cat,
            total_bs:    stats?.total_bs    ?? 0,
            total_usd:   stats?.total_usd   ?? 0,
            kg_vendidos: stats?.kg_vendidos ?? 0,
            boveda:      bov,
        }
    })
)
</script>

<template>
    <AppLayout title="Dashboard">
        <div class="dash-wrap">

            <!-- ─── Fila 1: KPIs ────────────────────────────────────────────── -->
            <div class="kpi-row">

                <div class="kpi-card kpi-sales">
                    <span class="kpi-label">Ventas del día</span>
                    <span class="kpi-big">{{ fmtBs(d.ventas_hoy.total_bs) }}</span>
                    <span class="kpi-sub">{{ fmtUsd(d.ventas_hoy.total_usd) }} · Tasa: {{ d.tasa_hoy.toFixed(2) }}</span>
                </div>

                <div class="kpi-card">
                    <span class="kpi-label">Tickets emitidos</span>
                    <span class="kpi-big kpi-neutral">{{ d.ventas_hoy.count }}</span>
                    <span class="kpi-sub">ventas pagadas hoy</span>
                </div>

                <div class="kpi-card kpi-top">
                    <span class="kpi-label">Ticket promedio</span>
                    <span class="kpi-big kpi-neutral" style="font-size:1.4rem">{{ fmtBs(ticketPromedio) }}</span>
                    <span class="kpi-sub">{{ d.ventas_hoy.count ? 'basado en ' + d.ventas_hoy.count + ' tickets' : 'Sin ventas hoy' }}</span>
                </div>

                <div class="kpi-card" :class="d.pedidos_pendientes > 0 ? 'kpi-warn' : ''">
                    <span class="kpi-label">Pedidos pendientes</span>
                    <span class="kpi-big">
                        {{ d.pedidos_pendientes }}
                        <span v-if="d.pedidos_pendientes > 0" class="badge-red">!</span>
                    </span>
                    <span class="kpi-sub">{{ d.caja_activa ? '🟢 Caja abierta' : '🔴 Caja cerrada' }}</span>
                </div>

            </div>

            <!-- ─── Fila 2: Categorías + Stock crítico ──────────────────────── -->
            <div class="mid-row">

                <!-- Top productos -->
                <div class="card">
                    <h3 class="card-title">Top productos del día</h3>
                    <div v-if="d.top_productos.length" class="cat-table">
                        <div v-for="(p, i) in d.top_productos" :key="p.name" class="cat-row">
                            <div class="cat-name-wrap">
                                <div style="display:flex;align-items:center;gap:0.5rem">
                                    <span class="cat-rank">#{{ i + 1 }}</span>
                                    <span class="cat-name">{{ p.name }}</span>
                                </div>
                                <div class="cat-bar-bg">
                                    <div class="cat-bar" :style="{ width: barWidth(p.monto_bs) }"></div>
                                </div>
                            </div>
                            <span class="cat-bs">{{ fmtBs(p.monto_bs) }}</span>
                            <span class="cat-pct">{{ p.cantidad % 1 === 0 ? p.cantidad + ' u.' : p.cantidad.toFixed(2) + ' kg' }}</span>
                        </div>
                    </div>
                    <p v-else class="empty-msg">Sin ventas registradas hoy.</p>
                </div>

                <!-- Stock crítico -->
                <div class="card">
                    <h3 class="card-title">Stock crítico</h3>
                    <div v-if="d.stock_critico.length" class="stock-list">
                        <div v-for="p in d.stock_critico" :key="p.id" class="stock-row">
                            <div class="stock-info">
                                <span class="stock-name">{{ p.name }}</span>
                                <span class="stock-cat">{{ p.category }}</span>
                            </div>
                            <span class="stock-qty">
                                {{ p.sale_mode === 'weight' ? p.stock.toFixed(2) + ' kg' : Math.round(p.stock) + ' u.' }}
                            </span>
                            <span class="status-badge" :class="p.stock <= 0 ? 'badge-empty' : 'badge-low'">{{ p.stock <= 0 ? 'Sin stock' : 'Stock bajo' }}</span>
                        </div>
                    </div>
                    <p v-else class="empty-ok">✓ Todo en orden</p>
                </div>

            </div>

            <!-- ─── Fila 3: Últimas ventas ───────────────────────────────────── -->
            <div class="card">
                <h3 class="card-title">Últimas ventas</h3>
                <div class="sales-table-wrap">
                    <table class="sales-table">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Hora</th>
                                <th>Items</th>
                                <th>Total Bs.</th>
                                <th>Método</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in d.ultimas_ventas" :key="s.id">
                                <td class="ticket-col">{{ s.ticket_number }}</td>
                                <td class="muted">{{ fmtTime(s.sold_at) }}</td>
                                <td class="muted center">{{ s.items_count }}</td>
                                <td class="amount">{{ fmtBs(s.total_bs) }}</td>
                                <td class="muted">{{ s.payment_method ?? '—' }}</td>
                                <td><span class="status-badge badge-paid">pagado</span></td>
                            </tr>
                            <tr v-if="!d.ultimas_ventas.length">
                                <td colspan="6" class="empty-row">Sin ventas hoy.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ─── Centro de Control ─────────────────────────────────────── -->
            <div class="card cc-section">
                <div class="cc-header">
                    <h3 class="card-title cc-title">Centro de Control</h3>
                    <a :href="route('reports.day-pdf')" target="_blank" class="btn-export">
                        📄 Exportar reporte del día
                    </a>
                </div>

                <!-- Chips de categorías -->
                <div class="cc-chips">
                    <button
                        v-for="cat in ALL_CATS"
                        :key="cat"
                        class="cc-chip"
                        :class="{ 'cc-chip--active': selectedCats.includes(cat) }"
                        @click="toggleCat(cat)"
                    >{{ cat }}</button>
                </div>

                <!-- Cards de categorías activas -->
                <div v-if="catCardsData.length" class="cc-cards">
                    <div v-for="cat in catCardsData" :key="cat.name" class="cc-card">
                        <div class="cc-card-name">{{ cat.name }}</div>

                        <div class="cc-stats">
                            <div class="cc-stat">
                                <span class="cc-stat-lbl">Ventas Bs.</span>
                                <span class="cc-stat-val">{{ fmtBs(cat.total_bs) }}</span>
                            </div>
                            <div class="cc-stat">
                                <span class="cc-stat-lbl">Ventas USD</span>
                                <span class="cc-stat-val">{{ fmtUsd(cat.total_usd) }}</span>
                            </div>
                            <div class="cc-stat">
                                <span class="cc-stat-lbl">Kg despachados</span>
                                <span class="cc-stat-val">{{ fmtKg(cat.kg_vendidos) }}</span>
                            </div>
                        </div>

                        <!-- Bóveda info (si aplica) -->
                        <div v-if="cat.boveda" class="cc-boveda">
                            <div class="cc-bov-row">
                                <span class="cc-stat-lbl">Costo bóveda</span>
                                <span class="cc-stat-val">{{ fmtUsd(cat.boveda.costo) }}</span>
                            </div>
                            <div class="cc-bov-row">
                                <span class="cc-stat-lbl">Utilidad</span>
                                <span
                                    class="cc-stat-val cc-utilidad"
                                    :class="cat.boveda.utilidad >= 0 ? 'cc-pos' : 'cc-neg'"
                                >{{ cat.boveda.utilidad >= 0 ? '+' : '' }}{{ fmtUsd(cat.boveda.utilidad) }}</span>
                            </div>
                            <div class="cc-progress-wrap">
                                <div class="cc-progress-track">
                                    <div
                                        class="cc-progress-bar"
                                        :class="cat.boveda.porcentaje >= 100 ? 'cc-bar--ok' : cat.boveda.porcentaje >= 50 ? 'cc-bar--mid' : 'cc-bar--low'"
                                        :style="{ width: Math.min(cat.boveda.porcentaje, 100) + '%' }"
                                    ></div>
                                </div>
                                <span class="cc-progress-lbl">{{ cat.boveda.porcentaje }}% recuperado</span>
                            </div>
                        </div>

                        <p v-else class="cc-no-boveda">Sin entrada bóveda activa</p>
                    </div>
                </div>

                <p v-else class="empty-msg">Selecciona al menos una categoría.</p>
            </div>

        </div>
    </AppLayout>
</template>

<style scoped>
.dash-wrap {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    max-width: 1280px;
    margin: 0 auto;
}

/* ─── KPIs ─────────────────────────────────────────────────────────────────── */
.kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
.kpi-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1.1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.kpi-card.kpi-sales  { border-color: rgba(245,158,11,0.45); background: rgba(245,158,11,0.07); }
.kpi-card.kpi-warn   { border-color: rgba(239,68,68,0.4);   background: rgba(239,68,68,0.06); }
.kpi-card.kpi-top    { border-color: rgba(139,92,246,0.35); background: rgba(139,92,246,0.06); }
.kpi-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.kpi-big   { font-size: 1.65rem; font-weight: 800; color: #f59e0b; line-height: 1.1; }
.kpi-big.kpi-neutral { color: var(--text-primary); }
.kpi-big.kpi-top-name { font-size: 1.2rem; word-break: break-word; }
.kpi-sub   { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.1rem; }
.badge-red { display: inline-flex; align-items: center; justify-content: center; width: 1.1rem; height: 1.1rem; background: #ef4444; color: #fff; border-radius: 50%; font-size: 0.7rem; font-weight: 900; vertical-align: super; margin-left: 0.25rem; }

/* ─── Fila 2 ─────────────────────────────────────────────────────────────── */
.mid-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

/* ─── Card genérica ──────────────────────────────────────────────────────── */
.card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; padding: 1.1rem 1.25rem; }
.card-title { font-size: 0.82rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.85rem; }

/* ─── Categorías ──────────────────────────────────────────────────────────── */
.cat-table { display: flex; flex-direction: column; gap: 0.5rem; }
.cat-head { display: grid; grid-template-columns: 1fr 9rem 4rem; font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; padding-bottom: 0.35rem; border-bottom: 1px solid var(--border); }
.cat-row  { display: grid; grid-template-columns: 1fr 9rem 5rem; align-items: center; font-size: 0.86rem; gap: 0.5rem; margin-bottom: 0.5rem; }
.cat-rank { font-size: 0.75rem; font-weight: 800; color: var(--brand); min-width: 1.5rem; }
.cat-name-wrap { display: flex; flex-direction: column; gap: 0.25rem; }
.cat-name { color: var(--text-primary); font-weight: 600; }
.cat-bar-bg { height: 4px; background: var(--bg-base); border-radius: 2px; overflow: hidden; }
.cat-bar    { height: 100%; background: var(--brand); border-radius: 2px; transition: width 0.5s; }
.cat-bs { color: var(--text-primary); font-weight: 600; font-size: 0.85rem; }
.cat-pct { color: var(--text-muted); font-size: 0.82rem; text-align: right; }

/* ─── Stock crítico ──────────────────────────────────────────────────────── */
.stock-list { display: flex; flex-direction: column; gap: 0.5rem; }
.stock-row  { display: flex; align-items: center; gap: 0.75rem; padding: 0.45rem 0.65rem; background: var(--bg-base); border-radius: 8px; }
.stock-info { flex: 1; display: flex; flex-direction: column; gap: 0.1rem; }
.stock-name { font-size: 0.86rem; font-weight: 600; color: var(--text-primary); }
.stock-cat  { font-size: 0.72rem; color: var(--text-muted); }
.stock-qty  { font-size: 0.85rem; color: var(--text-muted); white-space: nowrap; min-width: 4rem; text-align: right; }
.empty-ok   { color: #16a34a; font-weight: 600; font-size: 0.9rem; text-align: center; padding: 1rem 0; }
.empty-msg  { color: var(--text-muted); font-size: 0.85rem; text-align: center; padding: 1rem 0; }

/* ─── Badges ──────────────────────────────────────────────────────────────── */
.status-badge { font-size: 0.73rem; padding: 0.18rem 0.55rem; border-radius: 99px; font-weight: 600; white-space: nowrap; }
.badge-paid   { background: rgba(22,163,74,0.15);  color: #16a34a; }
.badge-empty  { background: rgba(239,68,68,0.14);  color: #ef4444; }
.badge-low    { background: rgba(245,158,11,0.15); color: #d97706; }

/* ─── Últimas ventas ─────────────────────────────────────────────────────── */
.sales-table-wrap { overflow-x: auto; }
.sales-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
.sales-table th { padding: 0.5rem 0.75rem; text-align: left; font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid var(--border); }
.sales-table td { padding: 0.55rem 0.75rem; border-bottom: 1px solid var(--border); color: var(--text-primary); }
.sales-table tr:last-child td { border-bottom: none; }
.ticket-col { font-family: monospace; color: var(--brand); font-size: 0.82rem; }
.amount     { font-weight: 700; }
.muted      { color: var(--text-muted); }
.center     { text-align: center; }
.empty-row  { text-align: center; color: var(--text-muted); padding: 1.5rem !important; }

/* ─── Responsive ─────────────────────────────────────────────────────────── */
@media (max-width: 900px) {
    .kpi-row { grid-template-columns: repeat(2, 1fr); }
    .mid-row { grid-template-columns: 1fr; }
}
@media (max-width: 560px) {
    .kpi-row { grid-template-columns: 1fr; }
}

/* ─── Centro de Control ─────────────────────────────────────────────────── */
.cc-section  { display: flex; flex-direction: column; gap: 1rem; }
.cc-header   { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem; }
.cc-title    { margin-bottom: 0; }

.btn-export  { display: inline-flex; align-items: center; gap: .35rem; padding: .4rem .9rem; border-radius: .5rem; background: var(--brand); color: #fff; font-size: .82rem; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: opacity .15s; }
.btn-export:hover { opacity: .85; }

.cc-chips    { display: flex; flex-wrap: wrap; gap: .5rem; }
.cc-chip     { border: 1px solid var(--border); border-radius: 999px; padding: .3rem .8rem; background: transparent; color: var(--text-muted); font-size: .8rem; font-weight: 600; cursor: pointer; font-family: inherit; transition: all .15s; }
.cc-chip--active { border-color: var(--brand); background: rgba(37,99,235,.1); color: var(--brand); }
.cc-chip:hover:not(.cc-chip--active) { border-color: var(--text-muted); }

.cc-cards    { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: .85rem; }
.cc-card     { background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: .9rem 1rem; display: flex; flex-direction: column; gap: .65rem; }
.cc-card-name { font-size: .9rem; font-weight: 700; color: var(--text-primary); }

.cc-stats    { display: grid; grid-template-columns: repeat(3, 1fr); gap: .4rem; }
.cc-stat     { display: flex; flex-direction: column; gap: .15rem; }
.cc-stat-lbl { font-size: .67rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; }
.cc-stat-val { font-size: .85rem; font-weight: 600; color: var(--text-primary); font-variant-numeric: tabular-nums; }

.cc-boveda    { display: flex; flex-direction: column; gap: .4rem; border-top: 1px solid var(--border); padding-top: .55rem; }
.cc-bov-row   { display: flex; justify-content: space-between; align-items: center; }
.cc-utilidad  { font-weight: 700; font-size: .88rem; }
.cc-pos       { color: #22c55e; }
.cc-neg       { color: #ef4444; }

.cc-progress-wrap  { display: flex; flex-direction: column; gap: .25rem; }
.cc-progress-track { height: 5px; background: var(--border); border-radius: 999px; overflow: hidden; }
.cc-progress-bar   { height: 100%; border-radius: 999px; transition: width .4s ease; }
.cc-bar--ok  { background: #22c55e; }
.cc-bar--mid { background: #f59e0b; }
.cc-bar--low { background: #ef4444; }
.cc-progress-lbl { font-size: .69rem; color: var(--text-muted); }

.cc-no-boveda { font-size: .75rem; color: var(--text-muted); font-style: italic; margin: 0; }
</style>

