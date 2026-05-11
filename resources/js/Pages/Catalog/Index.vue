<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'

const props = defineProps({
    categories: { type: Array, default: () => [] },
    products:   { type: Array, default: () => [] },
})

// ─── Tabs ─────────────────────────────────────────────────────────────────────
const activeTab = ref(props.categories[0]?.id ?? null)

const tabProducts = computed(() =>
    props.products.filter(p => p.category_id === activeTab.value)
)

// ─── KPIs ─────────────────────────────────────────────────────────────────────
const kpis = computed(() => ({
    total:      props.products.length,
    categories: props.categories.length,
    byKg:       props.products.filter(p => p.sale_mode === 'weight').length,
    byUnit:     props.products.filter(p => p.sale_mode === 'unit').length,
}))

// ─── Modal nuevo producto ─────────────────────────────────────────────────────
const showModal   = ref(false)
const editProduct = ref(null)

const form = useForm({
    name:               '',
    category_id:        props.categories[0]?.id ?? '',
    subcategory_id:     '',
    sale_mode:          'weight',
    price_per_kg_usd:   '',
    price_per_unit_usd: '',
    min_stock:          0,
    active:             true,
})

const activeCategory = computed(() =>
    props.categories.find(c => c.id === Number(form.category_id))
)

const hasSubcategories = computed(() =>
    (activeCategory.value?.subcategories?.length ?? 0) > 0
)

function openNew() {
    editProduct.value = null
    form.reset()
    form.category_id = props.categories[0]?.id ?? ''
    form.sale_mode   = 'weight'
    showModal.value  = true
}

function openEdit(product) {
    editProduct.value = product
    form.name               = product.name
    form.category_id        = product.category_id
    form.subcategory_id     = product.subcategory_id ?? ''
    form.sale_mode          = product.sale_mode
    form.price_per_kg_usd   = product.price_per_kg_usd ?? ''
    form.price_per_unit_usd = product.price_per_unit_usd ?? ''
    form.min_stock          = product.min_stock ?? 0
    form.active             = product.active
    showModal.value         = true
}

function closeModal() {
    showModal.value = false
    editProduct.value = null
    form.reset()
}

function submitForm() {
    const payload = { ...form.data() }
    if (!hasSubcategories.value) payload.subcategory_id = null

    if (editProduct.value) {
        form.transform(() => payload).put(route('catalog.update', editProduct.value.id), {
            onSuccess: () => closeModal(),
        })
    } else {
        form.transform(() => payload).post(route('catalog.store'), {
            onSuccess: () => closeModal(),
        })
    }
}

function toggleActive(product) {
    router.put(route('catalog.update', product.id), {
        ...product,
        active: !product.active,
    }, { preserveScroll: true })
}

// ─── Stock utils ──────────────────────────────────────────────────────────────
function stockStatus(product) {
    if (product.sale_mode !== 'weight') return 'unit'
    const stock = Number(product.current_stock ?? 0)
    const min   = Number(product.min_stock ?? 0)
    if (stock <= 0)   return 'empty'
    if (stock <= min) return 'low'
    return 'ok'
}

function stockLabel(product) {
    const st = stockStatus(product)
    if (st === 'unit') return '—'
    const stock = Number(product.current_stock ?? 0)
    return `${stock.toFixed(2)} kg`
}

function priceDisplay(product) {
    if (product.sale_mode === 'weight') {
        return product.price_per_kg_usd
            ? `$${Number(product.price_per_kg_usd).toFixed(2)}/kg`
            : '—'
    }
    return product.price_per_unit_usd
        ? `$${Number(product.price_per_unit_usd).toFixed(2)}/und`
        : '—'
}

function categoryColor(id) {
    return props.categories.find(c => c.id === id)?.color ?? '#6B7280'
}

// ─── Sección categorías ───────────────────────────────────────────────────────
const mainTab = ref('products') // 'products' | 'categories'

// Modal categoría
const showCatModal   = ref(false)
const editCategory   = ref(null)
const catForm = useForm({ name: '', color: '#6B7280', icon: '' })

function openNewCat() {
    editCategory.value = null
    catForm.reset()
    catForm.color = '#6B7280'
    showCatModal.value = true
}
function openEditCat(cat) {
    editCategory.value = cat
    catForm.name  = cat.name
    catForm.color = cat.color ?? '#6B7280'
    catForm.icon  = cat.icon ?? ''
    showCatModal.value = true
}
function closeCatModal() {
    showCatModal.value = false
    editCategory.value = null
    catForm.reset()
}
function submitCatForm() {
    if (editCategory.value) {
        catForm.put(route('catalog.category.update', editCategory.value.id), {
            onSuccess: () => closeCatModal(),
        })
    } else {
        catForm.post(route('catalog.category.store'), {
            onSuccess: () => closeCatModal(),
        })
    }
}
function destroyCategory(cat) {
    if (!confirm(`¿Eliminar la categoría "${cat.name}"?`)) return
    router.delete(route('catalog.category.destroy', cat.id), { preserveScroll: true })
}
function catProductCount(catId) {
    return props.products.filter(p => p.category_id === catId).length
}

// Modal subcategoría
const showSubModal   = ref(false)
const editSubcat     = ref(null)
const subParentId    = ref(null)
const subForm = useForm({ name: '', category_id: null })

function openNewSub(cat) {
    editSubcat.value   = null
    subParentId.value  = cat.id
    subForm.name       = ''
    subForm.category_id = cat.id
    showSubModal.value = true
}
function openEditSub(sub) {
    editSubcat.value    = sub
    subForm.name        = sub.name
    subForm.category_id = sub.category_id
    showSubModal.value  = true
}
function closeSubModal() {
    showSubModal.value = false
    editSubcat.value   = null
    subForm.reset()
}
function submitSubForm() {
    if (editSubcat.value) {
        subForm.put(route('catalog.subcategory.update', editSubcat.value.id), {
            onSuccess: () => closeSubModal(),
        })
    } else {
        subForm.post(route('catalog.subcategory.store'), {
            onSuccess: () => closeSubModal(),
        })
    }
}
function destroySubcat(sub) {
    if (!confirm(`¿Eliminar la subcategoría "${sub.name}"?`)) return
    router.delete(route('catalog.subcategory.destroy', sub.id), { preserveScroll: true })
}
function subProductCount(subId) {
    return props.products.filter(p => p.subcategory_id === subId).length
}
</script>

<template>
    <AppLayout title="Catálogo de Productos">
        <div class="catalog-wrap">

            <!-- ─── KPI Cards ─────────────────────────────────────────────── -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <span class="kpi-label">Total productos</span>
                    <span class="kpi-value">{{ kpis.total }}</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-label">Categorías</span>
                    <span class="kpi-value">{{ kpis.categories }}</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-label">Por kilo</span>
                    <span class="kpi-value">{{ kpis.byKg }}</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-label">Por unidad</span>
                    <span class="kpi-value">{{ kpis.byUnit }}</span>
                </div>
            </div>

            <!-- ─── Header + botón ────────────────────────────────────────── -->
            <div class="catalog-header">
                <div class="main-tabs">
                    <button
                        class="main-tab-btn"
                        :class="{ active: mainTab === 'products' }"
                        @click="mainTab = 'products'"
                    >Productos</button>
                    <button
                        class="main-tab-btn"
                        :class="{ active: mainTab === 'categories' }"
                        @click="mainTab = 'categories'"
                    >Categorías</button>
                </div>
                <button v-if="mainTab === 'products'" class="btn-primary" @click="openNew">+ Nuevo Producto</button>
                <button v-else class="btn-primary" @click="openNewCat">+ Nueva Categoría</button>
            </div>

            <!-- ─── Tabs por categoría ────────────────────────────────────── -->
            <div class="tab-bar" v-if="categories.length && mainTab === 'products'">
                <button
                    v-for="cat in categories"
                    :key="cat.id"
                    class="tab-btn"
                    :class="{ active: activeTab === cat.id }"
                    :style="activeTab === cat.id ? `--tab-color: ${cat.color}` : ''"
                    @click="activeTab = cat.id"
                >
                    <span v-if="cat.icon" class="tab-icon">{{ cat.icon }}</span>
                    {{ cat.name }}
                </button>
            </div>

            <!-- ─── Tabla de productos ────────────────────────────────────── -->
            <div class="table-wrap" v-if="activeTab && mainTab === 'products'">
                <table class="prod-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Subcategoría</th>
                            <th>Tipo</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="tabProducts.length === 0">
                            <td colspan="7" class="empty-row">Sin productos en esta categoría</td>
                        </tr>
                        <tr v-for="p in tabProducts" :key="p.id">
                            <td class="prod-name">{{ p.name }}</td>
                            <td>
                                <span v-if="p.subcategory" class="badge badge-sub">
                                    {{ p.subcategory.name }}
                                </span>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td>
                                <span class="badge" :class="p.sale_mode === 'weight' ? 'badge-weight' : 'badge-unit'">
                                    {{ p.sale_mode === 'weight' ? '⚖️ Por kilo' : '📦 Por unidad' }}
                                </span>
                            </td>
                            <td class="price-cell">{{ priceDisplay(p) }}</td>
                            <td>{{ stockLabel(p) }}</td>
                            <td>
                                <span
                                    class="badge"
                                    :class="{
                                        'badge-ok':    p.active && stockStatus(p) === 'ok',
                                        'badge-low':   p.active && stockStatus(p) === 'low',
                                        'badge-empty': p.active && stockStatus(p) === 'empty',
                                        'badge-unit':  p.active && stockStatus(p) === 'unit',
                                        'badge-off':   !p.active,
                                    }"
                                >
                                    <template v-if="!p.active">Inactivo</template>
                                    <template v-else-if="stockStatus(p) === 'empty'">Agotado</template>
                                    <template v-else-if="stockStatus(p) === 'low'">Bajo Stock</template>
                                    <template v-else>Activo</template>
                                </span>
                            </td>
                            <td class="actions-cell">
                                <button class="btn-icon" title="Editar" @click="openEdit(p)">✏️</button>
                                <button
                                    class="btn-icon"
                                    :title="p.active ? 'Desactivar' : 'Activar'"
                                    @click="toggleActive(p)"
                                >
                                    {{ p.active ? '🔴' : '🟢' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ─── Sección Categorías ────────────────────────────────────── -->
            <div v-if="mainTab === 'categories'" class="cat-section">
                <div v-if="categories.length === 0" class="empty-row">Sin categorías creadas.</div>
                <div v-for="cat in categories" :key="cat.id" class="cat-card">
                    <!-- Fila categoría -->
                    <div class="cat-row">
                        <span class="cat-color-dot" :style="{ background: cat.color ?? '#6B7280' }"></span>
                        <span class="cat-icon">{{ cat.icon }}</span>
                        <span class="cat-name">{{ cat.name }}</span>
                        <span class="badge badge-sub cat-count">{{ catProductCount(cat.id) }} productos</span>
                        <div class="cat-actions">
                            <button class="btn-icon" title="Editar" @click="openEditCat(cat)">✏️</button>
                            <button
                                class="btn-icon"
                                title="Eliminar"
                                :disabled="catProductCount(cat.id) > 0"
                                :class="{ 'btn-disabled': catProductCount(cat.id) > 0 }"
                                @click="destroyCategory(cat)"
                            >🗑️</button>
                            <button class="btn-icon btn-add-sub" title="Nueva subcategoría" @click="openNewSub(cat)">＋ Sub</button>
                        </div>
                    </div>
                    <!-- Subcategorías inline -->
                    <div v-if="cat.subcategories?.length" class="sub-list">
                        <div v-for="sub in cat.subcategories" :key="sub.id" class="sub-row">
                            <span class="sub-indicator">└</span>
                            <span class="sub-name">{{ sub.name }}</span>
                            <span class="badge badge-sub sub-count">{{ subProductCount(sub.id) }} productos</span>
                            <div class="cat-actions">
                                <button class="btn-icon" title="Editar" @click="openEditSub(sub)">✏️</button>
                                <button
                                    class="btn-icon"
                                    title="Eliminar"
                                    :disabled="subProductCount(sub.id) > 0"
                                    :class="{ 'btn-disabled': subProductCount(sub.id) > 0 }"
                                    @click="destroySubcat(sub)"
                                >🗑️</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── Modal nuevo / editar producto ─────────────────────────── -->
            <Teleport to="body">
                <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
                    <div class="modal-box">
                        <div class="modal-header">
                            <h2 class="modal-title">{{ editProduct ? 'Editar Producto' : 'Nuevo Producto' }}</h2>
                            <button class="btn-close" @click="closeModal">✕</button>
                        </div>

                        <form class="modal-form" @submit.prevent="submitForm">

                            <!-- Nombre -->
                            <label class="field-label">Nombre</label>
                            <input
                                v-model="form.name"
                                class="field-input"
                                type="text"
                                placeholder="Ej: Lomito"
                                required
                            />
                            <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>

                            <!-- Categoría -->
                            <label class="field-label">Categoría</label>
                            <select v-model="form.category_id" class="field-input" required>
                                <option value="" disabled>Selecciona categoría</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                    {{ cat.icon }} {{ cat.name }}
                                </option>
                            </select>

                            <!-- Subcategoría (condicional) -->
                            <template v-if="hasSubcategories">
                                <label class="field-label">Subcategoría</label>
                                <select v-model="form.subcategory_id" class="field-input">
                                    <option value="">Sin subcategoría</option>
                                    <option
                                        v-for="sub in activeCategory.subcategories"
                                        :key="sub.id"
                                        :value="sub.id"
                                    >
                                        {{ sub.name }}
                                    </option>
                                </select>
                            </template>

                            <!-- Tipo de medida -->
                            <label class="field-label">Tipo de medida</label>
                            <div class="mode-radios">
                                <label
                                    class="mode-option"
                                    :class="{ selected: form.sale_mode === 'weight' }"
                                >
                                    <input
                                        type="radio"
                                        value="weight"
                                        v-model="form.sale_mode"
                                        class="sr-only"
                                    />
                                    <span class="mode-icon">⚖️</span>
                                    <span class="mode-text">
                                        <strong>Por Kilo (kg)</strong>
                                        <small>Para carnes y productos pesados</small>
                                    </span>
                                </label>
                                <label
                                    class="mode-option"
                                    :class="{ selected: form.sale_mode === 'unit' }"
                                >
                                    <input
                                        type="radio"
                                        value="unit"
                                        v-model="form.sale_mode"
                                        class="sr-only"
                                    />
                                    <span class="mode-icon">📦</span>
                                    <span class="mode-text">
                                        <strong>Por Unidad (und)</strong>
                                        <small>Para productos individuales</small>
                                    </span>
                                </label>
                            </div>

                            <!-- Precio por kg -->
                            <template v-if="form.sale_mode === 'weight'">
                                <label class="field-label">Precio por kg (USD)</label>
                                <input
                                    v-model="form.price_per_kg_usd"
                                    class="field-input"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                    required
                                />
                                <p v-if="form.errors.price_per_kg_usd" class="field-error">{{ form.errors.price_per_kg_usd }}</p>
                            </template>

                            <!-- Precio por unidad -->
                            <template v-if="form.sale_mode === 'unit'">
                                <label class="field-label">Precio por unidad (USD)</label>
                                <input
                                    v-model="form.price_per_unit_usd"
                                    class="field-input"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                    required
                                />
                                <p v-if="form.errors.price_per_unit_usd" class="field-error">{{ form.errors.price_per_unit_usd }}</p>
                            </template>

                            <!-- Stock mínimo -->
                            <label class="field-label">Stock mínimo</label>
                            <input
                                v-model="form.min_stock"
                                class="field-input"
                                type="number"
                                step="0.001"
                                min="0"
                                placeholder="0"
                            />

                            <div class="modal-footer">
                                <button type="button" class="btn-secondary" @click="closeModal">Cancelar</button>
                                <button type="submit" class="btn-primary" :disabled="form.processing">
                                    {{ editProduct ? 'Guardar cambios' : 'Crear producto' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>

            <!-- ─── Modal categoría ───────────────────────────────────────── -->
            <Teleport to="body">
                <div v-if="showCatModal" class="modal-overlay" @click.self="closeCatModal">
                    <div class="modal-box">
                        <div class="modal-header">
                            <h2 class="modal-title">{{ editCategory ? 'Editar Categoría' : 'Nueva Categoría' }}</h2>
                            <button class="btn-close" @click="closeCatModal">✕</button>
                        </div>
                        <form class="modal-form" @submit.prevent="submitCatForm">
                            <label class="field-label">Nombre</label>
                            <input v-model="catForm.name" class="field-input" type="text" placeholder="Ej: Res" required />
                            <p v-if="catForm.errors.name" class="field-error">{{ catForm.errors.name }}</p>

                            <label class="field-label">Icono (emoji)</label>
                            <input v-model="catForm.icon" class="field-input" type="text" placeholder="🥩" maxlength="4" />

                            <label class="field-label">Color</label>
                            <div class="color-row">
                                <input v-model="catForm.color" class="field-color" type="color" />
                                <input v-model="catForm.color" class="field-input" type="text" placeholder="#EF4444" maxlength="20" />
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn-secondary" @click="closeCatModal">Cancelar</button>
                                <button type="submit" class="btn-primary" :disabled="catForm.processing">
                                    {{ editCategory ? 'Guardar cambios' : 'Crear categoría' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>

            <!-- ─── Modal subcategoría ────────────────────────────────────── -->
            <Teleport to="body">
                <div v-if="showSubModal" class="modal-overlay" @click.self="closeSubModal">
                    <div class="modal-box">
                        <div class="modal-header">
                            <h2 class="modal-title">{{ editSubcat ? 'Editar Subcategoría' : 'Nueva Subcategoría' }}</h2>
                            <button class="btn-close" @click="closeSubModal">✕</button>
                        </div>
                        <form class="modal-form" @submit.prevent="submitSubForm">
                            <label class="field-label">Nombre</label>
                            <input v-model="subForm.name" class="field-input" type="text" placeholder="Ej: Premium" required />
                            <p v-if="subForm.errors.name" class="field-error">{{ subForm.errors.name }}</p>

                            <div class="modal-footer">
                                <button type="button" class="btn-secondary" @click="closeSubModal">Cancelar</button>
                                <button type="submit" class="btn-primary" :disabled="subForm.processing">
                                    {{ editSubcat ? 'Guardar cambios' : 'Crear subcategoría' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>

        </div>
    </AppLayout>
</template>

<style scoped>
/* ─── Layout ─────────────────────────────────────────────────────────────── */
.catalog-wrap {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* ─── KPI grid ───────────────────────────────────────────────────────────── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
@media (max-width: 768px) {
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
}

.kpi-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    padding: 1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.kpi-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.kpi-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
}

/* ─── Header ─────────────────────────────────────────────────────────────── */
.catalog-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.catalog-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
}

/* ─── Tabs ───────────────────────────────────────────────────────────────── */
.tab-bar {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    border-bottom: 1px solid var(--border);
    padding-bottom: 0;
}
.tab-btn {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem 0.5rem 0 0;
    border: 1px solid transparent;
    border-bottom: none;
    background: transparent;
    color: var(--text-muted);
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    transition: color 0.15s, background 0.15s;
}
.tab-btn:hover {
    color: var(--text-primary);
    background: var(--bg-card);
}
.tab-btn.active {
    background: var(--bg-card);
    color: var(--tab-color, var(--brand));
    border-color: var(--border);
    border-bottom-color: var(--bg-card);
    font-weight: 700;
}
.tab-icon { font-size: 1rem; }

/* ─── Table ──────────────────────────────────────────────────────────────── */
.table-wrap {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    overflow: auto;
}
.prod-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.prod-table th {
    padding: 0.75rem 1rem;
    text-align: left;
    color: var(--text-muted);
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid var(--border);
}
.prod-table td {
    padding: 0.75rem 1rem;
    color: var(--text-primary);
    border-bottom: 1px solid var(--border);
}
.prod-table tr:last-child td { border-bottom: none; }
.prod-table tr:hover td { background: color-mix(in srgb, var(--brand) 4%, transparent); }

.prod-name { font-weight: 500; }
.price-cell { font-variant-numeric: tabular-nums; }
.text-muted { color: var(--text-muted); }
.empty-row { text-align: center; color: var(--text-muted); padding: 2rem; }

/* ─── Badges ─────────────────────────────────────────────────────────────── */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    white-space: nowrap;
}
.badge-sub    { background: color-mix(in srgb, var(--brand) 15%, transparent); color: var(--brand); }
.badge-weight { background: color-mix(in srgb, #8B5CF6 15%, transparent); color: #8B5CF6; }
.badge-unit   { background: color-mix(in srgb, #06B6D4 15%, transparent); color: #06B6D4; }
.badge-ok     { background: color-mix(in srgb, #10B981 15%, transparent); color: #10B981; }
.badge-low    { background: color-mix(in srgb, #F59E0B 15%, transparent); color: #F59E0B; }
.badge-empty  { background: color-mix(in srgb, #EF4444 15%, transparent); color: #EF4444; }
.badge-off    { background: color-mix(in srgb, #6B7280 15%, transparent); color: #6B7280; }

/* ─── Action buttons ─────────────────────────────────────────────────────── */
.actions-cell { display: flex; gap: 0.25rem; }
.btn-icon {
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 0.375rem;
    padding: 0.3rem 0.5rem;
    cursor: pointer;
    font-size: 0.875rem;
    transition: background 0.15s;
}
.btn-icon:hover { background: var(--bg-base); }

/* ─── Buttons ────────────────────────────────────────────────────────────── */
.btn-primary {
    background: var(--brand);
    color: #fff;
    border: none;
    border-radius: 0.5rem;
    padding: 0.5rem 1.1rem;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: opacity 0.15s;
}
.btn-primary:hover   { opacity: 0.85; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-secondary {
    background: transparent;
    color: var(--text-muted);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.5rem 1.1rem;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: background 0.15s;
}
.btn-secondary:hover { background: var(--bg-base); color: var(--text-primary); }

/* ─── Modal ──────────────────────────────────────────────────────────────── */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(2px);
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
    max-width: 480px;
    max-height: 90vh;
    overflow-y: auto;
}
.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 1.5rem 0;
}
.modal-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
}
.btn-close {
    background: transparent;
    border: none;
    color: var(--text-muted);
    font-size: 1rem;
    cursor: pointer;
}
.btn-close:hover { color: var(--text-primary); }

/* ─── Form ───────────────────────────────────────────────────────────────── */
.modal-form {
    padding: 1.25rem 1.5rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.field-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
    margin-top: 0.5rem;
}
.field-input {
    width: 100%;
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.55rem 0.75rem;
    color: var(--text-primary);
    font-size: 0.9rem;
    outline: none;
    transition: border-color 0.15s;
}
.field-input:focus { border-color: var(--brand); }
.field-error {
    font-size: 0.75rem;
    color: #EF4444;
    margin-top: -0.25rem;
}

/* ─── Mode radios ────────────────────────────────────────────────────────── */
.mode-radios {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    margin-top: 0.25rem;
}
.mode-option {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border: 2px solid var(--border);
    border-radius: 0.625rem;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
}
.mode-option:hover { border-color: var(--brand); }
.mode-option.selected {
    border-color: var(--brand);
    background: color-mix(in srgb, var(--brand) 8%, transparent);
}
.mode-icon { font-size: 1.4rem; }
.mode-text {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
}
.mode-text strong { font-size: 0.8rem; color: var(--text-primary); }
.mode-text small  { font-size: 0.7rem; color: var(--text-muted); }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); }

/* ─── Modal footer ───────────────────────────────────────────────────────── */
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border);
}

/* ─── Main tabs (Productos / Categorías) ─────────────────────────────────── */
.main-tabs {
    display: flex;
    gap: 0.25rem;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.25rem;
}
.main-tab-btn {
    padding: 0.35rem 1rem;
    border-radius: 0.35rem;
    border: none;
    background: transparent;
    color: var(--text-muted);
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.main-tab-btn.active {
    background: var(--brand);
    color: #fff;
    font-weight: 700;
}

/* ─── Sección categorías ─────────────────────────────────────────────────── */
.cat-section {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.cat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    overflow: hidden;
}
.cat-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
}
.cat-color-dot {
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 50%;
    flex-shrink: 0;
}
.cat-icon { font-size: 1.1rem; }
.cat-name {
    font-weight: 600;
    color: var(--text-primary);
    flex: 1;
}
.cat-count { margin-left: auto; }
.cat-actions {
    display: flex;
    gap: 0.25rem;
    align-items: center;
}
.btn-add-sub {
    font-size: 0.75rem;
    padding: 0.3rem 0.6rem;
    color: var(--brand);
    border-color: var(--brand);
}
.btn-disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

/* ─── Subcategorías ──────────────────────────────────────────────────────── */
.sub-list {
    border-top: 1px solid var(--border);
    background: color-mix(in srgb, var(--bg-base) 50%, transparent);
}
.sub-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.6rem 1rem 0.6rem 1.75rem;
    border-bottom: 1px solid var(--border);
}
.sub-row:last-child { border-bottom: none; }
.sub-indicator { color: var(--text-muted); font-size: 0.875rem; }
.sub-name { color: var(--text-primary); font-size: 0.875rem; flex: 1; }
.sub-count { margin-left: auto; }

/* ─── Color picker row ───────────────────────────────────────────────────── */
.color-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.field-color {
    width: 2.5rem;
    height: 2.5rem;
    border: 1px solid var(--border);
    border-radius: 0.375rem;
    cursor: pointer;
    padding: 0.15rem;
    background: var(--bg-base);
    flex-shrink: 0;
}
</style>
