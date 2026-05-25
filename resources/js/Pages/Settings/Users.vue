<script setup>
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import HelpModal      from '@/Components/HelpModal.vue'
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    users: Array,
})

const showModal = ref(false)
const editUser  = ref(null)

const form = useForm({
    name:        '',
    email:       '',
    role:        'cashier',
    password:    '',
    permissions: [],
})

const roleLabels = {
    admin:      'Administrador',
    supervisor: 'Supervisor',
    cashier:    'Cajero',
}
const roleBadgeClass = {
    admin:      'badge-brand',
    supervisor: 'badge-purple',
    cashier:    'badge-gray',
}

// ─── Permisos por módulo ────────────────────────────────────────────────────────
const MODULES = [
    { key: 'dashboard',   label: 'Dashboard' },
    { key: 'pos',         label: 'POS' },
    { key: 'cash',        label: 'Caja' },
    { key: 'sales',       label: 'Ventas del Día' },
    { key: 'dayclose',    label: 'Cierre del Día' },
    { key: 'orders',      label: 'Pedidos' },
    { key: 'clients',     label: 'Clientes' },
    { key: 'inventory',   label: 'Inventario' },
    { key: 'catalog',     label: 'Catálogo' },
    { key: 'boveda',      label: 'Bóveda' },
    { key: 'fabrica',     label: 'Fábrica' },
    { key: 'contingency', label: 'Contingencia' },
    { key: 'reports',     label: 'Reportes' },
    { key: 'settings',    label: 'Configuración' },
    { key: 'users',       label: 'Usuarios' },
]
const ALL_KEYS = MODULES.map(m => m.key)
const PRESETS = {
    cajero:         ['dashboard', 'pos', 'cash', 'sales', 'dayclose', 'orders', 'clients', 'inventory', 'contingency'],
    contadora:      ALL_KEYS.filter(k => k !== 'users'),
    admin_sucursal: ALL_KEYS.filter(k => k !== 'users'),
    dueno:          [...ALL_KEYS],
}
// Espejo de rolePermissions de AppLayout — pre-llena los checkboxes con el acceso efectivo actual cuando permissions es null
const rolePermissions = {
    admin:        ['dashboard', 'pos', 'inventory', 'boveda', 'fabrica', 'orders', 'sales', 'dayclose', 'catalog', 'clients', 'contingency', 'users', 'settings', 'cash'],
    owner:        ['dashboard', 'pos', 'inventory', 'boveda', 'fabrica', 'orders', 'sales', 'dayclose', 'catalog', 'clients', 'contingency', 'users', 'settings', 'cash'],
    branch_admin: ['dashboard', 'pos', 'inventory', 'boveda', 'fabrica', 'orders', 'sales', 'dayclose', 'catalog', 'clients', 'contingency', 'users', 'settings', 'cash'],
    supervisor:   ['dashboard', 'pos', 'cash', 'sales', 'dayclose', 'inventory', 'catalog', 'boveda', 'fabrica', 'orders', 'clients', 'reports', 'contingency'],
    analyst:      ['dashboard', 'pos', 'inventory', 'boveda', 'fabrica', 'orders', 'sales', 'dayclose', 'catalog', 'clients', 'contingency', 'settings', 'cash', 'reports'],
    cashier:      ['dashboard', 'pos', 'cash', 'sales', 'dayclose', 'orders', 'clients', 'inventory', 'contingency'],
}

const selectedPreset = ref('personalizado')

function sameSet(a, b) {
    if (a.length !== b.length) return false
    const sb = new Set(b)
    return a.every(x => sb.has(x))
}
function detectPreset(perms) {
    for (const key of ['cajero', 'contadora', 'admin_sucursal', 'dueno']) {
        if (sameSet(perms, PRESETS[key])) return key
    }
    return 'personalizado'
}
function applyPreset(key) {
    selectedPreset.value = key
    if (key !== 'personalizado') {
        form.permissions = [...PRESETS[key]]
    }
}
function onPermToggle() {
    selectedPreset.value = detectPreset(form.permissions)
}

function openNew() {
    editUser.value = null
    form.reset()
    form.role = 'cashier'
    selectedPreset.value = 'personalizado'
    showModal.value = true
}

function openEdit(user) {
    editUser.value = user
    form.name     = user.name
    form.email    = user.email
    form.role     = user.role
    form.password = ''
    const perms = Array.isArray(user.permissions) ? user.permissions : (rolePermissions[user.role] ?? [])
    form.permissions = [...perms]
    selectedPreset.value = detectPreset(form.permissions)
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    form.clearErrors()
}

function submit() {
    if (editUser.value) {
        form.put(route('settings.users.update', editUser.value.id), {
            onSuccess: closeModal,
        })
    } else {
        form.post(route('settings.users.store'), {
            onSuccess: closeModal,
        })
    }
}

function destroy(user) {
    if (!confirm(`¿Eliminar al usuario "${user.name}"?`)) return
    useForm({}).delete(route('settings.users.destroy', user.id))
}

const inputClass = 'w-full bg-[var(--bg-input)] border border-[var(--border)] text-[var(--text-primary)] rounded-lg px-3.5 py-2.5 text-sm placeholder:text-[var(--text-muted)] focus:border-[var(--brand)] focus:ring-1 focus:ring-[var(--brand)] outline-none transition'
const labelClass = 'block text-xs font-medium text-[var(--text-secondary)] mb-1.5'
const errorClass = 'mt-1 text-xs text-red-400'

// ─── Ayuda ────────────────────────────────────────────────────────────────────
const showHelp = ref(false)

const helpSteps = [
    {
        title: 'Gestión de usuarios del sistema',
        body:  'Este módulo es exclusivo del super administrador. Aquí se crean y administran las cuentas de acceso al sistema: nombre, correo, rol y contraseña.',
        tip:   'Para configuraciones avanzadas como sucursal, horario de acceso y suspensión, usa Configuración → Equipo.',
    },
    {
        title: 'Roles disponibles',
        body:  'Administrador: gestión completa de su sucursal. Supervisor: gestión operativa sin acceso a configuración. Cajero: acceso solo al POS e inventario sin costos.',
        tip:   'Asigna el rol mínimo necesario. Un cajero con rol de administrador por error puede modificar configuraciones críticas.',
    },
    {
        title: 'Crear y editar usuarios',
        body:  'Al crear un usuario se le asigna una contraseña inicial que debe comunicar de forma segura. Al editar, la contraseña solo se cambia si se ingresa una nueva — déjala en blanco para conservar la actual.',
        tip:   'El correo electrónico es opcional para cajeros pero necesario si el usuario necesita recuperar su contraseña por email.',
    },
]

const helpFaqs = [
    {
        q: '¿Qué diferencia hay entre este módulo y Configuración → Equipo?',
        a: 'Usuarios gestiona las cuentas de acceso al sistema (credenciales y rol). Equipo es más completo: incluye sucursal, horario, suspensión y cierre forzado de sesión.',
    },
    {
        q: '¿Puedo eliminar un usuario con ventas registradas?',
        a: 'No. El botón de eliminar estará deshabilitado si el usuario tiene ventas asociadas. En ese caso, suspéndelo desde Configuración → Equipo.',
    },
    {
        q: '¿La contraseña se puede ver una vez creada?',
        a: 'No. Las contraseñas se guardan cifradas y no son recuperables. Si un usuario olvida su contraseña, edita el usuario y asigna una nueva.',
    },
]
</script>

<template>
    <SettingsLayout>
        <div class="settings-panel">

            <!-- Header -->
            <div class="settings-panel__header">
                <div class="header-row">
                    <div>
                        <h2 class="settings-panel__title">Usuarios</h2>
                        <p class="settings-panel__subtitle">Gestiona quién tiene acceso al sistema</p>
                    </div>
                    <div class="header-actions">
                        <button class="help-btn" @click="showHelp = true" title="Ayuda">?</button>
                        <button class="btn-primary" @click="openNew">+ Agregar usuario</button>
                    </div>
                </div>
            </div>

            <!-- Tabla -->
            <div class="panel-body">
                <table class="data-table" v-if="users.length">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in users" :key="u.id">
                            <td class="td-name">
                                <div class="user-avatar">{{ u.name.split(' ').slice(0,2).map(w => w[0]).join('').toUpperCase() }}</div>
                                {{ u.name }}
                            </td>
                            <td class="text-[var(--text-secondary)]">{{ u.email }}</td>
                            <td>
                                <span :class="['badge', roleBadgeClass[u.role] ?? 'badge-gray']">
                                    {{ roleLabels[u.role] ?? u.role }}
                                </span>
                            </td>
                            <td class="td-actions">
                                <button class="icon-action" @click="openEdit(u)" title="Editar">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button class="icon-action icon-action--danger" @click="destroy(u)" title="Eliminar">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else class="empty-state">
                    <p>No hay usuarios registrados.</p>
                </div>
            </div>
        </div>

        <!-- ── Modal ─────────────────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showModal" class="modal-overlay">
                <div class="modal">
                    <div class="modal__header">
                        <h3>{{ editUser ? 'Editar usuario' : 'Nuevo usuario' }}</h3>
                        <button class="modal__close" @click="closeModal">✕</button>
                    </div>
                    <form @submit.prevent="submit" class="modal__body">
                        <div>
                            <label :class="labelClass">Nombre completo <span class="text-[var(--brand)]">*</span></label>
                            <input v-model="form.name" type="text" :class="inputClass" placeholder="Juan Pérez" autofocus />
                            <p v-if="form.errors.name" :class="errorClass">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">Correo electrónico <span class="text-[var(--brand)]">*</span></label>
                            <input v-model="form.email" type="email" :class="inputClass" placeholder="usuario@tunegocio.com" />
                            <p v-if="form.errors.email" :class="errorClass">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">Rol <span class="text-[var(--brand)]">*</span></label>
                            <select v-model="form.role" :class="inputClass">
                                <option value="admin">Administrador</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="cashier">Cajero</option>
                            </select>
                            <p v-if="form.errors.role" :class="errorClass">{{ form.errors.role }}</p>
                        </div>
                        <div>
                            <label :class="labelClass">
                                Contraseña
                                <span v-if="editUser" class="text-[var(--text-muted)] font-normal ml-1">— dejar en blanco para no cambiar</span>
                                <span v-else class="text-[var(--brand)]">*</span>
                            </label>
                            <input v-model="form.password" type="password" :class="inputClass" placeholder="Mínimo 8 caracteres" autocomplete="new-password" />
                            <p v-if="form.errors.password" :class="errorClass">{{ form.errors.password }}</p>
                        </div>

                        <!-- Panel de permisos — solo al editar -->
                        <div v-if="editUser" class="perm-panel">
                            <label :class="labelClass">Permisos de acceso</label>
                            <select :value="selectedPreset" @change="applyPreset($event.target.value)" :class="inputClass">
                                <option value="cajero">Cajero</option>
                                <option value="contadora">Contadora</option>
                                <option value="admin_sucursal">Admin Sucursal</option>
                                <option value="dueno">Dueño</option>
                                <option value="personalizado">Personalizado</option>
                            </select>
                            <div class="perm-grid">
                                <label v-for="m in MODULES" :key="m.key" class="perm-check">
                                    <input type="checkbox" :value="m.key" v-model="form.permissions" @change="onPermToggle" />
                                    <span>{{ m.label }}</span>
                                </label>
                            </div>
                            <p v-if="form.errors.permissions" :class="errorClass">{{ form.errors.permissions }}</p>
                        </div>

                        <div class="modal__footer">
                            <button type="button" class="btn-secondary" @click="closeModal">Cancelar</button>
                            <button type="submit" class="btn-primary" :disabled="form.processing">
                                {{ editUser ? 'Guardar cambios' : 'Crear usuario' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Panel de ayuda ───────────────────────────────────────────────── -->
        <HelpModal
            :show="showHelp"
            title="Usuarios — Cómo funciona"
            :steps="helpSteps"
            :faqs="helpFaqs"
            @close="showHelp = false"
        />

    </SettingsLayout>
</template>

<style scoped>
.settings-panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.settings-panel__header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); }
.header-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.header-actions { display: flex; align-items: center; gap: 0.5rem; }
.help-btn {
    border-radius: 50%;
    width: 28px; height: 28px;
    padding: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem; font-weight: 700;
    border: 1.5px solid var(--border);
    background: none; color: var(--text-muted);
    cursor: pointer; flex-shrink: 0;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.help-btn:hover { background: var(--brand); color: #fff; border-color: var(--brand); }
.settings-panel__title { font-size: 1rem; font-weight: 700; color: var(--text-primary); }
.settings-panel__subtitle { font-size: 0.8rem; color: var(--text-muted); margin-top: 2px; }
.panel-body { padding: 1.25rem 1.5rem; }

.data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.data-table th { text-align: left; font-size: 11px; font-weight: 600; letter-spacing: 0.05em; color: var(--text-muted); padding: 0 0.75rem 0.75rem; text-transform: uppercase; }
.data-table td { padding: 0.75rem; border-top: 1px solid var(--border); color: var(--text-primary); }
.td-name { display: flex; align-items: center; gap: 0.625rem; font-weight: 500; }
.td-actions { display: flex; gap: 0.375rem; justify-content: flex-end; }

.user-avatar { width: 28px; height: 28px; border-radius: 50%; background: color-mix(in srgb, var(--brand) 20%, transparent); color: var(--brand); font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

.badge { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; }
.badge-brand { background: color-mix(in srgb, var(--brand) 15%, transparent); color: var(--brand); }
.badge-purple { background: #3b0764; color: #c084fc; }
.badge-gray { background: var(--bg-input); color: var(--text-secondary); }

.icon-action { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 6px; border: none; background: transparent; color: var(--text-muted); cursor: pointer; transition: background 0.15s, color 0.15s; }
.icon-action svg { width: 15px; height: 15px; }
.icon-action:hover { background: var(--hover); color: var(--text-primary); }
.icon-action--danger:hover { background: #3f0d0d; color: #f87171; }
.empty-state { padding: 2rem; text-align: center; color: var(--text-muted); font-size: 0.875rem; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(2px); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 1rem; }
.modal { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; width: 100%; max-width: 440px; overflow: hidden; }
.modal__header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); }
.modal__header h3 { font-size: 0.9375rem; font-weight: 700; color: var(--text-primary); }
.modal__close { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1rem; padding: 0.25rem; }
.modal__body { padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
.modal__footer { display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 0.25rem; }

.btn-primary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1.25rem; background: var(--brand); color: #fff; font-size: 0.875rem; font-weight: 600; border-radius: 8px; border: none; cursor: pointer; transition: opacity 0.15s; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary:hover:not(:disabled) { opacity: 0.9; }
.btn-secondary { display: inline-flex; align-items: center; padding: 0.5rem 1rem; background: var(--bg-input); border: 1px solid var(--border); color: var(--text-secondary); font-size: 0.875rem; font-weight: 500; border-radius: 8px; cursor: pointer; }

/* ── Panel de permisos ──────────────────────────────────────────────────── */
.perm-panel { display: flex; flex-direction: column; gap: 0.5rem; border-top: 1px solid var(--border); padding-top: 1rem; }
.perm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.25rem 0.75rem; margin-top: 0.25rem; }
@media (min-width: 480px) { .perm-grid { grid-template-columns: 1fr 1fr 1fr; } }
.perm-check { display: flex; align-items: center; gap: 0.5rem; min-height: 40px; padding: 0.25rem 0; font-size: 0.8125rem; color: var(--text-secondary); cursor: pointer; }
.perm-check input { width: 18px; height: 18px; accent-color: var(--brand); cursor: pointer; flex-shrink: 0; }
</style>
