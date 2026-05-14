<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    activas:          { type: Array,  default: () => [] },
    historial:        { type: Array,  default: () => [] },
    productosVitrina: { type: Array,  default: () => [] },
    bovedaProducts:   { type: Array,  default: () => [] },
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
    entered_at:        new Date().toISOString().slice(0, 16),
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
        entered_at:        new Date().toISOString().slice(0, 16),
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
const showSurtirModal = ref(false);
const surtirEntry     = ref(null);
const surtirForm      = ref({ product_id: '', kg_surtir: '' });
const surtirErrors    = ref({});
const savingSurtir    = ref(false);
const surtirCatFilter = ref('Todos');

const surtirCatList = computed(() => {
    const cats = [...new Set(props.productosVitrina.map(p => p.category_name).filter(Boolean))].sort();
    return ['Todos', ...cats];
});
const productosFiltered = computed(() => {
    if (surtirCatFilter.value === 'Todos') return props.productosVitrina;
    return props.productosVitrina.filter(p => p.category_name === surtirCatFilter.value);
});

function openSurtir(entry) {
    surtirEntry.value     = entry;
    surtirErrors.value    = {};
    surtirCatFilter.value = 'Todos';
    surtirForm.value      = {
        product_id: props.productosVitrina[0]?.id ?? '',
        kg_surtir:  '',
    };
    showSurtirModal.value = true;
}
async function saveSurtir() {
    if (savingSurtir.value || !surtirEntry.value) return;
    savingSurtir.value  = true;
    surtirErrors.value  = {};
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
        showSurtirModal.value = false;
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
const editingProduct   = ref(null); // null = crear, objeto = editar
const savingProduct    = ref(false);
const productForm      = ref({ name: '', unit: 'kg' });
const productErrors    = ref({});

// Lista reactiva local (se actualiza optimistamente para no recargar página)
const localBovedaProducts = ref([...props.bovedaProducts]);

function openNewProduct() {
    editingProduct.value   = null;
    productErrors.value    = {};
    productForm.value      = { name: '', unit: 'kg' };
    showProductModal.value = true;
}
function openEditProduct(product) {
    editingProduct.value   = product;
    productErrors.value    = {};
    productForm.value      = { name: product.name, unit: product.unit };
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

    try {
        const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
            body: JSON.stringify(productForm.value),
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
                <button class="btn-brand" @click="openEntrada">+ Nueva entrada</button>
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
                                        class="btn-sm btn-surtir"
                                        :disabled="e.kg_disponible <= 0"
                                        @click="openSurtir(e)"
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
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in localBovedaProducts" :key="p.id" :class="{ 'row-closed': !p.active }">
                                <td>{{ p.name }}</td>
                                <td><span class="unit-badge">{{ p.unit }}</span></td>
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
                            <h3>Surtir vitrina</h3>
                            <button class="close-btn" @click="showSurtirModal = false">×</button>
                        </div>

                        <p class="surtir-info">
                            Disponible:
                            <strong>{{ fmtKg(surtirEntry?.kg_disponible) }}</strong>
                            de <em>{{ surtirEntry?.product_type }}</em>
                        </p>

                        <div class="form-grid">
                            <div class="form-field full">
                                <label>Categoría destino</label>
                                <div class="surtir-cats">
                                    <button
                                        v-for="cat in surtirCatList"
                                        :key="cat"
                                        class="cat-chip"
                                        :class="{ 'cat-chip--active': surtirCatFilter === cat }"
                                        @click="surtirCatFilter = cat; surtirForm.product_id = productosFiltered[0]?.id ?? ''"
                                    >{{ cat }}</button>
                                </div>
                            </div>
                            <div class="form-field full">
                                <label>Producto destino (vitrina)</label>
                                <select v-model="surtirForm.product_id" class="form-select">
                                    <option v-for="p in productosFiltered" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                                <span v-if="surtirErrors.product_id" class="field-err">{{ surtirErrors.product_id[0] }}</span>
                            </div>
                            <div class="form-field full">
                                <label>Kg a surtir</label>
                                <input v-model="surtirForm.kg_surtir" type="number" class="form-input" :class="{ 'input-error': excedeLimite }" min="0.001" step="0.001" placeholder="0.000" />
                                <span v-if="excedeLimite" class="field-err">Excede kg disponibles ({{ fmtKg(surtirEntry?.kg_disponible) }})</span>
                                <span v-if="surtirErrors.kg_surtir" class="field-err">{{ surtirErrors.kg_surtir[0] }}</span>
                            </div>
                        </div>

                        <div class="modal-actions">
                            <button class="btn-ghost" @click="showSurtirModal = false">Cancelar</button>
                            <button class="btn-brand" :disabled="savingSurtir || excedeLimite || !surtirForm.kg_surtir" @click="saveSurtir">
                                {{ savingSurtir ? 'Surtiendo…' : 'Confirmar surtido' }}
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
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; gap: 1rem; flex-wrap: wrap; }
.header-left { display: flex; align-items: center; gap: 0.75rem; }
.header-icon { font-size: 2rem; }
.page-title  { font-size: 1.4rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.page-sub    { font-size: 0.82rem; color: var(--text-muted); margin: 0; }

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
.table-wrap { overflow-x: auto; border-radius: 10px; border: 1px solid var(--border); }
.bov-table  { width: 100%; border-collapse: collapse; font-size: 0.85rem; background: var(--bg-card); }
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
.btn-surtir { background: rgba(37,99,235,0.12); color: var(--brand); }
.btn-surtir:not(:disabled):hover { background: rgba(37,99,235,0.22); }
.btn-close  { background: rgba(239,68,68,0.1); color: #ef4444; }
.btn-close:not(:disabled):hover { background: rgba(239,68,68,0.2); }

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
