<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { animate } from 'motion'
import axios from 'axios'

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

// Sucursales seleccionadas inicialmente = las que vinieron en initial
const selectedIds = ref((props.initial?.branches ?? []).map(b => b.id))

const branchesData = computed(() => data.value?.branches ?? [])
const totals       = computed(() => data.value?.totals ?? {})
const tendencia    = computed(() => data.value?.tendencia ?? [])
const categorias   = computed(() => data.value?.categorias ?? [])

const multiSucursal = computed(() => branchesData.value.length > 1)

// ─── Selección de sucursales ──────────────────────────────────────────────────
function isSelected(id) {
    return selectedIds.value.includes(id)
}

function toggleBranch(id) {
    if (isSelected(id)) {
        if (selectedIds.value.length === 1) return // mínimo 1
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

// ─── KPIs animados (count-up con Motion One) ──────────────────────────────────
const kpi = reactive({ vendido_usd: 0, utilidad_usd: 0, ticket_prom_usd: 0, ventas_count: 0, kg: 0, margen: 0 })

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
    const ticketUsd = t.ventas_count > 0 ? t.vendido_usd / t.ventas_count : 0
    countTo('vendido_usd',    t.vendido_usd,  2)
    countTo('utilidad_usd',   t.utilidad_usd, 2)
    countTo('ticket_prom_usd', ticketUsd,     2)
    countTo('ventas_count',   t.ventas_count, 0)
    countTo('kg',             t.kg_vendidos,  3)
    countTo('margen',         t.margen_pct,   1)
}

watch(data, refreshKpis, { immediate: true })

// ─── Comparativa: barras por sucursal ─────────────────────────────────────────
const maxVendidoBranch = computed(() =>
    Math.max(...branchesData.value.map(b => b.vendido_usd), 1),
)

function branchBarWidth(v) {
    return Math.round((v / maxVendidoBranch.value) * 100)
}

// ticket promedio USD por sucursal
function ticketUsdBranch(b) {
    return b.ventas_count > 0 ? b.vendido_usd / b.ventas_count : 0
}

// ─── Tendencia: línea SVG ─────────────────────────────────────────────────────
const CHART_W = 640
const CHART_H = 180
const CHART_PAD = 8

const trendGeometry = computed(() => {
    const pts = tendencia.value
    if (pts.length === 0) return { line: '', area: '', dots: [] }

    const max = Math.max(...pts.map(p => p.total_usd), 1)
    const stepX = pts.length > 1 ? (CHART_W - CHART_PAD * 2) / (pts.length - 1) : 0
    const scaleY = v => CHART_H - CHART_PAD - (v / max) * (CHART_H - CHART_PAD * 2)

    const dots = pts.map((p, i) => ({
        x: CHART_PAD + i * stepX,
        y: scaleY(p.total_usd),
        dia: p.dia,
        total_usd: p.total_usd,
    }))

    const line = dots.map((d, i) => `${i === 0 ? 'M' : 'L'}${d.x.toFixed(1)},${d.y.toFixed(1)}`).join(' ')
    const area = `${line} L${dots[dots.length - 1].x.toFixed(1)},${CHART_H} L${dots[0].x.toFixed(1)},${CHART_H} Z`

    return { line, area, dots }
})

// ─── Mezcla por categoría: donut SVG ──────────────────────────────────────────
const DONUT_PALETTE = ['#2563EB', '#16A34A', '#EA580C', '#7C3AED', '#0891B2', '#DC2626', '#CA8A04', '#DB2777']
const DONUT_C = 2 * Math.PI * 42 // circunferencia (r=42)

const donutSegments = computed(() => {
    const cats = categorias.value
    const total = cats.reduce((s, c) => s + c.vendido_bs, 0)
    if (total <= 0) return []

    let offset = 0
    return cats.map((c, i) => {
        const frac = c.vendido_bs / total
        const seg = {
            categoria: c.categoria,
            vendido_bs: c.vendido_bs,
            pct: Math.round(frac * 1000) / 10,
            color: DONUT_PALETTE[i % DONUT_PALETTE.length],
            dash: `${(frac * DONUT_C).toFixed(2)} ${DONUT_C.toFixed(2)}`,
            offset: (-offset * DONUT_C).toFixed(2),
        }
        offset += frac
        return seg
    })
})

// ─── Animaciones de entrada ───────────────────────────────────────────────────
onMounted(() => {
    animate(
        '.anim-in',
        { opacity: [0, 1], transform: ['translateY(16px)', 'translateY(0)'] },
        { duration: 0.5, delay: (i) => i * 0.07, ease: [0.22, 1, 0.36, 1] },
    )
})

// ─── Formato ──────────────────────────────────────────────────────────────────
function fmtBs(n)  { return 'Bs. ' + Number(n ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
function fmtBsK(n) {
    const v = Number(n ?? 0)
    if (Math.abs(v) >= 1_000_000) return 'Bs. ' + (v / 1_000_000).toFixed(2) + ' M'
    if (Math.abs(v) >= 1_000)     return 'Bs. ' + (v / 1_000).toFixed(1) + ' K'
    return fmtBs(v)
}
function fmtUsd(n) { return '$' + Number(n ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
function fmtNum(n) { return Number(n ?? 0).toLocaleString('es-VE') }
function fmtKg(n)  { return Number(n ?? 0).toFixed(3) + ' kg' }
function fmtDia(d) { return d ? new Date(d + 'T00:00:00').toLocaleDateString('es-VE', { day: '2-digit', month: 'short' }) : '' }
</script>

<template>
    <AppLayout title="Panel Empresarial">
        <div class="emp-wrap">

            <!-- ─── Encabezado ───────────────────────────────────────────── -->
            <header class="emp-header anim-in">
                <div>
                    <h1 class="emp-title">Panel Empresarial</h1>
                    <p class="emp-sub">
                        Visión consolidada de
                        <strong>{{ branchesData.length }}</strong>
                        {{ branchesData.length === 1 ? 'sucursal' : 'sucursales' }}
                        · {{ fmtDia(data.rango?.desde) }} — {{ fmtDia(data.rango?.hasta) }}
                    </p>
                </div>
                <div class="emp-daterange">
                    <div class="filter-group">
                        <label class="filter-label">Desde</label>
                        <input v-model="fechas.desde" type="date" class="filter-input" />
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Hasta</label>
                        <input v-model="fechas.hasta" type="date" class="filter-input" />
                    </div>
                    <button class="btn-brand" :disabled="loading" @click="loadData">
                        {{ loading ? 'Cargando…' : 'Actualizar' }}
                    </button>
                </div>
            </header>

            <!-- ─── Selector de sucursales ───────────────────────────────── -->
            <div class="branch-picker anim-in">
                <span class="picker-label">Sucursales</span>
                <div class="chips">
                    <button
                        v-for="b in branches" :key="b.id"
                        class="chip"
                        :class="{
                            'chip--on': isSelected(b.id),
                            'chip--locked': !isSelected(b.id) && selectedIds.length >= max_branches,
                        }"
                        @click="toggleBranch(b.id)"
                    >
                        <span class="chip-dot" />
                        {{ b.name }}
                        <span v-if="b.city" class="chip-city">{{ b.city }}</span>
                        <span
                            v-if="!isSelected(b.id) && selectedIds.length >= max_branches"
                            class="chip-lock"
                        >🔒</span>
                    </button>
                </div>
                <span class="picker-hint">Plan actual: hasta {{ max_branches }} sucursales</span>
            </div>

            <p v-if="errorMsg" class="error-msg anim-in">{{ errorMsg }}</p>

            <!-- ─── KPIs Hero ────────────────────────────────────────────── -->
            <section class="kpi-grid">
                <div class="kpi-card kpi-card--hero anim-in">
                    <span class="kpi-icon">💰</span>
                    <span class="kpi-label">Ventas Totales</span>
                    <span class="kpi-value">{{ fmtUsd(kpi.vendido_usd) }}</span>
                    <span class="kpi-foot">Bs. {{ fmtBs(totals.vendido_bs) }} al cliente</span>
                </div>
                <div class="kpi-card anim-in">
                    <span class="kpi-icon">📈</span>
                    <span class="kpi-label">Utilidad Bruta</span>
                    <span class="kpi-value" :class="kpi.utilidad_usd >= 0 ? 'pos' : 'neg'">
                        {{ fmtUsd(kpi.utilidad_usd) }}
                    </span>
                    <span class="kpi-foot">Margen {{ kpi.margen }}%</span>
                </div>
                <div class="kpi-card anim-in">
                    <span class="kpi-icon">🧾</span>
                    <span class="kpi-label">Ticket Promedio</span>
                    <span class="kpi-value">{{ fmtUsd(kpi.ticket_prom_usd) }}</span>
                    <span class="kpi-foot">{{ fmtNum(kpi.ventas_count) }} transacciones</span>
                </div>
                <div class="kpi-card anim-in">
                    <span class="kpi-icon">⚖️</span>
                    <span class="kpi-label">Volumen Vendido</span>
                    <span class="kpi-value">{{ fmtKg(kpi.kg) }}</span>
                    <span class="kpi-foot">peso despachado</span>
                </div>
            </section>

            <!-- ─── Comparativa por sucursal ─────────────────────────────── -->
            <section v-if="multiSucursal" class="card anim-in">
                <h2 class="card-title">Comparativa entre sucursales</h2>
                <div class="cmp-list">
                    <div v-for="b in branchesData" :key="b.id" class="cmp-row">
                        <div class="cmp-head">
                            <span class="cmp-name">{{ b.name }}</span>
                            <span class="cmp-amount">{{ fmtUsd(b.vendido_usd) }}</span>
                        </div>
                        <div class="cmp-track">
                            <div class="cmp-fill" :style="{ width: branchBarWidth(b.vendido_usd) + '%' }" />
                        </div>
                        <div class="cmp-meta">
                            <span>{{ fmtNum(b.ventas_count) }} ventas</span>
                            <span>Ticket {{ fmtUsd(ticketUsdBranch(b)) }}</span>
                            <span :class="b.utilidad_usd >= 0 ? 'pos' : 'neg'">
                                Utilidad {{ fmtUsd(b.utilidad_usd) }} · {{ b.margen_pct }}%
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ─── Tendencia + Donut ────────────────────────────────────── -->
            <div class="split-grid">
                <section class="card anim-in">
                    <h2 class="card-title">Tendencia de ventas (USD)</h2>
                    <svg
                        v-if="trendGeometry.dots.length"
                        class="trend-svg"
                        :viewBox="`0 0 ${CHART_W} ${CHART_H}`"
                        preserveAspectRatio="none"
                    >
                        <defs>
                            <linearGradient id="trendFill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%"   stop-color="var(--brand)" stop-opacity="0.35" />
                                <stop offset="100%" stop-color="var(--brand)" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        <path :d="trendGeometry.area" fill="url(#trendFill)" />
                        <path :d="trendGeometry.line" class="trend-line" />
                        <g v-for="d in trendGeometry.dots" :key="d.dia">
                            <circle :cx="d.x" :cy="d.y" r="3.5" class="trend-dot" />
                        </g>
                    </svg>
                    <p v-else class="empty-row">Sin ventas en el rango seleccionado.</p>
                    <div v-if="trendGeometry.dots.length" class="trend-axis">
                        <span>{{ fmtDia(tendencia[0]?.dia) }}</span>
                        <span>{{ fmtDia(tendencia[tendencia.length - 1]?.dia) }}</span>
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
                                fill="none"
                                :stroke="seg.color"
                                stroke-width="16"
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

            <!-- ─── Tabla resumen ────────────────────────────────────────── -->
            <section class="card anim-in">
                <h2 class="card-title">Resumen ejecutivo por sucursal</h2>
                <div class="table-wrap">
                    <table class="emp-table">
                        <thead>
                            <tr>
                                <th>Sucursal</th>
                                <th class="right">Ventas</th>
                                        <th class="right">Vendido USD</th>
                                <th class="right">Costo USD</th>
                                <th class="right">Utilidad USD</th>
                                <th class="right">Margen</th>
                                <th class="right">Ticket USD</th>
                                <th class="right">Kg</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="b in branchesData" :key="b.id">
                                <td>
                                    <span class="td-name">{{ b.name }}</span>
                                    <span v-if="b.city" class="td-city">{{ b.city }}</span>
                                </td>
                                <td class="right muted">{{ fmtNum(b.ventas_count) }}</td>
                                <td class="right amount">{{ fmtUsd(b.vendido_usd) }}</td>
                                <td class="right muted">{{ fmtUsd(b.costo_usd) }}</td>
                                <td class="right" :class="b.utilidad_usd >= 0 ? 'pos' : 'neg'">{{ fmtUsd(b.utilidad_usd) }}</td>
                                <td class="right">{{ b.margen_pct }}%</td>
                                <td class="right muted">{{ fmtUsd(ticketUsdBranch(b)) }}</td>
                                <td class="right muted">{{ fmtKg(b.kg_vendidos) }}</td>
                            </tr>
                            <tr class="total-row">
                                <td><strong>TOTAL GENERAL</strong></td>
                                <td class="right"><strong>{{ fmtNum(totals.ventas_count) }}</strong></td>
                                <td class="right"><strong>{{ fmtUsd(totals.vendido_usd) }}</strong></td>
                                <td class="right muted"><strong>{{ fmtUsd(totals.costo_usd) }}</strong></td>
                                <td class="right" :class="totals.utilidad_usd >= 0 ? 'pos' : 'neg'">
                                    <strong>{{ fmtUsd(totals.utilidad_usd) }}</strong>
                                </td>
                                <td class="right"><strong>{{ totals.margen_pct }}%</strong></td>
                                <td class="right"><strong>{{ fmtUsd(totals.ticket_prom_bs) }}</strong></td>
                                <td class="right"><strong>{{ fmtKg(totals.kg_vendidos) }}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>

        <!-- ─── Modal límite de sucursales ───────────────────────────────── -->
        <Transition name="modal-fade">
            <div v-if="limitModal" class="modal-backdrop" @click.self="limitModal = false">
                <div class="modal-box">
                    <span class="modal-icon">🔒</span>
                    <h3 class="modal-title">Límite del plan alcanzado</h3>
                    <p class="modal-text">
                        Tu plan actual permite consolidar hasta
                        <strong>{{ max_branches }} sucursales</strong> a la vez.
                        Para visualizar más sucursales en simultáneo, comunícate con
                        nuestro equipo de soporte y con gusto ampliamos tu plan.
                    </p>
                    <button class="btn-brand" @click="limitModal = false">Entendido</button>
                </div>
            </div>
        </Transition>
    </AppLayout>
</template>

<style scoped>
.emp-wrap {
    padding: 1.5rem;
    max-width: 1440px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}

/* ─── Encabezado ──────────────────────────────────────────────────────────── */
.emp-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 1rem;
}
.emp-title {
    font-size: 1.7rem;
    font-weight: 800;
    color: var(--text-primary);
    letter-spacing: -0.02em;
    margin: 0;
}
.emp-sub { font-size: 0.86rem; color: var(--text-muted); margin: 0.2rem 0 0; }
.emp-sub strong { color: var(--brand); }
.emp-daterange { display: flex; align-items: flex-end; gap: 0.6rem; }

.filter-group { display: flex; flex-direction: column; gap: 0.25rem; }
.filter-label {
    font-size: 0.7rem; color: var(--text-muted); font-weight: 600;
    text-transform: uppercase; letter-spacing: 0.04em;
}
.filter-input {
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.42rem 0.7rem;
    color: var(--text-primary);
    font-size: 0.88rem;
    outline: none;
}
.filter-input:focus { border-color: var(--brand); }

.btn-brand {
    background: var(--brand);
    color: #fff;
    border: none;
    padding: 0.5rem 1.25rem;
    border-radius: 9px;
    font-weight: 700;
    font-size: 0.88rem;
    cursor: pointer;
    transition: opacity 0.15s, transform 0.1s;
}
.btn-brand:hover { opacity: 0.9; }
.btn-brand:active { transform: scale(0.97); }
.btn-brand:disabled { opacity: 0.5; cursor: not-allowed; }

/* ─── Selector de sucursales ──────────────────────────────────────────────── */
.branch-picker {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.6rem 0.9rem;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.85rem 1rem;
}
.picker-label {
    font-size: 0.72rem; font-weight: 700; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.05em;
}
.chips { display: flex; flex-wrap: wrap; gap: 0.45rem; }
.chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.8rem;
    border-radius: 99px;
    border: 1px solid var(--border);
    background: var(--bg-base);
    color: var(--text-muted);
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.18s ease;
}
.chip:hover { border-color: var(--brand); }
.chip--on {
    background: var(--brand);
    border-color: var(--brand);
    color: #fff;
    box-shadow: 0 4px 14px -4px var(--brand);
}
.chip--locked { opacity: 0.55; cursor: not-allowed; }
.chip-dot {
    width: 7px; height: 7px; border-radius: 99px;
    background: currentColor; opacity: 0.7;
}
.chip-city { font-weight: 400; opacity: 0.75; font-size: 0.74rem; }
.chip-lock { font-size: 0.72rem; }
.picker-hint { font-size: 0.74rem; color: var(--text-muted); margin-left: auto; }

/* ─── KPIs ────────────────────────────────────────────────────────────────── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.9rem;
}
.kpi-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1.2rem 1.3rem;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    position: relative;
    overflow: hidden;
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 12px 30px -12px rgba(0,0,0,0.45); }
.kpi-card--hero {
    background: linear-gradient(135deg, var(--brand), var(--brand-hover));
    border-color: transparent;
}
.kpi-card--hero .kpi-label,
.kpi-card--hero .kpi-value,
.kpi-card--hero .kpi-foot { color: #fff; }
.kpi-icon { font-size: 1.4rem; }
.kpi-label {
    font-size: 0.74rem; font-weight: 700; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.04em;
}
.kpi-value {
    font-size: 1.55rem; font-weight: 800; color: var(--text-primary);
    letter-spacing: -0.02em; font-variant-numeric: tabular-nums;
    line-height: 1.15;
}
.kpi-foot { font-size: 0.76rem; color: var(--text-muted); }

/* ─── Card genérica ───────────────────────────────────────────────────────── */
.card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1.25rem 1.35rem;
}
.card-title {
    font-size: 0.8rem; font-weight: 700; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.04em;
    margin: 0 0 1rem;
}

/* ─── Comparativa ─────────────────────────────────────────────────────────── */
.cmp-list { display: flex; flex-direction: column; gap: 1rem; }
.cmp-row { display: flex; flex-direction: column; gap: 0.35rem; }
.cmp-head { display: flex; justify-content: space-between; align-items: baseline; }
.cmp-name { font-weight: 700; color: var(--text-primary); font-size: 0.92rem; }
.cmp-amount {
    font-weight: 800; color: var(--text-primary);
    font-variant-numeric: tabular-nums; font-size: 0.95rem;
}
.cmp-track {
    height: 12px; border-radius: 99px;
    background: var(--bg-base); overflow: hidden;
}
.cmp-fill {
    height: 100%; border-radius: 99px;
    background: linear-gradient(90deg, var(--brand-hover), var(--brand));
    transition: width 0.7s cubic-bezier(0.22, 1, 0.36, 1);
}
.cmp-meta {
    display: flex; gap: 1.1rem; flex-wrap: wrap;
    font-size: 0.78rem; color: var(--text-muted);
}

/* ─── Split grid ──────────────────────────────────────────────────────────── */
.split-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 0.9rem;
}

/* ─── Tendencia ───────────────────────────────────────────────────────────── */
.trend-svg { width: 100%; height: 180px; display: block; }
.trend-line {
    fill: none;
    stroke: var(--brand);
    stroke-width: 2.5;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.trend-dot { fill: var(--bg-card); stroke: var(--brand); stroke-width: 2; }
.trend-axis {
    display: flex; justify-content: space-between;
    font-size: 0.72rem; color: var(--text-muted); margin-top: 0.4rem;
}

/* ─── Donut ───────────────────────────────────────────────────────────────── */
.donut-block { display: flex; align-items: center; gap: 1.2rem; }
.donut-svg {
    width: 132px; height: 132px;
    transform: rotate(-90deg);
    flex-shrink: 0;
}
.donut-bg { fill: none; stroke: var(--bg-base); stroke-width: 16; }
.donut-seg {
    transition: stroke-dasharray 0.7s cubic-bezier(0.22, 1, 0.36, 1);
    stroke-linecap: butt;
}
.donut-legend {
    list-style: none; margin: 0; padding: 0;
    display: flex; flex-direction: column; gap: 0.4rem;
    flex: 1;
}
.donut-legend li {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.82rem; color: var(--text-primary);
}
.legend-dot { width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0; }
.legend-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.legend-pct { font-weight: 700; font-variant-numeric: tabular-nums; color: var(--text-muted); }

/* ─── Tabla ───────────────────────────────────────────────────────────────── */
.table-wrap { overflow-x: auto; }
.emp-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.emp-table th {
    padding: 0.5rem 0.8rem; text-align: left;
    font-size: 0.7rem; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.04em;
    border-bottom: 1px solid var(--border); white-space: nowrap;
}
.emp-table td {
    padding: 0.6rem 0.8rem;
    border-bottom: 1px solid var(--border);
    color: var(--text-primary); vertical-align: middle;
}
.emp-table tr:hover td { background: var(--bg-base); }
.right { text-align: right !important; }
.muted { color: var(--text-muted) !important; }
.amount { font-weight: 700; font-variant-numeric: tabular-nums; }
.td-name { font-weight: 700; display: block; }
.td-city { font-size: 0.74rem; color: var(--text-muted); }
.total-row td {
    border-top: 2px solid var(--border);
    border-bottom: none;
    background: var(--bg-base);
    padding-top: 0.7rem; padding-bottom: 0.7rem;
}
.pos { color: #16A34A !important; }
.neg { color: #EF4444 !important; }
.empty-row {
    text-align: center; color: var(--text-muted);
    padding: 2rem 0; font-size: 0.88rem;
}
.error-msg {
    font-size: 0.85rem; color: #EF4444;
    background: rgba(239, 68, 68, 0.1);
    padding: 0.55rem 0.9rem; border-radius: 8px;
}

/* ─── Modal ───────────────────────────────────────────────────────────────── */
.modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(0, 0, 0, 0.6);
    display: flex; align-items: center; justify-content: center;
    z-index: 60; padding: 1.5rem;
}
.modal-box {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 2rem;
    max-width: 420px; text-align: center;
    display: flex; flex-direction: column; gap: 0.7rem; align-items: center;
}
.modal-icon { font-size: 2.4rem; }
.modal-title { font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0; }
.modal-text { font-size: 0.88rem; color: var(--text-muted); line-height: 1.55; margin: 0 0 0.5rem; }
.modal-text strong { color: var(--text-primary); }

.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }

/* ─── Animación de entrada ────────────────────────────────────────────────── */
.anim-in { opacity: 0; }

/* ─── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 1024px) {
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .split-grid { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
    .kpi-grid { grid-template-columns: 1fr; }
    .emp-daterange { width: 100%; flex-wrap: wrap; }
    .donut-block { flex-direction: column; }
}
</style>
