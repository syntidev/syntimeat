<script setup>
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import HelpModal      from '@/Components/HelpModal.vue'
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    terminals: Array,
})

const showModal    = ref(false)
const editTerminal = ref(null)

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

const METHODS = [
    { value: 'pos_debit',  label: 'Débito' },
    { value: 'pos_credit', label: 'Crédito' },
    { value: 'biopago',    label: 'BioPago' },
]

const form = useForm({
    method:            'pos_debit',
    bank_name:         '',
    serial:            '',
    commercial_number: '',
    is_active:         true,
})

const methodLabels = {
    pos_debit:  'Débito',
    pos_credit: 'Crédito',
    biopago:    'BioPago',
}

function openNew() {
    editTerminal.value = null
    form.reset()
    form.method = 'pos_debit'
    form.is_active = true
    showModal.value = true
}

function openEdit(t) {
    editTerminal.value = t
    form.method            = t.method
    form.bank_name         = t.bank_name    ?? ''
    form.serial            = t.serial       ?? ''
    form.commercial_number = t.commercial_number ?? ''
    form.is_active         = t.is_active
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    form.clearErrors()
}

function submit() {
    if (editTerminal.value) {
        form.put(route('settings.terminals.update', editTerminal.value.id), {
            onSuccess: closeModal,
        })
    } else {
        form.post(route('settings.terminals.store'), {
            onSuccess: closeModal,
        })
    }
}

function destroy(t) {
    if (!confirm(`¿Eliminar el terminal "${t.bank_name} ${methodLabels[t.method] ?? ''}"?`)) return
    useForm({}).delete(route('settings.terminals.destroy', t.id))
}

const inputClass = 'w-full bg-[var(--bg-input)] border border-[var(--border)] text-[var(--text-primary)] rounded-lg px-3.5 py-2.5 text-sm placeholder:text-[var(--text-muted)] focus:border-[var(--brand)] focus:ring-1 focus:ring-[var(--brand)] outline-none transition'
const labelClass = 'block text-sm font-medium text-[var(--text-secondary)] mb-1.5'
const errorClass = 'mt-1 text-xs text-red-400'

// ─── Ayuda ────────────────────────────────────────────────────────────────────
const showHelp = ref(false)

const helpSteps = [
    {
        title: 'Tipos de terminal',
        body:  'Registra los equipos físicos de cobro electrónico: Débito (POS tarjeta de débito), Crédito (POS tarjeta de crédito) y BioPago (dispositivo biométrico). Cada uno se asocia a un banco.',
        tip:   'Si tienes dos terminales del mismo banco (ej: un POS de respaldo), crea dos registros separados con distintos seriales.',
    },
    {
        title: 'Datos del terminal',
        body:  'Registra el banco emisor (obligatorio), el serial del dispositivo y el número de comercio que aparece en los vouchers. Estos datos te ayudan a identificar el equipo en caso de fallas.',
        tip:   'El número de comercio lo asigna el banco. Lo encuentras en los vouchers de cobro o en el contrato del servicio.',
    },
    {
        title: 'Activar o desactivar',
        body:  'Un terminal inactivo no aparece como opción al cobrar en el POS pero conserva su historial. Úsalo cuando el equipo está en reparación o de baja temporal.',
        tip:   'No elimines un terminal con historial — márcalo como inactivo para preservar la trazabilidad de las ventas anteriores.',
    },
]

const helpFaqs = [
    {
        q: '¿Para qué sirve el número de comercio?',
        a: 'Es el identificador que el banco usa para procesar los cobros. Aparece en los vouchers de cada transacción. Es útil para conciliar pagos con el estado de cuenta bancario.',
    },
    {
        q: '¿Puedo tener terminales de varios bancos?',
        a: 'Sí, sin límite. Puedes registrar todos los equipos que uses. Al cobrar en el POS, la cajera selecciona cuál terminal procesó el pago.',
    },
    {
        q: '¿Qué diferencia hay entre BioPago y débito/crédito?',
        a: 'BioPago es un sistema biométrico venezolano que verifica la identidad por huella dactilar conectada a la cuenta bancaria. Es un tipo de cobro distinto a los POS tradicionales.',
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
                        <h2 class="settings-panel__title">Terminales POS</h2>
                        <p class="settings-panel__subtitle">Configura tus terminales de débito, crédito y BioPago</p>
                    </div>
                    <div class="header-actions">
                        <button class="help-btn" @click="showHelp = true" title="Ayuda">?</button>
                        <button class="btn-primary" @click="openNew">+ Agregar terminal</button>
                    </div>
                </div>
            </div>

            <!-- Lista -->
            <div class="panel-body">
                <div v-if="terminals.length" class="terminal-list">
                    <div v-for="t in terminals" :key="t.id" class="terminal-card">
                        <div class="terminal-card__icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div class="terminal-card__info">
                            <div class="terminal-card__top">
                                <span class="terminal-card__bank">{{ t.bank_name }}</span>
                                <span class="terminal-card__type">{{ methodLabels[t.method] ?? t.method }}</span>
                                <span v-if="!t.is_active" class="badge-inactive">Inactivo</span>
                            </div>
                            <div class="terminal-card__meta">
                                <span v-if="t.serial">Serial: {{ t.serial }}</span>
                                <span v-if="t.commercial_number">Nro. Comercio: {{ t.commercial_number }}</span>
                            </div>
                        </div>
                        <div class="terminal-card__actions">
                            <button class="icon-action" @click="openEdit(t)" title="Editar">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <button class="icon-action icon-action--danger" @click="destroy(t)" title="Eliminar">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else class="empty-state">
                    <p>No hay terminales configurados. Agrega tu primer terminal.</p>
                    <p class="mt-1 text-xs opacity-60">Puedes tener múltiples terminales de distintos bancos.</p>
                </div>
            </div>
        </div>

        <!-- ── Modal ─────────────────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showModal" class="modal-overlay">
                <div class="modal">
                    <div class="modal__header">
                        <h3>{{ editTerminal ? 'Editar terminal' : 'Nuevo terminal POS' }}</h3>
                        <button class="modal__close" @click="closeModal">✕</button>
                    </div>
                    <form @submit.prevent="submit" class="modal__body">

                        <!-- Tipo -->
                        <div>
                            <label :class="labelClass">Tipo <span class="text-[var(--brand)]">*</span></label>
                            <div class="mode-group">
                                <label
                                    v-for="m in METHODS"
                                    :key="m.value"
                                    :class="['mode-option', { selected: form.method === m.value }]"
                                >
                                    <input type="radio" :value="m.value" v-model="form.method" class="sr-only" />
                                    {{ m.label }}
                                </label>
                            </div>
                            <p v-if="form.errors.method" :class="errorClass">{{ form.errors.method }}</p>
                        </div>

                        <!-- Banco -->
                        <div>
                            <label :class="labelClass">Banco <span class="text-[var(--brand)]">*</span></label>
                            <select v-model="form.bank_name" :class="inputClass">
                                <option value="" disabled>Selecciona un banco</option>
                                <option v-for="b in BANKS" :key="b.code" :value="b.name">{{ b.name }} ({{ b.code }})</option>
                            </select>
                            <p v-if="form.errors.bank_name" :class="errorClass">{{ form.errors.bank_name }}</p>
                        </div>

                        <!-- Serial y Nro Comercio -->
                        <div class="grid-2">
                            <div>
                                <label :class="labelClass">Serial del terminal</label>
                                <input v-model="form.serial" type="text" :class="inputClass" placeholder="Ej: 123456" />
                                <p v-if="form.errors.serial" :class="errorClass">{{ form.errors.serial }}</p>
                            </div>
                            <div>
                                <label :class="labelClass">Nro. de comercio</label>
                                <input v-model="form.commercial_number" type="text" :class="inputClass" placeholder="Ej: 789012" />
                                <p v-if="form.errors.commercial_number" :class="errorClass">{{ form.errors.commercial_number }}</p>
                            </div>
                        </div>

                        <!-- Activo -->
                        <label class="toggle-row">
                            <input type="checkbox" v-model="form.is_active" class="sr-only" />
                            <div :class="['toggle', { 'toggle--on': form.is_active }]">
                                <div class="toggle__thumb" />
                            </div>
                            <span class="text-sm text-[var(--text-secondary)]">Terminal activo</span>
                        </label>

                        <div class="modal__footer">
                            <button type="button" class="btn-secondary" @click="closeModal">Cancelar</button>
                            <button type="submit" class="btn-primary" :disabled="form.processing">
                                {{ editTerminal ? 'Guardar cambios' : 'Crear terminal' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Panel de ayuda ───────────────────────────────────────────────── -->
        <HelpModal
            :show="showHelp"
            title="Terminales POS — Cómo funciona"
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
    width: 44px; height: 44px;
    padding: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.875rem; font-weight: 700;
    border: 1.5px solid var(--border);
    background: none; color: var(--text-muted);
    cursor: pointer; flex-shrink: 0;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.help-btn:hover { background: var(--brand); color: #fff; border-color: var(--brand); }
.settings-panel__title { font-size: 1rem; font-weight: 700; color: var(--text-primary); }
.settings-panel__subtitle { font-size: 0.8rem; color: var(--text-muted); margin-top: 2px; }
.panel-body { padding: 1.25rem 1.5rem; }

.terminal-list { display: flex; flex-direction: column; gap: 0.625rem; }
.terminal-card { display: flex; align-items: center; gap: 1rem; padding: 0.875rem 1rem; background: var(--bg-input); border: 1px solid var(--border); border-radius: 10px; }
.terminal-card__icon { width: 36px; height: 36px; border-radius: 8px; background: color-mix(in srgb, var(--brand) 12%, transparent); color: var(--brand); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.terminal-card__icon svg { width: 18px; height: 18px; }
.terminal-card__info { flex: 1; min-width: 0; }
.terminal-card__top { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.terminal-card__bank { font-size: 0.9rem; font-weight: 600; color: var(--text-primary); }
.terminal-card__type { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 99px; background: color-mix(in srgb, var(--brand) 12%, transparent); color: var(--brand); }
.badge-inactive { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 99px; background: var(--bg-card); color: var(--text-muted); border: 1px solid var(--border); }
.terminal-card__meta { font-size: 11px; color: var(--text-muted); display: flex; gap: 1rem; margin-top: 3px; }
.terminal-card__actions { display: flex; gap: 0.375rem; }

.icon-action { width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; border-radius: 6px; border: none; background: transparent; color: var(--text-muted); cursor: pointer; transition: background 0.15s, color 0.15s; }
.icon-action svg { width: 15px; height: 15px; }
.icon-action:hover { background: var(--hover); color: var(--text-primary); }
.icon-action--danger:hover { background: #3f0d0d; color: #f87171; }
.empty-state { padding: 2rem; text-align: center; color: var(--text-muted); font-size: 0.875rem; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(2px); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 1rem; }
.modal { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; width: 100%; max-width: 440px; overflow: hidden; }
.modal__header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); }
.modal__header h3 { font-size: 0.9375rem; font-weight: 700; color: var(--text-primary); }
.modal__close { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1rem; padding: 0.25rem; min-width: 44px; min-height: 44px; display: flex; align-items: center; justify-content: center; }
.modal__body { padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
.modal__footer { display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 0.25rem; }

.mode-group { display: flex; gap: 0.5rem; }
.mode-option { flex: 1; text-align: center; padding: 0.5rem; min-height: 44px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; font-weight: 600; color: var(--text-secondary); cursor: pointer; transition: all 0.15s; }
.mode-option.selected { border-color: var(--brand); background: color-mix(in srgb, var(--brand) 10%, transparent); color: var(--brand); }

.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }

.toggle-row { display: flex; align-items: center; gap: 0.625rem; cursor: pointer; }
.toggle { width: 36px; height: 20px; border-radius: 10px; background: var(--border); position: relative; transition: background 0.2s; flex-shrink: 0; }
.toggle--on { background: var(--brand); }
.toggle__thumb { position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: transform 0.2s; }
.toggle--on .toggle__thumb { transform: translateX(16px); }

.btn-primary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1.25rem; background: var(--brand); color: #fff; font-size: 0.875rem; font-weight: 600; border-radius: 8px; border: none; cursor: pointer; transition: opacity 0.15s; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary:hover:not(:disabled) { opacity: 0.9; }
.btn-secondary { display: inline-flex; align-items: center; padding: 0.5rem 1rem; background: var(--bg-input); border: 1px solid var(--border); color: var(--text-secondary); font-size: 0.875rem; font-weight: 500; border-radius: 8px; cursor: pointer; }
</style>
