<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, markRaw } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Scale, Package, Pencil, Star, ToggleLeft, ToggleRight, Trash2 } from '@lucide/vue'
import HelpModal from '@/Components/HelpModal.vue'

const props = defineProps({
    categories:  { type: Array,   default: () => [] },
    products:    { type: Array,   default: () => [] },
})

// ─── Tabs + búsqueda ──────────────────────────────────────────────────────────
const activeTab   = ref(props.categories[0]?.id ?? null)
const searchQuery = ref('')

const tabProducts = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    if (q) {
        // search mode: cross all categories
        return props.products.filter(p =>
            p.name.toLowerCase().includes(q) ||
            (p.category?.name ?? '').toLowerCase().includes(q) ||
            (p.subcategory?.name ?? '').toLowerCase().includes(q)
        )
    }
    return props.products.filter(p => p.category_id === activeTab.value)
})

function onCatalogSearch() { /* reactivity handles it */ }
function clearCatalogSearch() { searchQuery.value = '' }

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

const submitting = ref(false)

const form = useForm({
    name:               '',
    category_id:        props.categories[0]?.id ?? '',
    subcategory_id:     '',
    sale_mode:          'weight',
    price_per_kg_usd:   '',
    price_per_unit_usd: '',
    min_stock:          0,
    active:             true,
    fabricable:         false,
    image:              null,
    remove_image:       false,
})

const selectedImagePreview = ref(null)
const fileInputRef         = ref(null)

const activeCategory = computed(() =>
    props.categories.find(c => c.id === Number(form.category_id))
)

const hasSubcategories = computed(() =>
    (activeCategory.value?.subcategories?.length ?? 0) > 0
)

const currentImageUrl = computed(() => {
    if (!editProduct.value?.image_path) return null
    return `/storage/${editProduct.value.image_path}`
})

const displayImage = computed(() => {
    if (selectedImagePreview.value) return selectedImagePreview.value
    if (!form.remove_image && currentImageUrl.value) return currentImageUrl.value
    return null
})

function openNew() {
    editProduct.value          = null
    selectedImagePreview.value = null
    form.reset()
    form.category_id   = props.categories[0]?.id ?? ''
    form.sale_mode     = 'weight'
    formTouched.value  = false
    showModal.value    = true
}

function openEdit(product) {
    editProduct.value          = product
    selectedImagePreview.value = null
    form.name               = product.name
    form.category_id        = product.category_id
    form.subcategory_id     = product.subcategory_id ?? ''
    form.sale_mode          = product.sale_mode
    form.price_per_kg_usd   = product.price_per_kg_usd ?? ''
    form.price_per_unit_usd = product.price_per_unit_usd ?? ''
    form.min_stock          = product.min_stock ?? 0
    form.active             = product.active
    form.fabricable         = product.fabricable ?? false
    form.image              = null
    form.remove_image       = false
    formTouched.value       = false
    showModal.value         = true
}

function closeModal() {
    showModal.value            = false
    editProduct.value          = null
    selectedImagePreview.value = null
    formTouched.value          = false
    if (fileInputRef.value) fileInputRef.value.value = ''
    form.reset()
}

function onImageSelect(event) {
    const file = event.target.files?.[0]
    if (!file) return
    // markRaw prevents Vue 3 from wrapping File in a Proxy.
    // FormData.append() requires a real File, not a Proxy.
    form.image        = markRaw(file)
    form.remove_image = false
    const reader      = new FileReader()
    reader.onload     = (e) => { selectedImagePreview.value = e.target.result }
    reader.readAsDataURL(file)
}

function removeImage() {
    form.image              = null
    form.remove_image       = true
    selectedImagePreview.value = null
    if (fileInputRef.value) fileInputRef.value.value = ''
}

function submitForm() {
    if (submitting.value) return
    if (!hasSubcategories.value) {
        form.subcategory_id = null
    }

    // Pass a plain object — Inertia v2 detects File instances automatically
    // and switches to multipart/form-data transport. A native FormData object
    // passed to router.post() does NOT trigger that detection in v2.
    const data = {
        name:               form.name,
        category_id:        form.category_id,
        subcategory_id:     form.subcategory_id || null,
        sale_mode:          form.sale_mode,
        price_per_kg_usd:   form.price_per_kg_usd || null,
        price_per_unit_usd: form.price_per_unit_usd || null,
        min_stock:          form.min_stock ?? 0,
        active:             form.active,
        fabricable:         form.fabricable ? 1 : 0,
        remove_image:       form.remove_image,
    }

    if (form.image instanceof File) {
        data.image = form.image
    }

    submitting.value = true

    if (editProduct.value) {
        // _method: 'PUT' in the body makes Laravel route it to update().
        // Apache strips PUT body but never strips POST body.
        router.post(route('catalog.update', editProduct.value.id), {
            ...data,
            _method: 'PUT',
        }, {
            onSuccess: () => { submitting.value = false; closeModal() },
            onError:   (errors) => { submitting.value = false; form.errors = errors; console.error('[update errors]', errors) },
        })
    } else {
        router.post(route('catalog.store'), data, {
            onSuccess: () => { submitting.value = false; closeModal() },
            onError:   (errors) => { submitting.value = false; form.errors = errors; console.error('[store errors]', errors) },
        })
    }
}

function toggleActive(product) {
    router.put(route('catalog.update', product.id), {
        ...product,
        active: !product.active,
    }, { preserveScroll: true })
}

function toggleFavorite(product) {
    router.patch(route('catalog.product.favorite', product.id), {}, { preserveScroll: true })
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

// ─── Unsaved change guards ─────────────────────────────────────────────────────
const formTouched    = ref(false)
const catFormTouched = ref(false)
const subFormTouched = ref(false)

function tryCloseModal() {
    if (formTouched.value && !confirm('Hay cambios sin guardar. ¿Salir de todas formas?')) return
    closeModal()
}
function tryCloseCatModal() {
    if (catFormTouched.value && !confirm('Hay cambios sin guardar. ¿Salir de todas formas?')) return
    closeCatModal()
}
function tryCloseSubModal() {
    if (subFormTouched.value && !confirm('Hay cambios sin guardar. ¿Salir de todas formas?')) return
    closeSubModal()
}

// ─── Sección categorías ───────────────────────────────────────────────────────
const mainTab = ref('products') // 'products' | 'categories'

// Modal categoría
const showCatModal   = ref(false)
const editCategory   = ref(null)
const catForm = useForm({ name: '', color: '#6B7280', icon: '', macro_category: null })

const macroOptions = [
    { value: null,       label: 'Sin macro-categoría' },
    { value: 'RES',      label: 'RES' },
    { value: 'POLLO',    label: 'POLLO' },
    { value: 'CERDO',    label: 'CERDO' },
    { value: 'TRASTES',  label: 'TRASTES' },
    { value: 'MISC',     label: 'MISC' },
]

function openNewCat() {
    editCategory.value     = null
    catForm.reset()
    catForm.color          = '#6B7280'
    catForm.macro_category = null
    catFormTouched.value   = false
    showCatModal.value     = true
}
function openEditCat(cat) {
    editCategory.value     = cat
    catForm.name           = cat.name
    catForm.color          = cat.color ?? '#6B7280'
    catForm.icon           = cat.icon ?? ''
    catForm.macro_category = cat.macro_category ?? null
    catFormTouched.value   = false
    showCatModal.value     = true
}
function closeCatModal() {
    showCatModal.value   = false
    editCategory.value   = null
    catFormTouched.value = false
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
    editSubcat.value     = null
    subParentId.value    = cat.id
    subForm.name         = ''
    subForm.category_id  = cat.id
    subFormTouched.value = false
    showSubModal.value   = true
}
function openEditSub(sub) {
    editSubcat.value     = sub
    subForm.name         = sub.name
    subForm.category_id  = sub.category_id
    subFormTouched.value = false
    showSubModal.value   = true
}
function closeSubModal() {
    showSubModal.value = false
    editSubcat.value   = null
    subForm.reset()
    subFormTouched.value = false
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

// ─── Ayuda ────────────────────────────────────────────────────────────────────
const showHelp = ref(false)

const helpSteps = [
    {
        title: 'Ver productos y categorías',
        body: 'Aquí están todos los productos del negocio organizados por categoría. Res, Pollo, Cerdo, Charcutería, etc.',
        tip: 'Los productos inactivos no aparecen en el POS.',
    },
    {
        title: 'Crear un producto',
        body: 'Agrega nombre, categoría, precio en USD y modo de venta (por kg o por unidad). El sistema convierte a Bs. automáticamente.',
        tip: 'El precio se define en USD. El POS usa la tasa del día para cobrar en Bs.',
    },
    {
        title: 'Editar precio',
        body: 'Toca el producto y cambia el precio. El cambio aplica a partir de la próxima venta. Las ventas anteriores no se afectan.',
        tip: 'Los precios históricos quedan guardados en cada ticket.',
    },
    {
        title: 'Configurar stock mínimo',
        body: 'Define cuántos kg mínimos debe tener el producto antes de aparecer en alerta crítica de inventario.',
        tip: 'El badge rojo en Inventario usa este valor como referencia.',
    },
]

const helpFaqs = [
    {
        q: '¿Puedo borrar un producto?',
        a: 'No se borran, solo se desactivan. El historial de ventas se conserva.',
    },
    {
        q: '¿El precio en USD se cobra así al cliente?',
        a: 'No. El sistema multiplica por la tasa BCV del día y cobra en Bs.',
    },
    {
        q: '¿Qué es modo de venta por kg vs. por unidad?',
        a: 'Por kg: el cliente paga según el peso. Por unidad: precio fijo por pieza.',
    },
    {
        q: '¿Puedo tener el mismo producto en varias categorías?',
        a: 'No, cada producto pertenece a una sola categoría.',
    },
    {
        q: '¿Qué es "fabricable"?',
        a: 'Indica que el producto se produce en Fábrica a partir de materia prima de bóveda.',
    },
]
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
                <button class="tab-btn tab-btn--help" @click="showHelp = true" title="Ayuda">?</button>
            </div>

            <!-- ─── Barra búsqueda catálogo ───────────────────────────────── -->
            <div v-if="mainTab === 'products'" class="cat-search-bar">
                <div class="cat-search-wrap">
                    <svg class="cat-search-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3a6 6 0 1 0 0 12A6 6 0 0 0 9 3Zm-8 6a8 8 0 1 1 14.32 4.906l4.387 4.387a1 1 0 0 1-1.414 1.414l-4.387-4.387A8 8 0 0 1 1 9Z" clip-rule="evenodd"/></svg>
                    <input
                        v-model="searchQuery"
                        class="cat-search-input"
                        type="search"
                        placeholder="Buscar producto en todas las categorías…"
                        autocomplete="off"
                    />
                    <button v-if="searchQuery" class="cat-search-clear" @click="clearCatalogSearch">×</button>
                </div>
                <span v-if="searchQuery" class="cat-search-count">
                    {{ tabProducts.length }} resultado{{ tabProducts.length !== 1 ? 's' : '' }}
                </span>
            </div>

            <!-- ─── Tabla de productos ────────────────────────────────────── -->
            <div class="table-wrap" v-if="(activeTab || searchQuery) && mainTab === 'products'">
                <table class="prod-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th v-if="searchQuery">Categoría</th>
                            <th>Tipo</th>
                            <th>Precio</th>
                            <th>Existencia</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="tabProducts.length === 0">
                            <td :colspan="searchQuery ? 7 : 6" class="empty-row">
                                {{ searchQuery ? 'Sin resultados para "' + searchQuery + '"' : 'Sin productos en esta categoría' }}
                            </td>
                        </tr>
                        <tr v-for="p in tabProducts" :key="p.id">
                            <td class="prod-name">{{ p.name }}</td>
                            <td v-if="searchQuery">
                                <span class="cat-pill" :style="{ borderColor: categoryColor(p.category_id) }">
                                    {{ p.category?.name ?? '—' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge" :class="p.sale_mode === 'weight' ? 'badge-weight' : 'badge-unit'">
                                    <Scale v-if="p.sale_mode === 'weight'" :size="12" style="vertical-align:middle;margin-right:3px;" />
                                    <Package v-else :size="12" style="vertical-align:middle;margin-right:3px;" />
                                    {{ p.sale_mode === 'weight' ? 'Por kilo' : 'Por unidad' }}
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
                                <button class="btn-icon" title="Editar" @click="openEdit(p)"><Pencil :size="14" /></button>
                                <button
                                    class="btn-icon"
                                    :title="p.active ? 'Desactivar' : 'Activar'"
                                    @click="toggleActive(p)"
                                >
                                    <ToggleRight v-if="p.active" :size="16" class="toggle-on" />
                                    <ToggleLeft v-else :size="16" class="toggle-off" />
                                </button>
                                <button
                                    class="btn-icon"
                                    :title="p.is_favorite ? 'Quitar de favoritos' : 'Marcar favorito'"
                                    @click="toggleFavorite(p)"
                                >
                                    <Star :size="14" :class="p.is_favorite ? 'star-on' : 'star-off'" />
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
                            <button class="btn-icon" title="Editar" @click="openEditCat(cat)"><Pencil :size="14" /></button>
                            <button
                                class="btn-icon"
                                title="Eliminar"
                                :disabled="catProductCount(cat.id) > 0"
                                :class="{ 'btn-disabled': catProductCount(cat.id) > 0 }"
                                @click="destroyCategory(cat)"
                            ><Trash2 :size="14" /></button>
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
                                <button class="btn-icon" title="Editar" @click="openEditSub(sub)"><Pencil :size="14" /></button>
                                <button
                                    class="btn-icon"
                                    title="Eliminar"
                                    :disabled="subProductCount(sub.id) > 0"
                                    :class="{ 'btn-disabled': subProductCount(sub.id) > 0 }"
                                    @click="destroySubcat(sub)"
                                ><Trash2 :size="14" /></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── Modal nuevo / editar producto ─────────────────────────── -->
            <Teleport to="body">
                <div v-if="showModal" class="modal-overlay">
                    <div class="modal-box prod-modal-box">
                        <div class="modal-header">
                            <h2 class="modal-title">{{ editProduct ? 'Editar Producto' : 'Nuevo Producto' }}</h2>
                            <button class="btn-close" @click="tryCloseModal">✕</button>
                        </div>

                        <form
                            class="prod-form-wrap"
                            @submit.prevent="submitForm"
                            @input="formTouched = true"
                            @change="formTouched = true"
                        >
                            <!-- Error general visible -->
                            <div v-if="Object.keys(form.errors).length" class="form-errors-box">
                                <p v-for="(msg, field) in form.errors" :key="field" class="form-error-line">
                                    <strong>{{ field }}</strong>: {{ msg }}
                                </p>
                            </div>

                            <div class="prod-modal-body">
                                <!-- ── Columna izquierda: imagen + nombre ── -->
                                <div class="prod-col-left">
                                    <!-- Imagen del producto -->
                                    <div class="img-upload-area">
                                        <div v-if="displayImage" class="img-preview-box">
                                            <img :src="displayImage" alt="Vista previa" class="img-preview-img" />
                                            <button type="button" class="img-remove-btn" @click="removeImage" title="Quitar imagen">✕</button>
                                        </div>
                                        <label v-else class="img-dropzone">
                                            <input
                                                ref="fileInputRef"
                                                type="file"
                                                accept=".jpg,.jpeg,.png"
                                                class="sr-only"
                                                @change="onImageSelect"
                                            />
                                            <svg class="img-zone-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                            <span class="img-zone-label">Click para seleccionar</span>
                                            <span class="img-zone-hint">JPG · PNG · máx 2 MB</span>
                                        </label>
                                    </div>
                                    <p v-if="form.errors.image" class="field-error">{{ form.errors.image }}</p>

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
                                </div>

                                <!-- ── Columna derecha: tipo + precio + opciones ── -->
                                <div class="prod-col-right">
                                    <!-- Tipo de medida -->
                                    <label class="field-label">Tipo de medida</label>
                                    <div class="mode-radios">
                                        <label
                                            class="mode-option"
                                            :class="{ selected: form.sale_mode === 'weight' }"
                                        >
                                            <input type="radio" value="weight" v-model="form.sale_mode" class="sr-only" />
                                            <span class="mode-icon"><Scale :size="20" /></span>
                                            <span class="mode-text">
                                                <strong>Por Kilo (kg)</strong>
                                                <small>Carnes y pesados</small>
                                            </span>
                                        </label>
                                        <label
                                            class="mode-option"
                                            :class="{ selected: form.sale_mode === 'unit' }"
                                        >
                                            <input type="radio" value="unit" v-model="form.sale_mode" class="sr-only" />
                                            <span class="mode-icon"><Package :size="20" /></span>
                                            <span class="mode-text">
                                                <strong>Por Unidad (und)</strong>
                                                <small>Productos individuales</small>
                                            </span>
                                        </label>
                                    </div>

                                    <!-- Precio por kg -->
                                    <template v-if="form.sale_mode === 'weight'">
                                        <label class="field-label">Precio de venta / kg (USD)</label>
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
                                        <label class="field-label">Precio de venta / und (USD)</label>
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

                                    <!-- Existencia mínima -->
                                    <label class="field-label">Existencia mínima {{ form.sale_mode === 'weight' ? '(kg)' : '(und)' }}</label>
                                    <input
                                        v-model="form.min_stock"
                                        class="field-input"
                                        type="number"
                                        step="0.001"
                                        min="0"
                                        placeholder="0"
                                    />

                                    <!-- Opciones -->
                                    <div class="field-checks">
                                        <label class="field-check-row">
                                            <input type="checkbox" v-model="form.fabricable" class="field-chk" />
                                            <span>
                                                <strong>Habilitar en Fábrica</strong>
                                                <small> — chorizo, cesta, combo…</small>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn-secondary" @click="tryCloseModal">Cancelar</button>
                                <button type="submit" class="btn-primary" :disabled="submitting">
                                    {{ editProduct ? 'Guardar cambios' : 'Crear producto' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>

            <!-- ─── Modal categoría ───────────────────────────────────────── -->
            <Teleport to="body">
                <div v-if="showCatModal" class="modal-overlay">
                    <div class="modal-box">
                        <div class="modal-header">
                            <h2 class="modal-title">{{ editCategory ? 'Editar Categoría' : 'Nueva Categoría' }}</h2>
                            <button class="btn-close" @click="tryCloseCatModal">✕</button>
                        </div>
                        <form
                            class="modal-form"
                            @submit.prevent="submitCatForm"
                            @input="catFormTouched = true"
                            @change="catFormTouched = true"
                        >
                            <label class="field-label">Nombre</label>
                            <input v-model="catForm.name" class="field-input" type="text" placeholder="Ej: Res" required />
                            <p v-if="catForm.errors.name" class="field-error">{{ catForm.errors.name }}</p>

                            <label class="field-label">Icono (opcional)</label>
                            <input v-model="catForm.icon" class="field-input" type="text" placeholder="ej. RS" maxlength="4" />

                            <label class="field-label">Color</label>
                            <div class="color-row">
                                <input v-model="catForm.color" class="field-color" type="color" />
                                <input v-model="catForm.color" class="field-input" type="text" placeholder="#EF4444" maxlength="20" />
                            </div>

                            <label class="field-label">Macro-categoría (reportes)</label>
                            <select v-model="catForm.macro_category" class="field-input">
                                <option v-for="opt in macroOptions" :key="opt.value ?? 'none'" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>

                            <div class="modal-footer">
                                <button type="button" class="btn-secondary" @click="tryCloseCatModal">Cancelar</button>
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
                <div v-if="showSubModal" class="modal-overlay">
                    <div class="modal-box">
                        <div class="modal-header">
                            <h2 class="modal-title">{{ editSubcat ? 'Editar Subcategoría' : 'Nueva Subcategoría' }}</h2>
                            <button class="btn-close" @click="tryCloseSubModal">✕</button>
                        </div>
                        <form
                            class="modal-form"
                            @submit.prevent="submitSubForm"
                            @input="subFormTouched = true"
                            @change="subFormTouched = true"
                        >
                            <label class="field-label">Nombre</label>
                            <input v-model="subForm.name" class="field-input" type="text" placeholder="Ej: Premium" required />
                            <p v-if="subForm.errors.name" class="field-error">{{ subForm.errors.name }}</p>

                            <div class="modal-footer">
                                <button type="button" class="btn-secondary" @click="tryCloseSubModal">Cancelar</button>
                                <button type="submit" class="btn-primary" :disabled="subForm.processing">
                                    {{ editSubcat ? 'Guardar cambios' : 'Crear subcategoría' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>

        </div>

        <!-- ── Panel de ayuda ────────────────────────────────────────────── -->
        <HelpModal
            :show="showHelp"
            title="Catálogo — Cómo funciona"
            :steps="helpSteps"
            :faqs="helpFaqs"
            @close="showHelp = false"
        />

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

/* ─── Catalog search bar ─────────────────────────────────────────────────── */
.cat-search-bar {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.cat-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
    flex: 1;
    max-width: 480px;
}
.cat-search-icon {
    position: absolute;
    left: 0.65rem;
    width: 1rem;
    height: 1rem;
    color: var(--text-muted);
    pointer-events: none;
}
.cat-search-input {
    width: 100%;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.5rem 2rem 0.5rem 2.1rem;
    color: var(--text-primary);
    font-size: 0.875rem;
    font-family: inherit;
    outline: none;
    transition: border-color 0.15s;
}
.cat-search-input:focus { border-color: var(--brand); }
.cat-search-input::-webkit-search-cancel-button { display: none; }
.cat-search-clear {
    position: absolute;
    right: 0.5rem;
    background: transparent;
    border: none;
    color: var(--text-muted);
    font-size: 1.1rem;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}
.cat-search-clear:hover { color: var(--text-primary); }
.cat-search-count { font-size: 0.8rem; color: var(--text-muted); white-space: nowrap; }
.cat-pill {
    display: inline-block;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    border: 1.5px solid var(--border);
    color: var(--text-primary);
    white-space: nowrap;
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
    font-size: 14px;
    cursor: pointer;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.tab-btn--help:hover { background: var(--brand); color: #fff; border-color: var(--brand); }

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
.btn-icon { display: inline-flex; align-items: center; justify-content: center; }
.toggle-on  { color: var(--brand); }
.toggle-off { color: var(--text-muted); }
.star-on    { color: #f59e0b; fill: #f59e0b; }
.star-off   { color: var(--text-muted); }

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
    display: flex;
    flex-direction: column;
}

/* ─── Product modal: 2-column, no external scroll ─────────────────────── */
.prod-modal-box {
    max-width: 780px;
    height: min(560px, 92vh);
    overflow: hidden;
}
.prod-form-wrap {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
}
.prod-modal-body {
    flex: 1;
    min-height: 0;
    display: grid;
    grid-template-columns: 1fr 1fr;
}
.prod-col-left {
    overflow-y: auto;
    padding: 1rem 1.25rem;
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.prod-col-right {
    overflow-y: auto;
    padding: 1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
@media (max-width: 640px) {
    .prod-modal-box { height: 92vh; }
    .prod-modal-body { grid-template-columns: 1fr; }
    .prod-col-left { border-right: none; border-bottom: 1px solid var(--border); }
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 1.5rem 1rem;
    flex-shrink: 0;
    border-bottom: 1px solid var(--border);
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
    overflow-y: auto;
    flex: 1;
    min-height: 0;
}
.field-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
    margin-top: 0.5rem;
}
.field-checks { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.25rem; }
.field-check-row { display: flex; align-items: flex-start; gap: 0.5rem; cursor: pointer; font-size: 0.875rem; color: var(--text-primary); }
.field-chk { margin-top: 0.2rem; accent-color: var(--brand); }
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

/* ─── Cost fields (restricted visibility) ───────────────────────────────── */
.field-label--cost {
    color: #B45309;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.field-label--cost::before {
    content: '●';
    font-size: 0.5rem;
}
.field-input--cost {
    border-color: color-mix(in srgb, #B45309 40%, var(--border));
    background: color-mix(in srgb, #B45309 6%, var(--bg-base));
}
.field-input--cost:focus {
    border-color: #B45309;
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
    padding: 0.875rem 1.25rem;
    border-top: 1px solid var(--border);
    flex-shrink: 0;
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

/* ─── Image upload ───────────────────────────────────────────────────────── */
.field-hint {
    font-size: 0.7rem;
    color: var(--text-muted);
    font-weight: 400;
    margin-left: 0.25rem;
}
.form-errors-box {
    background: color-mix(in srgb, #EF4444 10%, transparent);
    border: 1px solid color-mix(in srgb, #EF4444 30%, transparent);
    border-radius: 0.5rem;
    padding: 0.5rem 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    margin: 0 1.25rem;
}
.form-error-line {
    font-size: 0.78rem;
    color: #EF4444;
    margin: 0;
}
.img-upload-area { margin-top: 0.25rem; }
.img-preview-box {
    position: relative;
    display: block;
    width: 100%;
}
.img-preview-img {
    width: 100%;
    max-height: 130px;
    object-fit: contain;
    border-radius: 0.5rem;
    border: 1px solid var(--border);
    background: var(--bg-base);
    display: block;
}
.img-remove-btn {
    position: absolute;
    top: 0.35rem;
    right: 0.35rem;
    background: rgba(0,0,0,0.65);
    border: none;
    color: #fff;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    cursor: pointer;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
.img-remove-btn:hover { background: rgba(239,68,68,0.85); }
.img-dropzone {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    border: 2px dashed var(--border);
    border-radius: 0.5rem;
    padding: 1rem;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
    min-height: 110px;
}
.img-dropzone:hover {
    border-color: var(--brand);
    background: color-mix(in srgb, var(--brand) 5%, transparent);
}
.img-zone-icon {
    width: 2rem;
    height: 2rem;
    color: var(--text-muted);
}
.img-zone-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-primary);
}
.img-zone-hint {
    font-size: 0.72rem;
    color: var(--text-muted);
    text-align: center;
}
</style>
