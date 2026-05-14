<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    fabricables:  { type: Array,  default: () => [] },
    ingredientes: { type: Array,  default: () => [] },
    stockMap:     { type: Object, default: () => ({}) },
    historial:    { type: Array,  default: () => [] },
});

// ─── Formatters ───────────────────────────────────────────────────────────────
function fmtKg(v)   { return Number(v || 0).toFixed(3) + ' kg'; }
function fmtUsd(v)  { return '$ ' + Number(v || 0).toFixed(2); }
function fmtDate(d) { return d ?? '—'; }

// ─── Tab principal ────────────────────────────────────────────────────────────
const tab = ref('fabricar');

// ─── Modal de lote ────────────────────────────────────────────────────────────
const showModal    = ref(false);
const modalProduct = ref(null);

const form = useForm({
    output_product_id: null,
    output_kg:         '',
    output_units:      '',
    notes:             '',
    produced_at:       new Date().toISOString().slice(0, 16),
    inputs:            [],
});

function openModal(product) {
    modalProduct.value        = product;
    form.output_product_id    = product.id;
    form.output_kg            = '';
    form.output_units         = '';
    form.notes                = '';
    form.produced_at          = new Date().toISOString().slice(0, 16);
    form.inputs               = [];
    ingredSearch.value        = '';
    showModal.value           = true;
}

function closeModal() {
    showModal.value    = false;
    modalProduct.value = null;
}

// ─── Buscador de ingredientes ─────────────────────────────────────────────────
const ingredSearch = ref('');
const filteredIngredientes = computed(() => {
    const q = ingredSearch.value.trim().toLowerCase();
    if (!q) return props.ingredientes;
    return props.ingredientes.filter(p => p.name.toLowerCase().includes(q));
});

function stockFor(id) { return props.stockMap[id] ?? 0; }

// ─── Ingredientes seleccionados ───────────────────────────────────────────────
// inputs = [{ product_id, name, quantity_kg, cost_usd }]

function addIngrediente(prod) {
    const existing = form.inputs.find(i => i.product_id === prod.id);
    if (existing) return; // ya está — el usuario edita cantidad directamente
    form.inputs.push({
        product_id:  prod.id,
        name:        prod.name,
        quantity_kg: '',
        cost_usd:    '',
    });
}

function removeIngrediente(idx) {
    form.inputs.splice(idx, 1);
}

// ─── Totales ──────────────────────────────────────────────────────────────────
const totalInputKg   = computed(() => form.inputs.reduce((s, i) => s + (parseFloat(i.quantity_kg) || 0), 0));
const totalInputCost = computed(() => form.inputs.reduce((s, i) => s + (parseFloat(i.cost_usd)    || 0), 0));
const rendimiento    = computed(() => {
    const outKg = parseFloat(form.output_kg) || 0;
    return totalInputKg.value > 0 ? ((outKg / totalInputKg.value) * 100).toFixed(1) : '—';
});
const costoPorKg = computed(() => {
    const outKg = parseFloat(form.output_kg) || 0;
    return outKg > 0 ? fmtUsd(totalInputCost.value / outKg) : '—';
});

const canSubmit = computed(() =>
    form.output_product_id &&
    parseFloat(form.output_kg) > 0 &&
    form.inputs.length > 0 &&
    form.inputs.every(i => parseFloat(i.quantity_kg) > 0)
);

function submitBatch() {
    if (!canSubmit.value || form.processing) return;
    form.post(route('fabrica.store'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
}
</script>

<template>
    <AppLayout title="Fábrica">
        <div class="fab-root">

            <!-- Header ─────────────────────────────────────────────────────── -->
            <div class="fab-header">
                <div>
                    <h1 class="fab-title">Fábrica</h1>
                    <p class="fab-sub">Selecciona un producto para registrar su producción.</p>
                </div>
                <div class="fab-tabs">
                    <button :class="['fab-tab', { 'fab-tab--active': tab === 'fabricar' }]" @click="tab = 'fabricar'">Fabricar</button>
                    <button :class="['fab-tab', { 'fab-tab--active': tab === 'historial' }]" @click="tab = 'historial'">
                        Historial <span v-if="historial.length" class="fab-count">{{ historial.length }}</span>
                    </button>
                </div>
            </div>

            <!-- Flash ───────────────────────────────────────────────────────── -->
            <div v-if="$page.props.flash?.success" class="fab-flash">{{ $page.props.flash.success }}</div>

            <!-- ── Tab: Fabricar ──────────────────────────────────────────── -->
            <div v-if="tab === 'fabricar'">

                <div v-if="!fabricables.length" class="fab-empty-state">
                    <p>No hay productos habilitados para fabricación.</p>
                    <p class="fab-empty-hint">Ve al <strong>Catálogo</strong> y activa la opción "Habilitar en Fábrica" en los productos que correspondan (chorizo, cesta, combo…)</p>
                </div>

                <div v-else class="fab-cards">
                    <button
                        v-for="p in fabricables"
                        :key="p.id"
                        class="fab-product-card"
                        @click="openModal(p)"
                    >
                        <div class="fab-card-img">
                            <img v-if="p.image_path" :src="`/storage/${p.image_path}`" :alt="p.name" />
                            <span v-else class="fab-card-placeholder">🏭</span>
                        </div>
                        <div class="fab-card-info">
                            <span class="fab-card-name">{{ p.name }}</span>
                            <span v-if="p.category" class="fab-card-cat">{{ p.category }}</span>
                        </div>
                        <div class="fab-card-cta">+ Registrar lote</div>
                    </button>
                </div>
            </div>

            <!-- ── Tab: Historial ─────────────────────────────────────────── -->
            <div v-else-if="tab === 'historial'" class="fab-card">
                <p v-if="!historial.length" class="fab-empty">Sin lotes registrados.</p>
                <table v-else class="fab-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th class="text-right">Kg fab.</th>
                            <th class="text-right">Ingred. kg</th>
                            <th class="text-right">Costo $</th>
                            <th>Operador</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in historial" :key="b.id">
                            <td class="fab-date">{{ fmtDate(b.produced_at) }}</td>
                            <td>{{ b.output_product }}</td>
                            <td class="text-right">{{ fmtKg(b.output_kg) }}</td>
                            <td class="text-right">{{ fmtKg(b.inputs_kg) }}</td>
                            <td class="text-right">{{ fmtUsd(b.input_cost_usd) }}</td>
                            <td class="fab-creator">{{ b.creator }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- ── Modal de lote ──────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showModal" class="fab-modal-bg" @click.self="closeModal">
                <div class="fab-modal">

                    <!-- Header del modal -->
                    <div class="fab-modal-header">
                        <div>
                            <h2 class="fab-modal-title">{{ modalProduct?.name }}</h2>
                            <p class="fab-modal-sub">Registrar nuevo lote de producción</p>
                        </div>
                        <button class="fab-modal-close" @click="closeModal">×</button>
                    </div>

                    <div class="fab-modal-body">

                        <!-- Columna izquierda: output + notas -->
                        <div class="fab-modal-left">

                            <section class="fab-section">
                                <h3 class="fab-section-title">Producción</h3>
                                <div class="fab-field-row">
                                    <div class="fab-field">
                                        <label class="fab-label">Kg fabricados *</label>
                                        <input v-model="form.output_kg" type="number" step="0.001" min="0.001" class="fab-input" placeholder="0.000" />
                                        <p v-if="form.errors.output_kg" class="fab-error">{{ form.errors.output_kg }}</p>
                                    </div>
                                    <div class="fab-field">
                                        <label class="fab-label">Unidades (opcional)</label>
                                        <input v-model="form.output_units" type="number" step="1" min="0" class="fab-input" placeholder="0" />
                                    </div>
                                </div>
                                <div class="fab-field">
                                    <label class="fab-label">Fecha de producción</label>
                                    <input v-model="form.produced_at" type="datetime-local" class="fab-input" />
                                </div>
                                <div class="fab-field">
                                    <label class="fab-label">Notas / receta del lote</label>
                                    <textarea v-model="form.notes" class="fab-input" rows="3" maxlength="500" placeholder="Ingredientes especiales, proporciones, observaciones…" />
                                </div>
                            </section>

                            <!-- Resumen del lote -->
                            <section class="fab-section fab-summary">
                                <h3 class="fab-section-title">Resumen</h3>
                                <div class="fab-summary-row"><span>Insumos totales</span><strong>{{ fmtKg(totalInputKg) }}</strong></div>
                                <div class="fab-summary-row"><span>Costo total</span><strong>{{ fmtUsd(totalInputCost) }}</strong></div>
                                <div class="fab-summary-row"><span>Costo/kg fabricado</span><strong>{{ costoPorKg }}</strong></div>
                                <div class="fab-summary-row fab-summary-row--ok"><span>Rendimiento</span><strong>{{ rendimiento }}%</strong></div>
                            </section>

                        </div>

                        <!-- Columna derecha: ingredientes -->
                        <div class="fab-modal-right">
                            <section class="fab-section fab-section--grow">
                                <h3 class="fab-section-title">Ingredientes usados</h3>

                                <!-- Buscador de productos -->
                                <input
                                    v-model="ingredSearch"
                                    type="text"
                                    class="fab-input fab-search"
                                    placeholder="Buscar ingrediente…"
                                />

                                <!-- Lista de búsqueda -->
                                <div class="fab-ingred-list">
                                    <button
                                        v-for="prod in filteredIngredientes"
                                        :key="prod.id"
                                        type="button"
                                        class="fab-ingred-opt"
                                        :class="{ 'fab-ingred-opt--added': form.inputs.some(i => i.product_id === prod.id) }"
                                        @click="addIngrediente(prod)"
                                    >
                                        <span class="fab-ingred-name">{{ prod.name }}</span>
                                        <span class="fab-ingred-stock" :class="stockFor(prod.id) <= 0 ? 'stock-zero' : ''">
                                            {{ fmtKg(stockFor(prod.id)) }}
                                        </span>
                                        <span class="fab-ingred-add">{{ form.inputs.some(i => i.product_id === prod.id) ? '✓' : '+' }}</span>
                                    </button>
                                </div>

                                <!-- Tabla de ingredientes seleccionados -->
                                <div v-if="form.inputs.length" class="fab-selected">
                                    <p class="fab-selected-title">Seleccionados</p>
                                    <div v-for="(inp, idx) in form.inputs" :key="inp.product_id" class="fab-selected-row">
                                        <span class="fab-sel-name">{{ inp.name }}</span>
                                        <input
                                            v-model="inp.quantity_kg"
                                            type="number"
                                            step="0.001"
                                            min="0.001"
                                            class="fab-input fab-input--qty"
                                            placeholder="kg"
                                        />
                                        <input
                                            v-model="inp.cost_usd"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="fab-input fab-input--cost"
                                            placeholder="$ costo"
                                        />
                                        <button type="button" class="fab-sel-remove" @click="removeIngrediente(idx)">×</button>
                                    </div>
                                </div>
                                <p v-else class="fab-empty">Selecciona ingredientes de la lista.</p>

                                <p v-if="form.errors.inputs" class="fab-error">{{ form.errors.inputs }}</p>
                            </section>
                        </div>

                    </div>

                    <!-- Footer del modal -->
                    <div class="fab-modal-footer">
                        <button class="btn btn-ghost" @click="closeModal">Cancelar</button>
                        <button
                            class="btn btn-brand"
                            :disabled="!canSubmit || form.processing"
                            @click="submitBatch"
                        >
                            {{ form.processing ? 'Registrando…' : 'Registrar lote' }}
                        </button>
                    </div>

                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>

<style scoped>
.fab-root { padding: 1.5rem; max-width: 1100px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.25rem; }

.fab-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
.fab-title  { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.fab-sub    { font-size: 0.875rem; color: var(--text-secondary); margin: 0; }

.fab-tabs        { display: flex; gap: 0.5rem; }
.fab-tab         { padding: 0.45rem 1rem; border-radius: 0.5rem; border: 1px solid var(--border); background: transparent; color: var(--text-secondary); font-size: 0.875rem; cursor: pointer; transition: all 0.15s; display: flex; align-items: center; gap: 0.4rem; }
.fab-tab--active { background: var(--brand); color: #fff; border-color: var(--brand); }
.fab-count       { background: rgba(255,255,255,0.25); border-radius: 999px; padding: 0 0.4rem; font-size: 0.7rem; }

.fab-flash { padding: 0.75rem 1rem; background: color-mix(in srgb, var(--brand) 15%, transparent); border: 1px solid var(--brand); border-radius: 0.5rem; color: var(--brand); font-size: 0.875rem; }

/* ── Cards de fabricables ─────────────────────────────────────────── */
.fab-empty-state { text-align: center; padding: 3rem 1rem; color: var(--text-secondary); }
.fab-empty-state p { margin: 0.5rem 0; }
.fab-empty-hint { font-size: 0.875rem; color: var(--text-muted); }

.fab-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; }

.fab-product-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    padding: 1.25rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    transition: border-color 0.15s, transform 0.15s;
    text-align: center;
}
.fab-product-card:hover { border-color: var(--brand); transform: translateY(-2px); }

.fab-card-img { width: 64px; height: 64px; border-radius: 0.5rem; overflow: hidden; background: var(--bg-input); display: flex; align-items: center; justify-content: center; }
.fab-card-img img { width: 100%; height: 100%; object-fit: cover; }
.fab-card-placeholder { font-size: 2rem; }
.fab-card-name { font-weight: 600; color: var(--text-primary); font-size: 0.9rem; }
.fab-card-cat  { font-size: 0.75rem; color: var(--text-muted); }
.fab-card-cta  { font-size: 0.75rem; color: var(--brand); font-weight: 600; }

/* ── Modal ────────────────────────────────────────────────────────── */
.fab-modal-bg {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100;
    padding: 1rem;
}

.fab-modal {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 1rem;
    width: 100%;
    max-width: 880px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.fab-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.fab-modal-title { font-size: 1.25rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.fab-modal-sub   { font-size: 0.8rem; color: var(--text-muted); margin: 0; }
.fab-modal-close { background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer; line-height: 1; padding: 0; }
.fab-modal-close:hover { color: var(--text-primary); }

.fab-modal-body {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 0;
    overflow: hidden;
    flex: 1;
}
@media (max-width: 640px) { .fab-modal-body { grid-template-columns: 1fr; } }

.fab-modal-left  { padding: 1.25rem 1.5rem; border-right: 1px solid var(--border); overflow-y: auto; display: flex; flex-direction: column; gap: 1rem; }
.fab-modal-right { padding: 1.25rem 1.5rem; overflow-y: auto; display: flex; flex-direction: column; }

.fab-section { display: flex; flex-direction: column; gap: 0.625rem; }
.fab-section--grow { flex: 1; }
.fab-section-title { font-size: 0.75rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.06em; margin: 0; }

.fab-field { display: flex; flex-direction: column; gap: 0.25rem; }
.fab-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.fab-label { font-size: 0.8rem; font-weight: 500; color: var(--text-secondary); }
.fab-input {
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.5rem 0.65rem;
    font-size: 0.875rem;
    color: var(--text-primary);
    outline: none;
    transition: border-color 0.15s;
    width: 100%;
}
.fab-input:focus { border-color: var(--brand); }
.fab-error { font-size: 0.75rem; color: var(--error, #ef4444); margin: 0; }
.fab-empty { font-size: 0.875rem; color: var(--text-muted); text-align: center; padding: 1rem 0; }

.fab-summary { background: var(--bg-input); border-radius: 0.5rem; padding: 0.875rem; }
.fab-summary-row { display: flex; justify-content: space-between; font-size: 0.8125rem; color: var(--text-secondary); padding: 0.2rem 0; }
.fab-summary-row strong { color: var(--text-primary); }
.fab-summary-row--ok strong { color: var(--brand); }

.fab-search { margin-bottom: 0.5rem; }

.fab-ingred-list {
    max-height: 220px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0;
}
.fab-ingred-opt {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: none;
    border: none;
    border-bottom: 1px solid color-mix(in srgb, var(--border) 50%, transparent);
    cursor: pointer;
    text-align: left;
    transition: background 0.1s;
}
.fab-ingred-opt:last-child { border-bottom: none; }
.fab-ingred-opt:hover { background: var(--bg-input); }
.fab-ingred-opt--added { background: color-mix(in srgb, var(--brand) 8%, transparent); }
.fab-ingred-name  { flex: 1; font-size: 0.8125rem; color: var(--text-primary); }
.fab-ingred-stock { font-size: 0.75rem; color: var(--text-muted); }
.fab-ingred-add   { font-size: 0.875rem; font-weight: 700; color: var(--brand); min-width: 1rem; text-align: center; }
.stock-zero       { color: var(--error, #ef4444); }

.fab-selected { display: flex; flex-direction: column; gap: 0.4rem; margin-top: 0.75rem; }
.fab-selected-title { font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin: 0; }
.fab-selected-row { display: grid; grid-template-columns: 1fr 80px 80px 24px; gap: 0.4rem; align-items: center; }
.fab-sel-name   { font-size: 0.8rem; color: var(--text-primary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fab-input--qty  { min-width: 0; }
.fab-input--cost { min-width: 0; }
.fab-sel-remove { background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 1.1rem; text-align: center; }
.fab-sel-remove:hover { color: var(--error, #ef4444); }

.fab-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border);
    flex-shrink: 0;
}

/* ── Historial ────────────────────────────────────────────────────── */
.fab-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 0.75rem; padding: 1.25rem; }
.fab-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
.fab-table th { padding: 0.5rem 0.75rem; text-align: left; font-size: 0.7rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border); }
.fab-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid color-mix(in srgb, var(--border) 50%, transparent); color: var(--text-primary); }
.fab-date    { font-size: 0.8rem; color: var(--text-muted); white-space: nowrap; }
.fab-creator { font-size: 0.8rem; color: var(--text-muted); }
.text-right  { text-align: right; }
</style>
