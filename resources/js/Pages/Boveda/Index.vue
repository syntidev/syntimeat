<script setup>
import { ref, computed } from 'vue';
import AppLayout  from '@/Layouts/AppLayout.vue';
import HelpModal  from '@/Components/HelpModal.vue';

const props = defineProps({
    activas:          { type: Array,  default: () => [] },
    historial:        { type: Array,  default: () => [] },
    bovedaProducts:   { type: Array,  default: () => [] },
    productosVitrina: { type: Array,  default: () => [] },
    kpis:             { type: Object, default: () => ({}) },
});

// ─── Tab ──────────────────────────────────────────────────────────────────────
const tab = ref('activas');

// ─── Formatters ───────────────────────────────────────────────────────────────
function fmtKg(v)  { return Number(v || 0).toFixed(3) + ' kg'; }
function fmtUsd(v) { return '$ ' + Number(v || 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

function csrfToken() {
    return document.head.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function localDateTimeString() {
    const d = new Date()
    const pad = (n) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

// ─── Flash ────────────────────────────────────────────────────────────────────
const flash = ref('');
function showFlash(msg) {
    flash.value = msg;
    setTimeout(() => { flash.value = ''; }, 3000);
}

// ─── Modal Nueva Entrada ──────────────────────────────────────────────────────
const showEntradaModal = ref(false);
const savingEntrada    = ref(false);

const bovedaProductosActivos = computed(() => localBovedaProducts.value.filter(p => p.active));

const firstProductName = computed(() => bovedaProductosActivos.value[0]?.name ?? '');

const entradaForm = ref({
    product_type:      '',
    customProductType: '',
    description:       '',
    kg_entrada:        '',
    costo_usd:         '',
    supplier:          '',
    entered_at:        localDateTimeString(),
});
const entradaErrors = ref({});
const OTRO_LABEL = 'Otro (libre)';
const isCustomType = computed(() => entradaForm.value.product_type === OTRO_LABEL);

function openEntrada() {
    entradaErrors.value = {};
    entradaForm.value   = {
        product_type:      firstProductName.value || OTRO_LABEL,
        customProductType: '',
        description:       '',
        kg_entrada:        '',
        costo_usd:         '',
        supplier:          '',
        entered_at:        localDateTimeString(),
    };
    showEntradaModal.value = true;
}
async function saveEntrada() {
    if (savingEntrada.value) return;
    savingEntrada.value  = true;
    entradaErrors.value  = {};
    const payload = {
        product_type: isCustomType.value ? entradaForm.value.customProductType.trim() : entradaForm.value.product_type,
        description:  entradaForm.value.description,
        kg_entrada:   entradaForm.value.kg_entrada,
        costo_usd:    entradaForm.value.costo_usd,
        supplier:     entradaForm.value.supplier,
        entered_at:   entradaForm.value.entered_at,
    };
    try {
        const res = await fetch(route('boveda.store'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
        if (res.status === 422) {
            const body = await res.json();
            entradaErrors.value = body.errors ?? {};
            return;
        }
        if (!res.ok) throw new Error('Error al guardar');
        showEntradaModal.value = false;
        showFlash('Entrada registrada.');
        setTimeout(() => location.reload(), 600);
    } catch (e) {
        alert(e?.message ?? 'Error al guardar');
    } finally {
        savingEntrada.value = false;
    }
}

// ─── Modal Surtir ─────────────────────────────────────────────────────────────
const showSurtirModal   = ref(false);
const surtirEntry       = ref(null);
const surtirForm        = ref({ kg_surtir: '' });
const surtirErrors      = ref({});
const savingSurtir      = ref(false);
const despiecePendiente = ref(null); // { product_type, kg_surtir } cuando requires_despiece

function openSurtir(entry) {
    surtirEntry.value     = entry;
    surtirErrors.value    = {};
    surtirForm.value      = { kg_surtir: '' };
    showSurtirModal.value = true;
}
async function saveSurtir() {
    if (savingSurtir.value || !surtirEntry.value) return;
    savingSurtir.value = true;
    surtirErrors.value = {};
    try {
        const res = await fetch(route('boveda.surte', { entry: surtirEntry.value.id }), {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
            body: JSON.stringify(surtirForm.value),
        });
        if (res.status === 422) {
            const body = await res.json();
            surtirErrors.value = body.errors ?? {};
            return;
        }
        if (!res.ok) throw new Error('Error al surtir');
        const body = await res.json();
        showSurtirModal.value = false;
        if (body.requires_despiece) {
            despiecePendiente.value = { product_type: body.product_type, kg_surtir: body.kg_surtir };
        }
        showFlash('Surtido registrado.');
        setTimeout(() => location.reload(), 600);
    } catch (e) {
        alert(e?.message ?? 'Error al surtir');
    } finally {
        savingSurtir.value = false;
    }
}

// ─── Cerrar entrada ───────────────────────────────────────────────────────────
const closing = ref(null);
async function closeEntry(entry) {
    if (!confirm('¿Cerrar esta entrada? Ya no se podrá surtir más desde ella.')) return;
    closing.value = entry.id;
    try {
        const res = await fetch(route('boveda.close', { entry: entry.id }), {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
        });
        if (!res.ok) throw new Error('Error al cerrar');
        showFlash('Entrada cerrada.');
        setTimeout(() => location.reload(), 600);
    } catch (e) {
        alert(e?.message ?? 'Error al cerrar');
    } finally {
        closing.value = null;
    }
}

// ─── Computed validación surtir ───────────────────────────────────────────────
const excedeLimite = computed(() => {
    if (!surtirEntry.value) return false;
    return parseFloat(surtirForm.value.kg_surtir || 0) > surtirEntry.value.kg_disponible;
});

// ─── Modal Producto Bóveda ────────────────────────────────────────────────────
const showProductModal = ref(false);
const editingProduct   = ref(null);
const savingProduct    = ref(false);
const productForm      = ref({ name: '', unit: 'kg', requires_despiece: true, vitrina_product_id: null });
const productErrors    = ref({});

// Lista reactiva local (se actualiza optimistamente para no recargar página)
const localBovedaProducts = ref([...props.bovedaProducts]);

// ─── Ayuda ────────────────────────────────────────────────────────────────────
const showHelp = ref(false);

const helpSteps = [
    {
        icon: '📥',
        title: 'Registrar entrada',
        body: 'Cuando llega un canal de res, una paleta o cualquier pieza entera, créala aquí con el peso exacto (kg entrada), el costo en dólares y el proveedor.',
        tip: 'Usa el campo Descripción para identificar la pieza física: "Canal RES #2", "Paleta - Proveedor García". Así sabes cuál fila es cuál cuando tienes varias del mismo tipo.',
    },
    {
        icon: '⚖️',
        title: 'Registrar merma (si aplica)',
        body: 'Antes de despachar, pon la pieza en la balanza. Haz clic en Merma e ingresa el peso que ves ahora. El sistema calcula y registra la diferencia solo — tú no tienes que restar nada.',
        tip: 'Ejemplo: sistema muestra 50 kg disponibles, la balanza marca 48 kg → ingresas 48 → el sistema registra 2 kg de merma automáticamente.',
    },
    {
        icon: '🔪',
        title: 'Surtir a vitrina',
        body: 'Cuando llevas carne de esa pieza a la vitrina para venta al corte, haz clic en Surtir EN LA FILA de esa pieza. Indica los kg exactos que estás despachando. El sistema los descuenta de esa pieza y los suma al stock de vitrina.',
        tip: 'Puedes surtir varias veces de la misma pieza. Cada surtido va al historial con fecha y operador.',
    },
    {
        icon: '✅',
        title: 'Cerrar entrada',
        body: 'Cuando ya no queda nada de esa pieza en bóveda, ciérrala. Pasa al Historial con trazabilidad completa: cuánto entró, cuánto se surtió y cuánto mermó.',
    },
];

const helpFaqs = [
    {
        q: '¿Cómo sé cuál fila corresponde a la pieza que tengo en mano?',
        a: 'Cada fila ES una pieza física diferente. Al crear la entrada, escribe algo que te identifique la pieza en el campo Descripción ("Canal #4", "Paleta Juan García"). Al hacer Surtir en esa fila, el sistema registra que esos kg salieron exactamente de esa pieza.',
    },
    {
        q: '¿Qué es Surtir?',
        a: 'Surtir es el acto de llevar carne de la bóveda (donde se almacena en piezas enteras) a la vitrina (donde se vende al corte). Cuando surtres, el sistema descuenta kg de esa entrada específica y los agrega al inventario disponible en vitrina para ventas.',
    },
    {
        q: 'La carne mermó entre el lunes y el miércoles. ¿Cómo afecta el inventario?',
        a: 'Haz clic en el botón Merma de esa fila e ingresa el peso que marca la balanza ahora mismo. El sistema hace la resta: disponible actual menos el peso que ingresaste = merma. Tú no calculas nada. El kg disponible se corrige al instante.',
    },
    {
        q: '¿Puedo surtir parcialmente varias veces?',
        a: 'Sí. Puedes surtir 20 kg hoy y otros 15 kg mañana de la misma pieza. Cada surtido se descuenta del disponible de esa entrada. La entrada permanece activa hasta que la cierres manualmente.',
    },
    {
        q: '¿Qué son los "Productos bóveda"?',
        a: 'Es el catálogo de tipos de piezas que puedes recibir en bóveda (canal de res, paleta, costillar, etc.). Son diferentes a los productos de vitrina. Al crear una entrada, seleccionas el tipo de este catálogo.',
    },
];

// ─── Merma ────────────────────────────────────────────────────────────────────
const showMermaModal = ref(false);
const mermaEntry     = ref(null);
const mermaForm      = ref({ peso_actual: '' });
const mermaErrors    = ref({});
const savingMerma    = ref(false);

const mermaCalculada = computed(() => {
    if (!mermaEntry.value || !mermaForm.value.peso_actual) return null;
    const diff = mermaEntry.value.kg_disponible - parseFloat(mermaForm.value.peso_actual || 0);
    return diff > 0 ? diff.toFixed(3) : null;
});

function openMerma(entry) {
    mermaEntry.value     = entry;
    mermaForm.value      = { peso_actual: '' };
    mermaErrors.value    = {};
    showMermaModal.value = true;
}
async function saveMerma() {
    if (savingMerma.value || !mermaEntry.value) return;
    savingMerma.value = true;
    mermaErrors.value = {};
    try {
        const res = await fetch(route('boveda.merma', { entry: mermaEntry.value.id }), {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
            body: JSON.stringify(mermaForm.value),
        });
        if (res.status === 422) {
            const body = await res.json();
            mermaErrors.value = body.errors ?? {};
            return;
        }
        if (!res.ok) throw new Error('Error al registrar merma');
        showMermaModal.value = false;
        showFlash('Merma registrada.');
        setTimeout(() => location.reload(), 600);
    } catch (e) {
        alert(e?.message ?? 'Error al registrar merma');
    } finally {
        savingMerma.value = false;
    }
}

function openNewProduct() {
    editingProduct.value   = null;
    productErrors.value    = {};
    productForm.value      = { name: '', unit: 'kg', requires_despiece: true, vitrina_product_id: null };
    showProductModal.value = true;
}
function openEditProduct(product) {
    editingProduct.value   = product;
    productErrors.value    = {};
    productForm.value      = {
        name:               product.name,
        unit:               product.unit,
        requires_despiece:  product.requires_despiece,
        vitrina_product_id: product.vitrina_product_id ?? null,
    };
    showProductModal.value = true;
}
async function saveProduct() {
    if (savingProduct.value) return;
    savingProduct.value = true;
    productErrors.value = {};

    const isEdit = editingProduct.value !== null;
    const url    = isEdit
        ? route('boveda.product.update', { product: editingProduct.value.id })
        : route('boveda.product.store');
    const method = isEdit ? 'PUT' : 'POST';

    const payload = {
        name:               productForm.value.name,
        unit:               productForm.value.unit,
        requires_despiece:  productForm.value.requires_despiece,
        vitrina_product_id: productForm.value.requires_despiece ? null : (productForm.value.vitrina_product_id || null),
    };

    try {
        const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
        if (res.status === 422) {
            const body = await res.json();
            productErrors.value = body.errors ?? {};
            return;
        }
        if (!res.ok) throw new Error('Error al guardar');
        const body = await res.json();
        if (isEdit) {
            const idx = localBovedaProducts.value.findIndex(p => p.id === editingProduct.value.id);
            if (idx !== -1) localBovedaProducts.value[idx] = body.product;
        } else {
            localBovedaProducts.value.push(body.product);
        }
        showProductModal.value = false;
        showFlash(isEdit ? 'Producto actualizado.' : 'Producto creado.');
    } catch (e) {
        alert(e?.message ?? 'Error al guardar');
    } finally {
        savingProduct.value = false;
    }
}
async function deactivateProduct(product) {
    if (!confirm(`¿Desactivar "${product.name}"? No aparecerá en el selector de entradas.`)) return;
    try {
        const res = await fetch(route('boveda.product.destroy', { product: product.id }), {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
        });
        if (!res.ok) throw new Error('Error al desactivar');
        const idx = localBovedaProducts.value.findIndex(p => p.id === product.id);
        if (idx !== -1) localBovedaProducts.value[idx] = { ...localBovedaProducts.value[idx], active: false };
        showFlash('Producto desactivado.');
    } catch (e) {
        alert(e?.message ?? 'Error al desactivar');
    }
}
</script>

<template>
    <AppLayout title="Bóveda">

        <!-- Flash -->
        <Transition name="fl">
            <div v-if="flash" class="flash-msg">{{ flash }}</div>
        </Transition>

        <div class="boveda-page">

            <!-- Header -->
            <div class="page-header">
                <div class="header-left">
                    <span class="header-icon">🏚️</span>
                    <div>
                        <h1 class="page-title">Bóveda</h1>
                        <p class="page-sub">Control de canales y piezas enteras</p>
                    </div>
                </div>
                <div class="header-actions">
                    <button class="btn-help" @click="showHelp = true" title="Ayuda">?</button>
                    <button class="btn-brand" @click="openEntrada">+ Nueva entrada</button>
                </div>
            </div>

            <!-- KPIs -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <p class="kpi-lbl">Entradas activas</p>
                    <p class="kpi-val">{{ kpis.entradasActivas ?? 0 }}</p>
                </div>
                <div class="kpi-card">
                    <p class="kpi-lbl">Kg disponibles</p>
                    <p class="kpi-val">{{ fmtKg(kpis.kgDisponible) }}</p>
                </div>
                <div class="kpi-card">
                    <p class="kpi-lbl">Costo activo</p>
                    <p class="kpi-val">{{ fmtUsd(kpis.costoActivo) }}</p>
                </div>
                <div class="kpi-card">
                    <p class="kpi-lbl">Surtido hoy</p>
                    <p class="kpi-val">{{ fmtKg(kpis.surtidoHoy) }}</p>
                </div>
            </div>

            <!-- Alerta despiece pendiente -->
            <Transition name="fl">
                <div v-if="despiecePendiente" class="despiece-alert">
                    <span class="despiece-alert__icon">🔪</span>
                    <div class="despiece-alert__body">
                        <strong>Despiece pendiente:</strong>
                        {{ despiecePendiente.product_type }} — {{ Number(despiecePendiente.kg_surtir).toFixed(3) }} kg enviados a vitrina.
                        Registra los cortes resultantes desde el módulo de Despiece.
                    </div>
                    <a :href="route('despiece.index')" class="despiece-alert__btn">Ir a Despiece →</a>
                    <button class="despiece-alert__close" @click="despiecePendiente = null">×</button>
                </div>
            </Transition>

            <!-- Tabs -->
            <div class="tabs-row">
                <button class="tab-btn" :class="{ active: tab === 'activas' }" @click="tab = 'activas'">
                    Activas ({{ activas.length }})
                </button>
                <button class="tab-btn" :class="{ active: tab === 'historial' }" @click="tab = 'historial'">
                    Historial
                </button>
                <button class="tab-btn" :class="{ active: tab === 'productos' }" @click="tab = 'productos'">
                    Productos bóveda ({{ localBovedaProducts.length }})
                </button>
            </div>

            <!-- Tabla Activas -->
            <div v-if="tab === 'activas'">
                <div v-if="!activas.length" class="empty-state">
                    <div class="empty-icon">📭</div>
                    <p>No hay entradas activas en bóveda.</p>
                </div>
                <div v-else class="table-wrap">
                    <table class="bov-table">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th>Producto</th>
                                <th>Kg entrada</th>
                                <th>Costo</th>
                                <th>Kg surtido</th>
                                <th>Kg disponible</th>
                                <th>Proveedor</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in activas" :key="e.id">
                                <td>{{ e.description || '—' }}</td>
                                <td><span class="product-badge">{{ e.product_type }}</span></td>
                                <td class="num">{{ fmtKg(e.kg_entrada) }}</td>
                                <td class="num">{{ fmtUsd(e.costo_usd) }}</td>
                                <td class="num">{{ fmtKg(e.kg_surtido_vitrina) }}</td>
                                <td class="num" :class="e.kg_disponible <= 0 ? 'text-muted' : 'text-ok'">
                                    {{ fmtKg(e.kg_disponible) }}
                                </td>
                                <td>{{ e.supplier || '—' }}</td>
                                <td class="date-col">{{ e.entered_at }}</td>
                                <td class="actions-col">
                                    <button
                                        class="btn-sm btn-merma"
                                        :disabled="e.kg_disponible <= 0"
                                        @click="openMerma(e)"
                                        title="Registrar merma (pérdida de peso)"
                                    >Merma</button>
                                    <button
                                        class="btn-sm btn-surtir"
                                        :disabled="e.kg_disponible <= 0"
                                        @click="openSurtir(e)"
                                        title="Llevar kg a vitrina"
                                    >Surtir</button>
                                    <button
                                        class="btn-sm btn-close"
                                        :disabled="closing === e.id"
                                        @click="closeEntry(e)"
                                    >Cerrar</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Historial -->
            <div v-if="tab === 'historial'">
                <div v-if="!historial.length" class="empty-state">
                    <div class="empty-icon">📭</div>
                    <p>Sin entradas cerradas aún.</p>
                </div>
                <div v-else class="table-wrap">
                    <table class="bov-table">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th>Producto</th>
                                <th>Kg entrada</th>
                                <th>Costo</th>
                                <th>Kg surtido</th>
                                <th>Proveedor</th>
                                <th>Ingresó</th>
                                <th>Cerró</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in historial" :key="e.id" class="row-closed">
                                <td>{{ e.description || '—' }}</td>
                                <td><span class="product-badge">{{ e.product_type }}</span></td>
                                <td class="num">{{ fmtKg(e.kg_entrada) }}</td>
                                <td class="num">{{ fmtUsd(e.costo_usd) }}</td>
                                <td class="num">{{ fmtKg(e.kg_surtido_vitrina) }}</td>
                                <td>{{ e.supplier || '—' }}</td>
                                <td class="date-col">{{ e.entered_at }}</td>
                                <td class="date-col">{{ e.closed_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab Productos Bóveda -->
            <div v-if="tab === 'productos'">
                <div class="products-header">
                    <p class="products-hint">Catálogo de productos bóveda. Los activos aparecen en el selector de nuevas entradas.</p>
                    <button class="btn-brand" @click="openNewProduct">+ Agregar producto</button>
                </div>
                <div v-if="!localBovedaProducts.length" class="empty-state">
                    <div class="empty-icon">📦</div>
                    <p>No hay productos bóveda. Agrega el primero.</p>
                </div>
                <div v-else class="table-wrap">
                    <table class="bov-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Unidad</th>
                                <th>Flujo</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in localBovedaProducts" :key="p.id" :class="{ 'row-closed': !p.active }">
                                <td>{{ p.name }}</td>
                                <td><span class="unit-badge">{{ p.unit }}</span></td>
                                <td>
                                    <span v-if="p.requires_despiece" class="flujo-badge flujo-despiece">Despiece</span>
                                    <span v-else class="flujo-badge flujo-directo">Directo</span>
                                </td>
                                <td>
                                    <span v-if="p.active" class="status-badge status-active">Activo</span>
                                    <span v-else class="status-badge status-inactive">Inactivo</span>
                                </td>
                                <td class="actions-col">
                                    <button class="btn-sm btn-surtir" @click="openEditProduct(p)">Editar</button>
                                    <button
                                        v-if="p.active"
                                        class="btn-sm btn-close"
                                        @click="deactivateProduct(p)"
                                    >Desactivar</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ── Modal Nueva Entrada ─────────────────────────────────────────── -->
        <Teleport to="body">
            <Transition name="mo">
                <div v-if="showEntradaModal" class="modal-bg" @click.self="showEntradaModal = false">
                    <div class="modal-box">
                        <div class="modal-header">
                            <h3>Nueva entrada bóveda</h3>
                            <button class="close-btn" @click="showEntradaModal = false">×</button>
                        </div>

                        <div class="form-grid">
                            <div class="form-field full">
                                <label>Tipo de producto bóveda</label>
                                <select v-model="entradaForm.product_type" class="form-select">
                                    <option v-for="p in bovedaProductosActivos" :key="p.id" :value="p.name">{{ p.name }}</option>
                                    <option :value="OTRO_LABEL">{{ OTRO_LABEL }}</option>
                                </select>
                                <span v-if="entradaErrors.product_type" class="field-err">{{ entradaErrors.product_type[0] }}</span>
                            </div>
                            <div v-if="isCustomType" class="form-field full">
                                <label>Especificar tipo</label>
                                <input v-model="entradaForm.customProductType" type="text" class="form-input" maxlength="80" placeholder="Ej: Res Madurada, Cerdo Entero…" />
                            </div>
                            <div class="form-field full">
                                <label>Descripción (opcional)</label>
                                <input v-model="entradaForm.description" type="text" class="form-input" maxlength="100" placeholder="Ej: Media canal #3" />
                                <span v-if="entradaErrors.description" class="field-err">{{ entradaErrors.description[0] }}</span>
                            </div>
                            <div class="form-field">
                                <label>Kg entrada</label>
                                <input v-model="entradaForm.kg_entrada" type="number" class="form-input" min="0.001" step="0.001" placeholder="0.000" />
                                <span v-if="entradaErrors.kg_entrada" class="field-err">{{ entradaErrors.kg_entrada[0] }}</span>
                            </div>
                            <div class="form-field">
                                <label>Costo USD (total)</label>
                                <input v-model="entradaForm.costo_usd" type="number" class="form-input" min="0" step="0.01" placeholder="0.00" />
                                <span v-if="entradaErrors.costo_usd" class="field-err">{{ entradaErrors.costo_usd[0] }}</span>
                            </div>
                            <div class="form-field">
                                <label>Proveedor</label>
                                <input v-model="entradaForm.supplier" type="text" class="form-input" maxlength="100" placeholder="Nombre proveedor" />
                            </div>
                            <div class="form-field">
                                <label>Fecha / Hora</label>
                                <input v-model="entradaForm.entered_at" type="datetime-local" class="form-input" />
                                <span v-if="entradaErrors.entered_at" class="field-err">{{ entradaErrors.entered_at[0] }}</span>
                            </div>
                        </div>

                        <div class="modal-actions">
                            <button class="btn-ghost" @click="showEntradaModal = false">Cancelar</button>
                            <button class="btn-brand" :disabled="savingEntrada" @click="saveEntrada">
                                {{ savingEntrada ? 'Guardando…' : 'Registrar entrada' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Modal Surtir ───────────────────────────────────────────────── -->
        <Teleport to="body">
            <Transition name="mo">
                <div v-if="showSurtirModal" class="modal-bg" @click.self="showSurtirModal = false">
                    <div class="modal-box modal-sm">
                        <div class="modal-header">
                            <h3>Surtir a vitrina</h3>
                            <button class="close-btn" @click="showSurtirModal = false">×</button>
                        </div>

                        <p class="surtir-info">
                            Pieza: <strong>{{ surtirEntry?.product_type }}</strong>
                            <span v-if="surtirEntry?.description"> — {{ surtirEntry.description }}</span><br>
                            Disponible: <strong>{{ fmtKg(surtirEntry?.kg_disponible) }}</strong>
                        </p>
                        <p class="merma-hint">
                            Indica cuántos kg físicos estás moviendo a vitrina.
                            Los cortes se registran desde vitrina después.
                        </p>

                        <div class="form-grid">
                            <div class="form-field full">
                                <label>Kg a mover a vitrina</label>
                                <input
                                    v-model="surtirForm.kg_surtir"
                                    type="number"
                                    class="form-input"
                                    :class="{ 'input-error': excedeLimite }"
                                    min="0.001"
                                    step="0.001"
                                    placeholder="0.000"
                                    autofocus
                                />
                                <span v-if="excedeLimite" class="field-err">
                                    Excede disponible ({{ fmtKg(surtirEntry?.kg_disponible) }})
                                </span>
                                <span v-if="surtirErrors.kg_surtir" class="field-err">{{ surtirErrors.kg_surtir[0] }}</span>
                            </div>
                        </div>

                        <div class="modal-actions">
                            <button class="btn-ghost" @click="showSurtirModal = false">Cancelar</button>
                            <button
                                class="btn-brand"
                                :disabled="savingSurtir || excedeLimite || !surtirForm.kg_surtir"
                                @click="saveSurtir"
                            >
                                {{ savingSurtir ? 'Surtiendo…' : 'Confirmar surtido' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Modal Merma ───────────────────────────────────────────────── -->
        <Teleport to="body">
            <Transition name="mo">
                <div v-if="showMermaModal" class="modal-bg" @click.self="showMermaModal = false">
                    <div class="modal-box modal-sm">
                        <div class="modal-header">
                            <h3>Registrar merma</h3>
                            <button class="close-btn" @click="showMermaModal = false">×</button>
                        </div>

                        <p class="surtir-info">
                            Pieza: <strong>{{ mermaEntry?.product_type }}</strong>
                            <span v-if="mermaEntry?.description"> — {{ mermaEntry.description }}</span><br>
                            Disponible actual: <strong>{{ fmtKg(mermaEntry?.kg_disponible) }}</strong>
                        </p>
                        <p class="merma-hint">
                            Pon la pieza en la balanza y anota el peso que ves ahora.
                            El sistema calcula la merma automáticamente.
                        </p>

                        <div class="form-grid">
                            <div class="form-field full">
                                <label>Peso actual de la pieza (kg)</label>
                                <input
                                    v-model="mermaForm.peso_actual"
                                    type="number"
                                    class="form-input"
                                    min="0"
                                    step="0.001"
                                    placeholder="Ej: 48.000"
                                    autofocus
                                />
                                <span v-if="mermaErrors.peso_actual" class="field-err">{{ mermaErrors.peso_actual[0] }}</span>
                            </div>
                        </div>

                        <!-- Preview merma calculada -->
                        <div v-if="mermaCalculada" class="merma-preview">
                            <span class="merma-preview__label">Merma calculada:</span>
                            <span class="merma-preview__val">{{ mermaCalculada }} kg</span>
                            <span class="merma-preview__eq">
                                ({{ fmtKg(mermaEntry?.kg_disponible) }} disponible − {{ mermaForm.peso_actual }} kg actual)
                            </span>
                        </div>
                        <div v-else-if="mermaForm.peso_actual && !mermaCalculada" class="merma-preview merma-preview--warn">
                            El peso actual debe ser menor que el disponible ({{ fmtKg(mermaEntry?.kg_disponible) }})
                        </div>

                        <div class="modal-actions">
                            <button class="btn-ghost" @click="showMermaModal = false">Cancelar</button>
                            <button
                                class="btn-brand btn-merma-confirm"
                                :disabled="savingMerma || !mermaCalculada"
                                @click="saveMerma"
                            >
                                {{ savingMerma ? 'Registrando…' : 'Confirmar merma' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Modal Producto Bóveda ─────────────────────────────────────── -->
        <Teleport to="body">
            <Transition name="mo">
                <div v-if="showProductModal" class="modal-bg" @click.self="showProductModal = false">
                    <div class="modal-box modal-sm">
                        <div class="modal-header">
                            <h3>{{ editingProduct ? 'Editar producto' : 'Nuevo producto bóveda' }}</h3>
                            <button class="close-btn" @click="showProductModal = false">×</button>
                        </div>

                        <div class="form-grid">
                            <div class="form-field full">
                                <label>Nombre</label>
                                <input v-model="productForm.name" type="text" class="form-input" maxlength="100" placeholder="Ej: Medio Canal Res" autofocus />
                                <span v-if="productErrors.name" class="field-err">{{ productErrors.name[0] }}</span>
                            </div>
                            <div class="form-field full">
                                <label>Unidad</label>
                                <select v-model="productForm.unit" class="form-select">
                                    <option value="kg">kg</option>
                                    <option value="unidad">unidad</option>
                                    <option value="pieza">pieza</option>
                                    <option value="caja">caja</option>
                                </select>
                            </div>
                            <div class="form-field full">
                                <label class="toggle-label">
                                    <span>Requiere despiece</span>
                                    <span class="toggle-hint">Actívalo para canales/piezas enteras que hay que cortar antes de vender.</span>
                                </label>
                                <div
                                    class="toggle-wrap"
                                    @click="productForm.requires_despiece = !productForm.requires_despiece"
                                >
                                    <div class="toggle-track" :class="{ 'toggle-track--on': productForm.requires_despiece }">
                                        <div class="toggle-thumb" :class="{ 'toggle-thumb--on': productForm.requires_despiece }"></div>
                                    </div>
                                    <span class="toggle-text">{{ productForm.requires_despiece ? 'Sí — pasa por Despiece' : 'No — va directo a vitrina' }}</span>
                                </div>
                            </div>
                            <div v-if="!productForm.requires_despiece" class="form-field full">
                                <label>Producto vitrina destino</label>
                                <select v-model="productForm.vitrina_product_id" class="form-select">
                                    <option :value="null">— Seleccionar —</option>
                                    <option v-for="p in productosVitrina" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                                <span class="toggle-hint">El stock de este producto en vitrina aumentará automáticamente al surtir.</span>
                                <span v-if="productErrors.vitrina_product_id" class="field-err">{{ productErrors.vitrina_product_id[0] }}</span>
                            </div>
                        </div>

                        <div class="modal-actions">
                            <button class="btn-ghost" @click="showProductModal = false">Cancelar</button>
                            <button class="btn-brand" :disabled="savingProduct || !productForm.name.trim()" @click="saveProduct">
                                {{ savingProduct ? 'Guardando…' : (editingProduct ? 'Actualizar' : 'Crear producto') }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Panel de ayuda ────────────────────────────────────────────── -->
        <HelpModal
            :show="showHelp"
            title="Bóveda — Cómo funciona"
            :steps="helpSteps"
            :faqs="helpFaqs"
            @close="showHelp = false"
        />

    </AppLayout>
</template>

<style scoped>
/* ─── Page ───────────────────────────────────────────────────────────────────*/
.boveda-page { padding: 1.5rem; max-width: 1200px; margin: 0 auto; }

/* ─── Flash ──────────────────────────────────────────────────────────────────*/
.flash-msg { position: fixed; top: 1rem; right: 1rem; z-index: 900; background: var(--green, #10b981); color: #fff; border-radius: 8px; padding: 0.6rem 1.2rem; font-size: 0.9rem; font-weight: 600; box-shadow: 0 4px 16px rgba(0,0,0,.25); }
.fl-enter-active, .fl-leave-active { transition: opacity .2s, transform .2s; }
.fl-enter-from, .fl-leave-to { opacity: 0; transform: translateY(-8px); }

/* ─── Header ─────────────────────────────────────────────────────────────────*/
.page-header   { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; gap: 1rem; flex-wrap: wrap; }
.header-left   { display: flex; align-items: center; gap: 0.75rem; }
.header-actions { display: flex; align-items: center; gap: 0.6rem; }
.header-icon   { font-size: 2rem; }
.page-title    { font-size: 1.4rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.page-sub      { font-size: 0.82rem; color: var(--text-muted); margin: 0; }

.btn-help {
    width: 32px; height: 32px;
    border-radius: 50%;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text-muted);
    font-size: 14px; font-weight: 700;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.btn-help:hover { background: var(--brand); color: #fff; border-color: var(--brand); }

/* ─── KPIs ───────────────────────────────────────────────────────────────────*/
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 0.75rem; margin-bottom: 1.25rem; }
.kpi-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; padding: 0.9rem 1rem; }
.kpi-lbl  { font-size: 0.75rem; color: var(--text-muted); margin: 0 0 0.25rem; }
.kpi-val  { font-size: 1.35rem; font-weight: 700; color: var(--text-primary); margin: 0; }

/* ─── Tabs ───────────────────────────────────────────────────────────────────*/
.tabs-row { display: flex; gap: 0.4rem; margin-bottom: 1rem; }
.tab-btn  { border: 1px solid var(--border); border-radius: 8px; padding: 0.35rem 0.9rem; background: transparent; cursor: pointer; font-family: inherit; font-size: 0.85rem; color: var(--text-muted); }
.tab-btn.active { border-color: var(--brand); color: var(--brand); font-weight: 600; background: rgba(37,99,235,0.07); }

/* ─── Empty ──────────────────────────────────────────────────────────────────*/
.empty-state { display: flex; flex-direction: column; align-items: center; padding: 3rem 1rem; gap: 0.5rem; color: var(--text-muted); }
.empty-icon  { font-size: 2.5rem; }

/* ─── Table ──────────────────────────────────────────────────────────────────*/
.table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; min-width: 0; border-radius: 10px; border: 1px solid var(--border); }
.bov-table  { width: 100%; min-width: 560px; border-collapse: collapse; font-size: 0.85rem; background: var(--bg-card); }
.bov-table th { padding: 0.6rem 0.8rem; text-align: left; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); background: var(--bg); border-bottom: 1px solid var(--border); white-space: nowrap; }
.bov-table td { padding: 0.6rem 0.8rem; border-bottom: 1px solid var(--border); color: var(--text-primary); vertical-align: middle; }
.bov-table tr:last-child td { border-bottom: none; }
.num       { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.date-col  { white-space: nowrap; color: var(--text-muted); font-size: 0.8rem; }
.text-ok   { color: var(--green, #10b981); font-weight: 600; }
.text-muted { color: var(--text-muted); }
.row-closed td { opacity: 0.65; }
.actions-col { white-space: nowrap; display: flex; gap: 0.4rem; align-items: center; }

/* ─── Badges ─────────────────────────────────────────────────────────────────*/
.product-badge { background: rgba(37,99,235,0.1); color: var(--brand); border-radius: 20px; padding: 0.15rem 0.55rem; font-size: 0.78rem; font-weight: 600; white-space: nowrap; }

/* ─── Botones tabla ──────────────────────────────────────────────────────────*/
.btn-sm { border: none; border-radius: 6px; padding: 0.25rem 0.6rem; font-size: 0.78rem; font-weight: 600; cursor: pointer; font-family: inherit; white-space: nowrap; }
.btn-sm:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-merma  { background: rgba(245,158,11,0.12); color: #d97706; }
.btn-merma:not(:disabled):hover { background: rgba(245,158,11,0.22); }
.btn-surtir { background: rgba(37,99,235,0.12); color: var(--brand); }
.btn-surtir:not(:disabled):hover { background: rgba(37,99,235,0.22); }
.btn-close  { background: rgba(239,68,68,0.1); color: #ef4444; }
.btn-close:not(:disabled):hover { background: rgba(239,68,68,0.2); }
.btn-merma-confirm { background: #d97706; color: #fff; }
.btn-merma-confirm:not(:disabled):hover { background: #b45309; }
.merma-hint { font-size: 0.82rem; color: var(--text-muted); margin: 0; line-height: 1.4; }

.merma-preview {
    background: rgba(245,158,11,0.1);
    border: 1px solid rgba(245,158,11,0.35);
    border-radius: 8px;
    padding: 0.65rem 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.merma-preview--warn {
    background: rgba(239,68,68,0.08);
    border-color: rgba(239,68,68,0.3);
    font-size: 0.82rem;
    color: #ef4444;
}
.merma-preview__label { font-size: 0.8rem; color: var(--text-muted); }
.merma-preview__val   { font-size: 1.1rem; font-weight: 700; color: #d97706; }
.merma-preview__eq    { font-size: 0.78rem; color: var(--text-muted); }

/* ─── Modal ──────────────────────────────────────────────────────────────────*/
.modal-bg  { position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 500; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.modal-box { background: var(--bg-card); border-radius: 14px; width: 100%; max-width: 520px; max-height: 92vh; overflow-y: auto; padding: 1.25rem; display: flex; flex-direction: column; gap: 0.85rem; }
.modal-sm  { max-width: 360px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { font-size: 1rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.close-btn { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: var(--text-muted); line-height: 1; padding: 0; }

/* ─── Formulario ─────────────────────────────────────────────────────────────*/
.form-grid  { display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem; }
.form-field { display: flex; flex-direction: column; gap: 0.3rem; }
.form-field.full { grid-column: 1 / -1; }
.form-field label { font-size: 0.78rem; font-weight: 600; color: var(--text-muted); }
.form-input, .form-select { padding: 0.5rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); color: var(--text-primary); font-size: 0.88rem; font-family: inherit; width: 100%; box-sizing: border-box; }
.form-input:focus, .form-select:focus { outline: none; border-color: var(--brand); }
.input-error { border-color: #ef4444 !important; }
.field-err { font-size: 0.75rem; color: #ef4444; }

.surtir-info { font-size: 0.88rem; color: var(--text-muted); margin: 0; padding: 0.6rem; background: var(--bg); border-radius: 8px; }
.surtir-info strong { color: var(--brand); }

.surtir-cats { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.cat-chip { border: 1px solid var(--border); border-radius: 999px; padding: .2rem .65rem; background: transparent; cursor: pointer; font-family: inherit; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); transition: all .12s; }
.cat-chip--active { border-color: var(--brand); background: rgba(37,99,235,.1); color: var(--brand); }
.cat-chip:hover:not(.cat-chip--active) { border-color: var(--text-muted); }

/* ─── Botones globales ───────────────────────────────────────────────────────*/
.btn-brand { background: var(--brand); color: #fff; border: none; border-radius: 8px; padding: 0.5rem 1.1rem; font-size: 0.88rem; font-weight: 600; cursor: pointer; font-family: inherit; transition: opacity .15s; }
.btn-brand:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-brand:not(:disabled):hover { opacity: 0.88; }
.btn-ghost { border: 1px solid var(--border); border-radius: 8px; padding: 0.45rem 1rem; background: none; cursor: pointer; font-family: inherit; font-size: 0.88rem; color: var(--text-muted); }
.btn-ghost:hover { border-color: var(--brand); color: var(--brand); }

/* ─── Acciones ───────────────────────────────────────────────────────────────*/
.modal-actions { display: flex; gap: 0.6rem; justify-content: flex-end; }

/* ─── Toggle ─────────────────────────────────────────────────────────────────*/
.toggle-label     { display: flex; flex-direction: column; gap: 0.15rem; font-size: 0.78rem; font-weight: 600; color: var(--text-muted); }
.toggle-hint      { font-size: 0.73rem; color: var(--text-muted); font-weight: 400; line-height: 1.3; }
.toggle-wrap      { display: flex; align-items: center; gap: 0.55rem; cursor: pointer; user-select: none; }
.toggle-track     { width: 38px; height: 22px; border-radius: 999px; background: var(--border); position: relative; transition: background 0.2s; flex-shrink: 0; }
.toggle-track--on { background: var(--brand); }
.toggle-thumb     { width: 16px; height: 16px; border-radius: 50%; background: #fff; position: absolute; top: 3px; left: 3px; transition: left 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,.25); }
.toggle-thumb--on { left: 19px; }
.toggle-text      { font-size: 0.85rem; color: var(--text-primary); }

/* ─── Flujo badges ───────────────────────────────────────────────────────────*/
.flujo-badge   { border-radius: 20px; padding: 0.15rem 0.55rem; font-size: 0.75rem; font-weight: 600; white-space: nowrap; }
.flujo-despiece { background: rgba(139,92,246,0.12); color: #8b5cf6; }
.flujo-directo  { background: rgba(16,185,129,0.12); color: #10b981; }

/* ─── Alerta despiece ────────────────────────────────────────────────────────*/
.despiece-alert {
    display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
    background: rgba(139,92,246,0.1); border: 1px solid rgba(139,92,246,0.35);
    border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 1rem;
}
.despiece-alert__icon  { font-size: 1.3rem; flex-shrink: 0; }
.despiece-alert__body  { flex: 1; font-size: 0.85rem; color: var(--text-primary); line-height: 1.4; }
.despiece-alert__body strong { color: #8b5cf6; }
.despiece-alert__btn   { background: #8b5cf6; color: #fff; border: none; border-radius: 7px; padding: 0.35rem 0.85rem; font-size: 0.82rem; font-weight: 600; cursor: pointer; font-family: inherit; text-decoration: none; white-space: nowrap; }
.despiece-alert__btn:hover { opacity: 0.85; }
.despiece-alert__close { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-muted); line-height: 1; padding: 0; margin-left: auto; }

/* ─── Transición modal ───────────────────────────────────────────────────────*/
.mo-enter-active, .mo-leave-active { transition: opacity .18s; }
.mo-enter-from, .mo-leave-to { opacity: 0; }

/* ─── Tab Productos ──────────────────────────────────────────────────────────*/
.products-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; gap: 0.75rem; flex-wrap: wrap; }
.products-hint   { font-size: 0.82rem; color: var(--text-muted); margin: 0; }
.unit-badge { background: rgba(107,114,128,0.12); color: var(--text-muted); border-radius: 6px; padding: 0.1rem 0.45rem; font-size: 0.78rem; font-weight: 600; }
.status-badge { border-radius: 20px; padding: 0.15rem 0.55rem; font-size: 0.75rem; font-weight: 600; }
.status-active   { background: rgba(16,185,129,0.12); color: #10b981; }
.status-inactive { background: rgba(239,68,68,0.1); color: #ef4444; }
</style>
