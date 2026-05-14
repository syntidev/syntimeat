<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    cashRegister: { type: Object, default: null },
    history:      { type: Array,  default: () => [] },
    kpis:         { type: Object, default: null },
    todayRate:    { type: Number, default: 1 },
});

// ─── Tabs ─────────────────────────────────────────────────────────────────────
const activeTab = ref('day'); // 'day' | 'history'

// ─── Formato ─────────────────────────────────────────────────────────────────
function fmtBs(n)   { return 'Bs. ' + Number(n ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function fmtUsd(n)  { return '$' + Number(n ?? 0).toFixed(2); }
function fmtTime(d) { return d ? new Date(d).toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit' }) : '—'; }
function fmtDate(d) { return d ? new Date(d).toLocaleDateString('es-VE') : '—'; }

// ─── Modal Abrir Caja ─────────────────────────────────────────────────────────
const openModal = ref(false);
const openForm  = useForm({ opening_amount_bs: '' });

function submitOpen() {
    openForm.post(route('cash.open'), {
        onSuccess: () => { openModal.value = false; openForm.reset(); },
    });
}

// ─── Modal Movimiento ─────────────────────────────────────────────────────────
const movModal = ref(false);
const movForm  = useForm({ type: 'out', amount_bs: '', concept: '' });

function submitMovement() {
    movForm.post(route('cash.movement', { register: props.cashRegister?.id }), {
        onSuccess: () => { movModal.value = false; movForm.reset(); movForm.type = 'out'; },
    });
}

// ─── Modal Corte ──────────────────────────────────────────────────────────────
const corteModal = ref(false);
const corteForm  = useForm({ counted_cash_bs: '', notes: '' });

const corteCountedUsd = computed(() => {
    const val = parseFloat(corteForm.counted_cash_bs) || 0;
    return props.todayRate > 0 ? val / props.todayRate : 0;
});
const corteExpectedBs = computed(() => props.kpis?.expected_bs ?? 0);
const corteDiffBs     = computed(() => {
    const counted  = parseFloat(corteForm.counted_cash_bs) || 0;
    return counted - corteExpectedBs.value;
});

function submitCorte() {
    corteForm.post(route('cash.close', { register: props.cashRegister?.id }), {
        onSuccess: () => { corteModal.value = false; corteForm.reset(); },
    });
}
</script>

<template>
    <AppLayout title="Caja">

        <!-- Sin caja abierta ─────────────────────────────────────────────── -->
        <div v-if="!cashRegister" class="no-cash-wrap">
            <div class="no-cash-card">
                <div class="no-cash-icon">🏧</div>
                <h2 class="no-cash-title">No hay caja abierta</h2>
                <p class="no-cash-hint">
                    Debes abrir la caja para registrar ventas.
                </p>
                <p class="no-cash-rate">Tasa del día: <strong>{{ fmtBs(todayRate) }} / USD</strong></p>
                <button class="btn btn-brand" @click="openModal = true">Abrir Caja</button>
            </div>
        </div>

        <!-- Caja abierta ─────────────────────────────────────────────────── -->
        <div v-else class="cash-wrap">

            <!-- Header con tabs -->
            <div class="cash-header">
                <div class="cash-tabs">
                    <button class="cash-tab" :class="{ active: activeTab === 'day' }" @click="activeTab = 'day'">Caja del Día</button>
                    <button class="cash-tab" :class="{ active: activeTab === 'history' }" @click="activeTab = 'history'">Historial</button>
                </div>
                <div class="cash-actions">
                    <button class="btn btn-ghost" @click="movModal = true">+ Movimiento</button>
                    <button class="btn btn-danger" @click="corteModal = true">Hacer Corte</button>
                </div>
            </div>

            <!-- Tab: Caja del Día ──────────────────────────────────────── -->
            <div v-if="activeTab === 'day'" class="tab-content">

                <!-- Info de apertura -->
                <div class="cash-info-bar">
                    <span class="ci-label">{{ cashRegister.name }}</span>
                    <span class="ci-sep">|</span>
                    <span class="ci-label">Abierta: {{ fmtTime(cashRegister.opened_at) }}</span>
                    <span class="ci-sep">|</span>
                    <span class="ci-label">Apertura: {{ fmtBs((cashRegister.opening_amount_usd ?? 0) * todayRate) }}</span>
                    <span class="ci-sep">|</span>
                    <span class="ci-label">Tasa: {{ fmtBs(todayRate) }}/USD</span>
                </div>

                <!-- KPIs -->
                <div class="kpi-grid" v-if="kpis">
                    <div class="kpi-card">
                        <p class="kpi-label">Efectivo Esperado</p>
                        <p class="kpi-value">{{ fmtBs(kpis.expected_bs) }}</p>
                    </div>
                    <div class="kpi-card">
                        <p class="kpi-label">Ventas del Día</p>
                        <p class="kpi-value amber">{{ fmtBs(kpis.sales_total_bs) }}</p>
                    </div>
                    <div class="kpi-card">
                        <p class="kpi-label">Movimientos</p>
                        <p class="kpi-value">{{ kpis.movements_count }}</p>
                    </div>
                    <div class="kpi-card">
                        <p class="kpi-label">Tasa del Día</p>
                        <p class="kpi-value">{{ fmtBs(kpis.rate) }}</p>
                    </div>
                </div>

                <!-- Tabla movimientos -->
                <div class="table-card">
                    <p class="table-title">Movimientos</p>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Tipo</th>
                                <th>Concepto</th>
                                <th class="text-right">Monto Bs.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!cashRegister.movements?.length">
                                <td colspan="4" class="empty-msg">Sin movimientos registrados.</td>
                            </tr>
                            <tr v-for="mov in cashRegister.movements" :key="mov.id">
                                <td class="muted">{{ fmtTime(mov.created_at) }}</td>
                                <td>
                                    <span class="badge" :class="mov.type === 'in' ? 'badge-in' : 'badge-out'">
                                        {{ mov.type === 'in' ? 'Ingreso' : 'Retiro' }}
                                    </span>
                                </td>
                                <td>{{ mov.concept }}</td>
                                <td class="text-right" :class="mov.type === 'in' ? 'green' : 'red'">
                                    {{ mov.type === 'out' ? '−' : '+' }}{{ fmtBs((mov.amount_usd ?? 0) * todayRate) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Historial ─────────────────────────────────────────── -->
            <div v-if="activeTab === 'history'" class="tab-content">
                <div class="table-card">
                    <p class="table-title">Cierres anteriores</p>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th class="text-right">Apertura USD</th>
                                <th class="text-right">Esperado USD</th>
                                <th class="text-right">Contado USD</th>
                                <th class="text-right">Diferencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!history.length">
                                <td colspan="5" class="empty-msg">Sin cierres registrados.</td>
                            </tr>
                            <tr v-for="reg in history" :key="reg.id">
                                <td>{{ fmtDate(reg.closed_at) }}</td>
                                <td class="text-right muted">{{ fmtUsd(reg.opening_amount_usd) }}</td>
                                <td class="text-right muted">{{ fmtUsd(reg.expected_cash_usd) }}</td>
                                <td class="text-right">{{ fmtUsd(reg.counted_cash_usd) }}</td>
                                <td class="text-right" :class="(reg.difference_usd ?? 0) < 0 ? 'red' : 'green'">
                                    {{ fmtUsd(reg.difference_usd) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal Abrir Caja ──────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="openModal" class="modal-overlay" @click.self="openModal = false">
                <div class="modal-box">
                    <div class="modal-header">
                        <h3>Abrir Caja</h3>
                        <button class="modal-close" @click="openModal = false">×</button>
                    </div>
                    <p class="modal-hint">Tasa del día: <strong>{{ fmtBs(todayRate) }} / USD</strong></p>
                    <form @submit.prevent="submitOpen" class="modal-form">
                        <label class="form-label">Efectivo inicial (Bs.)</label>
                        <input
                            v-model="openForm.opening_amount_bs"
                            type="number"
                            min="0"
                            step="0.01"
                            class="form-input"
                            placeholder="0.00"
                            required
                            autofocus
                        />
                        <p v-if="openForm.errors.opening_amount_bs" class="form-error">{{ openForm.errors.opening_amount_bs }}</p>
                        <p v-if="openForm.errors.caja" class="form-error">{{ openForm.errors.caja }}</p>
                        <div class="modal-actions">
                            <button type="button" class="btn btn-ghost" @click="openModal = false">Cancelar</button>
                            <button type="submit" class="btn btn-brand" :disabled="openForm.processing">
                                {{ openForm.processing ? 'Abriendo…' : 'Abrir Caja' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal Movimiento ─────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="movModal" class="modal-overlay" @click.self="movModal = false">
                <div class="modal-box">
                    <div class="modal-header">
                        <h3>Registrar Movimiento</h3>
                        <button class="modal-close" @click="movModal = false">×</button>
                    </div>
                    <form @submit.prevent="submitMovement" class="modal-form">
                        <label class="form-label">Tipo</label>
                        <div class="type-toggle">
                            <button
                                type="button"
                                class="type-btn"
                                :class="{ active: movForm.type === 'out' }"
                                @click="movForm.type = 'out'"
                            >Retiro</button>
                            <button
                                type="button"
                                class="type-btn"
                                :class="{ active: movForm.type === 'in' }"
                                @click="movForm.type = 'in'"
                            >Ingreso</button>
                        </div>

                        <label class="form-label">Monto (Bs.)</label>
                        <input
                            v-model="movForm.amount_bs"
                            type="number"
                            min="0.01"
                            step="0.01"
                            class="form-input"
                            placeholder="0.00"
                            required
                        />

                        <label class="form-label">Concepto</label>
                        <input
                            v-model="movForm.concept"
                            type="text"
                            class="form-input"
                            placeholder="Ej: Retiro para cambio"
                            required
                        />

                        <p v-if="movForm.errors.amount_bs" class="form-error">{{ movForm.errors.amount_bs }}</p>
                        <p v-if="movForm.errors.concept" class="form-error">{{ movForm.errors.concept }}</p>

                        <div class="modal-actions">
                            <button type="button" class="btn btn-ghost" @click="movModal = false">Cancelar</button>
                            <button type="submit" class="btn btn-brand" :disabled="movForm.processing">
                                {{ movForm.processing ? 'Guardando…' : 'Registrar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal Corte ──────────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="corteModal" class="modal-overlay" @click.self="corteModal = false">
                <div class="modal-box">
                    <div class="modal-header">
                        <h3>Hacer Corte de Caja</h3>
                        <button class="modal-close" @click="corteModal = false">×</button>
                    </div>
                    <form @submit.prevent="submitCorte" class="modal-form">

                        <!-- Esperado vs contado -->
                        <div class="corte-summary">
                            <div class="corte-row">
                                <span class="corte-label">Efectivo esperado</span>
                                <span class="corte-val">{{ fmtBs(corteExpectedBs) }}</span>
                            </div>
                            <div class="corte-row">
                                <span class="corte-label">Efectivo contado</span>
                                <span class="corte-val">{{ fmtBs(parseFloat(corteForm.counted_cash_bs) || 0) }}</span>
                            </div>
                            <div class="corte-divider"></div>
                            <div class="corte-row">
                                <span class="corte-label">Diferencia</span>
                                <span class="corte-val" :class="corteDiffBs < 0 ? 'red' : corteDiffBs > 0 ? 'green' : ''">
                                    {{ fmtBs(corteDiffBs) }}
                                </span>
                            </div>
                        </div>

                        <label class="form-label">Efectivo contado (Bs.)</label>
                        <input
                            v-model="corteForm.counted_cash_bs"
                            type="number"
                            min="0"
                            step="0.01"
                            class="form-input"
                            placeholder="0.00"
                            required
                            autofocus
                        />

                        <label class="form-label">Notas (opcional)</label>
                        <textarea
                            v-model="corteForm.notes"
                            class="form-input"
                            rows="2"
                            placeholder="Observaciones del corte…"
                        ></textarea>

                        <p v-if="corteForm.errors.counted_cash_bs" class="form-error">{{ corteForm.errors.counted_cash_bs }}</p>

                        <div class="modal-actions">
                            <button type="button" class="btn btn-ghost" @click="corteModal = false">Cancelar</button>
                            <button type="submit" class="btn btn-danger" :disabled="corteForm.processing">
                                {{ corteForm.processing ? 'Cerrando…' : 'Confirmar Corte' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>

<style scoped>
/* ─── No cash ────────────────────────────────────────────────────────────────── */
.no-cash-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 60vh;
    padding: 2rem;
}
.no-cash-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 3rem 2.5rem;
    text-align: center;
    max-width: 400px;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}
.no-cash-icon  { font-size: 3rem; }
.no-cash-title { font-size: 1.4rem; font-weight: 800; color: var(--text-primary); }
.no-cash-hint  { font-size: 0.88rem; color: var(--text-muted); }
.no-cash-rate  { font-size: 0.85rem; color: var(--text-muted); }

/* ─── Cash wrap ──────────────────────────────────────────────────────────────── */
.cash-wrap {
    max-width: 960px;
    margin: 0 auto;
    padding: 1rem 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
@media (min-width: 640px) {
    .cash-wrap { padding: 1.5rem 1rem; }
}

/* ─── Header ─────────────────────────────────────────────────────────────────── */
.cash-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.cash-tabs {
    display: flex;
    gap: 0.25rem;
    border-bottom: 2px solid var(--border);
}
.cash-tab {
    padding: 0.5rem 1.1rem;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--text-muted);
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    cursor: pointer;
    transition: color 0.15s, border-color 0.15s;
}
.cash-tab.active { color: var(--brand); border-bottom-color: var(--brand); }
.cash-actions { display: flex; gap: 0.5rem; }

/* ─── Info bar ───────────────────────────────────────────────────────────────── */
.cash-info-bar {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    font-size: 0.82rem;
    color: var(--text-muted);
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.6rem 1rem;
}
.ci-label { color: var(--text-primary); font-weight: 500; }
.ci-sep   { color: var(--border); }

/* ─── KPI grid ───────────────────────────────────────────────────────────────── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}
@media (min-width: 640px) {
    .kpi-grid { grid-template-columns: repeat(4, 1fr); }
}
.kpi-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 1rem;
}
.kpi-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.3rem; }
.kpi-value { font-size: 1.25rem; font-weight: 800; color: var(--text-primary); }
.kpi-value.amber { color: #f59e0b; }

/* ─── Table ──────────────────────────────────────────────────────────────────── */
.tab-content  { display: flex; flex-direction: column; gap: 1rem; }
.table-card   { background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
.table-title  { font-size: 0.88rem; font-weight: 700; color: var(--text-primary); padding: 0.85rem 1rem 0; }
.data-table   { width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-top: 0.5rem; }
.data-table th {
    padding: 0.6rem 1rem;
    text-align: left;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border-bottom: 1px solid var(--border);
}
.data-table td { padding: 0.6rem 1rem; border-bottom: 1px solid var(--border); color: var(--text-primary); }
.data-table tr:last-child td { border-bottom: none; }
.text-right { text-align: right; }
.muted { color: var(--text-muted); }
.green { color: #16a34a; font-weight: 600; }
.red   { color: #ef4444; font-weight: 600; }
.empty-msg { text-align: center; color: var(--text-muted); padding: 1.5rem; }

/* ─── Badges ─────────────────────────────────────────────────────────────────── */
.badge {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 600;
}
.badge-in  { background: rgba(22,163,74,0.15); color: #16a34a; }
.badge-out { background: rgba(239,68,68,0.12); color: #ef4444; }

/* ─── Corte summary ──────────────────────────────────────────────────────────── */
.corte-summary {
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.85rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.corte-row { display: flex; justify-content: space-between; align-items: baseline; font-size: 0.88rem; }
.corte-label { color: var(--text-muted); }
.corte-val   { font-weight: 700; color: var(--text-primary); }
.corte-divider { border-top: 1px solid var(--border); margin: 0.25rem 0; }

/* ─── Buttons ────────────────────────────────────────────────────────────────── */
.btn {
    padding: 0.6rem 1.1rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    border: none;
    transition: opacity 0.15s;
    min-height: 44px;
}
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-brand  { background: var(--brand); color: #fff; }
.btn-brand:not(:disabled):hover  { opacity: 0.88; }
.btn-danger { background: #dc2626; color: #fff; }
.btn-danger:not(:disabled):hover { opacity: 0.88; }
.btn-ghost  { background: transparent; border: 1px solid var(--border); color: var(--text-muted); }
.btn-ghost:hover { color: var(--text-primary); }

/* ─── Modal ──────────────────────────────────────────────────────────────────── */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.6);
    z-index: 70;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.modal-box {
    background: var(--bg-card);
    border-radius: 14px;
    border: 1px solid var(--border);
    width: 100%;
    max-width: 400px;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.modal-header h3 { font-size: 1rem; font-weight: 700; color: var(--text-primary); }
.modal-close { background: none; border: none; font-size: 1.4rem; color: var(--text-muted); cursor: pointer; line-height: 1; }
.modal-hint  { font-size: 0.82rem; color: var(--text-muted); }
.modal-form  { display: flex; flex-direction: column; gap: 0.65rem; }
.modal-actions { display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 0.25rem; }
.form-label { font-size: 0.78rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
.form-input {
    width: 100%;
    padding: 0.6rem 0.85rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--bg-base);
    color: var(--text-primary);
    font-size: 0.92rem;
}
.form-error { font-size: 0.78rem; color: #ef4444; }

/* ─── Type toggle ────────────────────────────────────────────────────────────── */
.type-toggle { display: flex; gap: 0.5rem; }
.type-btn {
    flex: 1;
    padding: 0.5rem;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--bg-base);
    color: var(--text-muted);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
}
.type-btn.active { border-color: var(--brand); background: var(--brand); color: #fff; }

/* ─── Responsive ─────────────────────────────────────────────────────────────── */
.cash-header { flex-direction: column; align-items: flex-start; }
@media (min-width: 640px) {
    .cash-header { flex-direction: row; align-items: center; }
}
</style>
