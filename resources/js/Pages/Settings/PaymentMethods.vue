<script setup>
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'

const props = defineProps({
    methods: Array,
})

// ─── Types ────────────────────────────────────────────────────────────────────
const TYPE_LABELS = {
    cash:       'Efectivo',
    transfer:   'Transferencia',
    mobile:     'Pago Móvil',
    biometric:  'BioPago',
    other:      'Otro',
}
const TYPE_COLORS = {
    cash:       'badge-green',
    transfer:   'badge-blue',
    mobile:     'badge-purple',
    biometric:  'badge-orange',
    other:      'badge-gray',
}

// ─── Bancos venezolanos ──────────────────────────────────────────────────────
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

// ─── Local list (optimistic drag) ────────────────────────────────────────────
const list = ref([...props.methods])

// ─── Modal ───────────────────────────────────────────────────────────────────
const showModal   = ref(false)
const editMethod  = ref(null)

const form = useForm({
    name:      '',
    type:      'cash',
    bank_name: '',
})

const needsBank = computed(() =>
    ['mobile', 'biometric', 'transfer'].includes(form.type)
)

function openNew() {
    editMethod.value = null
    form.reset()
    form.type = 'cash'
    showModal.value = true
}

function openEdit(method) {
    editMethod.value = method
    form.name      = method.name
    form.type      = method.type
    form.bank_name = method.bank_name ?? ''
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    form.clearErrors()
}

function submitForm() {
    const payload = {
        name:      form.name,
        type:      form.type,
        bank_name: needsBank.value ? form.bank_name : null,
    }

    if (editMethod.value) {
        form.transform(() => payload).put(
            route('payment-methods.update', editMethod.value.id),
            { onSuccess: () => { closeModal(); list.value = [...props.methods] } }
        )
    } else {
        form.transform(() => payload).post(
            route('payment-methods.store'),
            { onSuccess: () => { closeModal(); list.value = [...props.methods] } }
        )
    }
}

// ─── Toggle activo ────────────────────────────────────────────────────────────
function toggleActive(method) {
    router.patch(route('payment-methods.toggle', method.id), {}, {
        onSuccess: () => { list.value = [...props.methods] },
        preserveScroll: true,
    })
}

// ─── Destroy ─────────────────────────────────────────────────────────────────
function destroy(method) {
    if (! confirm(`¿Eliminar "${method.name}"?`)) return
    router.delete(route('payment-methods.destroy', method.id), {
        onSuccess: () => { list.value = [...props.methods] },
        preserveScroll: true,
    })
}

// ─── Drag & drop reorder ──────────────────────────────────────────────────────
const dragging = ref(null)

function onDragStart(index) {
    dragging.value = index
}

function onDragOver(index) {
    if (dragging.value === null || dragging.value === index) return
    const moved = [...list.value]
    const [item] = moved.splice(dragging.value, 1)
    moved.splice(index, 0, item)
    list.value   = moved
    dragging.value = index
}

function onDragEnd() {
    dragging.value = null
    router.post(route('payment-methods.reorder'), {
        order: list.value.map(m => m.id),
    }, { preserveScroll: true })
}
</script>

<template>
    <SettingsLayout>
        <div class="pm-wrap">

            <!-- Header -->
            <div class="pm-header">
                <div>
                    <h1 class="pm-title">Métodos de Pago</h1>
                    <p class="pm-sub">Arrastra para reordenar · se aplican en el POS</p>
                </div>
                <button class="btn btn-primary" @click="openNew">+ Nuevo método</button>
            </div>

            <!-- List -->
            <div class="pm-list">
                <div
                    v-for="(method, i) in list"
                    :key="method.id"
                    class="pm-row"
                    :class="{ 'pm-row--inactive': !method.is_active, 'pm-row--dragging': dragging === i }"
                    draggable="true"
                    @dragstart="onDragStart(i)"
                    @dragover.prevent="onDragOver(i)"
                    @dragend="onDragEnd"
                >
                    <!-- Drag handle -->
                    <span class="drag-handle" title="Arrastra para reordenar">⠿</span>

                    <!-- Badge tipo -->
                    <span class="badge" :class="TYPE_COLORS[method.type]">
                        {{ TYPE_LABELS[method.type] }}
                    </span>

                    <!-- Nombre + banco -->
                    <div class="pm-info">
                        <span class="pm-name">{{ method.name }}</span>
                        <span v-if="method.bank_name" class="pm-bank">{{ method.bank_name }}</span>
                    </div>

                    <!-- Acciones -->
                    <div class="pm-actions">
                        <!-- Toggle activo -->
                        <button
                            class="toggle-btn"
                            :class="method.is_active ? 'toggle-on' : 'toggle-off'"
                            :title="method.is_active ? 'Desactivar' : 'Activar'"
                            @click="toggleActive(method)"
                        >
                            <span class="toggle-knob" />
                        </button>
                        <button class="btn btn-sm btn-ghost" @click="openEdit(method)">Editar</button>
                        <button class="btn btn-sm btn-danger" @click="destroy(method)">✕</button>
                    </div>
                </div>

                <p v-if="list.length === 0" class="pm-empty">
                    Sin métodos de pago. Crea uno con el botón de arriba.
                </p>
            </div>

        </div>

        <!-- Modal crear / editar -->
        <Teleport to="body">
            <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
                <div class="modal-box">
                    <h2 class="modal-title">
                        {{ editMethod ? 'Editar método' : 'Nuevo método de pago' }}
                    </h2>

                    <form @submit.prevent="submitForm" class="modal-form">

                        <label class="field-label">Nombre</label>
                        <input
                            v-model="form.name"
                            class="field-input"
                            :class="{ 'field-error': form.errors.name }"
                            maxlength="80"
                            placeholder="Ej. Pago Móvil BDV"
                        />
                        <span v-if="form.errors.name" class="error-msg">{{ form.errors.name }}</span>

                        <label class="field-label">Tipo</label>
                        <select v-model="form.type" class="field-input">
                            <option value="cash">Efectivo</option>
                            <option value="transfer">Transferencia</option>
                            <option value="mobile">Pago Móvil</option>
                            <option value="biometric">BioPago</option>
                            <option value="other">Otro</option>
                        </select>

                        <template v-if="needsBank">
                            <label class="field-label">Banco</label>
                            <select v-model="form.bank_name" class="field-input" :class="{ 'field-error': form.errors.bank_name }">
                                <option value="" disabled>Selecciona un banco</option>
                                <option v-for="b in BANKS" :key="b.code" :value="b.name">{{ b.name }} ({{ b.code }})</option>
                            </select>
                            <span v-if="form.errors.bank_name" class="error-msg">{{ form.errors.bank_name }}</span>
                        </template>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" @click="closeModal">Cancelar</button>
                            <button type="submit" class="btn btn-primary" :disabled="form.processing">
                                {{ editMethod ? 'Guardar cambios' : 'Crear método' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </SettingsLayout>
</template>

<style scoped>
/* ─── Layout ─────────────────────────────────────────────────────────────── */
.pm-wrap {
    max-width: 680px;
    margin: 0 auto;
    padding: 2rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.pm-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.pm-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}
.pm-sub {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin: 0.2rem 0 0;
}

/* ─── List ───────────────────────────────────────────────────────────────── */
.pm-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.pm-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 0.75rem;
    padding: 0.75rem 1rem;
    transition: opacity 0.15s;
    cursor: default;
}
.pm-row--inactive {
    opacity: 0.5;
}
.pm-row--dragging {
    opacity: 0.6;
    box-shadow: 0 4px 16px color-mix(in srgb, var(--brand) 25%, transparent);
}
.drag-handle {
    font-size: 1.2rem;
    color: var(--text-muted);
    cursor: grab;
    user-select: none;
    line-height: 1;
}
.drag-handle:active { cursor: grabbing; }

.pm-info {
    display: flex;
    flex-direction: column;
    flex: 1;
}
.pm-name {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.9rem;
}
.pm-bank {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.pm-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.pm-empty {
    text-align: center;
    color: var(--text-muted);
    padding: 2rem 0;
}

/* ─── Badges ─────────────────────────────────────────────────────────────── */
.badge {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.2rem 0.55rem;
    border-radius: 999px;
    white-space: nowrap;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.badge-green  { background: color-mix(in srgb, #22c55e 18%, transparent); color: #16a34a; }
.badge-blue   { background: color-mix(in srgb, #3b82f6 18%, transparent); color: #2563eb; }
.badge-purple { background: color-mix(in srgb, #a855f7 18%, transparent); color: #9333ea; }
.badge-orange { background: color-mix(in srgb, #f97316 18%, transparent); color: #ea580c; }
.badge-gray   { background: color-mix(in srgb, #6b7280 18%, transparent); color: #4b5563; }

/* ─── Toggle ─────────────────────────────────────────────────────────────── */
.toggle-btn {
    position: relative;
    width: 2.25rem;
    height: 1.25rem;
    border-radius: 999px;
    border: none;
    cursor: pointer;
    transition: background 0.2s;
    flex-shrink: 0;
}
.toggle-on  { background: var(--brand); }
.toggle-off { background: var(--border); }
.toggle-knob {
    position: absolute;
    top: 0.15rem;
    width: 0.95rem;
    height: 0.95rem;
    border-radius: 50%;
    background: #fff;
    transition: left 0.2s;
}
.toggle-on  .toggle-knob { left: calc(100% - 1.1rem); }
.toggle-off .toggle-knob { left: 0.15rem; }

/* ─── Buttons ─────────────────────────────────────────────────────────────── */
.btn {
    padding: 0.45rem 1rem;
    border-radius: 0.5rem;
    border: 1px solid transparent;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: opacity 0.15s, background 0.15s;
    white-space: nowrap;
}
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary {
    background: var(--brand);
    color: #fff;
    border-color: var(--brand);
}
.btn-ghost {
    background: transparent;
    color: var(--text-primary);
    border-color: var(--border);
}
.btn-danger {
    background: transparent;
    color: #ef4444;
    border-color: #ef4444;
}
.btn-sm { padding: 0.3rem 0.65rem; font-size: 0.8rem; }

/* ─── Modal ──────────────────────────────────────────────────────────────── */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: color-mix(in srgb, #000 60%, transparent);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
}
.modal-box {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 1rem;
    padding: 1.5rem;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 8px 32px color-mix(in srgb, #000 40%, transparent);
}
.modal-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 1.25rem;
}
.modal-form { display: flex; flex-direction: column; gap: 0.5rem; }
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--border);
}

/* ─── Fields ─────────────────────────────────────────────────────────────── */
.field-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
    margin-top: 0.25rem;
}
.field-input {
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    color: var(--text-primary);
    font-size: 0.9rem;
    width: 100%;
    box-sizing: border-box;
    outline: none;
    transition: border-color 0.15s;
}
.field-input:focus  { border-color: var(--brand); }
.field-error        { border-color: #ef4444; }
.error-msg {
    font-size: 0.75rem;
    color: #ef4444;
    margin-top: -0.25rem;
}
</style>
