<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpModal  from '@/Components/HelpModal.vue';
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    cashRegister: { type: Object, required: true },
    salesCount:   { type: Number, default: 0 },
    totalBs:      { type: Number, default: 0 },
    byMethod:     { type: Array,  default: () => [] },
    movements:    { type: Array,  default: () => [] },
    expectedBs:   { type: Number, default: 0 },
    expectedUsd:  { type: Number, default: 0 },
    todayRate:    { type: Number, default: 1 },
    bovedaActiva:        { type: Array,  default: () => [] },
    macroStats:          { type: Array,  default: () => [] },
    creditos_bs:         { type: Number, default: 0 },
    creditos_tickets:    { type: Number, default: 0 },
    total_devengado_bs:  { type: Number, default: 0 },
});

// ─── Formato ──────────────────────────────────────────────────────────────────
function fmtBs(n)    { return 'Bs. ' + Number(n ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function fmtUsd(n)   { return '$ ' + Number(n ?? 0).toFixed(2); }
function fmtTime(d)  { return d ? new Date(d).toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit' }) : '—'; }
function fmtDate(d)  { return d ? new Date(d).toLocaleDateString('es-VE', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—'; }

// ─── Formulario cierre ────────────────────────────────────────────────────────
const form = useForm({
    counted_cash_bs: '',
    notes: '',
});

const countedNum = computed(() => parseFloat(form.counted_cash_bs) || 0);
const differenceBs = computed(() => countedNum.value - props.expectedBs);
const differencePositive = computed(() => differenceBs.value >= 0);

// ─── Modal confirmación ───────────────────────────────────────────────────────
const showModal = ref(false);

// ─── Ayuda ────────────────────────────────────────────────────────────────────
const showHelp = ref(false);

const helpSteps = [
    {
        title: 'Corte de turno vs Cierre del día — cuál es cuál',
        body: 'El corte de turno es parcial: cuenta el efectivo en un momento del día, registra la diferencia, pero la caja sigue abierta y se puede seguir vendiendo. El cierre del día es definitivo: sella la caja, congela todas las cifras y ya no se pueden agregar ventas a ese día. Esta pantalla es el cierre — úsala al terminar la jornada completa.',
        tip: 'Si solo cambias de turno o quieres cuadrar a mitad del día, usa el corte de turno desde la pantalla de Caja. Reserva esta pantalla para el cierre final.',
    },
    {
        title: 'Revisar el resumen del día',
        body: 'Antes de cerrar, revisa las ventas cobradas, el total en Bs. y el desglose por método de pago. El sistema ya calculó todo automáticamente — tu trabajo es confirmar que los números cuadran con lo que tienes en la caja física.',
        tip: 'Si hay alguna venta que no reconoces, ve a Ventas del Día para ver el detalle de cada ticket antes de cerrar.',
    },
    {
        title: 'Por qué el saldo esperado solo cuenta efectivo',
        body: 'El "saldo esperado" es el dinero físico que debería estar en la gaveta. Los pagos por pago móvil, transferencia o punto de venta nunca entran a la gaveta — se liquidan por otros canales — así que el sistema los excluye del saldo esperado. Solo suma las ventas pagadas en efectivo más el monto de apertura de caja, menos los retiros manuales del día.',
        tip: 'Si el cliente pagó por pago móvil, ese dinero ya está en tu cuenta bancaria, no en la gaveta. Es correcto que no aparezca en el saldo esperado.',
    },
    {
        title: 'La diferencia (+/-) y qué significa',
        body: 'La diferencia es lo que ingresaste como efectivo físico contado menos lo que el sistema esperaba. Si es cero, cuadró perfecto. Si es positiva (+), hay más efectivo de lo esperado — posiblemente un cobro no registrado. Si es negativa (-), falta dinero en la gaveta. En cualquier caso, la diferencia queda registrada permanentemente en el historial para auditoría.',
        tip: 'Una diferencia de 0 no es obligatoria para poder cerrar. El botón se habilita aunque el efectivo sea 0 Bs. Lo importante es que quede el registro real.',
    },
    {
        title: 'Revisar utilidad por bóveda y confirmar cierre',
        body: 'La sección de utilidad muestra cuánto costó la carne que entró a bóveda versus cuánto se vendió — es la ganancia bruta del día por canal. Una vez revisado todo, presiona "Confirmar Cierre". La caja queda sellada y el día queda cerrado.',
        tip: 'El cierre es irreversible desde esta pantalla. Si necesitas corregir algo después, contacta al administrador.',
    },
];

const helpFaqs = [
    {
        q: '¿Puedo cerrar si el efectivo contado es cero?',
        a: 'Sí. El botón de cierre se habilita aunque ingreses 0 Bs. en efectivo. El sistema registrará la diferencia correspondiente. Esto es útil si todas las ventas del día fueron por pago móvil o transferencia y no hubo efectivo en la gaveta.',
    },
    {
        q: '¿Por qué el saldo esperado no incluye los pagos móviles?',
        a: 'Porque los pagos por pago móvil, punto de venta o transferencia nunca pasan por la gaveta física — se liquidan directamente en tu cuenta bancaria. El saldo esperado solo refleja el efectivo que debería estar en la caja: apertura + ventas en efectivo - retiros manuales.',
    },
    {
        q: '¿Qué pasa si la diferencia no es cero?',
        a: 'Nada se bloquea. El sistema registra la diferencia tal como está — positiva o negativa — y el cierre procede normalmente. El historial queda con esa diferencia para que el administrador pueda revisarla. Una diferencia de cero es ideal, pero no es un requisito para cerrar.',
    },
    {
        q: '¿El corte de turno es lo mismo que el cierre del día?',
        a: 'No son lo mismo. El corte de turno es parcial: registra el efectivo en un momento, anota la diferencia, pero la caja sigue abierta para seguir vendiendo. El cierre del día es definitivo: sella la caja y ya no entran más ventas a ese día.',
    },
    {
        q: '¿Qué pasa si cierro por error?',
        a: 'El cierre es irreversible desde esta pantalla. Si necesitas reabrir o corregir algo, contacta al administrador — solo un super_admin puede intervenir en un cierre ya confirmado.',
    },
    {
        q: '¿Por qué la utilidad muestra $0 en costo?',
        a: 'La entrada de bóveda no tiene el costo registrado. Para que aparezca la utilidad correcta, asegúrate de registrar el precio de compra por kg al crear la entrada en el módulo Bóveda.',
    },
    {
        q: '¿Dónde veo cierres anteriores?',
        a: 'En el módulo Caja, en el historial de aperturas y cierres. Allí puedes ver la fecha, el monto de apertura, el efectivo contado al cierre y la diferencia registrada de cada día.',
    },
];

function submitClose() {
    form.post(route('cash.confirm-close', { register: props.cashRegister?.id }), {
        onSuccess: () => { showModal.value = false; router.visit(route('cash.index')); },
    });
}
</script>

<template>
    <AppLayout title="Cierre del Día">
        <div v-if="cashRegister" class="dc-wrap">

            <!-- Encabezado ─────────────────────────────────────────────────── -->
            <div class="dc-header">
                <div>
                    <h1 class="dc-title">Cierre del Día</h1>
                    <p class="dc-sub">{{ fmtDate(cashRegister?.opened_at) }} &bull; Tasa: {{ fmtBs(todayRate) }}/USD</p>
                </div>
                <div class="dc-header-actions">
                    <button class="btn btn-ghost btn-help" @click="showHelp = true" title="Ayuda">?</button>
                    <a :href="route('cash.index')" class="btn btn-ghost">← Volver a Caja</a>
                </div>
            </div>

            <!-- KPIs ───────────────────────────────────────────────────────── -->
            <div class="dc-kpis">
                <div class="dc-kpi">
                    <span class="dc-kpi-label">Ventas cobradas</span>
                    <span class="dc-kpi-value">{{ salesCount }}</span>
                </div>
                <div class="dc-kpi">
                    <span class="dc-kpi-label">Total en Bs.</span>
                    <span class="dc-kpi-value">{{ fmtBs(totalBs) }}</span>
                </div>
                <div class="dc-kpi">
                    <span class="dc-kpi-label">Saldo esperado</span>
                    <span class="dc-kpi-value">{{ fmtBs(expectedBs) }}</span>
                </div>
                <div class="dc-kpi">
                    <span class="dc-kpi-label">Movimientos</span>
                    <span class="dc-kpi-value">{{ movements.length }}</span>
                </div>
            </div>

            <div class="dc-cols">

                <!-- Columna izquierda ────────────────────────────────────── -->
                <div class="dc-left">

                    <!-- Ventas por método de pago ─────────────────────────── -->
                    <section class="dc-card">
                        <h2 class="dc-card-title">Ventas por método de pago</h2>
                        <table class="dc-table" v-if="byMethod.length">
                            <thead>
                                <tr>
                                    <th>Método</th>
                                    <th class="text-right">Tickets</th>
                                    <th class="text-right">Total Bs.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in byMethod" :key="row.method">
                                    <td class="dc-method-name">{{ row.method || '—' }}</td>
                                    <td class="text-right">{{ row.count }}</td>
                                    <td class="text-right">{{ fmtBs(row.total_bs) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="dc-tfoot">
                                    <td>TOTAL</td>
                                    <td class="text-right">{{ salesCount }}</td>
                                    <td class="text-right">{{ fmtBs(totalBs) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                        <p v-else class="dc-empty">Sin ventas registradas hoy.</p>
                    </section>

                    <!-- Ventas por macro-categoría ────────────────────────── -->
                    <section class="dc-card" v-if="macroStats.length">
                        <h2 class="dc-card-title">Ventas por categoría</h2>
                        <div class="macro-grid">
                            <div v-for="row in macroStats" :key="row.macro" class="macro-card">
                                <span class="macro-label">{{ row.macro }}</span>
                                <span class="macro-bs">{{ fmtBs(row.total_bs) }}</span>
                                <div class="macro-sub">
                                    <span>{{ row.ventas }} ticket{{ row.ventas !== 1 ? 's' : '' }}</span>
                                    <span v-if="row.kg_total > 0">· {{ Number(row.kg_total).toFixed(2) }} kg</span>
                                    <span class="macro-usd">{{ fmtUsd(row.total_usd) }}</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Movimientos del día ────────────────────────────────── -->
                    <section class="dc-card" v-if="movements.length">
                        <h2 class="dc-card-title">Movimientos del día</h2>
                        <table class="dc-table">
                            <thead>
                                <tr>
                                    <th>Hora</th>
                                    <th>Concepto</th>
                                    <th>Tipo</th>
                                    <th class="text-right">Monto Bs.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="mov in movements" :key="mov.id">
                                    <td class="dc-time">{{ fmtTime(mov.created_at) }}</td>
                                    <td>{{ mov.concept }}</td>
                                    <td>
                                        <span :class="['dc-badge', mov.type === 'corte' ? 'dc-badge--corte' : mov.type === 'in' ? 'dc-badge--in' : 'dc-badge--out']">
                                            {{ mov.type === 'corte' ? 'Corte' : mov.type === 'in' ? 'Entrada' : 'Salida' }}
                                        </span>
                                    </td>
                                    <td class="text-right">{{ fmtBs(mov.amount_bs) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <!-- Utilidad por Bóveda ────────────────────────────────── -->
                    <section class="dc-card bov-section">
                        <h2 class="dc-card-title bov-title">Utilidad por Bóveda</h2>
                        <p v-if="!bovedaActiva.length" class="dc-empty">No hay entradas activas en bóveda hoy.</p>
                        <div v-else class="bov-grid">
                            <div v-for="entry in bovedaActiva" :key="entry.id" class="bov-card">

                                <!-- Cabecera -->
                                <div class="bov-card-header">
                                    <span class="bov-product-name">
                                        {{ entry.product_label }}
                                        <span v-if="entry.description" class="bov-desc"> — {{ entry.description }}</span>
                                    </span>
                                    <span
                                        class="bov-pct-badge"
                                        :class="entry.porcentaje_recuperado >= 100 ? 'bov-pct--ok' : entry.porcentaje_recuperado >= 50 ? 'bov-pct--mid' : 'bov-pct--low'"
                                    >{{ entry.porcentaje_recuperado }}%</span>
                                </div>

                                <!-- Métricas de kg -->
                                <div class="bov-metrics">
                                    <div class="bov-metric">
                                        <span class="bov-metric-lbl">Kg entrados</span>
                                        <span class="bov-metric-val">{{ Number(entry.kg_entrada).toFixed(3) }}</span>
                                    </div>
                                    <div class="bov-metric">
                                        <span class="bov-metric-lbl">Kg vendidos hoy</span>
                                        <span class="bov-metric-val">{{ Number(entry.kg_vendidos_categoria).toFixed(3) }}</span>
                                    </div>
                                    <div v-if="entry.waste_kg > 0" class="bov-metric">
                                        <span class="bov-metric-lbl">Merma</span>
                                        <span class="bov-metric-val bov-merma">{{ Number(entry.waste_kg).toFixed(3) }}</span>
                                    </div>
                                    <div class="bov-metric">
                                        <span class="bov-metric-lbl">Stock restante</span>
                                        <span class="bov-metric-val" :class="entry.kg_disponible <= 0 ? 'bov-zero' : ''">
                                            {{ Number(entry.kg_disponible).toFixed(3) }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Métricas financieras -->
                                <div class="bov-metrics">
                                    <div class="bov-metric">
                                        <span class="bov-metric-lbl">Costo entrada</span>
                                        <span class="bov-metric-val">{{ fmtUsd(entry.costo_entrada_usd) }}</span>
                                    </div>
                                    <div class="bov-metric">
                                        <span class="bov-metric-lbl">Ventas acumuladas</span>
                                        <span class="bov-metric-val bov-ventas">{{ fmtUsd(entry.ventas_categoria_usd) }}</span>
                                    </div>
                                    <div class="bov-metric">
                                        <span class="bov-metric-lbl">Utilidad</span>
                                        <span
                                            class="bov-metric-val bov-utilidad"
                                            :class="entry.utilidad_usd >= 0 ? 'bov-pos' : 'bov-neg'"
                                        >
                                            {{ entry.utilidad_usd >= 0 ? '+' : '' }}{{ fmtUsd(entry.utilidad_usd) }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Barra de progreso -->
                                <div class="bov-progress-wrap">
                                    <div class="bov-progress-track">
                                        <div
                                            class="bov-progress-bar"
                                            :class="entry.porcentaje_recuperado >= 100 ? 'bov-bar--ok' : entry.porcentaje_recuperado >= 50 ? 'bov-bar--mid' : 'bov-bar--low'"
                                            :style="{ width: Math.min(entry.porcentaje_recuperado, 100) + '%' }"
                                        ></div>
                                    </div>
                                    <span class="bov-progress-lbl">Recuperado del costo</span>
                                </div>

                            </div>
                        </div>
                    </section>

                </div>

                <!-- Columna derecha — Cuadre ─────────────────────────────── -->
                <div class="dc-right">
                    <section class="dc-card dc-cuadre">
                        <h2 class="dc-card-title">Cuadre de caja</h2>

                        <div class="dc-cuadre-row">
                            <span>Apertura</span>
                            <strong>{{ fmtBs(cashRegister?.opening_amount_bs ?? 0) }}</strong>
                        </div>
                        <div class="dc-cuadre-row">
                            <span>Ventas cobradas</span>
                            <strong>{{ fmtBs(totalBs) }}</strong>
                        </div>
                        <div v-if="creditos_tickets > 0" class="dc-summary-row dc-creditos">
                            <span>Créditos/Delivery despachados <span class="dc-badge">{{ creditos_tickets }} tickets</span></span>
                            <strong>{{ fmtBs(creditos_bs) }}</strong>
                        </div>
                        <div v-if="creditos_tickets > 0" class="dc-summary-row dc-devengado">
                            <span><strong>Total devengado del día</strong></span>
                            <strong>{{ fmtBs(total_devengado_bs) }}</strong>
                        </div>
                        <div class="dc-cuadre-row dc-cuadre-expected">
                            <span>Total esperado</span>
                            <strong>{{ fmtBs(expectedBs) }}</strong>
                        </div>

                        <hr class="dc-hr" />

                        <label class="dc-label">Efectivo contado en caja (Bs.)</label>
                        <input
                            v-model="form.counted_cash_bs"
                            type="number"
                            min="0"
                            step="0.01"
                            class="dc-input"
                            placeholder="0.00"
                        />
                        <p v-if="form.errors.counted_cash_bs" class="dc-error">{{ form.errors.counted_cash_bs }}</p>

                        <!-- Diferencia ──────────────────────────────────── -->
                        <div v-if="form.counted_cash_bs !== ''" class="dc-diff" :class="differencePositive ? 'dc-diff--ok' : 'dc-diff--bad'">
                            <span>Diferencia</span>
                            <strong>{{ differencePositive ? '+' : '' }}{{ fmtBs(differenceBs) }}</strong>
                        </div>

                        <label class="dc-label" style="margin-top:1rem;">Notas (opcional)</label>
                        <textarea
                            v-model="form.notes"
                            class="dc-input dc-textarea"
                            rows="2"
                            placeholder="Observaciones del cierre..."
                        ></textarea>

                        <button
                            class="btn btn-danger dc-btn-close"
                            :disabled="form.counted_cash_bs === '' || form.counted_cash_bs === null || form.processing"
                            @click="showModal = true"
                        >
                            Confirmar Cierre
                        </button>
                    </section>
                </div>

            </div><!-- /.dc-cols -->

        </div><!-- /.dc-wrap -->

        <div v-else style="text-align:center;padding:80px 24px;">
            <p style="color:var(--text-secondary);margin-bottom:24px;">No hay caja abierta hoy.</p>
            <a :href="route('cash.index')"
               style="background:var(--brand);color:white;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600;">
               Ir a Caja
            </a>
        </div>

        <!-- Modal confirmación ─────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showModal && cashRegister" class="modal-overlay">
                <div class="modal-box">
                    <h3 class="modal-title">¿Confirmar cierre del día?</h3>
                    <p class="modal-body">
                        Efectivo contado: <strong>{{ fmtBs(countedNum) }}</strong><br />
                        Diferencia: <strong :class="differencePositive ? 'text-ok' : 'text-bad'">
                            {{ differencePositive ? '+' : '' }}{{ fmtBs(differenceBs) }}
                        </strong><br /><br />
                        Esta acción cerrará la caja y no se puede deshacer.
                    </p>
                    <div class="modal-actions">
                        <button class="btn btn-ghost" @click="showModal = false">Cancelar</button>
                        <button class="btn btn-danger" :disabled="form.processing" @click="submitClose">
                            {{ form.processing ? 'Cerrando...' : 'Sí, cerrar caja' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ── Panel de ayuda ────────────────────────────────────────────── -->
        <HelpModal
            :show="showHelp"
            title="Cierre del Día — Cómo funciona"
            :steps="helpSteps"
            :faqs="helpFaqs"
            @close="showHelp = false"
        />

    </AppLayout>
</template>

<style scoped>
.dc-wrap       { max-width: 1100px; margin: 0 auto; padding: 1.5rem 1rem; }
.dc-header          { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 1.5rem; gap: 1rem; }
.dc-header-actions  { display: flex; align-items: center; gap: .5rem; }
.btn-help           { width: 44px; height: 44px; padding: 0; justify-content: center; font-size: 1.1rem; font-weight: 700; border-radius: 50%; }
.dc-title      { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.dc-sub        { font-size: .875rem; color: var(--text-muted); margin: .25rem 0 0; }

/* KPIs */
.dc-kpis       { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.dc-kpi        { background: var(--bg-card); border: 1px solid var(--border); border-radius: .75rem; padding: 1rem 1.25rem; }
.dc-kpi-label  { display: block; font-size: .75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: .25rem; }
.dc-kpi-value  { display: block; font-size: 1.375rem; font-weight: 700; color: var(--text-primary); }

/* Layout cols */
.dc-cols  { display: grid; grid-template-columns: 1fr 340px; gap: 1.25rem; }
@media (max-width: 900px) { .dc-cols { grid-template-columns: 1fr; } }

/* Cards */
.dc-card       { background: var(--bg-card); border: 1px solid var(--border); border-radius: .75rem; padding: 1.25rem; margin-bottom: 1.25rem; overflow-x: auto; -webkit-overflow-scrolling: touch; min-width: 0; }
.dc-card-title { font-size: 1rem; font-weight: 600; color: var(--text-primary); margin: 0 0 1rem; }

/* Tables */
.dc-table      { width: 100%; min-width: 420px; border-collapse: collapse; font-size: .875rem; }
.dc-table th   { text-align: left; color: var(--text-muted); font-weight: 500; padding: .5rem .75rem; border-bottom: 1px solid var(--border); }
.dc-table td   { padding: .5rem .75rem; border-bottom: 1px solid var(--border-faint, var(--border)); color: var(--text-primary); }
.dc-table tr:last-child td { border-bottom: none; }
.dc-tfoot td   { font-weight: 700; color: var(--text-primary); padding-top: .75rem; border-top: 2px solid var(--border); border-bottom: none; }
.text-right    { text-align: right; }
.dc-empty      { color: var(--text-muted); font-size: .875rem; }
.dc-time       { color: var(--text-muted); font-size: .8rem; white-space: nowrap; }
.dc-method-name { text-transform: capitalize; }

/* Badges */
.dc-badge      { display: inline-block; padding: .125rem .5rem; border-radius: 999px; font-size: .75rem; font-weight: 600; }
.dc-badge--in    { background: color-mix(in srgb, var(--color-success, #22c55e) 15%, transparent); color: var(--color-success, #22c55e); }
.dc-badge--out   { background: color-mix(in srgb, var(--color-danger, #ef4444) 15%, transparent); color: var(--color-danger, #ef4444); }
.dc-badge--corte { background: color-mix(in srgb, var(--brand) 15%, transparent); color: var(--brand); }

/* Cuadre */
.dc-cuadre       { position: sticky; top: 1rem; }
.dc-cuadre-row   { display: flex; justify-content: space-between; align-items: center; font-size: .9rem; padding: .5rem 0; color: var(--text-secondary, var(--text-primary)); border-bottom: 1px solid var(--border-faint, var(--border)); }
.dc-cuadre-expected { font-weight: 700; color: var(--text-primary); }
.dc-hr           { border: none; border-top: 2px solid var(--border); margin: 1rem 0; }

.dc-label  { display: block; font-size: .8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; margin-bottom: .4rem; }
.dc-input  { width: 100%; background: var(--bg-input, var(--bg-card)); border: 1px solid var(--border); border-radius: .5rem; padding: .6rem .75rem; color: var(--text-primary); font-size: .95rem; box-sizing: border-box; }
.dc-input:focus { outline: 2px solid var(--brand); outline-offset: -1px; }
.dc-textarea { resize: vertical; min-height: 60px; }
.dc-error    { color: var(--color-danger, #ef4444); font-size: .8rem; margin-top: .25rem; }

.dc-diff     { display: flex; justify-content: space-between; align-items: center; margin-top: .75rem; padding: .6rem .75rem; border-radius: .5rem; font-size: .95rem; font-weight: 700; }
.dc-diff--ok  { background: color-mix(in srgb, var(--color-success, #22c55e) 15%, transparent); color: var(--color-success, #22c55e); }
.dc-diff--bad { background: color-mix(in srgb, var(--color-danger, #ef4444) 15%, transparent); color: var(--color-danger, #ef4444); }

.dc-btn-close { width: 100%; margin-top: 1.25rem; padding: .75rem; font-size: 1rem; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.modal-box     { background: var(--bg-card); border: 1px solid var(--border); border-radius: .875rem; padding: 1.75rem; max-width: 400px; width: 90%; }
.modal-title   { font-size: 1.125rem; font-weight: 700; color: var(--text-primary); margin: 0 0 .75rem; }
.modal-body    { font-size: .9rem; color: var(--text-secondary, var(--text-primary)); line-height: 1.6; margin: 0 0 1.25rem; }
.modal-actions { display: flex; gap: .75rem; justify-content: flex-end; }
.text-ok  { color: var(--color-success, #22c55e); }
.text-bad { color: var(--color-danger, #ef4444); }

/* Botones genéricos */
.btn         { display: inline-flex; align-items: center; gap: .4rem; padding: .5rem 1.1rem; border-radius: .5rem; font-size: .875rem; font-weight: 600; cursor: pointer; border: none; transition: opacity .15s; }
.btn:disabled { opacity: .5; cursor: not-allowed; }
.btn-ghost   { background: transparent; border: 1px solid var(--border); color: var(--text-primary); }
.btn-danger  { background: var(--color-danger, #ef4444); color: #fff; }
.btn-ghost:hover { background: var(--bg-hover, rgba(255,255,255,.06)); }
.btn-danger:hover:not(:disabled) { opacity: .88; }

/* ─── Responsive ────────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .dc-wrap { padding: 1rem 0.75rem; }
    .dc-kpis { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 640px) {
    /* Header stacks */
    .dc-header { flex-direction: column; align-items: flex-start; }
    .dc-header .btn { width: 100%; justify-content: center; min-height: 44px; }

    /* KPIs: 1 col */
    .dc-kpis { grid-template-columns: 1fr; }

    /* Tables scroll */
    .dc-card { padding: 0.85rem; }
    .dc-table { font-size: 0.8rem; }

    /* Confirm button full-width */
    .dc-btn-close { min-height: 44px; }

    /* Input prevent iOS zoom */
    .dc-input { font-size: 1rem; }

    /* Modal → bottom sheet */
    .modal-overlay { align-items: flex-end; }
    .modal-box { border-radius: 16px 16px 0 0; max-height: 90dvh; width: 100%; max-width: 100%; }
    .modal-actions .btn { min-height: 44px; flex: 1; justify-content: center; }

    /* Bov grid: 1 col */
    .bov-grid { grid-template-columns: 1fr; }
    .bov-metrics { grid-template-columns: repeat(2, 1fr); }

    /* Macro grid: 2 col */
    .macro-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 480px) {
    .macro-grid { grid-template-columns: 1fr; }
    .bov-metrics { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 1023px) {
    .dc-wrap { padding: 1rem 0.75rem; }
    .dc-kpis { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
    /* bov-grid en tablet: 1 col */
    .bov-grid { grid-template-columns: 1fr; }
}

/* ── Utilidad por Bóveda ──────────────────────────────────────────────────── */
.bov-section   { margin-bottom: 0; }
.bov-title     { margin-bottom: 1rem; }
.bov-grid      { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem; }
.bov-card      { background: var(--bg-card); border: 1px solid var(--border); border-radius: .75rem; padding: 1.1rem 1.25rem; display: flex; flex-direction: column; gap: .75rem; }

.bov-card-header { display: flex; justify-content: space-between; align-items: flex-start; gap: .5rem; }
.bov-product-name { font-size: .95rem; font-weight: 700; color: var(--text-primary); line-height: 1.3; }
.bov-desc      { font-weight: 400; color: var(--text-muted); font-size: .85rem; }

.bov-pct-badge { flex-shrink: 0; font-size: .8rem; font-weight: 700; border-radius: 999px; padding: .2rem .6rem; }
.bov-pct--ok   { background: color-mix(in srgb, #22c55e 18%, transparent); color: #22c55e; }
.bov-pct--mid  { background: color-mix(in srgb, #f59e0b 18%, transparent); color: #f59e0b; }
.bov-pct--low  { background: color-mix(in srgb, #ef4444 18%, transparent); color: #ef4444; }

.bov-metrics   { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; }
.bov-metric    { display: flex; flex-direction: column; gap: .2rem; }
.bov-metric-lbl { font-size: .7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; }
.bov-metric-val { font-size: .92rem; font-weight: 600; color: var(--text-primary); font-variant-numeric: tabular-nums; }
.bov-zero      { color: var(--text-muted); }
.bov-merma     { color: var(--warning, #f59e0b); }

/* ── Macro-categorías ───────────────────────────────────────────── */
.macro-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 0.75rem;
}
.macro-card {
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.macro-label { font-size: 0.7rem; font-weight: 700; color: var(--brand); text-transform: uppercase; letter-spacing: 0.06em; }
.macro-bs    { font-size: 1rem; font-weight: 700; color: var(--text-primary); }
.macro-sub   { display: flex; flex-wrap: wrap; gap: 0.3rem; font-size: 0.7rem; color: var(--text-muted); align-items: center; }
.macro-usd   { margin-left: auto; color: var(--text-secondary); }
.bov-ventas    { color: var(--brand); }
.bov-pos       { color: #22c55e; }
.bov-neg       { color: #ef4444; }
.bov-utilidad  { font-weight: 700; }

.bov-progress-wrap  { display: flex; flex-direction: column; gap: .3rem; }
.bov-progress-track { height: 6px; background: var(--border); border-radius: 999px; overflow: hidden; }
.bov-progress-bar   { height: 100%; border-radius: 999px; transition: width .4s ease; }
.bov-bar--ok   { background: #22c55e; }
.bov-bar--mid  { background: #f59e0b; }
.bov-bar--low  { background: #ef4444; }
.bov-progress-lbl { font-size: .7rem; color: var(--text-muted); }
.dc-creditos { color: var(--brand); }
.dc-devengado { 
    border-top: 1px solid var(--border); 
    margin-top: 0.5rem; 
    padding-top: 0.5rem;
    font-size: 1rem;
}
.dc-devengado strong { color: var(--success); font-size: 1.1rem; }
.dc-badge {
    background: rgba(99,102,241,0.15);
    color: var(--brand);
    font-size: 0.72rem;
    padding: 0.1rem 0.4rem;
    border-radius: 999px;
    margin-left: 0.3rem;
}
</style>
