<script setup>
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    registers: Array,
})

const showModal    = ref(false)
const editRegister = ref(null)

const form = useForm({ name: '' })

function openNew() {
    editRegister.value = null
    form.reset()
    showModal.value = true
}

function openEdit(reg) {
    editRegister.value = reg
    form.name = reg.name
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    form.clearErrors()
}

function submit() {
    if (editRegister.value) {
        form.put(route('settings.cash-registers.update', editRegister.value.id), {
            onSuccess: closeModal,
        })
    } else {
        form.post(route('settings.cash-registers.store'), {
            onSuccess: closeModal,
        })
    }
}

function destroy(reg) {
    if (!confirm(`¿Eliminar la caja "${reg.name}"?`)) return
    useForm({}).delete(route('settings.cash-registers.destroy', reg.id))
}

const inputClass = 'w-full bg-[var(--bg-input)] border border-[var(--border)] text-[var(--text-primary)] rounded-lg px-3.5 py-2.5 text-sm placeholder:text-[var(--text-muted)] focus:border-[var(--brand)] focus:ring-1 focus:ring-[var(--brand)] outline-none transition'
const labelClass = 'block text-xs font-medium text-[var(--text-secondary)] mb-1.5'
const errorClass = 'mt-1 text-xs text-red-400'
</script>

<template>
    <SettingsLayout>
        <div class="settings-panel">

            <!-- Header -->
            <div class="settings-panel__header">
                <div class="header-row">
                    <div>
                        <h2 class="settings-panel__title">Cajas Registradoras</h2>
                        <p class="settings-panel__subtitle">Configura los puntos de venta físicos de tu negocio</p>
                    </div>
                    <button class="btn-primary" @click="openNew">+ Agregar caja</button>
                </div>
            </div>

            <!-- Lista -->
            <div class="panel-body">
                <div class="register-list" v-if="registers.length">
                    <div v-for="reg in registers" :key="reg.id" class="register-card">
                        <div class="register-card__icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div class="register-card__info">
                            <span class="register-card__name">{{ reg.name }}</span>
                            <span :class="['register-card__status', reg.opened_at && !reg.closed_at ? 'status-open' : 'status-closed']">
                                {{ reg.opened_at && !reg.closed_at ? 'Abierta' : 'Cerrada' }}
                            </span>
                        </div>
                        <div class="register-card__actions">
                            <button class="icon-action" @click="openEdit(reg)" title="Editar">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            <button class="icon-action icon-action--danger" @click="destroy(reg)" title="Eliminar">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div v-else class="empty-state">
                    <p>No hay cajas configuradas. Agrega tu primera caja.</p>
                </div>
            </div>
        </div>

        <!-- ── Modal ─────────────────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
                <div class="modal">
                    <div class="modal__header">
                        <h3>{{ editRegister ? 'Editar caja' : 'Nueva caja registradora' }}</h3>
                        <button class="modal__close" @click="closeModal">✕</button>
                    </div>
                    <form @submit.prevent="submit" class="modal__body">
                        <div>
                            <label :class="labelClass">Nombre de la caja <span class="text-[var(--brand)]">*</span></label>
                            <input v-model="form.name" type="text" :class="inputClass" placeholder="Ej: Caja Principal" autofocus />
                            <p v-if="form.errors.name" :class="errorClass">{{ form.errors.name }}</p>
                        </div>
                        <div class="modal__footer">
                            <button type="button" class="btn-secondary" @click="closeModal">Cancelar</button>
                            <button type="submit" class="btn-primary" :disabled="form.processing">
                                {{ editRegister ? 'Guardar cambios' : 'Crear caja' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </SettingsLayout>
</template>

<style scoped>
.settings-panel { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.settings-panel__header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); }
.header-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.settings-panel__title { font-size: 1rem; font-weight: 700; color: var(--text-primary); }
.settings-panel__subtitle { font-size: 0.8rem; color: var(--text-muted); margin-top: 2px; }
.panel-body { padding: 1.25rem 1.5rem; }

.register-list { display: flex; flex-direction: column; gap: 0.625rem; }
.register-card { display: flex; align-items: center; gap: 1rem; padding: 0.875rem 1rem; background: var(--bg-input); border: 1px solid var(--border); border-radius: 10px; }
.register-card__icon { width: 36px; height: 36px; border-radius: 8px; background: color-mix(in srgb, var(--brand) 12%, transparent); color: var(--brand); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.register-card__icon svg { width: 18px; height: 18px; }
.register-card__info { flex: 1; display: flex; align-items: center; gap: 0.75rem; }
.register-card__name { font-size: 0.9rem; font-weight: 600; color: var(--text-primary); }
.register-card__status { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 99px; }
.status-open  { background: #14532d; color: #4ade80; }
.status-closed { background: var(--bg-input); color: var(--text-muted); border: 1px solid var(--border); }
.register-card__actions { display: flex; gap: 0.375rem; }

.icon-action { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 6px; border: none; background: transparent; color: var(--text-muted); cursor: pointer; transition: background 0.15s, color 0.15s; }
.icon-action svg { width: 15px; height: 15px; }
.icon-action:hover { background: var(--hover); color: var(--text-primary); }
.icon-action--danger:hover { background: #3f0d0d; color: #f87171; }
.empty-state { padding: 2rem; text-align: center; color: var(--text-muted); font-size: 0.875rem; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(2px); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 1rem; }
.modal { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; width: 100%; max-width: 380px; overflow: hidden; }
.modal__header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); }
.modal__header h3 { font-size: 0.9375rem; font-weight: 700; color: var(--text-primary); }
.modal__close { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1rem; padding: 0.25rem; }
.modal__body { padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
.modal__footer { display: flex; justify-content: flex-end; gap: 0.75rem; padding-top: 0.25rem; }

.btn-primary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1.25rem; background: var(--brand); color: #fff; font-size: 0.875rem; font-weight: 600; border-radius: 8px; border: none; cursor: pointer; transition: opacity 0.15s; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-primary:hover:not(:disabled) { opacity: 0.9; }
.btn-secondary { display: inline-flex; align-items: center; padding: 0.5rem 1rem; background: var(--bg-input); border: 1px solid var(--border); color: var(--text-secondary); font-size: 0.875rem; font-weight: 500; border-radius: 8px; cursor: pointer; }
</style>
