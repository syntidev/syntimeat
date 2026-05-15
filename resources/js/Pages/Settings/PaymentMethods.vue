<script setup>
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'

const props = defineProps({
    methods:   Array,
    terminals: Array,
})

// ─── Constantes ───────────────────────────────────────────────────────────────
const TYPE_LABELS = {
    cash:      'Efectivo',
    transfer:  'Transferencia',
    mobile:    'Pago Móvil',
    biometric: 'BioPago',
    other:     'Otro',
}
const TYPE_COLORS = {
    cash:      'badge-green',
    transfer:  'badge-blue',
    mobile:    'badge-purple',
    biometric: 'badge-orange',
    other:     'badge-gray',
}
const TERMINAL_LABELS = {
    pos_debit:  'Débito',
    pos_credit: 'Crédito',
    biopago:    'BioPago',
}
const BANKS = [
    { code: '0102', name: 'Banco de Venezuela' },
    { code: '0163', name: 'Banco del Tesoro' },
    { code: '0175', name: 'Banco Digital de los Trabajadores' },
    { code: '0177', name: 'BANFANB' },
    { code: '0166', name: 'Banco Agrícola de Venezuela' },
    { code: '0134', name: 'Banesco' },
    { code: '0105', name: 'Mercantil' },
    { code: '0108', name: 'BBVA Provincial' },
    { code: '0172', name: 'Bancamiga' },
    { code: '0191', name: 'BNC' },
    { code: '0114', name: 'Bancaribe' },
    { code: '0174', name: 'Banplus' },
    { code: '0138', name: 'Banco Plaza' },
    { code: '0115', name: 'Banco Exterior' },
    { code: '0151', name: 'BFC' },
    { code: '0104', name: 'Venezolano de Crédito' },
    { code: '0156', name: '100% Banco' },
    { code: '0171', name: 'Banco Activo' },
    { code: '0128', name: 'Banco Caroní' },
    { code: '0157', name: 'Delsur' },
    { code: '0137', name: 'Sofitasa' },
    { code: '0173', name: 'Banco Int. de Desarrollo' },
    { code: '0178', name: 'N58 Banco Digital' },
    { code: '0168', name: 'Bancrecer' },
    { code: '0169', name: 'R4, Banco Microfinanciero' },
    { code: '0146', name: 'Bangente' },
    { code: '0601', name: 'IMCP' },
]
const TERMINAL_METHODS = [
    { value: 'pos_debit',  label: 'Débito' },
    { value: 'pos_credit', label: 'Crédito' },
    { value: 'biopago',    label: 'BioPago' },
]

// ─── Métodos de pago ──────────────────────────────────────────────────────────
const list = ref([...props.methods])

const showMethodModal = ref(false)
const editMethod      = ref(null)

const methodForm = useForm({ name: '', type: 'cash', bank_name: '' })
const needsBank  = computed(() => ['mobile', 'biometric', 'transfer'].includes(methodForm.type))

function openNewMethod() {
    editMethod.value = null
    methodForm.reset()
    methodForm.type  = 'cash'
    showMethodModal.value = true
}
function openEditMethod(m) {
    editMethod.value      = m
    methodForm.name       = m.name
    methodForm.type       = m.type
    methodForm.bank_name  = m.bank_name ?? ''
    showMethodModal.value = true
}
function closeMethodModal() {
    showMethodModal.value = false
    methodForm.clearErrors()
}
function submitMethod() {
    const payload = { name: methodForm.name, type: methodForm.type, bank_name: needsBank.value ? methodForm.bank_name : null }
    if (editMethod.value) {
        methodForm.transform(() => payload).put(
            route('payment-methods.update', editMethod.value.id),
            { onSuccess: () => { closeMethodModal(); list.value = [...props.methods] } }
        )
    } else {
        methodForm.transform(() => payload).post(
            route('payment-methods.store'),
            { onSuccess: () => { closeMethodModal(); list.value = [...props.methods] } }
        )
    }
}
function toggleMethod(m) {
    router.patch(route('payment-methods.toggle', m.id), {}, {
        onSuccess: () => { list.value = [...props.methods] },
        preserveScroll: true,
    })
}
function destroyMethod(m) {
    if (!confirm(`¿Eliminar "${m.name}"?`)) return
    router.delete(route('payment-methods.destroy', m.id), {
        onSuccess: () => { list.value = [...props.methods] },
        preserveScroll: true,
    })
}

// ─── Drag & drop ──────────────────────────────────────────────────────────────
const dragging = ref(null)
function onDragStart(i)  { dragging.value = i }
function onDragOver(i)   {
    if (dragging.value === null || dragging.value === i) return
    const moved = [...list.value]
    const [item] = moved.splice(dragging.value, 1)
    moved.splice(i, 0, item)
    list.value     = moved
    dragging.value = i
}
function onDragEnd() {
    dragging.value = null
    router.post(route('payment-methods.reorder'), { order: list.value.map(m => m.id) }, { preserveScroll: true })
}

// ─── Terminales / Dispositivos ────────────────────────────────────────────────
const showTerminalModal = ref(false)
const editTerminal      = ref(null)

const terminalForm = useForm({
    method:            'pos_debit',
    bank_name:         '',
    serial:            '',
    commercial_number: '',
    is_active:         true,
})

function openNewTerminal() {
    editTerminal.value = null
    terminalForm.reset()
    terminalForm.method    = 'pos_debit'
    terminalForm.is_active = true
    showTerminalModal.value = true
}
function openEditTerminal(t) {
    editTerminal.value              = t
    terminalForm.method             = t.method
    terminalForm.bank_name          = t.bank_name          ?? ''
    terminalForm.serial             = t.serial             ?? ''
    terminalForm.commercial_number  = t.commercial_number  ?? ''
    terminalForm.is_active          = t.is_active
    showTerminalModal.value = true
}
function closeTerminalModal() {
    showTerminalModal.value = false
    terminalForm.clearErrors()
}
function submitTerminal() {
    if (editTerminal.value) {
        terminalForm.put(route('settings.terminals.update', editTerminal.value.id), { onSuccess: closeTerminalModal })
    } else {
        terminalForm.post(route('settings.terminals.store'), { onSuccess: closeTerminalModal })
    }
}
function destroyTerminal(t) {
    if (!confirm(`¿Eliminar el dispositivo "${t.bank_name} – ${TERMINAL_LABELS[t.method] ?? t.method}"?`)) return
    router.delete(route('settings.terminals.destroy', t.id), { preserveScroll: true })
}
</script>

<template>
    <SettingsLayout>
        <div class="page">

            <!-- ── Métodos de pago ──────────────────────────────────────────── -->
            <div class="section-head">
                <div>
                    <h2 class="section-title">Cobros</h2>
                    <p class="section-sub">Métodos de pago y dispositivos aceptados en el POS</p>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <span class="card-label">Métodos de pago</span>
                    <p class="card-hint">Arrastra para reordenar · se muestran en el POS al cobrar</p>
                    <button class="btn-brand" @click="openNewMethod">+ Nuevo método</button>
                </div>

                <div class="method-list">
                    <div
                        v-for="(m, i) in list" :key="m.id"
                        class="method-row"
                        :class="{ 'row--dim': !m.is_active, 'row--drag': dragging === i }"
                        draggable="true"
                        @dragstart="onDragStart(i)"
                        @dragover.prevent="onDragOver(i)"
                        @dragend="onDragEnd"
                    >
                        <span class="drag-handle" title="Arrastra para reordenar">⠿</span>
                        <span class="badge" :class="TYPE_COLORS[m.type]">{{ TYPE_LABELS[m.type] }}</span>
                        <div class="method-info">
                            <span class="method-name">{{ m.name }}</span>
                            <span v-if="m.bank_name" class="method-bank">{{ m.bank_name }}</span>
                        </div>
                        <div class="row-actions">
                            <button class="toggle-btn" :class="m.is_active ? 'tog--on' : 'tog--off'" @click="toggleMethod(m)">
                                <span class="toggle-knob" />
                            </button>
                            <button class="btn-act" @click="openEditMethod(m)">Editar</button>
                            <button class="btn-act act--danger" @click="destroyMethod(m)">✕</button>
                        </div>
                    </div>
                    <p v-if="!list.length" class="empty">Sin métodos. Crea el primero.</p>
                </div>
            </div>

            <!-- ── Dispositivos ─────────────────────────────────────────────── -->
            <div class="card">
                <div class="card-head">
                    <span class="card-label">Dispositivos</span>
                    <p class="card-hint">Terminales POS, BioPago y otros equipos de cobro</p>
                    <button class="btn-brand" @click="openNewTerminal">+ Nuevo dispositivo</button>
                </div>

                <div class="terminal-list">
                    <div v-for="t in terminals" :key="t.id" class="terminal-row">
                        <div class="terminal-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div class="terminal-info">
                            <div class="terminal-top">
                                <span class="terminal-bank">{{ t.bank_name }}</span>
                                <span class="terminal-type">{{ TERMINAL_LABELS[t.method] ?? t.method }}</span>
                                <span v-if="!t.is_active" class="badge-off">Inactivo</span>
                            </div>
                            <div class="terminal-meta">
                                <span v-if="t.serial">Serial: {{ t.serial }}</span>
                                <span v-if="t.commercial_number">Comercio: {{ t.commercial_number }}</span>
                            </div>
                        </div>
                        <div class="row-actions">
                            <button class="btn-act" @click="openEditTerminal(t)">Editar</button>
                            <button class="btn-act act--danger" @click="destroyTerminal(t)">✕</button>
                        </div>
                    </div>
                    <p v-if="!terminals.length" class="empty">Sin dispositivos. Agrega tu primer terminal o BioPago.</p>
                </div>
            </div>

        </div>

        <!-- ── Modal Método ──────────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showMethodModal" class="overlay">
                <div class="modal">
                    <div class="modal-head">
                        <h3>{{ editMethod ? 'Editar método' : 'Nuevo método de pago' }}</h3>
                        <button @click="closeMethodModal">✕</button>
                    </div>
                    <form class="modal-body" @submit.prevent="submitMethod">
                        <div class="field"><label>Nombre</label>
                            <input v-model="methodForm.name" class="input" maxlength="80" placeholder="Ej. Pago Móvil BDV" required />
                            <span v-if="methodForm.errors.name" class="err">{{ methodForm.errors.name }}</span>
                        </div>
                        <div class="field"><label>Tipo</label>
                            <select v-model="methodForm.type" class="input">
                                <option value="cash">Efectivo</option>
                                <option value="transfer">Transferencia</option>
                                <option value="mobile">Pago Móvil</option>
                                <option value="biometric">BioPago</option>
                                <option value="other">Otro</option>
                            </select>
                        </div>
                        <div v-if="needsBank" class="field"><label>Banco</label>
                            <select v-model="methodForm.bank_name" class="input">
                                <option value="" disabled>Selecciona un banco</option>
                                <option v-for="b in BANKS" :key="b.code" :value="b.name">{{ b.name }} ({{ b.code }})</option>
                            </select>
                            <span v-if="methodForm.errors.bank_name" class="err">{{ methodForm.errors.bank_name }}</span>
                        </div>
                        <div class="modal-foot">
                            <button type="button" class="btn-ghost" @click="closeMethodModal">Cancelar</button>
                            <button type="submit" class="btn-brand" :disabled="methodForm.processing">
                                {{ editMethod ? 'Guardar' : 'Crear método' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ── Modal Dispositivo ─────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showTerminalModal" class="overlay">
                <div class="modal">
                    <div class="modal-head">
                        <h3>{{ editTerminal ? 'Editar dispositivo' : 'Nuevo dispositivo' }}</h3>
                        <button @click="closeTerminalModal">✕</button>
                    </div>
                    <form class="modal-body" @submit.prevent="submitTerminal">
                        <div class="field"><label>Tipo</label>
                            <div class="mode-group">
                                <label v-for="m in TERMINAL_METHODS" :key="m.value" :class="['mode-opt', terminalForm.method === m.value && 'mode-opt--on']">
                                    <input type="radio" :value="m.value" v-model="terminalForm.method" class="sr-only" />
                                    {{ m.label }}
                                </label>
                            </div>
                        </div>
                        <div class="field"><label>Banco *</label>
                            <select v-model="terminalForm.bank_name" class="input" required>
                                <option value="" disabled>Selecciona un banco</option>
                                <option v-for="b in BANKS" :key="b.code" :value="b.name">{{ b.name }} ({{ b.code }})</option>
                            </select>
                            <span v-if="terminalForm.errors.bank_name" class="err">{{ terminalForm.errors.bank_name }}</span>
                        </div>
                        <div class="field-row">
                            <div class="field"><label>Serial</label>
                                <input v-model="terminalForm.serial" type="text" class="input" placeholder="Ej: 123456" />
                            </div>
                            <div class="field"><label>Nro. comercio</label>
                                <input v-model="terminalForm.commercial_number" type="text" class="input" placeholder="Ej: 789012" />
                            </div>
                        </div>
                        <label class="toggle-row">
                            <input type="checkbox" v-model="terminalForm.is_active" class="sr-only" />
                            <div :class="['toggle-pill', terminalForm.is_active && 'pill--on']"><div class="pill-thumb" /></div>
                            <span>Dispositivo activo</span>
                        </label>
                        <div class="modal-foot">
                            <button type="button" class="btn-ghost" @click="closeTerminalModal">Cancelar</button>
                            <button type="submit" class="btn-brand" :disabled="terminalForm.processing">
                                {{ editTerminal ? 'Guardar' : 'Agregar dispositivo' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </SettingsLayout>
</template>

<style scoped>
.page         { display: flex; flex-direction: column; gap: 1.25rem; }
.section-head { display: flex; align-items: center; justify-content: space-between; }
.section-title { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); }
.section-sub   { font-size: .8rem; color: var(--text-muted); margin-top: 2px; }

.card      { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.card-head { display: flex; align-items: center; gap: .75rem; padding: .9rem 1.25rem; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
.card-label { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); flex: 1; }
.card-hint  { font-size: .75rem; color: var(--text-muted); flex: 2; }

/* ── Methods ── */
.method-list { display: flex; flex-direction: column; }
.method-row  { display: flex; align-items: center; gap: .75rem; padding: .7rem 1.25rem; border-bottom: 1px solid var(--border); transition: background .1s; }
.method-row:last-child { border-bottom: none; }
.method-row:hover { background: var(--hover); }
.row--dim  { opacity: .45; }
.row--drag { opacity: .6; box-shadow: 0 4px 16px color-mix(in srgb,var(--brand) 20%,transparent); }
.drag-handle { font-size: 1.1rem; color: var(--text-muted); cursor: grab; user-select: none; }
.drag-handle:active { cursor: grabbing; }
.method-info { display: flex; flex-direction: column; flex: 1; }
.method-name { font-weight: 600; font-size: .875rem; color: var(--text-primary); }
.method-bank { font-size: .72rem; color: var(--text-muted); }

/* ── Terminals ── */
.terminal-list { display: flex; flex-direction: column; }
.terminal-row  { display: flex; align-items: center; gap: .75rem; padding: .7rem 1.25rem; border-bottom: 1px solid var(--border); }
.terminal-row:last-child { border-bottom: none; }
.terminal-row:hover { background: var(--hover); }
.terminal-icon { width: 32px; height: 32px; border-radius: 8px; background: color-mix(in srgb,var(--brand) 12%,transparent); color: var(--brand); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.terminal-icon svg { width: 16px; height: 16px; }
.terminal-info { flex: 1; min-width: 0; }
.terminal-top  { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
.terminal-bank { font-size: .875rem; font-weight: 600; color: var(--text-primary); }
.terminal-type { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 99px; background: color-mix(in srgb,var(--brand) 12%,transparent); color: var(--brand); }
.badge-off     { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 99px; background: var(--hover); color: var(--text-muted); border: 1px solid var(--border); }
.terminal-meta { font-size: 11px; color: var(--text-muted); display: flex; gap: 1rem; margin-top: 2px; }

/* ── Badges ── */
.badge        { font-size: .7rem; font-weight: 700; padding: .2rem .55rem; border-radius: 999px; white-space: nowrap; }
.badge-green  { background: color-mix(in srgb,#22c55e 15%,transparent); color: #16a34a; }
.badge-blue   { background: color-mix(in srgb,#3b82f6 15%,transparent); color: #2563eb; }
.badge-purple { background: color-mix(in srgb,#a855f7 15%,transparent); color: #9333ea; }
.badge-orange { background: color-mix(in srgb,#f97316 15%,transparent); color: #ea580c; }
.badge-gray   { background: color-mix(in srgb,#6b7280 15%,transparent); color: #4b5563; }

/* ── Row actions ── */
.row-actions { display: flex; align-items: center; gap: .4rem; }
.btn-act     { font-size: .72rem; font-weight: 500; padding: .25rem .6rem; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-secondary); cursor: pointer; white-space: nowrap; }
.btn-act:hover { background: var(--hover); color: var(--text-primary); }
.act--danger  { color: #ef4444; border-color: color-mix(in srgb,#ef4444 30%,transparent); }
.act--danger:hover { background: color-mix(in srgb,#ef4444 10%,transparent); }

/* ── Toggle switch ── */
.toggle-btn  { position: relative; width: 2.25rem; height: 1.25rem; border-radius: 999px; border: none; cursor: pointer; transition: background .2s; flex-shrink: 0; }
.tog--on     { background: var(--brand); }
.tog--off    { background: var(--border); }
.toggle-knob { position: absolute; top: .15rem; width: .95rem; height: .95rem; border-radius: 50%; background: #fff; transition: left .2s; }
.tog--on  .toggle-knob { left: calc(100% - 1.1rem); }
.tog--off .toggle-knob { left: .15rem; }

.empty { text-align: center; color: var(--text-muted); padding: 2rem; font-size: .85rem; }

/* ── Modal ── */
.overlay    { position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 50; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.modal      { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; width: 100%; max-width: 440px; }
.modal-head { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); }
.modal-head h3 { font-size: .95rem; font-weight: 700; color: var(--text-primary); }
.modal-head button { background: none; border: none; color: var(--text-muted); font-size: 1rem; cursor: pointer; }
.modal-body { padding: 1.25rem; display: flex; flex-direction: column; gap: .85rem; max-height: 75vh; overflow-y: auto; }
.modal-foot { display: flex; justify-content: flex-end; gap: .6rem; padding-top: .5rem; }

.field       { display: flex; flex-direction: column; gap: .3rem; }
.field-row   { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
.field label { font-size: .78rem; font-weight: 600; color: var(--text-secondary); }
.input       { background: var(--hover); border: 1px solid var(--border); color: var(--text-primary); border-radius: 8px; padding: .5rem .75rem; font-size: .875rem; outline: none; font-family: inherit; width: 100%; }
.input:focus { border-color: var(--brand); }
.err         { font-size: .73rem; color: #ef4444; }

.mode-group  { display: flex; gap: .5rem; }
.mode-opt    { flex: 1; text-align: center; padding: .45rem; border: 1px solid var(--border); border-radius: 8px; font-size: .8rem; font-weight: 600; color: var(--text-secondary); cursor: pointer; transition: all .15s; }
.mode-opt--on { border-color: var(--brand); background: color-mix(in srgb,var(--brand) 10%,transparent); color: var(--brand); }

.toggle-row  { display: flex; align-items: center; gap: .6rem; cursor: pointer; font-size: .85rem; color: var(--text-secondary); }
.toggle-pill { width: 36px; height: 20px; border-radius: 10px; background: var(--border); position: relative; transition: background .2s; flex-shrink: 0; }
.pill--on    { background: var(--brand); }
.pill-thumb  { position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: transform .2s; }
.pill--on .pill-thumb { transform: translateX(16px); }

.btn-brand  { font-size: .875rem; font-weight: 600; color: #fff; background: var(--brand); border: none; border-radius: 8px; padding: .5rem 1.25rem; cursor: pointer; font-family: inherit; white-space: nowrap; }
.btn-brand:disabled { opacity: .5; cursor: not-allowed; }
.btn-ghost  { font-size: .8125rem; font-weight: 500; color: var(--text-secondary); background: transparent; border: 1px solid var(--border); border-radius: 8px; padding: .5rem 1rem; cursor: pointer; font-family: inherit; }
.sr-only    { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); }
</style>
