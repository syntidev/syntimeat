<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Bike, Mailbox, Check } from '@lucide/vue';

const props = defineProps({
    pedidos:        { type: Array,  default: () => [] },
    paymentMethods: { type: Array,  default: () => [] },
    todayRate:      { type: Number, default: 0 },
});

const page = usePage();

// ─── Formatters ───────────────────────────────────────────────────────────────
function fmtBs(v) {
    return Number(v || 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function fmtQty(qty, type) {
    return type === 'weight'
        ? Number(qty).toFixed(3) + ' kg'
        : Number(qty).toFixed(0) + ' und';
}
function fmtDate(dt) {
    if (!dt) return '—';
    return new Date(dt).toLocaleString('es-VE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

// ─── Modal de cobro ───────────────────────────────────────────────────────────
const activeSale     = ref(null);
const payModal       = ref(false);
const paying         = ref(false);
const payments       = ref([]);
const newMethodId    = ref(null);
const newAmountBs    = ref('');
const newReference   = ref('');

const totalBs = computed(() => activeSale.value
    ? round2(activeSale.value.total_usd * props.todayRate)
    : 0
);
const paidBs  = computed(() => payments.value.reduce((s, p) => s + parseFloat(p.amount_bs || 0), 0));
const restBs  = computed(() => Math.max(0, totalBs.value - paidBs.value));
const changeBs= computed(() => Math.max(0, paidBs.value - totalBs.value));
const canConfirm = computed(() => payments.value.length > 0 && restBs.value <= 0);

const selectedNeedsRef = computed(() => {
    if (!newMethodId.value) return false;
    const m = props.paymentMethods.find(p => p.id === newMethodId.value);
    return m ? ['transfer', 'mobile', 'biometric'].includes(m.type) : false;
});

function round2(v) { return Math.round(v * 100) / 100; }

function openCobro(sale) {
    activeSale.value  = sale;
    payments.value    = [];
    newMethodId.value = props.paymentMethods[0]?.id ?? null;
    newAmountBs.value = String(Math.ceil(sale.total_usd * props.todayRate));
    newReference.value= '';
    payModal.value    = true;
}
function closeModal() {
    payModal.value   = false;
    activeSale.value = null;
    payments.value   = [];
}
function addPayment() {
    const amount = parseFloat(newAmountBs.value);
    if (!newMethodId.value || !amount || amount <= 0) return;
    payments.value.push({
        payment_method_id: newMethodId.value,
        amount_bs:         amount,
        reference:         newReference.value.trim() || null,
    });
    const rest = restBs.value;
    newAmountBs.value  = rest > 0 ? String(Math.ceil(rest)) : '0';
    newReference.value = '';
    newMethodId.value  = props.paymentMethods[0]?.id ?? null;
}
function removePayment(idx) { payments.value.splice(idx, 1); }
function pmtName(id) { return props.paymentMethods.find(m => m.id === id)?.name ?? ''; }

function confirmCobro() {
    if (!canConfirm.value || paying.value) return;
    paying.value = true;
    const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.content ?? '';
    fetch(route('sales.delivery-confirm', { sale: activeSale.value.id }), {
        method:  'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ payments: payments.value }),
    })
    .then(r => {
        if (!r.ok) return r.json().then(e => { throw new Error(e.error ?? 'Error al confirmar'); });
        return r.json();
    })
    .then(() => {
        closeModal();
        // Quitar el pedido de la lista localmente
        const idx = localPedidos.value.findIndex(p => p.id === activeSale.value?.id);
        if (idx !== -1) localPedidos.value.splice(idx, 1);
    })
    .catch(err => alert(err?.message || 'Error al procesar el cobro'))
    .finally(() => { paying.value = false; });
}

// ─── Lista local (para eliminar al cobrar sin reload) ────────────────────────
const localPedidos = ref([...props.pedidos]);
</script>

<template>
    <AppLayout>
        <div class="delivery-page">
            <!-- Header -->
            <div class="page-header">
                <div class="header-left">
                    <span class="header-icon"><Bike :size="28" /></span>
                    <div>
                        <h1 class="page-title">Delivery en tránsito</h1>
                        <p class="page-sub">{{ localPedidos.length }} pedido{{ localPedidos.length !== 1 ? 's' : '' }} pendiente{{ localPedidos.length !== 1 ? 's' : '' }} de cobro</p>
                    </div>
                </div>
                <a :href="route('orders.index')" class="btn-back">← Pedidos</a>
            </div>

            <!-- Sin pedidos -->
            <div v-if="!localPedidos.length" class="empty-state">
                <div class="empty-icon"><Mailbox :size="40" /></div>
                <p class="empty-txt">No hay deliveries pendientes de cobro</p>
            </div>

            <!-- Grid de tarjetas -->
            <div v-else class="delivery-grid">
                <div
                    v-for="sale in localPedidos"
                    :key="sale.id"
                    class="delivery-card"
                >
                    <!-- Card header -->
                    <div class="dc-head">
                        <div class="dc-ticket">
                            <span class="ticket-badge">🎫 {{ sale.ticket_number }}</span>
                            <span class="channel-badge" :class="sale.channel">
                                {{ sale.channel === 'online' ? '🌐 Online' : '📍 Físico' }}
                            </span>
                        </div>
                        <div class="dc-time">{{ fmtDate(sale.created_at) }}</div>
                    </div>

                    <!-- Cliente -->
                    <div v-if="sale.client_name || sale.client_phone" class="dc-client">
                        <span class="dc-client-name">{{ sale.client_name ?? 'Sin nombre' }}</span>
                        <span v-if="sale.client_phone" class="dc-client-phone">{{ sale.client_phone }}</span>
                    </div>
                    <div v-else class="dc-client dc-client-anon">Sin datos de cliente</div>

                    <!-- Items -->
                    <div class="dc-items">
                        <div v-for="(item, i) in sale.items" :key="i" class="dc-item-row">
                            <span class="dc-item-name">{{ item.product_name }}</span>
                            <span class="dc-item-qty">{{ fmtQty(item.quantity_value, item.input_type) }}</span>
                            <span class="dc-item-sub">{{ fmtBs(item.subtotal_bs) }} Bs.</span>
                        </div>
                    </div>

                    <!-- Total + acción -->
                    <div class="dc-footer">
                        <div class="dc-total">
                            <span class="dc-total-lbl">Total</span>
                            <span class="dc-total-bs">{{ fmtBs(sale.total_bs) }} <small>Bs.</small></span>
                        </div>
                        <button class="btn-cobrar" @click="openCobro(sale)">
                            Confirmar cobro
                        </button>
                    </div>

                    <div class="dc-cashier">Creado por: {{ sale.cashier_name }}</div>
                </div>
            </div>
        </div>

        <!-- Modal cobro -->
        <Teleport to="body">
            <Transition name="mo">
                <div v-if="payModal" class="modal-bg">
                    <div class="modal-box">
                        <div class="modal-header">
                            <h3>Cobrar delivery · {{ activeSale?.ticket_number }}</h3>
                            <button class="close-btn" @click="closeModal">×</button>
                        </div>

                        <!-- Resumen items -->
                        <div class="pay-summary">
                            <div v-for="(item, i) in activeSale?.items" :key="i" class="pay-item-row">
                                <span>{{ item.product_name }} × {{ fmtQty(item.quantity_value, item.input_type) }}</span>
                                <span>{{ fmtBs(item.subtotal_bs) }} Bs.</span>
                            </div>
                            <div class="pay-divider"></div>
                            <div class="pay-total-row">
                                <span>Total a cobrar</span>
                                <span class="pay-total-bs">{{ fmtBs(totalBs) }} Bs.</span>
                            </div>
                        </div>

                        <!-- Pagos añadidos -->
                        <div v-if="payments.length" class="multipay-list">
                            <div v-for="(p, idx) in payments" :key="idx" class="multipay-row">
                                <span class="multipay-method">{{ pmtName(p.payment_method_id) }}</span>
                                <span v-if="p.reference" class="multipay-ref">{{ p.reference }}</span>
                                <span class="multipay-amount">{{ fmtBs(p.amount_bs) }} Bs.</span>
                                <button class="multipay-remove" @click="removePayment(idx)">×</button>
                            </div>
                        </div>

                        <!-- Totales pago -->
                        <div class="multipay-totals">
                            <div class="multipay-total-row"><span>Total</span><span>{{ fmtBs(totalBs) }} Bs.</span></div>
                            <div class="multipay-total-row"><span>Pagado</span><span>{{ fmtBs(paidBs) }} Bs.</span></div>
                            <div class="multipay-total-row" :class="restBs > 0 ? 'rest-pending' : 'rest-done'">
                                <span>Restante</span><span>{{ fmtBs(restBs) }} Bs.</span>
                            </div>
                            <div v-if="changeBs > 0" class="multipay-total-row rest-change">
                                <span>Vuelto</span><span><strong>{{ fmtBs(changeBs) }} Bs.</strong></span>
                            </div>
                        </div>

                        <!-- Agregar pago -->
                        <div v-if="restBs > 0 || !payments.length" class="multipay-add">
                            <p class="pay-section-label">+ Agregar pago</p>
                            <div class="pay-methods">
                                <button
                                    v-for="pm in paymentMethods"
                                    :key="pm.id"
                                    class="pay-method-card"
                                    :class="{ selected: newMethodId === pm.id }"
                                    @click="newMethodId = pm.id"
                                >
                                    <span class="pm-name">{{ pm.name }}</span>
                                </button>
                            </div>
                            <input v-model="newAmountBs" type="number" class="pay-input" min="0.01" step="0.01" placeholder="Monto Bs." />
                            <input v-if="selectedNeedsRef" v-model="newReference" type="text" class="pay-input" maxlength="50" placeholder="Referencia (opcional)" style="margin-top:0.4rem;" />
                            <button class="btn-ghost multipay-btn-add" @click="addPayment">Añadir pago</button>
                        </div>

                        <div class="modal-actions">
                            <button class="btn-ghost" @click="closeModal">Cancelar</button>
                            <button class="btn-brand" :disabled="!canConfirm || paying" @click="confirmCobro">
                                <template v-if="paying">Procesando…</template>
                                <template v-else><Check :size="14" style="vertical-align:middle;margin-right:4px;" />Confirmar cobro</template>
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
/* ─── Layout ─────────────────────────────────────────────────────────────────*/
.delivery-page { padding: 1.5rem; max-width: 1100px; margin: 0 auto; }

/* ─── Header ─────────────────────────────────────────────────────────────────*/
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap; }
.header-left { display: flex; align-items: center; gap: 0.75rem; }
.header-icon { font-size: 2rem; }
.page-title { font-size: 1.4rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.page-sub   { font-size: 0.82rem; color: var(--text-muted); margin: 0; }
.btn-back   { font-size: 0.85rem; color: var(--text-muted); text-decoration: none; border: 1px solid var(--border); padding: 0.35rem 0.8rem; border-radius: 8px; }
.btn-back:hover { color: var(--text-primary); border-color: var(--brand); }

/* ─── Empty ──────────────────────────────────────────────────────────────────*/
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 4rem 1rem; gap: 0.75rem; }
.empty-icon  { font-size: 3rem; }
.empty-txt   { color: var(--text-muted); font-size: 1rem; }

/* ─── Grid de tarjetas ───────────────────────────────────────────────────────*/
.delivery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem; }

/* ─── Card ───────────────────────────────────────────────────────────────────*/
.delivery-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 1rem; display: flex; flex-direction: column; gap: 0.65rem; }
.dc-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; flex-wrap: wrap; }
.dc-ticket { display: flex; gap: 0.4rem; align-items: center; flex-wrap: wrap; }
.ticket-badge { font-size: 0.85rem; font-weight: 700; color: var(--brand); }
.channel-badge { font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 20px; font-weight: 600; }
.channel-badge.online   { background: rgba(37,99,235,0.12); color: var(--brand); }
.channel-badge.physical { background: rgba(16,185,129,0.12); color: var(--green,#10b981); }
.dc-time { font-size: 0.78rem; color: var(--text-muted); white-space: nowrap; }

.dc-client { font-size: 0.85rem; display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
.dc-client-name { font-weight: 600; color: var(--text-primary); }
.dc-client-phone { color: var(--text-muted); font-size: 0.8rem; }
.dc-client-anon { color: var(--text-muted); font-style: italic; }

.dc-items { background: var(--bg); border-radius: 8px; padding: 0.5rem 0.65rem; display: flex; flex-direction: column; gap: 0.3rem; }
.dc-item-row { display: flex; gap: 0.5rem; font-size: 0.82rem; }
.dc-item-name { flex: 1; color: var(--text-primary); }
.dc-item-qty  { color: var(--text-muted); white-space: nowrap; }
.dc-item-sub  { font-weight: 600; color: var(--text-primary); white-space: nowrap; }

.dc-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 0.25rem; }
.dc-total { display: flex; flex-direction: column; }
.dc-total-lbl { font-size: 0.75rem; color: var(--text-muted); }
.dc-total-bs  { font-size: 1.3rem; font-weight: 700; color: var(--text-primary); }
.dc-total-bs small { font-size: 0.75rem; font-weight: 400; color: var(--text-muted); }

.btn-cobrar { background: var(--brand); color: #fff; border: none; border-radius: 8px; padding: 0.55rem 1.1rem; font-size: 0.9rem; font-weight: 600; cursor: pointer; font-family: inherit; transition: opacity .15s; }
.btn-cobrar:hover { opacity: 0.88; }

.dc-cashier { font-size: 0.75rem; color: var(--text-muted); margin-top: -0.2rem; }

/* ─── Modal ──────────────────────────────────────────────────────────────────*/
.modal-bg  { position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 500; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.modal-box { background: var(--bg-card); border-radius: 14px; width: 100%; max-width: 420px; max-height: 90vh; overflow-y: auto; padding: 1.25rem; display: flex; flex-direction: column; gap: 0.75rem; }
.modal-header { display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { font-size: 1rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.close-btn { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: var(--text-muted); line-height: 1; padding: 0; }

.pay-summary { background: var(--bg); border-radius: 8px; padding: 0.65rem 0.75rem; display: flex; flex-direction: column; gap: 0.3rem; }
.pay-item-row { display: flex; justify-content: space-between; font-size: 0.82rem; color: var(--text-muted); }
.pay-divider  { border: none; border-top: 1px solid var(--border); margin: 0.35rem 0; }
.pay-total-row { display: flex; justify-content: space-between; font-size: 0.9rem; font-weight: 600; color: var(--text-primary); }
.pay-total-bs { color: var(--brand); font-size: 1.05rem; }

.multipay-list { display: flex; flex-direction: column; gap: 0.3rem; }
.multipay-row  { display: flex; align-items: center; gap: 0.4rem; font-size: 0.82rem; background: var(--bg); border-radius: 6px; padding: 0.3rem 0.6rem; }
.multipay-method { flex: 1; font-weight: 600; color: var(--text-primary); }
.multipay-ref    { color: var(--text-muted); }
.multipay-amount { font-weight: 700; color: var(--brand); }
.multipay-remove { background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 1rem; }

.multipay-totals { background: var(--bg); border-radius: 8px; padding: 0.5rem 0.75rem; display: flex; flex-direction: column; gap: 0.25rem; }
.multipay-total-row { display: flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-muted); }
.rest-pending span:last-child { color: #ef4444; font-weight: 700; }
.rest-done    span:last-child { color: var(--green,#10b981); font-weight: 700; }
.rest-change  span:last-child { color: var(--brand); }

.multipay-add { display: flex; flex-direction: column; gap: 0.5rem; }
.pay-section-label { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin: 0; }
.pay-methods { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.pay-method-card { border: 1px solid var(--border); border-radius: 8px; padding: 0.3rem 0.65rem; background: transparent; cursor: pointer; font-family: inherit; font-size: 0.82rem; color: var(--text-muted); transition: all .15s; }
.pay-method-card.selected { border-color: var(--brand); color: var(--brand); font-weight: 600; background: rgba(37,99,235,0.08); }
.pay-input { width: 100%; box-sizing: border-box; padding: 0.5rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); color: var(--text-primary); font-size: 0.9rem; font-family: inherit; }
.pay-input:focus { outline: none; border-color: var(--brand); }
.multipay-btn-add { border: 1px dashed var(--border); border-radius: 8px; padding: 0.4rem; background: none; cursor: pointer; color: var(--text-muted); font-family: inherit; font-size: 0.82rem; }
.multipay-btn-add:hover { border-color: var(--brand); color: var(--brand); }

.modal-actions { display: flex; gap: 0.6rem; justify-content: flex-end; }
.btn-ghost { border: 1px solid var(--border); border-radius: 8px; padding: 0.45rem 1rem; background: none; cursor: pointer; font-family: inherit; font-size: 0.88rem; color: var(--text-muted); }
.btn-ghost:hover { border-color: var(--brand); color: var(--brand); }
.btn-brand { background: var(--brand); color: #fff; border: none; border-radius: 8px; padding: 0.45rem 1.1rem; font-size: 0.88rem; font-weight: 600; cursor: pointer; font-family: inherit; transition: opacity .15s; }
.btn-brand:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-brand:not(:disabled):hover { opacity: 0.88; }

/* ─── Transición modal ───────────────────────────────────────────────────────*/
.mo-enter-active, .mo-leave-active { transition: opacity .18s; }
.mo-enter-from, .mo-leave-to { opacity: 0; }
</style>
