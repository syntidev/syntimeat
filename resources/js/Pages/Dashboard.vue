<script setup>
import AppLayout  from '@/Layouts/AppLayout.vue'
import HelpModal  from '@/Components/HelpModal.vue'
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import axios from 'axios'
import { Receipt, BarChart2, AlertTriangle, CheckCircle, Package, Check, Bell, X, Layers } from '@lucide/vue'

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
    banking_alert:      { type: String, default: null },
})

const d = ref({
    ventas_hoy:          props.ventas_hoy,
    top_productos:       props.top_productos,
    stock_critico:       props.stock_critico,
    ultimas_ventas:      props.ultimas_ventas,
    caja_activa:         props.caja_activa,
    tasa_hoy:            props.tasa_hoy,
    pedidos_pendientes:  props.pedidos_pendientes,
    categorias_hoy:      props.categorias_hoy,
    utilidad_boveda:     props.utilidad_boveda,
    kilos_por_categoria: [],
    banking_alert:       props.banking_alert,
})

// ─── Polling 30 s ─────────────────────────────────────────────────────────────
let pollTimer = null
async function fetchData() {
    try {
        const res = await axios.get(route('dashboard.data'))
        d.value = res.data
    } catch (_) {}
}
onMounted(() => { pollTimer = setInterval(fetchData, 30_000) })
onUnmounted(() => clearInterval(pollTimer))

// ─── Contador animado ─────────────────────────────────────────────────────────
function useCounter(getValue, duration = 1200) {
    const display = ref(0)
    let raf = null
    const animate = (target) => {
        const start = display.value
        const delta = target - start
        const startTime = performance.now()
        const tick = (now) => {
            const elapsed = now - startTime
            const progress = Math.min(elapsed / duration, 1)
            const ease = 1 - Math.pow(1 - progress, 3)
            display.value = start + delta * ease
            if (progress < 1) raf = requestAnimationFrame(tick)
            else display.value = target
        }
        cancelAnimationFrame(raf)
        raf = requestAnimationFrame(tick)
    }
    onMounted(() => animate(getValue()))
    watch(getValue, (v) => animate(v))
    onUnmounted(() => cancelAnimationFrame(raf))
    return display
}

// ─── Formato ──────────────────────────────────────────────────────────────────
function fmtBs(n)     { return 'Bs. ' + Number(n ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
function fmtUsd(n)    { return '$' + Number(n ?? 0).toFixed(2) }
function fmtKg(n)     { return Number(n ?? 0).toFixed(1) + ' kg' }
function fmtTime(dt)  { return dt ? new Date(dt).toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', hour12: false }) : '—' }
function fmtDate(dt)  { return dt ? new Date(dt).toLocaleDateString('es-VE', { day: '2-digit', month: 'short' }) : '—' }

const ticketPromedio = computed(() => {
    const c = d.value.ventas_hoy.count
    return c > 0 ? d.value.ventas_hoy.total_bs / c : 0
})

// ─── Contadores animados (después de ticketPromedio) ──────────────────────────
const animTotalBs  = useCounter(() => d.value.ventas_hoy.total_bs)
const animCount    = useCounter(() => d.value.ventas_hoy.count, 800)
const animTicket   = useCounter(() => ticketPromedio.value)
const animPedidos  = useCounter(() => d.value.pedidos_pendientes, 600)

// ─── Barras animadas ──────────────────────────────────────────────────────────
const barsVisible = ref(false)
onMounted(() => { setTimeout(() => { barsVisible.value = true }, 300) })

const maxMonto = computed(() => Math.max(1, ...d.value.top_productos.map(p => p.monto_bs)))
function barWidth(monto) { return Math.round((monto / maxMonto.value) * 100) }

const totalMontoCat = computed(() =>
    (d.value.kilos_por_categoria ?? []).reduce((s, c) => s + c.monto_bs, 0)
)
const maxMontoCat = computed(() => Math.max(1, ...(d.value.kilos_por_categoria ?? []).map(c => c.monto_bs)))
const maxKgCat    = computed(() => Math.max(1, ...catCardsData.value.map(c => c.kg_vendidos)))
function kgBarWidth(kg) { return Math.min(100, Math.round((kg / maxKgCat.value) * 100)) }

// ─── Centro de Control ────────────────────────────────────────────────────────
const ALL_CATS    = ['Res', 'Cerdo', 'Pollo', 'Charcutería', 'Trastes', 'Víveres']
const LS_KEY      = 'syntimeat_dashboard_cats'
const selectedCats = ref((() => {
    try { const v = localStorage.getItem(LS_KEY); return v ? JSON.parse(v) : [...ALL_CATS] }
    catch { return [...ALL_CATS] }
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
        return { name: cat, total_bs: stats?.total_bs ?? 0, total_usd: stats?.total_usd ?? 0, kg_vendidos: stats?.kg_vendidos ?? 0, boveda: bov }
    })
)

// ─── Hora actual ──────────────────────────────────────────────────────────────
const horaActual = ref('')
function updateHora() {
    horaActual.value = new Date().toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}
let clockTimer = null
onMounted(() => { updateHora(); clockTimer = setInterval(updateHora, 1000) })
onUnmounted(() => clearInterval(clockTimer))

// ─── Ayuda ────────────────────────────────────────────────────────────────────
const showHelp = ref(false)

const helpSteps = [
    {
        title: 'KPIs en tiempo real',
        body:  'El panel superior muestra ventas del día (USD y Bs.), tickets emitidos y ticket promedio. Los datos se actualizan automáticamente cada 30 segundos sin necesidad de recargar la página.',
        tip:   'El punto verde parpadeante indica que el dashboard está vivo — no necesitas recargar.',
    },
    {
        title: 'Centro de Control por categoría',
        body:  'Activa o desactiva las categorías con los chips de colores para ver el rendimiento de cada corte: ventas en USD/Bs., kilos despachados y utilidad vs. costo de bóveda activa.',
        tip:   'El porcentaje "recuperado" te dice cuánto del costo de bóveda ya fue cubierto con ventas. 100% = punto de equilibrio superado.',
    },
    {
        title: 'Stock crítico y últimas ventas',
        body:  'El panel de stock crítico lista productos con inventario bajo o agotado. Las últimas ventas muestran los tickets más recientes del día con monto, método de pago y estado.',
        tip:   'Si un producto aparece en rojo con "Sin stock", ve a Inventario y registra una entrada antes de que la cajera intente venderlo.',
    },
]

const helpFaqs = [
    {
        q: '¿Cada cuánto se actualiza el dashboard?',
        a: 'Automáticamente cada 30 segundos. No necesitas recargar la página — el sistema hace la consulta en segundo plano.',
    },
    {
        q: '¿Qué significa el porcentaje "recuperado"?',
        a: 'Es la relación entre lo vendido en esa categoría y el costo total de la bóveda activa. Cuando llega a 100% significa que ya cubriste el costo de la compra con las ventas del día.',
    },
    {
        q: '¿Los filtros de categoría del Centro de Control se guardan?',
        a: 'Sí. Tu selección se guarda en el navegador y se mantiene entre sesiones en el mismo dispositivo.',
    },
]

// ─── Alerta de corte bancario ──────────────────────────────────────────────────
// Se descarta manualmente; se restablece si el polling trae un mensaje nuevo
const dismissedAlertText = ref(null)
const activeBankingAlert = computed(() => {
    const msg = d.value.banking_alert
    if (!msg || msg === dismissedAlertText.value) return null
    return msg
})
function dismissBankingAlert() {
    dismissedAlertText.value = d.value.banking_alert
}
</script>

<template>
    <AppLayout title="Dashboard">
        <div class="dash-wrap">

            <!-- ═══ ALERTA CORTE BANCARIO ═════════════════════════════════════ -->
            <Transition name="banking-alert">
                <div v-if="activeBankingAlert" class="banking-banner" role="alert" aria-live="assertive">
                    <Bell :size="18" class="banking-banner__icon" />
                    <span class="banking-banner__msg">{{ activeBankingAlert }}</span>
                    <button class="banking-banner__close" @click="dismissBankingAlert" aria-label="Cerrar alerta">
                        <X :size="16" />
                    </button>
                </div>
            </Transition>

            <!-- Header de página con botón de ayuda ──────────────────────── -->
            <div class="dash-header">
                <button class="dash-help-btn" @click="showHelp = true" title="Ayuda">?</button>
            </div>

            <!-- ═══ HERO KPIs ══════════════════════════════════════════════════ -->
            <div class="kpi-hero">

                <!-- Ventas del día — card grande -->
                <div class="kpi-main">
                    <div class="kpi-main-glow" aria-hidden="true" />
                    <div class="kpi-main-inner">
                        <div class="kpi-main-top">
                            <span class="kpi-eyebrow">Ventas del día</span>
                            <div class="live-dot">
                                <span class="live-ring" />
                                <span class="live-core" />
                            </div>
                        </div>
                        <div class="kpi-main-number">{{ fmtUsd(d.ventas_hoy.total_usd) }}</div>
                        <div class="kpi-main-meta">
                            <span class="kpi-usd">{{ fmtBs(animTotalBs) }}</span>
                            <span class="kpi-sep">·</span>
                            <span class="kpi-rate">Tasa BCV: <strong>{{ d.tasa_hoy.toFixed(2) }}</strong></span>
                        </div>
                    </div>
                    <div class="kpi-main-clock">{{ horaActual }}</div>
                </div>

                <!-- Tres stats secundarios -->
                <div class="kpi-side">

                    <div class="kpi-stat kpi-stat--tickets">
                        <span class="kpi-stat-icon"><Receipt :size="22" /></span>
                        <div class="kpi-stat-body">
                            <span class="kpi-stat-label">Tickets emitidos</span>
                            <span class="kpi-stat-val">{{ Math.round(animCount) }}</span>
                            <span class="kpi-stat-sub">ventas pagadas hoy</span>
                        </div>
                    </div>

                    <div class="kpi-stat kpi-stat--avg">
                        <span class="kpi-stat-icon"><BarChart2 :size="22" /></span>
                        <div class="kpi-stat-body">
                            <span class="kpi-stat-label">Ticket promedio</span>
                            <span class="kpi-stat-val kpi-stat-val--sm">{{ fmtUsd(d.ventas_hoy.count ? d.ventas_hoy.total_usd / d.ventas_hoy.count : 0) }}</span>
                            <span class="kpi-stat-sub">{{ fmtBs(animTicket) }} · {{ d.ventas_hoy.count ? d.ventas_hoy.count + ' tickets' : 'Sin ventas' }}</span>
                        </div>
                    </div>

                    <div class="kpi-stat" :class="d.pedidos_pendientes > 0 ? 'kpi-stat--warn' : 'kpi-stat--ok'">
                        <span class="kpi-stat-icon">
                            <AlertTriangle v-if="d.pedidos_pendientes > 0" :size="22" />
                            <CheckCircle v-else :size="22" />
                        </span>
                        <div class="kpi-stat-body">
                            <span class="kpi-stat-label">Pedidos pendientes</span>
                            <span class="kpi-stat-val">{{ Math.round(animPedidos) }}</span>
                            <span class="kpi-stat-sub">{{ d.caja_activa ? '● Caja abierta' : '○ Caja cerrada' }}</span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ═══ CENTRO DE CONTROL ════════════════════════════════════════ -->
            <div class="panel panel--cc">
                <div class="panel-head">
                    <div>
                        <h3 class="panel-title">Centro de Control</h3>
                        <p class="panel-desc">Rendimiento por categoría · {{ fmtDate(new Date().toISOString()) }}</p>
                    </div>
                    <a :href="route('reports.day-pdf')" target="_blank" class="btn-export">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0-3-3m3 3 3-3M3 17v3a1 1 0 001 1h16a1 1 0 001-1v-3M3 7V4a1 1 0 011-1h16a1 1 0 011 1v3"/></svg>
                        Exportar día
                    </a>
                </div>

                <div class="cc-chips">
                    <button
                        v-for="cat in ALL_CATS" :key="cat"
                        class="cc-chip" :class="{ 'cc-chip--on': selectedCats.includes(cat) }"
                        @click="toggleCat(cat)"
                    >{{ cat }}</button>
                </div>

                <div v-if="catCardsData.length" class="cc-grid">
                    <div v-for="cat in catCardsData" :key="cat.name" class="cc-card">
                        <div class="cc-card-header">
                            <span class="cc-cat-name">{{ cat.name }}</span>
                            <span class="cc-cat-bs">{{ fmtUsd(cat.total_usd) }}</span>
                        </div>
                        <div class="cc-stats">
                            <div class="cc-stat">
                                <span class="cc-lbl">Vendido Bs.</span>
                                <span class="cc-val">{{ fmtBs(cat.total_bs) }}</span>
                            </div>
                            <div class="cc-stat">
                                <span class="cc-lbl">Vendido USD</span>
                                <span class="cc-val">{{ fmtUsd(cat.total_usd) }}</span>
                            </div>
                        </div>
                        <div class="cc-kg-bar">
                            <div class="cc-kg-track">
                                <div
                                    class="cc-kg-fill"
                                    :style="{ width: barsVisible ? kgBarWidth(cat.kg_vendidos) + '%' : '0%' }"
                                />
                            </div>
                            <span class="cc-kg-label">{{ fmtKg(cat.kg_vendidos) }} despachados</span>
                        </div>
                        <div v-if="cat.boveda" class="cc-boveda">
                            <div class="cc-bov-row">
                                <span class="cc-lbl">Costo bóveda</span>
                                <span class="cc-val">{{ fmtUsd(cat.boveda.costo) }}</span>
                            </div>
                            <div class="cc-bov-row">
                                <span class="cc-lbl">Utilidad</span>
                                <span class="cc-val" :class="cat.boveda.utilidad >= 0 ? 'val-green' : 'val-red'">
                                    {{ cat.boveda.utilidad >= 0 ? '+' : '' }}{{ fmtUsd(cat.boveda.utilidad) }}
                                </span>
                            </div>
                            <div class="cc-progress">
                                <div class="cc-track">
                                    <div
                                        class="cc-fill"
                                        :class="cat.boveda.porcentaje >= 100 ? 'fill-green' : cat.boveda.porcentaje >= 50 ? 'fill-amber' : 'fill-red'"
                                        :style="{ width: Math.min(cat.boveda.porcentaje, 100) + '%' }"
                                    />
                                </div>
                                <span class="cc-pct">{{ cat.boveda.porcentaje }}% recuperado</span>
                            </div>
                        </div>
                        <div v-else class="cc-no-bov">
                            <Layers :size="14" />
                            <span>Sin bóveda activa</span>
                        </div>
                    </div>
                </div>
                <p v-else class="td-empty">Selecciona al menos una categoría.</p>
            </div>

            <!-- ═══ FILA PRINCIPAL ════════════════════════════════════════════ -->
            <div class="main-row">

                <!-- Top productos -->
                <div class="panel panel--top">
                    <div class="panel-head">
                        <h3 class="panel-title">Top productos del día</h3>
                        <span class="panel-badge">{{ d.top_productos.length }} items</span>
                    </div>
                    <div v-if="d.top_productos.length" class="prod-list">
                        <div
                            v-for="(p, i) in d.top_productos"
                            :key="p.name"
                            class="prod-row"
                            :style="{ '--delay': i * 80 + 'ms' }"
                        >
                            <div class="prod-line">
                                <span class="prod-rank" :class="i === 0 ? 'rank-gold' : i === 1 ? 'rank-silver' : i === 2 ? 'rank-bronze' : 'rank-plain'">#{{ i + 1 }}</span>
                                <span class="prod-name">{{ p.name }}</span>
                                <span class="prod-qty prod-qty--inline">{{ p.cantidad % 1 === 0 ? p.cantidad + ' u.' : p.cantidad.toFixed(2) + ' kg' }}</span>
                                <span class="prod-bs">{{ fmtBs(p.monto_bs) }}</span>
                            </div>
                            <div class="prod-bar-track">
                                <div
                                    class="prod-bar-fill"
                                    :class="i === 0 ? 'fill-gold' : 'fill-brand'"
                                    :style="{ width: barsVisible ? barWidth(p.monto_bs) + '%' : '0%' }"
                                />
                            </div>
                        </div>
                    </div>
                    <div v-else class="empty-state">
                        <span class="empty-icon"><Package :size="32" /></span>
                        <span>Sin ventas registradas hoy</span>
                    </div>
                </div>

                <!-- Stock crítico -->
                <div class="panel panel--stock">
                    <div class="panel-head">
                        <h3 class="panel-title">Stock crítico</h3>
                        <span class="panel-badge panel-badge--red" v-if="d.stock_critico.length">
                            {{ d.stock_critico.length }} alertas
                        </span>
                    </div>
                    <div v-if="d.stock_critico.length" class="stock-list">
                        <div v-for="p in d.stock_critico" :key="p.id" class="stock-item">
                            <div class="stock-dot" :class="p.stock <= 0 ? 'dot-red' : 'dot-amber'" />
                            <div class="stock-body">
                                <span class="stock-name">{{ p.name }}</span>
                                <span class="stock-cat">{{ p.category }}</span>
                            </div>
                            <div class="stock-right">
                                <span class="stock-qty">
                                    {{ p.sale_mode === 'weight' ? p.stock.toFixed(2) + ' kg' : Math.round(p.stock) + ' u.' }}
                                </span>
                                <span class="stock-badge" :class="p.stock <= 0 ? 'sbadge-empty' : 'sbadge-low'">
                                    {{ p.stock <= 0 ? 'Sin stock' : 'Bajo' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="empty-ok">
                        <span class="ok-icon"><Check :size="24" /></span>
                        <span>Todo el inventario en orden</span>
                    </div>
                </div>

            </div>

            <!-- ═══ ÚLTIMAS VENTAS ════════════════════════════════════════════ -->
            <div class="panel panel--sales">
                <div class="panel-head">
                    <h3 class="panel-title">Últimas ventas</h3>
                    <span class="panel-badge">Hoy</span>
                </div>

                <!-- Tabla — tablet+ -->
                <div class="sales-scroll hide-sm">
                    <table class="sales-tbl">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Hora</th>
                                <th class="center">Items</th>
                                <th>Total</th>
                                <th>Método</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in d.ultimas_ventas" :key="s.id" class="sales-row">
                                <td class="td-ticket">{{ s.ticket_number }}</td>
                                <td class="td-muted">{{ fmtTime(s.sold_at) }}</td>
                                <td class="td-muted center">{{ s.items_count }}</td>
                                <td class="td-amount">
                                    <span>{{ fmtUsd(s.total_usd) }}</span>
                                    <span class="td-bs-sub">{{ fmtBs(s.total_bs) }}</span>
                                </td>
                                <td class="td-muted">{{ s.payment_method ?? '—' }}</td>
                                <td><span class="spill spill-paid">pagado</span></td>
                            </tr>
                            <tr v-if="!d.ultimas_ventas.length">
                                <td colspan="6" class="td-empty">Sin ventas registradas hoy.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Cards — mobile -->
                <div class="sv-cards show-sm">
                    <div v-if="!d.ultimas_ventas.length" class="td-empty">Sin ventas registradas hoy.</div>
                    <div v-for="s in d.ultimas_ventas" :key="s.id" class="sv-card">
                        <div class="sv-top">
                            <span class="sv-ticket">{{ s.ticket_number }}</span>
                            <span class="sv-time">{{ fmtTime(s.sold_at) }}</span>
                        </div>
                        <div class="sv-mid">
                            <span class="sv-usd">{{ fmtUsd(s.total_usd) }}</span>
                            <span class="sv-bs">{{ fmtBs(s.total_bs) }}</span>
                        </div>
                        <div class="sv-bot">
                            <span class="sv-method">{{ s.payment_method ?? '—' }}</span>
                            <span class="sv-items">{{ s.items_count }} item{{ s.items_count !== 1 ? 's' : '' }}</span>
                            <span class="spill spill-paid">pagado</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Panel de ayuda ───────────────────────────────────────────────── -->
        <HelpModal
            :show="showHelp"
            title="Dashboard — Cómo funciona"
            :steps="helpSteps"
            :faqs="helpFaqs"
            @close="showHelp = false"
        />

    </AppLayout>
</template>

<style scoped>
/* ═══ HEADER AYUDA ════════════════════════════════════════════════════════════ */
.dash-header {
    display: flex;
    justify-content: flex-end;
    align-items: center;
}
.dash-help-btn {
    border-radius: 50%;
    width: 44px;
    height: 44px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.85rem;
    font-weight: 700;
    border: 1.5px solid var(--border);
    background: none;
    color: var(--text-muted);
    cursor: pointer;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.dash-help-btn:hover { background: var(--brand); color: #fff; border-color: var(--brand); }

/* ═══ LAYOUT ══════════════════════════════════════════════════════════════════ */
.dash-wrap {
    padding: 1rem 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    max-width: 1280px;
    margin: 0 auto;
}
@media (min-width: 960px) {
    .dash-wrap { padding: 1.5rem; gap: 1.25rem; }
}

/* ═══ HERO KPIs ═══════════════════════════════════════════════════════════════ */
.kpi-hero {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    align-items: stretch;
}

/* Card grande — ventas */
.kpi-main {
    position: relative;
    background: linear-gradient(135deg, color-mix(in srgb, var(--brand) 12%, transparent) 0%, color-mix(in srgb, var(--brand) 4%, transparent) 60%, var(--bg-card) 100%);
    border: 1px solid color-mix(in srgb, var(--brand) 35%, transparent);
    border-radius: 20px;
    padding: 1.75rem 1.75rem 1.25rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
    min-height: 160px;
}
.kpi-main-glow {
    position: absolute;
    top: -60px; left: -40px;
    width: 260px; height: 260px;
    background: radial-gradient(circle, color-mix(in srgb, var(--brand) 18%, transparent) 0%, transparent 70%);
    pointer-events: none;
}
.kpi-main-inner { position: relative; z-index: 1; }
.kpi-main-top   { display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.4rem; }
.kpi-eyebrow    { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: color-mix(in srgb, var(--brand) 85%, white); }
.kpi-main-number {
    font-size: clamp(1.6rem, 3.5vw, 2.4rem);
    font-weight: 800;
    color: var(--brand);
    line-height: 1;
    font-variant-numeric: tabular-nums;
    margin-bottom: 0.5rem;
}
.kpi-main-meta  { display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: var(--text-muted); }
.kpi-usd        { color: var(--text-secondary); font-weight: 600; }
.kpi-sep        { color: var(--border); }
.kpi-rate       { color: var(--text-muted); }
.kpi-rate strong { color: var(--text-secondary); }
.kpi-main-clock {
    position: absolute;
    bottom: 1.1rem; right: 1.25rem;
    font-size: 0.78rem;
    font-variant-numeric: tabular-nums;
    color: color-mix(in srgb, var(--brand) 40%, transparent);
    font-weight: 700;
    letter-spacing: 0.05em;
}

/* Live dot */
.live-dot { position: relative; width: 10px; height: 10px; }
.live-ring {
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    border: 1.5px solid color-mix(in srgb, var(--brand) 50%, transparent);
    animation: ping 1.8s ease-out infinite;
}
.live-core {
    position: absolute;
    inset: 0;
    background: var(--brand);
    border-radius: 50%;
}
@keyframes ping {
    0%   { transform: scale(1); opacity: 0.7; }
    100% { transform: scale(2.2); opacity: 0; }
}

/* Tres stats secundarios */
.kpi-side {
    display: grid;
    grid-template-rows: repeat(3, 1fr);
    gap: 0.6rem;
}
.kpi-stat {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 0.85rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.9rem;
    transition: border-color 0.2s, transform 0.2s;
}
.kpi-stat:hover { transform: translateX(3px); border-color: var(--brand); }
.kpi-stat--warn { border-color: rgba(239,68,68,0.4); background: rgba(239,68,68,0.05); }
.kpi-stat--ok   { border-color: rgba(16,185,129,0.3); background: rgba(16,185,129,0.04); }
.kpi-stat--avg  { border-color: rgba(139,92,246,0.3); background: rgba(139,92,246,0.04); }
.kpi-stat-icon  { font-size: 1.3rem; flex-shrink: 0; }
.kpi-stat-body  { display: flex; flex-direction: column; gap: 0.05rem; min-width: 0; }
.kpi-stat-label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--text-muted); }
.kpi-stat-val   { font-size: 1.4rem; font-weight: 800; color: var(--text-primary); font-variant-numeric: tabular-nums; line-height: 1.15; }
.kpi-stat-val--sm { font-size: 1rem; }
.kpi-stat-sub   { font-size: 0.72rem; color: var(--text-muted); }

/* ═══ FILA PRINCIPAL ══════════════════════════════════════════════════════════ */
.main-row {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 1rem;
}

/* ═══ PANEL BASE ══════════════════════════════════════════════════════════════ */
.panel {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 1.25rem 1.35rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.panel-title {
    font-size: 0.78rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    margin: 0;
}
.panel-desc  { font-size: 0.72rem; color: var(--text-muted); margin: 0.15rem 0 0; }
.panel-badge {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.2rem 0.6rem;
    border-radius: 99px;
    background: rgba(255,255,255,0.06);
    color: var(--text-muted);
}
.panel-badge--red { background: rgba(239,68,68,0.12); color: #ef4444; animation: pulse-badge 1.6s ease-in-out infinite; }
@keyframes pulse-badge { 0%, 100% { opacity: 1; } 50% { opacity: 0.55; } }

/* ═══ TOP PRODUCTOS ══════════════════════════════════════════════════════════ */
.prod-list { display: flex; flex-direction: column; gap: 0.55rem; }
.prod-row  {
    display: flex;
    flex-direction: column;
    gap: 0.28rem;
    animation: fadeSlideIn 0.4s ease both;
    animation-delay: var(--delay);
}
.prod-line { display: flex; align-items: center; gap: 0.5rem; }
@keyframes fadeSlideIn {
    from { opacity: 0; transform: translateX(-10px); }
    to   { opacity: 1; transform: translateX(0); }
}
.prod-rank-wrap { flex: 1; display: flex; align-items: center; gap: 0.65rem; min-width: 0; }
.prod-rank {
    font-size: 0.72rem;
    font-weight: 800;
    min-width: 2rem;
    text-align: center;
    padding: 0.2rem 0.4rem;
    border-radius: 6px;
    flex-shrink: 0;
}
.rank-gold   { background: rgba(245,158,11,0.18); color: #f59e0b; }
.rank-silver { background: rgba(148,163,184,0.15); color: #94a3b8; }
.rank-bronze { background: rgba(180,120,60,0.15); color: #b4783c; }
.rank-plain  { background: rgba(255,255,255,0.05); color: var(--text-muted); }
.prod-meta   { flex: 1; display: flex; flex-direction: column; gap: 0.3rem; min-width: 0; }
.prod-name   { font-size: 0.88rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.prod-bar-track { height: 3px; background: var(--bg-base); border-radius: 2px; overflow: hidden; }
.prod-bar-fill  { height: 100%; border-radius: 2px; transition: width 0.9s cubic-bezier(0.16,1,0.3,1); }
.fill-gold  { background: linear-gradient(90deg, #f59e0b, #fde68a); }
.fill-brand { background: var(--brand); }
.prod-amounts    { display: flex; flex-direction: column; align-items: flex-end; gap: 0.1rem; flex-shrink: 0; }
.prod-bs         { font-size: 0.88rem; font-weight: 700; color: var(--text-primary); font-variant-numeric: tabular-nums; white-space: nowrap; flex-shrink: 0; }
.prod-qty        { font-size: 0.72rem; color: var(--text-muted); }
.prod-qty--inline { font-size: 0.72rem; color: var(--text-muted); flex: 1; text-align: right; white-space: nowrap; }

/* ═══ STOCK CRÍTICO ══════════════════════════════════════════════════════════ */
.stock-list { display: flex; flex-direction: column; gap: 0.45rem; overflow-y: auto; max-height: 320px; }
.stock-item {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.55rem 0.7rem;
    background: var(--bg-base);
    border-radius: 10px;
    border: 1px solid transparent;
    transition: border-color 0.15s;
}
.stock-item:hover { border-color: var(--border); }
.stock-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.dot-red   { background: #ef4444; box-shadow: 0 0 6px rgba(239,68,68,0.5); }
.dot-amber { background: #f59e0b; box-shadow: 0 0 6px rgba(245,158,11,0.4); }
.stock-body { flex: 1; display: flex; flex-direction: column; gap: 0.05rem; min-width: 0; }
.stock-name { font-size: 0.85rem; font-weight: 600; color: var(--text-primary); }
.stock-cat  { font-size: 0.7rem; color: var(--text-muted); }
.stock-right { display: flex; flex-direction: column; align-items: flex-end; gap: 0.2rem; flex-shrink: 0; }
.stock-qty   { font-size: 0.8rem; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.stock-badge { font-size: 0.67rem; font-weight: 700; padding: 0.12rem 0.45rem; border-radius: 99px; white-space: nowrap; }
.sbadge-empty { background: rgba(239,68,68,0.14); color: #ef4444; }
.sbadge-low   { background: rgba(245,158,11,0.14); color: #d97706; }

.empty-ok {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    color: #10b981;
    font-weight: 600;
    font-size: 0.88rem;
    padding: 1rem 0;
}
.ok-icon { font-size: 1.1rem; }
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-muted);
    font-size: 0.88rem;
    padding: 2rem 0;
}
.empty-icon { font-size: 1.8rem; }

/* ═══ ÚLTIMAS VENTAS ═════════════════════════════════════════════════════════ */
.sales-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; min-width: 0; }
.sales-tbl    { width: 100%; min-width: 580px; border-collapse: collapse; font-size: 0.86rem; }
.sales-tbl th {
    padding: 0.45rem 0.75rem;
    text-align: left;
    font-size: 0.68rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    border-bottom: 1px solid var(--border);
}
.sales-row td { padding: 0.55rem 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--text-primary); }
.sales-row:hover td { background: rgba(255,255,255,0.02); }
.sales-row:last-child td { border-bottom: none; }
.td-ticket { font-family: 'Courier New', monospace; color: var(--brand); font-size: 0.82rem; font-weight: 700; white-space: nowrap; }
.td-amount { font-weight: 700; font-variant-numeric: tabular-nums; display: flex; flex-direction: column; align-items: flex-end; gap: 1px; }
.td-bs-sub { font-size: .72rem; font-weight: 400; color: var(--text-muted); }
.td-muted  { color: var(--text-muted); white-space: nowrap; }
.center    { text-align: center; }
.td-empty  { text-align: center; color: var(--text-muted); padding: 2rem !important; font-size: 0.88rem; }
.spill     { font-size: 0.7rem; font-weight: 700; padding: 0.18rem 0.55rem; border-radius: 99px; }
.spill-paid { background: rgba(16,185,129,0.14); color: #10b981; }

/* ═══ CENTRO DE CONTROL ══════════════════════════════════════════════════════ */
.btn-export {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.9rem;
    background: var(--brand);
    color: #fff;
    font-size: 0.8rem;
    font-weight: 700;
    text-decoration: none;
    border-radius: 9px;
    transition: opacity 0.15s, transform 0.15s;
}
.btn-export:hover { opacity: 0.88; transform: translateY(-1px); }

.cc-chips { display: flex; flex-wrap: wrap; gap: 0.45rem; }
.cc-chip  {
    padding: 0.28rem 0.75rem;
    border-radius: 99px;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text-muted);
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.15s;
}
.cc-chip--on { border-color: var(--brand); background: rgba(37,99,235,0.12); color: var(--brand); }
.cc-chip:hover:not(.cc-chip--on) { border-color: var(--text-muted); color: var(--text-secondary); }

.cc-grid  { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem; }
.cc-card  {
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1.1rem 1.2rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    transition: border-color 0.2s, transform 0.2s;
}
.cc-card:hover { border-color: var(--brand); transform: translateY(-2px); }
.cc-card-header { display: flex; justify-content: space-between; align-items: flex-start; }
.cc-cat-name { font-size: 0.92rem; font-weight: 800; color: var(--text-primary); }
.cc-cat-bs   { font-size: 0.82rem; font-weight: 700; color: #f59e0b; font-variant-numeric: tabular-nums; }
.cc-stats    { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
.cc-stat     { display: flex; flex-direction: column; gap: 0.12rem; }
.cc-lbl      { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
.cc-val      { font-size: 0.88rem; font-weight: 600; color: var(--text-primary); font-variant-numeric: tabular-nums; }
.val-green   { color: #10b981; }
.val-red     { color: #ef4444; }
.cc-boveda   { border-top: 1px solid var(--border); padding-top: 0.6rem; display: flex; flex-direction: column; gap: 0.4rem; }
.cc-bov-row  { display: flex; justify-content: space-between; align-items: center; }
.cc-progress { display: flex; flex-direction: column; gap: 0.25rem; }
.cc-track    { height: 4px; background: var(--border); border-radius: 2px; overflow: hidden; }
.cc-fill     { height: 100%; border-radius: 2px; transition: width 0.6s ease; }
.fill-green  { background: linear-gradient(90deg, #10b981, #34d399); }
.fill-amber  { background: linear-gradient(90deg, #f59e0b, #fde68a); }
.fill-red    { background: #ef4444; }
.cc-pct      { font-size: 0.68rem; color: var(--text-muted); }
.cc-no-bov   { display: flex; align-items: center; gap: 0.4rem; font-size: 0.75rem; color: var(--text-muted); margin: 0; padding: 0.2rem 0; }

/* kg dispatched bar */
.cc-kg-bar   { display: flex; flex-direction: column; gap: 0.22rem; }
.cc-kg-track { height: 5px; background: var(--border); border-radius: 3px; overflow: hidden; }
.cc-kg-fill  { height: 100%; background: linear-gradient(90deg, var(--brand), color-mix(in srgb, var(--brand) 55%, transparent)); border-radius: 3px; transition: width 0.9s cubic-bezier(0.16,1,0.3,1); }
.cc-kg-label { font-size: 0.68rem; color: var(--text-muted); }

/* ═══ VENTAS MOBILE CARDS ════════════════════════════════════════════════════ */
.hide-sm { display: block; }
.show-sm { display: none; }

.sv-cards {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}
.sv-card {
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.75rem 0.9rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.sv-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.sv-ticket { font-family: 'Courier New', monospace; color: var(--brand); font-size: 0.8rem; font-weight: 700; }
.sv-time   { font-size: 0.74rem; color: var(--text-muted); }
.sv-mid {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
}
.sv-usd { font-size: 1.15rem; font-weight: 800; color: var(--text-primary); font-variant-numeric: tabular-nums; }
.sv-bs  { font-size: 0.75rem; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.sv-bot {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}
.sv-method { font-size: 0.76rem; color: var(--text-muted); }
.sv-items  { font-size: 0.74rem; color: var(--text-muted); }

/* ═══ RESPONSIVE — mobile-first ═══════════════════════════════════════════════ */

/* Base: ≤ 639px — todo apilado, cards, touch targets */
@media (max-width: 639px) {
    .dash-wrap       { padding: 0.85rem 0.75rem; gap: 0.85rem; }
    .kpi-hero        { grid-template-columns: 1fr; gap: 0.7rem; }
    .kpi-main        { min-height: 130px; padding: 1.25rem; }
    .kpi-main-number { font-size: 1.75rem; }
    .kpi-main-meta   { font-size: 0.74rem; flex-wrap: wrap; gap: 0.35rem; }
    .kpi-side        { grid-template-rows: none; grid-template-columns: 1fr; gap: 0.5rem; }
    .kpi-stat        { min-height: 64px; -webkit-tap-highlight-color: transparent; }
    .kpi-stat-val    { font-size: 1.25rem; }

    .main-row { grid-template-columns: 1fr; }
    .panel    { padding: 1rem; border-radius: 14px; }
    .panel-head { flex-direction: column; align-items: flex-start; gap: 0.4rem; }
    .btn-export { align-self: flex-start; min-height: 44px; padding: 0 1rem; }

    /* Ventas: tabla oculta, cards visibles */
    .hide-sm { display: none; }
    .show-sm { display: flex; flex-direction: column; }

    .cc-grid  { grid-template-columns: 1fr; }
    .cc-chips { gap: 0.4rem; }
    .cc-chip  {
        min-height: 44px;
        padding: 0 0.85rem;
        font-size: 0.82rem;
        display: inline-flex;
        align-items: center;
        -webkit-tap-highlight-color: transparent;
    }
    .stock-item { min-height: 52px; }
}

/* sm: 640px – 959px */
@media (min-width: 640px) and (max-width: 959px) {
    .dash-wrap { padding: 1rem; gap: 1rem; }
    .kpi-hero  { grid-template-columns: 1fr; }
    .kpi-side  { grid-template-rows: none; grid-template-columns: repeat(3, 1fr); }
    .main-row  { grid-template-columns: 1fr; }
    .cc-grid   { grid-template-columns: repeat(2, 1fr); }
}

/* md: 960px+ — layout completo */
@media (min-width: 960px) {
    .kpi-hero { grid-template-columns: 1fr 1fr; }
    .kpi-side { grid-template-columns: none; grid-template-rows: repeat(3, 1fr); }
    .main-row { grid-template-columns: 1.4fr 1fr; }
}

/* ═══ BANNER CORTE BANCARIO ═══════════════════════════════════════════════════ */
.banking-banner {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    background: linear-gradient(90deg, rgba(245,158,11,0.15), rgba(239,68,68,0.10));
    border: 1px solid rgba(245,158,11,0.5);
    border-radius: 12px;
    color: #f59e0b;
    font-weight: 600;
    font-size: 0.9rem;
}
.banking-banner__icon { flex-shrink: 0; }
.banking-banner__msg  { flex: 1; }
.banking-banner__close {
    display: flex;
    align-items: center;
    justify-content: center;
    background: none;
    border: none;
    cursor: pointer;
    color: inherit;
    opacity: 0.7;
    padding: 0.25rem;
    min-width: 44px;
    min-height: 44px;
    border-radius: 8px;
    transition: opacity 0.2s, background 0.2s;
}
.banking-banner__close:hover { opacity: 1; background: rgba(245,158,11,0.12); }

/* Transition */
.banking-alert-enter-active,
.banking-alert-leave-active { transition: opacity 0.3s ease, transform 0.3s ease; }
.banking-alert-enter-from,
.banking-alert-leave-to     { opacity: 0; transform: translateY(-6px); }
</style>
