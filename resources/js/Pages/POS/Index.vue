<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    products:       { type: Array, default: () => [] },
    categories:     { type: Array, default: () => [] },
    cashRegister:   { type: Object, default: null },
    todayRate:      { type: Number, default: 1 },
    paymentMethods: { type: Array, default: () => [] },
    ticketPrefix:   { type: String, default: 'VEN' },
});

// ─── Tickets (tabs paralelos, max 5) ─────────────────────────────────────────
function emptyTicket(n) {
    return { id: n, label: `Ticket #${n}`, items: [], sale: null };
}
const tickets      = ref([emptyTicket(1)]);
const activeTicket = ref(0); // index
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

// ─── Filtro de categorías ─────────────────────────────────────────────────────
const selectedCat = ref(null); // null = Todas
const filteredProducts = computed(() =>
    selectedCat.value === null
        ? props.products
        : props.products.filter(p => p.category_id === selectedCat.value)
);

// ─── Búsqueda ─────────────────────────────────────────────────────────────────
const search = ref('');
const displayedProducts = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return filteredProducts.value;
    return filteredProducts.value.filter(p => p.name.toLowerCase().includes(q));
});

// ─── Precio en Bs ─────────────────────────────────────────────────────────────
function priceBs(product) {
    const usd = product.sale_mode === 'weight'
        ? parseFloat(product.price_per_kg_usd || 0)
        : parseFloat(product.price_per_unit_usd || 0);
    return usd * props.todayRate;
}
function fmtBs(n)  { return 'Bs. ' + Number(n).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function fmtUsd(n) { return '$' + Number(n).toFixed(2); }
function fmtQty(qty, mode) {
    return mode === 'weight' ? Number(qty).toFixed(3) + ' kg' : Number(qty).toFixed(0) + ' und';
}

// ─── Modal de cantidad ─────────────────────────────────────────────────────────
const qtyModal    = ref(false);
const qtyProduct  = ref(null);
const qtyInput    = ref('');

function openQtyModal(product) {
    qtyProduct.value = product;
    qtyInput.value   = '';
    qtyModal.value   = true;
}
function closeQtyModal() { qtyModal.value = false; qtyProduct.value = null; qtyInput.value = ''; }

// Teclado numérico
function kbPress(key) {
    if (key === '←') { qtyInput.value = qtyInput.value.slice(0, -1); return; }
    if (key === '.' && qtyProduct.value?.sale_mode !== 'weight') return; // sin decimales en unit
    if (key === '.' && qtyInput.value.includes('.')) return;
    if (qtyInput.value === '0' && key !== '.') { qtyInput.value = key; return; }
    qtyInput.value += key;
}

const qtyValue = computed(() => parseFloat(qtyInput.value) || 0);
const qtyUsd   = computed(() => {
    if (!qtyProduct.value) return 0;
    const price = qtyProduct.value.sale_mode === 'weight'
        ? parseFloat(qtyProduct.value.price_per_kg_usd || 0)
        : parseFloat(qtyProduct.value.price_per_unit_usd || 0);
    return qtyValue.value * price;
});
const qtyBsTotal = computed(() => qtyUsd.value * props.todayRate);

function addToCart() {
    if (qtyValue.value <= 0) return;
    const product  = qtyProduct.value;
    const existing = cart.value.find(i => i.product_id === product.id);
    if (existing) {
        existing.quantity_value += qtyValue.value;
        existing.subtotal_usd   = parseFloat(((product.sale_mode === 'weight'
            ? parseFloat(product.price_per_kg_usd || 0)
            : parseFloat(product.price_per_unit_usd || 0)) * existing.quantity_value).toFixed(2));
    } else {
        cart.value.push({
            product_id:      product.id,
            product_name:    product.name,
            input_type:      product.sale_mode === 'weight' ? 'weight' : 'unit',
            sale_mode:       product.sale_mode,
            quantity_value:  qtyValue.value,
            price_per_kg_usd:   parseFloat(product.price_per_kg_usd  || 0),
            price_per_unit_usd: parseFloat(product.price_per_unit_usd || 0),
            subtotal_usd:    parseFloat(qtyUsd.value.toFixed(2)),
        });
    }
    closeQtyModal();
}

function removeFromCart(idx) { cart.value.splice(idx, 1); }

const cartTotalUsd = computed(() => cart.value.reduce((s, i) => s + i.subtotal_usd, 0));
const cartTotalBs  = computed(() => cartTotalUsd.value * props.todayRate);
const cartCount    = computed(() => cart.value.reduce((s, i) => s + i.quantity_value, 0));

// ─── Carrito mobile drawer ────────────────────────────────────────────────────
const showMobileCart = ref(false);

// ─── Modal de cobro ───────────────────────────────────────────────────────────
const payModal          = ref(false);
const payMethodSelected = ref(null);
const payAmountBs       = ref('');
const paying            = ref(false);

const payChangeBs = computed(() => {
    const received = parseFloat(payAmountBs.value) || 0;
    return Math.max(0, received - cartTotalBs.value);
});

function openPayModal() {
    if (!cart.value.length) return;
    payMethodSelected.value = props.paymentMethods[0]?.id ?? null;
    payAmountBs.value       = Math.ceil(cartTotalBs.value).toString();
    payModal.value          = true;
}
function closePayModal() { payModal.value = false; payAmountBs.value = ''; }

// ─── Modal éxito ──────────────────────────────────────────────────────────────
const successModal  = ref(false);
const successTicket = ref('');
const successTotal  = ref(0);

function confirmPay() {
    if (!payMethodSelected.value || paying.value) return;

    paying.value = true;

    const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.content ?? '';

    fetch(route('sales.store'), {
        method:  'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept':       'application/json',
        },
        body: JSON.stringify({
            items: cart.value.map(i => ({
                product_id:     i.product_id,
                quantity_value: i.quantity_value,
                input_type:     i.input_type,
            })),
        }),
    })
    .then(r => r.json())
    .then(data => {
        if (!data.sale) throw new Error('Sin venta');
        const saleId         = data.sale.id;
        const ticketNumber   = data.sale.ticket_number;
        return fetch(route('sales.pay', { sale: saleId }), {
            method:  'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept':       'application/json',
            },
            body: JSON.stringify({
                payment_method_id:  payMethodSelected.value,
                amount_received_bs: parseFloat(payAmountBs.value) || cartTotalBs.value,
            }),
        }).then(r => r.json()).then(() => ticketNumber);
    })
    .then((ticket) => {
        successTicket.value = ticket;
        successTotal.value  = cartTotalBs.value;
        closePayModal();
        successModal.value = true;
        tickets.value[activeTicket.value].items = [];
    })
    .catch(() => { alert('Error al procesar el pago. Intente nuevamente.'); })
    .finally(() => { paying.value = false; });
}

function newSale() {
    successModal.value = false;
    successTicket.value = '';
}

function whatsAppText() {
    const lines = ['*CARNICERÍA CHAGUARAMAS*', `Ticket: ${successTicket.value}`, ''];
    return encodeURIComponent(lines.join('\n') + `Total: ${fmtBs(successTotal.value)}`);
}

// ─── Limpiar carrito ──────────────────────────────────────────────────────────
function clearCart() { tickets.value[activeTicket.value].items = []; }

// ─── Color de categoría ───────────────────────────────────────────────────────
function catColor(product) {
    return product.category?.color ?? '#888';
}
</script>

<template>
    <AppLayout title="Punto de Venta">

        <!-- Tabs de tickets paralelos -->
        <div class="ticket-tabs-bar">
            <button
                v-for="(t, idx) in tickets"
                :key="t.id"
                class="ticket-tab"
                :class="{ active: activeTicket === idx }"
                @click="activeTicket = idx"
            >
                {{ t.label }}
                <span v-if="tickets[idx].items.length" class="tab-badge">{{ tickets[idx].items.length }}</span>
                <span v-if="tickets.length > 1" class="tab-close" @click.stop="removeTicket(idx)">×</span>
            </button>
            <button class="ticket-tab-add" @click="addTicket" :disabled="tickets.length >= 5" title="Nuevo ticket">
                +
            </button>
        </div>

        <!-- Layout principal -->
        <div class="pos-wrap">

            <!-- ── Columna izquierda: catálogo ── -->
            <div class="pos-left">

                <!-- Tabs de categorías -->
                <div class="cat-tabs">
                    <button
                        class="cat-tab"
                        :class="{ active: selectedCat === null }"
                        @click="selectedCat = null"
                    >
                        Todas
                    </button>
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        class="cat-tab"
                        :class="{ active: selectedCat === cat.id }"
                        @click="selectedCat = cat.id"
                    >
                        <span class="cat-dot" :style="{ background: cat.color }"></span>
                        {{ cat.name }}
                    </button>
                </div>

                <!-- Búsqueda -->
                <div class="search-row">
                    <input
                        v-model="search"
                        type="search"
                        class="search-input"
                        placeholder="Buscar producto…"
                    />
                </div>

                <!-- Grid de productos -->
                <div class="product-grid">
                    <button
                        v-for="product in displayedProducts"
                        :key="product.id"
                        class="product-card"
                        :style="{ '--cat-color': catColor(product) }"
                        @click="openQtyModal(product)"
                    >
                        <div class="card-top-bar"></div>
                        <p class="card-name">{{ product.name }}</p>
                        <p v-if="product.subcategory" class="card-sub">{{ product.subcategory.name }}</p>
                        <p class="card-price-bs">{{ fmtBs(priceBs(product)) }}</p>
                        <p class="card-price-usd">
                            {{ fmtUsd(product.sale_mode === 'weight' ? product.price_per_kg_usd : product.price_per_unit_usd) }}
                            / {{ product.sale_mode === 'weight' ? 'kg' : 'und' }}
                        </p>
                    </button>
                    <p v-if="!displayedProducts.length" class="empty-msg">Sin productos en esta categoría.</p>
                </div>
            </div>

            <!-- ── Columna derecha: carrito ── -->
            <aside class="pos-cart">
                <div class="cart-header">
                    <span class="cart-title">Carrito</span>
                    <span v-if="cart.length" class="cart-badge">{{ cart.length }}</span>
                </div>

                <div class="cart-items">
                    <div v-if="!cart.length" class="cart-empty">
                        <p>Sin productos</p>
                        <p class="cart-empty-hint">Toca un producto para agregar</p>
                    </div>
                    <div v-for="(item, idx) in cart" :key="idx" class="cart-item">
                        <div class="ci-info">
                            <p class="ci-name">{{ item.product_name }}</p>
                            <p class="ci-qty">{{ fmtQty(item.quantity_value, item.sale_mode) }}</p>
                        </div>
                        <div class="ci-prices">
                            <p class="ci-bs">{{ fmtBs(item.subtotal_usd * todayRate) }}</p>
                            <p class="ci-usd">{{ fmtUsd(item.subtotal_usd) }}</p>
                        </div>
                        <button class="ci-remove" @click="removeFromCart(idx)">×</button>
                    </div>
                </div>

                <div class="cart-footer">
                    <div class="cart-total-row">
                        <span class="ct-label">Total USD</span>
                        <span class="ct-usd">{{ fmtUsd(cartTotalUsd) }}</span>
                    </div>
                    <div class="cart-total-row cart-total-main">
                        <span class="ct-label">Total Bs.</span>
                        <span class="ct-bs">{{ fmtBs(cartTotalBs) }}</span>
                    </div>
                    <button class="btn-pay" :disabled="!cart.length" @click="openPayModal">
                        Cobrar
                    </button>
                    <button class="btn-clear" :disabled="!cart.length" @click="clearCart">
                        Limpiar carrito
                    </button>
                </div>
            </aside>
        </div>

        <!-- Botón flotante mobile -->
        <button class="cart-fab" @click="showMobileCart = true">
            Ver Carrito ({{ cart.length }})
        </button>

        <!-- Drawer carrito mobile -->
        <Teleport to="body">
            <Transition name="cart-drawer">
                <div v-if="showMobileCart" class="mobile-cart-overlay" @click.self="showMobileCart = false">
                    <div class="mobile-cart-drawer">
                        <div class="cart-header">
                            <span class="cart-title">Carrito</span>
                            <button class="drawer-close" @click="showMobileCart = false">×</button>
                        </div>
                        <div class="cart-items">
                            <div v-if="!cart.length" class="cart-empty"><p>Sin productos</p></div>
                            <div v-for="(item, idx) in cart" :key="idx" class="cart-item">
                                <div class="ci-info">
                                    <p class="ci-name">{{ item.product_name }}</p>
                                    <p class="ci-qty">{{ fmtQty(item.quantity_value, item.sale_mode) }}</p>
                                </div>
                                <div class="ci-prices">
                                    <p class="ci-bs">{{ fmtBs(item.subtotal_usd * todayRate) }}</p>
                                    <p class="ci-usd">{{ fmtUsd(item.subtotal_usd) }}</p>
                                </div>
                                <button class="ci-remove" @click="removeFromCart(idx)">×</button>
                            </div>
                        </div>
                        <div class="cart-footer">
                            <div class="cart-total-row cart-total-main">
                                <span class="ct-label">Total Bs.</span>
                                <span class="ct-bs">{{ fmtBs(cartTotalBs) }}</span>
                            </div>
                            <button class="btn-pay" :disabled="!cart.length" @click="() => { showMobileCart = false; openPayModal(); }">
                                Cobrar
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Modal cantidad -->
        <Teleport to="body">
            <div v-if="qtyModal" class="modal-overlay" @click.self="closeQtyModal">
                <div class="modal-box qty-modal">
                    <div class="modal-header">
                        <h3>{{ qtyProduct?.name }}</h3>
                        <button class="modal-close" @click="closeQtyModal">×</button>
                    </div>

                    <div class="qty-display">
                        <span class="qty-num">{{ qtyInput || '0' }}</span>
                        <span class="qty-unit">{{ qtyProduct?.sale_mode === 'weight' ? 'kg' : 'und' }}</span>
                    </div>

                    <div class="qty-calc">
                        <span>{{ fmtUsd(qtyUsd) }}</span>
                        <span class="qty-eq"> × tasa = </span>
                        <span class="qty-total-bs">{{ fmtBs(qtyBsTotal) }}</span>
                    </div>

                    <!-- Teclado numérico (weight) -->
                    <div v-if="qtyProduct?.sale_mode === 'weight'" class="numpad">
                        <button v-for="k in ['1','2','3','4','5','6','7','8','9','.','0','←']"
                            :key="k" class="np-key" @click="kbPress(k)">
                            {{ k }}
                        </button>
                    </div>

                    <!-- +/- entero (unit) -->
                    <div v-else class="unit-controls">
                        <button class="unit-btn" @click="qtyInput = String(Math.max(1, (parseInt(qtyInput)||0) - 1))">−</button>
                        <input type="number" v-model="qtyInput" min="1" step="1" class="unit-input" />
                        <button class="unit-btn" @click="qtyInput = String((parseInt(qtyInput)||0) + 1)">+</button>
                    </div>

                    <div class="modal-actions">
                        <button class="btn btn-ghost" @click="closeQtyModal">Cancelar</button>
                        <button class="btn btn-brand" :disabled="qtyValue <= 0" @click="addToCart">
                            Agregar al carrito
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal de cobro -->
        <Teleport to="body">
            <div v-if="payModal" class="modal-overlay" @click.self="closePayModal">
                <div class="modal-box pay-modal">
                    <div class="modal-header">
                        <h3>Cobrar</h3>
                        <button class="modal-close" @click="closePayModal">×</button>
                    </div>

                    <!-- Resumen -->
                    <div class="pay-summary">
                        <div v-for="(item, idx) in cart" :key="idx" class="pay-item-row">
                            <span>{{ item.product_name }} × {{ fmtQty(item.quantity_value, item.sale_mode) }}</span>
                            <span>{{ fmtBs(item.subtotal_usd * todayRate) }}</span>
                        </div>
                        <div class="pay-divider"></div>
                        <div class="pay-total-row">
                            <span>Total a cobrar</span>
                            <span class="pay-total-bs">{{ fmtBs(cartTotalBs) }}</span>
                        </div>
                        <div class="pay-total-usd">Ref: {{ fmtUsd(cartTotalUsd) }}</div>
                    </div>

                    <!-- Método de pago -->
                    <p class="pay-section-label">Método de pago</p>
                    <div class="pay-methods">
                        <button
                            v-for="pm in paymentMethods"
                            :key="pm.id"
                            class="pay-method-card"
                            :class="{ selected: payMethodSelected === pm.id }"
                            @click="payMethodSelected = pm.id"
                        >
                            <span class="pm-name">{{ pm.name }}</span>
                            <span class="pm-type">{{ pm.type }}</span>
                        </button>
                    </div>

                    <!-- Monto recibido -->
                    <p class="pay-section-label">Monto recibido (Bs.)</p>
                    <input
                        v-model="payAmountBs"
                        type="number"
                        class="pay-amount-input"
                        min="0"
                        step="0.01"
                        placeholder="0.00"
                    />

                    <!-- Vuelto -->
                    <div v-if="payChangeBs > 0" class="pay-change">
                        Vuelto: <strong>{{ fmtBs(payChangeBs) }}</strong>
                    </div>

                    <div class="modal-actions">
                        <button class="btn btn-ghost" @click="closePayModal">Cancelar</button>
                        <button
                            class="btn btn-brand"
                            :disabled="!payMethodSelected || paying"
                            @click="confirmPay"
                        >
                            {{ paying ? 'Procesando…' : 'Confirmar Pago' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal de éxito -->
        <Teleport to="body">
            <div v-if="successModal" class="modal-overlay">
                <div class="modal-box success-modal">
                    <div class="success-icon">✓</div>
                    <h3 class="success-title">Pago Confirmado</h3>
                    <p class="success-ticket">{{ successTicket }}</p>
                    <p class="success-total">{{ fmtBs(successTotal) }}</p>
                    <div class="success-actions">
                        <a
                            :href="`https://wa.me/?text=${whatsAppText()}`"
                            target="_blank"
                            rel="noopener"
                            class="btn btn-whatsapp"
                        >
                            Enviar por WhatsApp
                        </a>
                        <button class="btn btn-brand" @click="newSale">
                            Nueva Venta
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>

<style scoped>
/* ─── Layout ─────────────────────────────────────────────────────────────────── */
.pos-wrap {
    display: flex;
    gap: 1rem;
    height: calc(100vh - 120px);
    padding: 0 1rem 1rem;
    overflow: hidden;
}

/* ─── Ticket tabs ──────────────────────────────────────────────────────────── */
.ticket-tabs-bar {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.5rem 1rem 0;
    overflow-x: auto;
    border-bottom: 1px solid var(--border);
}
.ticket-tab {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border-radius: 6px 6px 0 0;
    border: 1px solid var(--border);
    border-bottom: none;
    background: var(--bg-base);
    color: var(--text-muted);
    font-size: 0.8rem;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s;
}
.ticket-tab.active { background: var(--bg-card); color: var(--text-primary); }
.tab-badge {
    background: var(--brand);
    color: #fff;
    border-radius: 9px;
    padding: 0 5px;
    font-size: 0.7rem;
}
.tab-close {
    opacity: 0.5;
    font-size: 1rem;
    line-height: 1;
    margin-left: 0.15rem;
}
.tab-close:hover { opacity: 1; }
.ticket-tab-add {
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    border: 1px dashed var(--border);
    background: transparent;
    color: var(--text-muted);
    font-size: 1.1rem;
    cursor: pointer;
}
.ticket-tab-add:disabled { opacity: 0.35; cursor: not-allowed; }

/* ─── Left column ────────────────────────────────────────────────────────────── */
.pos-left {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    overflow: hidden;
}

/* Category tabs */
.cat-tabs {
    display: flex;
    gap: 0.4rem;
    overflow-x: auto;
    padding-bottom: 0.25rem;
    scrollbar-width: thin;
}
.cat-tab {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.4rem 0.9rem;
    border-radius: 20px;
    border: 1px solid var(--border);
    background: var(--bg-card);
    color: var(--text-muted);
    font-size: 0.82rem;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s, color 0.15s;
}
.cat-tab.active { background: var(--brand); color: #fff; border-color: var(--brand); }
.cat-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* Search */
.search-row { display: flex; }
.search-input {
    flex: 1;
    padding: 0.5rem 0.85rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--bg-card);
    color: var(--text-primary);
    font-size: 0.9rem;
}
.search-input::placeholder { color: var(--text-muted); }

/* Product grid */
.product-grid {
    flex: 1;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.65rem;
    overflow-y: auto;
    padding-right: 0.25rem;
    align-content: start;
}
.product-card {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    padding: 0.75rem 0.75rem 0.65rem;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--bg-card);
    cursor: pointer;
    text-align: left;
    overflow: hidden;
    transition: transform 0.1s, box-shadow 0.1s;
}
.product-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,.25); }
.product-card:active { transform: translateY(0); }
.card-top-bar {
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--cat-color, var(--brand));
}
.card-name { font-weight: 600; font-size: 0.88rem; color: var(--text-primary); margin-top: 0.15rem; }
.card-sub  { font-size: 0.72rem; color: var(--text-muted); }
.card-price-bs  { font-size: 1.05rem; font-weight: 700; color: #f59e0b; margin-top: 0.3rem; }
.card-price-usd { font-size: 0.72rem; color: var(--text-muted); }
.empty-msg { grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 2rem; font-size: 0.875rem; }

/* ─── Cart sidebar ───────────────────────────────────────────────────────────── */
.pos-cart {
    width: 340px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}
.cart-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--border);
}
.cart-title { font-weight: 700; font-size: 1rem; color: var(--text-primary); }
.cart-badge {
    background: var(--brand);
    color: #fff;
    border-radius: 12px;
    padding: 0 7px;
    font-size: 0.75rem;
}
.cart-items {
    flex: 1;
    overflow-y: auto;
    padding: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.cart-empty { padding: 2rem 1rem; text-align: center; color: var(--text-muted); }
.cart-empty-hint { font-size: 0.78rem; margin-top: 0.25rem; }
.cart-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.6rem;
    border-radius: 8px;
    background: var(--bg-base);
    border: 1px solid var(--border);
}
.ci-info { flex: 1; min-width: 0; }
.ci-name { font-size: 0.82rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ci-qty  { font-size: 0.72rem; color: var(--text-muted); }
.ci-prices { text-align: right; }
.ci-bs  { font-size: 0.82rem; font-weight: 600; color: #f59e0b; }
.ci-usd { font-size: 0.7rem; color: var(--text-muted); }
.ci-remove {
    background: none;
    border: none;
    color: var(--text-muted);
    font-size: 1.1rem;
    cursor: pointer;
    padding: 0 0.25rem;
    border-radius: 4px;
}
.ci-remove:hover { color: #ef4444; }

.cart-footer {
    padding: 0.85rem;
    border-top: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.cart-total-row { display: flex; justify-content: space-between; align-items: center; }
.cart-total-main { padding: 0.35rem 0; }
.ct-label { font-size: 0.82rem; color: var(--text-muted); }
.ct-usd   { font-size: 0.82rem; color: var(--text-muted); }
.ct-bs    { font-size: 1.3rem; font-weight: 800; color: #f59e0b; }
.btn-pay {
    padding: 0.75rem;
    border-radius: 8px;
    background: #16a34a;
    color: #fff;
    font-weight: 700;
    font-size: 1rem;
    border: none;
    cursor: pointer;
    transition: background 0.15s;
}
.btn-pay:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-pay:not(:disabled):hover { background: #15803d; }
.btn-clear {
    padding: 0.5rem;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text-muted);
    font-size: 0.82rem;
    cursor: pointer;
}
.btn-clear:disabled { opacity: 0.35; cursor: not-allowed; }
.btn-clear:not(:disabled):hover { color: var(--text-primary); }

/* ─── Mobile FAB ─────────────────────────────────────────────────────────────── */
.cart-fab {
    display: none;
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    z-index: 50;
    padding: 0.75rem 1.25rem;
    border-radius: 50px;
    background: var(--brand);
    color: #fff;
    font-weight: 700;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(0,0,0,.4);
}

/* ─── Mobile cart drawer ─────────────────────────────────────────────────────── */
.mobile-cart-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.55);
    z-index: 60;
    display: flex;
    align-items: flex-end;
}
.mobile-cart-drawer {
    width: 100%;
    max-height: 80vh;
    background: var(--bg-card);
    border-radius: 16px 16px 0 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.drawer-close {
    margin-left: auto;
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--text-muted);
    cursor: pointer;
}
.cart-drawer-enter-active, .cart-drawer-leave-active { transition: transform 0.25s ease; }
.cart-drawer-enter-from .mobile-cart-drawer,
.cart-drawer-leave-to  .mobile-cart-drawer { transform: translateY(100%); }

/* ─── Modal overlay ──────────────────────────────────────────────────────────── */
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
    max-width: 420px;
    max-height: 90vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 1.25rem;
}
.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.modal-header h3 { font-size: 1rem; font-weight: 700; color: var(--text-primary); }
.modal-close {
    background: none;
    border: none;
    font-size: 1.4rem;
    color: var(--text-muted);
    cursor: pointer;
    line-height: 1;
}

/* Qty modal */
.qty-display {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 0.75rem 1rem;
}
.qty-num  { font-size: 2.5rem; font-weight: 800; color: var(--text-primary); }
.qty-unit { font-size: 1rem; color: var(--text-muted); }
.qty-calc {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.85rem;
    color: var(--text-muted);
}
.qty-eq { color: var(--text-muted); }
.qty-total-bs { font-weight: 700; color: #f59e0b; font-size: 1rem; }

/* Numpad */
.numpad {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
}
.np-key {
    padding: 0.85rem;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--bg-base);
    color: var(--text-primary);
    font-size: 1.2rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.1s;
}
.np-key:hover { background: var(--brand); color: #fff; }

/* Unit controls */
.unit-controls { display: flex; align-items: center; gap: 0.75rem; justify-content: center; }
.unit-btn {
    width: 48px; height: 48px;
    border-radius: 50%;
    border: 1px solid var(--border);
    background: var(--bg-base);
    color: var(--text-primary);
    font-size: 1.5rem;
    cursor: pointer;
}
.unit-btn:hover { background: var(--brand); color: #fff; }
.unit-input {
    width: 80px;
    text-align: center;
    padding: 0.5rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--bg-base);
    color: var(--text-primary);
    font-size: 1.2rem;
    font-weight: 700;
}

/* Pay modal */
.pay-modal { max-width: 480px; }
.pay-summary {
    background: var(--bg-base);
    border-radius: 8px;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.pay-item-row { display: flex; justify-content: space-between; font-size: 0.82rem; color: var(--text-muted); }
.pay-divider { border-top: 1px solid var(--border); margin: 0.25rem 0; }
.pay-total-row { display: flex; justify-content: space-between; align-items: baseline; font-weight: 700; }
.pay-total-bs { font-size: 1.35rem; color: #f59e0b; }
.pay-total-usd { font-size: 0.75rem; color: var(--text-muted); text-align: right; }
.pay-section-label { font-size: 0.78rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
.pay-methods { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.pay-method-card {
    flex: 1; min-width: 120px;
    padding: 0.6rem 0.75rem;
    border-radius: 8px;
    border: 2px solid var(--border);
    background: var(--bg-base);
    cursor: pointer;
    text-align: left;
    transition: border-color 0.15s;
}
.pay-method-card.selected { border-color: var(--brand); }
.pm-name { display: block; font-weight: 600; font-size: 0.85rem; color: var(--text-primary); }
.pm-type { font-size: 0.72rem; color: var(--text-muted); }
.pay-amount-input {
    width: 100%;
    padding: 0.65rem 0.85rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--bg-base);
    color: var(--text-primary);
    font-size: 1.1rem;
    font-weight: 700;
}
.pay-change { font-size: 0.92rem; color: #16a34a; font-weight: 600; text-align: right; }

/* ─── Success modal ──────────────────────────────────────────────────────────── */
.success-modal { align-items: center; text-align: center; }
.success-icon {
    width: 64px; height: 64px;
    border-radius: 50%;
    background: #16a34a;
    color: #fff;
    font-size: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}
.success-title  { font-size: 1.3rem; font-weight: 800; color: var(--text-primary); }
.success-ticket { font-size: 1rem; color: var(--text-muted); }
.success-total  { font-size: 2rem; font-weight: 800; color: #f59e0b; }
.success-actions { display: flex; flex-direction: column; gap: 0.5rem; width: 100%; }

/* ─── Buttons shared ─────────────────────────────────────────────────────────── */
.modal-actions { display: flex; gap: 0.75rem; justify-content: flex-end; }
.btn {
    padding: 0.6rem 1.25rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.88rem;
    cursor: pointer;
    border: none;
    transition: opacity 0.15s;
}
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-brand { background: var(--brand); color: #fff; }
.btn-brand:not(:disabled):hover { opacity: 0.88; }
.btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text-muted); }
.btn-ghost:hover { color: var(--text-primary); }
.btn-whatsapp { background: #25d366; color: #fff; display: block; width: 100%; text-align: center; text-decoration: none; border-radius: 8px; padding: 0.65rem; font-weight: 700; }
.btn-whatsapp:hover { opacity: 0.88; }

/* ─── Responsive ─────────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .pos-cart { display: none; }
    .cart-fab { display: block; }
    .product-grid { grid-template-columns: repeat(2, 1fr); }
    .pos-wrap { height: auto; overflow: visible; }
}
</style>
