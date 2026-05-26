<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout  from '@/Layouts/AppLayout.vue';
import HelpModal  from '@/Components/HelpModal.vue';
import { Warehouse, Scissors, Factory, CheckCircle2, Package, Printer } from '@lucide/vue';

const props = defineProps({
    activas:          { type: Array,  default: () => [] },
    historial:        { type: Array,  default: () => [] },
    bovedaProducts:   { type: Array,  default: () => [] },
    productosVitrina: { type: Array,  default: () => [] },
    categorias:       { type: Array,  default: () => [] },
    kpis:             { type: Object, default: () => ({}) },
});

// ─── Tab ──────────────────────────────────────────────────────────────────────
const tab = ref('activas');

// ─── Formatters ───────────────────────────────────────────────────────────────
function fmtKg(v)  { return Number(v || 0).toFixed(3) + ' kg'; }
function fmtKg2(v) { return Number(v || 0).toFixed(2) + ' kg'; }
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
    conCanal2:         false,
    kg_par:            '',
    costo_usd_par:     '',
});
const entradaErrors = ref({});
const OTRO_LABEL = 'Otro (libre)';
const isCustomType = computed(() => entradaForm.value.product_type === OTRO_LABEL);

const kgTotal19    = computed(() => (entradaForm.value.kg_entrada || 0) + (entradaForm.value.kg_par || 0))
const costoTotal19 = computed(() => (entradaForm.value.costo_usd || 0) + (entradaForm.value.costo_usd_par || 0))
const prorrateo1   = computed(() =>
    kgTotal19.value > 0
        ? (costoTotal19.value * ((entradaForm.value.kg_entrada || 0) / kgTotal19.value)).toFixed(2)
        : '0.00'
)
const prorrateo2   = computed(() =>
    kgTotal19.value > 0
        ? (costoTotal19.value * ((entradaForm.value.kg_par || 0) / kgTotal19.value)).toFixed(2)
        : '0.00'
)

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
        conCanal2:         false,
        kg_par:            '',
        costo_usd_par:     '',
    };
    showEntradaModal.value = true;
}
async function saveEntrada() {
    if (savingEntrada.value) return;
    savingEntrada.value  = true;
    entradaErrors.value  = {};
    const isRes = entradaForm.value.product_type === 'RES - Medio Canal';
    const payload = {
        product_type: isCustomType.value ? entradaForm.value.customProductType.trim() : entradaForm.value.product_type,
        description:  entradaForm.value.description,
        kg_entrada:   entradaForm.value.kg_entrada,
        costo_usd:    entradaForm.value.costo_usd,
        supplier:     entradaForm.value.supplier,
        entered_at:   entradaForm.value.entered_at,
        ...(isRes && entradaForm.value.conCanal2 ? { kg_par: entradaForm.value.kg_par, costo_usd_par: entradaForm.value.costo_usd_par } : {}),
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
        router.reload({ only: ['activas', 'historial', 'kpis'] });
    } catch (e) {
        alert(e?.message ?? 'Error al guardar');
    } finally {
        savingEntrada.value = false;
    }
}

// ─── Modal Surtir ─────────────────────────────────────────────────────────────
const showSurtirModal   = ref(false);
const surtirEntry       = ref(null);
const surtirForm        = ref({ peso_real: '', pollo_tipo: '' });
const surtirErrors      = ref({});
const savingSurtir      = ref(false);
const DESPIECE_KEY      = 'boveda_despiece_pendiente';
const isPollo           = computed(() => surtirEntry.value?.product_type === 'POLLO - Entero Congelado');
const despiecePendiente = ref(null);

// ─── Modal Crear Producto Vitrina (fallback cuando surte() no encuentra producto) ──
const showVitrinaProductModal  = ref(false);
const productoFaltante         = ref(null);   // { nombre }
const savingVitrinaProduct     = ref(false);
const vitrinaProductErrors     = ref({});
const vitrinaProductForm       = ref({ name: '', price_per_kg_usd: '', category_id: null });

const mermaPreview = computed(() => {
    if (!surtirEntry.value || !surtirForm.value.peso_real) return null;
    const diff = round3(surtirEntry.value.kg_disponible - parseFloat(surtirForm.value.peso_real || 0));
    return diff >= 0 ? diff : null;
});

function round3(v) { return Math.round(v * 1000) / 1000; }

onMounted(() => {
    const saved = sessionStorage.getItem(DESPIECE_KEY);
    if (saved) {
        despiecePendiente.value = JSON.parse(saved);
        sessionStorage.removeItem(DESPIECE_KEY);
    }
});

function openSurtir(entry) {
    surtirEntry.value     = entry;
    surtirErrors.value    = {};
    surtirForm.value      = { peso_real: '', pollo_tipo: '' };
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
            body: JSON.stringify({
                peso_real:  surtirForm.value.peso_real,
                ...(isPollo.value ? { pollo_tipo: surtirForm.value.pollo_tipo } : {}),
            }),
        });
        if (res.status === 422) {
            const body = await res.json();
            // Error específico: producto no existe en vitrina → abrir modal de creación
            if (body.error && body.error.includes('No existe producto en vitrina')) {
                productoFaltante.value       = { nombre: surtirEntry.value.product_type };
                vitrinaProductForm.value     = { name: surtirEntry.value.product_type, price_per_kg_usd: '', category_id: null };
                vitrinaProductErrors.value   = {};
                showVitrinaProductModal.value = true;
                return;
            }
            surtirErrors.value = body.errors ?? {};
            return;
        }
        if (!res.ok) throw new Error('Error al surtir');
        const body = await res.json();
        showSurtirModal.value = false;
        if (body.requires_despiece) {
            despiecePendiente.value = {
                product_type: body.product_type,
                kg_surtir:    body.kg_surtir,
            };
        }
        showFlash('Surtido registrado.');
        router.reload({ only: ['activas', 'historial', 'kpis'] });
    } catch (e) {
        alert(e?.message ?? 'Error al surtir');
    } finally {
        savingSurtir.value = false;
    }
}

async function saveVitrinaProduct() {
    if (savingVitrinaProduct.value) return;
    savingVitrinaProduct.value  = true;
    vitrinaProductErrors.value  = {};
    try {
        const res = await fetch(route('catalog.store'), {
            method:      'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
            body:    JSON.stringify({
                name:             vitrinaProductForm.value.name,
                category_id:      vitrinaProductForm.value.category_id,
                sale_mode:        'weight',
                price_per_kg_usd: vitrinaProductForm.value.price_per_kg_usd,
            }),
        });
        if (res.status === 422) {
            const body = await res.json();
            vitrinaProductErrors.value = body.errors ?? {};
            return;
        }
        if (!res.ok) throw new Error('Error al crear el producto en vitrina');
        const body         = await res.json();
        const newProductId = body.product?.id ?? null;

        // Vincular en boveda_products para que el próximo surtido del mismo tipo sea directo (best-effort)
        if (newProductId !== null) {
            await linkVitrinaProduct(surtirEntry.value.product_type, newProductId);
        }

        showVitrinaProductModal.value = false;
        productoFaltante.value        = null;
        showFlash('Producto creado en vitrina. Reintentando surtido…');
        await saveSurtir();
    } catch (e) {
        alert(e?.message ?? 'Error al crear producto');
    } finally {
        savingVitrinaProduct.value = false;
    }
}

async function linkVitrinaProduct(name, vitrinaProductId) {
    const existing = localBovedaProducts.value.find(p => p.name === name);
    try {
        const res = existing
            ? await fetch(route('boveda.product.update', { product: existing.id }), {
                method:      'PUT',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                body:    JSON.stringify({ name, requires_despiece: false, vitrina_product_id: vitrinaProductId }),
            })
            : await fetch(route('boveda.product.store'), {
                method:      'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                body:    JSON.stringify({ name, requires_despiece: false, vitrina_product_id: vitrinaProductId }),
            });
        if (!res.ok) return;
        const body = await res.json();
        if (!body.product) return;
        if (existing) {
            const idx = localBovedaProducts.value.findIndex(p => p.id === existing.id);
            if (idx !== -1) localBovedaProducts.value[idx] = body.product;
        } else {
            localBovedaProducts.value.push(body.product);
        }
    } catch (e) {
        // best-effort: si falla el vínculo, el surtido igual procede por match exacto de nombre
    }
}

const excedeLimite = computed(() => {
    if (!surtirEntry.value) return false;
    return parseFloat(surtirForm.value.peso_real || 0) > surtirEntry.value.kg_disponible;
});

watch(() => surtirForm.value.peso_real, (val) => {
    const disponible = surtirEntry.value?.kg_disponible;
    if (!disponible) return;
    const diff = Math.abs(parseFloat(val) - parseFloat(disponible));
    if (diff <= 0.5 && diff > 0) {
        surtirForm.value.peso_real = parseFloat(disponible);
    }
});

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
        router.reload({ only: ['activas', 'historial', 'kpis'] });
    } catch (e) {
        alert(e?.message ?? 'Error al cerrar');
    } finally {
        closing.value = null;
    }
}


// ─── Modal Producto Bóveda ────────────────────────────────────────────────────
const showProductModal = ref(false);
const editingProduct   = ref(null);
const savingProduct    = ref(false);
const productForm      = ref({ name: '', unit: 'kg', requires_despiece: true, vitrina_product_id: null });
const productErrors    = ref({});
const deactivating     = ref(null);

// Lista reactiva local (se actualiza optimistamente para no recargar página)
const localBovedaProducts = ref([...props.bovedaProducts]);

// ─── Ayuda ────────────────────────────────────────────────────────────────────
const showHelp = ref(false);

const helpSteps = [
    {
        title: 'Tipos de entrada en Bóveda',
        body: 'Registra toda carne que llega al almacenamiento frío. Hay cuatro tipos: RES - Medio Canal (pasa a despiece en Fábrica), POLLO - Entero Congelado (va directo a vitrina al surtir), CERDO - Canal (pasa a despiece en Fábrica), y Otro (libre) para cualquier producto diferente que también va directo a vitrina.',
        tip: 'El tipo que selecciones determina si la pieza pasa por Fábrica o va directo a vitrina. Selecciónalo bien antes de guardar.',
    },
    {
        title: 'Surtir Res o Cerdo → va a Fábrica',
        body: 'Cuando llevas el Medio Canal de Res o el Canal de Cerdo al área de corte, pésalo y registra el peso en "Surtir". El sistema lo envía automáticamente a Fábrica y Despiece para que el carnicero documente cada corte.',
        tip: 'Si no ves la pieza en Fábrica después de surtir, verifica que el estado en Bóveda diga "Surtido".',
    },
    {
        title: 'Surtir Pollo → directo a vitrina',
        body: 'Al surtir un POLLO - Entero Congelado, el sistema te pregunta si es Tipo A o Tipo B. Selecciona la opción correcta y los kg van directo al inventario de vitrina. El Pollo nunca pasa por Fábrica.',
        tip: 'Tipo A y Tipo B son las dos presentaciones de pollo que manejas en vitrina. Si tienes dudas sobre cuál es cuál, consulta con tu supervisor antes de confirmar.',
    },
    {
        title: 'Surtir "Otro (libre)" → vitrina con vínculo',
        body: 'Para productos libres (como Chuleta Ahumada o Jamón especial), el nombre que escribes debe coincidir exactamente con el producto en vitrina. Si el producto todavía no existe, el sistema abre un modal para crearlo en ese momento.',
        tip: 'Una vez creado el vínculo, los próximos surtidos del mismo producto lo detectarán automáticamente. El nombre debe ser idéntico — mayúsculas, acentos y todo.',
    },
    {
        title: 'Campo "Quedan sin surtir"',
        body: 'Este número muestra los kg que quedan disponibles en bóveda después del surtido que acabas de declarar. No es merma — es el stock que todavía está en el almacén frío esperando ser surtido en otra ocasión.',
        tip: 'La merma real se registra al cerrar la entrada. Mientras la entrada esté abierta, puedes seguir haciendo surtidos parciales.',
    },
    {
        title: 'Cerrar entrada',
        body: 'Cuando ya no queda nada de esa pieza en bóveda, ciérrala. El sistema calcula la merma de almacenamiento final y la entrada pasa al Historial con trazabilidad completa: cuánto entró, cuánto se surtió y cuánto mermó.',
        tip: 'Solo cierra la entrada cuando la pieza ya no está en el frío. Si todavía queda carne, puedes hacer otro surtido después.',
    },
];

const helpFaqs = [
    {
        q: '¿Cuándo va a Fábrica y cuándo va directo a vitrina?',
        a: 'RES - Medio Canal y CERDO - Canal van a Fábrica porque hay que documentar los cortes. POLLO - Entero Congelado y los productos "Otro (libre)" van directo al inventario de vitrina sin pasar por Fábrica.',
    },
    {
        q: '¿Por qué al surtir el Pollo me pregunta Tipo A o Tipo B?',
        a: 'POLLO - Entero Congelado tiene dos presentaciones en vitrina. El sistema necesita saber a cuál de las dos abonar los kg. Si no estás seguro de cuál es cuál, consulta el catálogo o con tu supervisor antes de confirmar.',
    },
    {
        q: '¿Qué significa "Quedan sin surtir"?',
        a: 'Son los kg que quedan disponibles en bóveda después del surtido que declaraste. No es merma — es stock que todavía está en el frío disponible para un próximo surtido. La merma real se calcula al cerrar la entrada.',
    },
    {
        q: 'El producto "Otro" que escribí no conecta con vitrina, ¿qué hago?',
        a: 'El nombre debe coincidir exactamente con el producto de vitrina, incluyendo mayúsculas y acentos. Si el producto no existe todavía, el sistema te ofrece crearlo en ese momento desde un modal. Una vez creado, el vínculo queda guardado para futuros surtidos.',
    },
    {
        q: '¿Puedo surtir varias veces de la misma entrada?',
        a: 'Sí. Puedes surtir 30 kg hoy y otros 20 kg mañana de la misma pieza. El sistema descuenta cada surtido del disponible y la entrada se mantiene activa hasta que la cierres manualmente.',
    },
    {
        q: '¿Cuándo debo cerrar una entrada?',
        a: 'Cuando ya no queda nada en bóveda de esa pieza. Al cerrar, el sistema registra la merma de almacenamiento final y la entrada pasa al Historial. Si todavía queda carne en el frío, no la cierres — espera a hacer el último surtido.',
    },
];


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
    if (deactivating.value === product.id) return;
    if (!confirm(`¿Desactivar "${product.name}"? No aparecerá en el selector de entradas.`)) return;
    deactivating.value = product.id;
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
    } finally {
        deactivating.value = null;
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
                    <span class="header-icon"><Warehouse :size="28" /></span>
                    <div>
                        <h1 class="page-title">Bóveda</h1>
                        <p class="page-sub">Control de canales y piezas enteras</p>
                    </div>
                </div>
                <div class="header-actions">
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
                    <span class="despiece-alert__icon"><Scissors :size="20" /></span>
                    <div class="despiece-alert__body">
                        <strong>Despiece pendiente:</strong>
                        {{ despiecePendiente.product_type }} — {{ Number(despiecePendiente.kg_surtir).toFixed(3) }} kg enviados a vitrina.
                        Registra los cortes resultantes desde el módulo de Fábrica.
                    </div>
                    <a :href="route('fabrica.index')" class="despiece-alert__btn">Ir a Fábrica →</a>
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
                <button class="tab-btn tab-btn--help" @click="showHelp = true" title="Ayuda">?</button>
            </div>

            <!-- Tabla Activas -->
            <div v-if="tab === 'activas'">
                <div v-if="!activas.length" class="empty-state">
                    <div class="empty-icon"><Package :size="36" /></div>
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
                                <td class="num">{{ fmtKg2(e.kg_entrada) }}</td>
                                <td class="num">{{ fmtUsd(e.costo_usd) }}</td>
                                <td class="num">{{ fmtKg2(e.kg_surtido_vitrina) }}</td>
                                <td class="num" :class="e.kg_disponible <= 0 ? 'text-muted' : 'text-ok'">
                                    {{ fmtKg2(e.kg_disponible) }}
                                </td>
                                <td>{{ e.supplier || '—' }}</td>
                                <td class="date-col">{{ e.entered_at }}</td>
                                <td class="actions-col">
                                    <button
                                        class="btn-sm btn-surtir"
                                        :disabled="e.kg_disponible <= 0"
                                        @click="openSurtir(e)"
                                        title="Registrar peso y surtir a vitrina"
                                    >Surtir</button>
                                    <a
                                        v-if="e.requires_despiece && e.kg_surtido_vitrina > 0"
                                        :href="route('boveda.plantilla', { entry: e.id })"
                                        target="_blank"
                                        class="btn-sm btn-plantilla"
                                        title="Imprimir planilla de despiece"
                                    ><Printer :size="13" style="vertical-align:middle;margin-right:4px;" />Planilla</a>
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
                    <div class="empty-icon"><Package :size="36" /></div>
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
                                <td class="num">{{ fmtKg2(e.kg_entrada) }}</td>
                                <td class="num">{{ fmtUsd(e.costo_usd) }}</td>
                                <td class="num">{{ fmtKg2(e.kg_surtido_vitrina) }}</td>
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
                    <div class="empty-icon"><Package :size="36" /></div>
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
                                        :disabled="deactivating === p.id"
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
                <div v-if="showEntradaModal" class="modal-bg">
                    <div class="modal-box">
                        <div class="modal-header">
                            <h3>Nueva entrada bóveda</h3>
                            <button class="close-btn" @click="showEntradaModal = false">×</button>
                        </div>

                        <div class="form-grid">
                            <div class="form-field full">
                                <label>Tipo de producto bóveda</label>
                                <template v-if="bovedaProductosActivos.length > 10">
                                    <input
                                        v-model="entradaForm.product_type"
                                        list="boveda-productos-list"
                                        class="form-input"
                                        placeholder="Escribe para buscar…"
                                        autocomplete="off"
                                    />
                                    <datalist id="boveda-productos-list">
                                        <option v-for="p in bovedaProductosActivos" :key="p.id" :value="p.name" />
                                        <option :value="OTRO_LABEL" />
                                    </datalist>
                                </template>
                                <select v-else v-model="entradaForm.product_type" class="form-select">
                                    <option v-for="p in bovedaProductosActivos" :key="p.id" :value="p.name">{{ p.name }}</option>
                                    <option :value="OTRO_LABEL">Otro tipo de producto</option>
                                </select>
                                <span v-if="entradaErrors.product_type && !isCustomType" class="field-err">{{ entradaErrors.product_type[0] }}</span>
                                <p v-if="entradaForm.product_type === OTRO_LABEL" class="field-hint">
                                    Escribe el nombre exacto del producto tal como lo configuraste en "Productos bóveda".
                                    Ejemplo: Chuleta Ahumada, Jamón Especial, etc.
                                </p>
                            </div>
                            <div v-if="isCustomType" class="form-field full">
                                <label>Especificar tipo</label>
                                <input v-model="entradaForm.customProductType" type="text" class="form-input" maxlength="80" placeholder="Ej: Res Madurada, Cerdo Entero…" />
                                <p v-if="entradaErrors.product_type && isCustomType" class="text-red-500 text-xs mt-1">{{ entradaErrors.product_type[0] }}</p>
                            </div>
                            <div class="form-field full">
                                <label>Descripción (opcional)</label>
                                <input v-model="entradaForm.description" type="text" class="form-input" maxlength="100" placeholder="Ej: Media canal #3" />
                                <span v-if="entradaErrors.description" class="field-err">{{ entradaErrors.description[0] }}</span>
                            </div>
                            <div class="form-field">
                                <label>Kg entrada</label>
                                <input v-model.number="entradaForm.kg_entrada" type="number" class="form-input" min="0.001" step="0.001" placeholder="0.000" />
                                <span v-if="entradaErrors.kg_entrada" class="field-err">{{ entradaErrors.kg_entrada[0] }}</span>
                            </div>
                            <!-- Dual canal — solo RES Medio Canal -->
                            <template v-if="entradaForm.product_type === 'RES - Medio Canal'">
                                <div class="form-field full">
                                    <label class="dual-toggle">
                                        <input type="checkbox" v-model="entradaForm.conCanal2" />
                                        <span>¿Ingresan dos piezas?</span>
                                    </label>
                                </div>
                                <div v-if="entradaForm.conCanal2" class="form-field full">
                                    <div class="dual-fields">
                                        <div class="field-group">
                                            <label>Kg Pieza 2</label>
                                            <input type="number" step="0.001" min="0.001" v-model.number="entradaForm.kg_par"
                                                   placeholder="0.000" class="form-input" />
                                            <span v-if="entradaErrors.kg_par" class="field-err">{{ entradaErrors.kg_par[0] }}</span>
                                        </div>
                                        <div class="field-group">
                                            <label>Costo Pieza 2 (USD)</label>
                                            <input type="number" step="0.01" min="0" v-model.number="entradaForm.costo_usd_par"
                                                   placeholder="0.00" class="form-input" />
                                        </div>
                                        <p v-if="entradaForm.kg_entrada > 0 && entradaForm.kg_par > 0 && (entradaForm.costo_usd + entradaForm.costo_usd_par) > 0"
                                           class="dual-preview">
                                            Pieza 1: ${{ prorrateo1 }} · Pieza 2: ${{ prorrateo2 }}
                                        </p>
                                    </div>
                                </div>
                            </template>
                            <div class="form-field">
                                <label>Costo USD (total)</label>
                                <input v-model.number="entradaForm.costo_usd" type="number" class="form-input" min="0" step="0.01" placeholder="0.00" />
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
                <div v-if="showSurtirModal" class="modal-bg">
                    <div class="modal-box modal-sm">
                        <div class="modal-header">
                            <h3>Surtir a vitrina</h3>
                            <button class="close-btn" @click="showSurtirModal = false">×</button>
                        </div>

                        <p class="surtir-info">
                            <strong>{{ surtirEntry?.product_type }}</strong>
                            <span v-if="surtirEntry?.description"> — {{ surtirEntry.description }}</span><br>
                            Entró: <strong>{{ fmtKg(surtirEntry?.kg_entrada) }}</strong>
                        </p>

                        <p class="field-hint">Disponibles: {{ surtirEntry?.kg_disponible?.toFixed(3) }} kg</p>

                        <div class="form-grid">
                            <div class="form-field full">
                                <label>Peso en balanza al sacar (kg)</label>
                                <div class="input-row">
                                    <input
                                        v-model="surtirForm.peso_real"
                                        type="number"
                                        class="form-input"
                                        :class="{ 'input-error': excedeLimite }"
                                        min="0.001"
                                        step="0.001"
                                        placeholder="0.000"
                                        autofocus
                                    />
                                    <button
                                        type="button"
                                        class="text-xs text-brand underline ml-2"
                                        @click="surtirForm.peso_real = surtirEntry.kg_disponible"
                                    >Máximo</button>
                                </div>
                                <span v-if="excedeLimite" class="field-err">
                                    Supera el disponible ({{ fmtKg(surtirEntry?.kg_disponible) }})
                                </span>
                                <span v-if="surtirErrors.peso_real" class="field-err">{{ surtirErrors.peso_real[0] }}</span>
                            </div>
                        </div>

                        <div v-if="mermaPreview !== null && mermaPreview > 0" class="merma-preview">
                            <span class="merma-preview__label">Quedan sin surtir:</span>
                            <span class="merma-preview__val">{{ mermaPreview.toFixed(3) }} kg</span>
                            <span class="merma-preview__eq">(los kg restantes quedan disponibles para surtidos futuros)</span>
                        </div>

                        <div v-if="isPollo" class="form-field full" style="margin-top: 0.5rem;">
                            <label>Tipo de pollo</label>
                            <select v-model="surtirForm.pollo_tipo" class="form-select" required>
                                <option value="">-- Selecciona el tipo --</option>
                                <option value="Pollo Entero Tipo A">Pollo Entero Tipo A</option>
                                <option value="Pollo Entero Tipo B">Pollo Entero Tipo B</option>
                            </select>
                        </div>

                        <div class="modal-actions">
                            <button class="btn-ghost" @click="showSurtirModal = false">Cancelar</button>
                            <button
                                class="btn-brand"
                                :disabled="savingSurtir || excedeLimite || !surtirForm.peso_real || (isPollo && !surtirForm.pollo_tipo)"
                                @click="saveSurtir"
                            >
                                {{ savingSurtir ? 'Registrando…' : 'Registrar surtido' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>


        <!-- ── Modal Crear Producto Vitrina ──────────────────────────────── -->
        <Teleport to="body">
            <Transition name="mo">
                <div v-if="showVitrinaProductModal" class="modal-bg">
                    <div class="modal-box modal-sm">
                        <div class="modal-header">
                            <h3>Crear producto en vitrina</h3>
                            <button class="close-btn" @click="showVitrinaProductModal = false">×</button>
                        </div>

                        <p class="surtir-info">
                            El producto <strong>{{ productoFaltante?.nombre }}</strong> no existe en vitrina.
                            Completa los datos para crearlo y continuar con el surtido automáticamente.
                        </p>

                        <div class="form-grid">
                            <div class="form-field full">
                                <label>Nombre del producto</label>
                                <input
                                    v-model="vitrinaProductForm.name"
                                    type="text"
                                    class="form-input"
                                    maxlength="120"
                                    autofocus
                                />
                                <span v-if="vitrinaProductErrors.name" class="field-err">{{ vitrinaProductErrors.name[0] }}</span>
                            </div>
                            <div class="form-field full">
                                <label>Precio por kg (USD)</label>
                                <input
                                    v-model.number="vitrinaProductForm.price_per_kg_usd"
                                    type="number"
                                    class="form-input"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00"
                                />
                                <span v-if="vitrinaProductErrors.price_per_kg_usd" class="field-err">{{ vitrinaProductErrors.price_per_kg_usd[0] }}</span>
                            </div>
                            <div class="form-field full">
                                <label>Categoría</label>
                                <select v-model="vitrinaProductForm.category_id" class="form-select">
                                    <option :value="null">— Seleccionar categoría —</option>
                                    <option v-for="c in categorias" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                                <span v-if="vitrinaProductErrors.category_id" class="field-err">{{ vitrinaProductErrors.category_id[0] }}</span>
                            </div>
                        </div>

                        <div class="modal-actions">
                            <button class="btn-ghost" @click="showVitrinaProductModal = false">Cancelar</button>
                            <button
                                class="btn-brand"
                                :disabled="savingVitrinaProduct || !vitrinaProductForm.name.trim() || !vitrinaProductForm.price_per_kg_usd || !vitrinaProductForm.category_id"
                                @click="saveVitrinaProduct"
                            >
                                {{ savingVitrinaProduct ? 'Creando…' : 'Crear y continuar surtido' }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Modal Producto Bóveda ─────────────────────────────────────── -->
        <Teleport to="body">
            <Transition name="mo">
                <div v-if="showProductModal" class="modal-bg">
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

.tab-btn--help {
    margin-left: auto;
    border-radius: 50%;
    width: 2rem; height: 2rem;
    padding: 0;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text-muted);
    font-size: 14px; font-weight: 700;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.tab-btn--help:hover { background: var(--brand); color: #fff; border-color: var(--brand); }

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
.btn-plantilla { background: rgba(124,58,237,0.12); color: #7c3aed; text-decoration: none; display: inline-flex; align-items: center; }
.btn-plantilla:hover { background: rgba(124,58,237,0.22); }
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
.canal-par-row   { padding: 0.25rem 0; }
.canal-par-label { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; cursor: pointer; color: var(--text-primary); }
.canal-par-check { width: 1rem; height: 1rem; accent-color: var(--brand); cursor: pointer; }
.form-field { display: flex; flex-direction: column; gap: 0.3rem; }
.form-field.full { grid-column: 1 / -1; }
.form-field label { font-size: 0.78rem; font-weight: 600; color: var(--text-muted); }
.form-input, .form-select { padding: 0.5rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); color: var(--text-primary); font-size: 0.88rem; font-family: inherit; width: 100%; box-sizing: border-box; }
.form-input:focus, .form-select:focus { outline: none; border-color: var(--brand); }
.input-error { border-color: #ef4444 !important; }
.field-err  { font-size: 0.75rem; color: #ef4444; }
.field-hint { font-size: 0.75rem; color: var(--text-muted); margin: 0.15rem 0 0; line-height: 1.4; }

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

.dual-toggle { display:flex; align-items:center; gap:8px; cursor:pointer;
               color:var(--text-primary); font-size:0.875rem; }
.dual-fields { display:flex; flex-direction:column; gap:12px;
               padding:12px; background:var(--bg-elevated);
               border-radius:8px; border:1px solid var(--border); }
.dual-preview { font-size:0.78rem; color:var(--text-secondary);
                margin:0; padding-top:4px; }
.field-group  { display:flex; flex-direction:column; gap:4px; }
.field-group label { font-size:0.8rem; color:var(--text-secondary); }
</style>
