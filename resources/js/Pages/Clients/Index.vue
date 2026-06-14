<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import HelpModal from '@/Components/HelpModal.vue'
import { Eye, Pencil, Trash2 } from '@lucide/vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

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
const search = ref(props.filters?.q ?? '')
let searchTimer = null
function onSearch() {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        router.get(route('clients.index'), { q: search.value }, { preserveScroll: true, preserveState: true })
    }, 300)
}

// ─── Modal crear/editar ───────────────────────────────────────────────────────
const showModal     = ref(false)
const modalMode     = ref('create')
const editingClient = ref(null)
const saving        = ref(false)
const errors        = ref({})
const form          = ref({ cedula: '', name: '', phone: '', email: '', address: '', notes: '' })

function openCreate() {
    form.value = { cedula: '', name: '', phone: '', email: '', address: '', notes: '' }
    errors.value = {}
    modalMode.value = 'create'
    editingClient.value = null
    showModal.value = true
}

function openEdit(client) {
    form.value = {
        cedula:  client.cedula  ?? '',
        name:    client.name,
        phone:   client.phone   ?? '',
        email:   client.email   ?? '',
        address: client.address ?? '',
        notes:   client.notes   ?? '',
    }
    errors.value = {}
    modalMode.value = 'edit'
    editingClient.value = { ...client }
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    editingClient.value = null
    saving.value = false
    errors.value = {}
}

async function submitForm() {
    if (saving.value) return
    saving.value = true
    errors.value = {}
    try {
        if (modalMode.value === 'edit') {
            await axios.put(
                route('clients.update', { client: editingClient.value.id }),
                form.value
            )
        } else {
            await axios.post(route('clients.store'), form.value)
        }
        closeModal()
        router.reload({ preserveScroll: true })
    } catch (err) {
        errors.value = err.response?.data?.errors ?? {}
        if (err.response?.data?.message) {
            errors.value._general = err.response.data.message
        }
    } finally {
        saving.value = false
    }
}

// ─── Drawer historial ─────────────────────────────────────────────────────────
const showDrawer   = ref(false)
const drawerClient = ref(null)
const drawerSales  = ref([])
const loadingSales = ref(false)

async function openDrawer(client) {
    drawerClient.value = { ...client }
    drawerSales.value  = []
    loadingSales.value = true
    showDrawer.value   = true
    try {
        const r = await axios.get(route('clients.show', { client: client.id }), {
            headers: {
                'X-Inertia': 'true',
                'X-Inertia-Partial-Data': 'clientSales',
                'X-Inertia-Partial-Component': 'Clients/Index',
            }
        })
        drawerSales.value = r.data?.props?.clientSales ?? []
    } catch {
        drawerSales.value = []
    } finally {
        loadingSales.value = false
    }
}

function closeDrawer() {
    showDrawer.value   = false
    drawerClient.value = null
    drawerSales.value  = []
}

// ─── Eliminar ─────────────────────────────────────────────────────────────────
const showDeleteModal = ref(false)
const deletingClient  = ref(null)
const deleting        = ref(false)

function openDelete(client) {
    deletingClient.value  = { ...client }
    showDeleteModal.value = true
}

function closeDelete() {
    showDeleteModal.value = false
    deletingClient.value  = null
}

async function confirmDelete() {
    if (deleting.value) return
    deleting.value = true
    try {
        await axios.delete(route('clients.destroy', { client: deletingClient.value.id }))
        if (showDrawer.value && drawerClient.value?.id === deletingClient.value?.id) {
            closeDrawer()
        }
        closeDelete()
        router.reload({ preserveScroll: true })
    } catch (err) {
        alert(err.response?.data?.message ?? 'Error al eliminar.')
    } finally {
        deleting.value = false
    }
}

// ─── Ayuda ────────────────────────────────────────────────────────────────────
const showHelp = ref(false)

const helpSteps = [
    { title: 'Registrar un cliente', body: 'Agrega el nombre y teléfono del cliente para llevar historial de compras.', tip: 'El teléfono es importante para delivery y crédito.' },
    { title: 'Buscar un cliente', body: 'Escribe el nombre, teléfono o cédula en el buscador. Filtra en tiempo real.', tip: 'También puedes buscarlo desde el POS al momento de vender.' },
    { title: 'Ver historial de compras', body: 'Toca el ícono de ojo en la fila del cliente para ver sus últimas 20 compras.', tip: 'Útil para clientes frecuentes o que compran a crédito.' },
    { title: 'Editar o eliminar', body: 'Usa el ícono de lápiz para editar datos. El ícono de papelera elimina o desactiva si tiene ventas.', tip: 'Un cliente desactivado no aparece en el buscador del POS.' },
]

const helpFaqs = [
    { q: '¿Es obligatorio registrar el cliente al vender?', a: 'No. Solo si quieres llevar historial o es una venta a crédito.' },
    { q: '¿Qué pasa al eliminar un cliente con ventas?', a: 'Se desactiva en lugar de eliminar para preservar el historial.' },
    { q: '¿Cómo busco un cliente en el POS?', a: 'En el campo cliente del POS escribe el nombre y aparece el autocompletado.' },
    { q: '¿Puedo registrar la cédula?', a: 'Sí, el campo cédula es opcional en el formulario de cliente.' },
    { q: '¿Cómo reactivo un cliente desactivado?', a: 'Con el botón Editar cambia el estado a Activo.' },
]
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
                    @input="onSearch"
                />
                <button class="btn btn-brand" @click="openCreate">+ Nuevo cliente</button>
                <button class="help-btn" @click="showHelp = true" title="Ayuda">?</button>
            </div>

            <!-- ─── Tabla ─────────────────────────────────────────────────── -->
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Cédula</th>
                            <th>Nombre</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Ventas</th>
                            <th>Última venta</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in clients.data" :key="c.id" class="data-row">
                            <td data-label="Cédula">
                                <span class="code-badge">{{ c.cedula ?? '—' }}</span>
                            </td>
                            <td class="name-cell" data-label="Nombre">{{ c.name }}</td>
                            <td class="muted-cell" data-label="Teléfono">{{ c.phone ?? '—' }}</td>
                            <td class="muted-cell" data-label="Email">{{ c.email ?? '—' }}</td>
                            <td class="muted-cell" data-label="Ventas">{{ c.sales_count }}</td>
                            <td class="muted-cell" data-label="Última venta">{{ fmtDate(c.sales_max_sold_at) }}</td>
                            <td data-label="Estado">
                                <span class="status-pill" :class="c.active ? 'active' : 'inactive'">
                                    {{ c.active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="actions-cell" data-label="">
                                <button class="icon-btn" title="Ver historial" @click="openDrawer(c)">
                                    <Eye :size="15" />
                                </button>
                                <button class="icon-btn" title="Editar" @click="openEdit(c)">
                                    <Pencil :size="15" />
                                </button>
                                <button class="icon-btn icon-btn--danger" title="Eliminar" @click="openDelete(c)">
                                    <Trash2 :size="15" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!clients.data?.length">
                            <td colspan="8" class="empty-row">No hay clientes registrados.</td>
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

        <!-- ─── Drawer historial ──────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showDrawer" class="drawer-overlay" @click.self="closeDrawer">
                <div class="drawer">
                    <div class="drawer-header">
                        <div>
                            <span v-if="drawerClient?.cedula" class="code-badge">{{ drawerClient.cedula }}</span>
                            <h3 class="drawer-name">{{ drawerClient?.name }}</h3>
                        </div>
                        <button class="modal-close" @click="closeDrawer">×</button>
                    </div>

                    <div class="drawer-info">
                        <div class="info-row"><span>Teléfono</span><span>{{ drawerClient?.phone ?? '—' }}</span></div>
                        <div class="info-row"><span>Email</span><span>{{ drawerClient?.email ?? '—' }}</span></div>
                        <div class="info-row"><span>Dirección</span><span>{{ drawerClient?.address ?? '—' }}</span></div>
                        <div v-if="drawerClient?.notes" class="info-row"><span>Notas</span><span>{{ drawerClient?.notes }}</span></div>
                    </div>

                    <div class="drawer-actions">
                        <button class="btn btn-ghost" @click="openEdit(drawerClient); closeDrawer()">Editar</button>
                        <button class="btn btn-ghost btn-danger-ghost" @click="openDelete(drawerClient); closeDrawer()">Eliminar</button>
                    </div>

                    <div class="drawer-section">
                        <p class="section-label">Últimas 20 compras</p>
                        <p v-if="loadingSales" class="loading-msg">Cargando…</p>
                        <p v-else-if="!drawerSales.length" class="loading-msg">Sin compras registradas.</p>
                        <div v-else class="sale-list">
                            <div v-for="s in drawerSales" :key="s.id" class="sale-row">
                                <span class="sale-ticket">{{ s.ticket_number }}</span>
                                <span class="sale-date">{{ fmtDate(s.sold_at) }}</span>
                                <span class="sale-total">{{ fmtBs(s.total_bs) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ─── Modal crear/editar ───────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
                <div class="modal-box">
                    <div class="modal-header">
                        <h3>{{ modalMode === 'create' ? 'Nuevo cliente' : 'Editar cliente' }}</h3>
                        <button class="modal-close" @click="closeModal">×</button>
                    </div>

                    <p v-if="errors._general" class="field-error" style="margin-bottom:0.25rem">{{ errors._general }}</p>

                    <div class="form-grid">
                        <div class="form-field">
                            <label>Cédula <span class="opt">(V- / E-)</span></label>
                            <input v-model="form.cedula" type="text" maxlength="20" class="form-input" placeholder="Ej. V-12345678" />
                            <span v-if="errors.cedula" class="field-error">{{ errors.cedula[0] }}</span>
                        </div>
                        <div class="form-field">
                            <label>Nombre *</label>
                            <input v-model="form.name" type="text" maxlength="100" class="form-input" autofocus />
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

        <!-- ─── Modal confirmar eliminar ─────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showDeleteModal" class="modal-overlay" @click.self="closeDelete">
                <div class="modal-box modal-sm">
                    <div class="modal-header">
                        <h3>Eliminar cliente</h3>
                        <button class="modal-close" @click="closeDelete">×</button>
                    </div>
                    <p class="confirm-msg">
                        ¿Eliminar <strong>{{ deletingClient?.name }}</strong>?
                        Si tiene ventas asociadas, será desactivado en lugar de eliminado.
                    </p>
                    <div class="modal-actions">
                        <button class="btn btn-ghost" @click="closeDelete">Cancelar</button>
                        <button class="btn btn-danger" :disabled="deleting" @click="confirmDelete">
                            {{ deleting ? 'Eliminando…' : 'Confirmar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ─── Módulo de ayuda ───────────────────────────────────────────── -->
        <HelpModal
            :show="showHelp"
            title="Clientes — Cómo funciona"
            :steps="helpSteps"
            :faqs="helpFaqs"
            @close="showHelp = false"
        />
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
.help-btn { width: 2rem; height: 2rem; min-width: 2rem; border-radius: 50%; border: 1px solid var(--border); background: transparent; color: var(--text-muted); font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.15s, color 0.15s, border-color 0.15s; }
.help-btn:hover { background: var(--brand); color: #fff; border-color: var(--brand); }

/* Table */
.table-wrap { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.data-table { width: 100%; min-width: 640px; border-collapse: collapse; }
.data-table th { padding: 0.65rem 1rem; text-align: left; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid var(--border); background: var(--bg-base); white-space: nowrap; }
.data-row { transition: background 0.12s; }
.data-row:hover { background: var(--bg-base); }
.data-row td { padding: 0.7rem 1rem; border-bottom: 1px solid var(--border); font-size: 0.88rem; color: var(--text-primary); vertical-align: middle; }
.data-row:last-child td { border-bottom: none; }
.name-cell { font-weight: 600; }
.muted-cell { color: var(--text-muted); }
.actions-cell { white-space: nowrap; }
.empty-row { text-align: center; color: var(--text-muted); padding: 2rem !important; }
.code-badge { display: inline-block; font-family: monospace; font-size: 0.78rem; background: var(--bg-base); border: 1px solid var(--border); border-radius: 4px; padding: 0.1rem 0.4rem; color: var(--text-muted); }
.status-pill { font-size: 0.75rem; padding: 0.2rem 0.6rem; border-radius: 99px; font-weight: 600; }
.status-pill.active   { background: rgba(22,163,74,0.15); color: #16a34a; }
.status-pill.inactive { background: rgba(239,68,68,0.12); color: #ef4444; }
.icon-btn { background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 0.25rem; border-radius: 4px; display: inline-flex; align-items: center; transition: color 0.15s, background 0.15s; }
.icon-btn:hover { color: var(--brand); background: rgba(0,0,0,0.05); }
.icon-btn--danger:hover { color: #ef4444; background: rgba(239,68,68,0.08); }

/* Pagination */
.pagination { display: flex; gap: 0.35rem; flex-wrap: wrap; }
.page-link { display: inline-flex; align-items: center; justify-content: center; min-width: 2rem; height: 2rem; padding: 0 0.5rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-muted); font-size: 0.82rem; cursor: pointer; text-decoration: none; }
.page-link.active   { background: var(--brand); color: #fff; border-color: var(--brand); }
.page-link.disabled { opacity: 0.4; cursor: not-allowed; }

/* Drawer */
.drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 200; display: flex; justify-content: flex-end; }
.drawer { width: 420px; max-width: 95vw; background: var(--bg-card); height: 100%; overflow-y: auto; display: flex; flex-direction: column; border-left: 1px solid var(--border); box-shadow: -4px 0 24px rgba(0,0,0,0.15); }
.drawer-header { display: flex; justify-content: space-between; align-items: flex-start; padding: 1.25rem; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.drawer-name { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-top: 0.25rem; }
.drawer-info { padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: 0.5rem; border-bottom: 1px solid var(--border); }
.info-row { display: flex; justify-content: space-between; font-size: 0.85rem; gap: 1rem; }
.info-row span:first-child { color: var(--text-muted); flex-shrink: 0; }
.info-row span:last-child  { color: var(--text-primary); font-weight: 500; text-align: right; }
.drawer-actions { display: flex; gap: 0.5rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
.drawer-section { padding: 1rem 1.25rem; flex: 1; }
.section-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.75rem; }
.loading-msg { font-size: 0.82rem; color: var(--text-muted); padding: 0.5rem 0; }
.sale-list { display: flex; flex-direction: column; gap: 0.4rem; }
.sale-row { display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0.65rem; background: var(--bg-base); border-radius: 6px; font-size: 0.83rem; }
.sale-ticket { font-family: monospace; color: var(--brand); font-size: 0.8rem; flex-shrink: 0; }
.sale-date { color: var(--text-muted); font-size: 0.8rem; }
.sale-total { margin-left: auto; font-weight: 700; color: var(--text-primary); white-space: nowrap; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 300; padding: 1rem; }
.modal-box { background: var(--bg-card); border-radius: 14px; width: 520px; max-width: 100%; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; max-height: 90vh; overflow-y: auto; }
.modal-sm { width: 400px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { font-size: 1.05rem; font-weight: 700; color: var(--text-primary); }
.modal-close { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: var(--text-muted); line-height: 1; padding: 0.1rem 0.3rem; }
.modal-close:hover { color: var(--text-primary); }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.form-field { display: flex; flex-direction: column; gap: 0.3rem; }
.form-field.full { grid-column: 1 / -1; }
.form-field label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.03em; font-weight: 600; }
.opt { font-weight: 400; text-transform: none; }
.form-input { padding: 0.6rem 0.85rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-base); color: var(--text-primary); font-size: 0.9rem; width: 100%; box-sizing: border-box; }
.form-input:focus { outline: none; border-color: var(--brand); }
textarea.form-input { resize: vertical; min-height: 60px; }
.field-error { font-size: 0.75rem; color: #ef4444; }
.modal-actions { display: flex; justify-content: flex-end; gap: 0.5rem; padding-top: 0.25rem; }
.confirm-msg { font-size: 0.92rem; color: var(--text-primary); line-height: 1.6; }

/* Buttons */
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.55rem 1.1rem; border-radius: 8px; font-size: 0.88rem; font-weight: 600; cursor: pointer; border: none; transition: opacity 0.15s, background 0.15s; text-decoration: none; white-space: nowrap; }
.btn:disabled { opacity: 0.45; cursor: not-allowed; }
.btn-brand { background: var(--brand); color: #fff; }
.btn-brand:not(:disabled):hover { opacity: 0.88; }
.btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text-muted); }
.btn-ghost:hover { color: var(--text-primary); border-color: var(--text-muted); }
.btn-danger { background: rgba(239,68,68,0.12); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
.btn-danger:not(:disabled):hover { background: rgba(239,68,68,0.22); }
.btn-danger-ghost { color: #ef4444; border-color: rgba(239,68,68,0.3); }
.btn-danger-ghost:hover { background: rgba(239,68,68,0.08); color: #ef4444; border-color: rgba(239,68,68,0.4); }

/* Responsive */
@media (max-width: 1023px) {
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .drawer   { width: min(420px, 95vw); }
}
@media (max-width: 768px) {
    .clients-wrap { padding: 1rem 0.75rem; }
    .top-bar      { flex-wrap: wrap; }
    .search-input { width: 100%; }
    .drawer       { width: 100vw; max-width: 100vw; }
}
@media (max-width: 640px) {
    .clients-wrap { padding: 0.75rem 0.6rem; }
    .kpi-grid     { grid-template-columns: 1fr 1fr; }
    .kpi-value    { font-size: 1.35rem; }
    .modal-overlay { align-items: flex-end; padding: 0; }
    .modal-box    { width: 100%; max-width: 100%; border-radius: 16px 16px 0 0; max-height: 92dvh; }
    .form-grid    { grid-template-columns: 1fr; }
    .form-input   { font-size: 1rem; }
    .btn          { min-height: 44px; }
    .icon-btn     { min-width: 36px; min-height: 36px; }
}
</style>
