<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HelpModal  from '@/Components/HelpModal.vue'
import { ref, computed, reactive } from 'vue'
import axios from 'axios'
import { FileText, Truck, Home } from '@lucide/vue'

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    pedidosActivos:   { type: Array,  default: () => [] },
    historial:        { type: Array,  default: () => [] },
    cobrosPendientes: { type: Array,  default: () => [] },
    products:         { type: Array,  default: () => [] },
    paymentMethods:   { type: Array,  default: () => [] },
    paymentTerminals: { type: Array,  default: () => [] },
    todayRate:        { type: Number, default: 1 },
    kpis:             { type: Object, default: () => ({}) },
})

// ─── Estado reactivo ──────────────────────────────────────────────────────────
const pedidos           = ref([...props.pedidosActivos])
const historial         = ref([...props.historial])
const cobrosPendientes  = ref([...props.cobrosPendientes])
const cobradosHoy       = ref(props.kpis.cobrados_hoy ?? 0)
const activeTab         = ref('pending')
const saving            = ref(false)
const globalError       = ref('')

// ─── Ayuda ────────────────────────────────────────────────────────────────────
const showHelp = ref(false)

const helpSteps = [
    {
        title: 'Crear un pedido',
        body:  'Agrega los productos que el cliente quiere. El pedido queda en estado "abierto" hasta que se prepara y cobra.',
        tip:   'Puedes crear pedidos para delivery o para consumo interno.',
    },
    {
        title: 'Preparar el pedido',
        body:  'Cuando el pedido está listo, márcalo como preparado. El sistema lo mueve a la lista de cobro pendiente.',
        tip:   'El badge en el menú muestra cuántos pedidos están por cobrar.',
    },
    {
        title: 'Registrar cobro',
        body:  'Selecciona el método de pago y confirma. El sistema genera el ticket y descuenta el stock igual que una venta normal.',
        tip:   'Puedes cobrar con terminal, Pago Móvil o efectivo.',
    },
    {
        title: 'Delivery',
        body:  'Si es delivery, el pedido queda pendiente hasta que el motorizado confirma la entrega y se registra el cobro.',
        tip:   'Los deliveries sin cobrar aparecen en el badge del menú.',
    },
]

const helpFaqs = [
    { q: '¿Un pedido descuenta el stock?',        a: 'No hasta que se cobra. El stock baja solo cuando el pago se confirma.' },
    { q: '¿Puedo modificar un pedido abierto?',   a: 'Sí, mientras no esté cobrado puedes agregar o quitar productos.' },
    { q: '¿Qué es el badge rojo en Pedidos?',     a: 'Muestra créditos pendientes más deliveries sin cobrar.' },
    { q: '¿Puedo cancelar un pedido?',            a: 'Sí, solo el administrador puede cancelar con motivo obligatorio.' },
    { q: '¿Dónde veo los pedidos cobrados?',      a: 'En Ventas del Día aparecen como ventas normales.' },
]

// ─── KPIs calculados reactivamente ───────────────────────────────────────────
const kpis = computed(() => ({
    abiertos:            pedidos.value.length,
    internos:            pedidos.value.filter(o => o.client_type === 'internal').length,
    total_pendiente_bs:  pedidos.value.reduce((s, o) => s + o.total_bs, 0),
    cobrados_hoy:        cobradosHoy.value,
    cobros_pendientes:   cobrosPendientes.value.length,
}))

// ──────────────────────────────────────────────────────────────────────────────
// MODAL: NUEVO PEDIDO
// ──────────────────────────────────────────────────────────────────────────────
const showNewModal  = ref(false)
const newForm       = reactive({ client_name: '', client_type: 'external', notes: '' })
const newCart       = ref([])
const catFilter     = ref(null)
const prodSearch    = ref('')
const newError      = ref('')

const categories = computed(() => {
    const seen = new Set()
    return props.products
        .filter(p => p.category && !seen.has(p.category.id) && seen.add(p.category.id))
        .map(p => p.category)
})

const filteredProducts = computed(() => {
    let list = props.products
    if (catFilter.value) list = list.filter(p => p.category?.id === catFilter.value)
    if (prodSearch.value) {
        const q = prodSearch.value.toLowerCase()
        list = list.filter(p => p.name.toLowerCase().includes(q))
    }
    return list
})

const cartTotalUsd = computed(() => newCart.value.reduce((s, i) => s + i.subtotal_usd, 0))
const cartTotalBs  = computed(() => round2(cartTotalUsd.value * props.todayRate))

function cartItem(productId) {
    return newCart.value.find(i => i.product_id === productId)
}

function addToCart(product) {
    const existing = cartItem(product.id)
    if (existing) {
        const step = product.sale_mode === 'weight' ? 0.5 : 1
        updateCartQty(product.id, existing.quantity_value + step)
        return
    }
    const qty      = product.sale_mode === 'weight' ? 0.5 : 1
    const price    = product.sale_mode === 'weight' ? (product.price_per_kg_usd ?? 0) : (product.price_per_unit_usd ?? 0)
    newCart.value.push({
        product_id:     product.id,
        product_name:   product.name,
        input_type:     product.sale_mode === 'weight' ? 'weight' : 'unit',
        unit_label:     product.base_unit_label ?? (product.sale_mode === 'weight' ? 'kg' : 'und'),
        quantity_value: qty,
        price_usd:      price,
        subtotal_usd:   round2(qty * price),
    })
}

function updateCartQty(productId, qty) {
    const item = cartItem(productId)
    if (!item) return
    const min     = item.input_type === 'weight' ? 0.001 : 1
    const clamped = Math.max(min, qty)
    item.quantity_value = item.input_type === 'weight' ? +clamped.toFixed(3) : Math.round(clamped)
    item.subtotal_usd   = round2(item.quantity_value * item.price_usd)
}

function updateCartByBs(productId, bsAmount) {
    const item    = cartItem(productId)
    if (!item || !item.price_usd || !props.todayRate) return
    const priceBs = item.price_usd * props.todayRate
    if (priceBs <= 0) return
    const rawQty  = bsAmount / priceBs
    const min     = item.input_type === 'weight' ? 0.001 : 1
    const clamped = Math.max(min, rawQty)
    item.quantity_value = item.input_type === 'weight' ? +clamped.toFixed(3) : Math.round(clamped)
    item.subtotal_usd   = round2(item.quantity_value * item.price_usd)
}

function removeFromCart(productId) {
    newCart.value = newCart.value.filter(i => i.product_id !== productId)
}

function resetNewModal() {
    newForm.client_name  = ''
    newForm.client_type  = 'external'
    newForm.notes        = ''
    newCart.value        = []
    catFilter.value      = null
    prodSearch.value     = ''
    newError.value       = ''
}

async function submitNewOrder() {
    if (!newForm.client_name.trim()) { newError.value = 'El nombre del cliente es obligatorio.'; return }
    if (!newCart.value.length)       { newError.value = 'Agregue al menos un producto.'; return }
    saving.value   = true
    newError.value = ''
    try {
        const res = await axios.post(route('orders.store'), {
            client_name: newForm.client_name,
            client_type: newForm.client_type,
            notes:       newForm.notes || null,
            items:       newCart.value.map(i => ({
                product_id:     i.product_id,
                quantity_value: i.quantity_value,
                input_type:     i.input_type,
            })),
        })
        pedidos.value.unshift(res.data.order)
        showNewModal.value = false
        resetNewModal()
    } catch (err) {
        newError.value = err.response?.data?.message ?? 'Error al guardar el pedido.'
    } finally {
        saving.value = false
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// PAGOS — lista unificada métodos + terminales
// ──────────────────────────────────────────────────────────────────────────────

// Terminales usan value 'T:<id>' para distinguirlos de payment_method ids
const allPaymentOptions = computed(() => [
    ...props.paymentMethods.map(m => ({
        value: String(m.id),
        label: m.name,
        group: 'method',
    })),
    ...props.paymentTerminals.map(t => ({
        value: `T:${t.id}`,
        label: t.bank_name ?? t.method,
        group: 'terminal',
    })),
])

// Resuelve la opción seleccionada en {payment_method_id, reference, _label}.
// Terminales usan el payment_method de tipo 'card'; si no existe, usa el primero.
function resolveOption(selectedValue, customRef) {
    if (String(selectedValue).startsWith('T:')) {
        const tid       = parseInt(String(selectedValue).slice(2))
        const terminal  = props.paymentTerminals.find(t => t.id === tid)
        const method    = props.paymentMethods.find(m => m.type === 'card') ?? props.paymentMethods[0]
        const termLabel = terminal?.bank_name ?? terminal?.method ?? 'Terminal'
        const autoRef   = terminal?.commercial_number
            ? `${termLabel} #${terminal.commercial_number}`
            : termLabel
        return {
            payment_method_id: method?.id ?? 0,
            reference:         customRef || autoRef || null,
            _label:            termLabel,
        }
    }
    const method = props.paymentMethods.find(m => m.id == selectedValue)
    return {
        payment_method_id: parseInt(selectedValue),
        reference:         customRef || null,
        _label:            method?.name ?? '',
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// MODAL: COBRAR
// ──────────────────────────────────────────────────────────────────────────────
const showCollectModal = ref(false)
const collectOrder     = ref(null)
const payments         = ref([])
const newPmtMethodId   = ref('')
const newPmtAmountBs   = ref('')
const newPmtRef        = ref('')
const collectError     = ref('')

const collectTotalBs = computed(() => collectOrder.value?.total_bs ?? 0)
const paidBs         = computed(() => payments.value.reduce((s, p) => s + p.amount_bs, 0))
const restBs         = computed(() => Math.max(0, collectTotalBs.value - paidBs.value))
const changeBs       = computed(() => Math.max(0, paidBs.value - collectTotalBs.value))
const canConfirm     = computed(() => payments.value.length > 0 && paidBs.value >= collectTotalBs.value)

function openCollect(order) {
    collectOrder.value   = order
    payments.value       = []
    newPmtMethodId.value = allPaymentOptions.value[0]?.value ?? ''
    newPmtAmountBs.value = ''
    newPmtRef.value      = ''
    collectError.value   = ''
    showCollectModal.value = true
}

function addPayment() {
    if (!newPmtMethodId.value || !newPmtAmountBs.value) return
    const amt = parseFloat(newPmtAmountBs.value)
    if (isNaN(amt) || amt <= 0) return
    const resolved = resolveOption(newPmtMethodId.value, newPmtRef.value)
    payments.value.push({
        payment_method_id: resolved.payment_method_id,
        amount_bs:         round2(amt),
        reference:         resolved.reference,
        _label:            resolved._label,
    })
    newPmtAmountBs.value = ''
    newPmtRef.value      = ''
}

function fillRest() {
    newPmtAmountBs.value = restBs.value > 0 ? restBs.value.toFixed(2) : ''
}

function removePayment(idx) {
    payments.value.splice(idx, 1)
}

async function submitCollect() {
    if (!canConfirm.value) return
    saving.value       = true
    collectError.value = ''
    try {
        await axios.patch(route('orders.collect', collectOrder.value.id), {
            payments: payments.value.map(p => ({
                payment_method_id: p.payment_method_id,
                amount_bs:         p.amount_bs,
                reference:         p.reference,
            })),
        })
        const id = collectOrder.value.id
        pedidos.value   = pedidos.value.filter(o => o.id !== id)
        cobradosHoy.value++
        showCollectModal.value = false
    } catch (err) {
        collectError.value = err.response?.data?.error ?? 'Error al cobrar el pedido.'
    } finally {
        saving.value = false
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// MODAL: CANCELAR
// ──────────────────────────────────────────────────────────────────────────────
const showCancelModal = ref(false)
const cancelOrder     = ref(null)
const cancelReason    = ref('')
const cancelError     = ref('')

function openCancel(order) {
    cancelOrder.value   = order
    cancelReason.value  = ''
    cancelError.value   = ''
    showCancelModal.value = true
}

async function submitCancel() {
    if (!cancelReason.value.trim() || cancelReason.value.length < 5) {
        cancelError.value = 'El motivo debe tener al menos 5 caracteres.'
        return
    }
    saving.value      = true
    cancelError.value = ''
    try {
        await axios.patch(route('orders.cancel', cancelOrder.value.id), { reason: cancelReason.value })
        const id = cancelOrder.value.id
        pedidos.value = pedidos.value.filter(o => o.id !== id)
        showCancelModal.value = false
    } catch (err) {
        cancelError.value = err.response?.data?.message ?? 'Error al cancelar.'
    } finally {
        saving.value = false
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// DESPACHAR SIN COBRAR
// ──────────────────────────────────────────────────────────────────────────────
async function dispatchOrder(order) {
    if (!confirm(`¿Despachar el pedido de "${order.client_name}" sin cobrar ahora?`)) return
    saving.value = true
    try {
        await axios.patch(route('orders.dispatch', order.id))
        pedidos.value = pedidos.value.filter(o => o.id !== order.id)
    } catch (err) {
        globalError.value = err.response?.data?.error ?? 'Error al despachar el pedido.'
    } finally {
        saving.value = false
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// MODAL: REGISTRAR COBRO (cobros pendientes)
// ──────────────────────────────────────────────────────────────────────────────
const showPendCollectModal = ref(false)
const pendCollectSale      = ref(null)
const pendPayments         = ref([])
const pendPmtMethodId      = ref('')
const pendPmtAmountBs      = ref('')
const pendPmtRef           = ref('')
const pendCollectError     = ref('')

const pendTotalBs  = computed(() => pendCollectSale.value?.total_bs ?? 0)
const pendPaidBs   = computed(() => pendPayments.value.reduce((s, p) => s + p.amount_bs, 0))
const pendRestBs   = computed(() => Math.max(0, pendTotalBs.value - pendPaidBs.value))
const pendChangeBs = computed(() => Math.max(0, pendPaidBs.value - pendTotalBs.value))
const pendCanConfirm = computed(() => pendPayments.value.length > 0 && pendPaidBs.value >= pendTotalBs.value)

function openPendCollect(sale) {
    pendCollectSale.value    = sale
    pendPayments.value       = []
    pendPmtMethodId.value    = allPaymentOptions.value[0]?.value ?? ''
    pendPmtAmountBs.value    = ''
    pendPmtRef.value         = ''
    pendCollectError.value   = ''
    showPendCollectModal.value = true
}

function addPendPayment() {
    if (!pendPmtMethodId.value || !pendPmtAmountBs.value) return
    const amt = parseFloat(pendPmtAmountBs.value)
    if (isNaN(amt) || amt <= 0) return
    const resolved = resolveOption(pendPmtMethodId.value, pendPmtRef.value)
    pendPayments.value.push({
        payment_method_id: resolved.payment_method_id,
        amount_bs:         round2(amt),
        reference:         resolved.reference,
        _label:            resolved._label,
    })
    pendPmtAmountBs.value = ''
    pendPmtRef.value      = ''
}

function fillPendRest() {
    pendPmtAmountBs.value = pendRestBs.value > 0 ? pendRestBs.value.toFixed(2) : ''
}

function removePendPayment(idx) {
    pendPayments.value.splice(idx, 1)
}

async function submitPendCollect() {
    if (!pendCanConfirm.value) return
    saving.value           = true
    pendCollectError.value = ''
    try {
        const isDelivery   = pendCollectSale.value?.sale_type === 'delivery'
        const collectRoute = isDelivery
            ? route('sales.delivery-confirm', pendCollectSale.value.id)
            : route('sales.collect-pending',  pendCollectSale.value.id)
        await axios.patch(collectRoute, {
            payments: pendPayments.value.map(p => ({
                payment_method_id: p.payment_method_id,
                amount_bs:         p.amount_bs,
                reference:         p.reference,
            })),
        })
        const id = pendCollectSale.value.id
        cobrosPendientes.value = cobrosPendientes.value.filter(s => s.id !== id)
        cobradosHoy.value++
        showPendCollectModal.value = false
    } catch (err) {
        pendCollectError.value = err.response?.data?.error ?? 'Error al registrar el cobro.'
    } finally {
        saving.value = false
    }
}

// ─── Formato ──────────────────────────────────────────────────────────────────
function fmtBs(n)  { return 'Bs. ' + Number(n ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
function fmtUsd(n) { return '$' + Number(n ?? 0).toFixed(2) }
function round2(n) { return Math.round(n * 100) / 100 }

function elapsed(dt) {
    if (!dt) return '—'
    const mins = Math.floor((Date.now() - new Date(dt)) / 60000)
    if (mins < 60) return `${mins}m`
    const h = Math.floor(mins / 60)
    return `${h}h ${mins % 60}m`
}

function fmtDatetime(dt) {
    if (!dt) return '—'
    return new Date(dt).toLocaleString('es-VE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function methodName(id) {
    return props.paymentMethods.find(m => m.id == id)?.name ?? '—'
}
</script>

<template>
    <AppLayout title="Pedidos">
        <div class="orders-wrap">

            <!-- ─── KPIs ──────────────────────────────────────────────────── -->
            <div class="kpi-row">
                <div class="kpi-card">
                    <span class="kpi-label">Pedidos abiertos</span>
                    <span class="kpi-big">{{ kpis.abiertos }}</span>
                </div>
                <div class="kpi-card kpi-amber">
                    <span class="kpi-label">Consumo interno</span>
                    <span class="kpi-big">{{ kpis.internos }}</span>
                    <span class="kpi-sub">pendientes</span>
                </div>
                <div class="kpi-card kpi-brand">
                    <span class="kpi-label">Total pendiente</span>
                    <span class="kpi-big kpi-sm">{{ fmtBs(kpis.total_pendiente_bs) }}</span>
                </div>
                <div class="kpi-card kpi-green">
                    <span class="kpi-label">Cobrados hoy</span>
                    <span class="kpi-big">{{ kpis.cobrados_hoy }}</span>
                </div>
                <div class="kpi-card kpi-red" v-if="kpis.cobros_pendientes > 0">
                    <span class="kpi-label">Por cobrar</span>
                    <span class="kpi-big">{{ kpis.cobros_pendientes }}</span>
                    <span class="kpi-sub">despachados sin pago</span>
                </div>
            </div>

            <!-- ─── Header: tabs + botón ──────────────────────────────────── -->
            <div class="orders-header">
                <div class="tabs">
                    <button class="tab" :class="{ 'tab-active': activeTab === 'pending' }" @click="activeTab = 'pending'">
                        Pendientes
                        <span v-if="pedidos.length" class="tab-badge">{{ pedidos.length }}</span>
                    </button>
                    <button class="tab" :class="{ 'tab-active': activeTab === 'cobros' }" @click="activeTab = 'cobros'">
                        Por Cobrar
                        <span v-if="cobrosPendientes.length" class="tab-badge tab-badge-red">{{ cobrosPendientes.length }}</span>
                    </button>
                    <button class="tab" :class="{ 'tab-active': activeTab === 'history' }" @click="activeTab = 'history'">
                        Historial
                    </button>
                </div>
                <div class="header-actions">
                    <button class="btn-brand" @click="showNewModal = true">+ Nuevo Pedido</button>
                    <button class="tab-btn--help" @click="showHelp = true" title="Ayuda">?</button>
                </div>
            </div>

            <!-- ─── Tab: Pendientes ───────────────────────────────────────── -->
            <div v-if="activeTab === 'pending'">
                <div v-if="pedidos.length" class="orders-grid">
                    <div v-for="order in pedidos" :key="order.id" class="order-card">

                        <div class="order-card-head">
                            <div class="order-meta">
                                <span class="type-badge" :class="order.client_type === 'internal' ? 'badge-amber' : 'badge-blue'">
                                    {{ order.client_type === 'internal' ? 'Consumo Interno' : 'Delivery' }}
                                </span>
                                <span class="order-client">{{ order.client_name }}</span>
                            </div>
                            <span class="order-elapsed">⏱ {{ elapsed(order.created_at) }}</span>
                        </div>

                        <div class="order-items">
                            <div v-for="item in order.items" :key="item.id" class="order-item-row">
                                <span class="item-name">{{ item.product_name }}</span>
                                <span class="item-qty">{{ item.quantity_value }} {{ item.unit_label }}</span>
                                <span class="item-sub">{{ fmtBs(item.subtotal_bs) }}</span>
                            </div>
                        </div>

                        <div class="order-footer">
                            <div class="order-total">
                                <span class="total-bs">{{ fmtBs(order.total_bs) }}</span>
                                <span class="total-usd">{{ fmtUsd(order.total_usd) }}</span>
                            </div>
                            <div class="order-actions">
                                <button class="btn-collect" @click="openCollect(order)">Cobrar</button>
                                <button class="btn-dispatch" :disabled="saving" @click="dispatchOrder(order)">Despachar</button>
                                <button class="btn-cancel-order" @click="openCancel(order)">Cancelar</button>
                            </div>
                        </div>

                        <p v-if="order.notes" class="order-notes"><FileText :size="13" class="notes-icon" />{{ order.notes }}</p>
                    </div>
                </div>
                <div v-else class="empty-state">No hay pedidos activos.</div>
            </div>

            <!-- ─── Tab: Por Cobrar ──────────────────────────────────────── -->
            <div v-else-if="activeTab === 'cobros'">
                <div v-if="cobrosPendientes.length" class="cobros-list">
                    <div v-for="sale in cobrosPendientes" :key="sale.id" class="cobro-card">
                        <div class="cobro-head">
                            <div class="cobro-meta">
                                <span class="cobro-ticket">{{ sale.ticket_number }}</span>
                                <span v-if="sale.client_name" class="cobro-client">{{ sale.client_name }}</span>
                                <span v-else class="cobro-client-none">Sin cliente asignado</span>
                            </div>
                            <span class="cobro-elapsed">⏱ {{ elapsed(sale.created_at) }}</span>
                        </div>
                        <div class="cobro-items">
                            <div v-for="(item, i) in sale.items" :key="i" class="cobro-item-row">
                                <span class="item-name">{{ item.product_name }}</span>
                                <span class="item-qty">{{ item.quantity_value }} {{ item.unit_label }}</span>
                                <span class="item-sub">{{ fmtBs(item.subtotal_bs) }}</span>
                            </div>
                        </div>
                        <div class="cobro-footer">
                            <div class="order-total">
                                <span class="total-bs">{{ fmtBs(sale.total_bs) }}</span>
                                <span class="total-usd">{{ fmtUsd(sale.total_usd) }}</span>
                            </div>
                            <button class="btn-collect" @click="openPendCollect(sale)">Registrar Cobro</button>
                        </div>
                    </div>
                </div>
                <div v-else class="empty-state">No hay cobros pendientes.</div>
            </div>

            <!-- ─── Tab: Historial ────────────────────────────────────────── -->
            <div v-else-if="activeTab === 'history'" class="card">
                <div class="table-wrap">
                    <table class="hist-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Tipo</th>
                                <th class="center">Items</th>
                                <th>Total Bs.</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="o in historial" :key="o.id">
                                <td class="muted">{{ fmtDatetime(o.updated_at) }}</td>
                                <td>{{ o.client_name }}</td>
                                <td>
                                    <span class="type-badge-sm" :class="o.client_type === 'internal' ? 'badge-amber' : 'badge-blue'">
                                        {{ o.client_type === 'internal' ? 'Consumo Interno' : 'Delivery' }}
                                    </span>
                                </td>
                                <td class="center muted">{{ o.items_count }}</td>
                                <td class="amount">{{ fmtBs(o.total_bs) }}</td>
                                <td>
                                    <span class="status-pill" :class="o.status === 'paid' ? 'pill-paid' : 'pill-cancelled'">
                                        {{ o.status === 'paid' ? 'Cobrado' : 'Cancelado' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!historial.length">
                                <td colspan="6" class="empty-row">Sin historial.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /.orders-wrap -->

        <!-- ═══════════════════════════════════════════════════════════════════
             MODAL: NUEVO PEDIDO
        ════════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <div v-if="showNewModal" class="modal-overlay">
                <div class="modal-box modal-xl">
                    <div class="modal-head">
                        <h2 class="modal-title">Nuevo Pedido</h2>
                        <button class="modal-close" @click="showNewModal = false; resetNewModal()">✕</button>
                    </div>

                    <div class="new-order-body">
                        <!-- Columna izquierda: cliente + grid de productos -->
                        <div class="new-order-left">
                            <!-- Cliente -->
                            <div class="field-row">
                                <label class="field-label">Nombre del cliente</label>
                                <input v-model="newForm.client_name" class="field-input" placeholder="Nombre o referencia" />
                            </div>

                            <!-- Tipo -->
                            <div class="type-toggle">
                                <button class="toggle-btn" :class="{ 'toggle-active': newForm.client_type === 'external' }" @click="newForm.client_type = 'external'">
                                    <Truck :size="14" style="vertical-align:middle;margin-right:5px;" />Delivery / Encargo
                                </button>
                                <button class="toggle-btn" :class="{ 'toggle-active': newForm.client_type === 'internal' }" @click="newForm.client_type = 'internal'">
                                    <Home :size="14" style="vertical-align:middle;margin-right:5px;" />Consumo Interno
                                </button>
                            </div>

                            <!-- Filtro categoría + búsqueda -->
                            <div class="prod-filters">
                                <input v-model="prodSearch" class="field-input" placeholder="Buscar producto…" />
                                <div class="cat-chips">
                                    <button class="cat-chip" :class="{ active: catFilter === null }" @click="catFilter = null">Todos</button>
                                    <button
                                        v-for="cat in categories" :key="cat.id"
                                        class="cat-chip" :class="{ active: catFilter === cat.id }"
                                        @click="catFilter = cat.id"
                                    >{{ cat.name }}</button>
                                </div>
                            </div>

                            <!-- Mini grid de productos -->
                            <div class="prod-mini-grid">
                                <button
                                    v-for="p in filteredProducts" :key="p.id"
                                    class="prod-mini-btn" @click="addToCart(p)"
                                >
                                    <span class="prod-mini-name">{{ p.name }}</span>
                                    <span class="prod-mini-price">
                                        {{ p.sale_mode === 'weight' ? fmtUsd(p.price_per_kg_usd) + '/kg' : fmtUsd(p.price_per_unit_usd) }}
                                    </span>
                                </button>
                                <p v-if="!filteredProducts.length" class="empty-msg">Sin productos.</p>
                            </div>

                            <!-- Notas -->
                            <div class="field-row">
                                <label class="field-label">Notas (opcional)</label>
                                <textarea v-model="newForm.notes" class="field-input" rows="2" placeholder="Instrucciones especiales…"></textarea>
                            </div>
                        </div>

                        <!-- Columna derecha: carrito -->
                        <div class="new-order-right">
                            <h3 class="cart-title">Carrito</h3>
                            <div v-if="newCart.length" class="cart-list">
                                <div v-for="item in newCart" :key="item.product_id" class="cart-row">
                                    <div class="cart-info">
                                        <div class="cart-name-row">
                                            <span class="cart-name">{{ item.product_name }}</span>
                                            <button class="cart-remove" @click="removeFromCart(item.product_id)">✕</button>
                                        </div>
                                        <div class="cart-dual-row">
                                            <!-- Input en cantidad/kg -->
                                            <div class="dual-block">
                                                <span class="dual-lbl">{{ item.unit_label }}</span>
                                                <div class="dual-input-row">
                                                    <button class="qty-btn" @click="updateCartQty(item.product_id, item.quantity_value - (item.input_type === 'weight' ? 0.5 : 1))">−</button>
                                                    <input
                                                        class="qty-input"
                                                        type="number"
                                                        :step="item.input_type === 'weight' ? 0.001 : 1"
                                                        :value="item.quantity_value"
                                                        @change="updateCartQty(item.product_id, +$event.target.value)"
                                                    />
                                                    <button class="qty-btn" @click="updateCartQty(item.product_id, item.quantity_value + (item.input_type === 'weight' ? 0.5 : 1))">+</button>
                                                </div>
                                            </div>

                                            <span class="dual-sep">⇄</span>

                                            <!-- Input en Bolívares -->
                                            <div class="dual-block">
                                                <span class="dual-lbl">Bs.</span>
                                                <input
                                                    class="qty-input bs-input"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    :value="(item.subtotal_usd * todayRate).toFixed(2)"
                                                    @change="updateCartByBs(item.product_id, +$event.target.value)"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="empty-msg">Agrega productos del catálogo.</p>

                            <div v-if="newCart.length" class="cart-total-row">
                                <span class="cart-total-label">Total</span>
                                <div>
                                    <span class="cart-total-bs">{{ fmtBs(cartTotalBs) }}</span>
                                    <span class="cart-total-usd">{{ fmtUsd(cartTotalUsd) }}</span>
                                </div>
                            </div>

                            <p v-if="newError" class="error-msg">{{ newError }}</p>

                            <button
                                class="btn-brand btn-block mt-4"
                                :disabled="saving"
                                @click="submitNewOrder"
                            >{{ saving ? 'Guardando…' : 'Crear Pedido' }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ═══════════════════════════════════════════════════════════════════
             MODAL: COBRAR
        ════════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <div v-if="showCollectModal" class="modal-overlay">
                <div class="modal-box modal-md">
                    <div class="modal-head">
                        <h2 class="modal-title">Cobrar Pedido</h2>
                        <button class="modal-close" @click="showCollectModal = false">✕</button>
                    </div>

                    <div class="collect-client-bar">
                        <span class="collect-client-lbl">Cliente:</span>
                        <strong v-if="collectOrder?.client_name" class="collect-client-val">{{ collectOrder.client_name }}</strong>
                        <span v-else class="collect-client-none">Sin cliente asignado</span>
                    </div>

                    <!-- Resumen items -->
                    <div class="collect-items">
                        <div v-for="item in collectOrder?.items ?? []" :key="item.id" class="collect-item-row">
                            <span class="ci-name">{{ item.product_name }}</span>
                            <span class="ci-qty">{{ item.quantity_value }} {{ item.unit_label }}</span>
                            <span class="ci-sub">{{ fmtBs(item.subtotal_bs) }}</span>
                        </div>
                        <div class="collect-total-row">
                            <span>Total a cobrar</span>
                            <span class="collect-total-bs">{{ fmtBs(collectTotalBs) }}</span>
                        </div>
                    </div>

                    <!-- Multi-pago -->
                    <div class="pay-section">
                        <p class="pay-section-label">Pagos registrados</p>
                        <div v-for="(p, i) in payments" :key="i" class="pay-line">
                            <span class="pay-method">{{ p._label }}</span>
                            <span class="pay-amount">{{ fmtBs(p.amount_bs) }}</span>
                            <button class="pay-remove" @click="removePayment(i)">✕</button>
                        </div>

                        <div class="pay-add-row">
                            <select v-model="newPmtMethodId" class="field-input pay-select">
                                <optgroup label="Métodos de pago">
                                    <option v-for="m in paymentMethods" :key="m.id" :value="String(m.id)">{{ m.name }}</option>
                                </optgroup>
                                <optgroup v-if="paymentTerminals.length" label="Terminales">
                                    <option v-for="t in paymentTerminals" :key="`T:${t.id}`" :value="`T:${t.id}`">{{ t.bank_name ?? t.method }}</option>
                                </optgroup>
                            </select>
                            <input v-model="newPmtAmountBs" class="field-input pay-amount-input" type="number" step="0.01" placeholder="Monto Bs." />
                            <button class="btn-sm" @click="fillRest" title="Completar restante">↓</button>
                            <button class="btn-brand btn-sm" @click="addPayment" :disabled="paidBs >= collectTotalBs">+ Agregar</button>
                        </div>

                        <input v-model="newPmtRef" class="field-input mt-1" placeholder="Referencia (opcional)" />

                        <div class="pay-summary">
                            <div class="pay-row-summary">
                                <span>Pagado</span>
                                <span>{{ fmtBs(paidBs) }}</span>
                            </div>
                            <div class="pay-row-summary" v-if="restBs > 0">
                                <span class="warn-text">Resta</span>
                                <span class="warn-text">{{ fmtBs(restBs) }}</span>
                            </div>
                            <div class="pay-row-summary" v-if="changeBs > 0">
                                <span class="ok-text">Vuelto</span>
                                <span class="ok-text">{{ fmtBs(changeBs) }}</span>
                            </div>
                        </div>
                    </div>

                    <p v-if="collectError" class="error-msg">{{ collectError }}</p>

                    <button
                        class="btn-brand btn-block"
                        :disabled="!canConfirm || saving"
                        @click="submitCollect"
                    >{{ saving ? 'Procesando…' : 'Confirmar Cobro' }}</button>
                </div>
            </div>
        </Teleport>

        <!-- ═══════════════════════════════════════════════════════════════════
             MODAL: CANCELAR
        ════════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <div v-if="showCancelModal" class="modal-overlay">
                <div class="modal-box modal-sm">
                    <div class="modal-head">
                        <h2 class="modal-title">Cancelar Pedido</h2>
                        <button class="modal-close" @click="showCancelModal = false">✕</button>
                    </div>

                    <p class="cancel-client">Pedido de: <strong>{{ cancelOrder?.client_name }}</strong></p>

                    <div class="field-row">
                        <label class="field-label">Motivo de cancelación (obligatorio)</label>
                        <textarea v-model="cancelReason" class="field-input" rows="3" placeholder="Indique el motivo…"></textarea>
                    </div>

                    <p v-if="cancelError" class="error-msg">{{ cancelError }}</p>

                    <button
                        class="btn-danger btn-block"
                        :disabled="saving || cancelReason.length < 5"
                        @click="submitCancel"
                    >{{ saving ? 'Cancelando…' : 'Confirmar Cancelación' }}</button>
                </div>
            </div>
        </Teleport>

        <!-- ═══════════════════════════════════════════════════════════════════
             MODAL: REGISTRAR COBRO (venta despachada sin pago)
        ════════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <div v-if="showPendCollectModal" class="modal-overlay">
                <div class="modal-box modal-md">
                    <div class="modal-head">
                        <h2 class="modal-title">Registrar Cobro</h2>
                        <button class="modal-close" @click="showPendCollectModal = false">✕</button>
                    </div>

                    <p class="pend-meta">
                        Ticket <strong>{{ pendCollectSale?.ticket_number }}</strong>
                    </p>
                    <p v-if="pendCollectSale?.client_name" class="pend-client">
                        Cliente: {{ pendCollectSale.client_name }}
                    </p>
                    <p v-else class="pend-client-none">Sin cliente asignado</p>

                    <!-- Resumen items -->
                    <div class="collect-items">
                        <div v-for="(item, i) in pendCollectSale?.items ?? []" :key="i" class="collect-item-row">
                            <span class="ci-name">{{ item.product_name }}</span>
                            <span class="ci-qty">{{ item.quantity_value }} {{ item.unit_label }}</span>
                            <span class="ci-sub">{{ fmtBs(item.subtotal_bs) }}</span>
                        </div>
                        <div class="collect-total-row">
                            <span>Total a cobrar</span>
                            <span class="collect-total-bs">{{ fmtBs(pendTotalBs) }}</span>
                        </div>
                    </div>

                    <!-- Multi-pago -->
                    <div class="pay-section">
                        <p class="pay-section-label">Pagos</p>
                        <div v-for="(p, i) in pendPayments" :key="i" class="pay-line">
                            <span class="pay-method">{{ p._label }}</span>
                            <span class="pay-amount">{{ fmtBs(p.amount_bs) }}</span>
                            <button class="pay-remove" @click="removePendPayment(i)">✕</button>
                        </div>

                        <div class="pay-add-row">
                            <select v-model="pendPmtMethodId" class="field-input pay-select">
                                <optgroup label="Métodos de pago">
                                    <option v-for="m in paymentMethods" :key="m.id" :value="String(m.id)">{{ m.name }}</option>
                                </optgroup>
                                <optgroup v-if="paymentTerminals.length" label="Terminales">
                                    <option v-for="t in paymentTerminals" :key="`T:${t.id}`" :value="`T:${t.id}`">{{ t.bank_name ?? t.method }}</option>
                                </optgroup>
                            </select>
                            <input v-model="pendPmtAmountBs" class="field-input pay-amount-input" type="number" step="0.01" placeholder="Monto Bs." />
                            <button class="btn-sm" @click="fillPendRest" title="Completar restante">↓</button>
                            <button class="btn-brand btn-sm" @click="addPendPayment" :disabled="pendPaidBs >= pendTotalBs">+ Agregar</button>
                        </div>

                        <input v-model="pendPmtRef" class="field-input mt-1" placeholder="Referencia (opcional)" />

                        <div class="pay-summary">
                            <div class="pay-row-summary">
                                <span>Pagado</span>
                                <span>{{ fmtBs(pendPaidBs) }}</span>
                            </div>
                            <div class="pay-row-summary" v-if="pendRestBs > 0">
                                <span class="warn-text">Resta</span>
                                <span class="warn-text">{{ fmtBs(pendRestBs) }}</span>
                            </div>
                            <div class="pay-row-summary" v-if="pendChangeBs > 0">
                                <span class="ok-text">Vuelto</span>
                                <span class="ok-text">{{ fmtBs(pendChangeBs) }}</span>
                            </div>
                        </div>
                    </div>

                    <p v-if="pendCollectError" class="error-msg">{{ pendCollectError }}</p>

                    <button
                        class="btn-brand btn-block"
                        :disabled="!pendCanConfirm || saving"
                        @click="submitPendCollect"
                    >{{ saving ? 'Procesando…' : 'Confirmar Cobro' }}</button>
                </div>
            </div>
        </Teleport>

        <!-- ── Panel de ayuda ────────────────────────────────────────────── -->
        <HelpModal
            :show="showHelp"
            title="Pedidos — Cómo funciona"
            :steps="helpSteps"
            :faqs="helpFaqs"
            @close="showHelp = false"
        />

    </AppLayout>
</template>

<style scoped>
.orders-wrap {
    padding: 1rem 0.85rem;
    max-width: 1280px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
@media (min-width: 640px) {
    .orders-wrap { padding: 1.5rem; gap: 1.25rem; }
}

/* ─── KPIs ─────────────────────────────────────────────────────────────────── */
.kpi-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
@media (min-width: 640px) { .kpi-row { grid-template-columns: repeat(4, 1fr); gap: 1rem; } }
.kpi-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.kpi-card.kpi-amber { border-color: rgba(245,158,11,0.4); background: rgba(245,158,11,0.07); }
.kpi-card.kpi-brand { border-color: rgba(var(--brand-rgb, 99,102,241),0.3); background: rgba(var(--brand-rgb, 99,102,241),0.06); }
.kpi-card.kpi-green { border-color: rgba(22,163,74,0.35); background: rgba(22,163,74,0.06); }
.kpi-card.kpi-red   { border-color: rgba(239,68,68,0.35); background: rgba(239,68,68,0.06); }
.kpi-label { font-size: 0.73rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.kpi-big   { font-size: 1.8rem; font-weight: 800; color: var(--text-primary); line-height: 1.1; }
.kpi-big.kpi-sm { font-size: 1.2rem; }
.kpi-sub   { font-size: 0.73rem; color: var(--text-muted); }

/* ─── Header ─────────────────────────────────────────────────────────────── */
.orders-header { display: flex; align-items: center; justify-content: space-between; }
.tabs { display: flex; gap: 0.5rem; background: var(--bg-base); border-radius: 10px; padding: 0.25rem; }
.tab {
    padding: 0.6rem 1.1rem;
    border-radius: 7px;
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--text-muted);
    background: transparent;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    transition: background 0.15s, color 0.15s;
    min-height: 44px;
}
.tab.tab-active { background: var(--bg-card); color: var(--text-primary); }
.tab-badge {
    background: var(--brand);
    color: #fff;
    border-radius: 99px;
    font-size: 0.7rem;
    padding: 0.05rem 0.45rem;
    font-weight: 700;
}
.tab-badge-red { background: #ef4444; }
.btn-brand {
    background: var(--brand);
    color: #fff;
    border: none;
    padding: 0.6rem 1.25rem;
    border-radius: 9px;
    font-weight: 700;
    font-size: 0.88rem;
    cursor: pointer;
    transition: opacity 0.15s;
    min-height: 44px;
}
.btn-brand:hover { opacity: 0.88; }
.btn-brand:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-danger {
    background: #ef4444;
    color: #fff;
    border: none;
    padding: 0.55rem 1.25rem;
    border-radius: 9px;
    font-weight: 700;
    font-size: 0.88rem;
    cursor: pointer;
}
.btn-danger:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-block { width: 100%; margin-top: 1rem; }
.btn-sm { padding: 0.35rem 0.75rem; font-size: 0.82rem; border-radius: 7px; }
.mt-1 { margin-top: 0.5rem; }
.mt-4 { margin-top: 1rem; }

/* ─── Orders grid ─────────────────────────────────────────────────────────── */
.orders-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
}
.order-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 1rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.order-card-head { display: flex; align-items: center; justify-content: space-between; }
.order-meta { display: flex; align-items: center; gap: 0.5rem; }
.order-client { font-weight: 700; font-size: 0.95rem; color: var(--text-primary); }
.order-elapsed { font-size: 0.75rem; color: var(--text-muted); }
.type-badge {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.15rem 0.55rem;
    border-radius: 99px;
    white-space: nowrap;
}
.type-badge-sm { font-size: 0.68rem; font-weight: 700; padding: 0.1rem 0.45rem; border-radius: 99px; }
.badge-amber { background: rgba(245,158,11,0.15); color: #d97706; }
.badge-blue  { background: rgba(59,130,246,0.12); color: #3b82f6; }
.order-items { display: flex; flex-direction: column; gap: 0.3rem; }
.order-item-row { display: flex; align-items: center; gap: 0.5rem; font-size: 0.84rem; }
.item-name { flex: 1; color: var(--text-primary); }
.item-qty  { color: var(--text-muted); white-space: nowrap; }
.item-sub  { font-weight: 600; color: var(--text-primary); white-space: nowrap; }
.order-footer { display: flex; align-items: center; justify-content: space-between; padding-top: 0.5rem; border-top: 1px solid var(--border); }
.order-total { display: flex; flex-direction: column; }
.total-bs  { font-weight: 800; font-size: 1.1rem; color: var(--brand); }
.total-usd { font-size: 0.75rem; color: var(--text-muted); }
.order-actions { display: flex; gap: 0.5rem; }
.btn-collect {
    background: rgba(22,163,74,0.15);
    color: #16a34a;
    border: 1px solid rgba(22,163,74,0.3);
    padding: 0.55rem 0.9rem;
    border-radius: 8px;
    font-size: 0.84rem;
    font-weight: 700;
    cursor: pointer;
    min-height: 44px;
}
.btn-collect:hover { background: rgba(22,163,74,0.22); }
.btn-cancel-order {
    background: rgba(239,68,68,0.1);
    color: #ef4444;
    border: 1px solid rgba(239,68,68,0.25);
    padding: 0.55rem 0.9rem;
    border-radius: 8px;
    font-size: 0.84rem;
    font-weight: 700;
    cursor: pointer;
    min-height: 44px;
}
.btn-cancel-order:hover { background: rgba(239,68,68,0.17); }
.btn-dispatch {
    background: rgba(99,102,241,0.12);
    color: var(--brand);
    border: 1px solid rgba(99,102,241,0.3);
    padding: 0.55rem 0.9rem;
    border-radius: 8px;
    font-size: 0.84rem;
    font-weight: 700;
    cursor: pointer;
    min-height: 44px;
}
.btn-dispatch:hover { background: rgba(99,102,241,0.2); }
.btn-dispatch:disabled { opacity: 0.5; cursor: not-allowed; }
.order-notes { font-size: 0.78rem; color: var(--text-muted); display: flex; align-items: center; gap: 5px; }
.notes-icon  { flex-shrink: 0; }

/* ─── Cobros Pendientes ───────────────────────────────────────────────────── */
.cobros-list { display: flex; flex-direction: column; gap: 0.75rem; }
.cobro-card {
    background: var(--bg-card);
    border: 1px solid rgba(239,68,68,0.3);
    border-radius: 14px;
    padding: 1rem 1.1rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}
.cobro-head    { display: flex; align-items: center; justify-content: space-between; }
.cobro-meta    { display: flex; flex-direction: column; align-items: flex-start; gap: 0.1rem; }
.cobro-ticket  { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.cobro-client  { font-weight: 700; font-size: 1rem; color: var(--brand); }
.cobro-elapsed { font-size: 0.75rem; color: var(--text-muted); }
.cobro-items   { display: flex; flex-direction: column; gap: 0.25rem; }
.cobro-item-row { display: flex; align-items: center; gap: 0.5rem; font-size: 0.84rem; }
.cobro-footer  { display: flex; align-items: center; justify-content: space-between; padding-top: 0.5rem; border-top: 1px solid var(--border); }
.pend-meta         { font-size: 0.82rem; color: var(--text-muted); }
.pend-client       { font-size: 0.95rem; font-weight: 700; color: var(--brand); margin-top: 0.1rem; }
.pend-client-none  { font-size: 0.82rem; color: var(--text-muted); font-style: italic; }
.cobro-client-none { font-size: 0.82rem; color: var(--text-muted); font-style: italic; }
.collect-client-bar { display: flex; align-items: center; gap: 0.4rem; padding: 0.5rem 0.75rem; background: var(--bg-base); border-radius: 8px; }
.collect-client-lbl  { font-size: 0.78rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
.collect-client-val  { font-size: 0.95rem; color: var(--brand); }
.collect-client-none { font-size: 0.85rem; color: var(--text-muted); font-style: italic; }

/* ─── Card genérica ──────────────────────────────────────────────────────── */
.card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; padding: 1.25rem; }

/* ─── Historial tabla ─────────────────────────────────────────────────────── */
.table-wrap { overflow-x: auto; }
.hist-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
.hist-table th { padding: 0.5rem 0.75rem; text-align: left; font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid var(--border); }
.hist-table td { padding: 0.55rem 0.75rem; border-bottom: 1px solid var(--border); color: var(--text-primary); }
.hist-table tr:last-child td { border-bottom: none; }
.status-pill { font-size: 0.72rem; padding: 0.15rem 0.55rem; border-radius: 99px; font-weight: 600; }
.pill-paid      { background: rgba(22,163,74,0.14); color: #16a34a; }
.pill-cancelled { background: rgba(239,68,68,0.12); color: #ef4444; }
.muted  { color: var(--text-muted); }
.center { text-align: center; }
.amount { font-weight: 700; }
.empty-row  { text-align: center; color: var(--text-muted); padding: 1.5rem !important; }
.empty-state { text-align: center; color: var(--text-muted); padding: 3rem 0; font-size: 0.95rem; }

/* ─── Modales ─────────────────────────────────────────────────────────────── */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.55);
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.modal-box {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 1.5rem;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.modal-sm { max-width: 440px; }
.modal-md { max-width: 580px; }
.modal-xl { max-width: 920px; }
.modal-head { display: flex; align-items: center; justify-content: space-between; }
.modal-title { font-size: 1.1rem; font-weight: 800; color: var(--text-primary); }
.modal-close { background: none; border: none; color: var(--text-muted); font-size: 1.1rem; cursor: pointer; }

/* ─── Campos ──────────────────────────────────────────────────────────────── */
.field-row { display: flex; flex-direction: column; gap: 0.35rem; }
.field-label { font-size: 0.78rem; color: var(--text-muted); font-weight: 600; }
.field-input {
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 9px;
    padding: 0.5rem 0.75rem;
    color: var(--text-primary);
    font-size: 0.9rem;
    width: 100%;
    outline: none;
}
.field-input:focus { border-color: var(--brand); }

/* ─── Nuevo Pedido — layout 2 col ────────────────────────────────────────── */
.new-order-body { display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
.new-order-left { display: flex; flex-direction: column; gap: 0.85rem; overflow: hidden; }
.new-order-right { display: flex; flex-direction: column; gap: 0.75rem; }

.type-toggle { display: flex; gap: 0.5rem; }
.toggle-btn {
    flex: 1;
    padding: 0.5rem;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--bg-base);
    color: var(--text-muted);
    font-size: 0.84rem;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
}
.toggle-btn.toggle-active { border-color: var(--brand); color: var(--brand); background: rgba(var(--brand-rgb,99,102,241),0.08); }

.prod-filters { display: flex; flex-direction: column; gap: 0.5rem; }
.cat-chips { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.cat-chip {
    padding: 0.25rem 0.7rem;
    border-radius: 99px;
    border: 1px solid var(--border);
    background: var(--bg-base);
    color: var(--text-muted);
    font-size: 0.76rem;
    cursor: pointer;
}
.cat-chip.active { border-color: var(--brand); color: var(--brand); }

.prod-mini-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 0.5rem;
    max-height: 240px;
    overflow-y: auto;
}
.prod-mini-btn {
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 0.5rem 0.6rem;
    cursor: pointer;
    text-align: left;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    transition: border-color 0.12s;
}
.prod-mini-btn:hover { border-color: var(--brand); }
.prod-mini-name  { font-size: 0.82rem; font-weight: 600; color: var(--text-primary); line-height: 1.2; }
.prod-mini-price { font-size: 0.72rem; color: var(--text-muted); }

/* ─── Carrito ─────────────────────────────────────────────────────────────── */
.cart-title { font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); }
.cart-list { display: flex; flex-direction: column; gap: 0.5rem; max-height: 360px; overflow-y: auto; }
.cart-row  { padding: 0.55rem 0.65rem; background: var(--bg-base); border-radius: 9px; border: 1px solid var(--border); }
.cart-info { display: flex; flex-direction: column; gap: 0.4rem; }

/* Nombre + eliminar en la misma fila */
.cart-name-row  { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
.cart-name      { font-size: 0.84rem; font-weight: 600; color: var(--text-primary); }
.cart-remove    { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 0.85rem; padding: 0 0.15rem; flex-shrink: 0; }
.cart-remove:hover { color: #EF4444; }

/* Dual row: [qty block] ⇄ [bs block] */
.cart-dual-row  { display: flex; align-items: center; gap: 0.5rem; }
.dual-block     { display: flex; flex-direction: column; gap: 0.2rem; flex: 1; }
.dual-lbl       { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); }
.dual-input-row { display: flex; align-items: center; gap: 0.25rem; }
.dual-sep       { font-size: 0.9rem; color: var(--text-muted); flex-shrink: 0; margin-top: 1rem; }

.qty-btn   { background: var(--bg-card); border: 1px solid var(--border); border-radius: 6px; width: 1.5rem; height: 1.5rem; cursor: pointer; font-size: 0.9rem; color: var(--text-primary); line-height: 1; flex-shrink: 0; }
.qty-input { width: 3.2rem; text-align: center; background: var(--bg-card); border: 1px solid var(--border); border-radius: 6px; padding: 0.15rem 0.25rem; font-size: 0.84rem; color: var(--text-primary); font-variant-numeric: tabular-nums; }
.bs-input  { width: 100%; border-color: color-mix(in srgb, var(--brand) 35%, var(--border)); background: color-mix(in srgb, var(--brand) 5%, var(--bg-card)); color: var(--brand); font-weight: 600; }
.cart-total-row { display: flex; align-items: center; justify-content: space-between; padding-top: 0.5rem; border-top: 1px solid var(--border); }
.cart-total-label { font-weight: 600; color: var(--text-muted); font-size: 0.84rem; }
.cart-total-bs  { display: block; font-weight: 800; font-size: 1.05rem; color: var(--brand); }
.cart-total-usd { display: block; font-size: 0.75rem; color: var(--text-muted); }

/* ─── Cobrar modal ────────────────────────────────────────────────────────── */
.collect-items { display: flex; flex-direction: column; gap: 0.35rem; border: 1px solid var(--border); border-radius: 10px; padding: 0.75rem; }
.collect-item-row { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; }
.ci-name { flex: 1; color: var(--text-primary); }
.ci-qty  { color: var(--text-muted); }
.ci-sub  { font-weight: 600; }
.collect-total-row { display: flex; align-items: center; justify-content: space-between; padding-top: 0.5rem; border-top: 1px solid var(--border); font-weight: 700; }
.collect-total-bs { font-size: 1.1rem; color: var(--brand); }

.pay-section { display: flex; flex-direction: column; gap: 0.5rem; }
.pay-section-label { font-size: 0.78rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
.pay-line { display: flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0.5rem; background: var(--bg-base); border-radius: 7px; font-size: 0.84rem; }
.pay-method { flex: 1; color: var(--text-primary); }
.pay-amount { font-weight: 700; }
.pay-remove { background: none; border: none; color: var(--text-muted); cursor: pointer; }
.pay-add-row { display: flex; align-items: center; gap: 0.5rem; }
.pay-select { flex: 0 0 auto; width: auto; }
.pay-amount-input { flex: 1; }
.pay-summary { display: flex; flex-direction: column; gap: 0.2rem; padding-top: 0.5rem; border-top: 1px solid var(--border); }
.pay-row-summary { display: flex; justify-content: space-between; font-size: 0.88rem; font-weight: 600; }
.warn-text { color: #ef4444; }
.ok-text   { color: #16a34a; }

/* ─── Cancelar modal ──────────────────────────────────────────────────────── */
.cancel-client { font-size: 0.9rem; color: var(--text-muted); }

/* ─── Misc ────────────────────────────────────────────────────────────────── */
.empty-msg { font-size: 0.84rem; color: var(--text-muted); text-align: center; padding: 0.75rem 0; }
.error-msg { font-size: 0.84rem; color: #ef4444; background: rgba(239,68,68,0.1); padding: 0.5rem 0.75rem; border-radius: 7px; }

/* ─── Header actions ────────────────────────────────────────────────────── */
.header-actions { display: flex; align-items: center; gap: 0.5rem; }
.tab-btn--help {
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
    flex-shrink: 0;
}
.tab-btn--help:hover { background: var(--brand); color: #fff; border-color: var(--brand); }

/* ─── Responsive ─────────────────────────────────────────────────────────── */
/* Base (mobile): new-order body stacks, orders-grid 1 col */
.orders-header { flex-wrap: wrap; gap: 0.75rem; }
@media (min-width: 900px) {
    .new-order-body { grid-template-columns: 1fr 320px; }
}
</style>
