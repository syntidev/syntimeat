<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    clients:     { type: Object, default: () => ({ data: [], links: [], meta: {} }) },
    kpis:        { type: Object, default: null },
    filters:     { type: Object, default: () => ({ q: '' }) },
    client:      { type: Object, default: null },
    clientSales: { type: Array,  default: () => [] },
})

// ─── Formato ──────────────────────────────────────────────────────────────────
function fmtBs(n)   { return 'Bs. ' + Number(n ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
function fmtDate(d) { return d ? new Date(d).toLocaleDateString('es-VE') : '—' }

// ─── Búsqueda ─────────────────────────────────────────────────────────────────
const search = ref(props.filters.q ?? '')
let searchTimeout = null

watch(search, (val) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(route('clients.index'), { q: val }, { preserveState: true, replace: true })
    }, 350)
})

// ─── Drawer lateral ──────────────────────────────────────────────────────────
const activeClient = ref(null)
const editMode     = ref(false)

function openDrawer(client) {
    activeClient.value = { ...client }
    editMode.value     = false
}
function closeDrawer() {
    activeClient.value = null
    editMode.value     = false
}

// ─── Modal crear/editar ───────────────────────────────────────────────────────
const showModal = ref(false)
const modalMode = ref('create') // 'create' | 'edit'
const form      = ref({ cedula: '', name: '', phone: '', email: '', address: '', notes: '' })
const errors    = ref({})
const saving    = ref(false)

function openCreate() {
    form.value  = { cedula: '', name: '', phone: '', email: '', address: '', notes: '' }
    errors.value = {}
    modalMode.value = 'create'
    showModal.value = true
}
function openEdit(client) {
    form.value  = { cedula: client.cedula ?? '', name: client.name, phone: client.phone ?? '', email: client.email ?? '', address: client.address ?? '', notes: client.notes ?? '' }
    errors.value = {}
    modalMode.value = 'edit'
    showModal.value = true
    activeClient.value = { ...client }
}
function closeModal() { showModal.value = false }

const csrfToken = () => document.head.querySelector('meta[name="csrf-token"]')?.content ?? ''

function submitForm() {
    if (saving.value) return
    saving.value = true
    errors.value = {}

    const isEdit  = modalMode.value === 'edit'
    const url     = isEdit ? route('clients.update', { client: activeClient.value.id }) : route('clients.store')
    const method  = isEdit ? 'PUT' : 'POST'

    fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
        body: JSON.stringify(form.value),
    })
    .then(r => {
        if (!r.ok) return r.json().then(e => { throw e })
        return r.json()
    })
    .then(() => {
        closeModal()
        router.reload({ only: ['clients', 'kpis'] })
    })
    .catch(e => { errors.value = e.errors ?? {} })
    .finally(() => { saving.value = false })
}

// ─── Toggle activo ────────────────────────────────────────────────────────────
function toggleActive(client) {
    fetch(route('clients.update', { client: client.id }), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
        body: JSON.stringify({ ...client, active: !client.active }),
    }).then(() => router.reload({ only: ['clients'] }))
}
</script>

<template>
    <AppLayout title="Clientes">
        <div class="clients-wrap">

            <!-- ─── KPIs ──────────────────────────────────────────────────── -->
            <div v-if="kpis" class="kpi-grid">
                <div class="kpi-card">
                    <span class="kpi-label">Total clientes</span>
                    <span class="kpi-value">{{ kpis.total }}</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-label">Nuevos este mes</span>
                    <span class="kpi-value accent">{{ kpis.newMonth }}</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-label">Ventas identificadas</span>
                    <span class="kpi-value">{{ kpis.identified }}</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-label">Ventas anónimas</span>
                    <span class="kpi-value muted">{{ kpis.anonymous }}</span>
                </div>
            </div>

            <!-- ─── Barra superior ────────────────────────────────────────── -->
            <div class="top-bar">
                <input
                    v-model="search"
                    type="search"
                    class="search-input"
                    placeholder="Buscar por nombre, teléfono o cédula…"
                />
                <button class="btn btn-brand" @click="openCreate">+ Nuevo cliente</button>
            </div>

            <!-- ─── Tabla ─────────────────────────────────────────────────── -->
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Cédula</th>
                            <th>Nombre</th>
                            <th>Teléfono</th>
                            <th>Última compra</th>
                            <th>Compras</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="c in clients.data"
                            :key="c.id"
                            class="data-row"
                            @click="openDrawer(c)"
                        >
                            <td><span class="code-badge">{{ c.cedula ?? '—' }}</span></td>
                            <td class="name-cell">{{ c.name }}</td>
                            <td class="muted-cell">{{ c.phone ?? '—' }}</td>
                            <td class="muted-cell">{{ fmtDate(c.sales_max_sold_at) }}</td>
                            <td class="muted-cell">{{ c.sales_count }}</td>
                            <td>
                                <span class="status-pill" :class="c.active ? 'active' : 'inactive'">
                                    {{ c.active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td @click.stop>
                                <button class="icon-btn" title="Editar" @click="openEdit(c)">✎</button>
                            </td>
                        </tr>
                        <tr v-if="!clients.data?.length">
                            <td colspan="7" class="empty-row">No hay clientes registrados.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ─── Paginación ────────────────────────────────────────────── -->
            <div v-if="clients.meta?.last_page > 1" class="pagination">
                <a
                    v-for="link in clients.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="page-link"
                    :class="{ active: link.active, disabled: !link.url }"
                    @click.prevent="link.url && router.visit(link.url)"
                    v-html="link.label"
                />
            </div>
        </div>

        <!-- ─── Drawer lateral ────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="activeClient" class="drawer-overlay">
                <div class="drawer">
                    <div class="drawer-header">
                        <div>
                            <span v-if="activeClient.cedula" class="code-badge">{{ activeClient.cedula }}</span>
                            <h3 class="drawer-name">{{ activeClient.name }}</h3>
                        </div>
                        <button class="modal-close" @click="closeDrawer">×</button>
                    </div>

                    <div class="drawer-info">
                        <div class="info-row"><span>Cédula</span><span>{{ activeClient.cedula ?? '—' }}</span></div>
                        <div class="info-row"><span>Teléfono</span><span>{{ activeClient.phone ?? '—' }}</span></div>
                        <div class="info-row"><span>Email</span><span>{{ activeClient.email ?? '—' }}</span></div>
                        <div class="info-row"><span>Dirección</span><span>{{ activeClient.address ?? '—' }}</span></div>
                        <div v-if="activeClient.notes" class="info-row"><span>Notas</span><span>{{ activeClient.notes }}</span></div>
                    </div>

                    <div class="drawer-actions">
                        <button class="btn btn-ghost" @click="openEdit(activeClient)">Editar</button>
                        <a :href="route('pos.index')" class="btn btn-brand">Nueva Venta →</a>
                        <button class="btn btn-ghost" @click="toggleActive(activeClient)">
                            {{ activeClient.active ? 'Desactivar' : 'Activar' }}
                        </button>
                    </div>

                    <!-- Historial de tickets -->
                    <div v-if="clientSales.length" class="drawer-section">
                        <p class="section-label">Últimas 20 compras</p>
                        <div class="sale-list">
                            <div v-for="s in clientSales" :key="s.id" class="sale-row">
                                <span class="sale-ticket">{{ s.ticket_number }}</span>
                                <span class="sale-date muted-cell">{{ fmtDate(s.sold_at) }}</span>
                                <span class="sale-method muted-cell">{{ s.payment_method ?? '—' }}</span>
                                <span class="sale-total">{{ fmtBs(s.total_bs) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ─── Modal crear/editar ───────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showModal" class="modal-overlay">
                <div class="modal-box">
                    <div class="modal-header">
                        <h3>{{ modalMode === 'create' ? 'Nuevo cliente' : 'Editar cliente' }}</h3>
                        <button class="modal-close" @click="closeModal">×</button>
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label>Cédula <span class="opt">(V- / E-)</span></label>
                            <input v-model="form.cedula" type="text" maxlength="20" class="form-input" placeholder="Ej. V-12345678" />
                            <span v-if="errors.cedula" class="field-error">{{ errors.cedula[0] }}</span>
                        </div>
                        <div class="form-field">
                            <label>Nombre *</label>
                            <input v-model="form.name" type="text" maxlength="100" class="form-input" />
                            <span v-if="errors.name" class="field-error">{{ errors.name[0] }}</span>
                        </div>
                        <div class="form-field">
                            <label>Teléfono</label>
                            <input v-model="form.phone" type="tel" maxlength="30" class="form-input" />
                            <span v-if="errors.phone" class="field-error">{{ errors.phone[0] }}</span>
                        </div>
                        <div class="form-field">
                            <label>Email</label>
                            <input v-model="form.email" type="email" maxlength="100" class="form-input" />
                            <span v-if="errors.email" class="field-error">{{ errors.email[0] }}</span>
                        </div>
                        <div class="form-field full">
                            <label>Dirección</label>
                            <input v-model="form.address" type="text" class="form-input" />
                        </div>
                        <div class="form-field full">
                            <label>Notas</label>
                            <textarea v-model="form.notes" class="form-input" rows="2"></textarea>
                        </div>
                    </div>

                    <div class="modal-actions">
                        <button class="btn btn-ghost" @click="closeModal">Cancelar</button>
                        <button class="btn btn-brand" :disabled="saving" @click="submitForm">
                            {{ saving ? 'Guardando…' : 'Guardar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.clients-wrap { padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; max-width: 1200px; margin: 0 auto; }

/* KPIs */
.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
.kpi-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: 0.25rem; }
.kpi-label { font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
.kpi-value { font-size: 1.6rem; font-weight: 800; color: var(--text-primary); }
.kpi-value.accent { color: var(--brand); }
.kpi-value.muted  { color: var(--text-muted); }

/* Top bar */
.top-bar { display: flex; gap: 0.75rem; align-items: center; }
.search-input { flex: 1; padding: 0.6rem 0.9rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-card); color: var(--text-primary); font-size: 0.9rem; }
.search-input:focus { outline: none; border-color: var(--brand); }

/* Table */
.table-wrap { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow-x: auto; -webkit-overflow-scrolling: touch; min-width: 0; }
.data-table { width: 100%; min-width: 540px; border-collapse: collapse; }
.data-table th { padding: 0.65rem 1rem; text-align: left; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid var(--border); background: var(--bg-base); }
.data-row { cursor: pointer; transition: background 0.12s; }
.data-row:hover { background: var(--bg-base); }
.data-row td { padding: 0.7rem 1rem; border-bottom: 1px solid var(--border); font-size: 0.88rem; color: var(--text-primary); }
.data-row:last-child td { border-bottom: none; }
.name-cell { font-weight: 600; }
.muted-cell { color: var(--text-muted); }
.empty-row { text-align: center; color: var(--text-muted); padding: 2rem !important; }
.code-badge { display: inline-block; font-family: monospace; font-size: 0.78rem; background: var(--bg-base); border: 1px solid var(--border); border-radius: 4px; padding: 0.1rem 0.4rem; color: var(--text-muted); }
.opt { font-weight: 400; color: var(--text-muted); font-size: 0.75rem; }
.status-pill { font-size: 0.75rem; padding: 0.2rem 0.6rem; border-radius: 99px; font-weight: 600; }
.status-pill.active   { background: rgba(22,163,74,0.15); color: #16a34a; }
.status-pill.inactive { background: rgba(239,68,68,0.12); color: #ef4444; }
.icon-btn { background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 1rem; padding: 0.2rem 0.4rem; }
.icon-btn:hover { color: var(--brand); }

/* Pagination */
.pagination { display: flex; gap: 0.35rem; flex-wrap: wrap; }
.page-link { display: inline-flex; align-items: center; justify-content: center; min-width: 2rem; height: 2rem; padding: 0 0.5rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-muted); font-size: 0.82rem; cursor: pointer; text-decoration: none; }
.page-link.active   { background: var(--brand); color: #fff; border-color: var(--brand); }
.page-link.disabled { opacity: 0.4; cursor: not-allowed; }

/* Drawer */
.drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 200; display: flex; justify-content: flex-end; }
.drawer { width: 400px; max-width: 95vw; background: var(--bg-card); height: 100%; overflow-y: auto; display: flex; flex-direction: column; gap: 0; border-left: 1px solid var(--border); }
.drawer-header { display: flex; justify-content: space-between; align-items: flex-start; padding: 1.25rem; border-bottom: 1px solid var(--border); }
.drawer-name { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-top: 0.25rem; }
.drawer-info { padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: 0.5rem; border-bottom: 1px solid var(--border); }
.info-row { display: flex; justify-content: space-between; font-size: 0.85rem; }
.info-row span:first-child { color: var(--text-muted); }
.info-row span:last-child  { color: var(--text-primary); font-weight: 500; }
.drawer-actions { display: flex; gap: 0.5rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
.drawer-section { padding: 1rem 1.25rem; }
.section-label { font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.75rem; }
.sale-list { display: flex; flex-direction: column; gap: 0.4rem; }
.sale-row { display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0.65rem; background: var(--bg-base); border-radius: 6px; font-size: 0.83rem; }
.sale-ticket { font-family: monospace; color: var(--brand); font-size: 0.8rem; }
.sale-total  { margin-left: auto; font-weight: 700; color: var(--text-primary); }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 300; }
.modal-box { background: var(--bg-card); border-radius: 14px; width: 520px; max-width: 95vw; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
.modal-header { display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { font-size: 1.05rem; font-weight: 700; color: var(--text-primary); }
.modal-close { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: var(--text-muted); line-height: 1; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.form-field { display: flex; flex-direction: column; gap: 0.3rem; }
.form-field.full { grid-column: 1 / -1; }
.form-field label { font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.03em; }
.form-input { padding: 0.6rem 0.85rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-base); color: var(--text-primary); font-size: 0.9rem; width: 100%; }
.form-input:focus { outline: none; border-color: var(--brand); }
.field-error { font-size: 0.75rem; color: #ef4444; }
.modal-actions { display: flex; justify-content: flex-end; gap: 0.5rem; padding-top: 0.25rem; }

/* Buttons */
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.55rem 1.1rem; border-radius: 8px; font-size: 0.88rem; font-weight: 600; cursor: pointer; border: none; transition: opacity 0.15s; text-decoration: none; }
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-brand { background: var(--brand); color: #fff; }
.btn-brand:not(:disabled):hover { opacity: 0.88; }
.btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text-muted); }
.btn-ghost:hover { color: var(--text-primary); }

@media (max-width: 768px) {
    .clients-wrap { padding: 1rem 0.75rem; }
    .kpi-grid     { grid-template-columns: repeat(2, 1fr); }
    .top-bar      { flex-wrap: wrap; }
    .top-bar .btn { width: 100%; justify-content: center; }
    .search-input { width: 100%; }
    .data-table   { font-size: 0.8rem; }
    .drawer       { width: 100vw; }
}
@media (max-width: 640px) {
    .clients-wrap { padding: 0.75rem 0.6rem; min-height: 100dvh; }
    .kpi-grid     { grid-template-columns: 1fr 1fr; }
    .kpi-value    { font-size: 1.35rem; }
    .modal-overlay { align-items: flex-end; padding: 0; }
    .modal-box    { width: 100%; max-width: 100%; border-radius: 16px 16px 0 0; max-height: 90dvh; overflow-y: auto; padding: 1.25rem 1rem; }
    .form-grid    { grid-template-columns: 1fr; }
    .form-input   { font-size: 1rem; }
    .btn          { min-height: 44px; touch-action: manipulation; }
    .icon-btn     { min-width: 44px; min-height: 44px; display: inline-flex; align-items: center; justify-content: center; }
}
</style>
