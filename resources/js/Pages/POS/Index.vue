﻿<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AppLogo from '@/Components/AppLogo.vue';
import HelpModal from '@/Components/HelpModal.vue';
import { Lock, AlertTriangle, FileText, Store, Bike, Clock, Check, ChevronLeft, ChevronRight, Bell, X } from '@lucide/vue';

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    products:       { type: Array,  default: () => [] },
    categories:     { type: Array,  default: () => [] },
    cashRegister:   { type: Object, default: null },
    openRegisters:  { type: Array,  default: () => [] },
    todayRate:      { type: Number, default: 1 },
    paymentMethods: { type: Array,  default: () => [] },
    ticketPrefix:   { type: String, default: 'VEN' },
    stockMap:       { type: Object, default: () => ({}) },
    posShowKg:      { type: Boolean, default: false },
    businessInfo:   { type: Object, default: () => ({}) },
    ticketPrefs:    { type: Object, default: () => ({ show_address: true, show_phone: false, show_client: true }) },
});

// ─── Auth / usuario ───────────────────────────────────────────────────────────
const page     = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);

// ─── Selección de caja (multi-caja por sucursal) ───────────────────────────────
const selectingCaja = ref(false);
function selectCaja(id) {
    if (selectingCaja.value) return;
    selectingCaja.value = true;
    window.axios.post(route('cash.select'), { cash_register_id: id })
        .then(() => router.reload())
        .catch(() => { selectingCaja.value = false; });
}

// ─── Alerta de corte bancario ─────────────────────────────────────────────────
const dismissedAlertText = ref(null);
const activeBankingAlert = computed(() => {
    const msg = page.props.banking_alert;
    if (!msg || msg === dismissedAlertText.value) return null;
    return msg;
});
function dismissBankingAlert() {
    dismissedAlertText.value = page.props.banking_alert;
}

// ─── Reloj ────────────────────────────────────────────────────────────────────
const currentTime = ref('');
let clockTimer;
function tick() {
    const n = new Date();
    const t = n.toLocaleTimeString('es-VE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const d = n.toLocaleDateString('es-VE', { weekday: 'short', day: '2-digit', month: 'short' });
    currentTime.value = `${t} · ${d}`;
}
// ─── Tabs scroll arrows ───────────────────────────────────────────────────────
const tabsWrapRef  = ref(null);
const tabArrowLeft  = ref(false);
const tabArrowRight = ref(false);

function updateTabArrows() {
    const el = tabsWrapRef.value;
    if (!el) return;
    tabArrowLeft.value  = el.scrollLeft > 1;
    tabArrowRight.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 1;
}
function scrollTabsLeft()  { tabsWrapRef.value?.scrollBy({ left: -160, behavior: 'smooth' }); }
function scrollTabsRight() { tabsWrapRef.value?.scrollBy({ left:  160, behavior: 'smooth' }); }

onMounted(() => {
    tick();
    clockTimer = setInterval(tick, 1000);
    window.addEventListener('keydown', onPhysKeyDown);
    window.addEventListener('keydown', onKeyDown);
    window.addEventListener('resize', updateTabArrows);
    nextTick(updateTabArrows);
});
onUnmounted(() => {
    clearInterval(clockTimer);
    clearTimeout(scanTimer);
    window.removeEventListener('keydown', onPhysKeyDown);
    window.removeEventListener('keydown', onKeyDown);
    window.removeEventListener('resize', updateTabArrows);
});

// ─── Animación "recién añadido" ───────────────────────────────────────────────
const justAdded    = ref(null);
const scanFeedback = ref(null);

// ─── Tickets (tabs paralelos, max 5) ─────────────────────────────────────────
function emptyTicket(n) {
    return { id: n, label: `Ticket #${n}`, items: [], sale: null };
}
function loadTicketsFromSession() {
    try {
        const saved = sessionStorage.getItem('pos_tickets')
        const savedActive = sessionStorage.getItem('pos_active_ticket')
        if (saved) {
            const parsed = JSON.parse(saved)
            if (Array.isArray(parsed) && parsed.length > 0) {
                return { tickets: parsed, active: parseInt(savedActive ?? '0') }
            }
        }
    } catch (e) {}
    return null
}
const _savedPos = loadTicketsFromSession()
const tickets      = ref(_savedPos ? _savedPos.tickets : [emptyTicket(1)])
const activeTicket = ref(_savedPos ? _savedPos.active : 0)

watch(tickets, (val) => {
    try { sessionStorage.setItem('pos_tickets', JSON.stringify(val)) } catch (e) {}
}, { deep: true })

watch(activeTicket, (val) => {
    try { sessionStorage.setItem('pos_active_ticket', String(val)) } catch (e) {}
})
const cart         = computed(() => tickets.value[activeTicket.value].items);

function addTicket() {
    if (tickets.value.length >= 5) return;
    const n = tickets.value.length + 1;
    tickets.value.push(emptyTicket(n));
    activeTicket.value = tickets.value.length - 1;
}
function removeTicket(idx) {
    tickets.value.splice(idx, 1);
    if (!tickets.value.length) tickets.value.push(emptyTicket(1));
    activeTicket.value = Math.min(activeTicket.value, tickets.value.length - 1);
}

// ─── Filtro de categorías + búsqueda ─────────────────────────────────────────
const selectedCat = ref(null);
const search      = ref('');
const catPage     = ref(1);
const PAGE_SIZE   = 20;

// Helper: producto tiene stock disponible (null = sin seguimiento = OK)
function hasStock(p) {
    const s = stockFor(p);
    return s === null || s > 0;
}

const soloConStock = ref(true);

const categoryProductsAll = computed(() => {
    const base = selectedCat.value === 'favorites'
        ? props.products.filter(p => p.is_favorite)
        : selectedCat.value === null
            ? props.products
            : props.products.filter(p => p.category_id === selectedCat.value);
    return base.filter(p => !soloConStock.value || stockFor(p) > 0);
});

// Búsqueda: siempre todos los productos, ignora el toggle
// Con stock primero → sin stock al final, max 20
const searchResults = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return categoryProductsAll.value;
    const matched = props.products.filter(p => p.name.toLowerCase().includes(q));
    const inStock  = matched.filter(hasStock);
    const noStock  = matched.filter(p => !hasStock(p));
    return [...inStock, ...noStock].slice(0, 20);
});

// Paginación (solo aplica cuando NO hay búsqueda activa)
const categoryProducts = computed(() =>
    categoryProductsAll.value.slice(0, catPage.value * PAGE_SIZE)
);
const hasMoreCategory = computed(() =>
    categoryProducts.value.length < categoryProductsAll.value.length
);

// Lo que se muestra: búsqueda si hay query, paginado si no
const displayedProducts = computed(() =>
    search.value.trim() ? searchResults.value : categoryProducts.value
);

function selectCat(id) {
    selectedCat.value = id;
    catPage.value     = 1;
    search.value      = '';
}
function loadMore() { catPage.value++; }

// ─── Precio en Bs ─────────────────────────────────────────────────────────────
function priceBs(product) {
    const usd = product.sale_mode === 'weight'
        ? parseFloat(product.price_per_kg_usd || 0)
        : parseFloat(product.price_per_unit_usd || 0);
    return usd * props.todayRate;
}
function fmtBs(n)  { return Number(n).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function fmtUsd(n) { return '$' + Number(n).toFixed(2); }
function fmtQty(qty, mode) {
    return mode === 'weight' ? Number(qty).toFixed(3) + ' kg' : Number(qty).toFixed(0) + ' und';
}

// ─── Color de categoría ───────────────────────────────────────────────────────
function catColor(product) {
    return product.category?.color ?? '#64748B';
}

// ─── Modal de cantidad ─────────────────────────────────────────────────────────
const qtyModal   = ref(false);
const qtyProduct = ref(null);
const qtyInput   = ref('');

function openQtyModal(product) {
    qtyProduct.value = product;
    qtyInput.value   = '';
    qtyModal.value   = true;
}
function closeQtyModal() { qtyModal.value = false; qtyProduct.value = null; qtyInput.value = ''; }

function kbPress(key) {
    if (key === '←') { qtyInput.value = qtyInput.value.slice(0, -1); return; }
    // Para weight el cajero ingresa Bs. (con decimales); para unit solo enteros
    if (key === '.' && qtyProduct.value?.sale_mode !== 'weight') return;
    if (key === '.' && qtyInput.value.includes('.')) return;
    if (qtyInput.value === '0' && key !== '.') { qtyInput.value = key; return; }
    qtyInput.value += key;
}

// Bug 2 FIX: teclado físico → delegar al numpad del modal
function onPhysKeyDown(e) {
    if (!qtyModal.value) return;
    if (e.key >= '0' && e.key <= '9') { e.preventDefault(); kbPress(e.key); return; }
    if (e.key === '.')         { e.preventDefault(); kbPress('.'); return; }
    if (e.key === 'Backspace') { e.preventDefault(); kbPress('←'); return; }
    if (e.key === 'Enter' && qtyValue.value > 0) { e.preventDefault(); addToCart(); }
}

// Bug 1 FIX: para weight, qtyInput es BOLÍVARES → el sistema calcula kg
// para unit, qtyInput sigue siendo unidades (sin cambio)
const qtyKg = computed(() => {
    if (!qtyProduct.value || qtyProduct.value.sale_mode !== 'weight') return 0;
    const priceBsKg = parseFloat(qtyProduct.value.price_per_kg_usd || 0) * props.todayRate;
    return priceBsKg > 0 ? Math.round((parseFloat(qtyInput.value) / priceBsKg) * 1000) / 1000 : 0;
});

const qtyValue = computed(() => {
    if (!qtyProduct.value) return 0;
    return qtyProduct.value.sale_mode === 'weight'
        ? qtyKg.value
        : (parseFloat(qtyInput.value) || 0);
});

const qtyUsd = computed(() => {
    if (!qtyProduct.value) return 0;
    if (qtyProduct.value.sale_mode === 'weight') {
        return props.todayRate > 0 ? (parseFloat(qtyInput.value) || 0) / props.todayRate : 0;
    }
    return qtyValue.value * parseFloat(qtyProduct.value.price_per_unit_usd || 0);
});

const qtyBsTotal = computed(() => {
    if (!qtyProduct.value) return 0;
    return qtyProduct.value.sale_mode === 'weight'
        ? (parseFloat(qtyInput.value) || 0)   // el input YA es Bs.
        : qtyUsd.value * props.todayRate;
});

function addToCart() {
    if (qtyValue.value <= 0) return;
    const product    = qtyProduct.value;
    const isWeight   = product.sale_mode === 'weight';
    const inputedBs  = isWeight ? (parseFloat(qtyInput.value) || 0) : null;
    const existing   = cart.value.find(i => i.product_id === product.id);
    if (existing) {
        existing.quantity_value += qtyValue.value;
        if (isWeight && inputedBs !== null) existing.amount_bs = (existing.amount_bs ?? 0) + inputedBs;
        existing.subtotal_usd = parseFloat(qtyUsd.value.toFixed(2));
        existing.subtotal_usd = parseFloat(((isWeight
            ? parseFloat(product.price_per_kg_usd || 0)
            : parseFloat(product.price_per_unit_usd || 0)) * existing.quantity_value).toFixed(2));
    } else {
        cart.value.push({
            product_id:         product.id,
            product_name:       product.name,
            input_type:         isWeight ? 'weight' : 'unit',
            sale_mode:          product.sale_mode,
            quantity_value:     qtyValue.value,        // kg para weight, unidades para unit
            amount_bs:          inputedBs,             // Bs. ingresados (solo weight)
            price_per_kg_usd:   parseFloat(product.price_per_kg_usd  || 0),
            price_per_unit_usd: parseFloat(product.price_per_unit_usd || 0),
            subtotal_usd:       parseFloat(qtyUsd.value.toFixed(2)),
        });
    }
    const pid = product.id;
    justAdded.value = pid;
    setTimeout(() => { justAdded.value = null; }, 750);
    closeQtyModal();
}

function removeFromCart(idx) { cart.value.splice(idx, 1); }

const cartTotalUsd = computed(() => cart.value.reduce((s, i) => s + i.subtotal_usd, 0));
const cartTotalBs  = computed(() =>
    cart.value.reduce((sum, item) =>
        sum + (item.input_type === 'weight'
            ? (item.amount_bs ?? 0)
            : item.subtotal_usd * props.todayRate),
    0)
);
const cartCount    = computed(() => cart.value.reduce((s, i) => s + i.quantity_value, 0));

// ─── Carrito mobile drawer ────────────────────────────────────────────────────
const showMobileCart = ref(false);

// ─── Modal de cobro (multi-pago) ─────────────────────────────────────────────
const payModal    = ref(false);
const paying      = ref(false);
const payments    = ref([]);
const saleOrigin  = ref('onsite'); // onsite | delivery | credit

const newPmtMethodId  = ref(null);
const newPmtAmountBs  = ref('');
const newPmtReference = ref('');

function roundCents(n) { return Math.round(n * 100) / 100; }
function exactBs(n)   { return roundCents(n).toFixed(2); }

const payTotalBs    = computed(() => roundCents(cartTotalBs.value));
const payPaidBs     = computed(() => roundCents(payments.value.reduce((s, p) => s + parseFloat(p.amount_bs || 0), 0)));
const payRestBs     = computed(() => Math.max(0, roundCents(payTotalBs.value - payPaidBs.value)));
const payChangeBs   = computed(() => Math.max(0, roundCents(payPaidBs.value - payTotalBs.value)));
const selectedMethodNeedsRef = computed(() => {
    if (!newPmtMethodId.value) return false;
    const m = props.paymentMethods.find(p => p.id === newPmtMethodId.value);
    return m ? ['transfer', 'mobile', 'biometric'].includes(m.type) : false;
});

function openPayModal() {
    if (!cart.value.length) return;
    if (negativeStockItems.value.length > 0) {
        showNegativeWarning.value = true;
        return;
    }
    launchPayModal();
}
function launchPayModal() {
    showNegativeWarning.value = false;
    payments.value        = [];
    saleOrigin.value      = 'onsite';
    newPmtMethodId.value  = props.paymentMethods[0]?.id ?? null;
    newPmtAmountBs.value  = exactBs(cartTotalBs.value);
    newPmtReference.value = '';
    showClientFields.value = false;
    clientId.value        = null;
    clientName.value      = '';
    clientPhone.value     = '';
    clientSearch.value    = '';
    clientSearchOpen.value = false;
    payModal.value        = true;
}
function closePayModal() {
    payModal.value = false;
    payments.value = [];
}
function addPayment() {
    const amount = parseFloat(newPmtAmountBs.value);
    if (!newPmtMethodId.value || !amount || amount <= 0) return;
    payments.value.push({
        payment_method_id: newPmtMethodId.value,
        amount_bs:         amount,
        reference:         newPmtReference.value.trim() || null,
    });
    const rest = payRestBs.value;
    newPmtAmountBs.value  = rest > 0 ? exactBs(rest) : '';
    newPmtReference.value = '';
    newPmtMethodId.value  = props.paymentMethods[0]?.id ?? null;
}
function removePayment(idx) {
    payments.value.splice(idx, 1);
    const rest = payRestBs.value;
    newPmtAmountBs.value = rest > 0 ? exactBs(rest) : exactBs(payTotalBs.value);
}
function pmtMethodName(id) {
    return props.paymentMethods.find(p => p.id === id)?.name ?? id;
}

// ─── Cliente opcional ─────────────────────────────────────────────────────────
const showClientFields  = ref(false);
const clientId          = ref(null);
const clientName        = ref('');
const clientPhone       = ref('');
const clientSearch      = ref('');
const clientResults     = ref([]);
const clientSearchOpen  = ref(false);
let   clientDebounce    = null;

function onClientInput() {
    clientId.value = null;
    clearTimeout(clientDebounce);
    if (clientSearch.value.length < 3) { clientResults.value = []; clientSearchOpen.value = false; return; }
    clientDebounce = setTimeout(() => {
        window.axios.get(route('clients.search'), { params: { q: clientSearch.value } })
            .then(({ data }) => { clientResults.value = data; clientSearchOpen.value = data.length > 0; })
            .catch(() => {});
    }, 300);
}
function selectClient(c) {
    clientId.value         = c.id;
    clientName.value       = c.name;
    clientPhone.value      = c.phone ?? '';
    clientSearch.value     = c.name;
    clientSearchOpen.value = false;
    clientResults.value    = [];
}
function clearClient() {
    clientId.value         = null;
    clientName.value       = '';
    clientPhone.value      = '';
    clientSearch.value     = '';
    clientSearchOpen.value = false;
    clientResults.value    = [];
}

const showQuickClientModal = ref(false);
const quickClientForm      = ref({ name: '', phone: '', email: '' });
const quickClientSaving    = ref(false);

function openQuickClient() { quickClientForm.value = { name: clientSearch.value, phone: '', email: '' }; showQuickClientModal.value = true; }
function closeQuickClient() { showQuickClientModal.value = false; }
function submitQuickClient() {
    if (quickClientSaving.value) return;
    quickClientSaving.value = true;
    window.axios.post(route('clients.store'), quickClientForm.value)
        .then(({ data }) => { if (data.client) selectClient(data.client); closeQuickClient(); })
        .catch(() => {})
        .finally(() => { quickClientSaving.value = false; });
}

// ─── Modal éxito ──────────────────────────────────────────────────────────────
const successModal    = ref(false);
const successTicket   = ref('');
const successOrigin   = ref('');   // snapshot del origen al momento de la venta
const successTotal    = ref(0);
const successItems    = ref([]);   // snapshot de ítems antes de limpiar carrito
const successPayments = ref([]);   // snapshot de pagos
const successDate     = ref('');   // fecha/hora de la venta
const successClient   = ref({ name: '', phone: '' });  // snapshot del cliente

// payCanConfirm depende del origen: delivery/credit no necesitan pagos
const payCanConfirm = computed(() => {
    if (saleOrigin.value === 'delivery' || saleOrigin.value === 'credit') {
        return clientName.value.trim().length > 0;
    }
    return payments.value.length > 0 && payRestBs.value <= 0;
});

function confirmPay() {
    if (!payCanConfirm.value || paying.value) return;
    paying.value = true;

    const items = cart.value.map(i => ({
        product_id: i.product_id,
        input_type: i.input_type,
        ...(i.input_type === 'weight' && i.amount_bs
            ? { amount_bs: i.amount_bs }
            : { quantity_value: i.quantity_value }),
    }));

    // ── Delivery: crea pedido pendiente, sin cobro inmediato ─────────────────
    if (saleOrigin.value === 'delivery') {
        window.axios.post(route('sales.store'), { items, origin: 'delivery' })
            .then(({ data }) => {
                if (!data.sale) throw new Error('Sin venta');
                successTicket.value   = data.sale.ticket_number;
                successOrigin.value   = 'delivery';
                successTotal.value    = cartTotalBs.value;
                successItems.value    = cart.value.map(i => ({ ...i, subtotal_bs: i.input_type === 'weight' ? (i.amount_bs ?? 0) : i.subtotal_usd * props.todayRate }));
                successPayments.value = [];
                successDate.value     = new Date().toLocaleString('es-VE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                successClient.value   = { name: clientName.value.trim(), phone: clientPhone.value.trim() };
                closePayModal();
                successModal.value = true;
                tickets.value[activeTicket.value].items = [];
                try {
                    sessionStorage.setItem('pos_tickets', JSON.stringify(tickets.value))
                } catch (e) {}
            })
            .catch((err) => { alert(err?.response?.data?.error ?? err?.response?.data?.message ?? err?.message ?? 'Error al registrar el delivery.'); })
            .finally(() => { paying.value = false; });
        return;
    }

    // ── Crédito: despacha sin cobro, payment_status=pendiente_cobro ──────────
    if (saleOrigin.value === 'credit') {
        window.axios.post(route('sales.store'), {
            items,
            origin: 'credit',
            client_name:  clientName.value.trim() || null,
            client_phone: clientPhone.value.trim() || null,
        })
            .then(({ data }) => {
                if (!data.sale) throw new Error('Sin venta');
                successTicket.value   = data.sale.ticket_number;
                successOrigin.value   = 'credit';
                successTotal.value    = cartTotalBs.value;
                successItems.value    = cart.value.map(i => ({ ...i, subtotal_bs: i.input_type === 'weight' ? (i.amount_bs ?? 0) : i.subtotal_usd * props.todayRate }));
                successPayments.value = [];
                successDate.value     = new Date().toLocaleString('es-VE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                successClient.value   = { name: clientName.value.trim(), phone: clientPhone.value.trim() };
                closePayModal();
                successModal.value = true;
                tickets.value[activeTicket.value].items = [];
                try {
                    sessionStorage.setItem('pos_tickets', JSON.stringify(tickets.value))
                } catch (e) {}
            })
            .catch((err) => { alert(err?.response?.data?.error ?? err?.response?.data?.message ?? err?.message ?? 'Error al registrar el crédito.'); })
            .finally(() => { paying.value = false; });
        return;
    }

    // ── En Sitio: flujo estándar store → pay ────────────────────────────────
    window.axios.post(route('sales.store'), { items, origin: 'onsite' })
    .then(({ data }) => {
        if (!data.sale) throw new Error('Sin venta');
        const saleId       = data.sale.id;
        const ticketNumber = data.sale.ticket_number;
        return window.axios.patch(route('sales.pay', { sale: saleId }), {
            payments:     payments.value,
            client_id:    clientId.value,
            client_name:  clientName.value.trim() || null,
            client_phone: clientPhone.value.trim() || null,
        }).then(() => ticketNumber);
    })
    .then((ticket) => {
        successTicket.value   = ticket;
        successOrigin.value   = 'onsite';
        successTotal.value    = cartTotalBs.value;
        successItems.value    = cart.value.map(i => ({ ...i, subtotal_bs: i.input_type === 'weight' ? (i.amount_bs ?? 0) : i.subtotal_usd * props.todayRate }));
        successPayments.value = payments.value.map(p => ({
            ...p,
            method_label: pmtMethodName(p.payment_method_id),
        }));
        successDate.value     = new Date().toLocaleString('es-VE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        successClient.value   = { name: clientName.value.trim(), phone: clientPhone.value.trim() };
        closePayModal();
        successModal.value = true;
        tickets.value[activeTicket.value].items = [];
        try {
            sessionStorage.setItem('pos_tickets', JSON.stringify(tickets.value))
        } catch (e) {}
    })
    .catch((err) => { alert(err?.response?.data?.error ?? err?.response?.data?.message ?? err?.message ?? 'Error al procesar el pago. Intente nuevamente.'); })
    .finally(() => { paying.value = false; });
}

function newSale() {
    successModal.value    = false;
    successTicket.value   = '';
    successOrigin.value   = '';
    successItems.value    = [];
    successPayments.value = [];
}

function printTicket() {
    const biz   = props.businessInfo
    const name  = (biz.name  || 'Mi Negocio').toUpperCase()
    const addr  = [biz.address, biz.city, biz.state].filter(Boolean).join(', ')
    const phone = biz.phone || ''

    const itemRows = successItems.value.map(i => {
        const qty = i.sale_mode === 'weight'
            ? fmtQty(i.quantity_value, 'weight')
            : `${Math.round(i.quantity_value)} u.`
        return `<tr>
            <td class="t-name">${i.product_name}</td>
            <td class="t-qty">${qty}</td>
            <td class="t-amt">${fmtBs(i.subtotal_bs)}</td>
        </tr>`
    }).join('')

    const payRows = successPayments.value.map(p =>
        `<tr><td colspan="2" class="t-pay-lbl">${p.method_label || p.method || '—'}</td><td class="t-amt">${fmtBs(p.amount_bs ?? 0)}</td></tr>`
    ).join('')

    const footer = biz.ticket_footer
        ? `<p class="t-footer">${biz.ticket_footer}</p>`
        : ''

    const originBadge = successOrigin.value === 'delivery'
        ? `<p class="t-origin">DELIVERY</p>`
        : successOrigin.value === 'credit'
            ? `<p class="t-origin">CRÉDITO</p>`
            : ''

    const html = `<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ticket ${successTicket.value}</title>
<style>
  @page { size: 80mm auto; margin: 4mm 3mm; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Courier New', Courier, monospace; font-size: 10pt; color: #000; width: 74mm; }
  .t-biz  { text-align: center; font-weight: bold; font-size: 12pt; margin-bottom: 1mm; }
  .t-sub  { text-align: center; font-size: 8.5pt; margin-bottom: 0.5mm; }
  .t-meta { text-align: center; font-size: 8.5pt; margin: 2mm 0; }
  .t-sep  { border: none; border-top: 1px dashed #000; margin: 2mm 0; }
  table   { width: 100%; border-collapse: collapse; }
  th      { font-size: 8pt; text-align: left; border-bottom: 1px solid #000; padding-bottom: 1mm; }
  th.t-amt, td.t-amt { text-align: right; }
  th.t-qty, td.t-qty { text-align: center; width: 16mm; }
  td      { font-size: 9pt; padding: 1mm 0; vertical-align: top; }
  td.t-name { max-width: 35mm; word-break: break-word; }
  .t-total-row td { font-weight: bold; font-size: 11pt; border-top: 1px solid #000; padding-top: 2mm; }
  .t-pay-lbl { font-size: 8.5pt; color: #333; }
  .t-footer { text-align: center; font-size: 8pt; margin-top: 3mm; }
  .t-thanks  { text-align: center; font-size: 9pt; font-weight: bold; margin-top: 3mm; }
  .t-origin  { text-align: center; font-weight: bold; font-size: 9pt; letter-spacing: 0.5px; margin: 1mm 0; }
</style>
</head>
<body>
  <p class="t-biz">${name}</p>
  ${addr ? `<p class="t-sub">${addr}</p>` : ''}
  ${phone ? `<p class="t-sub">Tel: ${phone}</p>` : ''}
  <hr class="t-sep">
  <p class="t-meta">Ticket: <strong>${successTicket.value}</strong></p>
  ${originBadge}
  <p class="t-meta">${successDate.value}</p>
  ${successClient.value?.name ? `<p class="t-meta">Cliente: ${successClient.value.name}</p>` : ''}
  <hr class="t-sep">
  <table>
    <thead><tr><th>Producto</th><th class="t-qty">Cant.</th><th class="t-amt">Monto</th></tr></thead>
    <tbody>${itemRows}</tbody>
  </table>
  <hr class="t-sep">
  <table>
    <tbody>
      <tr class="t-total-row"><td colspan="2">TOTAL Bs.</td><td class="t-amt">${fmtBs(successTotal.value)}</td></tr>
      ${payRows}
    </tbody>
  </table>
  ${footer}
  <p class="t-thanks">¡Gracias por su compra!</p>
</body>
</html>`

    const win = window.open('', '_blank', 'width=320,height=600')
    win.document.write(html)
    win.document.close()
    win.focus()
    win.print()
    win.onafterprint = () => win.close()
}

function clearCart() { tickets.value[activeTicket.value].items = []; }

// ─── Stock helpers ────────────────────────────────────────────────────────────
function stockFor(product) { return props.stockMap[product.id] ?? null; }
function stockStatus(product) {
    const s = stockFor(product);
    if (s === null) return 'ok';
    if (s <= 0)     return 'empty';
    if (product.min_stock && s < product.min_stock) return 'low';
    return 'ok';
}

const negativeStockItems = computed(() =>
    cart.value.filter(item => {
        if (item.input_type !== 'weight') return false;
        const available = props.stockMap[item.product_id] ?? 0;
        return item.quantity_value > available;
    })
);
const showNegativeWarning = ref(false);

// ─── Imagen de producto ───────────────────────────────────────────────────────
function productImageUrl(product) {
    if (!product.image_path) return null;
    if (product.image_path.startsWith('http')) return product.image_path;
    return '/storage/' + product.image_path;
}

// ─── Scanner EAN-13 ──────────────────────────────────────────────────────────
// Detecta input de scanner por burst: caracteres < 50 ms entre sí + Enter final.
// EAN-13 prefijo '2': pos 2-6 = PLU/SKU (5 dígitos), pos 7-11 = precio Bs (÷100).
const scannerBuffer    = ref('')
const scannerLastTime  = ref(0)
const scannerFired     = ref(false)
const SCANNER_BURST_MS = 400

function onSearchKeydown(e) {
    if (qtyModal.value) return   // el numpad físico ya lo maneja onPhysKeyDown

    const now  = Date.now()
    const diff = scannerLastTime.value ? now - scannerLastTime.value : 9999

    if (e.key === 'Enter') {
        const buf = scannerBuffer.value
        scannerBuffer.value   = ''
        scannerLastTime.value = 0
        if (buf.length === 13 && /^\d{13}$/.test(buf)) {
            e.preventDefault()
            handleScan(buf)
            search.value = ''
        }
        return
    }

    if (e.key.length === 1 && /\d/.test(e.key)) {
        scannerBuffer.value   = diff < SCANNER_BURST_MS ? scannerBuffer.value + e.key : e.key
        scannerLastTime.value = now
    } else {
        // Tecla no numérica → no es burst de scanner, resetear buffer
        scannerBuffer.value   = ''
        scannerLastTime.value = 0
    }
}


// ─── Scanner global — multi-estándar EAN-13 / Code128 ────────────────────────
let scanBuffer = ''
let scanTimer  = null

function onKeyDown(e) {
    // Ceder si un input está enfocado (onSearchKeydown lo maneja)
    const tag = document.activeElement?.tagName
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return
    // Ceder si el numpad modal está abierto
    if (qtyModal.value) return

    if (e.key === 'Enter' && scanBuffer.length > 3) {
        handleScan(scanBuffer)
        scanBuffer = ''
        return
    }
    if (e.key.length === 1) {
        scanBuffer += e.key
        clearTimeout(scanTimer)
        scanTimer = setTimeout(() => { scanBuffer = '' }, 300)
    }
}

function parseBarcodeScale(code) {
    const raw = code.trim()

    // EAN-13 (13 dígitos)
    if (/^\d{13}$/.test(raw)) {
        const prefix = parseInt(raw.substring(0, 2))

        // Prefijo 20-29 = interno de báscula
        if (prefix >= 20 && prefix <= 29) {
            const productCode = raw.substring(2, 7)  // 5 dígitos producto
            const valueRaw    = raw.substring(7, 12) // 5 dígitos valor

            const asPriceBs  = parseInt(valueRaw)
            const asWeightKg = parseInt(valueRaw) / 1000  // kg con 3 decimales

            return { type: 'scale_ean13', productCode, priceBs: asPriceBs, weightKg: asWeightKg, raw }
        }

        // EAN-13 estándar — buscar por barcode en productos
        return { type: 'ean13_standard', barcode: raw, raw }
    }

    // Code128 o cualquier otro — buscar directo por barcode
    return { type: 'direct', barcode: raw, raw }
}

function handleScan(code) {
    // EAN-13 báscula (prefijo 20-29) en modo PESO: pos 2-6 = PLU, pos 7-11 = gramos.
    // Producto, peso y monto entran al carrito AUTOMÁTICAMENTE, sin intervención.
    if (code.length === 13 && parseInt(code.substring(0, 2)) >= 20 && parseInt(code.substring(0, 2)) <= 29) {
        const plu       = code.slice(2, 7)
        const weightRaw = code.slice(7, 12)
        const kg        = parseInt(weightRaw, 10) / 1000
        const product   = props.products.find(p => p.barcode && parseInt(p.barcode, 10) === parseInt(plu, 10))
        if (product) {
            const priceBsKg = (product.price_per_kg_usd ?? 0) * (props.todayRate ?? 1)
            const totalBs   = Math.round(kg * priceBsKg * 100) / 100
            addToCartFromScanner(product, totalBs)
            showScanFeedback(product.name, kg)
        }
        return
    }

    const parsed = parseBarcodeScale(code)

    if (parsed.type === 'scale_ean13') {
        const product = props.products.find(p =>
            p.barcode === parsed.productCode ||
            String(p.id).padStart(5, '0') === parsed.productCode
        )
        if (product) {
            const isWeight  = product.sale_mode === 'weight'
            const priceBsKg = isWeight ? parseFloat(product.price_per_kg_usd || 0) * props.todayRate : 0
            const amountBs  = isWeight && priceBsKg > 0
                ? parsed.weightKg * priceBsKg
                : parseFloat(product.price_per_unit_usd || 0) * props.todayRate
            addToCartFromScanner(product, amountBs)
            showScanFeedback(product.name, isWeight ? parsed.weightKg : 1)
        }
    } else {
        const product = props.products.find(p => p.barcode === parsed.barcode)
        if (product) {
            const amountBs = parseFloat(
                product.sale_mode === 'weight'
                    ? (product.price_per_kg_usd || 0) * props.todayRate
                    : (product.price_per_unit_usd || 0) * props.todayRate
            )
            addToCartFromScanner(product, amountBs)
            showScanFeedback(product.name, 1)
        }
    }
}

function showScanFeedback(name, qty) {
    scanFeedback.value = { name, qty }
    setTimeout(() => { scanFeedback.value = null }, 1500)
}

function addToCartFromScanner(product, amountBs) {
    const isWeight  = product.sale_mode === 'weight'
    const priceBsKg = isWeight
        ? parseFloat(product.price_per_kg_usd || 0) * props.todayRate
        : 0
    const quantityValue = isWeight && priceBsKg > 0
        ? Math.round((amountBs / priceBsKg) * 1000) / 1000
        : 1

    const subtotalUsd = isWeight
    ? parseFloat((amountBs / props.todayRate).toFixed(2))
    : parseFloat((parseFloat(product.price_per_unit_usd || 0) * quantityValue).toFixed(2))

    const existing = cart.value.find(i => i.product_id === product.id)
    if (existing) {
        existing.quantity_value += quantityValue
        if (isWeight) existing.amount_bs = (existing.amount_bs ?? 0) + amountBs
        existing.subtotal_usd = parseFloat(
            ((isWeight
                ? parseFloat(product.price_per_kg_usd  || 0)
                : parseFloat(product.price_per_unit_usd || 0)) * existing.quantity_value
            ).toFixed(2)
        )
    } else {
        cart.value.push({
            product_id:         product.id,
            product_name:       product.name,
            input_type:         isWeight ? 'weight' : 'unit',
            sale_mode:          product.sale_mode,
            quantity_value:     quantityValue,
            amount_bs:          isWeight ? amountBs : null,
            price_per_kg_usd:   parseFloat(product.price_per_kg_usd  || 0),
            price_per_unit_usd: parseFloat(product.price_per_unit_usd || 0),
            subtotal_usd:       subtotalUsd,
        })
    }

    justAdded.value    = product.id
    scannerFired.value = true
    setTimeout(() => {
        justAdded.value    = null
        scannerFired.value = false
    }, 2000)
}

// ─── Ayuda ────────────────────────────────────────────────────────────────────
const showHelp = ref(false);

const helpSteps = [
    {
        title: 'Seleccionar producto',
        body: 'Toca el producto en la vitrina. El sistema lo agrega al carrito.',
        tip: 'Usa "Solo con stock" para ver solo lo disponible hoy.',
    },
    {
        title: 'Ingresar monto en Bs.',
        body: 'Escribe cuánto te da el cliente en bolívares. El sistema calcula los kg automáticamente. Tú no haces ninguna resta.',
        tip: 'El sistema usa la tasa BCV del día. No necesitas calcular nada.',
    },
    {
        title: 'Seleccionar método de pago',
        body: 'Elige cómo pagó el cliente: efectivo, Pago Móvil, punto, etc.',
        tip: 'Puedes dividir el pago en varios métodos si el cliente paga mixto.',
    },
    {
        title: 'Confirmar venta',
        body: 'Presiona "Cobrar". El sistema genera el ticket automáticamente.',
        tip: 'El ticket muestra el total en Bs. y el desglose del producto.',
    },
    {
        title: 'Imprimir o cerrar',
        body: 'Imprime el ticket o ciérralo. El stock se descuenta en ese momento.',
        tip: 'Si cierras sin imprimir, la venta ya quedó registrada igual.',
    },
];

const helpFaqs = [
    {
        q: '¿Qué pasa si me equivoco en el monto?',
        a: 'Borra el ítem del carrito y vuélvelo a agregar.',
    },
    {
        q: '¿Puedo vender a crédito?',
        a: 'Sí, selecciona "Crédito" al momento de cobrar.',
    },
    {
        q: '¿Puedo hacer delivery desde aquí?',
        a: 'Sí, selecciona "Delivery" en el tipo de venta.',
    },
    {
        q: '¿El stock se descuenta al instante?',
        a: 'Sí, en el momento que confirmas el cobro.',
    },
    {
        q: '¿Qué es el toggle "Solo con stock"?',
        a: 'Filtra y muestra solo productos que tienen kg disponibles hoy.',
    },
];
</script>

<template>
    <div class="pos-root">

        <!-- ── Selector de caja: varias abiertas en la sucursal ── -->
        <div v-if="!cashRegister && openRegisters.length > 1" class="pos-caja-lock">
            <div class="pos-caja-lock__card">
                <div class="pos-caja-lock__icon-wrap">
                    <Store :size="48" stroke-width="1.5" style="color: var(--brand)" />
                </div>
                <div class="pos-caja-lock__text">
                    <h2 class="pos-caja-lock__title">Elige tu caja</h2>
                    <p class="pos-caja-lock__sub">Hay varias cajas abiertas en esta sucursal. Selecciona en cuál vas a cobrar.</p>
                </div>
                <div class="pos-caja-select">
                    <button
                        v-for="r in openRegisters"
                        :key="r.id"
                        class="pos-caja-select__btn"
                        :disabled="selectingCaja"
                        @click="selectCaja(r.id)"
                    >{{ r.name }}</button>
                </div>
                <div class="pos-caja-lock__actions">
                    <a :href="route('dashboard')" class="pos-caja-lock__btn-ghost">← Dashboard</a>
                </div>
            </div>
        </div>

        <!-- ── Pantalla de bloqueo: sin caja abierta ── -->
        <div v-else-if="!cashRegister" class="pos-caja-lock">
            <div class="pos-caja-lock__card">
                <div class="pos-caja-lock__icon-wrap">
                    <Lock :size="48" stroke-width="1.5" style="color: var(--brand)" />
                </div>
                <div class="pos-caja-lock__text">
                    <h2 class="pos-caja-lock__title">Caja cerrada</h2>
                    <p class="pos-caja-lock__sub">Abre tu caja registradora para habilitar el punto de venta.</p>
                </div>
                <div class="pos-caja-lock__actions">
                    <a :href="route('cash.index')" class="pos-caja-lock__btn-primary">Abrir Caja →</a>
                    <a :href="route('dashboard')" class="pos-caja-lock__btn-ghost">← Dashboard</a>
                </div>
            </div>
        </div>

        <!-- ── HEADER ── -->
        <header v-if="cashRegister" class="pos-header">

            <!-- Izquierda: back + logo -->
            <div class="hd-left">
                <a :href="route('dashboard')" class="nav-back" title="Volver al tablero">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                        <path d="M9 15l-5-5 5-5M4 10h12"/>
                    </svg>
                </a>
                <div class="pos-logo">
                    <AppLogo :dark="true" :size="28" />
                    <span class="logo-text"><span class="logo-synti">SYNTI</span><span class="logo-meat">meat</span></span>
                </div>
            </div>

            <div class="sep" />

            <!-- Centro: tasa + tabs -->
            <div class="hd-center">
                <div class="rate-badge">
                    <span class="rate-dot" />
                    <span class="rate-lbl">Tasa</span>
                    <span class="rate-val">{{ fmtBs(todayRate) }} Bs/$</span>
                </div>

                <div class="sep hd-sep-tabs" />

                <!-- Ticket tabs -->
                <div class="header-tabs">
                    <button
                        v-for="(t, idx) in tickets"
                        :key="t.id"
                        class="h-ticket-tab"
                        :class="{ active: activeTicket === idx }"
                        @click="activeTicket = idx"
                    >
                        {{ t.label }}
                        <span v-if="tickets[idx].items.length" class="h-tab-badge">{{ tickets[idx].items.length }}</span>
                        <span v-if="tickets.length > 1" class="h-tab-close" @click.stop="removeTicket(idx)">×</span>
                    </button>
                    <button class="h-ticket-add" :disabled="tickets.length >= 5" @click="addTicket" title="Nuevo ticket">+</button>
                </div>
            </div>

            <!-- Derecha: estado + hora + usuario -->
            <div class="hd-right">
                <span v-if="!cashRegister" class="no-caja-pill">Sin caja</span>
                <span class="pos-time">{{ currentTime }}</span>
                <button class="pos-help-fab" @click="showHelp = true" title="Ayuda">?</button>
                <div class="user-av" :title="authUser?.name ?? 'Usuario'">{{ (authUser?.name ?? 'U')[0].toUpperCase() }}</div>
            </div>

        </header>

        <!-- ── MAIN ── -->
        <div v-if="cashRegister" class="pos-main">

            <!-- Productos -->
            <section class="pnl-products">

                <div class="tabs-outer">
                    <button v-if="tabArrowLeft" class="tab-arrow tab-arrow--left" @click="scrollTabsLeft" tabindex="-1">
                        <ChevronLeft :size="15" />
                    </button>

                    <div class="tabs-wrap" ref="tabsWrapRef" @scroll="updateTabArrows">
                        <div class="tabs">
                            <button
                                class="tab tab-favorites"
                                :class="{ active: selectedCat === 'favorites' }"
                                :style="selectedCat === 'favorites' ? { color: '#f59e0b', borderBottomColor: '#f59e0b' } : {}"
                                @click="selectCat('favorites')"
                            >⭐ Favoritos</button>
                            <button
                                class="tab"
                                :class="{ active: selectedCat === null }"
                                :style="selectedCat === null ? { color: 'var(--brand)', borderBottomColor: 'var(--brand)' } : {}"
                                @click="selectCat(null)"
                            >Todas</button>
                            <button
                                v-for="cat in categories.filter(c => c.name !== 'Bóveda' && c.macro_category !== 'BOVEDA')"
                                :key="cat.id"
                                class="tab"
                                :class="{ active: selectedCat === cat.id }"
                                :style="selectedCat === cat.id ? { color: cat.color, borderBottomColor: cat.color } : {}"
                                @click="selectCat(cat.id)"
                            >
                                <span class="tab-dot" :style="{ background: cat.color }" />
                                {{ cat.name }}
                            </button>
                        </div>
                    </div>

                    <button v-if="tabArrowRight" class="tab-arrow tab-arrow--right" @click="scrollTabsRight" tabindex="-1">
                        <ChevronRight :size="15" />
                    </button>

                    <div v-show="tabArrowLeft"  class="tab-fade tab-fade--left"  aria-hidden="true"></div>
                    <div v-show="tabArrowRight" class="tab-fade tab-fade--right" aria-hidden="true"></div>
                </div>

                <!-- Búsqueda + toggle -->
                <div class="search-row">
                    <div class="search-wrap">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                        </svg>
                        <input
                            v-model="search"
                            type="search"
                            class="search-input"
                            placeholder="Busca un producto para agregarlo al ticket…"
                            autocomplete="off"
                            @keydown="onSearchKeydown"
                        />
                        <button v-if="search" class="search-clear" @click="search = ''" title="Limpiar">×</button>
                    </div>

                    <!-- Pill toggle -->
                    <div class="stk-toggle" @click="soloConStock = !soloConStock" :title="soloConStock ? 'Ver todos' : 'Solo con stock'">
                        <span class="stk-label">Solo con stock</span>
                        <div class="stk-pill" :class="{ 'stk-pill--on': soloConStock }">
                            <div class="stk-thumb" />
                        </div>
                    </div>
                </div>

                <!-- Scanner feedback ─────────────────────────────────────── -->
                <Transition name="scanner-toast">
                    <div v-if="scanFeedback" class="scanner-toast scanner-toast--rich">
                        <Check :size="14" />
                        <span class="scanner-toast__name">{{ scanFeedback.name }}</span>
                        <span class="scanner-toast__qty">{{ scanFeedback.qty }} {{ scanFeedback.qty !== 1 ? 'kg' : 'und' }}</span>
                    </div>
                    <div v-else-if="scannerFired" class="scanner-toast">
                        <Check :size="14" />
                        Escaneado
                    </div>
                </Transition>

                <div class="grid-wrap">
                    <!-- ── Alerta de corte bancario ── -->
                    <Transition name="banking-alert">
                        <div v-if="activeBankingAlert" class="banking-banner" role="alert" aria-live="assertive">
                            <Bell :size="18" class="banking-banner__icon" />
                            <span class="banking-banner__msg">{{ activeBankingAlert }}</span>
                            <button class="banking-banner__close" @click="dismissBankingAlert" aria-label="Cerrar alerta">
                                <X :size="16" />
                            </button>
                        </div>
                    </Transition>

                    <!-- Contador solo cuando hay búsqueda activa -->
                    <p v-if="search.trim()" class="search-count">
                        {{ searchResults.length }} resultado{{ searchResults.length !== 1 ? 's' : '' }}
                        <span v-if="searchResults.length === 20"> (máx. 20)</span>
                    </p>

                    <div class="product-grid" :key="`${selectedCat}-${catPage}`">
                        <button
                            v-for="(product, i) in displayedProducts"
                            :key="product.id"
                            class="product-card"
                            :class="{ 'product-card--no-stock': !hasStock(product) }"
                            :style="{
                                borderTopColor: catColor(product),
                                '--glow': catColor(product) + '55',
                                animationDelay: `${i * 35}ms`,
                            }"
                            @click="openQtyModal(product)"
                        >
                            <!-- Zona imagen -->
                            <div class="p-img-zone" :style="{ '--cat-color': catColor(product) }">
                                <img
                                    v-if="productImageUrl(product)"
                                    :src="productImageUrl(product)"
                                    :alt="product.name"
                                    class="p-img"
                                />
                                <div v-else class="p-img-ph">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" width="28" height="28" opacity="0.3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                </div>
                                <span class="p-cat-pill" :style="{ background: catColor(product) + '22', color: catColor(product) }">
                                    {{ product.subcategory?.name ?? product.category?.name ?? '' }}
                                </span>
                                <span v-if="stockStatus(product) === 'empty'" class="p-stock-pill p-stock-empty">Sin stock</span>
                                <span v-else-if="stockStatus(product) === 'low'" class="p-stock-pill p-stock-low">Stock bajo</span>
                            </div>

                            <!-- Info -->
                            <div class="p-info">
                                <div class="p-name">{{ product.name }}</div>
                                <div class="p-bs">
                                    {{ fmtUsd(product.sale_mode === 'weight' ? product.price_per_kg_usd : product.price_per_unit_usd) }}<span class="p-bs-unit"> / {{ product.sale_mode === 'weight' ? 'kg' : 'und' }}</span>
                                </div>
                                <div class="p-usd">
                                    {{ fmtBs(priceBs(product)) }} Bs/{{ product.sale_mode === 'weight' ? 'kg' : 'und' }}
                                </div>
                            </div>
                        </button>

                        <p v-if="!displayedProducts.length" class="empty-msg">Sin resultados.</p>
                    </div>

                    <!-- Cargar más (solo modo categoría) -->
                    <div v-if="hasMoreCategory && search.trim().length < 2" class="load-more-wrap">
                        <button class="btn-load-more" @click="loadMore">
                            Cargar más ({{ categoryProductsAll.length - categoryProducts.length }} restantes)
                        </button>
                    </div>
                </div>
            </section>

            <!-- Carrito -->
            <aside class="pnl-cart">
                <div class="cart-head">
                    <span class="cart-title">Ticket</span>
                    <span :class="['cart-badge', { has: cart.length > 0 }]">
                        {{ cart.length }} {{ cart.length === 1 ? 'producto' : 'productos' }}
                    </span>
                </div>

                <div class="cart-items-list">
                    <div v-if="!cart.length" class="cart-empty">
                        <div class="empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <p class="empty-txt">Ticket vacío<br>Toca un producto para agregar</p>
                    </div>

                    <div
                        v-for="(item, idx) in cart"
                        :key="item.product_id"
                        :class="['ci', { 'ci-new': justAdded === item.product_id }]"
                    >
                        <div class="ci-info">
                            <div class="ci-name">{{ item.product_name }}</div>
                            <div class="ci-dual">
                                <span class="ci-qty">{{ fmtQty(item.quantity_value, item.sale_mode) }}</span>
                                <span class="ci-dot">·</span>
                                <span class="ci-sub">{{ item.amount_bs ? fmtBs(item.amount_bs) : fmtBs(item.subtotal_usd * todayRate) }} Bs.</span>
                                <span class="ci-dot">·</span>
                                <span class="ci-sub ci-sub-usd">{{ fmtUsd(item.subtotal_usd) }}</span>
                            </div>
                        </div>
                        <button class="ci-rm" @click.stop="removeFromCart(idx)">×</button>
                    </div>
                </div>

                <div class="cart-footer">
                    <div class="total-area">
                        <div class="total-meta">
                            <span class="total-lbl">Total</span>
                            <span class="total-usd">{{ fmtUsd(cartTotalUsd) }} USD</span>
                        </div>
                        <div class="total-main">
                            <span class="total-bs">{{ fmtBs(cartTotalBs) }}</span>
                            <span class="total-curr">Bs.</span>
                        </div>
                    </div>
                    <button class="btn-cobrar" :disabled="!cart.length" @click="openPayModal">
                        Cobrar · {{ fmtUsd(cartTotalUsd) }} USD
                    </button>
                    <button v-if="cart.length" class="btn-clear-cart" @click="clearCart">Limpiar ticket</button>
                </div>
            </aside>
        </div>

        <!-- FAB mobile -->
        <button class="cart-fab" @click="showMobileCart = true" :class="{ 'cart-fab--has': cart.length > 0 }">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            <span>{{ cart.length > 0 ? cart.length + ' ítem' + (cart.length > 1 ? 's' : '') : 'Carrito' }}</span>
        </button>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!-- MODALES                                                           -->
        <!-- ══════════════════════════════════════════════════════════════════ -->

        <!-- Drawer mobile -->
        <Teleport to="body">
            <Transition name="cart-drawer">
                <div v-if="showMobileCart" class="mobile-cart-overlay">
                    <div class="mobile-cart-drawer">
                        <div class="cart-head">
                            <span class="cart-title">Carrito</span>
                            <div class="rate-badge rate-badge--drawer">
                                <span class="rate-dot" />
                                <span class="rate-val">{{ fmtBs(todayRate) }} Bs/$</span>
                            </div>
                            <button class="drawer-close" @click="showMobileCart = false">×</button>
                        </div>
                        <div class="cart-items-list">
                            <div v-if="!cart.length" class="cart-empty"><p>Sin productos</p></div>
                            <div v-for="(item, idx) in cart" :key="idx" class="ci">
                                <div class="ci-info">
                                    <div class="ci-name">{{ item.product_name }}</div>
                                    <div v-if="posShowKg" class="ci-qty">{{ fmtQty(item.quantity_value, item.sale_mode) }}</div>
                                </div>
                                <div class="ci-sub">{{ fmtBs(item.subtotal_usd * todayRate) }} Bs.</div>
                                <button class="ci-rm" @click="removeFromCart(idx)">×</button>
                            </div>
                        </div>
                        <div class="cart-footer">
                            <div class="total-meta">
                                <span class="total-lbl">Total Bs.</span>
                                <span class="total-bs" style="font-size:1.6rem">{{ fmtBs(cartTotalBs) }}</span>
                            </div>
                            <button class="btn-cobrar" :disabled="!cart.length" @click="() => { showMobileCart = false; openPayModal(); }">Cobrar</button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Modal cantidad -->
        <Teleport to="body">
            <Transition name="mo">
                <div v-if="qtyModal" class="modal-bg">
                    <Transition name="mc" appear>
                        <div v-if="qtyModal" class="modal-card">
                            <div class="modal-top" :style="{ borderTopColor: catColor(qtyProduct) }">
                                <div>
                                    <div class="m-cat" :style="{ color: catColor(qtyProduct) }">
                                        {{ qtyProduct?.subcategory?.name ?? qtyProduct?.category?.name ?? '' }}
                                    </div>
                                    <div class="m-name">{{ qtyProduct?.name }}</div>
                                </div>
                                <button class="close-btn" @click="closeQtyModal">×</button>
                            </div>
                            <div class="modal-body">
                                <!-- Weight: cajero ingresa BOLÍVARES → sistema calcula kg -->
                                <template v-if="qtyProduct?.sale_mode === 'weight'">
                                    <label class="input-lbl">Bolívares a cobrar</label>
                                    <div class="qty-display-box">
                                        <span class="qty-num">{{ qtyInput || '0' }}</span>
                                        <span class="qty-unit-lbl">Bs.</span>
                                    </div>
                                    <div class="calc-box">
                                        <div class="formula">
                                            <span class="fv">{{ fmtBs(parseFloat(qtyInput) || 0) }} Bs.</span>
                                            <span class="fo">÷</span>
                                            <span class="fv">({{ fmtUsd(qtyProduct?.price_per_kg_usd) }}</span>
                                            <span class="fo">×</span>
                                            <span class="fv">{{ fmtBs(todayRate) }} Bs/USD)</span>
                                        </div>
                                        <div class="qty-kg-result">
                                            <span class="fv-kg-lbl">= </span>
                                            <span class="fv-kg">{{ qtyKg.toFixed(3) }} kg</span>
                                        </div>
                                        <div class="m-total-row">
                                            <span class="m-total-lbl">Total</span>
                                            <div class="m-total-main">
                                                <span class="m-total-amt">{{ fmtBs(qtyBsTotal) }}</span>
                                                <span class="m-total-curr">Bs.</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- Unit: sin cambios -->
                                <template v-else>
                                    <label class="input-lbl">Cantidad</label>
                                    <div class="qty-display-box">
                                        <span class="qty-num">{{ qtyInput || '0' }}</span>
                                        <span class="qty-unit-lbl">und</span>
                                    </div>
                                    <div class="calc-box">
                                        <div class="formula">
                                            <span class="fv">{{ qtyInput || '0' }}</span>
                                            <span class="fo">und ×</span>
                                            <span class="fv">{{ fmtUsd(qtyProduct?.price_per_unit_usd) }}</span>
                                            <span class="fo">×</span>
                                            <span class="fv">{{ fmtBs(todayRate) }}</span>
                                            <span class="fo">Bs/USD =</span>
                                        </div>
                                        <div class="m-total-row">
                                            <span class="m-total-lbl">Total</span>
                                            <div class="m-total-main">
                                                <span class="m-total-amt">{{ fmtBs(qtyBsTotal) }}</span>
                                                <span class="m-total-curr">Bs.</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <div v-if="qtyProduct?.sale_mode === 'weight'" class="numpad">
                                    <button v-for="k in ['1','2','3','4','5','6','7','8','9','.','0','←']" :key="k" class="np-key" @click="kbPress(k)">{{ k }}</button>
                                </div>
                                <div v-else class="unit-controls">
                                    <button class="unit-btn" @click="qtyInput = String(Math.max(1, (parseInt(qtyInput)||0) - 1))">−</button>
                                    <input type="number" v-model="qtyInput" min="1" step="1" class="unit-input" />
                                    <button class="unit-btn" @click="qtyInput = String((parseInt(qtyInput)||0) + 1)">+</button>
                                </div>
                                <button class="btn-agregar" :disabled="qtyValue <= 0" @click="addToCart">Agregar al ticket</button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- Modal stock negativo -->
        <Teleport to="body">
            <div v-if="showNegativeWarning" class="modal-bg">
                <div class="modal-box">
                    <div class="modal-header">
                        <h3 style="display:flex;align-items:center;gap:0.4rem;"><AlertTriangle :size="16" stroke-width="2" style="color:var(--amber);flex-shrink:0" /> Advertencia de inventario</h3>
                        <button class="close-btn" @click="showNegativeWarning = false">×</button>
                    </div>
                    <p class="warn-text">Los siguientes productos quedarán con stock negativo:</p>
                    <ul class="warn-list">
                        <li v-for="item in negativeStockItems" :key="item.product_id">
                            <strong>{{ item.product_name }}</strong> — solicitado: {{ fmtQty(item.quantity_value, item.sale_mode) }}, disponible: {{ fmtQty(stockMap[item.product_id] ?? 0, item.sale_mode) }}
                        </li>
                    </ul>
                    <div class="modal-actions">
                        <button class="btn btn-ghost" @click="showNegativeWarning = false">Cancelar</button>
                        <button class="btn btn-warn" @click="launchPayModal">Confirmar de todas formas</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal cobro — 2 columnas -->
        <Teleport to="body">
            <div v-if="payModal" class="modal-bg">
                <div class="pay-modal-2col">

                    <!-- ── Columna izquierda: resumen + cliente ── -->
                    <div class="pay-col pay-col--left">
                        <div class="pay-col-head">
                            <h3 class="pay-col-title">Cobrar</h3>
                            <button class="close-btn" @click="closePayModal">×</button>
                        </div>

                        <!-- Selector de origen -->
                        <div class="origin-selector">
                            <button class="origin-btn" :class="{ active: saleOrigin === 'onsite' }" @click="saleOrigin = 'onsite'">
                                <Store :size="16" class="origin-icon-svg" />
                                <span class="origin-label">En Sitio</span>
                            </button>
                            <button class="origin-btn" :class="{ active: saleOrigin === 'delivery' }" @click="saleOrigin = 'delivery'">
                                <Bike :size="16" class="origin-icon-svg" />
                                <span class="origin-label">Delivery</span>
                            </button>
                            <button class="origin-btn" :class="{ active: saleOrigin === 'credit' }" @click="saleOrigin = 'credit'">
                                <FileText :size="15" stroke-width="1.8" class="origin-icon-svg" />
                                <span class="origin-label">Crédito</span>
                            </button>
                        </div>

                        <!-- Items del carrito -->
                        <div class="pay-items-scroll">
                            <div v-for="(item, idx) in cart" :key="idx" class="pay-item-row">
                                <span class="pay-item-name">{{ item.product_name }} <em class="pay-item-qty">× {{ fmtQty(item.quantity_value, item.sale_mode) }}</em></span>
                                <span class="pay-item-amount">{{ fmtBs(item.input_type === 'weight' ? (item.amount_bs ?? 0) : item.subtotal_usd * todayRate) }} Bs.</span>
                            </div>
                        </div>

                        <!-- Totalizador -->
                        <div class="pay-totalizador">
                            <div class="pay-tot-main">
                                <span>Total a cobrar</span>
                                <span class="pay-total-bs">{{ fmtBs(payTotalBs) }} Bs.</span>
                            </div>
                            <div class="pay-tot-ref">Ref: {{ fmtUsd(cartTotalUsd) }}</div>

                            <div class="pay-tot-lines" v-if="payments.length">
                                <div class="pay-tot-line"><span>Pagado</span><span>{{ fmtBs(payPaidBs) }} Bs.</span></div>
                                <div class="pay-tot-line" :class="payRestBs > 0 ? 'rest-pending' : 'rest-done'">
                                    <span>Restante</span><span>{{ fmtBs(payRestBs) }} Bs.</span>
                                </div>
                                <div v-if="payChangeBs > 0" class="pay-tot-line rest-change">
                                    <span>Vuelto</span><span><strong>{{ fmtBs(payChangeBs) }} Bs.</strong></span>
                                </div>
                            </div>
                        </div>

                        <!-- Cliente -->
                        <div class="client-toggle" @click="showClientFields = !showClientFields">
                            <span>{{ showClientFields ? '▾' : '▸' }} {{ clientId ? clientName : 'Cliente (opcional)' }}</span>
                            <Check v-if="clientId" :size="13" class="client-selected-badge" />
                        </div>
                        <div v-if="showClientFields" class="client-fields">
                            <div class="client-search-wrap">
                                <input v-model="clientSearch" type="text" class="pay-amount-input" placeholder="Buscar cliente…" @input="onClientInput" @blur="clientSearchOpen = false" @focus="clientSearch.length >= 3 && (clientSearchOpen = clientResults.length > 0)" />
                                <button v-if="clientId" class="client-clear-btn" @click="clearClient">×</button>
                                <button class="client-add-btn" @click="openQuickClient">+</button>
                            </div>
                            <div v-if="clientSearchOpen" class="client-dropdown">
                                <div v-for="c in clientResults" :key="c.id" class="client-option" @mousedown.prevent="selectClient(c)">
                                    <span class="client-opt-code">{{ c.client_code }}</span>
                                    <span class="client-opt-name">{{ c.name }}</span>
                                    <span class="client-opt-phone">{{ c.phone ?? '' }}</span>
                                </div>
                            </div>
                            <div v-if="clientId" class="client-selected-row">
                                <span class="client-tag">{{ clientName }}</span>
                                <input v-model="clientPhone" type="tel" class="pay-amount-input" maxlength="30" placeholder="Teléfono (opcional)" style="margin-top:0.4rem;" />
                            </div>
                        </div>
                        <p
                            v-if="(saleOrigin === 'credit' || saleOrigin === 'delivery') && !clientName.trim()"
                            class="client-required-hint"
                        >Requerido para créditos y delivery</p>
                    </div>

                    <!-- ── Columna derecha: métodos de pago ── -->
                    <div class="pay-col pay-col--right">

                        <!-- Aviso para delivery/crédito -->
                        <div v-if="saleOrigin === 'delivery'" class="origin-notice origin-notice--delivery">
                            <Bike :size="20" class="origin-notice-icon" />
                            <div>
                                <strong>Delivery pendiente</strong>
                                <p>El pedido se registrará sin cobro. El cobro se realiza desde la pantalla de Pedidos al confirmar la entrega.</p>
                            </div>
                        </div>
                        <div v-else-if="saleOrigin === 'credit'" class="origin-notice origin-notice--credit">
                            <FileText :size="15" stroke-width="1.8" class="origin-notice-icon" />
                            <div>
                                <strong>Venta a crédito</strong>
                                <p>El producto se despacha ahora. El cobro queda pendiente y aparecerá en la sección "Por Cobrar" de Pedidos.</p>
                            </div>
                        </div>

                        <!-- Pagos (solo onsite) -->
                        <template v-if="saleOrigin === 'onsite'">
                            <p class="pay-section-label">Pagos registrados</p>

                            <div class="multipay-list" v-if="payments.length">
                                <div v-for="(p, idx) in payments" :key="idx" class="multipay-row">
                                    <span class="multipay-method">{{ pmtMethodName(p.payment_method_id) }}</span>
                                    <span v-if="p.reference" class="multipay-ref">{{ p.reference }}</span>
                                    <span class="multipay-amount">{{ fmtBs(p.amount_bs) }} Bs.</span>
                                    <button class="multipay-remove" @click="removePayment(idx)">×</button>
                                </div>
                            </div>
                            <p v-else class="pay-no-payments">Ningún pago añadido aún</p>

                            <div v-if="payRestBs > 0 || !payments.length" class="multipay-add">
                                <p class="pay-section-label" style="margin-top:0.75rem">+ Agregar pago</p>
                                <div class="pay-methods">
                                    <button v-for="pm in paymentMethods" :key="pm.id" class="pay-method-card" :class="{ selected: newPmtMethodId === pm.id }" @click="newPmtMethodId = pm.id">
                                        <span class="pm-name">{{ pm.name }}</span>
                                        <span class="pm-type">{{ pm.type }}</span>
                                    </button>
                                </div>
                                <input v-model="newPmtAmountBs" type="number" class="pay-amount-input" min="0.01" step="0.01" placeholder="Monto Bs." />
                                <input v-if="selectedMethodNeedsRef" v-model="newPmtReference" type="text" class="pay-amount-input" maxlength="50" placeholder="Referencia (opcional)" style="margin-top:0.5rem;" />
                                <button class="btn btn-ghost multipay-btn-add" @click="addPayment">Añadir pago</button>
                            </div>
                        </template>

                        <!-- Acciones -->
                        <div class="pay-col-actions">
                            <button class="btn btn-ghost" @click="closePayModal">Cancelar</button>
                            <button class="btn btn-brand" :disabled="!payCanConfirm || paying" @click="confirmPay">
                                <template v-if="paying">Procesando…</template>
                                <template v-else-if="saleOrigin === 'delivery'">Registrar Delivery</template>
                                <template v-else-if="saleOrigin === 'credit'">Despachar a Crédito</template>
                                <template v-else>Confirmar Pago</template>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal éxito -->
        <Teleport to="body">
            <div v-if="successModal" class="modal-bg">
                <div class="modal-box success-modal">

                    <!-- Encabezado estado -->
                    <div class="sc-status">
                        <div class="sc-check">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <div>
                            <div class="sc-title">¡Venta Confirmada!</div>
                            <div class="sc-subtitle">Pago registrado exitosamente</div>
                        </div>
                    </div>

                    <!-- Mini ticket -->
                    <div class="sc-ticket">
                        <!-- Cabecera negocio -->
                        <div class="sc-biz-name">{{ businessInfo.name }}</div>
                        <div v-if="businessInfo.city || businessInfo.state" class="sc-biz-sub">
                            {{ [businessInfo.city, businessInfo.state].filter(Boolean).join(', ') }}
                        </div>
                        <div v-if="ticketPrefs.show_address && businessInfo.address" class="sc-biz-sub">{{ businessInfo.address }}</div>
                        <div v-if="ticketPrefs.show_phone && businessInfo.phone" class="sc-biz-sub">Tel: {{ businessInfo.phone }}</div>
                        <div class="sc-biz-meta">
                            <span>Ticket #{{ successTicket }}</span>
                            <span>{{ successDate }}</span>
                            <span v-if="cashRegister">{{ cashRegister.name }}</span>
                        </div>
                        <div v-if="successOrigin === 'delivery' || successOrigin === 'credit'" class="sc-origin-badge">
                            <Bike v-if="successOrigin === 'delivery'" :size="16" />
                            <Clock v-if="successOrigin === 'credit'" :size="16" />
                            <span>{{ successOrigin === 'delivery' ? 'DELIVERY' : 'CRÉDITO' }}</span>
                        </div>
                        <div v-if="ticketPrefs.show_client && (successClient.name || successClient.phone)" class="sc-biz-sub" style="margin-top:0.15rem">
                            Cliente: {{ [successClient.name, successClient.phone].filter(Boolean).join(' · ') }}
                        </div>

                        <div class="sc-sep"></div>

                        <!-- Ítems -->
                        <div class="sc-items">
                            <div v-for="item in successItems" :key="item.product_id" class="sc-item">
                                <div class="sc-item-left">
                                    <span class="sc-item-name">{{ item.product_name }}</span>
                                    <span class="sc-item-qty">{{ fmtQty(item.quantity_value, item.sale_mode) }}</span>
                                </div>
                                <div class="sc-item-right">
                                    <span class="sc-item-bs">{{ fmtBs(item.subtotal_bs) }} Bs.</span>
                                    <span v-if="todayRate > 0 && ticketPrefs.show_usd" class="sc-item-usd">$ {{ (item.subtotal_usd ?? item.subtotal_bs / todayRate).toFixed(2) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="sc-sep"></div>

                        <!-- Total -->
                        <div class="sc-total-row">
                            <span class="sc-total-lbl">TOTAL:</span>
                            <span class="sc-total-val">{{ fmtBs(successTotal) }} Bs.</span>
                        </div>
                        <div v-for="p in successPayments" :key="p.method" class="sc-payment-row">
                            Método: {{ p.method_label || p.method || '—' }}
                        </div>

                        <!-- Pie -->
                        <div v-if="businessInfo.ticket_footer" class="sc-footer">
                            {{ businessInfo.ticket_footer }}
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="sc-actions">
                        <button class="btn btn-ghost" @click="newSale">Cerrar</button>
                        <button class="btn btn-print" @click="printTicket">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            Imprimir
                        </button>
                    </div>

                </div>
            </div>
        </Teleport>

        <!-- Modal cliente rápido -->
        <Teleport to="body">
            <div v-if="showQuickClientModal" class="modal-bg">
                <div class="modal-box" style="max-width:400px;">
                    <div class="modal-header">
                        <h3>Nuevo cliente</h3>
                        <button class="close-btn" @click="closeQuickClient">×</button>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:0.65rem;">
                        <input v-model="quickClientForm.name"  type="text"  class="pay-amount-input" placeholder="Nombre *" maxlength="100" />
                        <input v-model="quickClientForm.phone" type="tel"   class="pay-amount-input" placeholder="Teléfono" maxlength="30" />
                        <input v-model="quickClientForm.email" type="email" class="pay-amount-input" placeholder="Email" maxlength="100" />
                    </div>
                    <div class="modal-actions" style="margin-top:1rem;">
                        <button class="btn btn-ghost" @click="closeQuickClient">Cancelar</button>
                        <button class="btn btn-brand" :disabled="!quickClientForm.name || quickClientSaving" @click="submitQuickClient">
                            {{ quickClientSaving ? 'Guardando…' : 'Crear' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Panel de ayuda -->
        <HelpModal
            :show="showHelp"
            title="Punto de Venta — Cómo funciona"
            :steps="helpSteps"
            :faqs="helpFaqs"
            @close="showHelp = false"
        />

    </div>
</template>

<style scoped>
/* ── Local vars ── */
.pos-root {
    --amber:   #F59E0B;
    --green:   #10B981;
    --green-h: #059669;
    --red:     #EF4444;
    --red-a:   rgba(239,68,68,.12);
}

/* ── Layout ── */
.pos-root {
    height: 100vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--bg-base);
    color: var(--text-primary);
    font-family: 'Plus Jakarta Sans', sans-serif;
    -webkit-font-smoothing: antialiased;
    position: relative;
}
.pos-root::before {
    content: '';
    position: fixed; inset: 0; pointer-events: none; z-index: 0;
    background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.022) 1px, transparent 0);
    background-size: 28px 28px;
}
.pos-root::after {
    content: '';
    position: fixed; inset: 0; pointer-events: none; z-index: 0;
    background:
        radial-gradient(ellipse 65% 40% at 12% -8%, rgba(37,99,235,0.11) 0%, transparent 70%),
        radial-gradient(ellipse 45% 35% at 88% 108%, rgba(16,185,129,0.07) 0%, transparent 70%);
}

/* ── Header ── */
.pos-header {
    height: 56px;
    display: flex; align-items: center; gap: 10px;
    padding: 0 14px;
    background: var(--bg-card);
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
    position: relative; z-index: 10;
    overflow: visible;
}

/* Grupos del header */
.hd-left   { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.hd-center { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; overflow: hidden; }
.hd-right  { display: flex; align-items: center; gap: 8px; flex-shrink: 0; margin-left: auto; }

/* Logo — idéntico al sidebar */
.pos-logo      { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.logo-text     { font-size: 1.05rem; font-weight: 800; letter-spacing: -0.3px; white-space: nowrap; line-height: 1; }
.logo-synti    { color: var(--text-primary); }
.logo-meat     { color: #B91C1C; }

/* Back button — siempre visible a la izquierda */
.nav-back {
    flex-shrink: 0; display: flex; align-items: center; justify-content: center;
    min-width: 44px; min-height: 44px; border-radius: 7px;
    border: 1.5px solid var(--border); background: var(--bg-base);
    color: var(--text-secondary); text-decoration: none;
    transition: color 0.15s, border-color 0.15s, background 0.15s;
}
.nav-back:hover { background: var(--hover); border-color: var(--brand); color: var(--brand); }

.sep         { width: 1px; height: 20px; background: var(--border); flex-shrink: 0; }
.hd-sep-tabs { display: flex; }

.rate-badge {
    display: flex; align-items: center; gap: 8px;
    padding: 5px 12px;
    background: rgba(245,158,11,0.10);
    border: 1px solid rgba(245,158,11,0.28);
    border-radius: 6px; font-size: 12px; white-space: nowrap; flex-shrink: 0;
}
.rate-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: var(--amber); box-shadow: 0 0 8px var(--amber); flex-shrink: 0;
    animation: ratePulse 2.5s ease-in-out infinite;
}
@keyframes ratePulse {
    0%, 100% { opacity: 1; box-shadow: 0 0 8px var(--amber); }
    50%       { opacity: 0.4; box-shadow: 0 0 3px var(--amber); }
}
.rate-lbl { color: var(--text-muted); }
.rate-val  { color: var(--amber); font-weight: 700; font-variant-numeric: tabular-nums; }
/* Versión compacta visible en el drawer mobile */
.rate-badge--drawer { font-size: 0.875rem; padding: 3px 8px; margin-left: auto; }

/* Ticket tabs header */
.header-tabs { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
.h-ticket-tab {
    display: flex; align-items: center; gap: 5px;
    padding: 5px 11px;
    border-radius: 6px; border: 1px solid var(--border);
    background: var(--bg-base); color: var(--text-muted);
    font-size: 0.78rem; cursor: pointer; white-space: nowrap;
    font-family: inherit;
    transition: background 0.15s, color 0.15s;
}
.h-ticket-tab.active { background: var(--bg-card); color: var(--text-primary); border-color: var(--brand); }
.h-tab-badge { background: var(--brand); color: #fff; border-radius: 9px; padding: 0 5px; font-size: 0.68rem; }
.h-tab-close { opacity: 0.5; font-size: 0.95rem; margin-left: 2px; }
.h-tab-close:hover { opacity: 1; }
.h-ticket-add {
    padding: 5px 10px; border-radius: 6px; border: 1px dashed var(--border);
    background: transparent; color: var(--text-muted); font-size: 1rem; cursor: pointer;
    font-family: inherit;
}
.h-ticket-add:disabled { opacity: 0.35; cursor: not-allowed; }

.pos-caja-lock {
    position: fixed; inset: 0; z-index: 200;
    background: var(--bg-base);
    display: flex; align-items: center; justify-content: center;
    padding: 1.5rem;
}
.pos-caja-lock__card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 48px;
    max-width: 420px;
    width: 100%;
    display: flex; flex-direction: column; align-items: center; gap: 1.5rem;
}
.pos-caja-lock__icon-wrap { display: flex; align-items: center; justify-content: center; }
.pos-caja-lock__text { text-align: center; display: flex; flex-direction: column; gap: 0.5rem; }
.pos-caja-lock__title { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.pos-caja-lock__sub   { font-size: 0.9rem; color: var(--text-secondary); margin: 0; line-height: 1.6; }
.pos-caja-lock__actions { display: flex; flex-direction: column; align-items: stretch; gap: 0.625rem; width: 100%; }
.pos-caja-lock__btn-primary {
    display: block; width: 100%; text-align: center; box-sizing: border-box;
    background: var(--brand); color: #fff;
    border-radius: 0.5rem; padding: 0.7rem 1.5rem;
    font-size: 0.9375rem; font-weight: 700;
    text-decoration: none; transition: opacity 0.15s;
}
.pos-caja-lock__btn-primary:hover { opacity: 0.85; }
.pos-caja-lock__btn-ghost {
    display: block; width: 100%; text-align: center; box-sizing: border-box;
    border: 1px solid var(--border); border-radius: 0.5rem; padding: 0.7rem 1.5rem;
    font-size: 0.9375rem; font-weight: 500; color: var(--text-secondary);
    text-decoration: none; transition: color 0.15s, border-color 0.15s;
}
.pos-caja-lock__btn-ghost:hover { color: var(--text-primary); border-color: var(--text-secondary); }
.pos-caja-select { display: flex; flex-direction: column; gap: 0.5rem; width: 100%; }
.pos-caja-select__btn {
    display: block; width: 100%; text-align: center; box-sizing: border-box;
    border: 1px solid var(--border); border-radius: 0.5rem; padding: 0.85rem 1.5rem;
    font-size: 0.9375rem; font-weight: 600; color: var(--text-primary);
    background: var(--bg-card); cursor: pointer; min-height: 44px;
    transition: border-color 0.15s, background 0.15s;
}
.pos-caja-select__btn:hover { border-color: var(--brand); background: var(--hover); }
.pos-caja-select__btn:disabled { opacity: 0.5; cursor: not-allowed; }

.no-caja-pill {
    font-size: 0.75rem; font-weight: 700; color: var(--red);
    background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3);
    border-radius: 6px; padding: 3px 10px; white-space: nowrap; flex-shrink: 0;
}
.pos-help-fab {
    width: 2rem; height: 2rem; border-radius: 50%; flex-shrink: 0;
    border: 1px solid var(--border); background: transparent;
    color: var(--text-muted); cursor: pointer; font-family: inherit; font-size: 0.85rem;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.pos-help-fab:hover { background: var(--brand); color: #fff; border-color: var(--brand); }
.pos-time { font-size: 12px; color: var(--text-muted); font-variant-numeric: tabular-nums; white-space: nowrap; flex-shrink: 0; }
.user-chip {
    display: flex; align-items: center; gap: 8px; padding: 4px 10px 4px 6px;
    background: var(--bg-base); border: 1px solid var(--border); border-radius: 8px;
    white-space: nowrap; flex-shrink: 0;
}
.user-av {
    width: 28px; height: 28px; border-radius: 50%;
    background: color-mix(in srgb, var(--brand) 15%, transparent);
    border: 1.5px solid var(--brand);
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; color: var(--brand); flex-shrink: 0;
}
.user-info { display: flex; flex-direction: column; gap: 1px; }
.user-name-label { font-size: 12px; font-weight: 600; color: var(--text-primary); line-height: 1.1; }
.user-role-label { font-size: 10px; color: var(--text-muted); text-transform: capitalize; line-height: 1.1; }
.nav-back {
    flex-shrink: 0; display: flex; align-items: center; justify-content: center;
    min-width: 44px; min-height: 44px; border-radius: 8px;
    border: 1.5px solid var(--border); background: var(--bg-base);
    color: var(--text-primary); text-decoration: none;
    transition: color 0.15s, border-color 0.15s, background 0.15s;
}
.nav-back:hover { background: var(--bg-card); border-color: var(--brand); color: var(--brand); }

/* ── Main ── */
.pos-main { flex: 1; display: flex; overflow: hidden; min-height: 0; position: relative; z-index: 1; }

/* ── Products panel ── */
.pnl-products { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }

/* ── Category tabs — scrollable on mobile ── */
.tabs-outer {
    position: relative;
    flex-shrink: 0;
    display: flex;
    align-items: stretch;
}
.tab-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 3;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    border: 1px solid var(--border);
    background: var(--bg-card);
    color: var(--text-secondary);
    cursor: pointer;
    box-shadow: 0 1px 6px rgba(0,0,0,.25);
    transition: opacity .15s;
}
.tab-arrow--left  { left: 4px; }
.tab-arrow--right { right: 4px; }
.tab-arrow:hover  { color: var(--brand); border-color: var(--brand); }
.tab-fade {
    position: absolute; top: 0; bottom: 0;
    width: 44px; pointer-events: none; z-index: 2;
}
.tab-fade--left  { left: 0;  background: linear-gradient(to right, var(--bg-base), transparent); }
.tab-fade--right { right: 0; background: linear-gradient(to left,  var(--bg-base), transparent); }
@media (pointer: coarse) { .tab-arrow { display: none !important; } }
.tabs-wrap {
    padding: 14px 0 0;
    flex: 1;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
    scrollbar-width: none;
}
.tabs-wrap::-webkit-scrollbar { display: none; }
.tabs {
    display: flex; gap: 2px;
    border-bottom: 1px solid var(--border);
    padding: 0 20px;
    min-width: max-content; /* prevent wrapping; enables scroll */
}
.tab {
    display: flex; align-items: center; gap: 6px;
    min-height: 44px; /* WCAG touch target */
    padding: 8px 16px; background: transparent; border: none;
    border-bottom: 2px solid transparent; margin-bottom: -1px;
    color: var(--text-muted); font-size: 13px; font-weight: 500; cursor: pointer;
    touch-action: manipulation; /* eliminate 300ms tap delay */
    transition: color 0.15s, border-color 0.15s; white-space: nowrap; font-family: inherit;
    flex-shrink: 0;
}
.tab:hover { color: var(--text-primary); }
.tab.active { font-weight: 600; }
.tab-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.tab.active .tab-dot { box-shadow: 0 0 6px currentColor; }

.search-row  { padding: 10px 20px 8px; flex-shrink: 0; display: flex; flex-direction: row; align-items: center; gap: 8px; }
.search-wrap {
    position: relative;
    display: flex;
    align-items: center;
    flex: 1;
}
.search-icon {
    position: absolute;
    left: 12px;
    color: var(--text-muted);
    pointer-events: none;
    flex-shrink: 0;
}
.search-input {
    flex: 1;
    min-width: 0;
    padding: 10px 36px 10px 38px;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 14px;
    outline: none;
    font-family: inherit;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.search-input:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 15%, transparent);
}
.search-input::placeholder { color: var(--text-muted); }
.search-clear {
    position: absolute;
    right: 10px;
    background: none;
    border: none;
    color: var(--text-muted);
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
    padding: 2px 4px;
    border-radius: 4px;
}
.search-clear:hover { color: var(--text-primary); }

/* ── Solo con stock pill toggle ── */
.stk-toggle {
    display: flex;
    align-items: center;
    gap: 7px;
    flex-shrink: 0;
    cursor: pointer;
    user-select: none;
}
.stk-label {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--text-secondary);
    white-space: nowrap;
}
.stk-pill {
    width: 34px;
    height: 19px;
    border-radius: 999px;
    background: var(--bg-elevated);
    border: 1.5px solid var(--border);
    position: relative;
    transition: background 0.2s, border-color 0.2s;
    flex-shrink: 0;
}
.stk-pill--on {
    background: var(--brand);
    border-color: var(--brand);
}
.stk-thumb {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 11px;
    height: 11px;
    border-radius: 50%;
    background: var(--text-muted);
    transition: transform 0.2s, background 0.2s;
}
.stk-pill--on .stk-thumb {
    transform: translateX(15px);
    background: #fff;
}


/* ── Estado inicial vacío ── */
.pos-empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 3rem 2rem;
    text-align: center;
}
.pos-empty-icon { color: var(--border); }
.pos-empty-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-muted);
    margin: 0;
}
.pos-empty-hint {
    font-size: 0.82rem;
    color: var(--text-muted);
    opacity: 0.7;
    margin: 0;
}

/* ── Contador búsqueda ── */
.search-count {
    font-size: 0.78rem;
    color: var(--text-muted);
    padding: 0 2px 8px;
    margin: 0;
}

/* ── Cargar más ── */
.load-more-wrap { display: flex; justify-content: center; padding: 16px 0 8px; }
.btn-load-more {
    padding: 8px 20px;
    border: 1.5px solid var(--border);
    border-radius: 8px;
    background: transparent;
    color: var(--text-secondary);
    font-size: 0.83rem;
    font-weight: 500;
    cursor: pointer;
    font-family: inherit;
    transition: border-color 0.15s, color 0.15s;
}
.btn-load-more:hover { border-color: var(--brand); color: var(--brand); }

.grid-wrap {
    flex: 1; overflow-y: auto; padding: 10px 20px 20px;
    scrollbar-width: thin; scrollbar-color: var(--border) transparent;
    overscroll-behavior: contain;
}
.product-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; align-content: start; }

@keyframes cardIn {
    from { opacity: 0; transform: translateY(10px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}
.product-card {
    background: var(--bg-card); border: 1px solid var(--border); border-top: none;
    border-radius: 10px; padding: 10px 12px; cursor: pointer;
    display: flex; flex-direction: column; gap: 3px;
    position: relative; overflow: hidden; text-align: left;
    touch-action: manipulation;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, border-color 0.15s;
    animation: cardIn 0.28s ease both;
    font-family: inherit;
}
.product-card--no-stock {
    opacity: 0.55;
    cursor: pointer;
}
.product-card::before { content: ''; position: absolute; inset: 0; pointer-events: none; background: linear-gradient(145deg, rgba(255,255,255,0.025) 0%, transparent 55%); }
.product-card::after { content: '+'; position: absolute; top: 8px; right: 10px; font-size: 16px; font-weight: 300; color: var(--text-muted); opacity: 0; transition: opacity 0.15s, transform 0.18s cubic-bezier(0.34,1.56,0.64,1); transform: scale(0.7) rotate(20deg); }
.product-card:hover { transform: translateY(-2px); background: var(--bg-base); box-shadow: 0 12px 36px rgba(0,0,0,.45), 0 0 28px -6px var(--glow, rgba(37,99,235,.4)); border-color: var(--border); }
.product-card:hover::after { opacity: 0.6; transform: scale(1) rotate(0deg); }
.product-card:active { transform: translateY(-1px) scale(0.99); }

/* Zona imagen — banner superior de la card */
.p-img-zone {
    position: relative;
    width: calc(100% + 24px);
    margin: -10px -12px 0;
    height: 90px;
    background: linear-gradient(160deg, color-mix(in srgb, var(--cat-color, #2563eb) 12%, var(--bg-base)), var(--bg-base));
    overflow: hidden;
    border-radius: 8px 8px 0 0;
    flex-shrink: 0;
}
.p-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center;
    padding: 6px;
    filter: drop-shadow(0 2px 6px rgba(0,0,0,0.5));
    transition: transform 0.25s ease;
}
.p-card:hover .p-img { transform: scale(1.06); }
.p-img-ph {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}
/* Pills sobre la imagen */
.p-cat-pill {
    position: absolute;
    top: 6px; left: 7px;
    font-size: 0.58rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    padding: 0.15rem 0.45rem;
    border-radius: 99px;
    backdrop-filter: blur(4px);
    line-height: 1.4;
}
.p-stock-pill {
    position: absolute;
    top: 6px; right: 6px;
    font-size: 0.55rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.12rem 0.4rem;
    border-radius: 6px;
    line-height: 1.5;
    white-space: nowrap;
    max-width: calc(100% - 12px);
    overflow: hidden;
    text-overflow: ellipsis;
}
.p-stock-empty { background: #ef4444; color: #fff; }
.p-stock-low   { background: #d97706; color: #fff; }
/* Info bajo la imagen */
.p-info { display: flex; flex-direction: column; gap: 0.1rem; padding-top: 0.35rem; }
.stock-low   { background: rgba(245,158,11,0.18); color: #d97706; }
.stock-empty { background: rgba(239,68,68,0.14);  color: #dc2626; }
.p-name  { font-size: 13px; font-weight: 700; color: var(--text-primary); line-height: 1.2; }
.p-bs    { font-size: 18px; font-weight: 800; color: var(--amber); font-variant-numeric: tabular-nums; line-height: 1.1; margin-top: 1px; }
.p-bs-unit { font-size: 10px; font-weight: 500; color: var(--text-muted); margin-left: 2px; }
.p-usd   { font-size: 10px; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.empty-msg { grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 2rem; font-size: 0.875rem; }

/* ── Cart panel ── */
.pnl-cart { width: 340px; flex-shrink: 0; display: flex; flex-direction: column; border-left: 1px solid var(--border); background: var(--bg-card); overflow: hidden; }
.cart-head { padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.cart-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.4px; color: var(--text-muted); }
.cart-badge { padding: 2px 9px; background: var(--bg-base); border: 1px solid var(--border); border-radius: 100px; font-size: 11px; color: var(--text-muted); transition: background 0.2s, border-color 0.2s, color 0.2s; }
.cart-badge.has { background: rgba(37,99,235,0.12); border-color: rgba(37,99,235,0.35); color: var(--brand); }
.cart-items-list { flex: 1; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 6px; min-height: 0; scrollbar-width: thin; scrollbar-color: var(--border) transparent; overscroll-behavior: contain; }
.cart-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; min-height: 140px; }
.empty-icon { width: 48px; height: 48px; border-radius: 50%; background: var(--bg-base); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--text-muted); }
.empty-txt { font-size: 12px; color: var(--text-muted); text-align: center; line-height: 1.7; }

@keyframes itemFlash {
    0%   { background: rgba(37,99,235,0.22); border-color: rgba(37,99,235,0.45); }
    100% { background: var(--bg-base); border-color: var(--border); }
}
.ci { display: flex; align-items: center; gap: 10px; padding: 9px 12px; background: var(--bg-base); border: 1px solid var(--border); border-radius: 6px; transition: background 0.15s; }
.ci:hover { background: var(--bg-card); }
.ci-new { animation: itemFlash 0.65s ease; }
.ci-info { flex: 1; min-width: 0; }
.ci-name { font-size: 13px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ci-dual { display: flex; align-items: center; gap: 5px; margin-top: 2px; }
.ci-qty  { font-size: 11px; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.ci-dot  { font-size: 10px; color: var(--border); }
.ci-sub  { font-size: 12px; font-weight: 700; color: var(--amber); font-variant-numeric: tabular-nums; white-space: nowrap; }
.ci-sub-usd { font-size: 10px; font-weight: 500; color: var(--text-muted); }
.ci-rm   { width: 22px; height: 22px; border-radius: 4px; background: transparent; border: 1px solid transparent; display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 15px; flex-shrink: 0; line-height: 1; cursor: pointer; transition: background 0.15s, border-color 0.15s, color 0.15s; font-family: inherit; }
.ci-rm:hover { background: var(--red-a); border-color: rgba(239,68,68,0.25); color: var(--red); }

.cart-footer { border-top: 1px solid var(--border); background: var(--bg-card); flex-shrink: 0; }
.total-area  { padding: 14px 18px 10px; }
.total-meta  { display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; }
.total-lbl   { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); }
.total-usd   { font-size: 12px; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.total-main  { display: flex; align-items: baseline; gap: 5px; }
.total-bs    { font-size: 36px; font-weight: 800; color: var(--amber); font-variant-numeric: tabular-nums; line-height: 1; }
.total-curr  { font-size: 14px; font-weight: 600; color: var(--text-muted); }
.btn-cobrar  {
    display: block; width: calc(100% - 24px); margin: 8px 12px 6px; padding: 13px;
    background: var(--green); border: none; border-radius: 10px;
    color: #fff; font-size: 14px; font-weight: 700; letter-spacing: 0.2px;
    cursor: pointer; font-family: inherit; touch-action: manipulation;
    transition: background 0.2s, box-shadow 0.2s, transform 0.15s, opacity 0.2s;
}
.btn-cobrar:hover:not(:disabled) { background: var(--green-h); box-shadow: 0 6px 24px rgba(16,185,129,0.4); transform: translateY(-1px); }
.btn-cobrar:active:not(:disabled) { transform: translateY(0); }
.btn-cobrar:disabled { opacity: 0.22; cursor: not-allowed; }
.btn-clear-cart { display: block; width: calc(100% - 24px); margin: 0 12px 12px; padding: 7px; border-radius: 8px; border: 1px solid var(--border); background: transparent; color: var(--text-muted); font-size: 12px; cursor: pointer; font-family: inherit; }
.btn-clear-cart:hover { color: var(--text-primary); }

/* ── FAB mobile ── */
.cart-fab {
    display: none; position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 50;
    padding: 0.75rem 1.25rem; border-radius: 50px;
    background: var(--bg-card); border: 1px solid var(--border);
    color: var(--text-muted); font-weight: 700; cursor: pointer;
    box-shadow: 0 4px 20px rgba(0,0,0,0.4); font-family: inherit;
    align-items: center; gap: 0.5rem;
    touch-action: manipulation; transition: background 0.2s, color 0.2s, box-shadow 0.2s;
    min-height: 48px;
}
.cart-fab--has {
    background: var(--brand); color: #fff; border-color: var(--brand);
    box-shadow: 0 4px 20px rgba(37,99,235,0.45);
}

/* ── Mobile drawer ── */
.mobile-cart-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 60; display: flex; align-items: flex-end; }
.mobile-cart-drawer { width: 100%; max-height: 80dvh; background: var(--bg-card); border-radius: 16px 16px 0 0; display: flex; flex-direction: column; overflow: hidden; }
.drawer-close { margin-left: auto; background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer; }
.cart-drawer-enter-active, .cart-drawer-leave-active { transition: transform 0.25s ease; }
.cart-drawer-enter-from .mobile-cart-drawer, .cart-drawer-leave-to .mobile-cart-drawer { transform: translateY(100%); }

/* ── Modal overlay ── */
.modal-bg { position: fixed; inset: 0; background: rgba(6,12,24,0.78); backdrop-filter: blur(7px); -webkit-backdrop-filter: blur(7px); display: flex; align-items: center; justify-content: center; z-index: 70; }

/* Qty modal transitions */
.mo-enter-active { transition: opacity 0.18s ease; }
.mo-leave-active { transition: opacity 0.15s ease; }
.mo-enter-from, .mo-leave-to { opacity: 0; }
.mc-enter-active { transition: transform 0.26s cubic-bezier(0.34,1.56,0.64,1), opacity 0.18s ease; }
.mc-leave-active { transition: transform 0.15s ease, opacity 0.15s ease; }
.mc-enter-from   { opacity: 0; transform: scale(0.91) translateY(14px); }
.mc-leave-to     { opacity: 0; transform: scale(0.96) translateY(8px); }

/* Qty modal card */
.modal-card { width: min(388px, 94vw); background: var(--bg-base); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; box-shadow: 0 40px 80px rgba(0,0,0,0.65); }
.modal-top  { border-top: 3px solid; padding: 18px 20px; display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.m-cat  { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }
.m-name { font-size: 24px; font-weight: 800; color: var(--text-primary); line-height: 1.1; }
.modal-body { padding: 0 20px 20px; }
.input-lbl { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); margin-bottom: 8px; }
.qty-display-box { display: flex; align-items: baseline; gap: 8px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: 10px 16px; }
.qty-num      { font-size: 2.2rem; font-weight: 800; color: var(--text-primary); }
.qty-unit-lbl { font-size: 1rem; color: var(--text-muted); }
.calc-box { margin-top: 12px; padding: 13px 14px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; }
.formula { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; font-size: 11.5px; color: var(--text-muted); font-variant-numeric: tabular-nums; padding-bottom: 8px; }
.fv { color: var(--text-primary); font-weight: 600; }
.fo { color: var(--text-muted); margin: 0 1px; }
.qty-kg-result { display: flex; align-items: center; gap: 4px; padding: 6px 0 11px; border-bottom: 1px solid var(--border); margin-bottom: 11px; }
.fv-kg-lbl { font-size: 12px; color: var(--text-muted); }
.fv-kg { font-size: 1rem; font-weight: 700; color: var(--brand); }
.m-total-row  { display: flex; align-items: baseline; justify-content: space-between; }
.m-total-lbl  { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); }
.m-total-main { display: flex; align-items: baseline; gap: 4px; }
.m-total-amt  { font-size: 32px; font-weight: 800; color: var(--amber); font-variant-numeric: tabular-nums; }
.m-total-curr { font-size: 13px; color: var(--text-muted); }
.numpad { display: grid; grid-template-columns: repeat(3,1fr); gap: 0.5rem; margin-top: 14px; }
.np-key { padding: 0.85rem; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-primary); font-size: 1.2rem; font-weight: 600; cursor: pointer; transition: background 0.1s; font-family: inherit; min-height: 44px; display: flex; align-items: center; justify-content: center; }
.np-key:hover { background: var(--brand); color: #fff; }
.unit-controls { display: flex; align-items: center; gap: 0.75rem; justify-content: center; margin-top: 14px; }
.unit-btn { width: 48px; height: 48px; border-radius: 50%; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-primary); font-size: 1.5rem; cursor: pointer; font-family: inherit; }
.unit-btn:hover { background: var(--brand); color: #fff; }
.unit-input { width: 80px; text-align: center; padding: 0.5rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-card); color: var(--text-primary); font-size: 1.2rem; font-weight: 700; font-family: inherit; }
.btn-agregar { width: 100%; margin-top: 14px; padding: 13px; background: var(--brand); border: none; border-radius: 10px; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; transition: background 0.2s, box-shadow 0.2s, transform 0.15s, opacity 0.2s; font-family: inherit; }
.btn-agregar:hover:not(:disabled) { opacity: 0.88; box-shadow: 0 6px 24px rgba(37,99,235,0.45); transform: translateY(-1px); }
.btn-agregar:disabled { opacity: 0.28; cursor: not-allowed; }

/* Generic modal box */
.modal-box { background: var(--bg-card); border-radius: 14px; border: 1px solid var(--border); width: 100%; max-width: 480px; max-height: 90dvh; overflow-y: auto; display: flex; flex-direction: column; gap: 1rem; padding: 1.25rem; box-shadow: 0 40px 80px rgba(0,0,0,0.65); }
.modal-header { display: flex; align-items: center; justify-content: space-between; }
.modal-header h3 { font-size: 1rem; font-weight: 700; color: var(--text-primary); }
.close-btn { width: 28px; height: 28px; border-radius: 6px; flex-shrink: 0; line-height: 1; background: var(--bg-base); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-size: 16px; cursor: pointer; transition: background 0.15s, color 0.15s; font-family: inherit; }
.close-btn:hover { background: var(--bg-card); color: var(--text-primary); }

/* Cliente */
.client-toggle { display: flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; color: var(--text-muted); cursor: pointer; user-select: none; padding: 0.3rem 0; }
.client-toggle:hover { color: var(--text-primary); }
.client-fields { display: flex; flex-direction: column; gap: 0; margin-bottom: 0.75rem; position: relative; }
.client-search-wrap { display: flex; gap: 0.4rem; align-items: center; }
.client-search-wrap .pay-amount-input { flex: 1; }
.client-clear-btn { background: none; border: 1px solid var(--border); border-radius: 6px; color: var(--text-muted); cursor: pointer; font-size: 1rem; padding: 0.3rem 0.5rem; font-family: inherit; }
.client-clear-btn:hover { color: var(--red); border-color: var(--red); }
.client-add-btn { background: var(--brand); border: none; border-radius: 6px; color: #fff; cursor: pointer; font-size: 1.1rem; font-weight: 700; padding: 0.3rem 0.65rem; font-family: inherit; }
.client-dropdown { position: absolute; top: calc(100% - 0.75rem); left: 0; right: 0; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; z-index: 50; max-height: 180px; overflow-y: auto; box-shadow: 0 4px 16px rgba(0,0,0,0.18); }
.client-option { display: flex; align-items: center; gap: 0.6rem; padding: 0.55rem 0.85rem; cursor: pointer; font-size: 0.85rem; }
.client-option:hover { background: var(--bg-base); }
.client-opt-code  { font-family: monospace; font-size: 0.75rem; color: var(--text-muted); min-width: 5rem; }
.client-opt-name  { font-weight: 600; color: var(--text-primary); flex: 1; }
.client-opt-phone { color: var(--text-muted); font-size: 0.8rem; }
.client-selected-row { display: flex; flex-direction: column; margin-top: 0.4rem; }
.client-tag { display: inline-block; font-size: 0.8rem; font-weight: 600; color: var(--brand); background: rgba(37,99,235,0.12); padding: 0.2rem 0.6rem; border-radius: 6px; }
.client-selected-badge { margin-left: auto; color: var(--green); font-weight: 700; }

/* Shared buttons */
.modal-actions { display: flex; gap: 0.75rem; justify-content: flex-end; }
.btn { padding: 0.6rem 1.25rem; border-radius: 8px; font-weight: 600; font-size: 0.88rem; cursor: pointer; border: none; transition: opacity 0.15s; font-family: inherit; }
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-brand { background: var(--brand); color: #fff; }
.btn-brand:not(:disabled):hover { opacity: 0.88; }
.btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text-muted); }
.btn-ghost:hover { color: var(--text-primary); }
.btn-warn  { background: #d97706; color: #fff; }
.btn-warn:hover { opacity: 0.88; }
.btn-print { background: var(--brand); color: #fff; display: inline-flex; align-items: center; gap: 0.4rem; justify-content: center; width: 100%; border: none; border-radius: 8px; padding: 0.65rem; font-weight: 700; cursor: pointer; font-family: inherit; font-size: 0.9rem; }
.btn-print:hover { opacity: 0.88; }

/* Stock warning */
.warn-text { font-size: 0.88rem; color: var(--text-muted); }
.warn-list { list-style: none; padding: 0.75rem; display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.85rem; color: var(--text-primary); background: rgba(239,68,68,0.07); border: 1px solid rgba(239,68,68,0.2); border-radius: 8px; }

/* ── Modal cobro 2 columnas — sin scroll ── */
.pay-modal-2col {
    display: flex;
    width: min(800px, 96vw);
    height: min(560px, 92dvh);
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 24px 64px rgba(0,0,0,0.5);
}

/* columna base */
.pay-col {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    padding: 1rem 1.125rem;
}

/* izquierda: resumen + cliente */
.pay-col--left {
    flex: 1;
    border-right: 1px solid var(--border);
    gap: 1rem;
}

/* derecha: métodos + confirmar */
.pay-col--right {
    width: 310px;
    flex-shrink: 0;
    gap: 1rem;
}

.pay-col-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.pay-col-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

/* ── Selector de origen ── */
.origin-selector {
    display: flex;
    gap: 0.4rem;
    flex-shrink: 0;
    margin-bottom: 0.25rem;
}
.origin-btn {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
    padding: 0.45rem 0.3rem;
    border-radius: 8px;
    border: 1.5px solid var(--border);
    background: var(--bg-base);
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    font-family: inherit;
}
.origin-btn:hover { border-color: var(--brand); }
.origin-btn.active { border-color: var(--brand); background: color-mix(in srgb, var(--brand) 12%, transparent); }
.origin-icon { font-size: 1.1rem; }
.origin-label { font-size: 0.65rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; }

/* ── Aviso delivery/crédito ── */
.origin-notice {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    padding: 0.85rem 1rem;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--bg-base);
    margin-bottom: 0.5rem;
    flex: 1;
}
.origin-notice--delivery { border-color: #f59e0b44; background: #f59e0b0d; }
.origin-notice--credit   { border-color: #8b5cf644; background: #8b5cf60d; }
.origin-notice-icon { font-size: 1.6rem; flex-shrink: 0; }
.origin-notice strong { display: block; font-size: 0.85rem; color: var(--text-primary); margin-bottom: 0.3rem; }
.origin-notice p { font-size: 0.75rem; color: var(--text-muted); margin: 0; line-height: 1.45; }

/* lista de ítems: crece y hace scroll solo si hay muchos */
.pay-items-scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.pay-item-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 0.5rem;
}
.pay-item-name   { font-size: 0.82rem; color: var(--text-primary); flex: 1; }
.pay-item-qty    { font-size: 0.75rem; color: var(--text-muted); font-style: normal; }
.pay-item-amount { font-size: 0.82rem; font-weight: 600; white-space: nowrap; color: var(--text-primary); }

/* totalizador compacto */
.pay-totalizador {
    flex-shrink: 0;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.625rem 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.pay-tot-main {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
}
.pay-total-bs { font-size: 1.5rem; font-variant-numeric: tabular-nums; }
.pay-tot-ref   { font-size: 0.72rem; color: var(--text-muted); }
.pay-tot-lines { display: flex; flex-direction: column; gap: 0.15rem; margin-top: 0.3rem; border-top: 1px solid var(--border); padding-top: 0.3rem; }
.pay-tot-line  { display: flex; justify-content: space-between; font-size: 0.78rem; color: var(--text-secondary); }
.pay-tot-line.rest-pending { color: var(--red); font-weight: 700; }
.pay-tot-line.rest-done    { color: var(--green); font-weight: 700; }
.pay-tot-line.rest-change  { color: var(--green); }

/* sección cliente compacta */
.client-toggle {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.78rem;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0.3rem 0;
    border-top: 1px solid var(--border);
}

/* ── Columna derecha ── */
.pay-section-label {
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: var(--text-muted);
    text-transform: uppercase;
    flex-shrink: 0;
    margin: 0;
}
.pay-no-payments {
    font-size: 0.78rem;
    color: var(--text-muted);
    text-align: center;
    padding: 0.35rem 0;
    flex-shrink: 0;
}

/* pagos añadidos: lista compacta */
.multipay-list   { display: flex; flex-direction: column; gap: 0.2rem; flex-shrink: 0; }
.multipay-row    { display: flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; background: var(--bg-elevated); border-radius: 6px; padding: 0.3rem 0.5rem; }
.multipay-method { font-weight: 600; color: var(--text-primary); flex: 1; }
.multipay-ref    { color: var(--text-muted); font-size: 0.72rem; }
.multipay-amount { font-weight: 700; color: var(--brand); white-space: nowrap; }
.multipay-remove { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1rem; padding: 0 0.2rem; line-height: 1; }
.multipay-remove:hover { color: #ef4444; }

/* métodos: grid 3 columnas, tarjetas compactas */
.multipay-add { display: flex; flex-direction: column; gap: 0.75rem; flex-shrink: 0; }
.pay-methods {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.3rem;
}
.pay-method-card {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0.1rem;
    padding: 0.45rem 0.5rem;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-radius: 7px;
    cursor: pointer;
    transition: border-color 0.12s, background 0.12s;
    text-align: left;
}
.pay-method-card.selected {
    border-color: var(--brand);
    background: color-mix(in srgb, var(--brand) 10%, transparent);
}
.pay-method-card:hover:not(.selected) { border-color: var(--text-muted); }
.pm-name { font-size: 1rem; font-weight: 600; color: var(--text-primary); line-height: 1.2; }
.pm-type { font-size: 0.75rem; color: var(--text-muted); }

/* monto + botón añadir */
.pay-amount-input {
    width: 100%;
    background: var(--bg-input, var(--bg-elevated));
    border: 1px solid var(--border);
    border-radius: 7px;
    color: var(--text-primary);
    font-size: 1.1rem;
    font-weight: 600;
    min-height: 44px;
    padding: 0.625rem 0.7rem;
    outline: none;
    box-sizing: border-box;
    font-family: inherit;
    flex-shrink: 0;
}
.pay-amount-input:focus { border-color: var(--brand); }
.multipay-btn-add {
    width: 100%;
    font-size: 0.82rem;
    padding: 0.45rem;
    flex-shrink: 0;
}

/* acciones siempre al fondo */
.pay-col-actions {
    display: flex;
    gap: 0.6rem;
    margin-top: auto;
    padding-top: 0.6rem;
    border-top: 1px solid var(--border);
    flex-shrink: 0;
}
.pay-col-actions .btn { flex: 1; min-height: 48px; padding: 0.75rem 1rem; font-size: 1rem; }

/* desktop: ancho mínimo garantizado */
@media (min-width: 768px) {
    .pay-modal-2col { min-width: 480px; }
}

/* responsive tablet/móvil */
@media (max-width: 680px) {
    .pay-modal-2col {
        flex-direction: column;
        width: 96vw;
        height: 92dvh;
    }
    .pay-col--left {
        border-right: none;
        border-bottom: 1px solid var(--border);
        flex: 0 0 auto;
        max-height: 45dvh;
        overflow-y: auto;
    }
    .pay-col--right {
        width: 100%;
        flex: 1;
        overflow-y: auto;
    }
}

/* ── Modal éxito ── */
.success-modal { padding: 0; overflow: hidden; gap: 0; max-width: 360px; }

.sc-status {
    display: flex; align-items: center; gap: 0.875rem;
    padding: 1.25rem 1.25rem 1rem;
}
.sc-check {
    width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
    background: var(--green); color: #fff;
    display: flex; align-items: center; justify-content: center;
}
.sc-title    { font-size: 1rem; font-weight: 800; color: var(--text-primary); }
.sc-subtitle { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.1rem; }

/* Mini ticket — fondo blanco, fuente monoespaciada */
.sc-ticket {
    background: #fff; color: #111;
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.72rem; line-height: 1.55;
    padding: 0.875rem 1rem;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}
.sc-biz-name { font-size: 0.82rem; font-weight: 700; text-align: center; text-transform: uppercase; }
.sc-biz-sub  { font-size: 0.68rem; text-align: center; color: #555; }
.sc-biz-meta {
    display: flex; flex-direction: column; align-items: center;
    font-size: 0.68rem; color: #666; margin-top: 0.2rem; gap: 0.05rem;
}
.sc-origin-badge {
    display: flex; align-items: center; justify-content: center; gap: 0.3rem;
    font-size: 0.72rem; font-weight: 700; letter-spacing: 0.5px;
    color: #111; margin-top: 0.25rem;
}
.sc-sep { border: none; border-top: 1px dashed #bbb; margin: 0.5rem 0; }

.sc-items { display: flex; flex-direction: column; gap: 0.35rem; }
.sc-item  { display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; }
.sc-item-left  { display: flex; flex-direction: column; flex: 1; }
.sc-item-right { display: flex; flex-direction: column; align-items: flex-end; gap: 0.05rem; }
.sc-item-name  { font-weight: 600; font-size: 0.73rem; color: #111; }
.sc-item-qty   { color: #555; font-size: 0.68rem; }
.sc-item-bs    { font-weight: 600; white-space: nowrap; }
.sc-item-usd   { color: #888; font-size: 0.67rem; white-space: nowrap; }

.sc-total-row   { display: flex; justify-content: space-between; font-weight: 700; font-size: 0.8rem; }
.sc-total-lbl   { }
.sc-total-val   { }
.sc-payment-row { font-size: 0.68rem; color: #666; margin-top: 0.15rem; }
.sc-footer      { text-align: center; color: #777; font-size: 0.68rem; margin-top: 0.5rem; }

.sc-actions {
    display: flex; gap: 0.75rem;
    padding: 0.875rem 1.25rem;
    justify-content: flex-end;
}
.sc-actions .btn { flex: 1; justify-content: center; display: flex; align-items: center; gap: 0.4rem; }

/* ── Responsive ── */
@media (max-width: 1023px) {
    .hd-sep-tabs { display: none; }
    .rate-lbl    { display: none; }
}
@media (max-width: 900px) {
    .pnl-cart     { display: none; }
    .cart-fab     { display: flex; }
    .product-grid { grid-template-columns: repeat(2, 1fr); }
    .header-tabs  { display: none; }
    .hd-center    { display: none; }
    /* touch targets expandidos en mobile */
    .ci-rm        { min-width: 44px; min-height: 44px; }
}
@media (max-width: 640px) {
    .pos-header      { gap: 6px; padding: 0 10px; }
    .pos-time        { display: none; }
    .user-info       { display: none; }
    .product-grid    { grid-template-columns: repeat(2, 1fr); }
    .logo-text       { font-size: 14px; }
    .no-caja-pill    { font-size: 0.68rem; padding: 2px 7px; }
    /* evita auto-zoom en iOS Safari (threshold: 16px) */
    .search-input    { font-size: 16px; }
}

/* ═══ BANNER CORTE BANCARIO ═══════════════════════════════════════════════════ */
.banking-banner {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    margin-bottom: 0.75rem;
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

/* ── Scanner toast ── */
.scanner-toast {
    display: inline-flex; align-items: center; gap: 6px;
    background: #16a34a22; color: #16a34a;
    border: 1px solid #16a34a55;
    padding: 0.35rem 0.875rem;
    border-radius: 8px;
    font-size: 0.78rem; font-weight: 700;
    margin-bottom: 0.5rem;
    width: fit-content;
}
.scanner-toast--rich { gap: 8px; padding: 0.4rem 1rem; }
.scanner-toast__name { font-weight: 700; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.scanner-toast__qty  { opacity: 0.75; font-weight: 500; }
.scanner-toast-enter-active, .scanner-toast-leave-active { transition: opacity 0.2s, transform 0.2s; }
.scanner-toast-enter-from,   .scanner-toast-leave-to     { opacity: 0; transform: translateY(-4px); }

.client-required-hint {
    font-size: 0.72rem;
    color: var(--amber);
    margin: 0.25rem 0 0;
    padding: 0 0.25rem;
}
</style>
