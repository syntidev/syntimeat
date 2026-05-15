<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, router } from '@inertiajs/vue3';
import HelpModal from '@/Components/HelpModal.vue';

const props = defineProps({
    fabricables:       { type: Array,  default: () => [] },
    ingredientes:      { type: Array,  default: () => [] },
    stockMap:          { type: Object, default: () => ({}) },
    historial:         { type: Array,  default: () => [] },
    despiecePendiente: { type: Array,  default: () => [] },
});

// ─── Formatters ───────────────────────────────────────────────────────────────
function fmtKg(v)   { return Number(v || 0).toFixed(3) + ' kg'; }
function fmtUsd(v)  { return '$ ' + Number(v || 0).toFixed(2); }
function fmtDate(d) { return d ?? '—'; }

function fmtStock(prodId) {
    const val  = props.stockMap[prodId] ?? 0;
    const prod = props.ingredientes.find(p => p.id === prodId);
    if (prod?.sale_mode === 'unit') return Math.floor(val) + ' piezas';
    return fmtKg(val);
}

function ingredSaleMode(prodId) {
    return props.ingredientes.find(p => p.id === prodId)?.sale_mode ?? 'weight';
}

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

// ─── Modo unidad ──────────────────────────────────────────────────────────────
const isUnitMode = computed(() => modalProduct.value?.sale_mode === 'unit');

// ─── Totales ──────────────────────────────────────────────────────────────────
const totalInputKg   = computed(() => form.inputs.reduce((s, i) => s + (parseFloat(i.quantity_kg) || 0), 0));
const totalInputCost = computed(() => form.inputs.reduce((s, i) => s + (parseFloat(i.cost_usd)    || 0), 0));

const outputQty = computed(() =>
    isUnitMode.value ? (parseFloat(form.output_units) || 0) : (parseFloat(form.output_kg) || 0)
);

const rendimiento = computed(() => {
    if (isUnitMode.value) return '—'; // no aplica para piezas
    return totalInputKg.value > 0 ? ((outputQty.value / totalInputKg.value) * 100).toFixed(1) : '—';
});

const costoPorUnidad = computed(() => {
    if (outputQty.value <= 0) return '—';
    return fmtUsd(totalInputCost.value / outputQty.value);
});

const canSubmit = computed(() =>
    form.output_product_id &&
    outputQty.value >= (isUnitMode.value ? 1 : 0.001) &&
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

// ─── Ayuda ────────────────────────────────────────────────────────────────────
const showHelp = ref(false);

const helpSteps = [
    {
        icon: '🏭',
        title: 'Despiece pendiente',
        body: 'Cuando en Bóveda se surte una pieza que requiere despiece (res, cerdo, pollo), aparece aquí automáticamente con los kg surtidos. Es la primera tarea del día: documentar los cortes antes de fabricar.',
        tip: 'El sistema muestra cuántos kg van a cada corte (Premium, Primera, Costilla…). Lo que no se documente se registra como merma de despiece.',
    },
    {
        icon: '⚖️',
        title: 'Registrar cortes del despiece',
        body: 'Abre la pieza pendiente, ingresa los kg reales de cada corte según la balanza. El sistema calcula en tiempo real los kg documentados y la merma. Cuando cuadre, haz clic en "Registrar cortes".',
        tip: 'No puedes ingresar más kg de los que se surtieron. El botón se bloquea si la suma excede el total.',
    },
    {
        icon: '📄',
        title: 'Planilla de despiece (PDF)',
        body: 'Al registrar, el sistema ofrece imprimir la planilla con todos los datos. Abre en otra pestaña pre-llenada con los kg reales. Usa Ctrl+P → "Guardar como PDF" para el control físico o digital del día.',
    },
    {
        icon: '🥩',
        title: 'Fabricar un lote',
        body: 'Para productos elaborados (chorizo, embutidos, cestas), selecciona el producto en las tarjetas, agrega los ingredientes que usaste con sus kg y costo, anota los kg producidos y guarda el lote.',
        tip: 'Los ingredientes (incluyendo Recortes de Res del despiece) se descuentan del stock de vitrina al guardar. El producto fabricado se suma al inventario.',
    },
    {
        icon: '📋',
        title: 'Historial de lotes',
        body: 'En la pestaña Historial ves todos los lotes registrados con fecha, kg producidos, insumos usados, costo y operador. Sirve para trazabilidad y control de rendimiento.',
    },
];

const helpFaqs = [
    {
        q: '¿Por qué aparece una pieza en "Despiece pendiente"?',
        a: 'Porque en el módulo Bóveda se surtió esa pieza con flujo "Despiece". Eso significa que la carne salió de bóveda y ahora está en el área de corte esperando ser documentada. Hasta que registres los cortes, el stock de vitrina no se actualiza.',
    },
    {
        q: '¿Qué pasa con los kg que no documento en cortes?',
        a: 'Se registran automáticamente como merma de despiece en la entrada de bóveda. Es normal: huesos, grasa descartada, pérdidas de sangre al cortar. El sistema los separa de la merma de almacenamiento para que puedas analizar cada tipo por separado.',
    },
    {
        q: '¿Qué son los ingredientes de un lote?',
        a: 'Son los insumos de vitrina que se consumen para producir un lote. Por ejemplo, para chorizo usas Recortes de Res (que entraron del despiece), especias, etc. Al guardar el lote, esos kg se descuentan del stock de vitrina automáticamente.',
    },
    {
        q: '¿Puedo registrar un lote sin hacer despiece antes?',
        a: 'Sí, si tienes ingredientes en stock de vitrina. El despiece y la fabricación son procesos independientes. El despiece alimenta el stock; la fabricación lo consume.',
    },
    {
        q: '¿El % de rendimiento qué indica?',
        a: 'Es la relación entre los kg producidos y los kg de ingredientes usados. Si usaste 10 kg de carne y obtuviste 8 kg de chorizo, el rendimiento es 80%. Te ayuda a detectar si una receta está dando buenas proporciones o hay pérdida excesiva.',
    },
];

// ─── Despiece ─────────────────────────────────────────────────────────────────
const despieceExpanded  = ref({});
const despieceForms     = ref({});
const despieceErrors    = ref({});
const despieceSaving    = ref({});
const despieceFlash     = ref(null);
const despiecePdfEntry  = ref(null);

function initDespiece(entry) {
    if (despieceForms.value[entry.id]) return;
    despieceForms.value[entry.id] = entry.productos_vitrina.map(p => ({
        product_id: p.id,
        name:       p.name,
        kg:         '',
    }));
}

function toggleDespiece(entry) {
    initDespiece(entry);
    despieceExpanded.value[entry.id] = !despieceExpanded.value[entry.id];
}

function totalCortes(entryId) {
    const rows = despieceForms.value[entryId] ?? [];
    return rows.reduce((s, r) => s + (parseFloat(r.kg) || 0), 0);
}

function mermaDespiece(entry) {
    return Math.max(0, entry.kg_surtido - totalCortes(entry.id));
}

function cortesExceden(entry) {
    return totalCortes(entry.id) > entry.kg_surtido + 0.001;
}

async function submitDespiece(entry) {
    despieceErrors.value[entry.id] = null;
    despieceSaving.value[entry.id] = true;

    const cortes = (despieceForms.value[entry.id] ?? []).map(r => ({
        product_id: r.product_id,
        kg:         parseFloat(r.kg) || 0,
    }));

    try {
        const { data } = await axios.post(route('fabrica.despiece'), {
            boveda_entry_id: entry.id,
            cortes,
        });

        despieceFlash.value   = `Despiece completado. Merma registrada: ${Number(data.merma).toFixed(3)} kg`;
        despiecePdfEntry.value = entry;
        router.reload({ only: ['despiecePendiente', 'historial'] });
    } catch (err) {
        despieceErrors.value[entry.id] = err?.response?.data?.error ?? 'Error al guardar. Intenta de nuevo.';
    } finally {
        despieceSaving.value[entry.id] = false;
    }
}
</script>

<template>
    <AppLayout title="Fábrica">
        <div class="fab-root">

            <!-- Header ─────────────────────────────────────────────────────── -->
            <div class="fab-header">
                <div>
                    <h1 class="fab-title">
                        Fábrica
                        <span v-if="despiecePendiente.length" class="fab-despiece-badge">{{ despiecePendiente.length }} despiece{{ despiecePendiente.length > 1 ? 's' : '' }} pendiente{{ despiecePendiente.length > 1 ? 's' : '' }}</span>
                    </h1>
                    <p class="fab-sub">Selecciona un producto para registrar su producción.</p>
                </div>
                <div class="fab-tabs">
                    <button :class="['fab-tab', { 'fab-tab--active': tab === 'fabricar' }]" @click="tab = 'fabricar'">Fabricar</button>
                    <button :class="['fab-tab', { 'fab-tab--active': tab === 'historial' }]" @click="tab = 'historial'">
                        Historial <span v-if="historial.length" class="fab-count">{{ historial.length }}</span>
                    </button>
                    <button class="fab-tab fab-tab--help" @click="showHelp = true" title="Ayuda">?</button>
                </div>
            </div>

            <!-- Flash ───────────────────────────────────────────────────────── -->
            <div v-if="$page.props.flash?.success" class="fab-flash">{{ $page.props.flash.success }}</div>
            <div v-if="despieceFlash" class="fab-flash fab-flash--despiece">
                <span>{{ despieceFlash }}</span>
                <div class="despiece-pdf-actions">
                    <a
                        v-if="despiecePdfEntry"
                        :href="route('boveda.plantilla', { entry: despiecePdfEntry.id })"
                        target="_blank"
                        class="btn-pdf"
                    >🖨 Guardar / Imprimir planilla</a>
                    <button class="btn-pdf-close" @click="despieceFlash = null; despiecePdfEntry = null">×</button>
                </div>
            </div>

            <!-- ── Despiece pendiente ─────────────────────────────────────── -->
            <section v-if="despiecePendiente.length" class="despiece-section">
                <div class="despiece-section-header">
                    <div class="despiece-badge">{{ despiecePendiente.length }}</div>
                    <div>
                        <h2 class="despiece-title">Despiece pendiente</h2>
                        <p class="despiece-sub">Piezas surtidas de bóveda que requieren registro de cortes en vitrina.</p>
                    </div>
                </div>

                <div class="despiece-list">
                    <div v-for="entry in despiecePendiente" :key="entry.id" class="despiece-card">

                        <!-- Cabecera de la pieza -->
                        <div class="despiece-card-header" @click="toggleDespiece(entry)">
                            <div class="despiece-card-info">
                                <span class="despiece-card-title">{{ entry.product_type }}</span>
                                <span v-if="entry.description" class="despiece-card-desc">{{ entry.description }}</span>
                                <span v-if="entry.supplier" class="despiece-card-supplier">Proveedor: {{ entry.supplier }}</span>
                            </div>
                            <div class="despiece-card-kg">
                                <span class="despiece-kg-val">{{ fmtKg(entry.kg_surtido) }}</span>
                                <span class="despiece-kg-label">surtidos</span>
                            </div>
                            <button class="despiece-toggle" :aria-expanded="!!despieceExpanded[entry.id]">
                                {{ despieceExpanded[entry.id] ? '▲' : '▼' }}
                            </button>
                        </div>

                        <!-- Formulario de cortes -->
                        <div v-if="despieceExpanded[entry.id]" class="despiece-form">
                            <p class="despiece-form-hint">Ingresa los kg de cada corte. Lo que no se documente se registra como merma.</p>

                            <div class="despiece-rows">
                                <div
                                    v-for="row in despieceForms[entry.id]"
                                    :key="row.product_id"
                                    class="despiece-row"
                                >
                                    <label class="despiece-row-name">{{ row.name }}</label>
                                    <input
                                        v-model="row.kg"
                                        type="number"
                                        step="0.001"
                                        min="0"
                                        class="fab-input despiece-input"
                                        placeholder="0.000 kg"
                                    />
                                </div>
                            </div>

                            <div class="despiece-totals">
                                <div class="despiece-total-row">
                                    <span>Surtido</span>
                                    <strong>{{ fmtKg(entry.kg_surtido) }}</strong>
                                </div>
                                <div class="despiece-total-row">
                                    <span>Documentado</span>
                                    <strong>{{ fmtKg(totalCortes(entry.id)) }}</strong>
                                </div>
                                    <div class="despiece-total-row despiece-total-merma">
                                    <span>Merma</span>
                                    <strong>{{ fmtKg(mermaDespiece(entry)) }}</strong>
                                </div>
                            </div>

                            <p v-if="cortesExceden(entry)" class="despiece-over-limit">
                                ⚠ La suma ({{ fmtKg(totalCortes(entry.id)) }}) supera los {{ fmtKg(entry.kg_surtido) }} surtidos. Revisa los pesos.
                            </p>
                            <p v-else-if="despieceErrors[entry.id]" class="fab-error despiece-error">{{ despieceErrors[entry.id] }}</p>

                            <div class="despiece-actions">
                                <button
                                    class="btn btn-brand"
                                    :disabled="despieceSaving[entry.id] || cortesExceden(entry)"
                                    @click="submitDespiece(entry)"
                                >
                                    {{ despieceSaving[entry.id] ? 'Registrando…' : 'Registrar cortes' }}
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </section>

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
            <div v-if="showModal" class="fab-modal-bg">
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

                                <!-- Producto por UNIDAD -->
                                <div v-if="isUnitMode" class="fab-field">
                                    <label class="fab-label">Unidades producidas *</label>
                                    <div class="fab-unit-input-wrap">
                                        <input v-model="form.output_units" type="number" step="1" min="1" class="fab-input" placeholder="0" />
                                        <span class="fab-unit-suffix">piezas</span>
                                    </div>
                                    <p v-if="form.errors.output_units" class="fab-error">{{ form.errors.output_units }}</p>
                                </div>

                                <!-- Producto por PESO -->
                                <div v-else class="fab-field-row">
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
                                <template v-if="isUnitMode">
                                    <div class="fab-summary-row"><span>Piezas a producir</span><strong>{{ outputQty || '—' }} piezas</strong></div>
                                    <div class="fab-summary-row"><span>Insumos usados</span><strong>{{ totalInputKg.toFixed(3) }} u/kg</strong></div>
                                    <div class="fab-summary-row"><span>Costo total</span><strong>{{ fmtUsd(totalInputCost) }}</strong></div>
                                    <div class="fab-summary-row fab-summary-row--ok"><span>Costo/unidad</span><strong>{{ costoPorUnidad }}</strong></div>
                                </template>
                                <template v-else>
                                    <div class="fab-summary-row"><span>Insumos totales</span><strong>{{ fmtKg(totalInputKg) }}</strong></div>
                                    <div class="fab-summary-row"><span>Costo total</span><strong>{{ fmtUsd(totalInputCost) }}</strong></div>
                                    <div class="fab-summary-row"><span>Costo/kg fabricado</span><strong>{{ costoPorUnidad }}</strong></div>
                                    <div class="fab-summary-row fab-summary-row--ok"><span>Rendimiento</span><strong>{{ rendimiento }}%</strong></div>
                                </template>
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
                                            {{ fmtStock(prod.id) }}
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
                                            :step="ingredSaleMode(inp.product_id) === 'unit' ? 1 : 0.001"
                                            min="0.001"
                                            class="fab-input fab-input--qty"
                                            :placeholder="ingredSaleMode(inp.product_id) === 'unit' ? 'piezas' : 'kg'"
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

        <HelpModal
            :show="showHelp"
            title="Fábrica y Despiece"
            :steps="helpSteps"
            :faqs="helpFaqs"
            @close="showHelp = false"
        />

    </AppLayout>
</template>

<style scoped>
.fab-root { padding: 1.5rem; max-width: 1100px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.25rem; }

.fab-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
.fab-title  { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 0.625rem; flex-wrap: wrap; }
.fab-despiece-badge { font-size: 0.7rem; font-weight: 700; background: #a855f7; color: #fff; border-radius: 999px; padding: 0.2rem 0.6rem; vertical-align: middle; }
.fab-sub    { font-size: 0.875rem; color: var(--text-secondary); margin: 0; }

.fab-tabs        { display: flex; gap: 0.5rem; }
.fab-tab         { padding: 0.45rem 1rem; border-radius: 0.5rem; border: 1px solid var(--border); background: transparent; color: var(--text-secondary); font-size: 0.875rem; cursor: pointer; transition: all 0.15s; display: flex; align-items: center; gap: 0.4rem; }
.fab-tab--active { background: var(--brand); color: #fff; border-color: var(--brand); }
.fab-tab--help   { border-radius: 50%; width: 2rem; height: 2rem; padding: 0; font-weight: 700; justify-content: center; }
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
.fab-unit-input-wrap { display: flex; align-items: center; gap: 0.5rem; }
.fab-unit-suffix { font-size: 0.8rem; color: var(--text-secondary); white-space: nowrap; }
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

.btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.55rem 1.25rem; border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer; border: none; transition: opacity 0.15s; font-family: inherit; text-decoration: none; }
.btn-brand { background: var(--brand); color: #fff; }
.btn-brand:not(:disabled):hover { opacity: 0.88; }
.btn-brand:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-ghost { background: transparent; color: var(--text-secondary); border: 1px solid var(--border); }
.btn-ghost:hover { background: var(--bg-input); }

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

.fab-flash--despiece { background: color-mix(in srgb, #a855f7 15%, transparent); border-color: #a855f7; color: #a855f7; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; }
.despiece-pdf-actions { display: flex; align-items: center; gap: 0.5rem; }
.btn-pdf { background: #a855f7; color: #fff; border: none; border-radius: 0.375rem; padding: 0.35rem 0.75rem; font-size: 0.8rem; font-weight: 600; cursor: pointer; text-decoration: none; white-space: nowrap; }
.btn-pdf:hover { background: #9333ea; }
.btn-pdf-close { background: none; border: none; color: #a855f7; font-size: 1.2rem; cursor: pointer; line-height: 1; padding: 0 0.25rem; }

/* ── Despiece pendiente ───────────────────────────────────────────── */
.despiece-section {
    background: color-mix(in srgb, #a855f7 8%, var(--bg-card));
    border: 1.5px solid #a855f7;
    border-radius: 0.875rem;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.despiece-section-header {
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
}

.despiece-badge {
    background: #a855f7;
    color: #fff;
    font-size: 0.875rem;
    font-weight: 700;
    border-radius: 999px;
    width: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.despiece-title { font-size: 1rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.despiece-sub   { font-size: 0.8rem; color: var(--text-secondary); margin: 0; }

.despiece-list { display: flex; flex-direction: column; gap: 0.75rem; }

.despiece-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    overflow: hidden;
}

.despiece-card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    cursor: pointer;
    transition: background 0.15s;
}
.despiece-card-header:hover { background: var(--bg-input); }

.despiece-card-info { flex: 1; display: flex; flex-direction: column; gap: 0.15rem; }
.despiece-card-title    { font-size: 0.9375rem; font-weight: 600; color: var(--text-primary); }
.despiece-card-desc     { font-size: 0.8rem; color: var(--text-secondary); }
.despiece-card-supplier { font-size: 0.75rem; color: var(--text-muted); }

.despiece-card-kg { text-align: right; flex-shrink: 0; }
.despiece-kg-val   { display: block; font-size: 1.125rem; font-weight: 700; color: #a855f7; }
.despiece-kg-label { font-size: 0.7rem; color: var(--text-muted); }

.despiece-toggle { background: none; border: none; font-size: 0.75rem; color: var(--text-muted); cursor: pointer; padding: 0.25rem; }

.despiece-form {
    border-top: 1px solid var(--border);
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.despiece-form-hint { font-size: 0.8125rem; color: var(--text-secondary); margin: 0; }

.despiece-rows { display: flex; flex-direction: column; gap: 0.5rem; }
.despiece-row  { display: grid; grid-template-columns: 1fr 140px; gap: 0.75rem; align-items: center; }
.despiece-row-name { font-size: 0.875rem; color: var(--text-primary); }
.despiece-input { text-align: right; }

.despiece-totals {
    background: var(--bg-input);
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.despiece-total-row { display: flex; justify-content: space-between; font-size: 0.8125rem; color: var(--text-secondary); }
.despiece-total-row strong { color: var(--text-primary); }
.despiece-total-merma strong { color: #f59e0b; }

.despiece-error   { margin: 0; }
.despiece-over-limit { margin: 0; font-size: 0.8125rem; font-weight: 600; color: #ef4444; background: color-mix(in srgb, #ef4444 10%, transparent); border: 1px solid #ef4444; border-radius: 0.375rem; padding: 0.5rem 0.75rem; }

.despiece-actions { display: flex; justify-content: flex-end; }

@media (max-width: 640px) {
    .despiece-row { grid-template-columns: 1fr 110px; }
}

/* ── Responsividad móvil ──────────────────────────────────── */
@media (max-width: 640px) {
    .fab-root    { padding: 1rem 0.75rem; }
    .fab-header  { flex-direction: column; align-items: flex-start; }
    .fab-tabs    { width: 100%; justify-content: flex-start; flex-wrap: wrap; }
    .fab-tab     { flex: 1; justify-content: center; min-height: 40px; }
    .fab-tab--help { flex: none; }

    .fab-cards   { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }

    .fab-modal   { max-height: 100dvh; border-radius: 0; }
    .fab-modal-body { grid-template-columns: 1fr; overflow-y: auto; max-height: calc(100dvh - 130px); }
    .fab-modal-left  { border-right: none; border-bottom: 1px solid var(--border); }
    .fab-modal-right { max-height: 55vh; }

    .fab-selected-row { grid-template-columns: 1fr 70px 70px 24px; }

    .despiece-card-header { flex-wrap: wrap; gap: 0.5rem; }
    .despiece-card-kg     { text-align: left; }

    .fab-table th, .fab-table td { padding: 0.4rem 0.5rem; font-size: 0.75rem; }
    .fab-table .fab-creator { display: none; }
}
</style>
