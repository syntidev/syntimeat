<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, reactive, computed, watch, onMounted, useTemplateRef } from 'vue'
import { animate } from 'motion'
import axios from 'axios'
import { DollarSign, TrendingUp, Receipt, Scale, Lock, BarChart2, CheckSquare, Square, Box } from '@lucide/vue'

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    branches:     { type: Array,  default: () => [] },
    max_branches: { type: Number, default: 2 },
    initial:      { type: Object, default: () => ({}) },
    rango:        { type: Object, default: () => ({}) },
})

// ─── Estado ───────────────────────────────────────────────────────────────────
const data       = ref(props.initial ?? {})
const loading    = ref(false)
const errorMsg   = ref('')
const limitModal = ref(false)

const fechas = reactive({
    desde: props.rango?.desde ?? new Date().toISOString().split('T')[0],
    hasta: props.rango?.hasta ?? new Date().toISOString().split('T')[0],
})

const selectedIds = ref((props.initial?.branches ?? []).map(b => b.id))

const branchesData = computed(() => data.value?.branches ?? [])
const totals       = computed(() => data.value?.totals ?? {})
const tendencia    = computed(() => data.value?.tendencia ?? [])
const categorias   = computed(() => data.value?.categorias ?? [])
const multiSucursal = computed(() => branchesData.value.length > 1)

// ─── Selección de sucursales ──────────────────────────────────────────────────
function isSelected(id) { return selectedIds.value.includes(id) }

function toggleBranch(id) {
    if (isSelected(id)) {
        if (selectedIds.value.length === 1) return
        selectedIds.value = selectedIds.value.filter(x => x !== id)
        loadData()
        return
    }
    if (selectedIds.value.length >= props.max_branches) {
        limitModal.value = true
        return
    }
    selectedIds.value = [...selectedIds.value, id]
    loadData()
}

// ─── Carga de datos ───────────────────────────────────────────────────────────
async function loadData() {
    if (!selectedIds.value.length) return
    loading.value  = true
    errorMsg.value = ''
    try {
        const res = await axios.get(route('reports.consolidated-data'), {
            params: {
                branch_ids:  selectedIds.value,
                fecha_desde: fechas.desde,
                fecha_hasta: fechas.hasta,
            },
        })
        data.value = res.data
    } catch (e) {
        errorMsg.value = e.response?.data?.error ?? 'Error al cargar el panel.'
    } finally {
        loading.value = false
    }
}

// ─── KPIs animados ────────────────────────────────────────────────────────────
const kpi = reactive({ vendido_usd: 0, utilidad_usd: 0, ticket_usd: 0, ventas_count: 0, kg: 0, margen: 0 })

function countTo(key, target, decimals = 2) {
    const from = kpi[key]
    animate(from, target ?? 0, {
        duration: 0.9,
        ease: [0.22, 1, 0.36, 1],
        onUpdate: v => { kpi[key] = decimals === 0 ? Math.round(v) : Number(v.toFixed(decimals)) },
    })
}

function refreshKpis() {
    const t = totals.value
    countTo('vendido_usd',  t.vendido_usd,  2)
    countTo('utilidad_usd', t.utilidad_usd, 2)
    countTo('ticket_usd',   t.ventas_count > 0 ? t.vendido_usd / t.ventas_count : 0, 2)
    countTo('ventas_count', t.ventas_count, 0)
    countTo('kg',           t.kg_vendidos,  3)
    countTo('margen',       t.margen_pct,   1)
}

watch(data, refreshKpis, { immediate: true })

// ─── Comparativa ─────────────────────────────────────────────────────────────
const maxVendido = computed(() => Math.max(...branchesData.value.map(b => b.vendido_usd), 1))
function barW(v) { return Math.round((v / maxVendido.value) * 100) }
function ticketBranch(b) { return b.ventas_count > 0 ? b.vendido_usd / b.ventas_count : 0 }
function ticketTotal() {
    const t = totals.value
    return t.ventas_count > 0 ? t.vendido_usd / t.ventas_count : 0
}

// ─── Tendencia SVG ────────────────────────────────────────────────────────────
const CHART_W   = 640
const CHART_H   = 130
const CHART_PAD = { t: 16, r: 10, b: 10, l: 10 }
const GRID_LINES = 3 // líneas horizontales intermedias

const trendGeometry = computed(() => {
    const pts = tendencia.value
    if (!pts.length) return { line: '', area: '', dots: [], gridY: [], max: 0, min: 0 }

    const vals = pts.map(p => p.total_usd)
    const max  = Math.max(...vals)
    const min  = 0 // base siempre en 0 para que el área sea legible
    const rng  = max - min || 1

    const drawW = CHART_W - CHART_PAD.l - CHART_PAD.r
    const drawH = CHART_H - CHART_PAD.t - CHART_PAD.b
    const step  = pts.length > 1 ? drawW / (pts.length - 1) : 0
    const sy    = v => CHART_PAD.t + drawH - ((v - min) / rng) * drawH

    const dots = pts.map((p, i) => ({
        x: CHART_PAD.l + i * step,
        y: sy(p.total_usd),
        dia: p.dia,
        v: p.total_usd,
    }))

    const line = dots.map((d, i) => `${i === 0 ? 'M' : 'L'}${d.x.toFixed(1)},${d.y.toFixed(1)}`).join(' ')
    const area = `${line} L${dots.at(-1).x.toFixed(1)},${CHART_H - CHART_PAD.b} L${dots[0].x.toFixed(1)},${CHART_H - CHART_PAD.b} Z`

    // Líneas de cuadrícula: N niveles equidistantes entre min y max
    const gridY = Array.from({ length: GRID_LINES }, (_, i) => {
        const frac = (i + 1) / (GRID_LINES + 1)
        const val  = min + frac * rng
        return { y: sy(val), val }
    })

    return { line, area, dots, gridY, max, min }
})

// ─── Tooltip de tendencia ─────────────────────────────────────────────────────
const tooltip = reactive({ visible: false, x: 0, y: 0, dia: '', v: 0 })

function showTip(dot, svgEl) {
    const rect = svgEl.getBoundingClientRect()
    const scaleX = rect.width  / CHART_W
    const scaleY = rect.height / CHART_H
    tooltip.x   = dot.x * scaleX
    tooltip.y   = dot.y * scaleY
    tooltip.dia = dot.dia
    tooltip.v   = dot.v
    tooltip.visible = true
}
function hideTip() { tooltip.visible = false }

// ─── Donut ────────────────────────────────────────────────────────────────────
const PALETTE = ['#2563EB','#16A34A','#EA580C','#7C3AED','#0891B2','#DC2626','#CA8A04','#DB2777']
const CIRC    = 2 * Math.PI * 42

const donutSegments = computed(() => {
    const cats  = categorias.value
    const total = cats.reduce((s, c) => s + c.vendido_bs, 0)
    if (total <= 0) return []
    let off = 0
    return cats.map((c, i) => {
        const f = c.vendido_bs / total
        const seg = { categoria: c.categoria, pct: Math.round(f * 1000) / 10,
            color: PALETTE[i % PALETTE.length],
            dash: `${(f * CIRC).toFixed(2)} ${CIRC.toFixed(2)}`,
            offset: (-off * CIRC).toFixed(2) }
        off += f
        return seg
    })
})

// ─── Animación de entrada ─────────────────────────────────────────────────────
onMounted(() => {
    loadCanales()
    animate('.anim-in',
        { opacity: [0, 1], transform: ['translateY(14px)', 'translateY(0)'] },
        { duration: 0.45, delay: i => i * 0.065, ease: [0.22, 1, 0.36, 1] },
    )
})

// ─── Formato ─────────────────────────────────────────────────────────────────
function fmtBs(n)  { return 'Bs. ' + Number(n ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
function fmtUsd(n) { return '$' + Number(n ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
function fmtNum(n) { return Number(n ?? 0).toLocaleString('es-VE') }
function fmtKg(n)  { return Number(n ?? 0).toFixed(3) + ' kg' }
function fmtDia(d) { return d ? new Date(d + 'T00:00:00').toLocaleDateString('es-VE', { day: '2-digit', month: 'short' }) : '' }

// ─── Canal Rendimiento ────────────────────────────────────────────────────────
const canales        = ref([])
const loadingCanales = ref(false)

async function loadCanales() {
    loadingCanales.value = true
    try {
        const res = await axios.get('/reportes/canal-rendimiento')
        canales.value = res.data.canales ?? []
    } catch (e) {
        console.error('Error cargando canales', e)
    } finally {
        loadingCanales.value = false
    }
}
</script>

<template>
    <AppLayout title="Panel Empresarial">
        <div class="emp-wrap">

            <!-- ── Encabezado ──────────────────────────────────────────────── -->
            <header class="emp-header anim-in">
                <div class="emp-header__text">
                    <h1 class="emp-title">Panel Empresarial</h1>
                    <p class="emp-sub">
                        <strong>{{ branchesData.length }}</strong>
                        {{ branchesData.length === 1 ? 'sucursal' : 'sucursales' }}
                        · {{ fmtDia(data.rango?.desde) }} — {{ fmtDia(data.rango?.hasta) }}
                    </p>
                </div>
                <div class="emp-controls">
                    <div class="date-row">
                        <div class="filter-group">
                            <label class="filter-label">Desde</label>
                            <input v-model="fechas.desde" type="date" class="filter-input" />
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Hasta</label>
                            <input v-model="fechas.hasta" type="date" class="filter-input" />
                        </div>
                    </div>
                    <button class="btn-brand btn-full" :disabled="loading" @click="loadData">
                        <span v-if="loading" class="spinner" />
                        {{ loading ? 'Cargando…' : 'Actualizar' }}
                    </button>
                </div>
            </header>

            <!-- ── Selector de sucursales ──────────────────────────────────── -->
            <div class="branch-picker anim-in">
                <div class="picker-top">
                    <span class="picker-label">Sucursales</span>
                    <span class="picker-hint">Máx. {{ max_branches }}</span>
                </div>
                <div class="chips">
                    <button
                        v-for="b in branches" :key="b.id"
                        class="chip"
                        :class="{
                            'chip--on':     isSelected(b.id),
                            'chip--locked': !isSelected(b.id) && selectedIds.length >= max_branches,
                        }"
                        @click="toggleBranch(b.id)"
                    >
                        <span class="chip-dot" />
                        {{ b.name }}
                        <span v-if="b.city" class="chip-city">{{ b.city }}</span>
                        <Lock v-if="!isSelected(b.id) && selectedIds.length >= max_branches" :size="12" />
                    </button>
                </div>
            </div>

            <p v-if="errorMsg" class="error-msg anim-in">{{ errorMsg }}</p>

            <!-- ── KPIs ────────────────────────────────────────────────────── -->
            <section class="kpi-grid">
                <div class="kpi-card kpi-card--hero anim-in">
                    <span class="kpi-icon"><DollarSign :size="24" /></span>
                    <span class="kpi-label">Ventas Totales</span>
                    <span class="kpi-value">{{ fmtUsd(kpi.vendido_usd) }}</span>
                    <span class="kpi-foot">{{ fmtBs(totals.vendido_bs) }} al cliente</span>
                </div>
                <div class="kpi-card anim-in">
                    <span class="kpi-icon"><TrendingUp :size="24" /></span>
                    <span class="kpi-label">Utilidad Bruta</span>
                    <span class="kpi-value" :class="kpi.utilidad_usd >= 0 ? 'pos' : 'neg'">
                        {{ fmtUsd(kpi.utilidad_usd) }}
                    </span>
                    <span class="kpi-foot">Margen {{ kpi.margen }}%</span>
                </div>
                <div class="kpi-card anim-in">
                    <span class="kpi-icon"><Receipt :size="24" /></span>
                    <span class="kpi-label">Ticket Promedio</span>
                    <span class="kpi-value">{{ fmtUsd(kpi.ticket_usd) }}</span>
                    <span class="kpi-foot">{{ fmtNum(kpi.ventas_count) }} transacciones</span>
                </div>
                <div class="kpi-card anim-in">
                    <span class="kpi-icon"><Scale :size="24" /></span>
                    <span class="kpi-label">Volumen</span>
                    <span class="kpi-value">{{ fmtKg(kpi.kg) }}</span>
                    <span class="kpi-foot">peso despachado</span>
                </div>
            </section>

            <!-- ── Comparativa por sucursal ────────────────────────────────── -->
            <section v-if="multiSucursal" class="card anim-in">
                <h2 class="card-title">Comparativa entre sucursales</h2>
                <div class="cmp-list">
                    <div v-for="b in branchesData" :key="b.id" class="cmp-row">
                        <div class="cmp-head">
                            <div>
                                <span class="cmp-name">{{ b.name }}</span>
                                <span v-if="b.city" class="cmp-city">{{ b.city }}</span>
                            </div>
                            <span class="cmp-amount">{{ fmtUsd(b.vendido_usd) }}</span>
                        </div>
                        <div class="cmp-track">
                            <div class="cmp-fill" :style="{ width: barW(b.vendido_usd) + '%' }" />
                        </div>
                        <div class="cmp-meta">
                            <span>{{ fmtNum(b.ventas_count) }} ventas</span>
                            <span>Ticket {{ fmtUsd(ticketBranch(b)) }}</span>
                            <span :class="b.utilidad_usd >= 0 ? 'pos' : 'neg'">
                                Util. {{ fmtUsd(b.utilidad_usd) }} · {{ b.margen_pct }}%
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── Tendencia + Donut ───────────────────────────────────────── -->
            <div class="split-grid">
                <section class="card anim-in">
                    <h2 class="card-title">Tendencia de ventas (USD)</h2>
                    <div class="trend-wrap" @mouseleave="hideTip">
                        <svg
                            v-if="trendGeometry.dots.length"
                            ref="trendSvg"
                            class="trend-svg"
                            :viewBox="`0 0 ${CHART_W} ${CHART_H}`"
                            preserveAspectRatio="none"
                        >
                            <defs>
                                <linearGradient id="trendFill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%"   stop-color="var(--brand)" stop-opacity="0.28" />
                                    <stop offset="100%" stop-color="var(--brand)" stop-opacity="0" />
                                </linearGradient>
                            </defs>

                            <!-- Grid lines -->
                            <line
                                v-for="g in trendGeometry.gridY" :key="g.y"
                                :x1="CHART_PAD.l" :y1="g.y.toFixed(1)"
                                :x2="CHART_W - CHART_PAD.r" :y2="g.y.toFixed(1)"
                                class="grid-line"
                            />

                            <path :d="trendGeometry.area" fill="url(#trendFill)" />
                            <path :d="trendGeometry.line" class="trend-line" />

                            <!-- Línea vertical del tooltip -->
                            <line v-if="tooltip.visible"
                                :x1="trendGeometry.dots.find(d=>d.dia===tooltip.dia)?.x ?? 0"
                                y1="0"
                                :x2="trendGeometry.dots.find(d=>d.dia===tooltip.dia)?.x ?? 0"
                                :y2="CHART_H"
                                class="tip-vline"
                            />

                            <!-- Dots visibles + target táctil invisible -->
                            <g v-for="d in trendGeometry.dots" :key="d.dia">
                                <circle :cx="d.x" :cy="d.y" r="4" class="trend-dot"
                                    :class="{ 'trend-dot--active': tooltip.dia === d.dia }" />
                                <!-- target 24px para touch/mouse -->
                                <circle :cx="d.x" :cy="d.y" r="16" fill="transparent"
                                    style="cursor:pointer"
                                    @mouseenter="showTip(d, $event.currentTarget.closest('svg'))"
                                    @touchstart.prevent="showTip(d, $event.currentTarget.closest('svg'))"
                                />
                            </g>

                            <!-- Y-axis labels (máx y mín) -->
                            <text
                                :x="CHART_PAD.l + 2" :y="CHART_PAD.t - 4"
                                class="chart-label"
                            >{{ fmtUsd(trendGeometry.max) }}</text>
                        </svg>
                        <p v-else class="empty-row">Sin ventas en el rango.</p>

                        <!-- Tooltip flotante -->
                        <div
                            v-if="tooltip.visible"
                            class="trend-tip"
                            :style="{ left: tooltip.x + 'px', top: (tooltip.y - 52) + 'px' }"
                        >
                            <span class="tip-dia">{{ fmtDia(tooltip.dia) }}</span>
                            <span class="tip-val">{{ fmtUsd(tooltip.v) }}</span>
                        </div>
                    </div>
                    <div v-if="trendGeometry.dots.length" class="trend-axis">
                        <span>{{ fmtDia(tendencia[0]?.dia) }}</span>
                        <span>{{ fmtDia(tendencia.at(-1)?.dia) }}</span>
                    </div>
                </section>

                <section class="card anim-in">
                    <h2 class="card-title">Mezcla por categoría</h2>
                    <div v-if="donutSegments.length" class="donut-block">
                        <svg class="donut-svg" viewBox="0 0 120 120">
                            <circle cx="60" cy="60" r="42" class="donut-bg" />
                            <circle
                                v-for="seg in donutSegments" :key="seg.categoria"
                                cx="60" cy="60" r="42"
                                fill="none" :stroke="seg.color" stroke-width="16"
                                :stroke-dasharray="seg.dash"
                                :stroke-dashoffset="seg.offset"
                                class="donut-seg"
                            />
                        </svg>
                        <ul class="donut-legend">
                            <li v-for="seg in donutSegments" :key="seg.categoria">
                                <span class="legend-dot" :style="{ background: seg.color }" />
                                <span class="legend-name">{{ seg.categoria }}</span>
                                <span class="legend-pct">{{ seg.pct }}%</span>
                            </li>
                        </ul>
                    </div>
                    <p v-else class="empty-row">Sin datos de categorías.</p>
                </section>
            </div>

            <!-- ── Tabla resumen ───────────────────────────────────────────── -->
            <section class="card anim-in">
                <h2 class="card-title">Resumen ejecutivo por sucursal</h2>

                <!-- Vista tabla (tablet+) -->
                <div class="table-wrap hide-mobile">
                    <table class="emp-table">
                        <thead>
                            <tr>
                                <th>Sucursal</th>
                                <th class="r">Ventas</th>
                                <th class="r">Vendido USD</th>
                                <th class="r">Costo USD</th>
                                <th class="r">Utilidad USD</th>
                                <th class="r">Margen</th>
                                <th class="r">Ticket USD</th>
                                <th class="r">Kg</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="b in branchesData" :key="b.id">
                                <td class="td-first">
                                    <span class="td-name">{{ b.name }}</span>
                                    <span v-if="b.city" class="td-city">{{ b.city }}</span>
                                </td>
                                <td class="r muted">{{ fmtNum(b.ventas_count) }}</td>
                                <td class="r amount">{{ fmtUsd(b.vendido_usd) }}</td>
                                <td class="r muted">{{ fmtUsd(b.costo_usd) }}</td>
                                <td class="r" :class="b.utilidad_usd >= 0 ? 'pos' : 'neg'">{{ fmtUsd(b.utilidad_usd) }}</td>
                                <td class="r">{{ b.margen_pct }}%</td>
                                <td class="r muted">{{ fmtUsd(ticketBranch(b)) }}</td>
                                <td class="r muted">{{ fmtKg(b.kg_vendidos) }}</td>
                            </tr>
                            <tr class="total-row">
                                <td class="td-first"><strong>TOTAL GENERAL</strong></td>
                                <td class="r"><strong>{{ fmtNum(totals.ventas_count) }}</strong></td>
                                <td class="r"><strong>{{ fmtUsd(totals.vendido_usd) }}</strong></td>
                                <td class="r muted"><strong>{{ fmtUsd(totals.costo_usd) }}</strong></td>
                                <td class="r" :class="totals.utilidad_usd >= 0 ? 'pos' : 'neg'">
                                    <strong>{{ fmtUsd(totals.utilidad_usd) }}</strong>
                                </td>
                                <td class="r"><strong>{{ totals.margen_pct }}%</strong></td>
                                <td class="r"><strong>{{ fmtUsd(ticketTotal()) }}</strong></td>
                                <td class="r"><strong>{{ fmtKg(totals.kg_vendidos) }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Vista cards (mobile) -->
                <div class="mobile-cards show-mobile">
                    <div v-for="b in [...branchesData, { _total: true, name: 'TOTAL GENERAL', ...totals }]"
                         :key="b.id ?? 'total'"
                         class="mobile-card"
                         :class="{ 'mobile-card--total': b._total }"
                    >
                        <div class="mc-header">
                            <span class="mc-name">{{ b.name }}</span>
                            <span class="mc-amount">{{ fmtUsd(b.vendido_usd) }}</span>
                        </div>
                        <div class="mc-row">
                            <span class="mc-label">Ventas</span>
                            <span class="mc-val">{{ fmtNum(b.ventas_count) }}</span>
                        </div>
                        <div class="mc-row">
                            <span class="mc-label">Utilidad</span>
                            <span class="mc-val" :class="b.utilidad_usd >= 0 ? 'pos' : 'neg'">
                                {{ fmtUsd(b.utilidad_usd) }}
                            </span>
                        </div>
                        <div class="mc-row">
                            <span class="mc-label">Margen</span>
                            <span class="mc-val">{{ b.margen_pct }}%</span>
                        </div>
                        <div class="mc-row">
                            <span class="mc-label">Ticket</span>
                            <span class="mc-val">{{ fmtUsd(b._total ? ticketTotal() : ticketBranch(b)) }}</span>
                        </div>
                        <div class="mc-row">
                            <span class="mc-label">Volumen</span>
                            <span class="mc-val">{{ fmtKg(b.kg_vendidos) }}</span>
                        </div>
                        <div class="mc-row">
                            <span class="mc-label">Costo</span>
                            <span class="mc-val muted">{{ fmtUsd(b.costo_usd) }}</span>
                        </div>
                    </div>
                </div>
            </section>

        </div>

        <!-- ── Modal límite ────────────────────────────────────────────────── -->
        <Transition name="modal-fade">
            <div v-if="limitModal" class="modal-backdrop">
                <div class="modal-box">
                    <span class="modal-icon"><Lock :size="32" /></span>
                    <h3 class="modal-title">Límite del plan alcanzado</h3>
                    <p class="modal-text">
                        Tu plan permite consolidar hasta <strong>{{ max_branches }} sucursales</strong> a la vez.
                        Para ampliar, comunícate con nuestro equipo de soporte.
                    </p>
                    <button class="btn-brand" @click="limitModal = false">Entendido</button>
                </div>
            </div>
        </Transition>
    </AppLayout>
</template>

<style scoped>
/* ═══════════════════════════════════════════════════════════════════════════
   MOBILE-FIRST — base styles target 360px+
   Breakpoints: sm=480px  md=640px  lg=1024px  xl=1280px
   ═══════════════════════════════════════════════════════════════════════════ */

.emp-wrap {
    padding: 1rem;
    max-width: 1440px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}

/* ── Encabezado ─────────────────────────────────────────────────────────── */
.emp-header {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}
.emp-title {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--text-primary);
    letter-spacing: -0.02em;
    margin: 0;
    line-height: 1.2;
}
.emp-sub { font-size: 0.82rem; color: var(--text-muted); margin: 0.15rem 0 0; }
.emp-sub strong { color: var(--brand); }

.emp-controls { display: flex; flex-direction: column; gap: 0.6rem; }
.date-row { display: flex; gap: 0.5rem; }
.date-row .filter-group { flex: 1; }

.filter-group { display: flex; flex-direction: column; gap: 0.2rem; }
.filter-label {
    font-size: 0.68rem; color: var(--text-muted); font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.04em;
}
.filter-input {
    width: 100%;
    box-sizing: border-box;
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.6rem 0.7rem;
    min-height: 44px;
    color: var(--text-primary);
    font-size: 0.9rem;
    outline: none;
}
.filter-input:focus { border-color: var(--brand); }

.btn-brand {
    background: var(--brand);
    color: #fff;
    border: none;
    padding: 0 1.25rem;
    min-height: 44px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    transition: opacity 0.15s, transform 0.1s;
    white-space: nowrap;
}
.btn-brand:hover  { opacity: 0.9; }
.btn-brand:active { transform: scale(0.97); }
.btn-brand:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-full { width: 100%; }

.spinner {
    width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,0.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.65s linear infinite;
    display: inline-block;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Selector de sucursales ─────────────────────────────────────────────── */
.branch-picker {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.8rem 0.9rem;
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}
.picker-top { display: flex; justify-content: space-between; align-items: center; }
.picker-label {
    font-size: 0.68rem; font-weight: 700; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.05em;
}
.picker-hint { font-size: 0.72rem; color: var(--text-muted); }
.chips { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0 0.85rem;
    min-height: 44px;
    border-radius: 99px;
    border: 1px solid var(--border);
    background: var(--bg-base);
    color: var(--text-muted);
    font-size: 0.84rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.18s ease;
    -webkit-tap-highlight-color: transparent;
}
.chip:hover { border-color: var(--brand); }
.chip--on {
    background: var(--brand);
    border-color: var(--brand);
    color: #fff;
    box-shadow: 0 4px 14px -4px var(--brand);
}
.chip--locked { opacity: 0.5; cursor: not-allowed; }
.chip-dot { width: 7px; height: 7px; border-radius: 99px; background: currentColor; opacity: 0.7; flex-shrink: 0; }
.chip-city { font-weight: 400; opacity: 0.72; font-size: 0.74rem; }

/* ── KPIs ───────────────────────────────────────────────────────────────── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.65rem;
}
.kpi-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.kpi-card:active { transform: scale(0.98); }
.kpi-card--hero {
    grid-column: span 2;
    background: linear-gradient(135deg, var(--brand), var(--brand-hover));
    border-color: transparent;
    flex-direction: row;
    align-items: center;
    gap: 0.9rem;
    flex-wrap: wrap;
}
.kpi-card--hero .kpi-text { display: flex; flex-direction: column; gap: 0.2rem; }
.kpi-card--hero .kpi-label,
.kpi-card--hero .kpi-value,
.kpi-card--hero .kpi-foot { color: #fff; }
.kpi-icon { display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.kpi-label {
    font-size: 0.68rem; font-weight: 700; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.04em;
}
.kpi-value {
    font-size: 1.3rem; font-weight: 800; color: var(--text-primary);
    letter-spacing: -0.02em; font-variant-numeric: tabular-nums; line-height: 1.1;
}
.kpi-foot { font-size: 0.72rem; color: var(--text-muted); }

/* ── Card genérica ──────────────────────────────────────────────────────── */
.card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1rem 1.1rem;
}
.card-title {
    font-size: 0.72rem; font-weight: 700; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.04em; margin: 0 0 0.9rem;
}

/* ── Comparativa ────────────────────────────────────────────────────────── */
.cmp-list { display: flex; flex-direction: column; gap: 1rem; }
.cmp-row  { display: flex; flex-direction: column; gap: 0.3rem; }
.cmp-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; }
.cmp-name { font-weight: 700; color: var(--text-primary); font-size: 0.9rem; }
.cmp-city { font-size: 0.72rem; color: var(--text-muted); display: block; }
.cmp-amount { font-weight: 800; color: var(--text-primary); font-variant-numeric: tabular-nums; font-size: 0.95rem; white-space: nowrap; }
.cmp-track  { height: 10px; border-radius: 99px; background: var(--bg-base); overflow: hidden; }
.cmp-fill   { height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--brand-hover), var(--brand)); transition: width 0.7s cubic-bezier(0.22,1,0.36,1); }
.cmp-meta   { display: flex; gap: 0.6rem 1rem; flex-wrap: wrap; font-size: 0.76rem; color: var(--text-muted); }

/* ── Split grid ─────────────────────────────────────────────────────────── */
.split-grid { display: grid; grid-template-columns: 1fr; gap: 0.9rem; }

/* ── Tendencia ──────────────────────────────────────────────────────────── */
.trend-wrap {
    position: relative;
    border-radius: 8px;
    overflow: visible;
}
.trend-svg  { width: 100%; height: 130px; display: block; overflow: visible; }
.trend-line { fill: none; stroke: var(--brand); stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
.trend-dot  { fill: var(--bg-card); stroke: var(--brand); stroke-width: 2; transition: r 0.12s ease; }
.trend-dot--active { fill: var(--brand); r: 5; }
.grid-line  { stroke: var(--border); stroke-width: 1; stroke-dasharray: 3 4; opacity: 0.6; }
.tip-vline  { stroke: var(--brand); stroke-width: 1; stroke-dasharray: 3 3; opacity: 0.5; }
.chart-label { font-size: 22px; fill: var(--text-muted); font-family: inherit; }
.trend-axis { display: flex; justify-content: space-between; font-size: 0.68rem; color: var(--text-muted); margin-top: 0.2rem; }

/* Tooltip */
.trend-tip {
    position: absolute;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.35rem 0.65rem;
    pointer-events: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.1rem;
    transform: translateX(-50%);
    box-shadow: 0 6px 20px -6px rgba(0,0,0,0.4);
    z-index: 10;
    white-space: nowrap;
}
.tip-dia { font-size: 0.68rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
.tip-val { font-size: 0.92rem; font-weight: 800; color: var(--text-primary); font-variant-numeric: tabular-nums; }

/* ── Donut ──────────────────────────────────────────────────────────────── */
.donut-block  { display: flex; flex-direction: column; align-items: center; gap: 1rem; }
.donut-svg    { width: 110px; height: 110px; transform: rotate(-90deg); flex-shrink: 0; }
.donut-bg     { fill: none; stroke: var(--bg-base); stroke-width: 16; }
.donut-seg    { transition: stroke-dasharray 0.7s cubic-bezier(0.22,1,0.36,1); stroke-linecap: butt; }
.donut-legend { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.35rem; width: 100%; }
.donut-legend li { display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem; color: var(--text-primary); }
.legend-dot   { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
.legend-name  { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.legend-pct   { font-weight: 700; font-variant-numeric: tabular-nums; color: var(--text-muted); }

/* ── Tabla (tablet+) ────────────────────────────────────────────────────── */
.hide-mobile { display: none; }
.show-mobile { display: flex; }

.table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.emp-table  { width: 100%; min-width: 680px; border-collapse: collapse; font-size: 0.84rem; }
.emp-table th {
    padding: 0.45rem 0.75rem; text-align: left;
    font-size: 0.68rem; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.04em;
    border-bottom: 1px solid var(--border); white-space: nowrap;
}
.emp-table td {
    padding: 0.55rem 0.75rem;
    border-bottom: 1px solid var(--border);
    color: var(--text-primary); vertical-align: middle;
}
.emp-table tr:hover td { background: var(--bg-base); }
.td-first {
    position: sticky; left: 0;
    background: var(--bg-card);
    z-index: 1;
    min-width: 120px;
}
.emp-table tr:hover .td-first { background: var(--bg-base); }
.total-row td { border-top: 2px solid var(--border); border-bottom: none; background: var(--bg-base); }
.total-row .td-first { background: var(--bg-base); }

/* ── Mobile cards ───────────────────────────────────────────────────────── */
.mobile-cards {
    flex-direction: column;
    gap: 0.7rem;
}
.mobile-card {
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 0.85rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.mobile-card--total {
    border-color: var(--brand);
    background: var(--bg-card);
}
.mc-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.2rem; }
.mc-name   { font-weight: 800; font-size: 0.9rem; color: var(--text-primary); }
.mc-amount { font-weight: 800; font-size: 1rem; color: var(--text-primary); font-variant-numeric: tabular-nums; }
.mc-row    { display: flex; justify-content: space-between; align-items: baseline; }
.mc-label  { font-size: 0.76rem; color: var(--text-muted); }
.mc-val    { font-size: 0.84rem; font-weight: 600; color: var(--text-primary); font-variant-numeric: tabular-nums; }

/* ── Utilidades ─────────────────────────────────────────────────────────── */
.r      { text-align: right !important; }
.muted  { color: var(--text-muted) !important; }
.amount { font-weight: 700; font-variant-numeric: tabular-nums; }
.td-name { font-weight: 700; display: block; }
.td-city { font-size: 0.72rem; color: var(--text-muted); }
.pos { color: #16A34A !important; }
.neg { color: #EF4444 !important; }
.empty-row { text-align: center; color: var(--text-muted); padding: 1.5rem 0; font-size: 0.86rem; }
.error-msg {
    font-size: 0.84rem; color: #EF4444;
    background: rgba(239,68,68,0.1);
    padding: 0.55rem 0.9rem; border-radius: 8px;
}

/* ── Modal ──────────────────────────────────────────────────────────────── */
.modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.65);
    display: flex; align-items: flex-end; justify-content: center;
    z-index: 60; padding: 0;
}
.modal-box {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 20px 20px 0 0;
    padding: 1.5rem 1.5rem 2rem;
    width: 100%; max-width: 100%;
    text-align: center;
    display: flex; flex-direction: column; gap: 0.65rem; align-items: center;
}
.modal-icon  { display: flex; align-items: center; justify-content: center; color: var(--text-muted); }
.modal-title { font-size: 1.1rem; font-weight: 800; color: var(--text-primary); margin: 0; }
.modal-text  { font-size: 0.86rem; color: var(--text-muted); line-height: 1.55; margin: 0 0 0.3rem; }
.modal-text strong { color: var(--text-primary); }

.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }

/* ── Animación de entrada ───────────────────────────────────────────────── */
.anim-in { opacity: 0; }

/* ═══════════════════════════════════════════════════════════════════════════
   sm: 480px
   ═══════════════════════════════════════════════════════════════════════════ */
@media (min-width: 480px) {
    .emp-title  { font-size: 1.55rem; }
    .kpi-value  { font-size: 1.4rem; }
    .donut-block { flex-direction: row; align-items: center; }
    .donut-legend { width: auto; }
}

/* ═══════════════════════════════════════════════════════════════════════════
   md: 640px — pasa a tabla, oculta cards
   ═══════════════════════════════════════════════════════════════════════════ */
@media (min-width: 640px) {
    .emp-wrap   { padding: 1.25rem; gap: 1rem; }
    .emp-header { flex-direction: row; align-items: flex-end; }
    .emp-controls { flex-direction: row; align-items: flex-end; }
    .btn-full   { width: auto; }
    .kpi-grid   { grid-template-columns: repeat(4, 1fr); }
    .kpi-card--hero { grid-column: span 1; flex-direction: column; }
    .kpi-value  { font-size: 1.5rem; }
    .hide-mobile { display: block; }
    .show-mobile { display: none; }
    .modal-backdrop { align-items: center; padding: 1.5rem; }
    .modal-box { border-radius: 18px; width: auto; max-width: 420px; padding: 2rem; }
}

/* ═══════════════════════════════════════════════════════════════════════════
   lg: 1024px — tendencia + donut lado a lado
   ═══════════════════════════════════════════════════════════════════════════ */
@media (min-width: 1024px) {
    .emp-wrap   { padding: 1.5rem; gap: 1.1rem; }
    .emp-title  { font-size: 1.7rem; }
    .split-grid { grid-template-columns: 1.5fr 1fr; }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 12px 30px -12px rgba(0,0,0,0.4); }
    .trend-svg  { height: 150px; }
}
</style>
