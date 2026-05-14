<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    bovedaStock: Array,
    products: Array,
    history: Array,
})

// ——— Tipo config ———
const TIPO_CONFIG = {
    corte_principal:       { label: 'Corte',        color: '#2563EB', bg: 'rgba(37,99,235,0.12)' },
    subproducto_vendible:  { label: 'Subprod.',     color: '#059669', bg: 'rgba(5,150,105,0.12)' },
    subproducto_fabricado: { label: 'Fabricado',    color: '#7C3AED', bg: 'rgba(124,58,237,0.12)' },
    waste:                 { label: 'Desperdicio',  color: '#DC2626', bg: 'rgba(220,38,38,0.10)' },
}
const TIPO_OPTIONS = Object.entries(TIPO_CONFIG).map(([value, cfg]) => ({ value, label: cfg.label }))

// ——— Modal state ———
const showModal = ref(false)
const errors    = ref({})
const saving    = ref(false)

const form = ref({
    product_id:       null,
    quantity_kg_from: '',
    notes:            '',
    items:            [],
})

const newItemTipo       = ref('corte_principal')
const newItemProductId  = ref(null)
const newItemQuantityKg = ref('')

// ——— Computed ———
const selectedSource = computed(() =>
    props.bovedaStock.find(s => s.product_id === form.value.product_id)
)

const totalItemsKg = computed(() =>
    form.value.items.reduce((acc, i) => acc + parseFloat(i.quantity_kg || 0), 0)
)

const remainingKg = computed(() => {
    const from = parseFloat(form.value.quantity_kg_from || 0)
    return Math.max(0, from - totalItemsKg.value)
})

// ——— Helpers ———
function productName(id) {
    if (!id) return '—'
    const p = props.products.find(p => p.id === id)
    return p ? p.name : '—'
}

function tipoConfig(tipo) {
    return TIPO_CONFIG[tipo] ?? TIPO_CONFIG.corte_principal
}

function openModal(stock) {
    form.value = {
        product_id:       stock.product_id,
        quantity_kg_from: stock.total_kg,
        notes:            '',
        items:            [],
    }
    newItemTipo.value       = 'corte_principal'
    newItemProductId.value  = null
    newItemQuantityKg.value = ''
    errors.value = {}
    showModal.value = true
}

function closeModal() {
    showModal.value = false
}

function addItem() {
    if (!newItemQuantityKg.value) return
    const isWaste = newItemTipo.value === 'waste'
    if (!isWaste && !newItemProductId.value) return

    const qty = parseFloat(newItemQuantityKg.value)
    if (isNaN(qty) || qty <= 0) return

    form.value.items.push({
        tipo:        newItemTipo.value,
        product_id:  isWaste ? null : newItemProductId.value,
        quantity_kg: qty,
    })
    newItemProductId.value  = null
    newItemQuantityKg.value = ''
    // Mantener tipo seleccionado para agregar rápido items del mismo tipo
}

function removeItem(index) {
    form.value.items.splice(index, 1)
}

function submitForm() {
    if (saving.value) return
    saving.value = true
    errors.value = {}

    router.post(route('despiece.store'), {
        product_id:       form.value.product_id,
        quantity_kg_from: parseFloat(form.value.quantity_kg_from),
        notes:            form.value.notes || null,
        items:            form.value.items,
    }, {
        onSuccess: () => {
            showModal.value = false
            saving.value    = false
        },
        onError: (e) => {
            errors.value = e
            saving.value = false
        },
    })
}

function formatKg(val) {
    return Number(val).toFixed(3)
}

function formatDate(dt) {
    return new Date(dt).toLocaleString('es-VE', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    })
}
</script>

<template>
    <AppLayout title="Despiece">
        <div class="despiece-page">

            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">Despiece</h1>
                    <p class="page-sub">Procesa cortes desde bóveda hacia vitrina</p>
                </div>
            </div>

            <!-- Stock en bóveda -->
            <section class="section-card">
                <h2 class="section-title">Stock en Bóveda</h2>

                <div v-if="bovedaStock.length === 0" class="empty-state">
                    No hay productos con stock en bóveda.
                </div>

                <table v-else class="data-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th class="text-right">Stock (kg)</th>
                            <th class="text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="stock in bovedaStock" :key="stock.product_id">
                            <td>{{ stock.product_name }}</td>
                            <td class="text-muted">{{ stock.category }}</td>
                            <td class="text-right font-mono">{{ formatKg(stock.total_kg) }}</td>
                            <td class="text-center">
                                <button class="btn-primary btn-sm" @click="openModal(stock)">
                                    Procesar Despiece
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <!-- Historial -->
            <section class="section-card">
                <h2 class="section-title">Historial de Despieces</h2>

                <div v-if="history.length === 0" class="empty-state">
                    Aún no hay despieces registrados.
                </div>

                <div v-else class="history-list">
                    <div v-for="log in history" :key="log.id" class="history-card">
                        <div class="history-header">
                            <span class="history-product">{{ log.product.name }}</span>
                            <span class="history-kg">{{ formatKg(log.quantity_kg_from) }} kg</span>
                            <span class="history-date">{{ formatDate(log.processed_at) }}</span>
                            <span class="history-user">{{ log.user.name }}</span>
                        </div>
                        <div class="history-items">
                            <span
                                v-for="item in log.items"
                                :key="item.id"
                                class="history-item-badge"
                                :style="item.tipo ? { color: tipoConfig(item.tipo).color, borderColor: tipoConfig(item.tipo).color } : {}"
                            >
                                {{ item.tipo === 'waste' ? '🗑 Desperdicio' : (item.product?.name ?? '—') }}: {{ formatKg(item.quantity_kg) }} kg
                            </span>
                        </div>
                        <p v-if="log.notes" class="history-notes">{{ log.notes }}</p>
                    </div>
                </div>
            </section>
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
                <div class="modal-box">
                    <div class="modal-header">
                        <h3 class="modal-title">Procesar Despiece</h3>
                        <button class="modal-close" @click="closeModal">✕</button>
                    </div>

                    <!-- Origen -->
                    <div class="form-section">
                        <div class="form-row-info">
                            <span class="info-label">Producto origen:</span>
                            <span class="info-value">{{ selectedSource?.product_name }}</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Kg a procesar</label>
                            <input
                                v-model="form.quantity_kg_from"
                                type="number"
                                step="0.001"
                                :max="selectedSource?.total_kg"
                                class="form-input"
                                placeholder="0.000"
                            />
                            <span v-if="errors.quantity_kg_from" class="form-error">{{ errors.quantity_kg_from }}</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Notas <span class="optional">(opcional)</span></label>
                            <textarea v-model="form.notes" class="form-input" rows="2" placeholder="Ej: Canal trasero..." />
                        </div>
                    </div>

                    <!-- Ítems destino -->
                    <div class="form-section">
                        <h4 class="subsection-title">Cortes destino (vitrina)</h4>

                        <div class="add-item-row">
                            <select v-model="newItemTipo" class="form-input tipo-select">
                                <option v-for="t in TIPO_OPTIONS" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                            <select v-if="newItemTipo !== 'waste'" v-model="newItemProductId" class="form-input flex-1">
                                <option :value="null" disabled>Seleccionar producto...</option>
                                <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                            <span v-else class="waste-label">Desperdicio (sin destino)</span>
                            <input
                                v-model="newItemQuantityKg"
                                type="number"
                                step="0.001"
                                class="form-input w-28"
                                placeholder="kg"
                            />
                            <button class="btn-secondary btn-sm" @click="addItem">+ Agregar</button>
                        </div>

                        <div v-if="form.items.length > 0" class="items-list">
                            <div v-for="(item, idx) in form.items" :key="idx" class="item-row">
                                <span
                                    class="tipo-badge"
                                    :style="{ color: tipoConfig(item.tipo).color, background: tipoConfig(item.tipo).bg }"
                                >{{ tipoConfig(item.tipo).label }}</span>
                                <span class="item-name">{{ item.tipo === 'waste' ? '— desperdicio —' : productName(item.product_id) }}</span>
                                <span class="item-kg font-mono">{{ formatKg(item.quantity_kg) }} kg</span>
                                <button class="btn-danger-text" @click="removeItem(idx)">✕</button>
                            </div>
                        </div>

                        <!-- Resumen kg -->
                        <div class="kg-summary">
                            <span>Total asignado: <strong class="font-mono">{{ formatKg(totalItemsKg) }} kg</strong></span>
                            <span :class="remainingKg < 0 ? 'text-error' : 'text-muted'">
                                Remanente: <strong class="font-mono">{{ formatKg(remainingKg) }} kg</strong>
                            </span>
                        </div>

                        <span v-if="errors.items" class="form-error">{{ errors.items }}</span>
                    </div>

                    <!-- Errores generales -->
                    <div v-if="Object.keys(errors).length" class="form-errors-box">
                        <p v-for="(msg, key) in errors" :key="key">{{ msg }}</p>
                    </div>

                    <div class="modal-footer">
                        <button class="btn-ghost" @click="closeModal">Cancelar</button>
                        <button
                            class="btn-primary"
                            :disabled="saving || form.items.length === 0"
                            @click="submitForm"
                        >
                            {{ saving ? 'Procesando...' : 'Confirmar Despiece' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
/* ——— Layout ——— */
.despiece-page {
    padding: 1.5rem;
    max-width: 1100px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
}

.page-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.page-sub {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin: 0.25rem 0 0;
}

/* ——— Cards ——— */
.section-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    padding: 1.25rem;
}

.section-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 1rem;
}

.empty-state {
    color: var(--text-muted);
    font-size: 0.9rem;
    text-align: center;
    padding: 1.5rem 0;
}

/* ——— Table ——— */
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.data-table th {
    text-align: left;
    font-weight: 600;
    color: var(--text-muted);
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    padding: 0.5rem 0.75rem;
    border-bottom: 1px solid var(--border);
}

.data-table td {
    padding: 0.65rem 0.75rem;
    border-bottom: 1px solid var(--border-subtle, var(--border));
    color: var(--text-primary);
    vertical-align: middle;
}

.data-table tr:last-child td { border-bottom: none; }

.text-right  { text-align: right; }
.text-center { text-align: center; }
.text-muted  { color: var(--text-muted); }
.font-mono   { font-variant-numeric: tabular-nums; }

/* ——— History ——— */
.history-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.history-card {
    background: var(--bg-surface, var(--bg-card));
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.85rem 1rem;
}

.history-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem 1rem;
    margin-bottom: 0.5rem;
}

.history-product { font-weight: 600; color: var(--text-primary); }
.history-kg      { font-weight: 600; color: var(--brand); font-variant-numeric: tabular-nums; }
.history-date    { font-size: 0.8rem; color: var(--text-muted); margin-left: auto; }
.history-user    { font-size: 0.8rem; color: var(--text-muted); }

.history-items {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.history-item-badge {
    background: var(--brand-muted, rgba(var(--brand-rgb, 0,0,0), 0.12));
    color: var(--brand);
    font-size: 0.78rem;
    padding: 0.2rem 0.6rem;
    border-radius: 99px;
    font-variant-numeric: tabular-nums;
}

.history-notes {
    margin: 0.4rem 0 0;
    font-size: 0.82rem;
    color: var(--text-muted);
    font-style: italic;
}

/* ——— Buttons ——— */
.btn-primary {
    background: var(--brand);
    color: #fff;
    border: none;
    border-radius: 0.5rem;
    padding: 0.5rem 1.1rem;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: opacity .15s;
}
.btn-primary:hover:not(:disabled) { opacity: 0.85; }
.btn-primary:disabled { opacity: 0.45; cursor: not-allowed; }

.btn-secondary {
    background: var(--bg-surface, var(--bg-card));
    color: var(--text-primary);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.5rem 1rem;
    font-weight: 500;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background .15s;
}
.btn-secondary:hover { background: var(--border); }

.btn-ghost {
    background: transparent;
    color: var(--text-muted);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
    cursor: pointer;
}
.btn-ghost:hover { color: var(--text-primary); }

.btn-danger-text {
    background: none;
    border: none;
    color: var(--error, #f87171);
    cursor: pointer;
    font-size: 0.85rem;
    padding: 0.1rem 0.3rem;
    border-radius: 0.25rem;
}
.btn-danger-text:hover { opacity: 0.75; }

.btn-sm { padding: 0.35rem 0.8rem; font-size: 0.82rem; }

/* ——— Modal ——— */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
    padding: 1rem;
}

.modal-box {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 1rem;
    width: 100%;
    max-width: 560px;
    max-height: 90vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 0;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border);
}

.modal-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-muted);
    font-size: 1.1rem;
    cursor: pointer;
    line-height: 1;
}
.modal-close:hover { color: var(--text-primary); }

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-top: 1px solid var(--border);
}

/* ——— Form ——— */
.form-section {
    padding: 1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    border-bottom: 1px solid var(--border);
}

.form-section:last-of-type { border-bottom: none; }

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.form-label {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--text-muted);
}

.form-input {
    background: var(--bg-surface, var(--bg-card));
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    color: var(--text-primary);
    font-size: 0.9rem;
    width: 100%;
}
.form-input:focus {
    outline: none;
    border-color: var(--brand);
}

.form-error {
    font-size: 0.8rem;
    color: var(--error, #f87171);
}

.form-errors-box {
    margin: 0 1.25rem;
    padding: 0.75rem 1rem;
    background: rgba(248, 113, 113, 0.08);
    border: 1px solid rgba(248, 113, 113, 0.3);
    border-radius: 0.5rem;
    font-size: 0.82rem;
    color: var(--error, #f87171);
}

.form-row-info {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.info-label { font-size: 0.85rem; color: var(--text-muted); }
.info-value  { font-weight: 600; color: var(--text-primary); }

.subsection-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.add-item-row {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.tipo-select { width: 7.5rem; flex-shrink: 0; }
.waste-label { flex: 1; font-size: 0.85rem; color: var(--text-muted); font-style: italic; display: flex; align-items: center; }
.tipo-badge  { border-radius: 99px; padding: 0.15rem 0.5rem; font-size: 0.75rem; font-weight: 600; white-space: nowrap; }

.flex-1 { flex: 1; }
.w-28   { width: 7rem; }

.items-list {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin-top: 0.25rem;
}

.item-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.4rem 0.6rem;
    background: var(--bg-surface, var(--bg-card));
    border: 1px solid var(--border);
    border-radius: 0.4rem;
}

.item-name { flex: 1; font-size: 0.88rem; color: var(--text-primary); }
.item-kg   { font-size: 0.88rem; color: var(--brand); }

.kg-summary {
    display: flex;
    gap: 1.5rem;
    font-size: 0.85rem;
    color: var(--text-muted);
    padding: 0.5rem 0;
}

.text-error { color: var(--error, #f87171); }

.optional { font-weight: 400; color: var(--text-muted); font-size: 0.8rem; }
</style>
