<script setup>
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import { ref, computed } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'

const props = defineProps({
    branches: Array,
    users:    Array,
})

const page  = usePage()
const flash = computed(() => page.props.flash ?? {})

const roleLabels = { admin: 'Administrador', cashier: 'Cajero', analyst: 'Analista' }

function branchName(id) {
    return props.branches.find(b => b.id === id)?.name ?? '—'
}

// ─── Modal crear / editar ─────────────────────────────────────────────────
const showModal  = ref(false)
const editTarget = ref(null)

const DAYS = [
    { key: 'mon', label: 'L' , full: 'Lunes'     },
    { key: 'tue', label: 'M' , full: 'Martes'    },
    { key: 'wed', label: 'X' , full: 'Miércoles' },
    { key: 'thu', label: 'J' , full: 'Jueves'    },
    { key: 'fri', label: 'V' , full: 'Viernes'   },
    { key: 'sat', label: 'S' , full: 'Sábado'    },
    { key: 'sun', label: 'D' , full: 'Domingo'   },
]

const form = useForm({
    name:         '',
    email:        '',
    password:     '',
    role:         'cashier',
    branch_id:    null,
    access_start: '',
    access_end:   '',
    access_days:  [],
})

function openCreate() {
    editTarget.value = null
    form.reset()
    showModal.value  = true
}

function openEdit(user) {
    editTarget.value  = user
    form.name         = user.name
    form.email        = user.email ?? ''
    form.password     = ''
    form.role         = user.role
    form.branch_id    = user.branch_id
    form.access_start = user.access_start ?? ''
    form.access_end   = user.access_end   ?? ''
    form.access_days  = user.access_days  ?? []
    showModal.value   = true
}

function closeModal() {
    showModal.value = false
    editTarget.value = null
    form.reset()
    form.clearErrors()
}

function submit() {
    const opts = { preserveScroll: true, onSuccess: closeModal }
    editTarget.value
        ? form.put(route('settings.team.update', editTarget.value.id), opts)
        : form.post(route('settings.team.store'), opts)
}

// ─── Acciones rápidas ─────────────────────────────────────────────────────
function toggleActive(user) {
    if (!confirm(`¿${user.is_active ? 'Suspender' : 'Reactivar'} a ${user.name}?`)) return
    router.patch(route('settings.team.toggle-active', user.id), {}, { preserveScroll: true })
}

function killSession(user) {
    router.patch(route('settings.team.kill-session', user.id), {}, { preserveScroll: true })
}

function daysLabel(days) {
    if (!days?.length) return 'Todos los días'
    return days.map(d => DAYS.find(x => x.key === d)?.label ?? d).join(' ')
}

function destroy(user) {
    if (!confirm(`¿Eliminar a ${user.name}? No se puede deshacer.`)) return
    router.delete(route('settings.team.destroy', user.id), { preserveScroll: true })
}
</script>

<template>
    <SettingsLayout>
        <div class="page">

            <div class="page-head">
                <div>
                    <h2 class="page-title">Equipo</h2>
                    <p class="page-sub">Usuarios del negocio, su sucursal y horario de acceso.</p>
                </div>
                <button class="btn-brand" @click="openCreate">+ Nuevo usuario</button>
            </div>

            <div v-if="flash.success" class="flash flash--ok">{{ flash.success }}</div>
            <div v-if="flash.error"   class="flash flash--err">{{ flash.error }}</div>

            <!-- Tabla -->
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Sucursal</th>
                            <th>Acceso</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!users.length">
                            <td colspan="6" class="empty-row">No hay usuarios. Crea el primero.</td>
                        </tr>
                        <tr v-for="user in users" :key="user.id" :class="{ 'row--dim': !user.is_active }">
                            <td>
                                <div class="user-name">{{ user.name }}</div>
                                <div class="user-email">{{ user.email?.includes('@internal.syntimeat') ? '—' : user.email }}</div>
                            </td>
                            <td><span :class="['role-badge', `role--${user.role}`]">{{ roleLabels[user.role] ?? user.role }}</span></td>
                            <td class="muted">{{ branchName(user.branch_id) }}</td>
                            <td>
                                <div class="access-info">
                                    <span class="access-days">{{ daysLabel(user.access_days) }}</span>
                                    <span v-if="user.access_start && user.access_end" class="access-time muted mono">
                                        {{ user.access_start?.slice(0,5) }} – {{ user.access_end?.slice(0,5) }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span :class="['status-dot', user.is_active ? 'dot--on' : 'dot--off']" />
                                {{ user.is_active ? 'Activo' : 'Suspendido' }}
                            </td>
                            <td class="actions">
                                <button class="btn-act" @click="openEdit(user)" title="Editar">Editar</button>
                                <button
                                    class="btn-act act--session"
                                    @click="killSession(user)"
                                    title="Cierra la sesión activa de este usuario ahora mismo"
                                >⏏ Sesión</button>
                                <button
                                    class="btn-act"
                                    :class="user.is_active ? 'act--warn' : 'act--ok'"
                                    @click="toggleActive(user)"
                                >{{ user.is_active ? 'Suspender' : 'Reactivar' }}</button>
                                <button
                                    class="btn-act act--danger"
                                    :disabled="user.has_sales"
                                    :title="user.has_sales ? 'Tiene ventas — suspéndelo en su lugar' : ''"
                                    @click="destroy(user)"
                                >Eliminar</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="overlay" @click.self="closeModal">
                <div class="modal">
                    <div class="modal-head">
                        <h3>{{ editTarget ? 'Editar usuario' : 'Nuevo usuario' }}</h3>
                        <button @click="closeModal">✕</button>
                    </div>
                    <form class="modal-body" @submit.prevent="submit">

                        <div class="field"><label>Nombre *</label>
                            <input v-model="form.name" type="text" class="input" required />
                            <span v-if="form.errors.name" class="err">{{ form.errors.name }}</span>
                        </div>

                        <div class="field">
                            <label>Correo electrónico <span class="opt">(opcional para cajeros)</span></label>
                            <input v-model="form.email" type="email" class="input" placeholder="Sin correo → acceso solo por contraseña" />
                            <span v-if="form.errors.email" class="err">{{ form.errors.email }}</span>
                        </div>

                        <div class="field">
                            <label>Contraseña{{ editTarget ? ' (dejar vacío para no cambiar)' : ' *' }}</label>
                            <input v-model="form.password" type="password" class="input" :required="!editTarget" minlength="8" autocomplete="new-password" />
                            <span v-if="form.errors.password" class="err">{{ form.errors.password }}</span>
                        </div>

                        <div class="field-row">
                            <div class="field"><label>Rol *</label>
                                <select v-model="form.role" class="input" required>
                                    <option value="admin">Administrador</option>
                                    <option value="cashier">Cajero</option>
                                    <option value="analyst">Analista</option>
                                </select>
                                <span v-if="form.errors.role" class="err">{{ form.errors.role }}</span>
                            </div>
                            <div class="field"><label>Sucursal</label>
                                <select v-model="form.branch_id" class="input">
                                    <option :value="null">Sin asignar</option>
                                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="field">
                            <label>Días habilitados <span class="opt">(vacío = todos los días)</span></label>
                            <div class="day-picker">
                                <button
                                    v-for="d in DAYS" :key="d.key"
                                    type="button"
                                    :class="['day-btn', form.access_days.includes(d.key) && 'day-btn--on']"
                                    :title="d.full"
                                    @click="form.access_days.includes(d.key)
                                        ? form.access_days.splice(form.access_days.indexOf(d.key), 1)
                                        : form.access_days.push(d.key)"
                                >{{ d.label }}</button>
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field"><label>Acceso desde</label>
                                <input v-model="form.access_start" type="time" class="input" />
                            </div>
                            <div class="field"><label>Acceso hasta</label>
                                <input v-model="form.access_end" type="time" class="input" />
                            </div>
                        </div>
                        <p class="hint">El sistema cerrará la sesión automáticamente fuera del horario o días configurados.</p>

                        <div class="modal-foot">
                            <button type="button" class="btn-ghost" @click="closeModal">Cancelar</button>
                            <button type="submit" class="btn-brand" :disabled="form.processing">
                                {{ editTarget ? 'Guardar cambios' : 'Crear usuario' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </SettingsLayout>
</template>

<style scoped>
.page      { display: flex; flex-direction: column; gap: 1rem; }
.page-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.page-title { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); }
.page-sub   { font-size: .8rem; color: var(--text-muted); margin-top: 2px; }

.flash      { padding: .6rem 1rem; border-radius: 8px; font-size: .85rem; font-weight: 500; }
.flash--ok  { background: color-mix(in srgb,#22c55e 15%,transparent); color: #22c55e; }
.flash--err { background: color-mix(in srgb,#ef4444 15%,transparent); color: #ef4444; }

.table-wrap { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow-x: auto; -webkit-overflow-scrolling: touch; min-width: 0; }
.tbl        { width: 100%; min-width: 640px; border-collapse: collapse; font-size: .85rem; }
.tbl th     { padding: .6rem 1rem; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted); border-bottom: 1px solid var(--border); text-align: left; }
.tbl td     { padding: .7rem 1rem; border-bottom: 1px solid var(--border); color: var(--text-primary); vertical-align: middle; }
.tbl tr:last-child td { border-bottom: none; }
.tbl tr:hover td { background: var(--hover); }
.row--dim td { opacity: .55; }

.user-name  { font-weight: 600; }
.user-email { font-size: .75rem; color: var(--text-muted); }
.muted      { color: var(--text-muted); }
.mono       { font-size: .78rem; font-family: monospace; }

.role-badge     { font-size: .7rem; font-weight: 700; padding: .15rem .55rem; border-radius: 20px; }
.role--admin    { background: color-mix(in srgb,var(--brand) 15%,transparent); color: var(--brand); }
.role--cashier  { background: color-mix(in srgb,#6b7280 20%,transparent); color: #9ca3af; }
.role--analyst  { background: color-mix(in srgb,#a855f7 15%,transparent); color: #a855f7; }

.status-dot  { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: .35rem; vertical-align: middle; }
.dot--on     { background: #22c55e; }
.dot--off    { background: #6b7280; }

.actions    { display: flex; gap: .35rem; }
.btn-act    { font-size: .72rem; font-weight: 500; padding: .25rem .6rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-secondary); cursor: pointer; white-space: nowrap; }
.btn-act:hover      { background: var(--hover); color: var(--text-primary); }
.act--warn          { color: #f59e0b; border-color: color-mix(in srgb,#f59e0b 30%,transparent); }
.act--warn:hover    { background: color-mix(in srgb,#f59e0b 10%,transparent); }
.act--ok            { color: #22c55e; border-color: color-mix(in srgb,#22c55e 30%,transparent); }
.act--ok:hover      { background: color-mix(in srgb,#22c55e 10%,transparent); }
.act--danger        { color: #ef4444; border-color: color-mix(in srgb,#ef4444 30%,transparent); }
.act--danger:hover:not(:disabled) { background: color-mix(in srgb,#ef4444 10%,transparent); }
.act--danger:disabled { opacity: .35; cursor: not-allowed; }
.act--session { color: #60a5fa; border-color: color-mix(in srgb,#60a5fa 30%,transparent); }
.act--session:hover { background: color-mix(in srgb,#60a5fa 10%,transparent); }

.access-info  { display: flex; flex-direction: column; gap: .15rem; }
.access-days  { font-size: .78rem; font-weight: 500; color: var(--text-primary); }
.access-time  { font-size: .72rem; color: var(--text-muted); font-family: monospace; }

.day-picker   { display: flex; gap: .35rem; flex-wrap: wrap; }
.day-btn      { width: 2rem; height: 2rem; border-radius: 6px; border: 1px solid var(--border); background: var(--hover); color: var(--text-muted); font-size: .8rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.day-btn--on  { background: var(--brand); border-color: var(--brand); color: #fff; }

.empty-row  { text-align: center; color: var(--text-muted); padding: 2rem; }

/* Modal */
.overlay    { position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 50; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.modal      { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; width: 100%; max-width: 480px; }
.modal-head { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); }
.modal-head h3 { font-size: .95rem; font-weight: 700; color: var(--text-primary); }
.modal-head button { background: none; border: none; color: var(--text-muted); font-size: 1rem; cursor: pointer; }
.modal-body { padding: 1.25rem; display: flex; flex-direction: column; gap: .85rem; max-height: 75vh; overflow-y: auto; }
.modal-foot { display: flex; justify-content: flex-end; gap: .6rem; padding-top: .5rem; }
.field      { display: flex; flex-direction: column; gap: .3rem; }
.field-row  { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
.field label { font-size: .78rem; font-weight: 600; color: var(--text-secondary); }
.input      { background: var(--hover); border: 1px solid var(--border); color: var(--text-primary); border-radius: 8px; padding: .5rem .75rem; font-size: .875rem; outline: none; font-family: inherit; width: 100%; }
.input:focus { border-color: var(--brand); }
.err        { font-size: .73rem; color: #ef4444; }
.hint       { font-size: .73rem; color: var(--text-muted); margin-top: -.3rem; }
.opt        { font-weight: 400; color: var(--text-muted); }
.btn-brand  { font-size: .875rem; font-weight: 600; color: #fff; background: var(--brand); border: none; border-radius: 8px; padding: .5rem 1.25rem; cursor: pointer; font-family: inherit; }
.btn-brand:disabled { opacity: .5; cursor: not-allowed; }
.btn-ghost  { font-size: .8125rem; font-weight: 500; color: var(--text-secondary); background: transparent; border: 1px solid var(--border); border-radius: 8px; padding: .5rem 1rem; cursor: pointer; font-family: inherit; }

/* ─── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .tbl        { min-width: 640px; }
    .page-head  { flex-direction: column; align-items: flex-start; }
    .btn-brand  { width: 100%; text-align: center; touch-action: manipulation; min-height: 44px; }
    .actions    { flex-wrap: wrap; gap: .25rem; }
}
@media (max-width: 640px) {
    .overlay    { align-items: flex-end; padding: 0; }
    .modal      { max-width: 100%; border-radius: 16px 16px 0 0; max-height: 90dvh; overflow: hidden; display: flex; flex-direction: column; }
    .modal-body { overflow-y: auto; max-height: none; flex: 1; }
    .field-row  { grid-template-columns: 1fr; }
    .input      { font-size: 1rem; min-height: 44px; touch-action: manipulation; }
    .day-btn    { width: 2.5rem; height: 2.5rem; touch-action: manipulation; }
    .btn-act    { min-height: 36px; touch-action: manipulation; }
}
</style>
