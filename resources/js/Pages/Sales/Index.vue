<script setup>
import AppLayout  from '@/Layouts/AppLayout.vue'
import HelpModal  from '@/Components/HelpModal.vue'
import { router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    sales:          { type: Array,  default: () => [] },
    totals:         { type: Object, default: () => ({}) },
    cashiers:       { type: Array,  default: () => [] },
    paymentMethods: { type: Array,  default: () => [] },
    filters:        { type: Object, default: () => ({}) },
})

const page    = usePage()
const userRole = computed(() => page.props.auth?.user?.role)

// ─── Filtros ──────────────────────────────────────────────────────────────────
const filterDate    = ref(props.filters.date    ?? today())
const filterCashier = ref(props.filters.cashier ?? '')
const filterMethod  = ref(props.filters.method  ?? '')
const filterStatus  = ref(props.filters.status  ?? '')

function today() {
    return new Date().toISOString().slice(0, 10)
}

function applyFilters() {
    router.get(route('sales.index'), {
        date:    filterDate.value,
        cashier: filterCashier.value  || undefined,
        method:  filterMethod.value   || undefined,
        status:  filterStatus.value   || undefined,
    }, { preserveState: true, replace: true })
}

function resetFilters() {
    filterDate.value    = today()
    filterCashier.value = ''
    filterMethod.value  = ''
    filterStatus.value  = ''
    applyFilters()
}

// ─── Modal anulación ──────────────────────────────────────────────────────────
const showVoidModal = ref(false)
const voidTarget    = ref(null)
const voidReason    = ref('')
const voidError     = ref('')
const voidProcessing = ref(false)

const expandedSaleId = ref(null)
function toggleSale(id) {
    expandedSaleId.value = expandedSaleId.value === id ? null : id
}

function openVoid(sale) {
    voidTarget.value   = sale
    voidReason.value   = ''
    voidError.value    = ''
    showVoidModal.value = true
}

function closeVoid() {
    showVoidModal.value = false
    voidTarget.value    = null
}

function submitVoid() {
    if (voidReason.value.trim().length < 5) {
        voidError.value = 'El motivo debe tener al menos 5 caracteres.'
        return
    }
    voidProcessing.value = true
    router.patch(
        route('sales.void', voidTarget.value.id),
        { reason: voidReason.value },
        {
            onSuccess: () => { closeVoid(); voidProcessing.value = false },
            onError:   () => { voidProcessing.value = false },
        }
    )
}

// ─── Ayuda ────────────────────────────────────────────────────────────────────
const showHelp = ref(false)

const helpSteps = [
    {
        title: 'Ver ventas del día',
        body: 'Lista todas las ventas de la jornada con su ticket, hora, cajero, método de pago y estado.',
    },
    {
        title: 'Filtrar',
        body: 'Usa los filtros de fecha, cajero, método y estado para encontrar ventas específicas.',
    },
    {
        title: 'Anular una venta',
        body: 'Pulsa Anular en la venta pagada, indica el motivo (mínimo 5 caracteres) y confirma. La venta queda marcada como anulada.',
        tip: 'Al anular, el inventario se devuelve automáticamente — la carne vuelve al stock disponible.',
    },
]

const helpFaqs = [
    {
        q: '¿Quién puede anular una venta?',
        a: 'Solo usuarios con permiso: dueño, administrador de sucursal y supervisor. El cajero no puede anular.',
    },
    {
        q: '¿Qué pasa con el inventario al anular?',
        a: 'El sistema devuelve automáticamente los kilos vendidos al stock. Si vendiste un corte que descuenta de un pool de carne, vuelve a ese pool.',
    },
    {
        q: '¿Puedo anular una venta dos veces?',
        a: 'No. Una vez anulada, la venta no se puede volver a anular.',
    },
    {
        q: '¿La venta anulada afecta el cierre de caja?',
        a: 'No suma en el total esperado. El cuadre solo considera las ventas pagadas.',
    },
]

// ─── Helpers ──────────────────────────────────────────────────────────────────
function fmtBs(v) {
    return new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v ?? 0)
}

const statusLabel = { paid: 'Pagada', cancelled: 'Anulada', open: 'Abierta', pending: 'Pendiente' }
const statusClass = {
    paid:      'badge badge--paid',
    cancelled: 'badge badge--cancelled',
    open:      'badge badge--open',
    pending:   'badge badge--pending',
}

const methodLabel = {
    cash:        'Efectivo',
    transfer:    'Transferencia',
    mobile_pay:  'Pago Móvil',
    card:        'Tarjeta',
    zelle:       'Zelle',
    mixed:       'Mixto',
}
function getMethodLabel(m) { return methodLabel[m] ?? m ?? '—' }
</script>

<template>
    <AppLayout title="Ventas del Día">
        <div class="sales-page">

            <!-- ── Encabezado ──────────────────────────────────────────────── -->
            <div class="page-header">
                <div class="page-header__left">
                    <h1 class="page-title">Ventas del Día</h1>
                    <p class="page-subtitle">{{ filterDate }}</p>
                </div>

                <!-- Totales rápidos -->
                <div class="totals-bar">
                    <div class="totals-bar__item">
                        <span class="totals-bar__label">Total cobrado</span>
                        <span class="totals-bar__value">{{ fmtBs(totals.total_bs) }} Bs.</span>
                    </div>
                    <div class="totals-bar__item">
                        <span class="totals-bar__label">Ventas</span>
                        <span class="totals-bar__value">{{ totals.total_ventas }}</span>
                    </div>
                    <div class="totals-bar__item totals-bar__item--warning" v-if="totals.anuladas">
                        <span class="totals-bar__label">Anuladas</span>
                        <span class="totals-bar__value">{{ totals.anuladas }}</span>
                    </div>
                </div>

                <button class="btn-help" @click="showHelp = true" title="Ayuda">?</button>
            </div>

            <!-- ── Filtros ─────────────────────────────────────────────────── -->
            <div class="filters-bar">
                <input
                    v-model="filterDate"
                    type="date"
                    class="filter-input"
                    @change="applyFilters"
                />

                <select v-model="filterCashier" class="filter-input" @change="applyFilters">
                    <option value="">Todos los cajeros</option>
                    <option v-for="c in cashiers" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>

                <select v-model="filterMethod" class="filter-input" @change="applyFilters">
                    <option value="">Cualquier método</option>
                    <option v-for="m in paymentMethods" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>

                <select v-model="filterStatus" class="filter-input" @change="applyFilters">
                    <option value="">Todos los estados</option>
                    <option value="paid">Pagadas</option>
                    <option value="cancelled">Anuladas</option>
                </select>

                <button type="button" class="btn-ghost" @click="resetFilters">Limpiar</button>
            </div>

            <!-- ── Tabla (tablet+) ────────────────────────────────────────── -->
            <div class="table-wrapper hide-sm">
                <table class="sales-table mobile-cards">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Hora</th>
                            <th>Cajero</th>
                            <th>Cliente</th>
                            <th>Método</th>
                            <th class="text-right">Total Bs.</th>
                            <th>Estado</th>
                            <th v-if="['admin','supervisor','owner','branch_admin','super_admin','cashier'].includes(userRole)"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!sales.length">
                            <td colspan="8" class="empty-row">Sin ventas para los filtros seleccionados</td>
                        </tr>
                        <template v-for="sale in sales" :key="sale.id">
                        <tr :class="{ 'row--cancelled': sale.status === 'cancelled' }">
                            <td class="ticket-cell" data-label="Ticket" @click="toggleSale(sale.id)" style="cursor:pointer"><span>{{ sale.ticket_number }}</span> <span style="color:var(--text-muted);font-size:0.7rem">{{ expandedSaleId === sale.id ? "▲" : "▼" }}</span></td>
                            <td data-label="Hora">{{ sale.sold_at }}</td>
                            <td data-label="Cajero">{{ sale.cashier ?? '—' }}</td>
                            <td class="client-cell" data-label="Cliente">{{ sale.client_name || '—' }}</td>
                            <td data-label="Método">{{ getMethodLabel(sale.payment_method) }}</td>
                            <td class="text-right font-mono" data-label="Total Bs.">{{ fmtBs(sale.total_bs) }}</td>
                            <td data-label="Estado">
                                <span :class="statusClass[sale.status] ?? 'badge'">
                                    {{ statusLabel[sale.status] ?? sale.status }}
                                </span>
                            </td>
                            <td v-if="['admin','supervisor','owner','branch_admin','super_admin','cashier'].includes(userRole)" class="actions-cell" data-label="">
                                <button
                                    v-if="sale.status === 'paid'"
                                    type="button"
                                    class="btn-void"
                                    @click="openVoid(sale)"
                                >
                                    Anular
                                </button>
                                <span v-else-if="sale.status === 'cancelled'" class="void-note" :title="sale.cancellation_reason">
                                    {{ sale.cancelled_at }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="expandedSaleId === sale.id" class="sale-detail-row">
                            <td colspan="8" class="sale-detail-cell">
                                <div class="sale-detail-wrap">
                                    <div v-for="(item, i) in sale.items" :key="i" class="sale-detail-item">
                                        <span class="detail-name">{{ item.product_name }}</span>
                                        <span class="detail-qty">
                                            {{ item.input_type === 'weight'
                                                ? Number(item.quantity_value).toFixed(3) + ' kg'
                                                : Number(item.quantity_value).toFixed(0) + ' und' }}
                                        </span>
                                        <span class="detail-price">
                                            Bs. {{ Number(item.subtotal_bs).toLocaleString('es-VE', {minimumFractionDigits:2}) }}
                                        </span>
                                    </div>
                                    <div v-if="sale.payments?.length" class="sale-detail-payments">
                                        <span v-for="(p, i) in sale.payments" :key="i" class="detail-payment">
                                            {{ p.method }} · Bs. {{ Number(p.amount_bs).toLocaleString('es-VE', {minimumFractionDigits:2}) }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- ── Cards (móvil) ──────────────────────────────────────────── -->
            <div class="sv-cards show-sm">
                <div v-if="!sales.length" class="sv-empty">Sin ventas para los filtros seleccionados</div>
                <div
                    v-for="s in sales"
                    :key="s.id"
                    class="sv-card"
                    :class="{ 'sv-card--void': s.status === 'cancelled' }"
                >
                    <div class="sv-top">
                        <span class="sv-ticket">{{ s.ticket_number }}</span>
                        <span class="sv-time">{{ s.sold_at }}</span>
                    </div>
                    <div v-if="s.client_name" class="sv-client">{{ s.client_name }}</div>
                    <div class="sv-mid">
                        <span class="sv-total">{{ fmtBs(s.total_bs) }}</span>
                        <span class="sv-curr">Bs.</span>
                    </div>
                    <div class="sv-bot">
                        <span class="sv-method">{{ getMethodLabel(s.payment_method) }}</span>
                        <span :class="statusClass[s.status] ?? 'badge'">{{ statusLabel[s.status] ?? s.status }}</span>
                        <button
                            v-if="s.status === 'paid' && ['admin','supervisor','owner','branch_admin','super_admin','cashier'].includes(userRole)"
                            type="button"
                            class="btn-void"
                            @click="openVoid(s)"
                        >Anular</button>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── Panel de ayuda ────────────────────────────────────────────── -->
        <HelpModal
            :show="showHelp"
            title="Ventas del Día — Cómo funciona"
            :steps="helpSteps"
            :faqs="helpFaqs"
            @close="showHelp = false"
        />

        <!-- ── Modal Anulación ────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showVoidModal" class="modal-overlay">
                <div class="modal">
                    <div class="modal__header">
                        <h3 class="modal__title">Anular venta</h3>
                        <button type="button" class="modal__close" @click="closeVoid">✕</button>
                    </div>
                    <div class="modal__body">
                        <p class="modal__ticket">Ticket: <strong>{{ voidTarget?.ticket_number }}</strong></p>
                        <p class="modal__amount">Total: <strong>{{ fmtBs(voidTarget?.total_bs) }} Bs.</strong></p>

                        <label class="modal__label">Motivo de anulación <span class="required">*</span></label>
                        <textarea
                            v-model="voidReason"
                            class="modal__textarea"
                            rows="3"
                            placeholder="Describe el motivo (mínimo 5 caracteres)..."
                        />
                        <p v-if="voidError" class="modal__error">{{ voidError }}</p>
                    </div>
                    <div class="modal__footer">
                        <button type="button" class="btn-ghost" @click="closeVoid">Cancelar</button>
                        <button
                            type="button"
                            class="btn-danger"
                            :disabled="voidProcessing"
                            @click="submitVoid"
                        >
                            {{ voidProcessing ? 'Anulando…' : 'Confirmar anulación' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>

<style scoped>
.sales-page {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 1rem 0.85rem;
    max-width: 1200px;
    margin: 0 auto;
}
@media (min-width: 640px) {
    .sales-page { padding: 1.5rem; gap: 1.25rem; }
}

/* ── Encabezado ─────────────────────────────────────────────────────────── */
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}
.page-header__left { display: flex; flex-direction: column; gap: 2px; }
.btn-help {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 1px solid var(--border);
    background: var(--bg-card);
    color: var(--text-muted);
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: border-color 0.15s, color 0.15s;
}
.btn-help:hover { border-color: var(--brand); color: var(--brand); }
.page-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
}
.page-subtitle {
    font-size: 0.8125rem;
    color: var(--text-muted);
    margin-top: 2px;
}

/* ── Totales ─────────────────────────────────────────────────────────────── */
.totals-bar {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}
.totals-bar__item {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 0.6rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    min-width: 120px;
}
.totals-bar__item--warning { border-color: #f59e0b44; }
.totals-bar__label {
    font-size: 0.7rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.totals-bar__value {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
}

/* ── Filtros ─────────────────────────────────────────────────────────────── */
.filters-bar {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    align-items: center;
}
.filter-input {
    background: var(--bg-input);
    border: 1px solid var(--border);
    color: var(--text-primary);
    border-radius: 8px;
    padding: 0.6rem 0.75rem;
    font-size: 0.8125rem;
    outline: none;
    transition: border-color 0.15s;
    min-height: 44px;
}
.filter-input:focus { border-color: var(--brand); }

/* ── Tabla ───────────────────────────────────────────────────────────────── */
.table-wrapper {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    overflow-x: auto;
}
.sales-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8125rem;
}
.sales-table th {
    padding: 0.7rem 1rem;
    text-align: left;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: var(--bg-surface);
    border-bottom: 1px solid var(--border);
}
.sales-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--border);
    color: var(--text-primary);
    vertical-align: middle;
}
.sales-table tr:last-child td { border-bottom: none; }
.sales-table tr:hover td { background: var(--bg-elevated); }
.row--cancelled td { opacity: 0.55; }

.ticket-cell  { font-weight: 600; font-family: monospace; font-size: 0.875rem; }
.client-cell  { font-size: 0.8rem; color: var(--text-secondary); max-width: 10rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.text-right  { text-align: right; }
.font-mono   { font-variant-numeric: tabular-nums; }
.empty-row   { text-align: center; color: var(--text-muted); padding: 2.5rem 1rem; }

/* ── Badges ──────────────────────────────────────────────────────────────── */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.badge--paid      { background: #10b98122; color: #10b981; }
.badge--cancelled { background: #ef444422; color: #ef4444; }
.badge--open      { background: #3b82f622; color: #3b82f6; }
.badge--pending   { background: #f59e0b22; color: #f59e0b; }

/* ── Acciones ────────────────────────────────────────────────────────────── */
.actions-cell { text-align: right; white-space: nowrap; }
.btn-void {
    font-size: 0.75rem;
    font-weight: 600;
    color: #ef4444;
    background: #ef444415;
    border: 1px solid #ef444430;
    border-radius: 6px;
    padding: 3px 10px;
    cursor: pointer;
    transition: background 0.15s;
}
.btn-void:hover { background: #ef444425; }
.void-note { font-size: 0.7rem; color: var(--text-muted); }

/* ── Botones genéricos ───────────────────────────────────────────────────── */
.btn-ghost {
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--text-secondary);
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 0.6rem 0.875rem;
    cursor: pointer;
    transition: border-color 0.15s;
    min-height: 44px;
}
.btn-ghost:hover { border-color: var(--brand); color: var(--brand); }

.btn-danger {
    font-size: 0.875rem;
    font-weight: 600;
    color: #fff;
    background: #ef4444;
    border: none;
    border-radius: 8px;
    padding: 0.5rem 1.25rem;
    cursor: pointer;
    transition: opacity 0.15s;
}
.btn-danger:hover:not(:disabled) { opacity: 0.87; }
.btn-danger:disabled { opacity: 0.5; cursor: not-allowed; }

/* ── Modal ───────────────────────────────────────────────────────────────── */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.55);
    z-index: 60;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.modal {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    width: 100%;
    max-width: 420px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border);
}
.modal__title { font-size: 0.9375rem; font-weight: 700; color: var(--text-primary); }
.modal__close {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
    padding: 2px 4px;
}
.modal__body {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}
.modal__ticket, .modal__amount {
    font-size: 0.875rem;
    color: var(--text-secondary);
}
.modal__label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-secondary);
}
.required { color: #ef4444; margin-left: 2px; }
.modal__textarea {
    width: 100%;
    background: var(--bg-input);
    border: 1px solid var(--border);
    color: var(--text-primary);
    border-radius: 8px;
    padding: 0.6rem 0.75rem;
    font-size: 0.875rem;
    resize: vertical;
    outline: none;
    font-family: inherit;
    transition: border-color 0.15s;
}
.modal__textarea:focus { border-color: var(--brand); }
.modal__error { font-size: 0.75rem; color: #ef4444; margin-top: -0.25rem; }
.modal__footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-top: 1px solid var(--border);
}

/* ── Mobile/desktop toggle ────────────────────────────────────────────────── */
.hide-sm { display: block; }
.show-sm { display: none; }
@media (max-width: 639px) {
    .hide-sm { display: none !important; }
    .show-sm { display: flex; flex-direction: column; gap: 0.5rem; }
    /* evita auto-zoom en iOS (inputs < 16px) */
    .filter-input { font-size: 1rem; }
    /* touch target mínimo 44px */
    .btn-help { width: 44px; height: 44px; }
}

/* ── Mobile sale cards ────────────────────────────────────────────────────── */
.sv-empty {
    text-align: center;
    color: var(--text-muted);
    padding: 2rem 1rem;
    font-size: 0.875rem;
}
.sv-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.875rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.sv-card--void { opacity: 0.55; }
.sv-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}
.sv-client {
    font-size: 0.78rem;
    color: var(--text-secondary);
    padding: 0 0 0.15rem 0;
}
.sv-ticket {
    font-family: monospace;
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--text-primary);
}
.sv-time {
    font-size: 0.75rem;
    color: var(--text-muted);
}
.sv-mid {
    display: flex;
    align-items: baseline;
    gap: 0.35rem;
}
.sv-total {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--text-primary);
    font-variant-numeric: tabular-nums;
}
.sv-curr {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-weight: 500;
}
.sv-bot {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.sv-method {
    font-size: 0.8rem;
    color: var(--text-muted);
    flex: 1;
}

/* ── Responsive ──────────────────────────────────────────────────────────────── */
@media (max-width: 1023px) {
    .sales-page { padding: 1rem 0.75rem; }
    /* Filters apilan en tablet */
    .filters-bar { flex-direction: column; align-items: stretch; }
    .filter-input { width: 100%; }
    /* Tabla: scroll táctil */
    .table-wrapper { -webkit-overflow-scrolling: touch; }
    /* Totals bar: scroll horizontal */
    .totals-bar { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .totals-bar__item { flex-shrink: 0; }
}

@media (max-width: 640px) {
    /* Totals bar: apila en 2 col */
    .totals-bar { flex-wrap: wrap; overflow-x: unset; }
    .totals-bar__item { flex: 1 1 calc(50% - 0.5rem); min-width: 0; }
    /* Modal → bottom sheet */
    .modal-overlay { align-items: flex-end; padding: 0; }
    .modal { border-radius: 16px 16px 0 0; max-height: 92dvh; max-width: 100%; }
    /* Textarea anti-zoom iOS */
    .modal__textarea { font-size: 1rem; }
    /* Touch targets */
    .modal__footer .btn-ghost,
    .modal__footer .btn-danger { min-height: 44px; flex: 1; justify-content: center; }
}

.sale-detail-row td { padding: 0; }
.sale-detail-cell { background: var(--bg-base); border-bottom: 1px solid var(--border); }
.sale-detail-wrap { padding: 0.75rem 1.5rem; display: flex; flex-direction: column; gap: 0.4rem; }
.sale-detail-item { display: flex; gap: 1rem; align-items: center; font-size: 0.82rem; }
.detail-name { flex: 1; color: var(--text-primary); }
.detail-qty { color: var(--text-muted); min-width: 80px; }
.detail-price { color: var(--brand); font-weight: 500; min-width: 140px; text-align: right; }
.sale-detail-payments { margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid var(--border); display: flex; gap: 0.5rem; flex-wrap: wrap; }
.detail-payment { font-size: 0.78rem; color: var(--text-muted); background: var(--bg-card); padding: 0.2rem 0.6rem; border-radius: 1rem; }
</style>
